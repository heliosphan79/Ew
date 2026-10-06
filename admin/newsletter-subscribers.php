<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$errors = [];
$importStats = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['action']) && $_POST['action'] === 'add_one') {
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Vul een geldig e-mailadres in.';
        } else {
            newsletter_subscribe($mysqli, $email, 'manual');
            set_flash('success', 'Adres toegevoegd.');
            redirect('newsletter-subscribers.php');
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'import_csv') {
        if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Geen (geldig) CSV-bestand ontvangen.';
        } else {
            $handle = fopen($_FILES['csv']['tmp_name'], 'r');
            $added = 0;
            $skipped = 0;
            if ($handle) {
                // Deliberately NOT newsletter_subscribe(): an import is a
                // bulk, mostly-unattended action, so it only ever inserts
                // addresses that aren't in the table at all yet. It must
                // never flip an existing "unsubscribed" row back to
                // "subscribed" — a manual single add (below) is the one
                // place that explicit re-subscribe behaviour belongs.
                $insert = $mysqli->prepare(
                    'INSERT IGNORE INTO newsletter_subscribers (email, source, unsubscribe_token) VALUES (?, ?, ?)'
                );
                while (($row = fgetcsv($handle)) !== false) {
                    $email = trim((string) ($row[0] ?? ''));
                    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
                        $skipped++;
                        continue;
                    }
                    $source = 'import';
                    $token = newsletter_generate_token();
                    $insert->bind_param('sss', $email, $source, $token);
                    $insert->execute();
                    if ($insert->affected_rows > 0) {
                        $added++;
                    } else {
                        $skipped++;
                    }
                }
                $insert->close();
                fclose($handle);
            }
            $importStats = ['added' => $added, 'skipped' => $skipped];
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'unsubscribe') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $mysqli->prepare(
                "UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Adres uitgeschreven.');
        }
        redirect('newsletter-subscribers.php');
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $mysqli->prepare('DELETE FROM newsletter_subscribers WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Adres verwijderd.');
        }
        redirect('newsletter-subscribers.php');
    }
}

$subscribers = $mysqli->query(
    'SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC'
)->fetch_all(MYSQLI_ASSOC);
$subscribedCount = count(array_filter($subscribers, fn($s) => $s['status'] === 'subscribed'));

$sourceLabels = [
    'contact_form' => 'Contactformulier',
    'event_registration' => 'Evenementinschrijving',
    'manual' => 'Handmatig',
    'import' => 'Import',
];

$pageTitle = 'Nieuwsbrief-abonnees';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-header-row">
    <h1>Abonnees</h1>
    <a href="newsletters.php" class="button-secondary">Terug naar nieuwsbrieven</a>
</div>

<?php if (!empty($errors)): ?>
    <ul class="form-errors">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($importStats !== null): ?>
    <div class="flash flash-success">
        CSV geïmporteerd: <?= (int) $importStats['added'] ?> nieuw toegevoegd, <?= (int) $importStats['skipped'] ?> overgeslagen (al bestaand of ongeldig).
    </div>
<?php endif; ?>

<p><?= $subscribedCount ?> actieve abonnee<?= $subscribedCount === 1 ? '' : 's' ?> (<?= count($subscribers) ?> in totaal, inclusief uitgeschreven).</p>

<div class="page-form-columns">
    <div class="page-form-main">
        <div class="table-scroll">
        <table class="admin-table">
            <thead>
            <tr>
                <th>E-mailadres</th>
                <th>Status</th>
                <th>Bron</th>
                <th>Sinds</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($subscribers)): ?>
                <tr><td colspan="5">Nog geen abonnees.</td></tr>
            <?php endif; ?>
            <?php foreach ($subscribers as $sub): ?>
                <tr>
                    <td><?= e($sub['email']) ?></td>
                    <td>
                        <?= $sub['status'] === 'subscribed'
                            ? '<span class="badge badge-ok">Geabonneerd</span>'
                            : '<span class="badge badge-draft">Uitgeschreven</span>' ?>
                    </td>
                    <td><?= e($sourceLabels[$sub['source']] ?? $sub['source']) ?></td>
                    <td><?= e(date('d/m/Y', strtotime($sub['subscribed_at']))) ?></td>
                    <td class="admin-table-actions">
                        <?php if ($sub['status'] === 'subscribed'): ?>
                        <form method="post" action="newsletter-subscribers.php" onsubmit="return confirm('Dit adres uitschrijven?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unsubscribe">
                            <input type="hidden" name="id" value="<?= (int) $sub['id'] ?>">
                            <button type="submit" class="icon-btn" title="Uitschrijven">
                                <?= admin_icon('undo') ?><span class="visually-hidden">Uitschrijven</span>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="post" action="newsletter-subscribers.php" onsubmit="return confirm('Dit adres definitief verwijderen?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $sub['id'] ?>">
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
    </div>

    <div class="page-form-sidebar">
        <form method="post" action="newsletter-subscribers.php" class="page-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_one">
            <label for="email">Eén adres toevoegen</label>
            <input type="email" id="email" name="email" placeholder="naam@voorbeeld.be" required>
            <div class="page-form-actions">
                <button type="submit">Toevoegen</button>
            </div>
        </form>

        <form method="post" action="newsletter-subscribers.php" enctype="multipart/form-data" class="page-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_csv">
            <label for="csv">CSV importeren</label>
            <p class="field-hint">Eén e-mailadres per regel, of als eerste kolom van een CSV. Bestaande of uitgeschreven adressen worden nooit overschreven.</p>
            <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
            <div class="page-form-actions">
                <button type="submit"><?= admin_icon('upload') ?> Importeren</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
