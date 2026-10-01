<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');

$pages = $mysqli->query(
    'SELECT slug, is_homepage, updated_at FROM pages WHERE published = 1 ORDER BY is_homepage DESC, nav_order ASC'
)->fetch_all(MYSQLI_ASSOC);

function sitemap_url(string $loc, string $lastmod): string
{
    return "    <url>\n" .
        '        <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n" .
        '        <lastmod>' . htmlspecialchars($lastmod, ENT_XML1, 'UTF-8') . "</lastmod>\n" .
        "    </url>\n";
}

$today = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($pages as $page) {
    $lastmod = date('Y-m-d', strtotime($page['updated_at']));
    if ($page['is_homepage']) {
        echo sitemap_url(absolute_url($siteUrl, '/'), $lastmod);
    } else {
        echo sitemap_url(absolute_url($siteUrl, '/pagina/' . $page['slug']), $lastmod);
    }
}

echo sitemap_url(absolute_url($siteUrl, '/contact'), $today);
echo "</urlset>\n";
