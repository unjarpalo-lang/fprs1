<?php
/**
 * Firebase config — returns an array (require this file to get it), same
 * "return an array" convention as includes/iloilo_locations.php elsewhere
 * in this codebase.
 *
 * @return array{project_id:string, database_url:string, web_api_key:string, service_account_path:string}
 */

declare(strict_types=1);

$serviceAccountPath = realpath(__DIR__ . '/../storage/firebase-service-account.json');

if ($serviceAccountPath === false) {
    throw new RuntimeException(
        'Firebase service account key not found at storage/firebase-service-account.json. ' .
        'Download it from Firebase Console > Project settings > Service accounts > Generate new private key, ' .
        'and save it at that exact path.'
    );
}
if (!is_readable($serviceAccountPath)) {
    throw new RuntimeException("Firebase service account key exists but is not readable: {$serviceAccountPath}");
}
// Fail fast on a corrupt/truncated key file rather than deep inside a token request later.
$decoded = json_decode((string)file_get_contents($serviceAccountPath), true);
if (!is_array($decoded) || empty($decoded['private_key']) || empty($decoded['client_email'])) {
    throw new RuntimeException("Firebase service account key at {$serviceAccountPath} is not valid JSON (or is missing private_key/client_email).");
}

return [
    'project_id' => 'fprs-a8789',
    'database_url' => 'https://fprs-a8789-default-rtdb.firebaseio.com',
    // From Firebase Console > Project settings > General > Web API Key.
    // Needed for the REST-fallback ID-token verification in includes/firebase_init.php.
    'web_api_key' => 'AIzaSyBiFXMNgcISwXCo3yRCOCv4caLsL6g5cpI',
    'service_account_path' => $serviceAccountPath,
];
