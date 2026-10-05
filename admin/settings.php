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

    $form['address'] = trim((string) ($_POST['address'] ?? ''));
    $form['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));
    $form['contact_meta_title'] = trim((string) ($_POST['contact_meta_title'] ?? ''));
    $form['contact_meta_description'] = trim((string) ($_POST['contact_meta_description'] ?? ''));
    $form['ai_summary'] = trim((string) ($_POST['ai_summary'] ?? ''));
    $form['contact_form_background'] = sanitize_block_background($_POST['contact_form_background'] ?? '');
    $form['submission_retention_days'] = sanitize_retention_days($_POST['submission_retention_days'] ?? '');

    $rawBlocks = json_decode((string) ($_POST['blocks_json'] ?? '[]'), true);
    $blocksForEditor = is_array($rawBlocks) ? $rawBlocks : [];

    if (mb_strlen($form['address']) > 255) {
        $errors[] = 'Adres is te lang (max. 255 tekens).';
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

    $sanitized = sanitize_blocks($blocksForEditor);
    $errors = array_merge($errors, $sanitized['errors']);

    if (empty($errors)) {
        $address = $form['address'] !== '' ? $form['address'] : null;
        $phone = $form['phone'] !== '' ? $form['phone'] : null;
        $email = $form['email'] !== '' ? $form['email'] : null;
        $contactMetaTitle = $form['contact_meta_title'] !== '' ? $form['contact_meta_title'] : null;
        $contactMetaDescription = $form['contact_meta_description'] !== '' ? $form['contact_meta_description'] : null;
        $aiSummary = $form['ai_summary'] !== '' ? $form['ai_summary'] : null;
        $contactFormBackground = $form['contact_form_background'];
        $retentionDays = $form['submission_retention_days'];
        $contentJson = json_encode($sanitized['blocks'], JSON_UNESCAPED_UNICODE);
        $removedUploadUrls = array_diff(
            extract_upload_urls(decode_blocks($settings['content'])),
            extract_upload_urls($sanitized['blocks'])
        );

        $stmt = $mysqli->prepare('UPDATE site_settings SET address = ?, phone = ?, email = ?, content = ?, contact_meta_title = ?, contact_meta_description = ?, ai_summary = ?, contact_form_background = ?, submission_retention_days = ? WHERE id = 1');
        $stmt->bind_param('ssssssssi', $address, $phone, $email, $contentJson, $contactMetaTitle, $contactMetaDescription, $aiSummary, $contactFormBackground, $retentionDays);
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
            <label for="address">Adres (optioneel)</label>
            <input type="text" id="address" name="address" value="<?= e($form['address']) ?>">

            <label for="phone">Telefoonnummer (optioneel)</label>
            <input type="text" id="phone" name="phone" value="<?= e($form['phone']) ?>">

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

            <div class="page-form-actions">
                <button type="submit">Opslaan</button>
            </div>
        </div>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
