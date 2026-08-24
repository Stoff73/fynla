<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Agents\CoordinatingAgent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The one place a daily Fyn insight is composed. W-0478, Rule 20.
 *
 * There were two, reading different data and saying different things about the same
 * household:
 *
 *   - `InsightsController::extractInsights()` — six modules, real figures, and the
 *     `unmodelled_relief_caveat` on the Inheritance Tax number. **Nothing called it.**
 *     No client anywhere: not `resources/mobile/`, not `ios-native/`.
 *   - `MobileDashboardAggregator::generateFynInsight()` — prose only, no figures. **This
 *     is the one users saw**, on `/m` and on native, via `fyn_insight` in the dashboard
 *     payload.
 *
 * So the caveat work done for W-0466 landed in the branch nobody read, and the surface
 * people actually look at said "your estate may have an inheritance tax liability"
 * where the other named £58,500 and qualified it. **CSJ's call: the richer mechanism
 * wins.** Its sentences and thresholds now live here, the prose-only one is deleted,
 * and both the endpoint and the dashboard payload read this.
 *
 * ## What it takes, and why not a user id
 *
 * `compose()` is a pure function of the module payloads its caller ALREADY has —
 * each module's own `analyze()` data, unwrapped. That matters for the dashboard: the
 * aggregator calls all six agents to build its module summaries, and asking this
 * service to fetch its own would double that work on the hot path. `daily()` exists
 * for the caller that has no analysis in hand.
 *
 * **One known asymmetry, stated rather than hidden:** the dashboard aggregator has no
 * tax agent, so it supplies no `tax` payload and the tax-strategy insight is
 * unreachable from that caller. Nothing here behaves differently — the reader simply
 * finds nothing. Adding `TaxOptimisationAgent` to the dashboard path would put a
 * strategy computation on every dashboard load.
 */
class DailyInsightService
{
    private const CACHE_TTL = 86400;

    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
    ) {}

    /**
     * The insight for a user, fetching and caching the analysis itself.
     *
     * @return array{insight: string, category: string, cached_at: string}
     */
    public function daily(int $userId): array
    {
        return Cache::remember("mobile_insight_daily_{$userId}", self::CACHE_TTL, function () use ($userId) {
            try {
                $analysis = $this->coordinatingAgent->analyze($userId);
            } catch (\Exception $e) {
                Log::warning('Failed to generate insight from analysis', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);

                return $this->fallbackInsight();
            }

            return $this->select($this->compose($this->modulePayloads($analysis)));
        });
    }

    /**
     * Pull each module's own payload out of a coordinated analysis.
     *
     * W-0473 — every reader used to look one level above this. `analyze()` returns
     * `module_analysis` (not `modules`), whose entries are the coordinator's flat map
     * with the agent's own payload nested under `full_analysis`. Unwrapped ONCE, here,
     * so a seventh module cannot get it wrong.
     *
     * @return array<string, array<string, mixed>>
     */
    public function modulePayloads(array $analysis): array
    {
        $modules = $analysis['module_analysis'] ?? [];
        $payloads = [];

        foreach ($modules as $key => $module) {
            if (! is_array($module)) {
                continue;
            }

            $payloads[$key] = array_merge($module['full_analysis'] ?? [], $module);
        }

        // The coordinator names the tax module `tax_optimisation`; this service reads
        // `tax`, so callers that have no tax agent can simply omit it.
        if (isset($payloads['tax_optimisation'])) {
            $payloads['tax'] = $payloads['tax_optimisation'];
        }

        return $payloads;
    }

    /**
     * Compose every insight this household qualifies for.
     *
     * @param  array<string, array<string, mixed>>  $modules  module name => the agent's own data
     * @return list<array{text: string, category: string}>
     */
    public function compose(array $modules): array
    {
        $insights = [];

        $savings = $modules['savings'] ?? [];
        $runway = $savings['emergency_fund']['runway_months'] ?? null;
        if ($runway !== null && $runway < 6) {
            $insights[] = [
                'text' => sprintf(
                    'Your emergency fund covers %.1f months of expenses. Building towards 6 months could provide greater financial security.',
                    $runway
                ),
                'category' => 'savings',
            ];
        }

        $isaRemaining = $savings['isa_allowance']['remaining'] ?? null;
        if ($isaRemaining !== null && $isaRemaining > 0) {
            $insights[] = [
                'text' => sprintf(
                    'You have %s remaining in your ISA allowance this tax year. Contributions before 5 April are tax-efficient.',
                    number_format($isaRemaining, 2, '.', ',')
                ),
                'category' => 'savings',
            ];
        }

        // The gaps structure is present for every analysed household, so its existence
        // says nothing. A household has a gap when a figure in it is above zero —
        // `total_gap` can be zero while a category (income protection, say) is not.
        $gaps = $modules['protection']['gaps'] ?? [];
        if (! empty($gaps)) {
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

        $allowanceRemaining = $modules['retirement']['annual_allowance']['remaining_allowance'] ?? null;
        if ($allowanceRemaining !== null && $allowanceRemaining > 0) {
            $insights[] = [
                'text' => sprintf(
                    'You have %s of pension Annual Allowance remaining. Additional contributions could reduce your tax bill.',
                    number_format($allowanceRemaining, 2, '.', ',')
                ),
                'category' => 'retirement',
            ];
        }

        $estate = $modules['estate'] ?? [];
        $ihtLiability = $estate['summary']['iht_liability'] ?? $estate['iht_liability'] ?? null;
        if ($ihtLiability !== null && $ihtLiability > 0) {
            // W-0466 — the caveat travels with the figure. It is appended rather than
            // sent as its own field because an insight is a single string by contract,
            // and the sentence itself comes from the engine (Rule 20): this never
            // composes its own qualification.
            //
            // "could help reduce this" was an unhedged efficacy claim
            // (`compliance-lead` finding E); rule 1 makes hedging mandatory.
            $caveat = $estate['summary']['unmodelled_relief_caveat'] ?? null;

            $insights[] = [
                'text' => sprintf(
                    'Your estimated Inheritance Tax liability is %s. Gifting and trusts are among the things you could explore.',
                    number_format((float) $ihtLiability, 2, '.', ',')
                ).($caveat !== null ? ' '.$caveat : ''),
                'category' => 'estate',
            ];
        }

        $goals = $modules['goals'] ?? [];
        if (($goals['has_goals'] ?? true) === false) {
            $insights[] = [
                'text' => 'Setting financial goals helps you stay focused. Consider adding your first goal to track progress towards what matters most.',
                'category' => 'goals',
            ];
        }

        $strategies = $modules['tax']['strategies'] ?? [];
        if (! empty($strategies)) {
            $insights[] = [
                'text' => sprintf(
                    'There are %d tax optimisation strategies available for your situation. Reviewing them could help reduce your tax burden.',
                    count($strategies)
                ),
                'category' => 'tax',
            ];
        }

        if ($insights !== []) {
            return $insights;
        }

        // Nothing specific to say. These three came from the dashboard's own composer
        // and are kept because they are better than a generic line: they distinguish a
        // household that has finished something, one that has recorded something, and
        // one that has not started.
        $completed = (int) ($goals['completed_count'] ?? 0);
        $total = (int) ($goals['goals_count'] ?? 0);
        if ($total > 0 && $completed > 0) {
            return [[
                'text' => "Well done! You have completed {$completed} of your {$total} financial goals. Keep the momentum going.",
                'category' => 'goals',
            ]];
        }

        if (($estate['summary']['net_estate'] ?? 0) > 0) {
            return [[
                'text' => 'Your financial plan is taking shape. Regular reviews help ensure you stay on track with your goals.',
                'category' => 'goals',
            ]];
        }

        return [[
            'text' => 'Welcome to Fynla. Start by setting up your financial profile to receive personalised insights and guidance.',
            'category' => 'goals',
        ]];
    }

    /**
     * Pick today's insight from the ones composed, rotating by day of year so a
     * household sees the same line all day and a different one tomorrow.
     *
     * @param  list<array{text: string, category: string}>  $insights
     * @return array{insight: string, category: string, cached_at: string}
     */
    public function select(array $insights): array
    {
        if ($insights === []) {
            return $this->fallbackInsight();
        }

        $selected = $insights[(int) now()->format('z') % count($insights)];

        return [
            'insight' => $selected['text'],
            'category' => $selected['category'],
            'cached_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Used only when the analysis itself could not be produced — a household with no
     * data reaches the "Welcome to Fynla" line in `compose()` instead.
     *
     * @return array{insight: string, category: string, cached_at: string}
     */
    public function fallbackInsight(): array
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

        $selected = $fallbacks[(int) now()->format('z') % count($fallbacks)];

        return [
            'insight' => $selected['text'],
            'category' => $selected['category'],
            'cached_at' => now()->toIso8601String(),
        ];
    }
}
