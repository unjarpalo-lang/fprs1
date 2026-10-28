<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
// destroy session and redirect before any output
session_unset();
// Expire the cookie too — session_destroy() only clears the server-side
// data, leaving a stale PHPSESSID sitting in the browser.
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: ' . BASE_PATH . '/'); exit;
