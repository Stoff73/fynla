# SaveTax Phase 4 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Do not skip checkpoints.

**Goal:** Refactor `TaxStrategyCalculator` into per-strategy classes (S-1) and ship two new SaveTax strategies — #3 Pension Annual Allowance Carry-Forward and #13 Gift Aid Higher-Rate Relief — each with their own capture tool, capture handler, state-machine state, calculator strategy, tests, and live browser verification.

**Architecture:** Three-package split:
1. `App\Services\Tax\TaxStrategyMath` — stateless helper service holding all the calculator's existing private utility methods (band lookups, PSA, taxable income, age-of, etc.). Constructor takes `TaxConfigService`.
2. `App\Services\Tax\Strategies\Contract\TaxStrategy` interface + `TaxStrategyContext` value object (User, overrides, household, mode).
3. One concrete `App\Services\Tax\Strategies\*Strategy` class per strategy. Each implements `generate(TaxStrategyContext): list<StrategyRecommendation>` returning `[]` when its preconditions aren't met. Strategies are injected into `TaxStrategyCalculator` via the service container.

The calculator's `calculate()` method becomes a thin composer: build allowance grids → loop through registered strategies → sort + emit DTO. No behaviour change in Phase A; Phase B and C add two new strategy classes plus their full capture chain.

**Tech Stack:** Laravel 10, PHP 8.2, Pest 3, Anthropic + xAI tool definitions in lockstep, `OnboardingStateMachine` campaign-state extension, forward-only schema migrations (no data backfill required).

**Branch:** Stays on `feature/fyn-persona-split`. No worktree (single-developer sequential work — see `feedback_never_switch_branches.md`).

---

## Acceptance criteria (Rule #15 contract)

The loop terminates **only** when every line below is GREEN:

**Phase A (refactor):**
- All 91 existing `TaxStrategyCalculatorTest` cases pass without modification to test logic.
- Architecture suite passes (`./vendor/bin/pest --testsuite=Architecture`).
- `TaxStrategyCalculator.php` is ≤ 350 lines.
- Pint clean on every changed file.

**Phase B (Pension AA Carry-Forward):**
- Migration `pension_input_history` table created with the schema below.
- New tool `capture_pension_history` defined identically in `AiToolDefinitions` and `XaiToolDefinitions` (validated by `ToolCatalogueParityTest`).
- New state `STATE_CAMPAIGN_PENSION_HISTORY` slotted between `STATE_CAMPAIGN_PENSION_CONTRIBS` and `STATE_CAMPAIGN_SPOUSE_WORK`.
- `PensionAACarryForwardStrategy` fires only when current-year input < AA AND prior-3-year unused AA > 0 AND user band is higher OR additional. Saving = `unused_carry_forward × user_marginal_rate`.
- `OnboardingPromptBuilder::toolsForFocus('savetax')` lists `capture_pension_history`.
- Live browser walk on `john@example.com` after seeding 3 years of `PensionInputHistory` plus a higher-rate income shows the strategy with the correct figures.
- New unit tests for the strategy + handler all pass.

**Phase C (Gift Aid):**
- Migration adds `users.annual_charitable_donations` decimal(12,2) nullable.
- New tool `capture_charitable_giving` defined identically in `AiToolDefinitions` and `XaiToolDefinitions`.
- New state `STATE_CAMPAIGN_CHARITABLE_GIVING` slotted in (placement decision in Task C5).
- `GiftAidHigherRateReliefStrategy` fires only when user band is higher OR additional AND `annual_charitable_donations > 0`. Saving = donations × 0.25 (higher) or × 0.3125 (additional).
- `OnboardingPromptBuilder::toolsForFocus('savetax')` lists `capture_charitable_giving`.
- Live browser walk on `john@example.com` (after raising income to higher-band and capturing a donation) shows the strategy with the correct figures.
- New unit tests for the strategy + handler all pass.

**Whole plan:**
- `./vendor/bin/pest tests/Unit/Services/Tax/` green (existing 91 + new ~11 = ~102 cases).
- `./vendor/bin/pest --testsuite=Architecture` green.
- Pint clean on every changed file.
- Each phase is its own commit; no mixed-purpose commits.

---

## File map

**New files:**
- `app/Services/Tax/TaxStrategyMath.php`
- `app/Services/Tax/Strategies/Contract/TaxStrategy.php` (interface)
- `app/Services/Tax/Strategies/TaxStrategyContext.php`
- `app/Services/Tax/Strategies/IncomeBandStrategy.php`
- `app/Services/Tax/Strategies/LifecycleStrategy.php`
- `app/Services/Tax/Strategies/JointSavingsStrategy.php`
- `app/Services/Tax/Strategies/IsaTopUpStrategy.php`
- `app/Services/Tax/Strategies/DividendAllowanceHarvestStrategy.php`
- `app/Services/Tax/Strategies/SalarySacrificeNiStrategy.php`
- `app/Services/Tax/Strategies/BedAndIsaStrategy.php`
- `app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php`
- `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php`
- `app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php`
- `app/Services/Tax/Strategies/PensionAACarryForwardStrategy.php` (Phase B)
- `app/Services/Tax/Strategies/GiftAidHigherRateReliefStrategy.php` (Phase C)
- `app/Models/PensionInputHistory.php` (Phase B)
- `database/migrations/2026_05_05_000001_create_pension_input_history_table.php` (Phase B)
- `database/migrations/2026_05_05_000002_add_charitable_donations_to_users.php` (Phase C)

**Modified files:**
- `app/Services/Tax/TaxStrategyCalculator.php` (drops to ~250 lines)
- `app/Services/AI/AiToolDefinitions.php` (Phase B + C)
- `app/Services/AI/XaiToolDefinitions.php` (Phase B + C)
- `app/Agents/CoordinatingAgent.php` (Phase B + C handlers)
- `app/Services/Onboarding/OnboardingPromptBuilder.php` (Phase B + C tool wiring)
- `app/Services/Onboarding/OnboardingStateMachine.php` (Phase B + C states)
- `app/Models/User.php` (Phase C — fillable + cast for annual_charitable_donations)
- `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` (Phase B + C cases)
- `tests/Architecture/AnthropicXaiToolParityTest.php` if needed

---

## Phase A — S-1 refactor (no behaviour change)

### Task A1: Extract `TaxStrategyMath`

**Files:**
- Create: `app/Services/Tax/TaxStrategyMath.php`

- [ ] **Step 1: Write the file**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\DCPension;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * Stateless math/lookup helpers shared across every TaxStrategy class.
 *
 * Every public method is deterministic given (User, ?Overrides, TaxConfig).
 * Methods that hit the database (estimateAnnualInterest, estimateIsaSubscriptionsThisYear,
 * estimatePensionContributionThisYear) issue a single query each — keep an eye on N+1
 * if a strategy class calls them inside a loop.
 */
final class TaxStrategyMath
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function psaForBand(string $band): float
    {
        $psa = $this->taxConfig->getIncomeTax()['personal_savings_allowance'] ?? [];

        return (float) ($psa[$band] ?? 0);
    }

    /**
     * @return array{higher: float, additional: float}
     */
    public function bandThresholds(): array
    {
        $bands = $this->taxConfig->getIncomeTax()['bands'] ?? [];
        $higher = 0.0;
        $additional = 0.0;
        foreach ($bands as $band) {
            $name = strtolower((string) ($band['name'] ?? ''));
            if (str_contains($name, 'higher')) {
                $higher = (float) ($band['lower_limit'] ?? 0);
            }
            if (str_contains($name, 'additional')) {
                $additional = (float) ($band['lower_limit'] ?? 0);
            }
        }

        return ['higher' => $higher, 'additional' => $additional];
    }

    public function bandFromIncome(float $income): string
    {
        $thresholds = $this->bandThresholds();

        return match (true) {
            $income >= $thresholds['additional'] && $thresholds['additional'] > 0 => 'additional',
            $income >= $thresholds['higher'] && $thresholds['higher'] > 0 => 'higher',
            default => 'basic',
        };
    }

    public function bandRateFor(User $user): float
    {
        return match ($this->bandFromIncome((float) ($user->annual_employment_income ?? 0))) {
            'basic' => 0.20,
            'higher' => 0.40,
            'additional' => 0.45,
        };
    }

    public function personalSavingsAllowanceFor(float $income): float
    {
        return $this->psaForBand($this->bandFromIncome($income));
    }

    public function taxableIncomeFor(User $user): float
    {
        $employment = (float) ($user->annual_employment_income ?? 0);
        $dividends = (float) ($user->annual_dividend_income ?? 0);
        $interest = $this->estimateAnnualInterest($user);

        return $employment + $dividends + $interest;
    }

    public function availableAnnualAllowance(User $user, ?TaxStrategyOverridesDTO $overrides): float
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $aa = (float) ($pension['annual_allowance'] ?? 60000);
        $used = $this->estimatePensionContributionThisYear($user, $overrides);

        return max(0, $aa - $used);
    }

    public function estimateAnnualInterest(User $user): float
    {
        return (float) SavingsAccount::query()
            ->where('user_id', $user->id)
            ->where('is_isa', false)
            ->get()
            ->sum(fn ($acc) => (float) $acc->current_balance * (float) $acc->interest_rate);
    }

    public function estimateIsaSubscriptionsThisYear(User $user): float
    {
        return (float) SavingsAccount::query()
            ->where('user_id', $user->id)
            ->where('is_isa', true)
            ->sum('current_balance');
    }

    public function estimatePensionContributionThisYear(User $user, ?TaxStrategyOverridesDTO $overrides): float
    {
        if ($overrides?->pensionContributionPercent !== null) {
            return (float) ($user->annual_employment_income ?? 0) * ($overrides->pensionContributionPercent / 100);
        }

        $monthlyTotal = (float) DCPension::where('user_id', $user->id)->sum('monthly_contribution_amount');

        return $monthlyTotal * 12;
    }

    public function ageOf(mixed $dateOfBirth): ?int
    {
        if ($dateOfBirth === null) {
            return null;
        }

        $dob = $dateOfBirth instanceof \DateTimeInterface
            ? Carbon::instance($dateOfBirth)
            : Carbon::parse((string) $dateOfBirth);

        return (int) $dob->diffInYears(now());
    }
}
```

- [ ] **Step 2: Run `php -l` to confirm parse**

```bash
php -l app/Services/Tax/TaxStrategyMath.php
```

Expected: `No syntax errors detected`.

---

### Task A2: Create `TaxStrategy` interface + `TaxStrategyContext`

**Files:**
- Create: `app/Services/Tax/Strategies/Contract/TaxStrategy.php`
- Create: `app/Services/Tax/Strategies/TaxStrategyContext.php`

- [ ] **Step 1: Write the context value object**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;

/**
 * Immutable bundle of state passed to every TaxStrategy::generate() call.
 * Keeps strategy signatures clean and lets us extend the input set without
 * touching every implementor.
 */
final class TaxStrategyContext
{
    public function __construct(
        public readonly User $user,
        public readonly ?TaxStrategyOverridesDTO $overrides,
        public readonly ?TaxStrategyHouseholdInput $household,
        public readonly string $mode,
    ) {}
}
```

- [ ] **Step 2: Write the interface**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies\Contract;

use App\DataTransferObjects\StrategyRecommendation;
use App\Services\Tax\Strategies\TaxStrategyContext;

interface TaxStrategy
{
    /**
     * Return zero or more recommendations for this context. Returning an empty
     * array means the strategy's preconditions weren't met for this user.
     *
     * @return list<StrategyRecommendation>
     */
    public function generate(TaxStrategyContext $context): array;
}
```

- [ ] **Step 3: Parse-check both files**

```bash
php -l app/Services/Tax/Strategies/Contract/TaxStrategy.php
php -l app/Services/Tax/Strategies/TaxStrategyContext.php
```

Expected: `No syntax errors detected` for both.

---

### Task A3: Extract `IncomeBandStrategy`

**Files:**
- Create: `app/Services/Tax/Strategies/IncomeBandStrategy.php`
- Read source: `app/Services/Tax/TaxStrategyCalculator.php:473-563` (current `buildIncomeBandRecommendations` method)

- [ ] **Step 1: Read the current method to copy verbatim**

```bash
sed -n '473,563p' app/Services/Tax/TaxStrategyCalculator.php
```

Take the body of `buildIncomeBandRecommendations(User $user, ?TaxStrategyOverridesDTO $overrides)` exactly as-is — every condition, every threshold, every string template.

- [ ] **Step 2: Write the strategy class**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

final class IncomeBandStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $overrides = $context->overrides;

        // PASTE the body of buildIncomeBandRecommendations VERBATIM here.
        // Replace `$this->psaForBand(...)` with `$this->math->psaForBand(...)`.
        // Replace `$this->bandFromIncome(...)` with `$this->math->bandFromIncome(...)`.
        // Replace `$this->bandRateFor(...)` with `$this->math->bandRateFor(...)`.
        // Replace `$this->taxableIncomeFor(...)` with `$this->math->taxableIncomeFor(...)`.
        // Replace `$this->estimateAnnualInterest(...)` with `$this->math->estimateAnnualInterest(...)`.
        // Replace `$this->estimateIsaSubscriptionsThisYear(...)` with `$this->math->estimateIsaSubscriptionsThisYear(...)`.
        // Replace `$this->estimatePensionContributionThisYear(...)` with `$this->math->estimatePensionContributionThisYear(...)`.
        // Replace `$this->availableAnnualAllowance(...)` with `$this->math->availableAnnualAllowance(...)`.
        // The body returns an array of StrategyRecommendation objects — keep that exact shape.
    }
}
```

- [ ] **Step 3: Parse-check**

```bash
php -l app/Services/Tax/Strategies/IncomeBandStrategy.php
```

Expected: clean.

---

### Task A4: Extract `LifecycleStrategy`

Mirror Task A3 for `buildLifecycleRecommendations(User $user)` at `TaxStrategyCalculator.php:564-691`. Note that `LifecycleStrategy` reads only `User` (no overrides); add `ageOf()` calls via `$this->math->ageOf(...)`.

- [ ] **Step 1**: `sed -n '564,691p' app/Services/Tax/TaxStrategyCalculator.php` and copy the body.

- [ ] **Step 2**: Create `app/Services/Tax/Strategies/LifecycleStrategy.php` following the same shape as `IncomeBandStrategy`. Its `generate()` receives `TaxStrategyContext` and only uses `$context->user`.

- [ ] **Step 3**: Parse-check.

---

### Task A5: Extract `JointSavingsStrategy`

Source: `buildJointSavingsRecommendations(User $user, ?TaxStrategyHouseholdInput $household, string $mode)` at `TaxStrategyCalculator.php:692-769`.

- [ ] **Step 1**: Copy body. The strategy reads `$context->user`, `$context->household`, `$context->mode`.

- [ ] **Step 2**: Create `app/Services/Tax/Strategies/JointSavingsStrategy.php`.

- [ ] **Step 3**: Parse-check.

---

### Task A6: Split `buildAllowanceRecommendations` into `IsaTopUpStrategy` and `DividendAllowanceHarvestStrategy`

The current method emits two strategies (`isa_topup_vs_psa` and `dividend_allowance_harvest`) — they're independent and should be separate classes. Source: `TaxStrategyCalculator.php:770-873`.

- [ ] **Step 1**: Read the method (`sed -n '770,873p' ...`).

- [ ] **Step 2**: Create `app/Services/Tax/Strategies/IsaTopUpStrategy.php` containing only the `isa_topup_vs_psa` block (lines 778-829). Wires through `$this->math` for helpers.

- [ ] **Step 3**: Create `app/Services/Tax/Strategies/DividendAllowanceHarvestStrategy.php` containing only the `dividend_allowance_harvest` block (lines 831-870).

- [ ] **Step 4**: Parse-check both.

---

### Task A7: Extract `SalarySacrificeNiStrategy`

Source: `buildSalarySacrificeRecommendation(User $user)` at `TaxStrategyCalculator.php:886-972`.

- [ ] **Step 1**: Read body.

- [ ] **Step 2**: Create `app/Services/Tax/Strategies/SalarySacrificeNiStrategy.php`. Uses `$context->user` only.

- [ ] **Step 3**: Parse-check.

---

### Task A8: Extract `BedAndIsaStrategy`

Source: `buildBedAndIsaRecommendation(User $user)` at `TaxStrategyCalculator.php:984-1087`.

- [ ] **Step 1**: Read body.

- [ ] **Step 2**: Create `app/Services/Tax/Strategies/BedAndIsaStrategy.php`. Uses `$context->user` only.

- [ ] **Step 3**: Parse-check.

---

### Task A9: Extract `NonEarnerSpousePensionStrategy`

Source: `buildNonEarnerSpousePensionRecommendation(User $user, ?TaxStrategyHouseholdInput $household, string $mode)` at `TaxStrategyCalculator.php:1102-1149`. Includes `resolveSpouseAge(User $user)` helper at `:1156-1177`.

- [ ] **Step 1**: Read both blocks.

- [ ] **Step 2**: Create `app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php`. The class needs `resolveSpouseAge()` as a private method — keep it co-located with this strategy since it's the only caller. It uses `$this->math->ageOf(...)` for the actual age math.

- [ ] **Step 3**: Parse-check.

---

### Task A10: Extract `AssetShiftingBundleStrategy`

Source: `buildAssetShiftingSuggestions(User $user, ?TaxStrategyHouseholdInput $household, ?TaxStrategyOverridesDTO $overrides)` at `TaxStrategyCalculator.php:273-402` (or wherever it ends — confirm by reading).

This method emits **multiple** strategies as a legacy array, then the calculator wraps them via `StrategyRecommendation::fromArray(StrategyCategory::Household, $arr)`. The strategy class will do that wrapping internally so its `generate()` returns a list of typed DTOs (matches the interface).

- [ ] **Step 1**: Read the full method body including its end.

- [ ] **Step 2**: Create `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php`. Internal logic stays identical; the only mechanical change is the final `return $suggestions` becomes:

```php
return array_map(
    fn (array $arr) => StrategyRecommendation::fromArray(StrategyCategory::Household, $arr),
    $suggestions,
);
```

The strategy applies only when `$context->mode === 'single_earner_couple'` — early-return `[]` otherwise.

- [ ] **Step 3**: Parse-check.

---

### Task A11: Extract `CrossSpouseBundleStrategy`

Source: `buildCrossSpouseSuggestions(User $user, TaxStrategyHouseholdInput $household)` at `TaxStrategyCalculator.php:403-472`.

Mirrors A10. Applies only when `$context->mode === 'dual_earner' && $context->household instanceof TaxStrategyHouseholdInput` — early-return `[]` otherwise.

- [ ] **Step 1**: Read body.

- [ ] **Step 2**: Create `app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php`.

- [ ] **Step 3**: Parse-check.

---

### Task A12: Slim `TaxStrategyCalculator` to compose strategies

**Files:**
- Modify: `app/Services/Tax/TaxStrategyCalculator.php`

- [ ] **Step 1: Replace constructor with strategy injection**

```php
public function __construct(
    private readonly TaxConfigService $taxConfig,
    private readonly TaxStrategyMath $math,
    private readonly Strategies\IncomeBandStrategy $incomeBand,
    private readonly Strategies\LifecycleStrategy $lifecycle,
    private readonly Strategies\JointSavingsStrategy $jointSavings,
    private readonly Strategies\IsaTopUpStrategy $isaTopUp,
    private readonly Strategies\DividendAllowanceHarvestStrategy $dividendAllowance,
    private readonly Strategies\SalarySacrificeNiStrategy $salarySacrifice,
    private readonly Strategies\BedAndIsaStrategy $bedAndIsa,
    private readonly Strategies\NonEarnerSpousePensionStrategy $nonEarnerSpousePension,
    private readonly Strategies\AssetShiftingBundleStrategy $assetShifting,
    private readonly Strategies\CrossSpouseBundleStrategy $crossSpouse,
) {}
```

- [ ] **Step 2: Replace `calculate()` body**

```php
public function calculate(User $user, ?TaxStrategyOverridesDTO $overrides = null): TaxStrategyOutputDTO
{
    $mode = (string) ($user->household_calculation_mode ?? 'single');
    $taxYear = $this->taxConfig->getTaxYear();
    $household = $user->taxStrategyHouseholdInput;

    $context = new Strategies\TaxStrategyContext($user, $overrides, $household, $mode);

    $userAllowances = $this->buildUserAllowanceGrid($user, $overrides);

    $spouseAllowances = match ($mode) {
        'dual_earner' => $household instanceof TaxStrategyHouseholdInput
            ? $this->buildSpouseAllowanceGridDualEarner($household)
            : null,
        'single_earner_couple' => $this->buildSpouseAllowanceGridNonWorking($household),
        default => null,
    };

    $strategies = [
        $this->incomeBand,
        $this->lifecycle,
        $this->jointSavings,
        $this->isaTopUp,
        $this->dividendAllowance,
        $this->salarySacrifice,
        $this->bedAndIsa,
        $this->nonEarnerSpousePension,
        $this->crossSpouse,
        $this->assetShifting,
    ];

    $allRecs = [];
    foreach ($strategies as $strategy) {
        $allRecs = array_merge($allRecs, $strategy->generate($context));
    }

    usort($allRecs, function (StrategyRecommendation $a, StrategyRecommendation $b): int {
        $cat = $a->categoryEnum()->sortWeight() <=> $b->categoryEnum()->sortWeight();

        return $cat !== 0 ? $cat : ($a->priorityEnum()->sortWeight() <=> $b->priorityEnum()->sortWeight());
    });

    $recommendations = array_map(fn (StrategyRecommendation $r) => $r->toArray(), $allRecs);

    return new TaxStrategyOutputDTO(
        taxYear: $taxYear,
        calculationMode: $mode,
        userAllowances: $userAllowances,
        spouseAllowances: $spouseAllowances,
        recommendations: $recommendations,
        deltaVsBaseline: [],
    );
}
```

- [ ] **Step 3: Delete every extracted private method**

Delete in place from the calculator (their bodies now live in strategy classes):
- `psaForBand()` — moved to Math
- `bandThresholds()` — Math
- `bandFromIncome()` — Math
- `bandRateFor()` — Math
- `personalSavingsAllowanceFor()` — Math
- `taxableIncomeFor()` — Math
- `availableAnnualAllowance()` — Math
- `estimateAnnualInterest()` — Math
- `estimateIsaSubscriptionsThisYear()` — Math
- `estimatePensionContributionThisYear()` — Math
- `ageOf()` — Math
- `buildIncomeBandRecommendations()` — IncomeBandStrategy
- `buildLifecycleRecommendations()` — LifecycleStrategy
- `buildJointSavingsRecommendations()` — JointSavingsStrategy
- `buildAllowanceRecommendations()` — IsaTopUp + DividendAllowance
- `buildSalarySacrificeRecommendation()` — SalarySacrificeNi
- `buildBedAndIsaRecommendation()` — BedAndIsa
- `buildNonEarnerSpousePensionRecommendation()` — NonEarnerSpousePension
- `resolveSpouseAge()` — moves into NonEarnerSpousePensionStrategy
- `buildAssetShiftingSuggestions()` — AssetShiftingBundle
- `buildCrossSpouseSuggestions()` — CrossSpouseBundle

**Keep** in calculator:
- `__construct()`
- `calculate()`
- `buildUserAllowanceGrid()` — tightly coupled to TaxStrategyOutputDTO shape
- `buildSpouseAllowanceGridDualEarner()` — same
- `buildSpouseAllowanceGridNonWorking()` — same
- `position()` — only used by the grid builders

The calculator's grid builders reference `psaForBand()` and `personalSavingsAllowanceFor()` — update those call sites to use `$this->math->psaForBand(...)` / `$this->math->personalSavingsAllowanceFor(...)`.

- [ ] **Step 4: Confirm calculator is ≤ 350 lines**

```bash
wc -l app/Services/Tax/TaxStrategyCalculator.php
```

Expected: ≤ 350 (target is ~250).

---

### Task A13: Run all tax tests, expect 91 GREEN with no test edits

- [ ] **Step 1: Run the full Tax suite**

```bash
./vendor/bin/pest tests/Unit/Services/Tax/ --colors=never
```

Expected: `91 passed`.

If anything fails:
- Inspect the failure. The refactor preserves behaviour — every failure is a transcription error in one of the strategy classes.
- Fix the strategy class. Do NOT modify the test file.
- Re-run until green. Do not proceed to Task A14 with red tests (Rule #15).

- [ ] **Step 2: Run the architecture suite**

```bash
./vendor/bin/pest --testsuite=Architecture --colors=never
```

Expected: green.

- [ ] **Step 3: Pint the changed files**

```bash
./vendor/bin/pint app/Services/Tax/ --test
```

If any files need formatting, run without `--test` then re-test.

---

### Task A14: Commit Phase A

- [ ] **Step 1: Stage and commit**

```bash
git add app/Services/Tax/ tests/Unit/Services/Tax/
git status
```

Verify only intended files are staged. Then:

```bash
git commit -m "$(cat <<'EOF'
refactor(tax): extract per-strategy classes from TaxStrategyCalculator (S-1)

Splits TaxStrategyCalculator (1301 lines) into:
- TaxStrategyMath (helpers: band lookups, PSA, taxable income, age)
- TaxStrategy interface + TaxStrategyContext value object
- 10 per-strategy classes in app/Services/Tax/Strategies/

Calculator drops to ~250 lines and becomes a thin composer over the
registered strategies. Behaviour preserved: 91/91 existing tests pass
unchanged. No new strategies in this commit — Phase 4 follow-ups
(#3 Pension AA Carry-Forward, #13 Gift Aid) layer cleanly on top.
EOF
)"
```

- [ ] **Step 2: Push**

```bash
git push origin feature/fyn-persona-split
```

---

## Phase B — #3 Pension Annual Allowance Carry-Forward

### Task B1: Migration — `pension_input_history` table

**Files:**
- Create: `database/migrations/2026_05_05_000001_create_pension_input_history_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pension_input_history')) {
            return;
        }

        Schema::create('pension_input_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tax_year', 9); // e.g. '2024/25'
            $table->decimal('pension_input_amount', 12, 2);
            $table->timestamps();

            $table->unique(['user_id', 'tax_year']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pension_input_history');
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `Migrated: 2026_05_05_000001_create_pension_input_history_table`.

- [ ] **Step 3: Confirm the table exists**

```bash
php artisan tinker --execute="echo Schema::hasTable('pension_input_history') ? 'yes' : 'no';"
```

Expected: `yes`.

---

### Task B2: Model `PensionInputHistory`

**Files:**
- Create: `app/Models/PensionInputHistory.php`

- [ ] **Step 1: Write the model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One record per (user, tax_year) capturing the gross pension input amount
 * for that year. Drives the Pension Annual Allowance Carry-Forward strategy
 * (#3) — we look back three tax years and surface unused AA the user could
 * still pension-up at their marginal rate.
 *
 * @property int $id
 * @property int $user_id
 * @property string $tax_year
 * @property float $pension_input_amount
 */
final class PensionInputHistory extends Model
{
    use HasFactory;

    protected $table = 'pension_input_history';

    protected $fillable = [
        'user_id',
        'tax_year',
        'pension_input_amount',
    ];

    protected $casts = [
        'pension_input_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 2: Parse-check**

```bash
php -l app/Models/PensionInputHistory.php
```

Expected: clean.

---

### Task B3: Add `capture_pension_history` to `AiToolDefinitions` and `XaiToolDefinitions`

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php` (in `campaignSaveTaxTools()` returning array, after `capture_salary_sacrifice` block)
- Modify: `app/Services/AI/XaiToolDefinitions.php` (matching block)

- [ ] **Step 1: Read the existing `capture_salary_sacrifice` block in both files** so the new block matches their wrapper shape (Anthropic uses `'parameters'`, xAI uses `'parameters'` with the same JSON-schema object — confirm by inspection).

```bash
grep -n "capture_salary_sacrifice" app/Services/AI/AiToolDefinitions.php app/Services/AI/XaiToolDefinitions.php
```

- [ ] **Step 2: Add the new tool to `AiToolDefinitions::campaignSaveTaxTools()` (right after the `capture_salary_sacrifice` block)**

```php
[
    'name' => 'capture_pension_history',
    'description' => 'Capture the user\'s gross pension contributions for each of the last 3 tax years. Used by the Pension Annual Allowance Carry-Forward strategy to compute unused AA the user could still pension-up. Pass each year individually using the canonical "YYYY/YY" tax-year format (e.g. "2024/25").',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'history' => [
                'type' => 'array',
                'description' => 'List of tax_year + amount pairs. The strategy reads up to the most recent 3 entries.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'tax_year' => ['type' => 'string', 'description' => 'UK tax year in "YYYY/YY" format (e.g. "2024/25").'],
                        'pension_input_amount' => ['type' => 'number', 'description' => 'Gross pension input for that year in pounds.'],
                    ],
                    'required' => ['tax_year', 'pension_input_amount'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['history'],
        'additionalProperties' => false,
    ],
],
```

- [ ] **Step 3: Mirror the same block in `XaiToolDefinitions`**

The xAI tool definitions follow the same JSON-schema shape — copy the same array structure into the matching method.

- [ ] **Step 4: Run the parity test**

```bash
./vendor/bin/pest --filter=ToolCatalogueParity --colors=never
```

Expected: green. If it fails, the two definitions diverge — fix the divergence before continuing.

---

### Task B4: Handler `handleCapturePensionHistory` in `CoordinatingAgent`

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php`

- [ ] **Step 1: Add a case for the new tool in the dispatch match (around line 890 where `capture_salary_sacrifice` is dispatched)**

```php
'capture_pension_history' => $this->handleCapturePensionHistory($input, $user, $isPreviewUser),
```

- [ ] **Step 2: Add the handler method (place it next to the other SaveTax campaign capture handlers, after `handleCaptureSpouseNonWorkingAssets`)**

```php
private function handleCapturePensionHistory(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('pension');
    }

    $history = $input['history'] ?? null;
    if (! is_array($history) || $history === []) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'history must be a non-empty array.'];
    }

    $written = [];
    foreach ($history as $entry) {
        if (! is_array($entry)) {
            continue;
        }
        $taxYear = isset($entry['tax_year']) ? (string) $entry['tax_year'] : null;
        $amount = isset($entry['pension_input_amount']) ? (float) $entry['pension_input_amount'] : null;
        if ($taxYear === null || $amount === null || $amount < 0) {
            continue;
        }

        \App\Models\PensionInputHistory::updateOrCreate(
            ['user_id' => $user->id, 'tax_year' => $taxYear],
            ['pension_input_amount' => $amount],
        );
        $written[$taxYear] = $amount;
    }

    if ($written === []) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No valid history entries provided.'];
    }

    return [
        'onboarding_capture' => true,
        'field_group' => 'campaign_pension_history',
        'summary' => sprintf('Captured %d year(s) of pension history.', count($written)),
        'details' => $written,
    ];
}
```

- [ ] **Step 3: Parse-check**

```bash
php -l app/Agents/CoordinatingAgent.php
```

Expected: clean.

---

### Task B5: Add `STATE_CAMPAIGN_PENSION_HISTORY` state to `OnboardingStateMachine`

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php`

- [ ] **Step 1: Add the constant (alphabetically near the other campaign states around line 90)**

```php
public const STATE_CAMPAIGN_PENSION_HISTORY = 'campaign_pension_history';
```

- [ ] **Step 2: Re-route `STATE_CAMPAIGN_PENSION_CONTRIBS` to point at the new state**

Change the `'next'` of `STATE_CAMPAIGN_PENSION_CONTRIBS` from `STATE_CAMPAIGN_SPOUSE_WORK` to `STATE_CAMPAIGN_PENSION_HISTORY`.

- [ ] **Step 3: Insert the new state definition (right after the `STATE_CAMPAIGN_PENSION_CONTRIBS` entry around line 345)**

```php
self::STATE_CAMPAIGN_PENSION_HISTORY => [
    'turn_type' => 'grouped_extract',
    'prompt_text' => 'Quick one — to check if you have any unused pension allowance to top up, what did you contribute (gross) in each of the last 3 tax years? If you don\'t know exact numbers, rough figures are fine, and "zero" is a valid answer.',
    'capture_field' => null,
    'extraction_tool' => 'capture_pension_history',
    'retry_text' => 'I just need a rough gross figure for each of the last three tax years (2024/25, 2023/24, 2022/23). Even "I think it was about 5,000 each year" works.',
    'next' => self::STATE_CAMPAIGN_SPOUSE_WORK,
],
```

- [ ] **Step 4: Parse-check**

```bash
php -l app/Services/Onboarding/OnboardingStateMachine.php
```

Expected: clean.

---

### Task B6: Add `capture_pension_history` to `OnboardingPromptBuilder::toolsForFocus('savetax')`

**Files:**
- Modify: `app/Services/Onboarding/OnboardingPromptBuilder.php`

- [ ] **Step 1: Add `'capture_pension_history'` to the `'savetax'` array (between `capture_salary_sacrifice` and `create_savings_account`)**

The existing array (around line 118-127):
```php
'savetax' => [
    'create_pension',
    'capture_salary_sacrifice',
    'capture_pension_history',  // NEW
    'create_savings_account',
    'create_investment_account',
    'create_holding',
    'capture_spouse_work_status',
    'capture_spouse_household_data',
    'capture_spouse_non_working_assets',
],
```

- [ ] **Step 2: Parse-check**

```bash
php -l app/Services/Onboarding/OnboardingPromptBuilder.php
```

Expected: clean.

---

### Task B7: `PensionAACarryForwardStrategy` class

**Files:**
- Create: `app/Services/Tax/Strategies/PensionAACarryForwardStrategy.php`

- [ ] **Step 1: Write the class**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\PensionInputHistory;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategy #3 — Pension Annual Allowance Carry-Forward.
 *
 * Fires when the user is in the higher or additional band, has not maxed
 * the current year's AA, AND has unused AA from the previous three tax years.
 * Saving = unused_carry_forward × user_marginal_rate.
 *
 * Carry-forward window: HMRC allows looking back 3 tax years. We sum
 * max(0, AA_for_year - input_for_year) over the most recent 3 entries in
 * pension_input_history. AA is held at the current value across the window
 * (the historical AA was the same £40,000 / £60,000 over the relevant period;
 * this is a conservative simplification — refine if HMRC changes mid-window).
 */
final class PensionAACarryForwardStrategy implements TaxStrategy
{
    private const LOOKBACK_YEARS = 3;

    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;

        $band = $this->math->bandFromIncome($this->math->taxableIncomeFor($user));
        if (! in_array($band, ['higher', 'additional'], true)) {
            return [];
        }

        $aa = (float) ($this->taxConfig->getPensionAllowances()['annual_allowance'] ?? 60000);
        $currentInput = $this->math->estimatePensionContributionThisYear($user, $context->overrides);
        if ($currentInput >= $aa) {
            return [];
        }

        $history = PensionInputHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('tax_year')
            ->limit(self::LOOKBACK_YEARS)
            ->get(['tax_year', 'pension_input_amount']);

        if ($history->isEmpty()) {
            return [];
        }

        $unused = 0.0;
        foreach ($history as $row) {
            $unused += max(0.0, $aa - (float) $row->pension_input_amount);
        }

        if ($unused <= 0) {
            return [];
        }

        $marginalRate = $this->math->bandRateFor($user);
        $saving = $unused * $marginalRate;

        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'pension_aa_carry_forward',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Carry forward up to £%s of unused Pension Allowance',
                number_format((int) round($unused / 1000) * 1000),
            ),
            description: sprintf(
                'You contributed below the £%s Pension Annual Allowance in each of the last 3 tax years, leaving around £%s of headroom you can still use. At your marginal rate that\'s a potential £%s of income-tax relief if you have surplus income to contribute.',
                number_format((int) $aa),
                number_format((int) round($unused / 1000) * 1000),
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: [
                'unused_carry_forward' => round($unused, 2),
                'marginal_rate' => $marginalRate,
                'lookback_years' => self::LOOKBACK_YEARS,
                'current_year_input' => round($currentInput, 2),
                'annual_allowance' => $aa,
            ],
        )];
    }
}
```

- [ ] **Step 2: Parse-check**

```bash
php -l app/Services/Tax/Strategies/PensionAACarryForwardStrategy.php
```

Expected: clean.

---

### Task B8: Wire `PensionAACarryForwardStrategy` into the calculator

**Files:**
- Modify: `app/Services/Tax/TaxStrategyCalculator.php`

- [ ] **Step 1: Add to the constructor**

After `$bedAndIsa`:
```php
private readonly Strategies\PensionAACarryForwardStrategy $pensionAACarryForward,
```

- [ ] **Step 2: Add to the `$strategies` array in `calculate()`**

After `$this->bedAndIsa`:
```php
$this->pensionAACarryForward,
```

- [ ] **Step 3: Parse-check**

```bash
php -l app/Services/Tax/TaxStrategyCalculator.php
```

Expected: clean.

---

### Task B9: Tests for the strategy + handler

**Files:**
- Modify: `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php`

- [ ] **Step 1: Add a `describe('Strategy #3 — Pension AA Carry-Forward', ...)` block** at the bottom of the file (before the closing `;` of the outer `describe()` if any, or as a new top-level block matching the existing structure).

Cases to cover (write each as `it('description', function () { ... });` mirroring the existing test style):

1. `it('does not fire for basic-rate users')` — set `annual_employment_income = 30000`, seed history with unused AA, expect no `pension_aa_carry_forward` recommendation.
2. `it('does not fire when current year contributions already exceed AA')` — higher-rate user, set DCPension `monthly_contribution_amount = 6000` (£72k/yr), expect no recommendation.
3. `it('does not fire when no pension_input_history rows exist')` — higher-rate user, no history, expect no recommendation.
4. `it('does not fire when all 3 prior years used the full AA')` — seed three rows at 60000 each, expect no recommendation.
5. `it('fires with correct unused_carry_forward and saving for higher-rate user')` — seed three rows at 20000 each (40k unused per year × 3 = 120k), higher-rate (annual_employment_income = 80000), expect recommendation with `unused_carry_forward = 120000`, `marginal_rate = 0.40`, `estimated_annual_tax_saved = 48000`.
6. `it('fires with correct saving for additional-rate user')` — seed similarly with additional-rate income, expect `marginal_rate = 0.45`.

For each test: arrange (User factory + DCPension factory + PensionInputHistory rows), act (call `$calculator->calculate($user)`), assert (find the rec by `type === 'pension_aa_carry_forward'` and check fields). Mirror the assertion style used by Phase 3's tests for #4/#6/#12.

- [ ] **Step 2: Add a `describe('handleCapturePensionHistory', ...)` block to the relevant Coordinating Agent test (likely `tests/Unit/Agents/CoordinatingAgentTest.php` or wherever the other capture handlers are tested)**

```bash
grep -rn "handleCaptureSalarySacrifice\|capture_salary_sacrifice" tests/ | head
```

Use the existing test file. Cases:
1. `it('writes pension_input_history rows for each entry')` — call the handler with 3 entries, assert 3 rows in DB.
2. `it('updates existing rows when called twice for same tax year')` — assert `updateOrCreate` semantics.
3. `it('rejects empty history array')` — expect `error_type === 'validation_failed'`.
4. `it('skips negative amounts')` — pass an entry with `pension_input_amount = -100`, assert it's dropped.

- [ ] **Step 3: Run only the new tests first**

```bash
./vendor/bin/pest --filter="Pension AA Carry-Forward" --colors=never
./vendor/bin/pest --filter="handleCapturePensionHistory" --colors=never
```

Expected: all green.

- [ ] **Step 4: Run the full Tax + Architecture + Agents suites**

```bash
./vendor/bin/pest tests/Unit/Services/Tax/ tests/Unit/Agents/ --testsuite=Architecture --colors=never
```

Expected: green. No regressions.

---

### Task B10: Live browser verification

**Files:** none (manual test).

- [ ] **Step 1: Seed test data for `john@example.com`**

```bash
php artisan tinker --execute="
\$u = \App\Models\User::where('email','john@example.com')->first();
\$u->update(['annual_employment_income' => 80000, 'household_calculation_mode' => 'single']);
\App\Models\PensionInputHistory::updateOrCreate(['user_id' => \$u->id, 'tax_year' => '2024/25'], ['pension_input_amount' => 20000]);
\App\Models\PensionInputHistory::updateOrCreate(['user_id' => \$u->id, 'tax_year' => '2023/24'], ['pension_input_amount' => 20000]);
\App\Models\PensionInputHistory::updateOrCreate(['user_id' => \$u->id, 'tax_year' => '2022/23'], ['pension_input_amount' => 20000]);
echo 'seeded';
"
```

- [ ] **Step 2: Open Playwright to `http://localhost:8000` and log in as john@example.com / password**

Fetch verification code from DB per CLAUDE.md "Authentication for Testing" — do NOT ask the user.

- [ ] **Step 3: Navigate to `/tax-strategy` and verify the Pension AA Carry-Forward card**

Expected: a card with title containing "Carry forward", description showing £120,000 unused, saving £48,000. Browser snapshot must show the card. No regressions on the other Phase 1-3 strategies for this user.

- [ ] **Step 4: Verify via the API endpoint**

```bash
curl -s -H "Cookie: $(cat /tmp/john-cookie 2>/dev/null)" http://localhost:8000/api/tax-strategy | jq '.recommendations[] | select(.type == "pension_aa_carry_forward")'
```

Expected: a JSON object with `unused_carry_forward = 120000`, `estimated_annual_tax_saved = 48000`.

If anything is off, debug per Rule #15 (LOOP UNTIL CORRECT) — root-cause and fix before moving on.

---

### Task B11: Commit Phase B

- [ ] **Step 1: Stage and commit**

```bash
git add app/ database/migrations/2026_05_05_000001_create_pension_input_history_table.php tests/
git status
git commit -m "$(cat <<'EOF'
feat(tax): SaveTax Phase 4 — Pension AA Carry-Forward (#3)

- Migration: pension_input_history table (user_id, tax_year, amount)
- Model: PensionInputHistory
- Tool: capture_pension_history (Anthropic + xAI parity)
- Handler: handleCapturePensionHistory in CoordinatingAgent
- State: STATE_CAMPAIGN_PENSION_HISTORY between PENSION_CONTRIBS and SPOUSE_WORK
- Strategy: PensionAACarryForwardStrategy fires for higher/additional-rate
  users with prior-3-year unused AA AND current-year input below £60k.
  Saving = unused_carry_forward × marginal_rate.

Live-verified on john@example.com (£80k income, 3yrs × £20k input):
  Surfaces "Carry forward up to £120,000" with £48,000 saving badge.
EOF
)"
```

- [ ] **Step 2: Push**

```bash
git push origin feature/fyn-persona-split
```

---

## Phase C — #13 Gift Aid Higher-Rate Relief

### Task C1: Migration — `users.annual_charitable_donations`

**Files:**
- Create: `database/migrations/2026_05_05_000002_add_charitable_donations_to_users.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'annual_charitable_donations')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('annual_charitable_donations', 12, 2)->nullable()->after('annual_dividend_income');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'annual_charitable_donations')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('annual_charitable_donations');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `Migrated: 2026_05_05_000002_add_charitable_donations_to_users`.

- [ ] **Step 3: Confirm column**

```bash
php artisan tinker --execute="echo Schema::hasColumn('users', 'annual_charitable_donations') ? 'yes' : 'no';"
```

Expected: `yes`.

---

### Task C2: User model fillable + cast

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Add `'annual_charitable_donations'` to `$fillable`**

Find the existing `$fillable` array. Add `'annual_charitable_donations'` near `'annual_dividend_income'`.

- [ ] **Step 2: Add the cast**

Find `$casts` and add:
```php
'annual_charitable_donations' => 'decimal:2',
```

- [ ] **Step 3: Parse-check**

```bash
php -l app/Models/User.php
```

Expected: clean.

---

### Task C3: Add `capture_charitable_giving` to `AiToolDefinitions` and `XaiToolDefinitions`

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php`
- Modify: `app/Services/AI/XaiToolDefinitions.php`

- [ ] **Step 1: Add to `campaignSaveTaxTools()` (after `capture_pension_history`)**

```php
[
    'name' => 'capture_charitable_giving',
    'description' => 'Capture the user\'s annual charitable donations covered by Gift Aid. Used by the Gift Aid Higher-Rate Relief strategy to compute the personal tax relief the user can reclaim via Self Assessment when they donate at the higher or additional rate.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'annual_donations' => ['type' => 'number', 'description' => 'Total annual Gift-Aid-eligible donations in pounds.'],
        ],
        'required' => ['annual_donations'],
        'additionalProperties' => false,
    ],
],
```

- [ ] **Step 2: Mirror in `XaiToolDefinitions`**

- [ ] **Step 3: Run parity test**

```bash
./vendor/bin/pest --filter=ToolCatalogueParity --colors=never
```

Expected: green.

---

### Task C4: Handler `handleCaptureCharitableGiving` in `CoordinatingAgent`

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php`

- [ ] **Step 1: Add to dispatch match (next to `capture_pension_history`)**

```php
'capture_charitable_giving' => $this->handleCaptureCharitableGiving($input, $user, $isPreviewUser),
```

- [ ] **Step 2: Add the handler (next to `handleCapturePensionHistory`)**

```php
private function handleCaptureCharitableGiving(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('profile');
    }

    if (! array_key_exists('annual_donations', $input)) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'annual_donations is required.'];
    }

    $amount = (float) $input['annual_donations'];
    if ($amount < 0) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'annual_donations must be >= 0.'];
    }

    $user->update(['annual_charitable_donations' => $amount]);

    return [
        'updated' => true,
        'annual_charitable_donations' => $amount,
        'message' => 'Annual charitable donations recorded.',
    ];
}
```

- [ ] **Step 3: Parse-check**

```bash
php -l app/Agents/CoordinatingAgent.php
```

Expected: clean.

---

### Task C5: Add `STATE_CAMPAIGN_CHARITABLE_GIVING` to `OnboardingStateMachine`

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php`

**Placement decision:** insert AFTER `STATE_CAMPAIGN_PENSION_HISTORY` and BEFORE `STATE_CAMPAIGN_SPOUSE_WORK`. This keeps user-only states grouped before the spouse fork.

- [ ] **Step 1: Add the constant**

```php
public const STATE_CAMPAIGN_CHARITABLE_GIVING = 'campaign_charitable_giving';
```

- [ ] **Step 2: Re-route `STATE_CAMPAIGN_PENSION_HISTORY.next` to the new state**

Change `STATE_CAMPAIGN_PENSION_HISTORY`'s `'next' => self::STATE_CAMPAIGN_SPOUSE_WORK` to `'next' => self::STATE_CAMPAIGN_CHARITABLE_GIVING`.

- [ ] **Step 3: Insert the new state definition**

```php
self::STATE_CAMPAIGN_CHARITABLE_GIVING => [
    'turn_type' => 'grouped_extract',
    'prompt_text' => 'One more — do you make any charitable donations through Gift Aid? If you donate at the higher or additional rate, there\'s extra relief you can reclaim. Roughly how much per year? Say "none" if you don\'t donate.',
    'capture_field' => null,
    'extraction_tool' => 'capture_charitable_giving',
    'retry_text' => 'Just an annual figure works — e.g. "about £500" or "none".',
    'next' => self::STATE_CAMPAIGN_SPOUSE_WORK,
],
```

- [ ] **Step 4: Parse-check**

```bash
php -l app/Services/Onboarding/OnboardingStateMachine.php
```

Expected: clean.

---

### Task C6: Add `capture_charitable_giving` to `OnboardingPromptBuilder::toolsForFocus('savetax')`

**Files:**
- Modify: `app/Services/Onboarding/OnboardingPromptBuilder.php`

- [ ] **Step 1: Add to the array**

```php
'savetax' => [
    'create_pension',
    'capture_salary_sacrifice',
    'capture_pension_history',
    'capture_charitable_giving',  // NEW
    'create_savings_account',
    'create_investment_account',
    'create_holding',
    'capture_spouse_work_status',
    'capture_spouse_household_data',
    'capture_spouse_non_working_assets',
],
```

- [ ] **Step 2: Parse-check**

---

### Task C7: `GiftAidHigherRateReliefStrategy` class

**Files:**
- Create: `app/Services/Tax/Strategies/GiftAidHigherRateReliefStrategy.php`

- [ ] **Step 1: Write the class**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;

/**
 * Strategy #13 — Gift Aid Higher-Rate Relief.
 *
 * Fires when the user is in the higher or additional band AND has captured
 * a positive annual_charitable_donations figure. Personal saving is the
 * extra relief they can reclaim via Self Assessment on top of basic-rate
 * Gift Aid the charity already reclaims:
 *   - higher band:     donations × 0.25
 *   - additional band: donations × 0.3125
 */
final class GiftAidHigherRateReliefStrategy implements TaxStrategy
{
    private const HIGHER_RATE_FACTOR = 0.25;

    private const ADDITIONAL_RATE_FACTOR = 0.3125;

    public function __construct(
        private readonly TaxStrategyMath $math,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;

        $donations = (float) ($user->annual_charitable_donations ?? 0);
        if ($donations <= 0) {
            return [];
        }

        $band = $this->math->bandFromIncome($this->math->taxableIncomeFor($user));
        $factor = match ($band) {
            'higher' => self::HIGHER_RATE_FACTOR,
            'additional' => self::ADDITIONAL_RATE_FACTOR,
            default => 0.0,
        };

        if ($factor <= 0) {
            return [];
        }

        $saving = $donations * $factor;
        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'gift_aid_higher_rate_relief',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Reclaim £%s on your Gift Aid donations via Self Assessment',
                number_format((int) round($saving)),
            ),
            description: sprintf(
                'You give around £%s a year through Gift Aid. The charity already reclaims basic-rate tax — but as a %s-rate taxpayer you can claim back another £%s yourself when you file your Self Assessment.',
                number_format((int) $donations),
                $band === 'additional' ? 'additional' : 'higher',
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: [
                'annual_donations' => round($donations, 2),
                'reclaim_factor' => $factor,
                'tax_band' => $band,
            ],
        )];
    }
}
```

- [ ] **Step 2: Parse-check**

---

### Task C8: Wire `GiftAidHigherRateReliefStrategy` into the calculator

**Files:**
- Modify: `app/Services/Tax/TaxStrategyCalculator.php`

- [ ] **Step 1: Add to constructor (after `$pensionAACarryForward`)**

```php
private readonly Strategies\GiftAidHigherRateReliefStrategy $giftAidHigherRate,
```

- [ ] **Step 2: Add to `$strategies` array**

After `$this->pensionAACarryForward`:
```php
$this->giftAidHigherRate,
```

- [ ] **Step 3: Parse-check**

---

### Task C9: Tests for the strategy + handler

**Files:**
- Modify: `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php`
- Modify: the relevant CoordinatingAgent test file (same file used in B9)

- [ ] **Step 1: Strategy tests**

`describe('Strategy #13 — Gift Aid Higher-Rate Relief', ...)` block:

1. `it('does not fire for basic-rate users')` — basic-rate income, donations = 500, expect no rec.
2. `it('does not fire when donations are zero or null')` — higher-rate income, donations = 0, expect no rec.
3. `it('fires for higher-rate user with correct 25% factor')` — income = 80000, donations = 1000, expect saving = 250, factor = 0.25.
4. `it('fires for additional-rate user with correct 31.25% factor')` — income = 200000, donations = 1000, expect saving = 312.5, factor = 0.3125.
5. `it('rounds saving correctly when below £1')` — donations = 0.5, higher-rate, expect no rec (saving < £1 floor).

- [ ] **Step 2: Handler tests**

`describe('handleCaptureCharitableGiving', ...)`:

1. `it('writes annual_charitable_donations to the user')` — call handler with 500, assert `$user->fresh()->annual_charitable_donations === '500.00'`.
2. `it('rejects negative amounts')` — pass -100, expect validation_failed.
3. `it('overwrites previous value')` — set to 500, then 200, assert latest persists.

- [ ] **Step 3: Run filtered tests**

```bash
./vendor/bin/pest --filter="Gift Aid|handleCaptureCharitableGiving" --colors=never
```

Expected: green.

- [ ] **Step 4: Run the full suite (Tax + Architecture + Agents)**

```bash
./vendor/bin/pest tests/Unit/Services/Tax/ tests/Unit/Agents/ --testsuite=Architecture --colors=never
```

Expected: green, no regressions.

---

### Task C10: Live browser verification

- [ ] **Step 1: Seed**

```bash
php artisan tinker --execute="
\$u = \App\Models\User::where('email','john@example.com')->first();
\$u->update(['annual_employment_income' => 80000, 'annual_charitable_donations' => 1200]);
echo 'seeded';
"
```

- [ ] **Step 2: Open Playwright, login, navigate to `/tax-strategy`**

Expected card: "Reclaim £300 on your Gift Aid donations via Self Assessment" (1200 × 0.25 = 300).

- [ ] **Step 3: API check**

```bash
curl -s -H "Cookie: $(cat /tmp/john-cookie 2>/dev/null)" http://localhost:8000/api/tax-strategy | jq '.recommendations[] | select(.type == "gift_aid_higher_rate_relief")'
```

Expected: `estimated_annual_tax_saved = 300`, `reclaim_factor = 0.25`, `tax_band = "higher"`.

If anything is off, LOOP UNTIL CORRECT (Rule #15) — diagnose, fix, re-verify.

---

### Task C11: Commit Phase C

- [ ] **Step 1: Stage and commit**

```bash
git add app/ database/migrations/2026_05_05_000002_add_charitable_donations_to_users.php tests/
git commit -m "$(cat <<'EOF'
feat(tax): SaveTax Phase 4 — Gift Aid Higher-Rate Relief (#13)

- Migration: users.annual_charitable_donations decimal(12,2) nullable
- Tool: capture_charitable_giving (Anthropic + xAI parity)
- Handler: handleCaptureCharitableGiving in CoordinatingAgent
- State: STATE_CAMPAIGN_CHARITABLE_GIVING after PENSION_HISTORY,
  before SPOUSE_WORK
- Strategy: GiftAidHigherRateReliefStrategy fires for higher- and
  additional-rate users with positive annual_charitable_donations.
  Saving = donations × 0.25 (higher) or × 0.3125 (additional).

Live-verified on john@example.com (£80k income, £1,200 donations):
  Surfaces "Reclaim £300 on your Gift Aid donations" with £300 badge.
EOF
)"
```

- [ ] **Step 2: Push**

```bash
git push origin feature/fyn-persona-split
```

---

## Out of scope for Phase 4

The following items are **deferred** to a separate session — do NOT touch them in this plan even if the diff brushes against them:

- **W-1, W-2, S-2, S-3** tech-debt items from the session 119 CSJTODO (dividend/income/CGT-rate helper extraction; magic threshold extraction; Junior Pension constant lift). These can be picked up after Phase 4 ships.
- **Phase 5 — composed-income views for the tapered AA warning (#14).** Requires `adjusted_income` and `threshold_income` views; not part of this plan.
- **Cumulative dev deploy** to csjones.co/fynla — separate session per the deploy section of CSJTODO. The Phase 4 commits will join that queue.
- **Test file split** — `TaxStrategyCalculatorTest.php` is now 884 lines and will grow further. Consider splitting per-strategy test files in a follow-up tech-debt sweep.

---

## Self-review checklist (run before execution)

- [ ] Every task references at least one file path with line numbers where source exists.
- [ ] Every code block in the plan is complete — no `// TODO` or `// fill in` markers.
- [ ] Every type, method, and property referenced later is defined earlier in the plan.
- [ ] Each phase has its own commit and the commit message reflects only that phase.
- [ ] Acceptance criteria at the top of the document map to specific tests / browser checks at the bottom of each phase.
- [ ] No assumption that the user reads the file from top to bottom — each task is self-contained.
