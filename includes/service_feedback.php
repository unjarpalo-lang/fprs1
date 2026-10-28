<?php
declare(strict_types=1);
require_once __DIR__ . '/feedback_questionnaire.php';

/** Use row locks on MySQL; SQLite fixtures serialize writes themselves. */
function service_row_lock(PDO $pdo): string {
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
}

/** An inquiry exists only after its message is committed to the director inbox. */
function send_package_inquiry(PDO $pdo, int $clientId, int $packageId, string $message, int $afterInquiryId = 0): int {
    $message = trim($message);
    if ($message === '' || mb_strlen($message) > 4000) throw new InvalidArgumentException('Enter an inquiry of 1 to 4,000 characters.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT p.id, p.title, p.price, fp.id AS parlor_id, fp.location, fp.director_id
            FROM packages p JOIN funeral_parlors fp ON fp.id=p.parlor_id JOIN users u ON u.id=fp.director_id
            WHERE p.id=? AND fp.status='approved' AND u.status='approved'" . service_row_lock($pdo));
        $stmt->execute([$packageId]);
        $package = $stmt->fetch();
        if (!$package || !is_in_service_area($package['location'])) throw new DomainException('This package is no longer available for inquiry.');
        $existing = $pdo->prepare('SELECT * FROM service_inquiries WHERE client_id=? AND package_id=? ORDER BY occurrence_no DESC LIMIT 1' . service_row_lock($pdo));
        $existing->execute([$clientId, $packageId]);
        $previous = $existing->fetch();
        $occurrenceNo = 1;
        if ($previous) {
            $canReinquire = ($previous['status'] === 'availed' && service_feedback_eligible($previous)) || $previous['status'] === 'cancelled';
            if (!$canReinquire || !$afterInquiryId) {
                $pdo->commit();
                return (int)$previous['id'];
            }
            if ($afterInquiryId !== (int)$previous['id']) throw new DomainException('Your inquiry history has changed. Reload the contact page before sending another inquiry.');
            $occurrenceNo = (int)$previous['occurrence_no'] + 1;
        } elseif ($afterInquiryId) {
            throw new DomainException('The previous inquiry could not be found. Reload the contact page.');
        }
        $insert = $pdo->prepare('INSERT INTO service_inquiries (client_id,package_id,parlor_id,occurrence_no,package_title,package_price,inquiry_message) VALUES (?,?,?,?,?,?,?)');
        $insert->execute([$clientId,$packageId,$package['parlor_id'],$occurrenceNo,$package['title'],$package['price'],$message]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO messages (sender_id,receiver_id,message_text) VALUES (?,?,?)')->execute([
            $clientId, $package['director_id'], 'Inquiry #' . $id . ' - ' . $package['title'] . ': ' . $message,
        ]);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function client_service_experience(PDO $pdo, int $clientId, int $inquiryId): ?array {
    $stmt = $pdo->prepare('SELECT i.*, fp.name AS parlor_name, f.id AS feedback_id, f.rating, f.comment
        FROM service_inquiries i JOIN funeral_parlors fp ON fp.id=i.parlor_id
        LEFT JOIN package_feedback f ON f.inquiry_id=i.id WHERE i.id=? AND i.client_id=?');
    $stmt->execute([$inquiryId,$clientId]);
    return $stmt->fetch() ?: null;
}

/** Immutable review: a second POST cannot replace or append to this experience. */
function submit_package_feedback(PDO $pdo, int $clientId, int $inquiryId, array $input, string $comment): void {
    $answers = validated_questionnaire_answers($input);
    if (mb_strlen($comment) > 2000) throw new InvalidArgumentException('Comments must be at most 2,000 characters.');
    $rating = round(array_sum($answers) / count($answers), 2);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id,status,confirmed_at,confirmed_by FROM service_inquiries WHERE id=? AND client_id=?' . service_row_lock($pdo));
        $stmt->execute([$inquiryId,$clientId]);
        $experience = $stmt->fetch();
        if (!$experience || !service_feedback_eligible($experience)) throw new DomainException('Feedback is available only after the director confirms your inquiry or service.');
        $existing = $pdo->prepare('SELECT id FROM package_feedback WHERE inquiry_id=?');
        $existing->execute([$inquiryId]);
        if ($existing->fetchColumn()) throw new LogicException('Feedback has already been submitted for this experience.');
        $pdo->prepare('INSERT INTO package_feedback (inquiry_id,rating,comment) VALUES (?,?,?)')->execute([$inquiryId,$rating,trim($comment) ?: null]);
        $feedbackId = (int)$pdo->lastInsertId();
        $saveAnswer = $pdo->prepare('INSERT INTO package_feedback_answers (feedback_id,criterion_key,rating) VALUES (?,?,?)');
        foreach ($answers as $key=>$answer) $saveAnswer->execute([$feedbackId,$key,$answer]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Only the owning director can confirm inquiries or mark services availed. */
function manage_service_experience(PDO $pdo, int $directorId, int $inquiryId, string $action, ?int $parlorId = null): void {
    if (!in_array($action, ['confirm','avail','cancel'], true)) throw new InvalidArgumentException('Unknown inquiry action.');
    $pdo->beginTransaction();
    try {
        // Lock the initial record for this pair to serialize repeat occurrence numbers.
        $scope = $parlorId === null ? '' : ' AND i.parlor_id=?';
        $params = $parlorId === null ? [$inquiryId,$directorId] : [$inquiryId,$directorId,$parlorId];
        $stmt = $pdo->prepare('SELECT i.* FROM service_inquiries i JOIN funeral_parlors fp ON fp.id=i.parlor_id WHERE i.id=? AND fp.director_id=?' . $scope);
        $stmt->execute($params);
        $record = $stmt->fetch();
        if (!$record) throw new DomainException('Inquiry not found for your parlor.');
        $lock = $pdo->prepare('SELECT id FROM service_inquiries WHERE client_id=? AND package_id=? AND occurrence_no=1' . service_row_lock($pdo));
        $lock->execute([$record['client_id'],$record['package_id']]);
        $lock->fetchColumn();
        $current = $pdo->prepare('SELECT i.* FROM service_inquiries i JOIN funeral_parlors fp ON fp.id=i.parlor_id WHERE i.id=? AND fp.director_id=?' . $scope . service_row_lock($pdo));
        $current->execute($params);
        $record = $current->fetch();
        if (!$record) throw new DomainException('Inquiry not found for your parlor.');
        if ($action === 'confirm') {
            if (!in_array($record['status'], ['inquired','availed'], true) || service_feedback_eligible($record)) throw new DomainException('This inquiry cannot be confirmed again.');
            $pdo->prepare("UPDATE service_inquiries SET status=?,confirmed_at=CURRENT_TIMESTAMP,confirmed_by=? WHERE id=?")
                ->execute([$record['status'] === 'availed' ? 'availed' : 'confirmed',$directorId,$inquiryId]);
        } elseif ($action === 'avail') {
            if ($record['status'] !== 'confirmed' || !service_feedback_eligible($record)) throw new DomainException('Confirm the inquiry before marking the service availed.');
            $pdo->prepare("UPDATE service_inquiries SET status='availed' WHERE id=?")->execute([$inquiryId]);
        } else {
            if (!in_array($record['status'], ['inquired','confirmed'], true)) throw new DomainException('Only an open inquiry can be cancelled.');
            $pdo->prepare("UPDATE service_inquiries SET status='cancelled' WHERE id=?")->execute([$inquiryId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Recompute from committed, attributable reviews; never persist a stale average. */
function package_rating_summary(PDO $pdo, int $packageId): array {
    $stmt = $pdo->prepare('SELECT AVG(f.rating) AS average_rating, COUNT(*) AS review_count FROM package_feedback f JOIN service_inquiries i ON i.id=f.inquiry_id WHERE i.package_id=?');
    $stmt->execute([$packageId]);
    $row = $stmt->fetch();
    return ['average'=>(float)($row['average_rating'] ?? 0), 'count'=>(int)($row['review_count'] ?? 0)];
}

function render_package_rating(PDO $pdo, int $packageId): void {
    $hasRecordedReviews = false;
    // Preserve the original parlor survey/feedback score shown before package reviews.
    $parlor = $pdo->prepare('SELECT parlor_id FROM packages WHERE id=?');
    $parlor->execute([$packageId]);
    $parlorId = (int)$parlor->fetchColumn();
    if ($parlorId) {
        require_once __DIR__ . '/CollaborativeEngine.php';
        static $originalScores = [];
        $cacheKey = spl_object_id($pdo) . ':' . $parlorId;
        if (!isset($originalScores[$cacheKey])) $originalScores[$cacheKey] = (new CollaborativeEngine($pdo))->scoreParlor($parlorId, []);
        $original = $originalScores[$cacheKey];
        if ($original['respondent_count']) {
            $hasRecordedReviews = true;
            ?><span class="f-pill bg-emerald-100 text-emerald-800" data-parlor-rating="true" title="Parlor survey and client feedback ratings">&#9733; <?= number_format($original['score'] * 5,1) ?> (<?= (int)$original['respondent_count'] ?> review<?= $original['respondent_count'] === 1 ? '' : 's' ?>)</span><?php
        }
    }
    $summary = package_rating_summary($pdo,$packageId);
    ?>
    <span class="f-pill bg-emerald-100 text-emerald-800" data-package-rating-id="<?= $packageId ?>" data-has-recorded-reviews="<?= $hasRecordedReviews ? '1' : '0' ?>" <?= $hasRecordedReviews && !$summary['count'] ? 'style="display:none"' : '' ?>><?= $summary['count'] ? '&#9733; ' . number_format($summary['average'],1) . '/5 (' . $summary['count'] . ' review' . ($summary['count'] === 1 ? '' : 's') . ')' : ($hasRecordedReviews ? '' : 'No reviews yet') ?></span>
    <?php
}
