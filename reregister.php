<?php
/**
 * Reached only from the "Register Again" link on login.php's declined-account
 * notice. login.php stashes declined_reregister_id in the session at the
 * moment it verifies that visitor's password against that exact declined
 * row — so by the time we get here, whoever is asking has already proven
 * they own that account. Deleting it (re-checked as still declined, to
 * guard against a stale/reused session token) is what lets them resubmit
 * register.php with the same email instead of hitting "already registered".
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_SESSION['declined_reregister_id'] ?? 0);
unset($_SESSION['declined_reregister_id']);

if ($id) {
    $pdo = pdo_connect();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'rejected'");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn()) {
        delete_user_account($pdo, $id);
    }
}

header('Location: ' . BASE_PATH . '/register.php');
exit;
