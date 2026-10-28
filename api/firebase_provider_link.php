<?php
/**
 * Writes providers/{provider_id}/director_uid into the Realtime Database
 * using the service-account credential (which bypasses RTDB security
 * rules) — this is deliberately the ONLY writer of that node. Everything
 * else (including the director's own client) can only read it, so the
 * client-side inventory writes in firebase.rules.json have a trustworthy
 * "is this really their provider_id" check to compare against.
 *
 * Call this once after a director links Firebase (registers/logs in via
 * firebase-auth.js) and has a selected, approved-or-pending parlor — e.g.
 * right after selected_director_parlor() resolves on director/dashboard.php.
 * Safe to call repeatedly; it just overwrites the same mapping.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/firebase_init.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['director']);

header('Content-Type: application/json; charset=utf-8');

$pdo = pdo_connect();
ensure_firebase_columns($pdo);
ensure_director_scope_columns($pdo);
$user = current_user();

if (empty($user['firebase_uid'])) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'This account has not signed in with Firebase yet.']);
    exit;
}

$parlor = selected_director_parlor($pdo, (int)$user['id']);
if (!$parlor) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'Select a parlor before linking real-time inventory.']);
    exit;
}

firebase_database()->rtdbSet("providers/{$parlor['id']}", [
    'director_uid' => $user['firebase_uid'],
    'name' => $parlor['name'],
]);

echo json_encode(['ok' => true, 'providerId' => (int)$parlor['id']]);
