<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['client']);
$pdo = pdo_connect();
$user = current_user();
$stmt = $pdo->prepare('SELECT i.*, fp.name AS parlor_name, f.id AS feedback_id, f.rating, f.comment
    FROM service_inquiries i JOIN funeral_parlors fp ON fp.id=i.parlor_id
    LEFT JOIN package_feedback f ON f.inquiry_id=i.id WHERE i.client_id=? ORDER BY i.created_at DESC,i.id DESC');
$stmt->execute([$user['id']]);
$experiences = $stmt->fetchAll();
$notice = $_SESSION['service_notice'] ?? '';
unset($_SESSION['service_notice']);
?>
<div class="mx-auto max-w-4xl space-y-5 py-4">
  <section class="f-card p-5 sm:p-7">
    <?php render_catalog_back_link(BASE_PATH . '/client/dashboard.php', 'Back to dashboard'); ?>
    <h1 class="text-3xl font-bold text-funeral-800">Inquired Services</h1>
    <p class="mt-2 text-sm text-stone-600">Track your inquiries here. Feedback becomes available only after the director confirms your inquiry or service. Each confirmed experience allows one questionnaire. After a service is marked availed, you can inquire about the same package again.</p>
  </section>
  <?php if ($notice): ?><p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800"><?= htmlspecialchars($notice) ?></p><?php endif; ?>
  <?php if (!$experiences): ?>
    <p class="f-card p-6 text-stone-600">No inquiries yet. Choose a package and use Send Inquiry on its contact page to get started.</p>
  <?php endif; ?>
  <?php foreach ($experiences as $experience): ?>
    <article id="inquiry-<?= (int)$experience['id'] ?>" class="f-card min-w-0 p-5 sm:p-6" style="overflow-wrap:anywhere">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
          <h2 class="text-xl font-semibold text-funeral-800"><?= htmlspecialchars($experience['package_title']) ?></h2>
          <p class="mt-1 text-sm text-stone-600"><?= htmlspecialchars($experience['parlor_name']) ?> &middot; <?= htmlspecialchars(package_price_label($experience['package_price'])) ?></p>
          <p class="mt-2 text-sm text-stone-600">Date inquired/availed: <?= htmlspecialchars($experience['created_at']) ?></p>
          <p class="text-sm text-stone-600">Status: <strong><?= htmlspecialchars(service_experience_status($experience)) ?></strong></p>
          <div class="mt-3"><?php render_package_rating($pdo, (int)$experience['package_id']); ?></div>
        </div>
        <div class="shrink-0">
          <?php if ($experience['status'] === 'availed' && service_feedback_eligible($experience)): ?>
            <a href="<?= BASE_PATH ?>/client/contact.php?package_id=<?= (int)$experience['package_id'] ?>&amp;director_id=<?= (int)$experience['parlor_id'] ?>" class="mb-3 inline-flex min-h-11 items-center rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">Inquire Again</a>
          <?php endif; ?>
          <?php if ($experience['feedback_id']): ?>
            <span class="f-pill bg-emerald-100 text-emerald-800">Feedback submitted &middot; <?= number_format((float)$experience['rating'],2) ?>/5</span>
          <?php elseif (service_feedback_eligible($experience)): ?>
            <a href="<?= BASE_PATH ?>/client/feedback.php?inquiry_id=<?= (int)$experience['id'] ?>" class="inline-flex min-h-11 items-center rounded-full bg-funeral-600 px-4 py-2 text-sm font-semibold text-white">Leave Feedback</a>
          <?php else: ?>
            <span class="text-sm text-stone-500"><?= $experience['status'] === 'cancelled' ? 'Cancelled - feedback unavailable' : 'Awaiting director confirmation' ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($experience['feedback_id'] && $experience['comment']): ?><p class="mt-4 whitespace-pre-line border-t border-stone-200 pt-3 text-sm text-stone-700">Your review: <?= htmlspecialchars($experience['comment']) ?></p><?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
