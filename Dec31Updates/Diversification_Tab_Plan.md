# Diversification Tab Implementation Plan

**Date:** 2025-12-31
**Status:** Planning

## Overview

Add a comprehensive Diversification Tab to both Investment Account detail views and DC Pension detail views. The tab will calculate and display diversification metrics, compare current allocation against target allocation based on risk profile, and highlight areas for improvement.

## Research Summary (from diverse.md)

### Key Metrics to Implement

1. **Herfindahl-Hirschman Index (HHI)**
   - Sum of squared allocation percentages
   - Range: 0 (highly diversified) to 1 (single asset)
   - Formula: `HHI = Σ(weight_i)²`

2. **Concentration Analysis**
   - Identify holdings exceeding 5% threshold
   - Flag any single position >10% as high concentration
   - Calculate top 3 holdings percentage

3. **Asset Class Diversification**
   - Number of distinct asset classes
   - Spread across equity, bond, cash, alternative, property

4. **Diversification Score (existing)**
   - Already implemented in PortfolioAnalyzer.php
   - Penalty-based 0-100 scale
   - Will be enhanced and displayed per-account

## Current Architecture

### Existing Services
- `app/Services/Investment/PortfolioAnalyzer.php` - Core diversification calculations
- `app/Services/Investment/AssetAllocationOptimizer.php` - Target allocation mapping
- `app/Services/Retirement/PensionPortfolioAnalyzer.php` - DC pension analysis

### Risk Profile → Target Allocation Mapping
| Risk Level | Equity | Bond | Cash | Alternative |
|------------|--------|------|------|-------------|
| 1 (Very Conservative) | 10% | 70% | 20% | 0% |
| 2 (Conservative) | 30% | 55% | 15% | 5% |
| 3 (Moderate) | 50% | 40% | 10% | 5% |
| 4 (Growth) | 75% | 20% | 5% | 5% |
| 5 (Aggressive) | 90% | 5% | 5% | 5% |

### Asset Types in System
- uk_equity, us_equity, international_equity
- fund, etf, bond, cash, alternative, property

### Asset Type → Asset Class Grouping
```
Equities: uk_equity, us_equity, international_equity
Bonds: bond
Cash: cash
Alternatives: alternative, property
Funds/ETFs: fund, etf (need to look through to underlying)
```

## Implementation Plan

### Phase 1: Backend Enhancement

#### 1.1 Create DiversificationAnalyzer Service
**File:** `app/Services/Investment/DiversificationAnalyzer.php`

```php
class DiversificationAnalyzer
{
    // Calculate HHI from holdings
    public function calculateHHI(Collection $holdings): float

    // Calculate concentration metrics
    public function calculateConcentration(Collection $holdings): array

    // Get asset class breakdown (grouped from asset_types)
    public function getAssetClassBreakdown(Collection $holdings): array

    // Compare allocation to target (from risk profile)
    public function compareToTarget(array $currentAllocation, int $riskLevel): array

    // Full diversification analysis
    public function analyze(Collection $holdings, int $riskLevel, ?int $accountRiskLevel = null): array
}
```

#### 1.2 Add API Endpoints

**Investment Account:**
```
GET /api/investment/accounts/{id}/diversification
```

**DC Pension:**
```
GET /api/retirement/dc-pensions/{id}/diversification
```

#### 1.3 Controller Methods
- `InvestmentController@getAccountDiversification`
- `RetirementController@getPensionDiversification`

### Phase 2: Frontend Components

#### 2.1 Create Shared Diversification Components

**DiversificationTab.vue** (Shared component)
- Props: `holdings`, `riskLevel`, `accountRiskLevel`, `accountType`
- Displays all diversification metrics
- Reusable for both investments and pensions

**DiversificationScoreCard.vue**
- Visual score display (0-100 gauge)
- Label: Excellent/Good/Fair/Poor

**AssetAllocationComparison.vue**
- Side-by-side or stacked bar chart
- Current vs Target allocation
- Deviation indicators (over/under)

**ConcentrationWarnings.vue**
- List of holdings exceeding thresholds
- Warning badges for concentration risk

**HHIIndicator.vue**
- HHI value display
- Visual indicator (low/medium/high concentration)

#### 2.2 Integrate into Investment Detail View

**File:** `resources/js/views/Investment/AccountDetailView.vue`
- Add "Diversification" tab to existing tab navigation
- Import and use DiversificationTab component

#### 2.3 Integrate into DC Pension Detail View

**File:** `resources/js/views/Retirement/PensionDetail.vue`
- Add "Diversification" tab
- Use same DiversificationTab component

### Phase 3: Risk Profile Comparison Logic

#### 3.1 Effective Risk Level Determination
```javascript
// If account has custom risk, use account risk
// Otherwise, use user's risk profile
const effectiveRiskLevel = account.has_custom_risk
    ? account.risk_preference
    : userRiskProfile.risk_level;
```

#### 3.2 Deviation Analysis
- Calculate deviation from target per asset class
- Flag deviations >5% as "Needs Attention"
- Flag deviations >10% as "Significant Deviation"

#### 3.3 Recommendations
Based on deviations, suggest:
- "Consider reducing equity exposure by X%"
- "Bond allocation is below target by Y%"
- "Portfolio is well-aligned with risk profile"

## Files to Create/Modify

### New Files
| File | Purpose |
|------|---------|
| `app/Services/Investment/DiversificationAnalyzer.php` | Core analysis service |
| `resources/js/components/Investment/DiversificationTab.vue` | Main tab component |
| `resources/js/components/Investment/DiversificationScoreCard.vue` | Score display |
| `resources/js/components/Investment/AssetAllocationComparison.vue` | Allocation chart |
| `resources/js/components/Investment/ConcentrationWarnings.vue` | Warning display |
| `resources/js/components/Investment/HHIIndicator.vue` | HHI display |
| `resources/js/services/diversificationService.js` | API wrapper |

### Modified Files
| File | Changes |
|------|---------|
| `routes/api.php` | Add diversification endpoints |
| `app/Http/Controllers/Api/InvestmentController.php` | Add diversification method |
| `app/Http/Controllers/Api/RetirementController.php` | Add pension diversification method |
| `resources/js/views/Investment/AccountDetailView.vue` | Add Diversification tab |
| `resources/js/views/Retirement/PensionDetail.vue` | Add Diversification tab |

## API Response Structure

```json
{
    "success": true,
    "data": {
        "diversification_score": 72,
        "diversification_label": "Good",
        "hhi": 0.18,
        "hhi_label": "Moderate Concentration",
        "concentration": {
            "top_holding_percent": 25.5,
            "top_3_holdings_percent": 55.2,
            "holdings_over_10_percent": 2,
            "holdings_over_5_percent": 4
        },
        "asset_class_breakdown": {
            "equities": { "current": 65, "target": 50, "deviation": 15 },
            "bonds": { "current": 20, "target": 40, "deviation": -20 },
            "cash": { "current": 10, "target": 10, "deviation": 0 },
            "alternatives": { "current": 5, "target": 5, "deviation": 0 }
        },
        "risk_profile": {
            "user_level": 3,
            "account_level": 4,
            "effective_level": 4,
            "using_custom": true
        },
        "recommendations": [
            {
                "type": "warning",
                "message": "Bond allocation is 20% below target for your risk profile"
            },
            {
                "type": "info",
                "message": "Consider rebalancing to align with Growth risk profile"
            }
        ]
    }
}
```

## Testing Strategy

### Unit Tests
- DiversificationAnalyzer HHI calculation
- Asset class grouping logic
- Target allocation comparison
- Concentration thresholds

### Feature Tests
- API endpoint returns correct structure
- Authorization (user can only access own accounts)
- Handles accounts with no holdings
- Handles accounts with single holding

### Frontend Tests
- Component renders correctly
- Handles loading states
- Handles error states
- Responsive design

## Success Criteria

1. Users can view diversification analysis for each investment account
2. Users can view diversification analysis for each DC pension
3. Current allocation compared against target based on risk profile
4. Clear visual indicators for concentration risk
5. Actionable recommendations displayed
6. Works with both user-level and account-level risk profiles
