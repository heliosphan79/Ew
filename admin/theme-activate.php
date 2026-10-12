<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('themes.php');
}

csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $mysqli->prepare('SELECT name FROM themes WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $theme = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($theme) {
        $stmt = $mysqli->prepare('UPDATE site_settings SET active_theme_id = ? WHERE id = 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        set_flash('success', 'Thema "' . $theme['name'] . '" is nu actief.');
    } else {
        set_flash('error', 'Dit thema bestaat niet (meer).');
    }
}

redirect('themes.php');
