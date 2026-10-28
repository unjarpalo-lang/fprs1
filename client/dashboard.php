<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['client']);
$user = current_user();
// Reject malformed request types before any preference write.
foreach (['budget','religion','municipality','barangay','search','clear'] as $field) request_text($_GET, $field);
$pdo = pdo_connect();
$pdo->exec("CREATE TABLE IF NOT EXISTS funeral_parlors (id INT AUTO_INCREMENT PRIMARY KEY, director_id INT NOT NULL, name VARCHAR(200) NOT NULL, location VARCHAR(255) NOT NULL, contact_number VARCHAR(50) NOT NULL, license_path VARCHAR(255) NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE CASCADE)");
$columnCheck = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packages' AND COLUMN_NAME = 'parlor_id'");
$columnCheck->execute();
if (!$columnCheck->fetchColumn()) {
  $pdo->exec('ALTER TABLE packages ADD COLUMN parlor_id INT NULL');
}
require_once __DIR__ . '/../includes/RecommenderEngine.php';
require_once __DIR__ . '/../includes/CollaborativeEngine.php';
ensure_recommender_tables($pdo);
ensure_feedback_and_media_columns($pdo);
ensure_package_inclusion_images($pdo);
$collaborativeEngine = new CollaborativeEngine($pdo);
// One scoreParlor() lookup per distinct parlor per request, no matter how
// many of its packages appear in the results.
$parlorPerformanceCache = [];
$getParlorPerformance = function (int $parlorId) use (&$parlorPerformanceCache, $collaborativeEngine): array {
    if (!isset($parlorPerformanceCache[$parlorId])) {
        $parlorPerformanceCache[$parlorId] = $collaborativeEngine->scoreParlor($parlorId, []);
    }
    return $parlorPerformanceCache[$parlorId];
};

// remember this as the last visited dashboard for Back buttons
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['last_dashboard'] = BASE_PATH . '/client/dashboard.php';

$iloiloLocations = service_area_locations();
// Label shown in the dropdown => keywords checked against package title/inclusions text.
// A package's religion isn't a structured field, so matching is keyword-based rather
// than requiring the exact label text — "Other" has no reliable keyword, so it never
// excludes anything (safer than silently hiding every package tagged "other faiths").
$religionOptions = [
    'Roman Catholic' => ['catholic'],
    'Christian / Protestant' => ['christian', 'protestant'],
    'Iglesia ni Cristo' => ['iglesia', 'inc'],
    'Islam' => ['islam', 'muslim'],
    'Buddhist' => ['buddh'],
    'Other' => [],
];
// A package qualifies as a "near-budget" match up to this much over budget.
const BUDGET_TOLERANCE = 0.15;

if (isset($_GET['clear'])) {
    $pdo->prepare('DELETE FROM client_preferences WHERE client_id = ?')->execute([$user['id']]);
    header('Location: ' . BASE_PATH . '/client/dashboard.php');
    exit;
}

$prefStmt = $pdo->prepare('SELECT * FROM client_preferences WHERE client_id = ?');
$prefStmt->execute([$user['id']]);
$savedPreferences = $prefStmt->fetch();

$searchErrors = [];
if (isset($_GET['search'])) {
    $rawBudget = trim($_GET['budget'] ?? '');
    if ($rawBudget === '') {
        $searchBudget = null;
    } elseif (!is_numeric($rawBudget) || !is_finite((float)$rawBudget) || (float)$rawBudget < 0 || (float)$rawBudget > (float)PACKAGE_PRICE_MAX) {
        $searchErrors[] = 'Budget must be a valid, non-negative amount — showing results for any budget instead.';
        $searchBudget = null;
    } else {
        $searchBudget = (float)$rawBudget;
    }

    $searchReligion = trim($_GET['religion'] ?? '');
    if ($searchReligion !== '' && !array_key_exists($searchReligion, $religionOptions)) {
        $searchErrors[] = 'That religion option was not recognized — showing results for any religion instead.';
        $searchReligion = '';
    }

    $searchMunicipality = trim($_GET['municipality'] ?? '');
    $searchBarangay = trim($_GET['barangay'] ?? '');
    if ($searchMunicipality !== '' && !isset($iloiloLocations[$searchMunicipality])) {
        $searchErrors[] = 'That municipality was not recognized — showing results for every location instead.';
        $searchMunicipality = '';
        $searchBarangay = '';
    } elseif ($searchBarangay !== '' && !in_array($searchBarangay, $iloiloLocations[$searchMunicipality] ?? [], true)) {
        $searchErrors[] = 'That barangay does not belong to the selected municipality — showing every barangay in it instead.';
        $searchBarangay = '';
    }

    // A municipality alone is a valid, persistable preference (rank the whole
    // municipality by performance below); only fall back to no-location when
    // neither was given.
    $searchLocation = $searchBarangay !== ''
        ? "{$searchBarangay}, {$searchMunicipality}"
        : ($searchMunicipality !== '' ? $searchMunicipality : '');

    $stmt = $pdo->prepare(
        'INSERT INTO client_preferences (client_id, budget, religion, location)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE budget = VALUES(budget), religion = VALUES(religion), location = VALUES(location)'
    );
    if (!$searchErrors) {
        $stmt->execute([$user['id'], $searchBudget, $searchReligion ?: null, $searchLocation ?: null]);
    } else {
        http_response_code(422);
    }
} else {
    $searchBudget = $savedPreferences && $savedPreferences['budget'] !== null ? (float)$savedPreferences['budget'] : null;
    $searchReligion = $savedPreferences['religion'] ?? '';
    $searchLocation = $savedPreferences['location'] ?? '';
    // Ignore old out-of-scope preferences without deleting stored data.
    if ($searchLocation !== '' && !isset($iloiloLocations[location_municipality($searchLocation)])) {
        $searchLocation = '';
    }
    $searchMunicipality = location_municipality($searchLocation);
    $searchBarangay = '';
    if ($searchLocation && str_contains($searchLocation, ',')) {
        [$searchBarangay] = array_map('trim', explode(',', $searchLocation, 2));
    }
}

// Pull every package from approved parlors/directors only — a pending or
// rejected account's packages must never reach client search, no exceptions.
$rows = $pdo->query(
    "SELECT fp.id AS parlor_id, fp.name AS parlor_name, fp.location, fp.contact_number, u.full_name, u.email,
            p.id AS package_id, p.title, p.inclusions, p.price, p.image_path,
            (SELECT COUNT(DISTINCT pi.service_name) FROM package_inclusions pi WHERE pi.package_id = p.id) AS service_count
     FROM funeral_parlors fp
     JOIN users u ON u.id = fp.director_id
     JOIN packages p ON p.parlor_id = fp.id
     WHERE fp.status = 'approved' AND u.status = 'approved'
     ORDER BY p.price ASC"
)->fetchAll();

// Approved parlors with no packages yet (e.g. newly onboarded or imported
// with only crowd-survey data, no pricing) never appear in the package
// search above since it requires a package to join against. List them
// separately so families can see their contact information — that's how a parlor
// without gathered data yet gets improved over time.
$parlorsWithoutPackages = $pdo->query(
    "SELECT fp.id, fp.name, fp.location
     FROM funeral_parlors fp
     JOIN users u ON u.id = fp.director_id
     WHERE fp.status = 'approved' AND u.status = 'approved'
       AND NOT EXISTS (SELECT 1 FROM packages p WHERE p.parlor_id = fp.id)
     ORDER BY fp.name ASC"
)->fetchAll();

$parlorsWithoutPackages = array_values(array_filter($parlorsWithoutPackages, static fn(array $parlor): bool => is_in_service_area($parlor['location'])));
$crossingDistances = barangay_crossing_distances($searchLocation);
$maximumServiceCount = 1;
foreach ($rows as $candidate) {
    if (is_in_service_area($candidate['location'])) {
        $maximumServiceCount = max($maximumServiceCount, package_service_count((string)$candidate['inclusions'], (int)$candidate['service_count']));
    }
}
$exactResults = [];
$flaggedResults = [];
foreach ($rows as $row) {
    // Defensive only: excludes a parlor whose location isn't a recognized
    // municipality/barangay at all. Every parlor registered through the
    // standard dropdown always passes this.
    if (!is_in_service_area($row['location'])) {
        continue;
    }

    $price = (float)$row['price'];

    if ($searchBudget === null) {
        $budgetFlag = null;
    } elseif ($price <= $searchBudget) {
        $budgetFlag = null;
    } elseif ($price <= $searchBudget * (1 + BUDGET_TOLERANCE)) {
        $budgetFlag = 'Close to your budget';
    } else {
        $budgetFlag = 'Above your budget';
    }

    $religionFlag = null;
    if ($searchReligion !== '' && !empty($religionOptions[$searchReligion])) {
        $haystack = $row['title'] . ' ' . $row['inclusions'];
        $matchesKeyword = false;
        foreach ($religionOptions[$searchReligion] as $keyword) {
            // INC is an acronym; it must not match words such as "includes".
            $keywordMatches = $keyword === 'inc'
                ? preg_match('/\binc\b/iu', $haystack) === 1
                : stripos($haystack, $keyword) !== false;
            if ($keywordMatches) { $matchesKeyword = true; break; }
        }
        if (!$matchesKeyword) {
            $religionFlag = 'Religion compatibility unconfirmed';
        }
    }

    $locationFlag = null;
    if ($searchMunicipality !== '') {
        $parlorMunicipality = location_municipality($row['location']);
        if ($parlorMunicipality !== $searchMunicipality) {
            $locationFlag = 'Other municipality';
        }
    }

    $distanceKm = barangay_distance_km($searchLocation, $row['location']);
    $serviceCount = package_service_count((string)$row['inclusions'], (int)$row['service_count']);
    // Services and proximity both contribute. Unknown coordinates never mean zero distance.
    $locationKey = implode(', ', array_map('trim', explode(',', $row['location'], 2)));
    $crossings = $crossingDistances[$locationKey] ?? null;
    $proximityScore = $distanceKm !== null ? 1 / (1 + $distanceKm / 5)
        : ($crossings !== null ? 1 / (1 + $crossings) : 0);
    $recommendationScore = 0.6 * ($serviceCount / $maximumServiceCount) + 0.4 * $proximityScore;

    $performance = $getParlorPerformance((int)$row['parlor_id']);

    $result = [
        'package_id' => (int)$row['package_id'],
        'title' => $row['title'],
        'inclusions' => $row['inclusions'],
        'price' => $price,
        'image_path' => $row['image_path'],
        'parlor_id' => (int)$row['parlor_id'],
        'parlor_name' => $row['parlor_name'],
        'location' => $row['location'],
        'contact_number' => $row['contact_number'],
        'director_name' => $row['full_name'],
        'budget_flag' => $budgetFlag,
        'religion_flag' => $religionFlag,
        'distance_km' => $distanceKm,
        'service_count' => $serviceCount,
        'preference_penalty' => (int)($budgetFlag !== null) + (int)($religionFlag !== null),
        'recommendation_score' => $recommendationScore,
        'location_flag' => $locationFlag,
        'barangay_crossings' => $crossings,
        'performance_score' => $performance['score'],
        'respondent_count' => $performance['respondent_count'],
    ];

    if ($locationFlag === null) {
        $exactResults[] = $result;
    } else {
        $flaggedResults[] = $result;
    }
}

// Keep all approved in-scope packages visible; preferences affect rank and labels.
usort($exactResults, 'compare_client_packages');
usort($flaggedResults, 'compare_client_packages');

?>
<div class="space-y-6 py-4">
  <div class="flex flex-col gap-3 rounded-[28px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between sm:p-6">
    <div>
      <p class="f-eyebrow mb-2">Client portal</p>
      <h2 class="text-3xl font-bold text-funeral-800">Welcome, <?= htmlspecialchars($user['full_name']) ?></h2>
      <p class="mt-1 text-sm text-stone-600">Search by budget, location, and religion — we'll show what fits, and what's close.</p>
    </div>
    <a href="<?= BASE_PATH ?>/" class="inline-flex shrink-0 items-center gap-1 self-start rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50 sm:self-auto">
      <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
      Back
    </a>
  </div>

  <div class="f-card p-6 sm:p-8">
    <div class="mb-5 flex items-center gap-3">
      <div class="f-icon-tile">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      </div>
      <div>
        <h3 class="text-xl font-semibold text-funeral-800">Find your best match</h3>
        <p class="text-sm text-stone-500">Everything is optional, but the more you share, the sharper the match.</p>
      </div>
    </div>

    <?php if ($searchErrors): ?>
      <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <?= htmlspecialchars(implode(' ', $searchErrors)) ?>
      </div>
    <?php endif; ?>

    <form method="get" class="space-y-4 rounded-2xl border border-stone-200 bg-stone-50/70 p-4 sm:p-5">
      <input type="hidden" name="search" value="1" />
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="text-sm font-medium text-stone-700" for="field-client-dashboard-php-1">Budget (PHP)</label>
          <input id="field-client-dashboard-php-1" name="budget" type="number" step="0.01" min="0" value="<?= $searchBudget !== null ? htmlspecialchars((string)$searchBudget) : '' ?>" class="mt-1 w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" placeholder="Any amount" />
        </div>
        <div>
          <label class="text-sm font-medium text-stone-700" for="field-client-dashboard-php-2">Religion</label>
          <select id="field-client-dashboard-php-2" name="religion" class="mt-1 w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200">
            <option value="">Any / no preference</option>
            <?php foreach (array_keys($religionOptions) as $option): ?>
              <option value="<?= htmlspecialchars($option) ?>" <?= $searchReligion === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <?php render_location_fields($iloiloLocations, 'search', $searchMunicipality, $searchBarangay, 'municipality', 'barangay', 'Location', false); ?>

      <div class="flex items-center gap-3 pt-1">
        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-funeral-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-funeral-500">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
          Show my matches
        </button>
        <a href="<?= BASE_PATH ?>/client/dashboard.php?clear=1" class="text-sm font-medium text-stone-500 hover:text-stone-700">Clear preferences</a>
      </div>
    </form>

    <?php if ($searchBarangay !== ''): ?>
      <p class="mt-4 text-xs leading-5 text-stone-500">Packages in your municipality appear first. Packages are ranked by your preferences, listed services, and nearby locations.</p>
    <?php endif; ?>

    <?php if ($exactResults || $flaggedResults): ?>
      <div class="mt-6 space-y-3">
        <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-funeral-700">Available Packages</h4>
        <?php if (!$exactResults): ?>
          <p class="text-sm text-stone-500">Nothing matches every filter exactly — see the nearby/close-budget options below.</p>
        <?php else: foreach ($exactResults as $package): ?>
          <?php include __DIR__ . '/_package_result.php'; ?>
        <?php endforeach; endif; ?>
      </div>

      <?php if ($flaggedResults): ?>
        <div class="mt-6 space-y-3 border-t border-stone-200 pt-5">
          <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-funeral-700">Other Municipality Options</h4>
          <p class="-mt-1 text-xs text-stone-500">Close to your budget or a bit outside your chosen barangay — still worth a look.</p>
          <?php foreach ($flaggedResults as $package): ?>
            <?php include __DIR__ . '/_package_result.php'; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="mt-6 flex flex-col items-center gap-2 rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 p-8 text-center">
        <span class="text-2xl">🔍</span>
        <p class="text-sm font-medium text-stone-700">No approved packages are listed yet.</p>
        <p class="text-sm text-stone-500">
          Approved parlors need to publish packages before services can appear here.
        </p>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($parlorsWithoutPackages): ?>
    <div class="f-card p-6 sm:p-8">
      <h3 class="text-xl font-semibold text-funeral-800">Other Funeral Homes</h3>
      <p class="mt-1 text-sm text-stone-500">These parlors have not listed packages yet.</p>
      <div class="mt-4 space-y-3">
        <?php foreach ($parlorsWithoutPackages as $p): ?>
          <div class="f-card-flat flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div class="font-medium text-stone-800"><?= htmlspecialchars($p['name']) ?></div>
              <?php if ($p['location'] && $p['location'] !== 'Pending update'): ?>
                <div class="text-sm text-stone-500"><?= htmlspecialchars($p['location']) ?></div>
              <?php else: ?>
                <div class="text-sm text-stone-400">Location details pending</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php location_dropdown_script($iloiloLocations, 'search'); ?>
