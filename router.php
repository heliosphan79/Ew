<?php
// Router voor PHP's ingebouwde server tijdens lokaal testen (php -S ... router.php).
// Nodig omdat die server .htaccess volledig negeert: zonder dit bestand zou
// config/config.php, database/schema.sql, enz. gewoon opvraagbaar zijn via
// de browser tijdens lokaal testen. Op echte hosting (Apache) doet
// config/.htaccess, database/.htaccess en includes/.htaccess dit al.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$blocked = ['/config/', '/database/', '/includes/', '/admin/includes/'];
foreach ($blocked as $prefix) {
    if (str_starts_with($path, $prefix)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Spiegelt de RewriteRule's uit .htaccess (die de ingebouwde server
// negeert) zodat de nette URL's ook lokaal werken — anders valt elk
// onbekend pad terug op index.php (PHP's eigen standaardgedrag zonder
// router), en lijkt bv. het contactformulier te werken terwijl het in
// werkelijkheid nooit contact.php bereikt.
if (preg_match('#^/pagina/([a-z0-9-]+)/?$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/pagina.php';
    return true;
}
if (preg_match('#^/contact/?$#', $path)) {
    require __DIR__ . '/contact.php';
    return true;
}
if ($path === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}
if ($path === '/robots.txt') {
    require __DIR__ . '/robots.php';
    return true;
}
if ($path === '/llms.txt') {
    require __DIR__ . '/llms.php';
    return true;
}

return false; // laat de ingebouwde server het verzoek normaal afhandelen
