<?php
// Maintenance commands must never execute through a browser request.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Seed default users: admin, director, client using DEFAULT_USER_PASSWORD
try {
    $pdo = pdo_connect();
    $passwordHash = password_hash(DEFAULT_USER_PASSWORD, PASSWORD_DEFAULT);

    $users = [
        ['full_name'=>'System Administrator','email'=>'admin@example.com','phone'=>'000-000-0000','role'=>'admin','status'=>'approved'],
        ['full_name'=>'Lead Director','email'=>'director@example.com','phone'=>'111-111-1111','role'=>'director','status'=>'approved'],
        ['full_name'=>'Sample Client','email'=>'client@example.com','phone'=>'222-222-2222','role'=>'client','status'=>'approved'],
    ];

    foreach ($users as $u) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$u['email']]);
        if ($stmt->fetch()) {
            echo "User {$u['email']} already exists, skipping.\n";
            continue;
        }
        $ins = $pdo->prepare('INSERT INTO users (full_name,email,phone,password,role,status,created_at) VALUES (?,?,?,?,?,?,NOW())');
        $ins->execute([$u['full_name'],$u['email'],$u['phone'],$passwordHash,$u['role'],$u['status']]);
        echo "Created user: {$u['email']} with password: " . DEFAULT_USER_PASSWORD . "\n";
    }
    echo "Seeding complete. Please change default passwords after first login.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
