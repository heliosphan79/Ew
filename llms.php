<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

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

$siteSettings = get_site_settings($mysqli);
$contactMetaDescription = trim((string) $siteSettings['contact_meta_description']);
$aiSummary = trim((string) $siteSettings['ai_summary']);

$events = $mysqli->query(
    'SELECT title, description, event_date, event_time, location
     FROM events
     WHERE published = 1 AND event_date >= CURDATE()
     ORDER BY event_date ASC, event_time ASC
     LIMIT 10'
)->fetch_all(MYSQLI_ASSOC);

echo '# ' . $siteName . "\n\n";

$summary = trim((string) ($homepage['meta_description'] ?? ''));
echo '> ' . ($summary !== '' ? $summary : 'Website van ' . $siteName . '.') . "\n\n";

if ($aiSummary !== '') {
    echo "## Over de praktijk\n\n";
    echo $aiSummary . "\n\n";
}

echo "## Aanbod\n\n";
echo '- [' . ($homepage['title'] ?? 'Home') . '](' . absolute_url($siteUrl, '/') . ')' .
    ($summary !== '' ? ': ' . $summary : '') . "\n";

foreach ($pages as $page) {
    $description = trim((string) $page['meta_description']);
    echo '- [' . $page['title'] . '](' . absolute_url($siteUrl, '/pagina/' . $page['slug']) . ')' .
        ($description !== '' ? ': ' . $description : '') . "\n";
}

echo '- [Contact](' . absolute_url($siteUrl, '/contact') . ')' .
    ($contactMetaDescription !== '' ? ': ' . $contactMetaDescription : ': Contactformulier voor ' . $siteName . '.') . "\n";

if (!empty($events)) {
    echo "\n## Evenementen\n\n";
    foreach ($events as $event) {
        $when = format_event_date($event['event_date'], $event['event_time']);
        $where = trim((string) $event['location']);
        $description = trim((string) $event['description']);

        echo '- ' . $event['title'] . ' (' . $when . ($where !== '' ? ', ' . $where : '') . ')' .
            ($description !== '' ? ': ' . $description : '') . "\n";
    }
}
