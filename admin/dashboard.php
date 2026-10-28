<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['admin']);
$pdo = pdo_connect();
ensure_director_scope_columns($pdo);

// remember this as the last visited dashboard for Back buttons
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['last_dashboard'] = BASE_PATH . '/admin/dashboard.php';

$activeDirectors = $pdo->query("SELECT COUNT(*) FROM users WHERE role='director' AND status='approved'")->fetchColumn();
$activeFunerals = $pdo->query("SELECT COUNT(*) as cnt FROM funerals WHERE status IN ('pending','scheduled')")->fetchColumn();
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('client','director') AND status = 'pending'")->fetchColumn();
$pendingParlorsCount = (int)$pdo->query("SELECT COUNT(*) FROM funeral_parlors WHERE status = 'pending'")->fetchColumn();
?>
<div class="space-y-6 py-4">
  <div class="flex flex-col gap-3 rounded-[28px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between sm:p-6">
    <div>
      <p class="f-eyebrow mb-2">Admin access</p>
      <h2 class="text-3xl font-bold text-funeral-800">Admin Dashboard</h2>
      <p class="mt-1 text-sm text-stone-600">A calm overview of what needs your attention today.</p>
    </div>
    <a href="<?= BASE_PATH ?>/" class="inline-flex shrink-0 items-center gap-1 self-start rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50 sm:self-auto">
      <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
      Back
    </a>
  </div>

  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="f-card-flat flex items-center gap-3 p-4 shadow-sm">
      <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M9 21v-6h6v6"/></svg></span>
      <div><div class="text-2xl font-bold text-funeral-800"><?= htmlspecialchars($activeDirectors) ?></div><div class="text-xs text-stone-500">Active directors</div></div>
    </div>
    <div class="f-card-flat flex items-center gap-3 p-4 shadow-sm">
      <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c1.2 2.1 2 3.6 2 5a2 2 0 1 1-4 0c0-1.4.8-2.9 2-5Z" /><path d="M12 9v13" /><path d="M6 22c0-3.3 2.7-6 6-6s6 2.7 6 6" /></svg></span>
      <div><div class="text-2xl font-bold text-funeral-800"><?= htmlspecialchars($activeFunerals) ?></div><div class="text-xs text-stone-500">Active funerals</div></div>
    </div>
    <div class="f-card-flat flex items-center gap-3 p-4 shadow-sm">
      <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
      <div><div class="text-2xl font-bold text-funeral-800"><?= $pendingCount ?></div><div class="text-xs text-stone-500">Pending registrations</div></div>
    </div>
    <div class="f-card-flat flex items-center gap-3 p-4 shadow-sm">
      <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg></span>
      <div><div class="text-2xl font-bold text-funeral-800"><?= $pendingParlorsCount ?></div><div class="text-xs text-stone-500">Pending parlors</div></div>
    </div>
  </div>

  <div>
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-funeral-700">Account management</h3>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <a href="<?= BASE_PATH ?>/admin/manage_clients.php" class="f-card-flat flex items-center gap-3 p-4 shadow-sm transition hover:shadow-md">
        <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
        <div>
          <div class="font-semibold text-funeral-800">Manage Client</div>
          <div class="text-xs text-stone-500">Review client registrations</div>
        </div>
      </a>
      <a href="<?= BASE_PATH ?>/admin/manage_directors.php" class="f-card-flat flex items-center gap-3 p-4 shadow-sm transition hover:shadow-md">
        <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M9 21v-6h6v6"/></svg></span>
        <div>
          <div class="font-semibold text-funeral-800">Manage Director</div>
          <div class="text-xs text-stone-500">Review director registrations</div>
        </div>
      </a>
      <a href="<?= BASE_PATH ?>/admin/manage_parlors.php" class="f-card-flat flex items-center gap-3 p-4 shadow-sm transition hover:shadow-md">
        <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg></span>
        <div>
          <div class="font-semibold text-funeral-800">Manage Parlor</div>
          <div class="text-xs text-stone-500">Review parlor listings</div>
        </div>
      </a>
      <a href="<?= BASE_PATH ?>/admin/manage_all_accounts.php" class="f-card-flat flex items-center gap-3 p-4 shadow-sm transition hover:shadow-md">
        <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/></svg></span>
        <div>
          <div class="font-semibold text-funeral-800">Manage All Accounts</div>
          <div class="text-xs text-stone-500">Approved accounts, delete if needed</div>
        </div>
      </a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
