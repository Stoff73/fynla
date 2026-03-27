# CSJTODO — Fynla

*Last updated: 26 March 2026 — session 14*

---

## Session 14 (26 March) — Pension & Investment Edit Fixes + Holdings Detail

### Completed
- [x] Browser tested investment OCF fix — create, save, edit, verify DB
- [x] Fixed Monte Carlo £0 projection (PensionProjector.getUserAge DOB fallback)
- [x] Fixed pension edit always creating duplicates — routes to updateDCPension
- [x] Added holdings sync to pension and investment update controllers
- [x] Fixed beneficiary and policy number persistence in pension
- [x] Added holdings validation to UpdateInvestmentAccountRequest
- [x] Rewrote investment AccountHoldingsPanel to match pension format
- [x] Built and deployed all changes to production
- [x] Merged PR #166, pushed holdings rewrite to main
- [x] Policy number now displaying in pension detail view (was N/A)

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

All pension and investment edit flows now work correctly — holdings sync, OCF, beneficiary, policy number. Monte Carlo projections working. Investment and pension holdings detail views now use the same format. Everything deployed to production and verified working.
