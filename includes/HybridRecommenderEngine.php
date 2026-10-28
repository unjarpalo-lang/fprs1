<?php
declare(strict_types=1);

require_once __DIR__ . '/RecommenderEngine.php';
require_once __DIR__ . '/CollaborativeEngine.php';

/**
 * Blends the content-based RecommenderEngine (budget/religion/distance fit
 * against a parlor's actual packages) with the CollaborativeEngine (crowd
 * satisfaction from past clients' surveys) into one match percentage.
 */
final class HybridRecommenderEngine
{
    private RecommenderEngine $contentEngine;
    private CollaborativeEngine $collaborativeEngine;
    private float $contentWeight;
    private float $collaborativeWeight;

    /**
     * @param array{budget?: float, religion?: float, distance?: float} $contentCriteriaWeights
     *   Forwarded to RecommenderEngine to weight budget/religion/distance against each other.
     */
    public function __construct(
        PDO $pdo,
        float $contentWeight = 0.5,
        float $collaborativeWeight = 0.5,
        array $contentCriteriaWeights = []
    ) {
        $this->contentEngine = new RecommenderEngine($contentCriteriaWeights);
        $this->collaborativeEngine = new CollaborativeEngine($pdo);
        $total = ($contentWeight + $collaborativeWeight) ?: 1.0;
        $this->contentWeight = $contentWeight / $total;
        $this->collaborativeWeight = $collaborativeWeight / $total;
    }

    /**
     * @param array{budget?: float|null, religion?: string|null, max_distance?: float|null} $preferences
     * @param array<string,int|float> $categoryImportance Keys from CollaborativeEngine::CATEGORIES, values 1-5.
     * @param array<int, array<string, mixed>> $candidates
     *   Each needs 'id' (for display) and 'parlor_id' (for the collaborative lookup),
     *   plus whatever RecommenderEngine expects ('price', optionally 'religion', 'distance').
     * @return array<int, array<string, mixed>> Sorted by match_percentage, descending.
     */
    public function recommend(array $preferences, array $categoryImportance, array $candidates): array
    {
        $contentResults = $this->contentEngine->recommend($preferences, $candidates);

        $hybrid = [];
        foreach ($contentResults as $candidate) {
            $contentScore = ((float)$candidate['match_percentage']) / 100;
            $parlorId = (int)($candidate['parlor_id'] ?? $candidate['id']);
            $collaborative = $this->collaborativeEngine->scoreParlor($parlorId, $categoryImportance);

            $blended = ($contentScore * $this->contentWeight) + ($collaborative['score'] * $this->collaborativeWeight);

            $hybrid[] = array_merge($candidate, [
                'match_percentage' => round($blended * 100, 1),
                'content_score' => round($contentScore * 100, 1),
                'collaborative_score' => round($collaborative['score'] * 100, 1),
                'respondent_count' => $collaborative['respondent_count'],
                'category_averages' => $collaborative['category_averages'],
            ]);
        }

        usort($hybrid, static fn(array $a, array $b) => $b['match_percentage'] <=> $a['match_percentage']);
        return $hybrid;
    }
}
