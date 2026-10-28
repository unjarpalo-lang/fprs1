<?php
$showDirectorPersonalAccount = true;
require_once __DIR__ . '/../includes/header.php';
require_any_role(['director']);
$pdo = pdo_connect();
$user = current_user();
$pdo->exec("CREATE TABLE IF NOT EXISTS funeral_parlors (id INT AUTO_INCREMENT PRIMARY KEY, director_id INT NOT NULL, name VARCHAR(200) NOT NULL, location VARCHAR(255) NOT NULL, contact_number VARCHAR(50) NOT NULL, license_path VARCHAR(255) NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE CASCADE)");
$stmt = $pdo->prepare('SELECT * FROM funeral_parlors WHERE director_id = ? ORDER BY name ASC');
$stmt->execute([$user['id']]);
$parlors = $stmt->fetchAll();
?>
<div class="space-y-5 py-4">
  <div class="flex flex-col gap-3 rounded-[28px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between">
    <div>
<?php render_catalog_back_link(BASE_PATH . '/director/dashboard.php?overview=1', 'Back to director dashboard'); ?>
      <p class="f-eyebrow mb-2">Director portal</p>
      <h2 class="text-3xl font-bold text-funeral-800">List of Funeral Parlors</h2>
    </div>
    <a href="<?= BASE_PATH ?>/director/funeral_register.php" class="inline-flex items-center gap-2 rounded-full bg-funeral-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-funeral-500">
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      Register parlor
    </a>
  </div>

  <?php if (!$parlors): ?>
    <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 p-8 text-center">
      <span class="text-2xl">🏛️</span>
      <p class="text-sm font-medium text-stone-700">No funeral parlors registered yet.</p>
      <p class="text-sm text-stone-500">Register your first parlor to start listing packages.</p>
    </div>
  <?php endif; ?>

  <div class="grid gap-4 md:grid-cols-2">
    <?php foreach ($parlors as $parlor): ?>
      <div class="f-card-flat p-5 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3">
            <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M9 21v-6h6v6"/></svg></span>
            <div>
              <h3 class="text-xl font-semibold text-funeral-800"><?= htmlspecialchars($parlor['name']) ?></h3>
              <p class="mt-1 text-sm text-stone-600"><?= htmlspecialchars($parlor['location']) ?></p>
              <p class="text-sm text-stone-600"><?= htmlspecialchars($parlor['contact_number']) ?></p>
            </div>
          </div>
          <span class="f-pill <?= status_pill_class($parlor['status']) ?>"><?= htmlspecialchars(status_label($parlor['status'])) ?></span>
        </div>
        <?php if ($parlor['status'] !== 'approved'): ?>
          <p class="mt-3 text-sm text-stone-600">Your account is approved. This parlor is <?= htmlspecialchars(status_label($parlor['status'])) ?> and hidden from clients. <?= private_document_path($parlor['license_path'] ?? null) === null ? 'Upload a business license in Manage Account, then wait for administrator review.' : 'An administrator must approve this listing before its packages appear to clients.' ?></p>
        <?php endif; ?>
        <!-- Every parlor is selectable/manageable regardless of status — only
             client visibility is status-gated, never the director's own tools. -->
        <?php if (private_document_path($parlor['license_path'] ?? null) === null): ?><p class="mt-2 text-sm text-amber-700">Parlor business license missing. Select this parlor and open Manage Account to upload it.</p><?php endif; ?>
        <form method="post" action="<?= BASE_PATH ?>/director/dashboard.php" class="mt-4">
          <?= csrf_field() ?><input type="hidden" name="parlor_id" value="<?= (int)$parlor['id'] ?>" />
          <button name="select_parlor" value="1" class="rounded-full bg-funeral-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-funeral-500">Select Parlor</button>
        </form>
        <?php if ($parlor['status'] === 'pending'): ?>
          <p class="mt-2 text-xs text-stone-500">You can manage packages while awaiting approval — they won't be public until an admin approves this parlor.</p>
        <?php elseif ($parlor['status'] === 'rejected'): ?>
          <p class="mt-2 text-xs text-stone-500">Declined<?= !empty($parlor['status_reason']) ? ': ' . htmlspecialchars($parlor['status_reason']) : '.' ?> You can still update its info; an admin will need to re-approve it before it's public.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
