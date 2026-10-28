<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$skyVideoFile = __DIR__ . '/../bootstrap/video/sky-clouds.mp4';
$hasSkyVideo = is_file($skyVideoFile);
?>
<!doctype html>
<html lang="en" class="h-full bg-stone-100">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Funeral Service Management</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/bootstrap/css/site.css?v=<?= @filemtime(__DIR__ . '/../bootstrap/css/site.css') ?: time() ?>" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            serif: ['Fraunces', 'Georgia', 'serif'],
            sans: ['Inter', 'Segoe UI', 'sans-serif']
          },
          colors: {
            funeral: {
              50: '#f2f6fa',
              100: '#dde9f4',
              200: '#b3cfe6',
              300: '#f5d66f',
              400: '#eac449',
              500: '#d8ad27',
              600: '#2a5c82',
              700: '#1c4363',
              800: '#16324a',
              900: '#0f2438'
            }
          },
          boxShadow: {
            soft: '0 18px 40px rgba(15,40,70,0.08)',
            lift: '0 22px 48px rgba(15,40,70,0.14)'
          }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen font-sans text-stone-800 antialiased">
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
  <filter id="cloud-fluff" x="-40%" y="-40%" width="180%" height="180%">
    <feTurbulence type="fractalNoise" baseFrequency="0.012 0.035" numOctaves="3" seed="7" result="noise" />
    <feDisplacementMap in="SourceGraphic" in2="noise" scale="8" xChannelSelector="R" yChannelSelector="G" />
  </filter>
</svg>
<div class="sky-backdrop" aria-hidden="true">
  <?php if ($hasSkyVideo): ?>
    <video class="sky-video" autoplay muted loop playsinline preload="auto">
      <source src="<?= BASE_PATH ?>/bootstrap/video/sky-clouds.mp4?v=<?= filemtime($skyVideoFile) ?>" type="video/mp4" />
    </video>
    <div class="sky-veil"></div>
    <script>
      (function () {
        var mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        var vids = document.querySelectorAll('.sky-video');
        function apply() {
          vids.forEach(function (v) {
            if (mq.matches) { v.pause(); } else { v.play().catch(function () {}); }
          });
        }
        if (mq.addEventListener) mq.addEventListener('change', apply);
        else if (mq.addListener) mq.addListener(apply);
        apply();
      })();
    </script>
  <?php else: ?>
    <span class="cloud" style="--cloud-top:8%; --cloud-w:110px; --cloud-o:0.75; --cloud-dur:170s; --cloud-delay:-10s;"></span>
    <span class="cloud" style="--cloud-top:18%; --cloud-w:70px; --cloud-o:0.6; --cloud-dur:135s; --cloud-delay:-60s;"></span>
    <span class="cloud" style="--cloud-top:6%; --cloud-w:130px; --cloud-o:0.65; --cloud-dur:200s; --cloud-delay:-140s;"></span>
    <span class="cloud" style="--cloud-top:26%; --cloud-w:85px; --cloud-o:0.55; --cloud-dur:150s; --cloud-delay:-30s;"></span>
    <span class="cloud" style="--cloud-top:14%; --cloud-w:60px; --cloud-o:0.5; --cloud-dur:120s; --cloud-delay:-90s;"></span>
    <span class="cloud" style="--cloud-top:32%; --cloud-w:100px; --cloud-o:0.6; --cloud-dur:185s; --cloud-delay:-20s;"></span>
  <?php endif; ?>
</div>
<a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-funeral-900 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">Skip to content</a>
<div class="min-h-screen flex flex-col">
  <header class="sticky top-0 z-40 border-b border-white/10 bg-funeral-900/95 shadow-sm backdrop-blur-sm">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between">
        <?php
          $navUser = is_logged_in() ? current_user() : null;
        ?>
        <div class="flex items-center gap-3">
          <a href="<?php echo BASE_PATH; ?>" class="group flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-funeral-400/90 text-funeral-900 shadow-inner transition group-hover:bg-funeral-300">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c1.2 2.1 2 3.6 2 5a2 2 0 1 1-4 0c0-1.4.8-2.9 2-5Z" /><path d="M12 9v13" /><path d="M6 22c0-3.3 2.7-6 6-6s6 2.7 6 6" /></svg>
            </span>
            <span class="flex flex-col leading-none">
              <span class="font-serif text-lg font-semibold tracking-wide text-stone-50">FPRS</span>
              <span class="hidden text-[10px] font-medium uppercase tracking-[0.18em] text-funeral-300/90 sm:block">Care, coordinated</span>
            </span>
          </a>
        </div>

        <nav class="hidden items-center gap-1 md:flex">
          <?php if (($navUser['role'] ?? '') === 'client'): ?>
            <a href="<?= BASE_PATH ?>/client/inquired_services.php" class="rounded-full bg-funeral-400 px-4 py-2 text-sm font-semibold text-stone-900 transition hover:bg-funeral-300">Inquired Services</a>
            <a href="<?= BASE_PATH ?>/client/account.php" class="rounded-full px-3 py-2 text-sm font-medium text-stone-100 hover:bg-white/10">My Account</a>
          <?php elseif (($navUser['role'] ?? '') === 'director' && !empty($showDirectorPersonalAccount)): ?>
            <a href="<?= BASE_PATH ?>/director/account.php" class="rounded-full px-3 py-2 text-sm font-medium text-stone-100 hover:bg-white/10">Personal Account</a>
          <?php endif; ?>
          <a href="<?= BASE_PATH ?>/about.php" class="rounded-full px-3 py-2 text-sm font-medium text-stone-200 transition hover:bg-white/10 hover:text-white">About</a>
          <?php if($navUser): ?>
            <a href="<?php echo BASE_PATH; ?>/logout.php" class="ml-2 rounded-full border border-white/15 px-4 py-2 text-sm font-medium text-stone-100 transition hover:bg-white/10">Logout</a>
          <?php else: ?>
            <a href="<?php echo BASE_PATH; ?>/login.php" class="rounded-full px-3 py-2 text-sm font-medium text-stone-200 transition hover:bg-white/10 hover:text-white">Login</a>
            <a href="<?php echo BASE_PATH; ?>/register.php" class="ml-2 rounded-full bg-funeral-400 px-4 py-2 text-sm font-semibold text-stone-900 shadow-sm transition hover:bg-funeral-300">Register</a>
          <?php endif; ?>
        </nav>

        <?php if(!$navUser): ?>
          <div class="flex items-center gap-2 md:hidden">
            <a href="<?= BASE_PATH ?>/about.php" class="text-sm text-stone-100">About</a>
            <a href="<?php echo BASE_PATH; ?>/login.php" class="rounded-full border border-stone-600 px-3 py-1.5 text-sm text-stone-100">Login</a>
            <a href="<?php echo BASE_PATH; ?>/register.php" class="rounded-full bg-funeral-400 px-3 py-1.5 text-sm font-semibold text-stone-900">Join</a>
          </div>
        <?php else: ?>
          <div class="flex min-w-0 items-center gap-2 md:hidden">
            <a href="<?= BASE_PATH ?>/about.php" class="text-sm text-stone-100">About</a>
            <a href="<?php echo BASE_PATH; ?>/logout.php" class="rounded-full border border-stone-600 px-3 py-1.5 text-sm text-stone-100">Logout</a>
          </div>
        <?php endif; ?>
      </div>
      <?php if (in_array($navUser['role'] ?? '', ['client','director'], true)): ?>
        <nav aria-label="Services" class="flex flex-wrap gap-2 pb-3 md:hidden">
          <a href="<?= BASE_PATH ?>/<?= $navUser['role'] ?>/dashboard.php" class="inline-flex min-h-11 items-center rounded-full border border-white/20 px-4 py-2 text-sm font-medium text-stone-100">Dashboard</a>
          <?php if ($navUser['role'] === 'client'): ?>
          <a href="<?= BASE_PATH ?>/client/inquired_services.php" class="inline-flex min-h-11 items-center rounded-full bg-funeral-400 px-4 py-2 text-sm font-semibold text-stone-900">Inquired Services</a>
          <?php endif; ?>
          <?php if ($navUser['role'] === 'client'): ?><a href="<?= BASE_PATH ?>/client/account.php" class="inline-flex min-h-11 items-center rounded-full border border-white/20 px-4 py-2 text-sm font-medium text-stone-100">My Account</a><?php endif; ?>
          <?php if ($navUser['role'] === 'director' && !empty($showDirectorPersonalAccount)): ?><a href="<?= BASE_PATH ?>/director/account.php" class="inline-flex min-h-11 items-center rounded-full border border-white/20 px-4 py-2 text-sm font-medium text-stone-100">Personal Account</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  </header>
  <main id="main-content" class="site-main mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
