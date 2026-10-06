<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Beheer') ?> — <?= e($siteName) ?> beheer</title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/assets/images/favicon.ico">
    <link rel="icon" href="/assets/images/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">

    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <svg class="ew-logo" width="28" height="28" viewBox="0 0 120 120" aria-hidden="true">
                <circle class="ew-logo-ring" cx="60" cy="60" r="50" fill="none" stroke-width="6"></circle>
                <g class="ew-logo-needle">
                    <polygon class="ew-logo-needle-a" points="60,20 70,60 50,60"></polygon>
                    <polygon class="ew-logo-needle-b" points="50,60 70,60 60,100"></polygon>
                </g>
                <circle class="ew-logo-center" cx="60" cy="60" r="4.5"></circle>
            </svg>
            <span>eigen-wijzer.be</span>
        </div>
        <nav>
            <?php
            $adminNavCurrent = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            $adminNavGroups = [
                'dashboard.php' => 'dashboard',
                'pages.php' => 'pages',
                'page-edit.php' => 'pages',
                'page-reorder.php' => 'pages',
                'submissions.php' => 'submissions',
                'submission-delete.php' => 'submissions',
                'calendar.php' => 'calendar',
                'events.php' => 'events',
                'event-edit.php' => 'events',
                'event-delete.php' => 'events',
                'event-registrations.php' => 'events',
                'newsletters.php' => 'newsletters',
                'newsletter-edit.php' => 'newsletters',
                'newsletter-subscribers.php' => 'newsletters',
                'settings.php' => 'settings',
            ];
            $adminNavActive = $adminNavGroups[$adminNavCurrent] ?? '';
            $adminNavLinks = [
                'dashboard' => ['dashboard.php', 'dashboard', 'Dashboard'],
                'pages' => ['pages.php', 'pages', "Pagina's"],
                'submissions' => ['submissions.php', 'inbox', 'Contactberichten'],
                'calendar' => ['calendar.php', 'calendar', 'Kalender'],
                'events' => ['events.php', 'users', 'Evenementen'],
                'newsletters' => ['newsletters.php', 'mail', 'Nieuwsbrief'],
                'settings' => ['settings.php', 'settings', 'Instellingen'],
            ];
            foreach ($adminNavLinks as $key => [$href, $icon, $label]):
            ?>
                <a href="<?= e($href) ?>" class="<?= $adminNavActive === $key ? 'is-active' : '' ?>">
                    <?= admin_icon($icon) ?><span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <form method="post" action="logout.php" class="admin-logout">
            <button type="submit"><?= admin_icon('logout') ?><span>Uitloggen</span></button>
        </form>
    </aside>
    <main class="admin-main">
        <?php $flash = get_flash(); ?>
        <?php if ($flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
