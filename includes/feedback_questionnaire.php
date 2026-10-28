<?php
declare(strict_types=1);

/** Part II service statements, transcribed from Questionnaire4RespondentsNotFinished (1).docx. */
function feedback_questionnaire(): array {
    return [
        'location' => ['label'=>'Location','statements'=>[
            'Location of the Funeral Homes is easy to locate',
            'Location of the funeral homes is good and accessible',
            'Location is available on google map and waze',
        ]],
        'budget' => ['label'=>'Budget','statements'=>[
            'Funeral service cost are reasonable',
            'The customer is allowed to request a discount from the funeral home',
            'Payment options are available',
            'Payment arrangement might be made after the funeral',
            'Discounts will be given based on cutstomer’s status',
        ]],
        'religion' => ['label'=>'Religion','statements'=>[
            'Has the specific packages ready and tailored to my religious affiliations',
            'The facility can fully accomodate specific religions and traditions',
        ]],
        'casket' => ['label'=>'A. Casket','statements'=>[
            'Receiving customers needed customization',
            'Accepting customers casket preffered design .',
        ]],
        'service_delivery' => ['label'=>'B. Service Delivery','statements'=>[
            'Vehicle used in delivery is always on time',
            'Corpse of the bereaved family is secured',
            'The funeral homes provide quality of service',
            'Complaints are well taken by the staffs of the funeral home',
        ]],
        'documentation' => ['label'=>'C. Documentation','statements'=>[
            'Good quality of output documentation',
            'Offering media related activities in funeral service',
            'Always available type service',
        ]],
        'arrangement' => ['label'=>'D. Casket (Wake arrangement)','statements'=>[
            'Good arrangement of landscape',
            'Better atmosphere and quality of the arrangement for the wake.',
        ]],
        'flower' => ['label'=>'E. Flowers','statements'=>[
            'Flowers are in quality and delivered timely',
        ]],
    ];
}

function feedback_question_keys(): array {
    $keys=[];
    foreach (feedback_questionnaire() as $category=>$group) {
        foreach ($group['statements'] as $index=>$statement) $keys[]=$category . '_' . ($index+1);
    }
    return $keys;
}

/** Reject missing/malformed ratings rather than silently supplying neutral answers. */
function validated_questionnaire_answers(array $input): array {
    $answers=[];
    foreach (feedback_question_keys() as $key) {
        $raw=$input[$key] ?? null;
        if ((!is_int($raw) && !is_string($raw)) || !preg_match('/^[1-5]$/D', (string)$raw)) {
            throw new InvalidArgumentException('Please rate every statement from 1 to 5 before submitting.');
        }
        $answers[$key]=(int)$raw;
    }
    if (count($input) !== count($answers)) throw new InvalidArgumentException('The questionnaire contains unexpected answers. Please reload and try again.');
    return $answers;
}

function service_feedback_eligible(array $experience): bool {
    return in_array($experience['status'] ?? '', ['confirmed','availed'], true)
        && !empty($experience['confirmed_at']) && !empty($experience['confirmed_by']);
}

function service_experience_status(array $experience): string {
    if (($experience['status'] ?? '') === 'cancelled') return 'Cancelled';
    if (!service_feedback_eligible($experience)) return 'Awaiting director confirmation';
    return $experience['status'] === 'availed' ? 'Availed' : 'Confirmed';
}
