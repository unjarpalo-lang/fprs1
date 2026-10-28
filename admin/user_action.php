<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['admin']);
$pdo = pdo_connect();
$currentUser = current_user();

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$returnUrl = admin_return_url(BASE_PATH . '/admin/manage_all_accounts.php');

if ($action === 'delete' && $id) {
    require_csrf_token($_GET['token'] ?? null);
    if ($id === (int)$currentUser['id']) {
        header('Location: ' . BASE_PATH . '/admin/dashboard.php?error=self');
        exit;
    }
    $target = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $target->execute([$id]);
    $targetRole = $target->fetchColumn();

    if ($targetRole === 'admin') {
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        if ($adminCount <= 1) {
            header('Location: ' . BASE_PATH . '/admin/dashboard.php?error=lastadmin');
            exit;
        }
    }

    if ($targetRole !== false) {
        delete_user_account($pdo, $id);
    }
}

header('Location: ' . $returnUrl);
exit;
