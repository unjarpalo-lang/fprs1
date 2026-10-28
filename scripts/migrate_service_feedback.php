<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../config/db.php';
try {
    $pdo = pdo_connect();
    $sql = file_get_contents(__DIR__ . '/../sql/20261020_service_feedback.sql');
    if ($sql === false) throw new RuntimeException('Migration file unavailable.');
    $pdo->exec($sql);
    echo "Service inquiry and package feedback tables are ready. Existing records were preserved.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
