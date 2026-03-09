# Employee Share Schemes (ESS) - Field Audit

**Date:** 7 March 2026
**Scope:** Audit all ESS fields on InvestmentAccount for service consumption

## Summary

- **Total ESS-specific fields on InvestmentAccount model:** 69 fields across 8 groups
- **Fields consumed by EmployeeSchemeCalculationService:** 12
- **Fields consumed by InvestmentAccountResource (API output):** 7
- **Fields with zero downstream service usage:** 50+

## Service Consumption Map

### EmployeeSchemeCalculationService (app/Services/Investment/EmployeeSchemeCalculationService.php)

The ONLY service that reads ESS fields. Methods and fields consumed:

| Method | Fields Read |
|--------|-------------|
| `isHoldingPeriodComplete()` | `disposal_restriction_date` |
| `calculatePaperGainLoss()` | `investment_amount`, `latest_valuation` |
| `calculatePaperReturnPercent()` | `investment_amount`, `latest_valuation` |
| `isTaxAdvantagedScheme()` | `account_type` |
| `calculateIntrinsicValue()` | `current_share_price`, `exercise_price`, `units_vested` |
| `calculateSchemeCurrentValue()` | `current_share_price`, `units_vested` |
| `calculateUnvestedValue()` | `current_share_price`, `exercise_price`, `units_unvested` |
| `isInCsopTaxAdvantageWindow()` | `account_type`, `grant_date` |
| `calculateRemainingUnits()` | `units_granted`, `units_exercised`, `units_forfeited`, `units_expired` |

### InvestmentAccountResource (API response)

Returns these ESS fields when `isEmployeeShareScheme()` is true:
- `employer_name`, `grant_date`, `units_granted`, `units_vested`, `exercise_price`, `current_share_price`, `intrinsic_value` (computed attribute)

### Model Methods (InvestmentAccount)

- `isEmployeeShareScheme()` - checks `account_type` in `['saye', 'csop', 'emi', 'unapproved_options', 'rsu']`
- `isOptionsScheme()` - checks `account_type` in `['saye', 'csop', 'emi', 'unapproved_options']`
- `isTaxAdvantagedScheme()` - delegates to EmployeeSchemeCalculationService
- Computed attributes: `intrinsic_value`, `scheme_current_value`, `unvested_value`

### Agent-Level Consumption

No agent (ProtectionAgent, InvestmentAgent, etc.) directly references ESS-specific fields. The InvestmentAgent treats ESS accounts like regular investment accounts.

### Frontend Components

- `EmployeeShareSchemeFields.vue` - Form for all 69 fields (8 sections)
- `EmployeeShareSchemeDetail.vue` - Detail view for ESS accounts
- `AccountForm.vue` - Conditionally shows ESS fields based on `isEmployeeShareScheme`
- `InvestmentDetailInline.vue` - Shows ESS detail for share scheme account types
- `InvestmentList.vue` - Custom display/icons for ESS accounts
- `RequiredCapitalDetail.vue` - Custom display for ESS in retirement capital view

## Fields with Zero Service Consumption

### Group 1: Employer Details (6 unused of 8)
- `employer_registration`, `employer_ticker`, `employer_is_listed`, `parent_company_name`, `parent_company_country`, `ers_scheme_reference`, `ers_registered`
- Only `employer_name` is used (API resource output)

### Group 2: Grant Details (7 unused of 10)
- `grant_reference`, `market_value_at_grant`, `share_class_scheme`, `grant_currency`, `option_price_paid`, `scheme_start_date`, `scheme_duration_months`
- Used: `grant_date`, `units_granted`, `exercise_price`

### Group 3: Vesting Schedule (all 12 unused)
- `vesting_type`, `cliff_date`, `cliff_percentage`, `vesting_period_months`, `vesting_frequency_months`, `has_performance_conditions`, `performance_conditions_description`, `performance_period_end`, `performance_vesting_min_percent`, `performance_vesting_max_percent`, `full_vest_date`, `accelerated_vesting_allowed`

### Group 4: Current Status (1 unused of 8)
- `scheme_status`, `share_price_date` unused
- Used: `units_vested`, `units_unvested`, `units_exercised`, `units_forfeited`, `units_expired`, `current_share_price`

### Group 5: Exercise & Expiry (4 unused of 6)
- `last_exercise_date`, `total_exercise_proceeds`, `total_exercise_cost`, `exercise_history_json`
- Used: `exercise_window_start`, `exercise_window_end` (referenced in model casts only)

### Group 6: Tax Treatment (all 7 unused by services)
- `tax_treatment`, `is_readily_convertible_asset`, `paye_via_payroll`, `income_tax_at_vest_exercise`, `ni_at_vest_exercise`, `csop_disqualifying_event`, `csop_three_year_date`, `cost_basis_for_cgt`

### Group 7: SAYE-Specific (all 5 unused)
- `saye_monthly_savings`, `saye_current_savings_balance`, `saye_maturity_date`, `saye_option_discount_percent`, `saye_bonus_amount`

### Group 8: Leaver Terms (all 4 unused)
- `leaver_category`, `post_termination_exercise_days`, `termination_date`, `leaver_notes`

## Decision

**DO NOT REMOVE ESS from onboarding or forms.** ESS is an existing feature that needs development, not deletion. The fields are well-structured and represent a comprehensive employee share scheme data model suitable for future valuation and tax planning services.

### Future Development Opportunities

1. **Tax-at-exercise calculator** - Use Group 6 tax treatment fields
2. **Vesting schedule projector** - Use Group 3 vesting fields to show timeline of when units vest
3. **SAYE maturity calculator** - Use Group 7 to show SAYE savings vs option value at maturity
4. **Leaver impact analysis** - Use Group 8 to show what happens to unvested options if user leaves
5. **Exercise strategy advisor** - Combine Groups 5+6 to recommend optimal exercise timing
6. **CSOP 3-year rule tracker** - `csop_three_year_date` already captured, service just needs to consume it

### What Exists Today

The `EmployeeSchemeCalculationService` provides a solid foundation with:
- Intrinsic value calculation (options spread)
- Scheme current value (vested holdings)
- Unvested value projection
- CSOP tax advantage window detection
- Remaining units tracking
- Holding period compliance check

These calculations are surfaced through computed model attributes and the API resource, visible in the frontend detail views.
