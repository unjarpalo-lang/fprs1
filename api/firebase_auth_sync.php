<?php
/**
 * Bridges a Firebase Auth sign-in to this app's existing SQL-backed session.
 *
 * Called by assets/js/firebase-auth.js right after Firebase
 * createUserWithEmailAndPassword / signInWithEmailAndPassword succeeds.
 * On success this sets $_SESSION['user_id'] exactly like login.php does, so
 * is_logged_in() / current_user() / require_any_role() keep working
 * unchanged everywhere else in the app — Firebase only replaces *how the
 * password was checked*, not the session/role system built on top of it.
 *
 * POST JSON body:
 *   idToken   (required) — Firebase ID token from the client SDK
 *   mode      (required) — "register" | "login"
 *   role      (register only) — "client" | "director"  ("Family" -> client)
 *   full_name (register only)
 *   phone     (register only, optional)
 *   funeral_name (register only, directors only)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/firebase_init.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'POST is required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'A JSON object is required.']);
    exit;
}
$idToken = $input['idToken'] ?? '';
$mode = $input['mode'] ?? 'login';

if (!is_string($idToken) || $idToken === '' || !in_array($mode, ['login', 'register'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'idToken is required.']);
    exit;
}

try {
    $account = firebase_auth()->verifyIdToken($idToken);
} catch (Throwable $e) {
    error_log('Firebase token verification unavailable.');
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Sign-in is temporarily unavailable. Please try again.']);
    exit;
}
if (!$account) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid or expired Firebase ID token.']);
    exit;
}

$pdo = pdo_connect();
ensure_firebase_columns($pdo);

// Already linked? Just resume the session (covers "login" for any role).
$stmt = $pdo->prepare('SELECT * FROM users WHERE firebase_uid = ?');
$stmt->execute([$account['uid']]);
$user = $stmt->fetch();

if (!$user && $account['email']) {
    // First Firebase sign-in for an account that already exists by email
    // (e.g. it was created the old way) — link it rather than duplicating.
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$account['email']]);
    $existingByEmail = $stmt->fetch();
    if ($existingByEmail) {
        if (empty($account['emailVerified'])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Verify your Firebase email before linking this existing account.']);
            exit;
        }
        if (!empty($existingByEmail['firebase_uid']) && $existingByEmail['firebase_uid'] !== $account['uid']) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'This account is already linked to a different Firebase identity.']);
            exit;
        }
        $link = $pdo->prepare("UPDATE users SET firebase_uid = ? WHERE id = ? AND (firebase_uid IS NULL OR firebase_uid = '' OR firebase_uid = ?)");
        $link->execute([$account['uid'], $existingByEmail['id'], $account['uid']]);
        if ($link->rowCount() === 0) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'Account linking changed. Please sign in again.']);
            exit;
        }
        $user = $existingByEmail;
    }
}

if (!$user) {
    if ($mode !== 'register') {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'No account found for this Firebase user. Register first.']);
        exit;
    }
    $role = ($input['role'] ?? 'client') === 'director' ? 'director' : 'client';
    $fullName = trim($input['full_name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $funeralName = $role === 'director' ? trim($input['funeral_name'] ?? '') : null;
    if (!$fullName) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'full_name is required.']);
        exit;
    }
    // Matches register.php's rule: every role goes through the admin
    // approval queue before they can log in.
    $status = 'pending';
    // Firebase already verified the password/email — no local password hash
    // is meaningful, so store a random unusable placeholder for that column.
    $placeholderHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (full_name,email,phone,password,role,funeral_name,status,firebase_uid) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$fullName, $account['email'], $phone, $placeholderHash, $role, $funeralName, $status, $account['uid']]);
    $userId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
}

if ($user['status'] !== 'approved') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Account not approved yet.']);
    exit;
}

// Same session-fixation defense as login.php.
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
echo json_encode([
    'ok' => true,
    'role' => $user['role'],
    'redirect' => BASE_PATH . '/' . $user['role'] . '/dashboard.php',
]);
