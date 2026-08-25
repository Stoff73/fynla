# SaveTax Tech-Debt Sweep + Phase 5 (Tapered AA) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bundle the carried-forward tech-debt items (W-1, W-2, S-1, S-2) into a single sweep, then add the 11th SaveTax strategy (`TaperedAnnualAllowanceStrategy`, #14 in the catalogue) on the cleaned base.

**Architecture:** Two commits.

1. **Tech-debt sweep** — refactor `TaxStrategyMath` to source income-tax band rates and dividend-tax rates from `TaxConfigService` rather than hardcoded match expressions. Refactor 3 dividend-using strategies to call the new `dividendRateForBand` helper. Delete two dead variable assignments (S-1, S-2). Add an HMRC-source comment for the Junior Pension £2,880/£720 constants in `LifecycleStrategy` (full config exposure deferred per CSJTODO S-3).
2. **Phase 5 — Tapered AA warning** — add three composed-income helpers to `TaxStrategyMath` (`thresholdIncomeFor`, `adjustedIncomeFor`, `employerPensionContributionsFor`). Create `TaperedAnnualAllowanceStrategy` that fires when **BOTH** `adjusted_income > £260k` AND `threshold_income > £200k` (HMRC dual-gate per CSJ redline). Render as a `Warning`-category, `High`-priority recommendation that surfaces FIRST on the dashboard (sortWeight = 0). Wire into `TaxStrategyCalculator` constructor + registry. Tests live in `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` under a new `describe('Phase 5 — Tapered Annual Allowance (#14)', ...)` block.

**Tech Stack:** PHP 8.2 strict types, Pest (PHPUnit-compatible), Laravel 10 service container, Eloquent (User / DCPension models), `App\Services\TaxConfigService`.

---

## File Structure

**Create**
- `app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php` — new strategy class implementing `Contract\TaxStrategy`.

**Modify**
- `app/Services/Tax/TaxStrategyMath.php` — add `bandRateFor(User|string)` config-sourced rewrite (W-2), `dividendRateForBand(string $band)` helper (W-1), `thresholdIncomeFor(User)`, `adjustedIncomeFor(User)`, `employerPensionContributionsFor(User)` (Phase 5).
- `app/Services/Tax/Strategies/IncomeBandStrategy.php` — delete dead `$personalAllowance` line (S-1).
- `app/Services/Tax/Strategies/LifecycleStrategy.php` — delete dead `$pension` line (S-2), add HMRC source comment to Junior Pension constants.
- `app/Services/Tax/Strategies/DividendAllowanceHarvestStrategy.php` — replace inline dividend match with `$this->math->dividendRateForBand($userBand)` (W-1).
- `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php` — replace inline dividend match (W-1).
- `app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php` — replace inline dividend match (W-1).
- `app/Services/Tax/TaxStrategyCalculator.php` — inject + register `TaperedAnnualAllowanceStrategy` (Phase 5).
- `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` — add Phase 5 describe block.

**No migrations, no new model, no frontend changes.** Every config value Phase 5 needs (`pension.tapered_annual_allowance.threshold_income / adjusted_income / minimum_allowance / taper_rate`) is already in `TaxConfigurationSeeder` for 2025/26 (verified: `database/seeders/TaxConfigurationSeeder.php:240-246`).

---

## Task 1: W-2 — Source income-tax band rates from TaxConfigService

**Files:**
- Modify: `app/Services/Tax/TaxStrategyMath.php:76-83`

The current `bandRateFor()` returns hardcoded `0.20/0.40/0.45`. Rule #3 requires sourcing tax values from `TaxConfigService`. The seeder already exposes per-band rates at `income_tax.bands[*].rate` (verified: `database/seeders/TaxConfigurationSeeder.php:90-106`). Read those instead.

Note: keep the existing public signature `bandRateFor(User $user): float`. Add a private helper `bandRateForBand(string $band): float` that the public method (and Phase 5 strategy) can call.

- [ ] **Step 1: Update `TaxStrategyMath::bandRateFor` to read from config**

In `app/Services/Tax/TaxStrategyMath.php`, replace lines 76-83 with:

```php
public function bandRateFor(User $user): float
{
    return $this->bandRateForBand(
        $this->bandFromIncome((float) ($user->annual_employment_income ?? 0))
    );
}

/**
 * Marginal income-tax rate for a given band ('basic' / 'higher' /
 * 'additional'), sourced from TaxConfigService['income_tax']['bands'].
 * Falls back to HMRC 2025/26 defaults only if the band can't be matched
 * (defensive — config seeder always populates all three bands).
 */
public function bandRateForBand(string $band): float
{
    $bands = $this->taxConfig->getIncomeTax()['bands'] ?? [];
    $needle = strtolower($band);

    foreach ($bands as $row) {
        $name = strtolower((string) ($row['name'] ?? ''));
        if (str_contains($name, $needle)) {
            return (float) ($row['rate'] ?? 0);
        }
    }

    return match ($needle) {
        'basic' => 0.20,
        'higher' => 0.40,
        'additional' => 0.45,
        default => 0.20,
    };
}
```

- [ ] **Step 2: Run targeted test to confirm no regression**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php --filter="band\|carry_forward\|gift_aid" -v`

Expected: ALL existing band-rate-dependent tests stay green (Phase 4 carry-forward asserts `marginal_rate => 0.4` for higher-rate, `0.45` for additional-rate; if those break the helper is wrong).

---

## Task 2: W-1 — Extract `dividendRateForBand` helper into `TaxStrategyMath`

**Files:**
- Modify: `app/Services/Tax/TaxStrategyMath.php` (add public method)

Three strategies (`DividendAllowanceHarvestStrategy:51-56`, `AssetShiftingBundleStrategy:128-134`, `CrossSpouseBundleStrategy:41-45`) each contain the same `match($band) → dividend rate` block sourced from `$div = $this->taxConfig->getDividendTax();`. Centralise it.

- [ ] **Step 1: Add `dividendRateForBand` to `TaxStrategyMath`**

Append to `app/Services/Tax/TaxStrategyMath.php`, before the closing `}`, after the existing `ageOf()` method (~line 165):

```php
/**
 * Dividend tax rate for a given band, sourced from
 * TaxConfigService['dividend_tax']. Centralises the match block previously
 * duplicated across DividendAllowanceHarvestStrategy, AssetShiftingBundle-
 * Strategy, and CrossSpouseBundleStrategy.
 */
public function dividendRateForBand(string $band): float
{
    $div = $this->taxConfig->getDividendTax();

    return match (strtolower($band)) {
        'higher' => (float) ($div['higher_rate'] ?? 0.3375),
        'additional' => (float) ($div['additional_rate'] ?? 0.3935),
        default => (float) ($div['basic_rate'] ?? 0.0875),
    };
}
```

- [ ] **Step 2: No test run yet — refactor sites first (Task 3)**

---

## Task 3: W-1 — Refactor 3 strategies to use `dividendRateForBand`

**Files:**
- Modify: `app/Services/Tax/Strategies/DividendAllowanceHarvestStrategy.php:51-56`
- Modify: `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php:128-134`
- Modify: `app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php:41-45`

- [ ] **Step 1: `DividendAllowanceHarvestStrategy`**

In `app/Services/Tax/Strategies/DividendAllowanceHarvestStrategy.php`, replace lines 51-56:

```php
        $divRate = match ($userBand) {
            'basic' => (float) ($div['basic_rate'] ?? 0.0875),
            'higher' => (float) ($div['higher_rate'] ?? 0.3375),
            'additional' => (float) ($div['additional_rate'] ?? 0.3935),
            default => 0.0875,
        };
```

with:

```php
        $divRate = $this->math->dividendRateForBand($userBand);
```

- [ ] **Step 2: `AssetShiftingBundleStrategy`**

In `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php`, replace lines 128-135 (the `$userDivRate = match (...)` block plus the immediately following `$spouseDivRate = (float) ($div['basic_rate'] ?? 0.0875);`):

```php
            $userDividends = (float) ($user->annual_dividend_income ?? 0);
            $userDivRate = match ($this->math->bandFromIncome((float) ($user->annual_employment_income ?? 0))) {
                'basic' => (float) ($div['basic_rate'] ?? 0.0875),
                'higher' => (float) ($div['higher_rate'] ?? 0.3375),
                'additional' => (float) ($div['additional_rate'] ?? 0.3935),
                default => 0.0875,
            };
            $spouseDivRate = (float) ($div['basic_rate'] ?? 0.0875);
```

with:

```php
            $userDividends = (float) ($user->annual_dividend_income ?? 0);
            $userBand = $this->math->bandFromIncome((float) ($user->annual_employment_income ?? 0));
            $userDivRate = $this->math->dividendRateForBand($userBand);
            $spouseDivRate = $this->math->dividendRateForBand('basic');
```

The `$div = $this->taxConfig->getDividendTax();` line at 122 is still needed for the `$divAllowance` lookup — leave it.

- [ ] **Step 3: `CrossSpouseBundleStrategy`**

In `app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php`, replace lines 40-46 (the `$div = $this->taxConfig->getDividendTax();` line PLUS the `$userDivRate = match ($userBand) { ... }` block PLUS the `$spouseDivRate = ...` line):

```php
            $div = $this->taxConfig->getDividendTax();
            $userDivRate = match ($userBand) {
                'higher' => (float) ($div['higher_rate'] ?? 0.3375),
                'additional' => (float) ($div['additional_rate'] ?? 0.3935),
                default => (float) ($div['basic_rate'] ?? 0.0875),
            };
            $spouseDivRate = (float) ($div['basic_rate'] ?? 0.0875);
```

with:

```php
            $userDivRate = $this->math->dividendRateForBand($userBand);
            $spouseDivRate = $this->math->dividendRateForBand('basic');
```

- [ ] **Step 4: Run dividend-touching tests to confirm no regression**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php -v`

Expected: ALL Tax tests stay green (assertions in the Phase 2 dividend section + the household section validate the rates explicitly).

---

## Task 4: S-1 — Delete dead `$personalAllowance` in `IncomeBandStrategy`

**Files:**
- Modify: `app/Services/Tax/Strategies/IncomeBandStrategy.php:33`

This variable is assigned but never read (the strategy uses `$taperThreshold` and `$additionalRateThreshold` only). Delete it.

- [ ] **Step 1: Delete the dead line**

In `app/Services/Tax/Strategies/IncomeBandStrategy.php`, remove line 33:

```php
        $personalAllowance = (float) ($income['personal_allowance'] ?? 12570);
```

(Surrounding lines 31-34 should now read: `$income = $this->taxConfig->getIncomeTax();`, `$taperThreshold = ...`, `$additionalRateThreshold = ...`.)

- [ ] **Step 2: Run targeted test**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php --filter="income-band\|taper\|additional-rate" -v`

Expected: PASS — line was unreferenced.

---

## Task 5: S-2 — Delete dead `$pension` in `LifecycleStrategy` + add HMRC source comment

**Files:**
- Modify: `app/Services/Tax/Strategies/LifecycleStrategy.php:30, 112-116`

The strategy reads `$isa = $this->taxConfig->getISAAllowances();` for ISA values but the `$pension = $this->taxConfig->getPensionAllowances();` line is unused — Junior Pension constants are inlined as `2880.0` / `720.0`. Per CSJTODO, full config exposure is deferred (S-3); this task just removes the dead var and adds an inline HMRC-source comment so future readers know where the constants come from.

- [ ] **Step 1: Delete dead variable + extend comment**

In `app/Services/Tax/Strategies/LifecycleStrategy.php`, remove line 30:

```php
        $pension = $this->taxConfig->getPensionAllowances();
```

Then update the comment at lines 112-114 (above `$juniorPensionNet = 2880.0;`) from:

```php
            // #18 — Junior Pension. £2,880 net per child grossed up to £3,600
            // (25% tax relief). Use auto_enrolment / pension config; spec
            // gives £2,880 net + £720 uplift per child.
```

to:

```php
            // #18 — Junior Pension. £2,880 net per child grossed up to £3,600
            // by HMRC (£720 = 20% basic-rate relief grossed onto an £2,880 net
            // contribution; HMRC pension input cap for non-earners). Hardcoded
            // here as TaxConfigService doesn't yet expose junior_pension caps —
            // surface via config in a future sprint (CSJTODO S-3).
```

- [ ] **Step 2: Run targeted test**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php --filter="lifecycle\|junior" -v`

Expected: PASS — line was unreferenced; comment change has no behavioural impact.

---

## Task 6: Tech-debt sweep — full Tax sweep + Pint + commit

**Files:** All from Tasks 1-5.

- [ ] **Step 1: Full Tax + Architecture sweep**

Run: `./vendor/bin/pest tests/Unit/Services/Tax tests/Unit/Architecture --stop-on-failure`

Expected: 100% pass (190 Tax tests carried over from session 120 + Architecture suite). If anything red, debug + fix BEFORE committing — Rule #15.

- [ ] **Step 2: Pint format**

Run: `./vendor/bin/pint app/Services/Tax/`

Expected: Either "Nothing to fix" or auto-fixes applied. Re-run sweep if Pint changed anything.

- [ ] **Step 3: Stage and commit**

```bash
git add app/Services/Tax/TaxStrategyMath.php \
        app/Services/Tax/Strategies/IncomeBandStrategy.php \
        app/Services/Tax/Strategies/LifecycleStrategy.php \
        app/Services/Tax/Strategies/DividendAllowanceHarvestStrategy.php \
        app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php \
        app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php
git commit -m "$(cat <<'EOF'
refactor(tax): tech-debt sweep — band rates from config + dividend helper (S-1, S-2, W-1, W-2)

W-2: TaxStrategyMath::bandRateFor now reads income-tax band rates from
TaxConfigService['income_tax']['bands'][*].rate instead of hardcoded
0.20/0.40/0.45. Adds private bandRateForBand(string $band) helper that
Phase 5's TaperedAnnualAllowanceStrategy will reuse.

W-1: New public dividendRateForBand($band) helper on TaxStrategyMath.
Refactors DividendAllowanceHarvestStrategy, AssetShiftingBundleStrategy,
and CrossSpouseBundleStrategy to call it — drops 3 duplicated match
blocks. Single config-sourced site, Rule #3 compliant.

S-1: Removes unused $personalAllowance assignment in IncomeBandStrategy.
S-2: Removes unused $pension assignment in LifecycleStrategy; extends
the Junior Pension comment to cite the HMRC £2,880/£720 source.

Behaviour preserved: 190/190 Tax sweep + Architecture suite green.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 4: Verify commit**

Run: `git log -1 --stat`

Expected: ~6 files changed, all under `app/Services/Tax/`.

---

## Task 7: Phase 5 — Add composed-income helpers to `TaxStrategyMath`

**Files:**
- Modify: `app/Services/Tax/TaxStrategyMath.php` (append three public methods)

Composed views per CSJ redline:
- **`thresholdIncomeFor(User)`** — sum of all taxable income fields on `users` (`annual_employment_income + annual_self_employment_income + annual_rental_income + annual_dividend_income + estimated_savings_interest + annual_other_income + annual_trust_income`). Does NOT subtract any pension contribution. V1 simplification — does not yet handle salary-sacrifice anti-forestalling addback (defer to a future spec).
- **`adjustedIncomeFor(User)`** — `thresholdIncomeFor(User) + employerPensionContributionsFor(User)`. HMRC adjusted-income definition for tapered AA.
- **`employerPensionContributionsFor(User)`** — sum across all `DCPension` rows of `(annual_salary ?? user.annual_employment_income) × (employer_contribution_percent / 100)`. If `employer_contribution_percent` is null, contributes 0 from that pension.

- [ ] **Step 1: Append helpers to `TaxStrategyMath`**

In `app/Services/Tax/TaxStrategyMath.php`, append after the new `dividendRateForBand` method (added in Task 2):

```php
/**
 * Threshold income for tapered AA — sum of all taxable income fields on
 * the User row, with no pension-contribution deduction. V1 simplification:
 * does not handle salary-sacrifice anti-forestalling addback (HMRC rule
 * for sacrifices on/after 9 July 2015). Acceptable today; revisit if a
 * persona-driven false-negative appears.
 */
public function thresholdIncomeFor(User $user): float
{
    return (float) ($user->annual_employment_income ?? 0)
        + (float) ($user->annual_self_employment_income ?? 0)
        + (float) ($user->annual_rental_income ?? 0)
        + (float) ($user->annual_dividend_income ?? 0)
        + $this->estimateAnnualInterest($user)
        + (float) ($user->annual_other_income ?? 0)
        + (float) ($user->annual_trust_income ?? 0);
}

/**
 * Adjusted income for tapered AA — threshold income plus employer
 * pension contributions added back. Used as the £260k gate for the
 * tapered Annual Allowance.
 */
public function adjustedIncomeFor(User $user): float
{
    return $this->thresholdIncomeFor($user) + $this->employerPensionContributionsFor($user);
}

/**
 * Total annual employer pension contributions across all DC pensions,
 * estimated as (annual_salary ?? user employment income) × employer_pct.
 * Pensions with null employer_contribution_percent contribute 0.
 */
public function employerPensionContributionsFor(User $user): float
{
    $userIncome = (float) ($user->annual_employment_income ?? 0);

    return (float) DCPension::query()
        ->where('user_id', $user->id)
        ->whereNotNull('employer_contribution_percent')
        ->get(['annual_salary', 'employer_contribution_percent'])
        ->sum(function ($p) use ($userIncome) {
            $base = (float) ($p->annual_salary ?? 0) > 0
                ? (float) $p->annual_salary
                : $userIncome;

            return $base * ((float) $p->employer_contribution_percent / 100);
        });
}
```

- [ ] **Step 2: No test run yet — exercised by Task 10's Phase 5 tests**

---

## Task 8: Phase 5 — Create `TaperedAnnualAllowanceStrategy`

**Files:**
- Create: `app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php`

Strategy logic:
1. Read `pension.tapered_annual_allowance` from `TaxConfigService` (`threshold_income`, `adjusted_income` (or `adjusted_income_threshold`), `minimum_allowance`, `taper_rate`).
2. Compute `threshold = math.thresholdIncomeFor(user)` and `adjusted = math.adjustedIncomeFor(user)`.
3. **DUAL GATE per CSJ redline**: fire ONLY when `threshold > threshold_income_gate` AND `adjusted > adjusted_income_gate`. Either gate alone returns `[]`.
4. Tapered AA = `max(annual_allowance - taper_rate × (adjusted - adjusted_income_gate), minimum_allowance)`.
5. Render as a `Warning`-category, `High`-priority recommendation. `estimatedAnnualTaxSaved` carries the avoided HMRC charge: `(annual_allowance - tapered_aa) × marginalRate` — the tax cost the user would face if they contributed the full untapered AA without realising the taper applied.

- [ ] **Step 1: Create the strategy class**

Create `app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategy #14 — Tapered Annual Allowance warning.
 *
 * Fires when BOTH HMRC tapered-AA gates are breached:
 *   - threshold income > £200,000  (employment + bonus + other taxable
 *     income, no pension addback)
 *   - adjusted income  > £260,000  (threshold income + employer pension
 *     contributions added back)
 *
 * Either gate alone returns []. The dual-gate is HMRC's actual rule —
 * users above £260k adjusted but below £200k threshold (rare, e.g. heavy
 * employer contributions on modest salary) are NOT tapered.
 *
 * Tapered AA = max(annual_allowance − taper_rate × (adjusted − £260k),
 *                  minimum_allowance £10k).
 *
 * Surfaces as Warning category (sortWeight 0 — first on the dashboard)
 * and High priority because contributing the untapered AA when the taper
 * applies triggers an HMRC Annual Allowance charge at the user's marginal
 * rate. estimated_annual_tax_saved carries that avoided charge.
 */
final class TaperedAnnualAllowanceStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $pension = $this->taxConfig->getPensionAllowances();
        $taper = $pension['tapered_annual_allowance'] ?? [];

        $thresholdGate = (float) ($taper['threshold_income'] ?? 200000);
        $adjustedGate = (float) ($taper['adjusted_income_threshold']
            ?? $taper['adjusted_income']
            ?? 260000);
        $minimumAllowance = (float) ($taper['minimum_allowance'] ?? 10000);
        $taperRate = (float) ($taper['taper_rate'] ?? 0.5);
        $annualAllowance = (float) ($pension['annual_allowance'] ?? 60000);

        $threshold = $this->math->thresholdIncomeFor($user);
        $adjusted = $this->math->adjustedIncomeFor($user);

        // DUAL GATE — both must be exceeded for the taper to apply.
        if ($threshold <= $thresholdGate || $adjusted <= $adjustedGate) {
            return [];
        }

        $excessAdjusted = $adjusted - $adjustedGate;
        $taperedAa = max($minimumAllowance, $annualAllowance - $taperRate * $excessAdjusted);
        $aaReduction = $annualAllowance - $taperedAa;

        if ($aaReduction <= 0) {
            return [];
        }

        $marginalRate = $this->math->bandRateFor($user);
        $avoidedCharge = $aaReduction * $marginalRate;

        return [new StrategyRecommendation(
            type: 'tapered_annual_allowance',
            category: StrategyCategory::Warning,
            priority: StrategyPriority::High,
            title: sprintf(
                'Your Pension Annual Allowance is tapered to £%s',
                number_format((int) round($taperedAa / 1000) * 1000),
            ),
            description: sprintf(
                'Your adjusted income of £%s exceeds the £%s tapered AA threshold and your threshold income of £%s exceeds £%s, so HMRC reduces your Pension Annual Allowance by £1 for every £2 over £%s — down to £%s this year (floor £%s). Contributing the standard £%s allowance would trigger an Annual Allowance charge of around £%s at your marginal rate.',
                number_format((int) $adjusted),
                number_format((int) $adjustedGate),
                number_format((int) $threshold),
                number_format((int) $thresholdGate),
                number_format((int) $adjustedGate),
                number_format((int) round($taperedAa / 1000) * 1000),
                number_format((int) $minimumAllowance),
                number_format((int) $annualAllowance),
                number_format((int) round($avoidedCharge)),
            ),
            estimatedAnnualTaxSaved: round($avoidedCharge, 2),
            extra: [
                'threshold_income' => round($threshold, 2),
                'adjusted_income' => round($adjusted, 2),
                'threshold_income_gate' => $thresholdGate,
                'adjusted_income_gate' => $adjustedGate,
                'standard_annual_allowance' => $annualAllowance,
                'tapered_annual_allowance' => round($taperedAa, 2),
                'minimum_allowance' => $minimumAllowance,
                'taper_rate' => $taperRate,
                'aa_reduction' => round($aaReduction, 2),
                'marginal_rate' => $marginalRate,
            ],
        )];
    }
}
```

- [ ] **Step 2: Pint format the new file**

Run: `./vendor/bin/pint app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php`

Expected: "Nothing to fix" or auto-formatted.

---

## Task 9: Phase 5 — Wire `TaperedAnnualAllowanceStrategy` into `TaxStrategyCalculator`

**Files:**
- Modify: `app/Services/Tax/TaxStrategyCalculator.php:30-45, 67-80`

- [ ] **Step 1: Inject the new strategy in the constructor**

In `app/Services/Tax/TaxStrategyCalculator.php`, the constructor lines 30-45. Add `TaperedAnnualAllowanceStrategy` after `GiftAidHigherRateReliefStrategy` (alphabetical-ish, keeps Phase 4 + 5 together):

Replace:

```php
        private readonly Strategies\GiftAidHigherRateReliefStrategy $giftAidHigherRate,
        private readonly Strategies\NonEarnerSpousePensionStrategy $nonEarnerSpousePension,
```

with:

```php
        private readonly Strategies\GiftAidHigherRateReliefStrategy $giftAidHigherRate,
        private readonly Strategies\TaperedAnnualAllowanceStrategy $taperedAnnualAllowance,
        private readonly Strategies\NonEarnerSpousePensionStrategy $nonEarnerSpousePension,
```

- [ ] **Step 2: Register in the strategy array**

In the same file, the registry array at lines 67-80. Add `$this->taperedAnnualAllowance` to the registry — placement does NOT matter for correctness (sort happens by category sortWeight) but mirror the constructor order for readability:

Replace:

```php
        $strategies = [
            $this->incomeBand,
            $this->lifecycle,
            $this->jointSavings,
            $this->isaTopUp,
            $this->dividendAllowance,
            $this->salarySacrifice,
            $this->bedAndIsa,
            $this->pensionAACarryForward,
            $this->giftAidHigherRate,
            $this->nonEarnerSpousePension,
            $this->crossSpouse,
            $this->assetShifting,
        ];
```

with:

```php
        $strategies = [
            $this->incomeBand,
            $this->lifecycle,
            $this->jointSavings,
            $this->isaTopUp,
            $this->dividendAllowance,
            $this->salarySacrifice,
            $this->bedAndIsa,
            $this->pensionAACarryForward,
            $this->giftAidHigherRate,
            $this->taperedAnnualAllowance,
            $this->nonEarnerSpousePension,
            $this->crossSpouse,
            $this->assetShifting,
        ];
```

- [ ] **Step 3: Smoke-run the existing test sweep — no new tests yet, just confirm wiring didn't break anything**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php --stop-on-failure`

Expected: ALL existing tests still pass — the new strategy is registered but its preconditions (both gates breached) won't fire on any existing test fixture.

---

## Task 10: Phase 5 — Strategy tests in `TaxStrategyCalculatorTest`

**Files:**
- Modify: `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` (append new describe block)

- [ ] **Step 1: Append the Phase 5 describe block**

In `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php`, append a new `describe` block AFTER the existing `describe('Phase 4 — Gift Aid Higher-Rate Relief (#13)', ...)` block (which ends at the `});` on line ~1088) and BEFORE the `describe('overrides applied in-memory', ...)` block at line 1090:

```php
describe('Phase 5 — Tapered Annual Allowance (#14)', function () {
    it('does not fire when threshold income is at or below £200k', function () {
        // adjusted = £270k (employer pension addback) but threshold = £180k → no taper
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 180000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 180000,
            'employer_contribution_percent' => 50, // 90k addback → adjusted = 270k
            'employee_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'tapered_annual_allowance');
        expect($rec)->toBeNull();
    });

    it('does not fire when adjusted income is at or below £260k', function () {
        // threshold = £210k but no employer pension → adjusted = threshold = 210k
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 210000,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'tapered_annual_allowance');
        expect($rec)->toBeNull();
    });

    it('does not fire when both gates are exactly equal to thresholds', function () {
        // strict > comparison — gate equality should not fire
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 200000, // threshold = 200000 (gate)
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 200000,
            'employer_contribution_percent' => 30, // +60k → adjusted = 260000 (gate)
            'employee_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'tapered_annual_allowance');
        expect($rec)->toBeNull();
    });

    it('fires when both gates are breached and reports the tapered AA', function () {
        // threshold = 220k (>200k) AND adjusted = 280k (>260k)
        // taper = max(60k - 0.5 × (280k - 260k), 10k) = max(60k - 10k, 10k) = 50k
        // aa_reduction = 60k - 50k = 10k; marginal rate = 0.45 (additional)
        // avoided charge = 10k × 0.45 = 4500
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 220000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 220000,
            'employer_contribution_percent' => round(60000 / 220000 * 100, 4), // ~27.2727 → 60k addback
            'employee_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'tapered_annual_allowance');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('warning')
            ->and($rec['priority'])->toBe('high')
            ->and((float) $rec['tapered_annual_allowance'])->toBeGreaterThan(49000.0)
            ->and((float) $rec['tapered_annual_allowance'])->toBeLessThan(51000.0)
            ->and($rec['threshold_income_gate'])->toBe(200000.0)
            ->and($rec['adjusted_income_gate'])->toBe(260000.0)
            ->and($rec['standard_annual_allowance'])->toBe(60000.0)
            ->and($rec['minimum_allowance'])->toBe(10000.0)
            ->and($rec['taper_rate'])->toBe(0.5)
            ->and($rec['marginal_rate'])->toBe(0.45);
    });

    it('floors at the £10k minimum allowance for very high adjusted income', function () {
        // adjusted = 600k → untapered AA reduction = 0.5 × (600k - 260k) = 170k
        // 60k - 170k would go negative — floor at 10k minimum_allowance.
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 500000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 500000,
            'employer_contribution_percent' => 20, // 100k addback → adjusted = 600k
            'employee_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'tapered_annual_allowance');
        expect($rec)->not->toBeNull()
            ->and($rec['tapered_annual_allowance'])->toBe(10000.0)
            ->and($rec['aa_reduction'])->toBe(50000.0); // 60k - 10k floor
    });

    it('surfaces with sortWeight 0 — appears first in recommendations[]', function () {
        // High-income persona that ALSO triggers other strategies; verify the
        // tapered-AA warning sorts ahead of all others.
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 220000,
            'annual_charitable_donations' => 1000, // also fires gift_aid_higher_rate_relief
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 220000,
            'employer_contribution_percent' => round(60000 / 220000 * 100, 4),
            'employee_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(count($output->recommendations))->toBeGreaterThan(1);
        expect($output->recommendations[0]['type'])->toBe('tapered_annual_allowance');
        expect($output->recommendations[0]['category'])->toBe('warning');
    });
});
```

- [ ] **Step 2: Run Phase 5 tests in isolation**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php --filter="Phase 5" -v`

Expected: 6/6 PASS. If any fail, debug + fix BEFORE moving on (Rule #15 — diagnose, fix code, re-verify).

- [ ] **Step 3: Run the FULL Tax sweep**

Run: `./vendor/bin/pest tests/Unit/Services/Tax tests/Unit/Architecture --stop-on-failure`

Expected: ~196/196 PASS (190 carried over + 6 new). Architecture suite still green (no new files outside the existing `Strategies/` namespace pattern).

---

## Task 11: Phase 5 — Pint + commit

**Files:** All from Tasks 7-10.

- [ ] **Step 1: Pint format**

Run: `./vendor/bin/pint app/Services/Tax/ tests/Unit/Services/Tax/`

Expected: "Nothing to fix" or minor auto-fixes.

- [ ] **Step 2: Stage and commit**

```bash
git add app/Services/Tax/TaxStrategyMath.php \
        app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php \
        app/Services/Tax/TaxStrategyCalculator.php \
        tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php
git commit -m "$(cat <<'EOF'
feat(tax): SaveTax Phase 5 — Tapered Annual Allowance warning (#14)

11th SaveTax strategy. Fires when BOTH HMRC tapered-AA gates are
breached: threshold_income > £200k AND adjusted_income > £260k. Either
gate alone returns [] — dual-gate is HMRC's actual rule.

Composed-income helpers on TaxStrategyMath:
- thresholdIncomeFor(User): sum of all taxable income fields (no pension
  addback). V1 simplification — no salary-sacrifice anti-forestalling.
- adjustedIncomeFor(User): threshold + employer pension contributions.
- employerPensionContributionsFor(User): sum of (annual_salary
  ?? employment_income) × employer_pct across all DC pensions.

TaperedAnnualAllowanceStrategy:
- Reads pension.tapered_annual_allowance from TaxConfigService
- Tapered AA = max(annual_allowance − taper_rate × (adjusted − £260k),
  minimum_allowance £10k)
- Warning category (sortWeight 0 → surfaces FIRST), High priority
- estimated_annual_tax_saved = aa_reduction × marginal_rate (the HMRC
  AA charge the user would face contributing the full untapered AA)

6 new strategy tests covering both-gates failure modes, dual breach,
£10k floor, and sort-order verification. Full Tax sweep 196/196
(was 190); Architecture suite green; Pint clean.

SaveTax dashboard now surfaces 17 deterministic strategies (all 17
catalogue entries from session 117 spec).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 3: Verify commit**

Run: `git log -1 --stat`

Expected: 4 files changed (TaxStrategyMath, new TaperedAnnualAllowanceStrategy, TaxStrategyCalculator, TaxStrategyCalculatorTest).

---

## Task 12: Phase 5 — Live browser verification (Rule #15)

Per Rule #15 LOOP UNTIL CORRECT, the strategy MUST be observed on the live SaveTax dashboard for an actual user before this task is GREEN.

**Test persona:** `peak_earners` preview persona — David & Sarah Mitchell are seeded with high incomes (>£200k combined) and SIPP holdings. From the personas spec they should hit the dual gate.

If `peak_earners` does not breach both gates with their seeded data, fall back to manually adjusting `john@example.com` via `tinker` for verification — but DO NOT commit the tinker change. Reseed (`php artisan db:seed`) after.

- [ ] **Step 1: Confirm dev server is running**

Run: `lsof -i :8000 | head -3`

If empty, start in background: `./dev.sh` (php artisan serve runs on :8000).

- [ ] **Step 2: Navigate to SaveTax dashboard via Playwright**

Browser sequence:
1. `mcp__playwright__browser_navigate` → `http://localhost:8000`
2. Click landing-page persona selector → `peak_earners` (David Mitchell)
3. Wait for redirect to dashboard
4. Navigate to `/save-tax` (or `/dashboard/save-tax` — whichever the router has)
5. `mcp__playwright__browser_snapshot` to capture the rendered dashboard

If `peak_earners` does not surface the tapered-AA card:
1. Logout, login as `john@example.com` (password `password`, fetch verification code from DB per CLAUDE.md "Authentication for Testing")
2. Use `tinker` to set `john`'s `annual_employment_income = 220000`, create a `DCPension` row with `annual_salary => 220000, employer_contribution_percent => 30` (gives adjusted = 220k + 66k = 286k → both gates breached)
3. Reload `/save-tax`
4. After verifying, run `php artisan db:seed --force` to restore john's seeded state

- [ ] **Step 3: Verify the card is rendered FIRST and contains expected copy**

Acceptance criteria (must observe ALL in the browser, not in code):
- [ ] A card with title "Your Pension Annual Allowance is tapered to £…" appears
- [ ] It is the FIRST card on the recommendations list (sortWeight 0 — Warning category)
- [ ] The card displays the tapered AA figure (between £10k and £60k)
- [ ] The card displays the avoided HMRC charge in the body copy
- [ ] No design-system violations: card is using existing recommendation-card component, no amber/orange colours

- [ ] **Step 4: Hit the API directly to confirm the JSON shape**

Run from a browser-side `mcp__playwright__browser_evaluate`:

```js
fetch('/api/tax-strategy', { credentials: 'include' })
  .then(r => r.json())
  .then(j => j.data.recommendations.filter(r => r.type === 'tapered_annual_allowance'))
```

Expected: array with one entry, fields including `tapered_annual_allowance`, `aa_reduction`, `threshold_income`, `adjusted_income`, `marginal_rate` (per the strategy's `extra` block).

- [ ] **Step 5: Smoke-check no regressions on lower-income personas**

1. Logout, login as `young_saver` (John Morgan — basic-rate, no DC pension above the gates)
2. Navigate to `/save-tax`
3. Confirm NO tapered-AA card appears
4. Confirm the existing Phase 1-4 cards still render

- [ ] **Step 6: If any criterion fails — LOOP per Rule #15**

Diagnose with file:line evidence (DB rows, API JSON, Vue render path), fix the code, re-verify in the browser. Do NOT declare green on partial evidence.

---

## Task 13: Update CSJTODO with session 121 close-out

**Files:**
- Modify: `April/April30Updates/CSJTODO.md`

- [ ] **Step 1: Append session 121 section to CSJTODO**

Replace the "## Session 120" header in `April/April30Updates/CSJTODO.md` with a new "## Session 121 (30 April 2026, late) — Tech-debt sweep + Phase 5 (#14)" section, listing both commits. Move the Phase 5 task from "Outstanding for next session" → "Completed this session". Preserve session 120's content as a previous-session entry.

Update headers:
- Line 1: `*Last updated: 30 April 2026 — session 121 (Tech-debt sweep + Phase 5 #14 shipped, commits ` + tech-debt SHA + `, ` + phase 5 SHA + `).*`
- Line 4: `*Previous session: 120 (S-1 refactor + SaveTax Phase 4 — #3 + #13).*`

Add "NOT done — Outstanding for next session":
- Cumulative dev deploy (sessions 112+113+114+115+117+118+119+120+121)
- Carried tech-debt: S-3 (Junior Pension config), S-4 / S-5 (whatever survives Phase 5 — likely the V1 simplifications in adjustedIncomeFor / thresholdIncomeFor: salary-sacrifice anti-forestalling addback, salary-sacrifice base reduction)

Update "Phase progression" table:
| Phase | Scope | Status |
| 5 | Tapered AA warning (#14, dual gate) | ✅ session 121 (`<sha>`) |

Update "Deploy Status" footer to reflect the new commits.

- [ ] **Step 2: Commit the CSJTODO update**

```bash
git add April/April30Updates/CSJTODO.md
git commit -m "$(cat <<'EOF'
docs: session 121 end — Tech-debt sweep + Phase 5 (#14)

CSJTODO close-out. Two commits this session:
- Tech-debt sweep (S-1, S-2, W-1, W-2)
- Phase 5: Tapered Annual Allowance warning (#14, dual-gate)

SaveTax catalogue is now complete: 17/17 deterministic strategies surface
on the dashboard. No new migrations/frontend.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 3: Verify**

Run: `git log -3 --oneline`

Expected: three commits — tech-debt, phase 5, CSJTODO. Branch up to date with `feature/fyn-persona-split` plus 3 new commits.

---

## Self-Review Notes

**Spec coverage check:**
- W-1 (dividend rate helper) → Tasks 2-3 ✓
- W-2 (band rates from config) → Task 1 ✓
- S-1 (dead `$personalAllowance`) → Task 4 ✓
- S-2 (dead `$pension`) → Task 5 ✓
- Phase 5 composed `adjusted_income` view → Task 7 ✓
- Phase 5 composed `threshold_income` view → Task 7 ✓
- Phase 5 dual-gate logic → Task 8 ✓
- Phase 5 priority `high` (warning class) → Task 8 (StrategyPriority::High) ✓
- Phase 5 new strategy class → Task 8 ✓
- Phase 5 wired into calculator registry → Task 9 ✓
- Phase 5 live browser walk on high-income persona → Task 12 ✓
- Phase 5 green Pest sweep → Tasks 6, 10 ✓
- Update CSJTODO → Task 13 ✓

**Deferred per CSJTODO:**
- S-3 (Junior Pension config exposure) — kept as carried debt; comment added (Task 5).
- Magic `> 1000` "worth recommending" threshold (S-2-carried) — out of scope; revisit when 4th similar bundle appears.
- Bundle strategies' `array_map(fn → fromArray)` pattern — defer until 3rd household bundle.

**Type consistency check:**
- `bandRateFor(User)` keeps existing signature ✓
- `bandRateForBand(string)` matches `bandFromIncome(float): string` output ('basic' / 'higher' / 'additional') ✓
- `dividendRateForBand(string)` accepts same band strings ✓
- `thresholdIncomeFor(User): float` and `adjustedIncomeFor(User): float` consistent with `taxableIncomeFor(User): float` pattern ✓
- `StrategyCategory::Warning` (sortWeight 0) — already in enum, surfaces first ✓
- `StrategyPriority::High` — already in enum ✓
- `tapered_annual_allowance` `type` field — matches Phase 1+ snake_case convention ✓

**Placeholder scan:** No `TBD`, no "TODO later", no "similar to Task N" — every step has the actual code/command. Pass.
