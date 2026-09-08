<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Beheer') ?> — <?= e($siteName) ?> beheer</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-brand">eigen-wijzer.be</div>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="pages.php">Pagina's</a>
            <a href="submissions.php">Contactberichten</a>
        </nav>
        <form method="post" action="logout.php" class="admin-logout">
            <button type="submit">Uitloggen</button>
        </form>
    </aside>
    <main class="admin-main">
        <?php $flash = get_flash(); ?>
        <?php if ($flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
