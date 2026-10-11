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

// Sessions are edited as one line per session ("JJJJ-MM-DD" or "JJJJ-MM-DD
// UU:MM") rather than a dynamic add/remove row UI — same lightweight typed-
// convention pattern as the FAQ block's "V: vraag" or the buttons block's
// "Label | URL", so no extra JS is needed to manage a variable-length list.
function sessions_to_text(array $sessions): string
{
    return implode("\n", array_map(function (array $s): string {
        $line = $s['session_date'];
        if (!empty($s['session_time'])) {
            $line .= ' ' . substr($s['session_time'], 0, 5);
        }
        return $line;
    }, $sessions));
}

// Returns ['sessions' => [['date' => ..., 'time' => ?...], ...], 'errors' => [...]].
// Blank lines are skipped; every non-blank line must parse, and at least
// one valid session is required — same validation strictness as the event
// date field had before sessions existed.
function parse_sessions_text(string $text): array
{
    $sessions = [];
    $errors = [];
    $lineNumber = 0;

    foreach (preg_split('/\n/', $text) as $line) {
        $lineNumber++;
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:\s+([01]\d|2[0-3]):([0-5]\d))?$/', $line, $m)) {
            $errors[] = "Sessie op regel $lineNumber: gebruik het formaat JJJJ-MM-DD of JJJJ-MM-DD UU:MM.";
            continue;
        }

        $date = $m[1];
        if (!checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            $errors[] = "Sessie op regel $lineNumber: \"$date\" is geen geldige datum.";
            continue;
        }

        $time = isset($m[2]) && $m[2] !== '' ? $m[2] . ':' . $m[3] . ':00' : null;
        $sessions[] = ['date' => $date, 'time' => $time];
    }

    if (empty($errors) && empty($sessions)) {
        $errors[] = 'Vul minstens één sessiedatum in.';
    }

    return ['sessions' => $sessions, 'errors' => $errors];
}

$errors = [];
$form = [
    'title' => $event['title'] ?? '',
    'description' => $event['description'] ?? '',
    'sessions_text' => $id ? sessions_to_text(get_event_sessions($mysqli, $id)) : '',
    'location' => $event['location'] ?? '',
    'capacity' => $event['capacity'] ?? '',
    'price' => $event['price'] ?? '',
    'registration_deadline' => $event['registration_deadline'] ?? '',
    'published' => $event['published'] ?? 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['description'] = trim(normalize_newlines((string) ($_POST['description'] ?? '')));
    $form['sessions_text'] = trim(normalize_newlines((string) ($_POST['sessions_text'] ?? '')));
    $form['location'] = trim((string) ($_POST['location'] ?? ''));
    $form['capacity'] = trim((string) ($_POST['capacity'] ?? ''));
    $form['price'] = trim((string) ($_POST['price'] ?? ''));
    $form['registration_deadline'] = trim((string) ($_POST['registration_deadline'] ?? ''));
    $form['published'] = isset($_POST['published']) ? 1 : 0;

    if ($form['title'] === '' || mb_strlen($form['title']) > 200) {
        $errors[] = 'Vul een titel in (max. 200 tekens).';
    }
    if ($form['description'] === '') {
        $errors[] = 'Vul een beschrijving in.';
    }

    $parsedSessions = parse_sessions_text($form['sessions_text']);
    $errors = array_merge($errors, $parsedSessions['errors']);

    if ($form['capacity'] !== '' && (!ctype_digit($form['capacity']) || (int) $form['capacity'] < 1)) {
        $errors[] = 'Capaciteit moet leeg zijn (onbeperkt) of een positief getal.';
    }
    if (mb_strlen($form['price']) > 50) {
        $errors[] = 'Prijs is te lang (max. 50 tekens).';
    }
    if ($form['registration_deadline'] !== '') {
        $validDeadline = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['registration_deadline'])
            && checkdate((int) substr($form['registration_deadline'], 5, 2), (int) substr($form['registration_deadline'], 8, 2), (int) substr($form['registration_deadline'], 0, 4));
        if (!$validDeadline) {
            $errors[] = 'Vul een geldige inschrijvingsdeadline in (of laat leeg).';
        }
    }

    if (empty($errors)) {
        $capacity = $form['capacity'] !== '' ? (int) $form['capacity'] : null;
        $location = $form['location'] !== '' ? $form['location'] : null;
        $price = $form['price'] !== '' ? $form['price'] : null;
        $registrationDeadline = $form['registration_deadline'] !== '' ? $form['registration_deadline'] : null;

        $mysqli->begin_transaction();
        try {
            if ($id) {
                $stmt = $mysqli->prepare(
                    'UPDATE events SET title = ?, description = ?, location = ?, capacity = ?, price = ?, registration_deadline = ?, published = ? WHERE id = ?'
                );
                $stmt->bind_param(
                    'sssissii',
                    $form['title'],
                    $form['description'],
                    $location,
                    $capacity,
                    $price,
                    $registrationDeadline,
                    $form['published'],
                    $id
                );
                $stmt->execute();
                $stmt->close();

                $del = $mysqli->prepare('DELETE FROM event_sessions WHERE event_id = ?');
                $del->bind_param('i', $id);
                $del->execute();
                $del->close();
            } else {
                $stmt = $mysqli->prepare(
                    'INSERT INTO events (title, description, location, capacity, price, registration_deadline, published) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'sssissi',
                    $form['title'],
                    $form['description'],
                    $location,
                    $capacity,
                    $price,
                    $registrationDeadline,
                    $form['published']
                );
                $stmt->execute();
                $id = $stmt->insert_id;
                $stmt->close();
            }

            $insertSession = $mysqli->prepare('INSERT INTO event_sessions (event_id, session_date, session_time) VALUES (?, ?, ?)');
            foreach ($parsedSessions['sessions'] as $session) {
                $insertSession->bind_param('iss', $id, $session['date'], $session['time']);
                $insertSession->execute();
            }
            $insertSession->close();

            $mysqli->commit();
        } catch (mysqli_sql_exception $e) {
            $mysqli->rollback();
            throw $e;
        }

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
    <div class="richtext-toolbar" data-richtext-toolbar="description">
        <button type="button" data-format="bold" title="Vet"><strong>V</strong></button>
        <button type="button" data-format="italic" title="Cursief"><em>C</em></button>
        <button type="button" data-format="link" title="Link">Link</button>
        <button type="button" data-format="list" title="Opsomming">&bull;</button>
    </div>
    <textarea id="description" name="description" rows="5" required><?= e($form['description']) ?></textarea>
    <p class="field-hint">Opmaak: **vet**, *cursief*, [linktekst](url), of een regel die begint met "- " voor een opsomming — of gebruik de knoppen hierboven.</p>

    <label for="sessions_text">Sessiedatums (één per regel)</label>
    <textarea id="sessions_text" name="sessions_text" rows="5" placeholder="2026-01-14 19:00&#10;2026-01-21 19:00&#10;2026-01-28 19:00" required><?= e($form['sessions_text']) ?></textarea>
    <p class="field-hint">
        Eén sessie per regel, als "JJJJ-MM-DD" of "JJJJ-MM-DD UU:MM" (tijd
        optioneel). Eén regel = een eenmalig evenement; meerdere regels = een
        reeks — bezoekers schrijven zich in één keer in voor de volledige reeks.
    </p>

    <label for="location">Locatie (optioneel)</label>
    <input type="text" id="location" name="location" value="<?= e($form['location']) ?>">

    <div class="page-form-row">
        <label>Maximum aantal plaatsen (optioneel — leeg = onbeperkt)
            <input type="number" id="capacity" name="capacity" min="1" value="<?= e((string) $form['capacity']) ?>" style="width:8rem;">
        </label>
        <label>Prijs (optioneel)
            <input type="text" id="price" name="price" value="<?= e($form['price']) ?>" maxlength="50" placeholder="bv. €60 of Gratis">
        </label>
    </div>
    <p class="field-hint">De capaciteit geldt voor de volledige reeks — één inschrijving telt voor alle sessies samen.</p>

    <label for="registration_deadline">Inschrijven ten laatste op (optioneel)</label>
    <input type="date" id="registration_deadline" name="registration_deadline" value="<?= e($form['registration_deadline']) ?>" style="width:12rem;">
    <p class="field-hint">Leeg = inschrijven blijft open zolang er nog een sessie moet plaatsvinden.</p>

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
