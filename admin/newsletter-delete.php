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
    // Any status except an active send can be deleted — including 'sent'
    // and 'cancelled', which cascade-deletes their newsletter_sends/
    // newsletter_clicks rows (open/click history) along with them, so
    // there's no undo. A 'sending' newsletter must be cancelled first
    // (admin/newsletter-edit.php): deleting out from under a batch
    // request that's still running could otherwise update rows for a
    // newsletter_id that no longer exists mid-request.
    $stmt = $mysqli->prepare("DELETE FROM newsletters WHERE id = ? AND status != 'sending'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    set_flash($deleted ? 'success' : 'error', $deleted
        ? 'Nieuwsbrief verwijderd.'
        : 'Kan een nieuwsbrief die nog aan het verzenden is niet verwijderen — annuleer de verzending eerst.');
}

redirect('newsletters.php');
