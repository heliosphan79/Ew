<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

// Same GET rationale as theme-preview.php: only clears the admin's own
// session state, nothing shared.
unset($_SESSION['preview_theme_id'], $_SESSION['preview_scheme']);

$return = (string) ($_GET['return'] ?? '');
redirect($return !== '' && is_safe_redirect_path($return) ? $return : 'themes.php');
