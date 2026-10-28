<?php
// Maintenance commands must never execute through a browser request.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $pdo = pdo_connect();
    $passwordHash = password_hash(DEFAULT_USER_PASSWORD, PASSWORD_DEFAULT);

    $emails = ['admin@example.com','director@example.com','client@example.com'];
    $upd = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
    foreach ($emails as $e) {
        $upd->execute([$passwordHash, $e]);
        echo "Updated password for: {$e}\n";
    }
    echo "Password reset complete.\n";
} catch (PDOException $ex) {
    echo "Error: " . $ex->getMessage() . "\n";
    exit(1);
}

?>
