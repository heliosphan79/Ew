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

return false; // laat de ingebouwde server het verzoek normaal afhandelen
