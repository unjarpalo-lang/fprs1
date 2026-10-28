<?php include __DIR__ . '/includes/header.php'; ?>
<div class="mx-auto max-w-4xl space-y-5 py-6 sm:py-8">
  <div class="f-card p-6 sm:p-8">
    <p class="f-eyebrow mb-2">Technical reference</p>
    <h1 class="text-2xl font-bold text-funeral-800">System Requirements &amp; Specifications</h1>
    <p class="mt-2 text-sm text-stone-600">What FPRS expects from the environment it runs on.</p>
  </div>

  <div class="grid gap-4 sm:grid-cols-2">
    <div class="f-card-flat p-5 shadow-sm">
      <div class="mb-3 flex items-center gap-2.5">
        <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="10" rx="1"/><path d="M2 18h20M9 22h6"/></svg></span>
        <h2 class="text-lg font-semibold text-funeral-800">Minimum Hardware</h2>
      </div>
      <ul class="space-y-2 text-sm text-stone-600">
        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-funeral-400"></span>Processor: 12th Gen Intel Core i3-1215U (1.20 GHz) or higher</li>
        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-funeral-400"></span>Memory: 8 GB RAM</li>
        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-funeral-400"></span>Storage: 256 GB SSD (NVMe preferred)</li>
        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-funeral-400"></span>Graphics: Integrated GPU (Intel UHD Graphics or equivalent)</li>
      </ul>
    </div>

    <div class="f-card-flat p-5 shadow-sm">
      <div class="mb-3 flex items-center gap-2.5">
        <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 12h8M8 8h8M8 16h5"/></svg></span>
        <h2 class="text-lg font-semibold text-funeral-800">Minimum Software</h2>
      </div>
      <ul class="space-y-2 text-sm text-stone-600">
        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-funeral-400"></span>Operating System: Windows 10 or higher (server Linux recommended for production)</li>
        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-funeral-400"></span>PHP 8.0 or later (PHP 8.2 used for validation), MySQL (or MariaDB)</li>
        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-funeral-400"></span>Optional: Firebase for real-time features</li>
        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-funeral-400"></span>Frontend: Tailwind CSS</li>
      </ul>
    </div>
  </div>

  <div class="f-card-flat p-5 shadow-sm">
    <h2 class="mb-1.5 text-lg font-semibold text-funeral-800">Notes</h2>
    <p class="text-sm leading-6 text-stone-600">For production deployments use a secure Linux server, enable TLS, and configure regular database backups. Consider stronger CPU/RAM for high concurrency and enabling a CDN for static assets.</p>
  </div>

  <a href="<?= BASE_PATH ?>/about.php" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-5 py-2.5 text-sm font-medium text-funeral-700 shadow-sm transition hover:bg-stone-50">
    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
    About
  </a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
