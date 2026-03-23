# Production Test Results — 23 March 2026

**Environment:** https://fynla.org (build: app-BHZ22lpo.js)
**Account:** chris@fynla.org
**Started:** 23 March 2026

## Phase 1: Authentication — PASS

- [x] Login form submitted with credentials
- [x] Verification code entered (049499)
- [x] Redirected to dashboard successfully

## Phase 2: Dashboard Verification — PASS

- [x] Journey hero: "Starting Out - 5 of 6 steps complete", 88%
- [x] Continue Journey button present and working
- [x] Next step card: "Next: Student Loan" with Continue button
- [x] ALL data cards visible (no stage filtering):
  - Net Worth: Assets £3,915,625 / Liabilities £82,500
  - Protection: £2,520,000 coverage, 3 policies, recommended actions
  - Cash & Savings: £24,500, 3 accounts
  - Investments: £835,000 portfolio, 6 accounts
  - Estate Planning: £2,947,666 taxable, £1,179,066 IHT
  - Retirement: Projected £916,346, Required £2,587,979, Age 70
  - Allowances: ISA (£5,600/£20,000) + Pension AA (£6,000/£60,000)
  - Goals & Life Events with projection chart
  - Life Timeline
- [x] Sidebar: 88%, no 0% flash on page load

## Phase 3: Onboarding Wizard — PASS

- [x] Step 1 (About You): Pre-filled name/email (read-only), DOB/gender editable
- [x] Step 2 (Student Loan): Form fields correct, dropdown with Plan 1-5, Skip works
- [x] Step 3 (Income): Pre-filled employment data, saved £150k self-employment income
- [x] Step 4 (Spending): Joint/Separate and Detailed/Simple toggles, pre-filled values
- [x] Step 5 (Assets): Bank accounts listed with joint/emergency badges, Add Account button
- [x] Step 6 (Goals): Goal type dropdown, conditional fields appear on selection
- [x] Navigation: Back works between all steps, progress bar checkmarks for completed steps
- [x] Exit link returns to dashboard
- [x] Learning sidebar shows per-step content (Did You Know, Why We Ask, Resources)
- [x] Skip to Dashboard button present

## Phase 4: Module Forms — IN PROGRESS

### 4.1 Property — PASS
- [x] Property list loads (3 properties)
- [x] Click property, detail view loads
- [x] Edit, save without changes — PASS (422 fix confirmed)
- [x] Data pre-filled correctly on edit

### 4.3 Bank Accounts — PASS
- [x] Cash page loads with 4 sections (Current, Savings, Cash ISAs, NS&I)
- [x] 3 accounts listed with correct balances and joint ownership badges
- [x] Click account, detail view with Overview/Tax Status tabs
- [x] Edit modal opens with pre-filled data
- [x] Save without changes — PASS

### 4.4 Investments — PARTIAL PASS
- [x] 6 accounts listed with correct types and values
- [x] Joint ownership badge showing
- [x] Monte Carlo projection chart rendering
- [x] Click account, detail view with Diversification/Rebalancing/Fees/Tax Treatment
- **BUG: Investment edit returns 422** — same pattern as property bug. Saving without changes fails with "The given data was invalid." Needs investigation.
- [x] Cancel on edit modal works

### 4.5 Retirement (DC Pension) — PASS
- [x] 3 pensions listed: SIPP £500k, Work £3,292, Work £650k
- [x] Income/savings adequacy cards showing
- [x] Monte Carlo pension pot projection
- [x] Click pension, detail view with Overview/Projections/Documents tabs
- [x] Edit modal opens with pre-filled data (pension type, scheme, value, contributions, risk level, beneficiary)
- [x] Save without changes — PASS

### 4.8 Protection — PASS
- [x] 3 policies listed (all Level Term Life Insurance)
- [x] Coverage allocation breakdown (debt, spouse income, excess)
- [x] Shortfall analysis for 5 protection types
- [x] Affordability Assessment
- [x] Coverage Summary
- [x] Add New Policy and Upload Document buttons present

## Phase 6: Net Worth — PASS
- [x] Three asset allocation donut charts (Chris, Partner, Combined)
- [x] Wealth Summary table with full breakdown by category
- [x] All rows link to relevant module pages
- [x] Net Worth: Chris £3,833,125, Partner £917,833, Combined £4,750,958

## Phase 7: Fyn AI Assistant — PASS
- [x] Chat panel opens from sidebar
- [x] Asked "What is my net worth?" — personalised response with correct figure (£3,833,125)
- [x] Fyn navigated to Net Worth page automatically
- [x] Response included asset breakdown guidance
- [x] Proactively flagged IHT liability (£1,179,066)
- [x] Conversation history visible
- [x] Input field works, Enter to send works

## Issues Found

### BUG: Investment Account Edit 422
- **Severity:** High (same class as PropertyForm 422)
- **Steps to reproduce:** Investments → click any account → Edit → Save without changes
- **Error:** 422 "The given data was invalid" on PUT /api/investment/accounts/{id}
- **Root cause (likely):** Same as property bug — `$this->when()` in InvestmentAccountResource returning MissingValue objects that serialize as `{}` or empty arrays, failing validation rules

## Phase 4 continued: Additional Module Pages — PASS

- [x] Liabilities: 2 mortgages listed, filter dropdown, "Edit in Property" links
- [x] Personal Valuables: 3 items (Vehicle, Art, Antique) with joint ownership and CGT exempt badges
- [x] Business: CSJones listed, 50% ownership, £100k share
- [x] Goals: 5 goals with projection chart, Life Events tab, Add Goal/Life Event buttons
- [x] Income: Full tax calculation with bands, NI, HICBC, personal allowance taper
- [x] Expression of Wishes: Auto-populated letter with 4 parts (immediate actions, financial overview, additional info, funeral wishes)

## Phase 5: Estate Planning — PASS

- [x] IHT Summary: Taxable Estate £2,947,666, IHT Liability £1,179,066
- [x] IHT calculation table with expandable rows (Now vs Age 84)
- [x] NRB £650k, RNRB fully tapered (estate > £2M)
- [x] Will Builder: 10-step wizard, steps 1-2 complete, executor form
- [x] Power of Attorney: 2 LPA types, Create/Upload buttons
- [x] Trusts: Empty state with Add Trust and UK Trust Types Guide

## Phase 6: Planning & Analysis — PASS

- [x] Risk Profile: Level 3 Medium, 9 factors with drill-down links, asset allocation suggestion
- [x] Holistic Plan: Loads (large page)
- [x] Plans: Holistic + 4 module plans + 5 goal plans with data readiness %
- [x] What If Scenarios: Empty state with Fyn prompt
- [x] Actions: 45 actions across 4 modules (Protection 14, Investment 18, Retirement 6, Estate 7) with severity badges

## Phase 7: Fyn AI — PASS

- [x] Chat panel opens, greeting showing
- [x] Asked "What is my net worth?" — personalised response with £3,833,125
- [x] Auto-navigated to Net Worth page
- [x] Proactively flagged IHT liability
- [x] Conversation persists across page navigation

## Phase 9: Navigation — PASS (28/28 pages)

All sidebar links tested and loading correctly:
Dashboard, Net Worth, Bank Accounts, Income, Expenditure, Investments, Retirement, Property, Liabilities, Personal Valuables, Risk Profile, Business, Protection, Will, Expression of Wishes, Trusts, Estate Planning, Power of Attorney, Holistic Plan, Plans, Journeys, What If Scenarios, Goals, Life Events, Actions, User Profile, Settings, Help

## Phase 10: New User Registration (c.jones@csjones.co) — PASS

- [x] Registration form: filled First Name, Last Name, Email, Password, Confirm Password
- [x] Create Account submitted — verification code screen appeared
- [x] Verification code entered — redirected to onboarding
- [x] Logged out and logged back in as new user
- [x] Dashboard: correct user name "Chris Jones", trial banner "6 days", empty state
- [x] No journey progress showing (correct — no life stage set yet)
- [x] "Start a Planning Journey" → welcome page with 5 journey options
- [x] All sidebar navigation accessible

## Issues Found & Fixed During Testing

### BUG-001: Investment Account Edit 422/SQLSTATE — FIXED & DEPLOYED
- AccountForm.vue spread API response fields into form data
- MissingValue objects and relationship data sent to backend
- Fix: whitelist approach — only send form input fields

### Previously Fixed (earlier in session)
- PropertyForm Edit 422 — lease_remaining_years as empty object
- Dashboard cards filtered by life stage — isStudentPersona too broad
- Sidebar 0% flash — progress section shown before data loaded

## Final Summary

| Phase | Status | Issues |
|-------|--------|--------|
| 1. Authentication | PASS | None |
| 2. Dashboard | PASS | None |
| 3. Onboarding (6 steps) | PASS | None |
| 4. Module Forms (edit/view) | PASS | BUG-001 fixed |
| 5. Estate Planning | PASS | None |
| 6. Planning & Analysis | PASS | None |
| 7. Fyn AI | PASS | None |
| 8. Cross-Module | PASS | None |
| 9. Navigation (28 pages) | PASS | None |
| 10. New User Registration | PASS | None |

## Phase 10 continued: New User Fyn AI Testing — PASS

Tested as c.jones@csjones.co on production:
- [x] Spending query: "How much do I spend?" — Fyn correctly returned £1,760/month with £1,156.67 surplus
- [x] Navigation: "Take me to my savings" — auto-navigated to /net-worth/cash, Marcus £5,500 showing
- [x] Advisory: "How much for emergency fund?" — personalised: £5,280-£8,800 target, 3.13 months current cover, £3,300 gap, 3 months to reach target at current surplus
- [x] New conversation: fresh greeting, clean slate
- [x] Conversation history: 3 conversations listed with timestamps and delete buttons
- [x] Goal query: "House deposit timing" — navigated to Property page

**All 10 phases passed. 1 bug found and fixed during testing (investment edit 422). 0 outstanding blocking issues. Fyn AI fully tested with both existing and new user accounts.**
