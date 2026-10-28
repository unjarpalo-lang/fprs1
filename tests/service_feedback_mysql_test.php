<?php
/** Real MySQL SQL/locking/unique-index checks with connection-local temporary tables only. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/functions.php';
$pdo = pdo_connect();
function verify_service(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function blocked_service(callable $action, string $expected): void {
    try { $action(); } catch (Throwable $e) {
        if ($e instanceof $expected) return;
        throw $e;
    }
    throw new RuntimeException('Expected rejected service operation.');
}
$clientId = (int)$pdo->query("SELECT id FROM users WHERE role='client' AND status='approved' ORDER BY id LIMIT 1")->fetchColumn();
$candidates = $pdo->query("SELECT p.id,fp.director_id,fp.location FROM packages p JOIN funeral_parlors fp ON fp.id=p.parlor_id JOIN users u ON u.id=fp.director_id WHERE fp.status='approved' AND u.status='approved'")->fetchAll();
$package = null;
foreach ($candidates as $candidate) if (is_in_service_area($candidate['location'])) { $package=$candidate; break; }
if (!$clientId || !$package) throw new RuntimeException('An existing approved client and in-scope package are needed for read-only fixtures.');
// These shadow the real tables only on this PDO connection. No persistent records are inserted.
foreach (['service_inquiries','package_feedback','package_feedback_answers','messages'] as $table) {
    $temporary = 'fixture_' . $table . '_' . bin2hex(random_bytes(4));
    $pdo->exec("CREATE TEMPORARY TABLE {$temporary} LIKE {$table}");
    $pdo->exec("ALTER TABLE {$temporary} RENAME TO {$table}");
}
$packageId=(int)$package['id']; $directorId=(int)$package['director_id'];
$scores=static fn(int $value): array => array_fill_keys(feedback_question_keys(),$value);
$first=send_package_inquiry($pdo,$clientId,$packageId,'Is this service available?');
verify_service($first > 0,'Inquiry creation');
verify_service(send_package_inquiry($pdo,$clientId,$packageId,'Repeated click') === $first,'Idempotent initial inquiry');
verify_service((int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn() === 1,'One sent message');
blocked_service(fn()=>submit_package_feedback($pdo,$clientId+100000,$first,$scores(5),''),DomainException::class);
blocked_service(fn()=>submit_package_feedback($pdo,$clientId,$first,$scores(0),''),InvalidArgumentException::class);
blocked_service(fn()=>submit_package_feedback($pdo,$clientId,$first,$scores(5),'Too early'),DomainException::class);
manage_service_experience($pdo,$directorId,$first,'confirm');
submit_package_feedback($pdo,$clientId,$first,$scores(5),'Excellent');
verify_service((int)$pdo->query('SELECT COUNT(*) FROM package_feedback_answers')->fetchColumn() === 22,'Every questionnaire response is retained');
blocked_service(fn()=>submit_package_feedback($pdo,$clientId,$first,$scores(1),'Overwrite'),LogicException::class);
verify_service(package_rating_summary($pdo,$packageId) === ['average'=>5.0,'count'=>1],'First package average');
blocked_service(fn()=>manage_service_experience($pdo,$directorId,$first,'repeat'),InvalidArgumentException::class);
verify_service(send_package_inquiry($pdo,$clientId,$packageId,'Still active',$first) === $first,'Confirmed but unavailed services cannot be repeated');
blocked_service(fn()=>manage_service_experience($pdo,$directorId+100000,$first,'avail'),DomainException::class);
manage_service_experience($pdo,$directorId,$first,'avail');
$second=send_package_inquiry($pdo,$clientId,$packageId,'I need the same package again.',$first);
verify_service($second !== $first,'Client can inquire again after availing');
verify_service(send_package_inquiry($pdo,$clientId,$packageId,'Repeated form submit',$first) === $second,'Duplicate reorder form does not create another inquiry');
blocked_service(fn()=>submit_package_feedback($pdo,$clientId,$second,$scores(1),'Too early again'),DomainException::class);
manage_service_experience($pdo,$directorId,$second,'confirm');
submit_package_feedback($pdo,$clientId,$second,$scores(1),'Different experience on repeat order');
verify_service(package_rating_summary($pdo,$packageId) === ['average'=>3.0,'count'=>2],'Average decreases on lower repeat rating');
manage_service_experience($pdo,$directorId,$second,'avail');
blocked_service(fn()=>send_package_inquiry($pdo,$clientId,$packageId,'Stale reorder form',$first),DomainException::class);
$third=send_package_inquiry($pdo,$clientId,$packageId,'Another service request.',$second);
manage_service_experience($pdo,$directorId,$third,'confirm');
submit_package_feedback($pdo,$clientId,$third,$scores(5),'Improved service');
$summary=package_rating_summary($pdo,$packageId);
verify_service($summary['count'] === 3 && abs($summary['average'] - 11/3) < 0.0001,'Average increases on higher repeat rating');
// Confirm the unique index independently of the application-level duplicate check.
blocked_service(fn()=>$pdo->prepare('INSERT INTO package_feedback (inquiry_id,rating) VALUES (?,?)')->execute([$first,2]),PDOException::class);
manage_service_experience($pdo,$directorId,$third,'avail');
$fourth=send_package_inquiry($pdo,$clientId,$packageId,'Another inquiry for this package.',$third);
manage_service_experience($pdo,$directorId,$fourth,'confirm');
$pdo->exec("ALTER TABLE package_feedback_answers ADD CONSTRAINT fixture_answer_failure CHECK (criterion_key <> 'flower_1' OR rating <> 2)");
blocked_service(fn()=>submit_package_feedback($pdo,$clientId,$fourth,$scores(2),'Forced answer failure'),PDOException::class);
verify_service((int)$pdo->query('SELECT COUNT(*) FROM package_feedback')->fetchColumn() === 3,'Partial questionnaire does not leave a review');
verify_service((int)$pdo->query('SELECT COUNT(*) FROM package_feedback_answers')->fetchColumn() === 66,'Partial questionnaire answers are rolled back');
$mixed=$scores(3); $mixed['location_1']=4;
submit_package_feedback($pdo,$clientId,$fourth,$mixed,'Mixed questionnaire scores');
$savedRating=(float)$pdo->query('SELECT rating FROM package_feedback ORDER BY id DESC LIMIT 1')->fetchColumn();
verify_service(abs($savedRating - 3.05) < 0.0001,'Fractional questionnaire average is preserved');
$otherPackage = null;
foreach ($candidates as $candidate) {
    if ((int)$candidate['id'] !== $packageId && is_in_service_area($candidate['location'])) { $otherPackage=(int)$candidate['id']; break; }
}
if ($otherPackage) {
    $pdo->exec("ALTER TABLE messages ADD CONSTRAINT fixture_delivery_failure CHECK (message_text NOT LIKE '%ROLLBACK_TEST%')");
    blocked_service(fn()=>send_package_inquiry($pdo,$clientId,$otherPackage,'ROLLBACK_TEST'),PDOException::class);
    verify_service((int)$pdo->query('SELECT COUNT(*) FROM service_inquiries')->fetchColumn() === 4,'Failed message delivery rolls back review entitlement');
}
echo "PASS: MySQL inquiry delivery, ownership, repeat-order entitlement, one-review constraint, and average up/down checks (temporary tables only).\n";
