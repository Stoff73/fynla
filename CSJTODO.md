# CSJTODO — Fynla

*Last updated: 26 March 2026 — session 11*

---

## Session 11 (26 March) — Pension Detail View: Holdings Tab + Fee Display + OCF Input

### Completed
- [x] Pension detail view: Holdings moved to dedicated tab (Overview → Holdings → Projections → Documents)
- [x] Holdings tab: table with fund name, type, allocation %, value, OCF, cash remainder, fee summary bar
- [x] Fees section: platform fee handles fixed/percentage types with frequency display
- [x] Fees section: advisor fee row added (shown when > 0)
- [x] Fees section: total annual cost includes platform + advisor + weighted OCF
- [x] InlineHoldingsEditor: OCF % column added (captures OCF on holding creation)
- [x] Fixed CSS conflicts: font-medium/font-semibold, purple-600 → violet-600
- [x] Browser tested: registered new user, created 3 pensions (SIPP, Occupational, Stakeholder) with holdings + OCF + fees
- [x] Edit tested: modified SIPP fund value and contribution, verified save
- [x] All tabs verified: Overview, Holdings, Projections, Documents
- [x] Deploy guide written: March/March26Updates/deploy26.md

### Branch
- `pensionUI` — ready for merge to main

---

## Outstanding Items

### Next Priority Tasks
- [ ] Test expenditure AI fill on production
- [ ] Verify admin AI Provider panel shows Anthropic/xAI toggle cards
- [ ] Policy number not displaying in detail view (shows N/A despite being entered) — investigate

### Tech Debt (carried forward)
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking)
- [ ] LiabilitiesStep.vue: DEPRECATED comment
- [ ] IncomeStatementTab.vue: orphaned (never imported)
- [ ] DB enum missing step_child/partner — handler maps as workaround

### Known Issues
- [ ] DB pension field mapping mismatch (pre-existing — employer_name vs scheme_name)
- [ ] Expenditure form fill doesn't animate through form (direct DB save pattern)

## Context for Next Session

Pension detail view now has a dedicated Holdings tab with OCF values displayed correctly. The InlineHoldingsEditor (shared by investments and pensions) now captures OCF % on creation. Fee display in the overview handles all fee types (percentage, fixed £, advisor). All changes are on the `pensionUI` branch — needs building and deploying. The OCF input change also affects the investment account form since they share InlineHoldingsEditor — verify investment creation still works after merge.
