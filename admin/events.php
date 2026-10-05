<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$events = $mysqli->query(
    'SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id) AS registered_count
     FROM events e
     ORDER BY e.event_date DESC, e.event_time DESC'
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Evenementen';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-header-row">
    <h1>Evenementen</h1>
    <a class="button" href="event-edit.php"><?= admin_icon('plus') ?> Nieuw evenement</a>
</div>

<?php if (empty($events)): ?>
    <p>Nog geen evenementen aangemaakt.</p>
<?php else: ?>
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
        <tr>
            <th>Titel</th>
            <th>Datum</th>
            <th>Status</th>
            <th>Ingeschreven</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <tr>
                <td><?= e($event['title']) ?></td>
                <td>
                    <?= e(date('d/m/Y', strtotime($event['event_date']))) ?>
                    <?php if (!empty($event['event_time'])): ?>
                        <?= e(substr($event['event_time'], 0, 5)) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?= $event['published']
                        ? '<span class="badge badge-ok">Gepubliceerd</span>'
                        : '<span class="badge badge-draft">Concept</span>' ?>
                </td>
                <td>
                    <?= (int) $event['registered_count'] ?><?= $event['capacity'] !== null ? ' / ' . (int) $event['capacity'] : '' ?>
                </td>
                <td class="admin-table-actions">
                    <a class="icon-btn icon-btn-accent" href="event-edit.php?id=<?= (int) $event['id'] ?>" title="Bewerken">
                        <?= admin_icon('edit') ?><span class="visually-hidden">Bewerken</span>
                    </a>
                    <a class="icon-btn icon-btn-accent" href="event-registrations.php?event_id=<?= (int) $event['id'] ?>" title="Inschrijvingen">
                        <?= admin_icon('eye') ?><span class="visually-hidden">Inschrijvingen</span>
                    </a>
                    <form method="post" action="event-delete.php" onsubmit="return confirm('Dit evenement en alle inschrijvingen definitief verwijderen?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
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
