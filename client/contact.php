<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['client']);
$pdo = pdo_connect();
ensure_package_inclusion_images($pdo);
$inquiryError = '';
$packageId = (int)($_GET['package_id'] ?? 0);
$directorId = (int)($_GET['director_id'] ?? 0);

$parlorStmt = $pdo->prepare("SELECT fp.name, fp.location, fp.contact_number, u.full_name, u.email FROM funeral_parlors fp JOIN users u ON u.id = fp.director_id WHERE fp.id = ? AND fp.status = 'approved' AND u.status = 'approved'");
$parlorStmt->execute([$directorId]);
$parlor = $parlorStmt->fetch();
  // No fallback here on purpose. This used to drop back to "the first
  // approved parlor in the table" when the requested one did not resolve,
  // which showed the client a completely unrelated parlor's name, address
  // and phone number as though it were the one they asked for.
  if (!$parlor || !is_in_service_area($parlor['location'])) {
    http_response_code(404);
    echo '<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">Funeral parlor not found.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
  }

  $package = null;
  if ($packageId) {
    $packageStmt = $pdo->prepare('SELECT id, title, inclusions, price FROM packages WHERE id = ? AND parlor_id = ?');
    $packageStmt->execute([$packageId, $directorId]);
    $package = $packageStmt->fetch();
    if (!$package) {
      http_response_code(404);
      echo '<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">Package not found for this funeral parlor.</div>';
      include __DIR__ . '/../includes/footer.php';
      exit;
    }
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token(is_string($_POST['token'] ?? null) ? $_POST['token'] : null);
    try {
      if (!$package) throw new DomainException('Choose a package before sending an inquiry.');
      $message = is_string($_POST['inquiry_message'] ?? null) ? $_POST['inquiry_message'] : '';
      $inquiryId = send_package_inquiry($pdo, (int)current_user()['id'], (int)$package['id'], $message, (int)($_POST['after_inquiry_id'] ?? 0));
      $_SESSION['service_notice'] = 'Your inquiry was sent to the director. Feedback will be available after the director confirms it.';
      header('Location: ' . BASE_PATH . '/client/inquired_services.php#inquiry-' . $inquiryId);
      exit;
    } catch (DomainException | InvalidArgumentException $e) {
      http_response_code(422);
      $inquiryError = $e->getMessage();
    } catch (Throwable $e) {
      error_log('Package inquiry failed: ' . $e->getMessage());
      http_response_code(503);
      $inquiryError = 'Your inquiry could not be sent. Please try again; feedback is available only after a successful inquiry.';
    }
  }
  $previousInquiry = null;
  if ($package) {
    $previousStmt = $pdo->prepare('SELECT * FROM service_inquiries WHERE client_id=? AND package_id=? ORDER BY occurrence_no DESC LIMIT 1');
    $previousStmt->execute([current_user()['id'],$package['id']]);
    $previousInquiry = $previousStmt->fetch() ?: null;
  }
  $canSendInquiry = !$previousInquiry || ($previousInquiry['status'] === 'availed' && service_feedback_eligible($previousInquiry)) || $previousInquiry['status'] === 'cancelled';
?>
<div class="mx-auto min-w-0 max-w-3xl py-6 sm:py-10" style="overflow-wrap:anywhere">
  <div class="mb-6 text-center sm:text-left">
<?php render_catalog_back_link(BASE_PATH . '/client/dashboard.php', 'Back to search results'); ?>
    <p class="f-eyebrow mb-2">Contact a funeral parlor</p>
    <h2 class="text-3xl font-bold text-funeral-800">Everything you need to continue</h2>
    <p class="mt-2 text-stone-600">Share these details with the selected provider to discuss availability and arrangements — they'll take it from here.</p>
  </div>
  <?php if ($package): ?>
    <section class="f-card mt-5 p-6">
      <h3 class="text-xl font-semibold text-funeral-800">Send an inquiry</h3>
      <p class="mt-2 text-sm text-stone-600">Send your questions to the director. The director must confirm your inquiry before feedback is available. Opening this contact page does not record an inquiry.</p>
      <?php if ($inquiryError): ?><p role="alert" class="mt-3 text-sm text-red-700"><?= htmlspecialchars($inquiryError) ?></p><?php endif; ?>
      <?php if ($canSendInquiry): ?>
      <form method="post" class="mt-4 space-y-3">
        <input type="hidden" name="token" value="<?= htmlspecialchars(csrf_token()) ?>" />
        <input type="hidden" name="after_inquiry_id" value="<?= (int)($previousInquiry['id'] ?? 0) ?>" />
        <label for="inquiry-message" class="block text-sm font-medium text-stone-700">Your inquiry</label>
        <textarea id="inquiry-message" name="inquiry_message" rows="4" maxlength="4000" required class="w-full rounded-xl border border-stone-300 px-4 py-3"><?= htmlspecialchars(is_string($_POST['inquiry_message'] ?? null) ? $_POST['inquiry_message'] : '') ?></textarea>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-full bg-funeral-600 px-5 py-3 font-semibold text-white">Send Inquiry</button>
      </form>
      <?php else: ?>
        <p class="mt-3 text-sm text-stone-600">You already have an active inquiry for this package. You can send another inquiry after this service is marked availed.</p>
        <a href="<?= BASE_PATH ?>/client/inquired_services.php#inquiry-<?= (int)$previousInquiry['id'] ?>" class="mt-3 inline-flex min-h-11 items-center rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">View your inquiry</a>
      <?php endif; ?>
    </section>
  <?php endif; ?>
  <div class="grid gap-5 md:grid-cols-2">
    <section class="f-card p-6">
      <div class="mb-4 flex items-center gap-3">
        <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg></span>
        <h3 class="text-xl font-semibold text-funeral-800"><?= $package ? 'Selected package' : 'Package offerings' ?></h3>
      </div>
      <?php if ($package): ?>
        <div class="text-lg font-semibold text-stone-800"><?= htmlspecialchars($package['title']) ?></div>
        <div class="mt-1 text-2xl font-bold text-funeral-700"><?= htmlspecialchars(package_price_label($package['price'])) ?></div>
        <div class="mt-4 whitespace-pre-line text-sm leading-6 text-stone-600"><?= htmlspecialchars($package['inclusions'] ?: 'The provider can customize this service with you.') ?></div>

        <a href="<?= BASE_PATH ?>/client/package_details.php?package_id=<?= (int)$package['id'] ?>&amp;from=contact" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-funeral-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-funeral-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-funeral-300 sm:w-auto">
          View Full Package Inclusions &amp; Details
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      <?php else: ?>
        <p class="text-sm text-stone-600">Choose a package from this parlor's offerings, then contact the provider about availability and arrangements.</p>
      <?php endif; ?>
    </section>
    <section class="f-card p-6">
      <div class="mb-4 flex items-center gap-3">
        <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/></svg></span>
        <h3 class="text-xl font-semibold text-funeral-800">Provider contact</h3>
      </div>
      <?php if ($parlor): ?>
        <div class="text-lg font-semibold text-stone-800"><?= htmlspecialchars($parlor['name']) ?></div>
        <div class="mt-1 text-sm text-stone-600">Director: <?= htmlspecialchars($parlor['full_name']) ?></div>
        <div class="mt-1 text-sm text-stone-600">Location: <?= htmlspecialchars($parlor['location']) ?></div>
        <div class="mt-4 space-y-2 border-t border-stone-200 pt-3 text-sm text-stone-700">
          <div>Phone: <a class="font-semibold text-funeral-700 hover:text-funeral-600" href="tel:<?= htmlspecialchars($parlor['contact_number']) ?>"><?= htmlspecialchars($parlor['contact_number']) ?></a></div>
          <div>Email: <a class="font-semibold text-funeral-700 hover:text-funeral-600" href="mailto:<?= htmlspecialchars($parlor['email']) ?>"><?= htmlspecialchars($parlor['email']) ?></a></div>
        </div>
      <?php else: ?>
        <p class="text-sm text-stone-600">There are no approved funeral parlors available yet.</p>
      <?php endif; ?>
    </section>
  </div>
  <a href="<?= BASE_PATH ?>/client/dashboard.php" class="mt-6 inline-flex items-center gap-1 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50">
    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
    Back to search results
  </a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
