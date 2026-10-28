<?php
declare(strict_types=1);

/**
 * Standalone weighted match-scoring engine for FPRS.
 *
 * Does not touch the database or routing layer. You query your own data
 * (from funeral_parlors/packages, or funeral_homes/criteria_ratings —
 * whichever schema you land on) and pass the results in as a plain array
 * shaped like:
 *
 *   [
 *     'id'       => 1,
 *     'name'     => 'Sample Memorial Home',
 *     'price'    => 25000.00,
 *     'religion' => 'Catholic',   // optional, free text
 *     'distance' => 4.2,          // optional, km from the client
 *     'location' => 'Quezon City',// optional, passthrough for display only
 *   ]
 *
 * Usage:
 *   require_once __DIR__ . '/RecommenderEngine.php';
 *   $engine = new RecommenderEngine();
 *   $results = $engine->recommend(
 *       ['budget' => 30000, 'religion' => 'Catholic', 'max_distance' => 10],
 *       $candidateRows
 *   );
 *   echo $engine->recommendAsJson($preferences, $candidateRows);
 */
final class RecommenderEngine
{
    /** @var array{budget: float, religion: float, distance: float} */
    private array $weights;

    /**
     * @param array{budget?: float, religion?: float, distance?: float} $weights
     *   Override the default criteria weights. They do not need to sum to 1 —
     *   the engine normalizes them internally.
     */
    public function __construct(array $weights = [])
    {
        $defaults = ['budget' => 0.40, 'religion' => 0.35, 'distance' => 0.25];
        $merged = array_merge($defaults, $weights);
        $total = array_sum($merged) ?: 1.0;
        $this->weights = [
            'budget' => $merged['budget'] / $total,
            'religion' => $merged['religion'] / $total,
            'distance' => $merged['distance'] / $total,
        ];
    }

    /**
     * @param array{budget?: float|null, religion?: string|null, max_distance?: float|null} $preferences
     * @param array<int, array<string, mixed>> $candidates
     * @return array<int, array<string, mixed>> Candidates sorted by match_percentage, descending.
     */
    public function recommend(array $preferences, array $candidates): array
    {
        $budget = isset($preferences['budget']) && $preferences['budget'] !== ''
            ? (float)$preferences['budget'] : null;
        $religion = trim((string)($preferences['religion'] ?? ''));
        $maxDistance = isset($preferences['max_distance']) && $preferences['max_distance'] !== ''
            ? (float)$preferences['max_distance'] : null;

        $scored = [];
        foreach ($candidates as $candidate) {
            $distance = isset($candidate['distance']) ? (float)$candidate['distance'] : null;

            // Max distance is a hard constraint: outside range, don't recommend it at all.
            if ($maxDistance !== null && $distance !== null && $distance > $maxDistance) {
                continue;
            }

            $budgetScore = $this->scoreBudget((float)($candidate['price'] ?? 0), $budget);
            $religionScore = $this->scoreReligion((string)($candidate['religion'] ?? ''), $religion);
            $distanceScore = $this->scoreDistance($distance, $maxDistance);

            $weighted = ($budgetScore * $this->weights['budget'])
                + ($religionScore * $this->weights['religion'])
                + ($distanceScore * $this->weights['distance']);

            $scored[] = $candidate + [
                'match_percentage' => round($weighted * 100, 1),
                'score_breakdown' => [
                    'budget' => round($budgetScore * 100, 1),
                    'religion' => round($religionScore * 100, 1),
                    'distance' => round($distanceScore * 100, 1),
                ],
            ];
        }

        usort($scored, static fn(array $a, array $b) => $b['match_percentage'] <=> $a['match_percentage']);

        return $scored;
    }

    /**
     * Convenience wrapper that returns the recommendation list as a JSON string.
     *
     * @param array{budget?: float|null, religion?: string|null, max_distance?: float|null} $preferences
     * @param array<int, array<string, mixed>> $candidates
     */
    public function recommendAsJson(array $preferences, array $candidates): string
    {
        return (string) json_encode(
            $this->recommend($preferences, $candidates),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }

    private function scoreBudget(float $price, ?float $budget): float
    {
        if ($budget === null || $budget <= 0) return 1.0;
        if ($price <= 0) return 1.0;

        if ($price <= $budget) {
            // Fits the budget. Slight reward for using more of the available budget
            // (assumes a costlier package within budget reflects more inclusions),
            // while keeping cheaper options solidly competitive.
            return 0.85 + (0.15 * ($price / $budget));
        }

        // Over budget: score decays linearly to 0 at 50% over budget.
        $overBy = ($price - $budget) / $budget;
        return max(0.0, 1 - ($overBy / 0.5));
    }

    private function scoreReligion(string $candidateReligion, string $preferred): float
    {
        if ($preferred === '') return 1.0; // client stated no preference
        if ($candidateReligion === '') return 0.4; // unspecified isn't a hard fail, just uncertain
        return stripos($candidateReligion, $preferred) !== false ? 1.0 : 0.0;
    }

    private function scoreDistance(?float $distance, ?float $maxDistance): float
    {
        if ($distance === null) return 0.5; // unknown distance: neutral, don't punish or reward
        if ($maxDistance === null || $maxDistance <= 0) return 1.0;
        if ($distance <= 0) return 1.0;
        return max(0.0, 1 - ($distance / $maxDistance));
    }
}
