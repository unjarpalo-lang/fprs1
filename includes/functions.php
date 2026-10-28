<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/location_policy.php';
require_once __DIR__ . '/input_validation.php';
require_once __DIR__ . '/document_policy.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/service_feedback.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    $pdo = pdo_connect();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Ends the session of an account an admin has since set back to pending or
 * declined. login.php already gates on status, but that check only runs at
 * sign-in: without this, a user who is already signed in when an admin
 * declines them keeps full access until they happen to log out. Only the two
 * explicit revocation states are blocked, so an unexpected/blank status can
 * never lock a working account (notably an admin) out of the system.
 */
function enforce_active_account(?array $user): void {
    if ($user && in_array($user['status'] ?? '', ['pending', 'rejected'], true)) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_PATH . '/login.php'); exit;
    }
}

function require_role(string $role) {
    if (!is_logged_in()) {
        header('Location: ' . BASE_PATH . '/login.php'); exit;
    }
    $user = current_user();
    enforce_active_account($user);
    if (!$user || $user['role'] !== $role) {
        http_response_code(403); echo "Forbidden"; exit;
    }
}

function require_any_role(array $roles) {
    if (!is_logged_in()) { header('Location: ' . BASE_PATH . '/login.php'); exit; }
    $user = current_user();
    enforce_active_account($user);
    if (!$user || !in_array($user['role'],$roles,true)) { http_response_code(403); echo "Forbidden"; exit; }
}

/**
 * Per-session CSRF token, used to protect the irreversible delete actions.
 *
 * The session cookie is already SameSite=Strict (config/db.php), which is
 * what stops a cross-site page triggering these GET action links. The token
 * is the second layer, and it also stops a link prefetcher or an accelerator
 * quietly firing a delete that happens to sit in browser history.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Constant-time check of a submitted CSRF token against the session's. */
function csrf_check(?string $token): bool {
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Rejects a state-changing request that is missing a valid CSRF token. */
function require_csrf_token(mixed $token): void {
    if (!is_string($token) || !csrf_check($token)) {
        http_response_code(403);
        echo 'Invalid or expired request token. Please go back and try again.';
        exit;
    }
}

/** Largest accepted upload, in bytes. Applied on top of PHP's own ini limits. */
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
const MAX_PARLOR_LICENSE_BYTES = 40 * 1024 * 1024;

/** True when a file exceeds the supplied limit (5 MiB by default). */
function upload_too_large(array $file, int $limit = MAX_UPLOAD_BYTES): bool {
    return (int)($file['size'] ?? 0) > $limit;
}

function first_approved_director_id(): ?int {
    $pdo = pdo_connect();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE role = ? AND status = ? ORDER BY id ASC LIMIT 1');
    $stmt->execute(['director', 'approved']);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

/**
 * The single reusable Municipality -> Barangay location picker, used
 * identically everywhere the app captures a location (client search,
 * parlor registration, parlor address edits). Echoes the field markup;
 * pair with location_dropdown_script() for the cascading behavior.
 */
/**
 * Renders an uploaded document (image or PDF) directly inline — no external
 * link/new tab required, per the "instant review" verification requirement.
 * @param string $localPath Filesystem path, used only to sniff the mime type.
 * @param string $publicPath URL path (BASE_PATH-relative) used as the src.
 */
function render_document_viewer(string $localPath, string $publicPath, string $label): void {
    if (!is_file($localPath)) {
        echo '<div class="mt-2 rounded-xl border border-dashed border-stone-300 bg-stone-50 p-4 text-center text-sm text-stone-500">' . htmlspecialchars($label) . ' file not found.</div>';
        return;
    }
    $mime = (string)mime_content_type($localPath);
    ?>
    <?php if (str_contains(str_replace('\\', '/', $localPath), '/sample-licenses/')): ?>
        <p class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Sample document only — not a verified business license.</p>
    <?php endif; ?>
    <div class="mt-2 overflow-hidden rounded-xl border border-stone-200 bg-stone-50">
        <?php if (str_starts_with($mime, 'image/')): ?>
            <img src="<?= htmlspecialchars($publicPath) ?>" alt="<?= htmlspecialchars($label) ?>" class="max-h-[32rem] w-full object-contain" />
        <?php elseif ($mime === 'application/pdf'): ?>
            <iframe src="<?= htmlspecialchars($publicPath) ?>" title="<?= htmlspecialchars($label) ?>" class="h-[32rem] w-full" loading="lazy"></iframe>
        <?php else: ?>
            <div class="p-4 text-center text-sm text-stone-500">Preview isn't available for this file type (<?= htmlspecialchars($mime) ?>).</div>
        <?php endif; ?>
    </div>
    <?php
}

function render_location_fields(array $locations, string $idPrefix, string $prefillMunicipality, string $prefillBarangay, string $municipalityName = 'municipality', string $barangayName = 'barangay', string $label = 'Location', bool $required = true): void {
    $req = $required ? 'required' : '';
    ?>
    <div>
      <p class="mb-1 block text-sm font-medium text-stone-700"><?= htmlspecialchars($label) ?></p>
      <div class="grid gap-3 sm:grid-cols-2">
        <div><label for="<?= htmlspecialchars($idPrefix) ?>-municipality" class="mb-1 block text-sm text-stone-600">Municipality</label>
        <select name="<?= htmlspecialchars($municipalityName) ?>" id="<?= htmlspecialchars($idPrefix) ?>-municipality" <?= $req ?> class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200">
          <option value="">Select municipality</option>
          <?php foreach (array_keys($locations) as $municipality): ?>
            <option value="<?= htmlspecialchars($municipality) ?>" <?= $municipality === $prefillMunicipality ? 'selected' : '' ?>><?= htmlspecialchars($municipality) ?></option>
          <?php endforeach; ?>
        </select></div>
        <div><label for="<?= htmlspecialchars($idPrefix) ?>-barangay" class="mb-1 block text-sm text-stone-600">Barangay</label>
        <select name="<?= htmlspecialchars($barangayName) ?>" id="<?= htmlspecialchars($idPrefix) ?>-barangay" <?= $req ?> class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200">
          <option value="">Select barangay</option>
          <?php if ($prefillMunicipality && isset($locations[$prefillMunicipality])): foreach ($locations[$prefillMunicipality] as $barangay): ?>
            <option value="<?= htmlspecialchars($barangay) ?>" <?= $barangay === $prefillBarangay ? 'selected' : '' ?>><?= htmlspecialchars($barangay) ?></option>
          <?php endforeach; endif; ?>
        </select></div>
      </div>
    </div>
    <?php
}

/** Companion script for render_location_fields() — same idPrefix, same cascading behavior everywhere. */
function location_dropdown_script(array $locations, string $idPrefix): void {
    ?>
    <script>
      (function () {
        var LOCATIONS = <?= json_encode($locations) ?>;
        var m = document.getElementById('<?= htmlspecialchars($idPrefix, ENT_QUOTES) ?>-municipality');
        var b = document.getElementById('<?= htmlspecialchars($idPrefix, ENT_QUOTES) ?>-barangay');
        if (!m || !b) return;
        m.addEventListener('change', function () {
          var list = LOCATIONS[m.value] || [];
          b.innerHTML = '<option value="">Select barangay</option>' + list.map(function (name) {
            var opt = document.createElement('option');
            opt.value = name; opt.textContent = name;
            return opt.outerHTML;
          }).join('');
        });
      })();
    </script>
    <?php
}

/**
 * Municipality parsed out of a location string. Accepts the usual
 * "Barangay, Municipality" form (parlor records, which always have both)
 * as well as a bare "Municipality" with no comma (a municipality-only
 * client search preference, which has no barangay to combine with).
 */
function location_municipality(?string $location): string {
    if (!$location) return '';
    if (!str_contains($location, ',')) return trim($location);
    [, $municipality] = array_map('trim', explode(',', $location, 2));
    return $municipality;
}


    /** available above the low-stock threshold, insufficient if low but non-zero, missing at zero.
     *  Mirrors deriveStatus() in assets/js/firebase-inventory.js so both the SQL-tracked and any
     *  Firebase real-time inventory use the same three-state vocabulary. */
    function derive_inventory_status(int $quantity, int $lowThreshold = 3): string {
        if ($quantity <= 0) return 'missing';
        if ($quantity < $lowThreshold) return 'insufficient';
        return 'available';
    }

    function inventory_status_pill_class(string $status): string {
        return match ($status) {
            'available' => 'f-pill-approved',
            'insufficient' => 'f-pill-pending',
            default => 'f-pill-rejected', // missing
        };
    }

    /** Schema changes belong to sql/20261020_inventory_schema.php, never a page request. */
    function ensure_inventory_media_columns(PDO $pdo): void {
        static $checked = false;
        if ($checked) return;
        $stmt = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_resources'");
        $columns = [];
        foreach ($stmt->fetchAll() as $column) $columns[$column['COLUMN_NAME']] = $column['COLUMN_TYPE'];
        if (!isset($columns['image_path'], $columns['quantity'], $columns['available_status'], $columns['item_type'], $columns['director_id'], $columns['parlor_id'])
            || !str_contains($columns['available_status'], "'missing'")
            || !str_contains($columns['available_status'], "'insufficient'")
            || !str_contains($columns['item_type'], "'equipment'")) {
            http_response_code(503);
            exit('Inventory maintenance is required. Please contact the administrator.');
        }
        $checked = true;
    }

    function ensure_director_scope_columns(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS funeral_parlors (id INT AUTO_INCREMENT PRIMARY KEY, director_id INT NOT NULL, name VARCHAR(200) NOT NULL, location VARCHAR(255) NOT NULL, contact_number VARCHAR(50) NOT NULL, license_path VARCHAR(255) NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE CASCADE)");
        $reasonCol = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'funeral_parlors' AND COLUMN_NAME = 'status_reason'");
        $reasonCol->execute();
        if (!$reasonCol->fetchColumn()) {
            $pdo->exec("ALTER TABLE funeral_parlors ADD COLUMN status_reason VARCHAR(500) NULL");
        }
        foreach (['packages' => 'director_id', 'inventory_resources' => 'director_id'] as $table => $column) {
            $check = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $check->execute([$table, $column]);
            if (!$check->fetchColumn()) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} INT NULL");
            }
        }
        foreach (['packages', 'inventory_resources'] as $table) {
            $check = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'parlor_id'");
            $check->execute([$table]);
            if (!$check->fetchColumn()) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN parlor_id INT NULL");
            }
        }
    }

    function ensure_feedback_and_media_columns(PDO $pdo): void {
        $col = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packages' AND COLUMN_NAME = 'image_path'");
        $col->execute();
        if (!$col->fetchColumn()) {
            $pdo->exec("ALTER TABLE packages ADD COLUMN image_path VARCHAR(255) NULL");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS parlor_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parlor_id INT NOT NULL,
            client_id INT NOT NULL,
            location TINYINT NOT NULL,
            budget TINYINT NOT NULL,
            religion TINYINT NOT NULL,
            casket TINYINT NOT NULL,
            service_delivery TINYINT NOT NULL,
            documentation TINYINT NOT NULL,
            arrangement TINYINT NOT NULL,
            flower TINYINT NOT NULL,
            comment TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY parlor_client (parlor_id, client_id),
            FOREIGN KEY (parlor_id) REFERENCES funeral_parlors(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }

    /**
     * Deletes a user account and everything that only makes sense attached to
     * it. Real foreign keys handle most of this (funeral_parlors, messages,
     * client_preferences, parlor_feedback cascade automatically), but
     * packages/inventory_resources were added via ALTER TABLE without FKs,
     * so those are cleaned up explicitly to avoid orphaned rows.
     */
    function delete_user_account(PDO $pdo, int $userId): void {
        $pdo->beginTransaction();
        try {
            $parlorStmt = $pdo->prepare('SELECT id FROM funeral_parlors WHERE director_id = ?');
            $parlorStmt->execute([$userId]);
            $parlorIds = $parlorStmt->fetchAll(PDO::FETCH_COLUMN);

            $pdo->prepare('DELETE FROM packages WHERE director_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM inventory_resources WHERE director_id = ?')->execute([$userId]);
            if ($parlorIds) {
                $placeholders = implode(',', array_fill(0, count($parlorIds), '?'));
                $pdo->prepare("DELETE FROM packages WHERE parlor_id IN ({$placeholders})")->execute($parlorIds);
                $pdo->prepare("DELETE FROM inventory_resources WHERE parlor_id IN ({$placeholders})")->execute($parlorIds);
            }

            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Deletes a parlor listing on its own, independent of its director
     * account. parlor_feedback and parlor_survey_responses cascade via real
     * FKs; packages/inventory_resources were added via ALTER TABLE without
     * FKs, so those are cleaned up explicitly first to avoid orphaned rows.
     */
    function delete_parlor_listing(PDO $pdo, int $parlorId): void {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM packages WHERE parlor_id = ?')->execute([$parlorId]);
            $pdo->prepare('DELETE FROM inventory_resources WHERE parlor_id = ?')->execute([$parlorId]);
            $pdo->prepare('DELETE FROM funeral_parlors WHERE id = ?')->execute([$parlorId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    function selected_director_parlor(PDO $pdo, int $directorId): ?array {
        $parlorId = (int)($_SESSION['selected_parlor_id'] ?? 0);
        if (!$parlorId) return null;
        // A parlor is selectable/manageable regardless of status (approved,
        // pending, or declined) — only client-facing visibility is status-gated,
        // never the director's own management tools.
        $stmt = $pdo->prepare('SELECT * FROM funeral_parlors WHERE id = ? AND director_id = ?');
        $stmt->execute([$parlorId, $directorId]);
        return $stmt->fetch() ?: null;
    }

    /** User-facing label for a funeral_parlors.status value — "rejected" reads as "Declined" everywhere. */
    function status_label(string $status): string {
        return match ($status) {
            'approved' => 'Approved',
            'pending' => 'Pending',
            'rejected' => 'Declined',
            default => ucfirst($status),
        };
    }

    /** Tailwind pill class for a funeral_parlors.status value, paired with status_label(). */
    function status_pill_class(string $status): string {
        return match ($status) {
            'approved' => 'f-pill-approved',
            'rejected' => 'f-pill-rejected',
            default => 'f-pill-pending',
        };
    }

    /**
     * Shared row for the dedicated Manage Client / Manage Director pages —
     * same markup for both. Declined entries retain a confirmed delete
     * action using the existing CSRF-protected account deletion handler.
     */
    function render_admin_account_row(array $u): void {
        $isDeclined = $u['status'] === 'rejected';
        $returnUrl = BASE_PATH . '/admin/' . ($u['role'] === 'director' ? 'manage_directors.php' : 'manage_clients.php');
        $returnQuery = '&return_to=' . urlencode($returnUrl);
        ?>
        <div class="f-card-flat p-3.5">
          <div class="admin-record-grid">
            <div class="admin-record-identity">
              <div class="flex flex-wrap items-center gap-2">
                <span class="admin-record-name font-medium text-stone-800"><?= htmlspecialchars($u['full_name']) ?></span>
                <span class="f-pill <?= status_pill_class($u['status']) ?>"><?= status_label($u['status']) ?></span>
              </div>
            </div>
            <div class="admin-record-details">
              <div class="text-sm text-stone-500"><?= htmlspecialchars($u['email']) ?></div>
              <?php if (!empty($u['phone'])): ?><div class="text-sm text-stone-600">Contact: <?= htmlspecialchars($u['phone']) ?></div><?php endif; ?>
            </div>
            <div class="admin-record-actions">
              <?php if ($isDeclined): ?>
                <button type="button" data-delete-url="<?= htmlspecialchars(BASE_PATH . '/admin/user_action.php?action=delete&id=' . (int)$u['id'] . '&token=' . urlencode(csrf_token()) . $returnQuery, ENT_QUOTES) ?>" data-delete-name="<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>" onclick="openDeleteModal(this.dataset.deleteUrl, this.dataset.deleteName)" class="min-h-11 rounded-full bg-red-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-red-500">Delete</button>
              <?php else: ?>
                <a href="<?= BASE_PATH ?>/admin/verify_user.php?action=view&id=<?= (int)$u['id'] ?><?= $returnQuery ?>" class="rounded-full border border-stone-300 px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:bg-stone-50">View <?= $u['role'] === 'director' ? 'License' : 'ID' ?></a>
                <?php if ($u['status'] !== 'approved'): ?>
                  <a href="<?= BASE_PATH ?>/admin/verify_user.php?action=approve&id=<?= (int)$u['id'] ?>&token=<?= urlencode(csrf_token()) ?><?= $returnQuery ?>" class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-500">Approve</a>
                <?php endif; ?>
                <a href="<?= BASE_PATH ?>/admin/verify_user.php?action=reject&id=<?= (int)$u['id'] ?>&token=<?= urlencode(csrf_token()) ?><?= $returnQuery ?>" class="rounded-full bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-500">Decline</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php
    }

    /** Manage Parlor row — same layout/classes as render_admin_account_row() above, different fields. */
    function render_admin_parlor_row(array $p): void {
        $isDeclined = $p['status'] === 'rejected';
        $returnQuery = '&return_to=' . urlencode(BASE_PATH . '/admin/manage_parlors.php');
        ?>
        <div class="f-card-flat p-3.5">
          <div class="admin-record-grid">
            <div class="admin-record-identity">
              <div class="flex flex-wrap items-center gap-2">
                <span class="admin-record-name font-medium text-stone-800"><?= htmlspecialchars($p['name']) ?></span>
                <span class="f-pill <?= status_pill_class($p['status']) ?>"><?= status_label($p['status']) ?></span>
              </div>
            </div>
            <div class="admin-record-details">
              <div class="text-sm text-stone-500"><?= htmlspecialchars($p['location']) ?> · <?= htmlspecialchars($p['contact_number']) ?></div>
              <?php if (private_document_path($p['license_path'] ?? null) === null): ?><div class="mt-1 text-sm text-amber-700">Parlor business license missing — upload required before approval.</div><?php endif; ?>
              <div class="text-sm text-stone-600">Director: <?= htmlspecialchars($p['director_name']) ?></div>
            </div>
            <div class="admin-record-actions">
              <?php if ($isDeclined): ?>
                <span class="text-xs italic text-stone-400">Declined — no actions available</span>
              <?php else: ?>
                <a href="<?= BASE_PATH ?>/admin/parlor_action.php?action=view&id=<?= (int)$p['id'] ?><?= $returnQuery ?>" class="rounded-full border border-stone-300 px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:bg-stone-50">View License</a>
                <?php if ($p['status'] !== 'approved'): ?>
                  <a href="<?= BASE_PATH ?>/admin/parlor_action.php?action=approve&id=<?= (int)$p['id'] ?>&token=<?= urlencode(csrf_token()) ?><?= $returnQuery ?>" class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-500">Approve</a>
                <?php endif; ?>
                <?php if ($p['status'] !== 'pending'): ?>
                  <a href="<?= BASE_PATH ?>/admin/parlor_action.php?action=pending&id=<?= (int)$p['id'] ?>&token=<?= urlencode(csrf_token()) ?><?= $returnQuery ?>" class="rounded-full bg-amber-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-amber-400">Set to Pending</a>
                <?php endif; ?>
                <a href="<?= BASE_PATH ?>/admin/parlor_action.php?action=reject&id=<?= (int)$p['id'] ?>&token=<?= urlencode(csrf_token()) ?><?= $returnQuery ?>" class="rounded-full bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-500">Decline</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php
    }

    /**
     * Manage All Accounts row for a user (client/director/admin) — approved
     * only by construction of the page's query. Clients/directors can have
     * their submitted ID/license viewed; delete is guarded (self, last
     * remaining admin) via the shared delete-confirmation modal.
     */
    function render_admin_all_accounts_user_row(array $u, bool $isSelf, bool $isLastAdmin): void {
        $returnQuery = '&return_to=' . urlencode(safe_admin_return_url($_SERVER['REQUEST_URI'] ?? null, BASE_PATH . '/admin/manage_all_accounts.php'));
        ?>
        <div class="f-card-flat p-3.5">
          <div class="admin-record-grid">
            <div class="admin-record-identity">
              <div class="flex flex-wrap items-center gap-2">
                <span class="admin-record-name font-medium text-stone-800"><?= htmlspecialchars($u['full_name']) ?></span>
                <span class="f-pill bg-funeral-100 text-funeral-800"><?= htmlspecialchars(ucfirst($u['role'])) ?></span>
                <span class="f-pill <?= status_pill_class($u['status']) ?>"><?= status_label($u['status']) ?></span>
              </div>
            </div>
            <div class="admin-record-details">
              <div class="text-sm text-stone-500"><?= htmlspecialchars($u['email']) ?><?= $u['phone'] ? ' · ' . htmlspecialchars($u['phone']) : '' ?></div>
            </div>
            <div class="admin-record-actions">
              <?php if ($u['role'] !== 'admin'): ?>
                <a href="<?= BASE_PATH ?>/admin/verify_user.php?action=view&id=<?= (int)$u['id'] ?><?= $returnQuery ?>" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50">View <?= $u['role'] === 'director' ? 'License' : 'ID' ?></a>
              <?php endif; ?>
              <?php if ($isSelf): ?>
                <span class="text-xs text-stone-400">This is your account</span>
              <?php elseif ($isLastAdmin): ?>
                <span class="text-xs text-stone-400">Last remaining admin</span>
              <?php else: ?>
                <button type="button" onclick="openDeleteModal('<?= BASE_PATH ?>/admin/user_action.php?action=delete&id=<?= (int)$u['id'] ?>&token=<?= urlencode(csrf_token()) ?><?= $returnQuery ?>', '<?= htmlspecialchars(addslashes($u['full_name']), ENT_QUOTES) ?>')" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-full border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-500">Delete</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php
    }

    /**
     * Manage All Accounts row for a parlor — approved only by construction.
     * A real standalone delete (independent of its director account), via
     * the same shared delete-confirmation modal, sits beside the license action.
     */
    function render_admin_all_accounts_parlor_row(array $p): void {
        $returnQuery = '&return_to=' . urlencode(safe_admin_return_url($_SERVER['REQUEST_URI'] ?? null, BASE_PATH . '/admin/manage_all_accounts.php'));
        ?>
        <div class="f-card-flat p-3.5">
          <div class="admin-record-grid">
            <div class="admin-record-identity">
              <div class="flex flex-wrap items-center gap-2">
                <span class="admin-record-name font-medium text-stone-800"><?= htmlspecialchars($p['name']) ?></span>
                <span class="f-pill bg-funeral-100 text-funeral-800">Parlor</span>
                <span class="f-pill <?= status_pill_class($p['status']) ?>"><?= status_label($p['status']) ?></span>
              </div>
            </div>
            <div class="admin-record-details">
              <div class="text-sm text-stone-500"><?= htmlspecialchars($p['location']) ?> · <?= htmlspecialchars($p['contact_number']) ?></div>
              <?php if (private_document_path($p['license_path'] ?? null) === null): ?><div class="mt-1 text-sm text-amber-700">Parlor business license missing.</div><?php endif; ?>
              <div class="text-sm text-stone-500">Director: <?= htmlspecialchars($p['director_name']) ?></div>
            </div>
            <div class="admin-record-actions">
              <a href="<?= BASE_PATH ?>/admin/parlor_action.php?action=view&id=<?= (int)$p['id'] ?><?= $returnQuery ?>" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50">View License</a>
              <button type="button" onclick="openDeleteModal('<?= BASE_PATH ?>/admin/parlor_action.php?action=delete&id=<?= (int)$p['id'] ?>&token=<?= urlencode(csrf_token()) ?><?= $returnQuery ?>', '<?= htmlspecialchars(addslashes($p['name']), ENT_QUOTES) ?>')" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-full border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-500">Delete</button>
            </div>
          </div>
        </div>
        <?php
    }

    /** Shared delete-confirmation modal + JS — include once per page that calls openDeleteModal(). */
    function render_delete_confirmation_modal(): void {
        ?>
        <div id="delete-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/50 p-4" style="display:none;">
          <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-lift">
            <div class="mb-3 flex items-center gap-3">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
              </span>
              <h3 class="text-lg font-semibold text-funeral-800">Delete this?</h3>
            </div>
            <p class="text-sm text-stone-600">You're about to permanently delete <strong id="delete-modal-name" class="text-stone-800"></strong>. This cannot be undone.</p>
            <div class="mt-5 flex justify-end gap-2">
              <button type="button" onclick="closeDeleteModal()" class="rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50">Cancel</button>
              <a id="delete-modal-confirm-link" href="#" class="rounded-full bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-500">Delete</a>
            </div>
          </div>
        </div>
        <script>
        function openDeleteModal(url, name) {
          document.getElementById('delete-modal-name').textContent = name;
          document.getElementById('delete-modal-confirm-link').setAttribute('href', url);
          document.getElementById('delete-modal').style.display = 'flex';
        }
        function closeDeleteModal() {
          document.getElementById('delete-modal').style.display = 'none';
        }
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') closeDeleteModal();
        });
        </script>
        <?php
    }

    /** Links a SQL users row to its Firebase Auth account. Additive — the existing
     *  password-based session login (login.php) keeps working unchanged. */
    function ensure_firebase_columns(PDO $pdo): void {
        $col = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'firebase_uid'");
        $col->execute();
        if (!$col->fetchColumn()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN firebase_uid VARCHAR(128) NULL UNIQUE");
        }
    }

    /** One row per included service/item within a package (casket, flowers, hearse, ...),
     *  each with its own optional thumbnail. Additive alongside packages.inclusions, which
     *  stays a flattened text summary for the recommender engine's religion-keyword matching. */
    function ensure_package_inclusion_images(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS package_inclusions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            package_id INT NOT NULL,
            service_name VARCHAR(120) NOT NULL,
            service_detail VARCHAR(255) NOT NULL,
            image_path VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY package_idx (package_id),
            FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
        )");
        $col = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package_inclusions' AND COLUMN_NAME = 'description'");
        $col->execute();
        if (!$col->fetchColumn()) {
            $pdo->exec("ALTER TABLE package_inclusions ADD COLUMN description VARCHAR(500) NULL AFTER service_detail");
        }
    }

    /**
     * Responsive grid of a package's inclusions, each with its own thumbnail
     * (or a neutral placeholder icon when a provider hasn't uploaded one).
     */
    /**
     * @param bool $compact True for small inline previews (e.g. a director's package
     *   card): smaller thumbnails, name/detail only. False for the full package
     *   details page: larger images plus each item's description.
     */
    function render_package_inclusions(PDO $pdo, int $packageId, bool $compact = true): void {
        $stmt = $pdo->prepare('SELECT * FROM package_inclusions WHERE package_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$packageId]);
        $items = $stmt->fetchAll();
        if (!$items) {
            echo '<p class="text-sm text-stone-500">This provider hasn\'t itemized individual inclusions yet.</p>';
            return;
        }
        $imgSize = $compact ? 'h-14 w-14' : 'h-20 w-20';
        $iconSize = $compact ? 'h-6 w-6' : 'h-8 w-8';
        ?>
        <div class="grid gap-3 <?= $compact ? 'package-inclusions-compact' : 'grid-cols-1 sm:grid-cols-2' ?>">
            <?php foreach ($items as $item): ?>
                <div class="package-inclusion-item flex items-center gap-3 rounded-xl border border-stone-200 bg-white p-3 shadow-sm">
                    <?php if (!empty($item['image_path'])): ?>
                        <img src="<?= htmlspecialchars(BASE_PATH . '/' . ltrim($item['image_path'], '/')) ?>" alt="<?= htmlspecialchars($item['service_name']) ?>" class="<?= $imgSize ?> shrink-0 rounded-lg border border-stone-200 object-cover" />
                    <?php else: ?>
                        <div class="<?= $imgSize ?> flex shrink-0 items-center justify-center rounded-lg border border-dashed border-stone-300 bg-stone-50 text-stone-300">
                            <svg class="<?= $iconSize ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
                        </div>
                    <?php endif; ?>
                    <div class="package-inclusion-text min-w-0">
                        <div class="truncate text-sm font-semibold text-stone-800"><?= htmlspecialchars($item['service_name']) ?></div>
                        <div class="truncate text-xs text-stone-500"><?= htmlspecialchars($item['service_detail']) ?></div>
                        <?php if (!$compact && !empty($item['description'])): ?>
                            <div class="mt-1 text-xs leading-relaxed text-stone-600"><?= htmlspecialchars($item['description']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    function ensure_recommender_tables(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS parlor_survey_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parlor_id INT NOT NULL,
            respondent_no INT NOT NULL,
            criterion_key VARCHAR(40) NOT NULL,
            score TINYINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY parlor_idx (parlor_id),
            FOREIGN KEY (parlor_id) REFERENCES funeral_parlors(id) ON DELETE CASCADE
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS client_preferences (
            client_id INT PRIMARY KEY,
            budget DECIMAL(10,2) NULL,
            religion VARCHAR(100) NULL,
            max_distance DECIMAL(6,2) NULL,
            importance_location TINYINT NOT NULL DEFAULT 3,
            importance_budget TINYINT NOT NULL DEFAULT 3,
            importance_religion TINYINT NOT NULL DEFAULT 3,
            importance_casket TINYINT NOT NULL DEFAULT 3,
            importance_service_delivery TINYINT NOT NULL DEFAULT 3,
            importance_documentation TINYINT NOT NULL DEFAULT 3,
            importance_arrangement TINYINT NOT NULL DEFAULT 3,
            importance_flower TINYINT NOT NULL DEFAULT 3,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $col = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_preferences' AND COLUMN_NAME = 'location'");
        $col->execute();
        if (!$col->fetchColumn()) {
            $pdo->exec("ALTER TABLE client_preferences ADD COLUMN location VARCHAR(255) NULL");
        }
    }
