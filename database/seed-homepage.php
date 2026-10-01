<?php

declare(strict_types=1);

// One-time CLI script: creates the Eigen-Wijzer homepage using Wendy's real
// copy from her design (Main.dc.html) and the real practice photo. Safe to
// run more than once — it does nothing if a homepage already exists, so it
// never overwrites content someone has since edited in the admin panel.
//
// Usage (from the project root, with config/config.php already set up):
//   php database/seed-homepage.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Dit script is enkel bedoeld om via de command line te draaien:\nphp database/seed-homepage.php\n");
}

require __DIR__ . '/../includes/functions.php';

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "config/config.php ontbreekt. Kopieer config/config.example.php en vul je databasegegevens in.\n");
    exit(1);
}
$config = require $configFile;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($config['db']['host'], $config['db']['user'], $config['db']['pass'], $config['db']['name']);
$mysqli->set_charset('utf8mb4');

$existing = $mysqli->query('SELECT id FROM pages WHERE is_homepage = 1 OR slug = \'home\' LIMIT 1')->fetch_assoc();
if ($existing) {
    echo "Er bestaat al een homepagina (of een pagina met slug 'home') — niets aangepast.\n";
    echo "Verwijder die eerst in het beheerpaneel als je opnieuw wil seeden.\n";
    exit(0);
}

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

// Reuse the exact same validation/cleanup path the admin block editor uses,
// so this seed can never produce content the app itself wouldn't accept.
$sanitized = sanitize_blocks($blocks);
if (!empty($sanitized['errors'])) {
    fwrite(STDERR, "Kon de homepagina niet aanmaken — validatiefouten:\n");
    foreach ($sanitized['errors'] as $error) {
        fwrite(STDERR, "- $error\n");
    }
    exit(1);
}

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

echo "Homepagina aangemaakt — " . count($sanitized['blocks']) . " blokken, variant $themeVariant.\n";
echo "Nog te doen in het beheerpaneel:\n";
echo "- Een beheerdersaccount aanmaken (als dat nog niet gebeurd is).\n";
echo "- Een echt portret van Wendy toevoegen (de hero-afbeelding is in Wendy's ontwerp nog een placeholder, dus hier niet meegenomen).\n";
echo "- Een echte cliëntreactie toevoegen als quote-blok, zodra die er is (het testimonial in Wendy's ontwerp was zelf ook een placeholder).\n";
echo "- Adres, telefoonnummer en e-mailadres: die staan nog nergens (ook niet in Wendy's ontwerp) en worden dus niet verzonnen.\n";
echo "- Beschikbare tijdsloten toevoegen via 'Kalender' in het beheerpaneel, anders toont het kalenderblok niets.\n";
