<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/migrate_service_feedback.php';
try {
    $pdo = pdo_connect();
    $columns = $pdo->query("SELECT COLUMN_NAME,DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='service_inquiries'")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!isset($columns['confirmed_at'])) $pdo->exec('ALTER TABLE service_inquiries ADD COLUMN confirmed_at TIMESTAMP NULL DEFAULT NULL');
    if (!isset($columns['confirmed_by'])) $pdo->exec('ALTER TABLE service_inquiries ADD COLUMN confirmed_by INT NULL');
    $ratingType = $pdo->query("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='package_feedback' AND COLUMN_NAME='rating'")->fetchColumn();
    if ($ratingType !== 'decimal') $pdo->exec('ALTER TABLE package_feedback MODIFY COLUMN rating DECIMAL(4,2) NOT NULL');
    echo "Director confirmation and questionnaire storage are ready. Existing reviews were preserved.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Questionnaire migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
