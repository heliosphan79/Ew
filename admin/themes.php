<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$themes = $mysqli->query(
    'SELECT id, slug, name, description, logo_file, schemes, is_builtin FROM themes ORDER BY is_builtin DESC, name ASC'
)->fetch_all(MYSQLI_ASSOC);

$activeThemeId = get_active_theme($mysqli)['id'];

$pageTitle = "Thema's";
require __DIR__ . '/includes/header.php';
?>

<div class="admin-header-row">
    <h1>Thema's</h1>
</div>
<p>
    Een thema bepaalt de volledige visuele stijl van de site: lettertypes,
    kleuren, logo en vormgeving — elk thema heeft daarbinnen 3 eigen
    kleurschema's (het "Kleurschema" dat je per pagina kiest). Enkel
    het actieve thema is zichtbaar op de site.
</p>

<div class="table-scroll">
<table class="admin-table">
    <thead>
    <tr>
        <th>Logo</th>
        <th>Naam</th>
        <th>Kleurschema's</th>
        <th>Status</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($themes as $theme): ?>
        <?php $schemes = json_decode((string) $theme['schemes'], true) ?: []; ?>
        <tr>
            <td>
                <?php if ($theme['logo_file']): ?>
                    <img src="/themes/<?= e($theme['slug']) ?>/<?= e($theme['logo_file']) ?>" alt="" width="32" height="32" style="object-fit:contain;">
                <?php else: ?>
                    <span class="field-hint">—</span>
                <?php endif; ?>
            </td>
            <td>
                <strong><?= e($theme['name']) ?></strong>
                <?php if ($theme['description']): ?><p class="field-hint" style="margin:0.2rem 0 0;"><?= e($theme['description']) ?></p><?php endif; ?>
            </td>
            <td><?= e(implode(', ', array_map(fn($s) => $s['label'], $schemes))) ?></td>
            <td>
                <?= (int) $theme['id'] === $activeThemeId ? '<span class="badge badge-ok">Actief</span>' : '<span class="badge badge-draft">Niet actief</span>' ?>
                <?php if ($theme['is_builtin']): ?><p class="field-hint" style="margin:0.2rem 0 0;">Ingebouwd</p><?php endif; ?>
            </td>
            <td class="admin-table-actions">
                <a href="theme-preview.php?id=<?= (int) $theme['id'] ?>" target="_blank" rel="noopener" class="button-secondary">Voorbeeld</a>
                <?php if ((int) $theme['id'] !== $activeThemeId): ?>
                    <form method="post" action="theme-activate.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $theme['id'] ?>">
                        <button type="submit" class="button-secondary">Activeren</button>
                    </form>
                <?php endif; ?>
                <?php if (!$theme['is_builtin']): ?>
                    <form method="post" action="theme-delete.php" onsubmit="return confirm('Thema \'<?= e(addslashes($theme['name'])) ?>\' definitief verwijderen?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $theme['id'] ?>">
                        <button type="submit" class="icon-btn icon-btn-danger" title="Verwijderen">
                            <?= admin_icon('trash') ?><span class="visually-hidden">Verwijderen</span>
                        </button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<hr style="margin: 2rem 0;">

<h2>Nieuw thema uploaden</h2>
<p class="field-hint">
    Een <code>.zip</code>-bestand met <code>theme.json</code>,
    <code>style.css</code> en optioneel een <code>logo.svg</code> (of
    .png/.jpg/.webp) op het hoogste niveau — zie
    <code>themes/README.md</code> voor het volledige contract.
</p>
<form method="post" action="theme-upload.php" enctype="multipart/form-data" class="page-form" style="max-width: 500px;">
    <?= csrf_field() ?>
    <label for="theme_zip">Thema-zip</label>
    <input type="file" id="theme_zip" name="theme_zip" accept=".zip,application/zip" required>
    <div class="page-form-actions">
        <button type="submit"><?= admin_icon('upload') ?> Uploaden</button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
