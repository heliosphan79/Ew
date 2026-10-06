<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('newsletters.php');
}

csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    // Only ever delete a draft — a newsletter that's sending/sent keeps its
    // open/click history, which would otherwise cascade-delete with it.
    $stmt = $mysqli->prepare("DELETE FROM newsletters WHERE id = ? AND status = 'draft'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    set_flash('success', 'Nieuwsbrief verwijderd.');
}

redirect('newsletters.php');
