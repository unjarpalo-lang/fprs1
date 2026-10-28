<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['admin']);
$pdo = pdo_connect();
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token($_POST['token'] ?? null);
    $name = request_text($_POST, 'name');
    $location = request_text($_POST, 'location');
    $contact = request_text($_POST, 'contact_info');
    $desc = request_text($_POST, 'description');
    if ($_POST['id'] ?? false) {
        $pdo->prepare('UPDATE shops SET name=?,location=?,contact_info=?,description=? WHERE id=?')->execute([$name,$location,$contact,$desc,$_POST['id']]);
    } else {
        $pdo->prepare('INSERT INTO shops (admin_id,name,location,contact_info,description) VALUES (?,?,?,?,?)')->execute([$_SESSION['user_id'],$name,$location,$contact,$desc]);
    }
  header('Location: ' . BASE_PATH . '/admin/shops.php'); exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
  require_csrf_token($_GET['token'] ?? null);
  $pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$_GET['id']]); header('Location: ' . BASE_PATH . '/admin/shops.php'); exit;
}

$shop = null;
if ($action === 'edit' && isset($_GET['id'])) { $stmt = $pdo->prepare('SELECT * FROM shops WHERE id = ?'); $stmt->execute([$_GET['id']]); $shop = $stmt->fetch(); }

// include header for rendering
include __DIR__ . '/../includes/header.php';

?>
<div class="f-card mx-auto max-w-xl p-6 sm:p-8">
<?php render_catalog_back_link(BASE_PATH . '/admin/shops.php', 'Back to shops'); ?>
  <h2 class="text-2xl font-semibold text-funeral-800"><?=($shop? 'Edit' : 'Add')?> Shop</h2>
  <form method="post" class="space-y-3 mt-3">
      <?= csrf_field() ?>
    <?php if($shop): ?><input type="hidden" name="id" value="<?=$shop['id']?>" /><?php endif; ?>
    <div><label for="field-admin-shop-action-php-1">Name</label><input id="field-admin-shop-action-php-1" name="name" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3" value="<?=htmlspecialchars($shop['name'] ?? '')?>" required /></div>
    <div><label for="field-admin-shop-action-php-2">Location</label><input id="field-admin-shop-action-php-2" name="location" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3" value="<?=htmlspecialchars($shop['location'] ?? '')?>" /></div>
    <div><label for="field-admin-shop-action-php-3">Contact</label><input id="field-admin-shop-action-php-3" name="contact_info" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3" value="<?=htmlspecialchars($shop['contact_info'] ?? '')?>" /></div>
    <div><label for="field-admin-shop-action-php-4">Description</label><textarea id="field-admin-shop-action-php-4" name="description" class="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3"><?=htmlspecialchars($shop['description'] ?? '')?></textarea></div>
    <div><button class="rounded-full bg-funeral-600 px-5 py-3 font-semibold text-white">Save</button></div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
