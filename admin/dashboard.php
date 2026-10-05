<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ga_client.php';
require_login();

$pageCount = $mysqli->query('SELECT COUNT(*) AS total FROM pages')->fetch_assoc()['total'];
$publishedCount = $mysqli->query('SELECT COUNT(*) AS total FROM pages WHERE published = 1')->fetch_assoc()['total'];
$unreadCount = $mysqli->query('SELECT COUNT(*) AS total FROM contact_submissions WHERE is_read = 0')->fetch_assoc()['total'];

$recentSubmissions = $mysqli->query(
    'SELECT id, name, email, created_at FROM contact_submissions ORDER BY created_at DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

$analytics = ga_fetch_report($config, $mysqli, isset($_GET['refresh_analytics']));

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<h1>Dashboard</h1>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-number"><?= (int) $pageCount ?></span>
        <span class="stat-label">Pagina's totaal</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?= (int) $publishedCount ?></span>
        <span class="stat-label">Gepubliceerd</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?= (int) $unreadCount ?></span>
        <span class="stat-label">Ongelezen berichten</span>
    </div>
</div>

<h2>Bezoekers (Google Analytics)</h2>
<?php if ($analytics['status'] === 'not_configured'): ?>
    <p class="field-hint">
        Nog niet gekoppeld. Vul <code>google_analytics.property_id</code>,
        <code>service_account_email</code> en
        <code>service_account_private_key</code> in in
        <code>config/config.php</code> om bezoekerscijfers hier te tonen
        (zie README voor de volledige opzet). Je kan intussen ook altijd
        terecht op <a href="https://analytics.google.com" target="_blank" rel="noopener">analytics.google.com</a>.
    </p>
<?php elseif ($analytics['status'] === 'error'): ?>
    <p class="flash flash-error">
        Kon geen bezoekerscijfers ophalen bij Google Analytics. Controleer
        de gegevens onder <code>google_analytics</code> in
        <code>config/config.php</code> (property-ID, service-account e-mail
        en de toegang van dat account tot je GA4-property).
        <?php if (!empty($analytics['error_detail'])): ?>
            <br><strong>Reden:</strong> <?= e($analytics['error_detail']) ?>
        <?php endif; ?>
    </p>
<?php else: ?>
    <?php if ($analytics['status'] === 'stale'): ?>
        <p class="field-hint">
            Kon niet vernieuwen bij Google — dit zijn de laatst gekende
            cijfers (<?= e(date('d/m/Y H:i', strtotime($analytics['fetched_at']))) ?>).
            <?php if (!empty($analytics['error_detail'])): ?>
                <br><strong>Reden:</strong> <?= e($analytics['error_detail']) ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-number"><?= (int) $analytics['data']['active_users_7d'] ?></span>
            <span class="stat-label">Bezoekers (laatste 7 dagen)</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= (int) $analytics['data']['page_views_7d'] ?></span>
            <span class="stat-label">Paginaweergaven (laatste 7 dagen)</span>
        </div>
    </div>
    <?php if (!empty($analytics['data']['top_pages'])): ?>
        <div class="table-scroll">
        <table class="admin-table">
            <thead>
            <tr><th>Pagina</th><th>Weergaven</th></tr>
            </thead>
            <tbody>
            <?php foreach ($analytics['data']['top_pages'] as $page): ?>
                <tr>
                    <td><?= e($page['path']) ?></td>
                    <td><?= (int) $page['views'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    <?php if ($analytics['status'] === 'ok'): ?>
        <p class="field-hint">
            Bijgewerkt op <?= e(date('d/m/Y H:i', strtotime($analytics['fetched_at']))) ?>
            — <a href="dashboard.php?refresh_analytics=1">nu vernieuwen</a>.
        </p>
    <?php endif; ?>
<?php endif; ?>

<h2>Recente contactberichten</h2>
<?php if (empty($recentSubmissions)): ?>
    <p>Nog geen berichten ontvangen.</p>
<?php else: ?>
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
        <tr><th>Naam</th><th>E-mail</th><th>Datum</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recentSubmissions as $submission): ?>
            <tr>
                <td><?= e($submission['name']) ?></td>
                <td><?= e($submission['email']) ?></td>
                <td><?= e(date('d/m/Y H:i', strtotime($submission['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p><a href="submissions.php">Alle berichten bekijken →</a></p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
