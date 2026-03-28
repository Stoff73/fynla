# CSJTODO — Fynla

*Last updated: 27 March 2026 — session 15*

---

## Session 15 (27 March) — Production Bug Fixes (10 bugs)

### Completed
- [x] Bug #1: Onboarding progress bar red dash on Spending/Debts
- [x] Bug #2: Dashboard "6 of 8 steps complete" mismatch
- [x] Bug #3: Pension monthly contribution £0
- [x] Bug #4: Retirement age shows 67 instead of user target
- [x] Bug #5: ISA allowance £0
- [x] Bug #6: NRB shows £0 in IHT breakdown
- [x] Bug #7: Credit card persists in IHT projection
- [x] Bug #8: Dividend rate "34%" instead of 33.75%
- [x] Bug #9: Chatbot wrong net worth
- [x] Bug #10: Goals net worth mismatch
- [x] All browser tested on dev (full 8-step onboarding with property, mortgage, credit card, dividends)
- [x] All verified on production (chris@fynla.org + peak_earners persona)
- [x] PR #167 merged + follow-up fix pushed to main
- [x] Vault synced — git history, March Index, Home.md updated

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
