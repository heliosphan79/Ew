<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$newsletters = $mysqli->query(
    "SELECT n.*,
        (SELECT COUNT(*) FROM newsletter_sends s WHERE s.newsletter_id = n.id) AS total_recipients,
        (SELECT COUNT(*) FROM newsletter_sends s WHERE s.newsletter_id = n.id AND s.sent_at IS NOT NULL) AS sent_count,
        (SELECT COUNT(*) FROM newsletter_sends s WHERE s.newsletter_id = n.id AND s.opened_at IS NOT NULL) AS opened_count
     FROM newsletters n
     ORDER BY n.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Nieuwsbrief';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-header-row">
    <h1>Nieuwsbrief</h1>
    <a class="button" href="newsletter-edit.php"><?= admin_icon('plus') ?> Nieuwe nieuwsbrief</a>
</div>

<p>
    <a href="newsletter-subscribers.php"><?= admin_icon('users') ?> Abonnees beheren</a>
</p>

<?php if (empty($newsletters)): ?>
    <p>Nog geen nieuwsbrieven aangemaakt.</p>
<?php else: ?>
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
        <tr>
            <th>Onderwerp</th>
            <th>Status</th>
            <th>Verzonden / totaal</th>
            <th>Geopend</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($newsletters as $nl): ?>
            <tr>
                <td><?= e($nl['subject']) ?></td>
                <td>
                    <?php if ($nl['status'] === 'sent'): ?>
                        <span class="badge badge-ok">Verzonden</span>
                    <?php elseif ($nl['status'] === 'sending'): ?>
                        <span class="badge badge-draft">Bezig met verzenden</span>
                    <?php elseif ($nl['status'] === 'cancelled'): ?>
                        <span class="badge badge-draft">Geannuleerd</span>
                    <?php else: ?>
                        <span class="badge badge-draft">Concept</span>
                    <?php endif; ?>
                </td>
                <td><?= (int) $nl['sent_count'] ?> / <?= (int) $nl['total_recipients'] ?></td>
                <td><?= (int) $nl['opened_count'] ?></td>
                <td class="admin-table-actions">
                    <a class="icon-btn icon-btn-accent" href="newsletter-edit.php?id=<?= (int) $nl['id'] ?>" title="<?= $nl['status'] === 'draft' ? 'Bewerken' : 'Bekijken' ?>">
                        <?= admin_icon($nl['status'] === 'draft' ? 'edit' : 'eye') ?><span class="visually-hidden">Bekijken</span>
                    </a>
                    <form method="post" action="newsletter-copy.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $nl['id'] ?>">
                        <button type="submit" class="icon-btn" title="Dupliceren als nieuw concept">
                            <?= admin_icon('copy') ?><span class="visually-hidden">Dupliceren</span>
                        </button>
                    </form>
                    <?php if ($nl['status'] !== 'sending'): ?>
                    <?php
                        $deleteConfirm = $nl['status'] === 'draft'
                            ? 'Deze nieuwsbrief definitief verwijderen?'
                            : 'Deze nieuwsbrief definitief verwijderen? Dit verwijdert ook de verzend- en openingsgegevens.';
                    ?>
                    <form method="post" action="newsletter-delete.php" onsubmit="return confirm('<?= e($deleteConfirm) ?>');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $nl['id'] ?>">
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
