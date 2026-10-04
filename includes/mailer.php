<?php
declare(strict_types=1);

// Minimal hand-rolled SMTP client (RFC 5321) — no Composer/PHPMailer,
// matching this project's no-framework/no-dependency philosophy. Supports
// STARTTLS, implicit TLS and AUTH LOGIN, which covers the vast majority of
// hosting-provider and webmail SMTP setups. Included only where mail is
// actually sent (contact.php), not on every request.

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

function smtp_command($socket, string $command, int $expectedCode): bool
{
    fwrite($socket, $command . "\r\n");
    $response = smtp_read_response($socket);
    if ($response['code'] !== $expectedCode) {
        error_log('SMTP: expected ' . $expectedCode . ' after "' . $command . '", got: ' . trim($response['text']));
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
// best-effort and never let it block saving the underlying data.
function smtp_send(array $smtpConfig, string $toEmail, string $subject, string $body, ?string $replyTo = null): bool
{
    $host = (string) ($smtpConfig['host'] ?? '');
    $fromEmail = (string) ($smtpConfig['from_email'] ?? '');
    if ($host === '' || $fromEmail === '' || $toEmail === '') {
        return false;
    }

    $port = (int) ($smtpConfig['port'] ?? 587);
    $encryption = (string) ($smtpConfig['encryption'] ?? 'tls');
    $username = (string) ($smtpConfig['username'] ?? '');
    $password = (string) ($smtpConfig['password'] ?? '');
    $fromName = (string) ($smtpConfig['from_name'] ?? '');

    $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT
    );
    if ($socket === false) {
        error_log("SMTP: connect to $host:$port failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($socket, 10);

    $ok = smtp_send_sequence($socket, $host, $port, $encryption, $username, $password, $fromEmail, $fromName, $toEmail, $subject, $body, $replyTo);

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
    ?string $replyTo
): bool {
    $greeting = smtp_read_response($socket);
    if ($greeting['code'] !== 220) {
        error_log('SMTP: no 220 greeting from server: ' . trim($greeting['text']));
        return false;
    }

    $atPos = strpos($fromEmail, '@');
    $ehloDomain = $atPos !== false ? substr($fromEmail, $atPos + 1) : 'localhost';

    if (!smtp_command($socket, 'EHLO ' . $ehloDomain, 250)) {
        return false;
    }

    if ($encryption === 'tls') {
        if (!smtp_command($socket, 'STARTTLS', 220)) {
            return false;
        }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log('SMTP: STARTTLS negotiation failed');
            return false;
        }
        // Capabilities can change after STARTTLS (RFC 3207) — re-greet.
        if (!smtp_command($socket, 'EHLO ' . $ehloDomain, 250)) {
            return false;
        }
    }

    if ($username !== '') {
        if (!smtp_command($socket, 'AUTH LOGIN', 334)) {
            return false;
        }
        if (!smtp_command($socket, base64_encode($username), 334)) {
            return false;
        }
        if (!smtp_command($socket, base64_encode($password), 235)) {
            return false;
        }
    }

    if (!smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', 250)) {
        return false;
    }
    if (!smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', 250)) {
        return false;
    }
    if (!smtp_command($socket, 'DATA', 354)) {
        return false;
    }

    $headers = [
        'From: ' . smtp_format_address($fromEmail, $fromName),
        'To: ' . $toEmail,
    ];
    if ($replyTo !== null && $replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $headers[] = 'Subject: ' . smtp_encode_header($subject);
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $ehloDomain . '>';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    $message = implode("\r\n", $headers) . "\r\n\r\n" . smtp_dot_stuff($body);
    fwrite($socket, $message . "\r\n.\r\n");

    $response = smtp_read_response($socket);
    if ($response['code'] !== 250) {
        error_log('SMTP: message not accepted: ' . trim($response['text']));
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
    $smtpConfig = $config['smtp'] ?? [];
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
