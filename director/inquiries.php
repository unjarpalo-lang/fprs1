<?php
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['director']);
$pdo = pdo_connect();
$user = current_user();
$selectedParlor = selected_director_parlor($pdo, (int)$user['id']);
if (!$selectedParlor) {
    header('Location: ' . BASE_PATH . '/director/funeral_parlors.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token(is_string($_POST['token'] ?? null) ? $_POST['token'] : null);
    try {
        manage_service_experience($pdo, (int)$user['id'], (int)($_POST['inquiry_id'] ?? 0), is_string($_POST['action'] ?? null) ? $_POST['action'] : '', (int)$selectedParlor['id']);
        header('Location: ' . BASE_PATH . '/director/inquiries.php');
        exit;
    } catch (DomainException | LogicException $e) {
        http_response_code(409);
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Inquiry status update failed: ' . $e->getMessage());
        http_response_code(503);
        $error = 'The inquiry could not be updated. Please try again.';
    }
}
$stmt = $pdo->prepare('SELECT i.*, fp.name AS parlor_name, u.full_name AS client_name, f.rating
    FROM service_inquiries i JOIN funeral_parlors fp ON fp.id=i.parlor_id JOIN users u ON u.id=i.client_id
    LEFT JOIN package_feedback f ON f.inquiry_id=i.id WHERE fp.director_id=? AND i.parlor_id=? ORDER BY i.created_at DESC,i.id DESC');
$stmt->execute([$user['id'], $selectedParlor['id']]);
$inquiries = $stmt->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="mx-auto max-w-4xl space-y-5 py-4">
  <section class="f-card p-5 sm:p-7">
    <?php render_catalog_back_link(BASE_PATH . '/director/dashboard.php', 'Back to director dashboard'); ?>
    <h1 class="text-3xl font-bold text-funeral-800">Service Inquiries</h1>
    <p class="mt-2 text-sm text-stone-600">Confirm that a client genuinely inquired before they can leave feedback. Mark services availed when appropriate. Clients who avail the same package again send another inquiry for you to confirm.</p>
  </section>
  <?php if ($error): ?><p role="alert" class="rounded-xl bg-red-50 p-4 text-red-800"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if (!$inquiries): ?><p class="f-card p-6 text-stone-600">No recorded inquiries yet.</p><?php endif; ?>
  <?php foreach ($inquiries as $inquiry): ?>
    <article class="f-card min-w-0 p-5 sm:p-6" style="overflow-wrap:anywhere">
      <h2 class="text-xl font-semibold text-funeral-800"><?= htmlspecialchars($inquiry['package_title']) ?></h2>
      <p class="mt-1 text-sm text-stone-600"><?= htmlspecialchars($inquiry['parlor_name']) ?> &middot; <?= htmlspecialchars($inquiry['client_name']) ?></p>
      <p class="mt-1 text-sm text-stone-600"><?= htmlspecialchars($inquiry['created_at']) ?> &middot; Experience #<?= (int)$inquiry['occurrence_no'] ?> &middot; <?= htmlspecialchars(service_experience_status($inquiry)) ?></p>
      <p class="mt-3 whitespace-pre-line text-sm text-stone-700"><?= htmlspecialchars($inquiry['inquiry_message']) ?></p>
      <form method="post" class="mt-4 flex flex-wrap gap-2">
        <input type="hidden" name="token" value="<?= htmlspecialchars(csrf_token()) ?>" />
        <input type="hidden" name="inquiry_id" value="<?= (int)$inquiry['id'] ?>" />
        <?php if (in_array($inquiry['status'], ['inquired','availed'], true) && !service_feedback_eligible($inquiry)): ?>
          <button name="action" value="confirm" onclick="return confirm('Confirm that this client genuinely inquired about this service? This will unlock their feedback questionnaire.');" class="min-h-11 rounded-full bg-funeral-600 px-4 py-2 text-sm font-semibold text-white">Confirm Inquiry</button>
        <?php elseif ($inquiry['status'] === 'confirmed'): ?>
          <button name="action" value="avail" class="min-h-11 rounded-full bg-funeral-600 px-4 py-2 text-sm font-semibold text-white">Mark Availed</button>
        <?php endif; ?>
        <?php if (in_array($inquiry['status'], ['inquired','confirmed'], true)): ?>
          <button name="action" value="cancel" class="min-h-11 rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">Cancel Inquiry</button>
        <?php endif; ?>
      </form>
    </article>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
