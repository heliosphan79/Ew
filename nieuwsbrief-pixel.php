<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_GET['t'] ?? '');
if ($token !== '') {
    // First open only — repeat opens (re-reading the mail later) don't
    // keep moving opened_at forward, so it stays meaningful as "when they
    // first saw it".
    $stmt = $mysqli->prepare('UPDATE newsletter_sends SET opened_at = NOW() WHERE send_token = ? AND opened_at IS NULL');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->close();
}

// A 1x1 transparent GIF — smaller and more universally supported by
// ancient e-mail clients than a PNG, and there is no reason to ever cache
// this (every recipient's own pixel must always be requested fresh).
$pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7');
header('Content-Type: image/gif');
header('Content-Length: ' . strlen($pixel));
header('Cache-Control: no-store, no-cache, must-revalidate');
echo $pixel;
