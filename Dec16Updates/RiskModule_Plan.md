# Risk Module Integration Plan

## Status: COMPLETED

## Overview

A comprehensive 5-level risk preference system integrated into Fynla that allows users to:
1. Set a main risk level via self-selection (Low → High)
2. Override risk per product within ±1 level of their main preference
3. See risk indicators on dashboard cards and forms
4. View a dedicated Risk Profile page with educational content

## Risk Level System

### Risk Levels

| Level | DB Value | Display Name | Color | Equity/Bond/Cash | Expected Return |
|-------|----------|--------------|-------|------------------|-----------------|
| 1 | `low` | Low | Green | 10/70/20 | 1-3% |
| 2 | `lower_medium` | Lower-Medium | Teal | 30/55/10 | 2-4.5% |
| 3 | `medium` | Medium | Blue | 50/40/5 | 3.5-6.5% |
| 4 | `upper_medium` | Upper-Medium | Amber | 75/20/0 | 5-8.5% |
| 5 | `high` | High | Red | 90/5/0 | 6-12% |

### Product Risk Override Constraint

Users can only set per-product risk within ±1 level of their main preference:
- If main = `low` → products can be: `low`, `lower_medium`
- If main = `medium` → products can be: `lower_medium`, `medium`, `upper_medium`
- If main = `high` → products can be: `upper_medium`, `high`

---

## Database Schema

### risk_profiles table (modified)
```sql
ALTER TABLE risk_profiles ADD COLUMN risk_level ENUM('low', 'lower_medium', 'medium', 'upper_medium', 'high') NULL;
ALTER TABLE risk_profiles ADD COLUMN risk_assessed_at TIMESTAMP NULL;
ALTER TABLE risk_profiles ADD COLUMN is_self_assessed BOOLEAN DEFAULT TRUE;
```

### investment_accounts table (modified)
```sql
ALTER TABLE investment_accounts ADD COLUMN risk_preference ENUM('low', 'lower_medium', 'medium', 'upper_medium', 'high') NULL;
ALTER TABLE investment_accounts ADD COLUMN has_custom_risk BOOLEAN DEFAULT FALSE;
```

### dc_pensions table (modified)
```sql
ALTER TABLE dc_pensions ADD COLUMN risk_preference ENUM('low', 'lower_medium', 'medium', 'upper_medium', 'high') NULL;
ALTER TABLE dc_pensions ADD COLUMN has_custom_risk BOOLEAN DEFAULT FALSE;
```

---

## API Endpoints

Base path: `/api/investment/risk/`

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/levels` | Get all available risk levels with configurations |
| GET | `/profile` | Get user's current risk profile |
| POST | `/profile` | Set or update user's main risk level |
| GET | `/allowed-levels` | Get allowed levels for product override (±1) |
| POST | `/validate-product-level` | Validate a product risk level |
| GET | `/config/{level}` | Get configuration for a specific risk level |

### Example Responses

#### GET /levels
```json
{
  "success": true,
  "data": [
    {
      "key": "low",
      "level_numeric": 1,
      "display_name": "Low",
      "short_description": "Prioritises capital preservation with minimal volatility.",
      "asset_allocation": {"equities": 10, "bonds": 70, "cash": 20},
      "expected_returns": {"min": 1, "max": 3, "typical": 2},
      "colour_class": "green"
    }
    // ... other levels
  ]
}
```

#### GET /profile
```json
{
  "success": true,
  "data": {
    "id": 1,
    "risk_level": "medium",
    "risk_assessed_at": "2025-12-16T15:00:00Z",
    "is_self_assessed": true
  }
}
```

#### GET /allowed-levels
```json
{
  "success": true,
  "data": {
    "main_level": "medium",
    "allowed_levels": [
      {"key": "lower_medium", "display_name": "Lower-Medium", "is_main_level": false},
      {"key": "medium", "display_name": "Medium", "is_main_level": true},
      {"key": "upper_medium", "display_name": "Upper-Medium", "is_main_level": false}
    ]
  }
}
```

---

## Backend Files

### Service
**File**: `app/Services/Risk/RiskPreferenceService.php`

Key methods:
- `getAvailableRiskLevels()` - Returns all 5 risk levels with full config
- `setMainRiskLevel(int $userId, string $riskLevel)` - Sets main preference
- `getMainRiskLevel(int $userId)` - Gets current level
- `getAllowedProductRiskLevels(int $userId)` - Returns ±1 range
- `validateProductRiskLevel(int $userId, string $riskLevel)` - Validates override
- `getRiskLevelConfig(string $riskLevel)` - Returns allocation/returns

### Controller
**File**: `app/Http/Controllers/Api/RiskPreferenceController.php`

### Models Updated
- `app/Models/Investment/RiskProfile.php`
- `app/Models/Investment/InvestmentAccount.php`
- `app/Models/DCPension.php`

---

## Frontend Files

### New Components

| File | Purpose |
|------|---------|
| `components/Shared/RiskLevelSelector.vue` | Interactive 5-button selector with info panels |
| `components/Shared/RiskBadge.vue` | Compact badge for cards |
| `components/Risk/RiskFactorsPanel.vue` | 4-quadrant risk factors display |
| `components/Risk/CapacityForLossSection.vue` | Spectrum visualization |
| `components/Risk/TimeHorizonSection.vue` | Time horizon guidance |
| `components/Risk/InvestmentTypesAccordion.vue` | Asset class accordion |
| `views/Risk/RiskProfilePage.vue` | Main risk profile page |
| `services/riskService.js` | API wrapper |

### Modified Components

| File | Changes |
|------|---------|
| `components/Investment/AccountForm.vue` | Added RiskLevelSelector |
| `components/Investment/AccountCard.vue` | Added RiskBadge |
| `components/Retirement/DCPensionForm.vue` | Added RiskLevelSelector |
| `components/Retirement/PensionCard.vue` | Added RiskBadge |
| `router/index.js` | Added /risk-profile route |
| `store/modules/investment.js` | Added risk getters/actions |

---

## RiskLevelSelector Props

```vue
<RiskLevelSelector
  v-model="formData.risk_preference"
  :allowed-levels="allowedRiskLevels"
  :compact="false"
  :show-allocation="true"
  :show-returns="true"
  :collapsible="true"
  label="Risk Level for This Account"
/>
```

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| modelValue | String | null | Selected risk level |
| allowedLevels | Array | [] | Allowed level keys |
| compact | Boolean | false | Compact display mode |
| showAllocation | Boolean | true | Show asset allocation |
| showReturns | Boolean | true | Show expected returns |
| collapsible | Boolean | true | Make info panel collapsible |
| label | String | 'Select Your Risk Level' | Header label |

---

## RiskBadge Props

```vue
<RiskBadge
  :level="account.risk_preference"
  size="sm"
  :abbreviated="true"
  :has-custom-risk="account.has_custom_risk"
/>
```

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| level | String | required | Risk level value |
| size | String | 'md' | Size: xs, sm, md, lg |
| abbreviated | Boolean | false | Use short labels (L, LM, M, UM, H) |
| hasCustomRisk | Boolean | false | Show amber ring indicator |

---

## Educational Content Structure (RiskProfilePage)

1. **Introduction Section**
   - Why understanding risk matters
   - Self-assessment approach

2. **Risk Factors Panel**
   - Value falling risk
   - Capacity to withstand risk
   - Inflation risk
   - Liquidity risk

3. **Capacity for Loss Section**
   - Interactive spectrum
   - Level descriptions
   - Interpretation guidance

4. **Time Horizon Section**
   - Timeline visualization
   - Risk/horizon matrix
   - Guidance on how time affects risk

5. **Risk Level Selector**
   - Full 5-level selector
   - Asset allocation display
   - Expected returns

6. **Investment Types Accordion**
   - Cash
   - Bonds
   - Commercial Property
   - Equities
   - Alternative Investments

7. **Custom Products Section**
   - List of products with custom risk
   - Quick links to adjust

---

## Color Scheme

| Level | Badge BG | Badge Text | Border |
|-------|----------|------------|--------|
| Low | bg-green-100 | text-green-800 | border-green-200 |
| Lower-Medium | bg-teal-100 | text-teal-800 | border-teal-200 |
| Medium | bg-blue-100 | text-blue-800 | border-blue-200 |
| Upper-Medium | bg-amber-100 | text-amber-800 | border-amber-200 |
| High | bg-red-100 | text-red-800 | border-red-200 |

---

## Testing

### API Testing
```bash
# Get risk levels
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/investment/risk/levels

# Get profile
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/investment/risk/profile

# Set profile
curl -X POST -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"risk_level":"medium"}' \
  http://localhost:8000/api/investment/risk/profile

# Get allowed levels
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/investment/risk/allowed-levels
```

### Frontend Testing
1. Navigate to `/risk-profile`
2. Select a risk level
3. Verify it saves correctly
4. Open an investment account form
5. Verify RiskLevelSelector appears with ±1 constraint
6. Save account with custom risk
7. Verify RiskBadge appears on card with amber ring

---

## Migration Commands

```bash
# Run risk module migrations
php artisan migrate

# Verify migrations ran
php artisan migrate:status | grep risk
```
