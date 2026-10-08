<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/mailer.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim((string) ($_POST['username'] ?? ''));
    $toEmail = trim((string) (get_site_settings($mysqli)['email'] ?? ''));
    $mailConfig = resolve_mail_config($config, $mysqli);
    $smtpConfigured = !empty($mailConfig['host']);

    if (!$smtpConfigured || $toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        // Not a secret either way — this is a site configuration gap, not
        // something tied to whether $username happens to be valid.
        $error = 'Wachtwoordherstel per e-mail is niet ingesteld voor deze site '
            . '(geen e-mailadres onder Instellingen, of geen e-mailserver ingesteld onder Instellingen → E-mail). '
            . 'Neem contact op met de beheerder van de website.';
    } else {
        $stmt = $mysqli->prepare('SELECT id FROM admin_users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Same response whether or not $username exists — otherwise this
        // form becomes a way to confirm/deny the admin username exists.
        if ($admin) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $stmt = $mysqli->prepare('UPDATE admin_users SET password_reset_token_hash = ?, password_reset_expires = ? WHERE id = ?');
            $stmt->bind_param('ssi', $tokenHash, $expires, $admin['id']);
            $stmt->execute();
            $stmt->close();

            $resetUrl = absolute_url($siteUrl, '/admin/reset-password.php?token=' . $token);
            $subject = 'Wachtwoord herstellen — ' . $siteName . ' beheer';
            $body = "Er is een aanvraag om het beheerderswachtwoord van {$siteName} te herstellen.\n\n"
                . "Klik op onderstaande link om een nieuw wachtwoord in te stellen (1 uur geldig):\n"
                . $resetUrl . "\n\n"
                . "Heb je dit zelf niet aangevraagd? Dan kan je dit bericht gewoon negeren — "
                . "er verandert niets aan het wachtwoord zonder op de link te klikken.";

            smtp_send($mailConfig, $toEmail, $subject, $body);
        }

        $sent = true;
    }
}

$pageTitle = 'Wachtwoord vergeten';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e($siteName) ?> beheer</title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/assets/images/favicon.ico">
    <link rel="icon" href="/assets/images/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-auth-body">
<div>
    <div class="admin-auth-brand">
        <svg class="ew-logo" width="30" height="30" viewBox="0 0 120 120" aria-hidden="true">
            <circle class="ew-logo-ring" cx="60" cy="60" r="50" fill="none" stroke-width="6"></circle>
            <g class="ew-logo-needle">
                <polygon class="ew-logo-needle-a" points="60,20 70,60 50,60"></polygon>
                <polygon class="ew-logo-needle-b" points="50,60 70,60 60,100"></polygon>
            </g>
            <circle class="ew-logo-center" cx="60" cy="60" r="4.5"></circle>
        </svg>
        <span><?= e($siteName) ?> beheer</span>
    </div>
<div class="admin-auth-box">
    <h1>Wachtwoord vergeten</h1>

    <?php if ($error): ?>
        <ul class="form-errors"><li><?= e($error) ?></li></ul>
    <?php endif; ?>

    <?php if ($sent): ?>
        <p>Als <?= e($username ?? '') ?> een bestaande gebruikersnaam is, is er een
        e-mail met een herstellink verstuurd. De link is 1 uur geldig.</p>
        <p><a href="index.php">Terug naar inloggen</a></p>
    <?php else: ?>
        <form method="post" action="forgot-password.php">
            <?= csrf_field() ?>
            <label for="username">Gebruikersnaam</label>
            <input type="text" id="username" name="username" required autofocus>

            <button type="submit">Herstellink versturen</button>
        </form>
        <p><a href="index.php">Terug naar inloggen</a></p>
    <?php endif; ?>
</div>
</div>
</body>
</html>
