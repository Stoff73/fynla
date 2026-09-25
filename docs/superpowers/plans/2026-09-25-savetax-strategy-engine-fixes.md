# Save Tax Strategy Engine Fixes (Plan B) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (CSJ ruling 2026-09-24: plans run inline, no implementation subagents). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every item in the Save Tax plan appears only when the user qualifies for it. The headline "worth roughly £X a year" counts only real tax saved, and never counts the same allowance or interest twice.

**Architecture:** All fixes are in the tax strategy registry (`app/Services/Tax/Strategies/`), its shared maths (`TaxStrategyMath`), the plan composer's metadata (`TaxActionDefinitionSeeder`) and the public funnel's two questions. Every surface reads the same `ComposedTaxPlanService::forUser()` output: the web Tax Strategy page, `/m`, iOS and Fyn's "Here's your tax plan" synthesis. So each fix lands once for all of them (Rule 20). Items that are real but not tax saved keep their card and title, with a null `estimated_annual_tax_saved`. Every surface already hides the "saves £X" chip on null: web `StrategyRecommendationList.vue:35`, `/m` `TaxStrategy.vue:29`, iOS `TaxStrategyView.swift:250`, and the synthesis `OnboardingChatDirector.php:1672`.

**Amendment (CSJ 2026-09-25, plan approved):**
- The spouse pension top-up (both the non-earner and the modest-earner paths) keeps counting as tax saved: "a spouse is a legal contract". Only the junior pension uplift is excluded.
- The basic-rate pension relief item is sized at 10% of earnings, less what is already paid in, as planned.

**Tech Stack:** Laravel 10, Pest, MySQL 8. The public funnel is plain PHP and JS.

**Spec:** `September/September25Updates/savetax-outcomes-by-household-2026-09-25.md` (bugs B1–B14), plus the CSJ rulings in memory `project_tax_plan_howto_programme_2026_09_25` and the 2026-09-25 session-2 handover.

## Global Constraints

- Rule 2: no hardcoded tax values. Every rate, threshold and allowance comes from `TaxConfigService`, directly or through `TaxStrategyMath`. That includes figures inside description strings.
- Ruling (a): a suggestion appears only when the user qualifies, or has the income or asset it depends on. Only real tax saved counts toward the total. The Lifetime ISA bonus, the junior pension uplift, "unused Dividend Allowance" and the tapered allowance "charge avoided" are **not** tax saved. Nothing is double counted: ISA top-up and gift-to-spouse shelter the same interest, and Marriage Allowance and gift-to-spouse both use the spouse's Personal Allowance.
- Ruling (b): the public funnel says "spouse or civil partner". Unmarried partners get no Marriage Allowance and no spouse-transfer advice.
- Ruling (c): pension tax relief is suggested for every tax band.
- Keep the strategy `type` strings stable (`marriage_allowance_transfer`, `lifetime_isa`, …). They are Fyn provenance ids and Plan C's how-to keys.
- The Save Tax synthesis mirrors `composed_plan.items` (memory `feedback_savetax_synthesis_mirrors_dashboard`). Do not add a second list anywhere.
- User-facing text is British. No emoji or Unicode glyphs (Rule 15). No cold acronyms (Rule 9).
- `declare(strict_types=1);` in every PHP file. Pest uses `it()`/`describe()` and `RefreshDatabase`, and seeds `TaxConfigurationSeeder` in `beforeEach`.
- Never `migrate:fresh` or `--env=testing`. Never run two `pest` processes at once, because they share one testing database.
- Work in a worktree under the scratchpad on branch `fix/savetax-strategy-engine`, cut from `dev`. Copy `vendor` (never symlink it), then run `composer dump-autoload -o`.

## Review Focus

1. **A married basic-rate user with little taxable income** (for example £13,000) must see a Marriage Allowance saving capped at what they actually pay, not the full £252. Pinned in Task 4.
2. **A user with both an ISA top-up and a gift-to-spouse item** must have only the larger counted in the headline total, and the conflict note must name the other item by its title, not by an internal id like `isa_topup_vs_psa`. Pinned in Task 6.
3. **A non-earning spouse who already pays in the maximum** must not be told to top up again. **One who pays part** must be told the remainder only. Pinned in Task 3.
4. **A higher-rate taxpayer over the threshold** (£60,000) must get a pension item sized to the slice taxed at 40%, never to income taxed at 20%. **A £110,000 earner** must get only the Personal Allowance item, not a second pension item. Pinned in Task 7.
5. **Existing salary sacrifice users whose workplace pension was captured through the onboarding form** store `pension_type = occupational` with a null `scheme_type` and percentages only. They must now get the salary sacrifice item. Pinned in Task 1.

---

### Task 1: Salary sacrifice only on workplace pensions, priced from percentages (B1)

**Files:**
- Modify: `app/Services/Retirement/PensionContributionRule.php` (add `isWorkplace`, and an optional fallback salary on `monthlyEmployee`)
- Modify: `app/Services/Tax/Strategies/SalarySacrificeNiStrategy.php:42-52`
- Test: `tests/Unit/Services/Tax/Strategies/SalarySacrificeWorkplaceOnlyTest.php` (new)
- Modify: `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php:1196-1300`. Every `DCPension::factory()` call in the "Phase 3 — salary sacrifice" describe gets `'scheme_type' => 'workplace'`. The factory picks `scheme_type` at random from workplace/sipp/personal, so those tests would otherwise go red at random.

**Interfaces:**
- Produces: `PensionContributionRule::isWorkplace(DCPension $pension): bool` and `PensionContributionRule::monthlyEmployee(DCPension $pension, float $fallbackSalary = 0.0): float`. The existing two-argument-free callers in `UserProfileService` keep their behaviour.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function ssRec(User $user): ?array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'salary_sacrifice_ni');
}

function ssUser(): User
{
    return User::factory()->create([
        'household_calculation_mode' => 'single',
        'employment_status' => 'employed',
        'annual_employment_income' => 60000,
        'marital_status' => 'single',
    ]);
}

it('prices a workplace pension captured as a percentage of salary', function () {
    $user = ssUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'workplace',
        'pension_type' => 'occupational',
        'monthly_contribution_amount' => null,
        'annual_salary' => null,
        'employee_contribution_percent' => 5,
        'salary_sacrifice' => false,
    ]);

    $rec = ssRec($user);

    expect($rec)->not->toBeNull()
        ->and($rec['annual_contribution'])->toBe(3000.0)
        ->and($rec['estimated_annual_tax_saved'])->toBeGreaterThan(0);
});

it('treats the onboarding form shape (occupational type, null scheme) as workplace', function () {
    $user = ssUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => null,
        'pension_type' => 'occupational',
        'monthly_contribution_amount' => null,
        'annual_salary' => null,
        'employee_contribution_percent' => 5,
        'salary_sacrifice' => false,
    ]);

    expect(ssRec($user))->not->toBeNull();
});

it('never suggests salary sacrifice for a SIPP or personal pension', function (string $scheme, string $type) {
    $user = ssUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => $scheme,
        'pension_type' => $type,
        'monthly_contribution_amount' => 500,
        'salary_sacrifice' => false,
    ]);

    expect(ssRec($user))->toBeNull();
})->with([
    'sipp' => ['sipp', 'sipp'],
    'personal' => ['personal', 'personal'],
]);
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/SalarySacrificeWorkplaceOnlyTest.php`
Expected: the first two FAIL (no item, because percentages are ignored) and the SIPP case FAILS (item present).

- [ ] **Step 3: Add the workplace rule and the salary fallback**

In `PensionContributionRule.php`, change `monthlyEmployee` to take an optional fallback salary:

```php
    public static function monthlyEmployee(DCPension $pension, float $fallbackSalary = 0.0): float
    {
        $stated = (float) ($pension->monthly_contribution_amount ?? 0);

        if ($stated > 0) {
            return $stated;
        }

        $percent = (float) ($pension->employee_contribution_percent ?? 0);
        // The onboarding form captures the percentage but not the scheme's
        // salary; the caller's own pay figure stands in for it.
        $salary = (float) ($pension->annual_salary ?? 0) ?: $fallbackSalary;

        if ($percent <= 0 || $salary <= 0) {
            return 0.0;
        }

        return ($salary * $percent / 100) / 12;
    }
```

Add `isWorkplace` after `isSalaryDeducted`:

```php
    /**
     * Whether this is an employer-run scheme, the only kind salary sacrifice
     * can apply to. `scheme_type` decides when it is set. The onboarding form
     * records the kind in `pension_type` and leaves `scheme_type` null, so
     * 'occupational' there counts as workplace too.
     */
    public static function isWorkplace(DCPension $pension): bool
    {
        if ((string) ($pension->scheme_type ?? '') !== '') {
            return $pension->scheme_type === 'workplace';
        }

        return $pension->pension_type === 'occupational';
    }
```

- [ ] **Step 4: Use it in the strategy**

In `SalarySacrificeNiStrategy.php`, add `use App\Services\Retirement\PensionContributionRule;`, and replace the `$eligiblePensions` and `$annualContribution` block (lines 42-52):

```php
        $salary = $this->analyzer->payBeforeSacrifice($user);

        // Workplace schemes only: a SIPP or personal pension is paid from
        // money already received and cannot be salary-sacrificed (B1).
        $eligiblePensions = app(PensionStore::class)
            ->forUserByType($user, 'dc')
            ->filter(fn ($p) => empty($p->salary_sacrifice) && PensionContributionRule::isWorkplace($p));

        $annualContribution = (float) $eligiblePensions->sum(
            fn ($p) => PensionContributionRule::monthlyEmployee($p, $salary) * 12
        );
        if ($annualContribution <= 0) {
            return [];
        }
```

Delete the now-redundant `if ($eligiblePensions->isEmpty())` block. An empty collection sums to 0.

- [ ] **Step 5: Pin the existing tests' scheme type**

In `TaxStrategyCalculatorTest.php` lines 1196-1300, add `'scheme_type' => 'workplace',` to each `DCPension::factory()->for($user)->create([...])` call.

- [ ] **Step 6: Run the tests**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/SalarySacrificeWorkplaceOnlyTest.php tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php --filter="salary"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Services/Retirement/PensionContributionRule.php app/Services/Tax/Strategies/SalarySacrificeNiStrategy.php tests/Unit/Services/Tax/Strategies/SalarySacrificeWorkplaceOnlyTest.php tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php
git commit -m "fix(tax): salary sacrifice only on workplace pensions, priced from captured percentages (B1)"
```

---

### Task 2: Non-earner pension and Gift Aid figures from the tax configuration (B13)

**Files:**
- Modify: `app/Services/Tax/TaxStrategyMath.php` (add `nonEarnerPensionContribution()` and `giftAidReclaimFactor()`)
- Modify: `app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php:62-65`
- Modify: `app/Services/Tax/Strategies/LifecycleStrategy.php:127-128`
- Modify: `app/Services/Tax/Strategies/GiftAidHigherRateReliefStrategy.php` (drop the two constants)
- Modify: `app/Services/Onboarding/CaptureForms.php:941-946` (`nonEarnerNetContribution` delegates, so one place computes it)
- Test: `tests/Unit/Services/Tax/TaxStrategyMathTest.php` (append)

**Interfaces:**
- Produces: `TaxStrategyMath::nonEarnerPensionContribution(): array{gross: float, net: float, relief: float}` and `TaxStrategyMath::giftAidReclaimFactor(string $band): float`.

- [ ] **Step 1: Write the failing test** (append to `TaxStrategyMathTest.php`; add any missing `use` lines)

```php
it('derives the non-earner pension figures from the configured limit and basic-rate relief', function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $pension = app(\App\Services\TaxConfigService::class)->getPensionAllowances();
    $gross = (float) $pension['relevant_earnings_minimum'];
    $relief = $gross * (float) $pension['tax_relief']['basic_rate'];

    $figures = app(\App\Services\Tax\TaxStrategyMath::class)->nonEarnerPensionContribution();

    expect($figures['gross'])->toBe($gross)
        ->and($figures['relief'])->toBe(round($relief, 2))
        ->and($figures['net'])->toBe(round($gross - $relief, 2));
});

it('derives the Gift Aid reclaim share from the band rates', function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $math = app(\App\Services\Tax\TaxStrategyMath::class);
    $basic = $math->bandRateForBand('basic');

    expect($math->giftAidReclaimFactor('basic'))->toBe(0.0)
        ->and($math->giftAidReclaimFactor('higher'))
        ->toBe(round(($math->bandRateForBand('higher') - $basic) / (1 - $basic), 4))
        ->and($math->giftAidReclaimFactor('additional'))
        ->toBe(round(($math->bandRateForBand('additional') - $basic) / (1 - $basic), 4));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyMathTest.php --filter="non-earner pension figures|Gift Aid reclaim"`
Expected: FAIL with "Call to undefined method".

- [ ] **Step 3: Add the two helpers to `TaxStrategyMath`** (next to `dividendRateForBand`)

```php
    /**
     * Relief-at-source figures for a contribution by or for someone with no
     * relevant earnings. Gross is the configured limit, relief is basic-rate
     * relief on it, and net is what the payer actually hands over.
     *
     * @return array{gross: float, net: float, relief: float}
     */
    public function nonEarnerPensionContribution(): array
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $gross = (float) ($pension['relevant_earnings_minimum'] ?? 0);
        $relief = round($gross * (float) ($pension['tax_relief']['basic_rate'] ?? 0), 2);

        return ['gross' => $gross, 'net' => round($gross - $relief, 2), 'relief' => $relief];
    }

    /**
     * Share of a net Gift Aid donation a higher- or additional-rate taxpayer
     * reclaims through Self Assessment: the grossed-up gift (net ÷ (1 − basic))
     * times the gap between their rate and the basic rate. 0 at basic rate.
     */
    public function giftAidReclaimFactor(string $band): float
    {
        if (! in_array($band, ['higher', 'additional'], true)) {
            return 0.0;
        }

        $basic = $this->bandRateForBand('basic');

        return $basic < 1 ? round(($this->bandRateForBand($band) - $basic) / (1 - $basic), 4) : 0.0;
    }
```

- [ ] **Step 4: Replace the literals**

- `NonEarnerSpousePensionStrategy::nonEarnerPath`: replace the two `TaxDefaults` lines and their M9 comment with:

```php
        $figures = $this->math->nonEarnerPensionContribution();
        $netContribution = $figures['net'];
        $governmentUplift = $figures['relief'];
```

  Remove `use App\Constants\TaxDefaults;` if nothing else in the file uses it.
- `LifecycleStrategy` junior pension: replace the two `TaxDefaults` lines and their comment with:

```php
            $figures = $this->math->nonEarnerPensionContribution();
            $juniorPensionNet = $figures['net'];
            $juniorPensionUplift = $figures['relief'];
```

  Remove the `TaxDefaults` import if it's unused.
- `GiftAidHigherRateReliefStrategy`: delete `HIGHER_RATE_FACTOR` and `ADDITIONAL_RATE_FACTOR`, and replace the `match` with `$factor = $this->math->giftAidReclaimFactor($band);`. In the class docblock, change the two "× 0.25 / × 0.3125" lines to "donations × (band rate − basic rate) ÷ (1 − basic rate), from the configured rates".
- `CaptureForms::nonEarnerNetContribution()` body becomes `return app(\App\Services\Tax\TaxStrategyMath::class)->nonEarnerPensionContribution()['net'];`.
- `SpouseOptimisationService.php:457` and `RetirementActionDefinitionService.php:658` also read the `TaxDefaults` constants. They are other modules, so list them in the PR body as follow-ups and leave them alone.

- [ ] **Step 5: Run the tests.** The Gift Aid and non-earner tests in the calculator suite (they pin 250.0, 0.25, 720.0, 2880.0) prove the change is value-stable.

Run: `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyMathTest.php tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php tests/Unit/Services/Onboarding/CaptureFormsTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/Tax app/Services/Onboarding/CaptureForms.php tests/Unit/Services/Tax/TaxStrategyMathTest.php
git commit -m "fix(tax): non-earner pension and Gift Aid figures from TaxConfigService (B13)"
```

---

### Task 3: Spouse pension top-up takes account of what is already paid (B5)

**Files:**
- Modify: `app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php` (`nonEarnerPath`, `modestEarnerPath`)
- Test: `tests/Unit/Services/Tax/Strategies/SpousePensionAlreadyPaidTest.php` (new)

**Interfaces:**
- Consumes: `TaxStrategyMath::nonEarnerPensionContribution()` from Task 2.
- Basis note: in `single_earner_couple`, `spouse_pension_input_annual` holds the **net** figure. `CoordinatingAgent.php:5808` writes `CaptureForms::nonEarnerNetContribution()` for "pays the maximum". In `dual_earner` it holds the spouse's own **gross** contribution (`TaxStrategyCalculator.php:280`).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function spousePensionRec(string $mode, array $household): ?array
{
    $user = User::factory()->create([
        'household_calculation_mode' => $mode,
        'annual_employment_income' => 60000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id] + $household);

    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'non_earner_spouse_pension');
}

it('stays silent when a non-earning spouse already pays in the maximum', function () {
    $net = app(TaxStrategyMath::class)->nonEarnerPensionContribution()['net'];

    expect(spousePensionRec('single_earner_couple', ['spouse_pension_input_annual' => $net]))->toBeNull();
});

it('suggests only the remainder when a non-earning spouse pays part', function () {
    $figures = app(TaxStrategyMath::class)->nonEarnerPensionContribution();
    $rec = spousePensionRec('single_earner_couple', ['spouse_pension_input_annual' => 1000]);

    $remaining = round($figures['net'] - 1000, 2);
    expect($rec)->not->toBeNull()
        ->and($rec['net_contribution'])->toBe($remaining)
        ->and($rec['estimated_annual_tax_saved'])->toBe(round($remaining * $figures['relief'] / $figures['net'], 2));
});

it('sizes a modest earner by the earnings left after what they already contribute', function () {
    $rec = spousePensionRec('dual_earner', ['spouse_annual_income' => 8000, 'spouse_pension_input_annual' => 3000]);

    expect($rec)->not->toBeNull()
        ->and($rec['gross_capacity'])->toBe(5000.0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/SpousePensionAlreadyPaidTest.php`
Expected: FAIL. The first case returns an item, and the second and third show the full figures.

- [ ] **Step 3: Implement.** In `nonEarnerPath`, after the `$figures` lines from Task 2:

```php
        // What the spouse already pays in (stored net for a non-earner, the
        // relief-at-source shape the capture writes) comes off the top (B5).
        $alreadyPaid = (float) ($household?->spouse_pension_input_annual ?? 0);
        $netContribution = round(max(0.0, $figures['net'] - $alreadyPaid), 2);
        if ($netContribution < 1) {
            return [];
        }
        $governmentUplift = round($netContribution * $figures['relief'] / $figures['net'], 2);
```

(These replace the two assignments of `$netContribution` and `$governmentUplift`.) In `modestEarnerPath`, replace `$grossCapacity = $spouseIncome;` with:

```php
        // Relevant earnings cap the gross contribution; what they already pay
        // in (gross in this mode) has used part of it (B5).
        $grossCapacity = max(0.0, $spouseIncome - (float) ($household->spouse_pension_input_annual ?? 0));
        if ($grossCapacity < 1) {
            return [];
        }
```

In the `modestEarnerPath` description, the sentence "Paying in £%s net gets grossed up to £%s" already uses `$netCost` and `$grossCapacity`, so the copy stays correct.

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/SpousePensionAlreadyPaidTest.php tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php --filter="spouse|non_earner"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php tests/Unit/Services/Tax/Strategies/SpousePensionAlreadyPaidTest.php
git commit -m "fix(tax): spouse pension top-up nets off what the spouse already pays in (B5)"
```

---

### Task 4: Marriage Allowance as one strategy, qualified properly (B6, B7, B8, and part of B4)

**Files:**
- Modify: `app/Services/Tax/TaxStrategyMath.php` (add `isMarriedOrCivilPartner`, `marriageAllowanceAmount`, `marriageAllowanceTransfer`)
- Create: `app/Services/Tax/Strategies/MarriageAllowanceStrategy.php`
- Modify: `app/Services/Tax/TaxStrategyCalculator.php` (constructor and registry)
- Modify: `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php` (delete the Marriage Allowance block; the spouse's Personal Allowance is reduced by any transfer)
- Modify: `app/Services/Tax/Strategies/JointSavingsStrategy.php` (the same reduction)
- Test: `tests/Unit/Services/Tax/Strategies/MarriageAllowanceStrategyTest.php` (new)

**Interfaces:**
- Produces: `TaxStrategyMath::isMarriedOrCivilPartner(User $user): bool`, `TaxStrategyMath::marriageAllowanceAmount(): float` and `TaxStrategyMath::marriageAllowanceTransfer(User $user, string $mode, ?TaxStrategyHouseholdInput $household): float`. The last one returns the part of the transfer that reduces the user's tax, or 0 when the couple doesn't qualify. Task 5 consumes `isMarriedOrCivilPartner`.
- Output: the `marriage_allowance_transfer` type, category `household`, priority `medium`, and `amount_transferred` in extra. All unchanged.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function maUser(array $attrs, array $household = []): User
{
    $user = User::factory()->create($attrs + ['marital_status' => 'married']);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id] + $household);

    return $user;
}

function maRec(User $user): ?array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'marriage_allowance_transfer');
}

function maBasicSaving(): float
{
    $math = app(TaxStrategyMath::class);

    return round($math->marriageAllowanceAmount() * $math->bandRateForBand('basic'), 2);
}

it('does not offer Marriage Allowance when the user has no taxable income (B6)', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 0]);

    expect(maRec($user))->toBeNull();
});

it('caps the saving at the tax the user actually pays', function () {
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => $pa + 430]);

    $basic = app(TaxStrategyMath::class)->bandRateForBand('basic');
    expect(maRec($user)['estimated_annual_tax_saved'])->toBe(round(430 * $basic, 2));
});

it('gives the full saving to a basic-rate recipient with a non-earning spouse', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000]);

    expect(maRec($user)['estimated_annual_tax_saved'])->toBe(maBasicSaving());
});

it('offers Marriage Allowance in dual_earner mode when the spouse earns below the Personal Allowance (B7)', function () {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 35000], ['spouse_annual_income' => 8000]);

    expect(maRec($user)['estimated_annual_tax_saved'])->toBe(maBasicSaving());
});

it('does not offer it when the spouse earns above the Personal Allowance or their income is unknown', function (?float $spouseIncome) {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 35000], ['spouse_annual_income' => $spouseIncome]);

    expect(maRec($user))->toBeNull();
})->with(['above' => [20000.0], 'unknown' => [null]]);

it('does not offer it to a couple who are not married or in a civil partnership (B8)', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000, 'marital_status' => 'single']);

    expect(maRec($user))->toBeNull();
});

it('shrinks the spouse Personal Allowance used for the savings gift by the transferred amount (B4)', function () {
    $user = maUser(
        ['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000],
        ['spouse_existing_savings_balance' => 0],
    );
    SavingsAccount::factory()->for($user)->create([
        'current_balance' => 200000, 'interest_rate' => 4.5, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    $gift = collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'savings_to_spouse');
    $math = app(TaxStrategyMath::class);
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];

    expect(maRec($user))->not->toBeNull()
        ->and($gift['spouse_personal_allowance'])->toBe($pa - $math->marriageAllowanceAmount());
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/MarriageAllowanceStrategyTest.php`
Expected: FAIL with "undefined method marriageAllowanceAmount". Once the helpers exist, the B6, cap, B7, B8 and B4 cases still fail.

- [ ] **Step 3: Add the helpers to `TaxStrategyMath`** (add `use App\Models\TaxStrategyHouseholdInput;`)

```php
    /** Tax treats spouses and civil partners alike; unmarried partners get neither transfer. */
    public function isMarriedOrCivilPartner(User $user): bool
    {
        return in_array((string) ($user->marital_status ?? ''), ['married', 'civil_partnership'], true);
    }

    public function marriageAllowanceAmount(): float
    {
        return (float) ($this->taxConfig->getIncomeTax()['marriage_allowance']['amount'] ?? 0);
    }

    /**
     * How much of a Marriage Allowance transfer reduces this user's tax. It is
     * 0 unless all of these hold:
     * - they are married or in a civil partnership;
     * - the spouse's income is KNOWN to be below the Personal Allowance (a
     *   non-earner in single_earner_couple mode, or captured income in
     *   dual_earner mode);
     * - the user is a basic-rate taxpayer.
     * The result is capped at the user's income above their own allowance,
     * so nobody is promised a reduction on tax they don't pay.
     */
    public function marriageAllowanceTransfer(User $user, string $mode, ?TaxStrategyHouseholdInput $household): float
    {
        if (! $this->isMarriedOrCivilPartner($user)) {
            return 0.0;
        }

        $spouseIncome = match ($mode) {
            'single_earner_couple' => 0.0,
            'dual_earner' => $household?->spouse_annual_income === null
                ? null
                : (float) $household->spouse_annual_income + (float) ($household->spouse_annual_dividends ?? 0),
            default => null,
        };
        $personalAllowance = (float) ($this->taxConfig->getIncomeTax()['personal_allowance'] ?? 0);
        if ($spouseIncome === null || $spouseIncome >= $personalAllowance) {
            return 0.0;
        }

        $taxable = $this->taxableIncomeFor($user);
        if ($this->bandFromIncomeFor($user, $taxable) !== 'basic') {
            return 0.0;
        }

        return max(0.0, min($this->marriageAllowanceAmount(), $taxable - $this->personalAllowanceFor($user)));
    }
```

- [ ] **Step 4: Create `MarriageAllowanceStrategy.php`**

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
 * Marriage Allowance transfer, for both couple modes. The one home for the
 * rule: it used to live in the single-earner bundle only, so a dual-earner
 * couple with a low-earning spouse never saw it (B7), and a user with no
 * taxable income was promised the full saving (B6).
 */
final class MarriageAllowanceStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $reducible = $this->math->marriageAllowanceTransfer($context->user, $context->mode, $context->household);
        $saving = round($reducible * $this->math->bandRateForBand('basic'), 2);
        if ($saving < 1) {
            return [];
        }

        $amount = $this->math->marriageAllowanceAmount();

        return [new StrategyRecommendation(
            type: 'marriage_allowance_transfer',
            category: StrategyCategory::Household,
            priority: StrategyPriority::Medium,
            title: 'Claim Marriage Allowance',
            description: sprintf(
                'Your spouse or civil partner can transfer £%s of their unused Personal Allowance to you, saving you around £%s a year in income tax.',
                number_format((int) $amount),
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: $saving,
            extra: ['amount_transferred' => $amount],
        )];
    }
}
```

- [ ] **Step 5: Register it.** In `TaxStrategyCalculator`, add `private readonly Strategies\MarriageAllowanceStrategy $marriageAllowance,` to the constructor after `$nonEarnerSpousePension`, and add `$this->marriageAllowance,` to `$strategies` before `$this->crossSpouse`.

- [ ] **Step 6: Remove the old block and share the spouse allowance correctly**

In `AssetShiftingBundleStrategy::generate`:
- Delete section "1. Marriage Allowance transfer" (the `if ($user->marriage_allowance_eligible && $userBand === 'basic')` block) and the `$marriageAmount` variable.
- After `$personalAllowance = …`, add:

```php
        // A Marriage Allowance transfer takes that slice of the spouse's
        // Personal Allowance; it cannot also shelter gifted interest (B4).
        $spousePersonalAllowance = $this->math->marriageAllowanceTransfer($user, $context->mode, $household) > 0
            ? $personalAllowance - $this->math->marriageAllowanceAmount()
            : $personalAllowance;
```

- Replace `$personalAllowance` with `$spousePersonalAllowance` in `$spouseInterestCapacity`, `$stackedCapacity`, the first `number_format` of the description and `'spouse_personal_allowance'`. Leave the `$userPersonalAllowance` line as it is.

In `JointSavingsStrategy::generate`, after `$spousePersonalAllowance = $this->math->personalAllowanceForIncome(0.0);` add:

```php
        if ($this->math->marriageAllowanceTransfer($user, $mode, $household) > 0) {
            $spousePersonalAllowance -= $this->math->marriageAllowanceAmount();
        }
```

- [ ] **Step 7: Run the tests.** Existing calculator tests at lines 164-330 pin Marriage Allowance. They keep passing if they set `marital_status => 'married'`. Any that set only `marriage_allowance_eligible` with a £0 income now pin the B6 bug: change the fixture income to a basic-rate figure and state why in the test.

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/MarriageAllowanceStrategyTest.php tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php tests/Feature/Services/ComposedTaxPlanServiceTest.php`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Services/Tax tests/Unit/Services/Tax
git commit -m "fix(tax): Marriage Allowance as one strategy — taxable income cap, dual-earner spouse, married only, spouse PA shared (B4/B6/B7/B8)"
```

---

### Task 5: Spouse transfers only for spouses and civil partners, only on known data (B8, B12, ruling a)

**Files:**
- Modify: `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php` (gate the bundle; the ISA-in-spouse's-name item needs the user to have something to fund it)
- Modify: `app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php` (gate the bundle; the non-ISA investment move needs the spouse's income known)
- Test: `tests/Unit/Services/Tax/Strategies/SpouseTransferGatesTest.php` (new)

**Interfaces:**
- Consumes: `TaxStrategyMath::isMarriedOrCivilPartner()` from Task 4.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function spouseTypes(User $user): array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->where('category', 'household')->pluck('type')->all();
}

it('gives no spouse-transfer advice when the user is not married or in a civil partnership', function (string $mode) {
    $user = User::factory()->create([
        'household_calculation_mode' => $mode,
        'annual_employment_income' => 80000,
        'marital_status' => 'single',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => 20000, 'spouse_existing_isa_balance' => 0]);
    InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'gia']);

    expect(spouseTypes($user))->not->toContain('gia_to_spouse', 'gia_rebalance', 'savings_to_spouse', 'isa_topup_spouse');
})->with(['single_earner_couple', 'dual_earner']);

it('does not treat an unknown spouse income as basic rate (B12)', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'dual_earner',
        'annual_employment_income' => 80000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => null]);
    InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'gia']);

    expect(spouseTypes($user))->not->toContain('gia_rebalance');
});

it('does not suggest funding a spouse ISA when the user has nothing to fund it from', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 40000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_existing_isa_balance' => 0]);

    expect(spouseTypes($user))->not->toContain('isa_topup_spouse');
});
```

Before relying on `InvestmentAccount::factory()` and its `account_type` values, check that the factory exists (`ls database/factories/Investment/`). If it doesn't, copy the account-creation fixture used at `TaxStrategyCalculatorTest.php:918-960`.

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/SpouseTransferGatesTest.php`
Expected: FAIL. All three currently emit the items.

- [ ] **Step 3: Implement**

`AssetShiftingBundleStrategy::generate`, first guard:

```php
        if ($context->mode !== 'single_earner_couple' || ! $this->math->isMarriedOrCivilPartner($context->user)) {
            return [];
        }
```

In the same method, move the `$hasGia` query up to just before section 3 (the ISA top-up in the spouse's name), and wrap section 3's condition:

```php
        // Funding a spouse's ISA needs money to fund it with (ruling a).
        $hasFundsToGift = $userSavingsTotal > 0 || $hasGia;
        if ($hasFundsToGift && $spouseIsaBalance !== null && (float) $spouseIsaBalance === 0.0) {
```

Section 4 then reuses the same `$hasGia`.

`CrossSpouseBundleStrategy::generate`, first guard:

```php
        if ($context->mode !== 'dual_earner'
            || ! $context->household instanceof TaxStrategyHouseholdInput
            || ! $this->math->isMarriedOrCivilPartner($context->user)) {
            return [];
        }
```

Change the non-ISA rebalance condition to require known spouse income:

```php
        $spouseIncomeKnown = $household->spouse_annual_income !== null;
        if ($hasGia && $spouseIncomeKnown && $userBand !== 'basic' && $spouseBand === 'basic') {
```

- [ ] **Step 4: Run the tests.** Existing dual_earner and single_earner_couple fixtures in `TaxStrategyCalculatorTest.php` that omit `marital_status` now return no household items. Add `'marital_status' => 'married'` to those fixtures. Never loosen the gate.

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/SpouseTransferGatesTest.php tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php tests/Feature/Services/ComposedTaxPlanServiceTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Tax/Strategies tests/Unit/Services/Tax
git commit -m "fix(tax): spouse transfers only for spouses/civil partners, on known spouse income, with funds to transfer (B8/B12)"
```

---

### Task 6: The headline total counts only real tax saved, once (B4)

**Files:**
- Modify: `app/Services/Tax/Strategies/LifecycleStrategy.php` (Lifetime ISA and junior pension saving → null)
- Modify: `app/Services/Tax/Strategies/DividendAllowanceHarvestStrategy.php` (saving → null)
- Modify: `app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php` (saving → null; the charge moves to extra)
- Modify: `database/seeders/TaxActionDefinitionSeeder.php` (ISA top-up conflicts with both spouse-savings items)
- Modify: `app/Services/Coordination/StrategyPlanComposer.php` (the conflict note names the alternative by title, not internal id)
- Modify: `tests/Feature/Seeders/TaxCatalogueMetadataSeederTest.php`, `tests/Unit/Services/Coordination/StrategyPlanComposerTest.php:212`
- Test: `tests/Feature/Services/SaveTaxHeadlineTotalTest.php` (new)

**Interfaces:**
- Output: `lifetime_isa`, `junior_pension`, `dividend_allowance_harvest` and `tapered_annual_allowance` carry `estimated_annual_tax_saved: null`. Their figures stay in extra: `government_bonus`, `total_government_uplift`, `unused_allowance`, and the new `annual_allowance_charge_avoided`. Titles are unchanged.
- The conflict note becomes `Alternative to "<winner title>" — compare before doing both.`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Coordination\ComposedTaxPlanService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

function headlinePlan(User $user): array
{
    return app(ComposedTaxPlanService::class)->forUser($user);
}

it('keeps the Lifetime ISA card but does not count its bonus as tax saved', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single',
        'annual_employment_income' => 30000,
        'marital_status' => 'single',
        'date_of_birth' => now()->subYears(30)->toDateString(),
    ]);

    $lisa = collect(headlinePlan($user)['items'])->firstWhere('type', 'lifetime_isa');

    expect($lisa)->not->toBeNull()
        ->and($lisa['estimated_annual_tax_saved'])->toBeNull()
        ->and($lisa['government_bonus'])->toBeGreaterThan(0);
});

it('sums only real tax savings, counting ISA top-up and the savings gift once', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 60000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_existing_savings_balance' => 0]);
    SavingsAccount::factory()->for($user)->create([
        'current_balance' => 150000, 'interest_rate' => 4.5, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    $plan = headlinePlan($user);
    $items = collect($plan['items'])->keyBy('type');

    expect($items->has('isa_topup_vs_psa'))->toBeTrue()
        ->and($items->has('savings_to_spouse'))->toBeTrue();

    // The three sole-name-interest items all conflict with each other (a
    // triangle): only the largest counts, and the others name it by title.
    $trio = collect($plan['items'])
        ->whereIn('type', ['isa_topup_vs_psa', 'savings_to_spouse', 'joint_savings_psa_split']);
    $winner = $trio->sortByDesc('estimated_annual_tax_saved')->first();

    foreach ($trio->reject(fn ($i) => $i['type'] === $winner['type']) as $loser) {
        expect($loser['conflict_note'])->toContain($winner['title'])
            ->and($loser['conflict_note'])->not->toContain('_');
    }

    $expected = collect($plan['items'])
        ->reject(fn ($i) => $trio->pluck('type')->contains($i['type']))
        ->sum(fn ($i) => (float) ($i['estimated_annual_tax_saved'] ?? 0))
        + (float) $winner['estimated_annual_tax_saved'];
    expect($plan['combined_annual_saving'])->toBe(round($expected, 2));
});
```

Before running, confirm the `SavingsAccount` factory's column names (`ownership_type`, `interest_rate` as a percentage) against `TaxStrategyCalculatorTest.php:249-285` and match them.

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Services/SaveTaxHeadlineTotalTest.php`
Expected: FAIL. The Lifetime ISA saving is non-null, and both savings items count toward the total.

- [ ] **Step 3: Null the non-tax savings**

- `LifecycleStrategy`: the Lifetime ISA gets `estimatedAnnualTaxSaved: null,` (its `government_bonus` stays in extra), and the junior pension gets `estimatedAnnualTaxSaved: null,` (`total_government_uplift` stays). Add a one-line comment above each: `// A government bonus/uplift, not tax saved (CSJ ruling 2026-09-25): never in the headline total.`
- `DividendAllowanceHarvestStrategy`: `estimatedAnnualTaxSaved: null,` with the comment `// Unused allowance saves nothing until dividends exist to use it (ruling 2026-09-25).`
- `TaperedAnnualAllowanceStrategy`: `estimatedAnnualTaxSaved: null,` and add `'annual_allowance_charge_avoided' => round($avoidedCharge, 2),` to extra, with the comment `// A warning: the charge is avoided, not tax saved (ruling 2026-09-25).` Update the class docblock sentence "estimated_annual_tax_saved carries that avoided charge" to "extra.annual_allowance_charge_avoided carries that charge; it is not counted as tax saved".

- [ ] **Step 4: Seed the conflicts.** In `TaxActionDefinitionSeeder::strategyMetadata()`:
  - `isa_topup_vs_psa` gets `conflicts_with ['savings_to_spouse', 'joint_savings_psa_split']` (keep `do_before ['savings_to_spouse']`);
  - `savings_to_spouse` gets `conflicts_with ['joint_savings_psa_split', 'isa_topup_vs_psa']`;
  - `joint_savings_psa_split` gets `conflicts_with ['savings_to_spouse', 'isa_topup_vs_psa']`.

  Add the comment `// All three shelter the same sole-name interest; only the largest counts (B4).`

- [ ] **Step 5: Name the alternative by title.** In `StrategyPlanComposer::compose`, step 4, build a title map and use it:

```php
        $titleByType = [];
        foreach ($items as $rec) {
            $titleByType[$rec->type] = $rec->title;
        }
```

  Then:

```php
            $conflictNote = isset($noteFor[$rec->type])
                ? sprintf('Alternative to "%s" — compare before doing both.', $titleByType[$noteFor[$rec->type]] ?? $noteFor[$rec->type])
                : (is_string($isaNote) ? $isaNote : null);
```

  Update `StrategyPlanComposerTest.php:212` to expect the title of `strategy_a`'s fixture rec, and update the class docblock line "carries a conflict_note naming its preferred alternative" to "…naming its preferred alternative by title". The holistic plan renders these notes on web (`HolisticCompositePlan.vue`), `/m` (`HolisticPlan.vue`) and iOS, so internal ids were reaching users.

- [ ] **Step 6: Update the seeder test.** In `TaxCatalogueMetadataSeederTest.php` (b), also assert that `isa_topup_vs_psa`'s `conflicts_with` contains both `savings_to_spouse` and `joint_savings_psa_split`.

- [ ] **Step 7: Run the tests.** Update assertions that pin the old numbers: Lifetime ISA, junior pension, dividend harvest and tapered allowance savings in `TaxStrategyCalculatorTest.php` (1093-1156, 868-885, 1871+), `IsaSharedAllowanceAllocationTest.php` and `ComposedTaxPlanServiceTest.php`. Each edited expectation gets a one-line comment citing ruling (a).

Run: `./vendor/bin/pest tests/Feature/Services/SaveTaxHeadlineTotalTest.php tests/Unit/Services/Tax tests/Unit/Services/Coordination tests/Feature/Services/ComposedTaxPlanServiceTest.php tests/Feature/Seeders/TaxCatalogueMetadataSeederTest.php`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Services/Tax app/Services/Coordination/StrategyPlanComposer.php database/seeders/TaxActionDefinitionSeeder.php tests
git commit -m "fix(tax): headline total counts only real tax saved, once; conflict notes name the alternative (B4)"
```

---

### Task 7: Pension tax relief for every band (ruling c, B9)

**Files:**
- Create: `app/Services/Tax/Strategies/PensionTaxReliefStrategy.php`
- Modify: `app/Services/Tax/TaxStrategyCalculator.php` (constructor and registry)
- Modify: `database/seeders/TaxActionDefinitionSeeder.php` (two metadata rows)
- Modify: `tests/Feature/Seeders/TaxCatalogueMetadataSeederTest.php` (expected list)
- Test: `tests/Unit/Services/Tax/Strategies/PensionTaxReliefStrategyTest.php` (new)

**Interfaces:**
- Produces two types: `pension_relief_higher_rate` (category `income_band`, priority `high`) and `pension_relief_basic_rate` (category `income_band`, priority `medium`). Extra: `suggested_contribution` (rounded to £100), `relief_rate`, `tax_band`.
- Band ownership:
  - adjusted net income above the Personal Allowance taper threshold belongs to `pa_taper_rescue` or `additional_rate_avoidance`, so this strategy returns `[]`;
  - higher band below the threshold gets the higher-rate item;
  - basic band with income above the Personal Allowance gets the basic-rate item;
  - no relevant earnings, age 75 or over, no Annual Allowance left, or no income tax paid gets `[]`.
- Sizing:
  - **Higher rate:** the slice taxed at the higher rate (taxable income − higher-rate threshold for the user), capped by available Annual Allowance and relevant earnings.
  - **Basic rate:** 10% of relevant earnings, less what already goes in this year (`estimatePensionContributionThisYear`), capped by Annual Allowance and by income above the Personal Allowance, so relief never exceeds tax paid.
  - **Decision for CSJ at plan review:** the 10% basic-rate sizing mirrors the funnel estimate (`SaveTaxEstimateService.php:70-85`), so the plan keeps the funnel's promise.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function reliefRecs(User $user): array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->whereIn('type', ['pension_relief_higher_rate', 'pension_relief_basic_rate'])
        ->keyBy('type')->all();
}

function reliefUser(float $income, array $extra = []): User
{
    return User::factory()->create($extra + [
        'household_calculation_mode' => 'single',
        'employment_status' => 'employed',
        'annual_employment_income' => $income,
        'marital_status' => 'single',
        'date_of_birth' => now()->subYears(40)->toDateString(),
    ]);
}

it('sizes a higher-rate item to the slice taxed at the higher rate', function () {
    $user = reliefUser(60000);
    $math = app(TaxStrategyMath::class);
    $slice = 60000 - $math->bandThresholdsFor($user)['higher'];
    $display = (int) (round($slice / 100) * 100);

    $rec = reliefRecs($user)['pension_relief_higher_rate'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe((float) $display)
        ->and($rec['estimated_annual_tax_saved'])->toBe(round($display * $math->bandRateForBand('higher'), 2))
        ->and(reliefRecs($user))->not->toHaveKey('pension_relief_basic_rate');
});

it('gives a basic-rate earner an item sized at a tenth of their earnings', function () {
    $user = reliefUser(30000);
    $basic = app(TaxStrategyMath::class)->bandRateForBand('basic');

    $rec = reliefRecs($user)['pension_relief_basic_rate'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe(3000.0)
        ->and($rec['estimated_annual_tax_saved'])->toBe(round(3000 * $basic, 2));
});

it('stays silent for a basic-rate earner already paying in a tenth', function () {
    $user = reliefUser(30000);
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'workplace', 'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 8, 'employer_contribution_percent' => 3,
    ]);

    expect(reliefRecs($user))->toBe([]);
});

it('leaves the Personal Allowance taper band to pa_taper_rescue', function () {
    expect(reliefRecs(reliefUser(110000)))->toBe([]);
});

it('stays silent without taxable earnings', function (array $attrs) {
    expect(reliefRecs(reliefUser(...$attrs)))->toBe([]);
})->with([
    'below the Personal Allowance' => [[10000]],
    'no earnings' => [[0, ['employment_status' => 'retired', 'annual_other_income' => 30000]]],
    'aged 75' => [[30000, ['date_of_birth' => now()->subYears(76)->toDateString()]]],
]);
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/PensionTaxReliefStrategyTest.php`
Expected: FAIL. The strategy doesn't exist, so the positive cases have no items.

- [ ] **Step 3: Create `PensionTaxReliefStrategy.php`**

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
 * Pension tax relief below the Personal Allowance taper (CSJ ruling
 * 2026-09-25: suggested for every band). Above the taper threshold,
 * IncomeBandStrategy owns the pension items.
 *
 *   - Higher rate: the slice taxed at the higher rate, relieved at that rate.
 *   - Basic rate:  a tenth of relevant earnings less what already goes in,
 *                  relieved at the basic rate and capped at income above the
 *                  Personal Allowance so relief never exceeds tax paid.
 */
final class PensionTaxReliefStrategy implements TaxStrategy
{
    // ponytail: mirrors the /savetax funnel estimate (SaveTaxEstimateService)
    // so the plan keeps the funnel's promise; a user-set target replaces it if
    // one is ever captured.
    private const BASIC_RATE_SHARE_OF_EARNINGS = 0.10;

    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;

        $taperThreshold = (float) ($this->taxConfig->getIncomeTax()['personal_allowance_taper_threshold'] ?? 0);
        if ($this->math->adjustedNetIncomeFor($user) > $taperThreshold) {
            return [];
        }

        $earnings = (float) ($user->annual_employment_income ?? 0) + (float) ($user->annual_self_employment_income ?? 0);
        $age = $this->math->ageOf($user->date_of_birth);
        $availableAA = $this->math->availableAnnualAllowance($user, $context->overrides);
        $taxable = $this->math->taxableIncomeFor($user);
        $aboveAllowance = $taxable - $this->math->personalAllowanceFor($user);
        if ($earnings <= 0 || ($age !== null && $age >= 75) || $availableAA <= 0 || $aboveAllowance <= 0) {
            return [];
        }

        $band = $this->math->bandFromIncomeFor($user, $taxable);
        $contribution = $band === 'higher'
            ? min($taxable - $this->math->bandThresholdsFor($user)['higher'], $availableAA, $earnings)
            : min(
                $earnings * self::BASIC_RATE_SHARE_OF_EARNINGS - $this->math->estimatePensionContributionThisYear($user, $context->overrides),
                $availableAA,
                $aboveAllowance,
            );

        $display = (int) (round($contribution / 100) * 100);
        if ($display < 100) {
            return [];
        }

        $rate = $this->math->bandRateForBand($band);
        $saving = round($display * $rate, 2);
        $ratePct = (int) round($rate * 100);
        $basicPct = (int) round($this->math->bandRateForBand('basic') * 100);

        return [new StrategyRecommendation(
            type: $band === 'higher' ? 'pension_relief_higher_rate' : 'pension_relief_basic_rate',
            category: StrategyCategory::IncomeBand,
            priority: $band === 'higher' ? StrategyPriority::High : StrategyPriority::Medium,
            title: sprintf('Pay £%s more into your pension and save £%s in tax', number_format($display), number_format((int) round($saving))),
            description: $band === 'higher'
                ? sprintf(
                    'Pension contributions get tax relief at your highest rate. £%s of your income is taxed at %d%%, so paying that amount into a pension saves £%s this year. A workplace scheme gives the relief through your pay; for a personal pension the provider adds %d%% and you claim the rest through Self Assessment.',
                    number_format($display), $ratePct, number_format((int) round($saving)), $basicPct,
                )
                : sprintf(
                    'Every £%s you pay into a pension gets %d%% tax relief. Paying in £%s more this year saves £%s of income tax.',
                    number_format(100), $ratePct, number_format($display), number_format((int) round($saving)),
                ),
            estimatedAnnualTaxSaved: $saving,
            extra: [
                'suggested_contribution' => (float) $display,
                'relief_rate' => $rate,
                'tax_band' => $band,
            ],
        )];
    }
}
```

- [ ] **Step 4: Register it and seed its metadata**

In `TaxStrategyCalculator`, add `private readonly Strategies\PensionTaxReliefStrategy $pensionTaxRelief,` after `$incomeBand`, and add `$this->pensionTaxRelief,` after `$this->incomeBand` in `$strategies`.

In `TaxActionDefinitionSeeder::strategyMetadata()`, after `additional_rate_avoidance`:

```php
            // Pension relief below the taper band (CSJ ruling 2026-09-25: every
            // band). Band-exclusive with the two rows above by construction.
            [
                'strategy_type' => 'pension_relief_higher_rate',
                'category' => 'income_band',
                'priority' => 'high',
                'claim_tier' => 'mechanical',
                'required_data' => ['annual_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'pension_relief_basic_rate',
                'category' => 'income_band',
                'priority' => 'medium',
                'claim_tier' => 'mechanical',
                'required_data' => ['annual_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],
```

Add both types to the `$expected` list in `TaxCatalogueMetadataSeederTest.php`, and update the seeder's class docblock count ("20 strategy-registry metadata rows" becomes 22).

- [ ] **Step 5: Run the tests.** Fixtures in other suites that assert an exact item list or combined total for £20k–£100k earners now also see a pension item. Update each expectation, and add a comment citing ruling (c).

Run: `./vendor/bin/pest tests/Unit/Services/Tax tests/Unit/Services/Coordination tests/Feature/Services tests/Feature/Seeders/TaxCatalogueMetadataSeederTest.php tests/Unit/Services/Mobile/NextActionsServiceTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/Tax database/seeders/TaxActionDefinitionSeeder.php tests
git commit -m "feat(tax): pension tax relief items for basic and higher rate (ruling c, B9)"
```

---

### Task 8: "Spouse or civil partner" on the public funnel (ruling b, B8)

**Files:**
- Modify: `public/pages/savetax.php:226-229,253-256`
- Test: `tests/Feature/Marketing/SaveTaxFunnelWordingTest.php` (new)

The answers keep the `spouse` key and `yes`/`no` values, so `savetax.js`, `FunnelAnswersMapper` and `SaveTaxEstimateService` need no change. `savetax.js` holds no user-visible spouse text; confirm with `grep -n "'[^']*[Ss]pouse[^']*'" public/pages/js/savetax.js`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

it('asks about a spouse or civil partner on the public funnel', function () {
    $html = file_get_contents(public_path('pages/savetax.php'));

    expect($html)->toContain('Do you have a spouse or civil partner?')
        ->and($html)->toContain("What is your spouse or civil partner's annual income?")
        ->and($html)->not->toContain('Do you have a spouse?</h2>');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Marketing/SaveTaxFunnelWordingTest.php`
Expected: FAIL

- [ ] **Step 3: Implement.** In `savetax.php`, make these changes:
  - line 226 → `Do you have a spouse or civil partner?`;
  - line 253 → `What is your spouse or civil partner's annual income?`;
  - the `aria-label`s at 228 and 255 → `Spouse or civil partner options` and `Spouse or civil partner annual income options`.

  Run `grep -n -i "spouse" public/pages/savetax.php`. Any other visible text on these two screens gets the same wording.

- [ ] **Step 4: Run the test**

Run: `./vendor/bin/pest tests/Feature/Marketing/SaveTaxFunnelWordingTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add public/pages/savetax.php tests/Feature/Marketing/SaveTaxFunnelWordingTest.php
git commit -m "fix(savetax): ask about a spouse or civil partner on the public funnel (ruling b)"
```

---

### Task 9: Regression, review, live verification, PR

- [ ] **Step 1: One consolidated suite run** (Rule 17: one pass, not one per task). Run these one at a time, never concurrently:

```bash
./vendor/bin/pest tests/Unit/Services/Tax tests/Unit/Services/Coordination tests/Unit/Services/Onboarding tests/Unit/Services/Mobile tests/Unit/Services/Retirement
./vendor/bin/pest tests/Feature/Services tests/Feature/Seeders tests/Feature/Onboarding tests/Feature/AI tests/Feature/Marketing
./vendor/bin/pest tests/Architecture
./vendor/bin/pint --dirty
```

Expected: all green. A red test is diagnosed with the `test-failure-forensics` skill before any assertion changes. A test pinning old behaviour that a ruling overturns gets updated with a comment. Any other red is a bug to fix.

- [ ] **Step 2: Reviews.** Run `tax-compliance-reviewer` on `git diff dev...HEAD`, then `superpowers:requesting-code-review`. Fix every finding, or state in the PR why it is declined.

- [ ] **Step 3: Update the outcomes file.** Edit `September/September25Updates/savetax-outcomes-by-household-2026-09-25.md`:
  - mark each of B1, B4–B9, B12 and B13 fixed, with its commit;
  - record that B11 is **not a bug**: `FamilyMember::RELATIONSHIP_ALIASES` stores step-children as `child`, which `LifecycleStrategy` already counts, and local data has no `step_child` rows;
  - list B2, B3 and B14 as open (see "Not in this plan").
  - add the two new pension items to sections 1–4.

- [ ] **Step 4: Verify live on web and `/m`.** Work in the worktree with built bundles on `:8010`: `VITE_BASE_PATH=/build/` for web, `vite.mobile.config.js` for `/m`, then `APP_URL=http://localhost:8010 php artisan serve --port=8010`. Reseed: `php artisan db:seed --class=TaxActionDefinitionSeeder --force`. In Playwright, register fresh accounts through `/savetax` and walk the Save Tax chat to the plan for three households:
  - (i) single, £60,000, workplace pension at 5%, cash savings: expect the higher-rate pension item, salary sacrifice, and no Lifetime ISA money in the total;
  - (ii) married, £35,000, spouse earns nothing, £150,000 cash: expect Marriage Allowance at the full saving, only one of ISA top-up and gift-to-spouse counted, and the conflict note naming the other by title;
  - (iii) married, £35,000, spouse earns £8,000: expect Marriage Allowance (B7).

  For each, check that the chat synthesis total equals the Tax Strategy page total on web and on `/m` (the `verify-m` skill). Screenshots go to `tests/Persona/savetax-strategy-fixes/reports/`. **iOS: I COULD NOT TEST THIS** unless it is run. The change is server-side and `estimatedAnnualTaxSaved` is already `Decimal?` in `TaxStrategyModels.swift:94`.

- [ ] **Step 5: PR to `dev`.** The PR body includes:
  - `Mobile impact: shared-backend`;
  - the reseed note (`TaxActionDefinitionSeeder` must run on deploy);
  - the follow-ups (`TaxDefaults` non-earner constants still read by `SpouseOptimisationService` and `RetirementActionDefinitionService`);
  - the evidence links.

  Deploy the branch to csjones (`git pull` plus the built `build/` and `m-build/`, then run the seeder there), walk household (ii) there, then ask CSJ for the admin-merge.

---

## Not in this plan (need a CSJ decision or new data capture)

- **B2 carry-forward** and **B3 Bed & ISA** stay unreachable from the walk. B2 needs three years of pension input history; B3 needs holdings with a purchase cost. Both are new capture questions, which is a spec change.
- **B14:** property answers feed no item. There is no rule to invent one.
- **B11:** not a bug (see Task 9 Step 3).
- **Allocator order:** with the Lifetime ISA saving now null, `IsaAllowanceAllocator` gives the shared ISA pool to real-tax items (ISA top-up, Bed & ISA) first. The Lifetime ISA only gets what is left. This is correct for the headline total, but it changes the suggested Lifetime ISA contribution for users who also have taxable interest.
- **Tapered allowance warning order:** the composer orders by saving, so with a null saving the warning moves below the saving items in `composed_plan.items`. The calculator's own `recommendations` still list it first.
