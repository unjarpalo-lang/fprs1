<?php
declare(strict_types=1);

function safe_admin_return_url(?string $candidate, string $fallback): string {
    if (!$candidate || preg_match('/[\\\\\r\n]/', $candidate)) return $fallback;
    $parts = parse_url($candidate);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['fragment'])) return $fallback;
    $path = $parts['path'] ?? '';
    $allowed = ['dashboard.php', 'manage_clients.php', 'manage_directors.php', 'manage_parlors.php', 'manage_all_accounts.php', 'shops.php'];
    if (!in_array($path, array_map(static fn($page) => BASE_PATH . '/admin/' . $page, $allowed), true)) return $fallback;
    if (!isset($parts['query'])) return $path;
    parse_str($parts['query'], $query);
    if ($path === BASE_PATH . '/admin/manage_all_accounts.php'
        && count($query) === 1 && isset($query['type'])
        && in_array($query['type'], ['all', 'client', 'director', 'parlor'], true)) {
        return $path . '?type=' . $query['type'];
    }
    return $fallback;
}

function admin_return_url(string $fallback): string {
    $candidate = $_POST['return_to'] ?? $_GET['return_to'] ?? $_POST['redirect'] ?? $_GET['redirect'] ?? null;
    return safe_admin_return_url(is_string($candidate) ? $candidate : null, $fallback);
}

function render_catalog_back_link(string $href, string $label): void {
    ?>
    <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" class="mb-4 inline-flex min-h-11 items-center gap-1 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-funeral-300">
      <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
      <?= htmlspecialchars($label) ?>
    </a>
    <?php
}

function render_admin_notice(): void {
    $notice = $_SESSION['admin_notice'] ?? null;
    unset($_SESSION['admin_notice']);
    if (is_string($notice)) {
        echo '<div role="alert" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">' . htmlspecialchars($notice) . '</div>';
    }
}
