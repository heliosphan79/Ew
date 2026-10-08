<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/mailer.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('newsletters.php');
}
csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
$testEmail = trim((string) ($_POST['test_email'] ?? ''));

if ($id <= 0) {
    set_flash('error', 'Ongeldige nieuwsbrief.');
    redirect('newsletters.php');
}
if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', 'Vul een geldig e-mailadres in voor de testmail.');
    redirect('newsletter-edit.php?id=' . $id);
}

$stmt = $mysqli->prepare('SELECT subject, content FROM newsletters WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$newsletter = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$newsletter) {
    set_flash('error', 'Nieuwsbrief niet gevonden.');
    redirect('newsletters.php');
}

$mailConfig = resolve_mail_config($config, $mysqli);
if (empty($mailConfig['host'])) {
    set_flash('error', 'Geen e-mailserver ingesteld. Vul dit aan onder Instellingen → E-mail.');
    redirect('newsletter-edit.php?id=' . $id);
}

$blocks = decode_blocks($newsletter['content']);
$bodyHtml = render_newsletter_email_body($blocks, $mysqli);
$bodyHtml = render_newsletter_merge_tags($bodyHtml, null, true);
// Prefixed so a test is never mistaken for the real send, and no
// List-Unsubscribe header — there's no real subscriber/token behind a
// one-off manual test, unlike the actual batch send.
$subject = '[Test] ' . render_newsletter_merge_tags($newsletter['subject'], null, false);
$emailHtml = build_newsletter_email($bodyHtml, $siteName, $siteUrl, 'test', 'test');

$replyTo = $mailConfig['reply_to'] !== '' ? $mailConfig['reply_to'] : null;
$error = null;
$sent = smtp_send($mailConfig, $testEmail, $subject, $emailHtml, $replyTo, 'text/html', $error);

if ($sent) {
    set_flash('success', 'Testmail verzonden naar ' . $testEmail . '.');
} else {
    set_flash('error', 'Testmail versturen mislukt: ' . ($error ?? 'onbekende fout.'));
}
redirect('newsletter-edit.php?id=' . $id);
