<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$settings = get_site_settings($mysqli);
$form = $settings;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['contact_success_message'] = trim(normalize_newlines((string) ($_POST['contact_success_message'] ?? '')));
    $form['event_registration_success_message'] = trim(normalize_newlines((string) ($_POST['event_registration_success_message'] ?? '')));
    $form['booking_success_message'] = trim(normalize_newlines((string) ($_POST['booking_success_message'] ?? '')));
    $form['newsletter_unsubscribe_message'] = trim(normalize_newlines((string) ($_POST['newsletter_unsubscribe_message'] ?? '')));

    if (mb_strlen($form['contact_success_message']) > 500) {
        $errors[] = 'Bevestigingsbericht contactformulier is te lang (max. 500 tekens).';
    }
    if (mb_strlen($form['event_registration_success_message']) > 500) {
        $errors[] = 'Bevestigingsbericht evenement-interesse is te lang (max. 500 tekens).';
    }
    if (mb_strlen($form['booking_success_message']) > 500) {
        $errors[] = 'Bevestigingsbericht afspraak is te lang (max. 500 tekens).';
    }
    if (mb_strlen($form['newsletter_unsubscribe_message']) > 500) {
        $errors[] = 'Uitschrijfbericht nieuwsbrief is te lang (max. 500 tekens).';
    }

    if (empty($errors)) {
        $contactSuccessMessage = $form['contact_success_message'] !== '' ? $form['contact_success_message'] : null;
        $eventRegistrationSuccessMessage = $form['event_registration_success_message'] !== '' ? $form['event_registration_success_message'] : null;
        $bookingSuccessMessage = $form['booking_success_message'] !== '' ? $form['booking_success_message'] : null;
        $newsletterUnsubscribeMessage = $form['newsletter_unsubscribe_message'] !== '' ? $form['newsletter_unsubscribe_message'] : null;

        $stmt = $mysqli->prepare(
            'UPDATE site_settings SET contact_success_message = ?, event_registration_success_message = ?,
                booking_success_message = ?, newsletter_unsubscribe_message = ?
             WHERE id = 1'
        );
        $stmt->bind_param(
            'ssss',
            $contactSuccessMessage, $eventRegistrationSuccessMessage, $bookingSuccessMessage, $newsletterUnsubscribeMessage
        );
        $stmt->execute();
        $stmt->close();

        set_flash('success', 'Instellingen opgeslagen.');
        redirect('settings-messages.php');
    }
}

$pageTitle = 'Instellingen — Bevestigingsberichten';
$settingsTab = 'messages';
require __DIR__ . '/includes/header.php';
?>

<h1>Instellingen</h1>
<?php require __DIR__ . '/includes/settings-tabs.php'; ?>
<p class="field-hint">
    Wat de bezoeker zelf te zien krijgt na het versturen van het
    contactformulier, het doorgeven van interesse voor een evenement, het
    bevestigen van een afspraak, of het uitschrijven voor de nieuwsbrief.
    Leeg = de standaardtekst blijft gebruikt.
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

    <label for="contact_success_message">Na het contactformulier (optioneel)</label>
    <textarea id="contact_success_message" name="contact_success_message" rows="2" maxlength="500" placeholder="Bedankt, je bericht is verzonden. We nemen zo snel mogelijk contact op."><?= e($form['contact_success_message']) ?></textarea>

    <label for="event_registration_success_message">Na een evenement-interesse-opgave (optioneel)</label>
    <textarea id="event_registration_success_message" name="event_registration_success_message" rows="2" maxlength="500" placeholder='Je interesse voor "{{titel}}" is doorgegeven!'><?= e($form['event_registration_success_message']) ?></textarea>
    <p class="field-hint">Gebruik <code>{{titel}}</code> om de naam van het evenement in te voegen.</p>

    <label for="booking_success_message">Na het bevestigen van een afspraak (optioneel)</label>
    <textarea id="booking_success_message" name="booking_success_message" rows="2" maxlength="500" placeholder="Afspraak bevestigd — tot dan!"><?= e($form['booking_success_message']) ?></textarea>

    <label for="newsletter_unsubscribe_message">Na het uitschrijven voor de nieuwsbrief (optioneel)</label>
    <textarea id="newsletter_unsubscribe_message" name="newsletter_unsubscribe_message" rows="2" maxlength="500" placeholder="Je bent uitgeschreven. Je ontvangt geen nieuwsbrief meer van {{site}}."><?= e($form['newsletter_unsubscribe_message']) ?></textarea>
    <p class="field-hint">Gebruik <code>{{site}}</code> om de sitenaam in te voegen.</p>

    <div class="page-form-actions">
        <button type="submit">Opslaan</button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
