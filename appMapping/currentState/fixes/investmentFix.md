# Investment Module Fixes

**Date:** 21 February 2026
**Branch:** `investment-fixes`
**Scope:** All actionable known issues from Investment.md Section 17 (Simplifications S1-S5, Architecture Notes A3-A4)

---

## Scope Note

**Not addressed in this round (architectural constraints, not defects):**

| # | Issue | Reason |
|---|-------|--------|
| A1 | Polymorphic holdings shared between InvestmentAccount and DCPension | Intentional design; awareness note for future schema changes |
| A2 | Single-record joint ownership pattern | Working as designed; documented pattern from CLAUDE.md |
| A5 | Auto-calculations on store (EIS/SEIS, CSOP, SAYE dates) | Working as designed; no fix needed |
| - | InvestmentAccountResource conditional fields | Informational; describes current behaviour correctly |

---

## Issues Addressed

### Simplifications

| # | Priority | Issue | Fix |
|---|----------|-------|-----|
| S1 | MEDIUM | PortfolioAnalyzer YTD return is not time-weighted | Add date-filtered return calculations; separate YTD from total return |
| S2 | LOW | Asset allocation uses basic `asset_type` grouping, no look-through | Add simplified look-through mapping for funds/ETFs based on stored metadata |
| S3 | MEDIUM | Monte Carlo uses single-factor model, no asset class correlation | Introduce multi-asset simulation with Cholesky-decomposed correlated returns |
| S4 | LOW | Transaction cost hardcoded at 0.1% for all providers | Source from platform-specific lookup table; extend existing provider match block |
| S5 | MEDIUM | Dividend tax simplified; no band-splitting or PA taper | Implement proper UK dividend tax calculation with band stacking |

### Architecture Fixes

| # | Priority | Issue | Fix |
|---|----------|-------|-----|
| A3 | HIGH | ISA individual-only validation only on create; no DB constraint; update bypasses check | Add validation to `updateAccount()`; add data integrity check |
| A4 | MEDIUM | Goal CRUD and holding CRUD (joint owner) don't invalidate analysis cache | Add `clearCache()` calls to all missing CRUD operations |

---

## Changes By File

### New Files

| File | Purpose |
|------|---------|
| `app/Services/Investment/DividendTaxCalculator.php` | Proper UK dividend tax calculation with band stacking and PA taper |
| `config/investment_platforms.php` | Platform-specific transaction cost lookup |

### Modified Files

| File | Change |
|------|--------|
| `app/Services/Investment/PortfolioAnalyzer.php` | Date-filtered YTD/1-year returns; look-through allocation mapping for funds/ETFs |
| `app/Services/Investment/MonteCarloSimulator.php` | Multi-asset correlated simulation using Cholesky decomposition |
| `app/Services/Investment/FeeAnalyzer.php` | Replace hardcoded 0.1% with platform-specific transaction costs |
| `app/Services/Investment/TaxEfficiencyCalculator.php` | Delegate dividend tax to new `DividendTaxCalculator`; fix hardcoded 20% CGT rate |
| `app/Http/Controllers/Api/InvestmentController.php` | Add ISA ownership check to `updateAccount()`; add `clearCache()` to goal CRUD and toggle-retirement joint owner; add `clearCache()` for joint owner in holding CRUD |
| `app/Agents/InvestmentAgent.php` | No structural changes; existing `clearCache()` is sufficient once controller calls it |
| `app/Services/Investment/Utilities/MatrixOperations.php` | Add `choleskyDecomposition()` method for Monte Carlo correlation |

---

## Implementation Details

### S1. Time-Filtered Portfolio Returns

**Problem:** `PortfolioAnalyzer::calculateReturns()` (lines 24-51) sets `ytd_return` and `one_year_return` to the same value: simple total-return-since-inception `((currentValue - costBasis) / costBasis) * 100`. A holding bought in 2018 contributes its 7-year cumulative gain to the "YTD" figure. Both fields are identical, making neither meaningful.

**Fix:**

Replace the simplified calculation with date-filtered returns:

```php
public function calculateReturns(Collection $holdings): array
{
    $totalCostBasis = $holdings->sum('cost_basis');
    $totalCurrentValue = $holdings->sum('current_value');
    $totalGain = $totalCurrentValue - $totalCostBasis;
    $totalReturnPercent = $totalCostBasis > 0
        ? ($totalGain / $totalCostBasis) * 100
        : 0;

    // YTD: Holdings purchased this calendar year use cost_basis as start value;
    // holdings purchased earlier use their value at year start (approximated by
    // cost_basis + pro-rata share of gain at year start)
    $yearStart = Carbon::now()->startOfYear();
    $ytdReturn = $this->calculatePeriodReturn($holdings, $yearStart);

    // 1-year: Similar approach from 12 months ago
    $oneYearAgo = Carbon::now()->subYear();
    $oneYearReturn = $this->calculatePeriodReturn($holdings, $oneYearAgo);

    return [
        'total_cost_basis' => round($totalCostBasis, 2),
        'total_current_value' => round($totalCurrentValue, 2),
        'total_gain' => round($totalGain, 2),
        'total_return_percent' => round($totalReturnPercent, 2),
        'ytd_return' => round($ytdReturn, 2),
        'one_year_return' => round($oneYearReturn, 2),
    ];
}

private function calculatePeriodReturn(Collection $holdings, Carbon $periodStart): float
{
    $holdingsInPeriod = $holdings->filter(function ($holding) use ($periodStart) {
        return $holding->purchase_date && Carbon::parse($holding->purchase_date)->lte($periodStart);
    });

    if ($holdingsInPeriod->isEmpty()) {
        return 0;
    }

    // For holdings that existed before the period start, approximate start value
    // from cost_basis (conservative estimate)
    $startValue = $holdingsInPeriod->sum('cost_basis');
    $endValue = $holdingsInPeriod->sum('current_value');

    return $startValue > 0 ? (($endValue - $startValue) / $startValue) * 100 : 0;
}
```

**Limitation:** Without a historical price/valuation table, we approximate start-of-period value from cost basis for pre-existing holdings. This is more accurate than the current approach (which conflates all-time return with YTD) but still not a true TWR. A proper TWR would require a `portfolio_valuations` table recording daily/monthly snapshots, which is a future enhancement.

---

### S2. Fund/ETF Look-Through Allocation

**Problem:** `PortfolioAnalyzer::calculateAssetAllocation()` groups holdings by their `asset_type` column. A holding tagged as `fund` or `etf` is counted as 100% of that type, even if the underlying fund is a global equity/bond blend. This makes diversification analysis inaccurate for users who invest primarily through funds.

**Fix:**

Add a look-through mapping that distributes fund/ETF holdings across underlying asset classes based on stored metadata. Since we don't have live fund data feeds, use a combination of:

1. A new optional `underlying_allocation` JSON column on the `holdings` table (future migration), OR
2. A heuristic based on the fund's `security_name` and `asset_type` keywords

For now, implement option 2 as a pragmatic first step:

```php
public function calculateAssetAllocationWithLookThrough(Collection $holdings): array
{
    $totalValue = $holdings->sum('current_value');
    if ($totalValue == 0) {
        return [];
    }

    $allocation = [];

    foreach ($holdings as $holding) {
        $breakdown = $this->getAssetBreakdown($holding);

        foreach ($breakdown as $assetClass => $percentage) {
            $value = $holding->current_value * ($percentage / 100);
            $allocation[$assetClass] = ($allocation[$assetClass] ?? 0) + $value;
        }
    }

    return collect($allocation)->map(function ($value, $type) use ($totalValue) {
        return [
            'asset_type' => $type,
            'value' => round($value, 2),
            'percentage' => round(($value / $totalValue) * 100, 2),
        ];
    })->sortByDesc('value')->values()->toArray();
}

private function getAssetBreakdown(Holding $holding): array
{
    // Direct asset types pass through unchanged
    if (in_array($holding->asset_type, ['equity', 'bond', 'cash', 'commodity', 'property'])) {
        return [$holding->asset_type => 100];
    }

    // For funds/ETFs, use name-based heuristic
    $name = strtolower($holding->security_name ?? '');

    if (str_contains($name, 'global equity') || str_contains($name, 'world equity')) {
        return ['equity' => 100];
    }
    if (str_contains($name, 'bond') || str_contains($name, 'gilt') || str_contains($name, 'fixed income')) {
        return ['bond' => 100];
    }
    if (str_contains($name, 'balanced') || str_contains($name, 'multi-asset')) {
        return ['equity' => 60, 'bond' => 30, 'cash' => 10];
    }
    if (str_contains($name, 'property') || str_contains($name, 'reit')) {
        return ['property' => 100];
    }
    if (str_contains($name, 'money market') || str_contains($name, 'cash')) {
        return ['cash' => 100];
    }

    // Default: treat fund/etf as equity (most common)
    return ['equity' => 100];
}
```

The existing `calculateAssetAllocation()` method is preserved for backward compatibility and continues to be used where raw `asset_type` grouping is appropriate (e.g., holdings listing). The new method is used by `DiversificationAnalyzer` and the analysis pipeline where look-through provides better accuracy.

**Future enhancement:** Add an `underlying_allocation` JSON column to holdings, allowing users or advisers to store the actual fund breakdown. The look-through method would check this first, falling back to the heuristic.

---

### S3. Multi-Asset Monte Carlo with Correlation

**Problem:** `MonteCarloSimulator::runSimulation()` treats the entire portfolio as a single asset with one mean return and one volatility. In reality, a portfolio holding equities, bonds, and cash has different return distributions for each class, and crucially, these classes are correlated (equities and bonds often move inversely). The single-factor model overstates risk for well-diversified portfolios and understates it for concentrated ones.

**Fix:**

Add a `runMultiAssetSimulation()` method alongside the existing `runSimulation()` (which is preserved for backward compatibility and simple projections):

```php
public function runMultiAssetSimulation(
    array $assetClasses,       // [{type, weight, expectedReturn, volatility}]
    array $correlationMatrix,  // NxN correlation matrix
    float $startValue,
    float $monthlyContribution,
    int $years,
    int $iterations = 1000,
    ?string $cacheKey = null
): array {
    $n = count($assetClasses);
    $totalMonths = $years * 12;

    // Convert correlation matrix + volatilities to covariance matrix
    $covarianceMatrix = $this->buildCovarianceMatrix($assetClasses, $correlationMatrix);

    // Cholesky decomposition for correlated sampling
    $choleskyL = MatrixOperations::choleskyDecomposition($covarianceMatrix);

    $allResults = [];

    for ($i = 0; $i < $iterations; $i++) {
        $portfolioValue = $startValue;
        $yearlyValues = [];

        for ($month = 1; $month <= $totalMonths; $month++) {
            // Generate N independent standard normal samples
            $z = array_map(fn() => $this->standardNormal(), range(0, $n - 1));

            // Apply Cholesky factor to get correlated returns
            $correlatedZ = MatrixOperations::multiplyVector($choleskyL, $z);

            // Calculate weighted portfolio return for this month
            $monthlyReturn = 0;
            for ($j = 0; $j < $n; $j++) {
                $monthlyMean = $assetClasses[$j]['expectedReturn'] / 12;
                $monthlyVol = $assetClasses[$j]['volatility'] / sqrt(12);
                $assetReturn = $monthlyMean + $monthlyVol * $correlatedZ[$j];
                $monthlyReturn += $assetClasses[$j]['weight'] * $assetReturn;
            }

            $portfolioValue = $portfolioValue * (1 + $monthlyReturn) + $monthlyContribution;

            if ($month % 12 == 0) {
                $yearlyValues[] = $portfolioValue;
            }
        }

        $allResults[] = $yearlyValues;
    }

    return $this->aggregateResults($allResults, $startValue, $monthlyContribution, $years);
}
```

Add Cholesky decomposition to `MatrixOperations`:

```php
public static function choleskyDecomposition(array $matrix): array
{
    $n = count($matrix);
    $L = array_fill(0, $n, array_fill(0, $n, 0.0));

    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j <= $i; $j++) {
            $sum = 0;
            for ($k = 0; $k < $j; $k++) {
                $sum += $L[$i][$k] * $L[$j][$k];
            }
            if ($i === $j) {
                $L[$i][$j] = sqrt(max(0, $matrix[$i][$i] - $sum));
            } else {
                $L[$i][$j] = $L[$j][$j] > 0
                    ? ($matrix[$i][$j] - $sum) / $L[$j][$j]
                    : 0;
            }
        }
    }

    return $L;
}
```

**Default correlation assumptions** (sourced from `CorrelationMatrixCalculator` which already exists in the Analytics services):

| | Equity | Bond | Cash |
|---|---|---|---|
| Equity | 1.00 | -0.20 | 0.05 |
| Bond | -0.20 | 1.00 | 0.15 |
| Cash | 0.05 | 0.15 | 1.00 |

**Integration:** The `InvestmentProjectionService` would call `runMultiAssetSimulation()` when the user's portfolio has holdings across multiple asset classes, falling back to the existing `runSimulation()` for single-asset-class portfolios or when correlation data is unavailable.

---

### S4. Platform-Specific Transaction Costs

**Problem:** `FeeAnalyzer` hardcodes 0.1% transaction cost in two places (line 63 in `calculateTotalFees()` and line 530 in `estimateTransactionCosts()`). Real costs vary dramatically: Vanguard charges nothing for fund dealing, Hargreaves Lansdown charges £11.95 per equity trade, AJ Bell charges £9.95 per deal.

**Fix:**

Create a config file `config/investment_platforms.php` with platform-specific costs:

```php
return [
    'platforms' => [
        'vanguard' => [
            'transaction_cost_type' => 'fixed',
            'fund_dealing_cost' => 0,
            'equity_dealing_cost' => 7.50,
        ],
        'hargreaves_lansdown' => [
            'transaction_cost_type' => 'fixed',
            'fund_dealing_cost' => 0,
            'equity_dealing_cost' => 11.95,
        ],
        'aj_bell' => [
            'transaction_cost_type' => 'fixed',
            'fund_dealing_cost' => 1.50,
            'equity_dealing_cost' => 9.95,
        ],
        'interactive_investor' => [
            'transaction_cost_type' => 'fixed',
            'fund_dealing_cost' => 3.99,
            'equity_dealing_cost' => 3.99,
        ],
        'fidelity' => [
            'transaction_cost_type' => 'fixed',
            'fund_dealing_cost' => 0,
            'equity_dealing_cost' => 7.50,
        ],
        'charles_stanley_direct' => [
            'transaction_cost_type' => 'fixed',
            'fund_dealing_cost' => 0,
            'equity_dealing_cost' => 11.50,
        ],
    ],
    'default_transaction_cost_percent' => 0.001, // 0.1% fallback
];
```

Update `FeeAnalyzer`:

```php
private function estimateTransactionCosts(
    float $portfolioValue,
    float $turnoverRate,
    ?string $platform = null
): float {
    $annualTradedValue = $portfolioValue * $turnoverRate;

    if ($platform) {
        $platformKey = Str::snake(Str::lower($platform));
        $config = config("investment_platforms.platforms.{$platformKey}");

        if ($config && $config['transaction_cost_type'] === 'fixed') {
            // Estimate number of trades from turnover and average trade size
            $avgTradeSize = 2000; // reasonable assumption
            $estimatedTrades = max(1, $annualTradedValue / $avgTradeSize);
            $costPerTrade = $config['equity_dealing_cost'] ?? 0;
            return $estimatedTrades * $costPerTrade;
        }
    }

    // Fallback to percentage-based
    $costPercent = config('investment_platforms.default_transaction_cost_percent', 0.001);
    return $annualTradedValue * $costPercent;
}
```

The `platform` value is already available on the `InvestmentAccount` model and passed through the fee analysis pipeline. The existing `calculatePlatformFee()` method (lines 437-458) already has a match block for these same platforms, so this aligns with the existing pattern.

---

### S5. Proper UK Dividend Tax Calculation

**Problem:** `TaxEfficiencyCalculator::calculateDividendTax()` picks a single rate for all taxable dividends based on total income bracket. In reality, dividends sit on top of other income and may straddle multiple tax bands. The personal allowance taper (£100k-£125,140) is also ignored.

**Fix:**

Create a dedicated `DividendTaxCalculator` service:

```php
<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Services\Tax\TaxConfigService;

class DividendTaxCalculator
{
    public function __construct(private TaxConfigService $taxConfig) {}

    public function calculate(float $dividendIncome, float $nonDividendIncome): float
    {
        $dividendConfig = $this->taxConfig->getDividendTax();
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();

        $allowance = $dividendConfig['allowance'];
        $taxableDividends = max(0, $dividendIncome - $allowance);

        if ($taxableDividends <= 0) {
            return 0;
        }

        // Calculate effective personal allowance (tapered for income > £100k)
        $totalIncome = $nonDividendIncome + $dividendIncome;
        $personalAllowance = $this->calculateEffectivePersonalAllowance(
            $totalIncome,
            $incomeTaxConfig['personal_allowance'] ?? 12570
        );

        // Determine how much of each band is consumed by non-dividend income
        $basicRateLimit = $personalAllowance + ($incomeTaxConfig['basic_rate_limit'] ?? 37700);
        $higherRateLimit = $personalAllowance + ($incomeTaxConfig['higher_rate_limit'] ?? 125140);

        // Dividends sit on top of non-dividend income
        $bandStart = $nonDividendIncome;
        $remaining = $taxableDividends;
        $tax = 0;

        // Basic rate band
        if ($bandStart < $basicRateLimit && $remaining > 0) {
            $inBasic = min($remaining, $basicRateLimit - $bandStart);
            $tax += $inBasic * $dividendConfig['basic_rate'];
            $remaining -= $inBasic;
            $bandStart += $inBasic;
        }

        // Higher rate band
        if ($bandStart < $higherRateLimit && $remaining > 0) {
            $inHigher = min($remaining, $higherRateLimit - $bandStart);
            $tax += $inHigher * $dividendConfig['higher_rate'];
            $remaining -= $inHigher;
            $bandStart += $inHigher;
        }

        // Additional rate
        if ($remaining > 0) {
            $tax += $remaining * $dividendConfig['additional_rate'];
        }

        return round($tax, 2);
    }

    private function calculateEffectivePersonalAllowance(
        float $totalIncome,
        float $baseAllowance
    ): float {
        $taperThreshold = 100000;

        if ($totalIncome <= $taperThreshold) {
            return $baseAllowance;
        }

        $reduction = ($totalIncome - $taperThreshold) / 2;
        return max(0, $baseAllowance - $reduction);
    }
}
```

Update `TaxEfficiencyCalculator` to delegate:

```php
// Before
$dividendTax = $this->calculateDividendTax($dividendIncome, $totalIncome);

// After
$dividendTax = $this->dividendTaxCalculator->calculate($dividendIncome, $nonDividendIncome);
```

Also fix the hardcoded 20% CGT rate in `identifyHarvestingOpportunities()` (line ~148):

```php
// Before
'potential_tax_saving' => round($totalLosses * 0.20, 2),

// After
$cgtConfig = $this->taxConfig->getCapitalGainsTax();
$cgtRate = $cgtConfig['higher_rate'] ?? 0.20;
'potential_tax_saving' => round($totalLosses * $cgtRate, 2),
```

---

### A3. ISA Ownership Validation Gap

**Problem:** The ISA individual-only rule is enforced in `storeAccount()` (lines 276-281) but **not** in `updateAccount()` (lines 373-454). A user can create an ISA as `individual`, then change it to `joint` via the update endpoint. There is no database-level constraint either, so existing data could already violate this rule.

**Fix:**

**Step 1 - Add validation to `updateAccount()`:**

```php
// In updateAccount(), after $validated = $request->validated()
$account = InvestmentAccount::where('id', $id)
    ->where(function ($q) use ($userId) {
        $q->where('user_id', $userId);
    })->firstOrFail();

// Check if update would create invalid ISA ownership
$newType = $validated['account_type'] ?? $account->account_type;
$newOwnership = $validated['ownership_type'] ?? $account->ownership_type;

if ($newType === 'isa' && $newOwnership !== 'individual') {
    return response()->json([
        'success' => false,
        'message' => 'ISAs can only be individually owned. Joint or trust ownership is not permitted for ISAs under UK tax rules.',
    ], 422);
}
```

This covers two attack vectors:
- Changing `ownership_type` on an existing ISA to `joint`
- Changing `account_type` to `isa` on an existing `joint` account

**Step 2 - Data integrity check:**

Add a one-time artisan command to find and report any existing violations:

```php
// In a migration or tinker script
$violations = InvestmentAccount::where('account_type', 'isa')
    ->where('ownership_type', '!=', 'individual')
    ->get();

if ($violations->isNotEmpty()) {
    Log::warning('ISA ownership violations found', [
        'count' => $violations->count(),
        'ids' => $violations->pluck('id'),
    ]);
}
```

If violations exist in preview data, fix them in the `PreviewUserSeeder`. If violations exist in real user data, flag them for manual review rather than auto-correcting.

**Note on DB constraint:** A `CHECK` constraint (`account_type != 'isa' OR ownership_type = 'individual'`) would be ideal but MySQL 8.0 CHECK constraints have inconsistent enforcement across migrations. The PHP-level validation in both `store` and `update` provides equivalent protection.

---

### A4. Cache Invalidation Gaps

**Problem:** Two confirmed gaps where CRUD operations don't invalidate the analysis cache:

1. **Goal CRUD** (`storeGoal`, `updateGoal`, `destroyGoal`) - no cache clearing at all. Goals are embedded in the cached analysis, so changes are invisible until the cache expires.
2. **Holding CRUD for joint owner** - `storeHolding`, `updateHolding`, `destroyHolding` clear the primary owner's cache but not the joint owner's.
3. **Toggle retirement for joint owner** - clears primary owner only.

**Fix:**

**Goal CRUD (lines 682-729):**

```php
// storeGoal - add after successful creation
$this->investmentAgent->clearCache($userId);

// updateGoal - add after successful update
$this->investmentAgent->clearCache($userId);

// destroyGoal - add after successful deletion
$this->investmentAgent->clearCache($userId);
```

**Holding CRUD joint owner (lines 562, 635, 666):**

```php
// In storeHolding, updateHolding, destroyHolding - after clearing primary owner cache
$account = InvestmentAccount::find($holding->holdable_id);
if ($account && $account->joint_owner_id) {
    $this->investmentAgent->clearCache($account->joint_owner_id);
}
```

**Toggle retirement joint owner (line 478):**

```php
// After existing clearCache call
if ($account->joint_owner_id) {
    $this->investmentAgent->clearCache($account->joint_owner_id);
}
```

These are small additions (1-3 lines each) with no risk of side effects. The `clearCache()` method is idempotent - calling it when cache is already empty is harmless.

---

## Testing Requirements

| Fix | Test |
|-----|------|
| S1 YTD returns | Create holdings with different purchase dates; verify YTD only reflects current-year performance; verify 1-year return is distinct from total return |
| S2 Look-through | Create fund holding named "Global Equity Fund"; verify allocation shows as equity, not fund; verify direct equity holding unchanged |
| S3 Multi-asset MC | Run with 60/40 equity/bond split; verify result differs from single-factor with blended mean; verify negative correlation reduces portfolio volatility |
| S4 Transaction costs | Create account with platform "Vanguard"; verify fund dealing cost is £0; create with "Hargreaves Lansdown"; verify £11.95 per trade; verify unknown platform falls back to 0.1% |
| S5 Dividend tax | Test income straddling basic/higher band; verify tax is split across bands; test PA taper at £110k income; verify CGT harvesting uses TaxConfigService rate |
| A3 ISA validation | Attempt to update ISA to joint ownership; verify 422 rejection; attempt to change GIA to ISA when ownership is joint; verify 422 rejection |
| A4 Cache invalidation | Create goal; verify analysis cache is cleared; update holding on joint account; verify both owners' caches are cleared |

---

## Implementation Order

| Order | Fix | Reason |
|-------|-----|--------|
| 1 | A3 ISA validation gap | Highest priority; data integrity risk; smallest change (add 7 lines to `updateAccount()`) |
| 2 | A4 Cache invalidation gaps | High priority; user-facing stale data issue; small additions across 6 methods |
| 3 | S5 Dividend tax | Medium priority; correctness of tax calculations directly impacts user recommendations |
| 4 | S1 YTD returns | Medium priority; user-visible inaccuracy in portfolio performance display |
| 5 | S4 Transaction costs | Low-medium priority; fee analysis accuracy; config file + method refactor |
| 6 | S2 Look-through allocation | Low priority; improves diversification analysis accuracy; heuristic approach |
| 7 | S3 Multi-asset Monte Carlo | Lowest priority; most complex change; requires Cholesky decomposition + new simulation method; existing single-factor remains as fallback |
