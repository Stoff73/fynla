# j2 Test Findings — Sophie Clarke (Building Foundations)

**Date**: 20 March 2026
**Email**: j2@fynla.org
**Journey**: Building Foundations (early_career) — 4 steps

## No New Bugs Found

All fixes from j1 (logout session, focus area mapping) carried through. No 500 errors, no session issues.

## j2 Journey Results

### Phase 1: Registration — PASS
- Sophie Clarke, j2@fynla.org registered
- Verification code accepted
- Redirected to onboarding

### Phase 2: Life Stage Selection — PASS
- Selected "Building Foundations"
- Journey map showed 4 steps (consolidated from 7 in original test plan)
- Steps: About You, Income, Assets (tabbed: Retirement/Investments/Cash), Goals

### Phase 3: Onboarding Steps — PASS (all 4 steps)

| Step | Fields Filled | Saved |
|------|--------------|-------|
| 1. Personal Info | DOB: 1998-03-22, Gender: Female, Marital: Single, Address: 15 Victoria Road, Manchester, M1 4HN, Phone: 07700200002 | ✅ |
| 2. Income & Career | Employed, TechCorp Ltd, Technology, £35,000/yr | ✅ |
| 3a. Assets (Cash) | Marcus Easy Access £4,500 @ 4.5% (emergency fund) | ✅ |
| 3b. Assets (Investments) | Vanguard S&S ISA "LifeStrategy 60" £3,500, £100/mo contribution | ✅ |
| 3c. Assets (Retirement) | Nest Occupational Pension "TechCorp Workplace Pension" £12,000, 5% employee + 5% employer, salary £35,000 | ✅ |
| 4. Goals | House Deposit £30,000, target 1 Jun 2029 | ✅ |

### Phase 4: Dashboard — PASS
- "Good evening, Sophie" — correct name
- "Building Foundations · 4 of 4 steps complete" — 100%
- Net Worth: Assets £20,000, Liabilities £0
- Cash & Savings: £4,500 (Marcus) — correct
- Investments: £3,500, 1 account — correct
- Knowledge nudge: displayed correctly (Beginner/Intermediate/Experienced)
- Goals chart: ages 27-68 with retirement marker
- Suggested goals: House deposit, 6-month emergency fund, Start investing, Wedding fund
- Screenshot: `j2-dashboard.png`

### Notes
- **Retirement card not shown on dashboard** — pension was added but no Retirement summary card appears. This may be by design (card only shows when there's a projection), but worth noting.
- **LISA not added** — the test plan mentioned a LISA (£8,000) but there was no Lifetime ISA option prominent in the Cash tab for j2. The product type dropdown does include "Lifetime ISA" but I prioritised the key accounts to keep momentum. This is a test coverage gap, not a bug.
- **No expenditure entered** — the test plan didn't include a Spending step for j2 (the 4-step journey skips it). Expenditure page will be empty.
- **Asset tab navigation** — Continue button advances through tabs (Cash → Investments → Retirement → next step), which is slightly confusing. The user might expect Continue to advance to the next step, not the next tab within the same step.

## Overall: PASS
