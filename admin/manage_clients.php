<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['admin']);
$pdo = pdo_connect();

// Approved clients move to Manage All Accounts — this page is only for the
// ones still awaiting a decision (or already declined, available for deletion).
$clients = $pdo->query("SELECT id, full_name, email, phone, role, status, valid_id_path, created_at FROM users WHERE role = 'client' AND status != 'approved' ORDER BY created_at DESC")->fetchAll();
?>
<div class="space-y-6 py-4">
  <div class="flex flex-col gap-3 rounded-[28px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between sm:p-6">
    <div>
      <p class="f-eyebrow mb-2">Admin access</p>
      <h2 class="text-3xl font-bold text-funeral-800">Manage Client</h2>
      <p class="mt-1 text-sm text-stone-600">Review, approve, or decline client registrations. Approved accounts move to Manage All Accounts.</p>
    </div>
    <a href="<?= BASE_PATH ?>/admin/dashboard.php" class="inline-flex shrink-0 items-center gap-1 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50">
      <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
      Back to dashboard
    </a>
  </div>

  <?php render_admin_notice(); ?>
  <div class="f-card p-5 sm:p-6">
    <?php if (!$clients): ?>
      <div class="rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 p-8 text-center text-sm text-stone-500">No client accounts awaiting review. Approved ones are in Manage All Accounts.</div>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($clients as $u): render_admin_account_row($u); endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php render_delete_confirmation_modal(); ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
