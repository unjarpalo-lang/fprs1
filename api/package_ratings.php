<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = current_user();
if (!$user || $user['status'] !== 'approved' || $user['role'] !== 'client') {
    http_response_code(403); echo json_encode(['error'=>'forbidden']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); header('Allow: GET'); echo json_encode(['error'=>'unsupported']); exit;
}
try {
    $pdo = pdo_connect();
    $rows = $pdo->query("SELECT p.id, fp.location, summary.average_rating, summary.review_count FROM packages p
        JOIN funeral_parlors fp ON fp.id=p.parlor_id JOIN users u ON u.id=fp.director_id
        LEFT JOIN (SELECT i.package_id, AVG(f.rating) AS average_rating, COUNT(*) AS review_count
            FROM package_feedback f JOIN service_inquiries i ON i.id=f.inquiry_id GROUP BY i.package_id) summary ON summary.package_id=p.id
        WHERE fp.status='approved' AND u.status='approved'")->fetchAll();
    $ratings = [];
    foreach ($rows as $row) {
        if (is_in_service_area($row['location'])) $ratings[(int)$row['id']] = ['average'=>(float)($row['average_rating'] ?? 0), 'count'=>(int)($row['review_count'] ?? 0)];
    }
    echo json_encode(['ratings'=>(object)$ratings]);
} catch (Throwable $e) {
    error_log('Package rating refresh failed: ' . $e->getMessage());
    http_response_code(503); echo json_encode(['error'=>'temporarily_unavailable']);
}
