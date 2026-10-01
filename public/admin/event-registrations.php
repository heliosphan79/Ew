<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_login();

$eventId = (int) ($_GET['event_id'] ?? 0);

$stmt = $mysqli->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    set_flash('error', 'Evenement niet gevonden.');
    redirect('events.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $registrationId = (int) ($_POST['registration_id'] ?? 0);
    $stmt = $mysqli->prepare('DELETE FROM event_registrations WHERE id = ? AND event_id = ?');
    $stmt->bind_param('ii', $registrationId, $eventId);
    $stmt->execute();
    $stmt->close();
    set_flash('success', 'Inschrijving verwijderd.');
    redirect('event-registrations.php?event_id=' . $eventId);
}

$registrations = $mysqli->prepare(
    'SELECT * FROM event_registrations WHERE event_id = ? ORDER BY registered_at ASC'
);
$registrations->bind_param('i', $eventId);
$registrations->execute();
$registrations = $registrations->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Inschrijvingen — ' . $event['title'];
require __DIR__ . '/includes/header.php';
?>

<p><a href="events.php">&larr; Terug naar evenementen</a></p>
<h1>Inschrijvingen — <?= e($event['title']) ?></h1>
<p class="field-hint">
    <?= e(date('d/m/Y', strtotime($event['event_date']))) ?>
    <?= count($registrations) ?> ingeschreven<?= $event['capacity'] !== null ? ' van de ' . (int) $event['capacity'] . ' plaatsen' : '' ?>.
</p>

<?php if (empty($registrations)): ?>
    <p>Nog geen inschrijvingen.</p>
<?php else: ?>
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
        <tr><th>Naam</th><th>E-mail</th><th>Ingeschreven op</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($registrations as $registration): ?>
            <tr>
                <td><?= e($registration['name']) ?></td>
                <td><a href="mailto:<?= e($registration['email']) ?>"><?= e($registration['email']) ?></a></td>
                <td><?= e(date('d/m/Y H:i', strtotime($registration['registered_at']))) ?></td>
                <td class="admin-table-actions">
                    <form method="post" action="event-registrations.php?event_id=<?= (int) $eventId ?>" onsubmit="return confirm('Deze inschrijving verwijderen?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="registration_id" value="<?= (int) $registration['id'] ?>">
                        <button type="submit" class="link-button link-button-danger">Verwijderen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
