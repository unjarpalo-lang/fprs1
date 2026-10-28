<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/HybridRecommenderEngine.php';
function expect(bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); }
$engine = new RecommenderEngine();
$rows = [['id'=>1,'price'=>100,'religion'=>'Catholic','distance'=>0],['id'=>2,'price'=>200,'religion'=>'Catholic','distance'=>4],['id'=>3,'price'=>80,'religion'=>'Other','distance'=>30]];
$r = $engine->recommend(['budget'=>100,'religion'=>'Catholic','max_distance'=>10], $rows);
expect(array_column($r,'id') === [1,2], 'content distance exclusion and ordering');
expect($r[0]['match_percentage'] === 100.0, 'exact content score');
expect($engine->recommend([],[]) === [], 'empty content result');
expect(count($engine->recommend([], [['id'=>1]])) === 1, 'content cold start');
foreach ($r as $row) expect($row['match_percentage'] >= 0 && $row['match_percentage'] <= 100, 'score bounds');

class FixturePDO extends PDO {
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        // Only database metadata is adapted; scoring queries run unchanged.
        if (str_contains($query, 'information_schema.TABLES')) {
            $query = "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='parlor_feedback'";
        }
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
$pdo = new FixturePDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE parlor_survey_responses (parlor_id INTEGER, respondent_no INTEGER, criterion_key TEXT, score INTEGER)');
$pdo->exec('CREATE TABLE parlor_feedback (id INTEGER, parlor_id INTEGER, location INTEGER, budget INTEGER, religion INTEGER, casket INTEGER, service_delivery INTEGER, documentation INTEGER, arrangement INTEGER, flower INTEGER)');
$collaborative = new CollaborativeEngine($pdo);
expect($collaborative->scoreParlor(99, [])['score'] === 0.5, 'neutral cold-start survey score');
$insert=$pdo->prepare('INSERT INTO parlor_survey_responses VALUES (?,?,?,?)');
foreach (CollaborativeEngine::CATEGORIES as $category=>$count) for($i=1;$i<=$count;$i++) $insert->execute([1,1,$category.'_'.$i,5]);
expect($collaborative->scoreParlor(1, [])['score'] === 1.0, 'perfect survey score');
$pdo->exec('INSERT INTO parlor_feedback VALUES (1,1,1,1,1,1,1,1,1,1)');
$mixed=$collaborative->scoreParlor(1, []);
expect($mixed['respondent_count'] === 2 && $mixed['score'] < 1.0, 'live feedback joins survey signal');
$hybrid=new HybridRecommenderEngine($pdo);
$h=$hybrid->recommend(['budget'=>100,'religion'=>'Catholic','max_distance'=>10],[],[['id'=>1,'parlor_id'=>1,'price'=>100,'religion'=>'Catholic','distance'=>0]]);
expect(abs($h[0]['match_percentage'] - (($h[0]['content_score'] + $h[0]['collaborative_score']) / 2)) < 0.11, 'existing hybrid blend');
echo "PASS: existing content, collaborative and hybrid engine fixture scenarios.\n";
