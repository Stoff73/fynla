<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Agents\CoordinatingAgent;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InsightsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
    ) {}

    /**
     * Get daily Fyn insight for the user.
     *
     * GET /api/v1/mobile/insights/daily
     */
    public function daily(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $cacheKey = "mobile_insight_daily_{$userId}";

            $insight = Cache::remember($cacheKey, 86400, function () use ($userId) {
                return $this->generateDailyInsight($userId);
            });

            return response()->json([
                'success' => true,
                'data' => $insight,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching daily insight');
        }
    }

    /**
     * Generate a contextual daily insight based on the user's financial data.
     *
     * Uses the CoordinatingAgent to get a cross-module analysis, then
     * selects the most relevant insight to surface.
     */
    private function generateDailyInsight(int $userId): array
    {
        try {
            $analysis = $this->coordinatingAgent->analyze($userId);
        } catch (\Exception $e) {
            Log::warning('Failed to generate insight from analysis', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $this->getFallbackInsight();
        }

        $insights = $this->extractInsights($analysis);

        if (empty($insights)) {
            return $this->getFallbackInsight();
        }

        // Select an insight based on the day of year for consistent daily rotation
        $dayOfYear = (int) now()->format('z');
        $selectedIndex = $dayOfYear % count($insights);
        $selected = $insights[$selectedIndex];

        return [
            'insight' => $selected['text'],
            'category' => $selected['category'],
            'cached_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Extract actionable insights from the coordinated analysis.
     */
    private function extractInsights(array $analysis): array
    {
        $insights = [];

        // Extract savings insights
        $savings = $analysis['modules']['savings'] ?? $analysis['savings'] ?? [];
        if (! empty($savings)) {
            $emergencyFund = $savings['emergency_fund'] ?? [];
            $runway = $emergencyFund['runway_months'] ?? null;

            if ($runway !== null && $runway < 6) {
                $insights[] = [
                    'text' => sprintf(
                        'Your emergency fund covers %.1f months of expenses. Building towards 6 months could provide greater financial security.',
                        $runway
                    ),
                    'category' => 'savings',
                ];
            }

            $isaAllowance = $savings['isa_allowance'] ?? [];
            $remaining = $isaAllowance['remaining'] ?? null;
            if ($remaining !== null && $remaining > 0) {
                $insights[] = [
                    'text' => sprintf(
                        'You have %s remaining in your ISA allowance this tax year. Contributions before 5 April are tax-efficient.',
                        number_format($remaining, 2, '.', ',')
                    ),
                    'category' => 'savings',
                ];
            }
        }

        // Extract protection insights
        $protection = $analysis['modules']['protection'] ?? $analysis['protection'] ?? [];
        if (! empty($protection) && isset($protection['gaps'])) {
            $gaps = $protection['gaps'];
            if (! empty($gaps)) {
                $insights[] = [
                    'text' => 'There are gaps in your protection coverage. Reviewing your life and income protection could help safeguard your family.',
                    'category' => 'protection',
                ];
            }
        }

        // Extract retirement insights
        $retirement = $analysis['modules']['retirement'] ?? $analysis['retirement'] ?? [];
        if (! empty($retirement)) {
            $allowance = $retirement['annual_allowance'] ?? [];
            $remaining = $allowance['remaining'] ?? null;
            if ($remaining !== null && $remaining > 0) {
                $insights[] = [
                    'text' => sprintf(
                        'You have %s of pension Annual Allowance remaining. Additional contributions could reduce your tax bill.',
                        number_format($remaining, 2, '.', ',')
                    ),
                    'category' => 'retirement',
                ];
            }
        }

        // Extract estate insights
        $estate = $analysis['modules']['estate'] ?? $analysis['estate'] ?? [];
        if (! empty($estate)) {
            $ihtLiability = $estate['iht_liability'] ?? $estate['estimated_iht'] ?? null;
            if ($ihtLiability !== null && $ihtLiability > 0) {
                $insights[] = [
                    'text' => sprintf(
                        'Your estimated Inheritance Tax liability is %s. Gifting strategies and trust planning could help reduce this.',
                        number_format((float) $ihtLiability, 2, '.', ',')
                    ),
                    'category' => 'estate',
                ];
            }
        }

        // Extract goals insights
        $goals = $analysis['modules']['goals'] ?? $analysis['goals'] ?? [];
        if (! empty($goals)) {
            $hasGoals = $goals['has_goals'] ?? true;
            if (! $hasGoals) {
                $insights[] = [
                    'text' => 'Setting financial goals helps you stay focused. Consider adding your first goal to track progress towards what matters most.',
                    'category' => 'goals',
                ];
            }
        }

        // Extract tax insights
        $tax = $analysis['modules']['tax'] ?? $analysis['tax'] ?? [];
        if (! empty($tax)) {
            $strategies = $tax['data']['strategies'] ?? $tax['strategies'] ?? [];
            if (! empty($strategies)) {
                $insights[] = [
                    'text' => sprintf(
                        'There are %d tax optimisation strategies available for your situation. Reviewing them could help reduce your tax burden.',
                        count($strategies)
                    ),
                    'category' => 'tax',
                ];
            }
        }

        // General insight if nothing specific was extracted
        if (empty($insights)) {
            $insights[] = [
                'text' => 'Keeping your financial data up to date helps ensure your plan remains relevant. Consider reviewing your accounts and goals regularly.',
                'category' => 'savings',
            ];
        }

        return $insights;
    }

    /**
     * Return a safe fallback insight when analysis is unavailable.
     */
    private function getFallbackInsight(): array
    {
        $fallbacks = [
            [
                'text' => 'Regularly reviewing your financial plan helps you stay on track towards your goals. Take a moment to check your progress today.',
                'category' => 'goals',
            ],
            [
                'text' => 'Tax-efficient saving through ISAs and pensions can make a significant difference over time. Make sure you are using your allowances.',
                'category' => 'tax',
            ],
            [
                'text' => 'An emergency fund covering 3 to 6 months of essential expenses provides a strong financial safety net.',
                'category' => 'savings',
            ],
            [
                'text' => 'Reviewing your protection cover regularly ensures it keeps pace with your changing circumstances.',
                'category' => 'protection',
            ],
        ];

        $dayOfYear = (int) now()->format('z');
        $selected = $fallbacks[$dayOfYear % count($fallbacks)];

        return [
            'insight' => $selected['text'],
            'category' => $selected['category'],
            'cached_at' => now()->toIso8601String(),
        ];
    }
}
