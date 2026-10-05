<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $date = (string) ($_POST['slot_date'] ?? '');
        $time = (string) ($_POST['slot_time'] ?? '');

        $validDate = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
        $validTime = (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);

        if (!$validDate || !$validTime) {
            $errors[] = 'Vul een geldige datum en tijd in.';
        } else {
            $timeWithSeconds = $time . ':00';
            try {
                $stmt = $mysqli->prepare('INSERT INTO calendar_slots (slot_date, slot_time) VALUES (?, ?)');
                $stmt->bind_param('ss', $date, $timeWithSeconds);
                $stmt->execute();
                $stmt->close();
                set_flash('success', 'Tijdslot toegevoegd.');
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $errors[] = 'Dit tijdslot bestaat al.';
                } else {
                    throw $e;
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $mysqli->prepare("DELETE FROM calendar_slots WHERE id = ? AND status = 'available'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Tijdslot verwijderd.');
    } elseif ($action === 'cancel') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $mysqli->prepare(
            "UPDATE calendar_slots SET status = 'available', booked_name = NULL, booked_email = NULL, booked_message = NULL, booked_at = NULL WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Boeking geannuleerd — het tijdslot staat weer open.');
    }

    if (empty($errors)) {
        redirect('calendar.php');
    }
}

$slots = $mysqli->query(
    "SELECT * FROM calendar_slots WHERE slot_date >= CURDATE() ORDER BY slot_date ASC, slot_time ASC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Kalender';
require __DIR__ . '/includes/header.php';
?>

<h1>Kalender</h1>
<p class="field-hint">Beschikbare tijdsloten voor het kalenderblok. Bezoekers kunnen enkel een nog-niet-geboekt moment kiezen.</p>

<?php if (!empty($errors)): ?>
    <ul class="form-errors">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="calendar.php" class="page-form" style="max-width:420px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <label for="slot_date">Datum</label>
    <input type="date" id="slot_date" name="slot_date" required>
    <label for="slot_time">Tijd</label>
    <input type="time" id="slot_time" name="slot_time" required>
    <button type="submit"><?= admin_icon('plus') ?> Tijdslot toevoegen</button>
</form>

<h2 style="margin-top:2.5rem;">Aankomende tijdsloten</h2>

<?php if (empty($slots)): ?>
    <p>Nog geen tijdsloten ingepland.</p>
<?php else: ?>
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
        <tr>
            <th>Datum</th>
            <th>Tijd</th>
            <th>Status</th>
            <th>Geboekt door</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($slots as $slot): ?>
            <tr>
                <td><?= e(date('d/m/Y', strtotime($slot['slot_date']))) ?></td>
                <td><?= e(substr($slot['slot_time'], 0, 5)) ?></td>
                <td>
                    <?= $slot['status'] === 'booked'
                        ? '<span class="badge badge-ok">Geboekt</span>'
                        : '<span class="badge badge-draft">Beschikbaar</span>' ?>
                </td>
                <td>
                    <?php if ($slot['status'] === 'booked'): ?>
                        <?= e($slot['booked_name']) ?> &lt;<?= e($slot['booked_email']) ?>&gt;
                        <?php if (!empty($slot['booked_message'])): ?>
                            <br><span class="submission-message" style="font-size:0.85rem;"><?= nl2br(e($slot['booked_message'])) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="admin-table-actions">
                    <?php if ($slot['status'] === 'booked'): ?>
                        <form method="post" action="calendar.php" onsubmit="return confirm('Deze boeking annuleren? Het tijdslot komt weer vrij.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="id" value="<?= (int) $slot['id'] ?>">
                            <button type="submit" class="icon-btn icon-btn-accent" title="Annuleer boeking">
                                <?= admin_icon('undo') ?><span class="visually-hidden">Annuleer boeking</span>
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="calendar.php" onsubmit="return confirm('Dit tijdslot verwijderen?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $slot['id'] ?>">
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
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
