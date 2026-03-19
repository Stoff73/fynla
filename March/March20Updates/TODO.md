# TODO — Fynla

*Last updated: 19 March 2026 (session 2 — evening)*

## Completed This Session

### PR #140 — logicFix → main (Income definitions + expenditure fixes)
- [x] Simple expenditure bug: browser-tested end-to-end (Register → Starting Out → £1,500 → Expenditure tab → Current/Retired budgets correct)
- [x] LetterToSpouse.vue: properties.reduce crash fixed (API returns nested object not array)
- [x] UpdatePersonalInfoRequest: education_level validation whitelist aligned with frontend (added doctorate, foundation, hnd)

### PR #141 — taxConfigFix → main (Admin tax settings overhaul)
- [x] **NaN fixes**: PET taper relief, trust charges — data structure mismatch between seeder and component
- [x] **Rate formatting**: Income tax bands (0.2% → 20%), CGT, dividend, trust rates all multiplied by 100
- [x] **Income tax upper limit**: null → "No limit" for Additional Rate band
- [x] **Validation**: Income tax band rate input max changed from 100 to 1 (decimal storage)
- [x] **568/568 config keys covered**: All TaxConfigService values now visible in admin UI
- [x] **4 new tabs**: Gifting Exemptions, Benefits, Assumptions, Module Config
- [x] **New sections**: VCT/EIS/SEIS (rates, limits, tax treatment), onshore/offshore bond tax treatment, Early Years Funding (5 schemes), SSP/ESA/PIP/UC/Bereavement Support, trust types reference (9 types), domicile rules, asset class yields, retirement annuity estimates, Scottish income tax placeholder
- [x] **Agent hardcoded values removed**: EstateAgent (7 IHT rate instances), TaxOptimisationAgent (3 rate instances), HasAiChat fallbacks — all now use TaxConfigService
- [x] **AI tax tool expanded**: 5 → 18 topics with 5-minute caching per topic
- [x] **System prompt rule 7**: AI instructed to always use get_tax_information tool, never guess values

### PR #142 — aiTools → main (AI CRUD tools + zero-token navigation)
- [x] **4 new create tools**: family members, trusts, business interests, personal valuables
- [x] **Generic update_record**: updates any of 18 entity types by ID, validates ownership + fillable fields
- [x] **Generic delete_record**: deletes any of 18 entity types by ID with ownership check
- [x] **Profile update tool**: 4 sections (personal, income_occupation, expenditure, domicile) — enables AI-driven onboarding
- [x] **resolveModel()**: maps 18 entity types to Eloquent models with ownership check
- [x] **Zero-token navigation**: chatNavigationRouter.js — keyword matching for 25 routes, instant Vue Router navigation, zero API calls
- [x] Browser tested: "show me my goals" → instant navigate to /goals

### PR #143 — uiFix → main (UI fixes)
- [x] **Info guide button**: moved from floating bottom-right to top navbar, left of user name. Raspberry-600 with green badge.
- [x] **Chat renamed**: "Fynla Assistant" → "Fyn" in floating + docked panels
- [x] **Chat session lifecycle**: close clears state, open starts fresh, new conversation clears properly, history toggle fetches fresh data

### dataReadiness branch (in progress)
- [x] **PrerequisiteGateService refactored**: delegates to 5 DataReadiness services instead of duplicating checks
- [x] **Completeness endpoint enriched**: field-level blocking/warnings/completeness_percent per module
- [x] **AI prompt context enriched**: shows field-level blocking reasons + completion percentages
- [x] **Frontend completeness store**: new getters for moduleCompleteness, moduleBlocking, moduleWarnings, overallCompleteness

## Outstanding

### dataReadiness branch — still to do
- [ ] Journey progress calculation should use data completeness (Task 4 from plan)
- [ ] Pest tests for refactored PrerequisiteGateService (Task 6 from plan)
- [ ] PR and merge

### Must Verify — Income Definitions
- [ ] `IncomeDefinitionsPanel` renders on income tab for real logged-in users
- [ ] Charitable donations field saves correctly from ExpenditureForm
- [ ] Blind Person's Allowance checkbox saves from IncomeStep/IncomeOccupation

### Must Verify — UserResource Changes
- [ ] Adding ~30 fields to UserResource doesn't break existing sessions or API consumers
- [ ] Auth flow still works correctly with the larger response payload

### Tech Debt
- [ ] `ExpenditureForm.vue` — heavily modified by multiple agents, needs careful review
- [ ] Retired/widowed budget for simple mode uses hardcoded 85%/70% — should use TaxConfig
- [ ] 4 pre-existing Pest test failures (RecommendationPersonaliser x2, FamilyMembersController, InvestmentModule) — not caused by our changes

### Features (Backlog)
- [ ] PortfolioOptimization.vue:197 — rebalancing plan (coming soon toast)
- [ ] Scottish Income Tax support (placeholder tab added, needs rate bands)
- [ ] Pull benefits warnings/thresholds into onboarding helper text and family dashboard

### Production Deployment
- [ ] 4 database migrations need running (3 from March 18 + 1 income definitions)
- [ ] Deploy guide: `March/March19Updates/deploy.md`
- [ ] `UserResource` changes — test auth flow after deploy
- [ ] All tax config admin changes — TaxSettings.vue is now ~2500 lines

## Active Branches

| Branch | Status | Description |
|--------|--------|-------------|
| `main` | Up to date | PRs #140, #141, #142, #143 merged |
| `dataReadiness` | In progress | PrerequisiteGateService refactor — needs PR/merge |

## PRs Merged This Session

| PR | Branch | Description |
|----|--------|-------------|
| #140 | logicFix | Income definitions + expenditure fixes + LetterToSpouse fix |
| #141 | taxConfigFix | Admin tax settings NaN fixes + 568/568 config coverage + agent hardcoded values |
| #142 | aiTools | Full CRUD AI tools + zero-token navigation |
| #143 | uiFix | Info guide to navbar + chat rename + session lifecycle |
