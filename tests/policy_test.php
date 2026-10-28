<?php
// CLI-only, dependency-free checks. All document fixtures live outside the app.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASE_PATH', '/fprs');
require_once __DIR__ . '/../includes/location_policy.php';
require_once __DIR__ . '/../includes/navigation.php';
$checks = 0;
function check(bool $ok, string $name): void {
    global $checks;
    if (!$ok) throw new RuntimeException($name);
    $checks++;
}
check(array_keys(service_area_locations()) === ['Guimbal', 'Miagao'], 'two-municipality scope');
foreach (service_area_locations() as $municipality => $barangays) {
    foreach ($barangays as $barangay) check(is_in_service_area("$barangay, $municipality"), 'known scoped barangay');
}
foreach ([null, '', 'Guimbal', 'Unknown, Guimbal', 'Bacong, Oton', 'Pending update', 'Bacong, Miagao', 'Bacong, Guimbal, Iloilo'] as $location) {
    check(!is_in_service_area($location), 'invalid/outside scope');
}
check(is_in_service_area(' Bacong , Guimbal '), 'location whitespace');
$graph = json_decode(file_get_contents(__DIR__ . '/../includes/barangay_adjacency.json'), true)['adjacency'];
check(count($graph) === 152, 'boundary coverage');
$directedEdges = 0;
foreach ($graph as $start => $neighbors) {
    $distances = barangay_crossing_distances($start);
    check(count($distances) === 152 && $distances[$start] === 0, 'all nodes reachable');
    foreach ($neighbors as $neighbor) {
        check($start !== $neighbor && in_array($start, $graph[$neighbor], true), 'symmetric boundary edge');
        check($distances[$neighbor] === 1, 'adjacent distance is one');
        $directedEdges++;
    }
    foreach ($graph as $node => $nextNodes) {
        foreach ($nextNodes as $next) check(abs($distances[$node] - $distances[$next]) <= 1, 'shortest-path edge invariant');
    }
}
check($directedEdges === 752, '376 undirected boundary edges');
check(barangay_crossing_distances('Unknown, Guimbal') === [], 'unknown distances not invented');
check(barangay_crossing_distances('Batuan Ilaya, Oton') === [], 'outside scope has no distance');
$packages = [
    ['package_id'=>1,'price'=>100,'barangay_crossings'=>3],
    ['package_id'=>2,'price'=>500,'barangay_crossings'=>1],
    ['package_id'=>3,'price'=>200,'barangay_crossings'=>1],
    ['package_id'=>4,'price'=>1,'barangay_crossings'=>null],
    ['package_id'=>5,'price'=>800,'barangay_crossings'=>0],
];
usort($packages, 'compare_nearby_packages');
check(array_column($packages, 'package_id') === [5,3,2,1,4], 'nearest first, price tie break, unknown last');
$fallback = BASE_PATH . '/admin/dashboard.php';
check(safe_admin_return_url('/fprs/admin/manage_all_accounts.php?type=director', $fallback) === '/fprs/admin/manage_all_accounts.php?type=director', 'tab preserved');
foreach (['https://evil.test/fprs/admin/manage_clients.php', '//evil.test/x', '/fprs/admin/user_action.php?action=delete', '/fprs/admin/manage_clients.php?x=1', "/fprs/admin/manage_clients.php\r\nX:1", '/fprs/admin/../admin/manage_clients.php', '/fprs/admin/manage_all_accounts.php?type[]=all', '/fprs/admin/manage_clients.php#x'] as $url) {
    check(safe_admin_return_url($url, $fallback) === $fallback, 'unsafe return destination rejected');
}
ob_start(); render_catalog_back_link('/fprs/director/packages.php', 'Back <test>'); $link=ob_get_clean();
check(str_contains($link, 'Back &lt;test&gt;') && str_contains($link, 'min-h-11'), 'escaped touch-friendly Back link');

// Load document policy from a temporary mirror so uploads are never real user files.
$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fprs-policy-' . bin2hex(random_bytes(8));
mkdir($fixture . '/includes', 0700, true); mkdir($fixture . '/uploads', 0700);
copy(__DIR__ . '/../includes/document_policy.php', $fixture . '/includes/document_policy.php');
file_put_contents($fixture . '/uploads/license.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jZioAAAAASUVORK5CYII='));
file_put_contents($fixture . '/uploads/not-a-document.txt', 'not a license');
file_put_contents($fixture . '/outside.png', file_get_contents($fixture . '/uploads/license.png'));
try {
    require $fixture . '/includes/document_policy.php';
    check(private_document_path('uploads/license.png') !== null, 'real supported document accepted');
    check(private_document_path('uploads/missing.png') === null, 'missing file rejected');
    check(private_document_path('uploads/not-a-document.txt') === null, 'unsupported content rejected');
    check(private_document_path('uploads/../outside.png') === null, 'traversal outside uploads rejected');
    check(parlor_approval_error(['location'=>'Bacong, Guimbal','license_path'=>'uploads/license.png']) === null, 'scoped licensed parlor approvable');
    check(parlor_approval_error(['location'=>'Bacong, Guimbal','license_path'=>'']) !== null, 'missing license blocks approval');
    check(parlor_approval_error(['location'=>'Batuan Ilaya, Oton','license_path'=>'uploads/license.png']) !== null, 'outside scope blocks approval');
} finally {
    foreach (['uploads/license.png','uploads/not-a-document.txt','outside.png','includes/document_policy.php'] as $path) unlink($fixture . '/' . $path);
    rmdir($fixture . '/uploads'); rmdir($fixture . '/includes'); rmdir($fixture);
}
echo "PASS: $checks location, graph, ranking, navigation and document checks.\n";
