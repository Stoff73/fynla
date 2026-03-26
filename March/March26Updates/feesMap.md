# Fee System Map — Fynla

*26 March 2026 — comprehensive trace of fee checking, actions, and display across Investments, Pensions, and Protection*

---

## Overview

| Module | Fee Storage | Fee Calculation Service | Action Triggers | Thresholds | Frontend Display |
|--------|-----------|----------------------|----------------|-----------|-----------------|
| **Investment** | 5 fields on `InvestmentAccount` + OCF on `Holding` | `FeeAnalyzer`, `PlatformComparator` | 3 dedicated actions (high total, high fund, high platform) | Seeder: 1.0%, 0.5%, 0.8% | 5 components (FeeBreakdown, AccountFeesPanel, etc.) |
| **Pension** | 5 fields on `DCPension` + OCF on `Holding` | `PensionPortfolioAnalyzer`, `PensionProjector` | 1 indirect action (consolidation at 3+ pensions) | Seeder: count >= 3 | PensionDetailInline (Overview + Holdings tabs) |
| **Protection** | `premium_amount` + `premium_frequency` per policy | `RecommendationEngine`, `ComprehensiveProtectionPlanService` | 0 dedicated premium actions | RecommendationEngine: 5% of income (hardcoded) | PremiumBreakdownChart (pie chart) |

---

## 1. INVESTMENT FEES

### 1.1 Storage

**Model:** `app/Models/Investment/InvestmentAccount.php`

| Field | Type | Cast | Purpose |
|-------|------|------|---------|
| `platform_fee_percent` | decimal(5,4) | decimal:4 | Platform fee as % of portfolio |
| `platform_fee_amount` | decimal(10,2) | decimal:2 | Fixed £ amount (when type=fixed) |
| `platform_fee_type` | enum | string | `percentage` or `fixed` |
| `platform_fee_frequency` | enum | string | `monthly`, `quarterly`, `annually` |
| `advisor_fee_percent` | decimal(5,4) | decimal:4 | Advisor fee as % |

**Holdings:** `app/Models/Investment/Holding.php` — `ocf_percent` (float) per fund

### 1.2 Calculation

**FeeAnalyzer** (`app/Services/Investment/FeeAnalyzer.php`)

Core method: `analyzeAccountFees($account)` → returns per-account breakdown:

```
Platform Fee Calculation:
  IF platform_fee_type === 'fixed':
    annual = amount × { monthly: 12, quarterly: 4, annually: 1 }
  ELSE:
    annual = account_value × (platform_fee_percent / 100)

Fund OCF = Σ(holding.current_value × holding.ocf_percent / 100)
Transaction Costs = portfolio_value × turnover_rate × 0.001 (estimated)
Advisory Fee = account_value × (advisor_fee_percent / 100)

Total = Platform + Fund OCF + Transaction + Advisory
Total % = (Total / account_value) × 100
```

**Key:** Advisory fees are intentionally excluded from threshold comparisons — they represent a conscious choice for advice.

**Fee Assessment Tiers** (`assessFeeTier()`):
| Tier | Range | Message |
|------|-------|---------|
| Acceptable | < 0.8% | — |
| Higher than average | 0.8% – 1.0% | "Your fees are higher than average" |
| High | 1.0% – 1.5% | "Your fees are high" |
| Very high | > 1.5% | "Your fees are much higher than average" |

**PlatformComparator** (`app/Services/Investment/Fees/PlatformComparator.php`)

Compares 8 UK platforms with real fee structures:
- Vanguard: tiered (0.15% up to £250k, then 0% over)
- Hargreaves Lansdown: tiered (0.45% up to £250k, 0.25% to £1M, 0.10% over)
- AJ Bell: 0.25% capped (£3.50–£7.50 monthly min/max)
- Interactive Investor: flat £9.99/month + dealing charges
- Fidelity: 0.35% capped (max £45/year)
- Charles Stanley Direct: tiered (0.25% up to £50k, 0.15% to £500k, 0.10% over)
- Bestinvest: tiered

### 1.3 Action Triggers

**Service:** `app/Services/Investment/InvestmentActionDefinitionService.php`
**Seeder:** `database/seeders/InvestmentActionDefinitionSeeder.php`

| Action Key | Condition | Threshold | Category | Priority | Scope |
|------------|-----------|-----------|----------|----------|-------|
| `high_total_fees` | `total_fee_percent_above` | **1.0%** | Fees | high | per account |
| `high_fund_fees` | `weighted_ocf_above` | **0.5%** | High Fees | medium | per account |
| `high_platform_fees` | `platform_fee_percent_above` | **0.8%** | Platform Fees | medium | per account |

**How they trigger:**
1. `InvestmentAgent.analyze()` calls `FeeAnalyzer.analyzeAccountFees()` for each account
2. Returns `accountFeeAnalyses[]` array
3. `InvestmentActionDefinitionService.evaluateAgentActions()` receives this
4. For each enabled action definition, dispatches to the matching evaluator
5. Evaluator compares account's fee % against threshold from `trigger_config`
6. If exceeded → creates recommendation with templated title/description + decision trace

**Template variables:**
- `{account_name}` — account name
- `{total_fee_percent}` — total fee % (2dp)
- `{annual_fees}` — annual fee £ amount
- `{weighted_ocf}` — weighted OCF % (2dp)
- `{platform_fee_percent}` — platform fee % (2dp)

**Estimated impact:** 40% of annual fees (used for action prioritisation)

**Thresholds are stored in the database** (seeded from `InvestmentActionDefinitionSeeder`). Not loaded from TaxConfigService or PlanConfigService. To change: re-run the seeder or update the DB record directly.

### 1.4 Frontend Display

| Component | What it shows | Location |
|-----------|--------------|----------|
| `AccountFeesPanel.vue` | Per-account annual cost breakdown (platform, OCF, advisor, total), per-holding OCF table, 10-year impact | Investment detail → Fees tab |
| `FeeBreakdown.vue` | Portfolio-wide summary cards, by-account table, 10-year impact, benchmarks | Investment plan section |
| `FeeAnalysisSection.vue` | Total fees + status badge, breakdown by type, 10/20/30-year projections, high-fee holdings, reduction opportunities | Investment plan → Fee Analysis |
| `InvestmentProjections.vue` | Platform fee, OCF, advisor fee, total annual cost (% and £) | Net Worth → Investments → account detail |
| `AccountStrategyCard.vue` | Platform fee in strategy context | Investment plan cards |

---

## 2. PENSION FEES

### 2.1 Storage

**Model:** `app/Models/DCPension.php`

Same 5 fields as investment accounts:
| Field | Type | Cast |
|-------|------|------|
| `platform_fee_percent` | decimal(5,4) | decimal:4 |
| `platform_fee_type` | string(20) | — |
| `platform_fee_amount` | decimal(15,2) | decimal:2 |
| `platform_fee_frequency` | string(20) | — |
| `advisor_fee_percent` | decimal(5,4) | decimal:4 |

**Holdings:** via polymorphic `Holding` model — `ocf_percent` per fund

**Migration:** `2026_03_25_164053_add_fee_fields_to_dc_pensions_table` (added type, amount, frequency, advisor)

### 2.2 Calculation

**PensionPortfolioAnalyzer** (`app/Services/Retirement/PensionPortfolioAnalyzer.php`)

`analyzeFees()`:
```
Platform Fees = Σ(pension.current_fund_value × pension.platform_fee_percent / 100)
Fund OCF Fees = Σ(holding.current_value × holding.ocf_percent / 100)
Total = Platform + Fund OCF
Fee % = Total / total_portfolio_value × 100
Potential Saving = Total - (total_value × 0.002)  // vs 0.20% low-cost benchmark
```

**Note:** Only uses `platform_fee_percent` — does NOT handle `platform_fee_type === 'fixed'` or `advisor_fee_percent`. This is a gap.

**PensionProjector** (`app/Services/Retirement/PensionProjector.php`)

`projectDCPension()`:
```
net_growth_rate = gross_growth - (platform_fee_percent / 100)
future_value = current_value × (1 + net_growth)^years + contributions_FV
```

Platform fee directly reduces growth rate. Advisory fee and OCF are NOT deducted from projections.

**ContributionOptimizer** (`app/Services/Retirement/ContributionOptimizer.php`)

Same pattern — deducts `platform_fee_percent` from growth rate when calculating required contributions.

**RetirementPlanService** (`app/Services/Plans/RetirementPlanService.php`)

Uses `platform_fee_percent` to adjust growth in `buildPensionGrowthProjections()`:
```
net_growth = default_growth_rate - (platform_fee_percent / 100)
```

### 2.3 Action Triggers

**Service:** `app/Services/Retirement/RetirementActionDefinitionService.php`
**Seeder:** `database/seeders/RetirementActionDefinitionSeeder.php`

| Action Key | Condition | Threshold | Category | Priority |
|------------|-----------|-----------|----------|----------|
| `pension_consolidation_opportunity` | `multiple_dc_pensions` | **3+ pensions** | Pension Management | medium |

**This is the ONLY fee-related action for pensions.** It's triggered by pension count, not fee level. Fee data is included in the recommendation context (total fees, highest %, lowest %) but does not trigger independently.

**Missing pension fee actions (gaps):**
- No `high_platform_fee` trigger per pension
- No `high_total_fee` trigger (platform + advisor + OCF)
- No `high_advisor_fee` trigger
- No `high_ocf` trigger per pension
- No fee comparison between pensions (flag expensive one)

### 2.4 Frontend Display

**PensionDetailInline.vue** — Overview tab:

| Row | Source | Shown when |
|-----|--------|-----------|
| Platform Fee | `platformFeeDisplay` — handles % and fixed £ types | Always (DC) |
| Advisor Fee | `advisorFeePercent` | > 0 only |
| Avg Fund Fee (OCF) | `weightedAverageOCF` — value-weighted across holdings | hasHoldings |
| Total Annual Cost | `platformFeePercent + advisorFeePercent + weightedAverageOCF` | Always (DC) |
| Annual Fee Impact | `fund_value × totalFeePercent / 100` | Always (DC) |

**PensionDetailInline.vue** — Holdings tab:

| Column | Source |
|--------|--------|
| Fund Name | `holding.security_name` |
| Type | `holding.asset_type` |
| Allocation | `holding.allocation_percent` |
| Value | `fund_value × allocation / 100` |
| OCF | `holding.ocf_percent` |

Footer: Weighted Avg OCF + Total Annual Cost

---

## 3. PROTECTION PREMIUMS

### 3.1 Storage

| Model | Premium Field | Frequency Field | Notes |
|-------|-------------|----------------|-------|
| `LifeInsurancePolicy` | `premium_amount` (decimal:2) | `premium_frequency` | monthly/quarterly/annually |
| `CriticalIllnessPolicy` | `premium_amount` (decimal:2) | `premium_frequency` | monthly/quarterly/annually |
| `IncomeProtectionPolicy` | `premium_amount` (decimal:2) | — | No frequency field (implicit monthly) |

### 3.2 Calculation

**RecommendationEngine** (`app/Services/Protection/RecommendationEngine.php`)

`calculateTotalPremiums()`: Sums all policy premiums, converts to annual:
```
annual = premium_amount × { monthly: 12, quarterly: 4, annually: 1 }
total = Σ(life_policies) + Σ(ci_policies) + Σ(ip_policies)
```

Premium estimation for uninsured gaps uses TaxConfig factors:
```
life_premium = (sum_assured / 1000) × base_rate × smoker_loading × age_multiplier / 12
ci_premium = life_premium × 2.5
ip_premium = annual_benefit × 0.02 / 12
```

**TaxConfig protection factors** (`TaxConfigurationSeeder`):
| Factor | Value | Purpose |
|--------|-------|---------|
| `base_rate` | 0.50 | £ per £1000 per month |
| `smoker_loading` | 1.5 | 50% increase for smokers |
| `ci_ratio` | 2.5 | CI costs 2.5× life |
| `ip_rate` | 0.02 | 2% of benefit per month |
| `max_premium_percent_of_income` | 0.10 | 10% max affordability |
| `comfortable_premium_percent` | 0.05 | 5% comfortable level |
| `ipt.standard_rate` | 0.12 | 12% Insurance Premium Tax |

**ComprehensiveProtectionPlanService** (`app/Services/Protection/ComprehensiveProtectionPlanService.php`)

`buildCurrentCoverage()`:
- Converts all premiums to annual via `convertToAnnualPremium()`
- Returns `total_annual_premiums` and `total_monthly_premiums`

### 3.3 Action Triggers

**Service:** `app/Services/Protection/ProtectionActionDefinitionService.php`
**Seeder:** `database/seeders/ProtectionActionDefinitionSeeder.php`

**Dedicated premium actions: NONE**

The only premium check is in `RecommendationEngine`:
```
IF totalAnnualPremiums > annual_income × 0.05 (5%)
THEN recommend "Review and optimise existing policies"
     Priority: 5 (lowest)
```

This is NOT a seeder-driven action — it's a hardcoded recommendation at the lowest priority level.

**Indirectly premium-related seeder actions:**
| Action Key | Condition | What it does |
|------------|-----------|-------------|
| `review_existing_policies` | `policies_exist_with_gaps` | Suggests review for better value |
| `consolidate_policies` | `multiple_policies` (3+) | Suggests consolidation to reduce premiums |
| `dis_reliance_warning` | `dis_reliance_warning` | Death in service > 50% of life cover |

**Missing protection premium actions (gaps):**
- No `high_total_premiums` trigger (premiums > % of income)
- No `premium_affordability_warning` using TaxConfig thresholds
- No premium vs benchmark comparison
- No IPT (Insurance Premium Tax) applied to any calculations
- Income protection has no `premium_frequency` field

### 3.4 Frontend Display

| Component | What it shows |
|-----------|--------------|
| `PremiumBreakdownChart.vue` | Pie chart of monthly premiums by type (life, CI, IP, disability, sickness) |
| `PolicyCard.vue` | Premium amount and frequency per policy |
| `PolicyFormModal.vue` | Premium input (amount + frequency dropdown) |

---

## 4. CROSS-MODULE COMPARISON

### Where Fees Are Checked vs Where They Should Be

| Check | Investment | Pension | Protection |
|-------|-----------|---------|-----------|
| **Total fee % above threshold** | Yes (1.0%) | No | No |
| **Platform/provider fee above threshold** | Yes (0.8%) | No | No |
| **Fund OCF above threshold** | Yes (0.5%) | No | No |
| **Advisor fee flagged** | Excluded from warnings | Not checked | N/A |
| **Fee reduces projections** | Yes (FeeAnalyzer) | Partial (platform only) | N/A |
| **Consolidation for fee savings** | No | Yes (3+ pensions) | Yes (3+ policies) |
| **Platform comparison** | Yes (8 platforms) | No | No |
| **Affordability check** | No | No | Hardcoded 5% (lowest priority) |
| **10/20/30-year fee impact** | Yes (3 components) | No | No |
| **Per-holding OCF display** | Yes | Yes (Holdings tab) | N/A |

### Threshold Sources

| Module | Where thresholds live | How to change |
|--------|---------------------|--------------|
| Investment | `investment_action_definitions` DB table (seeded) | Re-run seeder or update DB |
| Pension | `retirement_action_definitions` DB table (count only) | Re-run seeder or update DB |
| Protection | Hardcoded in `RecommendationEngine` (5%) + TaxConfig (10% max, 5% comfortable) | Change code or TaxConfig seeder |

### Data Flow Summary

```
                    ┌──────────────────────────────┐
                    │  USER ENTERS FEE DATA         │
                    │  (Form → API → Database)       │
                    └──────────┬───────────────────┘
                               │
              ┌────────────────┼────────────────────┐
              ▼                ▼                     ▼
     ┌────────────┐   ┌──────────────┐    ┌──────────────────┐
     │ INVESTMENT  │   │   PENSION    │    │   PROTECTION     │
     │ FeeAnalyzer │   │ Portfolio    │    │ Recommendation   │
     │             │   │ Analyzer     │    │ Engine           │
     ├─────────────┤   ├──────────────┤    ├──────────────────┤
     │ Platform    │   │ Platform     │    │ Total premiums   │
     │ Fund OCF    │   │ Fund OCF     │    │ per policy       │
     │ Transaction │   │              │    │                  │
     │ Advisory    │   │              │    │                  │
     │ = Total %   │   │ = Total £    │    │ = Total annual £ │
     └──────┬──────┘   └──────┬───────┘    └────────┬─────────┘
            │                 │                      │
            ▼                 ▼                      ▼
     ┌────────────┐   ┌──────────────┐    ┌──────────────────┐
     │ ACTION     │   │ ACTION       │    │ RECOMMENDATION   │
     │ SERVICE    │   │ SERVICE      │    │ (hardcoded)      │
     ├────────────┤   ├──────────────┤    ├──────────────────┤
     │ 3 triggers │   │ 1 trigger    │    │ 0 triggers       │
     │ high_total │   │ consolidate  │    │ (5% check only,  │
     │ high_fund  │   │ (count>=3)   │    │  priority 5)     │
     │ high_plat  │   │              │    │                  │
     └──────┬──────┘   └──────┬───────┘    └────────┬─────────┘
            │                 │                      │
            ▼                 ▼                      ▼
     ┌────────────┐   ┌──────────────┐    ┌──────────────────┐
     │ FRONTEND   │   │ FRONTEND     │    │ FRONTEND         │
     │ 5 views    │   │ 2 tabs       │    │ Pie chart        │
     │ + actions  │   │ + actions    │    │ + policy cards   │
     └────────────┘   └──────────────┘    └──────────────────┘
```

---

## 5. GAPS AND RECOMMENDATIONS

### Pension Module — Needs Parity with Investment

1. **Add `high_total_fees` action** — trigger when platform + advisor + OCF > 1.0% (match investment threshold)
2. **Add `high_platform_fees` action** — trigger when platform fee > 0.8%
3. **Add `high_fund_fees` action** — trigger when weighted OCF > 0.5%
4. **Fix PensionPortfolioAnalyzer** — currently ignores `platform_fee_type === 'fixed'` and `advisor_fee_percent`
5. **Fix PensionProjector** — deduct advisor fee and OCF from growth rate, not just platform fee
6. **Add 10-year fee impact** calculation to pension detail view

### Protection Module — Needs Premium Action System

7. **Add `high_premium_cost` action** to seeder — trigger when total premiums > 5% of income (promote from priority 5 recommendation to proper action)
8. **Add `premium_affordability_warning` action** — trigger when > 10% of income (using TaxConfig threshold)
9. **Add `premium_frequency` to IncomeProtectionPolicy** — currently missing
10. **Apply IPT** to premium calculations where applicable

### Cross-Module

11. **Standardise fee threshold source** — consider moving all thresholds to `PlanConfigService` or `TaxConfigService` for single-source management
12. **Add cross-module fee dashboard** — total annual cost across investments + pensions + protection premiums
