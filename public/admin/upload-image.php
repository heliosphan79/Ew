<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_login();

header('Content-Type: application/json');

function upload_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    upload_fail('Ongeldige methode.', 405);
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    upload_fail('Ongeldige of verlopen aanvraag. Herlaad de pagina en probeer opnieuw.');
}

if (empty($_FILES['image']) || !is_array($_FILES['image'])) {
    upload_fail('Geen bestand ontvangen.');
}

$file = $_FILES['image'];

if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    upload_fail('Bestand is te groot voor deze server.');
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    upload_fail('Upload mislukt. Probeer opnieuw.');
}

$maxBytes = 5 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    upload_fail('Bestand is te groot (max. 5 MB).');
}

$allowedMimeToExtension = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

$mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!isset($allowedMimeToExtension[$mime])) {
    upload_fail('Alleen JPG, PNG, GIF of WEBP-afbeeldingen zijn toegestaan.');
}

// Defense against polyglot files that pass the mime sniff above.
if (@getimagesize($file['tmp_name']) === false) {
    upload_fail('Het bestand kon niet als afbeelding worden gelezen.');
}

$extension = $allowedMimeToExtension[$mime];
$filename = bin2hex(random_bytes(12)) . '.' . $extension;
$uploadDir = APP_ROOT . '/public/uploads/';
$destination = $uploadDir . $filename;

if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    upload_fail('De uploadmap is niet beschikbaar. Neem contact op met de beheerder.', 500);
}

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    upload_fail('Opslaan van de afbeelding is mislukt.', 500);
}

echo json_encode(['ok' => true, 'url' => '/uploads/' . $filename]);
