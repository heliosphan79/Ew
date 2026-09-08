<?php
declare(strict_types=1);

// Every entry-point script requires this file first. It wires up the
// session, config, db connection and helper functions in one place so
// path handling only has to be figured out once (see db.php / functions.php,
// which resolve everything relative to __DIR__ rather than the caller).

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

define('APP_ROOT', dirname(__DIR__));

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
