# CSJTODO — Fynla

*Last updated: 26 March 2026 — sessions 11-13*

---

## Session 11 (26 March) — Pension Detail View: Holdings Tab + Fee Display + OCF Input

### Completed
- [x] Holdings moved to dedicated tab, fee display updated, OCF input on holdings form
- [x] Merged to main (PR #162)

## Session 12 (26 March) — Fee System Gaps

### Completed
- [x] 3 pension fee actions, projection fixes, 2 protection premium actions, 10-year impact, premium_frequency migration
- [x] Merged to main (PR #163)

## Session 13 (26 March) — Protection UI + Eval Gate + Investment OCF Fix

### Completed
- [x] Remove domicile from completeness, simplify No Coverage card, unify to InfoGuidePanel
- [x] Merged to main (PR #164)
- [x] Eval-gate skill + eval-reviewer agent infrastructure

### REVERTED — Needs Browser Testing
- [ ] **Investment OCF fix** — PR #165 merged then reverted. Fix on `investmentFix-v2` branch. Adds `ocf_percent` to InvestmentController creation, StoreInvestmentAccountRequest validation, AccountForm edit loading. **MUST browser test before re-merging.**

---

## Outstanding Items

### CRITICAL — First Thing Next Session
- [ ] Browser test investment OCF fix on `investmentFix-v2` — create investment with holdings + OCF, verify saves, edit and verify loads back, then merge
- [ ] Build and deploy all changes to production

### Tier 3 (Future — from feesMap.md)
- [ ] Standardise all fee thresholds into PlanConfigService
- [ ] Cross-module fee dashboard

### Next Priority Tasks
- [ ] Test expenditure AI fill on production
- [ ] Verify admin AI Provider panel shows Anthropic/xAI toggle cards
- [ ] Policy number not displaying in pension detail view (shows N/A)

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

`investmentFix-v2` branch has the OCF fix (3 files, 4 lines). Was merged as PR #165 but reverted because not browser tested. Fix is correct (pension side works identically). Start by: checkout investmentFix-v2, start dev, test, then merge. Production deploy pending — full file list in March/March26Updates/deploy26.md.
