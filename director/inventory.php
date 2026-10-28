<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['director']);
$user = current_user();
$pdo = pdo_connect();
// Scope columns are checked by the inventory readiness guard.
ensure_inventory_media_columns($pdo);
$selectedParlor = selected_director_parlor($pdo, (int)$user['id']);
if (!$selectedParlor) { header('Location: ' . BASE_PATH . '/director/funeral_parlors.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM inventory_resources WHERE director_id = ? AND parlor_id = ? ORDER BY updated_at DESC');
$stmt->execute([$user['id'], $selectedParlor['id']]);
$items = $stmt->fetchAll();
?>
<div class="space-y-5 py-2">
  <div class="flex flex-col gap-3 rounded-[24px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between">
    <div>
<?php render_catalog_back_link(BASE_PATH . '/director/dashboard.php', 'Back to director dashboard'); ?>
      <p class="f-eyebrow mb-1">Supplies on hand</p>
      <h2 class="text-2xl font-semibold text-funeral-800">Inventory Resources</h2>
      <p class="mt-1 text-sm text-stone-600">Managing: <span class="font-medium text-funeral-700"><?= htmlspecialchars($selectedParlor['name']) ?></span></p>
    </div>
    <a href="inventory_action.php?action=create" class="inline-flex items-center gap-2 rounded-full bg-funeral-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-funeral-500">
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      Add Resource
    </a>
  </div>

  <?php if (empty($items)): ?>
    <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 p-8 text-center">
      <span class="text-2xl">🕯️</span>
      <p class="text-sm font-medium text-stone-700">No inventory resources yet.</p>
      <p class="text-sm text-stone-500">Add one to get started.</p>
    </div>
  <?php else: ?>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach($items as $it): ?>
        <?php
          $typeMap = ['flower'=>'Flowers','candle'=>'Candles','plant'=>'Plants','casket'=>'Caskets and urns','equipment'=>'Equipment','other'=>'Other supplies'];
          $typeLabel = $typeMap[$it['item_type']] ?? htmlspecialchars($it['item_type']);
        ?>
        <div class="f-card-flat flex flex-col justify-between p-5 shadow-sm">
          <div>
            <div class="mb-3 flex items-center gap-3">
              <?php if (!empty($it['image_path'])): ?>
                <img src="<?= htmlspecialchars(BASE_PATH . '/' . ltrim($it['image_path'], '/')) ?>" alt="<?= htmlspecialchars($it['name']) ?>" class="h-14 w-14 shrink-0 rounded-lg border border-stone-200 object-cover" />
              <?php else: ?>
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg border border-dashed border-stone-300 bg-stone-50 text-stone-300">
                  <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
                </div>
              <?php endif; ?>
              <div class="min-w-0">
                <div class="truncate text-lg font-semibold text-funeral-800"><?=htmlspecialchars($it['name'])?></div>
                <div class="text-sm text-stone-500">Qty: <span class="font-semibold text-stone-700"><?= (int)($it['quantity'] ?? 0) ?></span></div>
              </div>
            </div>
            <div class="mb-2 text-sm leading-relaxed text-stone-600"><?=nl2br(htmlspecialchars($it['details']))?></div>
          </div>
          <div class="mt-4 border-t border-stone-200 pt-3">
            <div class="flex flex-wrap gap-1.5">
              <span class="f-pill bg-funeral-100 text-funeral-800"><?= $typeLabel ?></span>
              <span class="f-pill <?= inventory_status_pill_class($it['available_status']) ?>"><?= htmlspecialchars(ucfirst($it['available_status'])) ?></span>
            </div>
            <div class="mt-3 flex gap-2">
              <a href="inventory_action.php?action=edit&id=<?=$it['id']?>" class="inline-flex items-center px-3 py-1.5 rounded-full bg-amber-500 text-white text-sm font-medium transition hover:bg-amber-400">Edit</a>
              <a href="inventory_action.php?action=delete&id=<?=(int)$it['id']?>&token=<?=urlencode(csrf_token())?>" class="inline-flex items-center px-3 py-1.5 rounded-full bg-red-600 text-white text-sm font-medium transition hover:bg-red-500" onclick="return confirm('Delete this resource?');">Delete</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
