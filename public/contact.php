<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$errors = [];
$old = ['name' => '', 'email' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Honeypot field: real visitors never fill this in, bots usually do.
    if (!empty($_POST['website'])) {
        redirect('/contact?verzonden=1');
    }

    $old['name'] = trim((string) ($_POST['name'] ?? ''));
    $old['email'] = trim((string) ($_POST['email'] ?? ''));
    $old['message'] = trim((string) ($_POST['message'] ?? ''));

    if ($old['name'] === '' || mb_strlen($old['name']) > 150) {
        $errors[] = 'Vul een geldige naam in.';
    }
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) {
        $errors[] = 'Vul een geldig e-mailadres in.';
    }
    if ($old['message'] === '') {
        $errors[] = 'Vul een bericht in.';
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare(
            'INSERT INTO contact_submissions (name, email, message, ip_address) VALUES (?, ?, ?, ?)'
        );
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt->bind_param('ssss', $old['name'], $old['email'], $old['message'], $ip);
        $stmt->execute();
        $stmt->close();

        redirect('/contact?verzonden=1');
    }
}

$pageTitle = 'Contact';
$metaDescription = 'Neem contact op met ' . $siteName . '.';
$canonicalUrl = absolute_url($siteUrl, '/contact');
$nav = $mysqli->query(
    'SELECT slug, title FROM pages WHERE published = 1 AND is_homepage = 0 ORDER BY nav_order ASC, title ASC'
)->fetch_all(MYSQLI_ASSOC);

// Contact isn't a CMS page itself — match the homepage's variant so the
// site doesn't suddenly look different on this one utility page.
$homepageVariant = $mysqli->query(
    'SELECT theme_variant FROM pages WHERE is_homepage = 1 LIMIT 1'
)->fetch_assoc();
$themeVariant = $homepageVariant['theme_variant'] ?? 'a';

require __DIR__ . '/../includes/header.php';
?>

<article class="page-content">
    <h1>Contact</h1>

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

        <form method="post" action="/contact" class="contact-form">
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

            <button type="submit">Versturen</button>
        </form>
    <?php endif; ?>
</article>

<?php require __DIR__ . '/../includes/footer.php'; ?>
