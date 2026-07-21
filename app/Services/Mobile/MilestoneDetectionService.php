<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Will;
use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserMilestone;
use App\Services\Gamification\MilestoneCollector;
use App\Services\Gamification\PointsService;
use App\Services\Stores\SavingsStore;
use App\Services\TaxConfigService;
use Illuminate\Support\Carbon;

/**
 * Detects financial milestones a user has newly crossed and records them once.
 *
 * "Newly crossed" = a threshold at or below the current value that has not yet
 * been recorded for this user. Each milestone is persisted (unique per
 * user/type/reference/threshold) so the celebratory share prompt fires a single
 * time, even though detection runs on every dashboard/goals read.
 *
 * Share content itself comes from ShareContentGenerator (generic, no monetary
 * values — Rule #12); these methods only return what to celebrate + which share
 * type to use.
 */
class MilestoneDetectionService
{
    /** Net-worth thresholds in GBP. */
    private const NET_WORTH_THRESHOLDS = [
        10000, 25000, 50000, 100000, 250000, 500000, 750000, 1000000, 2000000, 5000000,
    ];

    /** Goal-progress thresholds in percent. */
    private const GOAL_THRESHOLDS = [25, 50, 75, 100];

    /** WP-5c — combined pension value thresholds in GBP. */
    private const PENSION_POT_THRESHOLDS = [10000, 25000, 50000, 100000, 250000, 500000, 1000000];

    /** WP-5c — emergency-fund runway thresholds in months of spending. */
    private const EMERGENCY_FUND_MONTHS = [1, 3, 6];

    /** WP-5c — mortgage paydown thresholds as % of the original loan. */
    private const MORTGAGE_PAID_PERCENTS = [25, 50, 75, 100];

    /** WP-5c — cumulative actioned annual tax savings thresholds in GBP. */
    private const TAX_ACTIONED_THRESHOLDS = [250, 500, 1000, 2500, 5000];

    /** WP-5c — allowance-usage thresholds in percent (per tax year). */
    private const ALLOWANCE_USED_PERCENTS = [50, 100];

    /**
     * WP-5c — strategy type → named-first family (spec §7.2: pension, ISA,
     * Gift Aid, salary sacrifice, Marriage Allowance).
     */
    private const STRATEGY_FAMILIES = [
        'pa_taper_rescue' => 'pension',
        'additional_rate_avoidance' => 'pension',
        'pension_aa_carry_forward' => 'pension',
        'tapered_annual_allowance' => 'pension',
        'non_earner_spouse_pension' => 'pension',
        'junior_pension' => 'pension',
        'isa_topup_vs_psa' => 'isa',
        'bed_and_isa' => 'isa',
        'isa_topup_spouse' => 'isa',
        'lifetime_isa' => 'isa',
        'junior_isa' => 'isa',
        'isa_coordination' => 'isa',
        'gift_aid_higher_rate_relief' => 'gift_aid',
        'salary_sacrifice_ni' => 'salary_sacrifice',
        'marriage_allowance_transfer' => 'marriage_allowance',
    ];

    public const STRATEGY_FAMILY_LABELS = [
        'pension' => "You've completed your first pension tax action.",
        'isa' => "You've completed your first ISA action.",
        'gift_aid' => "You've claimed your first Gift Aid relief.",
        'salary_sacrifice' => "You've set up your first salary sacrifice saving.",
        'marriage_allowance' => "You've used the Marriage Allowance transfer.",
    ];

    /**
     * WP-5c — module key → stable reference id for module_profile rows
     * (reference_id is an integer column; module keys are strings).
     */
    public const MODULE_IDS = [
        'retirement' => 1,
        'protection' => 2,
        'savings' => 3,
        'investment' => 4,
        'estate' => 5,
        'goals' => 6,
    ];

    public function __construct(
        private readonly PointsService $points,
        private readonly SavingsStore $savingsStore,
        private readonly TaxConfigService $taxConfig,
        private readonly MilestoneCollector $collector,
    ) {}

    /**
     * Detect net-worth milestones newly crossed at the given total.
     *
     * @return array<int,array<string,mixed>> Newly-crossed milestones (may be empty)
     */
    public function detectNetWorth(User $user, float $netWorth): array
    {
        $new = [];

        foreach (self::NET_WORTH_THRESHOLDS as $threshold) {
            if ($netWorth < $threshold) {
                break; // ascending — nothing higher can be crossed
            }

            $record = UserMilestone::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'milestone_type' => 'net_worth',
                    'reference_id' => null,
                    'threshold' => $threshold,
                ],
                ['achieved_at' => Carbon::now()],
            );

            if ($record->wasRecentlyCreated) {
                $this->points->award(
                    $user,
                    'milestone',
                    "milestone:net_worth:0:{$threshold}",
                    (int) config('gamification.points.milestone'),
                    ['threshold' => $threshold],
                );

                $label = 'Your net worth has passed £'.number_format($threshold).'.';
                $this->minted($user, 'net_worth', $label);

                $new[] = [
                    'type' => 'net_worth',
                    'threshold' => $threshold,
                    'label' => $label,
                    'share_type' => 'net_worth_milestone',
                ];
            }
        }

        return $new;
    }

    /**
     * Detect goal-progress milestones newly crossed for one goal.
     *
     * @return array<int,array<string,mixed>> Newly-crossed milestones (may be empty)
     */
    public function detectGoal(User $user, int $goalId, float $progressPercent, string $goalName): array
    {
        $new = [];

        foreach (self::GOAL_THRESHOLDS as $threshold) {
            if ($progressPercent < $threshold) {
                break;
            }

            $record = UserMilestone::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'milestone_type' => 'goal',
                    'reference_id' => $goalId,
                    'threshold' => $threshold,
                ],
                ['achieved_at' => Carbon::now()],
            );

            if ($record->wasRecentlyCreated) {
                $this->points->award(
                    $user,
                    'milestone',
                    "milestone:goal:{$goalId}:{$threshold}",
                    (int) config('gamification.points.milestone'),
                    ['goal_id' => $goalId, 'threshold' => $threshold],
                );

                $label = $threshold >= 100
                    ? sprintf('You\'ve reached your goal: %s.', $goalName)
                    : sprintf('You\'re %d%% of the way to %s.', $threshold, $goalName);
                $this->minted($user, 'goal', $label);

                $new[] = [
                    'type' => 'goal',
                    'reference_id' => $goalId,
                    'threshold' => $threshold,
                    'label' => $label,
                    'share_type' => 'goal_milestone',
                ];
            }
        }

        return $new;
    }

    /**
     * Detect milestones across a collection of goals.
     *
     * @param  iterable<int,array<string,mixed>>  $goals  Each with id, name|goal_name, progress_percentage
     * @return array<int,array<string,mixed>>
     */
    public function detectGoals(User $user, iterable $goals): array
    {
        $new = [];

        foreach ($goals as $goal) {
            $id = (int) ($goal['id'] ?? 0);
            if ($id === 0) {
                continue;
            }
            $progress = (float) ($goal['progress_percentage'] ?? 0);
            $name = (string) ($goal['name'] ?? $goal['goal_name'] ?? 'your goal');
            $new = array_merge($new, $this->detectGoal($user, $id, $progress, $name));
        }

        return $new;
    }

    /**
     * WP-5b/WP-5c-ii — the milestones the user can achieve NEXT: the next
     * unearned milestone from every family that applies, grouped for the
     * milestones page, each with the concrete step (and the distance where
     * the figure is in hand). Event-celebration families (strategy firsts,
     * estate start, anniversaries) are deliberately absent — they aren't
     * targets a user picks.
     *
     * @return array<int,array{group: string, title: string, steps: string}>
     */
    public function upcoming(User $user, ?float $netWorth = null, ?float $pensionPot = null): array
    {
        $earned = UserMilestone::where('user_id', $user->id)
            ->get(['milestone_type', 'reference_id', 'threshold']);

        $has = static fn (string $type, ?int $ref = null, ?float $threshold = null): bool => $earned->contains(
            static fn (UserMilestone $m): bool => $m->milestone_type === $type
                && ($ref === null || (int) $m->reference_id === $ref)
                && ($threshold === null || abs((float) $m->threshold - $threshold) < 0.01)
        );

        $upcoming = [];

        // ── Wealth: next net-worth threshold (skip anything the live figure
        // has already passed — those mint on the next dashboard read).
        foreach (self::NET_WORTH_THRESHOLDS as $threshold) {
            if ($has('net_worth', null, (float) $threshold) || ($netWorth !== null && $netWorth >= $threshold)) {
                continue;
            }
            $upcoming[] = [
                'group' => 'Wealth',
                'title' => 'Net worth £'.number_format($threshold),
                'steps' => $netWorth !== null
                    ? 'Add to your savings, investments or pension — you\'re £'.number_format((int) round($threshold - $netWorth)).' away.'
                    : 'Add to your savings, investments or pension.',
            ];
            break;
        }

        // ── Goals: next progress step per active goal (top 3 by progress).
        $goals = Goal::forUserOrJoint($user->id)
            ->get()
            ->filter(fn (Goal $g): bool => (float) $g->progress_percentage < 100)
            ->sortByDesc(fn (Goal $g): float => (float) $g->progress_percentage)
            ->take(3);
        foreach ($goals as $goal) {
            $progress = (float) $goal->progress_percentage;
            foreach (self::GOAL_THRESHOLDS as $threshold) {
                if ($progress < $threshold) {
                    $name = (string) ($goal->goal_name ?? $goal->name ?? 'your goal');
                    $target = (float) $goal->target_amount;
                    $current = (float) $goal->current_amount;
                    $needed = max(0.0, $target * ($threshold / 100) - $current);
                    $upcoming[] = [
                        'group' => 'Goals',
                        'title' => ($threshold >= 100 ? 'Reach ' : $threshold.'% of ').$name,
                        'steps' => $needed > 0 && $target > 0
                            ? 'Put £'.number_format((int) round($needed)).' more towards it.'
                            : 'Keep contributing towards this goal.',
                    ];
                    break;
                }
            }
        }

        // ── Tax year: allowance usage repeats per tax year; actioned savings
        // climb a cumulative ladder.
        $taxYear = $this->taxConfig->getTaxYear();
        $year = (int) substr($taxYear, 0, 4);
        if ($year >= 2000) {
            if (! $has('isa_used', $year, 50.0)) {
                $upcoming[] = [
                    'group' => 'Tax year',
                    'title' => "Use half your ISA allowance ({$taxYear})",
                    'steps' => 'Pay into an ISA — contributions this tax year count towards it.',
                ];
            } elseif (! $has('isa_used', $year, 100.0)) {
                $upcoming[] = [
                    'group' => 'Tax year',
                    'title' => "Use your full ISA allowance ({$taxYear})",
                    'steps' => 'Top up your ISA before 5 April.',
                ];
            }

            if (! $has('pension_aa_used', $year, 50.0)) {
                $upcoming[] = [
                    'group' => 'Tax year',
                    'title' => "Use half your pension Annual Allowance ({$taxYear})",
                    'steps' => 'Add to your pension contributions this tax year.',
                ];
            } elseif (! $has('pension_aa_used', $year, 100.0)) {
                $upcoming[] = [
                    'group' => 'Tax year',
                    'title' => "Use your full pension Annual Allowance ({$taxYear})",
                    'steps' => 'Add to your pension contributions this tax year.',
                ];
            }
        }

        if (! $has('tax_actioned', null, 1.0)) {
            $upcoming[] = [
                'group' => 'Tax year',
                'title' => 'Action your first tax saving',
                'steps' => 'Mark a tax-plan action as done once you\'ve completed it.',
            ];
        } else {
            foreach (self::TAX_ACTIONED_THRESHOLDS as $threshold) {
                if (! $has('tax_actioned', null, (float) $threshold)) {
                    $upcoming[] = [
                        'group' => 'Tax year',
                        'title' => '£'.number_format($threshold).' a year of tax savings actioned',
                        'steps' => 'Complete more of the actions in your tax plan.',
                    ];
                    break;
                }
            }
        }

        // ── Savings.
        foreach (self::EMERGENCY_FUND_MONTHS as $months) {
            if (! $has('emergency_fund', null, (float) $months)) {
                $upcoming[] = [
                    'group' => 'Savings',
                    'title' => $months === 1
                        ? 'Emergency fund covering a month of spending'
                        : "Emergency fund covering {$months} months of spending",
                    'steps' => 'Top up your easy-access savings.',
                ];
                break;
            }
        }
        if (! $has('isa_first')) {
            $upcoming[] = [
                'group' => 'Savings',
                'title' => 'Open your first ISA',
                'steps' => 'Interest and gains inside an ISA stay tax-free.',
            ];
        }

        // ── Retirement.
        foreach (self::PENSION_POT_THRESHOLDS as $threshold) {
            if ($has('pension_pot', null, (float) $threshold) || ($pensionPot !== null && $pensionPot >= $threshold)) {
                continue;
            }
            $upcoming[] = [
                'group' => 'Retirement',
                'title' => 'Pension savings £'.number_format($threshold),
                'steps' => $pensionPot !== null && $pensionPot > 0
                    ? 'Add to your pension — you\'re £'.number_format((int) round($threshold - $pensionPot)).' away.'
                    : 'Add to your pension, or record one you already have.',
            ];
            break;
        }
        if (! $has('retirement_on_track')) {
            $upcoming[] = [
                'group' => 'Retirement',
                'title' => 'On track for retirement',
                'steps' => 'Add your pensions and set your retirement target so we can check.',
            ];
        }

        // ── Protection & estate.
        if (! $has('protection_adequate')) {
            $upcoming[] = [
                'group' => 'Protection & estate',
                'title' => 'Cover your protection needs',
                'steps' => 'Close the gaps shown in your protection section.',
            ];
        }
        if (! $has('will_in_place')) {
            $upcoming[] = [
                'group' => 'Protection & estate',
                'title' => 'Put your will in place',
                'steps' => 'Record your will in the estate section — or make one if you haven\'t.',
            ];
        }
        if (! $has('lpa_in_place')) {
            $upcoming[] = [
                'group' => 'Protection & estate',
                'title' => 'Put a Lasting Power of Attorney in place',
                'steps' => 'Register a Lasting Power of Attorney and record it in the estate section.',
            ];
        }

        // ── Property: next paydown step per mortgage, with the £ to get there.
        $mortgages = Mortgage::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->get(['id', 'original_loan_amount', 'outstanding_balance']);
        foreach ($mortgages as $mortgage) {
            $original = (float) $mortgage->original_loan_amount;
            if ($original <= 0) {
                continue;
            }
            $balance = (float) $mortgage->outstanding_balance;
            foreach (self::MORTGAGE_PAID_PERCENTS as $pct) {
                if ($has('mortgage_paid', (int) $mortgage->id, (float) $pct)) {
                    continue;
                }
                $toGo = max(0.0, $balance - $original * (1 - $pct / 100));
                $upcoming[] = [
                    'group' => 'Property',
                    'title' => $pct >= 100 ? 'Pay off your mortgage' : "Pay off {$pct}% of your mortgage",
                    'steps' => $toGo > 0
                        ? 'Pay down £'.number_format((int) round($toGo)).' more.'
                        : 'Keep up your repayments.',
                ];
                break;
            }
        }

        // ── Journey: the original WP-5b items, plus the remaining module
        // profiles (one combined line, not six) and the household link.
        if (! empty($user->funnel_answers)) {
            if (! (bool) $user->onboarding_completed && ! $has('campaign')) {
                $upcoming[] = [
                    'group' => 'Journey',
                    'title' => 'Complete your tax profile',
                    'steps' => 'Finish your chat with Fyn — a few questions to go.',
                ];
            }
            if (! $has('tax_savings')) {
                $upcoming[] = [
                    'group' => 'Journey',
                    'title' => 'Find your first tax saving',
                    'steps' => 'Add your accounts and pension details so your tax plan can quantify a saving.',
                ];
            }
        }

        if (! $has('action')) {
            $upcoming[] = [
                'group' => 'Journey',
                'title' => 'Complete your first action',
                'steps' => 'Mark any recommended action on your dashboard as done.',
            ];
        }

        $remainingModules = array_keys(array_filter(
            self::MODULE_IDS,
            static fn (int $id): bool => ! $has('module_profile', $id),
        ));
        if ($remainingModules !== []) {
            $upcoming[] = [
                'group' => 'Journey',
                'title' => 'Complete your remaining profiles',
                'steps' => 'Still to complete: '.implode(', ', $remainingModules).'.',
            ];
        }

        if ($user->spouse_id && ! $has('household')) {
            $upcoming[] = [
                'group' => 'Journey',
                'title' => 'Link your household',
                'steps' => 'Ask your spouse to link their account with yours.',
            ];
        }

        // WP-5c-iii — each step deep-links to the /m surface where the user
        // acts on it (route names from resources/mobile/router.js).
        $routeFor = static function (array $item): string {
            if (str_contains($item['title'], 'will') || str_contains($item['title'], 'Lasting Power')) {
                return 'm-estate';
            }
            if (str_contains($item['title'], 'tax saving')) {
                return 'tax-strategy';
            }

            return match ($item['group']) {
                'Wealth', 'Property' => 'm-net-worth',
                'Goals' => 'm-goals',
                'Tax year' => 'tax-strategy',
                'Savings' => 'm-savings',
                'Retirement' => 'm-retirement',
                'Protection & estate' => 'm-protection',
                default => 'dashboard',
            };
        };

        return array_map(
            static fn (array $item): array => $item + ['route' => $routeFor($item)],
            $upcoming,
        );
    }

    /**
     * WP-5 — journey milestones: completing the onboarding profile and
     * completing a first action. Cheap queries, safe to run on every
     * dashboard/progress-page read — which also makes the milestones page
     * self-healing (detection previously ran only on the /m dashboard read,
     * so a user who never opened it earned nothing).
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectJourney(User $user): array
    {
        $new = [];

        if ((bool) $user->onboarding_completed && ! empty($user->funnel_answers)) {
            $new = array_merge($new, $this->recordOnce(
                $user,
                'campaign',
                null,
                1,
                'You completed your tax profile.',
            ));
        }

        $hasCompletedAction = RecommendationTracking::where('user_id', $user->id)
            ->completed()
            ->exists();
        if ($hasCompletedAction) {
            $new = array_merge($new, $this->recordOnce(
                $user,
                'action',
                null,
                1,
                'You completed your first action.',
            ));
        }

        // WP-5c — anniversaries: one per completed year with Fynla.
        if ($user->created_at !== null) {
            $years = min(100, (int) floor($user->created_at->diffInDays(Carbon::now()) / 365.25));
            for ($y = 1; $y <= $years; $y++) {
                $new = array_merge($new, $this->recordOnce(
                    $user,
                    'anniversary',
                    null,
                    $y,
                    $y === 1 ? 'A year of planning with Fynla.' : $y.' years of planning with Fynla.',
                ));
            }
        }

        // WP-5c — household: a mutual spouse link.
        if ($user->spouse_id) {
            $spouse = User::find($user->spouse_id);
            if ($spouse && (int) $spouse->spouse_id === (int) $user->id) {
                $new = array_merge($new, $this->recordOnce(
                    $user,
                    'household',
                    null,
                    1,
                    "You've linked your household — planning together.",
                ));
            }
        }

        return $new;
    }

    /**
     * WP-5c — combined pension value crossing a threshold. The total comes
     * from the dashboard's net-worth breakdown, already computed on the read.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectPensionPot(User $user, float $total): array
    {
        $new = [];

        foreach (self::PENSION_POT_THRESHOLDS as $threshold) {
            if ($total < $threshold) {
                break;
            }
            $new = array_merge($new, $this->recordOnce(
                $user,
                'pension_pot',
                null,
                $threshold,
                'Your pension savings have passed £'.number_format($threshold).'.',
            ));
        }

        return $new;
    }

    /**
     * WP-5c — emergency-fund runway reaching 1/3/6 months of spending.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectEmergencyFund(User $user, float $months): array
    {
        $new = [];

        foreach (self::EMERGENCY_FUND_MONTHS as $threshold) {
            if ($months < $threshold) {
                break;
            }
            $new = array_merge($new, $this->recordOnce(
                $user,
                'emergency_fund',
                null,
                $threshold,
                $threshold === 1
                    ? 'Your emergency fund covers a month of your spending.'
                    : "Your emergency fund covers {$threshold} months of your spending.",
            ));
        }

        return $new;
    }

    /**
     * WP-5c — projected retirement income meeting the target, first time.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectRetirementOnTrack(User $user, float $projected, float $target): array
    {
        if ($target <= 0 || $projected < $target) {
            return [];
        }

        return $this->recordOnce(
            $user,
            'retirement_on_track',
            null,
            1,
            "You're on track for the retirement you've planned.",
        );
    }

    /**
     * WP-5c — every protection gap closed while holding at least one policy.
     * (Protection-wide rather than life-only: the dashboard read exposes the
     * critical-gaps count, not a per-type gap.)
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectProtectionAdequate(User $user, int $policyCount, int $criticalGaps): array
    {
        if ($policyCount < 1 || $criticalGaps !== 0) {
            return [];
        }

        return $this->recordOnce(
            $user,
            'protection_adequate',
            null,
            1,
            'Your protection now covers what your family would need.',
        );
    }

    /**
     * WP-5c — mortgage paydown at 25/50/75/100% of the original loan, per
     * mortgage. Cheap indexed query (a user typically has 0–2 mortgages).
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectMortgagesPaid(User $user): array
    {
        $new = [];

        $mortgages = Mortgage::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->get(['id', 'original_loan_amount', 'outstanding_balance']);

        foreach ($mortgages as $mortgage) {
            $original = (float) $mortgage->original_loan_amount;
            if ($original <= 0) {
                continue;
            }
            $paidPct = (1 - ((float) $mortgage->outstanding_balance / $original)) * 100;

            foreach (self::MORTGAGE_PAID_PERCENTS as $pct) {
                if ($paidPct < $pct) {
                    break;
                }
                $new = array_merge($new, $this->recordOnce(
                    $user,
                    'mortgage_paid',
                    $mortgage->id,
                    $pct,
                    match ($pct) {
                        25 => "You've paid off a quarter of your mortgage.",
                        50 => "You've paid off half your mortgage.",
                        75 => "You've paid off three-quarters of your mortgage.",
                        default => "You've paid off your mortgage.",
                    },
                ));
            }
        }

        return $new;
    }

    /**
     * WP-5c — will and Lasting Power of Attorney in place. Two cheap exists
     * queries on indexed user_id.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectEstateBasics(User $user): array
    {
        $new = [];

        // A Will row is a profile answer — has_will=false means "no will".
        if (Will::where('user_id', $user->id)->where('has_will', true)->exists()) {
            $new = array_merge($new, $this->recordOnce(
                $user,
                'will_in_place',
                null,
                1,
                'Your will is in place.',
            ));
        }

        // An LPA is in place once registered, not while drafted.
        if (LastingPowerOfAttorney::where('user_id', $user->id)->registered()->exists()) {
            $new = array_merge($new, $this->recordOnce(
                $user,
                'lpa_in_place',
                null,
                1,
                'Your Lasting Power of Attorney is in place.',
            ));
        }

        return $new;
    }

    /**
     * WP-5c — first ISA opened (cash or Stocks & Shares). ISAs are individual
     * by law, so both lookups are user_id-only.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectIsaFirst(User $user): array
    {
        // Already minted → skip the store read on every dashboard load.
        if (UserMilestone::where('user_id', $user->id)->where('milestone_type', 'isa_first')->exists()) {
            return [];
        }

        // Savings reads flow through the canonical store (SavingsStoreBoundary);
        // InvestmentAccount reads are unrestricted (its boundary guards writes).
        $hasIsa = $this->savingsStore->forUser($user)->contains(fn ($a): bool => (bool) $a->is_isa)
            || InvestmentAccount::where('user_id', $user->id)->isa()->exists();

        if (! $hasIsa) {
            return [];
        }

        return $this->recordOnce(
            $user,
            'isa_first',
            null,
            1,
            "You've opened your first ISA.",
        );
    }

    /**
     * WP-5c — a module's profile is complete when its prerequisite gate is
     * open and advice is flowing. The caller derives that from the dashboard's
     * focus-area cards (locked === false), so this adds zero queries.
     *
     * @param  array<int,string>  $completeModules  module keys (see MODULE_IDS)
     * @return array<int,array<string,mixed>>
     */
    public function detectModuleProfiles(User $user, array $completeModules): array
    {
        $new = [];

        foreach ($completeModules as $module) {
            $referenceId = self::MODULE_IDS[$module] ?? null;
            if ($referenceId === null) {
                continue;
            }
            $new = array_merge($new, $this->recordOnce(
                $user,
                'module_profile',
                $referenceId,
                1,
                "Your {$module} profile is complete.",
            ));
        }

        return $new;
    }

    /**
     * WP-5c — ISA / pension Annual Allowance usage crossing 50% and 100% for
     * the current tax year. Positions come from the tax-strategy allowance
     * grid already in the payload; reference_id is the tax-year start so the
     * milestone repeats each year (CSJ decision 2026-07-03).
     *
     * @param  string  $taxYear  e.g. "2026/27"
     * @param  array<int,array<string,mixed>>  $positions  allowance-grid rows
     * @return array<int,array<string,mixed>>
     */
    public function detectTaxYearAllowances(User $user, string $taxYear, array $positions): array
    {
        $year = (int) substr($taxYear, 0, 4);
        if ($year < 2000) {
            return [];
        }

        $byKey = [];
        foreach ($positions as $position) {
            if (is_array($position) && isset($position['key'])) {
                $byKey[(string) $position['key']] = $position;
            }
        }

        $families = [
            'isa_allowance' => ['isa_used', 'your ISA allowance'],
            'pension_annual_allowance' => ['pension_aa_used', 'your pension Annual Allowance'],
        ];

        $new = [];
        foreach ($families as $positionKey => [$type, $name]) {
            $position = $byKey[$positionKey] ?? null;
            if ($position === null || ! ($position['available'] ?? true) || (float) ($position['amount'] ?? 0) <= 0) {
                continue;
            }
            $pct = (float) ($position['utilisation_pct'] ?? 0);

            foreach (self::ALLOWANCE_USED_PERCENTS as $threshold) {
                if ($pct < $threshold) {
                    break;
                }
                $new = array_merge($new, $this->recordOnce(
                    $user,
                    $type,
                    $year,
                    $threshold,
                    ($threshold >= 100 ? "You've used all of " : "You've used half of ").$name." for {$taxYear}.",
                ));
            }
        }

        return $new;
    }

    /**
     * WP-5c — cumulative annual tax saving from completed plan actions: a
     * first-action milestone, then £ thresholds. The caller sums completed
     * items' savings from the composed plan already in the payload.
     * (Ceiling: completing both members of a conflict pair counts both — the
     * user did do both actions.)
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectTaxActioned(User $user, float $cumulativeAnnualSaving): array
    {
        if ($cumulativeAnnualSaving <= 0) {
            return [];
        }

        $new = $this->recordOnce(
            $user,
            'tax_actioned',
            null,
            1,
            "You've actioned your first tax saving.",
        );

        foreach (self::TAX_ACTIONED_THRESHOLDS as $threshold) {
            if ($cumulativeAnnualSaving < $threshold) {
                break;
            }
            $new = array_merge($new, $this->recordOnce(
                $user,
                'tax_actioned',
                null,
                $threshold,
                "Actions you've completed are saving you £".number_format($threshold).' a year in tax.',
            ));
        }

        return $new;
    }

    /**
     * WP-5c — named strategy firsts, minted from the recommendation-completed
     * observer. The recommendation id is 'tax_' + strategy type.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectStrategyFirst(User $user, string $recommendationId): array
    {
        if (! str_starts_with($recommendationId, 'tax_')) {
            return [];
        }

        $family = self::STRATEGY_FAMILIES[substr($recommendationId, 4)] ?? null;
        if ($family === null) {
            return [];
        }

        return $this->recordOnce(
            $user,
            'strategy:'.$family,
            null,
            1,
            self::STRATEGY_FAMILY_LABELS[$family],
        );
    }

    /**
     * WP-5c — first completed estate action, minted from the observer.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectEstateActionCompleted(User $user): array
    {
        return $this->recordOnce(
            $user,
            'estate_plan_started',
            null,
            1,
            "You've started planning your estate.",
        );
    }

    /**
     * WP-5 — the first time the composed tax plan quantifies an annual
     * saving. Called from the tax-strategy read, where the plan is already
     * computed (composing it again here would double the cost). The
     * threshold stores the rounded amount so the milestone title can quote
     * it.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectTaxSavingsIdentified(User $user, float $combinedAnnualSaving): array
    {
        if ($combinedAnnualSaving <= 0) {
            return [];
        }

        // One per user — the FIRST quantified find is the milestone; the
        // figure evolving later is normal plan drift, not a new milestone.
        if (UserMilestone::where('user_id', $user->id)->where('milestone_type', 'tax_savings')->exists()) {
            return [];
        }

        $amount = (int) round($combinedAnnualSaving);

        return $this->recordOnce(
            $user,
            'tax_savings',
            null,
            $amount,
            'We found £'.number_format($amount).' a year you could save in tax.',
        );
    }

    /**
     * firstOrCreate + award plumbing shared by the journey milestone
     * flavours.
     *
     * @return array<int,array<string,mixed>>
     */
    private function recordOnce(User $user, string $type, ?int $referenceId, int|float $threshold, string $label): array
    {
        $record = UserMilestone::firstOrCreate(
            [
                'user_id' => $user->id,
                'milestone_type' => $type,
                'reference_id' => $referenceId,
                'threshold' => $threshold,
            ],
            ['achieved_at' => Carbon::now()],
        );

        if (! $record->wasRecentlyCreated) {
            return [];
        }

        $this->points->award(
            $user,
            'milestone',
            'milestone:'.$type.':'.($referenceId ?? 0).':'.$threshold,
            (int) config('gamification.points.milestone'),
            ['threshold' => $threshold],
        );

        $this->minted($user, $type, $label);

        return [[
            'type' => $type,
            'threshold' => $threshold,
            'label' => $label,
            'share_type' => 'app_referral',
        ]];
    }

    /**
     * WP-5c-iii — every fresh mint lands in the request-scoped collector (so
     * the surface that caused it can acknowledge it in the same turn) and,
     * flag-gated, goes out as a push notification. Never throws, never fires
     * for preview personas.
     */
    private function minted(User $user, string $type, string $label): void
    {
        try {
            $this->collector->record($type, $label);

            if ((bool) config('gamification.push_enabled', false) && ! $user->is_preview_user) {
                app(PushNotificationService::class)->sendToUser(
                    $user->id,
                    'Milestone reached',
                    $label,
                    ['type' => 'milestone', 'milestone_type' => $type],
                );
            }
        } catch (\Throwable) {
            // best-effort — a nudge failure must never break detection
        }
    }
}
