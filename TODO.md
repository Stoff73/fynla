# TODO — Fynla

*Last updated: 19 March 2026 (session 2 — evening)*

## In Progress

### dataReadiness branch
- [x] PrerequisiteGateService delegates to DataReadiness services
- [x] Completeness endpoint returns field-level detail
- [x] AI prompt context shows field-level blocking
- [x] Frontend completeness store has new getters
- [ ] Journey progress uses data completeness
- [ ] Pest tests for refactored service
- [ ] PR and merge

## Outstanding

### Must Verify
- [ ] `IncomeDefinitionsPanel` renders for real logged-in users
- [ ] Charitable donations + Blind Person's Allowance save correctly
- [ ] UserResource ~30 new fields don't break existing sessions/auth

### Tech Debt
- [ ] `ExpenditureForm.vue` — heavily modified, needs review
- [ ] Retired/widowed budget hardcoded 85%/70% — should use TaxConfig
- [ ] 4 pre-existing Pest test failures (not caused by our changes)

### Features (Backlog)
- [ ] PortfolioOptimization.vue:197 — rebalancing plan
- [ ] Scottish Income Tax support
- [ ] Benefits warnings in onboarding + family dashboard

### Production Deployment
- [ ] 4 database migrations
- [ ] Deploy guide: `March/March19Updates/deploy.md`
- [ ] UserResource auth flow test after deploy

## Session Summary — 19 March 2026

4 PRs merged (#140-#143) covering:
- Simple expenditure bug fix (browser tested)
- Admin tax settings: NaN fixes + 568/568 config coverage + 4 new tabs
- Agent hardcoded tax values → TaxConfigService (EstateAgent, TaxOptimisationAgent)
- AI tax tool: 5 → 18 topics with caching
- AI CRUD tools: 4 new creates + generic update/delete + profile updates
- Zero-token chat navigation (25 routes)
- Info guide button moved to navbar
- Chat renamed to "Fyn" + session lifecycle fixed
- PrerequisiteGateService refactored to delegate to DataReadiness services

Full details: `March/March20Updates/TODO.md`

## Active Branches

| Branch | Status | Description |
|--------|--------|-------------|
| `main` | Up to date | PRs #140-#143 merged |
| `dataReadiness` | In progress | Needs journey progress fix + PR/merge |
