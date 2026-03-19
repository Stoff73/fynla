# TODO — Fynla

*Last updated: 19 March 2026*

## CRITICAL — Read shitWork.md First

**`March/March19Updates/shitWork.md` documents serious process failures from this session. All work on the `logicFix` branch MUST be reviewed and end-to-end tested before merging.**

## Outstanding from This Session

### MUST TEST — Simple Expenditure Bug (Original Request)
- [ ] **END-TO-END TEST REQUIRED**: Register new user → onboard with Starting Out → enter simple expenditure (e.g. £1,500) → skip to dashboard → go to Expenditure tab → verify Current Budget shows £1,500 → verify Budget at Retirement shows £1,275 (85%) → verify Budget if Widowed shows estimate (if married)
- [ ] The code changes are in place but a complete unbroken browser test was NOT completed in this session
- [ ] Files involved: `SimpleExpenditureStep.vue`, `ExpenditureForm.vue`, `UserResource.php`, `UserProfileService.php`, `UpdatePersonalInfoRequest.php`

### Must Verify — Income Definitions
- [ ] `IncomeDefinitionsPanel` renders on income tab for real logged-in users (verified for preview personas only)
- [ ] Charitable donations field saves correctly from ExpenditureForm
- [ ] Blind Person's Allowance checkbox saves from IncomeStep/IncomeOccupation

### Must Verify — UserResource Changes
- [ ] Adding ~30 fields to UserResource doesn't break existing sessions or API consumers
- [ ] Auth flow still works correctly with the larger response payload

### Tech Debt
- [ ] `ExpenditureForm.vue` — heavily modified by multiple agents, needs careful review
- [ ] `Error loading profile data: TypeError` console error on every page load — pre-existing but should be investigated
- [ ] Retired/widowed budget for simple mode uses hardcoded 85%/70% estimates — should use TaxConfig or be configurable

### Features (Backlog)
- [ ] PortfolioOptimization.vue:197 — rebalancing plan (coming soon toast)
- [ ] Scottish Income Tax support
- [ ] Pull benefits warnings/thresholds into onboarding helper text and family dashboard

### Production Deployment
- [ ] 4 database migrations need running (3 from March 18 + 1 income definitions)
- [ ] Deploy guide: `March/March19Updates/deploy.md`
- [ ] `UserResource` changes — test auth flow after deploy

## Context for Next Session

This session attempted too much and tested too little. The `logicFix` branch has 15+ commits covering income definitions, benefits config, expenditure bug fixes, and NaN fixes. The core simple expenditure bug fix is likely correct (code changes are sound, DB values verified) but was never fully browser-tested end-to-end in a single unbroken flow due to session expiry and verification code issues.

**Start the next session by:**
1. Reading `March/March19Updates/shitWork.md`
2. Seeding the database
3. Running the end-to-end simple expenditure test described above
4. If it fails, debug properly using systematic-debugging skill
5. Only then consider merging logicFix to main

## Active Branches

| Branch | Status | Description |
|--------|--------|-------------|
| `main` | Stable | onboardFix merged via PR #139 |
| `logicFix` | NEEDS REVIEW | Income definitions + expenditure fixes — see shitWork.md |
