<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$settings = get_site_settings($mysqli);
$form = $settings;
$errors = [];
$blocksForEditor = decode_blocks($settings['content']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['street_address'] = trim((string) ($_POST['street_address'] ?? ''));
    $form['postal_code'] = trim((string) ($_POST['postal_code'] ?? ''));
    $form['city'] = trim((string) ($_POST['city'] ?? ''));
    $form['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));
    $form['contact_meta_title'] = trim((string) ($_POST['contact_meta_title'] ?? ''));
    $form['contact_meta_description'] = trim((string) ($_POST['contact_meta_description'] ?? ''));
    $form['ai_summary'] = trim(normalize_newlines((string) ($_POST['ai_summary'] ?? '')));
    $form['contact_form_background'] = sanitize_block_background($_POST['contact_form_background'] ?? '');
    $form['submission_retention_days'] = sanitize_retention_days($_POST['submission_retention_days'] ?? '');
    $form['price_range'] = trim((string) ($_POST['price_range'] ?? ''));
    $form['area_served'] = trim((string) ($_POST['area_served'] ?? ''));
    $form['linkedin_url'] = trim((string) ($_POST['linkedin_url'] ?? ''));
    $form['google_business_url'] = trim((string) ($_POST['google_business_url'] ?? ''));
    $form['person_name'] = trim((string) ($_POST['person_name'] ?? ''));
    $form['person_job_title'] = trim((string) ($_POST['person_job_title'] ?? ''));
    $form['person_expertise'] = trim((string) ($_POST['person_expertise'] ?? ''));
    $form['person_bio'] = trim(normalize_newlines((string) ($_POST['person_bio'] ?? '')));

    $rawBlocks = json_decode((string) ($_POST['blocks_json'] ?? '[]'), true);
    $blocksForEditor = is_array($rawBlocks) ? $rawBlocks : [];

    if (mb_strlen($form['street_address']) > 150) {
        $errors[] = 'Straat en nummer zijn te lang (max. 150 tekens).';
    }
    if (mb_strlen($form['postal_code']) > 12) {
        $errors[] = 'Postcode is te lang (max. 12 tekens).';
    }
    if (mb_strlen($form['city']) > 100) {
        $errors[] = 'Gemeente is te lang (max. 100 tekens).';
    }
    if (mb_strlen($form['phone']) > 50) {
        $errors[] = 'Telefoonnummer is te lang (max. 50 tekens).';
    }
    if ($form['email'] !== '' && (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 190)) {
        $errors[] = 'Vul een geldig e-mailadres in (of laat leeg).';
    }
    if (mb_strlen($form['contact_meta_title']) > 200) {
        $errors[] = 'Titel contactpagina is te lang (max. 200 tekens).';
    }
    if (mb_strlen($form['contact_meta_description']) > 300) {
        $errors[] = 'Meta-omschrijving contactpagina is te lang (max. 300 tekens).';
    }
    if (mb_strlen($form['ai_summary']) > 2000) {
        $errors[] = 'AI-samenvatting is te lang (max. 2000 tekens).';
    }
    if (mb_strlen($form['price_range']) > 50) {
        $errors[] = 'Prijsklasse is te lang (max. 50 tekens).';
    }
    if (mb_strlen($form['area_served']) > 200) {
        $errors[] = 'Werkgebied is te lang (max. 200 tekens).';
    }
    foreach (['linkedin_url' => 'LinkedIn-link', 'google_business_url' => 'Google Business-link'] as $field => $label) {
        if ($form[$field] !== '' && (!preg_match('#^https://#i', $form[$field]) || mb_strlen($form[$field]) > 300)) {
            $errors[] = "$label moet een geldige https://-link zijn (of laat leeg).";
        }
    }
    if (mb_strlen($form['person_name']) > 150) {
        $errors[] = 'Naam is te lang (max. 150 tekens).';
    }
    if (mb_strlen($form['person_job_title']) > 150) {
        $errors[] = 'Functietitel is te lang (max. 150 tekens).';
    }
    if (mb_strlen($form['person_expertise']) > 500) {
        $errors[] = 'Expertise is te lang (max. 500 tekens).';
    }
    if (mb_strlen($form['person_bio']) > 1000) {
        $errors[] = 'Korte bio is te lang (max. 1000 tekens).';
    }

    $sanitized = sanitize_blocks($blocksForEditor);
    $errors = array_merge($errors, $sanitized['errors']);

    if (empty($errors)) {
        $streetAddress = $form['street_address'] !== '' ? $form['street_address'] : null;
        $postalCode = $form['postal_code'] !== '' ? $form['postal_code'] : null;
        $city = $form['city'] !== '' ? $form['city'] : null;
        $phone = $form['phone'] !== '' ? $form['phone'] : null;
        $email = $form['email'] !== '' ? $form['email'] : null;
        $contactMetaTitle = $form['contact_meta_title'] !== '' ? $form['contact_meta_title'] : null;
        $contactMetaDescription = $form['contact_meta_description'] !== '' ? $form['contact_meta_description'] : null;
        $aiSummary = $form['ai_summary'] !== '' ? $form['ai_summary'] : null;
        $contactFormBackground = $form['contact_form_background'];
        $retentionDays = $form['submission_retention_days'];
        $priceRange = $form['price_range'] !== '' ? $form['price_range'] : null;
        $areaServed = $form['area_served'] !== '' ? $form['area_served'] : null;
        $linkedinUrl = $form['linkedin_url'] !== '' ? $form['linkedin_url'] : null;
        $googleBusinessUrl = $form['google_business_url'] !== '' ? $form['google_business_url'] : null;
        $personName = $form['person_name'] !== '' ? $form['person_name'] : null;
        $personJobTitle = $form['person_job_title'] !== '' ? $form['person_job_title'] : null;
        $personExpertise = $form['person_expertise'] !== '' ? $form['person_expertise'] : null;
        $personBio = $form['person_bio'] !== '' ? $form['person_bio'] : null;
        $contentJson = json_encode($sanitized['blocks'], JSON_UNESCAPED_UNICODE);
        $removedUploadUrls = array_diff(
            extract_upload_urls(decode_blocks($settings['content'])),
            extract_upload_urls($sanitized['blocks'])
        );

        $stmt = $mysqli->prepare(
            'UPDATE site_settings SET street_address = ?, postal_code = ?, city = ?, phone = ?, email = ?, content = ?,
                contact_meta_title = ?, contact_meta_description = ?, ai_summary = ?, contact_form_background = ?,
                submission_retention_days = ?, price_range = ?, area_served = ?, linkedin_url = ?, google_business_url = ?,
                person_name = ?, person_job_title = ?, person_expertise = ?, person_bio = ?
             WHERE id = 1'
        );
        $stmt->bind_param(
            'ssssssssssissssssss',
            $streetAddress, $postalCode, $city, $phone, $email, $contentJson,
            $contactMetaTitle, $contactMetaDescription, $aiSummary, $contactFormBackground,
            $retentionDays, $priceRange, $areaServed, $linkedinUrl, $googleBusinessUrl,
            $personName, $personJobTitle, $personExpertise, $personBio
        );
        $stmt->execute();
        $stmt->close();

        delete_orphaned_uploads($mysqli, $removedUploadUrls);

        set_flash('success', 'Instellingen opgeslagen.');
        redirect('settings.php');
    }
}

$pageTitle = 'Instellingen';
require __DIR__ . '/includes/header.php';
?>

<h1>Instellingen</h1>
<p>Contactgegevens voor de footer van de site. Laat een veld leeg om het niet te tonen.</p>

<?php if (!empty($errors)): ?>
    <ul class="form-errors">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" class="page-form page-form-wide">
    <?= csrf_field() ?>

    <div class="page-form-columns">
        <div class="page-form-main">
            <label>Inhoud contactpagina</label>
            <p class="field-hint">
                Deze blokken verschijnen op de contactpagina, boven het vaste
                contactformulier (dat blijft ongewijzigd). Handig voor een korte
                intro, een kaart met de praktijklocatie, openingsuren, ... Leeg =
                enkel het formulier, zoals vandaag.
            </p>
            <div id="block-editor" class="block-editor"></div>
            <div id="block-toolbar" class="block-toolbar"></div>
            <script type="application/json" id="initial-blocks"><?= json_encode($blocksForEditor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
            <input type="hidden" id="blocks_json" name="blocks_json">
        </div>

        <div class="page-form-sidebar">
            <label for="street_address">Straat en nummer (optioneel)</label>
            <input type="text" id="street_address" name="street_address" value="<?= e($form['street_address']) ?>">

            <label for="postal_code">Postcode (optioneel)</label>
            <input type="text" id="postal_code" name="postal_code" value="<?= e($form['postal_code']) ?>" style="width:8rem;">

            <label for="city">Gemeente (optioneel)</label>
            <input type="text" id="city" name="city" value="<?= e($form['city']) ?>">

            <label for="phone">Telefoonnummer (optioneel)</label>
            <input type="text" id="phone" name="phone" value="<?= e($form['phone']) ?>" placeholder="+32 471 23 45 67">
            <p class="field-hint">Bij voorkeur in internationaal formaat (+32...) — dat gebruikt ook het schema.org-structuurdata hieronder.</p>

            <label for="email">E-mailadres (optioneel)</label>
            <input type="email" id="email" name="email" value="<?= e($form['email']) ?>">

            <label for="contact_meta_title">Titel contactpagina (SEO, optioneel)</label>
            <input type="text" id="contact_meta_title" name="contact_meta_title" value="<?= e($form['contact_meta_title']) ?>" maxlength="200" placeholder="Contact">
            <p class="field-hint">Leeg = standaardtitel "Contact" blijft gebruikt.</p>

            <label for="contact_meta_description">Meta-omschrijving contactpagina (SEO, optioneel)</label>
            <input type="text" id="contact_meta_description" name="contact_meta_description" value="<?= e($form['contact_meta_description']) ?>" maxlength="300" data-meta-description-input>
            <p class="field-hint" data-meta-description-hint></p>

            <label for="contact_form_background">Achtergrond contactformulier</label>
            <select id="contact_form_background" name="contact_form_background">
                <option value="none"<?= $form['contact_form_background'] !== 'accent' && $form['contact_form_background'] !== 'surface' ? ' selected' : '' ?>>Geen</option>
                <option value="accent"<?= $form['contact_form_background'] === 'accent' ? ' selected' : '' ?>>Accentkleur</option>
                <option value="surface"<?= $form['contact_form_background'] === 'surface' ? ' selected' : '' ?>>Zachte kaart</option>
            </select>

            <label for="submission_retention_days">Bewaartermijn contactberichten &amp; inschrijvingen</label>
            <select id="submission_retention_days" name="submission_retention_days">
                <option value=""<?= $form['submission_retention_days'] === null ? ' selected' : '' ?>>Voor altijd</option>
                <option value="90"<?= $form['submission_retention_days'] === 90 ? ' selected' : '' ?>>90 dagen</option>
                <option value="180"<?= $form['submission_retention_days'] === 180 ? ' selected' : '' ?>>180 dagen</option>
                <option value="365"<?= $form['submission_retention_days'] === 365 ? ' selected' : '' ?>>1 jaar</option>
                <option value="730"<?= $form['submission_retention_days'] === 730 ? ' selected' : '' ?>>2 jaar</option>
            </select>
            <p class="field-hint">
                Contactberichten en evenement-inschrijvingen ouder dan deze termijn
                worden automatisch verwijderd (naam, e-mailadres, bericht, IP-adres).
                Wordt opgeruimd bij het openen van het Dashboard, niet onmiddellijk.
            </p>

            <label for="ai_summary">AI-samenvatting van de praktijk (optioneel)</label>
            <textarea id="ai_summary" name="ai_summary" rows="5" maxlength="2000"><?= e($form['ai_summary']) ?></textarea>
            <p class="field-hint">
                Een uitgebreidere, eigen samenvatting van de praktijk voor AI-zoeksystemen
                (llms.txt) — los van de korte meta-omschrijving hierboven. Mag een paar
                zinnen tot een korte paragraaf zijn. Leeg = deze sectie verschijnt niet in llms.txt.
            </p>

            <hr>
            <h2>Structuurdata (schema.org)</h2>
            <p class="field-hint">
                Onzichtbare informatie voor zoekmachines en AI — verschijnt nergens
                letterlijk op de site zelf. Elk veld hieronder is optioneel: leeg =
                weggelaten uit het schema, net als adres/telefoon hierboven.
            </p>

            <label for="price_range">Prijsklasse (optioneel)</label>
            <input type="text" id="price_range" name="price_range" value="<?= e($form['price_range']) ?>" maxlength="50" placeholder="bv. €€ of vanaf €60">

            <label for="area_served">Werkgebied (optioneel)</label>
            <input type="text" id="area_served" name="area_served" value="<?= e($form['area_served']) ?>" maxlength="200" placeholder="bv. Antwerpen en omgeving">

            <label for="linkedin_url">LinkedIn-profiel (optioneel)</label>
            <input type="url" id="linkedin_url" name="linkedin_url" value="<?= e($form['linkedin_url']) ?>" maxlength="300" placeholder="https://www.linkedin.com/in/...">

            <label for="google_business_url">Google Business-profiel (optioneel)</label>
            <input type="url" id="google_business_url" name="google_business_url" value="<?= e($form['google_business_url']) ?>" maxlength="300" placeholder="https://g.page/...">

            <label for="person_name">Naam behandelaar (optioneel)</label>
            <input type="text" id="person_name" name="person_name" value="<?= e($form['person_name']) ?>" maxlength="150" placeholder="bv. Wendy Tulkens">
            <p class="field-hint">Vereist om als persoon in het schema te verschijnen — de velden hieronder worden pas gebruikt als dit is ingevuld.</p>

            <label for="person_job_title">Functietitel (optioneel)</label>
            <input type="text" id="person_job_title" name="person_job_title" value="<?= e($form['person_job_title']) ?>" maxlength="150" placeholder="bv. Psychotherapeut">

            <label for="person_expertise">Expertise (optioneel, kommagescheiden)</label>
            <input type="text" id="person_expertise" name="person_expertise" value="<?= e($form['person_expertise']) ?>" maxlength="500" placeholder="bv. Stressmanagement, Rouwverwerking, Ouderschap">

            <label for="person_bio">Korte bio (optioneel)</label>
            <textarea id="person_bio" name="person_bio" rows="3" maxlength="1000"><?= e($form['person_bio']) ?></textarea>

            <div class="page-form-actions">
                <button type="submit">Opslaan</button>
            </div>
        </div>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
