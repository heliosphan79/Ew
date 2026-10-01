<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

// llms.txt: an emerging (unofficial but widely adopted) convention that
// gives AI systems a concise, structured summary of a site's content —
// the AI-search equivalent of sitemap.xml. Plain text/Markdown, no HTML.
header('Content-Type: text/plain; charset=UTF-8');

$homepage = $mysqli->query(
    'SELECT title, meta_description FROM pages WHERE is_homepage = 1 AND published = 1 LIMIT 1'
)->fetch_assoc();

$pages = $mysqli->query(
    'SELECT slug, title, meta_description FROM pages WHERE published = 1 AND is_homepage = 0 ORDER BY nav_order ASC, title ASC'
)->fetch_all(MYSQLI_ASSOC);

echo '# ' . $siteName . "\n\n";

$summary = trim((string) ($homepage['meta_description'] ?? ''));
echo '> ' . ($summary !== '' ? $summary : 'Website van ' . $siteName . '.') . "\n\n";

echo "## Paginas\n\n";
echo '- [' . ($homepage['title'] ?? 'Home') . '](' . absolute_url($siteUrl, '/') . ')' .
    ($summary !== '' ? ': ' . $summary : '') . "\n";

foreach ($pages as $page) {
    $description = trim((string) $page['meta_description']);
    echo '- [' . $page['title'] . '](' . absolute_url($siteUrl, '/pagina/' . $page['slug']) . ')' .
        ($description !== '' ? ': ' . $description : '') . "\n";
}

echo '- [Contact](' . absolute_url($siteUrl, '/contact') . '): Contactformulier voor ' . $siteName . ".\n";
