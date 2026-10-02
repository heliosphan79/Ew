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
    <div class="wrap site-header-row">
        <a class="site-logo" href="/" aria-label="<?= e($siteName) ?> — naar home">
            <svg class="ew-logo" width="36" height="36" viewBox="0 0 120 120" aria-hidden="true">
                <circle class="ew-logo-ring" cx="60" cy="60" r="50" fill="none" stroke-width="5"></circle>
                <g class="ew-logo-needle">
                    <polygon class="ew-logo-needle-a" points="60,20 70,60 50,60"></polygon>
                    <polygon class="ew-logo-needle-b" points="50,60 70,60 60,100"></polygon>
                </g>
                <circle class="ew-logo-center" cx="60" cy="60" r="4.5"></circle>
            </svg>
            <span class="ew-logo-word"><?= render_site_wordmark($siteName) ?></span>
        </a>
        <button type="button" class="menu-btn" id="menu-toggle" aria-expanded="false" aria-controls="site-nav" aria-label="Menu openen">
            <span class="menu-btn-ln l1"></span>
            <span class="menu-btn-ln l2"></span>
            <span class="menu-btn-ln l3"></span>
        </button>
        <nav class="site-nav" id="site-nav" aria-label="Hoofdmenu">
            <a href="/"><?= render_needle_icon() ?>Home</a>
            <?php foreach ($nav ?? [] as $item): ?>
                <a href="/pagina/<?= e($item['slug']) ?>"><?= render_needle_icon() ?><?= e($item['title']) ?></a>
            <?php endforeach; ?>
            <a href="/contact"><?= render_needle_icon() ?>Contact</a>
        </nav>
    </div>
</header>
<main class="wrap">
<?php $flash = get_flash(); ?>
<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
