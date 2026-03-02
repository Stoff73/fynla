# Savings Module Fixes

**Date:** 21 February 2026
**Branch:** `savings-fixes`
**Scope:** All known issues from Savings.md Section 17 except #8 (Open Banking)

---

## Scope Note

**Not addressed in this round:**

| # | Priority | Issue | Reason |
|---|----------|-------|--------|
| 8 | INFO | Open Banking integration shown as "Coming Soon" | Excluded per user request; future feature requiring external API integration |

---

## Issues Addressed

### From Section 17 (Known Issues and Limitations)

| # | Priority | Issue | Fix |
|---|----------|-------|-----|
| 1 | MEDIUM | Market benchmark rates in `RateComparator` hardcoded for 2024/25 | Move rates to a `savings_market_rates` DB table with seeder; `RateComparator` queries DB instead of returning static array |
| 2 | LOW | `SavingsGoal` legacy model coexists with newer Goals module `Goal` model | Deprecate `SavingsGoal` with clear annotations; add migration path to Goals module; retain read-only support |
| 3 | MEDIUM | `CashFlowCoordinator.calculateAvailableSurplus()` returns placeholder £1,000 | Implement real calculation using `ExpenditureProfile` income vs expenditure data |
| 4 | LOW | Account number encryption has no length/format validation | Add UK sort code + account number format validation before encryption |
| 5 | MEDIUM | ISA subscription tracking depends on manual entry | Auto-calculate ISA subscriptions from regular contributions projected across the remaining tax year |
| 6 | LOW | `ExpenditureProfile` fallback chain could yield inconsistent results | Standardise to a single resolution method with explicit source tracking and user notification |
| 7 | LOW | No soft deletes on `savings_accounts` or `savings_goals` | Add `SoftDeletes` trait and `deleted_at` migration to both models |

---

## Changes By File

### New Files

| File | Purpose |
|------|---------|
| `database/migrations/xxxx_create_savings_market_rates_table.php` | New table for market benchmark rates |
| `database/seeders/SavingsMarketRatesSeeder.php` | Seeds current UK market rates for 2025/26 |
| `app/Models/SavingsMarketRate.php` | Model for market rate benchmarks |
| `database/migrations/xxxx_add_soft_deletes_to_savings_tables.php` | Adds `deleted_at` to `savings_accounts` and `savings_goals` |
| `database/migrations/xxxx_add_expenditure_source_to_savings_analysis.php` | Tracks which expenditure source was used |

### Modified Files

| File | Change |
|------|--------|
| `app/Services/Savings/RateComparator.php` | Replace `getMarketBenchmarks()` static array with DB query via `SavingsMarketRate` model |
| `app/Models/SavingsGoal.php` | Add `@deprecated` annotation; add `SoftDeletes` trait |
| `app/Models/SavingsAccount.php` | Add `SoftDeletes` trait |
| `app/Services/Coordination/CashFlowCoordinator.php` | Implement real `calculateAvailableSurplus()` using income and expenditure data |
| `app/Services/Savings/ISATracker.php` | Add `calculateProjectedSubscription()` method for auto-calculation from regular contributions |
| `app/Agents/SavingsAgent.php` | Track expenditure source in analysis output; use ISA projection |
| `app/Http/Requests/StoreSavingsAccountRequest.php` | Add account number format validation rule |
| `app/Http/Requests/UpdateSavingsAccountRequest.php` | Add account number format validation rule |
| `resources/js/components/Savings/SaveAccountModal.vue` | Show projected ISA subscription from regular contributions; display expenditure source indicator |
| `resources/js/components/Savings/ISAAllowanceTracker.vue` | Show projected vs actual ISA usage |

---

## Implementation Details

### 1. Market Benchmark Rates to Database

**Problem:** `RateComparator::getMarketBenchmarks()` returns 10 hardcoded rates labelled "2024/25". As market rates change these become stale, producing inaccurate rate categories (Excellent/Good/Fair/Poor) for user accounts.

**Fix:**

Create a `savings_market_rates` table:

```php
Schema::create('savings_market_rates', function (Blueprint $table) {
    $table->id();
    $table->string('rate_key');           // e.g. 'easy_access', 'fixed_1_year_isa'
    $table->string('label');              // e.g. 'Easy Access ISA'
    $table->decimal('rate', 5, 4);        // e.g. 0.0450
    $table->string('tax_year');           // e.g. '2025/26'
    $table->date('effective_from');
    $table->timestamps();
    $table->unique(['rate_key', 'tax_year']);
});
```

Create a `SavingsMarketRate` model and a `SavingsMarketRatesSeeder` that seeds current 2025/26 UK rates. The seeder is designed to be re-run when rates change -- it uses `updateOrCreate` on the composite key.

Refactor `RateComparator`:

```php
// Before (hardcoded)
public function getMarketBenchmarks(): array
{
    return [
        'easy_access' => 0.0450,
        // ... 9 more static values
    ];
}

// After (database-driven)
public function getMarketBenchmarks(?string $taxYear = null): array
{
    $taxYear = $taxYear ?? $this->isaTracker->getCurrentTaxYear();

    return SavingsMarketRate::where('tax_year', $taxYear)
        ->pluck('rate', 'rate_key')
        ->toArray();
}
```

The rest of the `RateComparator` logic (`compareToMarketRates`, `getBenchmarkForAccount`, `calculateInterestDifference`) remains unchanged since it already consumes the benchmarks array by key.

**To update rates:** Run `php artisan db:seed --class=SavingsMarketRatesSeeder --force` with updated values. No code deploy needed.

---

### 2. SavingsGoal Legacy Model Deprecation

**Problem:** Two competing goal systems exist -- `SavingsGoal` (savings module legacy) and `Goal` (Goals module). This creates confusion about which to use and potential data inconsistency.

**Fix:** A phased deprecation rather than immediate removal, since `GoalProgressCalculator` and `SavingsGoals.vue` still reference the legacy model.

**Phase 1 (this round):**
- Add `@deprecated Use App\Models\Goal instead` PHPDoc annotation to `SavingsGoal` model
- Add a deprecation comment to each `SavingsGoal`-related endpoint in `SavingsController`
- Add `SoftDeletes` trait to `SavingsGoal` (part of issue #7)
- Add a banner in `SavingsGoals.vue` explaining that goals are managed in the Goals module, with a link to `/goals`

**Phase 2 (future):**
- Migrate existing `savings_goals` data to `goals` table
- Remove `SavingsGoal` model, controller endpoints, and frontend components
- Redirect savings goal routes to Goals module

The phase 1 changes make the deprecation visible to developers and gently guide users toward the Goals module without breaking existing functionality.

---

### 3. CashFlowCoordinator Real Surplus Calculation

**Problem:** `calculateAvailableSurplus()` always returns £1,000 regardless of user. This makes `optimizeContributionAllocation()` and surplus-based recommendations unreliable. The companion `createCashFlowChartData()` also has hardcoded £4,500 income and £3,200 expenses.

**Fix:** Implement real calculation using existing data sources. The system already has `ExpenditureProfile` for expenses. Income data is available from user employment/pension records.

```php
public function calculateAvailableSurplus(int $userId): float
{
    $user = User::find($userId);
    if (!$user) {
        return 0.0;
    }

    // Calculate monthly income from employment and other sources
    $monthlyIncome = $this->calculateMonthlyIncome($user);

    // Calculate monthly expenditure using the same fallback chain as SavingsAgent
    $monthlyExpenditure = $this->resolveMonthlyExpenditure($user);

    // Subtract existing committed contributions (pensions, protection premiums, etc.)
    $committedContributions = $this->calculateCommittedContributions($userId);

    $surplus = $monthlyIncome - $monthlyExpenditure - $committedContributions;

    return max(0.0, round($surplus, 2));
}
```

New private helper methods:

- `calculateMonthlyIncome(User $user)`: Sums gross employment income (from `EmploymentIncome` or `User.annual_income / 12`), rental income, and other income sources
- `resolveMonthlyExpenditure(User $user)`: Uses the same fallback chain as `SavingsAgent` (ExpenditureProfile -> User.monthly_expenditure -> User.annual_expenditure/12) but extracted to a shared trait or helper to avoid duplication
- `calculateCommittedContributions(int $userId)`: Sums regular pension contributions, protection premiums, and savings regular contributions already committed

Also fix `createCashFlowChartData()` to use the same real data instead of hardcoded values.

**Note:** If income data is absent for a user, the method returns `0.0` rather than a misleading placeholder.

---

### 4. Account Number Format Validation

**Problem:** The `account_number` field is encrypted via Laravel `Crypt` but accepts any string. Invalid or malformed data (e.g. "abc", empty spaces, SQL injection attempts) gets encrypted without detection.

**Fix:** Add a validation rule in both `StoreSavingsAccountRequest` and `UpdateSavingsAccountRequest`:

```php
'account_number' => [
    'nullable',
    'string',
    'max:20',
    function ($attribute, $value, $fail) {
        // Strip spaces and dashes for validation
        $cleaned = preg_replace('/[\s\-]/', '', $value);

        // UK account numbers: 8 digits
        // Allow international accounts with alphanumeric up to 20 chars
        if (!preg_match('/^[A-Za-z0-9]{4,20}$/', $cleaned)) {
            $fail('The account number format is invalid. UK accounts should be 8 digits.');
        }
    },
],
```

This allows:
- UK accounts: `12345678` or `1234 5678`
- International accounts: alphanumeric strings 4-20 characters
- Dashes and spaces are stripped before validation

The validation fires **before** encryption, so malformed data never reaches the `Crypt` layer. Existing encrypted data is unaffected since validation only applies on create/update.

---

### 5. ISA Subscription Auto-Calculation from Regular Contributions

**Problem:** Users must manually enter `isa_subscription_amount` even when they have `regular_contribution_amount` and `contribution_frequency` set. The system has the data to project ISA usage but doesn't use it.

**Fix:** Add a `calculateProjectedSubscription()` method to `ISATracker`:

```php
public function calculateProjectedSubscription(SavingsAccount $account): float
{
    if (!$account->is_isa || !$account->regular_contribution_amount) {
        return 0.0;
    }

    $taxYearStart = $this->getTaxYearStartDate();
    $taxYearEnd = $taxYearStart->copy()->addYear()->subDay();
    $now = Carbon::now();

    // Calculate contributions already made (tax year start to now)
    $monthsElapsed = $taxYearStart->diffInMonths($now);
    $monthsRemaining = $now->diffInMonths($taxYearEnd);

    $frequencyMultiplier = match ($account->contribution_frequency) {
        'monthly' => 1,
        'quarterly' => 1/3,
        'annually' => 1/12,
        default => 1,
    };

    $contributionsPerMonth = $account->regular_contribution_amount * $frequencyMultiplier;
    $totalProjected = $contributionsPerMonth * ($monthsElapsed + $monthsRemaining);

    // Add planned lump sum if within tax year
    if ($account->planned_lump_sum_amount
        && $account->planned_lump_sum_date
        && $account->planned_lump_sum_date->between($taxYearStart, $taxYearEnd)
    ) {
        $totalProjected += $account->planned_lump_sum_amount;
    }

    return round($totalProjected, 2);
}
```

In `getISAAllowanceStatus()`, use the projected subscription as a supplementary data point:

```php
$response['projected_usage'] = [
    'cash_isa_projected' => $projectedCashIsa,
    'total_projected' => $projectedTotal,
    'projected_remaining' => max(0, $totalAllowance - $projectedTotal),
];
```

On the frontend, `ISAAllowanceTracker.vue` shows both actual and projected usage:
- Solid bar segment = actual subscriptions recorded
- Hatched/striped bar segment = projected from regular contributions

`SaveAccountModal.vue` displays the projected subscription alongside the manual entry field as a helper: "Based on your regular contributions, your projected ISA subscription this year is £X,XXX."

The manual `isa_subscription_amount` field remains as the source of truth -- the projection is advisory only, giving users a starting point rather than replacing manual tracking.

---

### 6. Expenditure Source Standardisation

**Problem:** The fallback chain in `SavingsAgent::analyze()` silently picks whichever expenditure source has data, with no indication to the user which source was used. This can produce confusing results when `ExpenditureProfile` shows £3,200/month but `User.monthly_expenditure` shows £2,500.

**Fix:** Extract the resolution logic into a reusable method and track the source:

```php
// In a new trait: app/Traits/ResolvesExpenditure.php
trait ResolvesExpenditure
{
    protected function resolveMonthlyExpenditure(User $user): array
    {
        $expenditureProfile = ExpenditureProfile::where('user_id', $user->id)->first();

        if ($expenditureProfile && $expenditureProfile->total_monthly_expenditure > 0) {
            return [
                'amount' => (float) $expenditureProfile->total_monthly_expenditure,
                'source' => 'expenditure_profile',
                'label' => 'Cashflow Profile',
            ];
        }

        if ($user->monthly_expenditure > 0) {
            return [
                'amount' => (float) $user->monthly_expenditure,
                'source' => 'user_monthly',
                'label' => 'Profile (Monthly)',
            ];
        }

        if ($user->annual_expenditure > 0) {
            return [
                'amount' => (float) ($user->annual_expenditure / 12),
                'source' => 'user_annual',
                'label' => 'Profile (Annual / 12)',
            ];
        }

        return [
            'amount' => 0.0,
            'source' => 'none',
            'label' => 'Not Set',
        ];
    }
}
```

Apply this trait to both `SavingsAgent` and `CashFlowCoordinator` (issue #3) to eliminate duplicated resolution logic.

In the analysis response, include the source:

```php
'summary' => [
    'total_savings' => $totalSavings,
    'monthly_expenditure' => $resolved['amount'],
    'expenditure_source' => $resolved['source'],
    'expenditure_label' => $resolved['label'],
],
```

On the frontend, `EmergencyFund.vue` shows a subtle indicator: "Based on your Cashflow Profile" or "Based on your Profile (Annual / 12)" with a link to update the preferred source. If `source === 'none'`, show a prompt: "Add your monthly expenditure to see emergency fund analysis."

---

### 7. Soft Deletes for Savings Tables

**Problem:** Deleting a `SavingsAccount` or `SavingsGoal` is permanent with no recovery option. Given these are financial records, accidental deletion is high-impact.

**Fix:**

Migration:

```php
Schema::table('savings_accounts', function (Blueprint $table) {
    $table->softDeletes();
});

Schema::table('savings_goals', function (Blueprint $table) {
    $table->softDeletes();
});
```

Model changes:

```php
// SavingsAccount.php
use Illuminate\Database\Eloquent\SoftDeletes;

class SavingsAccount extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;
    // ...
}

// SavingsGoal.php
use Illuminate\Database\Eloquent\SoftDeletes;

class SavingsGoal extends Model
{
    use HasFactory, SoftDeletes;
    // ...
}
```

No controller changes needed -- Eloquent automatically excludes soft-deleted records from queries. The `destroyAccount()` and `destroyGoal()` controller methods will now soft-delete instead of hard-delete.

Cache invalidation in `SavingsController::destroyAccount()` already works correctly since it runs before the delete call.

**Consideration:** The ISA tracker's `getISAAllowanceStatus()` queries `savings_accounts` with `is_isa = true`. Soft-deleted ISA accounts will be automatically excluded, which is correct -- a deleted ISA should not count toward the allowance.

---

## Testing Requirements

| Fix | Test |
|-----|------|
| 1. Market rates | Seed rates, verify `RateComparator` returns DB values; verify comparison logic unchanged |
| 2. SavingsGoal deprecation | Verify existing goal CRUD still works; verify deprecation banner renders |
| 3. CashFlowCoordinator | Test with full income/expenditure data; test with partial data; test with no data (returns 0) |
| 4. Account number validation | Test valid UK (8 digits), valid international, invalid formats, empty/null |
| 5. ISA projection | Test monthly/quarterly/annually frequencies; test with lump sum; test cross-tax-year boundary |
| 6. Expenditure source | Test each tier of the fallback; verify source tracking in response; verify UI indicators |
| 7. Soft deletes | Delete account, verify excluded from queries; verify ISA allowance recalculates; verify `withTrashed()` can recover |

---

## Implementation Order

| Order | Fix | Reason |
|-------|-----|--------|
| 1 | #7 Soft deletes | Foundation change, no dependencies, quick win |
| 2 | #6 Expenditure source | Creates reusable trait needed by #3 |
| 3 | #3 CashFlowCoordinator | Uses expenditure trait from #6 |
| 4 | #1 Market rates | Independent, involves migration + seeder |
| 5 | #4 Account number validation | Independent, small change |
| 6 | #5 ISA projection | Depends on understanding current ISA flow |
| 7 | #2 SavingsGoal deprecation | Lowest priority, annotation-only in this phase |
