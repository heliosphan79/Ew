<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';

if ($slug === '') {
    http_response_code(404);
    redirect('/');
}

$stmt = $mysqli->prepare(
    'SELECT slug, title, content, theme_variant, meta_description FROM pages WHERE slug = ? AND published = 1 LIMIT 1'
);
$stmt->bind_param('s', $slug);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$page) {
    http_response_code(404);
}

$nav = $mysqli->query(
    'SELECT slug, title FROM pages WHERE published = 1 AND is_homepage = 0 ORDER BY nav_order ASC, title ASC'
)->fetch_all(MYSQLI_ASSOC);

$pageBlocks = $page ? decode_blocks($page['content']) : [];

$pageTitle = $page['title'] ?? 'Pagina niet gevonden';
$metaDescription = $page['meta_description'] ?? '';
$themeVariant = $page['theme_variant'] ?? 'a';
$canonicalUrl = $page ? absolute_url($siteUrl, '/pagina/' . $page['slug']) : null;
$imageUrl = first_image_url($pageBlocks);
$ogImage = $imageUrl ? media_absolute_url($siteUrl, $imageUrl) : null;

require __DIR__ . '/../includes/header.php';
?>

<?php if ($page): ?>
    <article class="page-content">
        <h1><?= e($page['title']) ?></h1>
        <?= render_blocks($pageBlocks) ?>
    </article>
<?php else: ?>
    <article class="page-content">
        <h1>Pagina niet gevonden</h1>
        <p>De opgevraagde pagina bestaat niet (meer). <a href="/">Terug naar de homepage</a>.</p>
    </article>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
