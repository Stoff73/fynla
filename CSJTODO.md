# CSJTODO — Fynla

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

---

## Session 28 March 2026 — Personal journey pages redesign

### Completed This Session
- [x] Full redesign of all 5 personal journey pages (Starting Out, Building Foundations, Protecting and Growing, Planning Your Future, Enjoying Your Wealth)
- [x] Consistent template: horizon-to-raspberry gradient hero, journey name as h1, old title as bold subtitle
- [x] Green spring CTA buttons with "View demo" link (direct persona launch, no modal)
- [x] Dark horizon gradient "What Fynla shows you" section with coloured icon cards
- [x] Light pink features section with 3px hover borders
- [x] Horizon blue moments/small steps cards on eggshell background
- [x] "See it in action" section: large persona name left, CTA right-aligned
- [x] Merged related links + stage navigation into single row
- [x] Consistent spring-coloured ticks, related link colours, title sizes, orphan word prevention
- [x] Renamed "Your life stage" to "Your personal journey" in nav mega menu and landing page
- [x] Renamed "Calculators" to "Free calculators" in resources mega menu

### New Outstanding Items
- [ ] Browser test all 5 journey pages across breakpoints (mobile, tablet, desktop)
- [ ] Browser test "View demo" link loads correct persona on each page
- [ ] Browser test "Try [journey] demo" button loads correct persona
- [ ] Verify mockup HTML file `public/mockup-starting-out.html` is removed before deploy

### Context
Content branch has journey page redesign committed and pushed (2 commits: `e5ecd2bb`, `ae92ab8c`). All changes are frontend-only. Deploy notes at `March/March28Updates/deploy.md`. The mockup file `public/mockup-starting-out.html` should be deleted before production deploy.
