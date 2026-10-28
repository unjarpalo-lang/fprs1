<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['client']);
$pdo = pdo_connect();
$user = current_user();
$inquiryId = (int)($_GET['inquiry_id'] ?? $_POST['inquiry_id'] ?? 0);
$experience = client_service_experience($pdo, (int)$user['id'], $inquiryId);
if (!$experience) {
    http_response_code(403);
    echo '<p class="rounded-xl bg-red-50 p-5 text-red-800">Feedback requires one of your recorded inquiries or services. Open Inquired Services to choose an eligible experience.</p>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}
$error = '';
$postedAnswers = is_array($_POST['answers'] ?? null) ? $_POST['answers'] : [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token(is_string($_POST['token'] ?? null) ? $_POST['token'] : null);
    try {
        $comment = is_string($_POST['comment'] ?? null) ? trim($_POST['comment']) : '';
        submit_package_feedback($pdo, (int)$user['id'], $inquiryId, $postedAnswers, $comment);
        $_SESSION['service_notice'] = 'Thank you. Your review was saved and the package rating has been recalculated.';
        $_SESSION['reviewed_package_id'] = (int)$experience['package_id'];
        header('Location: ' . BASE_PATH . '/client/inquired_services.php#inquiry-' . $inquiryId);
        exit;
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        $error = $e->getMessage();
    } catch (DomainException | LogicException $e) {
        http_response_code(409);
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Package feedback failed: ' . $e->getMessage());
        http_response_code(503);
        $error = 'Your feedback could not be saved. Please try again.';
    }
}
$experience = client_service_experience($pdo, (int)$user['id'], $inquiryId) ?? $experience;
$eligible = !$experience['feedback_id'] && service_feedback_eligible($experience);
?>
<div class="mx-auto max-w-2xl space-y-5 py-5" style="overflow-wrap:anywhere">
  <section class="f-card p-5 sm:p-7">
    <?php render_catalog_back_link(BASE_PATH . '/client/inquired_services.php', 'Back to Inquired Services'); ?>
    <h1 class="text-3xl font-bold text-funeral-800">Rate <?= htmlspecialchars($experience['package_title']) ?></h1>
    <p class="mt-2 text-sm text-stone-600"><?= htmlspecialchars($experience['parlor_name']) ?></p>
    <p class="mt-2 text-sm text-stone-600">One questionnaire per director-confirmed inquiry or order. Submitted feedback cannot be changed or submitted again.</p>
    <div class="mt-3"><?php render_package_rating($pdo,(int)$experience['package_id']); ?></div>
  </section>
  <?php if ($error): ?><p role="alert" class="rounded-xl bg-red-50 p-4 text-red-800"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($eligible): ?>
    <form method="post" class="f-card space-y-5 p-5 sm:p-7">
      <input type="hidden" name="token" value="<?= htmlspecialchars(csrf_token()) ?>" />
      <input type="hidden" name="inquiry_id" value="<?= $inquiryId ?>" />
      <div class="rounded-xl border border-funeral-200 bg-funeral-50 p-4">
        <h2 class="font-semibold text-funeral-800">Instructions</h2>
        <p class="mt-2 text-sm leading-6 text-stone-700">Please read each statement carefully. Select one rating for every statement based on your actual experience. There are no right or wrong answers.</p>
        <p class="mt-2 text-sm leading-6 text-stone-700"><strong>5</strong> &mdash; Strongly Agree; <strong>4</strong> &mdash; Agree; <strong>3</strong> &mdash; Neutral; <strong>2</strong> &mdash; Disagree; <strong>1</strong> &mdash; Strongly Disagree.</p>
      </div>
      <?php $questionNo=0; foreach (feedback_questionnaire() as $category=>$group): ?>
        <section class="space-y-5 border-t border-stone-200 pt-5">
          <h2 class="text-lg font-semibold text-funeral-800"><?= htmlspecialchars($group['label']) ?></h2>
          <?php foreach ($group['statements'] as $index=>$statement): $questionNo++; $key=$category . '_' . ($index+1); ?>
            <fieldset class="min-w-0 border-b border-stone-100 pb-5 last:border-0" role="radiogroup" aria-labelledby="question-<?= $key ?>">
              <legend id="question-<?= $key ?>" class="mb-3 w-full text-sm font-medium leading-6 text-stone-800"><?= $questionNo ?>. <?= htmlspecialchars($statement) ?></legend>
              <div class="grid grid-cols-5 gap-1">
                <?php foreach ([5=>'Strongly Agree',4=>'Agree',3=>'Neutral',2=>'Disagree',1=>'Strongly Disagree'] as $value=>$meaning): ?>
                  <?php $selected=is_scalar($postedAnswers[$key] ?? null) && (string)$postedAnswers[$key] === (string)$value; ?>
                  <label class="rating-option flex min-h-11 min-w-0 cursor-pointer items-center justify-center rounded-xl border-2 <?= $selected ? 'border-funeral-500 bg-funeral-50' : 'border-stone-200 bg-white' ?> px-1 py-2 text-sm font-semibold text-stone-700 transition focus-within:ring-2 focus-within:ring-funeral-500" title="<?= $meaning ?>">
                    <input type="radio" name="answers[<?= $key ?>]" value="<?= $value ?>" required class="sr-only" <?= $selected ? 'checked' : '' ?> />
                    <span aria-hidden="true"><?= $value ?></span><span class="sr-only"><?= $value ?> &mdash; <?= $meaning ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>
      <div>
        <label for="review-comment" class="mb-2 block text-sm font-medium text-stone-700">Your review (optional)</label>
        <textarea id="review-comment" name="comment" rows="4" maxlength="2000" class="w-full rounded-xl border border-stone-300 px-4 py-3"><?= htmlspecialchars(is_string($_POST['comment'] ?? null) ? $_POST['comment'] : '') ?></textarea>
      </div>
      <button type="submit" class="inline-flex min-h-11 items-center rounded-full bg-funeral-600 px-5 py-3 font-semibold text-white">Submit Feedback</button>
    </form>
  <?php else: ?>
    <p class="f-card p-5 text-stone-700"><?= $experience['feedback_id'] ? 'Feedback already submitted for this experience.' : ($experience['status'] === 'cancelled' ? 'This inquiry is cancelled and is not eligible for feedback.' : 'Awaiting director confirmation. Feedback will become available after the director confirms your inquiry.') ?></p>
  <?php endif; ?>
</div>
<script>
document.querySelectorAll('.rating-option input').forEach(input => {
  input.addEventListener('change', () => {
    input.closest('[role="radiogroup"]').querySelectorAll('.rating-option').forEach(option => {
      const active = option.querySelector('input').checked;
      option.classList.toggle('border-funeral-500', active);
      option.classList.toggle('bg-funeral-50', active);
      option.classList.toggle('border-stone-200', !active);
      option.classList.toggle('bg-white', !active);
    });
  });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
