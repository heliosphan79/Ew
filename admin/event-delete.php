<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('events.php');
}

csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    // ON DELETE CASCADE on event_registrations takes care of its rows.
    $stmt = $mysqli->prepare('DELETE FROM events WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    set_flash('success', 'Evenement verwijderd.');
}

redirect('events.php');
