<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('themes.php');
}

csrf_verify();

function theme_upload_fail(string $message): never
{
    set_flash('error', $message);
    redirect('themes.php');
}

if (empty($_FILES['theme_zip']) || !is_array($_FILES['theme_zip'])) {
    theme_upload_fail('Geen bestand ontvangen.');
}

$file = $_FILES['theme_zip'];
if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    theme_upload_fail('Bestand is te groot voor deze server.');
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    theme_upload_fail('Upload mislukt. Probeer opnieuw.');
}

$maxZipBytes = 8 * 1024 * 1024;
if ($file['size'] > $maxZipBytes) {
    theme_upload_fail('Zip-bestand is te groot (max. 8 MB).');
}

$mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!in_array($mime, ['application/zip', 'application/x-zip-compressed'], true)) {
    theme_upload_fail('Dit is geen geldig zip-bestand.');
}

$zip = new ZipArchive();
if ($zip->open($file['tmp_name']) !== true) {
    theme_upload_fail('Het zip-bestand kon niet worden geopend.');
}

// Strict allowlist by exact top-level filename — never by sanitizing the
// zip entry's own path. Anything that doesn't match one of these exact
// names (theme.json, style.css, or a single logo.<ext>) is rejected
// outright, including any subdirectory or path-traversal entry: it
// simply never matches, so there's nothing further to sanitize against
// zip-slip. Directory entries (trailing "/") are silently skipped.
$logoExtensionPattern = implode('|', THEME_LOGO_EXTENSIONS);
$found = ['theme.json' => null, 'style.css' => null, 'logo' => null, 'logo_name' => null];

for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if ($name === false || str_ends_with($name, '/')) {
        continue;
    }

    if ($name === 'theme.json' || $name === 'style.css') {
        $found[$name] = $zip->getFromIndex($i);
        continue;
    }

    if (preg_match('/^logo\.(' . $logoExtensionPattern . ')$/i', $name)) {
        if ($found['logo'] !== null) {
            $zip->close();
            theme_upload_fail('Zip bevat meer dan één logo-bestand — voeg er maar één toe (logo.svg, .png, .jpg of .webp).');
        }
        $found['logo'] = $zip->getFromIndex($i);
        $found['logo_name'] = $name;
        continue;
    }

    $zip->close();
    theme_upload_fail("Onverwacht bestand in de zip: \"$name\". Enkel theme.json, style.css en optioneel een logo.<ext> zijn toegestaan.");
}
$zip->close();

if ($found['theme.json'] === false || $found['theme.json'] === null) {
    theme_upload_fail('theme.json ontbreekt in de zip.');
}
if ($found['style.css'] === false || $found['style.css'] === null) {
    theme_upload_fail('style.css ontbreekt in de zip.');
}

$manifest = json_decode((string) $found['theme.json'], true);
if (!is_array($manifest)) {
    theme_upload_fail('theme.json is geen geldige JSON.');
}

$themeName = trim((string) ($manifest['name'] ?? ''));
if ($themeName === '' || mb_strlen($themeName) > 150) {
    theme_upload_fail('theme.json: "name" is verplicht (max. 150 tekens).');
}
$themeDescription = trim((string) ($manifest['description'] ?? ''));
if (mb_strlen($themeDescription) > 300) {
    theme_upload_fail('theme.json: "description" is te lang (max. 300 tekens).');
}

$rawSchemes = $manifest['schemes'] ?? null;
if (!is_array($rawSchemes) || count($rawSchemes) !== 3) {
    theme_upload_fail('theme.json: "schemes" moet een array van exact 3 kleurschema\'s zijn.');
}
$schemes = [];
$seenKeys = [];
foreach ($rawSchemes as $scheme) {
    $key = is_array($scheme) ? (string) ($scheme['key'] ?? '') : '';
    $label = is_array($scheme) ? trim((string) ($scheme['label'] ?? '')) : '';
    if (!in_array($key, ['a', 'b', 'c'], true) || isset($seenKeys[$key])) {
        theme_upload_fail('theme.json: "schemes" moet precies de sleutels a, b en c bevatten (elk één keer).');
    }
    if ($label === '' || mb_strlen($label) > 100) {
        theme_upload_fail("theme.json: label voor kleurschema \"$key\" is verplicht (max. 100 tekens).");
    }
    $seenKeys[$key] = true;
    $schemes[] = ['key' => $key, 'label' => $label];
}
usort($schemes, fn($a, $b) => $a['key'] <=> $b['key']);

$css = (string) $found['style.css'];
if ($css === '' || strlen($css) > 300 * 1024) {
    theme_upload_fail('style.css is leeg of te groot (max. 300 KB).');
}
if (!mb_check_encoding($css, 'UTF-8')) {
    theme_upload_fail('style.css is geen geldige UTF-8-tekst.');
}

$logoBytes = null;
$logoExtension = null;
if ($found['logo'] !== null && $found['logo'] !== false) {
    $logoBytes = (string) $found['logo'];
    if (strlen($logoBytes) > 2 * 1024 * 1024) {
        theme_upload_fail('Het logo-bestand is te groot (max. 2 MB).');
    }

    $logoMimeToExtension = [
        'image/svg+xml' => 'svg',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];
    $logoMime = (new finfo(FILEINFO_MIME_TYPE))->buffer($logoBytes);
    $logoExtension = $logoMimeToExtension[$logoMime] ?? null;
    if ($logoExtension === null) {
        theme_upload_fail('Het logo moet een geldige SVG, PNG, JPG of WEBP-afbeelding zijn.');
    }

    if ($logoExtension === 'svg') {
        // Not rendered for sanitization (render_logo_mark() always uses
        // <img>, so embedded <script>/event handlers never execute) —
        // this is purely a well-formedness check so a corrupt upload
        // fails clearly now instead of silently as a broken <img> later.
        $previousSetting = libxml_use_internal_errors(true);
        $svgOk = simplexml_load_string($logoBytes, 'SimpleXMLElement', LIBXML_NONET) !== false;
        libxml_use_internal_errors($previousSetting);
        if (!$svgOk) {
            theme_upload_fail('Het SVG-logo kon niet worden gelezen (ongeldige XML).');
        }
    } elseif (@getimagesize('data://image/' . $logoExtension . ';base64,' . base64_encode($logoBytes)) === false) {
        theme_upload_fail('Het logo-bestand kon niet als afbeelding worden gelezen.');
    }
}

$baseSlug = slugify($themeName);
$slug = $baseSlug;
$suffix = 2;
while (true) {
    $stmt = $mysqli->prepare('SELECT id FROM themes WHERE slug = ?');
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
    if (!$exists) {
        break;
    }
    $slug = $baseSlug . '-' . $suffix;
    $suffix++;
}

$themeDir = APP_ROOT . '/themes/' . $slug;
if (!mkdir($themeDir, 0755, true) && !is_dir($themeDir)) {
    theme_upload_fail('Kon geen map aanmaken voor het thema.');
}

file_put_contents($themeDir . '/style.css', $css);
file_put_contents($themeDir . '/theme.json', (string) $found['theme.json']);

$logoFile = null;
if ($logoBytes !== null && $logoExtension !== null) {
    $logoFile = 'logo.' . $logoExtension;
    file_put_contents($themeDir . '/' . $logoFile, $logoBytes);
}

$schemesJson = json_encode($schemes, JSON_UNESCAPED_UNICODE);
$stmt = $mysqli->prepare(
    'INSERT INTO themes (slug, name, description, logo_file, schemes, is_builtin) VALUES (?, ?, ?, ?, ?, 0)'
);
$themeDescriptionValue = $themeDescription !== '' ? $themeDescription : null;
$stmt->bind_param('sssss', $slug, $themeName, $themeDescriptionValue, $logoFile, $schemesJson);
$stmt->execute();
$stmt->close();

set_flash('success', "Thema \"$themeName\" geïnstalleerd.");
redirect('themes.php');
