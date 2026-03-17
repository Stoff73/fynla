# Journey 2: Building Foundations (Early Career)

**Stage ID:** `early_career`
**Steps:** personal-info, income-career, savings-emergency, first-home-lisa, pension-auto-enrolment, investments, goals
**Status:** COMPLETE — 6 of 7 steps filled (investments step blank), dashboard loaded with data

## Issues Found

### Issue 22: Investments step renders blank
- **Found at:** Step 6 (Investments)
- **Problem:** Investment AccountForm component doesn't render. Left column empty with only nav buttons. Vue warning: "Missing required prop: show" — AccountForm is a modal component that requires a `show` prop.
- **Root cause:** AccountForm wasn't adapted for `context='onboarding'` like SaveAccountModal was. It still expects modal props.
- **Status:** NOT YET FIXED — skipped this step

### Issue 23: Goal type values mismatch (FIXED)
- **Found at:** Step 7 (Goals) — "The given data was invalid" 422 error
- **Problem:** Frontend used `house_deposit`, `car`, `home_improvement`, `other`. Backend StoreGoalRequest accepts `home_deposit`, `car_purchase`, `debt_repayment`, `custom`.
- **Fix:** Updated GoalSetupStep.vue option values and labels to match backend validation.
- **Status:** FIXED

### Same issues as Journey 1:
- Issue 19: Progress shows 5/7 instead of 7/7 (only counted 5 complete)
- Issue 20: "Next: About You" suggests completed step
- Issue 15: Duplicate nav buttons on savings/liability forms

## Steps Tested (All Fields Filled)

1. **Registration** — 6 fields (Sarah Jane Builder, test.builder@test.com)
2. **Verification** — Code entered
3. **Stage Selection** — "Building Foundations" selected, 7-step journey map correct
4. **Step 1: About You** — 15 fields: Name, Email, DOB (22/03/1996), Gender (F), Marital Status (Single), Phone, Address (42 Victoria Road, Flat 3B, Leeds, West Yorkshire, LS1 4DL), Job Title (Financial Analyst), Employer (Deloitte), Industry (Professional Services), Employment Status (Employed). Green checkmark.
5. **Step 2: Income & Career** — 9 fields: Status (Employed), Occupation (Financial Analyst), Employer (Deloitte), Industry (Professional Services), Retirement Age (67), Employment Income (£38,000), Dividend (£0), Interest (£150), Other (£0). Green checkmark.
6. **Step 3: Emergency Fund** — 8 fields: Institution (Marcus), Product Type (Easy Access), Balance (£4,500), Rate (4.75%), Access (Immediate), Emergency Fund (checked), Ownership (Individual), Account# (3456). Green checkmark.
7. **Step 4: First Home (LISA)** — 7 fields: Institution (AJ Bell), Product Type (Lifetime ISA), Balance (£8,000), Rate (3.8%), Access (Immediate), Ownership (Individual), Account# (9012). Green checkmark.
8. **Step 5: Pension** — 13 fields: Type (Workplace), Scheme (Deloitte Workplace Pension), Provider (Legal & General), Policy# (WP789012), Fund Value (£12,500), Annual Salary (£38,000), Employee% (5), Employer% (3), Return (5%), Risk (Medium), Salary Sacrifice (checked), Beneficiary (Margaret Builder), Notes. Green checkmark.
9. **Step 6: Investments** — BLANK (Issue 22). Skipped.
10. **Step 7: Goals** — 3 fields: Type (House Deposit), Amount (£30,000), Date (01/06/2030). Green checkmark.

## Dashboard Results

- Net Worth: £25,000 assets, £0 liabilities — CORRECT
- Cash & Savings: £12,500 (Marcus £4,500 + AJ Bell £8,000) — CORRECT
- Investments: £0, 0 accounts — correct (step skipped)
- Retirement: Projected Income £6,801/yr, Required £23,557/yr, Capital £144,712, Age 67, 37 years — CORRECT
- Goals chart with retirement marker — CORRECT
- Suggested goals: House deposit, emergency fund, investing, wedding — CORRECT for stage
- Sidebar: Dashboard, Bank Accounts, Income, Expenditure, Savings, Investments, Retirement, Goals — CORRECT (more items than Starting Out)
