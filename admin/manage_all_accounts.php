<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['admin']);
$pdo = pdo_connect();
ensure_director_scope_columns($pdo);
$currentUser = current_user();

$tabs = ['all' => 'All Accounts', 'client' => 'Client', 'parlor' => 'Parlor', 'director' => 'Director'];
$typeFilter = $_GET['type'] ?? 'all';
if (!array_key_exists($typeFilter, $tabs)) {
    $typeFilter = 'all';
}

$approvedUsers = [];
if ($typeFilter === 'all') {
    $approvedUsers = $pdo->query("SELECT id, full_name, email, phone, role, status, created_at FROM users WHERE status = 'approved' ORDER BY FIELD(role,'admin','director','client'), full_name ASC")->fetchAll();
} elseif ($typeFilter === 'client' || $typeFilter === 'director') {
    $stmt = $pdo->prepare("SELECT id, full_name, email, phone, role, status, created_at FROM users WHERE status = 'approved' AND role = ? ORDER BY full_name ASC");
    $stmt->execute([$typeFilter]);
    $approvedUsers = $stmt->fetchAll();
}

$approvedParlors = [];
if ($typeFilter === 'all' || $typeFilter === 'parlor') {
    $approvedParlors = $pdo->query("SELECT fp.*, u.full_name AS director_name FROM funeral_parlors fp JOIN users u ON u.id = fp.director_id WHERE fp.status = 'approved' ORDER BY fp.name ASC")->fetchAll();
}

$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$isEmpty = !$approvedUsers && !$approvedParlors;
?>
<div class="space-y-6 py-4">
  <div class="flex flex-col gap-3 rounded-[28px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between sm:p-6">
    <div>
      <p class="f-eyebrow mb-2">Admin access</p>
      <h2 class="text-3xl font-bold text-funeral-800">Manage All Accounts</h2>
      <p class="mt-1 text-sm text-stone-600">Every fully approved account and parlor. Delete one outright if it no longer belongs on the platform.</p>
    </div>
    <a href="<?= BASE_PATH ?>/admin/dashboard.php" class="inline-flex shrink-0 items-center gap-1 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50">
      <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
      Back to dashboard
    </a>
  </div>

  <?php render_admin_notice(); ?>
  <div class="f-card p-5 sm:p-6">
    <div class="mb-4 flex flex-wrap gap-2">
      <?php foreach ($tabs as $value => $label): ?>
        <a href="?type=<?= $value ?>" class="rounded-full px-4 py-2 text-sm font-medium transition <?= $typeFilter === $value ? 'bg-funeral-600 text-white' : 'border border-stone-300 bg-white text-stone-700 hover:bg-stone-50' ?>"><?= htmlspecialchars($label) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($isEmpty): ?>
      <div class="rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 p-8 text-center text-sm text-stone-500">No approved accounts in this category.</div>
    <?php else: ?>
      <?php if ($approvedUsers): ?>
        <?php if ($typeFilter === 'all'): ?>
          <h4 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-funeral-700">Accounts</h4>
        <?php endif; ?>
        <div class="space-y-3">
          <?php foreach ($approvedUsers as $u):
            $isSelf = (int)$u['id'] === (int)$currentUser['id'];
            $isLastAdmin = $u['role'] === 'admin' && $adminCount <= 1;
            render_admin_all_accounts_user_row($u, $isSelf, $isLastAdmin);
          endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($approvedParlors): ?>
        <?php if ($typeFilter === 'all'): ?>
          <h4 class="mb-3 mt-6 text-sm font-semibold uppercase tracking-[0.16em] text-funeral-700">Parlors</h4>
        <?php endif; ?>
        <div class="space-y-3">
          <?php foreach ($approvedParlors as $p): render_admin_all_accounts_parlor_row($p); endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php render_delete_confirmation_modal(); ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
