<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

// Outputs exactly what build_newsletter_email() would send — no admin
// chrome, just the raw HTML — so what's loaded here (in an iframe from
// newsletter-preview.php, or opened directly) is a genuinely accurate
// preview, not an approximation. Tokens are placeholders: no real
// newsletter_sends row backs this, so the tracking pixel/unsubscribe link
// render correctly but don't resolve to anything.

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = $mysqli->prepare('SELECT subject, content FROM newsletters WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$newsletter = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$newsletter) {
    http_response_code(404);
    echo 'Nieuwsbrief niet gevonden.';
    exit;
}

$blocks = decode_blocks($newsletter['content']);
$bodyHtml = render_newsletter_email_body($blocks, $mysqli);
$bodyHtml = render_newsletter_merge_tags($bodyHtml, null, true);
$emailHtml = build_newsletter_email($bodyHtml, $siteName, $siteUrl, 'voorvertoning', 'voorvertoning');

header('Content-Type: text/html; charset=UTF-8');
echo $emailHtml;
