<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$event = null;

if ($id) {
    $stmt = $mysqli->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        set_flash('error', 'Evenement niet gevonden.');
        redirect('events.php');
    }
}

$errors = [];
$form = [
    'title' => $event['title'] ?? '',
    'description' => $event['description'] ?? '',
    'event_date' => $event['event_date'] ?? '',
    'event_time' => !empty($event['event_time']) ? substr($event['event_time'], 0, 5) : '',
    'location' => $event['location'] ?? '',
    'capacity' => $event['capacity'] ?? '',
    'published' => $event['published'] ?? 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['description'] = trim((string) ($_POST['description'] ?? ''));
    $form['event_date'] = (string) ($_POST['event_date'] ?? '');
    $form['event_time'] = trim((string) ($_POST['event_time'] ?? ''));
    $form['location'] = trim((string) ($_POST['location'] ?? ''));
    $form['capacity'] = trim((string) ($_POST['capacity'] ?? ''));
    $form['published'] = isset($_POST['published']) ? 1 : 0;

    if ($form['title'] === '' || mb_strlen($form['title']) > 200) {
        $errors[] = 'Vul een titel in (max. 200 tekens).';
    }
    if ($form['description'] === '') {
        $errors[] = 'Vul een beschrijving in.';
    }
    $validDate = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['event_date'])
        && checkdate((int) substr($form['event_date'], 5, 2), (int) substr($form['event_date'], 8, 2), (int) substr($form['event_date'], 0, 4));
    if (!$validDate) {
        $errors[] = 'Vul een geldige datum in.';
    }
    if ($form['event_time'] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $form['event_time'])) {
        $errors[] = 'Vul een geldig tijdstip in (of laat leeg).';
    }
    if ($form['capacity'] !== '' && (!ctype_digit($form['capacity']) || (int) $form['capacity'] < 1)) {
        $errors[] = 'Capaciteit moet leeg zijn (onbeperkt) of een positief getal.';
    }

    if (empty($errors)) {
        $eventTime = $form['event_time'] !== '' ? $form['event_time'] . ':00' : null;
        $capacity = $form['capacity'] !== '' ? (int) $form['capacity'] : null;
        $location = $form['location'] !== '' ? $form['location'] : null;

        if ($id) {
            $stmt = $mysqli->prepare(
                'UPDATE events SET title = ?, description = ?, event_date = ?, event_time = ?, location = ?, capacity = ?, published = ? WHERE id = ?'
            );
            $stmt->bind_param(
                'sssssiii',
                $form['title'],
                $form['description'],
                $form['event_date'],
                $eventTime,
                $location,
                $capacity,
                $form['published'],
                $id
            );
        } else {
            $stmt = $mysqli->prepare(
                'INSERT INTO events (title, description, event_date, event_time, location, capacity, published) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'sssssii',
                $form['title'],
                $form['description'],
                $form['event_date'],
                $eventTime,
                $location,
                $capacity,
                $form['published']
            );
        }
        $stmt->execute();
        $stmt->close();

        set_flash('success', 'Evenement opgeslagen.');
        redirect('events.php');
    }
}

$pageTitle = $id ? 'Evenement bewerken' : 'Nieuw evenement';
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

<form method="post" action="event-edit.php<?= $id ? '?id=' . (int) $id : '' ?>" class="page-form">
    <?= csrf_field() ?>

    <label for="title">Titel</label>
    <input type="text" id="title" name="title" value="<?= e($form['title']) ?>" required>

    <label for="description">Beschrijving</label>
    <textarea id="description" name="description" rows="5" required><?= e($form['description']) ?></textarea>

    <div class="page-form-row">
        <label>Datum
            <input type="date" name="event_date" value="<?= e($form['event_date']) ?>" required>
        </label>
        <label>Tijd (optioneel)
            <input type="time" name="event_time" value="<?= e($form['event_time']) ?>">
        </label>
    </div>

    <label for="location">Locatie (optioneel)</label>
    <input type="text" id="location" name="location" value="<?= e($form['location']) ?>">

    <label for="capacity">Maximum aantal plaatsen (optioneel — leeg = onbeperkt)</label>
    <input type="number" id="capacity" name="capacity" min="1" value="<?= e((string) $form['capacity']) ?>" style="width:8rem;">

    <div class="page-form-row">
        <label class="checkbox-label">
            <input type="checkbox" name="published" <?= $form['published'] ? 'checked' : '' ?>>
            Gepubliceerd
        </label>
    </div>

    <button type="submit">Opslaan</button>
    <a href="events.php" class="button-secondary">Annuleren</a>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
