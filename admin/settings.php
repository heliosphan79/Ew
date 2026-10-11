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

    $sanitized = sanitize_blocks($blocksForEditor);
    $errors = array_merge($errors, $sanitized['errors']);

    if (empty($errors)) {
        $streetAddress = $form['street_address'] !== '' ? $form['street_address'] : null;
        $postalCode = $form['postal_code'] !== '' ? $form['postal_code'] : null;
        $city = $form['city'] !== '' ? $form['city'] : null;
        $phone = $form['phone'] !== '' ? $form['phone'] : null;
        $email = $form['email'] !== '' ? $form['email'] : null;
        $contentJson = json_encode($sanitized['blocks'], JSON_UNESCAPED_UNICODE);
        $removedUploadUrls = array_diff(
            extract_upload_urls(decode_blocks($settings['content'])),
            extract_upload_urls($sanitized['blocks'])
        );

        $stmt = $mysqli->prepare(
            'UPDATE site_settings SET street_address = ?, postal_code = ?, city = ?, phone = ?, email = ?, content = ?
             WHERE id = 1'
        );
        $stmt->bind_param('ssssss', $streetAddress, $postalCode, $city, $phone, $email, $contentJson);
        $stmt->execute();
        $stmt->close();

        delete_orphaned_uploads($mysqli, $removedUploadUrls);

        set_flash('success', 'Instellingen opgeslagen.');
        redirect('settings.php');
    }
}

$pageTitle = 'Instellingen';
$settingsTab = 'algemeen';
require __DIR__ . '/includes/header.php';
?>

<h1>Instellingen</h1>
<?php require __DIR__ . '/includes/settings-tabs.php'; ?>
<p>Contactgegevens voor de footer van de site. Laat een veld leeg om het niet te tonen.</p>

<?php if (!empty($errors)): ?>
    <ul class="form-errors">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" class="page-form" style="max-width: 760px;">
    <?= csrf_field() ?>

    <h2>Contactgegevens</h2>
    <div class="page-form-row">
        <label for="street_address">Straat en nummer (optioneel)
            <input type="text" id="street_address" name="street_address" value="<?= e($form['street_address']) ?>">
        </label>
        <label for="postal_code">Postcode (optioneel)
            <input type="text" id="postal_code" name="postal_code" value="<?= e($form['postal_code']) ?>" style="width:8rem;">
        </label>
        <label for="city">Gemeente (optioneel)
            <input type="text" id="city" name="city" value="<?= e($form['city']) ?>">
        </label>
    </div>

    <div class="page-form-row">
        <label for="phone">Telefoonnummer (optioneel)
            <input type="text" id="phone" name="phone" value="<?= e($form['phone']) ?>" placeholder="+32 471 23 45 67">
        </label>
        <label for="email">E-mailadres (optioneel)
            <input type="email" id="email" name="email" value="<?= e($form['email']) ?>">
        </label>
    </div>
    <p class="field-hint">Telefoonnummer bij voorkeur in internationaal formaat (+32...) — dat gebruikt ook het schema.org-structuurdata bij "Structuurdata". Een leeg veld wordt nergens getoond.</p>

    <hr>
    <h2>Inhoud contactpagina</h2>
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

    <div class="page-form-actions">
        <button type="submit">Opslaan</button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
