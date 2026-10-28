<?php
// Maintenance commands must never execute through a browser request.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * One-time import of the client satisfaction survey ("Q_A EXCEL.xlsx") into
 * live funeral_parlors + parlor_survey_responses rows, so the collaborative
 * engine has real crowd data to score against.
 *
 * Run once from the command line:
 *   C:\xampp\php\php.exe scripts\import_survey_data.php
 *
 * Safe to re-run: a parlor whose name already exists in funeral_parlors is
 * skipped entirely (no duplicate parlors or duplicate survey rows).
 *
 * IMPORTANT: the source spreadsheet only contains 1-5 satisfaction ratings —
 * no real address, phone number, or package pricing for these parlors. Those
 * fields are inserted with an obvious "Pending update" placeholder so admins
 * and directors know to fill them in before the content-based (budget /
 * location) half of the recommender has anything real to score. The
 * collaborative (crowd rating) half works immediately after this import.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Column order for the 22 raw criteria, as laid out in the source sheet
// (columns B..W): location(3), budget(5), religion(2), casket(2),
// service_delivery(4), documentation(3), arrangement(2), flower(1).
const CRITERION_KEYS = [
    'location_1', 'location_2', 'location_3',
    'budget_1', 'budget_2', 'budget_3', 'budget_4', 'budget_5',
    'religion_1', 'religion_2',
    'casket_1', 'casket_2',
    'service_delivery_1', 'service_delivery_2', 'service_delivery_3', 'service_delivery_4',
    'documentation_1', 'documentation_2', 'documentation_3',
    'arrangement_1', 'arrangement_2',
    'flower_1',
];

// One row of 22 scores per respondent, per parlor sheet.
const SURVEY_DATA = [
    'MOLETA' => [
        [5,5,5,4,5,5,2,3,4,4,4,4,4,4,4,4,3,4,4,3,3,4],
        [5,5,5,5,5,5,1,4,4,4,4,4,4,4,5,4,3,4,4,4,4,4],
        [5,5,5,4,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5],
        [5,5,5,5,4,4,5,5,4,5,5,5,5,5,5,5,5,5,5,5,5,5],
        [5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,4,5,5,4],
        [4,4,5,3,5,3,5,5,5,5,5,5,5,5,5,5,5,3,5,5,4,4],
        [4,5,5,5,5,5,5,5,5,5,5,5,4,5,5,5,5,5,5,5,5,5],
        [5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5],
        [5,5,5,5,5,5,5,5,3,5,5,5,4,5,5,5,5,4,5,5,5,5],
        [5,5,5,5,4,5,5,5,3,5,5,5,5,5,4,5,5,4,5,5,4,5],
    ],
    'SALOME-GASAPO' => [
        [5,4,5,4,5,5,5,4,5,5,4,5,4,5,5,5,4,4,5,5,5,5],
        [5,4,5,5,4,4,5,5,4,5,4,5,4,5,5,4,4,5,5,4,5,5],
        [5,5,5,5,4,5,5,5,5,4,5,4,5,5,5,4,5,4,5,4,4,5],
        [4,4,5,5,4,4,4,5,4,5,4,4,5,5,5,5,5,4,5,5,5,4],
        [4,5,5,5,4,5,5,4,4,4,4,4,4,4,5,4,4,5,5,5,4,5],
        [5,5,5,4,5,4,5,5,4,5,5,5,4,4,4,5,4,5,4,4,4,4],
        [5,5,5,4,4,5,5,5,4,5,5,5,4,4,4,5,4,5,5,5,5,5],
        [5,5,5,5,4,5,5,4,5,5,5,4,5,5,5,5,4,5,5,4,5,5],
        [5,4,4,5,5,5,5,5,4,5,4,4,5,4,4,4,5,4,5,5,5,5],
        [5,4,5,5,4,5,4,4,5,4,5,4,4,4,5,4,5,4,4,5,5,4],
    ],
    'GEGATO' => [
        [5,4,4,5,5,5,5,5,5,5,5,5,5,5,5,5,4,5,5,5,5,5],
        [4,5,5,4,5,5,5,4,4,5,5,5,4,5,5,4,4,5,5,4,5,5],
        [5,4,4,4,4,5,4,5,4,5,4,4,4,5,5,4,5,5,4,4,4,5],
        [4,5,4,4,5,5,4,5,4,5,4,5,4,5,5,5,4,5,4,5,5,5],
        [4,5,5,5,4,5,5,5,4,5,5,4,5,4,5,5,4,5,5,5,4,5],
        [5,4,5,5,4,5,5,5,5,5,4,5,4,5,5,5,4,5,5,5,5,5],
        [4,5,4,4,5,5,5,5,5,5,4,5,5,4,5,5,4,5,5,4,5,5],
        [4,5,5,4,5,5,4,5,4,4,4,4,5,4,5,4,5,4,4,4,4,5],
        [4,5,4,5,4,5,4,3,5,4,4,4,5,5,5,4,4,4,4,4,5,5],
        [5,4,5,4,5,5,4,5,4,5,5,5,4,5,5,5,4,5,5,4,5,5],
    ],
    'Esquera Solivio' => [
        [5,5,5,5,5,5,5,5,4,4,5,5,5,5,5,5,5,5,5,5,5,5],
        [5,5,5,4,4,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5],
        [5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,4,5,5,5,5,5,5],
        [5,5,5,4,4,4,5,5,4,4,4,4,5,5,5,4,5,4,5,5,5,5],
        [4,4,4,4,4,4,4,4,3,3,3,3,4,4,4,4,3,4,4,4,4,4],
        [5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5],
        [5,5,5,4,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5],
        [5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5],
        [5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5],
        [5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5],
    ],
];

function slugify(string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim((string)$slug, '-');
}

try {
    $pdo = pdo_connect();
    ensure_director_scope_columns($pdo);
    ensure_recommender_tables($pdo);
    $passwordHash = password_hash(DEFAULT_USER_PASSWORD, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    foreach (SURVEY_DATA as $parlorName => $respondents) {
        $existing = $pdo->prepare('SELECT id FROM funeral_parlors WHERE name = ?');
        $existing->execute([$parlorName]);
        if ($existing->fetchColumn()) {
            echo "Parlor \"{$parlorName}\" already exists, skipping.\n";
            continue;
        }

        $slug = slugify($parlorName);
        $directorEmail = "{$slug}@imported.fprs.local";

        $directorStmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $directorStmt->execute([$directorEmail]);
        $directorId = $directorStmt->fetchColumn();

        if (!$directorId) {
            $insertDirector = $pdo->prepare(
                'INSERT INTO users (full_name, email, phone, password, role, status, funeral_name, created_at) VALUES (?,?,?,?,?,?,?,NOW())'
            );
            $insertDirector->execute([
                "{$parlorName} (Imported Director)",
                $directorEmail,
                'Pending update',
                $passwordHash,
                'director',
                'pending',
                $parlorName,
            ]);
            $directorId = (int)$pdo->lastInsertId();
            echo "Created placeholder director for \"{$parlorName}\": {$directorEmail} (password: " . DEFAULT_USER_PASSWORD . ")\n";
        }

        $insertParlor = $pdo->prepare(
            'INSERT INTO funeral_parlors (director_id, name, location, contact_number, license_path, status) VALUES (?,?,?,?,?,?)'
        );
        $insertParlor->execute([
            $directorId,
            $parlorName,
            'Pending update',
            'Pending update',
            '',
            'pending',
        ]);
        $parlorId = (int)$pdo->lastInsertId();

        $insertResponse = $pdo->prepare(
            'INSERT INTO parlor_survey_responses (parlor_id, respondent_no, criterion_key, score) VALUES (?,?,?,?)'
        );
        $rowCount = 0;
        foreach ($respondents as $respondentIndex => $scores) {
            $respondentNo = $respondentIndex + 1;
            foreach (CRITERION_KEYS as $i => $criterionKey) {
                $insertResponse->execute([$parlorId, $respondentNo, $criterionKey, (int)$scores[$i]]);
                $rowCount++;
            }
        }

        echo "Imported \"{$parlorName}\" as parlor #{$parlorId} with {$rowCount} survey ratings from " . count($respondents) . " respondents.\n";
    }

    $pdo->commit();
    echo "Import complete. Directors and packages for these parlors still need real details (location, contact number, pricing) before content-based matching has anything to score.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
