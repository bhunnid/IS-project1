<?php
/* logout.php - destroy the session server-side and expire the cookie in the browser. */
require __DIR__ . '/security.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'] ?: 'Strict',
    ]);
}
session_destroy();
header('Location: login.php');
exit;
