<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$errors = [];
$importStats = null;

// Actief filter (?group=<id> of ?group=none): blijft behouden na elke
// actie, zodat je na het groeperen van een selectie in dezelfde weergave
// blijft.
$groupFilter = $_GET['group'] ?? ($_POST['return_group'] ?? '');
$groupFilter = is_string($groupFilter) ? $groupFilter : '';
if ($groupFilter !== 'none' && !(ctype_digit($groupFilter) && (int) $groupFilter > 0)) {
    $groupFilter = '';
}
$backUrl = 'newsletter-subscribers.php' . ($groupFilter !== '' ? '?group=' . rawurlencode($groupFilter) : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['action']) && $_POST['action'] === 'add_one') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Vul een geldig e-mailadres in.';
        } else {
            newsletter_subscribe($mysqli, $email, 'manual', $firstName);
            $groupIds = newsletter_filter_group_ids($mysqli, $_POST['group_ids'] ?? []);
            $subscriberId = newsletter_subscriber_id_by_email($mysqli, $email);
            if ($subscriberId !== null && !empty($groupIds)) {
                newsletter_add_to_groups($mysqli, [$subscriberId], $groupIds);
            }
            set_flash('success', 'Adres toegevoegd.');
            redirect($backUrl);
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'import_csv') {
        if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Geen (geldig) CSV-bestand ontvangen.';
        } else {
            $handle = fopen($_FILES['csv']['tmp_name'], 'r');
            $added = 0;
            $skipped = 0;
            $groupedExisting = 0;
            $groupsBefore = (int) $mysqli->query('SELECT COUNT(*) AS total FROM newsletter_groups')->fetch_assoc()['total'];
            $importGroupIds = newsletter_filter_group_ids($mysqli, isset($_POST['import_group_id']) ? [$_POST['import_group_id']] : []);
            $groupIdByName = [];
            if ($handle) {
                // Deliberately NOT newsletter_subscribe(): an import is a
                // bulk, mostly-unattended action, so it only ever inserts
                // addresses that aren't in the table at all yet. It must
                // never flip an existing "unsubscribed" row back to
                // "subscribed" — a manual single add (above) is the one
                // place that explicit re-subscribe behaviour belongs.
                // Groepen zijn daarop de enige uitzondering: een groep
                // bijvoegen aan een bestaand adres wijzigt niets aan zijn
                // status, en is net wat je met een CSV met groepskolom
                // over een bestaande lijst wilt.
                $insert = $mysqli->prepare(
                    'INSERT IGNORE INTO newsletter_subscribers (email, first_name, source, unsubscribe_token) VALUES (?, ?, ?, ?)'
                );
                while (($row = fgetcsv($handle)) !== false) {
                    $email = trim((string) ($row[0] ?? ''));
                    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
                        $skipped++;
                        continue;
                    }
                    // Optional second column — a plain one-address-per-line
                    // file (no column at all) works exactly as before.
                    $firstName = trim((string) ($row[1] ?? ''));
                    $firstNameParam = $firstName !== '' ? mb_substr($firstName, 0, 100) : null;
                    $source = 'import';
                    $token = newsletter_generate_token();
                    $insert->bind_param('ssss', $email, $firstNameParam, $source, $token);
                    $insert->execute();
                    $isNew = $insert->affected_rows > 0;
                    // Meteen vastleggen: de groep-queries hieronder (een nieuwe
                    // groep aanmaken) overschrijven $mysqli->insert_id.
                    $newSubscriberId = $isNew ? (int) $mysqli->insert_id : null;
                    if ($isNew) {
                        $added++;
                    } else {
                        $skipped++;
                    }

                    // Optionele derde kolom: groepsnamen, gescheiden door
                    // ; of | (een komma kan niet: dat is het CSV-scheidingsteken).
                    // Onbekende groepen worden aangemaakt.
                    $rowGroupIds = $importGroupIds;
                    foreach (preg_split('/[;|]/', (string) ($row[2] ?? '')) ?: [] as $groupName) {
                        $key = mb_strtolower(trim($groupName));
                        if ($key === '') {
                            continue;
                        }
                        if (!array_key_exists($key, $groupIdByName)) {
                            $groupIdByName[$key] = newsletter_find_or_create_group($mysqli, $groupName);
                        }
                        if ($groupIdByName[$key] !== null) {
                            $rowGroupIds[] = $groupIdByName[$key];
                        }
                    }
                    if (!empty($rowGroupIds)) {
                        $subscriberId = $newSubscriberId ?? newsletter_subscriber_id_by_email($mysqli, $email);
                        if ($subscriberId !== null && $subscriberId > 0) {
                            newsletter_add_to_groups($mysqli, [$subscriberId], array_values(array_unique($rowGroupIds)));
                            if (!$isNew) {
                                $groupedExisting++;
                            }
                        }
                    }
                }
                $insert->close();
                fclose($handle);
            }
            $groupsAfter = (int) $mysqli->query('SELECT COUNT(*) AS total FROM newsletter_groups')->fetch_assoc()['total'];
            $importStats = [
                'added' => $added,
                'skipped' => $skipped,
                'grouped_existing' => $groupedExisting,
                'groups_created' => max(0, $groupsAfter - $groupsBefore),
            ];
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'set_groups') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            newsletter_set_subscriber_groups($mysqli, $id, newsletter_filter_group_ids($mysqli, $_POST['group_ids'] ?? []));
            set_flash('success', 'Groepen bijgewerkt.');
        }
        redirect($backUrl);
    } elseif (isset($_POST['action']) && $_POST['action'] === 'bulk_groups') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn(int $v) => $v > 0)));
        $bulkGroupIds = newsletter_filter_group_ids($mysqli, isset($_POST['bulk_group_id']) ? [$_POST['bulk_group_id']] : []);
        $mode = ($_POST['mode'] ?? '') === 'remove' ? 'remove' : 'add';
        if (empty($ids)) {
            set_flash('error', 'Vink eerst een of meer abonnees aan.');
        } elseif (empty($bulkGroupIds)) {
            set_flash('error', 'Kies eerst een groep.');
        } elseif ($mode === 'add') {
            newsletter_add_to_groups($mysqli, $ids, $bulkGroupIds);
            set_flash('success', count($ids) . ' abonnee' . (count($ids) === 1 ? '' : 's') . ' toegevoegd aan de groep.');
        } else {
            newsletter_remove_from_groups($mysqli, $ids, $bulkGroupIds);
            set_flash('success', count($ids) . ' abonnee' . (count($ids) === 1 ? '' : 's') . ' verwijderd uit de groep.');
        }
        redirect($backUrl);
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
        redirect($backUrl);
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $mysqli->prepare('DELETE FROM newsletter_subscribers WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Adres verwijderd.');
        }
        redirect($backUrl);
    }
}

$groups = newsletter_get_groups($mysqli);
$groupNamesById = [];
foreach ($groups as $g) {
    $groupNamesById[(int) $g['id']] = $g['name'];
}

$where = '';
$params = [];
$types = '';
if ($groupFilter === 'none') {
    $where = 'WHERE NOT EXISTS (SELECT 1 FROM newsletter_subscriber_groups f WHERE f.subscriber_id = s.id)';
} elseif ($groupFilter !== '') {
    $where = 'WHERE EXISTS (SELECT 1 FROM newsletter_subscriber_groups f WHERE f.subscriber_id = s.id AND f.group_id = ?)';
    $params[] = (int) $groupFilter;
    $types = 'i';
}
$stmt = $mysqli->prepare(
    "SELECT s.*,
        (SELECT GROUP_CONCAT(sg.group_id) FROM newsletter_subscriber_groups sg WHERE sg.subscriber_id = s.id) AS group_ids
     FROM newsletter_subscribers s $where
     ORDER BY s.subscribed_at DESC"
);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$subscribers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totals = $mysqli->query(
    "SELECT COUNT(*) AS total, COALESCE(SUM(status = 'subscribed'), 0) AS active FROM newsletter_subscribers"
)->fetch_assoc();
$subscribedCount = (int) $totals['active'];
$totalCount = (int) $totals['total'];

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
    <div>
        <a href="newsletter-groups.php" class="button-secondary"><?= admin_icon('users') ?> Groepen beheren</a>
        <a href="newsletters.php" class="button-secondary">Terug naar nieuwsbrieven</a>
    </div>
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
        <?php if ($importStats['grouped_existing'] > 0): ?>
            Bij <?= (int) $importStats['grouped_existing'] ?> bestaande adres<?= $importStats['grouped_existing'] === 1 ? '' : 'sen' ?> zijn de groepen bijgewerkt.
        <?php endif; ?>
        <?php if ($importStats['groups_created'] > 0): ?>
            <?= (int) $importStats['groups_created'] ?> nieuwe groep<?= $importStats['groups_created'] === 1 ? '' : 'en' ?> aangemaakt.
        <?php endif; ?>
    </div>
<?php endif; ?>

<p><?= $subscribedCount ?> actieve abonnee<?= $subscribedCount === 1 ? '' : 's' ?> (<?= $totalCount ?> in totaal, inclusief uitgeschreven).</p>

<form method="get" action="newsletter-subscribers.php" class="filter-bar">
    <label for="group-filter">Toon</label>
    <select id="group-filter" name="group" onchange="this.form.submit()">
        <option value="">Alle abonnees</option>
        <option value="none" <?= $groupFilter === 'none' ? 'selected' : '' ?>>Zonder groep</option>
        <?php foreach ($groups as $g): ?>
            <option value="<?= (int) $g['id'] ?>" <?= $groupFilter === (string) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?> (<?= (int) $g['active_count'] ?>)</option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="button-secondary">Filteren</button></noscript>
    <?php if ($groupFilter !== ''): ?>
        <span class="field-hint"><?= count($subscribers) ?> getoond</span>
    <?php endif; ?>
</form>

<div class="page-form-columns">
    <div class="page-form-main">
        <?php if (!empty($groups)): ?>
        <!-- De selectievakjes in de tabel horen via form="bulk-form" bij dit
        formulier; de tabel zelf bevat al eigen formulieren (uitschrijven,
        verwijderen), en formulieren nesten mag niet in HTML. -->
        <form id="bulk-form" method="post" action="newsletter-subscribers.php" class="bulk-bar">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bulk_groups">
            <input type="hidden" name="return_group" value="<?= e($groupFilter) ?>">
            <span>Met de aangevinkte:</span>
            <select name="bulk_group_id" aria-label="Groep">
                <option value="">Kies een groep…</option>
                <?php foreach ($groups as $g): ?>
                    <option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="mode" value="add" class="button-secondary">Toevoegen aan groep</button>
            <button type="submit" name="mode" value="remove" class="button-secondary">Verwijderen uit groep</button>
        </form>
        <?php endif; ?>

        <div class="table-scroll">
        <table class="admin-table">
            <thead>
            <tr>
                <?php if (!empty($groups)): ?>
                <th><input type="checkbox" id="select-all" aria-label="Alles selecteren"></th>
                <?php endif; ?>
                <th>E-mailadres</th>
                <th>Voornaam</th>
                <th>Groepen</th>
                <th>Status</th>
                <th>Bron</th>
                <th>Sinds</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php $colspan = empty($groups) ? 7 : 8; ?>
            <?php if (empty($subscribers)): ?>
                <tr><td colspan="<?= $colspan ?>"><?= $groupFilter !== '' ? 'Geen abonnees in deze weergave.' : 'Nog geen abonnees.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($subscribers as $sub): ?>
                <?php
                $subGroupIds = $sub['group_ids'] !== null && $sub['group_ids'] !== ''
                    ? array_map('intval', explode(',', (string) $sub['group_ids']))
                    : [];
                ?>
                <tr>
                    <?php if (!empty($groups)): ?>
                    <td><input type="checkbox" name="ids[]" value="<?= (int) $sub['id'] ?>" form="bulk-form" aria-label="Selecteer <?= e($sub['email']) ?>"></td>
                    <?php endif; ?>
                    <td><?= e($sub['email']) ?></td>
                    <td><?= e($sub['first_name'] ?? '') ?></td>
                    <td>
                        <div class="group-badges">
                            <?php foreach ($subGroupIds as $gid): ?>
                                <?php if (isset($groupNamesById[$gid])): ?>
                                    <span class="badge badge-group"><?= e($groupNamesById[$gid]) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (!empty($groups)): ?>
                            <button type="button" class="icon-btn" title="Groepen aanpassen"
                                    data-edit-groups="<?= (int) $sub['id'] ?>"
                                    data-email="<?= e($sub['email']) ?>"
                                    data-groups="<?= e(implode(',', $subGroupIds)) ?>">
                                <?= admin_icon('edit') ?><span class="visually-hidden">Groepen aanpassen</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
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
                            <input type="hidden" name="return_group" value="<?= e($groupFilter) ?>">
                            <button type="submit" class="icon-btn" title="Uitschrijven">
                                <?= admin_icon('undo') ?><span class="visually-hidden">Uitschrijven</span>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="post" action="newsletter-subscribers.php" onsubmit="return confirm('Dit adres definitief verwijderen?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $sub['id'] ?>">
                            <input type="hidden" name="return_group" value="<?= e($groupFilter) ?>">
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
            <input type="hidden" name="return_group" value="<?= e($groupFilter) ?>">
            <label for="email">Eén adres toevoegen</label>
            <input type="email" id="email" name="email" placeholder="naam@voorbeeld.be" required>
            <label for="first_name">Voornaam (optioneel)</label>
            <input type="text" id="first_name" name="first_name" maxlength="100">
            <?php if (!empty($groups)): ?>
                <fieldset class="group-checks">
                    <legend>Groepen (optioneel)</legend>
                    <?php foreach ($groups as $g): ?>
                        <label class="form-checkbox-row">
                            <input type="checkbox" name="group_ids[]" value="<?= (int) $g['id'] ?>"> <?= e($g['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>
            <div class="page-form-actions">
                <button type="submit">Toevoegen</button>
            </div>
        </form>

        <form method="post" action="newsletter-subscribers.php" enctype="multipart/form-data" class="page-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_csv">
            <input type="hidden" name="return_group" value="<?= e($groupFilter) ?>">
            <label for="csv">CSV importeren</label>
            <p class="field-hint">
                Eén e-mailadres per regel, optioneel gevolgd door een komma en
                voornaam, en daarna (na een tweede komma) een of meer groepen
                gescheiden door <code>;</code> — bv. <code>jan@voorbeeld.be,Jan,Counseling;Huiskamer</code>.
                Onbekende groepen worden aangemaakt. Bestaande of uitgeschreven
                adressen worden nooit overschreven; enkel hun groepen worden
                aangevuld.
            </p>
            <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
            <?php if (!empty($groups)): ?>
                <label for="import_group_id">Alle geïmporteerde adressen ook toevoegen aan</label>
                <select id="import_group_id" name="import_group_id">
                    <option value="">— geen groep —</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <div class="page-form-actions">
                <button type="submit"><?= admin_icon('upload') ?> Importeren</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($groups)): ?>
<dialog id="groups-dialog" class="admin-dialog">
    <form method="post" action="newsletter-subscribers.php" class="page-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_groups">
        <input type="hidden" name="id" id="groups-dialog-id" value="">
        <input type="hidden" name="return_group" value="<?= e($groupFilter) ?>">
        <h2>Groepen</h2>
        <p class="field-hint" id="groups-dialog-email"></p>
        <fieldset class="group-checks">
            <?php foreach ($groups as $g): ?>
                <label class="form-checkbox-row">
                    <input type="checkbox" name="group_ids[]" value="<?= (int) $g['id'] ?>"> <?= e($g['name']) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="page-form-actions">
            <button type="submit">Opslaan</button>
            <button type="button" class="button-secondary" data-close-dialog>Annuleren</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    var all = document.getElementById('select-all');
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('input[name="ids[]"]').forEach(function (cb) { cb.checked = all.checked; });
        });
    }
    var dialog = document.getElementById('groups-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    document.querySelectorAll('[data-edit-groups]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var current = (btn.getAttribute('data-groups') || '').split(',').filter(Boolean);
            document.getElementById('groups-dialog-id').value = btn.getAttribute('data-edit-groups');
            document.getElementById('groups-dialog-email').textContent = btn.getAttribute('data-email');
            dialog.querySelectorAll('input[name="group_ids[]"]').forEach(function (cb) {
                cb.checked = current.indexOf(cb.value) !== -1;
            });
            dialog.showModal();
        });
    });
    dialog.querySelector('[data-close-dialog]').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (ev) { if (ev.target === dialog) dialog.close(); });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
