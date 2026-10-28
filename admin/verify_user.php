<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['admin']);
$pdo = pdo_connect();
$action = $_GET['action'] ?? 'list';
if (!isset($_GET['id'])) { header('Location: ' . BASE_PATH . '/admin/dashboard.php'); exit; }
$id = (int)$_GET['id'];
$targetStmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$targetStmt->execute([$id]);
$targetRole = $targetStmt->fetchColumn();
$returnUrl = admin_return_url(BASE_PATH . '/admin/' . ($targetRole === 'director' ? 'manage_directors.php' : 'manage_clients.php'));

// Unified status control, mirroring parlor_action.php: an approved or
// declined account can be moved back to any of the three states from the
// dashboard, not just approved-once-then-frozen.
$statusMap = ['approve' => 'approved', 'pending' => 'pending', 'reject' => 'rejected'];
if ($id && isset($statusMap[$action])) {
    require_csrf_token($_GET['token'] ?? null);
    $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$statusMap[$action], $id]);
    header('Location: ' . $returnUrl); exit;
}

if ($action === 'view') {
    $u = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $u->execute([$id]); $user = $u->fetch();
    if (!$user) { echo "Not found"; exit; }
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="mx-auto max-w-2xl py-4">
      <div class="f-card p-6">
        <?php render_catalog_back_link($returnUrl, 'Back to account list'); ?>
        <h2 class="text-2xl font-semibold text-funeral-800">Verification documents: <?= htmlspecialchars($user['full_name']) ?></h2>
        <p class="mt-1 text-sm text-stone-500">Rendered directly below — no need to open a new tab.</p>

        <?php if ($user['valid_id_path']): ?>
          <div class="mt-5">
            <div class="text-sm font-medium text-stone-700">Valid ID</div>
            <?php render_document_viewer(__DIR__ . '/../' . ltrim($user['valid_id_path'], '/'), BASE_PATH . '/admin/serve_document.php?type=user&doc=valid_id&id=' . $id, 'Valid ID'); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($user['license_path'])): ?>
          <div class="mt-5">
            <div class="text-sm font-medium text-stone-700">Funeral home license</div>
            <?php render_document_viewer(__DIR__ . '/../' . ltrim($user['license_path'], '/'), BASE_PATH . '/admin/serve_document.php?type=user&doc=license&id=' . $id, 'Funeral home license'); ?>
          </div>
        <?php endif; ?>

        <div class="mt-6 flex flex-wrap gap-2">
          <?php if ($user['status'] !== 'approved'): ?>
            <a href="<?= BASE_PATH ?>/admin/verify_user.php?action=approve&id=<?= $id ?>&token=<?= urlencode(csrf_token()) ?>&amp;return_to=<?= urlencode($returnUrl) ?>" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">Approve</a>
            <a href="<?= BASE_PATH ?>/admin/verify_user.php?action=reject&id=<?= $id ?>&token=<?= urlencode(csrf_token()) ?>&amp;return_to=<?= urlencode($returnUrl) ?>" class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">Decline</a>
          <?php endif; ?>
          <a href="<?= htmlspecialchars($returnUrl) ?>" class="inline-flex items-center rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300">Back</a>
        </div>
      </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}
