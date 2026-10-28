  </main>
  <footer class="site-footer border-t border-white/10 bg-funeral-900 text-stone-100">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-10 sm:px-6 md:grid-cols-3 lg:px-8">
      <div>
        <div class="flex items-center gap-2.5">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-funeral-400/90 text-funeral-900">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c1.2 2.1 2 3.6 2 5a2 2 0 1 1-4 0c0-1.4.8-2.9 2-5Z" /><path d="M12 9v13" /><path d="M6 22c0-3.3 2.7-6 6-6s6 2.7 6 6" /></svg>
          </span>
          <span class="font-serif text-base font-semibold tracking-wide">FPRS</span>
        </div>
        <p class="mt-3 max-w-xs text-sm leading-6 text-stone-300">A gentle place to arrange memorial care — for families who need clarity, and directors who provide it.</p>
      </div>
      <div>
        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-funeral-300/90">Explore</p>
        <ul class="space-y-2 text-sm text-stone-300">
          <li><a href="<?= BASE_PATH ?>/about.php" class="transition hover:text-white">About FPRS</a></li>
          <li><a href="<?= BASE_PATH ?>/requirements.php" class="transition hover:text-white">System requirements</a></li>
          <li><a href="<?= BASE_PATH ?>/register.php" class="transition hover:text-white">Register an account</a></li>
        </ul>
      </div>
      <div>
        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-funeral-300/90">Our promise</p>
        <ul class="space-y-2 text-sm text-stone-300">
          <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-funeral-400"></span>Compassion in every interaction</li>
          <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-funeral-400"></span>Clear, unhurried guidance</li>
          <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-funeral-400"></span>Respect for every family's wishes</li>
        </ul>
      </div>
    </div>
    <div class="border-t border-white/10">
      <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-4 text-xs text-stone-400 sm:px-6 sm:flex-row sm:items-center sm:justify-between lg:px-8">
        <div>© 2026 Funeral Service Management</div>
        <div class="tracking-[0.08em]">Compassion • Respect • Care</div>
      </div>
    </div>
  </footer>
</div>
<?php if (($navUser['role'] ?? '') === 'client'): ?>
  <script src="<?= BASE_PATH ?>/assets/js/package-ratings.js?v=<?= filemtime(__DIR__ . '/../assets/js/package-ratings.js') ?>" data-endpoint="<?= BASE_PATH ?>/api/package_ratings.php" data-reviewed-package="<?= (int)($_SESSION['reviewed_package_id'] ?? 0) ?: '' ?>" defer></script>
  <?php unset($_SESSION['reviewed_package_id']); ?>
<?php endif; ?>
</body>
</html>
