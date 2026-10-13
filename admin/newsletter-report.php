<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $mysqli->prepare('SELECT id, subject, status, audience, sent_at FROM newsletters WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$newsletter = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$newsletter) {
    set_flash('error', 'Nieuwsbrief niet gevonden.');
    redirect('newsletters.php');
}
if ($newsletter['status'] === 'draft') {
    set_flash('error', 'Deze nieuwsbrief is nog niet verzonden — er is nog niets te rapporteren.');
    redirect('newsletter-edit.php?id=' . $id);
}

$filters = [
    'all' => 'Iedereen',
    'opened' => 'Geopend',
    'unopened' => 'Niet geopend',
    'clicked' => 'Geklikt',
];
$filter = (string) ($_GET['f'] ?? 'all');
if (!array_key_exists($filter, $filters)) {
    $filter = 'all';
}

// Samenvatting. Percentages zijn t.o.v. wie de mail effectief ontving
// (sent_at gevuld) — bij een geannuleerde verzending staan er nog
// ontvangers in de wachtrij die nooit een mail kregen.
$stmt = $mysqli->prepare(
    'SELECT COUNT(*) AS queued,
        COALESCE(SUM(sent_at IS NOT NULL), 0) AS sent,
        COALESCE(SUM(opened_at IS NOT NULL), 0) AS opened,
        COALESCE(SUM(EXISTS (SELECT 1 FROM newsletter_clicks c WHERE c.send_id = ns.id)), 0) AS clicked
     FROM newsletter_sends ns WHERE ns.newsletter_id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
$sent = (int) $stats['sent'];
$opened = (int) $stats['opened'];
$clicked = (int) $stats['clicked'];
$pct = fn(int $n): string => $sent > 0 ? number_format($n / $sent * 100, 0, ',', '.') . '%' : '—';

$stmt = $mysqli->prepare(
    'SELECT c.url, COUNT(*) AS clicks, COUNT(DISTINCT c.send_id) AS people
     FROM newsletter_clicks c
     JOIN newsletter_sends ns ON ns.id = c.send_id
     WHERE ns.newsletter_id = ?
     GROUP BY c.url
     ORDER BY people DESC, clicks DESC'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$linkStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conditions = ['ns.newsletter_id = ?'];
if ($filter === 'opened') {
    $conditions[] = 'ns.opened_at IS NOT NULL';
} elseif ($filter === 'unopened') {
    $conditions[] = 'ns.sent_at IS NOT NULL AND ns.opened_at IS NULL';
} elseif ($filter === 'clicked') {
    $conditions[] = 'EXISTS (SELECT 1 FROM newsletter_clicks c WHERE c.send_id = ns.id)';
}
$stmt = $mysqli->prepare(
    "SELECT ns.sent_at, ns.opened_at, sub.email, sub.first_name, sub.status,
        (SELECT COUNT(*) FROM newsletter_clicks c WHERE c.send_id = ns.id) AS click_count,
        (SELECT GROUP_CONCAT(DISTINCT c.url ORDER BY c.url SEPARATOR '\n') FROM newsletter_clicks c WHERE c.send_id = ns.id) AS click_urls
     FROM newsletter_sends ns
     JOIN newsletter_subscribers sub ON sub.id = ns.subscriber_id
     WHERE " . implode(' AND ', $conditions) . '
     ORDER BY (ns.opened_at IS NULL), ns.opened_at DESC, sub.email ASC'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$recipients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$fmt = fn(?string $dt): string => $dt ? date('d/m/Y H:i', strtotime($dt)) : '';

if (($_GET['format'] ?? '') === 'csv') {
    // Een cel die met = + - @ begint kan in Excel als formule worden
    // uitgevoerd — een e-mailadres (of voornaam) is gebruikersinvoer, dus
    // neutraliseer ze met een voorafgaande apostrof.
    $cell = function (string $value): string {
        if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            $value = "'" . $value;
        }
        return '"' . str_replace('"', '""', $value) . '"';
    };
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="nieuwsbrief-' . $id . '-' . $filter . '.csv"');
    // BOM + puntkomma: zo opent Excel (NL/BE) het meteen met de juiste
    // tekens en kolommen.
    echo "\xEF\xBB\xBF" . implode(';', ['E-mailadres', 'Voornaam', 'Verzonden', 'Eerste keer geopend', 'Aantal klikken', 'Status abonnee']) . "\r\n";
    foreach ($recipients as $r) {
        echo implode(';', [
            $cell((string) $r['email']),
            $cell((string) ($r['first_name'] ?? '')),
            $cell($fmt($r['sent_at'])),
            $cell($fmt($r['opened_at'])),
            (int) $r['click_count'],
            $cell($r['status'] === 'subscribed' ? 'geabonneerd' : 'uitgeschreven'),
        ]) . "\r\n";
    }
    exit;
}

$pageTitle = 'Rapport: ' . $newsletter['subject'];
require __DIR__ . '/includes/header.php';
?>

<div class="admin-header-row">
    <h1>Rapport</h1>
    <div>
        <a href="newsletter-edit.php?id=<?= $id ?>" class="button-secondary">Nieuwsbrief bekijken</a>
        <a href="newsletters.php" class="button-secondary">Terug naar nieuwsbrieven</a>
    </div>
</div>

<p>
    <strong><?= e($newsletter['subject']) ?></strong>
    <?php if ($newsletter['sent_at']): ?>
        — <?= $newsletter['status'] === 'cancelled' ? 'geannuleerd' : 'verzonden' ?> op <?= e($fmt($newsletter['sent_at'])) ?>
    <?php endif; ?>
</p>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-card-icon"><?= admin_icon('mail') ?></span>
        <span>
            <span class="stat-number"><?= $sent ?></span>
            <span class="stat-label">Verzonden<?= (int) $stats['queued'] > $sent ? ' (van ' . (int) $stats['queued'] . ' in de wachtrij)' : '' ?></span>
        </span>
    </div>
    <div class="stat-card">
        <span class="stat-card-icon"><?= admin_icon('eye') ?></span>
        <span>
            <span class="stat-number"><?= $opened ?> <small>(<?= e($pct($opened)) ?>)</small></span>
            <span class="stat-label">Geopend</span>
        </span>
    </div>
    <div class="stat-card">
        <span class="stat-card-icon"><?= admin_icon('chart') ?></span>
        <span>
            <span class="stat-number"><?= $clicked ?> <small>(<?= e($pct($clicked)) ?>)</small></span>
            <span class="stat-label">Op een link geklikt</span>
        </span>
    </div>
</div>

<p class="field-hint">
    Een "open" wordt geteld zodra het mailprogramma het onzichtbare plaatje
    in de mail laadt, en enkel de <em>eerste</em> keer. Dat is indicatief:
    mailprogramma's die afbeeldingen blokkeren geven een gemiste open, terwijl
    andere (zoals Apple Mail met Privacybescherming) afbeeldingen automatisch
    laden en dus een open kunnen tonen terwijl de mail nooit gelezen is. Een
    klik op een link telt altijd ook als open. Een geklikte link is de
    betrouwbaarste maatstaf voor echte interesse.
</p>

<?php if (!empty($linkStats)): ?>
    <h2>Aangeklikte links</h2>
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
        <tr>
            <th>Link</th>
            <th>Personen</th>
            <th>Klikken</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($linkStats as $link): ?>
            <tr>
                <td><a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(mb_strimwidth((string) preg_replace('#^https?://#i', '', $link['url']), 0, 70, '…')) ?></a></td>
                <td><?= (int) $link['people'] ?></td>
                <td><?= (int) $link['clicks'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<h2>Ontvangers</h2>

<nav class="settings-tabs" aria-label="Filter">
    <?php foreach ($filters as $key => $label): ?>
        <a href="newsletter-report.php?id=<?= $id ?>&amp;f=<?= e($key) ?>" class="<?= $filter === $key ? 'is-active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<p>
    <?= count($recipients) ?> ontvanger<?= count($recipients) === 1 ? '' : 's' ?>
    <?php if (!empty($recipients)): ?>
        · <a href="newsletter-report.php?id=<?= $id ?>&amp;f=<?= e($filter) ?>&amp;format=csv">Downloaden als CSV</a>
    <?php endif; ?>
</p>

<div class="table-scroll">
<table class="admin-table">
    <thead>
    <tr>
        <th>E-mailadres</th>
        <th>Voornaam</th>
        <th>Verzonden</th>
        <th>Eerste keer geopend</th>
        <th>Klikken</th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($recipients)): ?>
        <tr><td colspan="5">Niemand in deze weergave.</td></tr>
    <?php endif; ?>
    <?php foreach ($recipients as $r): ?>
        <tr>
            <td>
                <?= e($r['email']) ?>
                <?php if ($r['status'] !== 'subscribed'): ?> <span class="badge badge-draft">Uitgeschreven</span><?php endif; ?>
            </td>
            <td><?= e($r['first_name'] ?? '') ?></td>
            <td><?= $r['sent_at'] ? e($fmt($r['sent_at'])) : '<span class="field-hint">niet verstuurd</span>' ?></td>
            <td><?= $r['opened_at'] ? '<span class="badge badge-ok">' . e($fmt($r['opened_at'])) . '</span>' : '<span class="field-hint">—</span>' ?></td>
            <td>
                <?php if ((int) $r['click_count'] > 0): ?>
                    <span title="<?= e((string) $r['click_urls']) ?>"><?= (int) $r['click_count'] ?></span>
                <?php else: ?>
                    <span class="field-hint">—</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
