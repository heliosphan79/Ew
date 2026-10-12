<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

// GET, not POST+CSRF like theme-activate/-delete: this only writes to the
// logged-in admin's own session (preview_theme_id/preview_scheme), never
// to the database, so it can't affect any other visitor — a forged link
// could at worst nudge an admin into previewing an existing theme, which
// "Stoppen met voorbeeld" undoes instantly. It also needs to be a plain
// link (the "Voorbeeld" action in themes.php, the A/B/C links in the
// preview banner), not a form.
$id = (int) ($_GET['id'] ?? 0);
$scheme = isset($_GET['scheme']) ? (string) $_GET['scheme'] : null;
$return = (string) ($_GET['return'] ?? '/');
if (!is_safe_redirect_path($return)) {
    $return = '/';
}

if ($id > 0) {
    $stmt = $mysqli->prepare('SELECT id FROM themes WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $theme = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($theme) {
        $_SESSION['preview_theme_id'] = (int) $theme['id'];
        if ($scheme !== null && in_array($scheme, ['a', 'b', 'c'], true)) {
            $_SESSION['preview_scheme'] = $scheme;
        } else {
            // Starting a fresh preview from themes.php (no scheme given
            // yet): drop any scheme override left over from a previous
            // preview session.
            unset($_SESSION['preview_scheme']);
        }
    } else {
        set_flash('error', 'Dit thema bestaat niet (meer).');
    }
}

redirect($return);
