<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

function find_admin_by_reset_token(mysqli $mysqli, string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $tokenHash = hash('sha256', $token);
    $stmt = $mysqli->prepare(
        'SELECT id FROM admin_users WHERE password_reset_token_hash = ? AND password_reset_expires > NOW()'
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $admin ?: null;
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$admin = find_admin_by_reset_token($mysqli, $token);
$error = null;
$done = false;

if ($admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (mb_strlen($password) < 8) {
        $error = 'Kies een wachtwoord van minstens 8 tekens.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'De twee wachtwoorden komen niet overeen.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare(
            'UPDATE admin_users SET password_hash = ?, password_reset_token_hash = NULL, password_reset_expires = NULL WHERE id = ?'
        );
        $stmt->bind_param('si', $hash, $admin['id']);
        $stmt->execute();
        $stmt->close();
        $done = true;
    }
}

$pageTitle = 'Nieuw wachtwoord instellen';
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
    <h1>Nieuw wachtwoord instellen</h1>

    <?php if (!$admin): ?>
        <p>Deze herstellink is ongeldig of verlopen. Vraag een nieuwe aan.</p>
        <p><a href="forgot-password.php">Nieuwe herstellink aanvragen</a></p>
    <?php elseif ($done): ?>
        <p>Je wachtwoord is gewijzigd. Je kan nu inloggen.</p>
        <p><a href="index.php">Naar inloggen</a></p>
    <?php else: ?>
        <?php if ($error): ?>
            <ul class="form-errors"><li><?= e($error) ?></li></ul>
        <?php endif; ?>

        <form method="post" action="reset-password.php">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <label for="password">Nieuw wachtwoord</label>
            <input type="password" id="password" name="password" required minlength="8" autofocus>

            <label for="password_confirm">Bevestig nieuw wachtwoord</label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8">

            <button type="submit">Wachtwoord instellen</button>
        </form>
    <?php endif; ?>
</div>
</div>
</body>
</html>
