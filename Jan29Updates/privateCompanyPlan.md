# Private Company & Crowdfunding Investment Types - Implementation Plan

## Overview

Add **Private Company** and **Crowdfunding** investment types to the FPS investment module with full support for company details, ownership structures, UK tax relief tracking (EIS/SEIS/SITR/VCT), performance monitoring, and exit tracking.

---

## Phase 1: Database Migration & Model Updates

### Migration: `add_private_investment_fields_to_investment_accounts_table.php`

**Company Details:**
```
company_legal_name (string 255, nullable)
company_registration_number (string 50, nullable)
company_country (string 100, nullable)
company_website (string 255, nullable)
company_trading_name (string 255, nullable)
company_sector (string 100, nullable)
crowdfunding_platform (string 255, nullable)
```

**Investment Details:**
```
investment_date (date, nullable)
investment_amount (decimal 15,2, nullable)
investment_currency (string 3, default 'GBP')
funding_round (enum: seed/pre_seed/series_a/series_b/series_c/bridge/safe/other, nullable)
pre_money_valuation (decimal 15,2, nullable)
post_money_valuation (decimal 15,2, nullable)
price_per_share (decimal 12,6, nullable)
number_of_shares (integer, nullable)
instrument_type (enum: ordinary_shares/preference_shares/convertible_loan_note/safe/revenue_share/fund_nominee_interest, nullable)
```

**Ownership & Legal:**
```
share_class (string 100, nullable)
has_voting_rights (boolean, default true)
has_dividend_rights (boolean, default true)
liquidation_preference (string 100, nullable)
has_anti_dilution (boolean, default false)
holding_structure (enum: direct/nominee, default 'direct')
nominee_name (string 255, nullable)
conversion_terms (text, nullable)
interest_rate (decimal 5,2, nullable)
maturity_date (date, nullable)
```

**UK Tax Relief:**
```
tax_relief_type (enum: eis/seis/sitr/vct/none, nullable)
eis3_certificate_number (string 50, nullable)
hmrc_reference (string 50, nullable)
relief_claimed_date (date, nullable)
relief_amount_claimed (decimal 12,2, nullable)
disposal_restriction_date (date, nullable)
clawback_risk (boolean, default false)
clawback_notes (text, nullable)
```

**Status & Valuation:**
```
latest_valuation (decimal 15,2, nullable)
latest_valuation_date (date, nullable)
current_ownership_percent (decimal 5,4, nullable)
company_status (enum: active/distressed/dormant/failed/exited, default 'active')
status_notes (text, nullable)
```

**Exit Tracking:**
```
exit_type (enum: acquisition/secondary_sale/buyback/ipo/liquidation, nullable)
exit_date (date, nullable)
exit_gross_proceeds (decimal 15,2, nullable)
exit_fees (decimal 12,2, nullable)
exit_net_proceeds (decimal 15,2, nullable)
exit_moic (decimal 6,2, nullable)
loss_relief_eligible (boolean, default false)
capital_loss_amount (decimal 15,2, nullable)
negligible_value_claim (boolean, default false)
```

---

## Phase 2: Backend Updates

### Files to Modify:

**1. `app/Models/Investment/InvestmentAccount.php`**
- Add all new fields to `$fillable` array
- Add date casts for new date fields
- Add boolean casts for new boolean fields
- Add computed properties: `isPrivateInvestment()`, `isHoldingPeriodComplete()`, `paper_gain_loss`, `paper_return_percent`

**2. `app/Http/Controllers/Api/InvestmentController.php`**
- Add `private_company` and `crowdfunding` to account_type validation enum
- Add conditional validation rules for private investment fields
- Auto-calculate `disposal_restriction_date` (investment_date + 3 years) for EIS/SEIS

### Required Fields (when private_company or crowdfunding):
- `company_legal_name`
- `investment_date`
- `investment_amount`
- `instrument_type`
- `crowdfunding_platform` (only for crowdfunding type)

---

## Phase 3: Frontend Form Updates

### File: `resources/js/components/Investment/AccountForm.vue`

**1. Add Account Type Options:**
```html
<option value="private_company">Private Company</option>
<option value="crowdfunding">Crowdfunding Investment</option>
```

**2. Add Computed Properties:**
```javascript
isPrivateInvestmentType() {
  return ['private_company', 'crowdfunding'].includes(this.formData.account_type);
}
isCrowdfundingType() {
  return this.formData.account_type === 'crowdfunding';
}
requiresTaxReliefTracking() {
  return ['eis', 'seis', 'sitr', 'vct'].includes(this.formData.tax_relief_type);
}
isDebtInstrument() {
  return ['convertible_loan_note', 'safe'].includes(this.formData.instrument_type);
}
showExitFields() {
  return this.formData.company_status === 'exited';
}
```

**3. Add Form Sections (collapsible, v-if="isPrivateInvestmentType"):**

| Section | Key Fields |
|---------|------------|
| Company Details | Legal name*, trading name, reg number, country, website, sector, platform* |
| Investment Details | Date*, amount*, currency, round, valuations, shares |
| Instrument & Ownership | Type*, share class, voting/dividend rights, nominee structure |
| UK Tax Relief (blue box) | Relief type, certificate number, HMRC ref, dates, clawback |
| Status & Valuation | Company status, latest valuation, ownership % |
| Exit Details (if exited) | Exit type, date, proceeds, fees, loss relief |

*Required fields

**4. Add formData Fields:**
All new database fields with appropriate defaults.

---

## Phase 4: Form Dropdown Options

| Field | Options |
|-------|---------|
| funding_round | Pre-Seed, Seed, Series A, Series B, Series C, Bridge, SAFE, Other |
| instrument_type | Ordinary Shares, Preference Shares, Convertible Loan Note, SAFE, Revenue Share, Fund/Nominee Interest |
| holding_structure | Direct Shareholding, Nominee Held |
| tax_relief_type | EIS, SEIS, SITR, VCT, None |
| company_status | Active, Distressed, Dormant, Failed, Exited |
| exit_type | Acquisition, Secondary Sale, Buyback, IPO, Liquidation |
| company_sector | Technology, Healthcare, Finance, Consumer, Energy, Other |

---

## Critical Files

| File | Changes |
|------|---------|
| `database/migrations/xxxx_add_private_investment_fields.php` | New migration |
| `app/Models/Investment/InvestmentAccount.php` | Fillable, casts, computed |
| `app/Http/Controllers/Api/InvestmentController.php` | Validation rules |
| `resources/js/components/Investment/AccountForm.vue` | Form sections |

---

## Verification

1. **Run migration:** `php artisan migrate`
2. **Test form:** Add new Private Company investment with all fields
3. **Test crowdfunding:** Verify platform field is required
4. **Test tax relief:** Verify disposal restriction date auto-calculates
5. **Test exit:** Change status to "Exited" and verify exit fields appear
6. **Test validation:** Submit with missing required fields
7. **Test existing accounts:** Verify ISA/GIA accounts still work unchanged

---

## Notes

- All new fields nullable for backward compatibility
- EIS/SEIS 3-year holding period auto-calculated from investment_date
- Form uses collapsible sections to avoid overwhelming user
- UK tax context: EIS 30% relief, SEIS 50% relief tracking
