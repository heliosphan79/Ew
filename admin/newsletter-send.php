<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/mailer.php';
require_login();

header('Content-Type: application/json');

function send_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_fail('Ongeldige methode.', 405);
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    send_fail('Ongeldige of verlopen aanvraag. Herlaad de pagina.');
}

$newsletterId = (int) ($_POST['id'] ?? 0);
if ($newsletterId <= 0) {
    send_fail('Ongeldige nieuwsbrief.');
}

$stmt = $mysqli->prepare("SELECT * FROM newsletters WHERE id = ? AND status = 'sending'");
$stmt->bind_param('i', $newsletterId);
$stmt->execute();
$newsletter = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$newsletter) {
    send_fail('Deze nieuwsbrief is niet (meer) bezig met verzenden.');
}

$mailConfig = resolve_mail_config($config, $mysqli);
if (empty($mailConfig['host'])) {
    send_fail('Geen e-mailserver ingesteld. Vul dit aan onder Instellingen → E-mail.', 500);
}
$replyTo = $mailConfig['reply_to'] !== '' ? $mailConfig['reply_to'] : null;

// One batch per request, called repeatedly by the browser — see
// admin/assets/js/admin.js. Keeps each request well under any shared-
// hosting execution-time limit regardless of total list size, and the
// remaining-count response lets the page show real progress.
const BATCH_SIZE = 20;

$blocks = decode_blocks($newsletter['content']);
$bodyHtml = render_newsletter_email_body($blocks);

$stmt = $mysqli->prepare(
    'SELECT ns.id, ns.send_token, sub.email, sub.unsubscribe_token, sub.first_name
     FROM newsletter_sends ns
     JOIN newsletter_subscribers sub ON sub.id = ns.subscriber_id
     WHERE ns.newsletter_id = ? AND ns.sent_at IS NULL
     LIMIT ' . BATCH_SIZE
);
$stmt->bind_param('i', $newsletterId);
$stmt->execute();
$batch = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Committed right after each individual send — not batched until the end
// of the loop — so a timeout or crash partway through (a slow/unresponsive
// SMTP server, a shared-hosting execution-time limit) never loses progress
// already made. Before this, a request that died mid-batch committed
// nothing at all, and the next poll would re-attempt the exact same
// recipients and could die at the exact same point again, looking like a
// permanent hang.
// Keeps the most recent SMTP-level failure in this batch (an auth
// rejection, a connection timeout, a relay refusal, ...) so the admin UI
// can show the mail server's own response instead of a generic "it
// failed" — the whole reason this project hand-rolls smtp_send() instead
// of trusting a library's own (often swallowed) error handling.
$lastError = null;
$lastErrorEmail = null;

$markSent = $mysqli->prepare('UPDATE newsletter_sends SET sent_at = NOW() WHERE id = ?');
foreach ($batch as $row) {
    $personalizedBody = render_newsletter_merge_tags($bodyHtml, $row['first_name'], true);
    $personalizedSubject = render_newsletter_merge_tags($newsletter['subject'], $row['first_name'], false);
    $emailHtml = build_newsletter_email($personalizedBody, $siteName, $siteUrl, $row['send_token'], $row['unsubscribe_token']);
    $unsubscribeUrl = absolute_url($siteUrl, '/nieuwsbrief-afmelden.php?t=' . rawurlencode($row['unsubscribe_token']));
    $sendError = null;
    $sent = smtp_send($mailConfig, $row['email'], $personalizedSubject, $emailHtml, $replyTo, 'text/html', $sendError, $unsubscribeUrl);
    if (!$sent) {
        $lastError = $sendError;
        $lastErrorEmail = $row['email'];
    }
    // Sent (or at least attempted) either way: a single bad address must
    // never jam the whole batch into retrying it forever.
    $sentId = (int) $row['id'];
    $markSent->bind_param('i', $sentId);
    $markSent->execute();
}
$markSent->close();

// Persisted on the newsletter itself — not just reported transiently in
// this response — so the problem is still visible after sending finishes
// (or the admin reloads mid-send), not only during the few seconds this
// one batch's response is on screen. NULL (cleared) when this batch had
// no failures, so it reflects the current state rather than a stale
// failure from early in a long send that later started working (e.g.
// after the admin fixed the mail settings and clicked "Opnieuw proberen").
$persistedError = $lastError !== null
    ? mb_substr('(' . $lastErrorEmail . ') ' . $lastError, 0, 500)
    : null;
$stmt = $mysqli->prepare('UPDATE newsletters SET last_send_error = ? WHERE id = ?');
$stmt->bind_param('si', $persistedError, $newsletterId);
$stmt->execute();
$stmt->close();

$stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM newsletter_sends WHERE newsletter_id = ? AND sent_at IS NULL');
$stmt->bind_param('i', $newsletterId);
$stmt->execute();
$remaining = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

if ($remaining === 0) {
    $stmt = $mysqli->prepare("UPDATE newsletters SET status = 'sent', sent_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $newsletterId);
    $stmt->execute();
    $stmt->close();
}

$stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM newsletter_sends WHERE newsletter_id = ? AND sent_at IS NOT NULL');
$stmt->bind_param('i', $newsletterId);
$stmt->execute();
$sentCount = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

echo json_encode([
    'ok' => true,
    'done' => $remaining === 0,
    'sent' => $sentCount,
    'remaining' => $remaining,
    'last_error' => $lastError,
    'last_error_email' => $lastErrorEmail,
]);
