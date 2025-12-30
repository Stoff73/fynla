# Per-Account Rebalancing Implementation Summary

**Date:** 30 December 2024
**Status:** Complete

## Overview

Added a "Rebalancing" tab to individual investment account detail views with user-configurable drift thresholds, CGT-aware trade recommendations, and visual allocation comparison.

## Files Created

### Backend
- `database/migrations/2025_12_30_110842_add_rebalance_threshold_to_investment_accounts.php`
  - Adds `rebalance_threshold_percent` column (default 10%)

### Frontend
- `resources/js/views/Investment/AccountRebalancingPanel.vue`
  - New component for rebalancing analysis display

## Files Modified

### Backend
- `app/Models/Investment/InvestmentAccount.php`
  - Added `rebalance_threshold_percent` to `$fillable`, `$casts`, `$attributes`

- `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php`
  - Added `getAccountRebalancing()` method
  - Added `updateRebalancingThreshold()` method
  - Added helper methods: `getTargetAllocationForRiskLevel()`, `getRiskLabel()`, `mapRiskStringToLevel()`, `convertAllocationToHoldingWeights()`

- `routes/api.php`
  - Added `GET /api/investment/accounts/{id}/rebalancing`
  - Added `PATCH /api/investment/accounts/{id}/rebalancing-threshold`

### Frontend
- `resources/js/services/rebalancingService.js`
  - Added `getAccountRebalancing(accountId)` method
  - Added `updateRebalancingThreshold(accountId, thresholdPercent)` method

- `resources/js/components/NetWorth/InvestmentDetailInline.vue`
  - Added "Rebalancing" tab to account detail view
  - Imported and registered `AccountRebalancingPanel` component

- `resources/js/components/NetWorth/InvestmentList.vue`
  - Removed portfolio-level Rebalancing tab (now per-account only)
  - Removed `RebalancingCalculator` import and registration

## Risk Level Mapping

Uses consistent platform labels:

| Level | Label | Equities | Bonds | Cash | Alternatives |
|-------|-------|----------|-------|------|--------------|
| 1 | Low | 10% | 70% | 20% | 0% |
| 2 | Lower-Medium | 30% | 55% | 10% | 5% |
| 3 | Medium | 50% | 40% | 5% | 5% |
| 4 | Upper-Medium | 75% | 20% | 0% | 5% |
| 5 | High | 90% | 5% | 0% | 5% |

## Risk Profile Logic

The system uses a hierarchical risk profile approach:

1. **User's Main Profile** - Stored in `risk_profiles` table
2. **Account Override** - Stored in `investment_accounts.risk_preference` when `has_custom_risk = true`
3. **Effective Risk** - Uses account override if set, otherwise user's main profile

String values (`low`, `lower_medium`, `medium`, `upper_medium`, `high`) are mapped to numeric levels 1-5.

## API Response Structure

```json
{
  "success": true,
  "data": {
    "account_id": 128,
    "account_type": "isa",
    "is_tax_free": true,
    "risk_profile": {
      "user_risk_level": 4,
      "user_risk_label": "Upper-Medium",
      "has_custom_risk": true,
      "account_risk_preference": "high",
      "effective_risk_level": 5,
      "effective_risk_label": "High"
    },
    "threshold_percent": 10,
    "current_allocation": { ... },
    "target_allocation": { "equities": 90, "bonds": 5, "cash": 0, "alternatives": 5 },
    "drift_analysis": {
      "drift_score": 12.5,
      "max_drift": 5.2,
      "needs_rebalancing": true,
      "urgency": { ... },
      "recommendation": "..."
    },
    "rebalancing_actions": [...],
    "cgt_analysis": { "total_gains": 1500, "allowance_used": 1500, "cgt_liability": 0 }
  }
}
```

## Features

- Default 10% drift threshold triggers rebalancing recommendation
- User-configurable threshold per account (1-50%)
- Shows user's main risk profile and account override (if any)
- CGT impact analysis for GIA accounts
- Tax-free notice for ISA/SIPP accounts
- Visual allocation comparison with target markers
- Colour-coded drift status (green/amber/red)
- Trade recommendations when rebalancing needed

## Tax Treatment

| Account Type | CGT Applicable | Display |
|--------------|----------------|---------|
| ISA | No | Green "Tax-Free Account" notice |
| SIPP | No | Green "Tax-Free Account" notice |
| LISA | No | Green "Tax-Free Account" notice |
| GIA | Yes | Full CGT analysis section |

## Testing

```bash
# Get auth token
curl -X POST "http://localhost:8000/api/preview/login/peak_earners" -H "Accept: application/json"

# Test rebalancing endpoint
curl -H "Authorization: Bearer TOKEN" "http://localhost:8000/api/investment/accounts/{id}/rebalancing"

# Update threshold
curl -X PATCH -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" \
  -d '{"threshold_percent": 15}' \
  "http://localhost:8000/api/investment/accounts/{id}/rebalancing-threshold"
```
