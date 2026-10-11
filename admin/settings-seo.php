<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$settings = get_site_settings($mysqli);
$form = $settings;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['contact_meta_title'] = trim((string) ($_POST['contact_meta_title'] ?? ''));
    $form['contact_meta_description'] = trim((string) ($_POST['contact_meta_description'] ?? ''));
    $form['contact_form_background'] = sanitize_block_background($_POST['contact_form_background'] ?? '');
    $form['submission_retention_days'] = sanitize_retention_days($_POST['submission_retention_days'] ?? '');
    $form['ai_summary'] = trim(normalize_newlines((string) ($_POST['ai_summary'] ?? '')));

    if (mb_strlen($form['contact_meta_title']) > 200) {
        $errors[] = 'Titel contactpagina is te lang (max. 200 tekens).';
    }
    if (mb_strlen($form['contact_meta_description']) > 300) {
        $errors[] = 'Meta-omschrijving contactpagina is te lang (max. 300 tekens).';
    }
    if (mb_strlen($form['ai_summary']) > 2000) {
        $errors[] = 'AI-samenvatting is te lang (max. 2000 tekens).';
    }

    if (empty($errors)) {
        $contactMetaTitle = $form['contact_meta_title'] !== '' ? $form['contact_meta_title'] : null;
        $contactMetaDescription = $form['contact_meta_description'] !== '' ? $form['contact_meta_description'] : null;
        $contactFormBackground = $form['contact_form_background'];
        $retentionDays = $form['submission_retention_days'];
        $aiSummary = $form['ai_summary'] !== '' ? $form['ai_summary'] : null;

        $stmt = $mysqli->prepare(
            'UPDATE site_settings SET contact_meta_title = ?, contact_meta_description = ?,
                contact_form_background = ?, submission_retention_days = ?, ai_summary = ?
             WHERE id = 1'
        );
        $stmt->bind_param(
            'sssis',
            $contactMetaTitle, $contactMetaDescription, $contactFormBackground, $retentionDays, $aiSummary
        );
        $stmt->execute();
        $stmt->close();

        set_flash('success', 'Instellingen opgeslagen.');
        redirect('settings-seo.php');
    }
}

$pageTitle = 'Instellingen — Weergave & SEO';
$settingsTab = 'seo';
require __DIR__ . '/includes/header.php';
?>

<h1>Instellingen</h1>
<?php require __DIR__ . '/includes/settings-tabs.php'; ?>
<p>Weergave en zoekmachine-/AI-omschrijving van de contactpagina.</p>

<?php if (!empty($errors)): ?>
    <ul class="form-errors">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" class="page-form" style="max-width: 760px;">
    <?= csrf_field() ?>

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
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
