# CSJTODO — Fynla

*Last updated: 25 March 2026 — sessions 8 & 9*

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

---

## Outstanding Items

### To Deploy
- [ ] Upload `public/build/` to production (built with investment tooltip fix)

### Tech Debt (carried forward)
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking)
- [ ] LiabilitiesStep.vue: DEPRECATED comment
- [ ] IncomeStatementTab.vue: orphaned (never imported)
- [ ] DB enum missing step_child/partner — handler maps as workaround

### Known Issues
- [ ] DB pension field mapping mismatch (pre-existing — employer_name vs scheme_name)
- [ ] Expenditure form fill doesn't animate through form (direct DB save pattern)

### Next Priority Tasks
- [ ] Investment detail view consolidation (user wants card-driven per-account view)
- [ ] Test expenditure AI fill on production
- [ ] Verify admin AI Provider panel shows Anthropic/xAI toggle cards

## Context for Next Session

AI form fill is fully deployed and working on production (14/14 modules PASS). Vault gateway system is live — next session will auto-load feedback rules, recent sessions, and TODOs. All CLAUDE.md files are current. Frontend build with investment tooltip fix is ready to upload. Main branch is clean, no stale branches or worktrees.

The vault-context skill can be invoked with `/vault-context [module]` before working on any module. Sub-agents must receive vault context per CLAUDE.md rules.
