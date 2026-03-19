# Income Definitions & Adjusted Allowances Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add HMRC-aligned income definitions (Total Income, Net Income, Adjusted Net Income, Threshold Income, Adjusted Income) with automatic computation of tapered allowances, new user fields for charitable donations and blind status, and a frontend waterfall display.

**Architecture:** New `IncomeDefinitionsService` centralises all 5 HMRC income calculations using data from User model + DCPension model + TaxConfigService. Results served via API endpoint. Frontend `IncomeDefinitionsPanel.vue` displays the waterfall breakdown. New fields added to User model (`is_registered_blind`), ExpenditureForm (`charitable_donations`, `is_gift_aid`), and corresponding onboarding steps.

**Tech Stack:** Laravel 10, PHP 8.5, Vue.js 3 (Options API with `setup()`), Tailwind CSS, Pest testing

**Spec:** `March/March19Updates/10-income-definitions-design.md`

---

### Task 1: Database migration + User model updates

**Files:**
- Create: `database/migrations/2026_03_19_100000_add_income_definition_fields_to_users_table.php`
- Modify: `app/Models/User.php:69-94` (add casts)

- [ ] **Step 1: Create migration**

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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_registered_blind')) {
                $table->boolean('is_registered_blind')->default(false)->after('annual_trust_income');
            }
            if (! Schema::hasColumn('users', 'annual_charitable_donations')) {
                $table->decimal('annual_charitable_donations', 15, 2)->nullable()->after('is_registered_blind');
            }
            if (! Schema::hasColumn('users', 'is_gift_aid')) {
                $table->boolean('is_gift_aid')->default(false)->after('annual_charitable_donations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_registered_blind', 'annual_charitable_donations', 'is_gift_aid']);
        });
    }
};
```

- [ ] **Step 2: Add casts to User model**

In `app/Models/User.php`, add to the `$casts` array (after line ~94 `annual_trust_income`):
```php
'is_registered_blind' => 'boolean',
'annual_charitable_donations' => 'decimal:2',
'is_gift_aid' => 'boolean',
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_03_19_100000_add_income_definition_fields_to_users_table.php app/Models/User.php
git commit -m "feat: add is_registered_blind, annual_charitable_donations, is_gift_aid to users"
```

---

### Task 2: TaxConfig — add Blind Person's Allowance

**Files:**
- Modify: `database/seeders/TaxConfigurationSeeder.php:67-116` (income_tax section)
- Modify: `app/Services/TaxConfigService.php` (add helper method)

- [ ] **Step 1: Add BPA to seeder**

In `TaxConfigurationSeeder.php`, add inside the `income_tax` array (after the `starting_rate_for_savings` block, before the closing `],` of `income_tax`):

```php
// Blind Person's Allowance
'blind_persons_allowance' => 2870,
```

Do this for the 2025/26 tax year config. Also add to 2024/25 (`2760`) and earlier years as appropriate.

- [ ] **Step 2: Add helper to TaxConfigService**

Add after the existing `getPersonalSavingsAllowance()` method (~line 384):

```php
/**
 * Get the Blind Person's Allowance for the active tax year.
 */
public function getBlindPersonsAllowance(): float
{
    return (float) ($this->get('income_tax.blind_persons_allowance') ?? 2870);
}
```

- [ ] **Step 3: Reseed**

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/TaxConfigurationSeeder.php app/Services/TaxConfigService.php
git commit -m "feat: add Blind Person's Allowance to TaxConfig (£2,870 for 2025/26)"
```

---

### Task 3: IncomeDefinitionsService — core calculation

**Files:**
- Create: `app/Services/Tax/IncomeDefinitionsService.php`
- Create: `tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\DCPension;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\TaxConfigService;

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->taxConfig = app(TaxConfigService::class);
    $this->service = new IncomeDefinitionsService($this->taxConfig);
});

afterEach(function () {
    Mockery::close();
});

describe('Total Income', function () {
    it('sums all income sources', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'annual_self_employment_income' => 0,
            'annual_rental_income' => 12000,
            'annual_dividend_income' => 5000,
            'annual_interest_income' => 2000,
            'annual_other_income' => 1000,
            'annual_trust_income' => 0,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['total_income'])->toBe(80000.00);
    });

    it('handles zero income', function () {
        $user = User::factory()->create();
        $result = $this->service->calculate($user->id);
        expect($result['total_income'])->toBeGreaterThanOrEqual(0);
    });
});

describe('Net Income', function () {
    it('deducts pension relief from total income', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_salary' => 60000,
            'employee_contribution_percent' => 5.00,
            'employer_contribution_percent' => 3.00,
        ]);

        $result = $this->service->calculate($user->id);
        // Pension relief = 60000 * 5% = 3000
        expect($result['net_income'])->toBe(57000.00);
        expect($result['deductions']['pension_relief'])->toBe(3000.00);
    });

    it('deducts Gift Aid gross-up when is_gift_aid is true', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'annual_charitable_donations' => 1000,
            'is_gift_aid' => true,
        ]);

        $result = $this->service->calculate($user->id);
        // Gift Aid gross-up = 1000 * 1.25 = 1250
        expect($result['deductions']['gift_aid_gross'])->toBe(1250.00);
        expect($result['net_income'])->toBe(58750.00);
    });

    it('does not deduct Gift Aid when is_gift_aid is false', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'annual_charitable_donations' => 1000,
            'is_gift_aid' => false,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['deductions']['gift_aid_gross'])->toBe(0.00);
        expect($result['net_income'])->toBe(60000.00);
    });
});

describe('Adjusted Net Income', function () {
    it('deducts Blind Persons Allowance when registered blind', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'is_registered_blind' => true,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['deductions']['blind_persons_allowance'])->toBe(2870.00);
        expect($result['adjusted_net_income'])->toBe(57130.00);
    });

    it('does not deduct BPA when not registered blind', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'is_registered_blind' => false,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['deductions']['blind_persons_allowance'])->toBe(0.00);
        expect($result['adjusted_net_income'])->toBe(60000.00);
    });
});

describe('Threshold and Adjusted Income', function () {
    it('calculates threshold income by deducting employee pension contributions', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 250000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_salary' => 250000,
            'employee_contribution_percent' => 5.00,
            'employer_contribution_percent' => 10.00,
        ]);

        $result = $this->service->calculate($user->id);
        // Threshold = adjusted_net_income - employee contributions
        // ANI = 250000 - 12500 (pension relief) = 237500
        // Threshold = 237500 - 12500 = 225000
        expect($result['threshold_income'])->toBe(225000.00);
    });

    it('calculates adjusted income by adding employer contributions to threshold', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 250000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_salary' => 250000,
            'employee_contribution_percent' => 5.00,
            'employer_contribution_percent' => 10.00,
        ]);

        $result = $this->service->calculate($user->id);
        // Adjusted = threshold + employer contributions
        // Threshold = 225000, employer = 25000
        // Adjusted = 225000 + 25000 = 250000
        expect($result['adjusted_income'])->toBe(250000.00);
    });
});

describe('Adjusted Allowances', function () {
    it('tapers personal allowance when adjusted net income exceeds 100k', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 130000,
        ]);

        $result = $this->service->calculate($user->id);
        // PA reduction = (130000 - 100000) / 2 = 15000
        // Adjusted PA = max(0, 12570 - 15000) = 0
        expect($result['adjusted_allowances']['personal_allowance'])->toBe(0.00);
        expect($result['adjusted_allowances']['personal_allowance_tapered'])->toBeTrue();
    });

    it('keeps full personal allowance when income below 100k', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['adjusted_allowances']['personal_allowance'])->toBe(12570.00);
        expect($result['adjusted_allowances']['personal_allowance_tapered'])->toBeFalse();
    });

    it('tapers pension annual allowance when both thresholds exceeded', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 300000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_salary' => 300000,
            'employee_contribution_percent' => 2.00,
            'employer_contribution_percent' => 5.00,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['adjusted_allowances']['pension_aa_tapered'])->toBeTrue();
        expect($result['adjusted_allowances']['pension_annual_allowance'])->toBeLessThan(60000.00);
        expect($result['adjusted_allowances']['pension_annual_allowance'])->toBeGreaterThanOrEqual(10000.00);
    });

    it('keeps full pension AA when threshold income below 200k', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 80000,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['adjusted_allowances']['pension_annual_allowance'])->toBe(60000.00);
        expect($result['adjusted_allowances']['pension_aa_tapered'])->toBeFalse();
    });
});

describe('Components breakdown', function () {
    it('returns all income components', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 50000,
            'annual_rental_income' => 10000,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['components'])->toHaveKeys([
            'employment', 'self_employment', 'rental', 'dividend',
            'interest', 'other', 'trust',
        ]);
        expect($result['components']['employment'])->toBe(50000.00);
        expect($result['components']['rental'])->toBe(10000.00);
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/pest tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php
```
Expected: FAIL — class not found

- [ ] **Step 3: Create the service**

Create `app/Services/Tax/IncomeDefinitionsService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\DCPension;
use App\Models\User;
use App\Services\TaxConfigService;

class IncomeDefinitionsService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Calculate all 5 HMRC income definitions for a user.
     *
     * @return array{
     *   total_income: float,
     *   net_income: float,
     *   adjusted_net_income: float,
     *   threshold_income: float,
     *   adjusted_income: float,
     *   components: array,
     *   deductions: array,
     *   adjusted_allowances: array,
     * }
     */
    public function calculate(int $userId): array
    {
        $user = User::findOrFail($userId);
        $pensionContributions = $this->getPensionContributions($userId);

        // 1. Total Income — gross from all sources
        $components = $this->getIncomeComponents($user);
        $totalIncome = array_sum($components);

        // 2. Net Income — total minus pension relief and Gift Aid
        $pensionRelief = $pensionContributions['employee'];
        $giftAidGross = $this->calculateGiftAidGrossUp($user);
        $netIncome = $totalIncome - $pensionRelief - $giftAidGross;

        // 3. Adjusted Net Income — net income minus BPA
        $bpa = $user->is_registered_blind ? $this->taxConfig->getBlindPersonsAllowance() : 0.0;
        $adjustedNetIncome = $netIncome - $bpa;

        // 4. Threshold Income — ANI minus employee pension contributions
        $thresholdIncome = $adjustedNetIncome - $pensionContributions['employee'];

        // 5. Adjusted Income — threshold plus employer pension contributions
        $adjustedIncome = $thresholdIncome + $pensionContributions['employer'];

        // Ensure no negative values
        $totalIncome = max(0, $totalIncome);
        $netIncome = max(0, $netIncome);
        $adjustedNetIncome = max(0, $adjustedNetIncome);
        $thresholdIncome = max(0, $thresholdIncome);
        $adjustedIncome = max(0, $adjustedIncome);

        // Calculate adjusted allowances
        $adjustedAllowances = $this->calculateAdjustedAllowances($adjustedNetIncome, $thresholdIncome, $adjustedIncome);

        return [
            'total_income' => round($totalIncome, 2),
            'net_income' => round($netIncome, 2),
            'adjusted_net_income' => round($adjustedNetIncome, 2),
            'threshold_income' => round($thresholdIncome, 2),
            'adjusted_income' => round($adjustedIncome, 2),

            'components' => $components,

            'deductions' => [
                'pension_relief' => round($pensionRelief, 2),
                'gift_aid_gross' => round($giftAidGross, 2),
                'blind_persons_allowance' => round($bpa, 2),
                'employee_pension_contributions' => round($pensionContributions['employee'], 2),
                'employer_pension_contributions' => round($pensionContributions['employer'], 2),
            ],

            'adjusted_allowances' => $adjustedAllowances,
        ];
    }

    private function getIncomeComponents(User $user): array
    {
        return [
            'employment' => round((float) ($user->annual_employment_income ?? 0), 2),
            'self_employment' => round((float) ($user->annual_self_employment_income ?? 0), 2),
            'rental' => round((float) ($user->annual_rental_income ?? 0), 2),
            'dividend' => round((float) ($user->annual_dividend_income ?? 0), 2),
            'interest' => round((float) ($user->annual_interest_income ?? 0), 2),
            'other' => round((float) ($user->annual_other_income ?? 0), 2),
            'trust' => round((float) ($user->annual_trust_income ?? 0), 2),
        ];
    }

    private function getPensionContributions(int $userId): array
    {
        $pensions = DCPension::where('user_id', $userId)->get();

        $employee = 0.0;
        $employer = 0.0;

        foreach ($pensions as $pension) {
            $salary = (float) ($pension->current_salary ?? $pension->annual_salary ?? 0);
            $employee += $salary * ((float) ($pension->employee_contribution_percent ?? 0) / 100);
            $employer += $salary * ((float) ($pension->employer_contribution_percent ?? 0) / 100);
        }

        return [
            'employee' => round($employee, 2),
            'employer' => round($employer, 2),
        ];
    }

    private function calculateGiftAidGrossUp(User $user): float
    {
        if (! $user->is_gift_aid || ! $user->annual_charitable_donations) {
            return 0.0;
        }

        return round((float) $user->annual_charitable_donations * 1.25, 2);
    }

    private function calculateAdjustedAllowances(float $adjustedNetIncome, float $thresholdIncome, float $adjustedIncome): array
    {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        $fullPA = (float) ($incomeTax['personal_allowance'] ?? 12570);
        $paTaperThreshold = (float) ($incomeTax['personal_allowance_taper_threshold'] ?? 100000);

        $fullAA = (float) ($pensionConfig['annual_allowance'] ?? 60000);
        $taper = $pensionConfig['tapered_annual_allowance'] ?? [];
        $aaThresholdIncome = (float) ($taper['threshold_income'] ?? 200000);
        $aaAdjustedIncome = (float) ($taper['adjusted_income_threshold'] ?? $taper['adjusted_income'] ?? 260000);
        $aaMinimum = (float) ($taper['minimum_allowance'] ?? 10000);
        $aaTaperRate = (float) ($taper['taper_rate'] ?? 0.5);

        // Personal Allowance taper
        $adjustedPA = $fullPA;
        $paTapered = false;
        if ($adjustedNetIncome > $paTaperThreshold) {
            $excess = $adjustedNetIncome - $paTaperThreshold;
            $reduction = floor($excess / 2);
            $adjustedPA = max(0, $fullPA - $reduction);
            $paTapered = $adjustedPA < $fullPA;
        }

        // Pension Annual Allowance taper — both conditions must be met
        $adjustedAA = $fullAA;
        $aaTapered = false;
        if ($thresholdIncome > $aaThresholdIncome && $adjustedIncome > $aaAdjustedIncome) {
            $excess = $adjustedIncome - $aaAdjustedIncome;
            $reduction = floor($excess * $aaTaperRate);
            $adjustedAA = max($aaMinimum, $fullAA - $reduction);
            $aaTapered = $adjustedAA < $fullAA;
        }

        return [
            'personal_allowance' => round($adjustedPA, 2),
            'personal_allowance_full' => round($fullPA, 2),
            'personal_allowance_tapered' => $paTapered,
            'pension_annual_allowance' => round($adjustedAA, 2),
            'pension_annual_allowance_full' => round($fullAA, 2),
            'pension_aa_tapered' => $aaTapered,
        ];
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/pest tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php
```
Expected: All pass

- [ ] **Step 5: Commit**

```bash
git add app/Services/Tax/IncomeDefinitionsService.php tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php
git commit -m "feat: add IncomeDefinitionsService with HMRC income definitions and taper calculations"
```

---

### Task 4: API endpoint

**Files:**
- Create: `app/Http/Controllers/Api/IncomeDefinitionsController.php`
- Modify: `routes/api.php:972-975` (add route in `tax` prefix group)

- [ ] **Step 1: Create controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Tax\IncomeDefinitionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomeDefinitionsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly IncomeDefinitionsService $service
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            $data = $this->service->calculate($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Calculating income definitions');
        }
    }
}
```

- [ ] **Step 2: Add route**

In `routes/api.php`, inside the existing `tax` prefix group (~line 972-975), add:

```php
Route::get('/income-definitions', [IncomeDefinitionsController::class, 'show']);
```

And add the import at top of file:
```php
use App\Http\Controllers\Api\IncomeDefinitionsController;
```

- [ ] **Step 3: Verify route exists**

```bash
php artisan route:list --path=tax/income
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/IncomeDefinitionsController.php routes/api.php
git commit -m "feat: add GET /api/tax/income-definitions endpoint"
```

---

### Task 5: Update AnnualAllowanceChecker to use IncomeDefinitionsService

**Files:**
- Modify: `app/Services/Retirement/AnnualAllowanceChecker.php:260-280`

- [ ] **Step 1: Inject IncomeDefinitionsService and replace getUserIncome()**

Replace the `getUserIncome()` private method with a call to `IncomeDefinitionsService`. Inject it in the constructor. Use `threshold_income` and `adjusted_income` from the service result in the taper calculation instead of the raw income sum.

Read the full `AnnualAllowanceChecker.php` to understand where `getUserIncome()` is called, then replace those calls with the service. The taper logic that checks `$income > $thresholdIncome` and `$income > $adjustedIncomeThreshold` should use `$definitions['threshold_income']` and `$definitions['adjusted_income']` respectively.

- [ ] **Step 2: Run existing tests**

```bash
./vendor/bin/pest tests/Unit/Services/Retirement/
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/Retirement/AnnualAllowanceChecker.php
git commit -m "refactor: AnnualAllowanceChecker uses IncomeDefinitionsService for proper HMRC definitions"
```

---

### Task 6: Frontend — IncomeDefinitionsPanel.vue

**Files:**
- Create: `resources/js/components/UserProfile/IncomeDefinitionsPanel.vue`

- [ ] **Step 1: Create the component**

A Vue component that:
- Accepts `definitions` prop (the API response data)
- Displays the 5 HMRC definitions in a waterfall layout
- Shows adjusted allowances section
- Uses `currencyMixin` for formatting
- Each definition has an `(i)` tooltip with HMRC explanation
- Threshold status shown in green (under) or raspberry (over)
- Styling: `bg-white rounded-lg border border-light-gray shadow-sm p-6`

The component should show:
```
Your Income Definitions

Total Income                              £X
  [component breakdown inline]

Less pension relief                       -£X
Less Gift Aid (grossed up)                -£X
                                          ───
Net Income                                £X

Less Blind Person's Allowance             -£X
                                          ───
Adjusted Net Income                       £X

Less employee pension contributions       -£X
                                          ───
Threshold Income                          £X
  ✓/✗ status vs £200,000

Plus employer pension contributions       +£X
                                          ───
Adjusted Income                           £X
  ✓/✗ status vs £260,000

Your Allowances
Personal Allowance       £X    (full / reduced)
Pension Annual Allowance £X    (full / reduced)
```

- [ ] **Step 2: Verify it compiles**

Check dev server for errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/UserProfile/IncomeDefinitionsPanel.vue
git commit -m "feat: add IncomeDefinitionsPanel waterfall display component"
```

---

### Task 7: Mount IncomeDefinitionsPanel in the income/tax tab

**Files:**
- Modify: the parent view/component that contains `TaxIncomeCard.vue`
- Create: API service method in `resources/js/services/` if needed

- [ ] **Step 1: Add API call**

Find where `TaxIncomeCard` is mounted (likely `UserProfile.vue` or an income tab component). Add an API call to `/api/tax/income-definitions` and pass the result as a prop to `IncomeDefinitionsPanel`.

If using Vuex, add an action to the appropriate store module. If the tax tab fetches data directly, add to the component's `mounted()` or `onMounted()`.

- [ ] **Step 2: Mount the panel**

Add `<IncomeDefinitionsPanel :definitions="incomeDefinitions" />` below or alongside `TaxIncomeCard` in the income tab.

- [ ] **Step 3: Verify in browser**

Navigate to the income/tax tab and confirm the panel renders with real data.

- [ ] **Step 4: Commit**

```bash
git add resources/js/
git commit -m "feat: mount IncomeDefinitionsPanel in income/tax tab"
```

---

### Task 8: Onboarding — blind status on income steps

**Files:**
- Modify: `resources/js/components/Onboarding/steps/IncomeStep.vue`
- Modify: `resources/js/components/Onboarding/steps/SimpleIncomeStep.vue`
- Modify: `resources/js/components/UserProfile/IncomeOccupation.vue`

- [ ] **Step 1: Add checkbox to IncomeStep**

Add before the navigation buttons, inside the form content:
```html
<div class="border-t pt-4">
  <div class="flex items-center gap-3">
    <input
      id="is_registered_blind"
      v-model="formData.is_registered_blind"
      type="checkbox"
      class="h-4 w-4 rounded border-light-gray text-violet-500 focus:ring-violet-500"
    >
    <label for="is_registered_blind" class="text-body-sm text-horizon-500">
      I am registered blind or severely sight impaired
    </label>
  </div>
  <p class="mt-1 ml-7 text-body-sm text-neutral-500">
    This qualifies you for the Blind Person's Allowance, which reduces your taxable income
  </p>
</div>
```

Add `is_registered_blind: false` to `formData` and include in the save payload.

- [ ] **Step 2: Repeat for SimpleIncomeStep and IncomeOccupation**

Same checkbox pattern. Ensure the field is saved to the user profile via the API.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Onboarding/steps/IncomeStep.vue resources/js/components/Onboarding/steps/SimpleIncomeStep.vue resources/js/components/UserProfile/IncomeOccupation.vue
git commit -m "feat: add registered blind checkbox to income steps and profile"
```

---

### Task 9: Onboarding — charitable donations on expenditure steps

**Files:**
- Modify: `resources/js/components/Onboarding/steps/ExpenditureStep.vue`
- Modify: `resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue`
- Modify: `resources/js/components/UserProfile/ExpenditureForm.vue`

- [ ] **Step 1: Split "Gifts & Charity" in ExpenditureForm**

In `ExpenditureForm.vue`, find the `gifts_charity` field definition (~line 1372). Replace with two separate fields:

```js
{ key: 'gifts_presents', label: 'Gifts & Presents', placeholder: '30', hint: 'Birthday and Christmas gifts' },
{ key: 'charitable_donations', label: 'Charitable Donations', placeholder: '20', hint: 'Monthly charitable giving' },
```

After the charitable_donations input, add a Gift Aid toggle:
```html
<div v-if="formData.charitable_donations > 0" class="flex items-center gap-3 mt-2 ml-1">
  <input
    id="is_gift_aid"
    v-model="giftAidEnabled"
    type="checkbox"
    class="h-4 w-4 rounded border-light-gray text-violet-500 focus:ring-violet-500"
  >
  <label for="is_gift_aid" class="text-body-sm text-horizon-500">
    I Gift Aid my donations
  </label>
</div>
<p v-if="formData.charitable_donations > 0" class="mt-1 ml-7 text-body-sm text-neutral-500">
  Gift Aid lets charities claim 25p for every £1 you donate and extends your basic rate band
</p>
```

The `giftAidEnabled` value needs to be saved to the user's `is_gift_aid` field, and `charitable_donations` (monthly × 12) to `annual_charitable_donations`.

- [ ] **Step 2: Add to onboarding expenditure steps**

Add the same charitable donations field + Gift Aid toggle to `SimpleExpenditureStep.vue` and `ExpenditureStep.vue`. Include in the save payload.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/UserProfile/ExpenditureForm.vue resources/js/components/Onboarding/steps/ExpenditureStep.vue resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue
git commit -m "feat: add charitable donations field with Gift Aid toggle to expenditure forms"
```

---

### Task 10: Backend — handle new fields in user profile save

**Files:**
- Modify: `app/Http/Requests/UpdatePersonalInfoRequest.php` or relevant request class
- Modify: relevant controller that saves income/expenditure data

- [ ] **Step 1: Add validation rules**

Add to the appropriate request class:
```php
'is_registered_blind' => ['nullable', 'boolean'],
'annual_charitable_donations' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
'is_gift_aid' => ['nullable', 'boolean'],
```

- [ ] **Step 2: Ensure controller saves the fields**

The User model uses `$guarded` (not `$fillable`), so new fields are mass-assignable by default. Verify the controller's update method includes these fields in the save payload.

- [ ] **Step 3: Commit**

```bash
git add app/Http/
git commit -m "feat: add validation for blind status and charitable donations fields"
```

---

### Task 11: Seed preview personas with sample data

**Files:**
- Modify: `database/seeders/PreviewUserSeeder.php`
- Modify: persona JSON files if needed

- [ ] **Step 1: Add sample data to relevant personas**

For `peak_earners` persona (David Mitchell, high income): set `is_registered_blind: false`, `annual_charitable_donations: 2400` (£200/month), `is_gift_aid: true`.

For `retired_couple` persona: set `annual_charitable_donations: 1200` (£100/month), `is_gift_aid: true`.

Other personas: set defaults (`is_registered_blind: false`, `annual_charitable_donations: null`, `is_gift_aid: false`).

- [ ] **Step 2: Reseed**

```bash
php artisan db:seed
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/PreviewUserSeeder.php
git commit -m "feat: add charitable donations to preview personas for income definitions testing"
```

---

### Task 12: Final verification

- [ ] **Step 1: Run all tests**

```bash
./vendor/bin/pest tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php
./vendor/bin/pest tests/Unit/Services/Retirement/
./vendor/bin/pest tests/Feature/Dashboard/
```

- [ ] **Step 2: Browser test**

Log in as peak_earners persona and navigate to income/tax tab. Verify:
- Income Definitions panel renders with waterfall layout
- All 5 definitions show correct values
- Adjusted allowances show full (not tapered) for this persona
- Charitable donations (£2,400) and Gift Aid flag visible in deductions

- [ ] **Step 3: Test onboarding fields**

Register new user, go through onboarding. Verify:
- Blind checkbox appears on income step
- Charitable donations field appears on expenditure step
- Gift Aid toggle appears when amount > 0

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete income definitions with HMRC waterfall display and adjusted allowances"
```
