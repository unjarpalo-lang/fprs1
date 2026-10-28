<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['director']);
$pdo = pdo_connect();
$user = current_user();
$error = '';
$success = '';
$postedId = request_text($_POST, 'id', '0');
$parlorId = (int)request_text($_GET, 'id', $postedId);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'], $_POST['id']) && (int)$postedId !== $parlorId) {
    http_response_code(400); exit('Invalid parlor selection.');
}
$existing = null;
if ($parlorId) {
  $stmt = $pdo->prepare('SELECT * FROM funeral_parlors WHERE id = ? AND director_id = ?');
  $stmt->execute([$parlorId, $user['id']]);
  $existing = $stmt->fetch();
  if (!$existing) { http_response_code(404); exit('Funeral parlor not found.'); }
  $selectedParlor = selected_director_parlor($pdo, (int)$user['id']);
  if (!$selectedParlor || (int)$selectedParlor['id'] !== $parlorId) {
      if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(403); exit('This parlor is not selected.'); }
      header('Location: ' . BASE_PATH . '/director/funeral_parlors.php'); exit;
  }
}

$iloiloLocations = require __DIR__ . '/../includes/iloilo_locations.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 48 * 1024 * 1024) {
        http_response_code(413); exit('License file must be 40 MB or smaller.');
    }
    require_csrf_token($_POST['token'] ?? null);
    $name = trim(request_text($_POST, 'name'));
    $municipality = trim(request_text($_POST, 'municipality'));
    $barangay = trim(request_text($_POST, 'barangay'));
    $location = ($barangay && $municipality) ? "{$barangay}, {$municipality}" : '';
    $contact = trim(request_text($_POST, 'contact_number'));
    $license = $_FILES['license'] ?? null;
    $hasNewLicense = $license && $license['error'] === UPLOAD_ERR_OK;
    if (text_length($name) > 200 || text_length($contact) > 50) {
      $error = 'Parlor name must be at most 200 characters; contact number at most 50.';
      http_response_code(422);
    } elseif ($license && (in_array($license['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($hasNewLicense && upload_too_large($license, MAX_PARLOR_LICENSE_BYTES)))) {
      $error = 'License file must be 40 MB or smaller.';
      http_response_code(413);
    } elseif ($license && !in_array($license['error'], [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
      $error = 'License upload failed. Please try again.';
    } elseif (!$name || !$location || !$contact || ((!$existing || private_document_path($existing['license_path'] ?? null) === null) && !$hasNewLicense)) {
      $error = 'Name, location, contact number, and a license picture are required.';
    } else {
      // Extension is derived from the sniffed MIME type, never the user-supplied
      // filename — trusting an attacker-chosen filename's extension (even after
      // a content check) can let a disguised script land in uploads/ as .php.
      $allowedLicenseExtensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'];
      $mime = $hasNewLicense ? (new finfo(FILEINFO_MIME_TYPE))->file($license['tmp_name']) : null;
      if ($hasNewLicense && !isset($allowedLicenseExtensions[$mime])) {
            $error = 'License must be a PNG, JPG, or PDF file.';
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS funeral_parlors (id INT AUTO_INCREMENT PRIMARY KEY, director_id INT NOT NULL, name VARCHAR(200) NOT NULL, location VARCHAR(255) NOT NULL, contact_number VARCHAR(50) NOT NULL, license_path VARCHAR(255) NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE CASCADE)");
            $licensePath = $existing['license_path'] ?? null;
            if ($hasNewLicense) {
              $filename = uniqid('parlor_license_', true) . '.' . $allowedLicenseExtensions[$mime];
              if (!move_uploaded_file($license['tmp_name'], __DIR__ . '/../uploads/' . $filename)) {
                $error = 'License upload could not be saved. Please try again.';
              } else {
                $licensePath = 'uploads/' . $filename;
              }
            }
            // Changed business details or a replacement license require review.
            // Saving identical values must not revoke an existing approval.
            $inScope = is_in_service_area($location);
            if ($error === '') {
            if ($existing) {
              $detailsChanged = $name !== $existing['name']
                  || $location !== $existing['location']
                  || $contact !== $existing['contact_number']
                  || $licensePath !== $existing['license_path'];
              if ($detailsChanged || !$inScope) {
                $stmt = $pdo->prepare("UPDATE funeral_parlors SET name=?,location=?,contact_number=?,license_path=?,status='pending' WHERE id=? AND director_id=?");
                $stmt->execute([$name, $location, $contact, $licensePath, $parlorId, $user['id']]);
                $existing = array_merge($existing, ['name' => $name, 'location' => $location, 'contact_number' => $contact, 'license_path' => $licensePath, 'status' => 'pending']);
                $success = $inScope
                    ? 'Changes submitted for admin approval.'
                    : 'Changes submitted. This parlor remains pending and hidden from clients because its location is outside the service area or its barangay is unrecognized.';
              } else {
                $success = 'No changes to save.';
              }
            } else {
              $stmt = $pdo->prepare('INSERT INTO funeral_parlors (director_id,name,location,contact_number,license_path,status) VALUES (?,?,?,?,?,?)');
              $stmt->execute([$user['id'], $name, $location, $contact, $licensePath, 'pending']);
              $success = 'Funeral parlor submitted. An administrator will review it and set it to Approved, Pending, or Declined.';
            }
            }
        }
    }
}

$formValues = $existing ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
    $formValues = array_merge($formValues, ['name'=>$name, 'location'=>$location, 'contact_number'=>$contact]);
}
$prefillMunicipality = '';
$prefillBarangay = '';
if (!empty($formValues['location']) && str_contains($formValues['location'], ',')) {
    [$maybeBarangay, $maybeMunicipality] = array_map('trim', explode(',', $formValues['location'], 2));
    if (isset($iloiloLocations[$maybeMunicipality]) && in_array($maybeBarangay, $iloiloLocations[$maybeMunicipality], true)) {
        $prefillMunicipality = $maybeMunicipality;
        $prefillBarangay = $maybeBarangay;
    }
}

$backHref = BASE_PATH . ($existing ? '/director/dashboard.php' : '/director/funeral_parlors.php');
$backLabel = $existing ? 'Back to director dashboard' : 'Back to funeral parlors';
include __DIR__ . '/../includes/header.php';
?>
<div class="mx-auto max-w-2xl py-6 sm:py-10">
  <div class="f-card p-6 sm:p-8">
<?php render_catalog_back_link($backHref, $backLabel); ?>
    <p class="f-eyebrow mb-2">Director service</p>
    <h2 class="mb-2 text-3xl font-bold text-funeral-800"><?= $existing ? 'Manage Account' : 'Register a funeral parlor' ?></h2>
    <p class="mb-6 text-sm text-stone-600"><?= $existing ? 'Manage the details and business license for this parlor, then save your changes.' : 'Enter the parlor details and upload a business license to submit it for review.' ?></p>
    <?php if ($error): ?><div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="space-y-4">
      <?= csrf_field() ?>
      <?php if ($existing): ?><input type="hidden" name="id" value="<?= (int)$existing['id'] ?>" /><?php endif; ?>
      <div><label class="mb-1 block text-sm font-medium text-stone-700" for="field-director-funeral-register-php-1">Funeral parlor name</label><input id="field-director-funeral-register-php-1" name="name" maxlength="200" value="<?= htmlspecialchars($formValues['name'] ?? '') ?>" required class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" /></div>
      <?php if (!empty($existing['location']) && !$prefillMunicipality): ?>
        <p class="text-xs text-stone-500">Current: <?= htmlspecialchars($existing['location']) ?> — choose below to replace it.</p>
      <?php endif; ?>
      <?php render_location_fields($iloiloLocations, 'reg', $prefillMunicipality, $prefillBarangay, 'municipality', 'barangay', 'Location'); ?>
      <div><label class="mb-1 block text-sm font-medium text-stone-700" for="field-director-funeral-register-php-2">Contact number</label><input id="field-director-funeral-register-php-2" name="contact_number" maxlength="50" value="<?= htmlspecialchars($formValues['contact_number'] ?? '') ?>" required class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" /></div>
      <div><label for="parlor-business-license" class="mb-1 block text-sm font-medium text-stone-700">Parlor business license <?= $existing && private_document_path($existing['license_path'] ?? null) !== null ? '(optional replacement)' : '(required)' ?></label><input id="parlor-business-license" name="license" type="file" accept="image/png,image/jpeg,application/pdf" <?= $existing && private_document_path($existing['license_path'] ?? null) !== null ? '' : 'required' ?> class="w-full rounded-xl border border-dashed border-stone-300 bg-stone-50 px-3 py-3 text-sm" /></div>
      <p class="text-xs text-stone-500">PNG, JPG or PDF. Maximum 40 MB per file.</p>
      <div class="flex items-center gap-4 pt-1"><button class="rounded-full bg-funeral-600 px-5 py-2.5 font-semibold text-white shadow-sm transition hover:bg-funeral-500"><?= $existing ? 'Save Account' : 'Submit for approval' ?></button><a href="<?= htmlspecialchars($backHref, ENT_QUOTES) ?>" class="text-sm font-medium text-funeral-700 hover:text-funeral-600">Cancel</a></div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php location_dropdown_script($iloiloLocations, 'reg'); ?>
