<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? $siteName) ?> — <?= e($siteName) ?></title>
    <?php if (!empty($metaDescription)): ?>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <?php if (!empty($canonicalUrl)): ?>
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php endif; ?>

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($pageTitle ?? $siteName) ?>">
    <?php if (!empty($metaDescription)): ?>
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <?php if (!empty($canonicalUrl)): ?>
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <?php endif; ?>
    <?php if (!empty($ogImage)): ?>
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= !empty($ogImage) ? 'summary_large_image' : 'summary' ?>">

    <script type="application/ld+json"><?= json_encode(organization_schema($siteName, $siteUrl), JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/ld+json"><?= json_encode(webpage_schema($pageTitle ?? $siteName, $metaDescription ?? null, $canonicalUrl ?? null), JSON_UNESCAPED_SLASHES) ?></script>

    <link rel="stylesheet" href="/assets/css/style.css">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body data-theme="<?= e(normalize_theme_variant($themeVariant ?? null)) ?>">
<header class="site-header">
    <div class="wrap">
        <a class="site-title" href="/"><?= e($siteName) ?></a>
        <nav class="site-nav">
            <a href="/">Home</a>
            <?php foreach ($nav ?? [] as $item): ?>
                <a href="/pagina/<?= e($item['slug']) ?>"><?= e($item['title']) ?></a>
            <?php endforeach; ?>
            <a href="/contact">Contact</a>
        </nav>
    </div>
</header>
<main class="wrap">
<?php $flash = get_flash(); ?>
<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
