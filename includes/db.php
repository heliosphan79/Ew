<?php
declare(strict_types=1);

// Resolved relative to this file's own location so it works no matter
// how deep the calling script is nested (public/, public/admin/, ...).
$configFile = APP_ROOT . '/config/config.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    die('Configuratiebestand ontbreekt. Kopieer config/config.example.php naar config/config.php en vul je databasegegevens in.');
}

$config = require $configFile;

$siteName = $config['site']['name'] ?? 'Eigen-Wijzer';
$siteUrl = $config['site']['url'] ?? '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli(
        $config['db']['host'],
        $config['db']['user'],
        $config['db']['pass'],
        $config['db']['name']
    );
    $mysqli->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die('Kan geen verbinding maken met de database. Controleer config/config.php.');
}
