<?php
declare(strict_types=1);

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

// Call at the top of every admin page except the login/install screens.
// $adminBaseUrl is the relative path back to public/admin/ from the
// calling script (e.g. '' for scripts inside admin/, '../admin/' from elsewhere).
function require_login(string $loginUrl = 'index.php'): void
{
    if (!is_logged_in()) {
        redirect($loginUrl);
    }
}

function attempt_login(mysqli $mysqli, string $username, string $password): bool
{
    $stmt = $mysqli->prepare('SELECT id, password_hash FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $user['id'];
    $_SESSION['admin_username'] = $username;
    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}
