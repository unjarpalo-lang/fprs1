<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$error = '';
$declined = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = request_text($_POST, 'email');
    $password = request_text($_POST, 'password');
    $pdo = pdo_connect();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] === 'approved') {
            // Regenerate the session ID on every successful login so a session
            // ID an attacker fixed/obtained before authentication can't be
            // reused post-login (session fixation).
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            header('Location: ' . BASE_PATH . '/');
            exit;
        } elseif ($user['status'] === 'rejected') {
            $declined = true;
            // Password was just verified above, so this is a legitimate
            // recovery intent for this exact account — stash it so
            // reregister.php can delete precisely this row without ever
            // exposing its id in a URL or re-collecting the password.
            $_SESSION['declined_reregister_id'] = (int)$user['id'];
        } else {
            $error = 'Account not approved yet. An administrator must review your registration and verification document before you can sign in.';
        }
    } else {
        $error = 'Invalid credentials.';
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="mx-auto max-w-4xl py-6 sm:py-10">
  <div class="overflow-hidden rounded-[28px] border border-stone-200 shadow-lift sm:grid sm:grid-cols-[1fr_1.1fr]">
    <div class="hidden flex-col justify-between bg-gradient-to-br from-funeral-800 via-funeral-700 to-funeral-900 p-8 text-stone-100 sm:flex">
      <div>
        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-funeral-400/90 text-funeral-900">
          <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c1.2 2.1 2 3.6 2 5a2 2 0 1 1-4 0c0-1.4.8-2.9 2-5Z" /><path d="M12 9v13" /><path d="M6 22c0-3.3 2.7-6 6-6s6 2.7 6 6" /></svg>
        </span>
        <h2 class="mt-6 font-serif text-2xl font-semibold leading-snug">Take your time. We'll be here.</h2>
        <p class="mt-3 text-sm leading-relaxed text-stone-300">Sign back in to pick up right where you left off — whether that's comparing packages, coordinating a service, or reviewing a request.</p>
      </div>
      <p class="text-xs uppercase tracking-[0.2em] text-funeral-300/80">Compassion • Respect • Care</p>
    </div>

    <div class="bg-white/90 p-6 sm:p-10">
      <?php if ($declined): ?>
        <div class="flex flex-col items-center py-2 text-center">
          <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
          </span>
          <p class="f-eyebrow mb-2">Account declined</p>
          <h2 class="mb-2 text-2xl font-bold text-funeral-800">This account wasn't approved</h2>
          <p class="mx-auto max-w-sm text-sm text-stone-600">An admin reviewed this registration and declined it, so you can't sign in with it. You're welcome to submit a fresh registration, or head back to the main page.</p>
          <div class="mt-6 flex w-full flex-col gap-2 sm:flex-row sm:justify-center">
            <a href="<?= BASE_PATH ?>/reregister.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-funeral-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-funeral-500/20 transition hover:bg-funeral-400">Register Again</a>
            <a href="<?= BASE_PATH ?>/" class="inline-flex items-center justify-center gap-2 rounded-full border border-stone-300 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:bg-stone-50">Go Back to Main Page</a>
          </div>
        </div>
      <?php else: ?>
        <p class="f-eyebrow mb-2">Welcome back</p>
        <h2 class="mb-6 text-3xl font-bold text-funeral-800">Login</h2>

        <?php if ($error): ?>
          <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700" for="field-login-php-1">Email</label>
            <input id="field-login-php-1" name="email" type="email" required value="<?= htmlspecialchars(request_text($_POST, 'email')) ?>" class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700" for="field-login-php-2">Password</label>
            <div class="login-password-field">
              <input id="field-login-php-2" name="password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
              <button id="login-password-toggle" type="button" class="login-password-toggle" aria-label="Show password" aria-controls="field-login-php-2" aria-pressed="false">
                <svg data-eye-open width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg data-eye-closed hidden width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m3 3 18 18M10.6 5.1A11 11 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3 4M6.5 6.5A20 20 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.5-1.5M10 10a3 3 0 0 0 4 4"/></svg>
              </button>
            </div>
          </div>
          <button type="submit" class="w-full rounded-full bg-funeral-500 px-5 py-3 font-semibold text-white shadow-lg shadow-funeral-500/20 transition hover:bg-funeral-400">Login</button>
        </form>

        <p class="mt-6 text-center text-sm text-stone-600">New here? <a href="<?= BASE_PATH ?>/register.php" class="font-semibold text-funeral-700 hover:text-funeral-600">Create an account</a></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
(() => {
  const button = document.getElementById('login-password-toggle');
  const input = document.getElementById('field-login-php-2');
  if (!button || !input) return;
  button.addEventListener('click', () => {
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    button.setAttribute('aria-pressed', String(visible));
    button.querySelector('[data-eye-open]').hidden = visible;
    button.querySelector('[data-eye-closed]').hidden = !visible;
  });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
