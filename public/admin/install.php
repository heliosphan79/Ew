<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

// One-time setup screen: only usable while the admin_users table is empty.
// Once the first admin exists, this page locks itself and redirects to login.
$existing = $mysqli->query('SELECT COUNT(*) AS total FROM admin_users')->fetch_assoc();
if ((int) $existing['total'] > 0) {
    redirect('index.php');
}

$errors = [];
$old = ['username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['username'] = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($old['username'] === '' || mb_strlen($old['username']) < 3) {
        $errors[] = 'Kies een gebruikersnaam van minstens 3 tekens.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Kies een wachtwoord van minstens 8 tekens.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'De wachtwoorden komen niet overeen.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
        $stmt->bind_param('ss', $old['username'], $hash);
        $stmt->execute();
        $stmt->close();

        set_flash('success', 'Beheerdersaccount aangemaakt. Je kan nu inloggen.');
        redirect('index.php');
    }
}

$pageTitle = 'Eerste installatie';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-auth-body">
<div class="admin-auth-box">
    <h1>Eerste installatie</h1>
    <p>Er bestaat nog geen beheerdersaccount. Maak hieronder het eerste account aan.</p>

    <?php if (!empty($errors)): ?>
        <ul class="form-errors">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="install.php">
        <?= csrf_field() ?>
        <label for="username">Gebruikersnaam</label>
        <input type="text" id="username" name="username" value="<?= e($old['username']) ?>" required>

        <label for="password">Wachtwoord</label>
        <input type="password" id="password" name="password" required minlength="8">

        <label for="password_confirm">Bevestig wachtwoord</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8">

        <button type="submit">Account aanmaken</button>
    </form>
</div>
</body>
</html>
