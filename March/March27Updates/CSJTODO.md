# CSJTODO — Fynla

*Last updated: 27 March 2026 — session 15*

---

## Session 15 (27 March) — Production Bug Fixes (10 bugs)

### Completed
- [x] Bug #1: Onboarding progress bar red dash on Spending/Debts — added missing steps to backend journey builders
- [x] Bug #2: Dashboard "6 of 8 steps complete" — same root cause as #1
- [x] Bug #3: Pension monthly contribution £0 — added percentage calculation helper to AssetsStep.vue
- [x] Bug #4: Retirement age shows 67 — added user target_retirement_age fallback
- [x] Bug #5: ISA allowance £0 — added S&S ISA estimation from monthly contributions
- [x] Bug #6: NRB shows £0 in IHT breakdown — exposed IHT_NIL_RATE_BAND to Vue template
- [x] Bug #7: Credit card persists in IHT projection — fixed end_date to maturity_date, added payoff estimation
- [x] Bug #8: Dividend rate "34%" — fixed formatPercent to preserve decimals (33.75%)
- [x] Bug #9: Chatbot wrong net worth — added NetWorthService to AI system prompt instead of estate net_estate
- [x] Bug #10: Goals net worth mismatch — fixed first-year snapshot, income crediting, added business + chattels
- [x] All 10 bugs browser tested on dev server (full 8-step onboarding journey filled and submitted)
- [x] All 10 bugs verified on production (fynla.org) — chris@fynla.org + peak_earners persona
- [x] Merged PR #167 to main
- [x] Follow-up fix for Bug #10 (business + chattels) pushed directly to main

### Files Changed
- `app/Services/LifeStage/LifeStageService.php` — 4 journey builders updated
- `app/Http/Controllers/Api/RetirementController.php` — retirement profile fallback
- `app/Services/Savings/ISATracker.php` — S&S ISA usage estimation
- `app/Services/Estate/IHTCalculationService.php` — liability field fix + payoff estimation
- `app/Traits/HasAiChat.php` — chatbot net worth from NetWorthService
- `app/Services/Goals/GoalsProjectionService.php` — projection fixes (2 commits)
- `resources/js/components/Onboarding/steps/AssetsStep.vue` — pension contribution + retirement age
- `resources/js/components/Estate/IHTCalculationTable.vue` — NRB constant exposure
- `resources/js/components/UserProfile/TaxIncomeCard.vue` — formatPercent decimals

---

## Outstanding Items

### Tier 3 (Future — from feesMap.md)
- [ ] Standardise all fee thresholds into PlanConfigService
- [ ] Cross-module fee dashboard

### Next Priority Tasks
- [ ] Test expenditure AI fill on production
- [ ] Verify admin AI Provider panel shows Anthropic/xAI toggle cards

### Tech Debt
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution
- [ ] LiabilitiesStep.vue: DEPRECATED comment
- [ ] IncomeStatementTab.vue: orphaned
- [ ] DB enum missing step_child/partner
- [ ] ProfileCompletenessAlert.vue: orphaned component
- [ ] AccountForm.vue: pre-existing console.log in AI Fill watcher

### Known Issues
- [ ] DB pension field mapping mismatch (employer_name vs scheme_name)
- [ ] Expenditure form fill doesn't animate through form

## Context for Next Session

10 production bug fixes deployed and verified. All reported by user brett.isenberg@capitoul.co.uk on 26 March. Key architectural findings:
- Backend step field configs must match frontend lifeStageConfig.js step lists
- IHT estate net_estate is NOT the same as net worth — chatbot was using wrong data source
- Goals projection was missing business + chattels asset categories
- Vue Options API components need constants exposed via data() to use in templates
- Liability model uses maturity_date not end_date
