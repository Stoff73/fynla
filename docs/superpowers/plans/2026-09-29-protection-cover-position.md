# Protection Cover Position Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show, for life cover, critical illness and income protection, what the user needs, what their own policies give, what their job gives and whether they are short or over. Then fold the overlapping protection cards into one card per cover type.

**Architecture:**
- One service, `ProtectionCoverPosition`, turns the analyser's needs and coverage into three positions.
- The comprehensive plan (which drives the cards) and the Protection page API both read it.
- `ProtectionActionDefinitionService` evaluates every definition as today. It then folds the gap and reliance definitions into three position cards, as their reasons.
- Web and `/m` render a "Your cover" section from the API.

**Tech Stack:** Laravel 10 (PHP 8.x, Pest), Vue 3 (web SPA and the isolated `/m` bundle), MySQL 8.

**Spec:** `docs/superpowers/specs/2026-09-29-protection-cover-position-design.md` (CSJ approved 2026-09-29)

## Global Constraints

- **Rule 2:** no hardcoded tax or planning values. Every threshold and multiplier comes from `TaxConfigService` (`protection.*`), with no literal fallback.
- **Rule 12:** no scores or ratings in user-facing text. Use currency, percentages and plain words.
- **Rule 15:** no icons, emoji or Unicode glyphs anywhere in this work.
- **Rule 19:** done means verified on web and `/m`.
- **Rule 23:** every how-to claim names its source.
- **Copy:** British spelling, no cold acronyms, never "Fynla" as the actor in how-to steps.
- **Rule 3:** form modals emit `save` (none are added here).
- **Rule 11:** palette tokens only, and no hex in `<style>`.
- **Currency** on web via `currencyMixin`, on `/m` via `resources/mobile/utils/currency.js` `formatCurrency`.
- **Income protection is monthly everywhere the user sees it** (spec). Lump sums for life and critical illness.
- **Need source (spec):**
  - life = `needs.total_need`;
  - critical illness = `needs.gross_income × protection.income_multipliers.critical_illness`;
  - income protection = `needs.income_protection_need / 12`.
- **Reliance threshold:** `protection.dis_reliance_percent`, seeded as `0.50` (the value that runs today via fallback).
- **Folded keys (spec):**
  - life: `life_insurance_gap`, `dependants_no_life_cover`, `mortgage_no_decreasing_term`, `education_funding_gap`, `dis_reliance_warning`, `non_earning_spouse_no_cover`;
  - critical illness: `critical_illness_gap`, `no_ci_with_mortgage`, `ci_combined_risk`;
  - income: `income_protection_gap`, `ip_gap_after_state_benefits`, `self_employed_no_ip`, `ip_any_occupation_definition`, `group_ip_any_occupation`, `ip_short_benefit_period`, `ip_long_deferred_period`.
- **Position keys and ids:**
  - `life_cover_position` becomes `protection_life_cover_position`;
  - `critical_illness_position` becomes `protection_critical_illness_position`;
  - `income_protection_position` becomes `protection_income_protection_position`.
- **Never run a full suite.** Run only the test files named in each task.

## Review Focus

1. **Income unknown (gross income £0):** the critical illness and income protection needs are £0. A policy the user holds must not read as "over by" its whole value. Over is shown only when need > 0. Pinned in Task 2.
2. **Joint-life policy held by the spouse (the W-0401 household):** the non-owning spouse's life position counts the joint policy as their own cover, and is not short. Pinned in Task 2 (`forUser`).
3. **Group income protection is annual in the analyser:** a £60,000 salary at 50% must show £2,500 a month through the job, not £30,000. Pinned in Task 2.
4. **A figure changes but the card id doesn't:** the life position card keeps `protection_life_cover_position` when the shortfall moves. Pinned in Task 4.
5. **Job cover only, no shortfall:** a user whose life cover is all death in service, and who isn't short, still gets the life card, headed "depends on your job". Pinned in Task 4.

---

### Task 1: Reliance threshold in configuration; no literal fallbacks

**Files:**
- Modify: `database/seeders/TaxConfigurationSeeder.php` (the `'protection' => [` block, ~line 1063)
- Modify: `app/Services/Protection/CoverageGapAnalyzer.php:221` (`dis_reliance_percent` fallback) and `:475` (`income_protection_max_benefit` fallback)
- Modify: `app/Services/Protection/ProtectionActionDefinitionService.php:633` (`dis_reliance_percent` fallback)
- Test: `tests/Unit/Services/Protection/ProtectionConfigThresholdsTest.php`

**Interfaces:**
- Produces: config key `protection.dis_reliance_percent` (float `0.5`) in every seeded tax year.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('seeds the death in service reliance threshold the analyser reads', function () {
    expect(app(TaxConfigService::class)->get('protection.dis_reliance_percent'))->toEqual(0.5);
});

it('reads the protection thresholds with no literal fallback in code', function () {
    foreach (['app/Services/Protection/CoverageGapAnalyzer.php', 'app/Services/Protection/ProtectionActionDefinitionService.php'] as $file) {
        $source = file_get_contents(base_path($file));
        expect($source)->not->toMatch("/get\\('protection\\.dis_reliance_percent', [0-9.]+\\)/")
            ->and($source)->not->toMatch("/income_protection_max_benefit', [0-9.]+\\)/");
    }
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Protection/ProtectionConfigThresholdsTest.php`
Expected: FAIL. The key is null, and the source still has `, 0.50)` and `, 0.60)`.

- [ ] **Step 3: Seed the key and remove the fallbacks**

In `TaxConfigurationSeeder.php`, inside `'protection' => [`, directly after the `income_multipliers` array:

```php
                // Death in service above this share of total life cover means the
                // cover depends on the job (ends on leaving). The value that ran as
                // a code fallback until 2026-09-29, now configuration (Rule 2).
                'dis_reliance_percent' => 0.50,
```

In `CoverageGapAnalyzer.php`, replace `$this->taxConfig->get('protection.dis_reliance_percent', 0.50)` with `$this->taxConfig->get('protection.dis_reliance_percent')`, and `$this->taxConfig->get('protection.income_multipliers.income_protection_max_benefit', 0.60)` with `$this->taxConfig->get('protection.income_multipliers.income_protection_max_benefit')`.

In `ProtectionActionDefinitionService.php:633`, replace `$this->taxConfig->get('protection.dis_reliance_percent', 0.50)` with `$this->taxConfig->get('protection.dis_reliance_percent')`.

- [ ] **Step 4: Run it and confirm it passes, then the neighbours**

Run: `./vendor/bin/pest tests/Unit/Services/Protection/ProtectionConfigThresholdsTest.php tests/Unit/Services/Coordination/ComposedProtectionPlanTest.php tests/Feature/Protection/EmployerBenefitsTest.php`
Expected: PASS.

- [ ] **Step 5: Reseed locally and commit**

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
git add database/seeders/TaxConfigurationSeeder.php app/Services/Protection/CoverageGapAnalyzer.php app/Services/Protection/ProtectionActionDefinitionService.php tests/Unit/Services/Protection/ProtectionConfigThresholdsTest.php
git commit -m "fix(protection): reliance threshold is configuration; no literal fallbacks (Rule 2)"
```

---

### Task 2: `ProtectionCoverPosition`, the one calculation

**Files:**
- Create: `app/Services/Protection/ProtectionCoverPosition.php`
- Test: `tests/Unit/Services/Protection/ProtectionCoverPositionTest.php`

**Interfaces:**
- Consumes: `CoverageGapAnalyzer::calculateProtectionNeeds(ProtectionProfile): array` (keys `total_need`, `gross_income`, `income_protection_need`) and `CoverageGapAnalyzer::calculateTotalCoverage(...)` (keys `life_coverage`, `critical_illness_coverage`, `income_protection_coverage`, `disability_coverage`, `sickness_illness_coverage`, and `employer_benefits` with `death_in_service`, `group_income_protection` and `group_critical_illness`, all annual). `LifeCoverReach::policiesCovering(User)`. `TaxConfigService`.
- Produces:
  - `ProtectionCoverPosition::fromAnalysis(array $needs, array $coverage): array` returns `['life' => P, 'critical_illness' => P, 'income_protection' => P]`.
  - `ProtectionCoverPosition::forUser(User $user): array` returns the same shape, or `[]` with no profile.
  - Each `P` is `{need: float, own_cover: float, employer_cover: float, total_cover: float, short_by: float, over_by: float, employer_share: float (0..1), depends_on_job: bool, status: 'short'|'over'|'covered', unit: 'lump_sum'|'monthly'}`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\ProtectionCoverPosition;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

function coverage(array $overrides = [], array $employer = []): array
{
    return array_merge([
        'life_coverage' => 0.0, 'critical_illness_coverage' => 0.0, 'income_protection_coverage' => 0.0,
        'disability_coverage' => 0.0, 'sickness_illness_coverage' => 0.0,
        'employer_benefits' => array_merge(['death_in_service' => 0.0, 'group_income_protection' => 0.0, 'group_critical_illness' => 0.0], $employer),
    ], $overrides);
}

it('splits life cover into own policies and the job, and says what is short', function () {
    $p = app(ProtectionCoverPosition::class)->fromAnalysis(
        ['total_need' => 645538, 'gross_income' => 72000, 'income_protection_need' => 43200],
        coverage(['life_coverage' => 288000], ['death_in_service' => 288000]),
    )['life'];

    expect($p)->toMatchArray(['need' => 645538.0, 'own_cover' => 0.0, 'employer_cover' => 288000.0, 'short_by' => 357538.0,
        'over_by' => 0.0, 'status' => 'short', 'depends_on_job' => true, 'unit' => 'lump_sum']);
});

it('works out the critical illness need from gross income and the configured multiple', function () {
    // protection.income_multipliers.critical_illness = 3 (TaxConfigurationSeeder)
    $p = app(ProtectionCoverPosition::class)->fromAnalysis(
        ['total_need' => 0, 'gross_income' => 72000, 'income_protection_need' => 0],
        coverage(['critical_illness_coverage' => 250000]),
    )['critical_illness'];

    expect($p)->toMatchArray(['need' => 216000.0, 'over_by' => 34000.0, 'short_by' => 0.0, 'status' => 'over']);
});

it('shows income protection a month, converting the annual group cover once', function () {
    $p = app(ProtectionCoverPosition::class)->fromAnalysis(
        ['total_need' => 0, 'gross_income' => 60000, 'income_protection_need' => 36000],
        coverage(['income_protection_coverage' => 30000], ['group_income_protection' => 30000]),
    )['income_protection'];

    expect($p)->toMatchArray(['need' => 3000.0, 'employer_cover' => 2500.0, 'own_cover' => 0.0, 'short_by' => 500.0, 'unit' => 'monthly']);
});

it('never calls cover "over" when the need is unknown', function () {
    $p = app(ProtectionCoverPosition::class)->fromAnalysis(
        ['total_need' => 0, 'gross_income' => 0, 'income_protection_need' => 0],
        coverage(['critical_illness_coverage' => 100000]),
    )['critical_illness'];

    expect($p['over_by'])->toBe(0.0)->and($p['status'])->toBe('covered');
});

it('does not flag job dependence at or below the configured share', function () {
    $p = app(ProtectionCoverPosition::class)->fromAnalysis(
        ['total_need' => 400000, 'gross_income' => 50000, 'income_protection_need' => 0],
        coverage(['life_coverage' => 400000], ['death_in_service' => 200000]),
    )['life'];

    expect($p['employer_share'])->toBe(0.5)->and($p['depends_on_job'])->toBeFalse()->and($p['status'])->toBe('covered');
});

it('counts a joint-life policy the spouse holds as the other life\'s own cover (W-0401)', function () {
    $owner = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => 150000, 'date_of_birth' => now()->subYears(45)]);
    $spouse = User::factory()->create(['marital_status' => 'married', 'spouse_id' => $owner->id, 'annual_employment_income' => 40000, 'date_of_birth' => now()->subYears(43)]);
    $owner->update(['spouse_id' => $spouse->id]);
    ProtectionProfile::factory()->create(['user_id' => $spouse->id, 'annual_income' => 40000, 'mortgage_balance' => 0, 'other_debts' => 0, 'number_of_dependents' => 0, 'dependents_ages' => []]);
    LifeInsurancePolicy::factory()->create(['user_id' => $owner->id, 'sum_assured' => 500000, 'joint_life' => true]);

    $life = app(ProtectionCoverPosition::class)->forUser($spouse->fresh())['life'];

    expect($life['own_cover'])->toBeGreaterThanOrEqual(500000.0);
});

it('has no position for a user with no protection profile', function () {
    expect(app(ProtectionCoverPosition::class)->forUser(User::factory()->create()))->toBe([]);
});
```

- [ ] **Step 2: Run them and confirm they fail**

Run: `./vendor/bin/pest tests/Unit/Services/Protection/ProtectionCoverPositionTest.php`
Expected: FAIL with "Class ProtectionCoverPosition not found".

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\TaxConfigService;

/**
 * The ONE calculation of where a user stands on each kind of cover (CSJ
 * 2026-09-29): what they need, what their own policies and their job give
 * them, and whether they are short or over. The Protection page and the
 * protection cards both read it, so their figures cannot drift (Rule 20).
 *
 * Needs are the Protection page's own (CoverageGapAnalyzer): life is the total
 * need; critical illness is gross earned income times
 * protection.income_multipliers.critical_illness; income protection is the
 * configured share of gross earned income. Income protection is monthly: the
 * analyser holds it and group income protection as annual amounts, divided by
 * 12 here, once.
 */
final class ProtectionCoverPosition
{
    public const TYPES = ['life', 'critical_illness', 'income_protection'];

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly CoverageGapAnalyzer $gapAnalyzer,
        private readonly LifeCoverReach $lifeCoverReach,
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function forUser(User $user): array
    {
        $profile = ProtectionProfile::where('user_id', $user->id)->first();
        if ($profile === null) {
            return [];
        }
        $user->loadMissing(['criticalIllnessPolicies', 'incomeProtectionPolicies', 'disabilityPolicies', 'sicknessIllnessPolicies']);
        $coverage = $this->gapAnalyzer->calculateTotalCoverage(
            $this->lifeCoverReach->policiesCovering($user),
            $user->criticalIllnessPolicies,
            $user->incomeProtectionPolicies,
            $user->disabilityPolicies,
            $user->sicknessIllnessPolicies,
            $profile,
            $user,
        );

        return $this->fromAnalysis($this->gapAnalyzer->calculateProtectionNeeds($profile), $coverage);
    }

    /**
     * @param  array<string, mixed>  $needs  CoverageGapAnalyzer::calculateProtectionNeeds
     * @param  array<string, mixed>  $coverage  CoverageGapAnalyzer::calculateTotalCoverage
     * @return array<string, array<string, mixed>>
     */
    public function fromAnalysis(array $needs, array $coverage): array
    {
        $employer = (array) ($coverage['employer_benefits'] ?? []);
        $gross = (float) ($needs['gross_income'] ?? 0);
        $incomeCover = (float) ($coverage['income_protection_coverage'] ?? 0)
            + (float) ($coverage['disability_coverage'] ?? 0)
            + (float) ($coverage['sickness_illness_coverage'] ?? 0);

        return [
            'life' => $this->position(
                (float) ($needs['total_need'] ?? 0),
                (float) ($coverage['life_coverage'] ?? 0),
                (float) ($employer['death_in_service'] ?? 0),
                'lump_sum',
            ),
            'critical_illness' => $this->position(
                $gross * (float) $this->taxConfig->get('protection.income_multipliers.critical_illness'),
                (float) ($coverage['critical_illness_coverage'] ?? 0),
                (float) ($employer['group_critical_illness'] ?? 0),
                'lump_sum',
            ),
            'income_protection' => $this->position(
                (float) ($needs['income_protection_need'] ?? 0) / 12,
                $incomeCover / 12,
                (float) ($employer['group_income_protection'] ?? 0) / 12,
                'monthly',
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function position(float $need, float $total, float $employer, string $unit): array
    {
        $threshold = (float) $this->taxConfig->get('protection.dis_reliance_percent');
        $share = $total > 0 ? $employer / $total : 0.0;
        $short = max(0.0, $need - $total);
        // An unknown need (no income recorded) is not a reason to call cover excess.
        $over = $need > 0 ? max(0.0, $total - $need) : 0.0;

        return [
            'need' => round($need, 2),
            'own_cover' => round(max(0.0, $total - $employer), 2),
            'employer_cover' => round($employer, 2),
            'total_cover' => round($total, 2),
            'short_by' => round($short, 2),
            'over_by' => round($over, 2),
            'employer_share' => round($share, 4),
            'depends_on_job' => $employer > 0 && $share > $threshold,
            'status' => $short > 0 ? 'short' : ($over > 0 ? 'over' : 'covered'),
            'unit' => $unit,
        ];
    }
}
```

- [ ] **Step 4: Run them and confirm they pass**

Run: `./vendor/bin/pest tests/Unit/Services/Protection/ProtectionCoverPositionTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Protection/ProtectionCoverPosition.php tests/Unit/Services/Protection/ProtectionCoverPositionTest.php
git commit -m "feat(protection): ProtectionCoverPosition, one calculation per cover type"
```

---

### Task 3: The plan's coverage analysis reads the position

The cards' gap figures come from `ComprehensiveProtectionPlanService::buildCoverageAnalysis`, which hardcodes critical illness at 3 times income (`:425`) and income protection at 70% of net (`:431`). It also subtracts critical illness cover from the life gap (`gaps.total_gap`). After this task, both the cards and the page read one position.

**Files:**
- Modify: `app/Services/Protection/ComprehensiveProtectionPlanService.php` (`__construct`, `generateComprehensiveProtectionPlan` return array, `buildCoverageAnalysis`)
- Test: `tests/Unit/Services/Protection/PlanCoverageMatchesPositionTest.php`

**Interfaces:**
- Consumes: `ProtectionCoverPosition::fromAnalysis(array $needs, array $coverage)` (Task 2).
- Produces:
  - `generateComprehensiveProtectionPlan()` gains the key `cover_position`, the Task 2 shape.
  - `coverage_analysis.{life_insurance|critical_illness|income_protection}.{need,coverage,gap}` now equal the position's `need`, `total_cover` and `short_by`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\CriticalIllnessPolicy;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\ComprehensiveProtectionPlanService;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('gives the cards the same need, cover and shortfall as the cover position', function () {
    $user = User::factory()->create(['employment_status' => 'employed', 'annual_employment_income' => 60000, 'annual_self_employment_income' => 0,
        'annual_rental_income' => 0, 'annual_dividend_income' => 0, 'annual_other_income' => 0, 'annual_expenditure' => 30000,
        'date_of_birth' => now()->subYears(40)]);
    ProtectionProfile::factory()->create(['user_id' => $user->id, 'annual_income' => 60000, 'monthly_expenditure' => 2500,
        'mortgage_balance' => 0, 'other_debts' => 0, 'number_of_dependents' => 0, 'dependents_ages' => []]);
    CriticalIllnessPolicy::factory()->create(['user_id' => $user->id, 'sum_assured' => 50000]);

    $plan = app(ComprehensiveProtectionPlanService::class)->generateComprehensiveProtectionPlan($user->fresh());
    $ci = $plan['coverage_analysis']['critical_illness'];
    $position = $plan['cover_position']['critical_illness'];

    // 3 x £60,000 from configuration, not a literal.
    expect($position['need'])->toBe(180000.0)
        ->and((float) $ci['need'])->toBe($position['need'])
        ->and((float) $ci['coverage'])->toBe($position['total_cover'])
        ->and((float) $ci['gap'])->toBe($position['short_by'])
        ->and((float) $plan['coverage_analysis']['life_insurance']['gap'])->toBe($plan['cover_position']['life']['short_by']);
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Protection/PlanCoverageMatchesPositionTest.php`
Expected: FAIL, because `cover_position` is undefined.

- [ ] **Step 3: Implement**

In the constructor, add `private readonly ProtectionCoverPosition $coverPosition,` (same namespace, no import needed).

In `generateComprehensiveProtectionPlan`, compute once before the return:

```php
        // One calculation for the cards and the page (ProtectionCoverPosition).
        $position = $this->coverPosition->fromAnalysis((array) ($data['needs'] ?? []), (array) ($data['coverage'] ?? []));
```

Then add `'cover_position' => $position,` to the returned array, and change the call to `'coverage_analysis' => $this->buildCoverageAnalysis($data, $position),`.

Replace `buildCoverageAnalysis` with:

```php
    /**
     * Need, cover and shortfall per cover type, from the one cover position
     * (the hardcoded 3x income and 70%-of-net needs are gone, CSJ 2026-09-29).
     *
     * @param  array<string, array<string, mixed>>  $position
     */
    private function buildCoverageAnalysis(array $data, array $position): array
    {
        $adequacyScore = $data['adequacy_score'] ?? [];
        $row = function (string $type, string $scoreKey) use ($position, $adequacyScore): array {
            $p = $position[$type];

            return [
                'need' => $p['need'],
                'coverage' => $p['total_cover'],
                'gap' => $p['short_by'],
                'coverage_percentage' => $p['need'] > 0 ? round(($p['total_cover'] / $p['need']) * 100, 1) : 100,
                'status' => $this->getCoverageStatus($adequacyScore[$scoreKey] ?? 0),
            ];
        };

        return [
            'life_insurance' => $row('life', 'life_insurance_score'),
            'critical_illness' => $row('critical_illness', 'critical_illness_score'),
            'income_protection' => $row('income_protection', 'income_protection_score'),
            'overall_rating' => $adequacyScore['rating'] ?? 'N/A',
        ];
    }
```

- [ ] **Step 4: Run it and the neighbours**

Run: `./vendor/bin/pest tests/Unit/Services/Protection/PlanCoverageMatchesPositionTest.php tests/Unit/Services/Protection/ProtectionActionDefinitionServiceTest.php tests/Unit/Services/Protection/ComprehensiveProtectionPlanProfileSourceTest.php tests/Unit/Services/Coordination/ComposedProtectionPlanTest.php`
Expected: PASS. If a `ProtectionActionDefinitionServiceTest` case asserted the old 3× or 70%-of-net figures, update its expected figure to the configured basis, and say so in the commit message.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Protection/ComprehensiveProtectionPlanService.php tests/Unit/Services/Protection/PlanCoverageMatchesPositionTest.php
git commit -m "fix(protection): cards read the cover position; configured critical illness and income needs"
```

---

### Task 4: Three position cards replace the folded cards

**Files:**
- Modify: `database/seeders/ProtectionActionDefinitionSeeder.php` (add three rows)
- Modify: `app/Services/Protection/ProtectionActionDefinitionService.php` (dispatcher arm `cover_position`, `evaluateActions` consolidation, `education_gap` var)
- Modify: `app/Services/Mobile/RecommendationRouting.php` (three FYN keys)
- Test: `tests/Unit/Services/Protection/ProtectionPositionCardsTest.php`

**Interfaces:**
- Consumes: `$comprehensivePlan['cover_position']` (Task 3); `buildRecommendation(ProtectionActionDefinition, array $vars, float $coverageAmount, ?int $policyId = null)`, existing.
- Produces: recs with `definition_key` `life_cover_position`, `critical_illness_position` or `income_protection_position`, whose `figures` include:
  - `need`, `own_cover`, `employer_cover`, `short_by`, `over_by` (money strings, with " a month" left to the copy);
  - `employer_share` (a whole-number string);
  - the booleans `is_short`, `is_over` and `depends_on_job`;
  - one boolean per folded key that fired (for example `mortgage_no_decreasing_term => true`);
  - each fired reason's own figures, except `gap_amount`, `need_amount`, `coverage_amount` and `description_text`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Coordination\PlanSources\ProtectionStrategySource;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);
});

function mortgagedEarner(array $profile = []): User
{
    $user = User::factory()->create(['employment_status' => 'employed', 'annual_employment_income' => 72000, 'annual_self_employment_income' => 0,
        'annual_rental_income' => 0, 'annual_dividend_income' => 0, 'annual_other_income' => 0, 'annual_expenditure' => 36000,
        'date_of_birth' => now()->subYears(40)]);
    ProtectionProfile::factory()->create(array_merge(['user_id' => $user->id, 'annual_income' => 72000, 'monthly_expenditure' => 3000,
        'mortgage_balance' => 200000, 'other_debts' => 0, 'number_of_dependents' => 0, 'dependents_ages' => [],
        'employer_benefits_recorded_at' => now()], $profile));

    return $user->fresh();
}

function protectionTypes(User $user): array
{
    return collect(app(ProtectionStrategySource::class)->recommendations($user))->pluck('type')->all();
}

it('folds the overlapping gap cards into one card per cover type', function () {
    $types = protectionTypes(mortgagedEarner());

    expect($types)->toContain('life_cover_position', 'critical_illness_position', 'income_protection_position')
        ->and($types)->not->toContain('life_insurance_gap')
        ->and($types)->not->toContain('mortgage_no_decreasing_term')
        ->and($types)->not->toContain('critical_illness_gap')
        ->and($types)->not->toContain('no_ci_with_mortgage')
        ->and($types)->not->toContain('income_protection_gap')
        ->and($types)->not->toContain('ip_gap_after_state_benefits')
        // Separate cards stay.
        ->and($types)->toContain('no_policies_warning');
});

it('carries the position and every reason that fired to the card', function () {
    $life = collect(app(ProtectionStrategySource::class)->recommendations(mortgagedEarner()))->firstWhere('type', 'life_cover_position');

    expect($life->title)->toStartWith('Your life cover is £')
        ->and($life->extra['figures'])->toMatchArray(['is_short' => true, 'mortgage_no_decreasing_term' => true])
        ->and($life->extra['figures']['mortgage_amount'])->toStartWith('£')
        ->and($life->extra['figures'])->not->toHaveKey('gap_amount');
});

it('keeps the card id when the shortfall changes', function () {
    $user = mortgagedEarner();
    $before = collect(app(ProtectionStrategySource::class)->recommendations($user))->firstWhere('type', 'life_cover_position');
    $user->protectionProfile->update(['mortgage_balance' => 150000]);
    $after = collect(app(ProtectionStrategySource::class)->recommendations($user->fresh()))->firstWhere('type', 'life_cover_position');

    expect($after->type)->toBe($before->type)->and($after->title)->not->toBe($before->title);
});

it('shows the life card when the cover depends on the job even with no shortfall', function () {
    // Death in service 10 x £72,000 = £720,000 covers the need, all of it through the job.
    $user = mortgagedEarner(['death_in_service_multiple' => 10, 'mortgage_balance' => 0]);
    $life = collect(app(ProtectionStrategySource::class)->recommendations($user))->firstWhere('type', 'life_cover_position');

    expect($life)->not->toBeNull()
        ->and($life->extra['figures'])->toMatchArray(['depends_on_job' => true, 'is_short' => false]);
});
```

- [ ] **Step 2: Run them and confirm they fail**

Run: `./vendor/bin/pest tests/Unit/Services/Protection/ProtectionPositionCardsTest.php`
Expected: FAIL, because no `life_cover_position` type exists.

- [ ] **Step 3: Seed the three definitions**

Add them to the seeder array in `ProtectionActionDefinitionSeeder.php`, next to the other agent rows:

```php
            [
                'key' => 'life_cover_position',
                'source' => 'agent',
                'title_template' => '{headline}',
                'description_template' => '{summary}',
                'action_template' => 'See how your life cover compares with what your family would need.',
                'category' => 'Life Insurance',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => ['condition' => 'cover_position', 'cover_type' => 'life'],
                'is_enabled' => true,
                'sort_order' => 5,
                'notes' => 'CSJ 2026-09-29: one card per cover type (ProtectionCoverPosition). Folds life_insurance_gap, dependants_no_life_cover, mortgage_no_decreasing_term, education_funding_gap, dis_reliance_warning, non_earning_spouse_no_cover as reasons.',
            ],
            [
                'key' => 'critical_illness_position',
                'source' => 'agent',
                'title_template' => '{headline}',
                'description_template' => '{summary}',
                'action_template' => 'See how your critical illness cover compares with what you would need.',
                'category' => 'Critical Illness',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => ['condition' => 'cover_position', 'cover_type' => 'critical_illness'],
                'is_enabled' => true,
                'sort_order' => 6,
                'notes' => 'CSJ 2026-09-29: folds critical_illness_gap, no_ci_with_mortgage, ci_combined_risk as reasons.',
            ],
            [
                'key' => 'income_protection_position',
                'source' => 'agent',
                'title_template' => '{headline}',
                'description_template' => '{summary}',
                'action_template' => 'See how your income protection compares with the income you would need.',
                'category' => 'Income Protection',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => ['condition' => 'cover_position', 'cover_type' => 'income_protection'],
                'is_enabled' => true,
                'sort_order' => 7,
                'notes' => 'CSJ 2026-09-29: folds income_protection_gap, ip_gap_after_state_benefits, self_employed_no_ip, ip_any_occupation_definition, group_ip_any_occupation, ip_short_benefit_period, ip_long_deferred_period as reasons.',
            ],
```

- [ ] **Step 4: Consolidate in `ProtectionActionDefinitionService`**

Add the constants at the top of the class:

```php
    /** CSJ 2026-09-29: these fire as reasons on one card per cover type, never as cards. */
    private const FOLDED = [
        'life' => ['life_insurance_gap', 'dependants_no_life_cover', 'mortgage_no_decreasing_term', 'education_funding_gap', 'dis_reliance_warning', 'non_earning_spouse_no_cover'],
        'critical_illness' => ['critical_illness_gap', 'no_ci_with_mortgage', 'ci_combined_risk'],
        'income_protection' => ['income_protection_gap', 'ip_gap_after_state_benefits', 'self_employed_no_ip', 'ip_any_occupation_definition', 'group_ip_any_occupation', 'ip_short_benefit_period', 'ip_long_deferred_period'],
    ];

    private const POSITION_KEYS = ['life' => 'life_cover_position', 'critical_illness' => 'critical_illness_position', 'income_protection' => 'income_protection_position'];

    private const COVER_NAMES = ['life' => 'life cover', 'critical_illness' => 'critical illness cover', 'income_protection' => 'income protection'];

    /** Reason figures the position states itself. */
    private const POSITION_OWNED_FIGURES = ['gap_amount', 'need_amount', 'coverage_amount', 'description_text'];
```

In the `evaluateDefinition` match, add this arm with the others:

```php
            // Built from the other definitions' results in consolidate().
            'cover_position' => null,
```

In `evaluateActions`, directly before the `usort(...)`, add:

```php
        $recommendations = $this->consolidate($recommendations, $comprehensivePlan);
```

Then add the method:

```php
    /**
     * One card per cover type (CSJ 2026-09-29): the folded definitions leave the
     * list and become the reasons on their cover type's position card, which
     * shows when the cover is short, over or depends on the job, or any reason fired.
     *
     * @param  list<array<string, mixed>>  $recommendations
     * @return list<array<string, mixed>>
     */
    private function consolidate(array $recommendations, array $comprehensivePlan): array
    {
        $positions = (array) ($comprehensivePlan['cover_position'] ?? []);
        $definitions = ProtectionActionDefinition::getEnabled()->keyBy('key');

        foreach (self::FOLDED as $type => $keys) {
            $reasons = array_values(array_filter($recommendations, static fn (array $r): bool => in_array($r['definition_key'] ?? null, $keys, true)));
            $recommendations = array_values(array_filter($recommendations, static fn (array $r): bool => ! in_array($r['definition_key'] ?? null, $keys, true)));

            $position = $positions[$type] ?? null;
            $definition = $definitions->get(self::POSITION_KEYS[$type]);
            if ($position === null || $definition === null) {
                continue;
            }
            $fires = $position['short_by'] > 0 || $position['over_by'] > 0 || $position['depends_on_job'] || $reasons !== [];
            if (! $fires) {
                continue;
            }

            $rec = $this->buildRecommendation($definition, $this->positionVars($type, $position, $reasons), (float) $position['short_by']);
            // The most urgent reason sets the card's urgency.
            $rec['priority'] = min(array_merge([$rec['priority']], array_column($reasons, 'priority')));
            $rec['decision_trace'] = array_merge(...array_map(static fn (array $r): array => (array) ($r['decision_trace'] ?? []), $reasons ?: [[]]));
            $recommendations[] = $rec;
        }

        return $recommendations;
    }

    /**
     * @param  array<string, mixed>  $position
     * @param  list<array<string, mixed>>  $reasons
     * @return array<string, mixed>
     */
    private function positionVars(string $type, array $position, array $reasons): array
    {
        $monthly = $position['unit'] === 'monthly';
        $money = fn (float $v): string => $this->formatCurrency($v).($monthly ? ' a month' : '');
        $name = self::COVER_NAMES[$type];
        $share = (string) (int) round($position['employer_share'] * 100);

        $headline = match (true) {
            $position['short_by'] > 0 => 'Your '.$name.' is '.$money($position['short_by']).' short',
            $position['over_by'] > 0 => 'Your '.$name.' is '.$money($position['over_by']).' more than you need',
            $position['depends_on_job'] => 'Most of your '.$name.' depends on your job',
            default => 'Review your '.$name,
        };
        $summary = 'You need '.$money($position['need']).'. Your own policies give '.$money($position['own_cover'])
            .' and your job gives '.$money($position['employer_cover']).'.';
        $titles = array_values(array_filter(array_map(static fn (array $r): string => (string) ($r['action'] ?? ''), $reasons)));
        if ($titles !== []) {
            $summary .= ' '.implode('. ', $titles).'.';
        }

        $vars = [
            'headline' => $headline,
            'summary' => $summary,
            'need' => $this->formatCurrency($position['need']),
            'own_cover' => $this->formatCurrency($position['own_cover']),
            'employer_cover' => $this->formatCurrency($position['employer_cover']),
            'short_by' => $this->formatCurrency($position['short_by']),
            'over_by' => $this->formatCurrency($position['over_by']),
            'employer_share' => $share,
            'is_short' => $position['short_by'] > 0,
            'is_over' => $position['over_by'] > 0,
            'depends_on_job' => (bool) $position['depends_on_job'],
        ];
        foreach ($reasons as $reason) {
            $vars[(string) $reason['definition_key']] = true;
            foreach ((array) ($reason['figures'] ?? []) as $key => $value) {
                if (! in_array($key, self::POSITION_OWNED_FIGURES, true) && ! array_key_exists($key, $vars)) {
                    $vars[$key] = $value;
                }
            }
        }

        return $vars;
    }
```

In `evaluateEducationFundingGap`, add `'education_gap' => $this->formatCurrency($educationGap),` to its `$vars`, so the reason keeps its own figure once `gap_amount` belongs to the position.

- [ ] **Step 5: Route the three cards to Fyn capture**

In `RecommendationRouting::FYN`, after the protection definition keys:

```php
        'protection_life_cover_position' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_critical_illness_position' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_income_protection_position' => ['action' => 'add', 'resource_type' => 'protection'],
```

- [ ] **Step 6: Run the tests, then the neighbours**

Run: `./vendor/bin/pest tests/Unit/Services/Protection/ProtectionPositionCardsTest.php tests/Unit/Services/Coordination/ComposedProtectionPlanTest.php tests/Feature/Database/ActionDefinitionDispatchCoverageTest.php tests/Unit/Services/Protection/ProtectionActionDefinitionServiceTest.php tests/Feature/Protection/EmployerBenefitsTest.php`
Expected: PASS. `ComposedProtectionPlanTest` W-0401 assertions still hold: the spouse's life position is not short, so no `life_cover_position` with `is_short`. If its `not->toContain('life_insurance_gap')` now passes vacuously, add `->and(collect(...)->firstWhere('type', 'life_cover_position')?->extra['figures']['is_short'] ?? false)->toBeFalse()` to keep the assertion meaningful.

- [ ] **Step 7: Reseed locally and commit**

```bash
php artisan db:seed --class=ProtectionActionDefinitionSeeder --force
git add database/seeders/ProtectionActionDefinitionSeeder.php app/Services/Protection/ProtectionActionDefinitionService.php app/Services/Mobile/RecommendationRouting.php tests/Unit/Services/Protection/ProtectionPositionCardsTest.php tests/Unit/Services/Coordination/ComposedProtectionPlanTest.php
git commit -m "feat(protection): one card per cover type with the reasons that fired"
```

---

### Task 5: "Your cover" on the Protection page, web and /m

**Files:**
- Modify: `app/Http/Controllers/Api/ProtectionController.php` (`index` response and constructor)
- Modify: `resources/js/store/modules/protection.js` (state `coverPosition`, mutation `setCoverPosition`, commit in `fetchProtectionData`)
- Create: `resources/js/components/Protection/CoverPositionSection.vue`
- Modify: `resources/js/views/Protection/ProtectionDashboard.vue` (render the section above the overview)
- Modify: `resources/mobile/views/modules/Protection.vue` (section above "Coverage gaps")
- Test: `tests/Feature/Protection/CoverPositionApiTest.php`

**Interfaces:**
- Consumes: `ProtectionCoverPosition::forUser(User)` (Task 2).
- Produces: `GET /api/protection` gains `data.cover_position` with the Task 2 shape.

- [ ] **Step 1: Write the failing API test**

```php
<?php

declare(strict_types=1);

use App\Models\ProtectionProfile;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

it('returns the cover position with the protection data', function () {
    $this->seed(TaxConfigurationSeeder::class);
    $user = User::factory()->create(['annual_employment_income' => 60000, 'date_of_birth' => now()->subYears(40)]);
    ProtectionProfile::factory()->create(['user_id' => $user->id, 'annual_income' => 60000, 'mortgage_balance' => 0, 'other_debts' => 0,
        'number_of_dependents' => 0, 'dependents_ages' => [], 'death_in_service_multiple' => 4]);
    Sanctum::actingAs($user);

    $this->getJson('/api/protection')->assertOk()
        ->assertJsonPath('data.cover_position.life.employer_cover', 240000)
        ->assertJsonPath('data.cover_position.income_protection.unit', 'monthly')
        ->assertJsonStructure(['data' => ['cover_position' => ['life' => ['need', 'own_cover', 'employer_cover', 'short_by', 'over_by', 'depends_on_job', 'status']]]]);
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `./vendor/bin/pest tests/Feature/Protection/CoverPositionApiTest.php`
Expected: FAIL (`data.cover_position` is missing).

- [ ] **Step 3: API**

Inject `private readonly ProtectionCoverPosition $coverPosition,` into the `ProtectionController` constructor, and add `use App\Services\Protection\ProtectionCoverPosition;`. In `index`, beside `'coverage_gaps' => ...`, add:

```php
                // Where the user stands per cover type: the same calculation the cards use.
                'cover_position' => $this->coverPosition->forUser($user),
```

Run the test. Expected: PASS.

- [ ] **Step 4: Web store and section**

In `protection.js`:
- state: `coverPosition: null,`
- mutation: `setCoverPosition(state, value) { state.coverPosition = value; },`
- in `fetchProtectionData`, after `commit('setPolicies', ...)`: `commit('setCoverPosition', data.cover_position || null);`

Create `CoverPositionSection.vue`:

```vue
<template>
  <section v-if="rows.length" class="bg-white rounded-lg border border-light-gray p-6" aria-labelledby="cover-position-heading">
    <h3 id="cover-position-heading" class="text-lg font-semibold text-horizon-500 mb-4">Your cover</h3>
    <div class="divide-y divide-light-gray">
      <div v-for="row in rows" :key="row.key" class="py-3">
        <button type="button" class="w-full flex items-center justify-between text-left" :aria-expanded="open === row.key" @click="open = open === row.key ? null : row.key">
          <span class="font-medium text-horizon-500">{{ row.label }}</span>
          <span :class="row.tone">{{ row.status }}</span>
        </button>
        <dl v-if="open === row.key" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
          <div><dt class="text-neutral-500">You need</dt><dd class="text-horizon-500 font-medium">{{ row.need }}</dd></div>
          <div><dt class="text-neutral-500">Your own policies</dt><dd class="text-horizon-500 font-medium">{{ row.own }}</dd></div>
          <div><dt class="text-neutral-500">Through your job (ends if you leave)</dt><dd class="text-horizon-500 font-medium">{{ row.job }}</dd></div>
        </dl>
      </div>
    </div>
  </section>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

const LABELS = { life: 'Life cover', critical_illness: 'Critical illness cover', income_protection: 'Income protection' };

/** Where the user stands per cover type (ProtectionCoverPosition, the cards' own figures). */
export default {
  name: 'CoverPositionSection',
  mixins: [currencyMixin],
  props: { position: { type: Object, default: null } },
  data: () => ({ open: null }),
  computed: {
    rows() {
      if (!this.position) return [];
      return Object.keys(LABELS).filter((key) => this.position[key]).map((key) => {
        const p = this.position[key];
        const money = (v) => this.formatCurrency(v) + (p.unit === 'monthly' ? ' a month' : '');
        const parts = [];
        if (p.short_by > 0) parts.push(`Short by ${money(p.short_by)}`);
        if (p.over_by > 0) parts.push(`Over by ${money(p.over_by)}`);
        if (p.depends_on_job) parts.push('Depends on your job');
        return {
          key,
          label: LABELS[key],
          status: parts.length ? parts.join(', ') : 'Covered',
          tone: p.short_by > 0 ? 'text-raspberry-600 font-medium' : (p.over_by > 0 || p.depends_on_job ? 'text-violet-600 font-medium' : 'text-spring-600 font-medium'),
          need: money(p.need),
          own: money(p.own_cover),
          job: money(p.employer_cover),
        };
      });
    },
  },
};
</script>
```

In `ProtectionDashboard.vue`:
- import `CoverPositionSection` and register it;
- add `'coverPosition'` to the `mapState('protection', [...])` list;
- render `<CoverPositionSection class="mb-6" :position="coverPosition" />` directly above the `<div class="bg-white rounded-lg border border-light-gray p-6">` that wraps `ProtectionModuleOverview`.

- [ ] **Step 5: /m section**

In `resources/mobile/views/modules/Protection.vue`, add a computed property:

```js
    coverRows() {
      const pos = this.payload?.cover_position || null;
      if (!pos) return [];
      const labels = { life: 'Life cover', critical_illness: 'Critical illness cover', income_protection: 'Income protection' };
      return Object.keys(labels).filter((k) => pos[k]).map((k) => {
        const p = pos[k];
        const money = (v) => this.fmt(v) + (p.unit === 'monthly' ? ' a month' : '');
        const parts = [];
        if (p.short_by > 0) parts.push(`Short by ${money(p.short_by)}`);
        if (p.over_by > 0) parts.push(`Over by ${money(p.over_by)}`);
        if (p.depends_on_job) parts.push('Depends on your job');
        return { key: k, label: labels[k], status: parts.length ? parts.join(', ') : 'Covered', need: money(p.need), own: money(p.own_cover), job: money(p.employer_cover) };
      });
    },
```

Then add this card directly above the "Coverage gaps" card:

```html
      <div v-if="coverRows.length" class="m-card">
        <p class="m-section-label" style="margin-top:0">Your cover</p>
        <div v-for="row in coverRows" :key="row.key" style="margin-bottom:12px">
          <p class="m-sub" style="margin-bottom:2px"><strong>{{ row.label }}</strong>: {{ row.status }}</p>
          <p class="m-sub" style="margin-bottom:0">You need {{ row.need }}. Your own policies give {{ row.own }}, and your job {{ row.job }} (ends if you leave).</p>
        </div>
      </div>
```

- [ ] **Step 6: Build and verify both surfaces locally**

```bash
./vendor/bin/pest tests/Feature/Protection/CoverPositionApiTest.php tests/Feature/Protection/ProtectionApiTest.php
npm run build:mobile
```

In Playwright:
- **Web, 1440×900:** as a user with a profile, open `/protection`. Confirm the "Your cover" rows show the same "short by" figure as the life card on `/actions/protection_life_cover_position`. Expand a row.
- **`/m`, 390×844:** open `/m/app/protection` and confirm the same figures.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/ProtectionController.php resources/js/store/modules/protection.js resources/js/components/Protection/CoverPositionSection.vue resources/js/views/Protection/ProtectionDashboard.vue resources/mobile/views/modules/Protection.vue tests/Feature/Protection/CoverPositionApiTest.php
git commit -m "feat(protection): Your cover section on the Protection page, web and /m"
```

---

### Task 6: One how-to per position card (draft, for CSJ)

**Files:**
- Modify: `database/seeders/data/action-how-to/protection.md` (header note plus three entries)
- Test: `tests/Unit/Services/Actions/ActionHowToSeederTest.php`, existing: every heading names a real key

**Interfaces:**
- Consumes: the Task 4 figures (`is_short`, `is_over`, `depends_on_job`, the reason booleans, `need`, `own_cover`, `employer_cover`, `short_by`, `over_by`, `employer_share`, `mortgage_amount`, `dependant_count`, `education_gap`, `ssp_weekly`, `ssp_weeks`, `ssp_total`, `provider`, `benefit_months`, `deferred_weeks`) and the household facts (`has_spouse`, `{spouse}`, `{spouse_start}`, `has_children`, `{children}`).

- [ ] **Step 1: Header note.** Under "**Not written, on purpose**" in `protection.md`, add:

```markdown
- **Folded into a position card (CSJ 2026-09-29):** `life_insurance_gap`, `dependants_no_life_cover`, `mortgage_no_decreasing_term`, `education_funding_gap`, `dis_reliance_warning`, `non_earning_spouse_no_cover` (life); `critical_illness_gap`, `no_ci_with_mortgage`, `ci_combined_risk` (critical illness); `income_protection_gap`, `ip_gap_after_state_benefits`, `self_employed_no_ip`, `ip_any_occupation_definition`, `group_ip_any_occupation`, `ip_short_benefit_period`, `ip_long_deferred_period` (income). They no longer show as cards; their approved entries below are the source the position entries were built from.
```

- [ ] **Step 2: Append the three entries**

```markdown
## life_cover_position
status: draft
source: every source under life_insurance_gap, mortgage_no_decreasing_term, education_funding_gap, dis_reliance_warning and non_earning_spouse_no_cover below (approved 2026-09-29); the cover position (`ProtectionCoverPosition`: need = total need, cover = life policies reaching you plus death in service)
figures: need, own_cover, employer_cover, short_by, over_by, employer_share, is_short, is_over, depends_on_job, mortgage_amount, dependant_count, education_gap; reasons: life_insurance_gap, dependants_no_life_cover, mortgage_no_decreasing_term, education_funding_gap, dis_reliance_warning, non_earning_spouse_no_cover
why when is_short:
1. Your family would need {need}. Your own policies give {own_cover} and your job {employer_cover}, so your life cover is {short_by} short.
why when is_over:
1. Your family would need {need}. Your own policies give {own_cover} and your job {employer_cover}, so you have {over_by} more life cover than you need.
why when depends_on_job:
2. {employer_share}% of your life cover comes through your job. It ends if you leave, change job or are made redundant.
why when dependants_no_life_cover:
3. {dependant_count} people depend on your income, and you have no life cover.
why when mortgage_no_decreasing_term:
4. You owe {mortgage_amount} on your mortgage, and no life policy you have recorded is set up to pay it off.
why when education_funding_gap:
5. Your children's education would be {education_gap} short.
why when non_earning_spouse_no_cover:
6. {spouse_start} has no earned income and no life cover. If they died, you would pay for the childcare and running of the home they now provide.
always:
1. Check the figures behind your need on the Protection page: your mortgage and other debts, your family's yearly income need, and your children's education.
when is_short:
2. Get quotes for level term life cover of about {short_by}, for as long as your family would need it. A protection adviser or a comparison service can quote several insurers at once.
when is_short and has_spouse:
3. Ask for quotes on single life policies for each of you and on a joint policy, and compare what each pays out and when.
when mortgage_no_decreasing_term:
4. If you already hold life cover for your mortgage, open it on the Protection page and tick "Is this to pay off your mortgage?". For a repayment mortgage, the cover for it can be decreasing term over the years left, falling as the balance does; for interest only, ask for level term.
when depends_on_job:
5. Check your employer's benefits booklet for how much the death in service pays, and get quotes for a personal policy that would replace it if you left.
when non_earning_spouse_no_cover:
6. Work out what childcare and help at home would cost each year, and get quotes for life cover on {spouse}'s life for that amount. {spouse_start} answers the health questions, fully and accurately.
when is_over:
2. Check whether you still need all of it, for example cover taken out for a debt you have since paid off. Do this before the policy next renews, and keep every policy until any change has started.
when not is_over:
7. Answer every health and lifestyle question fully and accurately. An insurer can refuse or reduce a claim if an answer was careless or wrong.
8. Once any new cover starts, add it on the Protection page with Add New Policy.
outcome when not is_over:
1. If you died, your family would have a lump sum to clear debts and replace your income, whatever happens to your job.
outcome when is_over:
1. You pay for the life cover your family needs, and no more.
learn:
1. How your protection shortfall is worked out | /help#protection

## critical_illness_position
status: draft
source: every source under critical_illness_gap, no_ci_with_mortgage and ci_combined_risk below (approved 2026-09-29); the cover position (need = gross earned income x `protection.income_multipliers.critical_illness`)
figures: need, own_cover, employer_cover, short_by, over_by, employer_share, is_short, is_over, depends_on_job, mortgage_amount, provider; reasons: critical_illness_gap, no_ci_with_mortgage, ci_combined_risk
why when is_short:
1. You would need {need} if a serious illness stopped you working. Your own policies give {own_cover} and your job {employer_cover}, so your cover is {short_by} short.
why when is_over:
1. You would need {need}. Your own policies give {own_cover} and your job {employer_cover}, so you have {over_by} more critical illness cover than you need.
why when depends_on_job:
2. {employer_share}% of your critical illness cover comes through your job, and it ends if you leave.
why when no_ci_with_mortgage:
3. You owe {mortgage_amount} on your mortgage.
why when ci_combined_risk:
4. Your policy with {provider} combines life and critical illness cover in one policy.
always:
1. Critical illness cover pays a tax-free lump sum if you are diagnosed with a condition the policy covers. Every policy covers cancer, heart attack and stroke, and the rest varies between insurers.
when is_short:
2. Get quotes for about {short_by} of cover, and compare which conditions each policy covers and how severe each must be to pay.
when no_ci_with_mortgage:
3. Include enough to clear your {mortgage_amount} mortgage.
when ci_combined_risk:
4. Check in your policy document whether a critical illness claim ends the life cover too. If your family would still need life cover after an illness, compare separate policies with what you pay now.
when is_over:
2. Check whether you still need all of it before the policy next renews, and keep every policy until any change has started.
when not is_over:
5. Answer every health and lifestyle question fully and accurately, and once any new cover starts, add it on the Protection page.
outcome when not is_over:
1. A serious diagnosis would come with a lump sum to clear debts or cover time off work.
outcome when is_over:
1. You pay for the critical illness cover you need, and no more.
learn:
1. How your protection shortfall is worked out | /help#protection

## income_protection_position
status: draft
source: every source under income_protection_gap, ip_gap_after_state_benefits, self_employed_no_ip, ip_any_occupation_definition, ip_short_benefit_period and ip_long_deferred_period below (approved 2026-09-29); the cover position (need = `protection.income_multipliers.income_protection_max_benefit` of gross earned income, a month)
figures: need, own_cover, employer_cover, short_by, over_by, employer_share, is_short, is_over, depends_on_job, ssp_weekly, ssp_weeks, ssp_total, provider, benefit_months, deferred_weeks; reasons: income_protection_gap, ip_gap_after_state_benefits, self_employed_no_ip, ip_any_occupation_definition, group_ip_any_occupation, ip_short_benefit_period, ip_long_deferred_period
why when is_short:
1. If illness or injury stopped you working, you would need {need} a month. Your own policies give {own_cover} and your job {employer_cover}, so you are {short_by} a month short.
why when is_over:
1. You would need {need} a month. Your own policies give {own_cover} and your job {employer_cover}, so you have {over_by} a month more than you need.
why when depends_on_job:
2. {employer_share}% of your income protection comes through your job, and it ends if you leave.
why when ip_gap_after_state_benefits:
3. Statutory Sick Pay pays up to {ssp_weekly} a week for up to {ssp_weeks} weeks, {ssp_total} in all.
why when self_employed_no_ip:
3. You are self-employed, so you cannot get Statutory Sick Pay.
why when ip_any_occupation_definition:
4. Your income protection with {provider} pays only if you cannot do any job at all, not just your own.
why when group_ip_any_occupation:
4. Your employer's income protection pays only if you cannot do any job at all, not just your own.
why when ip_short_benefit_period:
5. Your income protection with {provider} pays for up to {benefit_months} months for each claim.
why when ip_long_deferred_period:
6. Your income protection with {provider} starts paying {deferred_weeks} weeks after you stop work.
always:
1. Check what your employer pays when you are off sick, and for how long. Your contract or staff handbook says.
when is_short:
2. Get quotes for income protection of about {short_by} a month. It pays a monthly income while illness or injury stops you working. It does not pay if you are made redundant.
3. Choose when it starts paying, the deferred period, to begin when your sick pay ends or your savings would run out.
when ip_any_occupation_definition:
4. Read the definition of incapacity in your policy document, and ask {provider} whether the policy can change to pay if you cannot do your own job, and what that would cost.
when group_ip_any_occupation:
4. Read the definition of incapacity in your employer's scheme booklet, and get quotes for a personal policy that pays if you cannot do your own job.
when ip_short_benefit_period:
5. Ask {provider} what it would cost to extend the benefit period, up to your retirement age.
when ip_long_deferred_period:
6. Work out whether your sick pay and savings would cover your bills for the {deferred_weeks} weeks, and if not, ask {provider} what a shorter deferred period would cost.
when is_over:
2. Check whether you still need all of it before the policy next renews, and keep every policy until any change has started.
when not is_over:
7. Answer every health and lifestyle question fully and accurately, and once any new cover starts, add it on the Protection page.
outcome when not is_over:
1. Your household keeps an income if you cannot work.
outcome when is_over:
1. You pay for the income protection you need, and no more.
learn:
1. How your protection shortfall is worked out | /help#protection
```

Condition grammar: `ActionHowTo::holds` joins clauses only with `and` (an `or` works only inside `fact is a or b`), which is why the two occupation reasons are separate blocks.

- [ ] **Step 3: Parse, seed, and run the seeder test**

```bash
php artisan tinker --execute='print_r(array_keys(\App\Services\Actions\ActionHowTo::parse(file_get_contents(database_path("seeders/data/action-how-to/protection.md")))));'
php artisan db:seed --class=ActionHowToSeeder --force
./vendor/bin/pest tests/Unit/Services/Actions/ActionHowToSeederTest.php tests/Unit/Services/Actions/ActionCardProtectionHowToTest.php
```

Expected: the three keys are listed, and the tests pass.

- [ ] **Step 4: Render check.** Render each position entry against real local households with the Task 4 figures, using the scratchpad render script pattern from 2026-09-29 (`ActionHowTo::render` over `ActionHowToFacts::for($user, $rec->extra['figures'])`). Confirm no step shows a brace, and the `£0` cases read naturally.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/data/action-how-to/protection.md
git commit -m "docs(how-to): draft position how-tos built from the approved protection entries"
```

---

### Task 7: Verify on csjones and ship to dev

- [ ] **Step 1:** Run every test file this plan touched:
  - `ProtectionConfigThresholdsTest`, `ProtectionCoverPositionTest`, `PlanCoverageMatchesPositionTest`, `ProtectionPositionCardsTest`, `CoverPositionApiTest`;
  - `ComposedProtectionPlanTest`, `ProtectionActionDefinitionServiceTest`, `ActionDefinitionDispatchCoverageTest`, `EmployerBenefitsTest`;
  - `ActionHowToSeederTest`, `ActionCardProtectionHowToTest`, `ProtectionApiTest`.

  Then `./vendor/bin/pint` on the changed PHP files.
- [ ] **Step 2:** Push the branch.
  - Build with `./deploy/csjones-fynla/build.sh`.
  - On csjones, check out the branch. Write the ssh command out in full; zsh does not split `$VAR`.
  - Upload `public/build` and `public/m-build` with rsync, without `--delete`, so old chunks survive.
  - Run `php artisan db:seed --class=TaxConfigurationSeeder --force`, `--class=ProtectionActionDefinitionSeeder --force` and `--class=ActionHowToSeeder --force`, then `cache:clear`, `config:clear` and `route:clear`. Never `route:cache` or `optimize`.
- [ ] **Step 3:** Walk csjones as user 402 (`savetax-e2e-web-0916@example.com`, password `Password1!`, code via ssh tinker):
  - **Web, 1440×900:**
    - "Your cover" rows match the three cards' titles.
    - The life row shows £288,000 through the job, and "Depends on your job" if the share is above 50%.
    - Open the life card; its figures match the page.
  - **`/m`, 390×844:** the same figures, the same three cards, and no folded card on the list.
- [ ] **Step 4:** Rebuild local `/m` with `npm run build:mobile`, so the local bundle uses the local base path again.
- [ ] **Step 5:** Open the PR to `dev`, admin-merge it, and put csjones back on `dev` (`git checkout dev && git pull origin dev`). Then tell CSJ the three position how-tos are draft on `dev` for approval.
