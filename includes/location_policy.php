<?php
declare(strict_types=1);

/** Client scope; directors retain the existing wider registration list. */
function service_area_locations(): array {
    static $locations = null;
    if ($locations === null) {
        $all = require __DIR__ . '/iloilo_locations.php';
        $locations = array_intersect_key($all, array_flip(['Guimbal', 'Miagao']));
    }
    return $locations;
}

function is_in_service_area(?string $location): bool {
    if (!$location || !str_contains($location, ',')) return false;
    [$barangay, $municipality] = array_map('trim', explode(',', $location, 2));
    $locations = service_area_locations();
    return isset($locations[$municipality]) && in_array($barangay, $locations[$municipality], true);
}

/** Shortest shared-boundary path inside Guimbal/Miagao, never a road distance. */
function barangay_crossing_distances(?string $location): array {
    if (!is_in_service_area($location)) return [];
    [$barangay, $municipality] = array_map('trim', explode(',', $location, 2));
    $start = $barangay . ', ' . $municipality;
    static $graph = null;
    if ($graph === null) {
        $file = __DIR__ . '/barangay_adjacency.json';
        $data = is_readable($file) ? json_decode((string)file_get_contents($file), true) : null;
        $graph = is_array($data['adjacency'] ?? null) ? $data['adjacency'] : [];
    }
    if (!isset($graph[$start])) return [];
    $distances = [$start => 0];
    $queue = [$start];
    for ($i = 0; $i < count($queue); $i++) {
        $current = $queue[$i];
        foreach ($graph[$current] as $next) {
            if (!isset($distances[$next]) && isset($graph[$next])) {
                $distances[$next] = $distances[$current] + 1;
                $queue[] = $next;
            }
        }
    }
    return $distances;
}

function compare_nearby_packages(array $a, array $b): int {
    return ($a['barangay_crossings'] ?? PHP_INT_MAX) <=> ($b['barangay_crossings'] ?? PHP_INT_MAX)
        ?: $a['price'] <=> $b['price']
        ?: $a['package_id'] <=> $b['package_id'];
}

/** Haversine distance between indicative barangay polygon centers, not roads. */
function barangay_distance_km(?string $from, ?string $to): ?float {
    static $centers = null;
    if ($centers === null) {
        $file = __DIR__ . '/barangay_centers.json';
        $data = is_readable($file) ? json_decode((string)file_get_contents($file), true) : [];
        $centers = $data['centers'] ?? [];
    }
    $normalize = static fn(?string $location): string => implode(', ', array_map('trim', explode(',', $location ?? '', 2)));
    $a = $centers[$normalize($from)] ?? null;
    $b = $centers[$normalize($to)] ?? null;
    if (!$a || !$b) return null;
    $lat = deg2rad($b[1] - $a[1]);
    $lon = deg2rad($b[0] - $a[0]);
    $h = sin($lat / 2) ** 2 + cos(deg2rad($a[1])) * cos(deg2rad($b[1])) * sin($lon / 2) ** 2;
    return 6371.0088 * 2 * asin(sqrt(min(1.0, max(0.0, $h))));
}

/** Count distinct listed services; support legacy packages without item rows. */
function package_service_count(string $inclusions, int $structuredCount): int {
    if ($structuredCount > 0) return $structuredCount;
    $items = preg_split('/[,;\r\n]+/u', $inclusions) ?: [];
    $items = array_map(static fn(string $item): string => mb_strtolower(trim(explode(' - ', $item, 2)[0])), $items);
    return count(array_unique(array_filter($items, static fn(string $item): bool => $item !== '')));
}

function compare_client_packages(array $a, array $b): int {
    return $a['preference_penalty'] <=> $b['preference_penalty']
        ?: $b['recommendation_score'] <=> $a['recommendation_score']
        ?: ($a['distance_km'] ?? INF) <=> ($b['distance_km'] ?? INF)
        ?: $b['performance_score'] <=> $a['performance_score']
        ?: $a['price'] <=> $b['price']
        ?: $a['package_id'] <=> $b['package_id'];
}
