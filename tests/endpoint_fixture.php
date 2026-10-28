<?php
/**
 * Isolated route harness: actual endpoint business code and SQL run against
 * SQLite in memory. Only app/session bootstrapping and MySQL schema ensures
 * are removed; Firebase token verification is an explicit fixture service.
 * This is not a live MySQL/Firebase/browser end-to-end test.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASE_PATH', '/fprs');
$scenario = $argv[1] ?? '';
$root = dirname(__DIR__);
$sessionPath = getenv('FPRS_TEST_SESSION_PATH');
if (!$sessionPath || !is_dir($sessionPath)) throw new RuntimeException('An isolated test session directory is required.');
session_save_path($sessionPath);
session_start();
require_once $root . '/includes/location_policy.php';
require_once $root . '/includes/document_policy.php';
require_once $root . '/includes/navigation.php';
class RouteFixturePDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        // Execute the same preference upsert against SQLite's equivalent syntax.
        $query = str_replace('ON DUPLICATE KEY UPDATE budget = VALUES(budget), religion = VALUES(religion), location = VALUES(location)',
            'ON CONFLICT(client_id) DO UPDATE SET budget=excluded.budget,religion=excluded.religion,location=excluded.location', $query);
        return parent::prepare($query, $options);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false {
        if (str_contains($query, 'information_schema.TABLES')) $query = "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='parlor_feedback'";
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$args);
    }
}
$db = new RouteFixturePDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT, email TEXT, phone TEXT, password TEXT, role TEXT, status TEXT, license_path TEXT, valid_id_path TEXT, firebase_uid TEXT, funeral_name TEXT);
CREATE TABLE funeral_parlors (id INTEGER PRIMARY KEY, director_id INTEGER REFERENCES users(id) ON DELETE CASCADE, name TEXT, location TEXT, contact_number TEXT, license_path TEXT, status TEXT, status_reason TEXT, created_at TEXT);
CREATE TABLE packages (id INTEGER PRIMARY KEY, director_id INTEGER, parlor_id INTEGER, title TEXT, inclusions TEXT, price REAL, image_path TEXT, created_at TEXT);
CREATE TABLE inventory_resources (id INTEGER PRIMARY KEY, director_id INTEGER, parlor_id INTEGER, name TEXT, details TEXT, item_type TEXT, quantity INTEGER, available_status TEXT, image_path TEXT, updated_at TEXT);
CREATE TABLE package_inclusions (id INTEGER PRIMARY KEY, package_id INTEGER REFERENCES packages(id) ON DELETE CASCADE, service_name TEXT CHECK(service_name != 'FAIL'), service_detail TEXT, description TEXT, image_path TEXT, sort_order INTEGER);
CREATE TABLE messages (id INTEGER PRIMARY KEY, sender_id INTEGER, receiver_id INTEGER, message_text TEXT, sent_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE client_preferences (client_id INTEGER PRIMARY KEY, budget REAL, religion TEXT, location TEXT);
CREATE TABLE service_inquiries (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, package_id INTEGER NOT NULL REFERENCES packages(id) ON DELETE CASCADE, parlor_id INTEGER NOT NULL REFERENCES funeral_parlors(id) ON DELETE CASCADE, occurrence_no INTEGER NOT NULL DEFAULT 1, package_title TEXT NOT NULL, package_price REAL NOT NULL, inquiry_message TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'inquired', confirmed_at TEXT, confirmed_by INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(client_id,package_id,occurrence_no));
CREATE TABLE package_feedback (id INTEGER PRIMARY KEY AUTOINCREMENT, inquiry_id INTEGER NOT NULL UNIQUE REFERENCES service_inquiries(id) ON DELETE CASCADE, rating REAL NOT NULL CHECK(rating BETWEEN 1 AND 5), comment TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE package_feedback_answers (feedback_id INTEGER NOT NULL REFERENCES package_feedback(id) ON DELETE CASCADE, criterion_key TEXT NOT NULL, rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5), PRIMARY KEY(feedback_id,criterion_key));
CREATE TABLE funerals (id INTEGER PRIMARY KEY, client_id INTEGER REFERENCES users(id) ON DELETE CASCADE, director_id INTEGER REFERENCES users(id) ON DELETE SET NULL, package_id INTEGER REFERENCES packages(id) ON DELETE SET NULL, status TEXT);
INSERT INTO users VALUES (1,'Admin','admin@fixture.invalid','','','admin','approved',NULL,NULL,NULL,NULL),
(2,'Director','director@fixture.invalid','','','director','approved',NULL,NULL,NULL,NULL),
(3,'Client','client@fixture.invalid','','','client','approved',NULL,NULL,NULL,NULL),
(4,'Other director','other@fixture.invalid','','','director','approved',NULL,NULL,NULL,NULL),
(5,'Declined client','declined@fixture.invalid','','','client','rejected',NULL,NULL,NULL,NULL);
INSERT INTO funeral_parlors VALUES (10,2,'Scoped','Bacong, Guimbal','','','pending',NULL,'2026-01-01'),
(11,2,'Outside','Batuan Ilaya, Oton','','','approved',NULL,'2026-01-01'),
(12,4,'Other scoped','Agdum, Miagao','','','approved',NULL,'2026-01-01');
INSERT INTO packages VALUES (100,2,10,'Original','Catholic',100,NULL,'2026-01-01'),
(101,2,11,'Outside package','Catholic',100,NULL,'2026-01-01'),
(102,4,12,'Other package','Catholic',100,NULL,'2026-01-01');
INSERT INTO inventory_resources VALUES (100,2,10,'Original resource','','flower',5,'available',NULL,'2026-01-01'),
(102,4,12,'Other resource','','flower',5,'available',NULL,'2026-01-01');
INSERT INTO package_inclusions VALUES (1,100,'Original service','Original detail','',NULL,0);
INSERT INTO funerals VALUES (1,3,2,100,'scheduled');");
$db->exec('ALTER TABLE users ADD COLUMN created_at TEXT');
$db->exec('CREATE TABLE parlor_survey_responses (parlor_id INTEGER, respondent_no INTEGER, criterion_key TEXT, score INTEGER)');
$db->exec('CREATE TABLE parlor_feedback (id INTEGER, parlor_id INTEGER, location INTEGER, budget INTEGER, religion INTEGER, casket INTEGER, service_delivery INTEGER, documentation INTEGER, arrangement INTEGER, flower INTEGER)');
function fixture_layout(string $name): string {
    global $root;
    $source = file_get_contents($root . '/includes/' . $name . '.php');
    $source = preg_replace("~require_once __DIR__ \\. '/(?:\\.\\./config/db|functions)\\.php';~", '', $source);
    $source = str_replace('ob_start();', '', $source);
    return str_replace('__DIR__', var_export($root . '/includes', true), $source);
}
function pdo_connect(): PDO { global $db; return $db; }
$core = file_get_contents($root . '/includes/functions.php');
$core = str_replace("require_once __DIR__ . '/../config/db.php';", '', $core);
$core = str_replace('__DIR__', var_export($root . '/includes', true), $core);
eval('?>' . $core);
$_SESSION['user_id'] = 1;
$_SESSION['csrf_token'] = 'fixture-token';
$_SESSION['selected_parlor_id'] = 10;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/fprs/admin/manage_clients.php';
$_GET=[]; $_POST=[]; $_FILES=[];
$requestBody = '{}';
function fixture_move_uploaded_file(string $source, string $destination): bool { return is_file($source); }
$route = '';
switch ($scenario) {
    case 'selected_fix':
        $test = json_decode(getenv('FPRS_FIX_CASE'), true, 512, JSON_THROW_ON_ERROR);
        if (!in_array($test['route'], ['director/package_action.php','director/inventory_action.php','director/account.php','director/funeral_register.php','director/dashboard.php','admin/shop_action.php','client/account.php','login.php','register.php','client/dashboard.php','director/inventory.php','director/funeral_parlors.php','director/packages.php','admin/serve_document.php','director/inquiries.php'], true)) throw new RuntimeException('Invalid fixture route');
        $db->prepare('UPDATE funeral_parlors SET license_path=? WHERE id=10')->execute(['uploads/sample-licenses/UC_Ddash.jpg']);
        $route=$test['route']; $_SESSION['user_id']=$test['user'] ?? 2;
        if (isset($test['originalParlorContact'])) $db->prepare('UPDATE funeral_parlors SET contact_number=? WHERE id=10')->execute([$test['originalParlorContact']]);
        if (isset($test['parlorStatus']) && in_array($test['parlorStatus'], ['pending','approved','rejected'], true)) $db->prepare('UPDATE funeral_parlors SET status=? WHERE id=10')->execute([$test['parlorStatus']]);
        if (array_key_exists('selectedParlor', $test)) $_SESSION['selected_parlor_id'] = $test['selectedParlor'];
        if ($test['inquiryScope'] ?? false) {
            $db->exec("INSERT INTO users (id,full_name,role,status) VALUES (6,'Second client','client','approved'),(7,'Third client','client','approved')");
            foreach ([1=>10,2=>10,3=>10,4=>11] as $inquiryId=>$parlorId) {
                $db->prepare('INSERT INTO service_inquiries (id,client_id,package_id,parlor_id,package_title,package_price,inquiry_message) VALUES (?,?,?,?,?,100,?)')->execute([$inquiryId,$inquiryId===2?6:($inquiryId===3?7:3),$parlorId===10?100:101,$parlorId,'Scope package '.$inquiryId,'Scope inquiry '.$inquiryId]);
            }
        }
        $_SERVER['REQUEST_METHOD']=$test['method'] ?? 'POST';
        $_POST=$test['post'] ?? []; $_GET=$test['get'] ?? []; $_FILES=$test['files'] ?? [];
        if ($test['token'] ?? true) $_POST['token']='fixture-token';
        $db->exec('CREATE TABLE shops (id INTEGER PRIMARY KEY, admin_id INTEGER, name TEXT, location TEXT, contact_info TEXT, description TEXT)');
        if ($test['shopExisting'] ?? false) $db->exec("INSERT INTO shops (id,name) VALUES (5,'Existing')");
        $db->prepare('UPDATE users SET password=? WHERE id=3')->execute([password_hash('FixtureOldPassword!',PASSWORD_DEFAULT)]);
        if ($test['firebase'] ?? false) $db->exec("UPDATE users SET firebase_uid='fixture-client-uid' WHERE id=3");
        if ($test['long'] ?? false) { $db->prepare('UPDATE packages SET title=? WHERE id=100')->execute([str_repeat('P',200)]); $db->prepare('UPDATE funeral_parlors SET name=? WHERE id=10')->execute([str_repeat('Parlor',33)]); }
        break;

    case 'delete_declined': case 'delete_bad_token': case 'delete_self':
        $route='admin/user_action.php';
        $_GET=['action'=>'delete','id'=>$scenario === 'delete_self' ? 1 : 5,'token'=>$scenario === 'delete_bad_token' ? 'bad' : 'fixture-token','return_to'=>'/fprs/admin/manage_clients.php']; break;
    case 'approve_missing': case 'approve_outside': case 'decline_parlor':
        $route='admin/parlor_action.php';
        $_GET=['action'=>$scenario === 'decline_parlor' ? 'reject' : 'approve','id'=>$scenario === 'approve_outside' ? 11 : 10,'token'=>'fixture-token','return_to'=>'/fprs/admin/manage_parlors.php']; break;
    case 'package_save': case 'package_failure': case 'package_foreign':
        $route='director/package_action.php'; $_SESSION['user_id']=2; $_SERVER['REQUEST_METHOD']='POST';
        $_POST=['id'=>$scenario === 'package_foreign' ? 102 : 100,'title'=>'Changed','price'=>'150','service_names'=>[$scenario === 'package_failure' ? 'FAIL' : 'New service'],'service_details'=>['New detail'],'service_descriptions'=>['Description']]; break;
    case 'inventory_save': case 'inventory_foreign':
        $route='director/inventory_action.php'; $_SESSION['user_id']=2; $_SERVER['REQUEST_METHOD']='POST';
        $_POST=['id'=>$scenario === 'inventory_foreign' ? 102 : 100,'name'=>'Changed resource','quantity'=>0,'available_status'=>'available','item_type'=>'flower']; break;
    case 'contact_outside':
        $route='client/contact.php'; $_SESSION['user_id']=3; $_GET=['director_id'=>11,'package_id'=>101]; break;
    case 'details_outside':
        $route='client/package_details.php'; $_SESSION['user_id']=3; $_GET=['package_id'=>101]; break;
    case 'feedback_outside':
        $route='client/feedback.php'; $_SESSION['user_id']=3; $_GET=['parlor_id'=>11]; break;
    case 'director_outside_dashboard':
        $route='director/dashboard.php'; $_SESSION['user_id']=2; $_SESSION['selected_parlor_id']=11;
        $db->exec("UPDATE funeral_parlors SET status='pending' WHERE id=11"); break;
    case 'director_pending_catalog':
        $route='director/packages.php'; $_SESSION['user_id']=2; break;
    case 'client_catalog':
        $route='director/packages.php'; $_SESSION['user_id']=3; $db->exec("UPDATE funeral_parlors SET status='approved' WHERE id=10"); break;
    case 'messages_rejected':
        $route='api/messages.php'; $_SESSION['user_id']=5; break;
    case 'messages_recent':
        $route='api/messages.php'; $_SESSION['user_id']=3;
        $insert=$db->prepare('INSERT INTO messages (id,sender_id,receiver_id,message_text,sent_at) VALUES (?,3,2,?,?)');
        for($i=1;$i<=205;$i++) $insert->execute([$i,'Message '.$i,'2026-01-01']); break;
    case 'messages_invalid':
        $route='api/messages.php'; $_SESSION['user_id']=3; $_SERVER['REQUEST_METHOD']='POST'; $requestBody='{"to":2,"text":[]}'; break;
    case 'firebase_unverified': case 'firebase_verified': case 'firebase_link_conflict':
        $route='api/firebase_auth_sync.php'; $_SERVER['REQUEST_METHOD']='POST'; $requestBody='{"idToken":"fixture","mode":"login"}';
        if ($scenario === 'firebase_link_conflict') $db->exec("UPDATE users SET firebase_uid='other-uid' WHERE id=3");
        function firebase_auth(): object {
            global $scenario;
            return new class($scenario) {
                public function __construct(private string $scenario) {}
                public function verifyIdToken(string $token): array { return ['uid'=>'fixture-uid','email'=>'client@fixture.invalid','emailVerified'=>$this->scenario !== 'firebase_unverified']; }
            };
        }
        break;
    case 'original_reviews':
        $route='client/package_details.php'; $_SESSION['user_id']=3; $_GET=['package_id'=>100];
        $db->exec("UPDATE funeral_parlors SET status='approved' WHERE id=10; ALTER TABLE parlor_feedback ADD COLUMN comment TEXT; ALTER TABLE parlor_feedback ADD COLUMN created_at TEXT; INSERT INTO parlor_feedback VALUES (1,10,4,4,4,4,4,4,4,4,'Recorded original review','2026-01-01')");
        break;
    case 'inquiry_contact_get': case 'inquiry_contact_post': case 'inquiry_again_get': case 'inquiry_again_post': case 'inquiry_active_post': case 'inquiry_bad_token': case 'inquiry_package_mismatch':
        $route='client/contact.php'; $_SESSION['user_id']=3;
        $db->exec("UPDATE funeral_parlors SET status='approved' WHERE id=10");
        $_GET=['director_id'=>10,'package_id'=>$scenario === 'inquiry_package_mismatch' ? 102 : 100];
        if (in_array($scenario, ['inquiry_again_get','inquiry_again_post','inquiry_active_post'], true)) {
            $db->exec("INSERT INTO service_inquiries (id,client_id,package_id,parlor_id,package_title,package_price,inquiry_message,status,confirmed_at,confirmed_by) VALUES (1,3,100,10,'Original',100,'Earlier inquiry','availed','2026-10-20',2)");
            if ($scenario === 'inquiry_active_post') $db->exec("UPDATE service_inquiries SET status='confirmed' WHERE id=1");
        }
        if (!in_array($scenario, ['inquiry_contact_get','inquiry_again_get'], true)) {
            $_SERVER['REQUEST_METHOD']='POST';
            $_POST=['inquiry_message'=>'Please confirm package availability.','after_inquiry_id'=>in_array($scenario, ['inquiry_again_post','inquiry_active_post'], true) ? 1 : 0,'token'=>$scenario === 'inquiry_bad_token' ? 'bad' : 'fixture-token'];
        }
        break;
    case 'inquired_services': case 'inquired_confirmed_services': case 'inquired_availed_services': case 'feedback_unconfirmed': case 'feedback_unconfirmed_form': case 'feedback_incomplete': case 'feedback_spoof_confirm': case 'director_inquiry_confirm': case 'director_inquiry_client': case 'feedback_form': case 'feedback_submit': case 'feedback_foreign': case 'feedback_duplicate': case 'feedback_cancelled': case 'feedback_bad_token': case 'feedback_invalid': case 'director_inquiry_foreign': case 'director_inquiry_repeat': case 'director_inquiry_list': case 'package_rating_api':
        $db->exec("UPDATE funeral_parlors SET status='approved' WHERE id=10");
        $db->exec("INSERT INTO service_inquiries (id,client_id,package_id,parlor_id,package_title,package_price,inquiry_message) VALUES (1,3,100,10,'Original',100,'Availability please')");
        $_SESSION['user_id']=3;
        $_GET=['inquiry_id'=>1];
        if (!in_array($scenario, ['inquired_services','feedback_unconfirmed','feedback_unconfirmed_form','feedback_spoof_confirm','director_inquiry_confirm','director_inquiry_client','director_inquiry_list'], true)) $db->exec("UPDATE service_inquiries SET status='confirmed',confirmed_at='2026-10-20 09:00:00',confirmed_by=2 WHERE id=1");
        if ($scenario === 'inquired_availed_services') $db->exec("UPDATE service_inquiries SET status='availed' WHERE id=1");
        if (in_array($scenario, ['inquired_services','inquired_confirmed_services','inquired_availed_services'], true)) {$route='client/inquired_services.php'; break;}
        if ($scenario === 'package_rating_api') {$route='api/package_ratings.php'; $db->exec('INSERT INTO package_feedback (inquiry_id,rating) VALUES (1,5)'); break;}
        if (str_starts_with($scenario,'director_inquiry_')) {
            $route='director/inquiries.php'; if ($scenario === 'director_inquiry_foreign') $_SESSION['selected_parlor_id']=12; $_SESSION['user_id']=$scenario === 'director_inquiry_foreign' ? 4 : ($scenario === 'director_inquiry_client' ? 3 : 2);
            if ($scenario === 'director_inquiry_list') break;
            $_SERVER['REQUEST_METHOD']='POST'; $_POST=['inquiry_id'=>1,'action'=>in_array($scenario, ['director_inquiry_confirm','director_inquiry_client'], true) ? 'confirm' : 'repeat','token'=>'fixture-token'];
            if (!in_array($scenario, ['director_inquiry_confirm','director_inquiry_client'], true)) $db->exec("UPDATE service_inquiries SET status='availed',confirmed_at='2026-10-20 09:00:00',confirmed_by=2 WHERE id=1"); break;
        }
        $route='client/feedback.php';
        if (in_array($scenario, ['feedback_form','feedback_unconfirmed_form'], true)) break;
        if ($scenario === 'feedback_foreign') $db->exec('UPDATE service_inquiries SET client_id=2 WHERE id=1');
        if ($scenario === 'feedback_duplicate') $db->exec('INSERT INTO package_feedback (inquiry_id,rating) VALUES (1,5)');
        if ($scenario === 'feedback_cancelled') $db->exec("UPDATE service_inquiries SET status='cancelled' WHERE id=1");
        $_SERVER['REQUEST_METHOD']='POST';
        $_POST=['inquiry_id'=>1,'answers'=>array_fill_keys(feedback_question_keys(), $scenario === 'feedback_invalid' ? '6' : '2'),'comment'=>'Actual experience','token'=>$scenario === 'feedback_bad_token' ? 'bad' : 'fixture-token'];
        if ($scenario === 'feedback_incomplete') unset($_POST['answers']['location_1']);
        if ($scenario === 'feedback_spoof_confirm') {$_POST['confirmed_by']=2; $_POST['status']='confirmed';}
        break;
    case 'admin_alignment_catalog':
        $route='admin/manage_all_accounts.php';
        $db->sqliteCreateFunction('FIELD', static fn($value, ...$values) => (int)array_search($value, $values, true) + 1);
        $db->exec("UPDATE funeral_parlors SET status='approved' WHERE id=10");
        $db->prepare('UPDATE users SET full_name=?,email=? WHERE id=2')->execute([str_repeat('LongDirectorName', 8),str_repeat('longemail', 12).'@fixture.invalid']);
        $db->prepare('UPDATE funeral_parlors SET name=? WHERE id=10')->execute([str_repeat('LongParlorName', 8)]);
        break;
    case 'admin_pending_catalog':
        $route='admin/manage_directors.php';
        $db->prepare("UPDATE users SET status='pending',full_name=?,email=? WHERE id=2")->execute([str_repeat('LongDirectorName', 8),str_repeat('longemail', 12).'@fixture.invalid']);
        break;
    case 'admin_parlor_catalog':
        $route='admin/manage_parlors.php';
        $db->prepare('UPDATE funeral_parlors SET name=?,contact_number=? WHERE id=10')->execute([str_repeat('LongParlorName', 8),str_repeat('1234567890', 8)]);
        break;
    case 'admin_declined_catalog':
        $route='admin/manage_clients.php'; break;
    case 'package_form':
        $route='director/package_action.php'; $_SESSION['user_id']=2; $_GET=['action'=>'create']; break;
    case 'client_dashboard': case 'client_inc_false_positive': case 'client_inc_acronym': case 'client_same_municipality': case 'client_low_budget':
        $route='client/dashboard.php'; $_SESSION['user_id']=3;
        $db->exec("UPDATE funeral_parlors SET status='approved' WHERE id=10");
        $religion = $scenario === 'client_dashboard' ? 'Roman Catholic' : 'Iglesia ni Cristo';
        $db->prepare('INSERT INTO client_preferences VALUES (3,100,?,?)')->execute([$religion,'Bacong, Guimbal']);
        if (in_array($scenario, ['client_inc_false_positive','client_inc_acronym'], true)) $db->prepare('UPDATE packages SET title=?, inclusions=? WHERE id=100')->execute([$scenario === 'client_inc_acronym' ? 'INC Memorial' : 'Package includes transport','Transport and casket']);
        if (in_array($scenario, ['client_same_municipality','client_low_budget'], true)) {
            $db->exec("UPDATE client_preferences SET budget=1, religion='Christian / Protestant', location='Nanga, Guimbal' WHERE client_id=3");
            $db->exec("INSERT INTO funeral_parlors VALUES (15,2,'More services','Baras, Guimbal','','','approved',NULL,'2026-01-01'); INSERT INTO packages VALUES (103,2,15,'Comprehensive local package','Casket - Wood, Flowers - Basic, Documentation - Full, Transport - Local',200,NULL,'2026-01-01')");
        }
        $db->exec("INSERT INTO funeral_parlors VALUES (13,2,'Outside without package','Batuan Ilaya, Oton','','','approved',NULL,'2026-01-01'),(14,2,'Scoped without package','Nanga, Guimbal','','','approved',NULL,'2026-01-01')"); break;
    case 'maintenance_extract_http':
        $route='scripts/extract_docx.php'; break;
    case 'maintenance_http':
        $route='scripts/reset_passwords.php'; break;
    default: throw new RuntimeException('Unknown fixture scenario');
}
if (in_array($scenario, ['package_save','package_failure','package_foreign','inventory_save','inventory_foreign'], true)) $_POST['token']='fixture-token';
ob_start();
register_shutdown_function(function() use ($db,$scenario) {
    $body=ob_get_clean();
    $bodyHash=hash('sha256',$body);
    if (!preg_match('//u',$body)) $body='[binary document]';
    $clientHash=$db->query('SELECT password FROM users WHERE id=3')->fetchColumn();
    $visible=$db->query("SELECT p.id,fp.location FROM packages p JOIN funeral_parlors fp ON fp.id=p.parlor_id JOIN users u ON u.id=fp.director_id WHERE fp.status='approved' AND u.status='approved'")->fetchAll();
    $visible=array_values(array_map(static fn($p)=>(int)$p['id'],array_filter($visible,static fn($p)=>is_in_service_area($p['location']))));
    $result=['client_visible_package_ids'=>$visible,'before_admin_status'=>$GLOBALS['beforeAdminStatus'] ?? null,'selected_parlor_id'=>$_SESSION['selected_parlor_id'] ?? null,'body_sha256'=>$bodyHash,'client_password_matches_new'=>is_string($clientHash)&&password_verify('NewPassword123!',$clientHash),'inquiries'=>$db->query('SELECT * FROM service_inquiries ORDER BY id')->fetchAll(),'package_feedback'=>$db->query('SELECT * FROM package_feedback ORDER BY id')->fetchAll(),'questionnaire_answers'=>$db->query('SELECT * FROM package_feedback_answers ORDER BY feedback_id,criterion_key')->fetchAll(),'message_count'=>(int)$db->query('SELECT COUNT(*) FROM messages')->fetchColumn(),'scenario'=>$scenario,'status'=>http_response_code() ?: 200,'body'=>$body,'users'=>$db->query('SELECT id,firebase_uid,full_name,phone,password FROM users ORDER BY id')->fetchAll(),'parlors'=>$db->query('SELECT id,status,name,location,contact_number,license_path FROM funeral_parlors ORDER BY id')->fetchAll(),'packages'=>$db->query('SELECT id,title FROM packages ORDER BY id')->fetchAll(),'preferences'=>$db->query('SELECT * FROM client_preferences')->fetchAll(),'prices'=>$db->query('SELECT id,price FROM packages ORDER BY id')->fetchAll(),'shops'=>$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='shops'")->fetchColumn() ? $db->query('SELECT * FROM shops')->fetchAll() : [],'inclusions'=>$db->query('SELECT service_name FROM package_inclusions ORDER BY id')->fetchAll(),'resources'=>$db->query('SELECT id,name,quantity,available_status FROM inventory_resources ORDER BY id')->fetchAll(),'notice'=>$_SESSION['admin_notice'] ?? null];
    session_destroy();
    echo json_encode($result);
});
$file=$root . '/' . $route;
$code=file_get_contents($file);
if (($argv[2] ?? '') === 'render') {
    foreach (['header','footer'] as $part) {
        $code=preg_replace("~(?:require_once|include) __DIR__ \\. '/\\.\\./includes/" . $part . "\\.php';~", "eval('?>' . fixture_layout('" . $part . "'));", $code);
    }
}
if (($scenario === 'selected_fix' && $route === 'client/dashboard.php') || in_array($scenario, ['client_dashboard','client_inc_false_positive','client_inc_acronym','client_same_municipality','client_low_budget'], true)) {
    // Schema-only bootstrap is omitted; filtering/ranking SQL runs unchanged.
    $code=preg_replace('/^\\$pdo->exec\\("CREATE TABLE IF NOT EXISTS funeral_parlors[^\\n]+\\n/m', '', $code);
    $schemaStart = strpos($code, '$columnCheck = ');
    $schemaEnd = $schemaStart !== false ? strpos($code, 'require_once __DIR__', $schemaStart) : false;
    if ($schemaStart !== false && $schemaEnd !== false) $code = substr($code, 0, $schemaStart) . substr($code, $schemaEnd);
}
$code=preg_replace("~(?:require_once|include) __DIR__ \\. '/\\.\\./(?:config/db|includes/(?:header|footer|functions|firebase_init))\\.php';~", '', $code);
$code=preg_replace('/^\s*\$pdo->exec\("CREATE TABLE IF NOT EXISTS funeral_parlors[^\n]+\n/m', '', $code);
$code=preg_replace('/^ensure_[a-z_]+\\(\\$pdo\\);\\s*$/m', '', $code);
$code=str_replace('$pdo->exec("ALTER TABLE inventory_resources MODIFY item_type ENUM(\'flower\',\'candle\',\'plant\',\'casket\',\'equipment\',\'other\') NOT NULL");', '', $code);
$code=str_replace("file_get_contents('php://input')", '$requestBody', $code);
if (in_array($scenario, ['maintenance_http','maintenance_extract_http'], true)) $code=str_replace('PHP_SAPI', "'apache2handler'", $code);
$code=preg_replace("~(?:require_once|include) __DIR__ \\. '/(?:config/db|includes/(?:header|footer|functions))\\.php';~", '', $code);
$code=str_replace('__DIR__', var_export(dirname($file),true), $code);
if ($scenario === 'selected_fix' && !empty($test['mockLicenseUpload'])) {
    // Simulate PHP's uploaded-file move boundary without creating real uploads.
    $code = str_replace('move_uploaded_file(', 'fixture_move_uploaded_file(', $code);
}
eval('?>' . $code);
if ($scenario === 'selected_fix' && !empty($test['thenAdminApprove'])) {
    $beforeAdminStatus=$db->query('SELECT status FROM funeral_parlors WHERE id=10')->fetchColumn();
    $_SESSION['user_id']=1; $_SERVER['REQUEST_METHOD']='GET'; $_POST=[];
    $_GET=['action'=>'approve','id'=>10,'token'=>'fixture-token'];
    $adminCode=file_get_contents($root.'/admin/parlor_action.php');
    $adminCode=str_replace('ensure_director_scope_columns($pdo);', '', $adminCode);
    $adminCode=preg_replace("~require_once __DIR__ \\. '/\\.\\./(?:config/db|includes/functions)\\.php';~", '', $adminCode);
    $adminCode=str_replace('__DIR__', var_export($root.'/admin',true),$adminCode);
    eval('?>' . $adminCode);
}
