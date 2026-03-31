# TODO — Fynla

*Last updated: 26 March 2026 — dashboard branch session 2 (visual consistency + net worth restructure)*
*Previous session: 26 March 2026 (donut unification + net worth consistency)*

## Carried Forward (from previous sessions)

### CRITICAL — AI Form Fill NOT TESTED WITH GROK
- [ ] Step 4: Manual browser fill for EVERY variant (ISA, GIA, bond, VCT, EIS with holdings)
- [ ] Step 5: Verify DB save and dashboard display for each variant
- [ ] Step 6: Algorithm doc needs updating AFTER manual testing confirms it works
- [ ] Step 10: Test with Grok — send natural language prompts, verify form fills, verify DB saves
- [ ] `create_investment_account` with holdings — UNTESTED with Grok
- [ ] Previous issue: Grok creates accounts with £0 value — may still be broken
- [ ] Account lookup LIKE query too loose — picks wrong account when multiple share provider name

### Known Issues (Carried Forward)
- [ ] AI form fill: remaining entity types untested
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
- [ ] Merge grokAI branch to main

## Completed This Session

- [x] Replaced hardcoded hex (#FAD6E0/#F5B3C5) with light-pink palette tokens (6 files)
- [x] Eggshell backgrounds on Income, Property, Liabilities, Valuables, Risk Profile, Business, Investments
- [x] Module-gradient on cards: PropertyCard, LiabilityCard, ChattelCard, BusinessInterestCard, Risk sections
- [x] Grey hover borders replacing pink/raspberry across all card components
- [x] Teleport fix on Property, Liabilities, Investment modals
- [x] Sub-nav CTAs: Add Liability, Add Valuable/Import, Add Business via subNavConfig
- [x] Removed page titles (Liabilities, Valuables, Business, Risk Profile), left-aligned filters
- [x] All empty states: bg-light-blue-100 + horizon-500 buttons (16 files)
- [x] Net Worth: pie chart with cursor-following hover tooltip (coloured to match segment)
- [x] Net Worth: new Assets & Liabilities bar chart (positive/negative)
- [x] Net Worth: layout restructured — charts row + wealth summary below
- [x] Wealth Summary: light-pink hover, larger text hierarchy, light-blue section headers
- [x] Dashboard sparkline: larger markers (size 6), white fill + coloured border
- [x] Bank accounts: left-aligned totals
- [x] Donut chart title: 18px to match Wealth Summary heading
- [x] Pie chart card height matches bar chart card height
- [x] Analytics coming soon box with Bloomberg/Morningstar/FE Analytics pills
- [x] "Current pension potential growth:" → "Current potential growth:"

## Outstanding from This Session

### Tech Debt (from audit — 28 issues, partially addressed)
- [x] Fix hardcoded hex in CashOverview, AccountGroupList, ModuleStatusBar, CurrentSituation, PortfolioOverview (done — light-pink tokens)
- [ ] Extract `lightenColor` to `utils/color.js` — duplicated in 8 files
- [ ] Fix `this._uid` to `this.$.uid` in AssetAllocationDonut.vue (Vue 3 compatibility)
- [ ] Fix non-palette `pink-*` colours in ChattelsList.vue (6 occurrences) — should be `raspberry-*`
- [ ] Remove unused VueApexCharts imports from PortfolioOptimizer, InvestmentProjections
- [ ] Remove unused TEXT_COLORS, CHART_DEFAULTS, BORDER_COLORS imports from HoldingsTable
- [ ] Remove unused RiskBadge from PensionList
- [ ] Fix `purple-*` to `violet-*` in BusinessInterestsList, InvestmentList, InvestmentProjections, PensionList
- [ ] Fix `success-*` and `blue-*` tokens in IncomeOccupation.vue
- [ ] Remove dead `disposableIncomeClass` and `monthlyDisposable` from IncomeOccupation.vue
- [ ] Spell out "OCF" in HoldingsTable column header
- [ ] Spell out "TiC" in CashOverview badge text

### Browser Testing Still Needed
- [ ] Test all net worth sub-pages across personas (empty + populated states)
- [ ] Test sub-nav CTA buttons (Add Liability, Add Valuable, Import, Add Business)
- [ ] Test pie chart hover tooltip across browsers
- [ ] Test Assets & Liabilities bar chart with various data combinations
- [ ] Test mobile responsiveness of all restructured pages
- [ ] Full browser walkthrough of batch 9-10 dashboard changes

### Dashboard Branch — Merge to Main
- [ ] Merge dashboard branch to main when browser testing complete
- [ ] Deploy frontend build to production

## Context for Next Session

Dashboard branch has extensive visual consistency work across all net worth sub-pages. 3 commits today (48 files changed). All frontend-only, build compiles cleanly. Key additions: Assets & Liabilities bar chart on net worth page, cursor-following pie chart tooltips, eggshell backgrounds with module-gradient cards pattern applied consistently. The tech debt audit found 28 issues — hex colours were fixed but `lightenColor` duplication (8 files) and unused imports remain. Browser testing is the main outstanding item before merge to main.

Key files: Deploy notes at `March/March26Updates/deploy.md`, tech debt at `tech-debt-report.md`.

## Files to Review
- `AssetAllocationDonut.vue` — hover tooltip (fixed positioning, cursor tracking)
- `AssetBreakdownBar.vue` — new component (bar chart with positive/negative bars)
- `NetWorthWealthSummary.vue` — layout restructure (charts row + summary below)
- `subNavConfig.js` — new CTAs for Liabilities, Valuables, Business
- All card components (PropertyCard, LiabilityCard, ChattelCard, BusinessInterestCard) — module-gradient + hover changes
