# Threshold Position Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show each user the tax and benefit lines that apply to them, nearest first, with the pounds-a-year cost of crossing and the one lever, on web, `/m` and iOS.

**Architecture:** A `ThresholdPositionService` evaluator runs a catalogue of `ThresholdLine` classes against one user's income definitions and estate, sorts by proximity, and returns one strip line plus the rest. Cost is a delta of two full `UKTaxCalculator::calculateNetIncome` runs over the user's actual income mix. New income reaches everything through `IncomeDefinitionsService` alone: a `vesting` component from `VestScheduleResolver` and DC drawdown income from `ResolvesIncome`. One endpoint, `GET /api/thresholds`, serves three surfaces.

**Tech Stack:** Laravel 10, Pest, Vue 3 (web SPA in `resources/js/`, isolated `/m` bundle in `resources/mobile/`), Vitest, SwiftUI with Swift Testing.

**Spec:** `docs/superpowers/specs/2026-09-21-threshold-position-design.md`

## Global Constraints

- `declare(strict_types=1);` in every PHP file. PSR-12 via `./vendor/bin/pint` before each commit.
- No hardcoded tax values: every threshold, rate and date comes from `TaxConfigService`. Never hardcode the tax year.
- No scores, no icons, no emoji, no Unicode arrows, no amber or orange. Warnings `violet-*`, errors `raspberry-*`, success `spring-*`. Palette tokens only; no hex in `<style>`.
- User-facing text is British English. No uncited acronyms except ISA. Copy is built server-side in one class, `ThresholdCopy`.
- Currency on web via `currencyMixin`; on `/m` via the existing `fmt` helpers in the view.
- Every user-facing change lands on web AND `/m` (Rule 19); iOS shows end figures only (CSJ 2026-09-21).
- One mechanism per behaviour (Rule 20): vest income and drawdown income enter `IncomeDefinitionsService` once and nowhere else.
- Tests: Pest `it()`/`describe()`, `RefreshDatabase`, `$this->seed(TaxConfigurationSeeder::class)` where real years matter, `Mockery::close()` in `afterEach`. Run only the files you touched, never the full suite (CSJ lean cadence).
- Commit after every task on branch `feature/threshold-position`. Commit messages end with the attribution lines the session reminder gives.
- Never `migrate:fresh`, never `--env=testing`. Run `php artisan migrate` then `php artisan db:seed` locally after Task 1.
- Fixed decisions (CSJ 2026-09-21): salary-sacrifice NI cap date is 6 April 2027 (config `pension.salary_sacrifice.nic_exemption_cap_effective_date`); the vest join is in scope; both tax-strategy bugs are fixed here; DC drawdown capture is in scope.

---

## File structure

| File | Responsibility |
|---|---|
| `database/migrations/2026_09_21_100000_add_drawdown_income_to_dc_pensions.php` | two nullable columns |
| `app/Models/DCPension.php` | fillable + casts for the two columns |
| `app/Http/Requests/Retirement/StoreDCPensionRequest.php` | validation for the two columns |
| `app/Http/Resources/DCPensionResource.php` | expose the two columns |
| `app/Services/Stores/Normalisers/PensionNormaliser.php` | Fyn tool params to canonical |
| `app/Traits/ResolvesIncome.php` | drawdown income joins pension income in payment |
| `resources/js/components/Retirement/DCPensionForm.vue` | two fields on the web form |
| `app/Services/Onboarding/CaptureForms.php`, `RecordEditForms.php` | two fields on the Fyn form (`/m` and web capture) |
| `fyn-memory/procedural/tool_schema/savings/create_pension.md`, `.xai.md` | two tool parameters, version 5 |
| `app/Services/Tax/VestScheduleResolver.php` | vest events this tax year, and their income |
| `app/Services/Tax/IncomeDefinitionsService.php` | ninth component `vesting` |
| `database/seeders/TaxActionDefinitionSeeder.php` | conflict pair |
| `app/Services/Tax/TaxStrategyMath.php` | `bandThresholdsFor(User)` with Gift Aid extension |
| `app/Services/Tax/Strategies/IncomeBandStrategy.php` | uses `bandThresholdsFor` |
| `app/Services/Retirement/SalarySacrificeAnalyzer.php`, `RetirementActionDefinitionService.php` | `post_cap_*` keys, year from config |
| `database/seeders/TaxConfigurationSeeder.php` | hourly funding rates under `early_years_funding` |
| `app/Services/Tax/Thresholds/ThresholdLine.php` | the contract |
| `app/Services/Tax/Thresholds/ThresholdContext.php` | per-user shared inputs |
| `app/Services/Tax/Thresholds/ThresholdResult.php` | one line's output |
| `app/Services/Tax/Thresholds/ThresholdCost.php` | the itemised cost |
| `app/Services/Tax/Thresholds/ThresholdCostCalculator.php` | the delta |
| `app/Services/Tax/Thresholds/ChildcareEntitlements.php` | childcare figures from config and family record |
| `app/Services/Tax/Thresholds/Lines/*.php` | one class per catalogue line |
| `app/Services/Tax/Thresholds/ThresholdCopy.php` | every user-facing string |
| `app/Services/Tax/Thresholds/ThresholdPositionService.php` | the evaluator |
| `app/Providers/AppServiceProvider.php` | tags the lines |
| `app/Http/Controllers/Api/ThresholdController.php`, `routes/api.php` | `GET /api/thresholds` |
| `resources/js/components/Actions/ThresholdStrip.vue`, `views/Actions/ActionsDashboard.vue` | web strip |
| `resources/mobile/components/ThresholdStrip.vue`, `views/Actions.vue` | `/m` strip |
| `ios-native/Fynla/Features/Dashboard/ThresholdClient.swift`, `ThresholdModels.swift`, `ThresholdStripView.swift`, `DashboardView.swift`, `DashboardModel.swift`, `App/FynlaApp.swift` | native card |

---

### Task 1: DC drawdown capture, backend

**Files:**
- Create: `database/migrations/2026_09_21_100000_add_drawdown_income_to_dc_pensions.php`
- Modify: `app/Models/DCPension.php:40-80` (fillable), `:120-152` (casts)
- Modify: `app/Http/Requests/Retirement/StoreDCPensionRequest.php:59-66`
- Modify: `app/Http/Resources/DCPensionResource.php:41`
- Modify: `app/Services/Stores/Normalisers/PensionNormaliser.php:215`
- Modify: `app/Traits/ResolvesIncome.php:45-60`
- Test: `tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php`

**Interfaces:**
- Produces: `dc_pensions.annual_drawdown_income` (decimal 14,2 nullable), `dc_pensions.pcls_taken` (decimal 14,2 nullable). `resolvePensionIncomeInPayment(User)` now includes drawdown income.

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php` inside a new `describe`:

```php
describe('DC drawdown income', function () {
    it('adds annual drawdown income to pension income in payment and never the lump sum', function () {
        $user = User::factory()->create(['annual_employment_income' => 0]);
        DCPension::create([
            'user_id' => $user->id,
            'scheme_name' => 'Aviva SIPP',
            'pension_type' => 'personal',
            'current_fund_value' => 200000,
            'has_flexibly_accessed' => true,
            'annual_drawdown_income' => 18000,
            'pcls_taken' => 50000,
        ]);

        $result = $this->service->calculate($user->id);

        expect($result['components']['pension_income'])->toBe(18000.00)
            ->and($result['total_income'])->toBe(18000.00);
    });

    it('treats a null drawdown income as not asked, contributing nothing', function () {
        $user = User::factory()->create(['annual_employment_income' => 0]);
        DCPension::create([
            'user_id' => $user->id,
            'scheme_name' => 'Aviva SIPP',
            'pension_type' => 'personal',
            'current_fund_value' => 200000,
            'has_flexibly_accessed' => true,
        ]);

        expect($this->service->calculate($user->id)['components']['pension_income'])->toBe(0.00);
    });
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php --filter="DC drawdown"`
Expected: FAIL, unknown column `annual_drawdown_income`.

- [ ] **Step 3: Migration**

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
        Schema::table('dc_pensions', function (Blueprint $table) {
            // Null means the user has not been asked. Never default to 0: a zero
            // is a stated fact ("I draw nothing") and a null is an open question.
            $table->decimal('annual_drawdown_income', 14, 2)->nullable()->after('flexible_access_date');
            $table->decimal('pcls_taken', 14, 2)->nullable()->after('annual_drawdown_income');
        });
    }

    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn(['annual_drawdown_income', 'pcls_taken']);
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Model, request, resource, normaliser**

`app/Models/DCPension.php`: add `'annual_drawdown_income', 'pcls_taken',` after `'flexible_access_date',` in `$fillable`; add `'annual_drawdown_income' => 'decimal:2', 'pcls_taken' => 'decimal:2',` after `'has_flexibly_accessed' => 'boolean',` in `$casts`.

`StoreDCPensionRequest.php` after the `flexible_access_date` rule:

```php
            'annual_drawdown_income' => ['nullable', 'numeric', 'min:0'],
            'pcls_taken' => ['nullable', 'numeric', 'min:0'],
```

`DCPensionResource.php` after `'has_flexibly_accessed' => $this->has_flexibly_accessed,`:

```php
            'annual_drawdown_income' => $this->annual_drawdown_income,
            'pcls_taken' => $this->pcls_taken,
```

`PensionNormaliser.php:215`: add `'annual_drawdown_income', 'pcls_taken'` to the numeric foreach list in `fromFynPension`.

- [ ] **Step 5: Income resolution**

`app/Traits/ResolvesIncome.php`, in `resolvePensionIncomeInPayment`, before `return (float) $income;`:

```php
        // Taxable drawdown from a DC pot the user has flexibly accessed. The tax-free
        // lump sum (`pcls_taken`) is recorded on the same row and is never income
        // (FA 2004 Sch 29 para 1), so it is not read here or anywhere else that taxes.
        $income += $user->dcPensions
            ->filter(fn ($pension): bool => (bool) $pension->has_flexibly_accessed)
            ->sum(fn ($pension): float => (float) ($pension->annual_drawdown_income ?? 0));
```

`IncomeDefinitionsService::calculate` already eager-loads `dcPensions`. Check `resolvePensionIncomeInPayment`'s other callers (`UserProfileService`, `PersonalAccountsService`) load `dcPensions` too; if one does not, add `$user->loadMissing('dcPensions')` at the top of the method.

- [ ] **Step 6: Run the test**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php`
Expected: PASS, including the pre-existing cases.

- [ ] **Step 7: Commit**

```bash
./vendor/bin/pint database/migrations/2026_09_21_100000_add_drawdown_income_to_dc_pensions.php app/Models/DCPension.php app/Http/Requests/Retirement/StoreDCPensionRequest.php app/Http/Resources/DCPensionResource.php app/Services/Stores/Normalisers/PensionNormaliser.php app/Traits/ResolvesIncome.php
git add -A database/migrations app/Models/DCPension.php app/Http app/Services/Stores app/Traits tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php
git commit -m "feat(retirement): capture DC drawdown income and the lump sum taken; drawdown joins pension income in payment"
```

---

### Task 2: DC drawdown capture, every form

**Files:**
- Modify: `resources/js/components/Retirement/DCPensionForm.vue:195-215` (fields), `:835-850` (formData), `:1300-1330` (payload)
- Modify: `app/Services/Onboarding/CaptureForms.php:800-830` (pension kinds and fields), `:505-530` (`pensionInputs`)
- Modify: `app/Services/Onboarding/RecordEditForms.php:335-355` (`pensionAnswers`)
- Modify: `fyn-memory/procedural/tool_schema/savings/create_pension.md` and `create_pension.xai.md`
- Test: `tests/Unit/Services/Onboarding/CaptureFormsTest.php` (find the existing pension case with `grep -n "pension" tests/Unit/Services/Onboarding/CaptureFormsTest.php`), `tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusTest.php`

**Interfaces:**
- Consumes: the two columns from Task 1.
- Produces: form answers `annual_drawdown_income` and `pcls_taken` on the `personal` pension kind, mapped 1:1 to `create_pension` tool params of the same name.

- [ ] **Step 1: Failing test for the Fyn form**

In `tests/Unit/Services/Onboarding/CaptureFormsTest.php` add:

```php
it('carries drawdown income and the lump sum taken on a personal pension', function () {
    $input = CaptureForms::toolInput(CaptureForms::PENSION, 'personal', [
        'provider' => 'Aviva',
        'current_value' => 200000,
        'annual_drawdown_income' => 18000,
        'pcls_taken' => 50000,
    ]);

    expect($input['annual_drawdown_income'])->toBe(18000.0)
        ->and($input['pcls_taken'])->toBe(50000.0);
});
```

`toolInput` stands for the public entry point that turns form answers into tool params; find its real name with `grep -n "public static function" app/Services/Onboarding/CaptureForms.php` and the existing pension case in the test file, and use that name. The assertion's shape stays.

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php --filter="drawdown"`
Expected: FAIL, undefined index.

- [ ] **Step 3: Fyn form**

`CaptureForms::pension()`: change the `personal` kind's `fields` to `['provider', 'current_value', 'annual_contribution', 'annual_drawdown_income', 'pcls_taken']` and add to `'fields'`:

```php
                'annual_drawdown_income' => ['type' => 'money', 'label' => 'You draw from it each year', 'required' => false,
                    'hint' => "Leave blank if you haven't started drawing"],
                'pcls_taken' => ['type' => 'money', 'label' => 'Tax-free lump sum already taken', 'required' => false,
                    'hint' => 'Leave blank if none'],
```

`CaptureForms::pensionInputs()`, after the `monthly_contribution_amount` branch:

```php
        foreach (['annual_drawdown_income', 'pcls_taken'] as $field) {
            if (is_numeric($answers[$field] ?? null)) {
                $input[$field] = (float) $answers[$field];
            }
        }
        if (isset($input['annual_drawdown_income']) && $input['annual_drawdown_income'] > 0) {
            $input['has_flexibly_accessed'] = true;
        }
```

`RecordEditForms::pensionAnswers()`, in the non-workplace branch after `annual_contribution`:

```php
            $answers += array_filter([
                'annual_drawdown_income' => self::floatOrNull($pension->annual_drawdown_income),
                'pcls_taken' => self::floatOrNull($pension->pcls_taken),
            ], static fn ($v): bool => $v !== null);
```

- [ ] **Step 4: Tool schema, both provider files**

In both `create_pension.md` and `create_pension.xai.md`: bump `version: 4` to `version: 5`, set `effective_from: 2026-09-21`, and add two properties after `monthly_contribution_amount` (the `.xai.md` file uses `"type": ["number", "null"]`, the `.md` file uses `"type": "number"`):

```json
            "annual_drawdown_income": {
                "type": ["number", "null"],
                "description": "Taxable income drawn from this pension each year in pounds, personal and Self-Invested Personal Pension arrangements the user has started drawing from. Send null if not stated — never 0."
            },
            "pcls_taken": {
                "type": ["number", "null"],
                "description": "Tax-free lump sum already taken from this pension in pounds. Send null if not stated — never 0."
            },
```

In `.xai.md` also append `"annual_drawdown_income", "pcls_taken"` to the `required` list. Run `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural` and fix any golden-master fixture it names (the fixture lives under `tests/fixtures/ToolSchema/`, see its README).

- [ ] **Step 5: Web form**

`DCPensionForm.vue`: in `formData` add `annual_drawdown_income: null, pcls_taken: null,` after `lump_sum_contribution: null,`. In the template, directly after the Lump Sum Contribution block (line ~215), inside the same `v-if="isPersonalPension && showAdditionalInfo"` treatment but visible whenever `isDCType`:

```vue
          <!-- Drawdown: taxable income drawn and the tax-free lump sum taken -->
          <div v-if="isDCType" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="annual_drawdown_income" class="block text-sm font-medium text-neutral-500 mb-2">
                Income drawn each year (£) <span class="text-neutral-500 text-xs">(Optional)</span>
              </label>
              <input
                id="annual_drawdown_income"
                v-model.number="formData.annual_drawdown_income"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 18000.00"
              />
              <p class="text-xs text-neutral-500 mt-1">Leave blank if you have not started drawing from it.</p>
            </div>
            <div>
              <label for="pcls_taken" class="block text-sm font-medium text-neutral-500 mb-2">
                Tax-free lump sum taken (£) <span class="text-neutral-500 text-xs">(Optional)</span>
              </label>
              <input
                id="pcls_taken"
                v-model.number="formData.pcls_taken"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 50000.00"
              />
            </div>
          </div>
```

The payload builder spreads `dcFields`, so nothing else is needed. Where the form loads an existing pension into `formData` (search `current_fund_value:` in the edit-populate method around line 1100), add the two fields the same way.

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php tests/Unit/Services/AI/Memory/Procedural`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
./vendor/bin/pint app/Services/Onboarding
git add resources/js/components/Retirement/DCPensionForm.vue app/Services/Onboarding fyn-memory/procedural/tool_schema/savings tests
git commit -m "feat(retirement): drawdown income and lump sum taken on the web form, the Fyn form and the pension tool"
```

---

### Task 3: VestScheduleResolver and the `vesting` income component

**Files:**
- Create: `app/Services/Tax/VestScheduleResolver.php`
- Modify: `app/Services/Tax/IncomeDefinitionsService.php:16-19` (constructor), `:180-192` (`getIncomeComponents`)
- Test: `tests/Unit/Services/Tax/VestScheduleResolverTest.php`

**Interfaces:**
- Produces: `VestScheduleResolver::schedule(User $user): array<int, array{date: Carbon, value: float, account_id: int, account_name: string}>` for the current tax year; `VestScheduleResolver::annualVestIncome(User $user): float`; `IncomeDefinitionsService::calculate()['components']['vesting']`.

- [ ] **Step 1: Failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\VestScheduleResolver;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    Carbon::setTestNow('2026-09-21');
    $this->resolver = app(VestScheduleResolver::class);
});

afterEach(function () {
    Carbon::setTestNow();
    Mockery::close();
});

function rsuAccount(User $user, array $overrides = []): InvestmentAccount
{
    return InvestmentAccount::create(array_merge([
        'user_id' => $user->id,
        'account_type' => 'rsu',
        'account_name' => 'Acme RSUs',
        'provider' => 'Acme',
        'current_value' => 0,
        'scheme_status' => 'active',
        'vesting_type' => 'graded',
        'cliff_date' => null,
        'vesting_frequency_months' => 3,
        'full_vest_date' => '2028-03-15',
        'units_unvested' => 800,
        'current_share_price' => 30,
    ], $overrides));
}

it('projects the quarterly vest events that fall in this tax year', function () {
    $user = User::factory()->create();
    rsuAccount($user);

    $events = $this->resolver->schedule($user);

    // Quarterly back from 2028-03-15 to the tax year start gives eight dates; six are
    // on or after today (2026-12-15 to 2028-03-15), so 800 units split six ways.
    // Two of those six fall in 2026/27.
    $dates = array_map(fn ($e) => $e['date']->toDateString(), $events);
    expect($dates)->toBe(['2026-12-15', '2027-03-15'])
        ->and($events[0]['value'])->toBe(round(800 / 6 * 30, 2));
});

it('counts vests already past in this tax year as income but not as upcoming events', function () {
    $user = User::factory()->create();
    rsuAccount($user);

    expect($this->resolver->annualVestIncome($user))
        ->toBe(round(800 / 6 * 30 * 4, 2)); // 2026-06-15, 09-15, 12-15, 2027-03-15
});

it('excludes tax-advantaged schemes from income', function () {
    $user = User::factory()->create();
    rsuAccount($user, ['account_type' => 'emi', 'account_name' => 'Acme EMI']);

    expect($this->resolver->annualVestIncome($user))->toBe(0.0);
});

it('reaches adjusted net income through the income definitions', function () {
    $user = User::factory()->create(['annual_employment_income' => 90000]);
    rsuAccount($user);

    $definitions = app(IncomeDefinitionsService::class)->calculate($user->id);

    expect($definitions['components']['vesting'])->toBe(round(800 / 6 * 30 * 4, 2))
        ->and($definitions['adjusted_net_income'])->toBe(round(90000 + 800 / 6 * 30 * 4, 2));
});
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/VestScheduleResolverTest.php`
Expected: FAIL, class not found.

- [ ] **Step 3: Resolver**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * Scheduled share-scheme vests in the current tax year, valued at the recorded
 * share price. `InvestmentAccount` has held the schedule since 2026-01-29; the
 * tax layer never read it, so a user whose RSUs vest twice a year could not be
 * told they will cross £100,000 (Threshold-First spec, 2026-09-17).
 *
 * Only schemes taxed as employment income at vest or exercise count as income:
 * RSUs (ITEPA 2003 s62) and unapproved options (s476). EMI, CSOP and SAYE gains
 * are capital and stay out.
 */
final class VestScheduleResolver
{
    private const INCOME_AT_VEST = ['rsu', 'unapproved_options'];

    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /**
     * Events on or after today in the current tax year, soonest first.
     *
     * @return list<array{date: Carbon, value: float, account_id: int, account_name: string}>
     */
    public function schedule(User $user): array
    {
        $today = Carbon::today();

        return array_values(array_filter(
            $this->eventsThisTaxYear($user),
            fn (array $event): bool => $event['date']->gte($today),
        ));
    }

    /** Every vest in the current tax year, past and future, valued. */
    public function annualVestIncome(User $user): float
    {
        return round(array_sum(array_column($this->eventsThisTaxYear($user), 'value')), 2);
    }

    /** @return list<array{date: Carbon, value: float, account_id: int, account_name: string}> */
    private function eventsThisTaxYear(User $user): array
    {
        [$yearStart, $yearEnd] = $this->taxYearBounds();
        $events = [];

        $user->loadMissing('investmentAccounts');
        foreach ($user->investmentAccounts as $account) {
            if (! in_array($account->account_type, self::INCOME_AT_VEST, true)
                || ($account->scheme_status ?? 'active') !== 'active'
                || (int) ($account->units_unvested ?? 0) <= 0
                || ! $account->full_vest_date
                || (float) ($account->current_share_price ?? 0) <= 0) {
                continue;
            }

            foreach ($this->tranches($account) as $date => $units) {
                $date = Carbon::parse($date);
                if ($date->lt($yearStart) || $date->gt($yearEnd)) {
                    continue;
                }
                $events[] = [
                    'date' => $date,
                    'value' => round($units * (float) $account->current_share_price, 2),
                    'account_id' => (int) $account->id,
                    'account_name' => (string) ($account->account_name ?? $account->provider),
                ];
            }
        }

        usort($events, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $events;
    }

    /**
     * Tranche dates from the tax year start up to `full_vest_date`, every
     * `vesting_frequency_months`. Unvested units split evenly across the dates on
     * or after today; dates earlier in this tax year carry the same units, so the
     * year's income counts what has already vested. A cliff still ahead vests
     * `cliff_percentage` of the granted units on `cliff_date` first.
     *
     * ponytail: even split; per-tranche unit counts if a scheme with uneven
     * tranches is ever captured.
     *
     * @return array<string, float> date => units
     */
    private function tranches(InvestmentAccount $account): array
    {
        $frequency = max(1, (int) ($account->vesting_frequency_months ?? 12));
        $today = Carbon::today();
        [$yearStart] = $this->taxYearBounds();
        $unvested = (float) $account->units_unvested;
        $tranches = [];

        if ($account->cliff_date && $account->cliff_percentage && Carbon::parse($account->cliff_date)->gte($today)) {
            $cliffUnits = min($unvested, round((float) ($account->units_granted ?? $unvested) * (int) $account->cliff_percentage / 100, 4));
            $tranches[Carbon::parse($account->cliff_date)->toDateString()] = $cliffUnits;
            $unvested -= $cliffUnits;
        }

        $dates = [];
        for ($date = Carbon::parse($account->full_vest_date)->startOfDay(); $date->gte($yearStart); $date = $date->copy()->subMonths($frequency)) {
            $dates[] = $date->toDateString();
        }
        sort($dates);

        $remaining = array_filter($dates, fn (string $d): bool => Carbon::parse($d)->gte($today));
        $perTranche = $remaining === [] ? 0.0 : $unvested / count($remaining);
        foreach ($dates as $date) {
            $tranches[$date] = ($tranches[$date] ?? 0.0) + $perTranche;
        }

        return $tranches;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function taxYearBounds(): array
    {
        // TaxConfigService::getTaxYear() returns "2026/27"; the year starts 6 April.
        $startYear = (int) substr($this->taxConfig->getTaxYear(), 0, 4);

        return [Carbon::create($startYear, 4, 6)->startOfDay(), Carbon::create($startYear + 1, 4, 5)->endOfDay()];
    }
}
```

- [ ] **Step 4: Wire the component**

`IncomeDefinitionsService` constructor:

```php
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly PropertyService $propertyService,
        private readonly VestScheduleResolver $vests,
    ) {}
```

`getIncomeComponents`, after `'pension_income'`:

```php
            // Share-scheme vests this tax year (RSUs and unapproved options). Employment
            // income under ITEPA 2003, so it reaches every definition below. Assumed NOT
            // already inside `annual_employment_income`, which the form captures as
            // salary. ponytail: no per-account include flag; add one on a double-count report.
            'vesting' => $this->vests->annualVestIncome($user),
```

The existing `IncomeDefinitionsServiceTest` constructs the service by hand (`new IncomeDefinitionsService($this->taxConfig, app(PropertyService::class))`); update that line to pass `app(VestScheduleResolver::class)` as the third argument.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/VestScheduleResolverTest.php tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php tests/Unit/Services/Tax/TaxStrategyMathTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/Services/Tax
git add app/Services/Tax/VestScheduleResolver.php app/Services/Tax/IncomeDefinitionsService.php tests/Unit/Services/Tax
git commit -m "feat(tax): scheduled share vests enter the income definitions as a ninth component"
```

---

### Task 4: Fix 1, the income-band conflict pair

**Files:**
- Modify: `database/seeders/TaxActionDefinitionSeeder.php:68-83`
- Test: `tests/Unit/Services/Coordination/StrategyPlanComposerTest.php`

**Interfaces:**
- Produces: `tax_action_definitions.sequencing.conflicts_with` carries `['additional_rate_avoidance']` on `pa_taper_rescue` and the reverse.

- [ ] **Step 1: Failing test**

Append to `StrategyPlanComposerTest.php`:

```php
it('never sums pa_taper_rescue with additional_rate_avoidance once the seeder declares the pair', function () {
    $this->seed(\Database\Seeders\TaxActionDefinitionSeeder::class);
    $metadata = \App\Models\TaxActionDefinition::whereNotNull('strategy_type')
        ->get()
        ->keyBy('strategy_type')
        ->map(fn ($row) => ['claim_tier' => $row->claim_tier, 'sequencing' => $row->sequencing])
        ->all();

    // The spec's profile: £135,000 employment, £12,000 Gift Aid → both fire.
    $recs = [
        new StrategyRecommendation('pa_taper_rescue', StrategyCategory::IncomeBand, StrategyPriority::High, 'PA', 'd', 12000.0),
        new StrategyRecommendation('additional_rate_avoidance', StrategyCategory::IncomeBand, StrategyPriority::High, 'AR', 'd', 29521.0),
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: []);

    expect($plan['combined_annual_saving'])->toBe(29521.0);
});
```

Add `uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);` at the top of the file if absent.

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Coordination/StrategyPlanComposerTest.php --filter="never sums"`
Expected: FAIL, 41521.0.

- [ ] **Step 3: Seeder**

In `strategyMetadata()`, `pa_taper_rescue`: `'sequencing' => ['do_before' => [], 'conflicts_with' => ['additional_rate_avoidance']],` and `additional_rate_avoidance`: `'sequencing' => ['do_before' => [], 'conflicts_with' => ['pa_taper_rescue']],`. Add one comment above the pair:

```php
            // Both draw on the same Annual Allowance for the same user; the plan
            // summed them and overstated the saving by 41% (2026-09-17). The composer's
            // conflict resolution keeps the higher-saving member.
```

- [ ] **Step 4: Reseed locally and run**

Run: `php artisan db:seed --class=TaxActionDefinitionSeeder --force && ./vendor/bin/pest tests/Unit/Services/Coordination/StrategyPlanComposerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/TaxActionDefinitionSeeder.php tests/Unit/Services/Coordination/StrategyPlanComposerTest.php
git commit -m "fix(tax): pa_taper_rescue and additional_rate_avoidance conflict, so the plan no longer sums them"
```

Note for the release: run `php artisan db:seed --class=TaxActionDefinitionSeeder --force` on csjones and production.

---

### Task 5: Fix 2, Gift Aid extends the strategy band thresholds

**Files:**
- Modify: `app/Services/Tax/TaxStrategyMath.php:68-95`
- Modify: `app/Services/Tax/Strategies/IncomeBandStrategy.php:33`
- Test: `tests/Unit/Services/Tax/TaxStrategyMathTest.php`

**Interfaces:**
- Produces: `TaxStrategyMath::bandThresholdsFor(User $user): array{higher: float, additional: float}` and `bandFromIncomeFor(User $user, float $income): string`. `bandThresholds()` (raw) stays for `QuerySchemas`.

- [ ] **Step 1: Failing test**

```php
describe('bandThresholdsFor', function () {
    it('extends both limits by the grossed-up Gift Aid, matching UKTaxCalculator', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 135000,
            'is_gift_aid' => true,
            'annual_charitable_donations' => 12000, // £15,000 gross
        ]);

        $thresholds = $this->math->bandThresholdsFor($user);
        $raw = $this->math->bandThresholds();

        expect($thresholds['higher'])->toBe($raw['higher'] + 15000.0)
            ->and($thresholds['additional'])->toBe($raw['additional'] + 15000.0);
    });

    it('leaves the limits alone for a non-donor', function () {
        $user = User::factory()->create(['annual_employment_income' => 135000]);

        expect($this->math->bandThresholdsFor($user))->toBe($this->math->bandThresholds());
    });
});
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyMathTest.php --filter="bandThresholdsFor"`
Expected: FAIL, undefined method.

- [ ] **Step 3: Implement**

After `bandThresholds()` in `TaxStrategyMath`:

```php
    /**
     * The band limits as they apply to THIS user: extended by the grossed-up Gift
     * Aid under ITA 2007 s414, the way `UKTaxCalculator` already extends them.
     * Without this the strategy engine valued a slice at 45% that the calculator
     * taxed at 40% (2026-09-17).
     *
     * @return array{higher: float, additional: float}
     */
    public function bandThresholdsFor(User $user): array
    {
        $extension = (float) ($this->incomeDefinitionsFor($user)['deductions']['gift_aid_gross'] ?? 0);
        $raw = $this->bandThresholds();

        return [
            'higher' => $raw['higher'] > 0 ? $raw['higher'] + $extension : 0.0,
            'additional' => $raw['additional'] > 0 ? $raw['additional'] + $extension : 0.0,
        ];
    }

    public function bandFromIncomeFor(User $user, float $income): string
    {
        $thresholds = $this->bandThresholdsFor($user);

        return match (true) {
            $income >= $thresholds['additional'] && $thresholds['additional'] > 0 => 'additional',
            $income >= $thresholds['higher'] && $thresholds['higher'] > 0 => 'higher',
            default => 'basic',
        };
    }
```

Change `bandRateFor(User $user)` to `return $this->bandRateForBand($this->bandFromIncomeFor($user, $this->taxableIncomeFor($user)));`.

`IncomeBandStrategy.php:33`: `$additionalRateThreshold = $this->math->bandThresholdsFor($user)['additional'] ?: 125140;` (move the line below `$user = $context->user;`).

- [ ] **Step 4: Run**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyMathTest.php tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Tax
git add app/Services/Tax tests/Unit/Services/Tax/TaxStrategyMathTest.php
git commit -m "fix(tax): the strategy engine extends the band limits by Gift Aid like the calculator does"
```

---

### Task 6: The salary-sacrifice cap reads its year from config

**Files:**
- Modify: `app/Services/Retirement/SalarySacrificeAnalyzer.php:155-195`, `:225-265`, `:290-335`
- Modify: `app/Services/Retirement/RetirementActionDefinitionService.php:1368-1382`
- Test: `tests/Unit/Services/Retirement/SalarySacrificeAnalyzerTest.php` (create if absent)

**Interfaces:**
- Produces: analyser output keys `post_cap_employee_ni_saving`, `post_cap_total_ni_saving`, `exceeds_nic_cap`, `nic_cap_effective_year` (int, e.g. 2027). No `2029` anywhere in the two files.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Retirement\SalarySacrificeAnalyzer;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('names the cap year from config, not a literal', function () {
    $analyser = app(SalarySacrificeAnalyzer::class);
    $method = new ReflectionMethod($analyser, 'calculateNISavings');
    $method->setAccessible(true);

    $ni = $method->invoke($analyser, 5000.0);

    expect($ni['exceeds_nic_cap'])->toBeTrue()
        ->and($ni['nic_cap_effective_year'])->toBe(2027)
        ->and($ni)->not->toHaveKey('post_2029_employee');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Retirement/SalarySacrificeAnalyzerTest.php`
Expected: FAIL, key missing.

- [ ] **Step 3: Rename and read the date**

In `calculateNISavings`, after `$nicExemptionCap`:

```php
        $effectiveYear = (int) substr(
            (string) $this->taxConfig->get('pension.salary_sacrifice.nic_exemption_cap_effective_date', '2027-04-06'),
            0,
            4,
        );
```

Return keys: `post_cap_employee`, `post_cap_employer`, `post_cap_total`, `nic_exemption_cap`, `exceeds_nic_cap`, `nic_cap_effective_year => $effectiveYear`. Update the docblock's `@return` and its prose ("From April 2029" becomes "From the cap's effective date, in config"). At the two warning sites replace `From April 2029` and `Post-2029` in the `sprintf` templates with `From April %d` and `Post-%d`, passing `$niSavings['nic_cap_effective_year']` as the argument, and rename every `post_2029_*`/`exceeds_2029_cap` read to the new keys. Public output keys become `post_cap_employee_ni_saving`, `post_cap_total_ni_saving`, `exceeds_nic_cap`, `nic_cap_effective_year`.

`RetirementActionDefinitionService.php:1368-1382`: read `$analysis['post_cap_employee_ni_saving']`, `$year = (int) ($analysis['nic_cap_effective_year'] ?? 2027)`, and build the question, data field, threshold and explanation strings with `'April '.$year` in place of every `April 2029`, `'Post-'.$year` in place of `Post-2029`.

Run `grep -rn "2029" app/Services/Retirement` and expect no output.

- [ ] **Step 4: Run**

Run: `./vendor/bin/pest tests/Unit/Services/Retirement/SalarySacrificeAnalyzerTest.php tests/Unit/Services/Retirement`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Retirement
git add app/Services/Retirement tests/Unit/Services/Retirement
git commit -m "fix(retirement): the salary-sacrifice NI cap names its year from config (2027), not 2029"
```

---

### Task 7: Childcare hourly funding rates in the seeder

**Files:**
- Modify: `database/seeders/TaxConfigurationSeeder.php:800-845`
- Test: `tests/Unit/Services/TaxConfigServiceTest.php`

**Interfaces:**
- Produces: `benefits.early_years_funding.<band>.hourly_rate` for `working_parents_30hrs`, `working_parents_2yr`, `working_parents_under_2`.

- [ ] **Step 1: Failing test**

```php
it('seeds an hourly funding rate for every income-tested early years band', function () {
    $this->seed(TaxConfigurationSeeder::class);
    $funding = app(TaxConfigService::class)->getEarlyYearsFunding();

    foreach (['working_parents_30hrs', 'working_parents_2yr', 'working_parents_under_2'] as $band) {
        expect($funding[$band]['hourly_rate'] ?? null)->toBeGreaterThan(0.0);
    }
});
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/TaxConfigServiceTest.php --filter="hourly funding"`
Expected: FAIL.

- [ ] **Step 3: Seed**

In each of the three income-tested bands add:

```php
                        'hourly_rate' => 6.12,   // DfE 2025/26 national average hourly funding rate (early years operational guide), 3 and 4 year olds
```
```php
                        'hourly_rate' => 8.53,   // DfE 2025/26 national average, 2 year olds
```
```php
                        'hourly_rate' => 11.54,  // DfE 2025/26 national average, under 2s
```

Add the same three keys to the 2026/27 override block near line 1459 with the 2026/27 figures: 6.42, 8.90 and 12.04 (DfE early years operational guide 2026 to 2027; verified 2026-09-21).

- [ ] **Step 4: Reseed and run**

Run: `php artisan db:seed --class=TaxConfigurationSeeder --force && ./vendor/bin/pest tests/Unit/Services/TaxConfigServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/TaxConfigurationSeeder.php tests/Unit/Services/TaxConfigServiceTest.php
git commit -m "feat(tax-config): hourly funding rates for the income-tested early years bands"
```

---

### Task 8: Threshold contracts and the cost calculator

**Files:**
- Create: `app/Services/Tax/Thresholds/ThresholdLine.php`, `ThresholdContext.php`, `ThresholdResult.php`, `ThresholdCost.php`, `ThresholdCostCalculator.php`
- Test: `tests/Unit/Services/Tax/Thresholds/ThresholdCostCalculatorTest.php`

**Interfaces:**
- Produces:
  - `interface ThresholdLine { public function key(): string; public function evaluate(ThresholdContext $context): ?ThresholdResult; }`
  - `final class ThresholdContext { public readonly User $user; public readonly array $definitions; public function iht(): array; public function strategies(): array<string, array<string, mixed>> keyed by type (the calculator publishes arrays; `extra` is merged flat, so `suggested_contribution` is a top-level key); public function strategy(string $type): ?array; public function vests(): array; }`
  - `final class ThresholdResult` with readonly `key, title, range (?array{from: float, to: ?float}), position (array{value: float, distance: float, unit: 'gbp'|'days', over: bool}), headline, body, explanation, cost (?ThresholdCost), lever (?array{title: string, amount: float, recovers: float, downside: string, action: array{route: string}}), incomeMix (array<string,float>)` and `toArray(): array`.
  - `final class ThresholdCost { incomeTax, niClass1, niClass4, dividendTax, interestTax, benefits (list<array{label, detail, amount}>), total }` with `withBenefit(string $label, string $detail, float $amount): self` and `toArray()`.
  - `ThresholdCostCalculator::delta(ThresholdContext $context, float $reduceBy, string $mechanism = 'pension'): ThresholdCost`.

- [ ] **Step 1: Failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->calc = app(ThresholdCostCalculator::class);
});

function contextFor(User $user): ThresholdContext
{
    return new ThresholdContext($user, app(IncomeDefinitionsService::class)->calculate($user->id));
}

it('prices a salary excess with Class 1 NI and the taper, as a delta of two full computations', function () {
    $user = User::factory()->create(['annual_employment_income' => 112400]);

    $cost = $this->calc->delta(contextFor($user), 12400.0);

    // £12,400 back under £100,000 by pension: 40% relief plus half the PA restored at 40%.
    expect($cost->incomeTax)->toBe(12400 * 0.40 + 6200 * 0.40)
        ->and($cost->niClass1)->toBe(0.0) // a pension contribution does not change NI
        ->and($cost->niClass4)->toBe(0.0)
        ->and($cost->total())->toBe($cost->incomeTax);
});

it('prices a self-employed excess without Class 1', function () {
    $user = User::factory()->create(['annual_employment_income' => 0, 'annual_self_employment_income' => 108000]);

    $cost = $this->calc->delta(contextFor($user), 8000.0);

    expect($cost->niClass1)->toBe(0.0)
        ->and($cost->incomeTax)->toBeGreaterThan(8000 * 0.40);
});

it('attributes the dividend part of the delta to dividend tax', function () {
    $user = User::factory()->create(['annual_employment_income' => 90000, 'annual_dividend_income' => 20000]);

    $cost = $this->calc->delta(contextFor($user), 10000.0);

    expect($cost->dividendTax)->toBeGreaterThan(0.0)
        ->and($cost->total())->toBe(round($cost->incomeTax + $cost->dividendTax + $cost->interestTax + $cost->niClass1 + $cost->niClass4, 2));
});

it('removes interest from the mix when the mechanism is an ISA move', function () {
    $user = User::factory()->create(['annual_employment_income' => 98000, 'annual_interest_income' => 6000]);

    $cost = $this->calc->delta(contextFor($user), 4000.0, 'isa');

    expect($cost->interestTax)->toBeGreaterThan(0.0)->and($cost->total())->toBeGreaterThan(0.0);
});

it('prices a pensioner with no NI at all', function () {
    $user = User::factory()->create(['annual_employment_income' => 0]);
    \App\Models\DBPension::create(['user_id' => $user->id, 'scheme_name' => 'NHS', 'accrued_annual_pension' => 104000, 'scheme_status' => 'in_payment']);

    $cost = $this->calc->delta(contextFor($user), 4000.0);

    expect($cost->niClass1)->toBe(0.0)->and($cost->niClass4)->toBe(0.0)->and($cost->incomeTax)->toBeGreaterThan(0.0);
});
```

If `DBPension::create` needs more required columns, copy the minimal set from an existing test that creates one (`grep -rn "DBPension::create" tests | head -1`).

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Thresholds/ThresholdCostCalculatorTest.php`
Expected: FAIL, class not found.

- [ ] **Step 3: Contracts**

`ThresholdLine.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

/**
 * One line in the tax or benefit system that a user can stand near or over.
 * `evaluate` returns null when the line does not apply to this user: no data,
 * or not within the window. Nothing about the set of lines is fixed per user.
 */
interface ThresholdLine
{
    public function key(): string;

    public function evaluate(ThresholdContext $context): ?ThresholdResult;
}
```

`ThresholdContext.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\VestScheduleResolver;

/**
 * Everything the lines share for one user, computed once. The income definitions
 * are passed in; the heavier estate and strategy runs are lazy so a user with no
 * estate line never pays for an IHT computation.
 */
final class ThresholdContext
{
    private ?array $iht = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $strategies = null;

    private ?array $vests = null;

    public function __construct(
        public readonly User $user,
        public readonly array $definitions,
    ) {}

    public function adjustedNetIncome(): float
    {
        return (float) ($this->definitions['adjusted_net_income'] ?? 0);
    }

    public function totalIncome(): float
    {
        return (float) ($this->definitions['total_income'] ?? 0);
    }

    public function thresholdIncome(): float
    {
        return (float) ($this->definitions['threshold_income'] ?? 0);
    }

    /** @return array<string, float> */
    public function components(): array
    {
        return array_map('floatval', $this->definitions['components'] ?? []);
    }

    public function iht(): array
    {
        return $this->iht ??= app(IHTCalculationService::class)->calculate(
            $this->user,
            $this->user->liveSpouse(),
            $this->user->sharesFinancialDataWithSpouse(),
        );
    }

    /**
     * Strategy recommendations keyed by type, from the one calculator every surface
     * uses. The calculator publishes arrays (StrategyRecommendation::toArray), with
     * `extra` merged flat, so `suggested_contribution` is a top-level key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function strategies(): array
    {
        if ($this->strategies === null) {
            $this->strategies = [];
            foreach (app(TaxStrategyCalculator::class)->calculate($this->user)->recommendations as $rec) {
                $this->strategies[(string) ($rec['type'] ?? '')] = $rec;
            }
        }

        return $this->strategies;
    }

    public function strategy(string $type): ?array
    {
        return $this->strategies()[$type] ?? null;
    }

    /** @return list<array{date: \Carbon\Carbon, value: float, account_id: int, account_name: string}> */
    public function vests(): array
    {
        return $this->vests ??= app(VestScheduleResolver::class)->schedule($this->user);
    }
}
```

`ThresholdCost.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

final class ThresholdCost
{
    /** @param list<array{label: string, detail: string, amount: float}> $benefits */
    public function __construct(
        public readonly float $incomeTax = 0.0,
        public readonly float $niClass1 = 0.0,
        public readonly float $niClass4 = 0.0,
        public readonly float $dividendTax = 0.0,
        public readonly float $interestTax = 0.0,
        public readonly array $benefits = [],
    ) {}

    public function withBenefit(string $label, string $detail, float $amount): self
    {
        if ($amount <= 0) {
            return $this;
        }

        return new self($this->incomeTax, $this->niClass1, $this->niClass4, $this->dividendTax, $this->interestTax,
            [...$this->benefits, ['label' => $label, 'detail' => $detail, 'amount' => round($amount, 2)]]);
    }

    public function total(): float
    {
        return round($this->incomeTax + $this->niClass1 + $this->niClass4 + $this->dividendTax + $this->interestTax
            + array_sum(array_column($this->benefits, 'amount')), 2);
    }

    public function toArray(): array
    {
        return [
            'income_tax' => round($this->incomeTax, 2),
            'ni_class_1' => round($this->niClass1, 2),
            'ni_class_4' => round($this->niClass4, 2),
            'dividend_tax' => round($this->dividendTax, 2),
            'interest_tax' => round($this->interestTax, 2),
            'benefits' => $this->benefits,
            'total' => $this->total(),
        ];
    }
}
```

`ThresholdResult.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

final class ThresholdResult
{
    /**
     * @param  array{from: float, to: ?float}|null  $range
     * @param  array{value: float, distance: float, unit: string, over: bool}  $position
     * @param  array{title: string, amount: float, recovers: float, downside: string, action: array{route: string}}|null  $lever
     * @param  array<string, float>  $incomeMix
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly ?array $range,
        public readonly array $position,
        public readonly string $headline,
        public readonly string $body,
        public readonly string $explanation,
        public readonly ?ThresholdCost $cost,
        public readonly ?array $lever,
        public readonly array $incomeMix = [],
    ) {}

    public function isDateLine(): bool
    {
        return $this->position['unit'] === 'days';
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'range' => $this->range,
            'position' => $this->position,
            'headline' => $this->headline,
            'body' => $this->body,
            'explanation' => $this->explanation,
            'income_mix' => $this->incomeMix,
            'cost' => $this->cost?->toArray(),
            'cost_total' => $this->cost?->total() ?? 0.0,
            'lever' => $this->lever,
        ];
    }
}
```

- [ ] **Step 4: Cost calculator**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

use App\Services\UKTaxCalculator;

/**
 * What being `$reduceBy` over a line costs, as the difference between two full
 * computations over the user's actual income mix: one as they stand, one with
 * the excess taken out by the lever's mechanism. `UKTaxCalculator` already
 * applies Class 1 on employment, Class 4 on self-employment, no NI on pension
 * income, the savings allowance on interest, dividend rates on dividends, the
 * tapered allowance and the Gift Aid extension, so nothing is re-derived here.
 *
 * Dividend and interest tax are top-sliced the way HMRC stacks them: tax with
 * dividends less tax without is the dividend tax; the same for interest on the
 * non-dividend figure.
 */
final class ThresholdCostCalculator
{
    public function __construct(private readonly UKTaxCalculator $calculator) {}

    public function delta(ThresholdContext $context, float $reduceBy, string $mechanism = 'pension'): ThresholdCost
    {
        $mix = $this->mix($context);
        $before = $this->run($mix, $context);

        $after = $mix;
        $extraPension = 0.0;
        if ($mechanism === 'isa') {
            // Move interest first, then dividends, out of the computation entirely.
            $fromInterest = min($after['interest'], $reduceBy);
            $after['interest'] -= $fromInterest;
            $after['dividend'] -= min($after['dividend'], $reduceBy - $fromInterest);
        } else {
            $extraPension = $reduceBy;
        }
        $afterRun = $this->run($after, $context, $extraPension);

        return new ThresholdCost(
            incomeTax: round($before['non_savings_tax'] - $afterRun['non_savings_tax'], 2),
            niClass1: round($before['class_1'] - $afterRun['class_1'], 2),
            niClass4: round($before['class_4'] - $afterRun['class_4'], 2),
            dividendTax: round($before['dividend_tax'] - $afterRun['dividend_tax'], 2),
            interestTax: round($before['interest_tax'] - $afterRun['interest_tax'], 2),
        );
    }

    /** @return array<string, float> */
    public function mix(ThresholdContext $context): array
    {
        $c = $context->components();

        return [
            'employment' => ($c['employment'] ?? 0) + ($c['vesting'] ?? 0),
            'self_employment' => $c['self_employment'] ?? 0,
            'rental' => $c['rental'] ?? 0,
            'dividend' => $c['dividend'] ?? 0,
            'interest' => $c['interest'] ?? 0,
            // Pension income in payment, trust and other income share the non-savings
            // bands and carry no NI, which is exactly the calculator's `otherIncome`.
            'other' => ($c['pension_income'] ?? 0) + ($c['trust'] ?? 0) + ($c['other'] ?? 0),
        ];
    }

    /** @return array{non_savings_tax: float, dividend_tax: float, interest_tax: float, class_1: float, class_4: float} */
    private function run(array $mix, ThresholdContext $context, float $extraPension = 0.0): array
    {
        $deductions = $context->definitions['deductions'] ?? [];
        $pension = (float) ($deductions['employee_pension_contributions'] ?? 0) + $extraPension;
        $giftAid = (float) ($deductions['gift_aid_gross'] ?? 0);
        $bpa = (float) ($deductions['blind_persons_allowance'] ?? 0);

        $full = $this->calculator->calculateNetIncome($mix['employment'], $mix['self_employment'], $mix['rental'], $mix['dividend'], $mix['interest'], $mix['other'], $pension, $giftAid, $bpa);
        $noDividends = $this->calculator->calculateNetIncome($mix['employment'], $mix['self_employment'], $mix['rental'], 0.0, $mix['interest'], $mix['other'], $pension, $giftAid, $bpa);
        $noSavings = $this->calculator->calculateNetIncome($mix['employment'], $mix['self_employment'], $mix['rental'], 0.0, 0.0, $mix['other'], $pension, $giftAid, $bpa);

        return [
            'non_savings_tax' => (float) $noSavings['income_tax'],
            'interest_tax' => (float) $noDividends['income_tax'] - (float) $noSavings['income_tax'],
            'dividend_tax' => (float) $full['income_tax'] - (float) $noDividends['income_tax'],
            'class_1' => (float) ($full['breakdown']['class_1_ni'] ?? 0),
            'class_4' => (float) ($full['breakdown']['class_4_ni'] ?? 0),
        ];
    }
}
```

- [ ] **Step 5: Run**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Thresholds/ThresholdCostCalculatorTest.php`
Expected: PASS. If the first test's exact figure differs by rounding, assert with `toEqualWithDelta(…, 1.0)` and keep the mechanism assertions exact.

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/Services/Tax/Thresholds
git add app/Services/Tax/Thresholds tests/Unit/Services/Tax/Thresholds
git commit -m "feat(tax): threshold contracts and the mixed-income cost delta"
```

---

### Task 9: Childcare entitlements

**Files:**
- Create: `app/Services/Tax/Thresholds/ChildcareEntitlements.php`
- Test: `tests/Unit/Services/Tax/Thresholds/ChildcareEntitlementsTest.php`

**Interfaces:**
- Produces: `ChildcareEntitlements::for(User $user): list<array{label: string, detail: string, amount: float}>`; empty when no qualifying child.

- [ ] **Step 1: Failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Tax\Thresholds\ChildcareEntitlements;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    Carbon::setTestNow('2026-09-21');
    $this->entitlements = app(ChildcareEntitlements::class);
});

afterEach(fn () => Carbon::setTestNow());

function child(User $user, string $dob): FamilyMember
{
    return FamilyMember::create(['user_id' => $user->id, 'first_name' => 'Kid', 'last_name' => 'Test', 'relationship' => 'child', 'date_of_birth' => $dob]);
}

it('returns nothing for a household with no children', function () {
    $user = User::factory()->create(['childcare' => 800]);
    expect($this->entitlements->for($user))->toBe([]);
});

it('caps Tax-Free Childcare per child and prices the extended hours by age', function () {
    $user = User::factory()->create(['childcare' => 1000]); // monthly
    child($user, '2023-03-01'); // three
    child($user, '2018-01-01'); // eight

    $items = $this->entitlements->for($user);
    $labels = array_column($items, 'label');

    expect($labels)->toContain('Tax-Free Childcare')->toContain('Funded childcare hours');
    $tfc = collect($items)->firstWhere('label', 'Tax-Free Childcare');
    // 25% of £12,000 = £3,000, under the £4,000 cap for two children.
    expect($tfc['amount'])->toBe(3000.0);
    $hours = collect($items)->firstWhere('label', 'Funded childcare hours');
    // The three-year-old's extra 15 hours × 38 weeks × the seeded rate for the active year.
    $rate = (float) app(\App\Services\TaxConfigService::class)->getEarlyYearsFunding()['working_parents_30hrs']['hourly_rate'];
    expect($hours['amount'])->toBe(round(15 * 38 * $rate, 2));
});

it('gives no Tax-Free Childcare when no spend is recorded', function () {
    $user = User::factory()->create(['childcare' => null]);
    child($user, '2020-01-01');

    expect(collect($this->entitlements->for($user))->firstWhere('label', 'Tax-Free Childcare'))->toBeNull();
});
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Thresholds/ChildcareEntitlementsTest.php`
Expected: FAIL, class not found.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * The childcare half of the £100,000 line. `getTaxFreeChildcare()` and
 * `getEarlyYearsFunding()` were seeded with the right thresholds and read by
 * nothing (2026-09-17); this is their first caller.
 *
 * `users.childcare` is a MONTHLY expenditure figure (UserProfileService::getExpenditureBreakdown).
 */
final class ChildcareEntitlements
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /** @return list<array{label: string, detail: string, amount: float}> */
    public function for(User $user): array
    {
        $user->loadMissing('familyMembers');
        $children = $user->familyMembers
            ->filter(fn (FamilyMember $m): bool => in_array($m->relationship, ['child', 'step_child'], true) && $m->date_of_birth !== null)
            ->values();
        if ($children->isEmpty()) {
            return [];
        }

        $items = [];
        $tfc = $this->taxConfig->getTaxFreeChildcare();
        $ageLimit = (int) ($tfc['child_age_limit'] ?? 11);
        $underLimit = $children->filter(fn (FamilyMember $m): bool => (int) $m->age <= $ageLimit);
        $annualSpend = (float) ($user->childcare ?? 0) * 12;
        if ($underLimit->isNotEmpty() && $annualSpend > 0) {
            $rate = (float) ($tfc['government_top_up_rate'] ?? 0.25);
            $cap = (float) ($tfc['max_government_contribution'] ?? 2000) * $underLimit->count();
            $items[] = [
                'label' => 'Tax-Free Childcare',
                'detail' => sprintf('%s under %d, up to £%s each', $this->count($underLimit->count(), 'child', 'children'), $ageLimit + 1, number_format($cap / $underLimit->count())),
                'amount' => round(min($annualSpend * $rate, $cap), 2),
            ];
        }

        $funding = $this->taxConfig->getEarlyYearsFunding();
        $hoursValue = 0.0;
        $names = [];
        foreach ($children as $child) {
            $months = (int) Carbon::parse($child->date_of_birth)->diffInMonths(Carbon::today());
            $band = match (true) {
                $months >= 36 && $months < 60 => 'working_parents_30hrs',
                $months >= 24 && $months < 36 => 'working_parents_2yr',
                $months >= (int) ($funding['working_parents_under_2']['eligible_age_from_months'] ?? 9) && $months < 24 => 'working_parents_under_2',
                default => null,
            };
            if ($band === null || empty($funding[$band]['hourly_rate'])) {
                continue;
            }
            $hours = (float) ($funding[$band]['hours_per_week'] ?? 0);
            if ($band === 'working_parents_30hrs') {
                // Only the extension is income-tested; the universal 15 hours stay.
                $hours -= (float) ($funding['universal_15hrs']['hours_per_week'] ?? 15);
            }
            $hoursValue += $hours * (float) ($funding[$band]['weeks_per_year'] ?? 38) * (float) $funding[$band]['hourly_rate'];
            $names[] = sprintf('%d hours for your %s', (int) ($funding[$band]['hours_per_week'] ?? 0), $this->ageWord($months));
        }
        if ($hoursValue > 0) {
            $items[] = ['label' => 'Funded childcare hours', 'detail' => implode(', ', $names), 'amount' => round($hoursValue, 2)];
        }

        return $items;
    }

    private function count(int $n, string $one, string $many): string
    {
        return $n === 1 ? 'One '.$one : ucfirst(\Illuminate\Support\Number::spell($n)).' '.$many;
    }

    private function ageWord(int $months): string
    {
        return $months < 24 ? 'baby' : sprintf('%d-year-old', intdiv($months, 12));
    }
}
```

`Number::spell` needs `ext-intl`; if it is not available in this environment, replace with a small `['One', 'Two', 'Three', 'Four'][$n - 1] ?? (string) $n` map.

- [ ] **Step 4: Run**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Thresholds/ChildcareEntitlementsTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Tax/Thresholds
git add app/Services/Tax/Thresholds/ChildcareEntitlements.php tests/Unit/Services/Tax/Thresholds/ChildcareEntitlementsTest.php
git commit -m "feat(tax): childcare entitlements from the config that nothing read"
```

---

### Task 10: Copy and the catalogue lines

**Files:**
- Create: `app/Services/Tax/Thresholds/ThresholdCopy.php`
- Create: `app/Services/Tax/Thresholds/Lines/MoneyLine.php` (abstract helper), `PersonalAllowanceTaperLine.php`, `HighIncomeChildBenefitLine.php`, `HigherRateLine.php`, `AdditionalRateLine.php`, `TaperedAnnualAllowanceLine.php`, `SalarySacrificeNiCapLine.php`, `NilRateBandLine.php`, `ResidenceBandTaperLine.php`, `PensionsEnterEstateLine.php`
- Modify: `database/seeders/TaxConfigurationSeeder.php` (add `'normal_minimum_pension_age' => 57` under `pension`, with `// 57 from 6 April 2028 (FA 2022 s10); the downside copy names the age money is locked until`)
- Test: `tests/Unit/Services/Tax/Thresholds/LinesTest.php`

**Interfaces:**
- Consumes: `ThresholdContext`, `ThresholdCostCalculator::delta`, `ThresholdCostCalculator::mix`, `ChildcareEntitlements::for`, `ThresholdCost`, `ThresholdResult` from Tasks 8 and 9; config getters on `TaxConfigService`.
- Produces: nine `ThresholdLine` implementations with keys `pa_taper`, `hicbc`, `higher_rate`, `additional_rate`, `tapered_aa`, `ni_cap`, `nil_rate_band`, `rnrb_taper`, `pensions_in_estate`. `ThresholdCopy` static methods used only by the lines.

- [ ] **Step 1: Failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\Thresholds\Lines\AdditionalRateLine;
use App\Services\Tax\Thresholds\Lines\HigherRateLine;
use App\Services\Tax\Thresholds\Lines\HighIncomeChildBenefitLine;
use App\Services\Tax\Thresholds\Lines\PensionsEnterEstateLine;
use App\Services\Tax\Thresholds\Lines\PersonalAllowanceTaperLine;
use App\Services\Tax\Thresholds\Lines\SalarySacrificeNiCapLine;
use App\Services\Tax\Thresholds\ThresholdContext;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    Carbon::setTestNow('2026-09-21');
});

afterEach(function () {
    Carbon::setTestNow();
    Mockery::close();
});

function ctx(User $user): ThresholdContext
{
    return new ThresholdContext($user, app(IncomeDefinitionsService::class)->calculate($user->id));
}

describe('PersonalAllowanceTaperLine', function () {
    it('places a £112,400 parent inside the band with childcare in the cost and a pension lever', function () {
        $user = User::factory()->create(['annual_employment_income' => 112400, 'childcare' => 1000]);
        FamilyMember::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'relationship' => 'child', 'date_of_birth' => '2023-03-01']);

        $result = app(PersonalAllowanceTaperLine::class)->evaluate(ctx($user));

        expect($result)->not->toBeNull()
            ->and($result->position['over'])->toBeTrue()
            ->and($result->position['distance'])->toBe(12400.0)
            ->and($result->headline)->toBe('You are £12,400 into the 60% band')
            ->and(array_column($result->cost->benefits, 'label'))->toContain('Tax-Free Childcare')
            ->and($result->lever['amount'])->toBe(12400.0)
            ->and($result->lever['downside'])->toContain('locked until you are 57');
    });

    it('does not apply to a £70,000 earner', function () {
        $user = User::factory()->create(['annual_employment_income' => 70000]);
        expect(app(PersonalAllowanceTaperLine::class)->evaluate(ctx($user)))->toBeNull();
    });

    it('names an upcoming vest in the lever downside', function () {
        $user = User::factory()->create(['annual_employment_income' => 105000]);
        \App\Models\Investment\InvestmentAccount::create(['user_id' => $user->id, 'account_type' => 'rsu', 'account_name' => 'Acme RSUs', 'provider' => 'Acme', 'current_value' => 0, 'scheme_status' => 'active', 'vesting_frequency_months' => 6, 'full_vest_date' => '2027-03-15', 'units_unvested' => 800, 'current_share_price' => 30]);

        $result = app(PersonalAllowanceTaperLine::class)->evaluate(ctx($user));

        expect($result->lever['downside'])->toContain('vest');
    });
});

describe('HighIncomeChildBenefitLine', function () {
    it('applies to a £66,000 parent receiving child benefit and prices the charge', function () {
        $user = User::factory()->create(['annual_employment_income' => 66000]);
        FamilyMember::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'relationship' => 'child', 'date_of_birth' => '2018-01-01', 'receives_child_benefit' => true]);

        $result = app(HighIncomeChildBenefitLine::class)->evaluate(ctx($user));

        expect($result)->not->toBeNull()
            ->and($result->position['distance'])->toBe(6000.0)
            ->and(collect($result->cost->benefits)->firstWhere('label', 'Child Benefit charge')['amount'])->toBeGreaterThan(0.0);
    });

    it('does not apply without a child receiving child benefit', function () {
        $user = User::factory()->create(['annual_employment_income' => 66000]);
        expect(app(HighIncomeChildBenefitLine::class)->evaluate(ctx($user)))->toBeNull();
    });
});

describe('band lines', function () {
    it('places a £48,000 earner below the higher-rate line within the window', function () {
        $user = User::factory()->create(['annual_employment_income' => 48000]);
        $result = app(HigherRateLine::class)->evaluate(ctx($user));
        expect($result->position['over'])->toBeFalse()->and($result->position['distance'])->toBe(-2270.0);
    });

    it('names the ISA move when the excess is dividends', function () {
        $user = User::factory()->create(['annual_employment_income' => 45000, 'annual_dividend_income' => 8000]);
        $result = app(HigherRateLine::class)->evaluate(ctx($user));
        expect($result->lever['title'])->toContain('ISA');
    });

    it('uses the Gift Aid extended limit for the additional-rate line', function () {
        $user = User::factory()->create(['annual_employment_income' => 135000, 'is_gift_aid' => true, 'annual_charitable_donations' => 12000]);
        $result = app(AdditionalRateLine::class)->evaluate(ctx($user));
        expect($result->range['from'])->toBe(125140.0 + 15000.0);
    });
});

describe('date lines', function () {
    it('counts days to the NI cap for a £30,000 sacrificer', function () {
        $user = User::factory()->create(['annual_employment_income' => 145000, 'employment_income_basis' => 'gross']);
        DCPension::create(['user_id' => $user->id, 'scheme_name' => 'Work', 'pension_type' => 'occupational', 'current_fund_value' => 100000, 'annual_salary' => 145000, 'employee_contribution_percent' => 20.69, 'salary_sacrifice' => true]);

        $result = app(SalarySacrificeNiCapLine::class)->evaluate(ctx($user));

        expect($result)->not->toBeNull()
            ->and($result->position['unit'])->toBe('days')
            ->and($result->position['value'])->toBe((float) Carbon::today()->diffInDays(Carbon::parse('2027-04-06')))
            ->and($result->lever)->toBeNull();
    });

    it('applies the pensions-in-estate date to a DC holder', function () {
        $user = User::factory()->create();
        DCPension::create(['user_id' => $user->id, 'scheme_name' => 'SIPP', 'pension_type' => 'personal', 'current_fund_value' => 400000]);

        $result = app(PensionsEnterEstateLine::class)->evaluate(ctx($user));

        expect($result)->not->toBeNull()->and($result->position['unit'])->toBe('days');
    });
});
```

The estate money lines are exercised through the feature test in Task 12 with a constructed estate; they are not unit-tested here because `IHTCalculationService::calculate` needs the full household graph.

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Thresholds/LinesTest.php`
Expected: FAIL, classes not found.

- [ ] **Step 3: Copy**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

/**
 * Every user-facing string the strip shows, on every surface. British English,
 * no acronyms the surface has not spelt out, no scores, no icons.
 */
final class ThresholdCopy
{
    public static function pounds(float $n): string
    {
        return '£'.number_format(round($n));
    }

    public static function into(float $distance, string $bandName): string
    {
        return $distance > 0
            ? sprintf('You are %s into the %s', self::pounds($distance), $bandName)
            : sprintf('You are %s under the %s', self::pounds(-$distance), $bandName);
    }

    public static function estate(float $distance): string
    {
        return $distance > 0
            ? sprintf('Your estate is %s over the nil rate band', self::pounds($distance))
            : sprintf('Your estate is %s under the nil rate band', self::pounds(-$distance));
    }

    public static function taperBody(float $distance, int $effectivePct, float $threshold): string
    {
        return $distance > 0
            ? sprintf('The next %s you earn costs %dp in the pound. A pension contribution is the only way back under %s.', self::pounds($distance), $effectivePct, self::pounds($threshold))
            : sprintf('Every pound above %s costs %dp. A pay rise, bonus or share vest of %s would take you over it.', self::pounds($threshold), $effectivePct, self::pounds(-$distance));
    }

    public static function taperExplanation(float $threshold, int $higherPct, int $lostPct): string
    {
        return sprintf('For every £2 you earn above %s you lose £1 of your Personal Allowance. That lost allowance was being taxed at %d%%, so the pound you earned costs %dp and the allowance costs another %dp.', self::pounds($threshold), $higherPct, $higherPct, $lostPct);
    }

    public static function lockedUntil(int $age, ?array $nextVest): string
    {
        $text = sprintf('The money is locked until you are %d.', $age);
        if ($nextVest !== null) {
            $text .= sprintf(' Your %s share vest of %s cannot be sacrificed and will push you back over.', $nextVest['date']->format('F'), self::pounds($nextVest['value']));
        }

        return $text;
    }
}
```

- [ ] **Step 4: The abstract money helper**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use App\Services\Tax\Thresholds\ThresholdLine;
use App\Services\TaxConfigService;

abstract class MoneyLine implements ThresholdLine
{
    /** A line applies when the user is over it, or within this share below it. */
    protected const WINDOW = 0.25;

    public function __construct(
        protected readonly ThresholdCostCalculator $costs,
        protected readonly TaxConfigService $taxConfig,
    ) {}

    protected function within(float $value, float $threshold): bool
    {
        return $threshold > 0 && $value >= $threshold * (1 - static::WINDOW);
    }

    /** @return array{value: float, distance: float, unit: string, over: bool} */
    protected function position(float $value, float $threshold): array
    {
        return ['value' => round($value, 2), 'distance' => round($value - $threshold, 2), 'unit' => 'gbp', 'over' => $value > $threshold];
    }

    /** The ISA move applies when savings income alone covers the excess. */
    protected function mechanismFor(ThresholdContext $context, float $excess): string
    {
        $c = $context->components();

        return $excess > 0 && (($c['interest'] ?? 0) + ($c['dividend'] ?? 0)) >= $excess ? 'isa' : 'pension';
    }

    /**
     * The lever for an income excess: a pension contribution equal to the excess
     * (default), or the ISA move when the excess is covered by savings income.
     *
     * @return array{title: string, amount: float, recovers: float, downside: string, action: array{route: string}, mechanism: string}
     */
    protected function incomeLever(ThresholdContext $context, float $amount, ThresholdCost $cost): array
    {
        if ($this->mechanismFor($context, $amount) === 'isa') {
            return [
                'title' => sprintf('Move %s of savings income into an ISA', ThresholdCopy::pounds($amount)),
                'amount' => round($amount, 2),
                'recovers' => $cost->total(),
                'downside' => "Uses this year's ISA allowance.",
                'action' => ['route' => '/tax-strategy'],
                'mechanism' => 'isa',
            ];
        }

        return [
            'title' => sprintf('Salary sacrifice %s into your pension', ThresholdCopy::pounds($amount)),
            'amount' => round($amount, 2),
            'recovers' => $cost->total(),
            'downside' => ThresholdCopy::lockedUntil($this->minimumPensionAge(), $context->vests()[0] ?? null),
            'action' => ['route' => '/tax-strategy'],
            'mechanism' => 'pension',
        ];
    }

    protected function minimumPensionAge(): int
    {
        return (int) $this->taxConfig->get('pension.normal_minimum_pension_age', 57);
    }
}
```

- [ ] **Step 5: Personal Allowance taper**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\TaxStrategyMath;
use App\Services\Tax\Thresholds\ChildcareEntitlements;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use App\Services\Tax\Thresholds\ThresholdResult;
use App\Services\TaxConfigService;

final class PersonalAllowanceTaperLine extends MoneyLine
{
    public function __construct(
        ThresholdCostCalculator $costs,
        TaxConfigService $taxConfig,
        private readonly TaxStrategyMath $math,
        private readonly ChildcareEntitlements $childcare,
    ) {
        parent::__construct($costs, $taxConfig);
    }

    public function key(): string
    {
        return 'pa_taper';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        $income = $this->taxConfig->getIncomeTax();
        $threshold = (float) ($income['personal_allowance_taper_threshold'] ?? 100000);
        $ani = $context->adjustedNetIncome();
        if (! $this->within($ani, $threshold)) {
            return null;
        }

        $higherRate = $this->math->bandRateForBand('higher');
        $bandTop = $threshold + 2 * (float) ($income['personal_allowance'] ?? 12570);
        $excess = max(0.0, $ani - $threshold);

        $cost = $excess > 0 ? $this->costs->delta($context, $excess) : new ThresholdCost();
        foreach ($this->childcare->for($context->user) as $item) {
            $cost = $cost->withBenefit($item['label'], $item['detail'], $item['amount']);
        }

        $lever = null;
        if ($excess > 0) {
            // The one calculator every surface uses sizes the contribution; the
            // excess is the fallback when the strategy has not fired (no AA headroom).
            $suggested = (float) ($context->strategy('pa_taper_rescue')['suggested_contribution'] ?? $excess);
            $lever = $this->incomeLever($context, $suggested > 0 ? min($excess, $suggested) : $excess, $cost);
        }

        $effectivePct = (int) round($higherRate * 150);

        return new ThresholdResult(
            key: $this->key(),
            title: 'Personal Allowance taper',
            range: ['from' => $threshold, 'to' => $bandTop],
            position: $this->position($ani, $threshold),
            headline: ThresholdCopy::into($ani - $threshold, sprintf('%d%% band', $effectivePct)),
            body: ThresholdCopy::taperBody($ani - $threshold, $effectivePct, $threshold),
            explanation: ThresholdCopy::taperExplanation($threshold, (int) round($higherRate * 100), (int) round($higherRate * 50)),
            cost: $cost,
            lever: $lever,
            incomeMix: $this->costs->mix($context),
        );
    }
}
```

- [ ] **Step 6: High Income Child Benefit Charge**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Benefits\ChildBenefitService;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use App\Services\Tax\Thresholds\ThresholdResult;
use App\Services\TaxConfigService;

final class HighIncomeChildBenefitLine extends MoneyLine
{
    public function __construct(
        ThresholdCostCalculator $costs,
        TaxConfigService $taxConfig,
        private readonly ChildBenefitService $childBenefit,
    ) {
        parent::__construct($costs, $taxConfig);
    }

    public function key(): string
    {
        return 'hicbc';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        if ($this->childBenefit->getEligibleChildren($context->user)->isEmpty()) {
            return null;
        }
        $config = $this->taxConfig->getChildBenefit();
        $threshold = (float) ($config['high_income_charge_threshold'] ?? 60000);
        $top = (float) ($config['high_income_full_clawback'] ?? 80000);
        $ani = $context->adjustedNetIncome();
        if (! $this->within($ani, $threshold)) {
            return null;
        }

        $benefit = (float) $this->childBenefit->calculateAnnualChildBenefit($context->user)['annual_amount'];
        $charge = $this->childBenefit->calculateHICBC($ani, $benefit);
        $excess = max(0.0, $ani - $threshold);
        $cost = ($excess > 0 ? $this->costs->delta($context, $excess) : new ThresholdCost())
            ->withBenefit('Child Benefit charge', sprintf('%d%% of your %s Child Benefit repaid', (int) $charge['clawback_percentage'], ThresholdCopy::pounds($benefit)), (float) $charge['charge']);

        return new ThresholdResult(
            key: $this->key(),
            title: 'High Income Child Benefit Charge',
            range: ['from' => $threshold, 'to' => $top],
            position: $this->position($ani, $threshold),
            headline: ThresholdCopy::into($ani - $threshold, 'Child Benefit charge band'),
            body: sprintf('Between %s and %s you repay 1%% of your Child Benefit for every £200 of income. A pension contribution brings you back under.', ThresholdCopy::pounds($threshold), ThresholdCopy::pounds($top)),
            explanation: sprintf('The charge is collected through your tax return. At %s it is %s of the %s you receive.', ThresholdCopy::pounds($ani), ThresholdCopy::pounds((float) $charge['charge']), ThresholdCopy::pounds($benefit)),
            cost: $cost,
            lever: $excess > 0 ? $this->incomeLever($context, $excess, $cost) : null,
            incomeMix: $this->costs->mix($context),
        );
    }
}
```

`ChildBenefitService::calculateAnnualChildBenefit` is tier-gated through `TeaserGate` for `benefits_child`. `getEligibleChildren` is not. If the gate returns the zero position for a Free user, this line still applies and its `Child Benefit charge` item is £0; that is the correct reading of "we cannot show you the figure on this plan", and the web strip renders the row only when the amount is above zero.

- [ ] **Step 7: Higher-rate and additional-rate lines**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\TaxStrategyMath;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use App\Services\Tax\Thresholds\ThresholdResult;
use App\Services\TaxConfigService;

final class HigherRateLine extends MoneyLine
{
    public function __construct(ThresholdCostCalculator $costs, TaxConfigService $taxConfig, private readonly TaxStrategyMath $math)
    {
        parent::__construct($costs, $taxConfig);
    }

    public function key(): string
    {
        return 'higher_rate';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        $threshold = $this->math->bandThresholdsFor($context->user)['higher'];
        $income = $context->totalIncome();
        if (! $this->within($income, $threshold)) {
            return null;
        }
        $excess = max(0.0, $income - $threshold);
        $cost = $excess > 0 ? $this->costs->delta($context, $excess, $this->mechanismFor($context, $excess)) : new ThresholdCost();
        $pct = (int) round($this->math->bandRateForBand('higher') * 100);

        return new ThresholdResult(
            key: $this->key(),
            title: 'Higher-rate threshold',
            range: ['from' => $threshold, 'to' => null],
            position: $this->position($income, $threshold),
            headline: ThresholdCopy::into($income - $threshold, sprintf('%d%% band', $pct)),
            body: sprintf('Above %s your Savings Allowance halves, dividends and gains are taxed at the higher rates, and Marriage Allowance is lost.', ThresholdCopy::pounds($threshold)),
            explanation: sprintf('Income tax is %d%% on this slice, and the allowances that go with basic rate go with it.', $pct),
            cost: $cost,
            lever: $excess > 0 ? $this->incomeLever($context, $excess, $cost) : null,
            incomeMix: $this->costs->mix($context),
        );
    }
}
```

`AdditionalRateLine` is the same file shape with six changes: key `additional_rate`; threshold `bandThresholdsFor($context->user)['additional']`; title `Additional-rate threshold`; body `sprintf('Above %s income tax is %d%% and the Savings Allowance is nil.', ThresholdCopy::pounds($threshold), $pct)`; rate `bandRateForBand('additional')`; lever amount `min($excess, (float) ($context->strategy('additional_rate_avoidance')['suggested_contribution'] ?? $excess))` guarded so a zero suggestion falls back to `$excess`. Copy the class and change those six things.

- [ ] **Step 8: Tapered Annual Allowance**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\TaxStrategyMath;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use App\Services\Tax\Thresholds\ThresholdResult;
use App\Services\TaxConfigService;

final class TaperedAnnualAllowanceLine extends MoneyLine
{
    public function __construct(ThresholdCostCalculator $costs, TaxConfigService $taxConfig, private readonly TaxStrategyMath $math)
    {
        parent::__construct($costs, $taxConfig);
    }

    public function key(): string
    {
        return 'tapered_aa';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $taper = $pension['tapered_annual_allowance'] ?? [];
        $thresholdLimit = (float) ($taper['threshold_income'] ?? 200000);
        $adjustedLimit = (float) ($taper['adjusted_income_threshold'] ?? $taper['adjusted_income'] ?? 260000);
        $minimum = (float) ($taper['minimum_allowance'] ?? 10000);
        $adjusted = (float) ($context->definitions['adjusted_income'] ?? 0);
        if ($context->thresholdIncome() <= $thresholdLimit || ! $this->within($adjusted, $adjustedLimit)) {
            return null;
        }
        $full = (float) ($pension['annual_allowance'] ?? 60000);
        $lost = max(0.0, $full - $this->math->effectiveAnnualAllowanceFor($context->user));
        $cost = (new ThresholdCost())->withBenefit(
            'Pension Annual Allowance lost',
            sprintf('%s of your %s allowance, at your marginal rate', ThresholdCopy::pounds($lost), ThresholdCopy::pounds($full)),
            $lost * $this->math->bandRateFor($context->user),
        );
        $strategy = $context->strategy('tapered_annual_allowance');

        return new ThresholdResult(
            key: $this->key(),
            title: 'Tapered Annual Allowance',
            range: ['from' => $adjustedLimit, 'to' => $adjustedLimit + 2 * ($full - $minimum)],
            position: $this->position($adjusted, $adjustedLimit),
            headline: ThresholdCopy::into($adjusted - $adjustedLimit, 'pension taper'),
            body: sprintf('For every £2 of adjusted income above %s you lose £1 of pension Annual Allowance, down to %s.', ThresholdCopy::pounds($adjustedLimit), ThresholdCopy::pounds($minimum)),
            explanation: sprintf('Your allowance this year is %s against the full %s.', ThresholdCopy::pounds($full - $lost), ThresholdCopy::pounds($full)),
            cost: $cost,
            lever: $strategy === null ? null : [
                'title' => (string) ($strategy['title'] ?? 'Reduce your adjusted income'),
                'amount' => (float) ($strategy['suggested_contribution'] ?? 0),
                'recovers' => (float) ($strategy['estimated_annual_tax_saved'] ?? 0),
                'downside' => '',
                'action' => ['route' => '/tax-strategy'],
                'mechanism' => 'pension',
            ],
            incomeMix: $this->costs->mix($context),
        );
    }
}
```

- [ ] **Step 9: The date lines and the estate lines**

`SalarySacrificeNiCapLine`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdLine;
use App\Services\Tax\Thresholds\ThresholdResult;
use App\Services\TaxConfigService;
use Carbon\Carbon;

final class SalarySacrificeNiCapLine implements ThresholdLine
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    public function key(): string
    {
        return 'ni_cap';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        $sacrificed = (float) ($context->definitions['deductions']['salary_sacrificed'] ?? 0);
        $cap = (float) $this->taxConfig->get('pension.salary_sacrifice.nic_exemption_cap', 2000);
        $date = Carbon::parse((string) $this->taxConfig->get('pension.salary_sacrifice.nic_exemption_cap_effective_date', '2027-04-06'));
        if ($sacrificed <= $cap || $date->isPast()) {
            return null;
        }
        $rate = (float) $this->taxConfig->get('national_insurance.class_1.employee.main_rate', 0.08);
        $days = (int) Carbon::today()->diffInDays($date);
        $cost = (new ThresholdCost())->withBenefit(
            'National Insurance on sacrifice above the cap',
            sprintf('%s above the %s cap at %d%%', ThresholdCopy::pounds($sacrificed - $cap), ThresholdCopy::pounds($cap), (int) round($rate * 100)),
            ($sacrificed - $cap) * $rate,
        );

        return new ThresholdResult(
            key: $this->key(),
            title: 'Salary sacrifice National Insurance cap',
            range: null,
            position: ['value' => (float) $days, 'distance' => (float) $days, 'unit' => 'days', 'over' => false],
            headline: sprintf('%s · %d days', $date->format('j F Y'), $days),
            body: sprintf('From %s only the first %s of salary sacrifice each year is free of National Insurance. You sacrifice %s.', $date->format('j F Y'), ThresholdCopy::pounds($cap), ThresholdCopy::pounds($sacrificed)),
            explanation: 'Employer contributions and income tax relief are unchanged. Only employee National Insurance on the excess is affected.',
            cost: $cost,
            lever: null,
        );
    }
}
```

`PensionsEnterEstateLine` (same shape, implements `ThresholdLine` directly, constructor takes `TaxConfigService`): applies when `$pot = (float) $context->user->dcPensions()->sum('current_fund_value')` is above zero and `$date = Carbon::parse($this->taxConfig->getInheritanceTax()['pension_iht_inclusion']['effective_date'] ?? '2027-04-06')` is in the future. Then `$iht = $context->iht(); $room = max(0.0, (float) $iht['total_allowances'] - (float) $iht['total_net_estate']); $exposed = max(0.0, $pot - $room);` and the cost is one benefit item, `'Inheritance tax on your pension'`, detail `sprintf('%s in defined contribution pensions at %d%%', pounds($pot), (int) round($iht['iht_rate'] * 100))`, amount `$exposed * (float) $iht['iht_rate']`. Key `pensions_in_estate`, title `Pensions enter your estate`, headline `sprintf('%s · %d days', $date->format('j F Y'), $days)`, body `sprintf('From %s unused pension pots count towards inheritance tax.', $date->format('j F Y'))`, explanation `sprintf('Your estate is %s against allowances of %s, so %s of your pension would be taxed.', pounds($iht['total_net_estate']), pounds($iht['total_allowances']), pounds($exposed))`, lever null, position as the NI cap line's with its own `$days`.

`NilRateBandLine` extends `MoneyLine`. Guard before touching the estate: `if (! $context->user->properties()->exists() && ! $context->user->investmentAccounts()->exists() && ! $context->user->savingsAccounts()->exists()) return null;`. Then `$iht = $context->iht(); $net = (float) $iht['total_net_estate']; $allowances = (float) $iht['total_allowances'];` and `if (! $this->within($net, $allowances)) return null;`. Cost: `(new ThresholdCost())->withBenefit('Inheritance tax', sprintf('%d%% on %s', (int) round($iht['iht_rate'] * 100), pounds($iht['taxable_estate'])), (float) $iht['iht_liability'])`. Key `nil_rate_band`, title `Inheritance tax nil rate band`, range `['from' => $allowances, 'to' => null]`, position `$this->position($net, $allowances)`, headline `ThresholdCopy::estate($net - $allowances)`, body `sprintf('Everything above your allowances of %s is taxed at %d%%.', pounds($allowances), (int) round($iht['iht_rate'] * 100))`, explanation `sprintf('Your allowances combine the nil rate band of %s and the residence nil rate band of %s.', pounds($iht['nrb_available']), pounds($iht['rnrb_available']))`, lever null, `incomeMix: []`.

`ResidenceBandTaperLine` extends `MoneyLine`, same guard, `$threshold = (float) ($this->taxConfig->getInheritanceTax()['rnrb_taper_threshold'] ?? 2000000)`, applies when `within($net, $threshold)`. Cost benefit `'Residence nil rate band lost'`, detail `sprintf('Reduced by £1 for every £2 over %s', pounds($threshold))`, amount `(float) $iht['rnrb_taper_reduction'] * (float) $iht['iht_rate']`. Key `rnrb_taper`, title `Residence nil rate band taper`, range `['from' => $threshold, 'to' => null]`, headline `ThresholdCopy::into($net - $threshold, 'residence band taper')`, body `sprintf('Above %s the residence nil rate band tapers away.', pounds($threshold))`, explanation `sprintf('You lose %s of residence nil rate band at this estate value.', pounds($iht['rnrb_taper_reduction']))`, lever null.

- [ ] **Step 10: Run**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Thresholds/LinesTest.php`
Expected: PASS. Where a headline string differs by a pound of rounding, fix the copy helper, not the test.

- [ ] **Step 11: Commit**

```bash
./vendor/bin/pint app/Services/Tax/Thresholds database/seeders/TaxConfigurationSeeder.php
git add app/Services/Tax/Thresholds database/seeders/TaxConfigurationSeeder.php tests/Unit/Services/Tax/Thresholds/LinesTest.php
git commit -m "feat(tax): the nine catalogue lines and their copy"
```

---

### Task 11: The evaluator and its registration

**Files:**
- Create: `app/Services/Tax/Thresholds/ThresholdPositionService.php`
- Modify: `app/Providers/AppServiceProvider.php:61` (`register()`)
- Test: `tests/Unit/Services/Tax/Thresholds/ThresholdPositionServiceTest.php`

**Interfaces:**
- Consumes: every `ThresholdLine` tagged `threshold.lines`.
- Produces: `ThresholdPositionService::evaluate(User $user): array{strip: ?string, lines: list<array>}`.

- [ ] **Step 1: Failing test with fake lines**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdLine;
use App\Services\Tax\Thresholds\ThresholdPositionService;
use App\Services\Tax\Thresholds\ThresholdResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fakeLine(string $key, float $distance, string $unit, bool $lever): ThresholdLine
{
    return new class($key, $distance, $unit, $lever) implements ThresholdLine
    {
        public function __construct(private string $k, private float $d, private string $u, private bool $l) {}

        public function key(): string
        {
            return $this->k;
        }

        public function evaluate(ThresholdContext $context): ?ThresholdResult
        {
            return new ThresholdResult($this->k, $this->k, null, ['value' => $this->d, 'distance' => $this->d, 'unit' => $this->u, 'over' => $this->d > 0], 'h', 'b', 'e', new ThresholdCost(), $this->l ? ['title' => 't', 'amount' => 1.0, 'recovers' => 1.0, 'downside' => '', 'action' => ['route' => '/x']] : null);
        }
    };
}

it('orders money lines by proximity, dates after money, and leads with the nearest line that has a lever', function () {
    $user = User::factory()->create();
    $service = new ThresholdPositionService(app(IncomeDefinitionsService::class), [
        fakeLine('far', 20000.0, 'gbp', true),
        fakeLine('date', 30.0, 'days', false),
        fakeLine('near_no_lever', -500.0, 'gbp', false),
        fakeLine('near', 1200.0, 'gbp', true),
    ]);

    $out = $service->evaluate($user);

    expect(array_column($out['lines'], 'key'))->toBe(['near_no_lever', 'near', 'far', 'date'])
        ->and($out['strip'])->toBe('near');
});

it('returns an empty list and no strip when nothing applies', function () {
    $user = User::factory()->create();
    $none = new class implements ThresholdLine
    {
        public function key(): string
        {
            return 'none';
        }

        public function evaluate(ThresholdContext $context): ?ThresholdResult
        {
            return null;
        }
    };

    $out = (new ThresholdPositionService(app(IncomeDefinitionsService::class), [$none]))->evaluate($user);

    expect($out)->toBe(['strip' => null, 'lines' => []]);
});

it('is resolvable from the container with the catalogue tagged', function () {
    expect(app(ThresholdPositionService::class))->toBeInstanceOf(ThresholdPositionService::class);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Thresholds/ThresholdPositionServiceTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;

/**
 * Runs every catalogue line against one user and orders what applies by how
 * near it is. Money lines first, by absolute distance; date lines after, by
 * days. The strip is the nearest line the user can still act on (has a lever);
 * a line with no lever never leads. Empty output means the strip does not render.
 */
final class ThresholdPositionService
{
    /** @param iterable<ThresholdLine> $lines */
    public function __construct(
        private readonly IncomeDefinitionsService $definitions,
        private readonly iterable $lines,
    ) {}

    /** @return array{strip: ?string, lines: list<array<string, mixed>>} */
    public function evaluate(User $user): array
    {
        $context = new ThresholdContext($user, $this->definitions->calculate($user->id));

        $results = [];
        foreach ($this->lines as $line) {
            $result = $line->evaluate($context);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        usort($results, function (ThresholdResult $a, ThresholdResult $b): int {
            if ($a->isDateLine() !== $b->isDateLine()) {
                return $a->isDateLine() ? 1 : -1;
            }

            return abs($a->position['distance']) <=> abs($b->position['distance']);
        });

        $strip = null;
        foreach ($results as $result) {
            if ($result->lever !== null) {
                $strip = $result->key;
                break;
            }
        }

        return ['strip' => $strip, 'lines' => array_map(fn (ThresholdResult $r): array => $r->toArray(), $results)];
    }
}
```

`AppServiceProvider::register()`, at the end of the method:

```php
        // Threshold position (2026-09-21): the catalogue is the tagged set, so a new
        // line is one class and one entry here, never a change to the evaluator.
        $this->app->tag([
            \App\Services\Tax\Thresholds\Lines\PersonalAllowanceTaperLine::class,
            \App\Services\Tax\Thresholds\Lines\HighIncomeChildBenefitLine::class,
            \App\Services\Tax\Thresholds\Lines\HigherRateLine::class,
            \App\Services\Tax\Thresholds\Lines\AdditionalRateLine::class,
            \App\Services\Tax\Thresholds\Lines\TaperedAnnualAllowanceLine::class,
            \App\Services\Tax\Thresholds\Lines\SalarySacrificeNiCapLine::class,
            \App\Services\Tax\Thresholds\Lines\NilRateBandLine::class,
            \App\Services\Tax\Thresholds\Lines\ResidenceBandTaperLine::class,
            \App\Services\Tax\Thresholds\Lines\PensionsEnterEstateLine::class,
        ], 'threshold.lines');
        $this->app->bind(\App\Services\Tax\Thresholds\ThresholdPositionService::class, fn ($app) => new \App\Services\Tax\Thresholds\ThresholdPositionService(
            $app->make(\App\Services\Tax\IncomeDefinitionsService::class),
            $app->tagged('threshold.lines'),
        ));
```

- [ ] **Step 4: Run**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Thresholds`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Tax/Thresholds app/Providers/AppServiceProvider.php
git add app/Services/Tax/Thresholds app/Providers/AppServiceProvider.php tests/Unit/Services/Tax/Thresholds
git commit -m "feat(tax): the threshold position evaluator, ordered by proximity"
```

---

### Task 12: `GET /api/thresholds`

**Files:**
- Create: `app/Http/Controllers/Api/ThresholdController.php`
- Modify: `routes/api.php` (a new top-level line after the `recommendations` group closes, ~line 1131) and its `use` block (~line 103)
- Test: `tests/Feature/Api/ThresholdControllerTest.php`

**Interfaces:**
- Produces: `GET /api/thresholds` → `{"success": true, "data": {"strip": ?string, "lines": [...]}}`, Sanctum-guarded.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    Carbon::setTestNow('2026-09-21');
});

afterEach(fn () => Carbon::setTestNow());

it('requires authentication', function () {
    $this->getJson('/api/thresholds')->assertUnauthorized();
});

it('returns nothing for a user no line applies to', function () {
    Sanctum::actingAs(User::factory()->create(['annual_employment_income' => 30000]));

    $this->getJson('/api/thresholds')
        ->assertOk()
        ->assertJsonPath('data.strip', null)
        ->assertJsonPath('data.lines', []);
});

it('leads with the taper for the spec profile and keeps date lines last', function () {
    $user = User::factory()->create(['annual_employment_income' => 112400, 'childcare' => 1000]);
    FamilyMember::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'relationship' => 'child', 'date_of_birth' => '2023-03-01']);
    DCPension::create(['user_id' => $user->id, 'scheme_name' => 'SIPP', 'pension_type' => 'personal', 'current_fund_value' => 400000]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/thresholds')->assertOk();
    $lines = $response->json('data.lines');
    $units = array_column(array_column($lines, 'position'), 'unit');

    expect($response->json('data.strip'))->toBe('pa_taper')
        ->and($lines[0]['headline'])->toBe('You are £12,400 into the 60% band')
        ->and($lines[0]['cost_total'])->toBeGreaterThan(2480.0)
        ->and(array_search('days', $units, true))->toBeGreaterThan(array_search('gbp', $units, true));
});
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Feature/Api/ThresholdControllerTest.php`
Expected: FAIL, 404.

- [ ] **Step 3: Controller and route**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Tax\Thresholds\ThresholdPositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where the user stands against the tax and benefit lines that apply to them,
 * nearest first. One payload for web, /m and native (Rule 20); the surfaces
 * differ only in how much of it they show.
 */
class ThresholdController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(private readonly ThresholdPositionService $thresholds) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->thresholds->evaluate($request->user())]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Evaluating threshold position');
        }
    }
}
```

`routes/api.php`: add `use App\Http\Controllers\Api\ThresholdController;` beside the `RecommendationsController` import, and after the recommendations group closes:

```php
// Threshold position (2026-09-21) — the strip above "Your actions" on web, /m and native.
Route::middleware('auth:sanctum')->get('/thresholds', [ThresholdController::class, 'index']);
```

- [ ] **Step 4: Run**

Run: `./vendor/bin/pest tests/Feature/Api/ThresholdControllerTest.php`
Expected: PASS. If the third test trips the child-benefit tier gate, give the user a Premium tier the way `tests/Feature/Api/RecommendationsControllerTest.php` does; the taper line does not depend on that gate, only the child-benefit figure does.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/Api/ThresholdController.php
git add app/Http/Controllers/Api/ThresholdController.php routes/api.php tests/Feature/Api/ThresholdControllerTest.php
git commit -m "feat(api): GET /api/thresholds"
```

---

### Task 13: Web strip

**Files:**
- Create: `resources/js/components/Actions/ThresholdStrip.vue`
- Modify: `resources/js/views/Actions/ActionsDashboard.vue:22` (template, above `.top-priorities`), `:140-150` (data), `:200-215` (`load`)
- Test: `resources/js/components/Actions/__tests__/ThresholdStrip.spec.js`

**Interfaces:**
- Consumes: the `GET /api/thresholds` payload.
- Produces: `<ThresholdStrip :data="thresholds" />` rendering nothing when `data.lines` is empty.

- [ ] **Step 1: Failing test**

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ThresholdStrip from '../ThresholdStrip.vue';

const line = {
  key: 'pa_taper',
  title: 'Personal Allowance taper',
  range: { from: 100000, to: 125140 },
  position: { value: 112400, distance: 12400, unit: 'gbp', over: true },
  headline: 'You are £12,400 into the 60% band',
  body: 'The next £12,400 you earn costs 60p in the pound.',
  explanation: 'For every £2 you earn above £100,000 you lose £1 of your Personal Allowance.',
  income_mix: { employment: 112400 },
  cost: { income_tax: 2480, ni_class_1: 0, ni_class_4: 0, dividend_tax: 0, interest_tax: 0, benefits: [{ label: 'Tax-Free Childcare', detail: 'One child under 12', amount: 2000 }], total: 4480 },
  cost_total: 4480,
  lever: { title: 'Salary sacrifice £12,400 into your pension', amount: 12400, recovers: 4480, downside: 'The money is locked until you are 57.', action: { route: '/tax-strategy' } },
};
const dateLine = { key: 'ni_cap', title: 'Salary sacrifice National Insurance cap', range: null, position: { value: 197, distance: 197, unit: 'days', over: false }, headline: '6 April 2027 · 197 days', body: '', explanation: '', income_mix: {}, cost: null, cost_total: 160, lever: null };

const mountWith = (data) => mount(ThresholdStrip, {
  props: { data },
  global: { stubs: { 'router-link': { template: '<a><slot /></a>' } } },
});

describe('ThresholdStrip', () => {
  it('renders nothing when no line applies', () => {
    expect(mountWith({ strip: null, lines: [] }).html()).toBe('<!--v-if-->');
  });

  it('shows the strip line collapsed, then the cost and lever on expand', async () => {
    const w = mountWith({ strip: 'pa_taper', lines: [line, dateLine] });
    expect(w.text()).toContain('You are £12,400 into the 60% band');
    expect(w.text()).not.toContain('Tax-Free Childcare');
    await w.get('button').trigger('click');
    expect(w.text()).toContain('Tax-Free Childcare');
    expect(w.text()).toContain('£4,480');
    expect(w.text()).toContain('Salary sacrifice £12,400 into your pension');
    expect(w.text()).toContain('Salary sacrifice National Insurance cap');
  });
});
```

- [ ] **Step 2: Run to verify failure**

Run: `npx vitest run resources/js/components/Actions/__tests__/ThresholdStrip.spec.js`
Expected: FAIL.

- [ ] **Step 3: Component**

```vue
<template>
  <section v-if="strip" class="strip" aria-labelledby="threshold-strip-title">
    <div class="flex items-start justify-between gap-4">
      <div class="min-w-0">
        <h3 id="threshold-strip-title" class="text-lg font-bold text-horizon-500">{{ strip.headline }}</h3>
        <p class="text-body-sm text-neutral-500 mt-1">{{ strip.body }}</p>
      </div>
      <button type="button" class="strip-toggle" @click="expanded = !expanded">
        {{ expanded ? 'Hide' : 'See what this costs' }}
      </button>
    </div>

    <div v-if="strip.range" class="ribbon" role="img" :aria-label="ribbonLabel">
      <div class="ribbon-track">
        <div class="ribbon-marker" :style="{ left: markerLeft }"></div>
      </div>
      <div class="flex justify-between text-caption text-neutral-500 mt-1">
        <span>{{ formatCurrency(strip.range.from) }}</span>
        <span class="font-semibold text-horizon-500">You: {{ formatCurrency(strip.position.value) }}</span>
        <span>{{ strip.range.to ? formatCurrency(strip.range.to) : '' }}</span>
      </div>
    </div>

    <div v-if="expanded" class="mt-6 space-y-6">
      <div>
        <h4 class="text-body-sm font-bold text-horizon-500">{{ strip.title }}</h4>
        <p class="text-body-sm text-neutral-500 mt-1">{{ strip.explanation }}</p>
      </div>

      <div v-if="strip.cost">
        <h4 class="text-body-sm font-bold text-horizon-500 mb-2">What being over the line costs you</h4>
        <dl class="space-y-2">
          <div v-for="row in costRows" :key="row.label" class="flex justify-between gap-4">
            <dt class="text-body-sm text-neutral-500">{{ row.label }}<span v-if="row.detail" class="block text-caption">{{ row.detail }}</span></dt>
            <dd class="text-body-sm font-semibold text-horizon-500">{{ formatCurrency(row.amount) }}</dd>
          </div>
          <div class="flex justify-between gap-4 border-t border-light-gray pt-2">
            <dt class="text-body-sm font-bold text-horizon-500">Total</dt>
            <dd class="text-body-sm font-bold text-horizon-500">{{ formatCurrency(strip.cost_total) }} a year</dd>
          </div>
        </dl>
        <p v-if="mixRows.length > 1" class="text-caption text-neutral-500 mt-3">Your income this year: {{ mixRows.join(', ') }}.</p>
      </div>

      <div v-if="strip.lever">
        <h4 class="text-body-sm font-bold text-horizon-500 mb-1">The one lever that moves it</h4>
        <p class="text-body-sm text-horizon-500">{{ strip.lever.title }}. You recover {{ formatCurrency(strip.lever.recovers) }}.</p>
        <p class="text-body-sm text-violet-700 mt-1">{{ strip.lever.downside }}</p>
        <router-link :to="strip.lever.action.route" class="inline-block mt-2 text-body-sm font-semibold text-raspberry-500 hover:underline">Model this change</router-link>
      </div>

      <div v-if="others.length">
        <h4 class="text-body-sm font-bold text-horizon-500 mb-2">Other lines that apply to you</h4>
        <ul class="space-y-2">
          <li v-for="other in others" :key="other.key" class="flex justify-between gap-4">
            <span class="text-body-sm text-horizon-500">{{ other.title }}</span>
            <span class="text-body-sm text-neutral-500">{{ other.headline }}</span>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

/**
 * Threshold position (artboard D, 2026-09-17): one line, not a grid. Shows the
 * nearest line the user can still act on; everything else waits behind the
 * click. Renders nothing when no line applies. All copy comes from the server.
 */
export default {
  name: 'ThresholdStrip',
  mixins: [currencyMixin],
  props: {
    data: { type: Object, default: () => ({ strip: null, lines: [] }) },
  },
  data() {
    return { expanded: false };
  },
  computed: {
    strip() {
      const lines = this.data?.lines ?? [];
      return lines.find((l) => l.key === this.data?.strip) || null;
    },
    others() {
      return (this.data?.lines ?? []).filter((l) => l.key !== this.data?.strip);
    },
    costRows() {
      const c = this.strip?.cost;
      if (!c) return [];
      const named = [
        ['Income tax', c.income_tax], ['National Insurance', c.ni_class_1], ['Class 4 National Insurance', c.ni_class_4],
        ['Dividend tax', c.dividend_tax], ['Tax on savings interest', c.interest_tax],
      ].filter(([, amount]) => amount > 0).map(([label, amount]) => ({ label, detail: '', amount }));
      return [...named, ...(c.benefits || []).filter((b) => b.amount > 0)];
    },
    mixRows() {
      const labels = { employment: 'employment', self_employment: 'self-employment', rental: 'rental profit', dividend: 'dividends', interest: 'interest', other: 'pension and other income' };
      return Object.entries(this.strip?.income_mix ?? {}).filter(([, v]) => v > 0).map(([k, v]) => `${this.formatCurrency(v)} ${labels[k] || k}`);
    },
    markerLeft() {
      const r = this.strip?.range;
      if (!r || !r.to) return '0%';
      const pct = ((this.strip.position.value - r.from) / (r.to - r.from)) * 100;
      return `${Math.min(100, Math.max(0, pct))}%`;
    },
    ribbonLabel() {
      return `${this.strip.title}: ${this.strip.headline}`;
    },
  },
};
</script>

<style scoped>
.strip { @apply bg-white rounded-card border border-light-gray p-6 mb-5; }
.strip-toggle { @apply flex-shrink-0 text-xs font-semibold text-horizon-500 border border-horizon-300 rounded-full px-3 py-1 hover:bg-eggshell-500 transition-colors; }
.ribbon { @apply mt-4; }
.ribbon-track { @apply relative h-2 rounded-full bg-violet-100; }
.ribbon-marker { @apply absolute top-1/2 w-3 h-3 -mt-1.5 -ml-1.5 rounded-full bg-raspberry-500 border-2 border-white; }
</style>
```

- [ ] **Step 4: Wire into the page**

`ActionsDashboard.vue`: import and register `ThresholdStrip from '@/components/Actions/ThresholdStrip.vue'`; add `thresholds: { strip: null, lines: [] },` to `data()`; in `load()` add `api.get('/thresholds')` as a third promise and set `this.thresholds = thresholdRes?.data?.data ?? { strip: null, lines: [] };` (give that call its own `.catch(() => null)` so an error there never hides the actions). In the template, directly above `<div class="top-priorities module-gradient">`: `<ThresholdStrip :data="thresholds" />`.

- [ ] **Step 5: Run**

Run: `npx vitest run resources/js/components/Actions/__tests__/ThresholdStrip.spec.js`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/Actions/ThresholdStrip.vue resources/js/components/Actions/__tests__/ThresholdStrip.spec.js resources/js/views/Actions/ActionsDashboard.vue
git commit -m "feat(web): the threshold strip above Your actions"
```

---

### Task 14: `/m` strip

**Files:**
- Create: `resources/mobile/components/ThresholdStrip.vue`
- Modify: `resources/mobile/views/Actions.vue:12` (template, above the Open section), `:78-86` (data), `:150-165` (`load`)
- Test: `resources/mobile/views/__tests__/ActionsThresholdStrip.spec.js`

**Interfaces:**
- Consumes: the same payload via `apiGet('/api/thresholds', store.token)`.
- Produces: headline, total, lever line and the other lines' titles and figures. No breakdown (CSJ: end figures on `/m`).

- [ ] **Step 1: Failing test**

```js
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({ apiGet: vi.fn(), apiPost: vi.fn(), apiStream: vi.fn() }));
vi.mock('../../navigation/webHandoff.js', () => ({ issueWebHandoff: vi.fn() }));

import { apiGet } from '../../api.js';
import { store } from '../../store.js';
import Actions from '../Actions.vue';

const line = { key: 'pa_taper', title: 'Personal Allowance taper', headline: 'You are £12,400 into the 60% band', body: 'The next £12,400 you earn costs 60p in the pound.', cost_total: 12620, lever: { title: 'Salary sacrifice £12,400 into your pension', recovers: 12620, downside: 'The money is locked until you are 57.', action: { route: '/tax-strategy' } }, position: { unit: 'gbp' } };

const stubs = { MobileChrome: { template: '<div><slot /></div>' }, 'router-link': { template: '<a><slot /></a>' } };

describe('/m actions threshold strip', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.user = { id: 7, onboarding_completed: true };
    apiGet.mockImplementation(async (url) => {
      if (url === '/api/recommendations/actions') return { ok: true, status: 200, data: { data: { open: [], completed: [] } } };
      if (url === '/api/thresholds') return { ok: true, status: 200, data: { data: { strip: 'pa_taper', lines: [line] } } };
      return { ok: false, status: 404, data: {} };
    });
  });

  it('shows the headline and total, and the lever after a tap', async () => {
    const w = mount(Actions, { global: { stubs } });
    await flushPromises();
    expect(w.text()).toContain('You are £12,400 into the 60% band');
    expect(w.text()).toContain('£12,620');
    expect(w.text()).not.toContain('Salary sacrifice £12,400');
    await w.get('.mt-toggle').trigger('click');
    expect(w.text()).toContain('Salary sacrifice £12,400 into your pension');
  });

  it('renders nothing when no line applies', async () => {
    apiGet.mockImplementation(async (url) => (url === '/api/thresholds'
      ? { ok: true, status: 200, data: { data: { strip: null, lines: [] } } }
      : { ok: true, status: 200, data: { data: { open: [], completed: [] } } }));
    const w = mount(Actions, { global: { stubs } });
    await flushPromises();
    expect(w.find('.mt-strip').exists()).toBe(false);
  });
});
```

- [ ] **Step 2: Run to verify failure**

Find the mobile Vitest script with `grep -n "vitest" package.json` and run it for this spec.
Expected: FAIL.

- [ ] **Step 3: Component**

```vue
<template>
  <section v-if="strip" class="m-card mt-strip" aria-labelledby="m-threshold-title">
    <h2 id="m-threshold-title" class="mt-title">{{ strip.headline }}</h2>
    <p class="mt-body">{{ strip.body }}</p>
    <div class="mt-row">
      <span class="mt-label">Costs you</span>
      <span class="mt-figure">{{ fmt(strip.cost_total) }} a year</span>
    </div>
    <button type="button" class="mt-toggle" @click="expanded = !expanded">{{ expanded ? 'Hide' : 'See what moves it' }}</button>
    <div v-if="expanded" class="mt-more">
      <p v-if="strip.lever" class="mt-lever">{{ strip.lever.title }}. You recover {{ fmt(strip.lever.recovers) }}.</p>
      <p v-if="strip.lever" class="mt-downside">{{ strip.lever.downside }}</p>
      <router-link v-if="strip.lever" :to="strip.lever.action.route" class="mt-link">Model this change</router-link>
      <ul v-if="others.length" class="mt-others">
        <li v-for="other in others" :key="other.key" class="mt-other">
          <span class="mt-other__title">{{ other.title }}</span>
          <span class="mt-other__meta">{{ other.headline }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>

<script>
/**
 * Rule 19 parity for the web ThresholdStrip: same payload, end figures only
 * (CSJ 2026-09-21). The full cost breakdown stays on web.
 */
export default {
  name: 'MobileThresholdStrip',
  props: { data: { type: Object, default: () => ({ strip: null, lines: [] }) } },
  data() {
    return { expanded: false };
  },
  computed: {
    strip() {
      return (this.data?.lines ?? []).find((l) => l.key === this.data?.strip) || null;
    },
    others() {
      return (this.data?.lines ?? []).filter((l) => l.key !== this.data?.strip);
    },
  },
  methods: {
    fmt(n) {
      return '£' + Math.round(Number(n) || 0).toLocaleString('en-GB');
    },
  },
};
</script>

<style scoped>
.mt-title { margin: 0; font-size: 16px; font-weight: 800; color: var(--horizon-500); }
.mt-body { margin: 6px 0 10px; font-size: 13px; line-height: 1.5; color: var(--neutral-600); }
.mt-row { display: flex; justify-content: space-between; align-items: baseline; padding: 10px 0; border-top: 1px solid var(--horizon-200); }
.mt-label { font-size: 12px; color: var(--neutral-600); }
.mt-figure { font-size: 15px; font-weight: 800; color: var(--horizon-500); }
.mt-toggle { width: 100%; min-height: 44px; margin-top: 6px; border: 1px solid var(--horizon-200); border-radius: 999px; background: var(--white); font-size: 13px; font-weight: 700; color: var(--horizon-500); }
.mt-more { margin-top: 12px; }
.mt-lever { margin: 0; font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.mt-downside { margin: 6px 0 0; font-size: 13px; line-height: 1.45; color: var(--violet-500); }
.mt-link { display: inline-block; margin-top: 8px; font-size: 13px; font-weight: 700; color: var(--raspberry-500); }
.mt-others { list-style: none; margin: 12px 0 0; padding: 0; }
.mt-other { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-top: 1px solid var(--horizon-200); }
.mt-other__title { font-size: 13px; font-weight: 700; color: var(--horizon-500); }
.mt-other__meta { font-size: 12px; color: var(--neutral-600); text-align: right; }
</style>
```

`--violet-500` exists in `resources/mobile/style.css` (line 32).

- [ ] **Step 4: Wire into `Actions.vue`**

Import `MobileThresholdStrip from '../components/ThresholdStrip.vue'`, register it, add `thresholds: { strip: null, lines: [] },` to `data()`, and in `load()` after the actions fetch succeeds:

```js
        const thresholds = await apiGet('/api/thresholds', store.token);
        if (thresholds.ok) this.thresholds = thresholds.data?.data || { strip: null, lines: [] };
```

Template: `<MobileThresholdStrip :data="thresholds" />` directly above the `Open` section.

- [ ] **Step 5: Run**

Run the mobile Vitest script for the new spec and the existing `resources/mobile/views/__tests__` folder.
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/mobile/components/ThresholdStrip.vue resources/mobile/views/Actions.vue resources/mobile/views/__tests__/ActionsThresholdStrip.spec.js
git commit -m "feat(m): the threshold strip on /m actions, end figures only"
```

---

### Task 15: Native card

**Files:**
- Create: `ios-native/Fynla/Features/Dashboard/ThresholdModels.swift`, `ThresholdClient.swift`, `ThresholdStripView.swift`
- Modify: `ios-native/Fynla/Features/Dashboard/DashboardModel.swift` (load thresholds beside the snapshot), `DashboardView.swift:81` (card above `FocusAreasView`), `ios-native/Fynla/App/FynlaApp.swift:470-480` (client wiring)
- Test: `ios-native/FynlaTests/ThresholdClientTests.swift`, fixture `ios-native/FynlaTests/Fixtures/Thresholds/taper.json`

**Interfaces:**
- Consumes: `GET api/thresholds`.
- Produces: `ThresholdPosition { strip: String?, lines: [ThresholdLine] }`, `ThresholdLine { key, title, headline, body, costTotal: Decimal, lever: ThresholdLever? }`, `ThresholdLever { title, recovers: Decimal, downside, route: String }`; `protocol ThresholdClient { func load() async throws -> ThresholdPosition }`.

- [ ] **Step 1: Fixture and failing test**

`Fixtures/Thresholds/taper.json`:

```json
{"success":true,"data":{"strip":"pa_taper","lines":[{"key":"pa_taper","title":"Personal Allowance taper","headline":"You are £12,400 into the 60% band","body":"The next £12,400 you earn costs 60p in the pound.","cost_total":12620,"position":{"value":112400,"distance":12400,"unit":"gbp","over":true},"lever":{"title":"Salary sacrifice £12,400 into your pension","amount":12400,"recovers":12620,"downside":"The money is locked until you are 57.","action":{"route":"/tax-strategy"}}}]}}
```

```swift
import Foundation
import Testing
@testable import Fynla

@Suite("Threshold client")
struct ThresholdClientTests {
    @Test
    func loadsTheStripFromTheSharedEndpoint() async throws {
        let body = try Data(contentsOf: URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent().appending(path: "Fixtures/Thresholds/taper.json"))
        let transport = TestHTTPTransport([.response(status: 200, body: body)])
        let client = APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0", build: "12", transport: transport,
            tokenProvider: DashboardTokenProvider(), requestID: { "threshold-request" }
        )

        let position = try await LiveThresholdClient(apiClient: client).load()

        #expect(position.strip == "pa_taper")
        #expect(position.lines.first?.headline == "You are £12,400 into the 60% band")
        #expect(position.lines.first?.costTotal == 12620)
        let request = try #require(await transport.requests().first)
        #expect(request.url?.path == "/fynla/api/thresholds")
    }
}
```

`DashboardTokenProvider` is the test helper `DashboardClientTests` already uses; reuse it.

- [ ] **Step 2: Build the tests to verify failure**

Load the `ios-simulator` skill first for the booted device, then: `xcodebuild test -project ios-native/Fynla.xcodeproj -scheme Fynla -destination 'platform=iOS Simulator,name=iPhone 11' -only-testing:FynlaTests/ThresholdClientTests`
Expected: build fails, types missing.

- [ ] **Step 3: Models and client**

```swift
import Foundation

struct ThresholdLever: Decodable, Sendable, Equatable {
    let title: String
    let recovers: Decimal
    let downside: String
    let route: String

    private enum CodingKeys: String, CodingKey { case title, recovers, downside, action }
    private enum ActionKeys: String, CodingKey { case route }

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        title = try c.decode(String.self, forKey: .title)
        recovers = try c.decode(Decimal.self, forKey: .recovers)
        downside = try c.decode(String.self, forKey: .downside)
        route = try c.nestedContainer(keyedBy: ActionKeys.self, forKey: .action).decode(String.self, forKey: .route)
    }
}

struct ThresholdLine: Decodable, Sendable, Equatable {
    let key: String
    let title: String
    let headline: String
    let body: String
    let costTotal: Decimal
    let lever: ThresholdLever?

    private enum CodingKeys: String, CodingKey {
        case key, title, headline, body, lever
        case costTotal = "cost_total"
    }
}

struct ThresholdPosition: Decodable, Sendable, Equatable {
    let strip: String?
    let lines: [ThresholdLine]

    var stripLine: ThresholdLine? { lines.first { $0.key == strip } }
}
```

```swift
import Foundation

protocol ThresholdClient: Sendable {
    func load() async throws -> ThresholdPosition
}

/// The same `/api/thresholds` payload web and /m read; native shows the end
/// figures only (CSJ 2026-09-21).
struct LiveThresholdClient: ThresholdClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) { self.apiClient = apiClient }

    func load() async throws -> ThresholdPosition {
        try await apiClient.send(APIRequest<ThresholdPosition>(path: "api/thresholds", method: .get))
    }
}
```

`APIRequest<Value>` decodes the `data` envelope the way `DashboardSnapshot` does (see `APIEnvelope.swift`); if it needs an explicit envelope type, follow `DashboardClient.load()` exactly.

- [ ] **Step 4: View, model, wiring**

```swift
import SwiftUI

struct ThresholdStripView: View {
    let line: ThresholdLine
    let onModel: (String) -> Void
    @State private var expanded = false

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(line.headline).font(.system(size: 16, weight: .bold)).foregroundStyle(FynlaColor.Token.horizon500.color)
            Text(line.body).font(.system(size: 13)).foregroundStyle(FynlaColor.Token.horizon500.color)
            HStack {
                Text("Costs you").font(.system(size: 12)).foregroundStyle(FynlaColor.Token.horizon500.color)
                Spacer()
                Text("\(FynlaFormatting.pounds(line.costTotal)) a year").font(.system(size: 15, weight: .bold)).foregroundStyle(FynlaColor.Token.horizon500.color)
            }
            Button(expanded ? "Hide" : "See what moves it") { expanded.toggle() }
                .font(.system(size: 13, weight: .bold)).frame(maxWidth: .infinity, minHeight: 44)
                .accessibilityIdentifier("dashboard.threshold-toggle")
            if expanded, let lever = line.lever {
                Text("\(lever.title). You recover \(FynlaFormatting.pounds(lever.recovers)).").font(.system(size: 14, weight: .bold))
                Text(lever.downside).font(.system(size: 13)).foregroundStyle(FynlaColor.Token.violet500.color)
                Button("Model this change") { onModel(lever.route) }.font(.system(size: 13, weight: .bold)).foregroundStyle(FynlaColor.Token.raspberry500.color)
            }
        }
        .padding(16)
        .background(FynlaColor.Token.white.color)
        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
        .overlay(RoundedRectangle(cornerRadius: 14, style: .continuous).stroke(FynlaColor.Token.horizon200.color, lineWidth: 1))
        .accessibilityIdentifier("dashboard.threshold-strip")
    }
}
```

Use whatever currency helper `Core/Formatting` exposes in place of `FynlaFormatting.pounds` (`grep -rn "func pounds\|static func currency" ios-native/Fynla/Core/Formatting`), and whatever token names `FynlaColor.Token` has for violet and white (`grep -n "case violet\|case white" ios-native/Fynla/Core/DesignSystem/FynlaColor.swift`).

`DashboardModel`: add `private(set) var thresholds: ThresholdPosition?` and a `thresholdClient: ThresholdClient` init parameter; in `load()` after the snapshot succeeds, `thresholds = try? await thresholdClient.load()` (best effort: a threshold failure never blanks the dashboard). `DashboardView`: above `FocusAreasView(`, `if let line = model.thresholds?.stripLine { ThresholdStripView(line: line, onModel: { onRoute(SemanticDestinationResolver.route(for: nil, legacyPath: $0)) }) }` using the same `onRoute` closure the focus areas use. `FynlaApp.swift:470-480`: pass `thresholdClient: LiveThresholdClient(apiClient: <the same apiClient>)` wherever `LiveDashboardClient(` is constructed. Update `DashboardModelTests` construction sites with a stub client returning `ThresholdPosition(strip: nil, lines: [])`.

- [ ] **Step 5: Run the native tests**

Run: `xcodebuild test -project ios-native/Fynla.xcodeproj -scheme Fynla -destination 'platform=iOS Simulator,name=iPhone 11' -only-testing:FynlaTests/ThresholdClientTests -only-testing:FynlaTests/DashboardModelTests -only-testing:FynlaTests/DashboardClientTests`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add ios-native
git commit -m "feat(ios): the threshold strip card on the native dashboard, end figures only"
```

---

### Task 16: Live verification on csjones, web and `/m`

**Files:** none. This task is the Rule 14 loop.

- [ ] **Step 1: Open the PR to dev and deploy the branch to csjones**

Follow `deploy/DEPLOY.md` and the `release` skill for csjones: `git pull` on the server, upload both `build.sh` outputs (web and m-build) built locally with `./deploy/csjones-fynla/build.sh`, then on the server run the migration, reseed `TaxConfigurationSeeder` and `TaxActionDefinitionSeeder` with `--force`, clear the route cache and re-cache config only. Never run the artisan optimise command or compile the route cache on this app (CLAUDE.md troubleshooting table).

- [ ] **Step 2: Persona A, over the line with children and a vest**

In Playwright on csjones: register a fresh account, complete onboarding with £112,400 employment income, monthly childcare £1,000, two children born 2023-03-01 and 2018-01-01, an RSU account with 800 unvested units at £30, quarterly, full vest 2028-03-15, and a personal pension with £18,000 drawdown income and £50,000 lump sum taken. Open `/actions`. Assert by reading the page: the strip headline, the ribbon marker, "See what this costs" opens the breakdown, Tax-Free Childcare and Funded childcare hours rows are present, the total equals `GET /api/thresholds`'s `cost_total` (read it with `page.request.get`), the lever names the March vest, "Model this change" lands on `/tax-strategy`. Then `/m/actions` via the `verify-m` skill's path: headline, total, toggle, lever, "Other lines" titles.

- [ ] **Step 3: Persona B, nothing applies**

A second fresh account at £48,000 with no children, no pension, no estate. `/actions` and `/m/actions` show no strip at all. Record both as screenshots in `tests/Persona/threshold-position/`.

- [ ] **Step 4: Tax strategy page**

On Persona A open `/tax-strategy` and confirm the combined annual saving no longer sums the two income-band strategies. Then add £12,000 of Gift Aid to Persona A's profile and confirm the additional-rate recommendation does not appear for income below the extended limit.

- [ ] **Step 5: Loop**

Any assertion that fails: diagnose with `superpowers:systematic-debugging`, fix, re-run the touched tests, redeploy, re-test from Step 2. Reports come after green. When green on both surfaces, write the evidence pack (screenshots, the API payloads, the test file list) into `tests/Persona/threshold-position/reports/2026-09-21.md` and hand the PR to CSJ. Do not merge and do not deploy to production: CSJ decides.
