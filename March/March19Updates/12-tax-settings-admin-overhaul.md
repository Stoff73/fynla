# 12 — Tax Settings Admin Overhaul

**PR #141 — taxConfigFix → main**
**Date:** 19 March 2026 (session 2)

## Summary

Complete overhaul of the admin Tax Settings panel to display and edit all 568 TaxConfigService values. Fixed NaN values, incorrect rate formatting, and data structure mismatches. Removed hardcoded tax values from agents. Expanded AI tax tool from 5 to 18 topics.

## Changes

### TaxSettings.vue (resources/js/components/Admin/TaxSettings.vue)

**NaN fixes:**
- PET taper relief: component read `{years, rate}` but seeder stores `{min_years, max_years, tax_rate}` — mapped to correct keys
- Trust charges: component read flat `trust_entry_charge` but data is nested `trust_charges.entry.rate` — fixed all 5 bindings
- Trust charge periods: read nested `no_charge_periods.first_quarter` and `.will_trust_months`

**Rate formatting (decimals → percentages):**
- Income tax band rates: `band.rate` (0.2) → `(band.rate * 100).toFixed(0)` (20%)
- CGT rates: 4 individual + 1 trust rate
- Dividend rates: 7 rates (basic/higher/additional + trust rates + management expenses)
- Additional Rate upper limit: `null` → "No limit" instead of "£0"
- Validation: income tax band rate max changed from 100 to 1 (decimal storage)

**New tabs (4):**
- Gifting Exemptions: annual/small gifts, wedding gifts, normal expenditure from income, BPR, APR, QSR, CLTs, pension IHT inclusion
- Benefits: Child Benefit (HICBC), Tax-Free Childcare, SSP, ESA, PIP, Universal Credit, Bereavement Support, Early Years Funding (5 schemes), FSCS, Premium Bonds
- Assumptions: inflation, salary growth, property growth, investment growth by asset class, growth by risk profile
- Module Config: Protection (multipliers, IPT, expenses), Retirement (withdrawal rates, annuity estimates, Monte Carlo), asset class yields, investment safety/transfers, domicile rules, trust types reference (9 types)

**Expanded existing tabs:**
- Income Tax: PA taper threshold/rate, Blind Person's Allowance, Personal Savings Allowance (basic/higher/additional), Starting Rate for Savings, Scottish Income Tax (placeholder)
- Savings & Investments: BADR (rate, lifetime limit, ownership), chattel exemptions, VCT/EIS/SEIS (rates, limits, tax treatment badges), onshore/offshore bond tax treatment (detailed descriptions), fee benchmarks, portfolio thresholds
- Pensions: pension tax relief rates, State Pension (full amount, SPA, qualifying years), Auto-Enrolment (earnings trigger, contribution rates), Salary Sacrifice (NLW/NMW rates)
- Inheritance Tax: RNRB taper rate, charity threshold, spouse exemption, transferable NRB/RNRB flags
- Property/SDLT: non-resident surcharge

### Agent hardcoded values removed

**EstateAgent.php** — 7 instances of `0.40`/`0.36` replaced:
- Charity rate analysis (lines 581-582)
- Gifting scenario savings (line 867)
- PET capacity savings (line 997)
- IHT rate fallback (line 1162)
- Gifting strategy scenario (line 1483)
- Property downsizing scenario (lines 1509-1510)
- Trust creation scenario (line 1526)

**TaxOptimisationAgent.php** — 3 instances replaced:
- ISA tax saving: `0.06 * 0.20` → `assumptions.investment_growth.balanced_portfolio * income_tax.bands.0.rate`
- Pension tax saving: `0.40` → `income_tax.bands.1.rate`
- CGT scenario: `0.20` → `capital_gains_tax.higher_rate`

**HasAiChat.php** — fallback values:
- `12570` → `TaxDefaults::PERSONAL_ALLOWANCE`
- `50270` → `TaxDefaults::HIGHER_RATE_THRESHOLD`
- `125140` → `TaxDefaults::ADDITIONAL_RATE_THRESHOLD`

### AI tax tool expansion

**AiToolDefinitions.php** — `get_tax_information` enum expanded:
- Before: `income_tax`, `capital_gains`, `inheritance_tax`, `isa_allowances`, `pension_allowances`
- After: + `national_insurance`, `dividend_tax`, `gifting_exemptions`, `stamp_duty`, `state_pension`, `benefits`, `savings_config`, `assumptions`, `investment_bonds`, `venture_capital`, `protection_config`, `retirement_config`, `domicile`

**CoordinatingAgent.php** — handler expanded with `Cache::remember()` (5-minute TTL per topic)

**System prompt** — Rule 7 added: "NEVER state tax rates from memory. ALWAYS use get_tax_information tool."

## Files Changed

| File | Lines Changed |
|------|--------------|
| `resources/js/components/Admin/TaxSettings.vue` | +1490 |
| `app/Agents/EstateAgent.php` | +29 / -18 |
| `app/Agents/TaxOptimisationAgent.php` | +6 / -6 |
| `app/Traits/HasAiChat.php` | +14 / -8 |
| `app/Agents/CoordinatingAgent.php` | +36 / -16 |
| `app/Services/AI/AiToolDefinitions.php` | +13 / -3 |
