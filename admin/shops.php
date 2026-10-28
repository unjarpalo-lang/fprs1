<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['admin']);
$pdo = pdo_connect();
$shops = $pdo->query('SELECT * FROM shops ORDER BY created_at DESC')->fetchAll();
?>
<div class="space-y-5 py-2">
  <div class="flex flex-col gap-3 rounded-[24px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between">
    <div>
      <?php render_catalog_back_link(BASE_PATH . '/admin/dashboard.php', 'Back to admin dashboard'); ?>
      <p class="f-eyebrow mb-1">Community resources</p>
      <h2 class="text-2xl font-semibold text-funeral-800">Recommended Shops</h2>
    </div>
    <a href="shop_action.php?action=create" class="inline-flex items-center gap-2 rounded-full bg-funeral-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-funeral-500">
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      Add Shop
    </a>
  </div>

  <?php if (empty($shops)): ?>
    <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 p-8 text-center">
      <span class="text-2xl">🌸</span>
      <p class="text-sm font-medium text-stone-700">No shops listed yet.</p>
      <p class="text-sm text-stone-500">Add one to recommend it to families and directors.</p>
    </div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach($shops as $s): ?>
        <div class="f-card-flat flex flex-col gap-3 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3">
            <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1-5h16l1 5"/><path d="M4 9v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M9 20v-6h6v6"/></svg></span>
            <div>
              <div class="font-medium text-stone-800"><?=htmlspecialchars($s['name'])?></div>
              <div class="text-sm text-stone-500"><?=htmlspecialchars($s['location'])?></div>
            </div>
          </div>
          <div class="flex gap-2">
            <a href="shop_action.php?action=edit&id=<?=$s['id']?>" class="inline-flex items-center px-3 py-1.5 rounded-full bg-amber-500 text-white text-sm font-medium transition hover:bg-amber-400">Edit</a>
            <a href="shop_action.php?action=delete&id=<?=(int)$s['id']?>&token=<?=urlencode(csrf_token())?>" class="inline-flex items-center px-3 py-1.5 rounded-full bg-red-600 text-white text-sm font-medium transition hover:bg-red-500" onclick="return confirm('Delete this shop?');">Delete</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
