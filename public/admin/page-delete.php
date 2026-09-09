<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages.php');
}

csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $mysqli->prepare('SELECT content FROM pages WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deletedPage = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $mysqli->prepare('DELETE FROM pages WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    if ($deletedPage) {
        // Run after the DELETE so the removed page no longer counts as a
        // reference when checking whether its images are still in use.
        delete_orphaned_uploads($mysqli, extract_upload_urls(decode_blocks($deletedPage['content'])));
    }

    set_flash('success', 'Pagina verwijderd.');
}

redirect('pages.php');
