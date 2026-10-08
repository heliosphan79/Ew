<?php
declare(strict_types=1);

// Minimal hand-rolled SMTP client (RFC 5321) — no Composer/PHPMailer,
// matching this project's no-framework/no-dependency philosophy. Supports
// STARTTLS, implicit TLS and AUTH LOGIN, which covers the vast majority of
// hosting-provider and webmail SMTP setups. Included only where mail is
// actually sent (contact.php), not on every request.

// DKIM (RFC 6376, rsa-sha256, relaxed/relaxed canonicalization) — signs
// outgoing mail so receivers can cryptographically verify it really came
// from this domain, which (together with SPF) is what most inbox
// providers weigh when deciding "Primary" vs. "Promotions"/spam. Hand-
// rolled like the rest of this file; openssl_sign() is the only
// non-core-PHP dependency, already required for stream_socket_enable_crypto
// above. Signing is best-effort: any failure here must never block a send,
// since an unsigned mail still sends fine, just without this extra signal.

// Relaxed body canonicalization: collapse runs of space/tab within a line
// to a single space, strip trailing space/tab per line, strip trailing
// blank lines (an entirely empty body canonicalizes to a single CRLF).
function dkim_canonicalize_body(string $body): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $normalized);
    foreach ($lines as &$line) {
        $line = rtrim(preg_replace('/[ \t]+/', ' ', $line), " \t");
    }
    unset($line);
    while (count($lines) > 1 && end($lines) === '') {
        array_pop($lines);
    }
    if (count($lines) === 1 && $lines[0] === '') {
        return "\r\n";
    }
    return implode("\r\n", $lines) . "\r\n";
}

// Relaxed header canonicalization: lowercase the header name, collapse
// internal whitespace runs in the value to a single space, trim the value.
function dkim_canonicalize_header(string $name, string $value): string
{
    return strtolower(trim($name)) . ':' . preg_replace('/\s+/', ' ', trim($value));
}

// $headerLines: the exact "Name: value" lines as they'll be sent, in
// order — only those named in $dkimConfig get signed (From/To/Subject/
// Date/Message-ID), the rest (Reply-To, List-Unsubscribe, ...) are left
// out of h= on purpose: selectively signing only the stable "core"
// headers is normal DKIM practice and keeps the signature from breaking
// over header variations between mail types. Returns the full
// "DKIM-Signature: ..." line (no trailing CRLF) to prepend, or null if
// DKIM isn't configured or signing fails for any reason.
function dkim_sign(array $headerLines, string $body, array $dkimConfig): ?string
{
    $domain = trim((string) ($dkimConfig['domain'] ?? ''));
    $selector = trim((string) ($dkimConfig['selector'] ?? ''));
    $privateKeyPem = (string) ($dkimConfig['private_key'] ?? '');
    if ($domain === '' || $selector === '' || trim($privateKeyPem) === '') {
        return null;
    }

    $privateKey = openssl_pkey_get_private($privateKeyPem);
    if ($privateKey === false) {
        error_log('DKIM: kon privésleutel niet laden.');
        return null;
    }

    $signableNames = ['from', 'to', 'subject', 'date', 'message-id'];
    $canonHeaders = '';
    $signedHeaderNames = [];
    foreach ($headerLines as $line) {
        $colon = strpos($line, ':');
        if ($colon === false) {
            continue;
        }
        $name = substr($line, 0, $colon);
        if (!in_array(strtolower($name), $signableNames, true)) {
            continue;
        }
        $canonHeaders .= dkim_canonicalize_header($name, substr($line, $colon + 1)) . "\r\n";
        $signedHeaderNames[] = strtolower($name);
    }
    if (empty($signedHeaderNames)) {
        return null;
    }

    $bodyHash = base64_encode(hash('sha256', dkim_canonicalize_body($body), true));

    $signatureHeader = 'DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; d=' . $domain
        . '; s=' . $selector . '; h=' . implode(':', $signedHeaderNames)
        . '; bh=' . $bodyHash . '; b=';

    // The DKIM-Signature header itself is part of what gets signed — with
    // an empty b= tag and, unlike the other headers above, no trailing
    // CRLF (RFC 6376 §3.7).
    $dataToSign = $canonHeaders
        . dkim_canonicalize_header('DKIM-Signature', substr($signatureHeader, strlen('DKIM-Signature:')));

    if (!openssl_sign($dataToSign, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256)) {
        error_log('DKIM: ondertekenen mislukt.');
        return null;
    }

    return $signatureHeader . base64_encode($binarySignature);
}

function smtp_read_response($socket): array
{
    $full = '';
    $code = 0;
    do {
        $line = fgets($socket, 515); // SMTP lines are capped at ~512 bytes (RFC 5321 §4.5.3.1.5)
        if ($line === false) {
            return ['code' => 0, 'text' => $full];
        }
        $full .= $line;
        $code = (int) substr($line, 0, 3);
        $continuation = isset($line[3]) && $line[3] === '-';
    } while ($continuation);

    return ['code' => $code, 'text' => $full];
}

// $label overrides $command in the error text — used for the AUTH LOGIN
// username/password lines, which are the base64-encoded credentials
// themselves and must never appear in an error message that ends up in
// the admin UI (or a log file).
function smtp_command($socket, string $command, int $expectedCode, ?string &$error = null, ?string $label = null): bool
{
    fwrite($socket, $command . "\r\n");
    $response = smtp_read_response($socket);
    if ($response['code'] !== $expectedCode) {
        $received = trim($response['text']);
        $error = $received === ''
            ? 'Geen antwoord op "' . ($label ?? $command) . '" (time-out of verbinding verbroken).'
            : 'Na "' . ($label ?? $command) . '" verwacht: ' . $expectedCode . ', ontvangen: ' . $received;
        error_log('SMTP: ' . $error);
        return false;
    }
    return true;
}

// Lines starting with "." must be escaped (an extra leading ".") during
// the DATA phase, since a lone "." on a line signals end-of-message.
function smtp_dot_stuff(string $text): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $normalized);
    foreach ($lines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }
    unset($line);
    return implode("\r\n", $lines);
}

// MIME-encodes a header value (e.g. a Subject or display name) only when
// it contains non-ASCII characters — plain ASCII is left untouched.
function smtp_encode_header(string $value): string
{
    if (preg_match('/[^\x20-\x7E]/', $value) !== 1) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_format_address(string $email, string $name = ''): string
{
    return $name === '' ? $email : smtp_encode_header($name) . ' <' . $email . '>';
}

// Sends one plain-text e-mail. Returns false (and logs via error_log, never
// to the visitor) on any failure — callers should treat mail delivery as
// best-effort and never let it block saving the underlying data. The
// optional $error out-parameter carries the actual reason (a connection
// failure, or the SMTP server's own response text) for callers that do
// want to report it somewhere — currently only the nieuwsbrief batch-send,
// whose admin UI shows it live so a stuck send is actually diagnosable.
function smtp_send(array $smtpConfig, string $toEmail, string $subject, string $body, ?string $replyTo = null, string $contentType = 'text/plain', ?string &$error = null, ?string $listUnsubscribeUrl = null): bool
{
    $error = null;
    $host = (string) ($smtpConfig['host'] ?? '');
    $fromEmail = (string) ($smtpConfig['from_email'] ?? '');
    if ($host === '' || $fromEmail === '' || $toEmail === '') {
        $error = 'Geen SMTP-server, afzenderadres of geldig ontvangeradres ingesteld.';
        return false;
    }

    $port = (int) ($smtpConfig['port'] ?? 587);
    $encryption = (string) ($smtpConfig['encryption'] ?? 'tls');
    $username = (string) ($smtpConfig['username'] ?? '');
    $password = (string) ($smtpConfig['password'] ?? '');
    $fromName = (string) ($smtpConfig['from_name'] ?? '');
    $dkimConfig = [
        'domain' => $smtpConfig['dkim_domain'] ?? '',
        'selector' => $smtpConfig['dkim_selector'] ?? '',
        'private_key' => $smtpConfig['dkim_private_key'] ?? '',
    ];

    $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT
    );
    if ($socket === false) {
        $error = "Kon geen verbinding maken met $host:$port ($errstr, foutcode $errno).";
        error_log("SMTP: connect to $host:$port failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($socket, 10);

    $ok = smtp_send_sequence($socket, $host, $port, $encryption, $username, $password, $fromEmail, $fromName, $toEmail, $subject, $body, $replyTo, $contentType, $error, $listUnsubscribeUrl, $dkimConfig);

    fclose($socket);
    return $ok;
}

function smtp_send_sequence(
    $socket,
    string $host,
    int $port,
    string $encryption,
    string $username,
    string $password,
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $subject,
    string $body,
    ?string $replyTo,
    string $contentType = 'text/plain',
    ?string &$error = null,
    ?string $listUnsubscribeUrl = null,
    array $dkimConfig = []
): bool {
    $greeting = smtp_read_response($socket);
    if ($greeting['code'] !== 220) {
        $error = 'Geen 220-begroeting ontvangen: ' . trim($greeting['text']);
        error_log('SMTP: ' . $error);
        return false;
    }

    $atPos = strpos($fromEmail, '@');
    $ehloDomain = $atPos !== false ? substr($fromEmail, $atPos + 1) : 'localhost';

    if (!smtp_command($socket, 'EHLO ' . $ehloDomain, 250, $error)) {
        return false;
    }

    if ($encryption === 'tls') {
        if (!smtp_command($socket, 'STARTTLS', 220, $error)) {
            return false;
        }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $error = 'STARTTLS-onderhandeling mislukt.';
            error_log('SMTP: ' . $error);
            return false;
        }
        // Capabilities can change after STARTTLS (RFC 3207) — re-greet.
        if (!smtp_command($socket, 'EHLO ' . $ehloDomain, 250, $error)) {
            return false;
        }
    }

    if ($username !== '') {
        if (!smtp_command($socket, 'AUTH LOGIN', 334, $error)) {
            return false;
        }
        if (!smtp_command($socket, base64_encode($username), 334, $error, 'AUTH-gebruikersnaam')) {
            return false;
        }
        if (!smtp_command($socket, base64_encode($password), 235, $error, 'AUTH-wachtwoord')) {
            return false;
        }
    }

    if (!smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', 250, $error)) {
        return false;
    }
    if (!smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', 250, $error)) {
        return false;
    }
    if (!smtp_command($socket, 'DATA', 354, $error)) {
        return false;
    }

    $headers = [
        'From: ' . smtp_format_address($fromEmail, $fromName),
        'To: ' . $toEmail,
    ];
    if ($replyTo !== null && $replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    if ($listUnsubscribeUrl !== null && $listUnsubscribeUrl !== '') {
        // Gmail/Yahoo/Outlook all use this pair as a strong signal that a
        // message is a legitimate, properly-run bulk mailing rather than
        // spam — it's also what puts their native "Unsubscribe" link next
        // to the sender name. List-Unsubscribe-Post (RFC 8058) tells the
        // client it can POST the literal body below to unsubscribe
        // without opening anything; nieuwsbrief-afmelden.php already
        // handles that with no login/confirmation step, so this is safe.
        $headers[] = 'List-Unsubscribe: <' . $listUnsubscribeUrl . '>';
        $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }
    $headers[] = 'Subject: ' . smtp_encode_header($subject);
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $ehloDomain . '>';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: ' . $contentType . '; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    $dkimHeader = dkim_sign($headers, $body, $dkimConfig);
    if ($dkimHeader !== null) {
        array_unshift($headers, $dkimHeader);
    }

    $message = implode("\r\n", $headers) . "\r\n\r\n" . smtp_dot_stuff($body);
    fwrite($socket, $message . "\r\n.\r\n");

    $response = smtp_read_response($socket);
    if ($response['code'] !== 250) {
        $error = 'Bericht niet geaccepteerd: ' . trim($response['text']);
        error_log('SMTP: ' . $error);
        return false;
    }

    fwrite($socket, "QUIT\r\n");

    return true;
}

// Called right after a contact-form submission is saved. Mail delivery is
// best-effort: the submission is already safely in contact_submissions
// either way, this just additionally tries to notify by e-mail. Silently
// does nothing if SMTP isn't configured, or if there's no address to send
// to (smtp.to_email, falling back to the address set under Instellingen).
function send_contact_notification(array $config, mysqli $mysqli, array $submission): void
{
    $smtpConfig = resolve_mail_config($config, $mysqli);
    if (empty($smtpConfig['host'])) {
        return;
    }

    $toEmail = trim((string) ($smtpConfig['to_email'] ?? ''));
    if ($toEmail === '') {
        $toEmail = trim((string) (get_site_settings($mysqli)['email'] ?? ''));
    }
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $siteName = (string) ($config['site']['name'] ?? 'Eigen-Wijzer');
    $subject = 'Nieuw contactbericht via ' . $siteName;
    $body = "Naam: {$submission['name']}\n"
        . "E-mail: {$submission['email']}\n\n"
        . "Bericht:\n{$submission['message']}\n\n"
        . "---\nVerzonden via het contactformulier op de website. Beantwoorden via "
        . "\"Reply\" stuurt rechtstreeks naar {$submission['email']}.";

    smtp_send($smtpConfig, $toEmail, $subject, $body, $submission['email']);
}
