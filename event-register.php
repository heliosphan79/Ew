<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

csrf_verify();

$redirectTarget = (string) ($_POST['redirect'] ?? '/');
if (!is_safe_redirect_path($redirectTarget)) {
    $redirectTarget = '/';
}

// Honeypot: pretend success without actually registering.
if (!empty($_POST['website'])) {
    redirect($redirectTarget);
}

$eventId = (int) ($_POST['event_id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

if ($eventId <= 0) {
    redirect($redirectTarget);
}
if ($name === '' || mb_strlen($name) > 150) {
    set_flash('error', 'Vul een geldige naam in.');
    redirect($redirectTarget);
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    set_flash('error', 'Vul een geldig e-mailadres in.');
    redirect($redirectTarget);
}

$mysqli->begin_transaction();
try {
    // Row-lock the event for the duration of the transaction so two
    // concurrent registrations can never both squeeze into the last spot.
    $stmt = $mysqli->prepare(
        'SELECT title, capacity, published, registration_deadline,
            (SELECT COUNT(*) FROM event_sessions s WHERE s.event_id = events.id AND s.session_date >= CURDATE()) AS upcoming_session_count
         FROM events WHERE id = ? FOR UPDATE'
    );
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event || !$event['published'] || (int) $event['upcoming_session_count'] === 0
        || !is_event_registration_open($event['registration_deadline'])) {
        $mysqli->rollback();
        set_flash('error', 'Dit evenement is niet (meer) beschikbaar.');
        redirect($redirectTarget);
    }

    if ($event['capacity'] !== null) {
        $stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM event_registrations WHERE event_id = ?');
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $registered = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        if ($registered >= (int) $event['capacity']) {
            $mysqli->rollback();
            set_flash('error', 'Dit evenement is helaas net volzet geraakt.');
            redirect($redirectTarget);
        }
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $notifyNextSeries = !empty($_POST['notify_next_series']) ? 1 : 0;
    $stmt = $mysqli->prepare(
        'INSERT INTO event_registrations (event_id, name, email, notify_next_series, ip_address) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issis', $eventId, $name, $email, $notifyNextSeries, $ip);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();

    if (!empty($_POST['newsletter_optin'])) {
        newsletter_subscribe($mysqli, $email, 'event_registration', extract_first_name($name));
    }
} catch (mysqli_sql_exception $e) {
    $mysqli->rollback();
    throw $e;
}

set_flash('success', 'Je interesse voor "' . $event['title'] . '" is doorgegeven!');
redirect($redirectTarget);
