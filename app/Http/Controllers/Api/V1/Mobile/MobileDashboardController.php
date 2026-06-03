<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Coordination\RecommendationsAggregatorService;
use App\Services\Mobile\MobileDashboardAggregator;
use App\Services\Mobile\MobileLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDashboardController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly MobileDashboardAggregator $aggregator,
        private readonly MobileLevelService $levelService,
        private readonly RecommendationsAggregatorService $recommendations,
    ) {}

    /**
     * Get aggregated mobile dashboard data.
     *
     * Returns module summaries, net worth, Fyn insight (cached 24h), plus the
     * user's gamification level, dynamic percentile, and recommendation list
     * (their own shorter TTLs) for the redesigned dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $data = $this->aggregator->getAggregatedDashboard($userId);

            // Level + dynamic percentile (own caches inside the service).
            $data['level'] = $this->levelService->levelFor($userId);
            $data['percentile'] = $this->levelService->percentile($userId);

            // Recommendations for the accordion + Fyn suggestions.
            $data['recommendations'] = $this->mobileRecommendations($userId);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching mobile dashboard data');
        }
    }

    /**
     * Shape the aggregated recommendations into the per-category buckets the
     * mobile accordion expects (save-tax / retirement / savings), each item
     * carrying a title, a "You can save/earn: £X" meta, and a value for sort.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function mobileRecommendations(int $userId): array
    {
        $all = $this->recommendations->aggregateRecommendations($userId);

        // Module → accordion category mapping.
        $categoryFor = static function (array $rec): string {
            return match ($rec['module'] ?? '') {
                'retirement' => 'retirement',
                'savings' => 'savings',
                default => 'save_tax',   // protection/estate/investment/general
            };
        };

        $buckets = ['save_tax' => [], 'retirement' => [], 'savings' => []];

        foreach ($all as $rec) {
            $cat = $categoryFor($rec);
            $benefit = is_numeric($rec['potential_benefit'] ?? null) ? (float) $rec['potential_benefit'] : null;
            $verb = $cat === 'savings' ? 'earn' : 'save';
            $meta = $benefit !== null
                ? 'You can '.$verb.': £'.number_format($benefit)
                : ucfirst(str_replace('_', ' ', (string) ($rec['category'] ?? 'Recommended')));

            $buckets[$cat][] = [
                'id' => $rec['recommendation_id'] ?? uniqid('rec_'),
                'title' => $rec['recommendation_text'] ?? '',
                'meta' => $meta,
                'value' => $benefit ?? (float) ($rec['priority_score'] ?? 50),
                'module' => $rec['module'] ?? 'general',
                'done' => ($rec['status'] ?? 'pending') === 'completed',
            ];
        }

        // Sort each bucket by value desc (matches the mockup ordering).
        foreach ($buckets as &$list) {
            usort($list, static fn ($a, $b) => $b['value'] <=> $a['value']);
        }

        return $buckets;
    }
}
