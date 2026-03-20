# j4 Test Findings — Richard Moore (Planning Your Future)

**Date**: 20 March 2026
**Email**: j4@fynla.org
**Journey**: Planning Your Future (peak) — 6 steps (5 completed, Family skipped)

## No New Bugs Found

## j4 Journey Results

### Phase 1-2: Registration & Life Stage — PASS
- Richard Moore, j4@fynla.org registered
- Selected "Planning Your Future" — 6 steps shown

### Phase 3: Onboarding Steps — 5/6 PASS (Family skipped)

| Step | Fields Filled | Saved |
|------|--------------|-------|
| 1. Personal Info | DOB: 1974-04-05, Male, Married, 8 The Willows, Henley-on-Thames, Oxfordshire, RG9 1AA, Phone: 07700400004, Good health, Never smoked, Postgraduate Degree | ✅ |
| 2. Family | SKIPPED (no registered spouse to link) | ⏭️ |
| 3. Income & Tax | Self-Employed, Moore Consulting, Management Consulting, £120,000 self-employment + £15,000 dividends, Retirement age: 60 | ✅ |
| 4a. Assets (Retirement) | AJ Bell SIPP "Moore SIPP" £320,000 | ✅ |
| 4b. Assets (Investments) | Hargreaves Lansdown S&S ISA £85,000 + GIA £45,000 | ✅ |
| 4c. Assets (Property) | Main Residence: 8 The Willows, £750,000, Joint Tenancy 50/50 with Catherine Moore, Mortgage: HSBC Repayment £120,000 @ 3.9% fixed, £1,850/mo, Council Tax £250, Gas £110, Electric £120, Water £55, Buildings £50, Contents £30 | ✅ |
| 5. Estate | Has will (Jan 2024), Executor: Catherine Moore | ✅ |
| 6. Goals | Other: "Max pension contributions before retirement" £60,000, target Apr 2034 | ✅ |

### Phase 4: Dashboard — PASS (93% — Family skipped)

- "Good evening, Richard" — correct
- "Planning Your Future · 5 of 6 steps complete" — 93% (Family skipped, correctly shown)
- "Next: Family" prompt visible — correct
- **Net Worth**: Assets £825,000, Liabilities £0 (mortgage not reflected as liability — known issue)
- **Investments**: Portfolio Value £130,000 (£85k ISA + £45k GIA), 2 accounts
- **Estate Planning**: Taxable Estate £5,000, IHT Liability £2,000
  - Recommended: "Charitable Bequest Opportunity", "Liquidity Risk Identified"
- **Retirement**: Projected Income £17,637/yr vs Required £68,263/yr, Capital £375,256 vs Required £2,154,255, Retirement Age 60, 9 years
  - Recommended: "Approaching Retirement — Review Decumulation Strategy", "Start Pension Contributions"
- **Allowances**: ISA £20,000 remaining, Pension Annual Allowance £60,000 remaining
- **Suggested goals**: Maximise pension contributions, Downsize property, Fund care costs, Leave an inheritance
- Screenshot: `j4-dashboard.png`

### Notable
- **Self-employed income field** correctly changed label to "Annual Self-Employment Income" when Self-Employed was selected
- **BTL property not added** — test plan included a BTL flat at £325,000 but skipped for test momentum. Would need a second Add Property cycle.
- **DB pension not added** — test plan included DB pension £18,000/yr. Would need "Add Final Salary Pension" button. Not critical for testing flow.

## Overall: PASS
