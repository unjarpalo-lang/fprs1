<?php
/** Idempotent inventory migration. CLI only. Back up the target database first. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../config/db.php';
$database = $argv[1] ?? DB_NAME;
if ($database !== DB_NAME && !preg_match('/^fprs_qa_[a-z0-9_]+$/D', $database)) throw new RuntimeException('Unsupported migration target.');
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.$database.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$columns = [];
foreach ($pdo->query("SELECT COLUMN_NAME,COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_resources'")->fetchAll() as $c) $columns[$c['COLUMN_NAME']]=$c['COLUMN_TYPE'];
if (!$columns) throw new RuntimeException('Inventory table is missing; install the base schema first.');
if (!isset($columns['image_path'])) $pdo->exec('ALTER TABLE inventory_resources ADD COLUMN image_path VARCHAR(255) NULL');
if (!isset($columns['quantity'])) $pdo->exec('ALTER TABLE inventory_resources ADD COLUMN quantity INT NOT NULL DEFAULT 0');
foreach (['director_id','parlor_id'] as $column) if (!isset($columns[$column])) $pdo->exec("ALTER TABLE inventory_resources ADD COLUMN {$column} INT NULL");
$desiredStatus = "enum('available','insufficient','missing')";
if ($columns['available_status'] !== $desiredStatus) {
    $unexpected = $pdo->query("SELECT COUNT(*) FROM inventory_resources WHERE available_status IS NULL OR available_status NOT IN ('available','insufficient','missing','unavailable','')")->fetchColumn();
    if ($unexpected) throw new RuntimeException('Unexpected status values; review data before migrating.');
    $pdo->exec("ALTER TABLE inventory_resources MODIFY available_status ENUM('available','insufficient','missing','unavailable') NOT NULL DEFAULT 'available'");
    $pdo->exec("UPDATE inventory_resources SET available_status='missing' WHERE available_status IN ('unavailable','')");
    $pdo->exec("ALTER TABLE inventory_resources MODIFY available_status ENUM('available','insufficient','missing') NOT NULL DEFAULT 'available'");
}
$desiredTypes = "enum('flower','candle','plant','casket','equipment','other')";
if ($columns['item_type'] !== $desiredTypes) {
    $unexpected = $pdo->query("SELECT COUNT(*) FROM inventory_resources WHERE item_type IS NULL OR item_type NOT IN ('flower','candle','plant','casket','equipment','other')")->fetchColumn();
    if ($unexpected) throw new RuntimeException('Unexpected item types; review data before migrating.');
    $pdo->exec("ALTER TABLE inventory_resources MODIFY item_type ENUM('flower','candle','plant','casket','equipment','other') NOT NULL");
}
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
$pdo->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)')->execute(['20261020_inventory_schema']);
echo "Inventory migration verified for {$database}.\n";
