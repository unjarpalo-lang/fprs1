<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['director']);
$pdo = pdo_connect();
$user = current_user();
ensure_director_scope_columns($pdo);
ensure_feedback_and_media_columns($pdo);
ensure_package_inclusion_images($pdo);
$selectedParlor = selected_director_parlor($pdo, (int)$user['id']);
if (!$selectedParlor) { header('Location: ' . BASE_PATH . '/director/funeral_parlors.php'); exit; }
$action = $_GET['action'] ?? 'list';

/** Validate + normalize one uploaded inclusion-item image; null if none/invalid provided. */
function save_inclusion_image(?array $file): ?string {
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if (upload_too_large($file)) return null;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) return null;
    $filename = uniqid('inclusion_', true) . '.' . $allowed[$mime];
    move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $filename);
    return 'uploads/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token($_POST['token'] ?? null);
    $title = trim(request_text($_POST, 'title'));
    $rawPrice = $_POST['price'] ?? '';
    $price = validated_package_price($rawPrice);
    $validationError = $price === null ? 'Price must be between 0 and 99,999,999.99 PHP, with at most two decimal places.' : '';
    if ($title === '' || text_length($title) > 200) $validationError = 'Package title is required and must not exceed 200 characters.';
    if ($validationError !== '') http_response_code(422);
    $serviceNames = $_POST['service_names'] ?? [];
    $serviceDetails = $_POST['service_details'] ?? [];
    $serviceDescriptions = $_POST['service_descriptions'] ?? [];
    $existingImagePaths = $_POST['existing_image_paths'] ?? [];
    if (!is_array($serviceNames)) {
        $serviceNames = [$serviceNames];
    }
    if (!is_array($serviceDetails)) {
        $serviceDetails = [$serviceDetails];
    }
    if (!is_array($serviceDescriptions)) {
        $serviceDescriptions = [$serviceDescriptions];
    }
    if (!is_array($existingImagePaths)) {
        $existingImagePaths = [$existingImagePaths];
    }
    $selectedServices = [];
    $inclusionRows = []; // [service_name, service_detail, description, image_path] — feeds package_inclusions
    foreach ($serviceNames as $index => $serviceName) {
        $serviceName = trim(request_text(['value' => $serviceName], 'value'));
        $serviceDetail = trim(request_text($serviceDetails, (string)$index));
        $serviceDescription = trim(request_text($serviceDescriptions, (string)$index));
        if ($serviceName && $serviceDetail) {
            $selectedServices[] = $serviceName . ' - ' . $serviceDetail;
            $rowFile = isset($_FILES['service_images']['name'][$index]) ? [
                'name' => $_FILES['service_images']['name'][$index],
                'type' => $_FILES['service_images']['type'][$index],
                'tmp_name' => $_FILES['service_images']['tmp_name'][$index],
                'error' => $_FILES['service_images']['error'][$index],
                'size' => $_FILES['service_images']['size'][$index],
            ] : null;
            $newImage = $validationError === '' ? save_inclusion_image($rowFile) : null;
            $inclusionRows[] = [$serviceName, $serviceDetail, $serviceDescription, $newImage ?? (request_text($existingImagePaths, (string)$index) ?: null)];
        }
    }
    $inclusions = implode(', ', $selectedServices);
    // Never store a negative or non-numeric price: it would be shown to
    // clients as a negative peso amount and would skew budget matching.
    // The exact decimal value above is validated before uploads or database writes.

    $imagePath = null;
    $existingId = request_text($_POST, 'id') ?: null;
    if ($existingId) {
        $imgStmt = $pdo->prepare('SELECT image_path FROM packages WHERE id = ? AND director_id = ? AND parlor_id = ?');
        $imgStmt->execute([$existingId, $user['id'], $selectedParlor['id']]);
        $imagePath = $imgStmt->fetchColumn() ?: null;
    }
    $uploadError = $validationError;
    if ($uploadError === '' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image']['tmp_name']);
        $allowedImages = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (upload_too_large($_FILES['image'])) {
            $uploadError = 'Package image must be 5 MB or smaller.';
        } elseif (!isset($allowedImages[$mime])) {
            $uploadError = 'Package image must be a PNG, JPG, or WEBP file.';
        } else {
            $filename = uniqid('package_', true) . '.' . $allowedImages[$mime];
            move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../uploads/' . $filename);
            $imagePath = 'uploads/' . $filename;
        }
    } elseif (isset($_FILES['image']) && !in_array($_FILES['image']['error'], [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
        $uploadError = 'Image upload failed. Please try again.';
    }

    $ownsExistingPackage = true;
    if ($existingId) {
        $ownStmt = $pdo->prepare('SELECT 1 FROM packages WHERE id = ? AND director_id = ? AND parlor_id = ?');
        $ownStmt->execute([$existingId, $user['id'], $selectedParlor['id']]);
        $ownsExistingPackage = (bool)$ownStmt->fetchColumn();
    }

    if ($uploadError) {
        $error = $uploadError;
        $pkg = ['id' => $existingId, 'title' => $title, 'inclusions' => $inclusions, 'price' => is_scalar($rawPrice) ? (string)$rawPrice : '', 'image_path' => $imagePath];
    } elseif (!$ownsExistingPackage) {
        // Ownership check BEFORE touching package_inclusions — without this, a
        // forged "id" for a package that isn't this director's would still let
        // the inclusions DELETE+INSERT below overwrite someone else's rows,
        // even though the packages UPDATE itself is correctly scoped and would
        // silently affect zero rows.
        http_response_code(403);
        exit('You do not have permission to edit this package.');
    } else {
        $pdo->beginTransaction();
        try {
            if ($existingId) {
                $stmt = $pdo->prepare('UPDATE packages SET title=?,inclusions=?,price=?,image_path=? WHERE id=? AND director_id=? AND parlor_id=?');
                $stmt->execute([$title,$inclusions,$price,$imagePath,$existingId,$user['id'],$selectedParlor['id']]);
                $packageId = (int)$existingId;
            } else {
                $stmt = $pdo->prepare('INSERT INTO packages (title,inclusions,price,image_path,director_id,parlor_id) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$title,$inclusions,$price,$imagePath,$user['id'],$selectedParlor['id']]);
                $packageId = (int)$pdo->lastInsertId();
            }
            // Simplest consistent approach for a repeatable row form: replace every
            // inclusion row on each save rather than diffing individual edits.
            $pdo->prepare('DELETE FROM package_inclusions WHERE package_id = ?')->execute([$packageId]);
            $insertInclusion = $pdo->prepare('INSERT INTO package_inclusions (package_id, service_name, service_detail, description, image_path, sort_order) VALUES (?,?,?,?,?,?)');
            foreach ($inclusionRows as $order => [$rowName, $rowDetail, $rowDescription, $rowImage]) {
                $insertInclusion->execute([$packageId, $rowName, $rowDetail, $rowDescription ?: null, $rowImage, $order]);
            }
            $pdo->commit();
            header('Location: ' . BASE_PATH . '/director/packages.php'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Package save failed.');
            $error = 'The package could not be saved. Please try again.';
            $pkg = ['id' => $existingId, 'title' => $title, 'inclusions' => $inclusions, 'price' => $price, 'image_path' => $imagePath];
        }
    }
}
if ($action === 'delete' && isset($_GET['id'])) {
    require_csrf_token($_GET['token'] ?? null);
    $stmt = $pdo->prepare('DELETE FROM packages WHERE id = ? AND director_id = ? AND parlor_id = ?');
    $stmt->execute([$_GET['id'],$user['id'],$selectedParlor['id']]);
    header('Location: ' . BASE_PATH . '/director/packages.php'); exit;
}

$pkg = $pkg ?? null;
$savedInclusionRows = !empty($error) ? array_map(static fn($r) => ['service_name'=>$r[0], 'service_detail'=>$r[1], 'description'=>$r[2], 'image_path'=>$r[3]], $inclusionRows) : [];
if (empty($error) && $action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM packages WHERE id = ? AND director_id = ? AND parlor_id = ?'); $stmt->execute([$_GET['id'],$user['id'],$selectedParlor['id']]); $pkg = $stmt->fetch();
    if ($pkg) {
        $incStmt = $pdo->prepare('SELECT service_name, service_detail, description, image_path FROM package_inclusions WHERE package_id = ? ORDER BY sort_order ASC, id ASC');
        $incStmt->execute([$pkg['id']]);
        $savedInclusionRows = $incStmt->fetchAll();
    }
}

include __DIR__ . '/../includes/header.php';

?>
<div class="max-w-xl mx-auto w-full px-4 sm:px-6">
    <section class="rounded-[16px] border border-stone-200 bg-white/80 p-6 shadow-sm">
<?php render_catalog_back_link(BASE_PATH . '/director/packages.php', 'Back to packages'); ?>
        <h2 class="text-2xl font-semibold text-funeral-800"><?= ($pkg ? 'Edit Funeral Package' : 'Create Funeral Package') ?></h2>
        <p class="mt-1 text-sm text-stone-600">Define a package title, included services, price, and an image.</p>

        <?php if (!empty($error)): ?><div role="alert" id="package-errors" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="space-y-4 mt-4">
      <?= csrf_field() ?>
            <?php if($pkg): ?><input type="hidden" name="id" value="<?=$pkg['id']?>" /><?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-stone-700" for="field-director-package-action-php-1">Package Title</label>
                <input id="field-director-package-action-php-1" name="title" maxlength="200" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" value="<?=htmlspecialchars($pkg['title'] ?? '')?>" required aria-required="true" />
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700" for="field-director-package-action-php-2">Package Image</label>
                <?php if (!empty($pkg['image_path'])): ?>
                  <div class="mt-2 flex items-center gap-3">
                    <img src="<?= htmlspecialchars(BASE_PATH . '/' . ltrim($pkg['image_path'], '/')) ?>" alt="Current package image" class="h-16 w-16 rounded-lg border border-stone-200 object-cover" />
                    <span class="text-xs text-stone-500">Current image — upload a new one below to replace it.</span>
                  </div>
                <?php endif; ?>
                <input id="field-director-package-action-php-2" name="image" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 w-full rounded-xl border border-dashed border-stone-300 bg-stone-50 px-3 py-3 text-sm" />
                <p class="mt-1 text-xs text-stone-500">Optional. Shown to families browsing this package — e.g. a photo of the casket or flower arrangement. PNG, JPG, or WEBP.</p>
            </div>

            <div>
                <p class="block text-sm font-medium text-stone-700">Included Services</p>
                <p class="mt-1 text-xs text-stone-500">Each inclusion can carry its own photo (e.g. the actual casket or flower arrangement) — families see it right on the item, not just the package cover.</p>
                                <?php
                                    $serviceOptions = [
                                        'Casket' => ['Wood', 'Metal', 'Premium'],
                                        'Flowers' => ['Basic arrangement', 'Premium arrangement', 'Custom arrangement'],
                                        'Documentation' => ['Basic assistance', 'Complete assistance'],
                                        'Embalming and preparation' => ['Standard', 'Premium preparation'],
                                        'Viewing and wake setup' => ['Basic setup', 'Full setup'],
                                        'Hearse and transportation' => ['Local', 'Extended distance'],
                                        'Funeral ceremony coordination' => ['Basic coordination', 'Full coordination'],
                                        'Burial or cremation coordination' => ['Burial', 'Cremation'],
                                        'Memorial program printing' => ['Basic', 'Premium'],
                                        'Catering and refreshments' => ['Light refreshments', 'Full catering'],
                                        'Other service' => ['Custom service']
                                    ];
                                    $initialRows = $savedInclusionRows ?: [['service_name' => '', 'service_detail' => '', 'description' => '', 'image_path' => null]];
                                ?>
                                <div id="serviceRows" class="mt-2 space-y-3">
                                    <?php foreach ($initialRows as $row): ?>
                                    <div class="service-row rounded-xl border border-stone-200 bg-stone-50/70 p-3">
                                      <div class="flex flex-col gap-2 sm:flex-row">
                                        <select name="service_names[]" aria-label="Included service" class="service-name min-w-0 flex-1 rounded-xl border border-stone-300 bg-white px-4 py-3" required>
                                          <option value="">Choose a service</option>
                                          <?php foreach ($serviceOptions as $service => $details): ?>
                                            <option value="<?= htmlspecialchars($service) ?>" <?= $row['service_name'] === $service ? 'selected' : '' ?>><?= htmlspecialchars($service) ?></option>
                                          <?php endforeach; ?>
                                        </select>
                                        <select name="service_details[]" aria-label="Service option" class="service-detail min-w-0 flex-1 rounded-xl border border-stone-300 bg-white px-4 py-3" data-selected="<?= htmlspecialchars($row['service_detail']) ?>" required></select>
                                        <button type="button" class="remove-service shrink-0 rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">Remove</button>
                                      </div>
                                      <input type="text" name="service_descriptions[]" aria-label="Service description" value="<?= htmlspecialchars($row['description'] ?? '') ?>" maxlength="500" placeholder="Describe this item — e.g. &quot;White lily and rose wreath, standing arrangement&quot;" class="mt-2 w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
                                      <div class="mt-2 flex items-center gap-3">
                                        <?php if (!empty($row['image_path'])): ?>
                                          <img src="<?= htmlspecialchars(BASE_PATH . '/' . ltrim($row['image_path'], '/')) ?>" alt="" class="row-thumb h-12 w-12 shrink-0 rounded-lg border border-stone-200 object-cover" />
                                        <?php else: ?>
                                          <div class="row-thumb flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-dashed border-stone-300 bg-white text-stone-300">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
                                          </div>
                                        <?php endif; ?>
                                        <input type="hidden" name="existing_image_paths[]" value="<?= htmlspecialchars($row['image_path'] ?? '') ?>" />
                                        <input type="file" name="service_images[]" aria-label="Service image" accept="image/png,image/jpeg,image/webp" class="row-image-input min-w-0 flex-1 rounded-lg border border-dashed border-stone-300 bg-white px-3 py-2 text-xs" />
                                      </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" id="addService" class="mt-3 rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300">Add another service</button>
                                <p class="mt-1 text-xs text-stone-500">Choose a service, then choose its included option. For example: Casket - Wood.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700" for="field-director-package-action-php-4">Price (PHP)</label>
                <input id="field-director-package-action-php-4" name="price" <?= !empty($validationError) ? 'aria-invalid="true"' : '' ?> type="number" min="0" max="99999999.99" step="0.01" required aria-describedby="package-price-help<?= !empty($error) ? ' package-errors' : '' ?>" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" value="<?=htmlspecialchars($pkg['price'] ?? 0)?>" aria-label="price" />
                <p id="package-price-help" class="mt-1 text-xs text-stone-500">Enter 0 for “Contact for pricing”, or an amount up to 99,999,999.99 PHP (two decimal places).</p>
            </div>

            <div class="flex items-center gap-3">
                <button class="inline-flex items-center justify-center rounded-full bg-funeral-600 px-5 py-2 text-sm font-semibold text-white shadow transition hover:bg-funeral-500">Save Package</button>
                <a href="<?= BASE_PATH ?>/director/packages.php" class="inline-flex items-center rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300">Cancel</a>
            </div>
        </form>
    </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const serviceOptions = <?= json_encode($serviceOptions) ?>;
const serviceRows = document.getElementById('serviceRows');

function updateServiceDetails(row) {
    const service = row.querySelector('.service-name').value;
    const detail = row.querySelector('.service-detail');
    const selected = detail.dataset.selected || '';
    detail.innerHTML = '<option value="">Choose an option</option>' + (serviceOptions[service] || []).map(option => `<option value="${option.replace(/"/g, '&quot;')}"${option === selected ? ' selected' : ''}>${option}</option>`).join('');
    detail.dataset.selected = '';
}

function bindServiceRow(row) {
    row.querySelector('.service-name').addEventListener('change', () => updateServiceDetails(row));
    row.querySelector('.remove-service').addEventListener('click', () => {
        if (serviceRows.children.length > 1) row.remove();
    });
    updateServiceDetails(row);
}

document.querySelectorAll('.service-row').forEach(bindServiceRow);
document.getElementById('addService').addEventListener('click', () => {
    const row = serviceRows.firstElementChild.cloneNode(true);
    row.querySelector('.service-name').value = '';
    row.querySelector('.service-detail').dataset.selected = '';
    row.querySelector('input[name="service_descriptions[]"]').value = '';
    row.querySelector('input[name="existing_image_paths[]"]').value = '';
    const fileInput = row.querySelector('.row-image-input');
    fileInput.value = ''; // file inputs don't carry their value through cloneNode anyway, but be explicit
    row.querySelector('.row-thumb').outerHTML = `<div class="row-thumb flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-dashed border-stone-300 bg-white text-stone-300">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
    </div>`;
    serviceRows.appendChild(row);
    bindServiceRow(row);
});
</script>
