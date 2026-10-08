<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$pages = $mysqli->query(
    'SELECT id, slug, title, theme_variant, published, is_homepage, is_privacy_page, show_in_menu, nav_order, updated_at FROM pages ORDER BY nav_order ASC, title ASC'
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Pagina's";
require __DIR__ . '/includes/header.php';
?>

<div class="admin-header-row">
    <h1>Pagina's</h1>
    <a class="button" href="page-edit.php"><?= admin_icon('plus') ?> Nieuwe pagina</a>
</div>

<?php if (empty($pages)): ?>
    <p>Nog geen pagina's aangemaakt.</p>
<?php else: ?>
    <p class="field-hint">
        Versleep een rij aan het handvat (⠿) om de volgorde in het hoofdmenu
        te wijzigen — de nieuwe volgorde wordt meteen opgeslagen.
        <span id="reorder-status" aria-live="polite"></span>
    </p>
    <input type="hidden" id="pages-csrf" value="<?= e(csrf_token()) ?>">
    <div class="table-scroll">
    <table class="admin-table" id="pages-table">
        <thead>
        <tr>
            <th></th>
            <th>Titel</th>
            <th>Slug</th>
            <th>Variant</th>
            <th>Status</th>
            <th>Rol</th>
            <th>In menu</th>
            <th>Laatst bewerkt</th>
            <th></th>
        </tr>
        </thead>
        <tbody id="pages-tbody">
        <?php foreach ($pages as $page): ?>
            <tr data-id="<?= (int) $page['id'] ?>">
                <td><span class="block-drag-handle" draggable="true" title="Verslepen om te herordenen" aria-hidden="true">⠿</span></td>
                <td><?= e($page['title']) ?></td>
                <td>
                    <?php $liveUrl = $page['is_homepage'] ? '/' : '/pagina/' . $page['slug']; ?>
                    <a href="<?= e($liveUrl) ?>" target="_blank" rel="noopener"><code><?= e($liveUrl) ?></code></a>
                </td>
                <td><?= e(THEME_VARIANTS[$page['theme_variant']] ?? $page['theme_variant']) ?></td>
                <td><?= $page['published'] ? '<span class="badge badge-ok">Gepubliceerd</span>' : '<span class="badge badge-draft">Concept</span>' ?></td>
                <td>
                    <?php
                    $pageRoles = [];
                    if ($page['is_homepage']) {
                        $pageRoles[] = 'Homepagina';
                    }
                    if ($page['is_privacy_page']) {
                        $pageRoles[] = 'Privacypagina';
                    }
                    echo e(implode(', ', $pageRoles));
                    ?>
                </td>
                <td><?= ($page['is_homepage'] || $page['show_in_menu']) ? 'Ja' : '<span class="badge badge-draft">Enkel via link</span>' ?></td>
                <td><?= e(date('d/m/Y H:i', strtotime($page['updated_at']))) ?></td>
                <td class="admin-table-actions">
                    <a class="icon-btn icon-btn-accent" href="page-edit.php?id=<?= (int) $page['id'] ?>" title="Bewerken">
                        <?= admin_icon('edit') ?><span class="visually-hidden">Bewerken</span>
                    </a>
                    <form method="post" action="page-delete.php" onsubmit="return confirm('Deze pagina definitief verwijderen?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">
                        <button type="submit" class="icon-btn icon-btn-danger" title="Verwijderen">
                            <?= admin_icon('trash') ?><span class="visually-hidden">Verwijderen</span>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
