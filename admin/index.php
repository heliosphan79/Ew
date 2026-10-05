<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

// If no admin account exists yet, send the visitor to the one-time setup screen.
$existing = $mysqli->query('SELECT COUNT(*) AS total FROM admin_users')->fetch_assoc();
if ((int) $existing['total'] === 0) {
    redirect('install.php');
}

$error = null;
$lockedUntil = $_SESSION['login_locked_until'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (time() < $lockedUntil) {
        $error = 'Te veel mislukte pogingen. Probeer het over een minuut opnieuw.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (attempt_login($mysqli, $username, $password)) {
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            redirect('dashboard.php');
        }

        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 60;
            $_SESSION['login_attempts'] = 0;
        }
        $error = 'Ongeldige gebruikersnaam of wachtwoord.';
    }
}

$pageTitle = 'Inloggen';
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
    <h1>Inloggen</h1>

    <?php if ($error): ?>
        <ul class="form-errors"><li><?= e($error) ?></li></ul>
    <?php endif; ?>

    <form method="post" action="index.php">
        <?= csrf_field() ?>
        <label for="username">Gebruikersnaam</label>
        <input type="text" id="username" name="username" required autofocus>

        <label for="password">Wachtwoord</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Inloggen</button>
    </form>
    <p><a href="forgot-password.php">Wachtwoord vergeten?</a></p>
</div>
</div>
</body>
</html>
