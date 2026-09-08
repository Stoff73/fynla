# Fyn Wiring Batch A: Revive the Savings Recommendation Engine (F0) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the seeded savings action definitions fire on all three consumers (Fyn's ranked list, the dashboard aggregator, the savings plan page), with the interest-rate basis, the emergency-fund month table and the goal logic each living in one place.

**Architecture:** The `savings_action_definitions` rows are the contract: `trigger_config.condition` names the evaluator, the other `trigger_config` keys carry the thresholds. `SavingsActionDefinitionService::evaluateAgentTrigger` switches on that condition, exactly as the other five engines already do. Goal recommendations become seeded rows evaluated by the same service, so the savings agent's private goal builder and the plan page's separate goal call go away. `RateComparator` is the one place that reconciles the percentage column with the decimal benchmarks. `EmergencyFundCalculator::getTargetMonths` is the one month table.

**Tech Stack:** Laravel 10, Pest, MySQL 8. Pest suites run with `./vendor/bin/pest <path>`; formatting with `./vendor/bin/pint`.

**Spec:** The wiring artifact (https://claude.ai/code/artifact/7375932e-a8e0-4920-9142-5a2db33b2d88), findings F0, F15, F16, F17 and the savings half of F3, plus CSJ's decisions of 2026-09-08: goals must be taken into account and Fyn proposes a goal when the user is short; evaluators with no row are seeded when useful and deleted otherwise; the State Pension figure comes from tax configuration; the canonical emergency-fund table is 3-6-9 with retired at 3; the rate basis is fixed in the comparator.

## Global Constraints

- `declare(strict_types=1);` in every PHP file. British spelling in user-facing strings, American in code.
- No hardcoded tax values (root CLAUDE.md Rule 2). The full new State Pension is read from `TaxConfigService`, never a literal.
- `savings_accounts.interest_rate` holds **percentages** (4.25 means 4.25 per cent): form (`SaveAccountModal.vue:1081`), validation `max:20` (`StoreSavingsAccountRequest.php:53`), model accessor `getAnnualInterestAttribute` divides by 100 (`app/Models/SavingsAccount.php:93-95`), migration comment W-0263. `savings_market_rates.rate` holds **decimals** (0.0450). Nothing outside `RateComparator` may combine the two.
- Keep untouched: the spouse-consent and reciprocal-link reads in the spouse evaluators (W-0350, W-0530, 2026-08-29), the `DependantsReach` usage in the child evaluators (W-0275, 2026-09-01), the "designation, not definition" runway principle (W-0495), and the seven disabled Investment overlap rows.
- Lean cadence (Rule 17): one PR, targeted test families per task, one consolidated full pass at the end. Never `migrate:fresh`; reseed with `php artisan db:seed --class=SavingsActionDefinitionSeeder --force`.
- Done means verified on web AND `/m` (Rule 19), and Fyn's `<financial_context>` carrying the same savings recommendations (Rule 20).

---

## File Structure

| File | Responsibility after this plan |
|---|---|
| `database/seeders/SavingsActionDefinitionSeeder.php` | The catalogue. Rows added: `create_emergency_fund_goal`, `life_event_cash_buffer`, `isa_allowance_remaining`, `zero_rate_account`, `emergency_fund_no_designated`, `psa_headroom_available`, `child_no_savings`, `goal_no_contribution`, `goal_underfunded`, `goal_deadline_approaching`. No rows disabled: the eight rows that had no evaluator (`emergency_fund_no_data`, `psa_additional_rate`, `starting_rate_unused`, `regular_saver_opportunity`, `goal_wrong_account_type`, `goal_multi_account_rebalance`, `child_jisa_cash_vs_ss`, `child_parental_settlement`) get one each in Task 3. `regular_saver_opportunity` gains `min_monthly_contribution => 25` in its trigger config. Goal rows moved to `source => 'agent'`. |
| `app/Services/Savings/SavingsActionDefinitionService.php` | Dispatcher keyed on `condition`; config keys read as seeded; one runway-between evaluator; two new evaluators (`evaluateEmergencyFundGoalSuggested`, `evaluateLifeEventCashBuffer`), eight new for the rows that had none (`evaluateEmergencyFundNoData`, `evaluatePsaAdditionalRate`, `evaluateStartingRateUnused`, `evaluateRegularSaverOpportunity`, `evaluateGoalWrongAccountType`, `evaluateGoalMultiAccountRebalance`, `evaluateChildJisaCashVsStocks`, `evaluateChildParentalSettlement`), one new (`evaluateGoalNearlyAchieved`); constructor gains `TaxStrategyMath`; duplicates deleted (`evaluateEmergencyFundBuilding`, `evaluateRateImprovementAvailable`, `evaluateChildSavingsReview`, `evaluateGoalActions` and the four `evaluateLinkedGoal*` duplicates, `evaluateGoalTrigger`, `getTargetEmergencyMonths`); conflict resolver extended. |
| `app/Services/Savings/RateComparator.php` | The one basis boundary. Accepts the percentage column, works in decimals, returns both. |
| `app/Services/Savings/EmergencyFundCalculator.php` | The one month table. |
| `app/Agents/SavingsAgent.php` | `analyze()` carries `user_id`; `generateRecommendations()` delegates entirely to the service; the private goal builder and month table are deleted. |
| `app/Services/Coordination/PlanSources/SavingsStrategySource.php` | Stops injecting `user_id` (the analysis now carries it). |
| `app/Services/Plans/SavingsPlanService.php` | Stops calling `evaluateGoalActions`; goal-category recommendations are ordered first from the single list. |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | State Pension read from config with no literal fallback. |
| `tests/Feature/Database/ActionDefinitionDispatchCoverageTest.php` | New guard: every enabled seeded condition has a dispatcher arm and every arm has a seeded row, for all six engines. |
| `tests/Unit/Services/Savings/SavingsActionDefinitionServiceTest.php` | New: one firing test per revived family. |
| `tests/Unit/Services/Savings/RateComparatorTest.php` | New: basis tests. |

---

### Task 1: The dispatch-coverage guard test (fails first, for Savings only)

**Files:**
- Create: `tests/Feature/Database/ActionDefinitionDispatchCoverageTest.php`

**Interfaces:**
- Consumes: the six `*ActionDefinition` models and seeders; the six service source files.
- Produces: nothing; a guard every later task must keep green.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Every enabled seeded `trigger_config.condition` must have a dispatcher arm
 * in its engine, and every arm must have at least one seeded row. This is the
 * test that would have caught the savings engine being dead since 2026-03-14:
 * its arms were named after row keys while the rows carry condition names.
 */
$engines = [
    'Savings' => ['table' => 'savings_action_definitions', 'service' => 'app/Services/Savings/SavingsActionDefinitionService.php', 'seeder' => Database\Seeders\SavingsActionDefinitionSeeder::class],
    'Investment' => ['table' => 'investment_action_definitions', 'service' => 'app/Services/Investment/InvestmentActionDefinitionService.php', 'seeder' => Database\Seeders\InvestmentActionDefinitionSeeder::class],
    'Retirement' => ['table' => 'retirement_action_definitions', 'service' => 'app/Services/Retirement/RetirementActionDefinitionService.php', 'seeder' => Database\Seeders\RetirementActionDefinitionSeeder::class],
    'Protection' => ['table' => 'protection_action_definitions', 'service' => 'app/Services/Protection/ProtectionActionDefinitionService.php', 'seeder' => Database\Seeders\ProtectionActionDefinitionSeeder::class],
    'Estate' => ['table' => 'estate_action_definitions', 'service' => 'app/Services/Estate/EstateActionDefinitionService.php', 'seeder' => Database\Seeders\EstateActionDefinitionSeeder::class],
    'Tax' => ['table' => 'tax_action_definitions', 'service' => 'app/Services/Tax/TaxActionDefinitionService.php', 'seeder' => Database\Seeders\TaxActionDefinitionSeeder::class],
];

function dispatcherArms(string $relativePath): array
{
    $source = file_get_contents(base_path($relativePath));
    preg_match_all("/^\\s*'([a-z0-9_]+)'\\s*=>\\s*\\\$this->evaluate/m", $source, $matches);

    return array_values(array_unique($matches[1]));
}

function seededConditions(string $table, bool $enabledOnly): array
{
    $query = DB::table($table)->whereNotNull('trigger_config');
    if ($enabledOnly) {
        $query->where('is_enabled', true);
    }

    return $query->get()
        ->map(fn ($row) => json_decode((string) $row->trigger_config, true)['condition'] ?? null)
        ->filter()
        ->unique()
        ->values()
        ->all();
}

foreach ($engines as $name => $engine) {
    it("{$name}: every enabled seeded condition has a dispatcher arm", function () use ($engine) {
        $this->seed(Database\Seeders\TaxConfigurationSeeder::class);
        $this->seed($engine['seeder']);

        $arms = dispatcherArms($engine['service']);
        $missing = array_values(array_diff(seededConditions($engine['table'], true), $arms));

        expect($missing)->toBe([], 'Enabled conditions with no arm: '.implode(', ', $missing));
    });

    it("{$name}: every dispatcher arm has a seeded row", function () use ($engine) {
        $this->seed(Database\Seeders\TaxConfigurationSeeder::class);
        $this->seed($engine['seeder']);

        $orphans = array_values(array_diff(dispatcherArms($engine['service']), seededConditions($engine['table'], false)));

        expect($orphans)->toBe([], 'Arms with no seeded row: '.implode(', ', $orphans));
    });
}
```

- [ ] **Step 2: Run it to verify the two Savings cases fail and the other ten pass**

Run: `./vendor/bin/pest tests/Feature/Database/ActionDefinitionDispatchCoverageTest.php`
Expected: `Savings: every enabled seeded condition has a dispatcher arm` FAILS listing 36 conditions; `Savings: every dispatcher arm has a seeded row` FAILS listing 46 arms; Investment, Retirement, Protection, Estate, Tax all PASS. If Protection's seeder needs `PlanConfigurationSeeder`, add that seed call inside the loop for all engines.

- [ ] **Step 3: Commit the red test**

```bash
git checkout -b fix/f0-savings-engine-dispatch dev
git add tests/Feature/Database/ActionDefinitionDispatchCoverageTest.php
git commit -m "test(engines): guard that every seeded condition has a dispatcher arm and vice versa (red for savings)

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FSgisEQqTaU43JisiH7J9z"
```

---

### Task 2: Savings dispatcher keyed on `condition`, thresholds read as seeded

**Files:**
- Modify: `app/Services/Savings/SavingsActionDefinitionService.php:132-216` (dispatcher), `:399-724` (emergency fund evaluators), `:814-832` (PSA approaching), `:1033-1052` (Cash ISA not needed), `:1170-1345` (rate evaluators), `:1347-1440` (maturity and promo), `:1764-1800` (debt rate), `:1995-2010` (cash drag), `:2825-2840` (child turning 18), conflict resolver near `:3480`.
- Modify: `database/seeders/SavingsActionDefinitionSeeder.php` (rows listed in File Structure).
- Test: `tests/Unit/Services/Savings/SavingsActionDefinitionServiceTest.php` (create).

**Interfaces:**
- Consumes: `SavingsActionDefinition::getEnabledBySource('agent')`; `PSACalculator::assessPSAPosition(User): array{utilisation_percent, breach_amount, tax_band, ...}`; `RateComparator` output from Task 4 (`account_rate_percent`, `market_rate_percent`).
- Produces: `evaluateAgentActions(array $savingsAnalysis, array $investmentAnalysis, Collection $savingsAccounts, Collection $investmentAccounts, int $userId): array{recommendations: list<array>, total_count: int, high_priority_count: int}` unchanged in signature; each recommendation carries `definition_key`.

- [ ] **Step 1: Write the failing firing tests**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\SavingsActionDefinition;
use App\Models\User;
use App\Services\Savings\SavingsActionDefinitionService;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
    $this->service = app(SavingsActionDefinitionService::class);
    $this->user = User::factory()->create([
        'employment_status' => 'employed',
        'annual_employment_income' => 45000,
        'monthly_expenditure' => 2000,
        'date_of_birth' => '1985-01-01',
        'marital_status' => 'single',
    ]);
});

function savingsAnalysis(float $runway, float $expenditure = 2000, array $extra = []): array
{
    return array_merge([
        'summary' => ['monthly_expenditure' => $expenditure, 'total_savings' => $runway * $expenditure],
        'emergency_fund' => ['runway_months' => $runway, 'current_balance' => $runway * $expenditure],
        'rate_comparisons' => [],
        'isa_allowance' => ['remaining' => 0],
    ], $extra);
}

function keysOf(array $result): array
{
    return array_values(array_unique(array_column($result['recommendations'], 'definition_key')));
}

describe('emergency fund family', function () {
    it('fires emergency_fund_critical below 1 month', function () {
        $result = $this->service->evaluateAgentActions(savingsAnalysis(0.5), [], collect(), collect(), $this->user->id);
        expect(keysOf($result))->toContain('emergency_fund_critical')->not->toContain('emergency_fund_low');
    });

    it('fires emergency_fund_low between 1 and 3 months', function () {
        $result = $this->service->evaluateAgentActions(savingsAnalysis(2.0), [], collect(), collect(), $this->user->id);
        expect(keysOf($result))->toContain('emergency_fund_low')->not->toContain('emergency_fund_building');
    });

    it('fires emergency_fund_building between 3 and 6 months', function () {
        $result = $this->service->evaluateAgentActions(savingsAnalysis(4.0), [], collect(), collect(), $this->user->id);
        expect(keysOf($result))->toContain('emergency_fund_building');
    });

    it('fires emergency_fund_excess above the seeded 6-month threshold, not the old 12', function () {
        $result = $this->service->evaluateAgentActions(savingsAnalysis(8.0), [], collect(), collect(), $this->user->id);
        expect(keysOf($result))->toContain('emergency_fund_excess');
    });

    it('does not state a runway it cannot measure', function () {
        $result = $this->service->evaluateAgentActions(savingsAnalysis(0.0, 0.0), [], collect(), collect(), $this->user->id);
        expect(keysOf($result))->not->toContain('emergency_fund_critical');
    });
});

describe('fixed-rate maturity family reads days_threshold and de-duplicates', function () {
    it('fires only the urgent row for an account maturing in 10 days', function () {
        $account = SavingsAccount::factory()->create([
            'user_id' => $this->user->id, 'access_type' => 'fixed', 'maturity_date' => now()->addDays(10),
            'current_balance' => 10000, 'interest_rate' => 4.5,
        ]);
        $result = $this->service->evaluateAgentActions(savingsAnalysis(6.0), [], collect([$account]), collect(), $this->user->id);
        expect(keysOf($result))->toContain('fixed_maturity_urgent')->not->toContain('fixed_maturity_warning');
    });

    it('fires only the warning row for an account maturing in 60 days', function () {
        $account = SavingsAccount::factory()->create([
            'user_id' => $this->user->id, 'access_type' => 'fixed', 'maturity_date' => now()->addDays(60),
            'current_balance' => 10000, 'interest_rate' => 4.5,
        ]);
        $result = $this->service->evaluateAgentActions(savingsAnalysis(6.0), [], collect([$account]), collect(), $this->user->id);
        expect(keysOf($result))->toContain('fixed_maturity_warning')->not->toContain('fixed_maturity_urgent');
    });
});

describe('rate family uses the comparator gap in percentage points', function () {
    it('fires rate_poor, not rate_below_market, when the gap is 1.5 points or more', function () {
        $account = SavingsAccount::factory()->create([
            'user_id' => $this->user->id, 'access_type' => 'immediate', 'is_isa' => false,
            'current_balance' => 20000, 'interest_rate' => 2.5,
        ]);
        $comparison = ['account_id' => $account->id, 'comparison' => [
            'account_rate' => 0.025, 'market_rate' => 0.045, 'account_rate_percent' => 2.5, 'market_rate_percent' => 4.5,
            'difference' => -0.02, 'difference_percent' => -2.0, 'is_competitive' => false, 'category' => 'Poor',
        ], 'potential_gain' => 400.0];
        $result = $this->service->evaluateAgentActions(
            savingsAnalysis(6.0, 2000, ['rate_comparisons' => [$comparison]]), [], collect([$account]), collect(), $this->user->id
        );
        expect(keysOf($result))->toContain('rate_poor')->not->toContain('rate_below_market');
    });
});

describe('psa family reads seeded thresholds', function () {
    it('fires psa_approaching at 80 per cent usage and not at 76', function () {
        $definition = SavingsActionDefinition::where('key', 'psa_approaching')->firstOrFail();
        expect($definition->trigger_config['threshold'])->toBe(80);
        // The assertion on firing lives in the PSA calculator test: this test pins the seed.
    });
});

it('every enabled agent row is reachable by the dispatcher', function () {
    $arms = [];
    $source = file_get_contents(app_path('Services/Savings/SavingsActionDefinitionService.php'));
    preg_match_all("/^\\s*'([a-z0-9_]+)'\\s*=>\\s*\\\$this->evaluate/m", $source, $m);
    $arms = $m[1];
    $conditions = SavingsActionDefinition::where('is_enabled', true)->whereIn('source', ['agent'])->get()
        ->map(fn ($d) => $d->trigger_config['condition'] ?? null)->filter()->unique()->values()->all();
    expect(array_values(array_diff($conditions, $arms)))->toBe([]);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Savings/SavingsActionDefinitionServiceTest.php`
Expected: every firing test FAILS (empty `definition_key` lists); the seed-pinning test passes.

- [ ] **Step 3: Replace the dispatcher arms with the seeded condition names**

Replace the whole `match ($condition)` body at `SavingsActionDefinitionService.php:144-214` with:

```php
        return match ($condition) {
            // Data Readiness
            'date_of_birth_missing' => $this->evaluateMissingDOB($definition, $userId, $priority),
            'income_missing' => $this->evaluateMissingIncome($definition, $userId, $priority),
            'expenditure_missing' => $this->evaluateMissingExpenditure($definition, $userId, $priority),
            'employment_status_missing' => $this->evaluateMissingEmployment($definition, $userId, $priority),

            // Emergency Fund
            'emergency_runway_below' => $this->evaluateEmergencyFundCritical($definition, $savingsAnalysis, $savingsAccounts, $userId, $config, $priority),
            'emergency_runway_between' => $this->evaluateEmergencyRunwayBetween($definition, $savingsAnalysis, $savingsAccounts, $userId, $config, $priority),
            'emergency_runway_above' => $this->evaluateEmergencyFundExcessive($definition, $savingsAnalysis, $userId, $config, $priority),
            'no_designated_emergency_fund' => $this->evaluateEmergencyFundNoDesignated($definition, $savingsAccounts, $priority),
            'no_emergency_fund_goal_and_runway_below' => $this->evaluateEmergencyFundGoalSuggested($definition, $savingsAnalysis, $userId, $config, $priority),
            'emergency_fund_expenditure_missing' => $this->evaluateEmergencyFundNoData($definition, $savingsAnalysis, $savingsAccounts, $userId, $priority),

            // Tax Efficiency (PSA / ISA)
            'psa_exceeded' => $this->evaluatePSABreached($definition, $userId, $priority),
            'psa_usage_above' => $this->evaluatePSAApproaching($definition, $userId, $config, $priority),
            'psa_headroom_above' => $this->evaluatePSAHeadroomAvailable($definition, $userId, $config, $priority),
            'additional_rate_taxpayer_with_taxable_savings' => $this->evaluatePsaAdditionalRate($definition, $userId, $priority),
            'eligible_for_starting_rate' => $this->evaluateStartingRateUnused($definition, $userId, $priority),
            'has_taxable_savings_no_cash_isa' => $this->evaluateCashISARecommended($definition, $savingsAnalysis, $userId, $priority),
            'basic_rate_with_psa_headroom' => $this->evaluateCashISANotNeeded($definition, $savingsAnalysis, $userId, $config, $priority),
            'isa_remaining_and_runway_above' => $this->evaluateISAAllowanceRemaining($definition, $savingsAnalysis, $config, $priority),

            // Rate Optimisation
            'rate_below_market_best' => $this->evaluateRateBelowMarket($definition, $savingsAnalysis, $savingsAccounts, $config, $priority),
            'rate_significantly_below_market' => $this->evaluateRateSignificantlyBelow($definition, $savingsAnalysis, $savingsAccounts, $config, $priority),
            'fixed_term_maturing_within' => $this->evaluateFixedRateMaturing($definition, $savingsAccounts, $config, $priority),
            'promo_rate_expiring_within' => $this->evaluatePromoRateExpiring($definition, $savingsAccounts, $config, $priority),
            'account_rate_is_zero' => $this->evaluateZeroRateAccount($definition, $savingsAccounts, $priority),
            'has_easy_access_with_regular_contributions' => $this->evaluateRegularSaverOpportunity($definition, $savingsAccounts, $config, $priority),

            // FSCS Protection
            'institution_balance_above_fscs' => $this->evaluateFSCSBreach($definition, $savingsAccounts, $priority),
            'institution_balance_approaching_fscs' => $this->evaluateFSCSApproaching($definition, $savingsAccounts, $priority),

            // Debt vs Savings
            'debt_rate_exceeds_savings_rate' => $this->evaluateDebtRateExceedsSavings($definition, $userId, $savingsAccounts, $config, $priority),
            'mortgage_rate_exceeds_after_tax_savings_rate' => $this->evaluateMortgageRateComparison($definition, $userId, $savingsAccounts, $priority),

            // Cash vs Investment
            'excess_cash_and_isa_remaining' => $this->evaluateConsiderStocksSharesISA($definition, $savingsAnalysis, $investmentAnalysis, $userId, $priority),
            'excess_cash_isa_full_pension_remaining' => $this->evaluateConsiderPensionContribution($definition, $savingsAnalysis, $userId, $priority),
            'excess_cash_isa_and_pension_full' => $this->evaluateCashDragRisk($definition, $savingsAnalysis, $investmentAnalysis, $config, $priority),
            'surplus_above_emergency_fund' => $this->evaluateSurplusAboveEmergencyFund($definition, $savingsAnalysis, $config, $priority),

            // Goals (portfolio-level, one query per user)
            'goal_no_linked_savings_account' => $this->evaluateGoalNoLinkedAccount($definition, $userId, $priority),
            'goal_underfunded' => $this->evaluateGoalUnderfunded($definition, $userId, $priority),
            'goal_contribution_shortfall' => $this->evaluateGoalOffTrack($definition, $userId, $priority),
            'goal_no_monthly_contribution' => $this->evaluateGoalNoContribution($definition, $userId, $priority),
            'goal_months_remaining_below_and_progress_below' => $this->evaluateGoalDeadlineApproaching($definition, $userId, $config, $priority),
            'goal_progress_above' => $this->evaluateGoalNearlyAchieved($definition, $userId, $config, $priority),
            'expense_life_event_within' => $this->evaluateLifeEventCashBuffer($definition, $userId, $config, $priority),
            'goal_account_type_mismatch' => $this->evaluateGoalWrongAccountType($definition, $userId, $savingsAccounts, $priority),
            'multiple_goals_sharing_account' => $this->evaluateGoalMultiAccountRebalance($definition, $userId, $savingsAccounts, $priority),

            // Children's Savings
            'child_under_18_no_savings' => $this->evaluateChildNoSavings($definition, $userId, $savingsAccounts, $priority),
            'child_under_18_no_jisa' => $this->evaluateJuniorISANotOpen($definition, $userId, $savingsAccounts, $priority),
            'jisa_allowance_remaining' => $this->evaluateJuniorISAAllowanceRemaining($definition, $userId, $savingsAccounts, $priority),
            'child_turning_18_within' => $this->evaluateChildApproaching18($definition, $userId, $config, $priority),
            'child_has_cash_jisa_with_long_horizon' => $this->evaluateChildJisaCashVsStocks($definition, $userId, $savingsAccounts, $config, $priority),
            'child_parental_interest_above' => $this->evaluateChildParentalSettlement($definition, $userId, $savingsAccounts, $config, $priority),

            // Spouse Coordination
            'spouse_has_unused_psa' => $this->evaluateSpousePSAOptimisation($definition, $userId, $priority),
            'spouse_isa_allowance_imbalanced' => $this->evaluateSpouseISACoordination($definition, $userId, $savingsAnalysis, $priority),

            default => [],
        };
```

Every arm above is either an existing evaluator or one created in Task 3. Delete `evaluateEmergencyFundLow`, `evaluateEmergencyFundBuilding` (replaced by `evaluateEmergencyRunwayBetween` below), `evaluateRateImprovementAvailable` and `evaluateChildSavingsReview` (no row, no consumer).

- [ ] **Step 4: Merge the two runway-between evaluators into one**

Replace `evaluateEmergencyFundLow` (`:478-557`) with the following and delete `evaluateEmergencyFundBuilding` (`:559-611`). It keeps the richer top-up trace and reads both bounds from the row, so the low row (1 to 3) and the building row (3 to 6) both work.

```php
    /**
     * Emergency runway between two seeded bounds. Two rows share this
     * condition: `emergency_fund_low` (1 to 3 months) and
     * `emergency_fund_building` (3 to target). The bounds come from the row.
     */
    private function evaluateEmergencyRunwayBetween(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        Collection $savingsAccounts,
        int $userId,
        array $config,
        int $priority
    ): array {
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? null;
        if ($runway === null) {
            return []; // W-0495: an unmeasured runway is not zero months.
        }

        $low = (float) ($config['low'] ?? 1);
        $high = (float) ($config['high'] ?? 3);
        if ($runway < $low || $runway >= $high) {
            return [];
        }

        $monthlyExpenditure = (float) ($savingsAnalysis['summary']['monthly_expenditure'] ?? 0);
        if ($monthlyExpenditure <= 0) {
            return [];
        }

        $user = User::find($userId);
        $targetMonths = $this->emergencyFundCalculator->getTargetMonths($user?->employment_status);
        $currentBalance = (float) ($savingsAnalysis['emergency_fund']['current_balance']
            ?? $savingsAccounts->where('is_emergency_fund', true)->sum('current_balance'));
        $shortfallMonths = max(0, $targetMonths - $runway);
        $shortfallAmount = $shortfallMonths * $monthlyExpenditure;
        $monthlyTopUp = $this->emergencyFundCalculator->calculateMonthlyTopUp($shortfallAmount, 12);

        $trace = [];
        if ($user) {
            $trace[] = $this->buildUserProfileTrace($user);
        }
        $trace[] = $this->buildEmploymentTargetTrace($user, $targetMonths);
        $trace[] = $this->buildEmergencyFundAccountsTrace($savingsAccounts);
        $trace[] = [
            'question' => 'What is the current emergency fund runway?',
            'data_field' => 'runway_months',
            'data_value' => number_format($runway, 1).' months',
            'threshold' => 'Between '.number_format($low, 1).' and '.number_format($high, 1).' months',
            'passed' => true,
            'explanation' => 'Total emergency savings £'.number_format($currentBalance, 0).' ÷ £'.number_format($monthlyExpenditure, 0).' monthly expenditure = '.number_format($runway, 1).' months, against a '.$targetMonths.'-month target.',
        ];
        $trace[] = [
            'question' => 'How much needs to be saved each month to reach the target within a year?',
            'data_field' => 'monthly_top_up',
            'data_value' => '£'.number_format($monthlyTopUp, 0).'/month',
            'threshold' => $targetMonths.'-month target = £'.number_format($targetMonths * $monthlyExpenditure, 0),
            'passed' => true,
            'explanation' => 'Shortfall of £'.number_format($shortfallAmount, 0).' ('.number_format($shortfallMonths, 1).' months × £'.number_format($monthlyExpenditure, 0).'/month). Saving £'.number_format($monthlyTopUp, 0).' per month would close the gap within 12 months.',
        ];

        $vars = [
            'runway_months' => number_format($runway, 1),
            'target_months' => (string) $targetMonths,
            'monthly_top_up' => $this->formatCurrency($monthlyTopUp),
            'adequacy_percent' => number_format(min(100, $runway / max(1, $targetMonths) * 100), 0),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($shortfallAmount, 2);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }
```

Both seeded templates (`{runway_months}`, `{target_months}`, `{monthly_top_up}`, `{adequacy_percent}`) stay satisfied.

- [ ] **Step 5: Read the seeded thresholds where the evaluators carried literals**

Apply these edits exactly:

1. `evaluateEmergencyFundCritical` (`:410`): `$runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? null; if ($runway === null) { return []; }` before the threshold comparison. Replace the `getTargetEmergencyMonths($user)` call (`:424`) with `$this->emergencyFundCalculator->getTargetMonths($user?->employment_status)`.
2. `evaluateEmergencyFundExcessive` (`:662-724`): add `int $userId` before `array $config` in the signature; replace `$threshold = (float) ($config['threshold'] ?? 12);` with `$threshold = (float) ($config['threshold'] ?? 6);`; replace `$targetMonths = 6;` with `$targetMonths = $this->emergencyFundCalculator->getTargetMonths(User::find($userId)?->employment_status);`; change the two "6-month target" strings to use `$targetMonths`.
3. `evaluatePSAApproaching` (`:814`): add `array $config` before `int $priority`; replace `if (! $psaPosition['is_approaching']) { return []; }` with:
```php
        $threshold = (float) ($config['threshold'] ?? 80);
        if (($psaPosition['utilisation_percent'] ?? 0) < $threshold || ($psaPosition['breach_amount'] ?? 0) > 0) {
            return [];
        }
```
4. `evaluatePSAHeadroomAvailable` (`:872`): add `array $config` before `int $priority`; replace `$psaPosition['utilisation_percent'] > 50` with `(100 - ($psaPosition['utilisation_percent'] ?? 100)) < (float) ($config['headroom_threshold'] ?? 50)`.
5. `evaluateCashISANotNeeded` (`:1033`): add `array $config` before `int $priority`; replace `if ($psaPosition['utilisation_percent'] > 25)` with `if ((100 - ($psaPosition['utilisation_percent'] ?? 100)) < (float) ($config['headroom_threshold'] ?? 50))`.
6. `evaluateRateBelowMarket` (`:1170`) and `evaluateRateSignificantlyBelow` (`:1259`): add `array $config` before `int $priority`. Replace the `$rating !== 'Fair'` / `$rating !== 'Poor'` tests with a gap test, and read rates from the comparison instead of the column:
```php
            $gapThreshold = (float) ($config['gap_threshold'] ?? 0.5);
            $currentRate = (float) ($comparison['comparison']['account_rate_percent'] ?? 0);
            $marketRate = (float) ($comparison['comparison']['market_rate_percent'] ?? 0);
            $rateGap = $marketRate - $currentRate;
            if ($rateGap < $gapThreshold) {
                continue;
            }
            $currentInterest = $balance * $currentRate / 100;
            $marketInterest = $balance * $marketRate / 100;
```
Delete the four lines that computed `$currentRate`, `$marketRate`, `$currentInterest`, `$marketInterest` from `$account->interest_rate` and `market_rate`. `rate_poor` (gap 1.5) therefore also satisfies `rate_below_market` (gap 0.5); Step 7 de-duplicates.
7. `evaluateFixedRateMaturing` (`:1353`): `$windowDays = (int) ($config['days_threshold'] ?? 90);`. Replace `$currentRate = ((float) ($account->interest_rate ?? 0)) * 100;` with `$currentRate = (float) ($account->interest_rate ?? 0);` and `$annualInterest = $balance * ((float) ($account->interest_rate ?? 0));` with `$annualInterest = $account->annual_interest;`.
8. `evaluatePromoRateExpiring` (`:1426`): `$windowDays = (int) ($config['days_threshold'] ?? 30);` and the same two rate-line replacements as item 7.
9. `evaluateDebtRateExceedsSavings` (`:1764`): add `array $config` before `int $priority`; replace the closure with:
```php
        $minDifference = (float) ($config['min_rate_difference'] ?? 2);
        $highRateMortgage = $mortgages->first(function ($mortgage) use ($bestSavingsRate, $minDifference) {
            $mortgageRate = (float) ($mortgage->interest_rate ?? 0);

            return $mortgageRate > 0 && ($mortgageRate - $bestSavingsRate) >= $minDifference;
        });
```
(Both columns are percentages, so the subtraction is in percentage points.)
10. `evaluateCashDragRisk` (`:2005`): `$threshold = (float) ($config['min_excess'] ?? $config['threshold'] ?? 50000);`.
11. `evaluateChildApproaching18` (`:2825`): add `array $config` before `int $priority`; wherever the 12-month window is compared, read `$monthsThreshold = (int) ($config['months_threshold'] ?? 12);` and use it.
12. `evaluateGoalDeadlineApproaching` (`:2528`): already reads `months_threshold` and `progress_threshold`; no change.

- [ ] **Step 6: Delete the private month table**

Delete `getTargetEmergencyMonths` (`:3563-3576`) and its docblock (`:3551-3562`). Replace the remaining call at `:324` (`evaluateMissingExpenditure`) with `$this->emergencyFundCalculator->getTargetMonths($user?->employment_status)`. Update the explanation string at `:382` to "employed = 6 months, self-employed or contractor = 9 months, retired = 3 months".

- [ ] **Step 7: Extend the conflict resolver**

In `resolveConflicts` (near `:3480`), after the PSA block, add:

```php
        // Expenditure missing: the emergency-fund consequence supersedes the generic readiness row.
        if (in_array('emergency_fund_no_data', $keys, true)) {
            $recommendations = array_values(array_filter(
                $recommendations,
                fn ($r) => ($r['definition_key'] ?? '') !== 'missing_expenditure'
            ));
        }

        // Rate: rate_poor supersedes rate_below_market for the same account.
        $poorAccounts = collect($recommendations)->where('definition_key', 'rate_poor')->pluck('account_id')->filter()->all();
        if ($poorAccounts !== []) {
            $recommendations = array_values(array_filter(
                $recommendations,
                fn ($r) => ! (($r['definition_key'] ?? '') === 'rate_below_market' && in_array($r['account_id'] ?? null, $poorAccounts, true))
            ));
        }

        // Maturity: the 30-day urgent row supersedes the 90-day warning for the same account.
        $urgentAccounts = collect($recommendations)->where('definition_key', 'fixed_maturity_urgent')->pluck('account_id')->filter()->all();
        if ($urgentAccounts !== []) {
            $recommendations = array_values(array_filter(
                $recommendations,
                fn ($r) => ! (($r['definition_key'] ?? '') === 'fixed_maturity_warning' && in_array($r['account_id'] ?? null, $urgentAccounts, true))
            ));
        }
```

Also fix the FSCS suppression that matches on `account_name`: `evaluateFSCSBreach` and `evaluateFSCSApproaching` must set `$rec['institution_group']` from the exposure entry they iterate, and the resolver plucks and compares `institution_group` instead of `account_name` (finding F17, reported by the engines explorer at `:3506`, `:3519`, `:1676-1686`).

- [ ] **Step 8: Update the seeder rows**

In `database/seeders/SavingsActionDefinitionSeeder.php`:

1. Move the five goal rows to `'source' => 'agent'` (`goal_no_linked_account`, `goal_off_track`, `goal_nearly_achieved`, `goal_wrong_account_type`, `goal_multi_account_rebalance`); their conditions are unchanged. Add `'min_monthly_contribution' => 25` to `regular_saver_opportunity`'s `trigger_config`, and change its `action_template` to `'Consider moving the monthly contribution into a {product}.{wrapper_note}'` so a Cash ISA is only ever pointed at a regular saver ISA. Every existing row stays enabled: the eight that had no evaluator get one in Task 3. `emergency_fund_no_data` and `missing_expenditure` both describe a missing expenditure figure; the conflict resolver (Step 7) keeps the emergency-fund one when the user holds savings, because that is the consequence the user needs to hear.
2. Add these rows (all `'source' => 'agent'`, `'is_enabled' => true`, `'scope' => 'portfolio'` unless stated):

```php
            [
                'key' => 'isa_allowance_remaining',
                'source' => 'agent',
                'title_template' => 'Use Your Remaining ISA Allowance',
                'description_template' => 'You have {isa_remaining} of ISA allowance left this tax year and your emergency fund is in place.',
                'action_template' => 'Consider moving surplus savings into an ISA before the tax year ends.',
                'category' => 'Tax Efficiency',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => ['condition' => 'isa_remaining_and_runway_above', 'threshold' => 6],
                'is_enabled' => true,
                'sort_order' => 27,
                'notes' => 'Savings owns this nudge; the Investment row of the same key is disabled at the launch gate.',
            ],
            [
                'key' => 'zero_rate_account',
                'source' => 'agent',
                'title_template' => 'Your {account_name} Earns No Interest',
                'description_template' => '{account_name} holds {balance} at 0% interest.',
                'action_template' => 'Move the balance to an account that pays interest, keeping any amount you need on instant access.',
                'category' => 'Rate Optimisation',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'default',
                'trigger_config' => ['condition' => 'account_rate_is_zero'],
                'is_enabled' => true,
                'sort_order' => 46,
                'notes' => 'Triggers per account with a recorded balance above zero and a zero rate.',
            ],
            [
                'key' => 'emergency_fund_no_designated',
                'source' => 'agent',
                'title_template' => 'Designate an Emergency Fund Account',
                'description_template' => 'None of your savings accounts is marked as your emergency fund.',
                'action_template' => 'Mark the account you would draw on first as your emergency fund so Fynla can track it.',
                'category' => 'Emergency Fund',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => ['condition' => 'no_designated_emergency_fund'],
                'is_enabled' => true,
                'sort_order' => 24,
                'notes' => 'A designation, not a definition (W-0495): runway is still computed from all cash.',
            ],
            [
                'key' => 'psa_headroom_available',
                'source' => 'agent',
                'title_template' => 'Room Left in Your Personal Savings Allowance',
                'description_template' => 'You are using {utilisation}% of your Personal Savings Allowance.',
                'action_template' => 'You could hold more in interest-paying accounts before any tax is due on the interest.',
                'category' => 'Tax Efficiency',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => ['condition' => 'psa_headroom_above', 'headroom_threshold' => 50],
                'is_enabled' => true,
                'sort_order' => 33,
                'notes' => 'Triggers when more than headroom_threshold per cent of the allowance is unused.',
            ],
            [
                'key' => 'child_no_savings',
                'source' => 'agent',
                'title_template' => 'No Savings Recorded for {child_name}',
                'description_template' => '{child_name} has no savings account linked to them.',
                'action_template' => 'Consider opening a savings account or Junior ISA for {child_name}.',
                'category' => "Children's Savings",
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => ['condition' => 'child_under_18_no_savings'],
                'is_enabled' => true,
                'sort_order' => 70,
                'notes' => 'Per dependent child under 18 with no linked account.',
            ],
            [
                'key' => 'goal_no_contribution',
                'source' => 'agent',
                'title_template' => "'{goal_name}' Has No Monthly Contribution",
                'description_template' => "'{goal_name}' needs {required_monthly} a month to reach its target on time, but nothing is being paid in.",
                'action_template' => 'Set up a monthly contribution towards this goal.',
                'category' => 'Goal',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => ['condition' => 'goal_no_monthly_contribution'],
                'is_enabled' => true,
                'sort_order' => 60,
                'notes' => 'Portfolio-level: one recommendation per active savings goal with zero contribution.',
            ],
            [
                'key' => 'goal_underfunded',
                'source' => 'agent',
                'title_template' => "'{goal_name}' Is Underfunded",
                'description_template' => "'{goal_name}' is at {progress}% of its target.",
                'action_template' => 'Review the target and the contribution for this goal.',
                'category' => 'Goal',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => ['condition' => 'goal_underfunded'],
                'is_enabled' => true,
                'sort_order' => 61,
                'notes' => 'Portfolio-level.',
            ],
            [
                'key' => 'goal_deadline_approaching',
                'source' => 'agent',
                'title_template' => "'{goal_name}' Deadline Is Approaching",
                'description_template' => "'{goal_name}' has {months_remaining} months left and is at {progress}% of its target.",
                'action_template' => 'Increase the contribution or move the target date.',
                'category' => 'Goal',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => ['condition' => 'goal_months_remaining_below_and_progress_below', 'months_threshold' => 6, 'progress_threshold' => 75],
                'is_enabled' => true,
                'sort_order' => 62,
                'notes' => 'Portfolio-level.',
            ],
            [
                'key' => 'create_emergency_fund_goal',
                'source' => 'agent',
                'title_template' => 'Create an emergency fund goal',
                'description_template' => 'You have {runway_months} months of emergency savings. Consider creating an emergency fund goal of {target_amount} to cover {target_months} months of expenses.',
                'action_template' => 'Create emergency fund goal',
                'category' => 'Goal',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => ['condition' => 'no_emergency_fund_goal_and_runway_below', 'threshold' => 3],
                'is_enabled' => true,
                'sort_order' => 25,
                'notes' => 'Moved from SavingsAgent::buildGoalRecommendations (fyn-wiring Batch A). Fyn proposes the goal; the write goes through delegate_to_capture → create_goal.',
            ],
            [
                'key' => 'life_event_cash_buffer',
                'source' => 'agent',
                'title_template' => 'Build cash reserve for {event_name}',
                'description_template' => '{event_name} is expected in {months_until} months costing {amount}. Consider saving {monthly_saving} per month to prepare.',
                'action_template' => 'Set up savings for upcoming event',
                'category' => 'Goal',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => ['condition' => 'expense_life_event_within', 'months_threshold' => 12],
                'is_enabled' => true,
                'sort_order' => 26,
                'notes' => 'Moved from SavingsAgent::buildGoalRecommendations (fyn-wiring Batch A).',
            ],
```

Confirm the model template placeholders used by the row exist in the evaluator's `$vars` (Task 3 sets them).

- [ ] **Step 9: Reseed and run the two test files**

Run: `php artisan db:seed --class=SavingsActionDefinitionSeeder --force && ./vendor/bin/pest tests/Unit/Services/Savings/SavingsActionDefinitionServiceTest.php tests/Feature/Database/ActionDefinitionDispatchCoverageTest.php`
Expected: the emergency fund, maturity and rate families PASS. The dispatch-coverage Savings cases still FAIL until Task 3 adds the eleven new evaluators (`emergency_fund_expenditure_missing`, `no_emergency_fund_goal_and_runway_below`, `expense_life_event_within`, `goal_progress_above`, `additional_rate_taxpayer_with_taxable_savings`, `eligible_for_starting_rate`, `has_easy_access_with_regular_contributions`, `goal_account_type_mismatch`, `multiple_goals_sharing_account`, `child_has_cash_jisa_with_long_horizon`, `child_parental_interest_above`).

- [ ] **Step 10: Commit**

```bash
./vendor/bin/pint app/Services/Savings/SavingsActionDefinitionService.php database/seeders/SavingsActionDefinitionSeeder.php
git add app/Services/Savings/SavingsActionDefinitionService.php database/seeders/SavingsActionDefinitionSeeder.php tests/Unit/Services/Savings/SavingsActionDefinitionServiceTest.php
git commit -m "fix(savings): dispatch action definitions on the seeded condition, read thresholds from the row

The dispatcher switched on trigger_config.condition but every arm was named
after the row key, so no savings rule has fired since 2026-03-14 (F0).

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FSgisEQqTaU43JisiH7J9z"
```

---

### Task 3: The eleven new evaluators and the goal consolidation

**Files:**
- Modify: `app/Services/Savings/SavingsActionDefinitionService.php` (constructor gains `private readonly TaxStrategyMath $taxMath`; add eleven methods; delete `evaluateGoalActions` `:98-115`, `evaluateGoalTrigger` `:3156-3172`, `evaluateLinkedGoalNoContribution`, `evaluateLinkedGoalOffTrack`, `evaluateLinkedGoalDeadline`, `evaluateLinkedGoalUnderfunded`, `evaluateLinkedGoalNearlyComplete` `:3174-3460`).
- Modify: `app/Agents/SavingsAgent.php:285-330` (`generateRecommendations`), delete `buildGoalRecommendations` `:610-689`.
- Modify: `app/Services/Plans/SavingsPlanService.php:52-58`.
- Test: `tests/Unit/Agents/SavingsAgentGoalsTest.php` (existing, must stay green), `tests/Unit/Services/Plans/GoalIntegrationTest.php` (existing).

**Interfaces:**
- Consumes: `Goal::forUserOrJoint(int)` query scope; `Goal` columns `linked_savings_account_id`, `target_date`, `current_amount`, `target_amount`, accessors `is_on_track`, `progress_percentage`, `required_monthly_contribution`, `months_remaining`; `LifeEvent::forUserOrJoint(int)` with `->active()`; `PSACalculator::assessPSAPosition(User): array{tax_band: string, annual_interest: float, psa_amount: float, ...}`; `TaxStrategyMath::nonSavingsIncomeFor(User): float`, `personalAllowanceFor(User): float`, `estimateAnnualInterest(User): float`; `TaxConfigService::getIncomeTax()['starting_rate_for_savings']['band']`; savings account columns `access_type`, `notice_period_days`, `maturity_date`, `regular_contribution_amount`, `contribution_frequency`, `is_isa`, `isa_type` (`junior_isa`), `beneficiary_id` (family member); `DependantsReach::householdFamilyOf(User, ['child'])`.
- Produces: recommendations with `definition_key` in {`create_emergency_fund_goal`, `life_event_cash_buffer`, `goal_nearly_achieved`, `goal_wrong_account_type`, `goal_multi_account_rebalance`, `emergency_fund_no_data`, `psa_additional_rate`, `starting_rate_unused`, `regular_saver_opportunity`, `child_jisa_cash_vs_ss`, `child_parental_settlement`}.

- [ ] **Step 1: Confirm the existing goal tests are red after Task 2**

Run: `./vendor/bin/pest tests/Unit/Agents/SavingsAgentGoalsTest.php`
Expected: still PASS (the inline builder is untouched so far). They become the regression net for this task.

- [ ] **Step 2: Add the eleven evaluators**

Add `use App\Models\LifeEvent;`, `use App\Models\Goal;` (already present) and `use App\Services\Tax\TaxStrategyMath;` to the imports, add `private readonly TaxStrategyMath $taxMath` as the last constructor parameter, then insert after `evaluateGoalDeadlineApproaching`:

```php
    /**
     * Fyn proposes an emergency fund goal when none exists and the runway is
     * short. Moved from SavingsAgent::buildGoalRecommendations so all three
     * consumers see it (Rule 20).
     */
    private function evaluateEmergencyFundGoalSuggested(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        int $userId,
        array $config,
        int $priority
    ): array {
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? null;
        if ($runway === null) {
            return []; // W-0495: unmeasured is not zero.
        }

        $threshold = (float) ($config['threshold'] ?? 3);
        if ($runway >= $threshold) {
            return [];
        }

        $hasGoal = Goal::forUserOrJoint($userId)
            ->where('goal_type', 'emergency_fund')
            ->where('status', 'active')
            ->exists();
        if ($hasGoal) {
            return [];
        }

        $monthlyExpenditure = (float) ($savingsAnalysis['summary']['monthly_expenditure'] ?? 0);
        $user = User::find($userId);
        $targetMonths = $this->emergencyFundCalculator->getTargetMonths($user?->employment_status);
        $targetAmount = $monthlyExpenditure * $targetMonths;
        if ($targetAmount <= 0) {
            return [];
        }

        $rec = $this->buildRecommendation($definition, [
            'runway_months' => number_format($runway, 1),
            'target_months' => (string) $targetMonths,
            'target_amount' => $this->formatCurrency($targetAmount),
        ], $priority);
        $rec['estimated_impact'] = round($targetAmount, 2);
        $rec['decision_trace'] = [[
            'question' => 'Is there an active emergency fund goal?',
            'data_field' => 'goals.emergency_fund',
            'data_value' => 'none',
            'threshold' => 'Runway below '.number_format($threshold, 1).' months',
            'passed' => true,
            'explanation' => 'Runway is '.number_format($runway, 1).' months and no emergency fund goal exists. A goal of £'.number_format($targetAmount, 0).' covers '.$targetMonths.' months of £'.number_format($monthlyExpenditure, 0).' expenditure.',
        ]];

        return [$rec];
    }

    /**
     * Upcoming expense life events inside the seeded window need a cash buffer.
     * Moved from SavingsAgent::buildGoalRecommendations (Rule 20).
     */
    private function evaluateLifeEventCashBuffer(
        SavingsActionDefinition $definition,
        int $userId,
        array $config,
        int $priority
    ): array {
        $months = (int) ($config['months_threshold'] ?? 12);
        $events = LifeEvent::forUserOrJoint($userId)
            ->where('impact_type', 'expense')
            ->where('expected_date', '>', now())
            ->where('expected_date', '<=', now()->addMonths($months))
            ->whereIn('certainty', ['confirmed', 'likely'])
            ->active()
            ->get();

        $results = [];
        foreach ($events as $event) {
            $monthsUntil = max(1, (int) now()->diffInMonths($event->expected_date));
            $monthlySaving = round((float) $event->amount / $monthsUntil, 2);

            $rec = $this->buildRecommendation($definition, [
                'event_name' => (string) $event->event_name,
                'months_until' => (string) $monthsUntil,
                'amount' => $this->formatCurrency((float) $event->amount),
                'monthly_saving' => $this->formatCurrency($monthlySaving),
            ], $priority);
            $rec['scope'] = 'life_event';
            $rec['life_event_id'] = $event->id;
            $rec['estimated_impact'] = round((float) $event->amount, 2);
            $rec['decision_trace'] = [[
                'question' => 'Which expense life events fall inside the window?',
                'data_field' => 'life_events.expected_date',
                'data_value' => $event->event_name.' in '.$monthsUntil.' months',
                'threshold' => 'Within '.$months.' months, certainty confirmed or likely',
                'passed' => true,
                'explanation' => '£'.number_format((float) $event->amount, 0).' ÷ '.$monthsUntil.' months = £'.number_format($monthlySaving, 0).' per month to be ready.',
            ]];
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Goal nearly achieved: portfolio-level counterpart of the old linked-goal
     * evaluator, so it runs for every consumer without a plan-page formatter.
     */
    private function evaluateGoalNearlyAchieved(
        SavingsActionDefinition $definition,
        int $userId,
        array $config,
        int $priority
    ): array {
        $threshold = (float) ($config['threshold'] ?? 90);
        $goals = Goal::forUserOrJoint($userId)
            ->where('assigned_module', 'savings')
            ->where('status', 'active')
            ->get();

        $results = [];
        foreach ($goals as $goal) {
            $progress = (float) $goal->progress_percentage;
            if ($progress < $threshold || $progress >= 100) {
                continue;
            }
            $remaining = max(0, (float) $goal->target_amount - (float) $goal->current_amount);

            $rec = $this->buildRecommendation($definition, [
                'goal_name' => (string) ($goal->goal_name ?? 'Unnamed goal'),
                'progress' => number_format($progress, 0),
                'remaining' => $this->formatCurrency($remaining),
            ], $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $rec['decision_trace'] = [[
                'question' => 'Which goal is nearly complete?',
                'data_field' => 'goals.progress_percentage',
                'data_value' => number_format($progress, 0).'%',
                'threshold' => 'Above '.number_format($threshold, 0).'%',
                'passed' => true,
                'explanation' => '£'.number_format($remaining, 0).' remains to reach £'.number_format((float) $goal->target_amount, 0).'.',
            ]];
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Additional-rate taxpayers have no Personal Savings Allowance, so any
     * taxable interest is taxed in full.
     */
    private function evaluatePsaAdditionalRate(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $psa = $this->psaCalculator->assessPSAPosition($user);
        if (($psa['tax_band'] ?? '') !== 'additional' || (float) ($psa['annual_interest'] ?? 0) <= 0) {
            return [];
        }

        $rec = $this->buildRecommendation($definition, [
            'annual_interest' => $this->formatCurrency((float) $psa['annual_interest']),
        ], $priority);
        $rec['estimated_impact'] = round((float) $psa['annual_interest'], 2);
        $rec['decision_trace'] = [
            $this->buildUserProfileTrace($user),
            [
                'question' => 'Does the user have a Personal Savings Allowance?',
                'data_field' => 'psa_position.tax_band',
                'data_value' => 'additional rate',
                'threshold' => 'Additional-rate taxpayers have no allowance',
                'passed' => true,
                'explanation' => '£'.number_format((float) $psa['annual_interest'], 0).' of taxable interest a year is taxed in full. A Cash ISA shelters interest entirely.',
            ],
        ];

        return [$rec];
    }

    /**
     * Emergency fund cannot be assessed: the user holds savings but no
     * expenditure figure, so no runway can be stated (W-0495). The generic
     * `missing_expenditure` row is suppressed in favour of this one.
     */
    private function evaluateEmergencyFundNoData(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        Collection $savingsAccounts,
        int $userId,
        int $priority
    ): array {
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? null;
        if ($runway !== null || $savingsAccounts->isEmpty()) {
            return [];
        }

        $user = User::find($userId);
        $totalCash = (float) $savingsAccounts->sum('current_balance');
        $targetMonths = $this->emergencyFundCalculator->getTargetMonths($user?->employment_status);

        $rec = $this->buildRecommendation($definition, [
            'total_cash' => $this->formatCurrency($totalCash),
            'target_months' => (string) $targetMonths,
        ], $priority);
        $rec['decision_trace'] = [[
            'question' => 'Can the emergency fund runway be measured?',
            'data_field' => 'summary.monthly_expenditure',
            'data_value' => 'not recorded',
            'threshold' => 'A monthly expenditure figure above £0',
            'passed' => true,
            'explanation' => '£'.number_format($totalCash, 0).' of cash is recorded but no monthly expenditure, so the '.$targetMonths.'-month target cannot be sized. Runway = cash ÷ monthly expenditure.',
        ]];

        return [$rec];
    }

    /**
     * Starting rate for savings: up to the configured band of interest is
     * taxed at 0% when non-savings income sits within the Personal Allowance,
     * tapering £ for £ above it. Same arithmetic as TaxStrategyCalculator:153-159.
     */
    private function evaluateStartingRateUnused(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $income = $this->taxConfig->getIncomeTax();
        $band = (float) ($income['starting_rate_for_savings']['band'] ?? 0);
        if ($band <= 0) {
            return [];
        }

        $nonSavingsIncome = $this->taxMath->nonSavingsIncomeFor($user);
        $personalAllowance = $this->taxMath->personalAllowanceFor($user);
        $available = max(0.0, $band - max(0.0, $nonSavingsIncome - $personalAllowance));
        if ($available <= 0) {
            return [];
        }

        $annualInterest = $this->taxMath->estimateAnnualInterest($user);
        $unused = max(0.0, $available - $annualInterest);
        if ($unused <= 0) {
            return [];
        }

        $rec = $this->buildRecommendation($definition, [
            'available' => $this->formatCurrency($available),
            'unused' => $this->formatCurrency($unused),
            'annual_interest' => $this->formatCurrency($annualInterest),
        ], $priority);
        $rec['estimated_impact'] = round($unused, 2);
        $rec['decision_trace'] = [
            $this->buildUserProfileTrace($user),
            [
                'question' => 'Does the starting rate for savings apply?',
                'data_field' => 'income.non_savings',
                'data_value' => '£'.number_format($nonSavingsIncome, 0).' non-savings income',
                'threshold' => 'Band £'.number_format($band, 0).' less non-savings income above the £'.number_format($personalAllowance, 0).' Personal Allowance',
                'passed' => true,
                'explanation' => '£'.number_format($available, 0).' of interest can be earned at 0%; £'.number_format($annualInterest, 0).' is currently earned, leaving £'.number_format($unused, 0).' unused.',
            ],
        ];

        return [$rec];
    }

    /**
     * Regular saver opportunity: an easy-access account receiving a regular
     * monthly contribution at or above the seeded minimum could earn a
     * regular-saver rate on those contributions. ISA interest is tax-free, so
     * for a Cash ISA the only product suggested is a regular saver ISA: the
     * money must never be moved out of the ISA wrapper into taxable interest.
     */
    private function evaluateRegularSaverOpportunity(
        SavingsActionDefinition $definition,
        Collection $savingsAccounts,
        array $config,
        int $priority
    ): array {
        $minimum = (float) ($config['min_monthly_contribution'] ?? 25);
        $results = [];

        foreach ($savingsAccounts as $account) {
            if ($account->access_type !== 'immediate') {
                continue;
            }
            $monthly = match ((string) ($account->contribution_frequency ?? '')) {
                'monthly' => (float) ($account->regular_contribution_amount ?? 0),
                'weekly' => (float) ($account->regular_contribution_amount ?? 0) * 52 / 12,
                'annually', 'yearly' => (float) ($account->regular_contribution_amount ?? 0) / 12,
                default => 0.0,
            };
            if ($monthly < $minimum) {
                continue;
            }

            $isIsa = (bool) $account->is_isa;
            $rec = $this->buildRecommendation($definition, [
                'account_name' => (string) ($account->account_name ?? 'Unnamed account'),
                'monthly_contribution' => $this->formatCurrency($monthly),
                'current_rate' => number_format((float) $account->interest_rate, 2),
                'product' => $isIsa ? 'regular saver ISA' : 'regular saver account',
                'wrapper_note' => $isIsa ? ' Keep the money inside the ISA so the interest stays tax-free.' : '',
            ], $priority);
            $rec['keeps_isa_wrapper'] = $isIsa;
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $rec['estimated_impact'] = round($monthly * 12, 2);
            $rec['decision_trace'] = [[
                'question' => 'Is a regular contribution going into an easy-access account?',
                'data_field' => 'regular_contribution_amount',
                'data_value' => '£'.number_format($monthly, 0).' a month into '.($account->account_name ?? 'the account'),
                'threshold' => 'At least £'.number_format($minimum, 0).' a month',
                'passed' => true,
                'explanation' => ($isIsa ? 'A regular saver ISA' : 'A regular saver account').' pays a higher rate on monthly deposits than an easy-access '.($isIsa ? 'Cash ISA' : 'account').'; £'.number_format($monthly * 12, 0).' a year is being paid in at '.number_format((float) $account->interest_rate, 2).'%.'.($isIsa ? ' The interest is tax-free inside the ISA and must stay there.' : ''),
            ]];
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Goal in the wrong account type: the linked account locks the money
     * past the goal's target date (fixed term maturing after it, or a notice
     * period longer than the time left).
     */
    private function evaluateGoalWrongAccountType(
        SavingsActionDefinition $definition,
        int $userId,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $goals = Goal::forUserOrJoint($userId)
            ->where('status', 'active')
            ->whereNotNull('linked_savings_account_id')
            ->whereNotNull('target_date')
            ->get();

        $results = [];
        foreach ($goals as $goal) {
            $account = $savingsAccounts->firstWhere('id', $goal->linked_savings_account_id);
            if (! $account) {
                continue;
            }
            $targetDate = Carbon::parse($goal->target_date);
            $daysToTarget = (int) now()->diffInDays($targetDate, false);
            $reason = null;
            if ($account->access_type === 'fixed' && $account->maturity_date && Carbon::parse($account->maturity_date)->gt($targetDate)) {
                $reason = 'fixed until '.Carbon::parse($account->maturity_date)->format('d M Y').', after the goal date';
            } elseif ($account->access_type === 'notice' && (int) ($account->notice_period_days ?? 0) > max(0, $daysToTarget)) {
                $reason = ($account->notice_period_days).'-day notice period, longer than the '.max(0, $daysToTarget).' days left';
            }
            if ($reason === null) {
                continue;
            }

            $rec = $this->buildRecommendation($definition, [
                'goal_name' => (string) ($goal->goal_name ?? 'Unnamed goal'),
                'account_name' => (string) ($account->account_name ?? 'Unnamed account'),
                'reason' => $reason,
                'target_date' => $targetDate->format('d M Y'),
            ], $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $rec['account_id'] = $account->id;
            $rec['decision_trace'] = [[
                'question' => 'Will the linked account release the money in time?',
                'data_field' => 'access_type / maturity_date / notice_period_days',
                'data_value' => $reason,
                'threshold' => 'Accessible on or before '.$targetDate->format('d M Y'),
                'passed' => true,
                'explanation' => '"'.($goal->goal_name ?? 'Goal').'" is due '.$targetDate->format('d M Y').' but '.($account->account_name ?? 'the account').' is '.$reason.'.',
            ]];
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Several goals sharing one account: fine in itself, a problem when the
     * amounts counted towards those goals exceed what the account holds.
     */
    private function evaluateGoalMultiAccountRebalance(
        SavingsActionDefinition $definition,
        int $userId,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $byAccount = Goal::forUserOrJoint($userId)
            ->where('status', 'active')
            ->whereNotNull('linked_savings_account_id')
            ->get()
            ->groupBy('linked_savings_account_id')
            ->filter(fn ($goals) => $goals->count() > 1);

        $results = [];
        foreach ($byAccount as $accountId => $goals) {
            $account = $savingsAccounts->firstWhere('id', $accountId);
            if (! $account) {
                continue;
            }
            $allocated = (float) $goals->sum('current_amount');
            $balance = (float) ($account->current_balance ?? 0);
            if ($allocated <= $balance) {
                continue;
            }

            $rec = $this->buildRecommendation($definition, [
                'account_name' => (string) ($account->account_name ?? 'Unnamed account'),
                'goal_count' => (string) $goals->count(),
                'allocated' => $this->formatCurrency($allocated),
                'balance' => $this->formatCurrency($balance),
                'shortfall' => $this->formatCurrency($allocated - $balance),
            ], $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $rec['estimated_impact'] = round($allocated - $balance, 2);
            $rec['decision_trace'] = [[
                'question' => 'Do the goals sharing this account claim more than it holds?',
                'data_field' => 'goals.current_amount (sum) vs savings_accounts.current_balance',
                'data_value' => '£'.number_format($allocated, 0).' counted across '.$goals->count().' goals; £'.number_format($balance, 0).' in the account',
                'threshold' => 'Allocated amounts at or below the balance',
                'passed' => true,
                'explanation' => 'The goals "'.$goals->pluck('goal_name')->implode('", "').'" together count £'.number_format($allocated, 0).' but the account holds £'.number_format($balance, 0).'; £'.number_format($allocated - $balance, 0).' is double-counted.',
            ]];
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Cash Junior ISA with a long horizon: a savings-account JISA is a Cash
     * JISA by definition (Stocks and Shares JISAs live in investment
     * accounts). Beyond the seeded horizon, growth assets are worth considering.
     */
    private function evaluateChildJisaCashVsStocks(
        SavingsActionDefinition $definition,
        int $userId,
        Collection $savingsAccounts,
        array $config,
        int $priority
    ): array {
        $years = (int) ($config['years_threshold'] ?? 5);
        $children = $this->dependantsReach
            ->householdFamilyOf(User::findOrFail($userId), ['child'])
            ->where('is_dependent', true)
            ->filter(fn ($child) => $child->date_of_birth !== null);

        $results = [];
        foreach ($children as $child) {
            $yearsTo18 = 18 - (int) Carbon::parse($child->date_of_birth)->age;
            if ($yearsTo18 <= $years) {
                continue;
            }
            $cashJisas = $savingsAccounts->filter(fn ($a) => (bool) $a->is_isa && $a->isa_type === 'junior_isa' && (int) $a->beneficiary_id === (int) $child->id);
            if ($cashJisas->isEmpty()) {
                continue;
            }
            $balance = (float) $cashJisas->sum('current_balance');

            $rec = $this->buildRecommendation($definition, [
                'child_name' => (string) ($child->first_name ?? 'your child'),
                'years_to_18' => (string) $yearsTo18,
                'balance' => $this->formatCurrency($balance),
            ], $priority);
            $rec['scope'] = 'child';
            $rec['family_member_id'] = $child->id;
            $rec['decision_trace'] = [[
                'question' => 'How long until the Cash Junior ISA is accessible?',
                'data_field' => 'family_members.date_of_birth',
                'data_value' => $yearsTo18.' years to 18',
                'threshold' => 'More than '.$years.' years',
                'passed' => true,
                'explanation' => '£'.number_format($balance, 0).' is held in a Cash Junior ISA for '.($child->first_name ?? 'your child').' with '.$yearsTo18.' years to go. Over that horizon a Stocks and Shares Junior ISA has historically outpaced cash; the value can fall as well as rise.',
            ]];
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Parental settlement rule (HMRC): when a parent gives a child money and
     * the interest on it exceeds the threshold in a tax year, that interest is
     * taxed as the parent's. Junior ISA interest is tax-free and HMRC excludes
     * it from this rule, so only the child's non-ISA accounts are summed.
     * Who funded the account is not recorded, so the copy says "may apply".
     */
    private function evaluateChildParentalSettlement(
        SavingsActionDefinition $definition,
        int $userId,
        Collection $savingsAccounts,
        array $config,
        int $priority
    ): array {
        $threshold = (float) ($config['threshold'] ?? 100);
        $children = $this->dependantsReach
            ->householdFamilyOf(User::findOrFail($userId), ['child'])
            ->where('is_dependent', true);

        $results = [];
        foreach ($children as $child) {
            $accounts = $savingsAccounts->filter(fn ($a) => ! (bool) $a->is_isa && (int) $a->beneficiary_id === (int) $child->id);
            if ($accounts->isEmpty()) {
                continue;
            }
            $annualInterest = (float) $accounts->sum(fn ($a) => $a->annual_interest);
            if ($annualInterest <= $threshold) {
                continue;
            }

            $rec = $this->buildRecommendation($definition, [
                'child_name' => (string) ($child->first_name ?? 'your child'),
                'annual_interest' => $this->formatCurrency($annualInterest),
                'threshold' => $this->formatCurrency($threshold),
            ], $priority);
            $rec['scope'] = 'child';
            $rec['family_member_id'] = $child->id;
            $rec['estimated_impact'] = round($annualInterest, 2);
            $rec['decision_trace'] = [[
                'question' => 'Does interest on the child\'s non-ISA savings exceed the parental settlement threshold?',
                'data_field' => 'savings_accounts.annual_interest (non-ISA, beneficiary = child)',
                'data_value' => '£'.number_format($annualInterest, 0).' a year',
                'threshold' => 'Above £'.number_format($threshold, 0),
                'passed' => true,
                'explanation' => 'If the money was gifted by a parent, interest above £'.number_format($threshold, 0).' a year is taxed as the parent\'s income. Interest inside a Junior ISA is tax-free and does not count towards this rule.',
            ]];
            $results[] = $rec;
        }

        return $results;
    }
```

Check the seeded `title_template` / `description_template` placeholders of each of the eight rows against the `$vars` above and add any placeholder the row uses that the evaluator does not set (the seeder is the source of the copy; adjust the evaluator, not the copy). Check `PSACalculator::assessPSAPosition` returns `tax_band` and `annual_interest`; if the keys are named differently at `app/Services/Savings/PSACalculator.php:23-64`, use those names.

- [ ] **Step 3: Delete the duplicate goal family**

Delete `evaluateGoalActions` (`:98-115`), `evaluateGoalTrigger` (`:3156-3172`) and the five `evaluateLinkedGoal*` methods (`:3174-3460`). Keep `getWhatIfImpactType`.

- [ ] **Step 4: Make the agent delegate entirely to the service**

Replace `SavingsAgent::generateRecommendations` (`:285-330`) body's DB-driven branch with:

```php
            if ($this->actionDefinitionService) {
                $userId = (int) ($analysisData['user_id'] ?? 0);

                $savingsAccounts = $userId > 0 && ($savingsUser = User::find($userId))
                    ? app(SavingsStore::class)->forUser($savingsUser)
                    : collect();
                $investmentAccounts = $userId > 0
                    ? InvestmentAccount::forUserOrJoint($userId)->get()
                    : collect();

                $result = $this->actionDefinitionService->evaluateAgentActions(
                    $analysisData,
                    $analysisData['investment_analysis'] ?? [],
                    $savingsAccounts,
                    $investmentAccounts,
                    $userId
                );

                // Goal-category recommendations lead, as the plan page has always ordered them.
                $recs = $result['recommendations'] ?? [];
                usort($recs, fn (array $a, array $b): int => (($b['category'] ?? '') === 'Goal') <=> (($a['category'] ?? '') === 'Goal'));

                return $recs;
            }
```

Delete `buildGoalRecommendations` (`:610-689`) and the now-unused `Goal`/`LifeEvent` imports if nothing else in the file uses them.

- [ ] **Step 5: Stop the plan page calling the deleted goal evaluator**

Replace `SavingsPlanService.php:52-58`:

```php
        // 4. Goal-category recommendations come from the same catalogue; the
        //    agent already orders them first (Task 3 of fyn-wiring Batch A).
        $allRecs = $recommendations;
```

and delete the `array_merge($goalRecommendations, $recommendations)` line. Confirm `getRecommendations()` (`:89-107`) still calls `evaluateAgentActions` and now returns goal rows too.

- [ ] **Step 6: Run the goal tests and the two engine test files**

Run: `php artisan db:seed --class=SavingsActionDefinitionSeeder --force && ./vendor/bin/pest tests/Unit/Agents/SavingsAgentGoalsTest.php tests/Unit/Services/Plans/GoalIntegrationTest.php tests/Unit/Services/Savings/SavingsActionDefinitionServiceTest.php tests/Feature/Database/ActionDefinitionDispatchCoverageTest.php`
Expected: all PASS. `SavingsAgentGoalsTest` still finds "Holiday Fund" (now from `goal_off_track` via `evaluateGoalOffTrack`) and "emergency fund goal" (now from `create_emergency_fund_goal`). If the "Holiday Fund" case fails, the cause is `evaluateGoalOffTrack` querying `Goal::where('user_id', …)` rather than `forUserOrJoint`; change that query to `Goal::forUserOrJoint($userId)` (joint goals were invisible to it, the same W-0238 shape as savings accounts).

- [ ] **Step 7: Commit**

```bash
./vendor/bin/pint app/Services/Savings/SavingsActionDefinitionService.php app/Agents/SavingsAgent.php app/Services/Plans/SavingsPlanService.php
git add app/Services/Savings/SavingsActionDefinitionService.php app/Agents/SavingsAgent.php app/Services/Plans/SavingsPlanService.php database/seeders/SavingsActionDefinitionSeeder.php
git commit -m "refactor(savings): one goal mechanism — seeded rows evaluated by the action definition service

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FSgisEQqTaU43JisiH7J9z"
```

---

### Task 4: `user_id` travels with the analysis on every path

**Files:**
- Modify: `app/Agents/SavingsAgent.php:180-187` (the `'summary' => [` block inside `analyze()`).
- Modify: `app/Services/Coordination/PlanSources/SavingsStrategySource.php:36-40`.
- Test: `tests/Unit/Agents/SavingsAgentTest.php` (`returns expected structure`, `:50`).

**Interfaces:**
- Produces: `SavingsAgent::analyze(int $userId): array` now includes top-level `'user_id' => int`.

- [ ] **Step 1: Add the assertion to the existing structure test**

In `tests/Unit/Agents/SavingsAgentTest.php` inside `it('returns expected structure', …)` add after the existing expectations:

```php
        expect($result['user_id'])->toBe($user->id);
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Agents/SavingsAgentTest.php --filter="returns expected structure"`
Expected: FAIL, undefined key `user_id`.

- [ ] **Step 3: Carry the id on the analysis**

In `SavingsAgent::analyze`, in the returned array (`:180`), add as the first entry:

```php
                return [
                    'user_id' => $userId,
                    'summary' => [
```

In `SavingsStrategySource.php:36-40` delete the two lines `$data = $analysis['data'] ?? $analysis;` and `$data['user_id'] = $user->id;` and the comment above them, and pass `$analysis` straight to `generateRecommendations`. `CoordinatingAgent.php:525` needs no change: it already passes the analysis through.

- [ ] **Step 4: Run the agent tests, the aggregator tests and the composed-plan tests**

Run: `./vendor/bin/pest tests/Unit/Agents/SavingsAgentTest.php tests/Unit/Agents/SavingsAgentGoalsTest.php tests/Unit/Services/Coordination/ComposedSavingsPlanTest.php tests/Feature/Services/RecommendationsAggregatorComposedModulesTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Agents/SavingsAgent.php app/Services/Coordination/PlanSources/SavingsStrategySource.php tests/Unit/Agents/SavingsAgentTest.php
git commit -m "fix(savings): analysis carries user_id so the Fyn path evaluates the right household

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FSgisEQqTaU43JisiH7J9z"
```

---

### Task 5: Interest-rate basis reconciled in the comparator

**Files:**
- Modify: `app/Services/Savings/RateComparator.php:23-48, :91-101`.
- Test: `tests/Unit/Services/Savings/RateComparatorTest.php` (create).

**Interfaces:**
- Produces: `compareToMarketRates(SavingsAccount): array{account_rate: float, market_rate: float, difference: float, account_rate_percent: float, market_rate_percent: float, difference_percent: float, is_competitive: bool, category: string}`; decimals in the three existing keys (unchanged names, now correct values), percentages in the three new ones. `calculateInterestDifference(SavingsAccount, float $marketRateDecimal): float`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\SavingsMarketRate;
use App\Models\User;
use App\Services\Savings\RateComparator;
use Database\Seeders\SavingsMarketRatesSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(SavingsMarketRatesSeeder::class);
    $this->comparator = app(RateComparator::class);
    $this->user = User::factory()->create();
});

it('treats the percentage column as a percentage against the decimal benchmark', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id, 'access_type' => 'immediate', 'is_isa' => false,
        'current_balance' => 10000, 'interest_rate' => 4.25,
    ]);
    $benchmark = (float) SavingsMarketRate::where('rate_key', 'easy_access')->value('rate'); // 0.0450 seeded

    $result = $this->comparator->compareToMarketRates($account);

    expect($result['account_rate'])->toBe(0.0425)
        ->and($result['account_rate_percent'])->toBe(4.25)
        ->and($result['market_rate'])->toBe(round($benchmark, 4))
        ->and($result['market_rate_percent'])->toBe(round($benchmark * 100, 2))
        ->and($result['difference_percent'])->toBe(round(4.25 - $benchmark * 100, 2))
        ->and($result['category'])->toBe('Fair')
        ->and($result['is_competitive'])->toBeTrue();
});

it('rates a 2.5 per cent easy-access account as Poor against a 4.5 per cent market', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id, 'access_type' => 'immediate', 'is_isa' => false,
        'current_balance' => 10000, 'interest_rate' => 2.5,
    ]);

    expect($this->comparator->compareToMarketRates($account)['category'])->toBe('Poor');
});

it('computes the annual interest difference in pounds from the percentage column', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id, 'access_type' => 'immediate', 'is_isa' => false,
        'current_balance' => 10000, 'interest_rate' => 2.5,
    ]);

    expect($this->comparator->calculateInterestDifference($account, 0.045))->toBe(200.0);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Savings/RateComparatorTest.php`
Expected: FAIL. The first case reports `category` `Excellent` (difference 4.205) and `account_rate` 4.25; the third reports `-24550.0`.

- [ ] **Step 3: Convert at the boundary**

In `compareToMarketRates` replace `$accountRate = (float) $account->interest_rate;` with:

```php
        // savings_accounts.interest_rate holds percentages (4.25 = 4.25%);
        // savings_market_rates.rate holds decimals (0.0450). This method is
        // the one place the two meet — nothing else may do this arithmetic.
        $accountRate = (float) $account->interest_rate / 100;
```

and extend the returned array:

```php
        return [
            'account_rate' => round($accountRate, 4),
            'market_rate' => round($marketRate, 4),
            'difference' => round($difference, 4),
            'account_rate_percent' => round($accountRate * 100, 2),
            'market_rate_percent' => round($marketRate * 100, 2),
            'difference_percent' => round($difference * 100, 2),
            'is_competitive' => $isCompetitive,
            'category' => $category,
        ];
```

In `calculateInterestDifference` replace `$accountRate = (float) $account->interest_rate;` with `$accountRate = (float) $account->interest_rate / 100;`.

- [ ] **Step 4: Run the comparator test, the savings service test and the investment engine test that reads rate comparisons**

Run: `./vendor/bin/pest tests/Unit/Services/Savings/RateComparatorTest.php tests/Unit/Services/Savings/SavingsActionDefinitionServiceTest.php tests/Unit/Services/Investment/InvestmentActionDefinitionServiceTest.php tests/Unit/Agents/SavingsAgentTest.php`
Expected: PASS. `InvestmentActionDefinitionService.php:881-903` reads `category === 'Poor'` from the same output; it is now correct for the first time.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Savings/RateComparator.php tests/Unit/Services/Savings/RateComparatorTest.php
git commit -m "fix(savings): reconcile the percentage rate column with decimal benchmarks in RateComparator (F15)

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FSgisEQqTaU43JisiH7J9z"
```

---

### Task 6: One emergency-fund month table

**Files:**
- Modify: `app/Services/Savings/EmergencyFundCalculator.php:108-126`.
- Modify: `app/Agents/SavingsAgent.php:496-523` (`calculateEmploymentBasedTarget`).
- Modify: `app/Services/Investment/InvestmentActionDefinitionService.php:1006` (comment only).
- Test: `tests/Unit/Services/Savings/EmergencyFundCalculatorTest.php` (add cases).

**Interfaces:**
- Produces: `EmergencyFundCalculator::getTargetMonths(?string $employmentStatus): int` with the canonical table: `employed`, `full_time`, `part_time` → 6; `self_employed`, `freelance`, `contractor` → 9; `retired` → 3; anything else → 6.

- [ ] **Step 1: Add the failing cases**

```php
    describe('getTargetMonths is the one month table', function () {
        it('maps every employment status the users enum allows', function () {
            $calculator = new EmergencyFundCalculator;
            expect($calculator->getTargetMonths('employed'))->toBe(6)
                ->and($calculator->getTargetMonths('full_time'))->toBe(6)
                ->and($calculator->getTargetMonths('part_time'))->toBe(6)
                ->and($calculator->getTargetMonths('self_employed'))->toBe(9)
                ->and($calculator->getTargetMonths('contractor'))->toBe(9)
                ->and($calculator->getTargetMonths('freelance'))->toBe(9)
                ->and($calculator->getTargetMonths('retired'))->toBe(3)
                ->and($calculator->getTargetMonths('unemployed'))->toBe(6)
                ->and($calculator->getTargetMonths(null))->toBe(6);
        });
    });
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Savings/EmergencyFundCalculatorTest.php`
Expected: FAIL on `full_time` (currently falls to default 6, which passes) — if it passes, the only real change is in the agent; proceed.

- [ ] **Step 3: Make the calculator the single table and the agent use it**

Replace the `match` in `EmergencyFundCalculator::getTargetMonths`:

```php
        return match ($employmentStatus) {
            'employed', 'full_time', 'part_time' => 6,
            'self_employed', 'freelance', 'contractor' => 9,
            'retired' => 3,
            default => 6,
        };
```

Replace `SavingsAgent::calculateEmploymentBasedTarget` (`:496-523`) body:

```php
    private function calculateEmploymentBasedTarget(?User $user, float $monthlyExpenditure): array
    {
        $targetMonths = $this->emergencyFundCalculator->getTargetMonths($user?->employment_status);

        return [
            'target_months' => $targetMonths,
            'target_amount' => $this->roundToPenny($monthlyExpenditure * $targetMonths),
            'employment_status' => $user?->employment_status,
            'rationale' => match ($targetMonths) {
                9 => 'Self-employed and contractor income can be irregular, so a larger buffer is recommended.',
                3 => 'A stable pension income needs a smaller buffer than earned income.',
                default => 'The standard recommendation is 6 months of essential expenditure.',
            },
        ];
    }
```

Also replace `$adequacy = $this->emergencyFundCalculator->calculateAdequacy($runway, 6);` in `analyze()` (`:105`) with `calculateAdequacy($runway, $this->emergencyFundCalculator->getTargetMonths($user?->employment_status))` so the adequacy figure on the card uses the same target as the rules. Update the comment at `InvestmentActionDefinitionService.php:1006` to say the savings target comes from `EmergencyFundCalculator::getTargetMonths`.

- [ ] **Step 4: Run the savings test families**

Run: `./vendor/bin/pest tests/Unit/Services/Savings tests/Unit/Agents/SavingsAgentTest.php tests/Unit/Agents/SavingsAgentGoalsTest.php`
Expected: PASS. If a test pinned 12 months for `unemployed`, update it to 6: CSJ's decision of 2026-09-08.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Savings/EmergencyFundCalculator.php app/Agents/SavingsAgent.php app/Services/Investment/InvestmentActionDefinitionService.php tests/Unit/Services/Savings/EmergencyFundCalculatorTest.php
git commit -m "refactor(savings): EmergencyFundCalculator::getTargetMonths is the one month table (3-6-9, retired 3) (F16)

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FSgisEQqTaU43JisiH7J9z"
```

---

### Task 7: State Pension figure from tax configuration, no literal

**Files:**
- Modify: `app/Services/Retirement/RetirementActionDefinitionService.php:981, :1881`.
- Test: `tests/Unit/Services/Retirement/RetirementActionDefinitionServiceTest.php` (add one case).

- [ ] **Step 1: Write the failing test**

```php
it('reads the full new State Pension from tax configuration rather than a literal', function () {
    $source = file_get_contents(app_path('Services/Retirement/RetirementActionDefinitionService.php'));
    expect($source)->not->toContain('11502');
    expect(app(TaxConfigService::class)->get('pension.state_pension.full_new_state_pension'))->toBeGreaterThan(12000);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Retirement/RetirementActionDefinitionServiceTest.php --filter="full new State Pension"`
Expected: FAIL on the `11502` assertion.

- [ ] **Step 3: Remove the literal at both sites**

At `:981` and `:1881` replace `(float) ($this->taxConfig->get('pension.state_pension.full_new_state_pension', 11502))` with:

```php
        $fullStatePension = (float) ($this->taxConfig->get('pension.state_pension.full_new_state_pension') ?? 0);
        if ($fullStatePension <= 0) {
            return []; // Rule 2: no configured figure, no recommendation built on a guess.
        }
```

- [ ] **Step 4: Run the retirement engine tests**

Run: `./vendor/bin/pest tests/Unit/Services/Retirement/RetirementActionDefinitionServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Retirement/RetirementActionDefinitionService.php tests/Unit/Services/Retirement/RetirementActionDefinitionServiceTest.php
git commit -m "fix(retirement): State Pension figure from TaxConfigService, no literal fallback (Rule 2)

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FSgisEQqTaU43JisiH7J9z"
```

---

### Task 8: Consolidated verification, web and `/m`, then Fyn

**Files:** none modified; this task is the Rule 14 and Rule 19 gate.

- [ ] **Step 1: Targeted families, then the full suite once**

Run: `./vendor/bin/pest tests/Unit/Services/Savings tests/Unit/Agents tests/Unit/Services/Coordination tests/Feature/Database tests/Feature/Services tests/Integration/CrossModuleIntegrationTest.php tests/Feature/Fyn/ModuleScopedFinancialContextTest.php`
Expected: PASS. Then `./vendor/bin/pest` once. Expected: green, with only the pre-existing known-red families (the 6 StoreKit native tests are not in this suite).

- [ ] **Step 2: Reseed locally and pick a persona with savings**

Run: `php artisan db:seed --class=SavingsActionDefinitionSeeder --force`. Log in as the peak earners persona from the landing-page persona selector (never a direct URL). David and Sarah hold savings accounts; confirm at least one has a fixed maturity or a below-market rate, otherwise add one through the savings form with a 2.5 per cent easy-access rate and a £10,000 balance.

- [ ] **Step 3: Web verification in Playwright, clicking through**

1. Savings module page: the plan tab lists at least one savings recommendation carrying an emergency fund, rate or ISA key; open its detail and confirm the decision trace shows the runway arithmetic and the seeded threshold, and that the rate reads as a percentage under 20, never as hundreds.
2. Dashboard recommendations card: the same items appear with the same wording.
3. Fyn (advice mode): ask "How is my emergency fund looking?" (classifies `savings_emergency`). In the admin AI audit for that turn, confirm `<financial_context>` contains "Top ranked recommendations" with the savings item and a "Triggered by: emergency_fund_…" line, and that the reply cites the runway in months.
4. Ask "Should I create a goal for my emergency fund?" for a persona with runway under 3 months and no emergency fund goal: Fyn proposes the goal and, on "yes", the write goes through `delegate_to_capture` to `create_goal`, ending with an `entity_created` frame.

- [ ] **Step 4: `/m` verification**

Follow the `verify-m` skill: log in at `/m/app/login` with the tinker MFA code. Check the `/m` dashboard next actions and the savings detail show the same recommendations as web (Rule 19). Record the screens.

- [ ] **Step 5: Report**

Write the evidence (file:line, screenshots, the audit row id) into `September/September8Updates/fyn-wiring-batch-a-evidence.md`, update the artifact's F0, F15, F16, F17 entries to "fixed on branch", and open the PR to `dev` with the checklist above in the body. Do not deploy; CSJ decides.

---

## Self-review

- **Coverage.** F0 both halves: Tasks 2 and 4. F15: Task 5. F16: Task 6. F17 (thresholds, orphans, literal): Tasks 2, 3, 7. Savings half of F3: falls out of Task 2 (`definition_key` on savings rows). CSJ decisions of 2026-09-08: Task 3 (goals in one place; all eight previously unevaluated rows implemented, none disabled), Task 2 Step 8 (four useful orphan evaluators seeded, two deleted), Task 7 (State Pension), Task 6 (3-6-9), Task 5 (comparator).
- **Placeholders.** None: every step carries the code or the exact edit.
- **Type consistency.** `evaluateAgentActions` keeps its five-argument signature; `getTargetMonths(?string): int` is the name used in Tasks 2, 3 and 6; `account_rate_percent` / `market_rate_percent` are the keys produced in Task 5 and consumed in Task 2 Step 5 item 6; `create_emergency_fund_goal` and `life_event_cash_buffer` keep their old category-map entries in `SavingsRecommendationAdapter` valid because `definition_key` now takes precedence there.
- **Out of scope, reported not fixed.** The savings agent's `generateInlineRecommendations` fallback (`SavingsAgent.php:334`) and its tests exercise a path production never takes once the service is injected; delete in a later batch. F2 and F12 (ranking) are Batch B. Who funded a child's account is not recorded anywhere, so `child_parental_settlement` fires on interest alone and its copy says "may apply"; a `funded_by` field on savings accounts would make it exact and is a candidate for a later batch.
