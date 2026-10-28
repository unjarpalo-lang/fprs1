<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['director']);
$user = current_user();
$pdo = pdo_connect();
// Scope columns are checked by the inventory readiness guard.
ensure_inventory_media_columns($pdo);
$selectedParlor = selected_director_parlor($pdo, (int)$user['id']);
if (!$selectedParlor) { header('Location: ' . BASE_PATH . '/director/funeral_parlors.php'); exit; }
// Inventory schema is installed by the versioned CLI migration.
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token($_POST['token'] ?? null);
    $name = trim(request_text($_POST, 'name'));
    $type = request_text($_POST, 'item_type', 'flower');
    $details = request_text($_POST, 'details');
    $rawQuantity = $_POST['quantity'] ?? '';
    $quantity = validated_inventory_quantity($rawQuantity);
    $quantityError = $quantity === null ? 'Quantity must be a whole number from 0 to 2,147,483,647.' : '';
    if ($quantityError !== '') http_response_code(422);
    $requestedStatus = request_text($_POST, 'available_status');
    // A director can still mark an in-stock item "Insufficient" (e.g. reserved,
    // damaged) — but zero quantity always forces "Missing" so the badge can
    // never claim stock that doesn't exist.
    $status = $quantity <= 0
        ? 'missing'
        : (in_array($requestedStatus, ['available', 'insufficient'], true) ? $requestedStatus : derive_inventory_status($quantity));
    $existingId = request_text($_POST, 'id') ?: null;

    $imagePath = null;
    if ($existingId) {
        $imgStmt = $pdo->prepare('SELECT image_path FROM inventory_resources WHERE id = ? AND director_id = ? AND parlor_id = ?');
        $imgStmt->execute([$existingId, $user['id'], $selectedParlor['id']]);
        $imagePath = $imgStmt->fetchColumn() ?: null;
    }
    $uploadError = $quantityError;
    if ($name === '' || text_length($name) > 200) $uploadError = 'Resource name is required and must not exceed 200 characters.';
    if (!in_array($type, ['flower', 'candle', 'plant', 'casket', 'equipment', 'other'], true)) {
        $uploadError = 'Choose a valid resource type.';
    }
    if ($existingId) {
        $owner = $pdo->prepare('SELECT 1 FROM inventory_resources WHERE id = ? AND director_id = ? AND parlor_id = ?');
        $owner->execute([$existingId, $user['id'], $selectedParlor['id']]);
        if (!$owner->fetchColumn()) { http_response_code(403); exit('You do not have permission to edit this resource.'); }
    }
    if ($uploadError === '' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image']['tmp_name']);
        $allowedImages = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (upload_too_large($_FILES['image'])) {
            $uploadError = 'Resource image must be 5 MB or smaller.';
        } elseif (!isset($allowedImages[$mime])) {
            $uploadError = 'Resource image must be a PNG, JPG, or WEBP file.';
        } else {
            $filename = uniqid('inventory_', true) . '.' . $allowedImages[$mime];
            move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../uploads/' . $filename);
            $imagePath = 'uploads/' . $filename;
        }
    } elseif (isset($_FILES['image']) && !in_array($_FILES['image']['error'], [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
        $uploadError = 'Image upload failed. Please try again.';
    }

    if ($uploadError) {
        $error = $uploadError;
        $item = ['id' => $existingId, 'name' => $name, 'item_type' => $type, 'details' => $details, 'quantity' => is_scalar($rawQuantity) ? (string)$rawQuantity : '', 'available_status' => $status, 'image_path' => $imagePath];
    } else {
        if ($existingId) {
            $pdo->prepare('UPDATE inventory_resources SET item_type=?,name=?,details=?,quantity=?,available_status=?,image_path=? WHERE id=? AND director_id=? AND parlor_id=?')
              ->execute([$type,$name,$details,$quantity,$status,$imagePath,$existingId,$user['id'],$selectedParlor['id']]);
        } else {
            $pdo->prepare('INSERT INTO inventory_resources (director_id,parlor_id,item_type,name,details,quantity,available_status,image_path) VALUES (?,?,?,?,?,?,?,?)')
              ->execute([$user['id'],$selectedParlor['id'],$type,$name,$details,$quantity,$status,$imagePath]);
        }
        header('Location: ' . BASE_PATH . '/director/inventory.php'); exit;
    }
}
if ($action === 'delete' && isset($_GET['id'])) {
  require_csrf_token($_GET['token'] ?? null);
  $pdo->prepare('DELETE FROM inventory_resources WHERE id = ? AND director_id = ? AND parlor_id = ?')->execute([$_GET['id'],$user['id'],$selectedParlor['id']]);
  header('Location: ' . BASE_PATH . '/director/inventory.php'); exit;
}

$item = $item ?? null;
if (empty($error) && $action === 'edit' && isset($_GET['id'])) {
  $stmt = $pdo->prepare('SELECT * FROM inventory_resources WHERE id = ? AND director_id = ? AND parlor_id = ?');
  $stmt->execute([$_GET['id'],$user['id'],$selectedParlor['id']]);
  $item = $stmt->fetch();
}

include __DIR__ . '/../includes/header.php';

?>
<div class="max-w-xl mx-auto w-full px-4 sm:px-6">
  <section class="rounded-[16px] border border-stone-200 bg-white/80 p-6 shadow-sm">
<?php render_catalog_back_link(BASE_PATH . '/director/inventory.php', 'Back to inventory'); ?>
    <h2 class="text-2xl font-semibold text-funeral-800"><?=($item? 'Edit' : 'Add')?> Resource</h2>
    <p class="mt-1 text-sm text-stone-600">Add or update inventory items used for funeral coordination.</p>

    <?php if (!empty($error)): ?><div role="alert" id="inventory-errors" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="space-y-4 mt-4">
      <?= csrf_field() ?>
      <?php if($item): ?><input type="hidden" name="id" value="<?=$item['id']?>" /><?php endif; ?>

      <div>
        <label class="block text-sm font-medium text-stone-700" for="field-director-inventory-action-php-1">Name</label>
        <input id="field-director-inventory-action-php-1" name="name" maxlength="200" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" value="<?=htmlspecialchars($item['name'] ?? '')?>" required />
      </div>

      <div>
        <label class="block text-sm font-medium text-stone-700" for="field-director-inventory-action-php-2">Photo</label>
        <?php if (!empty($item['image_path'])): ?>
          <div class="mt-2 flex items-center gap-3">
            <img src="<?= htmlspecialchars(BASE_PATH . '/' . ltrim($item['image_path'], '/')) ?>" alt="Current resource photo" class="h-16 w-16 rounded-lg border border-stone-200 object-cover" />
            <span class="text-xs text-stone-500">Current photo — upload a new one below to replace it.</span>
          </div>
        <?php endif; ?>
        <input id="field-director-inventory-action-php-2" name="image" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 w-full rounded-xl border border-dashed border-stone-300 bg-stone-50 px-3 py-3 text-sm" />
        <p class="mt-1 text-xs text-stone-500">Optional. PNG, JPG, or WEBP.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-stone-700" for="field-director-inventory-action-php-3">Type</label>
        <select id="field-director-inventory-action-php-3" name="item_type" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3">
          <option value="flower" <?=(!empty($item['item_type']) && $item['item_type']==='flower')? 'selected':''?>>Flowers</option>
          <option value="candle" <?=(!empty($item['item_type']) && $item['item_type']==='candle')? 'selected':''?>>Candles</option>
          <option value="plant" <?=(!empty($item['item_type']) && $item['item_type']==='plant')? 'selected':''?>>Plants</option>
          <option value="casket" <?=(!empty($item['item_type']) && $item['item_type']==='casket')? 'selected':''?>>Caskets and urns</option>
          <option value="equipment" <?=(!empty($item['item_type']) && $item['item_type']==='equipment')? 'selected':''?>>Equipment</option>
          <option value="other" <?=(!empty($item['item_type']) && $item['item_type']==='other')? 'selected':''?>>Other supplies</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-stone-700" for="field-director-inventory-action-php-4">Details</label>
        <textarea id="field-director-inventory-action-php-4" name="details" rows="4" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3"><?=htmlspecialchars($item['details'] ?? '')?></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-stone-700" for="quantityInput">Quantity on hand</label>
        <input name="quantity" <?= !empty($quantityError) ? 'aria-invalid="true" aria-describedby="inventory-errors"' : '' ?> id="quantityInput" type="number" min="0" max="2147483647" step="1" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" value="<?=htmlspecialchars((string)($item['quantity'] ?? 0))?>" required />
      </div>

      <div>
        <label class="block text-sm font-medium text-stone-700" for="statusSelect">Availability</label>
        <?php $currentStatus = $item['available_status'] ?? derive_inventory_status((int)($item['quantity'] ?? 0)); ?>
        <select name="available_status" id="statusSelect" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3">
          <option value="available" <?= $currentStatus === 'available' ? 'selected' : '' ?>>Available</option>
          <option value="insufficient" <?= $currentStatus === 'insufficient' ? 'selected' : '' ?>>Insufficient</option>
          <option value="missing" disabled <?= $currentStatus === 'missing' ? 'selected' : '' ?>>Missing (automatic at 0 quantity)</option>
        </select>
        <p class="mt-1 text-xs text-stone-500">Defaults to matching your quantity, but you can mark stock "Insufficient" yourself (e.g. reserved or damaged) even if the count is fine. At 0 quantity this always shows Missing.</p>
      </div>

      <div class="flex items-center gap-3">
        <button class="inline-flex items-center justify-center rounded-full bg-funeral-600 px-5 py-2 text-sm font-semibold text-white shadow">Save Resource</button>
        <a href="<?= BASE_PATH ?>/director/inventory.php" class="inline-flex items-center rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300">Cancel</a>
      </div>
    </form>
  </section>
</div>

<script>
  const quantityInput = document.getElementById('quantityInput');
  const statusSelect = document.getElementById('statusSelect');
  const missingOption = statusSelect.querySelector('option[value="missing"]');
  function syncStatusToQuantity() {
    const isZero = Number(quantityInput.value) <= 0;
    missingOption.disabled = !isZero;
    if (isZero) statusSelect.value = 'missing';
    else if (statusSelect.value === 'missing') statusSelect.value = 'available';
  }
  quantityInput.addEventListener('input', syncStatusToQuantity);
  syncStatusToQuantity();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
