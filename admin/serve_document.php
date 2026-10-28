<?php
/**
 * Authenticated reader for the private verification documents (client valid
 * IDs, director licences, parlor business licences).
 *
 * Those files live in uploads/, which Apache serves directly, so before this
 * existed anyone who knew or guessed a filename could open a stranger's
 * government ID with no login at all. uploads/.htaccess now denies the
 * private prefixes outright and every viewer goes through here instead.
 *
 * The file path is always looked up in the database from a record id — it is
 * never taken from the query string — so there is no path to traverse. The
 * realpath check below is a second line of defence in case a stored path is
 * ever malformed.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['admin']);

$pdo = pdo_connect();
$type = $_GET['type'] ?? '';
$doc = $_GET['doc'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$storedPath = null;
if ($id > 0 && $type === 'user' && in_array($doc, ['valid_id', 'license'], true)) {
    $column = $doc === 'valid_id' ? 'valid_id_path' : 'license_path';
    $stmt = $pdo->prepare("SELECT {$column} FROM users WHERE id = ?"); // $column is from the allow-list above
    $stmt->execute([$id]);
    $storedPath = $stmt->fetchColumn() ?: null;
} elseif ($id > 0 && $type === 'parlor') {
    $stmt = $pdo->prepare('SELECT license_path FROM funeral_parlors WHERE id = ?');
    $stmt->execute([$id]);
    $storedPath = $stmt->fetchColumn() ?: null;
}

if (!$storedPath) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Document not found.';
    exit;
}

$uploadsDir = realpath(__DIR__ . '/../uploads');
$fullPath = private_document_path($storedPath);

// Must resolve to a real file that actually sits inside uploads/.
if ($uploadsDir === false || $fullPath === null || !is_file($fullPath)
    || strncmp($fullPath, $uploadsDir . DIRECTORY_SEPARATOR, strlen($uploadsDir) + 1) !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Document not found.';
    exit;
}

$mime = (string)mime_content_type($fullPath);
$allowedMimes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf'];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(415);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unsupported document type.';
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="document"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($fullPath);
