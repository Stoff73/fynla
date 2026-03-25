# Test Issues — 23 March 2026

## Bugs

### BUG-001: Investment Account Edit 422 -- FIXED & DEPLOYED
- **Severity:** High
- **Page:** Investments -> click account -> Edit -> Save without changes
- **Error:** 422 validation + SQLSTATE constraint violation on PUT /api/investment/accounts/{id}
- **Root cause:** AccountForm.vue used `...newAccount` spread to populate formData, pulling in ALL API response fields including computed fields (`user_share`, `annualised_return`), relationship objects (`user`, `joint_owner`), and MissingValue objects from `$this->when()`. On save, these non-form fields caused 422 validation errors or SQLSTATE NOT NULL constraint violations.
- **Fix:** Whitelist approach -- only send fields matching UpdateInvestmentAccountRequest validation rules. All computed, relationship, and MissingValue fields stripped before submission.
- **File:** `resources/js/components/Investment/AccountForm.vue`
- **Verified:** Browser tested on dev server and production (fynla.org). Edit -> Save without changes succeeds.
- **Status:** FIXED & DEPLOYED 23 March 2026

## Warnings

### WARN-001: ApexCharts "undefined color" console warning
- **Severity:** Low (cosmetic)
- **Page:** Investment detail page (Monte Carlo chart)
- **Error:** "undefined color - ApexCharts" in console
- **Impact:** No visible effect on chart rendering
- **Status:** Open

## Pages Tested -- All Pass (except BUG-001)

| Page | Route | Status |
|------|-------|--------|
| Dashboard | /dashboard | PASS |
| Net Worth | /net-worth/wealth-summary | PASS |
| Bank Accounts | /net-worth/cash | PASS |
| Bank Account Detail + Edit | /net-worth/cash (detail) | PASS |
| Income | /valuable-info?section=income | PASS |
| Investments | /net-worth/investments | PASS |
| Investment Detail | /net-worth/investments (detail) | PASS |
| Investment Edit | /net-worth/investments (edit) | FAIL (BUG-001) |
| Retirement | /net-worth/retirement | PASS |
| Pension Detail + Edit | /net-worth/retirement (detail) | PASS |
| Property | /net-worth/property | PASS |
| Property Detail + Edit | /net-worth/property (detail) | PASS |
| Liabilities | /net-worth/liabilities | PASS |
| Personal Valuables | /net-worth/chattels | PASS |
| Business | /net-worth/business | PASS |
| Risk Profile | /risk-profile | PASS |
| Protection | /protection | PASS |
| Will Builder | /estate/will-builder | PASS |
| Expression of Wishes | /valuable-info?section=letter | PASS |
| Trusts | /trusts | PASS |
| Estate Planning | /estate | PASS |
| Power of Attorney | /estate/power-of-attorney | PASS |
| Holistic Plan | /holistic-plan | PASS |
| Plans | /plans | PASS |
| Journeys | /planning/journeys | PASS |
| What If Scenarios | /planning/what-if | PASS |
| Goals | /goals | PASS |
| Actions | /actions | PASS |
| Settings | /settings | PASS |
| Onboarding Wizard (all 6 steps) | /onboarding | PASS |
| Fyn AI Chat | (panel) | PASS |

## Forms Tested

| Form | Action | Result |
|------|--------|--------|
| Property Edit (save without changes) | PUT /api/properties/{id} | PASS (422 fix deployed) |
| Bank Account Edit (save without changes) | PUT /api/savings/accounts/{id} | PASS |
| Investment Edit (save without changes) | PUT /api/investment/accounts/{id} | FAIL (422) |
| Pension Edit (save without changes) | PUT /api/retirement/pensions/{id} | PASS |
| Onboarding Income (save £150k) | POST /api/user/profile | PASS |
| Onboarding Goal (select Emergency Fund) | Conditional fields appear | PASS |

## Fyn Navigation Testing — ALL 30 ROUTES PASS

Tested on production as c.jones@csjones.co. All routes are zero-token (client-side `chatNavigationRouter.js`). Every route navigated correctly using "show me [keyword]" trigger.

30/30 routes tested and passed: Dashboard, User Profile, Settings, Security Settings, Planning Assumptions, Wealth Summary, Bank Accounts, Investments, Pensions, Property, Business, Chattels, Liabilities, Income, Expenditure, Letter to Spouse, Protection, Estate Planning, Will Builder, Power of Attorney, Trusts, Goals, Life Events, Risk Profile, Holistic Plan, Plans, Actions, Journeys, What-If, Help.

### WARN-002: Security Sessions API 500
- **Severity:** Low
- **Page:** /settings/security
- **Error:** 500 on GET /api/auth/sessions
- **Status:** Open (pre-existing)

### WARN-003: Vue Error on Holistic Plan
- **Severity:** Low
- **Page:** /holistic-plan
- **Error:** "Cannot read properties of undefined" (Vue runtime)
- **Status:** Open (pre-existing)
