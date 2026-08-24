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

        // W-0473 — every reader below looked one level above the data, so no
        // branch ever ran. `CoordinatingAgent::analyze()` returns `module_analysis`
        // (not `modules`), whose entries are the coordinator's flat map with the
        // agent's own payload nested under `full_analysis`. Unwrap ONCE here, so a
        // seventh module cannot get it wrong.
        $modules = $analysis['module_analysis'] ?? [];
        $module = static fn (string $key): array => array_merge(
            $modules[$key]['full_analysis'] ?? [],
            $modules[$key] ?? [],
        );

        // Extract savings insights
        $savings = $module('savings');
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
        $protection = $module('protection');
        $gaps = $protection['gaps'] ?? [];
        if (! empty($gaps)) {
            // The gaps structure is present for every analysed household, so its
            // existence says nothing. A household has a gap when a figure in it is
            // above zero — `total_gap` can be zero while a category (income
            // protection, say) is not.
            $shortfalls = array_merge(
                [$gaps['total_gap'] ?? 0],
                array_values($gaps['gaps_by_category'] ?? []),
            );
            if (max($shortfalls) > 0) {
                $insights[] = [
                    'text' => 'There are gaps in your protection coverage. Reviewing your life and income protection could help safeguard your family.',
                    'category' => 'protection',
                ];
            }
        }

        // Extract retirement insights
        $retirement = $module('retirement');
        if (! empty($retirement)) {
            $allowance = $retirement['annual_allowance'] ?? [];
            $remaining = $allowance['remaining_allowance'] ?? null;
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
        $estate = $module('estate');
        if (! empty($estate)) {
            $ihtLiability = $estate['iht_liability'] ?? $estate['estimated_iht'] ?? null;
            if ($ihtLiability !== null && $ihtLiability > 0) {
                // W-0466 F3 — this is one of the surfaces the caveat did not reach.
                // The fix assumed the `/m` teaser was the only place `/m` prints an
                // Inheritance Tax figure; it is not. A business-owning household
                // read a full number here with nothing qualifying it.
                //
                // Appended rather than sent as its own field: an insight is a single
                // string by contract, and inventing a second key here would be a
                // change to the insights shape for one caller. The sentence itself
                // still comes from the engine (Rule 20) — this never composes its own.
                $caveat = $estate['summary']['unmodelled_relief_caveat'] ?? null;

                $insights[] = [
                    'text' => sprintf(
                        // `compliance-lead` finding E applied here too (flagged by
                        // quality-lead as "a second efficacy claim on an Inheritance
                        // Tax figure, from a parallel mechanism to the one finding E
                        // corrected"). "could help reduce this" asserts an outcome;
                        // rule 1 makes hedging mandatory. Same correction as the
                        // teaser headline, so the two surfaces say the same kind of
                        // thing about the same figure.
                        'Your estimated Inheritance Tax liability is %s. Gifting and trusts are among the things you could explore.',
                        number_format((float) $ihtLiability, 2, '.', ',')
                    ).($caveat !== null ? ' '.$caveat : ''),
                    'category' => 'estate',
                ];
            }
        }

        // Extract goals insights
        $goals = $module('goals');
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
        $tax = $module('tax_optimisation');
        if (! empty($tax)) {
            $strategies = $tax['strategies'] ?? [];
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
