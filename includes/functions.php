<?php
declare(strict_types=1);

// Shorthand for output escaping — use this around every value printed
// into HTML that did not come from a hardcoded string.
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die('Ongeldige of verlopen aanvraag. Ga terug en probeer opnieuw.');
    }
}

function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = strtr($slug, [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ij' => 'ij',
    ]);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-') ?: 'pagina';
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

// ---------------------------------------------------------------------
// Content blocks: pages are built from a JSON-encoded array of blocks
// (see database/schema.sql). Each block has a 'type' plus type-specific
// fields. Rendering only ever emits HTML built from escaped, whitelisted
// fields — never raw stored markup — so page content cannot carry XSS.
// ---------------------------------------------------------------------

const BLOCK_TYPE_LABELS = [
    'text' => 'Tekst',
    'image' => 'Foto',
    'quote' => 'Quote',
    'list' => 'Lijst',
    'buttons' => 'Knoppen',
];

const THEME_VARIANTS = [
    'a' => 'A — Helder & rustig',
    'b' => 'B — Warm & zacht',
    'c' => 'C — Natuurlijk & aards',
    'd' => 'D — Strak & minimalistisch',
];

function normalize_theme_variant(?string $variant): string
{
    return array_key_exists((string) $variant, THEME_VARIANTS) ? $variant : 'a';
}

// Only allow link/image targets that can't carry an executable scheme
// (blocks javascript:, data:, vbscript:, ...). Relative paths and the
// common safe schemes are allowed.
function is_safe_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
        return true;
    }
    return (bool) preg_match('#^(https?|mailto|tel):\S+$#i', $url);
}

function decode_blocks(?string $json): array
{
    if (!$json) {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

// Validates and cleans blocks submitted from the admin block editor.
// Returns ['blocks' => <clean array ready to store>, 'errors' => <Dutch messages>].
function sanitize_blocks(array $rawBlocks): array
{
    $clean = [];
    $errors = [];
    $count = 0;

    foreach ($rawBlocks as $raw) {
        if (!is_array($raw) || empty($raw['type']) || !is_string($raw['type'])) {
            continue;
        }

        $count++;
        if ($count > 40) {
            $errors[] = 'Een pagina mag maximaal 40 blokken bevatten.';
            break;
        }

        switch ($raw['type']) {
            case 'text':
                $heading = mb_substr(trim((string) ($raw['heading'] ?? '')), 0, 200);
                $body = mb_substr(trim((string) ($raw['body'] ?? '')), 0, 5000);
                if ($body === '') {
                    $errors[] = "Tekstblok #$count: vul inhoud in.";
                    continue 2;
                }
                $clean[] = ['type' => 'text', 'heading' => $heading, 'body' => $body];
                break;

            case 'image':
                $url = mb_substr(trim((string) ($raw['url'] ?? '')), 0, 500);
                $alt = mb_substr(trim((string) ($raw['alt'] ?? '')), 0, 200);
                $caption = mb_substr(trim((string) ($raw['caption'] ?? '')), 0, 200);
                if ($url === '' || !is_safe_url($url)) {
                    $errors[] = "Fotoblok #$count: vul een geldige afbeeldings-URL in (begin met http(s):// of /).";
                    continue 2;
                }
                if ($alt === '') {
                    $errors[] = "Fotoblok #$count: vul een korte alt-tekst in (toegankelijkheid).";
                    continue 2;
                }
                $clean[] = ['type' => 'image', 'url' => $url, 'alt' => $alt, 'caption' => $caption];
                break;

            case 'quote':
                $text = mb_substr(trim((string) ($raw['text'] ?? '')), 0, 1000);
                $source = mb_substr(trim((string) ($raw['source'] ?? '')), 0, 200);
                if ($text === '') {
                    $errors[] = "Quoteblok #$count: vul een citaat in.";
                    continue 2;
                }
                $clean[] = ['type' => 'quote', 'text' => $text, 'source' => $source];
                break;

            case 'list':
                $heading = mb_substr(trim((string) ($raw['heading'] ?? '')), 0, 200);
                $style = ($raw['style'] ?? '') === 'check' ? 'check' : 'bullet';
                $items = [];
                foreach ((array) ($raw['items'] ?? []) as $item) {
                    if (count($items) >= 20) {
                        break;
                    }
                    $item = mb_substr(trim((string) $item), 0, 300);
                    if ($item !== '') {
                        $items[] = $item;
                    }
                }
                if (empty($items)) {
                    $errors[] = "Lijstblok #$count: vul minstens één item in.";
                    continue 2;
                }
                $clean[] = ['type' => 'list', 'heading' => $heading, 'style' => $style, 'items' => $items];
                break;

            case 'buttons':
                $buttons = [];
                foreach ((array) ($raw['buttons'] ?? []) as $btn) {
                    if (count($buttons) >= 3) {
                        break;
                    }
                    $btnLabel = mb_substr(trim((string) ($btn['label'] ?? '')), 0, 60);
                    $btnUrl = mb_substr(trim((string) ($btn['url'] ?? '')), 0, 500);
                    if ($btnLabel === '' || $btnUrl === '') {
                        continue;
                    }
                    if (!is_safe_url($btnUrl)) {
                        $errors[] = "Knoppenblok #$count: ongeldige link bij knop \"$btnLabel\".";
                        continue;
                    }
                    $buttons[] = ['label' => $btnLabel, 'url' => $btnUrl];
                }
                if (empty($buttons)) {
                    $errors[] = "Knoppenblok #$count: vul minstens één knop met label en link in.";
                    continue 2;
                }
                $clean[] = ['type' => 'buttons', 'buttons' => $buttons];
                break;

            default:
                continue 2;
        }
    }

    return ['blocks' => $clean, 'errors' => $errors];
}

function render_blocks(array $blocks): string
{
    $html = '';
    foreach ($blocks as $block) {
        if (!is_array($block) || empty($block['type'])) {
            continue;
        }
        $html .= match ($block['type']) {
            'text' => render_text_block($block),
            'image' => render_image_block($block),
            'quote' => render_quote_block($block),
            'list' => render_list_block($block),
            'buttons' => render_buttons_block($block),
            default => '',
        };
    }
    return $html;
}

function render_text_block(array $block): string
{
    $heading = trim((string) ($block['heading'] ?? ''));
    $body = trim((string) ($block['body'] ?? ''));
    if ($body === '') {
        return '';
    }

    $out = '<div class="block block-text" data-animate>';
    if ($heading !== '') {
        $out .= '<h2>' . e($heading) . '</h2>';
    }
    foreach (preg_split('/\n{2,}/', $body) as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }
        $out .= '<p>' . nl2br(e($paragraph)) . '</p>';
    }
    $out .= '</div>';
    return $out;
}

function render_image_block(array $block): string
{
    $url = trim((string) ($block['url'] ?? ''));
    if ($url === '' || !is_safe_url($url)) {
        return '';
    }
    $alt = trim((string) ($block['alt'] ?? ''));
    $caption = trim((string) ($block['caption'] ?? ''));

    $out = '<figure class="block block-image" data-animate>';
    $out .= '<img src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy">';
    if ($caption !== '') {
        $out .= '<figcaption>' . e($caption) . '</figcaption>';
    }
    $out .= '</figure>';
    return $out;
}

function render_quote_block(array $block): string
{
    $text = trim((string) ($block['text'] ?? ''));
    if ($text === '') {
        return '';
    }
    $source = trim((string) ($block['source'] ?? ''));

    $out = '<blockquote class="block block-quote" data-animate>';
    $out .= '<p>' . nl2br(e($text)) . '</p>';
    if ($source !== '') {
        $out .= '<cite>' . e($source) . '</cite>';
    }
    $out .= '</blockquote>';
    return $out;
}

function render_list_block(array $block): string
{
    $items = array_values(array_filter(
        array_map(fn($item) => trim((string) $item), (array) ($block['items'] ?? [])),
        fn($item) => $item !== ''
    ));
    if (empty($items)) {
        return '';
    }
    $heading = trim((string) ($block['heading'] ?? ''));
    $listClass = ($block['style'] ?? '') === 'check' ? 'list-check' : 'list-bullet';

    $out = '<div class="block block-list" data-animate>';
    if ($heading !== '') {
        $out .= '<h3>' . e($heading) . '</h3>';
    }
    $out .= '<ul class="' . $listClass . '">';
    foreach ($items as $item) {
        $out .= '<li>' . e($item) . '</li>';
    }
    $out .= '</ul></div>';
    return $out;
}

function render_buttons_block(array $block): string
{
    $buttons = (array) ($block['buttons'] ?? []);
    $inner = '';
    $rendered = 0;
    foreach ($buttons as $btn) {
        $label = trim((string) ($btn['label'] ?? ''));
        $url = trim((string) ($btn['url'] ?? ''));
        if ($label === '' || $url === '' || !is_safe_url($url)) {
            continue;
        }
        $variant = $rendered === 0 ? 'btn-primary' : 'btn-secondary';
        $inner .= '<a class="btn ' . $variant . '" href="' . e($url) . '">' . e($label) . '</a>';
        $rendered++;
    }
    if ($rendered === 0) {
        return '';
    }
    return '<div class="block block-buttons" data-animate>' . $inner . '</div>';
}

// ---------------------------------------------------------------------
// Upload cleanup: images live in public/uploads/ and are only ever
// referenced by URL from inside a page's blocks JSON — there is no
// foreign key. So "is this file still needed" is answered by re-scanning
// every page's current content rather than maintaining a reference count.
// ---------------------------------------------------------------------

function extract_upload_urls(array $blocks): array
{
    $urls = [];
    foreach ($blocks as $block) {
        if (is_array($block) && ($block['type'] ?? '') === 'image') {
            $url = (string) ($block['url'] ?? '');
            if (str_starts_with($url, '/uploads/')) {
                $urls[] = $url;
            }
        }
    }
    return $urls;
}

function all_referenced_upload_urls(mysqli $mysqli): array
{
    $referenced = [];
    $result = $mysqli->query('SELECT content FROM pages');
    while ($row = $result->fetch_assoc()) {
        foreach (extract_upload_urls(decode_blocks($row['content'])) as $url) {
            $referenced[$url] = true;
        }
    }
    return $referenced;
}

// Deletes any of $candidateUrls that are no longer referenced by any page.
// Call this AFTER the database change that might have dropped a reference
// (page save/delete) has already been committed, so the scan above is
// accurate.
function delete_orphaned_uploads(mysqli $mysqli, array $candidateUrls): void
{
    $candidateUrls = array_unique(array_filter($candidateUrls));
    if (empty($candidateUrls)) {
        return;
    }

    $referenced = all_referenced_upload_urls($mysqli);

    foreach ($candidateUrls as $url) {
        if (isset($referenced[$url])) {
            continue;
        }

        // Only ever delete a flat file directly under uploads/ — guards
        // against a manually-typed URL (this field also accepts free text)
        // containing "../" or extra path segments.
        $filename = basename($url);
        if ($filename === '' || '/uploads/' . $filename !== $url) {
            continue;
        }

        $path = APP_ROOT . '/public/uploads/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

// ---------------------------------------------------------------------
// SEO / discoverability: canonical URLs, Open Graph + Twitter card tags,
// and minimal, honest JSON-LD (only fields we actually have data for —
// never fabricated business details like address/phone).
// ---------------------------------------------------------------------

function absolute_url(string $siteUrl, string $path): string
{
    return rtrim($siteUrl, '/') . '/' . ltrim($path, '/');
}

// Like absolute_url(), but for values that might already be a full external
// URL (an image block's url can be either an /uploads/... path or an
// external http(s):// link) — never double-prefixes an already-absolute URL.
function media_absolute_url(string $siteUrl, string $url): string
{
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    return absolute_url($siteUrl, $url);
}

function first_image_url(array $blocks): ?string
{
    foreach ($blocks as $block) {
        if (is_array($block) && ($block['type'] ?? '') === 'image') {
            $url = trim((string) ($block['url'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }
    }
    return null;
}

function organization_schema(string $siteName, string $siteUrl): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $siteName,
        'url' => $siteUrl,
    ];
}

function webpage_schema(string $name, ?string $description, ?string $url): array
{
    $schema = ['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $name];
    if (!empty($description)) {
        $schema['description'] = $description;
    }
    if (!empty($url)) {
        $schema['url'] = $url;
    }
    return $schema;
}
