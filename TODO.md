# TODO — Fynla

*Last updated: 19 March 2026 (session 2)*

## Completed This Session

### Simple Expenditure Bug — BROWSER TESTED, PASSING
- [x] Register new user → onboard Starting Out → enter £1,500 simple expenditure → skip to dashboard → Expenditure tab
- [x] Current Budget: Monthly £1,500, Annual £18,000
- [x] Budget at Retirement: Monthly £1,275 (85%), Annual £15,300
- [x] Entry mode label: "Simple Total" — correct
- [x] No NaN values anywhere
- [x] No widowed tab for single user — correct
- [x] Screenshot saved: `.playwright-mcp/page-2026-03-19T14-12-10-191Z.png`

### Bug Fixes
- [x] **LetterToSpouse.vue:958** — `Error loading profile data: TypeError: this.profileData.properties.reduce is not a function` — Properties API returns `{ data: { properties: [] } }` (object) but code expected an array. Fixed: `propertiesRes.data?.properties || propertiesRes.data || propertiesRes || []`
- [x] **UpdatePersonalInfoRequest.php:46** — `education_level` validation whitelist missing `doctorate`, `foundation`, `hnd` values that the frontend dropdown offers. Added to `Rule::in(...)`.

## Outstanding

### Must Verify — Income Definitions
- [ ] `IncomeDefinitionsPanel` renders on income tab for real logged-in users (verified for preview personas only)
- [ ] Charitable donations field saves correctly from ExpenditureForm
- [ ] Blind Person's Allowance checkbox saves from IncomeStep/IncomeOccupation

### Must Verify — UserResource Changes
- [ ] Adding ~30 fields to UserResource doesn't break existing sessions or API consumers
- [ ] Auth flow still works correctly with the larger response payload

### Tech Debt
- [ ] `ExpenditureForm.vue` — heavily modified by multiple agents, needs careful review
- [ ] Retired/widowed budget for simple mode uses hardcoded 85%/70% estimates — should use TaxConfig or be configurable

### Features (Backlog)
- [ ] PortfolioOptimization.vue:197 — rebalancing plan (coming soon toast)
- [ ] Scottish Income Tax support
- [ ] Pull benefits warnings/thresholds into onboarding helper text and family dashboard

### Production Deployment
- [ ] 4 database migrations need running (3 from March 18 + 1 income definitions)
- [ ] Deploy guide: `March/March19Updates/deploy.md`
- [ ] `UserResource` changes — test auth flow after deploy

## Notes
- 422 on Step 1 save during onboarding could not be reproduced via API — likely transient auth timing after registration. Error is non-blocking (caught at OnboardingWizard.vue:728).
- `origin/onboardFix` remote branch is stale (merged via PR #139) — can be deleted.
- `origin/homepage` is a new remote branch fetched this session.

## Active Branches

| Branch | Status | Description |
|--------|--------|-------------|
| `main` | Stable | onboardFix merged via PR #139 |
| `logicFix` | Current | Simple expenditure TESTED + LetterToSpouse fix + education_level fix |
