<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

// Prefill with the *effective* config (database, falling back field-by-
// field to config/config.php) rather than the raw, possibly-still-empty
// database row — so on first visit after this feature ships, the admin
// sees the mail setup that's actually in use today, and simply clicking
// "Opslaan" (without changing anything) is enough to move it fully into
// the database.
$effective = resolve_mail_config($config, $mysqli);
$hasStoredPassword = $effective['password'] !== '';

$form = $effective;
$form['password'] = ''; // never echo a secret back into the page source
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['host'] = trim((string) ($_POST['host'] ?? ''));
    $form['port'] = trim((string) ($_POST['port'] ?? ''));
    $form['encryption'] = (string) ($_POST['encryption'] ?? 'tls');
    $form['username'] = trim((string) ($_POST['username'] ?? ''));
    $submittedPassword = (string) ($_POST['password'] ?? '');
    $form['from_email'] = trim((string) ($_POST['from_email'] ?? ''));
    $form['from_name'] = trim((string) ($_POST['from_name'] ?? ''));
    $form['reply_to'] = trim((string) ($_POST['reply_to'] ?? ''));
    $form['to_email'] = trim((string) ($_POST['to_email'] ?? ''));

    if ($form['host'] !== '' && mb_strlen($form['host']) > 190) {
        $errors[] = 'Server (host) is te lang.';
    }
    $port = $form['port'] !== '' ? (int) $form['port'] : 587;
    if ($port < 1 || $port > 65535) {
        $errors[] = 'Poort moet een getal zijn tussen 1 en 65535.';
    }
    if (!in_array($form['encryption'], ['tls', 'ssl', 'none'], true)) {
        $errors[] = 'Ongeldige encryptie-optie.';
    }
    foreach (['from_email' => 'Afzenderadres', 'reply_to' => 'Reply-to-adres', 'to_email' => 'Ontvanger contactmeldingen'] as $field => $label) {
        if ($form[$field] !== '' && (!filter_var($form[$field], FILTER_VALIDATE_EMAIL) || mb_strlen($form[$field]) > 190)) {
            $errors[] = $label . ': vul een geldig e-mailadres in (of laat leeg).';
        }
    }
    if (mb_strlen($form['from_name']) > 190) {
        $errors[] = 'Afzendernaam is te lang.';
    }

    if (empty($errors)) {
        // Leaving the password field blank keeps whatever is in effect
        // right now (database if already set, else the legacy config.php
        // value) — never silently blanks out a working password just
        // because the admin didn't feel like retyping it.
        $passwordToStore = $submittedPassword !== '' ? $submittedPassword : $effective['password'];

        $host = $form['host'] !== '' ? $form['host'] : null;
        $username = $form['username'] !== '' ? $form['username'] : null;
        $password = $passwordToStore !== '' ? $passwordToStore : null;
        $fromEmail = $form['from_email'] !== '' ? $form['from_email'] : null;
        $fromName = $form['from_name'] !== '' ? $form['from_name'] : null;
        $replyTo = $form['reply_to'] !== '' ? $form['reply_to'] : null;
        $toEmail = $form['to_email'] !== '' ? $form['to_email'] : null;

        $stmt = $mysqli->prepare(
            'UPDATE mail_settings SET host = ?, port = ?, encryption = ?, username = ?, password = ?,
                from_email = ?, from_name = ?, reply_to = ?, to_email = ? WHERE id = 1'
        );
        $stmt->bind_param('sisssssss', $host, $port, $form['encryption'], $username, $password, $fromEmail, $fromName, $replyTo, $toEmail);
        $stmt->execute();
        $stmt->close();

        set_flash('success', 'E-mailinstellingen opgeslagen.');
        redirect('mail-settings.php');
    }
}

$pageTitle = 'E-mailinstellingen';
require __DIR__ . '/includes/header.php';
?>

<h1>E-mailinstellingen</h1>
<p>
    SMTP-server voor alle mail die de site verstuurt: meldingen van het
    contactformulier, wachtwoordherstel voor het beheerpaneel, en de
    nieuwsbrief. Nog niets ingevuld? Dan wordt teruggevallen op
    <code>config/config.php</code> (de vroegere manier) — eenmaal hier
    opgeslagen heeft deze pagina voorrang.
</p>

<?php if (!empty($errors)): ?>
    <ul class="form-errors">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="mail-settings.php" class="page-form">
    <?= csrf_field() ?>

    <label for="host">Server (host)</label>
    <input type="text" id="host" name="host" value="<?= e($form['host']) ?>" placeholder="smtp.voorbeeld.be">

    <label for="port">Poort</label>
    <input type="number" id="port" name="port" value="<?= e((string) $form['port']) ?>" min="1" max="65535">

    <label for="encryption">Encryptie</label>
    <select id="encryption" name="encryption">
        <option value="tls"<?= $form['encryption'] === 'tls' ? ' selected' : '' ?>>TLS (STARTTLS, meestal poort 587)</option>
        <option value="ssl"<?= $form['encryption'] === 'ssl' ? ' selected' : '' ?>>SSL (impliciete TLS, meestal poort 465)</option>
        <option value="none"<?= $form['encryption'] === 'none' ? ' selected' : '' ?>>Geen (enkel voor een vertrouwde lokale relay)</option>
    </select>

    <label for="username">Gebruikersnaam</label>
    <input type="text" id="username" name="username" value="<?= e($form['username']) ?>" autocomplete="off">

    <label for="password">Wachtwoord</label>
    <input type="password" id="password" name="password" value="" autocomplete="new-password"
           placeholder="<?= $hasStoredPassword ? '•••••••• (laat leeg om te behouden)' : '' ?>">

    <label for="from_email">Afzenderadres</label>
    <input type="email" id="from_email" name="from_email" value="<?= e($form['from_email']) ?>">
    <p class="field-hint">De meeste providers vereisen dat dit (nagenoeg) overeenkomt met de geauthenticeerde mailbox, anders wordt de mail geweigerd of als spam gemarkeerd.</p>

    <label for="from_name">Afzendernaam</label>
    <input type="text" id="from_name" name="from_name" value="<?= e($form['from_name']) ?>" placeholder="Eigen-Wijzer website">

    <label for="reply_to">Reply-to-adres (optioneel)</label>
    <input type="email" id="reply_to" name="reply_to" value="<?= e($form['reply_to']) ?>">
    <p class="field-hint">Waar een antwoord op de nieuwsbrief naartoe gaat. Leeg = geen Reply-To-header, de ontvanger antwoordt dan op het afzenderadres zelf.</p>

    <label for="to_email">Ontvanger contactmeldingen (optioneel)</label>
    <input type="email" id="to_email" name="to_email" value="<?= e($form['to_email']) ?>">
    <p class="field-hint">Waar een melding van een nieuw contactformulier-bericht naartoe gaat. Leeg = het e-mailadres onder "Instellingen".</p>

    <div class="page-form-actions">
        <button type="submit">Opslaan</button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
