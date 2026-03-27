# CSJTODO — Fynla

<<<<<<< dashboard
*Last updated: 25 March 2026 — dashboard branch session (batches 9-10)*
*Previous session: 25 March 2026 (grokAI branch — inline investment holdings)*

## Carried Forward (from previous session)

### CRITICAL — AI Form Fill NOT TESTED WITH GROK
- [ ] Step 4: Manual browser fill for EVERY variant (ISA, GIA, bond, VCT, EIS with holdings)
- [ ] Step 5: Verify DB save and dashboard display for each variant
- [ ] Step 6: Algorithm doc needs updating AFTER manual testing confirms it works
- [ ] Step 10: Test with Grok — send natural language prompts, verify form fills, verify DB saves
- [ ] `create_investment_account` with holdings — UNTESTED with Grok
- [ ] Previous issue: Grok creates accounts with £0 value — may still be broken
- [ ] Account lookup LIKE query too loose — picks wrong account when multiple share provider name

### Known Issues (Carried Forward)
- [ ] AI form fill: remaining entity types untested (DB pension, property, mortgage, estate assets/gifts, trusts, business interests, chattels, goals, life events, family members, edit flow)
- [ ] Console errors: Protection TypeError at PolicyFormModal.vue:196 during AI fill (non-blocking)
- [ ] property_sale life event: Grok also creates property record (double navigation)

### Tech Debt (Carried Forward)
- [ ] Debug console.log statements in AccountForm.vue (remove before deploy)
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking)
- [ ] LiabilitiesStep.vue: DEPRECATED comment
- [ ] IncomeStatementTab.vue is orphaned (never imported)
- [ ] WARN-002: Security sessions API returns 500 on /api/auth/sessions
- [ ] WARN-003: Vue error on holistic-plan page

### Grok AI Migration (branch: grokAI)
- [ ] Get xAI API key from https://console.x.ai
- [ ] Complete AI form fill testing — follow aiProcess.md Steps 4-10
- [ ] Test with xAI locally — chat, streaming, tool calling, navigation
- [ ] Test document extraction with xAI
- [ ] Phase 5 remaining: remove Anthropic SDK, delete Python scripts, update legal text
- [ ] Merge grokAI branch to main

## Completed This Session (dashboard branch)

### Dashboard Batch 9
- [x] Grid breakpoint xl:grid-cols-3 for smaller desktops
- [x] Hover border fix — box-shadow then 3px transparent border
- [x] 0% progress bars show "0%" text in horizon blue
- [x] DashboardSparkline component (GA-style, ApexCharts)
- [x] Cash & Savings card redesign — sparkline + collapsible accounts
- [x] Investments card — mirror pattern
- [x] Goals bar chart — Horizon blue from designSystem
- [x] Income donut chart — designSystem colours

### Dashboard Batch 10
- [x] Card gradient z-index — renders below content
- [x] 3px hover border on dashboard cards
- [x] 0% progress bars left-aligned
- [x] Empty cards (Protection, Estate) — no gradient or hover
- [x] Allowances ISA → /net-worth/cash, Pension → /retirement
- [x] Mobile status bar carousel with swipe and dots
- [x] CashOverview: account cards grey gradient, Open Banking light blue
- [x] Rename General → Settings in nav
- [x] Settings tab navigation (General/Security/Privacy/Assumptions)
- [x] Remove Your Information from Settings

## Outstanding from This Session

### Dashboard Branch — Needs Browser Testing
- [ ] Full browser walkthrough of all batch 9-10 changes across personas
- [ ] Verify mobile carousel works on actual touch device / responsive mode
- [ ] Verify Settings tab navigation works on all 4 sub-pages
- [ ] Verify CashOverview card gradients display correctly
- [ ] Test allowance section clickthrough navigation

### Dashboard Branch — Merge to Main
- [ ] Merge dashboard branch to main when browser testing complete
- [ ] Deploy frontend build to production

## Context for Next Session

Dashboard branch has 17 new commits covering batches 9-10. All changes are frontend-only (Vue/CSS/JS). Build compiles cleanly. The branch is pushed to origin/dashboard. No browser testing was done this session — next session should start with a full visual walkthrough across personas before merging to main. The grokAI branch work (AI form fill testing) is still outstanding from the previous session.

Key files: Deploy notes at `March/March25Updates/deploy.md`.

## Files to Review
- `resources/js/components/Journey/JourneyProgressHero.vue` — mobile carousel (new, untested)
- `resources/js/components/Settings/SettingsTabBar.vue` — new component (untested)
- `resources/js/views/Settings.vue` — restructured (Your Info removed, tabs added)
- `resources/js/views/Dashboard.vue` — extensive changes (sparklines, progress bars, allowances)
=======
*Last updated: 26 March 2026 — sessions 11 & 12*

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
- [x] Browser tested: 3 pensions (SIPP, Occupational, Stakeholder) with holdings + OCF + fees
- [x] Edit tested: modified SIPP fund value and contribution, verified save
- [x] Merged to main (PR #162)

## Session 12 (26 March) — Fee System Gaps: Actions, Projections, Premiums

### Completed
- [x] Fee system map: comprehensive trace across Investment, Pension, Protection (feesMap.md)
- [x] 3 pension fee action triggers: high total (>1.0%), high platform (>0.8%), high fund OCF (>0.5%)
- [x] PensionPortfolioAnalyzer: handles fixed fee types + advisor fee in total calculation
- [x] PensionProjector + ContributionOptimizer: deduct platform + advisor fees from growth rate
- [x] 2 protection premium actions: high premiums (>5% income), affordability warning (>10% income)
- [x] IncomeProtectionPolicy: added premium_frequency field + migration
- [x] 10-year fee impact added to pension Holdings tab (cumulative fees, lost growth, total impact)
- [x] IPT confirmed not applicable (life/CI/IP all exempt per TaxConfig)
- [x] Browser tested: all 3 pensions verified with 10-year fee impact rendering correctly
- [x] Deploy guide updated: March/March26Updates/deploy26.md

### Branch
- `fees` — ready for merge to main

---

## Outstanding Items

### Next Priority Tasks
- [ ] Test expenditure AI fill on production
- [ ] Verify admin AI Provider panel shows Anthropic/xAI toggle cards
- [ ] Policy number not displaying in detail view (shows N/A despite being entered) — investigate

### Tier 3 (Future — from feesMap.md)
- [ ] Standardise all fee thresholds into PlanConfigService (currently hardcoded in seeders)
- [ ] Cross-module fee dashboard (total annual cost across investments + pensions + protection)

### Tech Debt (carried forward)
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking)
- [ ] LiabilitiesStep.vue: DEPRECATED comment
- [ ] IncomeStatementTab.vue: orphaned (never imported)
- [ ] DB enum missing step_child/partner — handler maps as workaround

### Known Issues
- [ ] DB pension field mapping mismatch (pre-existing — employer_name vs scheme_name)
- [ ] Expenditure form fill doesn't animate through form (direct DB save pattern)

## Context for Next Session

Fee system gaps now closed for Tier 1 + 2. Pension actions will fire for high fees (matching investment module parity). Protection premium affordability actions now exist. PensionProjector and ContributionOptimizer now deduct all fees from growth projections (was platform-only before). The `fees` branch needs merging, building, and deploying. On production: run migration, reseed RetirementActionDefinitionSeeder + ProtectionActionDefinitionSeeder. Tier 3 items (threshold standardisation + cross-module dashboard) are documented in feesMap.md for future planning.
>>>>>>> main
