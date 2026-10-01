<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

// TIJDELIJK HULPSCRIPT — verwijder dit bestand via FTP zodra de homepage
// gezet is. Enkel bedoeld als eenmalig alternatief voor
// `php database/seed-homepage.php` op hosting zonder terminal/SSH-toegang.
// Doet niets als er al een homepagina bestaat, dus dubbel uitvoeren kan geen
// kwaad — maar laat het sowieso niet permanent op de server staan.

$existing = $mysqli->query('SELECT id FROM pages WHERE is_homepage = 1 OR slug = \'home\' LIMIT 1')->fetch_assoc();

$done = false;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    csrf_verify();

    $blocks = [
        [
            'type' => 'text',
            'eyebrow' => 'Therapie & coaching — Wendy Tulkens',
            'heading' => 'Terug bij jezelf, stap voor stap.',
            'body' => 'Twijfel over je loopbaan, de druk van perfectionisme, een burn-out die je klein houdt — je hoeft er niet alleen doorheen. In een warme, nuchtere begeleiding zoeken we samen uit wat bij jou past, zonder omwegen.',
        ],
        [
            'type' => 'buttons',
            'buttons' => [
                ['label' => 'Plan een kennismaking', 'url' => '/contact'],
            ],
        ],
        [
            'type' => 'quote',
            'text' => 'Ik werk pragmatisch en resultaatgericht — met veel ruimte om echt gehoord te worden.',
            'source' => '',
        ],
        [
            'type' => 'text',
            'eyebrow' => '',
            'heading' => '',
            'body' => 'Elk gesprek begint bij jouw verhaal, niet bij een protocol. Door actief te luisteren en samen te verkennen wat er speelt, kom je stap voor stap dichter bij helderheid — over je werk, jezelf, of de richting die je op wil.',
        ],
        [
            'type' => 'text',
            'eyebrow' => 'Aanbod',
            'heading' => 'Vier vormen van begeleiding, één uitgangspunt: jij.',
            'body' => '',
        ],
        [
            'type' => 'list',
            'heading' => '',
            'style' => 'bullet',
            'items' => [
                'Gesprekstherapie — ruimte om te verwerken, te ordenen en weer grip te krijgen, in je eigen tempo.',
                'Loopbaanbegeleiding — van carrièretwijfel naar een richting die echt bij je past, met concrete stappen.',
                'Perfectionismecoaching — leren wanneer \'goed genoeg\' ook echt genoeg is, zonder je lat te breken.',
                'Stress- & burn-outbegeleiding — herstellen én begrijpen waarom je vastliep, zodat het niet opnieuw gebeurt.',
            ],
        ],
        [
            'type' => 'text',
            'eyebrow' => 'De praktijk',
            'heading' => 'Een warme, huiselijke plek — geen steriele spreekkamer.',
            'body' => 'Geen kliniek, geen wachtkamerdruk. Een rustige zithoek waar je gehoord wordt zoals je bent, met een kop koffie en de tijd die een gesprek nodig heeft.',
        ],
        [
            'type' => 'image',
            'url' => '/assets/images/praktijkruimte.jpg',
            'alt' => 'De praktijkruimte van Eigen-Wijzer',
            'caption' => 'De zithoek in de praktijk, Linkeroever — waar de gesprekken plaatsvinden.',
        ],
        [
            'type' => 'text',
            'eyebrow' => '',
            'heading' => 'Klaar voor een eerste, vrijblijvend gesprek?',
            'body' => 'Binnen twee werkdagen krijg je een reactie, meestal sneller. Kies hieronder meteen een moment dat je past.',
        ],
        [
            'type' => 'calendar',
            'heading' => '',
        ],
    ];

    $sanitized = sanitize_blocks($blocks);
    if (!empty($sanitized['errors'])) {
        $result = ['type' => 'error', 'message' => 'Validatiefouten: ' . implode(' / ', $sanitized['errors'])];
    } else {
        $contentJson = json_encode($sanitized['blocks'], JSON_UNESCAPED_UNICODE);
        $title = 'Home';
        $slug = 'home';
        $metaDescription = 'Therapie en coaching bij Eigen-Wijzer — Wendy Tulkens. Gesprekstherapie, loopbaanbegeleiding, perfectionismecoaching en stress- & burn-outbegeleiding.';
        $themeVariant = 'a';
        $published = 1;
        $isHomepage = 1;
        $showInMenu = 0;
        $navOrder = 0;

        $stmt = $mysqli->prepare(
            'INSERT INTO pages (title, slug, content, theme_variant, meta_description, published, is_homepage, show_in_menu, nav_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'sssssiiii',
            $title,
            $slug,
            $contentJson,
            $themeVariant,
            $metaDescription,
            $published,
            $isHomepage,
            $showInMenu,
            $navOrder
        );
        $stmt->execute();
        $stmt->close();

        $done = true;
        $result = ['type' => 'success', 'message' => count($sanitized['blocks']) . ' blokken aangemaakt, variant ' . $themeVariant . '.'];
    }
}

$pageTitle = 'Homepage seeden';
require __DIR__ . '/includes/header.php';
?>

<h1>Homepage seeden</h1>
<p>Zet Wendy's echte starttekst klaar als homepage — eenmalig, enkel als er nog geen homepagina bestaat.</p>

<?php if ($existing): ?>
    <div class="flash flash-error">Er bestaat al een homepagina (of een pagina met slug 'home') — niets aangepast. Verwijder die eerst bij "Pagina's" als je opnieuw wil seeden.</div>
<?php elseif ($result): ?>
    <div class="flash flash-<?= e($result['type']) ?>"><?= e($result['message']) ?></div>
<?php endif; ?>

<?php if ($done): ?>
    <p><strong>Nog te doen:</strong></p>
    <ul>
        <li>Een echt portret van Wendy toevoegen (de hero-afbeelding is in het ontwerp nog een placeholder).</li>
        <li>Een echte cliëntreactie toevoegen als quote-blok zodra die er is.</li>
        <li>Adres, telefoonnummer en e-mailadres — die staan nog nergens.</li>
        <li>Beschikbare tijdsloten toevoegen via "Kalender", anders toont het kalenderblok niets.</li>
    </ul>
    <p><a href="pages.php">Naar Pagina's →</a></p>
    <p><strong>Belangrijk:</strong> verwijder dit bestand (<code>admin/seed-homepage.php</code>) nu via FTP — het hoort niet permanent op de server te staan.</p>
<?php elseif (!$existing): ?>
    <form method="post">
        <?= csrf_field() ?>
        <button type="submit" class="btn-primary">Homepage nu aanmaken</button>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
