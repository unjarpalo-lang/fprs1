<?php
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['director']);
$pdo = pdo_connect();
ensure_director_scope_columns($pdo);
$user = current_user();
$message = '';
$accountError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') require_csrf_token($_POST['token'] ?? null);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_account'])) {
    http_response_code(400);
    exit('Invalid account action.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {
    $fullName = trim(request_text($_POST, 'full_name'));
    $phone = trim(request_text($_POST, 'phone'));
    if ($fullName === '' || text_length($fullName) > 200 || text_length($phone) > 50) {
        $accountError = 'Full name is required (maximum 200 characters); phone must be at most 50 characters.';
        http_response_code(422);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET full_name=?, phone=? WHERE id=? AND role=?');
        $stmt->execute([$fullName, $phone, $user['id'], 'director']);
        $user = current_user();
        $message = 'Account updated.';
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="mx-auto max-w-xl py-6 sm:py-10">
  <section class="f-card p-6 sm:p-8">
    <span class="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-funeral-100 text-funeral-700">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
    </span>
<?php render_catalog_back_link(BASE_PATH . '/director/funeral_parlors.php', 'Back to funeral parlors'); ?>
    <p class="f-eyebrow mb-1">Director portal</p>
    <h2 class="text-2xl font-semibold text-funeral-800">Personal Account</h2>
    <?php if ($message): ?><div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($accountError): ?><p role="alert" id="account-error" class="mt-4 text-red-700"><?= htmlspecialchars($accountError) ?></p><?php endif; ?>
    <form method="post" class="mt-5 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="update_account" value="1" />
      <div>
        <label class="mb-1 block text-sm font-medium text-stone-700" for="field-director-account-php-1">Full name</label>
        <input id="field-director-account-php-1" name="full_name" maxlength="200" <?= $accountError ? 'aria-invalid="true" aria-describedby="account-error"' : '' ?> value="<?= htmlspecialchars(isset($_POST['update_account']) ? request_text($_POST, 'full_name') : $user['full_name']) ?>" required class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-stone-700" for="field-director-account-php-2">Personal contact number</label>
        <input id="field-director-account-php-2" name="phone" maxlength="50" value="<?= htmlspecialchars(isset($_POST['update_account']) ? request_text($_POST, 'phone') : ($user['phone'] ?? '')) ?>" class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
      </div>
      <div class="flex gap-3 pt-1">
        <button class="rounded-full bg-funeral-600 px-5 py-2.5 font-semibold text-white shadow-sm transition hover:bg-funeral-500">Save Account</button>
        <a href="<?= BASE_PATH ?>/director/funeral_parlors.php" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-stone-50">Cancel</a>
      </div>
    </form>
  </section>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
