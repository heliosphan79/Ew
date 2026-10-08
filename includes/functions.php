<?php
declare(strict_types=1);

// Shorthand for output escaping — use this around every value printed
// into HTML that did not come from a hardcoded string.
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Renders the site name with its first hyphen styled in the accent color
// (matching the "Eigen-Wijzer" logo lockup) — falls back to plain escaped
// text if the configured site name has no hyphen to style.
function render_site_wordmark(string $name): string
{
    $pos = strpos($name, '-');
    if ($pos === false) {
        return e($name);
    }
    return e(substr($name, 0, $pos)) . '<span class="ew-logo-accent">-</span>' . e(substr($name, $pos + 1));
}

// The small diagonal "needle" icon used for both nav-panel items and bullet
// list items — echoes the logo's compass needle at the same -38° angle,
// straightening to 0° on hover (see .ew-needle-diag in style.css).
function render_needle_icon(): string
{
    return '<svg class="ew-needle-icon ew-needle-diag" width="20" height="20" viewBox="0 0 20 20" aria-hidden="true">'
        . '<polygon class="ew-needle-a" points="1,10 10,6 10,14"></polygon>'
        . '<polygon class="ew-needle-b" points="10,6 19,10 10,14"></polygon>'
        . '</svg>';
}

// Small outline icons for the public footer's contact details (address/
// phone/email) — same minimal line-icon style as the admin set below, kept
// as its own constant since the two icon sets serve different pages and
// have no reason to share a name->path mapping. $name must be a hardcoded
// literal at every call site, never user input.
const FOOTER_ICON_PATHS = [
    'pin' => '<path d="M12 21s7-7.58 7-12a7 7 0 1 0-14 0c0 4.42 7 12 7 12z"></path><circle cx="12" cy="9" r="2.5"></circle>',
    'phone' => '<path d="M4.5 4h4l2 5-2.5 1.5a11 11 0 0 0 5.5 5.5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 2.5 6a2 2 0 0 1 2-2z"></path>',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path>',
];

function footer_icon(string $name): string
{
    $path = FOOTER_ICON_PATHS[$name] ?? '';
    return '<svg class="footer-contact-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

// Generic outline UI icons for the admin panel (nav, buttons, table row
// actions) — a small, hand-drawn set (not a font/library dependency),
// deliberately distinct from the public site's branded needle icon above.
// $name must be a hardcoded literal at every call site, never user input.
const ADMIN_ICON_PATHS = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect>',
    'pages' => '<path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"></path><path d="M15 2v5h5"></path><path d="M8 13h8M8 17h8M8 9h4"></path>',
    'inbox' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path>',
    'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M3 9h18M8 3v4M16 3v4"></path>',
    'users' => '<circle cx="9" cy="8" r="3.25"></circle><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"></path><path d="M16.5 4.5a3.25 3.25 0 0 1 0 6.3M21 20c0-2.8-1.9-5.1-4.5-5.8"></path>',
    'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"></path>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path>',
    'edit' => '<path d="M4 20h4L19.5 8.5a2 2 0 0 0 0-2.8l-1.2-1.2a2 2 0 0 0-2.8 0L4 16v4z"></path><path d="M13.5 5.5l3 3"></path>',
    'trash' => '<path d="M4 7h16"></path><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"></path><path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"></path><path d="M10 11v6M14 11v6"></path>',
    'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle>',
    'plus' => '<path d="M12 5v14M5 12h14"></path>',
    'check' => '<path d="M5 13l4 4L19 7"></path>',
    'undo' => '<path d="M3 12a9 9 0 1 0 2.6-6.4"></path><path d="M3 4v5h5"></path>',
    'chart' => '<path d="M4 20V10M12 20V4M20 20v-7"></path>',
    'chevron-right' => '<path d="M9 6l6 6-6 6"></path>',
    'mail' => '<path d="M22 2L11 13"></path><path d="M22 2l-7 20-4-9-9-4 20-7z"></path>',
    'upload' => '<path d="M12 16V4"></path><path d="M6 10l6-6 6 6"></path><path d="M4 20h16"></path>',
    'copy' => '<rect x="8" y="8" width="13" height="13" rx="2"></rect><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"></path>',
];

function admin_icon(string $name, string $class = ''): string
{
    $path = ADMIN_ICON_PATHS[$name] ?? '';
    $classAttr = 'admin-icon' . ($class !== '' ? ' ' . $class : '');
    return '<svg class="' . $classAttr . '" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
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

// Verifies a reCAPTCHA v3 token server-side. Returns true when not
// configured at all (secret key empty) so the form still works before
// reCAPTCHA is set up — this is on top of, not instead of, the honeypot/
// timing/rate-limit checks in contact.php.
function recaptcha_verify(string $secretKey, string $token, string $remoteIp, float $minScore = 0.5): bool
{
    if ($secretKey === '') {
        return true;
    }
    if ($token === '') {
        return false;
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($response) || $httpCode !== 200) {
        // Google unreachable: fail open rather than blocking every visitor
        // because of a network hiccup on Google's end — the other spam
        // checks still apply.
        return true;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['success'])) {
        return false;
    }

    return (float) ($data['score'] ?? 0) >= $minScore;
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
    'calendar' => 'Kalender',
    'events' => 'Evenementen',
    'map' => 'Kaart',
    'media_text' => 'Foto + tekst',
    'columns' => 'Kolommen',
];

// Block types allowed inside a columns-block's column. Deliberately a
// narrow subset — no calendar/events/map/media_text/columns — to avoid
// runaway nesting in both the editor UI and the rendering.
const COLUMN_CHILD_TYPES = ['text', 'image', 'quote', 'list', 'buttons'];

const THEME_VARIANTS = [
    'a' => 'A — Crème, salie & terracotta',
    'b' => 'B — Koraal',
    'c' => 'C — Salie-groen',
];

function normalize_theme_variant(?string $variant): string
{
    return array_key_exists((string) $variant, THEME_VARIANTS) ? $variant : 'a';
}

// Optional background treatment, settable on any block (and individually
// per column of a columns-block). 'accent'/'surface' use the theme's own
// tokens so they re-skin per variant rather than a fixed color.
function sanitize_block_background(mixed $raw): string
{
    $value = is_string($raw) ? $raw : '';
    return in_array($value, ['accent', 'surface'], true) ? $value : 'none';
}

function block_bg_class(string $background): string
{
    return $background === 'none' ? '' : ' block-bg-' . $background;
}

// Bewaartermijn voor contactberichten/evenement-inschrijvingen — een vaste
// keuzelijst in plaats van een vrij getal, zodat een admin nooit per
// ongeluk een extreem korte termijn ingeeft. null = voor altijd bewaren.
const SUBMISSION_RETENTION_OPTIONS = [90, 180, 365, 730];

function sanitize_retention_days(mixed $raw): ?int
{
    $value = is_numeric($raw) ? (int) $raw : null;
    return in_array($value, SUBMISSION_RETENTION_OPTIONS, true) ? $value : null;
}

// Horizontal alignment for the Buttons block (left/center/right) — used
// both standalone and as a column child, see buttons_align_class().
function sanitize_block_align(mixed $raw): string
{
    $value = is_string($raw) ? $raw : '';
    return in_array($value, ['center', 'right'], true) ? $value : 'left';
}

function buttons_align_class(string $align): string
{
    return $align === 'left' ? '' : ' buttons-align-' . $align;
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

// Minimal, safe inline markup for free-text fields: **vet**, *cursief*,
// [label](url). Escapes the raw text FIRST and only then turns the markup
// into real tags on the escaped result, so literal <, >, & typed by an
// editor can never become live HTML — only the tags this function itself
// inserts exist in the output. Links reuse is_safe_url(), same as image
// and button links, so javascript:/data: etc. can't sneak in here either.
function render_inline_markup(string $text): string
{
    $escaped = e($text);

    $escaped = preg_replace_callback('/\[([^\[\]]+)\]\(([^()\s]+)\)/', function (array $m): string {
        $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if (!is_safe_url($url)) {
            return $m[0];
        }
        return '<a href="' . e($url) . '">' . $m[1] . '</a>';
    }, $escaped) ?? $escaped;

    // Bold before italic, so **x** isn't eaten by the single-asterisk
    // italic pattern first.
    $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\*([^*\n]+)\*/', '<em>$1</em>', $escaped) ?? $escaped;

    return $escaped;
}

// Shared by every free-text body field (text/media_text blocks, a column's
// text child): splits on blank lines into paragraphs, and — same typed-
// syntax principle as **vet**/*cursief* — turns a paragraph where every
// line starts with "- " into a bulleted list (styled like the dedicated
// List block's naald-bullets) instead of a <p>. Mixed paragraphs (only
// some lines prefixed) are left as plain text, "- " included, so existing
// content with a literal leading hyphen never changes appearance.
function render_text_paragraphs(string $body): string
{
    $out = '';
    foreach (preg_split('/\n{2,}/', $body) as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }

        $lines = preg_split('/\n/', $paragraph);
        $items = [];
        $isList = true;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!str_starts_with($line, '- ')) {
                $isList = false;
                break;
            }
            $items[] = trim(substr($line, 2));
        }

        if ($isList && !empty($items)) {
            $out .= '<ul class="list-bullet text-list">';
            foreach ($items as $item) {
                $out .= '<li>' . render_needle_icon() . '<span>' . render_inline_markup($item) . '</span></li>';
            }
            $out .= '</ul>';
        } else {
            $out .= '<p>' . nl2br(render_inline_markup($paragraph)) . '</p>';
        }
    }
    return $out;
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
                $eyebrow = mb_substr(trim((string) ($raw['eyebrow'] ?? '')), 0, 80);
                $heading = mb_substr(trim((string) ($raw['heading'] ?? '')), 0, 200);
                $body = mb_substr(trim((string) ($raw['body'] ?? '')), 0, 5000);
                if ($body === '' && $heading === '') {
                    $errors[] = "Tekstblok #$count: vul een titel of inhoud in.";
                    continue 2;
                }
                $clean[] = ['type' => 'text', 'eyebrow' => $eyebrow, 'heading' => $heading, 'body' => $body, 'background' => sanitize_block_background($raw['background'] ?? '')];
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
                $clean[] = ['type' => 'image', 'url' => $url, 'alt' => $alt, 'caption' => $caption, 'background' => sanitize_block_background($raw['background'] ?? '')];
                break;

            case 'quote':
                $text = mb_substr(trim((string) ($raw['text'] ?? '')), 0, 1000);
                $source = mb_substr(trim((string) ($raw['source'] ?? '')), 0, 200);
                if ($text === '') {
                    $errors[] = "Quoteblok #$count: vul een citaat in.";
                    continue 2;
                }
                $clean[] = ['type' => 'quote', 'text' => $text, 'source' => $source, 'background' => sanitize_block_background($raw['background'] ?? '')];
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
                $clean[] = ['type' => 'list', 'heading' => $heading, 'style' => $style, 'items' => $items, 'background' => sanitize_block_background($raw['background'] ?? '')];
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
                $clean[] = [
                    'type' => 'buttons',
                    'buttons' => $buttons,
                    'align' => sanitize_block_align($raw['align'] ?? ''),
                    'background' => sanitize_block_background($raw['background'] ?? ''),
                ];
                break;

            case 'calendar':
                // Available slots live in calendar_slots, managed globally via
                // admin/calendar.php — this block only carries an optional heading.
                $heading = mb_substr(trim((string) ($raw['heading'] ?? '')), 0, 200);
                $clean[] = ['type' => 'calendar', 'heading' => $heading, 'background' => sanitize_block_background($raw['background'] ?? '')];
                break;

            case 'events':
                // Events themselves live in the events table, managed via
                // admin/events.php — this block only carries an optional heading.
                $heading = mb_substr(trim((string) ($raw['heading'] ?? '')), 0, 200);
                $clean[] = ['type' => 'events', 'heading' => $heading, 'background' => sanitize_block_background($raw['background'] ?? '')];
                break;

            case 'newsletter_events':
                // Newsletter-only compact events overview (see
                // NEWSLETTER_BLOCK_TYPES/render_newsletter_events_block) — a
                // separate type from 'events' because registration always
                // happens on the website, never inside the e-mail itself, so
                // this carries an optional single button (label+url) instead
                // of the page block's inline registration form.
                $heading = mb_substr(trim((string) ($raw['heading'] ?? '')), 0, 200);
                $linkLabel = mb_substr(trim((string) ($raw['link_label'] ?? '')), 0, 60);
                $linkUrl = mb_substr(trim((string) ($raw['link_url'] ?? '')), 0, 500);
                if ($linkUrl !== '' && !is_safe_url($linkUrl)) {
                    $errors[] = "Evenementenblok #$count: ongeldige link.";
                    continue 2;
                }
                if ($linkUrl === '') {
                    $linkLabel = '';
                }
                $clean[] = [
                    'type' => 'newsletter_events',
                    'heading' => $heading,
                    'link_label' => $linkLabel,
                    'link_url' => $linkUrl,
                    'background' => sanitize_block_background($raw['background'] ?? ''),
                ];
                break;

            case 'map':
                $address = mb_substr(trim((string) ($raw['address'] ?? '')), 0, 255);
                $heading = mb_substr(trim((string) ($raw['heading'] ?? '')), 0, 200);
                $layout = ($raw['layout'] ?? '') === 'stretch' ? 'stretch' : 'box';
                if ($address === '') {
                    $errors[] = "Kaartblok #$count: vul het adres van de praktijk in.";
                    continue 2;
                }
                $clean[] = ['type' => 'map', 'address' => $address, 'heading' => $heading, 'layout' => $layout, 'background' => sanitize_block_background($raw['background'] ?? '')];
                break;

            case 'media_text':
                $url = mb_substr(trim((string) ($raw['url'] ?? '')), 0, 500);
                $alt = mb_substr(trim((string) ($raw['alt'] ?? '')), 0, 200);
                $caption = mb_substr(trim((string) ($raw['caption'] ?? '')), 0, 200);
                $heading = mb_substr(trim((string) ($raw['heading'] ?? '')), 0, 200);
                $body = mb_substr(trim((string) ($raw['body'] ?? '')), 0, 3000);
                $imagePosition = ($raw['image_position'] ?? '') === 'right' ? 'right' : 'left';
                if ($url === '' || !is_safe_url($url)) {
                    $errors[] = "Foto+tekstblok #$count: vul een geldige afbeeldings-URL in (begin met http(s):// of /).";
                    continue 2;
                }
                if ($alt === '') {
                    $errors[] = "Foto+tekstblok #$count: vul een korte alt-tekst in (toegankelijkheid).";
                    continue 2;
                }
                if ($body === '' && $heading === '') {
                    $errors[] = "Foto+tekstblok #$count: vul een titel of tekst in.";
                    continue 2;
                }
                $clean[] = [
                    'type' => 'media_text',
                    'url' => $url,
                    'alt' => $alt,
                    'caption' => $caption,
                    'heading' => $heading,
                    'body' => $body,
                    'image_position' => $imagePosition,
                    'background' => sanitize_block_background($raw['background'] ?? ''),
                ];
                break;

            case 'columns':
                $columnCount = (int) ($raw['column_count'] ?? 2);
                $columnCount = in_array($columnCount, [2, 3], true) ? $columnCount : 2;
                $rawColumns = array_values((array) ($raw['columns'] ?? []));
                $rawColumnBackgrounds = array_values((array) ($raw['column_backgrounds'] ?? []));
                $columns = [];
                $columnBackgrounds = [];
                for ($col = 0; $col < $columnCount; $col++) {
                    $rawChildren = is_array($rawColumns[$col] ?? null) ? $rawColumns[$col] : [];
                    $children = [];
                    foreach ($rawChildren as $child) {
                        if (count($children) >= 10) {
                            break;
                        }
                        if (!is_array($child) || empty($child['type']) || !is_string($child['type'])) {
                            continue;
                        }
                        $cleanChild = sanitize_column_child($child['type'], $child);
                        if ($cleanChild !== null) {
                            $children[] = $cleanChild;
                        }
                    }
                    $columns[] = $children;
                    $columnBackgrounds[] = sanitize_block_background($rawColumnBackgrounds[$col] ?? '');
                }
                $hasContent = array_filter($columns, fn($children) => !empty($children));
                if (empty($hasContent)) {
                    $errors[] = "Kolommenblok #$count: voeg minstens één blok toe aan een kolom.";
                    continue 2;
                }
                $clean[] = [
                    'type' => 'columns',
                    'column_count' => $columnCount,
                    'columns' => $columns,
                    'column_backgrounds' => $columnBackgrounds,
                    'background' => sanitize_block_background($raw['background'] ?? ''),
                ];
                break;

            default:
                continue 2;
        }
    }

    return ['blocks' => $clean, 'errors' => $errors];
}

// Validates one block inside a columns-block's column. Mirrors the
// relevant case in sanitize_blocks() for the types COLUMN_CHILD_TYPES
// allows, but — unlike the top-level editor — drops invalid entries
// silently instead of raising a page-level error, same as an unknown
// top-level block type would be dropped.
function sanitize_column_child(string $type, array $raw): ?array
{
    if (!in_array($type, COLUMN_CHILD_TYPES, true)) {
        return null;
    }

    switch ($type) {
        case 'text':
            $eyebrow = mb_substr(trim((string) ($raw['eyebrow'] ?? '')), 0, 80);
            $heading = mb_substr(trim((string) ($raw['heading'] ?? '')), 0, 200);
            $body = mb_substr(trim((string) ($raw['body'] ?? '')), 0, 5000);
            if ($body === '' && $heading === '') {
                return null;
            }
            return ['type' => 'text', 'eyebrow' => $eyebrow, 'heading' => $heading, 'body' => $body];

        case 'image':
            $url = mb_substr(trim((string) ($raw['url'] ?? '')), 0, 500);
            $alt = mb_substr(trim((string) ($raw['alt'] ?? '')), 0, 200);
            $caption = mb_substr(trim((string) ($raw['caption'] ?? '')), 0, 200);
            if ($url === '' || !is_safe_url($url) || $alt === '') {
                return null;
            }
            return ['type' => 'image', 'url' => $url, 'alt' => $alt, 'caption' => $caption];

        case 'quote':
            $text = mb_substr(trim((string) ($raw['text'] ?? '')), 0, 1000);
            $source = mb_substr(trim((string) ($raw['source'] ?? '')), 0, 200);
            if ($text === '') {
                return null;
            }
            return ['type' => 'quote', 'text' => $text, 'source' => $source];

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
                return null;
            }
            return ['type' => 'list', 'heading' => $heading, 'style' => $style, 'items' => $items];

        case 'buttons':
            $buttons = [];
            foreach ((array) ($raw['buttons'] ?? []) as $btn) {
                if (count($buttons) >= 3) {
                    break;
                }
                $btnLabel = mb_substr(trim((string) ($btn['label'] ?? '')), 0, 60);
                $btnUrl = mb_substr(trim((string) ($btn['url'] ?? '')), 0, 500);
                if ($btnLabel === '' || $btnUrl === '' || !is_safe_url($btnUrl)) {
                    continue;
                }
                $buttons[] = ['label' => $btnLabel, 'url' => $btnUrl];
            }
            if (empty($buttons)) {
                return null;
            }
            return ['type' => 'buttons', 'buttons' => $buttons, 'align' => sanitize_block_align($raw['align'] ?? '')];

        default:
            return null;
    }
}

function render_blocks(array $blocks, mysqli $mysqli): string
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
            'calendar' => render_calendar_block($block),
            'events' => render_events_block($block, $mysqli),
            'map' => render_map_block($block),
            'media_text' => render_media_text_block($block),
            'columns' => render_columns_block($block),
            default => '',
        };
    }
    return $html;
}

function render_text_block(array $block): string
{
    $eyebrow = trim((string) ($block['eyebrow'] ?? ''));
    $heading = trim((string) ($block['heading'] ?? ''));
    $body = trim((string) ($block['body'] ?? ''));
    if ($body === '' && $heading === '') {
        return '';
    }
    $bgClass = block_bg_class(sanitize_block_background($block['background'] ?? ''));

    $out = '<div class="block block-text' . $bgClass . '" data-animate>';
    if ($eyebrow !== '') {
        $out .= '<span class="eyebrow">' . e($eyebrow) . '</span>';
    }
    if ($heading !== '') {
        $out .= '<h2>' . e($heading) . '</h2>';
    }
    $out .= render_text_paragraphs($body);
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
    $bgClass = block_bg_class(sanitize_block_background($block['background'] ?? ''));

    $out = '<figure class="block block-image' . $bgClass . '" data-animate>';
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
    $bgClass = block_bg_class(sanitize_block_background($block['background'] ?? ''));

    $out = '<blockquote class="block block-quote' . $bgClass . '" data-animate>';
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
    $bgClass = block_bg_class(sanitize_block_background($block['background'] ?? ''));

    $out = '<div class="block block-list' . $bgClass . '" data-animate>';
    if ($heading !== '') {
        $out .= '<h3>' . e($heading) . '</h3>';
    }
    $out .= '<ul class="' . $listClass . '">';
    foreach ($items as $item) {
        $icon = $listClass === 'list-bullet' ? render_needle_icon() : '';
        $out .= '<li>' . $icon . '<span>' . render_inline_markup($item) . '</span></li>';
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
    $bgClass = block_bg_class(sanitize_block_background($block['background'] ?? ''));
    $alignClass = buttons_align_class(sanitize_block_align($block['align'] ?? ''));
    return '<div class="block block-buttons' . $bgClass . $alignClass . '" data-animate>' . $inner . '</div>';
}

// Plain Google Maps iframe embed (https://www.google.com/maps?q=...&output=embed)
// — no API key, no JS SDK, no Google Cloud account needed. $address drives the
// query, so the practice's location never has to be looked up/hardcoded as coordinates.
function render_map_block(array $block): string
{
    $address = trim((string) ($block['address'] ?? ''));
    if ($address === '') {
        return '';
    }
    $heading = trim((string) ($block['heading'] ?? ''));
    $layout = ($block['layout'] ?? '') === 'stretch' ? 'stretch' : 'box';
    $embedUrl = 'https://www.google.com/maps?q=' . urlencode($address) . '&output=embed';
    $bgClass = block_bg_class(sanitize_block_background($block['background'] ?? ''));

    $out = '<div class="block block-map map-' . $layout . $bgClass . '" data-animate>';
    if ($heading !== '') {
        $out .= '<h2>' . e($heading) . '</h2>';
    }
    $out .= '<div class="map-frame"><iframe src="' . e($embedUrl) . '" title="' . e($address) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
    $out .= '</div>';
    return $out;
}

function render_media_text_block(array $block): string
{
    $url = trim((string) ($block['url'] ?? ''));
    if ($url === '' || !is_safe_url($url)) {
        return '';
    }
    $alt = trim((string) ($block['alt'] ?? ''));
    $heading = trim((string) ($block['heading'] ?? ''));
    $body = trim((string) ($block['body'] ?? ''));
    if ($heading === '' && $body === '') {
        return '';
    }
    $caption = trim((string) ($block['caption'] ?? ''));
    $position = ($block['image_position'] ?? '') === 'right' ? 'right' : 'left';
    $bgClass = block_bg_class(sanitize_block_background($block['background'] ?? ''));

    $out = '<div class="block block-media-text media-text-' . $position . $bgClass . '" data-animate>';
    $out .= '<figure class="media-text-figure">';
    $out .= '<img src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy">';
    if ($caption !== '') {
        $out .= '<figcaption>' . e($caption) . '</figcaption>';
    }
    $out .= '</figure>';
    $out .= '<div class="media-text-content">';
    if ($heading !== '') {
        $out .= '<h2>' . e($heading) . '</h2>';
    }
    $out .= render_text_paragraphs($body);
    $out .= '</div></div>';
    return $out;
}

// Renders one child block as one or more ".col-row" cells instead of the
// usual ".block" wrapper, so each piece becomes its own CSS grid row
// inside its column. Columns then share row tracks via CSS subgrid (see
// style.css), so e.g. a longer heading in one column pushes that row's
// height for every column equally — bodies/buttons across columns start
// at the same height regardless of how each column's own heading wraps.
// text splits into a heading-row and a body-row (either may be absent);
// buttons becomes its own row; image/quote/list stay a single row,
// reusing their normal render_*_block() output as-is.
function render_column_child_rows(array $child): string
{
    if (!is_array($child) || empty($child['type'])) {
        return '';
    }

    switch ($child['type']) {
        case 'text':
            $eyebrow = trim((string) ($child['eyebrow'] ?? ''));
            $heading = trim((string) ($child['heading'] ?? ''));
            $body = trim((string) ($child['body'] ?? ''));
            if ($heading === '' && $body === '') {
                return '';
            }
            $out = '';
            if ($eyebrow !== '' || $heading !== '') {
                $out .= '<div class="col-row col-row-heading">';
                if ($eyebrow !== '') {
                    $out .= '<span class="eyebrow">' . e($eyebrow) . '</span>';
                }
                if ($heading !== '') {
                    $out .= '<h3>' . e($heading) . '</h3>';
                }
                $out .= '</div>';
            }
            if ($body !== '') {
                $out .= '<div class="col-row col-row-body">' . render_text_paragraphs($body) . '</div>';
            }
            return $out;

        case 'buttons':
            $buttons = (array) ($child['buttons'] ?? []);
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
            $alignClass = buttons_align_class(sanitize_block_align($child['align'] ?? ''));
            return '<div class="col-row col-row-buttons' . $alignClass . '">' . $inner . '</div>';

        case 'image':
            $html = render_image_block($child);
            return $html !== '' ? '<div class="col-row">' . $html . '</div>' : '';

        case 'quote':
            $html = render_quote_block($child);
            return $html !== '' ? '<div class="col-row">' . $html . '</div>' : '';

        case 'list':
            $html = render_list_block($child);
            return $html !== '' ? '<div class="col-row">' . $html . '</div>' : '';

        default:
            return '';
    }
}

function render_columns_block(array $block): string
{
    $columnCount = in_array($block['column_count'] ?? 2, [2, 3], true) ? $block['column_count'] : 2;
    $columnBackgrounds = (array) ($block['column_backgrounds'] ?? []);
    $columns = [];
    $hasContent = false;
    $anyColumnBg = false;

    foreach (array_values((array) ($block['columns'] ?? [])) as $i => $children) {
        $inner = '';
        foreach ((array) $children as $child) {
            $inner .= render_column_child_rows($child);
        }
        if ($inner !== '') {
            $hasContent = true;
        }
        $colBg = sanitize_block_background($columnBackgrounds[$i] ?? '');
        if ($colBg !== 'none') {
            $anyColumnBg = true;
        }
        $columns[] = ['bg' => $colBg, 'html' => $inner];
    }

    if (!$hasContent) {
        return '';
    }

    // When at least one column carries a background, every column (even the
    // plain ones) gets the same padding — otherwise only the "card" column's
    // content is inset, which desyncs its row starts from its siblings under
    // the subgrid row alignment (see CSS .has-column-bg).
    $columnsClass = $anyColumnBg ? ' has-column-bg' : '';
    $renderedColumns = '';
    foreach ($columns as $col) {
        $renderedColumns .= '<div class="column' . block_bg_class($col['bg']) . '">' . $col['html'] . '</div>';
    }

    $bgClass = block_bg_class(sanitize_block_background($block['background'] ?? ''));
    return '<div class="block block-columns columns-' . $columnCount . $columnsClass . $bgClass . '" data-animate>' . $renderedColumns . '</div>';
}

// Available slots are shared, global data (calendar_slots), managed in
// admin/calendar.php — not part of the block content. This just renders
// the mount point; assets/js/calendar-block.js fetches availability
// and handles the booking flow against calendar-availability.php /
// book-slot.php, using the CSRF token embedded below.
function render_calendar_block(array $block): string
{
    static $instance = 0;
    $instance++;

    $heading = trim((string) ($block['heading'] ?? ''));
    $bgClass = block_bg_class(sanitize_block_background($block['background'] ?? ''));

    $out = '<div class="block block-calendar' . $bgClass . '" data-animate>';
    if ($heading !== '') {
        $out .= '<h2>' . e($heading) . '</h2>';
    }
    $out .= '<div class="calendar-widget" id="calendar-block-' . $instance . '" data-calendar-widget data-csrf="' . e(csrf_token()) . '">';
    $out .= '<p class="calendar-loading">Beschikbare momenten laden…</p>';
    $out .= '</div>';
    $out .= '</div>';
    return $out;
}

function format_event_date(string $date, ?string $time): string
{
    $months = [
        'januari', 'februari', 'maart', 'april', 'mei', 'juni',
        'juli', 'augustus', 'september', 'oktober', 'november', 'december',
    ];
    $ts = strtotime($date);
    $formatted = (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    if (!empty($time)) {
        $formatted .= ' om ' . substr($time, 0, 5);
    }
    return $formatted;
}

// Only a same-site, relative path is accepted as a post-registration
// redirect target — never a scheme or protocol-relative ("//host/...")
// URL, so event-register.php can't be used as an open redirect.
function is_safe_redirect_path(string $path): bool
{
    return str_starts_with($path, '/') && !str_starts_with($path, '//');
}

// Events themselves live in the events table, managed via admin/events.php
// — this renders the next few upcoming, published events with an inline
// registration form (no JS needed: a <details> toggle + a plain POST back
// to event-register.php, same pattern as the contact form).
function render_events_block(array $block, mysqli $mysqli): string
{
    $heading = trim((string) ($block['heading'] ?? ''));

    $stmt = $mysqli->prepare(
        'SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id) AS registered_count
         FROM events e
         WHERE e.published = 1 AND e.event_date >= CURDATE()
         ORDER BY e.event_date ASC, e.event_time ASC
         LIMIT 6'
    );
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($events)) {
        return '';
    }

    $redirectTarget = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if (!is_safe_redirect_path($redirectTarget)) {
        $redirectTarget = '/';
    }

    $bgClass = block_bg_class(sanitize_block_background($block['background'] ?? ''));
    $out = '<div class="block block-events' . $bgClass . '" data-animate>';
    if ($heading !== '') {
        $out .= '<h2>' . e($heading) . '</h2>';
    }
    $out .= '<div class="events-list">';

    foreach ($events as $event) {
        $eventId = (int) $event['id'];
        $capacity = $event['capacity'] !== null ? (int) $event['capacity'] : null;
        $registered = (int) $event['registered_count'];
        $spotsLeft = $capacity !== null ? max(0, $capacity - $registered) : null;
        $isFull = $capacity !== null && $spotsLeft <= 0;

        $out .= '<article class="event-card">';
        $out .= '<span class="eyebrow">' . e(format_event_date($event['event_date'], $event['event_time'])) . '</span>';
        $out .= '<h3>' . e($event['title']) . '</h3>';
        if (!empty($event['location'])) {
            $out .= '<p class="event-location">' . e($event['location']) . '</p>';
        }
        $out .= '<p class="event-description">' . nl2br(e($event['description'])) . '</p>';

        if ($capacity !== null) {
            $out .= $isFull
                ? '<span class="event-badge badge-full">Volzet</span>'
                : '<span class="event-badge badge-spots">' . $spotsLeft . ' van de ' . $capacity . ' plaatsen vrij</span>';
        }

        if (!$isFull) {
            $out .= '<details class="event-register">';
            $out .= '<summary>Schrijf je in</summary>';
            $out .= '<form method="post" action="/event-register.php">';
            $out .= csrf_field();
            $out .= '<input type="hidden" name="event_id" value="' . $eventId . '">';
            $out .= '<input type="hidden" name="redirect" value="' . e($redirectTarget) . '">';
            $out .= '<div style="position:absolute;left:-9999px;" aria-hidden="true">';
            $out .= '<label for="event-website-' . $eventId . '">Website</label>';
            $out .= '<input type="text" id="event-website-' . $eventId . '" name="website" tabindex="-1" autocomplete="off">';
            $out .= '</div>';
            $out .= '<label for="event-name-' . $eventId . '">Naam</label>';
            $out .= '<input type="text" id="event-name-' . $eventId . '" name="name" required>';
            $out .= '<label for="event-email-' . $eventId . '">E-mailadres</label>';
            $out .= '<input type="email" id="event-email-' . $eventId . '" name="email" required>';
            $out .= '<label class="form-checkbox-row" for="event-newsletter-' . $eventId . '">';
            $out .= '<input type="checkbox" id="event-newsletter-' . $eventId . '" name="newsletter_optin" value="1">';
            $out .= '<span>Ja, ik wil graag de nieuwsbrief ontvangen.</span>';
            $out .= '</label>';
            $out .= '<button type="submit">Inschrijven</button>';
            $out .= '</form>';
            $out .= '</details>';
        }

        $eventJsonLd = event_schema($event);
        if ($eventJsonLd !== null) {
            $out .= '<script type="application/ld+json">' . json_encode($eventJsonLd, JSON_UNESCAPED_SLASHES) . '</script>';
        }

        $out .= '</article>';
    }

    $out .= '</div></div>';
    return $out;
}

// ---------------------------------------------------------------------
// Upload processing: downsizes and re-compresses an uploaded photo before
// it's written to uploads/, so a straight-from-the-phone multi-MB original
// doesn't get served to every visitor at full resolution. GIF is left
// untouched — GD would flatten an animated GIF to its first frame, which
// is worse than not resizing it. Caps the longest side at $maxDimension
// and never upscales a smaller image. Returns false (caller then falls
// back to storing the original) if GD is unavailable or the file can't be
// decoded, so a host without GD still gets a working, just-unresized
// upload rather than a hard failure.
// ---------------------------------------------------------------------

function resize_uploaded_image(string $sourcePath, string $destinationPath, string $mime, int $maxDimension = 1600, int $jpegQuality = 82): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }

    $image = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png' => @imagecreatefrompng($sourcePath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
        default => false,
    };
    if ($image === false) {
        return false;
    }

    // Mobile photos are frequently stored "sideways" with an EXIF
    // orientation tag telling the viewer how to rotate them on display —
    // GD ignores that tag entirely, so without correcting for it here, a
    // portrait photo would come out rotated once re-encoded.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($sourcePath);
        $orientation = $exif['Orientation'] ?? 1;
        $image = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $longestSide = max($width, $height);

    if ($longestSide > $maxDimension) {
        $scale = $maxDimension / $longestSide;
        $targetWidth = (int) round($width * $scale);
        $targetHeight = (int) round($height * $scale);
    } else {
        $targetWidth = $width;
        $targetHeight = $height;
    }

    $resized = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
    }
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    imagedestroy($image);

    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($resized, $destinationPath, $jpegQuality),
        'image/png' => imagepng($resized, $destinationPath, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($resized, $destinationPath, $jpegQuality) : false,
        default => false,
    };
    imagedestroy($resized);

    return $saved;
}

// ---------------------------------------------------------------------
// Upload cleanup: images live in uploads/ and are only ever
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

        $path = APP_ROOT . '/uploads/' . $filename;
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

// Also looks inside a columns-block's nested children — a page whose only
// photo sits in a column shouldn't fall back to the site-wide default image.
function first_image_url(array $blocks): ?string
{
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $type = $block['type'] ?? '';

        if ($type === 'image' || $type === 'media_text') {
            $url = trim((string) ($block['url'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        if ($type === 'columns') {
            foreach ((array) ($block['columns'] ?? []) as $children) {
                foreach ((array) $children as $child) {
                    if (is_array($child) && ($child['type'] ?? '') === 'image') {
                        $url = trim((string) ($child['url'] ?? ''));
                        if ($url !== '') {
                            return $url;
                        }
                    }
                }
            }
        }
    }
    return null;
}

// Site-wide fallback og:image: a page without its own photo still gets a
// real one (the practice room, already used elsewhere on the site) rather
// than no preview image at all — never a fabricated/generic placeholder.
const DEFAULT_OG_IMAGE = '/assets/images/praktijkruimte.jpg';

function og_image_url(string $siteUrl, ?string $pageImageUrl): string
{
    $url = $pageImageUrl !== null && $pageImageUrl !== '' ? $pageImageUrl : DEFAULT_OG_IMAGE;
    return media_absolute_url($siteUrl, $url);
}

// Contactgegevens (adres/telefoon/e-mail) voor de footer, beheerd via
// admin/settings.php. Eén vaste rij (id = 1) — zie site_settings in schema.sql.
function get_site_settings(mysqli $mysqli): array
{
    static $settings = null;
    if ($settings === null) {
        $row = $mysqli->query('SELECT address, phone, email, content, contact_meta_title, contact_meta_description, ai_summary, contact_form_background, submission_retention_days FROM site_settings WHERE id = 1')->fetch_assoc();
        $settings = [
            'address' => $row['address'] ?? '',
            'phone' => $row['phone'] ?? '',
            'email' => $row['email'] ?? '',
            'content' => $row['content'] ?? '[]',
            'contact_meta_title' => $row['contact_meta_title'] ?? '',
            'contact_meta_description' => $row['contact_meta_description'] ?? '',
            'ai_summary' => $row['ai_summary'] ?? '',
            'contact_form_background' => $row['contact_form_background'] ?? 'none',
            'submission_retention_days' => $row['submission_retention_days'] !== null ? (int) $row['submission_retention_days'] : null,
        ];
    }
    return $settings;
}

// E-mailinstellingen (SMTP-server, afzender, reply-to, ontvanger voor
// contactmeldingen), beheerd via admin/mail-settings.php. Eén vaste rij
// (id = 1) — zie mail_settings in schema.sql. Every field is NULL until an
// admin saves the settings page; resolve_mail_config() is what actually
// combines this with the legacy config.php fallback, use that instead of
// this directly when you need a usable SMTP config.
function get_mail_settings(mysqli $mysqli): array
{
    static $settings = null;
    if ($settings === null) {
        $row = $mysqli->query('SELECT host, port, encryption, username, password, from_email, from_name, reply_to, to_email FROM mail_settings WHERE id = 1')->fetch_assoc();
        $settings = [
            'host' => $row['host'] ?? '',
            'port' => $row['port'] !== null ? (int) $row['port'] : null,
            'encryption' => $row['encryption'] ?? '',
            'username' => $row['username'] ?? '',
            'password' => $row['password'] ?? '',
            'from_email' => $row['from_email'] ?? '',
            'from_name' => $row['from_name'] ?? '',
            'reply_to' => $row['reply_to'] ?? '',
            'to_email' => $row['to_email'] ?? '',
        ];
    }
    return $settings;
}

// The SMTP config actually used to send mail (smtp_send()'s $smtpConfig
// argument), everywhere mail is sent (contact notification, password
// reset, nieuwsbrief). Database settings (Instellingen → E-mail) win
// field-by-field; any field left empty there falls back to config/
// config.php's legacy 'smtp' section, so mail keeps working immediately
// after this feature ships, before an admin has opened the new settings
// page even once.
function resolve_mail_config(array $config, mysqli $mysqli): array
{
    $db = get_mail_settings($mysqli);
    $legacy = $config['smtp'] ?? [];

    $merged = [];
    foreach (['host', 'username', 'password', 'from_email', 'from_name', 'reply_to', 'to_email'] as $key) {
        $dbValue = (string) ($db[$key] ?? '');
        $merged[$key] = $dbValue !== '' ? $dbValue : (string) ($legacy[$key] ?? '');
    }

    $merged['port'] = $db['port'] ?? (isset($legacy['port']) ? (int) $legacy['port'] : 587);
    $encryption = $db['encryption'] !== '' ? $db['encryption'] : (string) ($legacy['encryption'] ?? '');
    $merged['encryption'] = $encryption !== '' ? $encryption : 'tls';

    // DKIM is config.php-only, no database/admin-UI equivalent — it's a
    // long-lived cryptographic secret set up once, not something to edit
    // through a web form (same reasoning as the GA service-account key).
    $dkim = $config['dkim'] ?? [];
    $merged['dkim_domain'] = (string) ($dkim['domain'] ?? '');
    $merged['dkim_selector'] = (string) ($dkim['selector'] ?? '');
    $merged['dkim_private_key'] = (string) ($dkim['private_key'] ?? '');

    return $merged;
}

// Deletes contact submissions and event registrations older than the
// configured retention window — personal data (name/email/message/IP)
// that otherwise accumulates indefinitely with no way to expire it. NULL
// (the default) keeps everything, unchanged from before this existed.
// No cron on this project's shared/manual-deploy hosting, so this runs
// opportunistically: called once from admin/dashboard.php, the page an
// admin lands on at the start of every session, the same "lazy cleanup on
// access" pattern already used for orphaned uploads.
function cleanup_expired_submissions(mysqli $mysqli): void
{
    $settings = get_site_settings($mysqli);
    $days = $settings['submission_retention_days'];
    if ($days === null) {
        return;
    }

    $stmt = $mysqli->prepare('DELETE FROM contact_submissions WHERE created_at < (NOW() - INTERVAL ? DAY)');
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare('DELETE FROM event_registrations WHERE registered_at < (NOW() - INTERVAL ? DAY)');
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $stmt->close();
}

// Switches to ProfessionalService (a LocalBusiness subtype — fits a
// therapy/coaching practice) once there's real address/phone data to back
// it up, same "never verzin data" principle as the footer: only claim to
// be a located, reachable business when Instellingen actually says so.
// address stays a plain string (schema.org allows this, not just a
// PostalAddress object) since it's a single free-text field, not
// street/city/postcode split out separately.
function organization_schema(string $siteName, string $siteUrl, array $siteSettings = []): array
{
    $address = trim((string) ($siteSettings['address'] ?? ''));
    $phone = trim((string) ($siteSettings['phone'] ?? ''));
    $email = trim((string) ($siteSettings['email'] ?? ''));

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => ($address !== '' || $phone !== '') ? 'ProfessionalService' : 'Organization',
        'name' => $siteName,
        'url' => $siteUrl,
    ];

    if ($address !== '') {
        $schema['address'] = $address;
    }
    if ($phone !== '') {
        $schema['telephone'] = $phone;
    }
    if ($email !== '') {
        $schema['email'] = $email;
    }

    return $schema;
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

// schema.org/Event requires a location, so an event without one (not yet
// filled in by the site owner) gets no JSON-LD rather than a fabricated address.
function event_schema(array $event): ?array
{
    $location = trim((string) ($event['location'] ?? ''));
    if ($location === '') {
        return null;
    }

    $date = (string) ($event['event_date'] ?? '');
    $time = trim((string) ($event['event_time'] ?? ''));
    $startDate = $time !== '' ? $date . 'T' . substr($time, 0, 5) . ':00' : $date;

    return [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => (string) ($event['title'] ?? ''),
        'description' => (string) ($event['description'] ?? ''),
        'startDate' => $startDate,
        'eventStatus' => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => [
            '@type' => 'Place',
            'name' => $location,
        ],
    ];
}

// ---------------------------------------------------------------------
// Nieuwsbrief: e-mailclients (vooral Outlook desktop) have none of the
// CSS this site otherwise relies on — no flexbox/grid, no custom
// properties, often no external stylesheet at all, and no inline SVG. So
// a newsletter is deliberately restricted to a small set of block types
// (NEWSLETTER_BLOCK_TYPES) and rendered through its own, email-safe
// functions below — inline styles only, plain <ul>/<li> bullets instead
// of the site's needle-icon ones — rather than reusing render_blocks()'s
// class-based output, which would simply not render in most inboxes.
// sanitize_blocks() itself is still reused as-is for validation/storage:
// it already handles every block type safely, so there is nothing
// newsletter-specific to duplicate there — only which types the admin
// UI/email renderer are willing to offer or display.
// ---------------------------------------------------------------------

const NEWSLETTER_BLOCK_TYPES = ['text', 'image', 'quote', 'list', 'buttons', 'newsletter_events'];

// Mirrors .block-bg-accent/.block-bg-surface from assets/css/style.css as
// inline styles: e-mail clients ignore classes and CSS custom properties,
// so the admin's "Achtergrond" field (shared with the page editor, via
// sanitize_block_background()) needs its own, fixed-color translation here
// rather than being silently ignored.
function render_newsletter_bg_style(string $background): string
{
    return match ($background) {
        'accent' => 'background:#6b7360;color:#f7f3ee;',
        'surface' => 'background:#f7f3ee;color:#262420;border:1px solid #ede0d8;',
        default => '',
    };
}

function render_newsletter_text_paragraphs(string $body): string
{
    $out = '';
    foreach (preg_split('/\n{2,}/', $body) as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }

        $lines = preg_split('/\n/', $paragraph);
        $items = [];
        $isList = true;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!str_starts_with($line, '- ')) {
                $isList = false;
                break;
            }
            $items[] = trim(substr($line, 2));
        }

        if ($isList && !empty($items)) {
            $out .= '<ul style="margin:0 0 16px;padding-left:22px;">';
            foreach ($items as $item) {
                $out .= '<li style="margin-bottom:6px;">' . render_inline_markup($item) . '</li>';
            }
            $out .= '</ul>';
        } else {
            $out .= '<p style="margin:0 0 16px;line-height:1.55;">' . nl2br(render_inline_markup($paragraph)) . '</p>';
        }
    }
    return $out;
}

function render_newsletter_text_block(array $block): string
{
    $heading = trim((string) ($block['heading'] ?? ''));
    $body = trim((string) ($block['body'] ?? ''));
    if ($body === '' && $heading === '') {
        return '';
    }
    $bgStyle = render_newsletter_bg_style(sanitize_block_background($block['background'] ?? ''));
    $wrapperStyle = 'margin:0 0 28px;' . ($bgStyle !== '' ? $bgStyle . 'padding:20px 24px;border-radius:4px;' : '');
    $out = '<div style="' . $wrapperStyle . '">';
    if ($heading !== '') {
        $out .= '<h2 style="margin:0 0 12px;font-family:Georgia,\'Times New Roman\',serif;font-size:1.3rem;color:inherit;">' . e($heading) . '</h2>';
    }
    $out .= render_newsletter_text_paragraphs($body);
    $out .= '</div>';
    return $out;
}

function render_newsletter_image_block(array $block): string
{
    $url = trim((string) ($block['url'] ?? ''));
    if ($url === '' || !is_safe_url($url)) {
        return '';
    }
    $alt = trim((string) ($block['alt'] ?? ''));
    $caption = trim((string) ($block['caption'] ?? ''));
    $bgStyle = render_newsletter_bg_style(sanitize_block_background($block['background'] ?? ''));
    $wrapperStyle = 'margin:0 0 28px;' . ($bgStyle !== '' ? $bgStyle . 'padding:20px 24px;border-radius:4px;' : '');

    $out = '<div style="' . $wrapperStyle . '">';
    $out .= '<img src="' . e($url) . '" alt="' . e($alt) . '" width="544" style="width:100%;max-width:544px;height:auto;display:block;border-radius:4px;">';
    if ($caption !== '') {
        $out .= '<p style="margin:8px 0 0;font-size:0.85rem;color:inherit;opacity:0.75;">' . e($caption) . '</p>';
    }
    $out .= '</div>';
    return $out;
}

function render_newsletter_quote_block(array $block): string
{
    $text = trim((string) ($block['text'] ?? ''));
    if ($text === '') {
        return '';
    }
    $source = trim((string) ($block['source'] ?? ''));
    $bgStyle = render_newsletter_bg_style(sanitize_block_background($block['background'] ?? ''));
    $wrapperStyle = $bgStyle !== ''
        ? 'margin:0 0 28px;padding:16px 20px;border-radius:4px;' . $bgStyle
        : 'margin:0 0 28px;padding:16px 20px;border-left:3px solid #6b7360;background:#f7f3ee;';

    $out = '<div style="' . $wrapperStyle . '">';
    $out .= '<p style="margin:0;font-style:italic;line-height:1.5;">' . nl2br(e($text)) . '</p>';
    if ($source !== '') {
        $out .= '<p style="margin:8px 0 0;font-size:0.85rem;color:inherit;opacity:0.75;">' . e($source) . '</p>';
    }
    $out .= '</div>';
    return $out;
}

function render_newsletter_list_block(array $block): string
{
    $items = array_values(array_filter(
        array_map(fn($item) => trim((string) $item), (array) ($block['items'] ?? [])),
        fn($item) => $item !== ''
    ));
    if (empty($items)) {
        return '';
    }
    $heading = trim((string) ($block['heading'] ?? ''));
    $bgStyle = render_newsletter_bg_style(sanitize_block_background($block['background'] ?? ''));
    $wrapperStyle = 'margin:0 0 28px;' . ($bgStyle !== '' ? $bgStyle . 'padding:20px 24px;border-radius:4px;' : '');

    $out = '<div style="' . $wrapperStyle . '">';
    if ($heading !== '') {
        $out .= '<h3 style="margin:0 0 10px;font-family:Georgia,\'Times New Roman\',serif;font-size:1.1rem;color:inherit;">' . e($heading) . '</h3>';
    }
    $out .= '<ul style="margin:0;padding-left:22px;">';
    foreach ($items as $item) {
        $out .= '<li style="margin-bottom:6px;">' . render_inline_markup($item) . '</li>';
    }
    $out .= '</ul></div>';
    return $out;
}

function render_newsletter_buttons_block(array $block): string
{
    $background = sanitize_block_background($block['background'] ?? '');
    // On an accent (dark) panel, the default primary/secondary colors
    // below would either vanish against the matching panel background or
    // lose contrast — so swap to a light-on-dark pair instead.
    $primaryStyle = $background === 'accent'
        ? 'background:#f7f3ee;color:#6b7360;'
        : 'background:#6b7360;color:#f7f3ee;';
    $secondaryStyle = $background === 'accent'
        ? 'background:transparent;color:#f7f3ee;border:2px solid #f7f3ee;'
        : 'background:transparent;color:#262420;border:2px solid #262420;';

    $buttons = (array) ($block['buttons'] ?? []);
    $inner = '';
    $rendered = 0;
    foreach ($buttons as $btn) {
        $label = trim((string) ($btn['label'] ?? ''));
        $url = trim((string) ($btn['url'] ?? ''));
        if ($label === '' || $url === '' || !is_safe_url($url)) {
            continue;
        }
        $style = $rendered === 0 ? $primaryStyle : $secondaryStyle;
        $inner .= '<a href="' . e($url) . '" style="display:inline-block;margin:0 10px 10px 0;padding:11px 22px;border-radius:4px;font-weight:600;text-decoration:none;' . $style . '">' . e($label) . '</a>';
        $rendered++;
    }
    if ($rendered === 0) {
        return '';
    }
    $bgStyle = render_newsletter_bg_style($background);
    $wrapperStyle = 'margin:0 0 28px;' . ($bgStyle !== '' ? $bgStyle . 'padding:20px 24px;border-radius:4px;' : '');
    return '<div style="' . $wrapperStyle . '">' . $inner . '</div>';
}

// Compact events overview for the newsletter — unlike render_events_block()
// (used on pages), there is deliberately no inline registration form: a
// subscriber always registers on the website itself, so this only lists
// the upcoming events and, optionally, a single button linking there.
function render_newsletter_events_block(array $block, mysqli $mysqli): string
{
    $stmt = $mysqli->prepare(
        'SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id) AS registered_count
         FROM events e
         WHERE e.published = 1 AND e.event_date >= CURDATE()
         ORDER BY e.event_date ASC, e.event_time ASC
         LIMIT 5'
    );
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($events)) {
        return '';
    }

    $heading = trim((string) ($block['heading'] ?? ''));
    $background = sanitize_block_background($block['background'] ?? '');
    $bgStyle = render_newsletter_bg_style($background);
    $wrapperStyle = 'margin:0 0 28px;' . ($bgStyle !== '' ? $bgStyle . 'padding:20px 24px;border-radius:4px;' : '');
    $rowBorder = $background === 'accent' ? '1px solid rgba(247,243,238,0.25);' : '1px solid #ede0d8;';

    $out = '<div style="' . $wrapperStyle . '">';
    if ($heading !== '') {
        $out .= '<h2 style="margin:0 0 14px;font-family:Georgia,\'Times New Roman\',serif;font-size:1.3rem;color:inherit;">' . e($heading) . '</h2>';
    }

    $count = count($events);
    foreach ($events as $i => $event) {
        $capacity = $event['capacity'] !== null ? (int) $event['capacity'] : null;
        $registered = (int) $event['registered_count'];
        $spotsLeft = $capacity !== null ? max(0, $capacity - $registered) : null;
        $isFull = $capacity !== null && $spotsLeft <= 0;
        $rowStyle = 'padding:12px 0;' . ($i < $count - 1 ? 'border-bottom:' . $rowBorder : '');

        $out .= '<div style="' . $rowStyle . '">';
        $out .= '<p style="margin:0 0 2px;font-size:0.85rem;font-weight:600;color:inherit;opacity:0.75;">' . e(format_event_date($event['event_date'], $event['event_time'])) . '</p>';
        $out .= '<p style="margin:0 0 4px;font-size:1.05rem;font-weight:600;color:inherit;">' . e($event['title']) . '</p>';
        if (!empty($event['location'])) {
            $out .= '<p style="margin:0;font-size:0.9rem;color:inherit;opacity:0.85;">' . e($event['location']) . '</p>';
        }
        if ($isFull) {
            $out .= '<p style="margin:4px 0 0;font-size:0.8rem;color:inherit;opacity:0.75;">Volzet</p>';
        } elseif ($spotsLeft !== null) {
            $out .= '<p style="margin:4px 0 0;font-size:0.8rem;color:inherit;opacity:0.75;">' . $spotsLeft . ' van de ' . $capacity . ' plaatsen vrij</p>';
        }
        $out .= '</div>';
    }

    $linkLabel = trim((string) ($block['link_label'] ?? ''));
    $linkUrl = trim((string) ($block['link_url'] ?? ''));
    if ($linkLabel !== '' && $linkUrl !== '' && is_safe_url($linkUrl)) {
        $buttonStyle = $background === 'accent'
            ? 'background:#f7f3ee;color:#6b7360;'
            : 'background:#6b7360;color:#f7f3ee;';
        $out .= '<p style="margin:16px 0 0;"><a href="' . e($linkUrl) . '" style="display:inline-block;padding:11px 22px;border-radius:4px;font-weight:600;text-decoration:none;' . $buttonStyle . '">' . e($linkLabel) . '</a></p>';
    }

    $out .= '</div>';
    return $out;
}

// Renders only the inner content (no <html>/<body> shell) — mirrors how
// render_blocks() stays shell-less and header.php/footer.php wrap a page;
// here build_newsletter_email() does that wrapping instead, once per
// recipient so it can also inject that recipient's tracking/unsubscribe
// links.
function render_newsletter_email_body(array $blocks, mysqli $mysqli): string
{
    $html = '';
    foreach ($blocks as $block) {
        if (!is_array($block) || empty($block['type']) || !in_array($block['type'], NEWSLETTER_BLOCK_TYPES, true)) {
            continue;
        }
        $html .= match ($block['type']) {
            'text' => render_newsletter_text_block($block),
            'image' => render_newsletter_image_block($block),
            'quote' => render_newsletter_quote_block($block),
            'list' => render_newsletter_list_block($block),
            'buttons' => render_newsletter_buttons_block($block),
            'newsletter_events' => render_newsletter_events_block($block, $mysqli),
            default => '',
        };
    }
    return $html;
}

// Replaces the {{voornaam}}-merge-tag an admin can type into a
// nieuwsbrief's subject or block content — in the subject (plain text) and
// in the already-rendered body HTML alike, since the tag is just literal
// text in both. Falls back to a generic "daar" when the subscriber's first
// name is unknown, so "Hallo {{voornaam}}," never sends as literally
// "Hallo ,". $escape must be true for HTML contexts (the body) and false
// for plain text (the subject) — the name itself comes from a subscriber,
// so it's untrusted input that needs the same e() treatment as any other
// user-supplied text when it ends up in HTML.
function render_newsletter_merge_tags(string $text, ?string $firstName, bool $escape): string
{
    $name = trim((string) $firstName) !== '' ? trim((string) $firstName) : 'daar';
    return str_replace('{{voornaam}}', $escape ? e($name) : $name, $text);
}

// Builds the full, per-recipient HTML e-mail: wraps the (shared) rendered
// body in a table-based layout e-mail clients actually support, rewrites
// every link through nieuwsbrief-klik.php for click tracking (keyed to
// this recipient's own send_token, so clicks attribute correctly), and
// appends a 1x1 open-tracking pixel plus the mandatory one-click
// unsubscribe footer — both are a legal requirement for commercial
// e-mail, not an optional nicety.
function build_newsletter_email(string $bodyHtml, string $siteName, string $siteUrl, string $sendToken, string $unsubscribeToken): string
{
    $trackedBody = preg_replace_callback('/href="([^"]+)"/', function (array $m) use ($siteUrl, $sendToken) {
        $target = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        $clickUrl = absolute_url($siteUrl, '/nieuwsbrief-klik.php')
            . '?t=' . rawurlencode($sendToken) . '&u=' . rawurlencode($target);
        return 'href="' . e($clickUrl) . '"';
    }, $bodyHtml) ?? $bodyHtml;

    $unsubscribeUrl = absolute_url($siteUrl, '/nieuwsbrief-afmelden.php?t=' . rawurlencode($unsubscribeToken));
    $pixelUrl = absolute_url($siteUrl, '/nieuwsbrief-pixel.php?t=' . rawurlencode($sendToken));

    $footer = '<div style="margin-top:32px;padding-top:16px;border-top:1px solid #ede0d8;font-size:0.8rem;color:#8b8478;">'
        . 'Je ontvangt dit bericht omdat je je hebt ingeschreven voor de nieuwsbrief van ' . e($siteName) . '. '
        . '<a href="' . e($unsubscribeUrl) . '" style="color:#8b8478;">Uitschrijven</a>'
        . '</div>'
        . '<img src="' . e($pixelUrl) . '" width="1" height="1" alt="" style="display:block;border:0;">';

    return '<!doctype html><html lang="nl"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . e($siteName) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f7f3ee;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#262420;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f3ee;"><tr><td align="center" style="padding:28px 16px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:6px;">'
        . '<tr><td style="padding:32px 28px;">'
        . $trackedBody
        . $footer
        . '</td></tr></table>'
        . '</td></tr></table>'
        . '</body></html>';
}

function newsletter_generate_token(): string
{
    return bin2hex(random_bytes(32));
}

// The contact form and event registration only ask for a full name, not a
// first name separately — asking twice would be pure friction for an
// optional newsletter checkbox. Takes the first whitespace-separated token
// instead, same convention as most newsletter tools ("Jan De Smet" ->
// "Jan"). Returns '' (not stored) when that's not possible.
function extract_first_name(string $fullName): string
{
    $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    return $parts[0] ?? '';
}

// Adds an address to the newsletter list, or re-subscribes one that had
// previously opted out — but never silently resurrects it: a prior
// unsubscribe always needs a fresh, explicit opt-in action to undo.
// Shared by the contact form, event registration, and the admin's manual
// "add one address" action; CSV import deliberately does NOT use this —
// see newsletter-subscribers.php for why.
// $firstName is optional (for the {{voornaam}}-merge-tag) — re-opting-in
// refreshes it when a new, non-empty value comes in, but never blanks an
// already-known name just because this particular call didn't collect one.
function newsletter_subscribe(mysqli $mysqli, string $email, string $source, ?string $firstName = null): void
{
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        return;
    }
    $firstName = trim((string) $firstName);
    if (mb_strlen($firstName) > 100) {
        $firstName = mb_substr($firstName, 0, 100);
    }
    $firstNameParam = $firstName !== '' ? $firstName : null;

    $token = newsletter_generate_token();
    $stmt = $mysqli->prepare(
        "INSERT INTO newsletter_subscribers (email, first_name, source, unsubscribe_token) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = 'subscribed', unsubscribed_at = NULL,
             first_name = COALESCE(VALUES(first_name), first_name)"
    );
    $stmt->bind_param('ssss', $email, $firstNameParam, $source, $token);
    $stmt->execute();
    $stmt->close();
}
