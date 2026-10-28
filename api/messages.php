<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = pdo_connect();

// The signed-in user's own id — NEVER trust a "user" id from the query
// string here. Previously this endpoint read $_GET['user'] directly with no
// session check at all, letting anyone read any other user's private
// messages just by requesting ?user=<id>. Every message lookup below must
// be scoped to the authenticated session, not to client-supplied input.
$authenticatedUserId = $_SESSION['user_id'] ?? null;
$authenticatedUser = $authenticatedUserId ? current_user() : null;
if (!$authenticatedUser || $authenticatedUser['status'] !== 'approved') {
    http_response_code(403);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

// GET => fetch recent messages for the signed-in user, optionally filtered to one peer
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = $authenticatedUserId;
    $peer = isset($_GET['peer']) ? (int)$_GET['peer'] : null;
    if ($peer) {
        $stmt = $pdo->prepare('SELECT m.*, s.full_name as sender_name FROM messages m LEFT JOIN users s ON s.id = m.sender_id WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) ORDER BY m.sent_at ASC');
        $stmt->execute([$user,$peer,$peer,$user]);
        $rows = $stmt->fetchAll();
        echo json_encode($rows); exit;
    } else {
        $stmt = $pdo->prepare('SELECT m.*, s.full_name as sender_name FROM messages m LEFT JOIN users s ON s.id = m.sender_id WHERE sender_id = ? OR receiver_id = ? ORDER BY sent_at DESC, m.id DESC LIMIT 200');
        $stmt->execute([$user,$user]);
        $rows = array_reverse($stmt->fetchAll());
        echo json_encode($rows); exit;
    }
}

// POST => send message as the signed-in user, to a receiver `to`
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !is_string($body['text'] ?? $body['message'] ?? '')) {
        http_response_code(400); echo json_encode(['error' => 'invalid_message']); exit;
    }
    $from = $authenticatedUserId;
    $to = isset($body['to']) ? (int)$body['to'] : null;
    $text = trim($body['text'] ?? $body['message'] ?? '');
    if (!$text) { http_response_code(400); echo json_encode(['error'=>'empty_message']); exit; }
    if (!$to) { http_response_code(400); echo json_encode(['error'=>'missing_receiver']); exit; }
    $receiver = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $receiver->execute([$to]);
    if (!$receiver->fetchColumn()) { http_response_code(400); echo json_encode(['error'=>'invalid_receiver']); exit; }
    $stmt = $pdo->prepare('INSERT INTO messages (sender_id,receiver_id,message_text) VALUES (?,?,?)');
    $stmt->execute([$from,$to,$text]);
    echo json_encode(['ok'=>true]); exit;
}

http_response_code(405);
header('Allow: GET, POST');
echo json_encode(['error'=>'unsupported']);
