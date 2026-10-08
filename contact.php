<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/mailer.php';

$errors = [];
$old = ['name' => '', 'email' => '', 'message' => ''];
$recaptchaSiteKey = (string) ($config['recaptcha']['site_key'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Honeypot field: real visitors never fill this in, bots usually do.
    if (!empty($_POST['website'])) {
        redirect('/contact?verzonden=1');
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    // Timing check: the render timestamp is stored server-side in the
    // session (not a forgeable hidden field) when the form was last shown.
    // A real visitor needs at least a few seconds to read and fill it in;
    // a submission faster than that is almost always a bot.
    $renderedAt = (int) ($_SESSION['contact_form_rendered_at'] ?? 0);
    $submittedTooFast = $renderedAt > 0 && (time() - $renderedAt) < 3;

    // Rate limit: max 3 submissions per IP per 10 minutes, using the
    // ip_address/created_at already stored on every submission.
    $rateLimited = false;
    if ($ip !== '') {
        $stmt = $mysqli->prepare(
            'SELECT COUNT(*) AS total FROM contact_submissions WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)'
        );
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $rateLimited = (int) $stmt->get_result()->fetch_assoc()['total'] >= 3;
        $stmt->close();
    }

    $old['name'] = trim((string) ($_POST['name'] ?? ''));
    $old['email'] = trim((string) ($_POST['email'] ?? ''));
    $old['message'] = trim(normalize_newlines((string) ($_POST['message'] ?? '')));

    if ($old['name'] === '' || mb_strlen($old['name']) > 150) {
        $errors[] = 'Vul een geldige naam in.';
    }
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) {
        $errors[] = 'Vul een geldig e-mailadres in.';
    }
    if ($old['message'] === '') {
        $errors[] = 'Vul een bericht in.';
    }
    if ($rateLimited) {
        $errors[] = 'Je hebt de laatste tijd al meerdere berichten verstuurd. Probeer het binnen enkele minuten opnieuw.';
    }
    if ($submittedTooFast || !recaptcha_verify($config['recaptcha']['secret_key'] ?? '', (string) ($_POST['recaptcha_token'] ?? ''), $ip)) {
        // Deliberately the same generic message as the other validation
        // errors — a bot shouldn't learn which specific check caught it.
        $errors[] = 'Je bericht kon niet verstuurd worden. Probeer het opnieuw.';
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare(
            'INSERT INTO contact_submissions (name, email, message, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('ssss', $old['name'], $old['email'], $old['message'], $ip);
        $stmt->execute();
        $stmt->close();

        if (!empty($_POST['newsletter_optin'])) {
            newsletter_subscribe($mysqli, $old['email'], 'contact_form', extract_first_name($old['name']));
        }

        send_contact_notification($config, $mysqli, $old);

        redirect('/contact?verzonden=1');
    }
}

$_SESSION['contact_form_rendered_at'] = time();

$contactSettings = get_site_settings($mysqli);
$contactBlocks = decode_blocks($contactSettings['content']);
$contactFormBgClass = block_bg_class(sanitize_block_background($contactSettings['contact_form_background'] ?? ''));
$contactFormClass = 'contact-form' . ($contactFormBgClass !== '' ? ' block' . $contactFormBgClass : '');

$pageTitle = $contactSettings['contact_meta_title'] !== '' ? $contactSettings['contact_meta_title'] : 'Contact';
$metaDescription = $contactSettings['contact_meta_description'] !== '' ? $contactSettings['contact_meta_description'] : 'Neem contact op met ' . $siteName . '.';
$canonicalUrl = absolute_url($siteUrl, '/contact');
$ogImage = og_image_url($siteUrl, first_image_url($contactBlocks));
$nav = $mysqli->query(
    'SELECT slug, title FROM pages WHERE published = 1 AND is_homepage = 0 AND show_in_menu = 1 ORDER BY nav_order ASC, title ASC'
)->fetch_all(MYSQLI_ASSOC);

// Contact isn't a CMS page itself — match the homepage's variant so the
// site doesn't suddenly look different on this one utility page.
$homepageVariant = $mysqli->query(
    'SELECT theme_variant FROM pages WHERE is_homepage = 1 LIMIT 1'
)->fetch_assoc();
$themeVariant = $homepageVariant['theme_variant'] ?? 'a';

require __DIR__ . '/includes/header.php';
?>

<article class="page-content">
    <h1>Contact</h1>
    <?= render_blocks($contactBlocks, $mysqli) ?>

    <?php if (isset($_GET['verzonden'])): ?>
        <p class="flash flash-success">Bedankt, je bericht is verzonden. We nemen zo snel mogelijk contact op.</p>
    <?php else: ?>

        <?php if (!empty($errors)): ?>
            <ul class="form-errors">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="/contact" class="<?= e($contactFormClass) ?>">
            <?= csrf_field() ?>
            <div class="form-field" style="position:absolute;left:-9999px;" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <label for="name">Naam</label>
            <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" required>

            <label for="email">E-mailadres</label>
            <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>

            <label for="message">Bericht</label>
            <textarea id="message" name="message" rows="6" required><?= e($old['message']) ?></textarea>

            <label class="form-checkbox-row" for="newsletter_optin">
                <input type="checkbox" id="newsletter_optin" name="newsletter_optin" value="1">
                <span>Ja, ik wil graag de nieuwsbrief ontvangen.</span>
            </label>

            <?= render_privacy_note($mysqli) ?>
            <button type="submit">Versturen</button>
        </form>
    <?php endif; ?>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
