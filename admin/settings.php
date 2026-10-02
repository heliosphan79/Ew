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

    $sanitized = sanitize_blocks($blocksForEditor);
    $errors = array_merge($errors, $sanitized['errors']);

    if (empty($errors)) {
        $address = $form['address'] !== '' ? $form['address'] : null;
        $phone = $form['phone'] !== '' ? $form['phone'] : null;
        $email = $form['email'] !== '' ? $form['email'] : null;
        $contentJson = json_encode($sanitized['blocks'], JSON_UNESCAPED_UNICODE);
        $removedUploadUrls = array_diff(
            extract_upload_urls(decode_blocks($settings['content'])),
            extract_upload_urls($sanitized['blocks'])
        );

        $stmt = $mysqli->prepare('UPDATE site_settings SET address = ?, phone = ?, email = ?, content = ? WHERE id = 1');
        $stmt->bind_param('ssss', $address, $phone, $email, $contentJson);
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

<form method="post" class="page-form">
    <?= csrf_field() ?>

    <label for="address">Adres (optioneel)</label>
    <input type="text" id="address" name="address" value="<?= e($form['address']) ?>">

    <label for="phone">Telefoonnummer (optioneel)</label>
    <input type="text" id="phone" name="phone" value="<?= e($form['phone']) ?>">

    <label for="email">E-mailadres (optioneel)</label>
    <input type="email" id="email" name="email" value="<?= e($form['email']) ?>">

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

    <button type="submit">Opslaan</button>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
