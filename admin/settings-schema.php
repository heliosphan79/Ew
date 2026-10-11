<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$settings = get_site_settings($mysqli);
$form = $settings;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['price_range'] = trim((string) ($_POST['price_range'] ?? ''));
    $form['area_served'] = trim((string) ($_POST['area_served'] ?? ''));
    $form['linkedin_url'] = trim((string) ($_POST['linkedin_url'] ?? ''));
    $form['google_business_url'] = trim((string) ($_POST['google_business_url'] ?? ''));
    $form['person_name'] = trim((string) ($_POST['person_name'] ?? ''));
    $form['person_job_title'] = trim((string) ($_POST['person_job_title'] ?? ''));
    $form['person_expertise'] = trim((string) ($_POST['person_expertise'] ?? ''));
    $form['person_bio'] = trim(normalize_newlines((string) ($_POST['person_bio'] ?? '')));

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

    if (empty($errors)) {
        $priceRange = $form['price_range'] !== '' ? $form['price_range'] : null;
        $areaServed = $form['area_served'] !== '' ? $form['area_served'] : null;
        $linkedinUrl = $form['linkedin_url'] !== '' ? $form['linkedin_url'] : null;
        $googleBusinessUrl = $form['google_business_url'] !== '' ? $form['google_business_url'] : null;
        $personName = $form['person_name'] !== '' ? $form['person_name'] : null;
        $personJobTitle = $form['person_job_title'] !== '' ? $form['person_job_title'] : null;
        $personExpertise = $form['person_expertise'] !== '' ? $form['person_expertise'] : null;
        $personBio = $form['person_bio'] !== '' ? $form['person_bio'] : null;

        $stmt = $mysqli->prepare(
            'UPDATE site_settings SET price_range = ?, area_served = ?, linkedin_url = ?, google_business_url = ?,
                person_name = ?, person_job_title = ?, person_expertise = ?, person_bio = ?
             WHERE id = 1'
        );
        $stmt->bind_param(
            'ssssssss',
            $priceRange, $areaServed, $linkedinUrl, $googleBusinessUrl,
            $personName, $personJobTitle, $personExpertise, $personBio
        );
        $stmt->execute();
        $stmt->close();

        set_flash('success', 'Instellingen opgeslagen.');
        redirect('settings-schema.php');
    }
}

$pageTitle = 'Instellingen — Structuurdata';
$settingsTab = 'schema';
require __DIR__ . '/includes/header.php';
?>

<h1>Instellingen</h1>
<?php require __DIR__ . '/includes/settings-tabs.php'; ?>
<p class="field-hint">
    Onzichtbare informatie voor zoekmachines en AI (schema.org) — verschijnt
    nergens letterlijk op de site zelf. Elk veld hieronder is optioneel:
    leeg = weggelaten uit het schema, net als adres/telefoon bij "Algemeen".
</p>

<?php if (!empty($errors)): ?>
    <ul class="form-errors">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" class="page-form" style="max-width: 760px;">
    <?= csrf_field() ?>

    <div class="page-form-row">
        <label for="price_range">Prijsklasse (optioneel)
            <input type="text" id="price_range" name="price_range" value="<?= e($form['price_range']) ?>" maxlength="50" placeholder="bv. €€ of vanaf €60">
        </label>
        <label for="area_served">Werkgebied (optioneel)
            <input type="text" id="area_served" name="area_served" value="<?= e($form['area_served']) ?>" maxlength="200" placeholder="bv. Antwerpen en omgeving">
        </label>
    </div>

    <div class="page-form-row">
        <label for="linkedin_url">LinkedIn-profiel (optioneel)
            <input type="url" id="linkedin_url" name="linkedin_url" value="<?= e($form['linkedin_url']) ?>" maxlength="300" placeholder="https://www.linkedin.com/in/...">
        </label>
        <label for="google_business_url">Google Business-profiel (optioneel)
            <input type="url" id="google_business_url" name="google_business_url" value="<?= e($form['google_business_url']) ?>" maxlength="300" placeholder="https://g.page/...">
        </label>
    </div>

    <label for="person_name">Naam behandelaar (optioneel)</label>
    <input type="text" id="person_name" name="person_name" value="<?= e($form['person_name']) ?>" maxlength="150" placeholder="bv. Wendy Tulkens">
    <p class="field-hint">Vereist om als persoon in het schema te verschijnen — de velden hieronder worden pas gebruikt als dit is ingevuld.</p>

    <div class="page-form-row">
        <label for="person_job_title">Functietitel (optioneel)
            <input type="text" id="person_job_title" name="person_job_title" value="<?= e($form['person_job_title']) ?>" maxlength="150" placeholder="bv. Psychotherapeut">
        </label>
        <label for="person_expertise">Expertise (optioneel, kommagescheiden)
            <input type="text" id="person_expertise" name="person_expertise" value="<?= e($form['person_expertise']) ?>" maxlength="500" placeholder="bv. Stressmanagement, Rouwverwerking, Ouderschap">
        </label>
    </div>

    <label for="person_bio">Korte bio (optioneel)</label>
    <textarea id="person_bio" name="person_bio" rows="3" maxlength="1000"><?= e($form['person_bio']) ?></textarea>

    <div class="page-form-actions">
        <button type="submit">Opslaan</button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
