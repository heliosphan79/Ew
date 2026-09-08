<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_login();

$pageCount = $mysqli->query('SELECT COUNT(*) AS total FROM pages')->fetch_assoc()['total'];
$publishedCount = $mysqli->query('SELECT COUNT(*) AS total FROM pages WHERE published = 1')->fetch_assoc()['total'];
$unreadCount = $mysqli->query('SELECT COUNT(*) AS total FROM contact_submissions WHERE is_read = 0')->fetch_assoc()['total'];

$recentSubmissions = $mysqli->query(
    'SELECT id, name, email, created_at FROM contact_submissions ORDER BY created_at DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

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

<h2>Recente contactberichten</h2>
<?php if (empty($recentSubmissions)): ?>
    <p>Nog geen berichten ontvangen.</p>
<?php else: ?>
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
    <p><a href="submissions.php">Alle berichten bekijken →</a></p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
