<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_login();

$pages = $mysqli->query(
    'SELECT id, slug, title, theme_variant, published, is_homepage, nav_order, updated_at FROM pages ORDER BY nav_order ASC, title ASC'
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Pagina's";
require __DIR__ . '/includes/header.php';
?>

<div class="admin-header-row">
    <h1>Pagina's</h1>
    <a class="button" href="page-edit.php">+ Nieuwe pagina</a>
</div>

<?php if (empty($pages)): ?>
    <p>Nog geen pagina's aangemaakt.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
        <tr>
            <th>Titel</th>
            <th>Slug</th>
            <th>Variant</th>
            <th>Status</th>
            <th>Homepagina</th>
            <th>Laatst bewerkt</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($pages as $page): ?>
            <tr>
                <td><?= e($page['title']) ?></td>
                <td>
                    <?php $liveUrl = $page['is_homepage'] ? '/' : '/pagina/' . $page['slug']; ?>
                    <a href="<?= e($liveUrl) ?>" target="_blank" rel="noopener"><code><?= e($liveUrl) ?></code></a>
                </td>
                <td><?= e(THEME_VARIANTS[$page['theme_variant']] ?? $page['theme_variant']) ?></td>
                <td><?= $page['published'] ? '<span class="badge badge-ok">Gepubliceerd</span>' : '<span class="badge badge-draft">Concept</span>' ?></td>
                <td><?= $page['is_homepage'] ? 'Ja' : '' ?></td>
                <td><?= e(date('d/m/Y H:i', strtotime($page['updated_at']))) ?></td>
                <td class="admin-table-actions">
                    <a href="page-edit.php?id=<?= (int) $page['id'] ?>">Bewerken</a>
                    <form method="post" action="page-delete.php" onsubmit="return confirm('Deze pagina definitief verwijderen?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">
                        <button type="submit" class="link-button">Verwijderen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
