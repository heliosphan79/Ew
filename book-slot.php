<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');

function booking_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    booking_fail('Ongeldige methode.', 405);
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    booking_fail('Ongeldige of verlopen aanvraag. Herlaad de pagina en probeer opnieuw.');
}

// Honeypot, same pattern as the contact form.
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]);
    exit;
}

$date = (string) ($_POST['date'] ?? '');
$time = (string) ($_POST['time'] ?? '');
$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$message = trim(normalize_newlines((string) ($_POST['message'] ?? '')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
    booking_fail('Ongeldige datum.');
}
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
    booking_fail('Ongeldig tijdstip.');
}
if ($name === '' || mb_strlen($name) > 150) {
    booking_fail('Vul een geldige naam in.');
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    booking_fail('Vul een geldig e-mailadres in.');
}
if (mb_strlen($message) > 1000) {
    $message = mb_substr($message, 0, 1000);
}

// Atomic: only succeeds if the slot is still 'available' at the moment of
// the UPDATE, so two visitors can never both book the same moment.
$timeWithSeconds = $time . ':00';
$stmt = $mysqli->prepare(
    "UPDATE calendar_slots
     SET status = 'booked', booked_name = ?, booked_email = ?, booked_message = ?, booked_at = NOW()
     WHERE slot_date = ? AND slot_time = ? AND status = 'available'"
);
$stmt->bind_param('sssss', $name, $email, $message, $date, $timeWithSeconds);
$stmt->execute();
$booked = $stmt->affected_rows === 1;
$stmt->close();

if (!$booked) {
    booking_fail('Dit moment is net ingenomen. Kies een ander moment.', 409);
}

echo json_encode(['ok' => true]);
