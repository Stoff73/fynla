# TODO — Fynla

*Last updated: 19 March 2026*

## Completed This Session

### Tech Debt & Code Quality
- [x] `error-*` → `raspberry-*` token standardisation across 43 Vue files + removed `error`/`warning`/`info` legacy color definitions from `tailwind.config.js` and `app.css`
- [x] `FinancialHealthScore.vue` — deleted component, store, service, controller, route, and all tests (14 files modified/deleted). Rule 13 compliance.
- [x] Monte Carlo consolidation — `MonteCarloSimulator` now extends `MonteCarloEngine`. Eliminated ~100 lines of duplicated code. All 38 tests pass.
- [x] `HouseholdPlanningService` hardcoded rates — verified, already uses `TaxConfigService` correctly. No change needed.
- [x] `console.log` cleanup — already complete. Only 20 in infrastructure files (legitimate).

### Bug Fixes
- [x] DashboardApiTest 500 errors — `AdvisorImpersonationMiddleware` crashed on `TransientToken::$id`. Fixed by checking for `PersonalAccessToken`. All 27 dashboard tests pass.
- [x] PreviewUserSeeder risk_profiles unique constraint violation — `create()` → `updateOrCreate()`. Database seeding now works.

### Cleanup
- [x] Branch cleanup — deleted 11 local branches, 3 remote branches, removed 10 worktrees. All merged into main.

### UI Changes (onboardFix branch — merged via PR #139)
- [x] Removed age ranges from all journey/life stage cards (6 files)
- [x] Removed "Why this matters" helper text from 14 onboarding form steps (17 occurrences)
- [x] Added external resource links to 18 onboarding steps — centralised registry (`onboardingLinks.js`), shared `UsefulResources.vue` component, inline + grouped links from Gov.uk, MoneyHelper, MoneySavingExpert, Which?, StepChange
- [x] Removed "focus on a specific area" section from onboarding welcome
- [x] Added welcome message and onboarding intro ("Welcome to Fynla" + what you'll get out of it)

### Income Definitions & Tax Logic (logicFix branch)
- [x] Migration: `is_registered_blind`, `annual_charitable_donations`, `is_gift_aid` on users table
- [x] TaxConfig: Blind Person's Allowance (£2,870 for 2025/26) + `getBlindPersonsAllowance()` helper
- [x] `IncomeDefinitionsService`: 5 HMRC income definitions (Total, Net, Adjusted Net, Threshold, Adjusted) + PA/AA taper — 14 tests
- [x] API endpoint: `GET /api/tax/income-definitions`
- [x] `AnnualAllowanceChecker` refactored to use proper HMRC definitions
- [x] `IncomeDefinitionsPanel.vue`: waterfall display at bottom of income tab
- [x] Blind checkbox on income steps + charitable donations with Gift Aid toggle on expenditure steps
- [x] Rental income computed from Property model, pension income from DB/State pensions (not stale User fields)
- [x] Backend validation + OnboardingService whitelist + preview persona seed data
- [x] Benefits config: Tax-Free Childcare, Early Years Funding, enhanced Child Benefit with age limits, earning thresholds, and structured warnings

## Outstanding

### Features (Backlog)
- [ ] PortfolioOptimization.vue:197 — rebalancing plan creation (button shows "coming soon" toast, needs backend model/service/controller)
- [ ] Scottish Income Tax support — no Scottish rate bands implemented. Needs `TaxConfigService` extension + income tax calculator updates.
- [ ] Pull benefits warnings/thresholds into onboarding helper text and family dashboard (data now in TaxConfig, needs frontend integration)

### Production Deployment
- [ ] 4 database migrations need running (3 from March 18 + 1 new income definitions migration)
- [ ] `UserResource` in auth responses may break frontend if it expects fields that are no longer returned — needs browser testing after deploy
- [ ] Sanctum token expiration reduced from 8hr to 4hr — may cause unexpected logouts for long sessions
- [ ] Deploy guide: `March/March18Updates/deployReview.md` and `March/March18Updates/allDeploy.md`

## Change Documents

| # | File | Description |
|---|------|-------------|
| 01 | `01-error-raspberry-token-standardisation.md` | error-* → raspberry-* across 43 Vue files |
| 02 | `02-financial-health-score-removal.md` | FinancialHealthScore feature deletion |
| 03 | `03-dashboard-test-fix.md` | AdvisorImpersonationMiddleware TransientToken fix |
| 04 | `04-seeder-unique-constraint-fix.md` | PreviewUserSeeder updateOrCreate fix |
| 05 | `05-monte-carlo-consolidation.md` | MonteCarloSimulator extends MonteCarloEngine |
| 06 | `06-journey-age-range-removal.md` | Age ranges removed from journey cards |
| 07 | `07-branch-cleanup.md` | 11 branches + 10 worktrees cleaned up |
| 08 | `08-why-this-matters-removal.md` | "Why this matters" helper text removed |
| 09 | `09-onboarding-external-links-design.md` | External links design spec |
| 09 | `09-onboarding-external-links-plan.md` | External links implementation plan |
| 10 | `10-income-definitions-design.md` | Income definitions & adjusted allowances design spec |
| 10 | `10-income-definitions-plan.md` | Income definitions implementation plan |
| 11 | `11-benefits-config-additions.md` | Tax-Free Childcare, Early Years, Child Benefit warnings |
| — | `deploy.md` | Full deployment guide for this session |

## Active Branches

| Branch | Status | Description |
|--------|--------|-------------|
| `main` | Up to date | All onboardFix changes merged |
| `logicFix` | In progress | Income definitions + benefits config complete, needs PR/merge |
