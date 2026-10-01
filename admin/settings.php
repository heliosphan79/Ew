<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$settings = get_site_settings($mysqli);
$form = $settings;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['address'] = trim((string) ($_POST['address'] ?? ''));
    $form['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));

    if (mb_strlen($form['address']) > 255) {
        $errors[] = 'Adres is te lang (max. 255 tekens).';
    }
    if (mb_strlen($form['phone']) > 50) {
        $errors[] = 'Telefoonnummer is te lang (max. 50 tekens).';
    }
    if ($form['email'] !== '' && (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 190)) {
        $errors[] = 'Vul een geldig e-mailadres in (of laat leeg).';
    }

    if (empty($errors)) {
        $address = $form['address'] !== '' ? $form['address'] : null;
        $phone = $form['phone'] !== '' ? $form['phone'] : null;
        $email = $form['email'] !== '' ? $form['email'] : null;

        $stmt = $mysqli->prepare('UPDATE site_settings SET address = ?, phone = ?, email = ? WHERE id = 1');
        $stmt->bind_param('sss', $address, $phone, $email);
        $stmt->execute();
        $stmt->close();

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

    <button type="submit">Opslaan</button>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
