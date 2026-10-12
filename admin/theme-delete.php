<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('themes.php');
}

csrf_verify();

// Only ever called on a theme's own directory (themes/<slug>/, slug read
// back from the database row we just deleted, never from user input
// directly), so no path-traversal concern here.
function theme_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        is_dir($path) ? theme_rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $mysqli->prepare('SELECT slug, name, is_builtin FROM themes WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $theme = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$theme) {
        set_flash('error', 'Dit thema bestaat niet (meer).');
    } elseif ((int) $theme['is_builtin'] === 1) {
        set_flash('error', 'Het ingebouwde thema kan niet verwijderd worden.');
    } else {
        $wasActive = get_active_theme($mysqli, true)['id'] === $id;

        $stmt = $mysqli->prepare('DELETE FROM themes WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        theme_rrmdir(APP_ROOT . '/themes/' . $theme['slug']);

        $message = 'Thema "' . $theme['name'] . '" verwijderd.';
        if ($wasActive) {
            $message .= ' De site gebruikt nu weer het ingebouwde thema.';
        }
        set_flash('success', $message);
    }
}

redirect('themes.php');
