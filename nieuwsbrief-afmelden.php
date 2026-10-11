<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_GET['t'] ?? '');
$done = false;

if ($token !== '') {
    // One click is the whole flow — no login, no confirmation step. That's
    // the legally-expected "one-click unsubscribe", and there is no
    // sensitive action being protected here: the token itself is the
    // authorization, same principle as the password-reset link.
    $stmt = $mysqli->prepare(
        "UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE unsubscribe_token = ?"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $done = $stmt->affected_rows > 0;
    $stmt->close();
}

$unsubscribeMessage = $done ? resolve_newsletter_unsubscribe_message(get_site_settings($mysqli), $siteName) : '';

$pageTitle = 'Uitschrijven nieuwsbrief';
$metaDescription = null;
$canonicalUrl = null;
$ogImage = null;
$themeVariant = null;
$nav = $mysqli->query(
    'SELECT slug, title FROM pages WHERE published = 1 AND is_homepage = 0 AND show_in_menu = 1 ORDER BY nav_order ASC, title ASC'
)->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/includes/header.php';
?>

<article class="page-content">
    <h1>Uitschrijven nieuwsbrief</h1>
    <?php if ($done): ?>
        <p><?= e($unsubscribeMessage) ?></p>
    <?php else: ?>
        <p>Deze link is ongeldig of je was al uitgeschreven.</p>
    <?php endif; ?>
    <p><a href="/">Terug naar de homepage</a></p>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
