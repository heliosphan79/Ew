<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    set_flash('error', 'Ongeldige nieuwsbrief.');
    redirect('newsletters.php');
}

$stmt = $mysqli->prepare('SELECT subject FROM newsletters WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$newsletter = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$newsletter) {
    set_flash('error', 'Nieuwsbrief niet gevonden.');
    redirect('newsletters.php');
}

$pageTitle = 'Voorvertoning';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-header-row">
    <h1>Voorvertoning</h1>
    <a href="newsletter-edit.php?id=<?= (int) $id ?>" class="button-secondary">Terug naar nieuwsbrief</a>
</div>

<p><strong>Onderwerp:</strong> <?= e($newsletter['subject']) ?></p>
<p class="field-hint">
    Exact wat er verstuurd wordt — inclusief <code>{{voornaam}}</code>, hier
    getoond met de terugval ("daar") zoals bij een onbekende voornaam.
    Gebaseerd op de laatst opgeslagen versie; sla eerst op als je recente
    wijzigingen hier wil zien.
    <a href="newsletter-preview-raw.php?id=<?= (int) $id ?>" target="_blank" rel="noopener">Open in nieuw tabblad</a>.
</p>

<iframe src="newsletter-preview-raw.php?id=<?= (int) $id ?>" title="Voorvertoning van de nieuwsbrief"
        style="width:100%;height:80vh;border:1px solid var(--color-border);border-radius:10px;background:#fff;"></iframe>

<?php require __DIR__ . '/includes/footer.php'; ?>
