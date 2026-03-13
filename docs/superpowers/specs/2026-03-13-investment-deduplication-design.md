# Investment Recommendations: Deduplication & Alignment

**Date:** 2026-03-13
**Status:** Approved (revised after spec review)
**Scope:** Stage 1 — Critical inconsistency fixes

## Problem

The investment recommendations system has 16 duplication/redundancy issues. The most critical are inconsistent constants that produce different results depending on which code path runs:

- Target allocations defined in 4 places with different values (35% vs 40% bonds at risk level 3)
- Asset class mappings defined in 5 places with inconsistent type coverage
- Fee thresholds defined in 2 places with different values (0.75% vs 0.80%) and different scales
- Identical fee calculation methods copy-pasted across services
- `fund` holdings blindly mapped to `equities` regardless of actual fund type

## Stage 1 Scope

Fix all inconsistencies that produce wrong results. Leave structural/architectural duplication for stage 2.

## Design

### 1. New File: `app/Constants/InvestmentDefaults.php`

Single source of truth for all investment constants. Follows the existing pattern of `TaxDefaults.php`, `EstateDefaults.php`, `ValidationLimits.php`.

**TARGET_ALLOCATIONS** — Risk levels 1-5, keyed by integer. Canonical values taken from the 3/4 majority (`ModelPortfolioBuilder`, `RebalancingCalculationController`, `AssetAllocationOptimizer`). `DiversificationAnalyzer` was the outlier and is corrected.

```
1 → equities: 10, bonds: 70, cash: 20, alternatives: 0
2 → equities: 30, bonds: 55, cash: 10, alternatives: 5
3 → equities: 50, bonds: 40, cash:  5, alternatives: 5
4 → equities: 75, bonds: 20, cash:  0, alternatives: 5
5 → equities: 90, bonds:  5, cash:  0, alternatives: 5
```

All keys use **plural form**: `equities`, `bonds`, `cash`, `alternatives`. This is the dominant convention (used by `DiversificationAnalyzer`, `ModelPortfolioBuilder`, `RebalancingCalculationController`). `AssetAllocationOptimizer` currently uses singular (`equity`, `bond`) and must be updated to use plural keys for consistency.

**RISK_LEVEL_MAP** — Maps legacy string keys to integer levels for backward compatibility with `AssetAllocationOptimizer`:

```php
public const RISK_LEVEL_MAP = [
    'low' => 1, 'cautious' => 1,
    'lower_medium' => 2, 'conservative' => 2,
    'medium' => 3, 'balanced' => 3,
    'upper_medium' => 4, 'growth' => 4,
    'high' => 5, 'adventurous' => 5, 'aggressive' => 5,
];
```

**ASSET_CLASS_MAP** — Maps raw `asset_type` strings to 4 standard classes. This is the union of ALL types currently handled across all 5 mapping implementations:

```
Equities: uk_equity, us_equity, international_equity, global_equity, emerging_markets,
          equity, stock, etf
Bonds:    bond, fixed_income, gilt, uk_bonds, global_bonds, government_bonds, corporate_bonds
Cash:     cash, money_market
Alternatives: alternative, property, real_estate, reit, commodities
Fund:     fund → mixed (new default — was incorrectly mapped to equities)
```

**FUND_SUB_TYPES** — Maps `sub_type` values to asset classes:

```
equity_fund → equities
bond_fund → bonds
mixed_fund → mixed
income_fund → bonds
index_fund → equities
money_market_fund → cash
property_fund → alternatives
```

**Static method: `resolveAssetClass(string $assetType, ?string $subType = null): string`**

Resolution order:
1. If `sub_type` is set → look up in `FUND_SUB_TYPES`
2. If `asset_type` is `fund` and no `sub_type` → return `mixed`
3. Otherwise → look up `asset_type` in `ASSET_CLASS_MAP`
4. Fallback → `equities`

**Fee threshold constants — dual scale:**

`FeeAnalyzer` compares against percentage values (e.g. `0.8` means 0.8%), while `OCFImpactCalculator` compares against decimal values (e.g. `0.0075` means 0.75%). To avoid conversion errors, provide both scales:

```php
// Canonical threshold: 0.75%
public const HIGH_OCF_THRESHOLD_DECIMAL = 0.0075;    // For OCFImpactCalculator (decimal scale)
public const HIGH_OCF_THRESHOLD_PERCENT = 0.75;      // For FeeAnalyzer (percentage scale)

// This is a deliberate unification: FeeAnalyzer was 0.80%, OCFImpactCalculator was 0.75%.
// 0.75% is adopted as canonical — a fund charging ≥0.75% OCF is considered high.

public const HIGH_TOTAL_FEE_PERCENT = 1.0;            // 1.0% total fees (percentage scale)
public const HIGH_PLATFORM_FEE_PERCENT = 0.8;          // 0.8% platform fee (percentage scale)
```

**Default OCF estimates** (used by `estimateOCF()` in the `CalculatesOCF` trait):

```php
public const DEFAULT_OCF_ESTIMATES = [
    'index_fund' => 0.001,   // 0.10%
    'etf' => 0.001,          // 0.10%
    'active_fund' => 0.0075, // 0.75%
    'equity' => 0.0,         // Direct holding
    'stock' => 0.0,          // Direct holding
    'bond' => 0.0005,        // 0.05%
    'alternative' => 0.015,  // 1.50%
    'default' => 0.005,      // 0.50%
];
```

### 2. New File: Migration — Add `sub_type` to `holdings`

- Nullable string column, no default
- Existing holdings keep `null` — legacy `fund` holdings map to `mixed` until users specify
- No data migration needed

### 3. Model Update: `app/Models/Investment/Holding.php`

- Add `sub_type` to `$fillable`

### 4. Request Validation Updates

**`app/Http/Requests/Investment/StoreHoldingRequest.php`:**
- Add `sub_type` as nullable string field
- Add validation: `nullable|string|in:equity_fund,bond_fund,mixed_fund,income_fund,index_fund,money_market_fund,property_fund`
- Add rule: `sub_type` required when `asset_type` is `fund`

**`app/Http/Requests/Investment/UpdateHoldingRequest.php`:**
- Same changes as `StoreHoldingRequest`

### 5. Controller Update: `app/Http/Controllers/Api/InvestmentController.php`

- `storeHolding()` and `updateHolding()` methods already use `$request->validated()` — `sub_type` will pass through automatically once added to the form request. No controller code changes needed.

### 6. Frontend Updates

**`resources/js/components/Investment/HoldingForm.vue`:**
- Add `sub_type` to `formData` in `data()`
- Add `sub_type` to `resetForm()`
- Handle `sub_type` in the `holding` watcher for edit mode
- When `asset_type === 'fund'`, show secondary dropdown for sub-type
- Options: Equity Fund, Bond Fund, Mixed Fund, Income Fund, Index Fund, Money Market Fund, Property Fund
- Clear `sub_type` when `asset_type` changes away from `fund`

**`resources/js/services/investmentService.js`** and **`resources/js/store/modules/investment.js`:**
- No changes needed — both pass through form data as-is

### 7. New File: `app/Traits/CalculatesOCF.php`

Extracts three methods from `FeeAnalyzer` and `OCFImpactCalculator`:

- `calculateWeightedOCF(Collection $holdings, float $totalValue): float`
- `estimateOCF(string $assetType): float` — uses `InvestmentDefaults::DEFAULT_OCF_ESTIMATES`
- `calculateCompoundSavings(float $portfolioValue, float $annualSavings, int $years, float $returnRate): float`

For `calculateCompoundSavings()`, both existing implementations accept `$annualSavings` as a currency value (pounds), not a fee rate. They internally convert to a percentage by dividing by portfolio value. The two implementations differ in guard clauses (`== 0` vs `<= 0`) and in an intermediate `* 100 / 100` round-trip in `FeeAnalyzer` that is mathematically redundant. The trait adopts the cleaner `OCFImpactCalculator` formula (no round-trip) with the stricter guard (`$annualSavings <= 0 || $portfolioValue == 0`).

Both `FeeAnalyzer` and `OCFImpactCalculator` use the trait, delete their private copies.

`FundSelector` keeps its own `calculateWeightedOCF()` — operates on a different data shape (array of fund candidates vs Collection of Holding models).

### 8. Consumer Updates

**Target allocations — 5 files import `InvestmentDefaults::TARGET_ALLOCATIONS`:**

| File | Change |
|------|--------|
| `DiversificationAnalyzer.php` | Delete `TARGET_ALLOCATIONS` constant, import from `InvestmentDefaults` |
| `AssetAllocationOptimizer.php` | Delete `getAllocationForLevel()` body, delegate to `InvestmentDefaults`. Use `RISK_LEVEL_MAP` for string→integer conversion. Change output keys from singular (`equity`, `bond`) to plural (`equities`, `bonds`) |
| `ModelPortfolioBuilder.php` | Delete hardcoded allocations from per-portfolio methods, import from `InvestmentDefaults` |
| `RebalancingCalculationController.php` | Delete `getTargetAllocationForRiskLevel()` private method, use `InvestmentDefaults::TARGET_ALLOCATIONS` |
| `PortfolioStrategyService.php` | Replace hardcoded fallback allocation (`60/30/5/5`) with `InvestmentDefaults::TARGET_ALLOCATIONS[3]` (medium risk default) |

**Asset class mappings — 5 files use `InvestmentDefaults::resolveAssetClass()`:**

| File | Change |
|------|--------|
| `DiversificationAnalyzer.php` | Delete `ASSET_CLASS_MAP`, call `resolveAssetClass($holding->asset_type, $holding->sub_type)` |
| `DriftAnalyzer.php` | Delete `normalizeAssetClass()`, use `resolveAssetClass()` |
| `ModelPortfolioBuilder.php` | Delete `mapAssetClass()`, use `resolveAssetClass()` |
| `PortfolioAnalyzer.php` | Update `getAssetBreakdown()` (line 179) and `calculateAssetAllocationWithLookThrough()` to use `resolveAssetClass()`. Remove the name-based heuristic mapping. |
| `DriftAnalyzer.php` default fallback | Currently returns `$assetType` as-is when unrecognised. After change, `resolveAssetClass()` returns `equities` as fallback — same practical effect since unrecognised types were being treated as equities downstream |

**Fee thresholds — 2 files import constants:**

| File | Change |
|------|--------|
| `FeeAnalyzer.php:162` | Replace `0.8` with `InvestmentDefaults::HIGH_OCF_THRESHOLD_PERCENT`. This changes the threshold from 0.80% to 0.75% (intentional unification). |
| `OCFImpactCalculator.php:271` | Replace `0.0075` with `InvestmentDefaults::HIGH_OCF_THRESHOLD_DECIMAL` (no value change) |

**Fee method extraction — 2 files use `CalculatesOCF` trait:**

| File | Change |
|------|--------|
| `FeeAnalyzer.php` | Use `CalculatesOCF` trait, delete private `calculateWeightedOCF()`, `estimateOCF()`, `calculateCompoundSavings()` |
| `OCFImpactCalculator.php` | Use `CalculatesOCF` trait, delete private `calculateWeightedOCF()`, `estimateOCF()`, `calculateCompoundSavings()` |

### 9. Key name migration: `AssetAllocationOptimizer`

`AssetAllocationOptimizer` currently returns arrays with singular keys (`equity`, `bond`, `cash`). After this change, it will return plural keys (`equities`, `bonds`, `cash`, `alternatives`).

**Downstream impact check:** Any code consuming `AssetAllocationOptimizer` output must be checked for `$result['equity']` vs `$result['equities']` references. This includes:
- `InvestmentAgent.php` — calls `calculateDeviation()` and `generateRebalancingTrades()`
- `InvestmentPlanService.php` — may reference allocation keys
- `RebalancingStrategyService.php` — accepts `$targetAllocation` as parameter, processes `equities`/`bonds` keys. If fed from `AssetAllocationOptimizer` output, the key rename affects it.
- Any Vue components receiving allocation data via API

**Legacy allocation values:** `AssetAllocationOptimizer` currently has separate allocations for legacy string keys (`cautious` = 20/60/20, `balanced` = 60/30/10, `adventurous` = 80/15/5) that differ from their mapped risk level equivalents (level 1 = 10/70/20, level 3 = 50/40/5/5, level 5 = 90/5/0/5). The `RISK_LEVEL_MAP` will cause these legacy keys to resolve to the canonical risk level values, changing allocations for any users stored with legacy string keys. This is an intentional normalisation — legacy string keys should have been mapped to their numeric equivalents. The old distinct values for `cautious`/`balanced`/`adventurous` are retired.

## Risk

1. The `fund` → `mixed` default change means existing portfolios with generic `fund` holdings will see their asset allocation shift. Diversification scores and rebalancing recommendations may change. This is more accurate but users may notice different numbers.

2. The fee threshold unification (0.80% → 0.75%) means `FeeAnalyzer` will flag slightly more holdings as high-fee. This is a minor tightening.

3. The `equity` → `equities` key rename in `AssetAllocationOptimizer` output is a breaking change for any consumers accessing keys by name. Must verify all downstream references including `RebalancingStrategyService`.

4. Legacy allocation values for `cautious`/`balanced`/`adventurous` string keys will change to match their canonical risk level equivalents. Users stored with these legacy keys will see different target allocations.

## Testing

- Unit tests for `InvestmentDefaults::resolveAssetClass()` covering all branches (direct type, sub_type override, fund without sub_type, unknown type fallback)
- Unit tests for `CalculatesOCF` trait methods
- Update any existing tests that hardcode target allocations or asset class mappings to use `InvestmentDefaults` constants
- Regression tests verifying `fund → mixed` change does not break existing calculations unexpectedly

## Files Summary

| Type | Count |
|------|-------|
| New files | 3 (`InvestmentDefaults.php`, `CalculatesOCF.php`, migration) |
| Modified files | 18 |
| Documentation | 1 (stage 2 backlog) |

**Modified files list:**
1. `app/Services/Investment/DiversificationAnalyzer.php`
2. `app/Services/Investment/AssetAllocationOptimizer.php`
3. `app/Services/Investment/ModelPortfolio/ModelPortfolioBuilder.php`
4. `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php`
5. `app/Services/Investment/Rebalancing/DriftAnalyzer.php`
6. `app/Services/Investment/PortfolioAnalyzer.php`
7. `app/Services/Investment/FeeAnalyzer.php`
8. `app/Services/Investment/Fees/OCFImpactCalculator.php`
9. `app/Models/Investment/Holding.php`
10. `app/Http/Requests/Investment/StoreHoldingRequest.php`
11. `app/Http/Requests/Investment/UpdateHoldingRequest.php`
12. `app/Http/Controllers/Api/InvestmentController.php` (verify only — may not need changes)
13. `resources/js/components/Investment/HoldingForm.vue`
14. `app/Agents/InvestmentAgent.php` (verify key name change impact)
15. `app/Services/Plans/InvestmentPlanService.php` (verify key name change impact)
16. `app/Services/Investment/PortfolioStrategyService.php` (replace hardcoded fallback allocation)
17. `app/Services/Investment/Rebalancing/RebalancingStrategyService.php` (verify key name change impact)
18. `database/seeders/InvestmentActionDefinitionSeeder.php` (use `InvestmentDefaults` constants at seed time for threshold values)

## Stage 2 (Documented Separately)

See `March/March14Updates/investment-stage2-backlog.md` for 10 remaining issues covering algorithmic duplication, shell components, missing mixin usage, and architectural decisions.
