# CSJTODO — Fynla

*Last updated: 25 March 2026 — sessions 8, 9 & 10*

---

## Session 8 (25 March) — Production Deploy + AI Testing

### Completed
- [x] grokAI branch merged to main (PR #160)
- [x] Deployed AI form fill to production (fynla.org)
- [x] Fixed missing files in deploy guide (config/services.php, AppServiceProvider, XaiClient, routes, AdminController, composer)
- [x] Tested 14/14 AI modules on production via Playwright — all PASS
- [x] Trust 422 fix (default creation_date to today)
- [x] Protection TypeError fix (null guard on this.errors)
- [x] Family member education_status inference from age
- [x] Expenditure navigation after direct save
- [x] InvestmentList.vue tooltip restored (lost in merge)
- [x] Stale branches cleaned up (grokAI, aiFormFill deleted)

## Session 9 (25 March) — Vault Gateway + CLAUDE.md Cleanup

### Completed
- [x] Vault gateway system designed and implemented (5 components)
- [x] vault-context skill created (/vault-context [module])
- [x] session-start enhanced with vault context loading
- [x] CLAUDE.md vault reference map + agent dispatch protocol added
- [x] Pre-edit vault reminder hook created
- [x] All 6 CLAUDE.md files updated with current metrics
- [x] Local dev auth self-service added (tinker command for verification codes)
- [x] session-end skill updated: CSJTODO naming, deploy completeness, pre-merge checks, append mode
- [x] Frontend build done — ready for upload

## Session 10 (25 March) — Investment UI + DC Pension Holdings/Fees

### Completed
- [x] Investment detail view consolidated — 2 views merged into 1 card-based layout (PR #161)
- [x] InvestmentProjections.vue rewritten: per-account Monte Carlo, header card, all analysis cards, drill-downs
- [x] InvestmentDetailInline.vue retired
- [x] DC pension inline holdings — reused InlineHoldingsEditor
- [x] DC pension fee fields — platform fee (% or £, with frequency) + advisor fee
- [x] Migration: add_fee_fields_to_dc_pensions_table
- [x] Fixed ocf_percent NOT NULL constraint in holdings creation
- [x] Fixed InlineHoldingsEditor Amount Invested auto-populate on allocation % change
- [x] Both features deployed and tested on fynla.org

### Reverted
- Pension detail view enhancements (pensionUI branch) — holdings/fees tabs + header metrics. Branch deleted, changes reverted.

---

## Outstanding Items

### Next Priority Tasks
- [ ] Pension detail view — add holdings tab, fees tab, header metrics (redo properly)
- [ ] Test expenditure AI fill on production
- [ ] Verify admin AI Provider panel shows Anthropic/xAI toggle cards

### Tech Debt (carried forward)
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking)
- [ ] LiabilitiesStep.vue: DEPRECATED comment
- [ ] IncomeStatementTab.vue: orphaned (never imported)
- [ ] DB enum missing step_child/partner — handler maps as workaround

### Known Issues
- [ ] DB pension field mapping mismatch (pre-existing — employer_name vs scheme_name)
- [ ] Expenditure form fill doesn't animate through form (direct DB save pattern)

## Context for Next Session

Investment detail view consolidated into card-based layout on InvestmentProjections.vue — deployed and working on production. DC pensions now have inline holdings and fee fields matching investments — deployed. The pension detail view (PensionDetailInline.vue) still needs holdings/fees tabs and header metrics added — attempted on pensionUI branch but reverted due to issues. This is the top priority for next session. Approach carefully — test scroll behaviour before committing.
