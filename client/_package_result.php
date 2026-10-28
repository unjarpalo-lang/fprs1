<?php
/**
 * Renders one package search result. Expects $package (an entry from
 * $exactResults or $flaggedResults in client/dashboard.php) in scope.
 * Not a standalone entry point — never requested directly.
 */
if (!defined('BASE_PATH') || !isset($package)) { http_response_code(404); exit; }
?>
<article class="f-card-flat p-4 shadow-sm">
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-3">
      <?php if (!empty($package['image_path'])): ?>
        <img src="<?= htmlspecialchars(BASE_PATH . '/' . ltrim($package['image_path'], '/')) ?>" alt="<?= htmlspecialchars($package['title']) ?>" class="h-16 w-16 shrink-0 rounded-lg border border-stone-200 object-cover" />
      <?php else: ?>
        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border border-dashed border-stone-300 bg-stone-50 text-stone-300">
          <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
        </div>
      <?php endif; ?>
      <div class="min-w-0 break-words">
        <div class="flex flex-wrap items-center gap-1.5">
          <span class="font-medium text-stone-800"><?= htmlspecialchars($package['title']) ?></span>
          <?php if ($package['budget_flag']): ?><span class="f-pill bg-amber-100 text-amber-800"><?= htmlspecialchars($package['budget_flag']) ?></span><?php endif; ?>
          <?php if ($package['location_flag']): ?><span class="f-pill bg-sky-100 text-sky-800"><?= htmlspecialchars($package['location_flag']) ?></span><?php endif; ?>
          <?php render_package_rating($pdo, (int)$package['package_id']); ?>
        </div>
        <div class="text-sm text-stone-600"><?= htmlspecialchars(package_price_label($package['price'])) ?> · <?= htmlspecialchars($package['inclusions']) ?></div>
        <div class="text-sm text-stone-500"><?= htmlspecialchars($package['parlor_name']) ?> — <?= htmlspecialchars($package['location']) ?> · <?= htmlspecialchars($package['contact_number']) ?></div>
      </div>
    </div>
    <div class="flex shrink-0 flex-wrap items-center gap-2">
      <?php if (!empty($package['package_id'])): ?>
        <a href="<?= BASE_PATH ?>/client/package_details.php?package_id=<?= $package['package_id'] ?>" class="inline-flex items-center rounded-full border border-stone-300 px-3 py-2 text-xs font-semibold text-stone-600 transition hover:bg-stone-50">View Package</a>
      <?php endif; ?>
      <a href="<?= BASE_PATH ?>/client/contact.php?package_id=<?= $package['package_id'] ?>&director_id=<?= $package['parlor_id'] ?>" class="inline-flex shrink-0 rounded-full bg-funeral-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-funeral-500">Contact</a>
    </div>
  </div>
</article>
