<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_GET['t'] ?? '');
$target = (string) ($_GET['u'] ?? '');

// Only ever redirect to a well-formed http(s) URL — build_newsletter_email()
// only ever puts such URLs here itself, but never trust a query string
// blindly: this would otherwise be an open redirect.
if ($target === '' || !filter_var($target, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $target)) {
    redirect('/');
}

if ($token !== '') {
    $stmt = $mysqli->prepare('SELECT id FROM newsletter_sends WHERE send_token = ?');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $send = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($send) {
        $stmt = $mysqli->prepare('INSERT INTO newsletter_clicks (send_id, url) VALUES (?, ?)');
        $stmt->bind_param('is', $send['id'], $target);
        $stmt->execute();
        $stmt->close();

        // A click obviously means the mail was seen — count it as an open
        // too, for a recipient whose mail client blocked the pixel but
        // still let them click a link (common: image-blocking is far more
        // prevalent than link-blocking).
        $stmt = $mysqli->prepare('UPDATE newsletter_sends SET opened_at = NOW() WHERE id = ? AND opened_at IS NULL');
        $stmt->bind_param('i', $send['id']);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: ' . $target, true, 302);
exit;
