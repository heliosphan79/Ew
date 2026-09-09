<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$page = null;

if ($id) {
    $stmt = $mysqli->prepare('SELECT * FROM pages WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $page = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$page) {
        set_flash('error', 'Pagina niet gevonden.');
        redirect('pages.php');
    }
}

$errors = [];
$form = [
    'title' => $page['title'] ?? '',
    'slug' => $page['slug'] ?? '',
    'meta_description' => $page['meta_description'] ?? '',
    'published' => $page['published'] ?? 0,
    'is_homepage' => $page['is_homepage'] ?? 0,
    'show_in_menu' => $page['show_in_menu'] ?? 1,
    'nav_order' => $page['nav_order'] ?? 0,
    'theme_variant' => normalize_theme_variant($page['theme_variant'] ?? null),
];
$blocksForEditor = $page ? decode_blocks($page['content']) : [];
$contentJson = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['slug'] = slugify((string) ($_POST['slug'] !== '' ? $_POST['slug'] : $form['title']));
    $form['meta_description'] = trim((string) ($_POST['meta_description'] ?? ''));
    $form['published'] = isset($_POST['published']) ? 1 : 0;
    $form['is_homepage'] = isset($_POST['is_homepage']) ? 1 : 0;
    $form['show_in_menu'] = isset($_POST['show_in_menu']) ? 1 : 0;
    $form['nav_order'] = (int) ($_POST['nav_order'] ?? 0);
    $form['theme_variant'] = normalize_theme_variant($_POST['theme_variant'] ?? null);

    $rawBlocks = json_decode((string) ($_POST['blocks_json'] ?? '[]'), true);
    $blocksForEditor = is_array($rawBlocks) ? $rawBlocks : [];

    if ($form['title'] === '' || mb_strlen($form['title']) > 200) {
        $errors[] = 'Vul een titel in (max. 200 tekens).';
    }
    if ($form['slug'] === '') {
        $errors[] = 'De slug mag niet leeg zijn.';
    }

    $sanitized = sanitize_blocks($blocksForEditor);
    $errors = array_merge($errors, $sanitized['errors']);
    if (empty($sanitized['blocks'])) {
        $errors[] = 'Voeg minstens één geldig blok toe aan de pagina.';
    }

    if (empty($errors)) {
        // Slug must be unique across pages (excluding this one when editing).
        $slugCheck = $mysqli->prepare('SELECT id FROM pages WHERE slug = ? AND id != ?');
        $excludeId = $id ?? 0;
        $slugCheck->bind_param('si', $form['slug'], $excludeId);
        $slugCheck->execute();
        if ($slugCheck->get_result()->fetch_assoc()) {
            $errors[] = 'Er bestaat al een pagina met deze slug. Kies een andere.';
        }
        $slugCheck->close();
    }

    if (empty($errors)) {
        $contentJson = json_encode($sanitized['blocks'], JSON_UNESCAPED_UNICODE);
        $removedUploadUrls = $id
            ? array_diff(extract_upload_urls(decode_blocks($page['content'])), extract_upload_urls($sanitized['blocks']))
            : [];

        $mysqli->begin_transaction();
        try {
            if ($form['is_homepage']) {
                $mysqli->query('UPDATE pages SET is_homepage = 0');
            }

            if ($id) {
                $stmt = $mysqli->prepare(
                    'UPDATE pages SET title = ?, slug = ?, content = ?, theme_variant = ?, meta_description = ?, published = ?, is_homepage = ?, show_in_menu = ?, nav_order = ? WHERE id = ?'
                );
                $stmt->bind_param(
                    'sssssiiiii',
                    $form['title'],
                    $form['slug'],
                    $contentJson,
                    $form['theme_variant'],
                    $form['meta_description'],
                    $form['published'],
                    $form['is_homepage'],
                    $form['show_in_menu'],
                    $form['nav_order'],
                    $id
                );
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $mysqli->prepare(
                    'INSERT INTO pages (title, slug, content, theme_variant, meta_description, published, is_homepage, show_in_menu, nav_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'sssssiiii',
                    $form['title'],
                    $form['slug'],
                    $contentJson,
                    $form['theme_variant'],
                    $form['meta_description'],
                    $form['published'],
                    $form['is_homepage'],
                    $form['show_in_menu'],
                    $form['nav_order']
                );
                $stmt->execute();
                $stmt->close();
            }

            $mysqli->commit();
        } catch (mysqli_sql_exception $e) {
            $mysqli->rollback();
            throw $e;
        }

        delete_orphaned_uploads($mysqli, $removedUploadUrls);

        set_flash('success', 'Pagina opgeslagen.');
        redirect('pages.php');
    }
}

$pageTitle = $id ? 'Pagina bewerken' : 'Nieuwe pagina';
require __DIR__ . '/includes/header.php';
?>

<h1><?= e($pageTitle) ?></h1>

<?php if (!empty($errors)): ?>
    <ul class="form-errors">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="page-edit.php<?= $id ? '?id=' . (int) $id : '' ?>" class="page-form">
    <?= csrf_field() ?>

    <label for="title">Titel</label>
    <input type="text" id="title" name="title" value="<?= e($form['title']) ?>" required>

    <label for="slug">Slug (URL)</label>
    <input type="text" id="slug" name="slug" value="<?= e($form['slug']) ?>" placeholder="wordt automatisch afgeleid van de titel indien leeg">

    <label for="meta_description">Meta-omschrijving (SEO)</label>
    <input type="text" id="meta_description" name="meta_description" value="<?= e($form['meta_description']) ?>" maxlength="300">

    <label for="theme_variant">Frontend-variant</label>
    <select id="theme_variant" name="theme_variant">
        <?php foreach (THEME_VARIANTS as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $form['theme_variant'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Inhoud</label>
    <div id="block-editor" class="block-editor"></div>
    <div id="block-toolbar" class="block-toolbar"></div>
    <script type="application/json" id="initial-blocks"><?= json_encode($blocksForEditor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
    <input type="hidden" id="blocks_json" name="blocks_json">

    <div class="page-form-row">
        <label class="checkbox-label">
            <input type="checkbox" name="published" <?= $form['published'] ? 'checked' : '' ?>>
            Gepubliceerd
        </label>
        <label class="checkbox-label">
            <input type="checkbox" name="is_homepage" <?= $form['is_homepage'] ? 'checked' : '' ?>>
            Als homepagina instellen
        </label>
        <label class="checkbox-label">
            <input type="checkbox" name="show_in_menu" <?= $form['show_in_menu'] ? 'checked' : '' ?>>
            Tonen in hoofdmenu
        </label>
        <label>
            Volgorde in menu
            <input type="number" name="nav_order" value="<?= (int) $form['nav_order'] ?>" style="width:5rem;">
        </label>
    </div>
    <p class="field-hint">
        Staat "Tonen in hoofdmenu" uit, dan blijft de pagina bereikbaar via
        haar eigen link (bv. vanuit een knoppenblok) maar krijgt ze geen
        plaats in de navigatie.
    </p>

    <button type="submit">Opslaan</button>
    <a href="pages.php" class="button-secondary">Annuleren</a>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
