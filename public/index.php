<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$homepage = $mysqli->query(
    'SELECT title, content, theme_variant, meta_description FROM pages WHERE is_homepage = 1 AND published = 1 LIMIT 1'
)->fetch_assoc();

$nav = $mysqli->query(
    'SELECT slug, title FROM pages WHERE published = 1 AND is_homepage = 0 ORDER BY nav_order ASC, title ASC'
)->fetch_all(MYSQLI_ASSOC);

$homepageBlocks = $homepage ? decode_blocks($homepage['content']) : [];

$pageTitle = $homepage['title'] ?? $siteName;
$metaDescription = $homepage['meta_description'] ?? '';
$themeVariant = $homepage['theme_variant'] ?? 'a';
$canonicalUrl = absolute_url($siteUrl, '/');
$imageUrl = first_image_url($homepageBlocks);
$ogImage = $imageUrl ? media_absolute_url($siteUrl, $imageUrl) : null;

require __DIR__ . '/../includes/header.php';
?>

<?php if ($homepage): ?>
    <article class="page-content">
        <h1><?= e($homepage['title']) ?></h1>
        <?= render_blocks($homepageBlocks) ?>
    </article>
<?php else: ?>
    <article class="page-content">
        <h1>Welkom bij <?= e($siteName) ?></h1>
        <p>Er is nog geen homepagina ingesteld. Log in op het beheerpaneel en markeer een pagina als homepagina.</p>
    </article>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
