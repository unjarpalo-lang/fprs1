<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['client']);
$pdo = pdo_connect();
ensure_package_inclusion_images($pdo);

$viewer = current_user();

$packageId = (int)($_GET['package_id'] ?? 0);
// Director name and email are included because contact.php already shows
// both to clients; the director's phone number is not shown anywhere on the
// client side, so it is deliberately left out of the printout too.
$stmt = $pdo->prepare(
    "SELECT p.*, fp.id AS parlor_id, fp.name AS parlor_name, fp.location, fp.contact_number,
            u.full_name AS director_name, u.email AS director_email
     FROM packages p
     JOIN funeral_parlors fp ON fp.id = p.parlor_id
     JOIN users u ON u.id = fp.director_id
     WHERE p.id = ? AND fp.status = 'approved' AND u.status = 'approved'"
);
$stmt->execute([$packageId]);
$package = $stmt->fetch();

if (!$package || !is_in_service_area($package['location'])) {
    http_response_code(404);
    $reason = $packageId === 0
        ? 'No package was selected.'
        : 'That package is no longer available, or the funeral parlor offering it is not approved.';
    ?>
    <div class="mx-auto max-w-lg py-10">
      <div class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center">
        <p class="font-semibold text-red-800">Package details unavailable</p>
        <p class="mt-1 text-sm text-red-700"><?= htmlspecialchars($reason) ?></p>
        <a href="<?= BASE_PATH ?>/client/dashboard.php" class="mt-4 inline-flex items-center rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50">Back to search results</a>
      </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Full inclusion list in its saved order, for the printed copy.
$incStmt = $pdo->prepare(
    'SELECT service_name, service_detail, description
     FROM package_inclusions WHERE package_id = ? ORDER BY sort_order ASC, id ASC'
);
$incStmt->execute([(int)$package['id']]);
$printInclusions = $incStmt->fetchAll();
// The client's own saved search preferences, if they have run a search.
$prefStmt = $pdo->prepare('SELECT budget, religion, location FROM client_preferences WHERE client_id = ?');
$prefStmt->execute([(int)$viewer['id']]);
$prefs = $prefStmt->fetch() ?: null;
$hasPrefs = $prefs && ($prefs['budget'] !== null || !empty($prefs['religion']) || !empty($prefs['location']));
?>
<style>
  .print-only { display: none; }
  @media print {
    @page { size: A4; margin: 14mm; }
    /* Site chrome has no place on paper. */
    header, .site-footer, .sky-backdrop, .no-print { display: none !important; }
    html, body { background: #fff !important; color: #000 !important; }
    body { font-size: 11pt; }
    .site-main { max-width: none !important; margin: 0 !important; padding: 0 !important; }
    .print-only { display: block !important; }
    /* Flatten the on-screen card styling to plain black and white. */
    .f-card, .f-card-flat { box-shadow: none !important; border: 1px solid #999 !important; background: #fff !important; }
    .f-card { overflow: visible !important; }
    [class*="text-funeral"], [class*="text-stone"] { color: #000 !important; }
    .print-block { break-inside: avoid; page-break-inside: avoid; }
    img { max-width: 100% !important; }
    a { text-decoration: none !important; color: #000 !important; }
  }
</style>
<div class="mx-auto max-w-3xl py-6 sm:py-10">
  <!-- Printed sheet header: only appears on paper. -->
  <div class="print-only print-block" style="margin-bottom:12pt; border-bottom:1px solid #000; padding-bottom:6pt;">
    <div style="font-size:15pt; font-weight:bold;">Funeral Parlor Recommendation System (FPRS)</div>
    <div style="font-size:10pt;">Package details &mdash; printed <?= htmlspecialchars(date('j F Y, g:i a')) ?></div>
  </div>

  <div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
    <?php
      // This page is reached both from contact.php and straight from the
      // search results ("View Package"). Only offer to go "back" to the
      // contact page when that is actually where the client came from —
      // otherwise send them back to their search.
      $cameFromContact = ($_GET['from'] ?? '') === 'contact';
      $backHref = $cameFromContact
          ? BASE_PATH . '/client/contact.php?package_id=' . (int)$package['id'] . '&director_id=' . (int)$package['parlor_id']
          : BASE_PATH . '/client/dashboard.php';
      $backLabel = $cameFromContact ? 'Back to contact details' : 'Back to search results';
    ?>
    <a href="<?= htmlspecialchars($backHref) ?>" class="inline-flex items-center gap-1 rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300">
      <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
      <?= htmlspecialchars($backLabel) ?>
    </a>
    <button type="button" onclick="window.print()" class="inline-flex items-center gap-1 rounded-lg bg-funeral-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-funeral-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-funeral-300">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
      Print Package Details
    </button>
  </div>

  <div class="f-card overflow-hidden p-0">
    <?php if (!empty($package['image_path'])): ?>
      <img src="<?= htmlspecialchars(BASE_PATH . '/' . ltrim($package['image_path'], '/')) ?>" alt="<?= htmlspecialchars($package['title']) ?>" class="h-56 w-full object-cover sm:h-72" />
    <?php else: ?>
      <div class="flex h-40 w-full items-center justify-center bg-stone-100 text-stone-300">
        <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
      </div>
    <?php endif; ?>

    <div class="p-6 sm:p-8">
      <p class="f-eyebrow mb-1"><?= htmlspecialchars($package['parlor_name']) ?></p>
      <h1 class="text-2xl font-bold text-funeral-800 sm:text-3xl"><?= htmlspecialchars($package['title']) ?></h1>
      <div class="mt-2"><?php render_package_rating($pdo, (int)$package['id']); ?></div>
      <a href="<?= BASE_PATH ?>/client/contact.php?package_id=<?= (int)$package['id'] ?>&amp;director_id=<?= (int)$package['parlor_id'] ?>" class="no-print mt-4 inline-flex min-h-11 items-center rounded-full bg-funeral-600 px-4 py-2 text-sm font-semibold text-white">Contact / Inquire</a>
      <div class="mt-1 text-2xl font-bold text-funeral-700"><?= htmlspecialchars(package_price_label($package['price'])) ?></div>
      <p class="mt-1 text-sm text-stone-500"><?= htmlspecialchars($package['location']) ?> · <?= htmlspecialchars($package['contact_number']) ?></p>

      <div class="f-divider my-6"></div>


      <h2 class="mb-4 text-lg font-semibold text-funeral-800">What's included</h2>
      <div class="no-print">
        <?php render_package_inclusions($pdo, (int)$package['id'], false); ?>
      </div>

      <!-- Plain-text inclusion list for the printed copy, in saved order. -->
      <div class="print-only print-block">
        <?php if (!$printInclusions): ?>
          <p style="font-size:10pt;">No itemised inclusions have been listed for this package.</p>
        <?php else: ?>
          <table style="width:100%; border-collapse:collapse; font-size:10pt;">
            <thead>
              <tr>
                <th style="text-align:left; border-bottom:1px solid #000; padding:4pt 6pt 4pt 0;">Service</th>
                <th style="text-align:left; border-bottom:1px solid #000; padding:4pt 6pt;">Detail</th>
                <th style="text-align:left; border-bottom:1px solid #000; padding:4pt 0 4pt 6pt;">Description</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($printInclusions as $inc): ?>
                <tr>
                  <td style="vertical-align:top; border-bottom:1px solid #ccc; padding:4pt 6pt 4pt 0;"><?= htmlspecialchars($inc['service_name']) ?></td>
                  <td style="vertical-align:top; border-bottom:1px solid #ccc; padding:4pt 6pt;"><?= htmlspecialchars($inc['service_detail']) ?></td>
                  <td style="vertical-align:top; border-bottom:1px solid #ccc; padding:4pt 0 4pt 6pt;"><?= htmlspecialchars($inc['description'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Provider and client-preference details, printed copy only. -->
      <div class="print-only print-block" style="margin-top:12pt; padding-top:8pt; border-top:1px solid #000;">
        <div style="font-size:11pt; font-weight:bold; margin-bottom:4pt;">Funeral parlor</div>
        <div style="font-size:10pt;"><?= htmlspecialchars($package['parlor_name']) ?></div>
        <div style="font-size:10pt;"><?= htmlspecialchars($package['location']) ?></div>
        <div style="font-size:10pt;">Contact number: <?= htmlspecialchars($package['contact_number']) ?></div>
        <div style="font-size:10pt; margin-top:4pt;">Funeral director: <?= htmlspecialchars($package['director_name']) ?> (<?= htmlspecialchars($package['director_email']) ?>)</div>
      </div>

      <?php if ($hasPrefs): ?>
        <div class="print-only print-block" style="margin-top:10pt;">
          <div style="font-size:11pt; font-weight:bold; margin-bottom:4pt;">Your search preferences</div>
          <?php if ($prefs['budget'] !== null): ?>
            <div style="font-size:10pt;">Budget: &#8369;<?= htmlspecialchars(number_format((float)$prefs['budget'], 2)) ?></div>
          <?php endif; ?>
          <?php if (!empty($prefs['religion'])): ?>
            <div style="font-size:10pt;">Religion: <?= htmlspecialchars($prefs['religion']) ?></div>
          <?php endif; ?>
          <?php if (!empty($prefs['location'])): ?>
            <div style="font-size:10pt;">Location: <?= htmlspecialchars($prefs['location']) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="print-only print-block" style="margin-top:12pt; padding-top:6pt; border-top:1px solid #000; font-size:9pt;">
        This printout is for reference only. Final arrangements, prices, and availability
        must be confirmed directly with the funeral parlor.
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
