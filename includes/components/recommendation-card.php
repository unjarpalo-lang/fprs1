<?php
/**
 * Isolated component — not included by any existing page.
 *
 * Renders one RecommenderEngine result as a card. Reuses the .f-card-flat /
 * funeral-* tokens already defined in bootstrap/css/site.css and the Tailwind
 * config in includes/header.php, so it matches the rest of the app without
 * pulling in any extra CSS.
 *
 * Usage (wherever you decide to display results):
 *   foreach ($results as $result) {
 *       include __DIR__ . '/../includes/components/recommendation-card.php';
 *   }
 *
 * Expects $result shaped like one row returned by RecommenderEngine::recommend():
 *   ['name', 'location', 'price', 'distance', 'match_percentage', ...]
 */
$name = $result['name'] ?? 'Untitled listing';
$location = $result['location'] ?? '';
$price = (float)($result['price'] ?? 0);
$distance = isset($result['distance']) ? (float)$result['distance'] : null;
$match = (float)($result['match_percentage'] ?? 0);
?>
<article class="f-card-flat flex flex-col gap-4 p-5 shadow-sm">
  <div class="flex items-start justify-between gap-3">
    <div>
      <h3 class="text-lg font-semibold text-funeral-800"><?= htmlspecialchars($name) ?></h3>
      <?php if ($location !== ''): ?>
        <p class="text-sm text-stone-500"><?= htmlspecialchars($location) ?></p>
      <?php endif; ?>
    </div>
    <div class="flex flex-col items-end shrink-0">
      <span class="text-2xl font-bold text-funeral-700"><?= htmlspecialchars(number_format($match, 0)) ?>%</span>
      <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-400">Match</span>
    </div>
  </div>

  <div class="h-2 w-full overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuenow="<?= htmlspecialchars((string) round($match)) ?>" aria-valuemin="0" aria-valuemax="100">
    <div class="h-full rounded-full bg-gradient-to-r from-funeral-400 to-funeral-600" style="width: <?= max(0, min(100, $match)) ?>%"></div>
  </div>

  <div class="grid grid-cols-2 gap-3 text-sm">
    <div class="rounded-xl border border-stone-200 bg-stone-50 p-3">
      <div class="text-[11px] font-semibold uppercase tracking-[0.12em] text-stone-400">Price range</div>
      <div class="mt-1 font-medium text-stone-700"><?= htmlspecialchars(package_price_label($price)) ?></div>
    </div>
    <div class="rounded-xl border border-stone-200 bg-stone-50 p-3">
      <div class="text-[11px] font-semibold uppercase tracking-[0.12em] text-stone-400">Location accessibility</div>
      <div class="mt-1 font-medium text-stone-700"><?= $distance !== null ? htmlspecialchars(number_format($distance, 1)) . ' km away' : 'Distance N/A' ?></div>
    </div>
  </div>
</article>
