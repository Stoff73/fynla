# Per-Account Rebalancing Implementation Plan

> **Status**: COMPLETE - 30 December 2024
> **See also**: `Rebalancing_Implementation_Summary.md` for full details

## Summary
Add a "Rebalancing" tab to the individual investment account detail view with user-configurable drift thresholds, CGT-aware trade recommendations, and visual allocation comparison.

## Requirements
- Rebalancing per account (not portfolio-wide)
- Default 10% asset allocation drift triggers rebalancing recommendation
- User-configurable threshold per account (stored in database)
- Account for CGT if applicable (ISAs tax-free, GIAs taxable)
- Show trades needed to get account back to user's risk level

## Risk Level → Target Allocation Mapping

| Risk Level | Label | Equities | Bonds | Cash | Alternatives |
|------------|-------|----------|-------|------|--------------|
| 1 | Low | 10% | 70% | 20% | 0% |
| 2 | Lower-Medium | 30% | 55% | 10% | 5% |
| 3 | Medium | 50% | 40% | 5% | 5% |
| 4 | Upper-Medium | 75% | 20% | 0% | 5% |
| 5 | High | 90% | 5% | 0% | 5% |

## Files to Create/Modify

### 1. Database Migration (NEW)
**File:** `database/migrations/2025_12_30_XXXXXX_add_rebalance_threshold_to_investment_accounts.php`

```php
Schema::table('investment_accounts', function (Blueprint $table) {
    $table->decimal('rebalance_threshold_percent', 5, 2)->default(10.00)->after('has_custom_risk');
});
```

### 2. InvestmentAccount Model
**File:** `app/Models/Investment/InvestmentAccount.php`

- Add `rebalance_threshold_percent` to `$fillable`
- Add to `$casts` as float
- Add to `$attributes` with default 10.00

### 3. Backend API Endpoint (NEW)
**File:** `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php`

Add method: `getAccountRebalancing(Request $request, int $accountId)`

**Logic:**
1. Fetch account with holdings
2. Get risk_preference (or user's default)
3. Use RiskProfiler to get target allocation
4. Use DriftAnalyzer for drift analysis
5. If drift > threshold, use RebalancingCalculator for trades
6. For GIA accounts, apply TaxAwareRebalancer for CGT

**Route:** `GET /api/investment/accounts/{id}/rebalancing`

Add method: `updateRebalancingThreshold(Request $request, int $accountId)`

**Route:** `PATCH /api/investment/accounts/{id}/rebalancing-threshold`

### 4. Routes
**File:** `routes/api.php`

```php
Route::get('/accounts/{id}/rebalancing', [RebalancingCalculationController::class, 'getAccountRebalancing']);
Route::patch('/accounts/{id}/rebalancing-threshold', [RebalancingCalculationController::class, 'updateRebalancingThreshold']);
```

### 5. Frontend Service
**File:** `resources/js/services/rebalancingService.js`

Add methods:
- `getAccountRebalancing(accountId)` - GET account rebalancing analysis
- `updateRebalancingThreshold(accountId, thresholdPercent)` - PATCH threshold

### 6. Vue Component (NEW)
**File:** `resources/js/views/Investment/AccountRebalancingPanel.vue`

**Sections:**
1. **Settings** - Risk level display, threshold input (editable)
2. **Allocation Comparison** - Current vs Target bar chart
3. **Drift Status** - Drift score, max drift, needs rebalancing indicator
4. **Trade Recommendations** - Table of buy/sell actions (if drift > threshold)
5. **CGT Impact** - For GIA accounts only (total gains, allowance used, liability)
6. **Tax-Free Notice** - For ISA/SIPP accounts

**Props:** `account` (Object, required)

### 7. Integration
**File:** `resources/js/components/NetWorth/InvestmentDetailInline.vue`

1. Import AccountRebalancingPanel
2. Add to components
3. Add 'rebalancing' tab to tabs array (after 'performance', before 'fees')
4. Add template section for tab

## Tax Treatment Logic

| Account Type | CGT Applicable | Show CGT Section |
|--------------|----------------|------------------|
| ISA | No | Green "Tax-Free" notice |
| SIPP | No | Green "Tax-Free" notice |
| GIA | Yes | Full CGT analysis |

## Existing Services to Use (No Changes Needed)

| Service | Purpose |
|---------|---------|
| `RiskProfiler.php` | Get target allocation from risk level |
| `DriftAnalyzer.php` | Analyze current vs target drift |
| `RebalancingCalculator.php` | Calculate buy/sell actions |
| `TaxAwareRebalancer.php` | CGT optimization for taxable accounts |

## API Response Structure

```json
{
  "success": true,
  "data": {
    "account_id": 123,
    "account_type": "gia",
    "is_tax_free": false,
    "risk_level": 3,
    "risk_label": "Moderate",
    "threshold_percent": 10.0,
    "current_allocation": { "equities": 55.2, "bonds": 35.1, "cash": 4.7, "alternatives": 5.0 },
    "target_allocation": { "equities": 50, "bonds": 40, "cash": 5, "alternatives": 5 },
    "drift_analysis": { "drift_score": 12.5, "max_drift": 5.2, "needs_rebalancing": true },
    "rebalancing_actions": [...],
    "cgt_analysis": { "total_gains": 1500, "allowance_used": 1500, "cgt_liability": 0 }
  }
}
```

## Implementation Steps

1. Create migration, update model, run migration
2. Add API endpoints to controller and routes
3. Add methods to rebalancingService.js
4. Create AccountRebalancingPanel.vue component
5. Integrate into InvestmentDetailInline.vue
6. Test with ISA (no CGT) and GIA (with CGT) accounts
