<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['admin']);
$pdo = pdo_connect();
ensure_director_scope_columns($pdo);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$returnUrl = admin_return_url(BASE_PATH . '/admin/manage_parlors.php');

// Unified status control: admin can move a parlor between Approved, Pending,
// and Declined from any current status — no confirmation step, single action.
// Kept alongside the older approve/reject actions for backward compatibility.
$statusMap = ['approve' => 'approved', 'pending' => 'pending', 'reject' => 'rejected', 'decline' => 'rejected'];
if ($id && ($action === 'set_status' || isset($statusMap[$action]))) {
    require_csrf_token($_POST['token'] ?? $_GET['token'] ?? null);
    $status = $action === 'set_status' ? ($_POST['status'] ?? $_GET['status'] ?? '') : $statusMap[$action];
    if (in_array($status, ['approved', 'pending', 'rejected'], true)) {
        if ($status === 'approved') {
            $check = $pdo->prepare('SELECT * FROM funeral_parlors WHERE id = ?');
            $check->execute([$id]);
            $candidate = $check->fetch();
            $approvalError = $candidate ? parlor_approval_error($candidate) : 'Funeral parlor not found.';
            if ($approvalError !== null) {
                $_SESSION['admin_notice'] = $approvalError;
                header('Location: ' . $returnUrl);
                exit;
            }
        }
        $reason = $status === 'rejected' ? trim($_POST['reason'] ?? '') : null;
        $stmt = $pdo->prepare('UPDATE funeral_parlors SET status = ?, status_reason = ? WHERE id = ?');
        $stmt->execute([$status, $reason ?: null, $id]);
    }
    $redirect = $returnUrl;
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete' && $id) {
    require_csrf_token($_GET['token'] ?? null);
    delete_parlor_listing($pdo, $id);
    $redirect = $returnUrl;
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'view' && $id) {
    $stmt = $pdo->prepare('SELECT * FROM funeral_parlors WHERE id=?');
    $stmt->execute([$id]);
    $parlor = $stmt->fetch();
    include __DIR__ . '/../includes/header.php';
    if (!$parlor) {
        echo '<p class="text-red-700">Parlor not found.</p>';
    } else {
        ?>
        <div class="mx-auto max-w-2xl py-4">
          <div class="f-card p-6">
            <?php render_catalog_back_link($returnUrl, 'Back to parlor list'); ?>
        <h2 class="text-2xl font-semibold text-funeral-800"><?= htmlspecialchars($parlor['name']) ?></h2>
            <p class="mt-1 text-sm text-stone-600"><?= htmlspecialchars($parlor['location']) ?> · <?= htmlspecialchars($parlor['contact_number']) ?></p>

            <div class="mt-5">
              <div class="text-sm font-medium text-stone-700">Business license</div>
              <?php render_document_viewer(__DIR__ . '/../' . ltrim($parlor['license_path'], '/'), BASE_PATH . '/admin/serve_document.php?type=parlor&id=' . (int)$parlor['id'], 'Business license'); ?>
            </div>

            <div class="mt-6">
              <a href="<?= htmlspecialchars($returnUrl) ?>" class="inline-flex items-center rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300">Back to parlor list</a>
            </div>
          </div>
        </div>
        <?php
    }
    include __DIR__ . '/../includes/footer.php';
}
