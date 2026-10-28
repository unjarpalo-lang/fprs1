<?php
declare(strict_types=1);

/** Existing supported verification documents must resolve inside uploads/. */
function private_document_path(?string $storedPath): ?string {
    if (!$storedPath) return null;
    $directory = realpath(__DIR__ . '/../uploads');
    $path = realpath(__DIR__ . '/../' . ltrim($storedPath, '/'));
    if ($directory === false || $path === false || !is_file($path) || !is_readable($path)) return null;
    $prefix = $directory . DIRECTORY_SEPARATOR;
    $inside = PHP_OS_FAMILY === 'Windows'
        ? strncasecmp($path, $prefix, strlen($prefix)) === 0
        : strncmp($path, $prefix, strlen($prefix)) === 0;
    if (!$inside) return null;
    return in_array((string)mime_content_type($path), ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf'], true)
        ? $path : null;
}

function parlor_approval_error(array $parlor): ?string {
    if (!is_in_service_area($parlor['location'] ?? null)) {
        return 'Approval requires a recognized barangay in Guimbal or Miagao. This listing remains hidden from clients.';
    }
    if (private_document_path($parlor['license_path'] ?? null) === null) {
        return 'Approval requires an uploaded parlor business license. A director account license does not replace it.';
    }
    return null;
}
