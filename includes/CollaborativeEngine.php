<?php
declare(strict_types=1);

/**
 * Collaborative-filtering half of the hybrid recommender.
 *
 * The requesting client has no ratings history of their own (cold start),
 * so this scores a parlor using OTHER past clients' satisfaction surveys
 * instead — "people who used this parlor before rated it this way on the
 * things you say matter to you." That's what makes it collaborative rather
 * than content-based: the signal comes from the community's ratings, not
 * from the parlor's own advertised attributes.
 *
 * Refined with k-nearest-neighbor weighting: respondents whose emphasis
 * pattern across the 8 categories most resembles the client's stated
 * priorities count more than a flat crowd average.
 *
 * Reads from parlor_survey_responses (see includes/functions.php ->
 * ensure_recommender_tables). Each respondent contributes 22 raw 1-5
 * ratings, grouped here into 8 categories matching the source survey:
 * location, budget, religion, casket, service_delivery, documentation,
 * arrangement, flower.
 */
final class CollaborativeEngine
{
    public const CATEGORIES = [
        'location' => 3,
        'budget' => 5,
        'religion' => 2,
        'casket' => 2,
        'service_delivery' => 4,
        'documentation' => 3,
        'arrangement' => 2,
        'flower' => 1,
    ];

    private PDO $pdo;
    private int $neighbors;

    public function __construct(PDO $pdo, int $neighbors = 3)
    {
        $this->pdo = $pdo;
        $this->neighbors = max(1, $neighbors);
    }

    /**
     * @param array<string,int|float> $clientImportance Keys from self::CATEGORIES, values 1-5.
     * @return array{score: float, respondent_count: int, category_averages: array<string,float>}
     *   score is 0-1. category_averages are the raw 1-5 crowd averages per category.
     */
    public function scoreParlor(int $parlorId, array $clientImportance): array
    {
        $respondents = $this->fetchRespondentVectors($parlorId);
        $categoryAverages = $this->averageByCategory($respondents);

        if (!$respondents) {
            // No survey data yet: neutral score, not a penalty.
            return ['score' => 0.5, 'respondent_count' => 0, 'category_averages' => $categoryAverages];
        }

        $clientVector = $this->normalizeVector($clientImportance);

        $similarities = [];
        foreach ($respondents as $respondentVector) {
            $similarity = $this->cosineSimilarity($clientVector, $respondentVector);
            $overall = array_sum($respondentVector) / count($respondentVector); // 1-5 scale
            $similarities[] = ['similarity' => $similarity, 'overall' => $overall];
        }

        usort($similarities, static fn(array $a, array $b) => $b['similarity'] <=> $a['similarity']);
        $topK = array_slice($similarities, 0, $this->neighbors);

        $weightSum = array_sum(array_column($topK, 'similarity'));
        if ($weightSum <= 0) {
            $predicted = array_sum(array_column($topK, 'overall')) / count($topK);
        } else {
            $predicted = 0.0;
            foreach ($topK as $neighbor) {
                $predicted += $neighbor['overall'] * $neighbor['similarity'];
            }
            $predicted /= $weightSum;
        }

        return [
            'score' => max(0.0, min(1.0, $predicted / 5)),
            'respondent_count' => count($respondents),
            'category_averages' => $categoryAverages,
        ];
    }

    /**
     * One 8-dim vector per respondent, combining the original client surveys
     * (parlor_survey_responses, 22 raw criteria averaged into 8 categories)
     * with any live client feedback (parlor_feedback, already 8 categories) —
     * so submitted feedback genuinely moves a parlor's collaborative score
     * and category averages, not just a separate display.
     *
     * @return array<string, array<string,float>>
     */
    private function fetchRespondentVectors(int $parlorId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT respondent_no, criterion_key, score FROM parlor_survey_responses WHERE parlor_id = ?'
        );
        $stmt->execute([$parlorId]);

        $raw = [];
        foreach ($stmt->fetchAll() as $row) {
            $category = preg_replace('/_\d+$/', '', (string)$row['criterion_key']);
            $raw[(int)$row['respondent_no']][$category][] = (float)$row['score'];
        }

        $vectors = [];
        foreach ($raw as $respondentNo => $categories) {
            $vector = [];
            foreach (array_keys(self::CATEGORIES) as $category) {
                $values = $categories[$category] ?? [];
                $vector[$category] = $values ? array_sum($values) / count($values) : 0.0;
            }
            $vectors["survey_{$respondentNo}"] = $vector;
        }

        $feedbackTableExists = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parlor_feedback'"
        )->fetchColumn();
        if ($feedbackTableExists) {
            $feedbackStmt = $this->pdo->prepare('SELECT * FROM parlor_feedback WHERE parlor_id = ?');
            $feedbackStmt->execute([$parlorId]);
            foreach ($feedbackStmt->fetchAll() as $feedback) {
                $vector = [];
                foreach (array_keys(self::CATEGORIES) as $category) {
                    $vector[$category] = (float)$feedback[$category];
                }
                $vectors["feedback_{$feedback['id']}"] = $vector;
            }
        }

        return $vectors;
    }

    /** @param array<int, array<string,float>> $respondents */
    private function averageByCategory(array $respondents): array
    {
        $averages = array_fill_keys(array_keys(self::CATEGORIES), 0.0);
        if (!$respondents) return $averages;
        foreach (array_keys(self::CATEGORIES) as $category) {
            $values = array_column($respondents, $category);
            $averages[$category] = $values ? round(array_sum($values) / count($values), 2) : 0.0;
        }
        return $averages;
    }

    /** @param array<string,int|float> $input */
    private function normalizeVector(array $input): array
    {
        $vector = [];
        foreach (array_keys(self::CATEGORIES) as $category) {
            $vector[$category] = (float)($input[$category] ?? 3.0);
        }
        return $vector;
    }

    /** @param array<string,float> $a @param array<string,float> $b */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        foreach ($a as $key => $valueA) {
            $valueB = $b[$key] ?? 0.0;
            $dot += $valueA * $valueB;
            $normA += $valueA ** 2;
            $normB += $valueB ** 2;
        }
        if ($normA <= 0 || $normB <= 0) return 0.0;
        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
