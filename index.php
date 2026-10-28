<?php include __DIR__ . '/includes/header.php'; ?>
<div class="space-y-8 py-6">
  <?php if (is_logged_in()): $navUser = current_user(); if ($navUser): ?>
    <section class="flex flex-col items-center gap-3 rounded-[24px] border border-funeral-200 bg-gradient-to-br from-funeral-50 to-white p-5 text-center shadow-soft sm:flex-row sm:justify-between sm:text-left">
      <div>
        <p class="f-eyebrow mb-1">Welcome back</p>
        <h2 class="text-lg font-semibold text-funeral-800">Continue as <?= htmlspecialchars($navUser['full_name']) ?></h2>
      </div>
      <a href="<?= BASE_PATH . '/' . htmlspecialchars($navUser['role']) . '/dashboard.php' ?>" class="inline-flex shrink-0 items-center gap-2 rounded-full bg-funeral-600 px-6 py-3 font-semibold text-white shadow-lift transition hover:bg-funeral-500">Go to <?= htmlspecialchars(ucfirst($navUser['role'])) ?> Dashboard <span aria-hidden="true">→</span></a>
    </section>
  <?php endif; endif; ?>

  <section class="overflow-hidden rounded-[28px] border border-stone-200/60 bg-gradient-to-br from-funeral-50 via-white to-funeral-100 p-6 shadow-soft sm:p-8 lg:p-10">
    <div class="grid items-center gap-8 lg:grid-cols-[1fr_0.9fr]">
      <div>
        <p class="f-eyebrow mb-3">Honoring every life</p>
        <h1 class="text-4xl font-bold leading-tight text-funeral-800 sm:text-5xl">Compassionate funeral care, beautifully coordinated.</h1>
        <p class="mt-4 max-w-xl text-lg leading-relaxed text-stone-600">Manage memorial arrangements with dignity, clarity, and calm support for families and staff alike. Every step is unhurried, transparent, and easy to follow.</p>

        <p class="f-quote mt-6 max-w-lg text-base">"We built FPRS so that during the hardest days, finding the right care never has to feel complicated."</p>

        <div class="mt-6 flex flex-wrap gap-3 text-sm text-stone-600">
          <span class="rounded-full bg-funeral-100 px-3 py-1.5">🕊️ Compassion</span>
          <span class="rounded-full bg-funeral-100 px-3 py-1.5">🔎 Clarity</span>
          <span class="rounded-full bg-funeral-100 px-3 py-1.5">🤝 Support</span>
        </div>
      </div>

      <div class="flex items-center justify-center">
        <div class="relative flex h-64 w-full max-w-[320px] items-center justify-center overflow-hidden rounded-[24px] bg-gradient-to-tr from-funeral-100 to-white shadow-lift">
          <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-funeral-200/40 blur-2xl"></div>
          <div class="pointer-events-none absolute -bottom-10 -left-8 h-32 w-32 rounded-full bg-funeral-300/30 blur-2xl"></div>
          <div class="relative z-10 flex w-full flex-col items-center gap-3 p-6 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-white/80 text-funeral-700 shadow-inner">
              <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c1.2 2.1 2 3.6 2 5a2 2 0 1 1-4 0c0-1.4.8-2.9 2-5Z" /><path d="M12 9v13" /><path d="M6 22c0-3.3 2.7-6 6-6s6 2.7 6 6" /></svg>
            </div>
            <div class="font-serif text-lg font-semibold text-funeral-800">A gentle place to begin</div>
            <div class="text-sm text-stone-600">Here to help, every step of the way</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="f-card p-6 sm:p-8">
    <p class="f-eyebrow mb-2 text-center sm:text-left">How it works</p>
    <h2 class="mb-6 text-center text-2xl font-semibold text-funeral-800 sm:text-left">Three unhurried steps</h2>
    <div class="grid gap-6 sm:grid-cols-3">
      <div class="f-step" data-step="1">
        <h3 class="mb-1 font-semibold text-funeral-800">Create an account</h3>
        <p class="text-sm leading-6 text-stone-600">Register as a family seeking care or a director offering services. Your details are reviewed with care before approval.</p>
      </div>
      <div class="f-step" data-step="2">
        <h3 class="mb-1 font-semibold text-funeral-800">Explore with clarity</h3>
        <p class="text-sm leading-6 text-stone-600">Browse approved funeral parlors, compare packages, and filter by budget, location, or preference — no pressure, no guesswork.</p>
      </div>
      <div class="f-step" data-step="3">
        <h3 class="mb-1 font-semibold text-funeral-800">Connect and arrange</h3>
        <p class="text-sm leading-6 text-stone-600">Reach out directly to a director, discuss arrangements, and let them guide the rest with compassion.</p>
      </div>
    </div>
  </section>

  <div class="grid gap-5 md:grid-cols-3">
    <div class="f-card-flat p-6 shadow-sm">
      <div class="f-icon-tile mb-3">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.6-9.5-9C1 8.5 2.8 5 6.2 5c1.9 0 3.4 1 4.8 2.8C12.4 6 13.9 5 15.8 5c3.4 0 5.2 3.5 3.7 7-2.5 4.4-9.5 9-9.5 9Z"/></svg>
      </div>
      <p class="mb-1 f-eyebrow">Families</p>
      <h3 class="mb-2 text-xl font-semibold text-funeral-800">Meaningful arrangements</h3>
      <p class="text-sm leading-6 text-stone-600">Easily schedule services, choose tasteful packages, and coordinate with your director.</p>
    </div>

    <div class="f-card-flat p-6 shadow-sm">
      <div class="f-icon-tile mb-3">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M9 21v-6h6v6"/></svg>
      </div>
      <p class="mb-1 f-eyebrow">Directors</p>
      <h3 class="mb-2 text-xl font-semibold text-funeral-800">Trusted coordination</h3>
      <p class="text-sm leading-6 text-stone-600">Track inventory, manage schedules, and provide calm guidance to families.</p>
    </div>

    <div class="f-card-flat p-6 shadow-sm">
      <div class="f-icon-tile mb-3">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
      </div>
      <p class="mb-1 f-eyebrow">Admin</p>
      <h3 class="mb-2 text-xl font-semibold text-funeral-800">Clear oversight</h3>
      <p class="text-sm leading-6 text-stone-600">Approve registrations, monitor services, and keep community resources organized.</p>
    </div>
  </div>

</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
