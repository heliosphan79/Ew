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
    'content' => $page['content'] ?? '',
    'meta_description' => $page['meta_description'] ?? '',
    'published' => $page['published'] ?? 0,
    'is_homepage' => $page['is_homepage'] ?? 0,
    'nav_order' => $page['nav_order'] ?? 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['slug'] = slugify((string) ($_POST['slug'] !== '' ? $_POST['slug'] : $form['title']));
    $form['content'] = (string) ($_POST['content'] ?? '');
    $form['meta_description'] = trim((string) ($_POST['meta_description'] ?? ''));
    $form['published'] = isset($_POST['published']) ? 1 : 0;
    $form['is_homepage'] = isset($_POST['is_homepage']) ? 1 : 0;
    $form['nav_order'] = (int) ($_POST['nav_order'] ?? 0);

    if ($form['title'] === '' || mb_strlen($form['title']) > 200) {
        $errors[] = 'Vul een titel in (max. 200 tekens).';
    }
    if ($form['slug'] === '') {
        $errors[] = 'De slug mag niet leeg zijn.';
    }
    if (trim(strip_tags($form['content'])) === '') {
        $errors[] = "Vul inhoud in voor de pagina.";
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
        $mysqli->begin_transaction();
        try {
            if ($form['is_homepage']) {
                $mysqli->query('UPDATE pages SET is_homepage = 0');
            }

            if ($id) {
                $stmt = $mysqli->prepare(
                    'UPDATE pages SET title = ?, slug = ?, content = ?, meta_description = ?, published = ?, is_homepage = ?, nav_order = ? WHERE id = ?'
                );
                $stmt->bind_param(
                    'ssssiiii',
                    $form['title'],
                    $form['slug'],
                    $form['content'],
                    $form['meta_description'],
                    $form['published'],
                    $form['is_homepage'],
                    $form['nav_order'],
                    $id
                );
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $mysqli->prepare(
                    'INSERT INTO pages (title, slug, content, meta_description, published, is_homepage, nav_order) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'ssssiii',
                    $form['title'],
                    $form['slug'],
                    $form['content'],
                    $form['meta_description'],
                    $form['published'],
                    $form['is_homepage'],
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

    <label for="editor">Inhoud</label>
    <div id="editor-toolbar"></div>
    <div id="editor"><?= $form['content'] ?></div>
    <textarea id="content" name="content" style="display:none;"><?= e($form['content']) ?></textarea>

    <div class="page-form-row">
        <label class="checkbox-label">
            <input type="checkbox" name="published" <?= $form['published'] ? 'checked' : '' ?>>
            Gepubliceerd
        </label>
        <label class="checkbox-label">
            <input type="checkbox" name="is_homepage" <?= $form['is_homepage'] ? 'checked' : '' ?>>
            Als homepagina instellen
        </label>
        <label>
            Volgorde in menu
            <input type="number" name="nav_order" value="<?= (int) $form['nav_order'] ?>" style="width:5rem;">
        </label>
    </div>

    <button type="submit">Opslaan</button>
    <a href="pages.php" class="button-secondary">Annuleren</a>
</form>

<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
    var quill = new Quill('#editor', {
        theme: 'snow',
        modules: { toolbar: [
            [{ header: [2, 3, false] }],
            ['bold', 'italic', 'underline', 'link'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['image', 'blockquote'],
            ['clean']
        ] }
    });

    var contentField = document.getElementById('content');
    var form = document.querySelector('.page-form');
    form.addEventListener('submit', function () {
        contentField.value = quill.root.innerHTML;
    });

    var titleField = document.getElementById('title');
    var slugField = document.getElementById('slug');
    var slugManuallyEdited = <?= $id ? 'true' : 'false' ?>;
    slugField.addEventListener('input', function () { slugManuallyEdited = true; });
    titleField.addEventListener('input', function () {
        if (slugManuallyEdited) return;
        slugField.value = titleField.value
            .toLowerCase()
            .normalize('NFD').replace(new RegExp('[̀-ͯ]', 'g'), '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
