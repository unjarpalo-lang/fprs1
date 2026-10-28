<?php
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['client']);
$pdo = pdo_connect();
$user = current_user();
$message = '';
$errors = [];
$fullName = $user['full_name'];
$phone = $user['phone'] ?? '';
$firebaseLinked = !empty($user['firebase_uid']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token($_POST['token'] ?? null);
    $action = request_text($_POST, 'action');
    if ($action === 'profile') {
        $fullName = trim(request_text($_POST, 'full_name'));
        $phone = trim(request_text($_POST, 'phone'));
        if ($fullName === '' || text_length($fullName) > 200) $errors['full_name'] = 'Enter your full name, up to 200 characters.';
        if (text_length($phone) > 50) $errors['phone'] = 'Phone must not exceed 50 characters.';
        if (!$errors) {
            $pdo->prepare("UPDATE users SET full_name=?,phone=? WHERE id=? AND role='client'")->execute([$fullName,$phone,$user['id']]);
            $message = 'Profile updated.';
        }
    } elseif ($action === 'password' && !$firebaseLinked) {
        $current = request_text($_POST, 'current_password');
        $password = request_text($_POST, 'new_password');
        $confirm = request_text($_POST, 'confirm_password');
        if (!password_verify($current, $user['password'])) $errors['password'] = 'Current password is incorrect.';
        elseif (strlen($password) < 8 || strlen($password) > 72) $errors['password'] = 'New password must be 8–72 bytes long.';
        elseif ($password !== $confirm) $errors['password'] = 'New passwords do not match.';
        elseif (password_verify($password, $user['password'])) $errors['password'] = 'Choose a different new password.';
        if (!$errors) {
            // Only this signed-in client; compare old hash to prevent concurrent overwrites.
            $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='client' AND password=?");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT),$user['id'],$user['password']]);
            if ($stmt->rowCount() !== 1) $errors['password'] = 'Your account changed. Reload and try again.';
            else {
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $message = 'Password changed.';
            }
        }
    } else {
        $errors['request'] = 'This account action is not available. Use the form shown below.';
    }
    if ($errors) http_response_code(422);
}
include __DIR__ . '/../includes/header.php';
?>
<div class="mx-auto max-w-xl space-y-5 py-6">
  <section class="f-card p-6 sm:p-8">
    <?php render_catalog_back_link(BASE_PATH . '/client/dashboard.php', 'Back to dashboard'); ?>
    <p class="f-eyebrow">Client account</p><h1 class="text-2xl font-semibold text-funeral-800">Manage account</h1>
    <?php if ($message): ?><p role="status" class="mt-4 rounded-xl bg-emerald-50 p-3 text-emerald-800"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($errors): ?><div role="alert" id="account-errors" class="mt-4 rounded-xl bg-red-50 p-3 text-red-700"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>
    <form method="post" class="mt-5 space-y-4">
      <?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <div><label for="client-name" class="block text-sm font-medium">Full name</label><input id="client-name" name="full_name" maxlength="200" required value="<?= htmlspecialchars($fullName, ENT_QUOTES) ?>" <?= isset($errors['full_name']) ? 'aria-invalid="true" aria-describedby="account-errors"' : '' ?> class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3"></div>
      <div><label for="client-phone" class="block text-sm font-medium">Phone</label><input id="client-phone" name="phone" maxlength="50" value="<?= htmlspecialchars($phone, ENT_QUOTES) ?>" <?= isset($errors['phone']) ? 'aria-invalid="true" aria-describedby="account-errors"' : '' ?> class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3"></div>
      <div><label for="client-email" class="block text-sm font-medium">Email</label><input id="client-email" type="email" readonly value="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>" aria-describedby="email-help" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3"><p id="email-help" class="mt-1 text-sm text-stone-500">Your sign-in email is read-only.</p></div>
      <button class="rounded-full bg-funeral-600 px-5 py-3 font-semibold text-white">Save profile</button>
    </form>
  </section>
  <section class="f-card p-6 sm:p-8">
    <h2 class="text-xl font-semibold text-funeral-800">Change password</h2>
    <p id="password-help" class="mt-2 text-sm text-stone-600"><?= $firebaseLinked ? 'This account is linked to Firebase. Re-enter your current Firebase password below to update that password. This does not change a separate local PHP password if you previously had one.' : 'Enter your current password and a new password of 8–72 bytes. Password fields are cleared after submission.' ?></p>
    <form method="post" <?= $firebaseLinked ? 'id="firebase-password-form"' : '' ?> class="mt-4 space-y-4" aria-describedby="password-help" <?= $firebaseLinked ? 'data-uid="' . htmlspecialchars($user['firebase_uid'], ENT_QUOTES) . '" data-email="' . htmlspecialchars($user['email'], ENT_QUOTES) . '"' : '' ?>>
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div><label for="current-password" class="block text-sm font-medium">Current <?= $firebaseLinked ? 'Firebase ' : '' ?>password</label><input id="current-password" name="current_password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3"></div>
      <div><label for="new-password" class="block text-sm font-medium">New password</label><input id="new-password" name="new_password" type="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3"></div>
      <div><label for="confirm-password" class="block text-sm font-medium">Confirm new password</label><input id="confirm-password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3"></div>
      <button <?= $firebaseLinked ? 'type="button" id="firebase-password-submit" disabled' : '' ?> class="rounded-full bg-funeral-600 px-5 py-3 font-semibold text-white">Change password</button>
    </form>
    <?php if ($firebaseLinked): ?><p id="firebase-password-status" role="status" aria-live="polite" class="mt-3 text-sm text-stone-600">Connecting to password service…</p><noscript><p>JavaScript is required to update your Firebase password.</p></noscript><?php endif; ?>
  </section>
</div>
<?php if ($firebaseLinked): ?>
<script type="module">
import { attachPasswordChange } from '<?= BASE_PATH ?>/assets/js/client-account.js';
attachPasswordChange();
</script>
<script>window.setTimeout(function(){var b=document.getElementById('firebase-password-submit');if(b && b.disabled && !b.dataset.busy)document.getElementById('firebase-password-status').textContent='Password service could not load. Check your connection and reload to try again.';},15000);</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
