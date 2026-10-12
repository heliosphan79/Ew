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

    <script type="application/ld+json"><?= json_encode(organization_schema($siteName, $siteUrl, get_site_settings($mysqli)), JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/ld+json"><?= json_encode(webpage_schema($pageTitle ?? $siteName, $metaDescription ?? null, $canonicalUrl ?? null), JSON_UNESCAPED_SLASHES) ?></script>

    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/assets/images/favicon.ico">
    <link rel="icon" href="/assets/images/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">

    <?php $activeTheme = get_active_theme($mysqli); ?>
    <link rel="stylesheet" href="<?= e(active_theme_css_url($activeTheme)) ?>">
    <script>document.documentElement.classList.add('js');</script>
    <?php if (!empty($config['google_analytics']['measurement_id'])): ?>
    <meta name="ga-measurement-id" content="<?= e($config['google_analytics']['measurement_id']) ?>">
    <?php endif; ?>
    <?php if (!empty($recaptchaSiteKey ?? '')): ?>
    <meta name="recaptcha-site-key" content="<?= e($recaptchaSiteKey) ?>">
    <?php endif; ?>
</head>
<body data-theme="<?= e(normalize_theme_variant($themeVariant ?? null)) ?>" data-privacy-url="<?= e(get_privacy_page_url($mysqli) ?? '') ?>">
<header class="site-header">
    <div class="wrap site-header-row">
        <a class="site-logo" href="/" aria-label="<?= e($siteName) ?> — naar home">
            <?= render_logo_mark($activeTheme) ?>
            <span class="ew-logo-word"><?= render_site_wordmark($siteName) ?></span>
        </a>
        <button type="button" class="menu-btn" id="menu-toggle" aria-expanded="false" aria-controls="site-nav" aria-label="Menu openen">
            <span class="menu-btn-ln l1"></span>
            <span class="menu-btn-ln l2"></span>
            <span class="menu-btn-ln l3"></span>
        </button>
        <?php
        $currentPath = rtrim((string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'), '/');
        if ($currentPath === '') {
            $currentPath = '/';
        }
        $navLinkAttrs = fn(string $path): string => $path === $currentPath ? ' class="is-active" aria-current="page"' : '';
        ?>
        <nav class="site-nav" id="site-nav" aria-label="Hoofdmenu">
            <a href="/"<?= $navLinkAttrs('/') ?>><?= render_needle_icon() ?>Home</a>
            <?php foreach ($nav ?? [] as $item): ?>
                <a href="/pagina/<?= e($item['slug']) ?>"<?= $navLinkAttrs('/pagina/' . $item['slug']) ?>><?= render_needle_icon() ?><?= e($item['title']) ?></a>
            <?php endforeach; ?>
            <a href="/contact"<?= $navLinkAttrs('/contact') ?>><?= render_needle_icon() ?>Contact</a>
        </nav>
    </div>
</header>
<main class="wrap">
<?php $flash = get_flash(); ?>
<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
