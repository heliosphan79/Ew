<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$errors = [];
$newName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $newName = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')) ?? '');
        if ($newName === '' || mb_strlen($newName) > NEWSLETTER_GROUP_NAME_MAX) {
            $errors[] = 'Geef de groep een naam (max. ' . NEWSLETTER_GROUP_NAME_MAX . ' tekens).';
        } else {
            $stmt = $mysqli->prepare('SELECT id FROM newsletter_groups WHERE name = ?');
            $stmt->bind_param('s', $newName);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc() !== null;
            $stmt->close();
            if ($exists) {
                $errors[] = 'Er bestaat al een groep met die naam.';
            } else {
                $stmt = $mysqli->prepare('INSERT INTO newsletter_groups (name) VALUES (?)');
                $stmt->bind_param('s', $newName);
                $stmt->execute();
                $stmt->close();
                set_flash('success', 'Groep "' . $newName . '" aangemaakt.');
                redirect('newsletter-groups.php');
            }
        }
    } elseif ($action === 'rename') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')) ?? '');
        if ($id <= 0 || $name === '' || mb_strlen($name) > NEWSLETTER_GROUP_NAME_MAX) {
            $errors[] = 'Geef de groep een naam (max. ' . NEWSLETTER_GROUP_NAME_MAX . ' tekens).';
        } else {
            $stmt = $mysqli->prepare('SELECT id FROM newsletter_groups WHERE name = ? AND id <> ?');
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc() !== null;
            $stmt->close();
            if ($exists) {
                $errors[] = 'Er bestaat al een groep met die naam.';
            } else {
                $stmt = $mysqli->prepare('UPDATE newsletter_groups SET name = ? WHERE id = ?');
                $stmt->bind_param('si', $name, $id);
                $stmt->execute();
                $stmt->close();
                set_flash('success', 'Groep hernoemd.');
                redirect('newsletter-groups.php');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            // ON DELETE CASCADE ruimt de ledenkoppelingen en de
            // doelgroep-koppelingen van nieuwsbrieven op; de abonnees en
            // nieuwsbrieven zelf blijven bestaan.
            $stmt = $mysqli->prepare('DELETE FROM newsletter_groups WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Groep verwijderd. De abonnees zelf blijven bestaan.');
        }
        redirect('newsletter-groups.php');
    }
}

$groups = newsletter_get_groups($mysqli);

// Per groep: in hoeveel nog niet verzonden concepten ze als doelgroep staat
// — voor de waarschuwing bij verwijderen.
$draftUse = [];
$res = $mysqli->query(
    "SELECT t.group_id, COUNT(*) AS total
     FROM newsletter_target_groups t
     JOIN newsletters n ON n.id = t.newsletter_id
     WHERE n.status = 'draft'
     GROUP BY t.group_id"
);
foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
    $draftUse[(int) $row['group_id']] = (int) $row['total'];
}

$ungrouped = (int) $mysqli->query(
    "SELECT COUNT(*) AS total FROM newsletter_subscribers s
     WHERE s.status = 'subscribed'
       AND NOT EXISTS (SELECT 1 FROM newsletter_subscriber_groups sg WHERE sg.subscriber_id = s.id)"
)->fetch_assoc()['total'];

$pageTitle = 'Nieuwsbrief-groepen';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-header-row">
    <h1>Groepen</h1>
    <div>
        <a href="newsletter-subscribers.php" class="button-secondary">Abonnees</a>
        <a href="newsletters.php" class="button-secondary">Terug naar nieuwsbrieven</a>
    </div>
</div>

<p>
    Met groepen deel je je abonnees in, zodat een nieuwsbrief enkel naar een
    bepaalde doelgroep gaat. Een abonnee kan in meerdere groepen zitten; bij
    het versturen kies je per nieuwsbrief voor "alle abonnees" of voor een of
    meer groepen (wie in meerdere gekozen groepen zit, krijgt de mail één keer).
    Abonnees zonder groep ontvangen enkel nieuwsbrieven aan "alle abonnees".
</p>

<?php if (!empty($errors)): ?>
    <ul class="form-errors">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="page-form-columns">
    <div class="page-form-main">
        <?php if (empty($groups)): ?>
            <p>Nog geen groepen. Maak er rechts eén aan, of importeer een CSV met een groepskolom bij de abonnees.</p>
        <?php else: ?>
        <div class="table-scroll">
        <table class="admin-table">
            <thead>
            <tr>
                <th>Naam</th>
                <th>Actieve leden</th>
                <th>Leden in totaal</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($groups as $g): ?>
                <?php
                $gid = (int) $g['id'];
                $inDrafts = $draftUse[$gid] ?? 0;
                $confirm = 'Groep "' . $g['name'] . '" verwijderen? De abonnees zelf blijven bestaan.';
                if ($inDrafts > 0) {
                    $confirm .= ' Let op: ' . $inDrafts . ' concept-nieuwsbrief' . ($inDrafts === 1 ? '' : 'en')
                        . ' staat' . ($inDrafts === 1 ? '' : 'en') . ' naar deze groep ingesteld en verliest die ontvangers.';
                }
                ?>
                <tr>
                    <td>
                        <form method="post" action="newsletter-groups.php" class="inline-form" style="display:flex;gap:0.4rem;align-items:center;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="rename">
                            <input type="hidden" name="id" value="<?= $gid ?>">
                            <input type="text" name="name" value="<?= e($g['name']) ?>" maxlength="<?= NEWSLETTER_GROUP_NAME_MAX ?>" required aria-label="Naam van de groep" style="min-width:12rem;">
                            <button type="submit" class="icon-btn" title="Naam opslaan">
                                <?= admin_icon('check') ?><span class="visually-hidden">Naam opslaan</span>
                            </button>
                        </form>
                    </td>
                    <td><?= (int) $g['active_count'] ?></td>
                    <td><?= (int) $g['total_count'] ?></td>
                    <td class="admin-table-actions">
                        <a class="icon-btn" href="newsletter-subscribers.php?group=<?= $gid ?>" title="Leden bekijken">
                            <?= admin_icon('users') ?><span class="visually-hidden">Leden bekijken</span>
                        </a>
                        <form method="post" action="newsletter-groups.php" onsubmit="return confirm('<?= e(addslashes($confirm)) ?>');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $gid ?>">
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
        <p class="field-hint">
            <a href="newsletter-subscribers.php?group=none"><?= $ungrouped ?> actieve abonnee<?= $ungrouped === 1 ? '' : 's' ?> zonder groep</a>
            — zij ontvangen enkel nieuwsbrieven aan "alle abonnees".
        </p>
        <?php endif; ?>
    </div>

    <div class="page-form-sidebar">
        <form method="post" action="newsletter-groups.php" class="page-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <label for="name">Nieuwe groep</label>
            <input type="text" id="name" name="name" value="<?= e($newName) ?>" maxlength="<?= NEWSLETTER_GROUP_NAME_MAX ?>" placeholder="bv. Huiskamerontmoetingen" required>
            <div class="page-form-actions">
                <button type="submit"><?= admin_icon('plus') ?> Aanmaken</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
