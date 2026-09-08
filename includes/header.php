<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? $siteName) ?> — <?= e($siteName) ?></title>
    <?php if (!empty($metaDescription)): ?>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body data-theme="<?= e(normalize_theme_variant($themeVariant ?? null)) ?>">
<header class="site-header">
    <div class="wrap">
        <a class="site-title" href="/">eigen-wijzer.be</a>
        <nav class="site-nav">
            <a href="/">Home</a>
            <?php foreach ($nav ?? [] as $item): ?>
                <a href="/pagina.php?slug=<?= e($item['slug']) ?>"><?= e($item['title']) ?></a>
            <?php endforeach; ?>
            <a href="/contact.php">Contact</a>
        </nav>
    </div>
</header>
<main class="wrap">
<?php $flash = get_flash(); ?>
<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
