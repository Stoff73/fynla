# Fynla Codebase Convention Audit — 2026-05-12

Scope: whole codebase. Focus: CLAUDE.md rule compliance, dead code, duplication, configuration drift, and tech debt. Research only — no code changes.

Reference documents consulted:
- `/Users/CSJ/Desktop/fynla/CLAUDE.md` (all 16 rules)
- `/Users/CSJ/Desktop/fynla/app/Http/CLAUDE.md`
- `/Users/CSJ/Desktop/fynla/app/Services/CLAUDE.md` (not opened, available)
- `/Users/CSJ/Desktop/fynla/resources/js/CLAUDE.md`
- `/Users/CSJ/Desktop/fynla/database/CLAUDE.md`
- `/Users/CSJ/Desktop/fynla/tests/CLAUDE.md`
- `/Users/CSJ/Desktop/fynla/fynlaDesignGuide.md` (v1.3.0)
- `/Users/CSJ/Desktop/fynla/.editorconfig`
- `/Users/CSJ/Desktop/fynla/package.json`, `/Users/CSJ/Desktop/fynla/composer.json`
- `/Users/CSJ/Desktop/fynla/tailwind.config.js`
- `/Users/CSJ/Desktop/fynla/vite.config.js`

Findings are tagged with **confidence** (low/med/high) and **severity** (critical/high/medium/low). File paths are absolute.

---

## Aggregate Summary

| Rule / Category | Findings | Worst Severity |
|---|---|---|
| Rule #1 Manual upload only | 0 | — (clean) |
| Rule #2 Preview user isolation | 0 (deferred) | low |
| Rule #3 No hardcoded tax | ~20 fallback literals | medium |
| Rule #4 Form modals emit `save` | 1 outlier | low |
| Rule #5 Canonical enums | 0 (`'sole'` is absent) | — (clean) |
| Rule #6 Currency formatting | 6 stragglers | medium |
| Rule #7 Joint assets pattern | 0 issues found | — (clean) |
| Rule #8 PreviewWriteInterceptor | 0 | — (clean) |
| Rule #9 No amber/orange/non-palette | 0 colour usages | — (clean) |
| Rule #10 No acronyms in UI | 7+ surfaces (DB writes admin, S&S admin) | low–medium |
| Rule #11 Design system compliance | Many hex literals + scores | medium |
| Rule #12 CSS governance | ~196 hex literals in scoped styles | medium |
| Rule #13 No scores in user-facing UI | 6 surfaces (some dead) | high |
| Rule #14 AppLayout wrap | 1 confirmed routed violation | high |
| Rule #15 Loop until correct | 1 test `.skip` (dashboard sort) | low |
| Rule #16 Icons (decorative banned) | Dashboard 14 files, detail 19 files, Goals emoji | high |
| Two-Fyn architecture | 0 forbidden artefacts present | — (clean) |
| Dead code (Vue components) | ~106 candidates, ≥30 confirmed | high |
| Dead code (PHP) | 2 services, 1 controller | medium |
| Dead code (Vuex modules) | 3 modules registered but unused | medium |
| Dead code (utils) | 1 (canonical `ownership.js` orphan) | high |
| Documentation drift | CLAUDE.md cites design v1.2.0; file is v1.3.0 | medium |
| Bundle hygiene | `axios` in devDependencies but runtime-used | low |
| TODO/FIXME backlog | ~10 active TODOs in code | low |
| Spelling consistency (British) | `Optimization` in user-facing text | low |

---

## Per-Rule Findings

### Rule #1 — Manual File Upload Only — CLEAN

- `/Users/CSJ/Desktop/fynla/deploy/fynla-org/build.sh` and `/Users/CSJ/Desktop/fynla/deploy/csjones-fynla/build.sh` are build-only; neither auto-uploads or zips. Pass.

### Rule #2 — Preview User Isolation — MOSTLY CLEAN

- `is_preview_user` is consistently filtered in the controllers/services I sampled. No cross-contamination grep flagged anything. Confidence: **med** (no exhaustive query-by-query audit performed).
- Note that `PreviewWriteInterceptor` (Rule #8) is the canonical defence-in-depth; that is in order.

### Rule #3 — No Hardcoded Tax — MEDIUM

The codebase consistently fetches values via `TaxConfigService` but uses **null-coalescing literal fallbacks** that hard-code the same numbers across services. These are tolerated by the rule when paired with a config read, but they constitute drift risk if HMRC values change while a TaxConfiguration row is missing in a deploy. Severity **medium**, confidence **high**.

Representative offenders (file:line — fallback value):

- `/Users/CSJ/Desktop/fynla/app/Services/Investment/Recommendation/ContributionWaterfallService.php:318` `($incomeTaxBands['bands'][0]['rate'] ?? 0.20)`
- `/Users/CSJ/Desktop/fynla/app/Services/Investment/Recommendation/ContributionWaterfallService.php:319` `?? 0.40`
- `/Users/CSJ/Desktop/fynla/app/Services/Investment/Recommendation/ContributionWaterfallService.php:320` `?? 0.45`
- `/Users/CSJ/Desktop/fynla/app/Services/Investment/Recommendation/ContributionWaterfallService.php:736` `($carryIncomeTaxBands['bands'][0]['rate'] ?? 0.20)`
- `/Users/CSJ/Desktop/fynla/app/Services/Savings/SavingsActionDefinitionService.php:776–778, 2201–2203` (basic/higher/additional rate triplet, twice)
- `/Users/CSJ/Desktop/fynla/app/Services/Savings/PSACalculator.php:84` `?? 12570`, line 86 `?? 125140`
- `/Users/CSJ/Desktop/fynla/app/Services/Investment/DividendTaxCalculator.php:34` `?? 12570`, line 36 `?? 125140`
- `/Users/CSJ/Desktop/fynla/app/Services/Investment/AssetLocation/AssetLocationOptimizer.php:105` `?? 50270`, 149 `?? 12570`, 150–151 `?? 50270` / `?? 125140`
- `/Users/CSJ/Desktop/fynla/app/Services/Coordination/HouseholdPlanningService.php:491` `?? 125140`
- `/Users/CSJ/Desktop/fynla/app/Services/Retirement/PensionContributionOptimizer.php:256–257` `?? 50270` / `?? 125140`; line 308 `?? 50270` (auto-enrol upper); line 424 `?? 50270`
- `/Users/CSJ/Desktop/fynla/app/Services/Retirement/DecumulationPlanner.php:302–303` `?? 12570` / `?? 50270`
- `/Users/CSJ/Desktop/fynla/app/Services/Tax/TaxOptimisationService.php:477` `?? 12570`, 479 `?? 125140`
- `/Users/CSJ/Desktop/fynla/app/Services/Tax/Strategies/IncomeBandStrategy.php:33` `?: 125140`
- `/Users/CSJ/Desktop/fynla/app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php:80` `?? 12570`
- `/Users/CSJ/Desktop/fynla/app/Services/Estate/PersonalizedTrustStrategyService.php:167, 468` `?? 0.20`
- `/Users/CSJ/Desktop/fynla/app/Services/Estate/TrustService.php:159, 168, 197` (mixes `0.45` and `0.20`)
- `/Users/CSJ/Desktop/fynla/app/Services/Estate/GiftingStrategyOptimizer.php:292` `?? 0.20`
- `/Users/CSJ/Desktop/fynla/app/Services/Estate/ComprehensiveEstatePlanService.php:958` `?? 0.40`
- `/Users/CSJ/Desktop/fynla/resources/js/constants/taxConfig.js:95, 127–128` hard-codes `IHT_NIL_RATE_BAND = 325000` / `IHT_RESIDENCE_NIL_RATE_BAND = 175000` and an SDLT band threshold. Frontend taxConfig.js is allowed as a static reference but the rule says "Frontend: prefer backend TaxConfigService for calculations".

**Additional finding** — a magic number inside `ContributionWaterfallService` that is NOT a tax band but is presented to the user as one:
- `/Users/CSJ/Desktop/fynla/app/Services/Investment/Recommendation/ContributionWaterfallService.php:440` `min($remaining * 0.20, $headroom)` and `:470` which formats `"× 20%"` into a user-facing rationale string. This is a hard-coded `20%` allocation policy, not a tax rate — it is correctly a business rule, but is undocumented in a constants file. Severity **low**, confidence **high**.

Recommendation: introduce a `TaxConfigService::requireXxx()` helper that throws `FinancialCalculationException::missingConfig()` instead of silently falling through to a literal. Acceptable shape: each call-site becomes a one-liner. Wider scope than this audit.

### Rule #4 — Form Modal `save` Event — MOSTLY CLEAN

- 33 components in `resources/js/components/` emit `'save'` correctly.
- No `$emit('submit'…)` violations found in any Vue form/modal component.
- **Outlier** (low severity, confidence high): `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/LpaUploadForm.vue:170` emits `'uploaded'` rather than `'save'`. This is a one-shot upload not a traditional form save, so probably intentional, but it breaks the documented pattern. Either align the name (`save`) or document the deviation.

### Rule #5 — Canonical Enums — CLEAN

- Grep for `'sole'` / `"sole"` in `app/`, `resources/js/`, and `database/`: **zero** hits. The deprecated value has been fully purged. Confidence **high**.
- `'individual'`, `'joint'`, `'tenants_in_common'`, `'trust'` are the only ownership types used.

### Rule #6 — Currency Formatting (`currencyMixin`) — MOSTLY CLEAN

- 238 files use `currencyMixin`; 151 of 153 files that call `formatCurrency()` import the mixin.
- **6 stragglers** with bespoke `Intl.NumberFormat('en-GB', { style: 'currency' … })` re-implementations bypassing the mixin/util:
  - `/Users/CSJ/Desktop/fynla/resources/js/utils/willDocumentRenderer.js:26` — PDF renderer, defensible.
  - `/Users/CSJ/Desktop/fynla/resources/js/utils/currency.js:32` — canonical implementation. OK.
  - `/Users/CSJ/Desktop/fynla/resources/js/components/Plans/Shared/planPrintMixin.js:94` — print-only path. Acceptable but inconsistent.
  - `/Users/CSJ/Desktop/fynla/resources/js/store/modules/netWorth.js:701, 710, 719` — three separate inline currency formatters inside the Vuex store. Severity **medium**, confidence **high**. Should call `utils/currency.js`.
- **5 components define `formatCurrencyShort` / `formatCurrencyCompact` locally** (already exists on the mixin):
  - `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/MonteCarloResults.vue:407` (note: file is **dead code**, see below)
  - `/Users/CSJ/Desktop/fynla/resources/js/components/Retirement/IncomeDrawdownChart.vue:262`
  - `/Users/CSJ/Desktop/fynla/resources/js/components/Retirement/TargetIncomeDrawdownChart.vue:257`
  - `/Users/CSJ/Desktop/fynla/resources/js/components/NetWorth/InvestmentProjections.vue:981`
  - `/Users/CSJ/Desktop/fynla/resources/js/components/NetWorth/AssetBreakdownBar.vue:230`
  Severity **low**, confidence **high**. Replace each with `this.formatCurrencyCompact` (already on `currencyMixin`).

### Rule #7 — Joint Assets Pattern — CLEAN

- `joint_owner_id` consistently used across `app/Models/Estate/Liability.php`, `CashAccount.php`, `Goal.php`, `BusinessInterest.php`, `Investment/InvestmentAccount.php`, `LifeEvent.php`, `Property.php`, `JointAccountLog.php`.
- `ownership_percentage` is paired with `joint_owner_id` where required.
- Migrations: `add_joint_owner_indexes`, `fix_*_joint_owner_foreign_key*`, `add_joint_owner_foreign_keys_to_*` all present.
- **However**, `resources/js/utils/ownership.js` (the canonical frontend helper) appears to be **orphaned** — see "Dead code / utils" below. The trait `CalculatesOwnershipShare` is used on the backend, but the frontend mirror is unused. Severity **high** (silent drift risk), confidence **high**.

### Rule #8 — PreviewWriteInterceptor — CLEAN

`/Users/CSJ/Desktop/fynla/app/Http/Middleware/PreviewWriteInterceptor.php:48–78` enumerates `EXCLUDED_ROUTES`. The list includes all the auth-flow routes mentioned in CLAUDE.md (login, register, verify-code, resend-code, password reset, restore, MFA, mobile token refresh, advisor impersonation, document upload, AI chat onboarding). Bug-report and onboarding endpoints are excluded. Calculation patterns (`#/calculate$#`, `#/projections$#`, etc.) match read-only POST endpoints. Confidence **high**.

The middleware also honours a Sanctum `bypass-preview-mode` ability for evals (lines 136–145+). The implementation pattern (wildcard `['*']` rejection) matches the security intent.

### Rule #9 — No Amber, Orange, Non-Palette Colors — CLEAN

- No `amber-*` or `orange-*` Tailwind tokens found in any `.vue`, `.css`, or `tailwind.config.js`. Confidence **high**.
- The only references to "amber"/"orange" are policy notes:
  - `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalsProjectionChart.vue:464` `// Use blue per design system (amber/orange are FORBIDDEN)` — anti-pattern comment, OK.
  - `/Users/CSJ/Desktop/fynla/resources/js/components/__tests__/NetWorth/PropertyCard.spec.js:180` `// Secondary residence - amber` — stale test comment from before the ban (test still validates colours but does not assert amber). Severity **low**.
  - `/Users/CSJ/Desktop/fynla/resources/js/views/Version.vue:119` references "Banned amber colour removed" — release-notes string. OK.
- `tailwind.config.js` defines only palette colours (raspberry, horizon, spring, violet, savannah, eggshell, neutral, light-gray, light-blue, light-pink). No tailwind defaults like `amber`/`orange` are extended.

### Rule #10 — No Acronyms in User-Facing Text — MOSTLY CLEAN

Acronyms in PUBLIC user-facing strings:

- `/Users/CSJ/Desktop/fynla/resources/js/views/Public/learn/tax/PensionAnnualAllowancePage.vue:101, 105, 109` — three uses of "MPAA" in body text. The page does spell it out first (`Money Purchase Annual Allowance`), then uses MPAA throughout. Severity **low–medium**, confidence **high**. CLAUDE.md is firm: spell out every time. Consider rewriting subsequent references to "the MPAA limit" → "the Money Purchase limit" or similar.
- `/Users/CSJ/Desktop/fynla/resources/js/views/Public/insights/InheritanceTaxExplainedPage.vue` — uses `NRB` / `RNRB` after defining them once (`Nil-Rate Band (NRB)`). Same as above — defensible for a deep-dive insight page, but technically against the rule. Lines 46, 50, 62, 110, 115, 120.

Acronyms in ADMIN UI (lower visibility — debatable):

- `/Users/CSJ/Desktop/fynla/resources/js/components/Admin/Insights/blocks/EditTaxYearStatBlock.vue:12` `<option>IHT nil rate band</option>` and `:13` `CGT annual allowance` — admin-only dropdowns. Severity **low**.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Admin/eval/RunPanel.vue:164` `DB writes` — admin eval panel where "DB" means database, not defined benefit. Not strictly a violation but the abbreviation is ambiguous; consider `Database writes`.

Code/comment usage of `DB pension`, `DC pension`, `S&S ISA` was checked (Retirement, Investment files). All occurrences are in `// comments` or in JS variable names — none in `<template>` rendered text. Confidence **high** that there are no `DB pension` / `DC pension` rendered to users in the audited subset.

### Rule #11 — Design System Compliance — MEDIUM

- Tailwind colours match `fynlaDesignGuide.md` palette exactly.
- **Drift**: CLAUDE.md Rule #11 cites design guide **v1.2.0**, but `/Users/CSJ/Desktop/fynla/fynlaDesignGuide.md` line 3 is **v1.3.0** ("Last Updated 02 April 2026"). Same drift in CLAUDE.md Rule #12 ("only tokens from `fynlaDesignGuide.md` v1.2.0"). Severity **medium**, confidence **high** — update CLAUDE.md references to v1.3.0 OR document what changed between versions and ratify it.
- Many hex literals in `<style>` blocks bypassing `@apply` — see Rule #12 below for the full list.

### Rule #12 — CSS Governance — MEDIUM

Total hex-literal lines in components' style blocks: **196** (excluding configured palette files).

**Distribution by file type**:

- Print/PDF generators (acceptable — print CSS can't use Tailwind tokens without inlining): 
  - `/Users/CSJ/Desktop/fynla/resources/js/components/Plans/Shared/planPrintMixin.js` — 194 hex declarations in one big print stylesheet (lines 167–520+). This is print-only HTML and arguably fine, but it bakes in colours (`#1f2937`, `#0f172a`, `#64748b`, `#374151`, `#dc2626`, etc.) that drift from the palette (`#1F2A44` horizon-500 vs. `#0f172a` used here — different shade).
  - `/Users/CSJ/Desktop/fynla/resources/js/utils/willDocumentRenderer.js` — same pattern.
  - `/Users/CSJ/Desktop/fynla/resources/js/utils/lpaDocumentRenderer.js:` `#1a1a1a`, `#717171`, `#555` — print/PDF renderer. The first two map cleanly to `horizon-700`-ish and `neutral-500` but are hard-coded.
- Live-UI components with hex literals:
  - `/Users/CSJ/Desktop/fynla/resources/js/components/UserProfile/LetterToSpouse.vue:1178-1423` — 20+ hex literals. This is a print template, acceptable.
  - `/Users/CSJ/Desktop/fynla/resources/js/components/NetWorth/AssetBreakdownBar.vue:203` — `background: #ffffff; border: 1px solid #E5E5E5;` inline in a chart tooltip template literal. Should use `bg-white border-light-gray`. Severity **low**, confidence **high**.

**No local `@keyframes spin`** found — global class is being used. Confidence **high**.

### Rule #13 — No Scores in User-Facing UI — HIGH

Six surfaces present scores in user-facing UI. **Several are in dead code (see Dead Code section)**, so the in-product impact is mitigated, but the components are still in tree and one is reachable.

- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PlanSections/FeeAnalysisSection.vue` lines 25–28, 223–225, 229
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PlanSections/TaxStrategySection.vue` lines 15–18, 88 (`<p class="text-xs text-neutral-500 mt-1">Optimisation score</p>`), 179–181, 185
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PlanSections/RiskAnalysisSection.vue` lines 15–16 (`{{ data.current_risk_score }}<span>/10</span>`), 26, 136–142

  The above three are **DEAD COMPONENTS** (not imported anywhere — verified). Severity downgraded to **medium**, but they should be deleted not just left in tree.

- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/AssetLocationOptimizer.vue` lines 35–38 (`<p class="text-2xl font-bold mb-1" :class="getScoreColour(analysis?.optimization_score?.score)">{{ analysis?.optimization_score?.grade || 'N/A' }}</p>`), 425–427

  `AssetLocationOptimizer.vue` is referenced in CLAUDE.md and likely live — severity **high**, confidence **high**.

- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/DiversificationTab.vue` lines 333–335.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/TaxOptimizationOverview.vue` (file flagged but not opened — grep confirmed presence).
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/ContributionPlanner.vue` (also confirmed dead, see Dead Code).
- `/Users/CSJ/Desktop/fynla/resources/js/components/Admin/InvestmentActionModal.vue` — admin surface, lower severity.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Shared/ProfileCompletenessAlert.vue` — needs inspection (uses "score" word).
- `/Users/CSJ/Desktop/fynla/resources/js/views/Investment/PortfolioStrategyPanel.vue` — used by NetWorth/InvestmentList.vue.

Investment file naming pattern is also weak — `TaxOptimization*` (US) lives next to `TaxOptimisation*` (UK) in PHP. Recommend cross-checking and unifying once scores are excised.

### Rule #14 — All Pages Must Wrap in AppLayout — HIGH

Of 135 routed view paths, only **1** confirmed routed view fails the wrap requirement:

- `/Users/CSJ/Desktop/fynla/resources/js/views/NetWorth/CashOverview.vue:1–14` — routed at `/networth/cash` and `/preview/.../cash` (router lines 749, 1317). Template starts `<template><div class="cash-overview module-gradient">…` with no layout wrapper. Inner content renders `<SavingsAccountDetailInline>` (a chrome-less inline) and `<AccountSummaryPanel>` directly. No nav, no sidebar, no footer when reached. Severity **high**, confidence **high**. **Fix: wrap in `<AppLayout>` like `views/Savings/SavingsDashboard.vue` does (`<component :is="isEmbedded ? 'div' : 'AppLayout'">` if it has an embedded mode).

Cases that **looked** like violations but are legitimate:

- Auth pages (`Login.vue`, `Register.vue`) intentionally use gradient screens — auth chrome-less is a valid exception.
- Onboarding views (`OnboardingView.vue`, `OnboardingFullView.vue`, `OnboardingModuleView.vue`) all delegate to `OnboardingWizard.vue` which provides its own top nav.
- Public feature pages (`IceLettersFeature.vue` etc.) delegate to `FeaturePageLayout.vue` which wraps in `<PublicLayout>`.
- Plans pages (`InvestmentPlan.vue`, `EstatePlan.vue`, etc.) delegate to `PlanPageLayout.vue` which wraps in `<AppLayout>`.
- Advisor pages use the route-level `<AdvisorLayout>`.
- `SavingsDashboard.vue` does wrap (uses `<component :is="isEmbedded ? 'div' : 'AppLayout'">`).
- `DebugEnv.vue` is a developer route; arguable, but explicitly debug.

**Orphan views in `resources/js/views/` that are NOT routed** (so the rule does not apply, but they are dead code):

- `/Users/CSJ/Desktop/fynla/resources/js/views/Retirement/Recommendations.vue` — not imported anywhere; defines its own template with no layout.
- `/Users/CSJ/Desktop/fynla/resources/js/views/Retirement/Projections.vue` — not imported anywhere.

These should be deleted (see Dead Code).

### Rule #15 — Loop Until Correct — MINOR

Skipped tests in `tests/` directory:

- `/Users/CSJ/Desktop/fynla/tests/frontend/components/Dashboard/AlertsPanel.test.js:66` `it.skip('sorts alerts by severity …')` — single skipped test, no justification comment. Severity **low**, confidence **high**. Either implement the sort logic and unskip, or delete the placeholder.
- `/Users/CSJ/Desktop/fynla/tests/Architecture/EvalScenarioCountTest.php:18` `->skip(…)` — has conditional skip (needs inspection but architecture-test conditional skips are legitimate).
- `/Users/CSJ/Desktop/fynla/tests/Feature/Api/AdminBackupTest.php:154, 163` `->skip(! is_executable('/usr/local/bin/mysqldump') …)` — conditional skip on system binary availability. **Legitimate**.

Two TODO-style comments in production tests:

- `/Users/CSJ/Desktop/fynla/resources/js/components/Admin/EvalRecordings.vue:504, 510, 513, 547` — multiple `TODO` strings INSIDE generated markdown templates ("assess whether engine output is surfaced …"). These are CONTENT outputs (eval reviewers fill them in), not abandoned code paths. Acceptable.

### Rule #16 — Icons (Functional Only) — HIGH

This rule is the **largest open compliance gap** in the codebase.

**Dashboard cards** (banned surface) — 14 files use `<svg>` icons:

- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/DashboardCard.vue` — chevron icon for `clickable` cards.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/TaxOptimisationCard.vue:92`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/AffordabilityOverviewCard.vue:57`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/JourneyCard.vue:12, 21, 57`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/EmptyDashboard.vue:18`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/NetWorthSummary.vue:6, 30, 43`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/AreasToConsiderCard.vue:20–66` — 9 inline SVGs (document/calendar/shield/chart/cash/target/currency/home).
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/LifeTimelineCard.vue:39, 95, 153`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/ActionsOverviewCard.vue:29`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/GoalsOverviewCard.vue:11, 120, 132`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/GoalsCard.vue:62`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/AreasToCompleteCard.vue:21, 25, …`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/ProfileCompletionCards.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/AlertsPanel.vue`

  Note: several of these are themselves dead code (see Dead Code), but `AreasToConsiderCard`, `AreasToCompleteCard`, `JourneyCard`, `LifeTimelineCard`, `DashboardCard` are wired into `views/Dashboard.vue`.

**Detail views** (banned surface) — confirmed SVG icons present:

- `/Users/CSJ/Desktop/fynla/resources/js/views/NetWorth/CashOverview.vue` — 9 inline SVGs.
- `/Users/CSJ/Desktop/fynla/resources/js/views/Retirement/Projections.vue` — 4 (also dead, but in tree).
- `/Users/CSJ/Desktop/fynla/resources/js/views/Goals/GoalsDashboard.vue` — 4.
- `/Users/CSJ/Desktop/fynla/resources/js/views/Trusts/TrustsDashboard.vue` — 3.
- `/Users/CSJ/Desktop/fynla/resources/js/views/Estate/EstateDashboard.vue` — 3.
- `/Users/CSJ/Desktop/fynla/resources/js/views/Trusts/TrustDetailView.vue` — 2.
- `/Users/CSJ/Desktop/fynla/resources/js/views/Retirement/Recommendations.vue` — 2 (dead).
- `/Users/CSJ/Desktop/fynla/resources/js/views/Plans/PlansDashboard.vue` — 2.
- `/Users/CSJ/Desktop/fynla/resources/js/views/Retirement/PensionDetail.vue` — 1.
- `/Users/CSJ/Desktop/fynla/resources/js/views/Protection/ProtectionDashboard.vue` — 1.

**Emoji and Unicode glyphs** in `.vue` and `.js` files — 72 hits total. Spot-check of worst offenders:

- `/Users/CSJ/Desktop/fynla/resources/js/constants/goalIcons.js` — emoji map:
  - line 7 `emergency_fund: '🛡️'`, 11 `retirement: '☀️'`, 12 `wealth_accumulation: '📈'`, 14 `holiday: '✈️'`, 17 `custom: '⭐'`, 27 fallback `'🎯'`.
  - Consumed by `GoalCard.vue:309`, `GoalsAnalysis.vue:248–253`, `GoalsOverview.vue:38`, etc. **These render as icons on goal detail and dashboard cards.** Severity **high**, confidence **high**. Goals UI is banned surface.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalCard.vue:131` `<span class="text-lg">🔥</span>` — streak emoji on Goal cards.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalMilestoneTracker.vue:140–142` `icon: '⭐'`, `'🚀'`, `'🏆'`. Component is itself unused (Dead Code section) but ships in repo.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalsOverview.vue:38` `<span class="text-2xl">🔥</span>`.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalContributionStreak.vue:103–106` — emoji-driven streak progression (`❄️` / `🔥` / `🏆`).
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalsAnalysis.vue:248–253` `savings: '💰'`, `investment: '📈'`, `retirement: '☀️'`, fallback `'🎯'`.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/TrustPlanningStrategy.vue:252` `<strong>⚠️ {{ … }}</strong>` rendered to user.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/TrustPlanningStrategy.vue:271, 286` — `✓` / `✗` glyphs being `.replace`-stripped from incoming strings (defensive but indicates upstream API sends glyphs).
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/IHTMitigationStrategies.vue:11, 117, 127, 141` — `✓` and `⚠️` glyphs rendered in template strings, plus `→` as a bullet on line 141. Component itself is dead (Dead Code section), but file ships.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/GiftForm.vue:109, 112, 115` — `✓` / `⚠️` glyphs rendered.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/IHTPlanning.vue:623` `✓ This trust's value …`, 1637 `logger.error('❌ Failed to load IHT calculation:', error)` — log emoji, lower severity.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/AssetsLiabilities.vue:126` `<span … :title="asset.exemption_reason">ℹ️</span>` — info icon on assets list (banned surface).
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/BedAndISATransfers.vue:147` `<p>💡 …</p>`.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/HoldingForm.vue:48` `⚠️ You need to create an investment account first …`.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/TaxOptimizationRecommendations.vue:99, 135, 171` — repeated `💡` light-bulb glyph.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/LifeCoverRecommendations.vue:139, 150, 159` — `✓` / `✗` glyphs as inline indicators.

**Allowed surface verification**: SVG icons in `AppNavbar.vue` (sidebar) are legitimate per Rule #16.

**Chat surface (banned)**: 
- `/Users/CSJ/Desktop/fynla/resources/js/components/Shared/AiChatButton.vue:14, 31` — 2 SVGs. This is the chat **trigger button**, not a message. The button itself is debatable — it lives in the top nav and might be the only way to identify the entry point. Confidence **medium** that this is a violation.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Shared/AiChatPanel.vue` and `AiChatPanelShell.vue` — **0 SVGs each**. Chat panel chrome is clean.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Fyn/FynOnboardingChat.vue` — 0 SVGs (`grep -c "<svg"` returned 0 earlier).

Pattern recommendation: replace SVG-by-`v-if` blocks (like `AreasToConsiderCard:20–48`) with a single component that takes an `icon` slot — if Rule #16 changes for these surfaces, you swap once.

---

## Two-Fyn Architecture Compliance — CLEAN

- `/Users/CSJ/Desktop/fynla/app/Http/Controllers/Api/AiChatController.php:171–179` — single `$inOnboarding = $user->onboarding_completed === false && $user->onboarding_fyn_step !== null && config('onboarding.fyn_flow_enabled', true);` then a ternary dispatch to `onboardingDirector->handleUserMessage` vs `adviceFyn->handle`. Matches the canonical contract.
- `/Users/CSJ/Desktop/fynla/app/Services/AI/AdviceFyn.php:152–174` — `WRITE_TOOLS` constant lists every `create_*`, `update_*`, `delete_*`, `set_expenditure`, `capture_*` tool (including `create_what_if_scenario` per the contract). Line 562 strips these via `array_diff($names, self::WRITE_TOOLS)`. Confidence **high**.
- No `FynPersonaOrchestrator`, `FynPersonaInvoker`, `FynPersonaRegistry`, or `DataCapturePromptBuilder` PHP classes exist in `app/`. The only `FynPersonaOrchestrator` mention is `/Users/CSJ/Desktop/fynla/database/migrations/2026_04_22_000002_add_persona_state_to_ai_conversations.php:18` in a migration **comment** documenting an old column purpose — historical, not active.
- No `persona_state_change` SSE event in code. Only mentions are in `EvalDeltaBuilder.php:487` (anti-pattern detection) and `AdviceFyn.php:30` docblock noting it's NOT emitted. Confidence **high**.
- No `is_capturing` / "capturing pill" frontend UI. `consoleCapture.js` has an `isCapturing` flag for console-log capture, unrelated to chat state.

---

## Dead Code / Unused Exports

### Vuex modules registered but unused

3 modules consume bundle weight with **zero references** under their namespace:

- `/Users/CSJ/Desktop/fynla/resources/js/store/modules/dashboard.js` (184 lines) — imported into `store/index.js:6` and registered (line 66), but no `mapActions('dashboard'…)`, `mapState('dashboard'…)`, `dispatch('dashboard/…')`, or `'dashboard/'` namespace lookup anywhere in `components/` or `views/`. Severity **medium**, confidence **high**.
- `/Users/CSJ/Desktop/fynla/resources/js/store/modules/insights.js` (139 lines) — registered, but no `'insights/'` namespace usage outside the file itself.
- `/Users/CSJ/Desktop/fynla/resources/js/store/modules/household.js` (119 lines) — registered, but no `'household/'` namespace usage anywhere.

### Vue components — orphaned

Scan of all 509 component files; 106 candidates found. Verified samples confirm true orphans. Notable confirmed-dead (severity **medium** each — clutter, drift):

- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PlanSections/FeeAnalysisSection.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PlanSections/TaxStrategySection.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PlanSections/RiskAnalysisSection.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PlanSections/ActionPlanSection.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PlanSections/CurrentSituationSection.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PlanSections/GoalProgressSection.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PlanSections/RecommendationsSection.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/MonteCarloResults.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/ContributionPlanner.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/InvestmentOverviewCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PortfolioOverview.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/TaxOptimization.vue` (US-named, also a scoreboard violator)
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/TaxFees.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/RebalancingCalculator.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/WhatIfScenariosBuilder.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PerformanceAttribution.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PerformanceLineChart.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/BenchmarkComparison.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/FeeSavingsCalculator.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/GoalForm.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/GoalProjection.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/InvestmentReadinessGate.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/AccountStrategyCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/AssetsLiabilities.vue` (also has emoji)
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/CashFlow.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/CashFlowProjectionChart.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/EstateOverviewCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/EstateProjectionComparison.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/GiftCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/GiftingStrategy.vue` (logs to console too)
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/GiftingTimelineChart.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/IHTLiabilityGauge.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/IHTMitigationStrategies.vue` (contains acronym `IHT` and emoji)
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/LifePolicyStrategy.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/NRBRNRBTracker.vue` (acronym in filename!)
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/PensionAmendmentBanner.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Estate/TrustPlanning.vue` (only self-references)
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/ChartTypeToggle.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/EventIconsOverlay.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/EventTooltip.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalContributionStreak.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalCountdown.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalMilestoneTracker.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalsAnalysis.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalsByModule.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Goals/GoalsList.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/ActionsOverviewCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/AffordabilityOverviewCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/AlertsPanel.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/AreasToConsiderCard.vue` (vs. `AreasToCompleteCard` which IS used in Dashboard.vue)
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/CrossModuleInsights.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/GoalsCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/GoalsOverviewCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/GoalsProjectionChartMini.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/HouseholdNetWorth.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/InvestmentsOverviewCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/NetWorthSummary.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/SpousalOptimisations.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Dashboard/TaxOptimisationCard.vue` (probably superseded by another file)
- `/Users/CSJ/Desktop/fynla/resources/js/components/Protection/CoverageGapChart.vue` / `CoverageTimelineChart.vue` / `GapAnalysis.vue` / `PremiumBreakdownChart.vue` / `ProtectionOverviewCard.vue` / `RecommendationCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Retirement/AnnualAllowanceTracker.vue` / `DrawdownSimulator.vue` / `RequiredCapitalDetail.vue` / `SalarySacrificeDisplay.vue` / `StrategiesTab.vue` / `TaxBreakdownCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Risk/CapacityForLossSection.vue` / `RiskFactorsPanel.vue` / `TimeHorizonSection.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Savings/InterestRateComparisonChart.vue` / `MissingDataCard.vue` / `SavingsDecisionPath.vue` / `SavingsGoals.vue` / `SavingsOverviewCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Shared/InfoGuideButton.vue` / `ISAAllowanceSummary.vue` / `PostcodeLookup.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Admin/AdminInvestmentActions.vue` / `AdminProtectionActions.vue` / `AdminRetirementActions.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/NetWorth/Property/AmortizationScheduleView.vue` / `PropertyTaxCalculator.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Onboarding/FocusAreaGrid.vue` / `JourneyPreview.vue` / `ProfileReviewPanel.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Preview/KeepDataOrFreshModal.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Public/CalculatorCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/UserProfile/AssetsOverview.vue` / `BalanceSheetTab.vue` / `CashFlowTab.vue` / `DomicileInformation.vue` / `IncomeStatementTab.vue` / `LiabilitiesOverview.vue` / `SpouseDataSharing.vue` / `TaxSummaryCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Plans/Estate/EstateJointView.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Plans/Investment/AccountFeeProjectionChart.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Trusts/TrustsOverviewCard.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Legal/StrategyDisclaimer.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Actions/RecommendationFilters.vue`
- `/Users/CSJ/Desktop/fynla/resources/js/components/WhatIf/ScenarioDetail.vue`

Confidence: my regex was strict (looks for `import.*\bName\b` or `<Name`). It produced **106 candidates** out of 509 files (~21 %). Spot-check of 20 randomly chosen items confirmed 19 were genuinely unused; one (`TrustPlanning.vue`) was self-referenced within its own template (and so genuinely unused at the application level). Net estimate: **at least 100 components are truly orphaned**.

Recommendation: run a deletion sweep in batches by module. Most of these collapse cleanly because the import graph is small.

### Routed views that are not imported

- `/Users/CSJ/Desktop/fynla/resources/js/views/Retirement/Recommendations.vue` — no importer; defines no layout wrapper; ships a "Coming Soon" watermark.
- `/Users/CSJ/Desktop/fynla/resources/js/views/Retirement/Projections.vue` — no importer.

Severity **medium**, confidence **high**.

### PHP services with no callers

Two service classes never referenced outside their own file (sweep of `app/`, `routes/`, `tests/`):

- `/Users/CSJ/Desktop/fynla/app/Services/Estate/EstateActionDefinitionService.php` — 371 lines. Referenced ONLY in stale historical notes under `April/`. Severity **medium**, confidence **high**.
- `/Users/CSJ/Desktop/fynla/app/Services/Estate/IHTStrategyGeneratorService.php` — 363 lines. Same. Both have been flagged in prior reviews (`April/April5Updates/codeReview.md` H-6 / B-5) but never deleted.

### Controllers with no routes

- `/Users/CSJ/Desktop/fynla/app/Http/Controllers/Api/LifeEventAllocationController.php` — 146 lines, no route registers it. Flagged in `April/April5Updates/codeReview.md` H-6 with the instruction "wire routes under `/api/life-events/{id}/allocations` or delete." Still untouched. Severity **medium**, confidence **high**.

### JS utils with no importers

- **`/Users/CSJ/Desktop/fynla/resources/js/utils/ownership.js`** — provides `OWNERSHIP_TYPES`, `isSharedOwnership`, `calculateUserShare`, `getOwnershipLabel`. Documented as the canonical frontend helper in `/Users/CSJ/Desktop/fynla/resources/js/CLAUDE.md` (under "Utilities"). **Not imported anywhere.** Components doing share calculations duplicate the logic instead. Severity **high**, confidence **high**. Either re-introduce imports across the codebase (preferred — CLAUDE.md says so) or delete the file and update CLAUDE.md.

### Dead config

- `/Users/CSJ/Desktop/fynla/app/Traits/HasAiGuardrails.php:97` and `/Users/CSJ/Desktop/fynla/app/Services/AI/XaiClient.php:112` read `advanced_chat_model` from config, but **no caller activates it** (no `useAdvancedModel(true)` site in the codebase). Cited in `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/MEMORY.md` under `project_advanced_chat_model_branch.md` — flagged for "delete or repoint to Sonnet 4.6" in Sprint 3. Severity **low**, confidence **high**.

### Migration comment referencing removed class

- `/Users/CSJ/Desktop/fynla/database/migrations/2026_04_22_000002_add_persona_state_to_ai_conversations.php:18` — comment still says `FynPersonaOrchestrator state: current mode, …`. The class no longer exists. Severity **low**. Either update the comment to "Legacy persona orchestrator (removed Sprint 0; column retained for backwards compatibility)" or drop the column in a follow-up migration if it's no longer read.

---

## Duplicate Code

- **Inline `Intl.NumberFormat` reimplementations** — see Rule #6. Three separate copies inside `store/modules/netWorth.js` and four bespoke `formatCurrencyShort/Compact` methods in components.
- **Two different glow keyframes** annotated by their authors:
  - `/Users/CSJ/Desktop/fynla/resources/js/components/Journey/JourneyMap.vue:506` — TODO comment: "similar glow animation exists in OnboardingWizard.vue (nodePulse)".
  - `/Users/CSJ/Desktop/fynla/resources/js/components/Onboarding/OnboardingWizard.vue:1606` — TODO comment: "similar glow animation exists in JourneyMap.vue (nodeGlow)".
  Both authors flagged the duplication but neither extracted to `app.css`. Severity **low**, confidence **high**.
- **Score-colour ladder duplicated** across:
  - `FeeAnalysisSection.vue:223–225` (`if (score >= 80) … if (score >= 60) … if (score >= 40) …`)
  - `TaxStrategySection.vue:179–181`
  - `RiskAnalysisSection.vue:136–138` (uses `<= 3/5/7` instead but same shape)
  - `AssetLocationOptimizer.vue:425–427`
  - `DiversificationTab.vue:333–335`
  Identical logic, five copies. They're all candidates for deletion (Rule #13) but if any survive, factor into `utils/score.js`.
- **Three PlanSections** (FeeAnalysis / TaxStrategy / RiskAnalysis) each render a "card header + big number + label + ladder colour" shape. Could be one `<ScoreSection>` reusable component IF scores were allowed — they aren't.

---

## Naming Consistency

- **Module module-directory naming** mostly consistent (`Estate`, `Investment`, `Protection`, `Retirement`, `Savings`, `Goals`, `Trusts`, `Plans`). One gap:
  - `Coordination` module exists as `/Users/CSJ/Desktop/fynla/app/Services/Coordination/` (8 services) but has **no** `resources/js/components/Coordination/` and no Vuex `coordination.js`. The frontend coordination UI lives only at `/Users/CSJ/Desktop/fynla/resources/js/mobile/views/CoordinationDetail.vue` (mobile only). Decision needed: either fold the backend services into another module or surface the module on web. Severity **medium**, confidence **high**.
- **PHP class vs DB column casing** spot-check: all DB columns `snake_case`, all PHP classes `PascalCase`, methods `camelCase` (sample of 20 services confirms). Confidence **high**.
- **US vs UK spelling drift in filenames**: `TaxOptimization.vue` and `TaxOptimisationCard.vue` co-exist. So do `TaxOptimisationAgent.php` (UK) and `Tax/TaxOptimisationService.php` (UK). Frontend: `TaxOptimisationCard.vue` (UK) vs `TaxOptimization.vue` / `TaxOptimizationOverview.vue` / `TaxOptimizationRecommendations.vue` (US). All three US-named are in `components/Investment/`. Severity **low**, confidence **high**. Rename to UK for consistency, OR (cheaper) just delete since `TaxOptimization.vue` is dead (orphaned).
- **`PortfolioOptimizer.vue`** (US) — actively used; uses "Optimization Failed" in user-facing strings (line 153, 259, 268). Recommend rename to `PortfolioOptimiser.vue` AND replace user-facing strings to "Optimisation".
- **`AssetLocationOptimizer.vue`** — same pattern.

---

## Configuration Drift

- **`fynlaDesignGuide.md` is v1.3.0**, but `/Users/CSJ/Desktop/fynla/CLAUDE.md` Rule #11 / #12 still says v1.2.0. Severity **medium**, confidence **high**.
- **CLAUDE.md asset counts** vs reality:
  - "Vue Components: 729" vs actual: 509 (components/) + 153 (views/) + 71 (mobile/) ≈ 733. Off by ~4. Close enough.
  - "32 module directories" in `app/Services/` — actual `app/Services/` mindepth=1 subdirectories: **38**. Off by 6. Severity **low**.
  - "488 components across 29 module directories" — components: 509 (off by 21), subdirectories: 34 (off by 5). Severity **low**.
  - "488 components across 29 module directories" — components actually 509.
  - "138 views" vs actual 153. Off by 15.
  Recommendation: regenerate these counts or remove the table (they always drift).
- **CLAUDE.md "Frontend" sub-section** says: "33 namespaced Vuex modules". Actual: **35** registered in `store/index.js` (counted lines 5–39). Three of those 35 are unused (`dashboard`, `insights`, `household`) — see Dead Code. Net "live" count is 32. The CLAUDE.md text is one number short of registered count and one number over live count.
- **`.env.example`** — 79 keys; `env(…)` calls in `app/` and `config/` reference 137 distinct keys, so **58 are not in `.env.example`**. Most are Laravel defaults (`REDIS_*`, `AWS_*`, `MAIL_URL`, `MEMCACHED_*`, `SQS_*`, `MAILGUN_*`, `POSTMARK_TOKEN`, `DYNAMODB_*`) — typical Laravel template noise. Real gaps that operators MUST configure (and are therefore worth adding):
  - `ANTHROPIC_ADVANCED_CHAT_MODEL`, `XAI_ADVANCED_CHAT_MODEL` (dead config — see Dead Code)
  - `FYN_EVAL_ENFORCE_MINIMA`, `FYN_EVAL_TOKEN_TTL_MINUTES` (eval canonical config, deploy-critical)
  - `ONBOARDING_FYN_FLOW_ENABLED` (read in `AiChatController.php:173`)
  - `LIFECYCLE_TEST_RECIPIENT`, `LIFECYCLE_ENGINE_ENABLED`, `LIFECYCLE_THROTTLE_MS` (lifecycle engine)
  - `GETADDRESS_API_KEY` (postcode lookup)
  - `AWIN_*` family (affiliate)
  - `FCM_SERVER_KEY` (push notifications)
  - `SANCTUM_TOKEN_EXPIRATION`, `SANCTUM_TOKEN_PREFIX`
  - `BCRYPT_ROUNDS`
  - `ACCOUNT_RETENTION_YEARS`
  - `ADMIN_EMAILS`
  Severity **medium**, confidence **high**.

---

## Documentation Rot

- `CSJTODO.md` lines 1262 and 1292/1298 still describe the **rejected** three-persona / `FynPersonaOrchestrator` plan as if pending. Two-Fyn collapse has already shipped (Sprint 0). Severity **low** — these are historical notes in a TODO file, not active plans, but they confuse readers.
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_04_22_000002_add_persona_state_to_ai_conversations.php:18` — comment references `FynPersonaOrchestrator` (removed class).
- `April/` and `May/` updates folders contain dated review docs that reference services/components/columns that have since been deleted or repurposed. Defensible as session archives, but if anything in there is sourced from `vault-sync`, it should be audited separately.
- `CLAUDE.md` Mobile section claims "Custom artisan commands: `preview:reset`, `audit:purge`, `trials:expire`, `sessions:cleanup`, `registrations:cleanup`" — not audited, but worth verifying these all exist in `app/Console/Commands/`.

---

## TODO / FIXME / HACK / XXX Backlog

Active TODOs in production code (not generated content / not aging context):

- `/Users/CSJ/Desktop/fynla/app/Http/Middleware/SecurityHeaders.php:59` — `// TODO: Migrate to nonce-based CSP when Revolut SDK supports it (tracks Revolut SDK changelog).` — proper TODO with conditions.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PortfolioOptimization.vue:198` — `// TODO: Implement rebalancing plan creation`. Note: file is **dead code** in `Investment/` per the unused-components scan. Either implement on the active rebalance UI or delete.
- `/Users/CSJ/Desktop/fynla/app/Constants/TaxDefaults.php:108` — `// CSJTODO S-3` reference (sprint TODO; tracked).
- `/Users/CSJ/Desktop/fynla/app/Services/Tax/Strategies/LifecycleStrategy.php:116` — `// CSJTODO S-3 promotes this to TaxConfigService`.
- `/Users/CSJ/Desktop/fynla/app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php:48` — `// CSJTODO S-3`.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Journey/JourneyMap.vue:506` and `/Users/CSJ/Desktop/fynla/resources/js/components/Onboarding/OnboardingWizard.vue:1606` — paired TODO comments about the duplicate glow animation (see Duplicate Code).

The `EvalRecordings.vue` "TODO:" strings are template content fed into eval reports (not abandoned code) — ignore.

---

## Bundle Bloat / Dependency Hygiene

`/Users/CSJ/Desktop/fynla/package.json`:

- **`axios` in `devDependencies`** but used in runtime: `resources/js/bootstrap.js:7` and `resources/js/services/api.js:1`. Should be in `dependencies`. Severity **low** — it's installed either way during dev install; matters only for production install when `--production` flag is set. Confidence **high**.
- **`@playwright/test`, `@vue/test-utils`, `jsdom`, `vitest`** in `devDependencies` — correct.
- **`@capacitor/cli`** in `devDependencies` — correct (build-time only).
- **`apexcharts` (5.x) AND `vue3-apexcharts` (1.9.x)** — both used (charts directly use `apexcharts`, components use `vue3-apexcharts`). No duplication; keep both.
- **No moment + dayjs collision** found.
- **No fetch + axios alternative collision** — code uses axios with a single `fetch()` fallback in `aiChatService.sendMessageStream` (intentional, for SSE streaming on Capacitor).
- **`dompurify`** — sanitiser, used in `utils/insightsSanitize.js`. Live.
- **`mammoth`** + **`jszip`** — used only in `components/Admin/Documents/DropZone.vue`. If admin doc upload is rarely loaded, both should be dynamically imported to keep them out of the main bundle.
- **`@tiptap/*`** family — 12 packages — used only in `components/Admin/Insights/RichTextEditor.vue`. Same dynamic-import recommendation as `mammoth`/`jszip`. Severity **medium** (bundle size), confidence **high**.

`/Users/CSJ/Desktop/fynla/composer.json`:

- **`anthropic-ai/sdk` AND `openai-php/client`** both required. Verified `openai-php/client` is used only by `XaiClient.php` to talk to grok via OpenAI-compatible API; `anthropic-ai/sdk` is used in `HasAiChat.php`. No duplication.
- **`barryvdh/laravel-dompdf` AND `smalot/pdfparser`** — write vs read. No overlap.
- **`bacon/bacon-qr-code` AND `pragmarx/google2fa-laravel`** — both 2FA, complementary (QR rendering + algorithm). Fine.
- **`mews/purifier`** (HTML sanitizer for PHP) AND `intervention/image` (image manipulation) — both used.

---

## Mobile / iOS Specifics (CLAUDE.md "Mobile App")

`/Users/CSJ/Desktop/fynla/vite.config.js`:

- Line 22: `base: process.env.VITE_BASE_PATH || '/'` — OK.
- Lines 56–64: `vue()` plugin has `template: { transformAssetUrls: false }` — OK (matches CLAUDE.md mandate).
- Line 65: `!disablePWA && VitePWA(…)` — OK (conditional).
- `build.rollupOptions` does NOT contain `external` for image paths — OK.
- Port: line 25 `port: 5173, strictPort: true` — matches `feedback_vite_canonical_port_5173.md`.

`/Users/CSJ/Desktop/fynla/resources/js/mobile/`:

- `MoreMenu.vue:40` — uses `auth/mobileLogout` (not `auth/logout`). OK.
- `SettingsList.vue:189` — uses `import.meta.env.VITE_API_BASE_URL || 'https://fynla.org'`. OK.
- No `auth/logout` call site found in `mobile/`.

**Mobile compliance is clean** for Rule "biometric logout / vite.config / API base".

---

## Linting / Formatting Config

- **`.editorconfig`** present, sets 4-space indent, LF line endings, charset utf-8. OK.
- **`.eslintrc*`** — **NOT present**. No ESLint config in the repo root.
- **`.prettierrc*`** — **NOT present**.
- **`pint.json`** — **NOT present** (Laravel Pint uses defaults, which is acceptable PSR-12).
- **`.php-cs-fixer*`** — **NOT present**.

Severity **medium** for the missing ESLint config — without it, all 16 frontend rules above are enforced **only** at code-review time. An ESLint config that bans:
- emoji literals in `.vue` files,
- the strings `'#FF'…` in `<style scoped>` blocks,
- `console.log` / `console.warn` (use `logger`),
- `formatCurrency` method definitions in components (must come from `currencyMixin`),
- the word `Optimization`/`Optimize` in template text,

…would catch many of the violations listed above automatically. Confidence **high**.

---

## Module Structure Symmetry

| Module | Agent | Services dir | Controller | Vuex | Components dir | Views dir |
|---|---|---|---|---|---|---|
| Protection | `ProtectionAgent.php` | `Services/Protection/` | yes | `protection.js` | yes | yes |
| Savings | `SavingsAgent.php` | `Services/Savings/` | yes | `savings.js` | yes | yes |
| Investment | `InvestmentAgent.php` | `Services/Investment/` | yes | `investment.js` | yes | yes |
| Retirement | `RetirementAgent.php` | `Services/Retirement/` | yes | `retirement.js` | yes | yes |
| Estate | `EstateAgent.php` | `Services/Estate/` | yes | `estate.js` | yes | yes |
| Goals | `GoalsAgent.php` | `Services/Goals/` | yes | `goals.js` | yes | yes |
| Coordination | `CoordinatingAgent.php` | `Services/Coordination/` | (no module controller; uses dashboard) | **MISSING** | **MISSING** | mobile only |
| TaxOptimisation | `TaxOptimisationAgent.php` | `Services/Tax/` | yes | `taxStrategy.js` | `components/TaxStrategy/` | `views/TaxStrategy/` |

`TaxOptimisationAgent` is described in CLAUDE.md as an "Agents (9)" entry but isn't included in the listed seven modules. Naming is also inconsistent — backend `TaxOptimisation*` vs frontend `TaxStrategy*`. Severity **low**, confidence **high**. Decision needed: is it a module on equal footing with the seven, or a sub-system of Coordination?

---

## Spelling Consistency (British)

User-facing US-spelling violations:

- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/PortfolioOptimizer.vue:153` `Optimization Failed`
- `:259` `Optimization Details`
- `:268` `Optimization Type:`
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/AssetLocationOptimizer.vue:20, 22` template comments mention "Optimization Score" but the rendered string says "Optimisation" — comments OK, but file/component naming is US.
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/TaxOptimization.vue:18` template comment "Tax Optimization Content".
- `/Users/CSJ/Desktop/fynla/resources/js/components/Investment/TaxFees.vue:111` `<!-- Tax Optimization Opportunities -->` — comment.
- `/Users/CSJ/Desktop/fynla/resources/js/views/Version.vue:1463` `Color coding (blue assets, red liabilities)` — release-notes string visible to admins reading the version page.

Severity **low**, confidence **high**.

The code-identifier side (`optimize()`, `color: '#...'`) is correctly American per the rule. The mixed convention is correct; only the rendered English needs fixing.

---

## Console Logging in Frontend

- 17 raw `console.warn/info` calls remain in `resources/js/components/` and `views/`. CLAUDE.md says to use `logger.warn/info` instead. Severity **low**.
- All in defensive `catch` paths and don't leak PII. Replace at low priority.

Full list:
- `Estate/GiftingStrategy.vue:590, 597, 626, 633` (also a dead component).
- `Investment/EfficientFrontier.vue:441`.
- `Protection/GapAnalysis.vue:676`, `ProtectionOverviewCard.vue:451`, `PremiumBreakdownChart.vue:154`, `CurrentSituation.vue:783`.
- `UserProfile/LetterToSpouse.vue:1117, 1124`, `FamilyMembers.vue:387, 397`.
- `Onboarding/steps/IncomeStep.vue:561`.
- `views/Dashboard.vue:2184`.

---

## Things Worth Reporting (Out of Scope but Noticed)

These are findings I'd surface to CSJ even though they aren't strictly on my audit list:

1. **`AccountStrategyCard.vue`, `InvestmentReadinessGate.vue`, `GoalForm.vue`, `GoalProjection.vue`** in `components/Investment/` are all dead — and the directory still holds them next to live components. New contributors will be confused which `GoalForm.vue` to copy when they want to make a goal form.
2. **`tests/Architecture/`** contains 10 tests including `HardcodedValuesArchitectureTest.php` and `NoStaleReferencesTest.php`. If these are passing while Rule #3 has 20+ literal fallbacks, they may be testing the wrong thing or whitelisting too much.
3. **Vuex strict mode** (`store/index.js:115`) is `process.env.NODE_ENV !== 'production'` — correct. But `process.env.NODE_ENV` evaluation in browser bundles depends on Vite's `mode` flag; consider replacing with `import.meta.env.MODE !== 'production'` for clarity.
4. **`personas/` directory** under repo root contains personas JSON files. These overlap with seeded preview personas. Worth a sweep — likely orphan demo files.
5. **`Marketplace.md`, `System Map.md`, `Persona Data.md`, `Home.md`** at repo root — orphan planning docs that should live in `fynlaBrain/` vault or under `April/`. They confuse the root README.
6. **`create_trial.php` at repo root** — looks like a one-off script. Either move to `tools/` or delete.
7. **`tech-debt-report.md` at repo root** — date unknown, content not audited. If it's a snapshot, archive into `May/MayXUpdates/` like the other reports.
8. **`router/index.js` has 1300+ lines** — biggest single file. Splitting by route group would help maintainability; not urgent.
9. **All Vue files** total **733**, exceeding the 729 quoted in CLAUDE.md by 4. Of those, ~100 are confirmed dead per the unused-components scan. Net live count ≈ 633.

---

## Summary by Severity

**Critical (block release)** — 0.

**High (act this sprint)**:
- Rule #16: Dashboard cards and detail views ship with decorative SVG icons (14 + 19 files); Goals UI uses emoji as icons throughout.
- Rule #14: `views/NetWorth/CashOverview.vue` ships without `AppLayout` wrap.
- Rule #13: `AssetLocationOptimizer.vue`, `DiversificationTab.vue`, and likely `TaxOptimizationOverview.vue` render numeric scores in user-facing UI.
- Dead code: `~100 Vue components` orphan, `LifeEventAllocationController.php`, `EstateActionDefinitionService.php` (371 lines), `IHTStrategyGeneratorService.php` (363 lines), `utils/ownership.js` (canonical-but-orphaned).

**Medium (act next sprint)**:
- Rule #3: ~20 hardcoded tax fallbacks in `?? 0.20` / `?? 12570` form.
- Rule #6: 3 inline `Intl.NumberFormat` reimplementations in `store/modules/netWorth.js`.
- Rule #11/12: hex literals in `<style>` blocks (mostly print stylesheets but `AssetBreakdownBar.vue:203` is live UI).
- Configuration drift: design guide version cited as v1.2.0 in CLAUDE.md but file is v1.3.0; `.env.example` missing ~10 operationally-critical keys.
- Naming: 3 Vuex modules registered but unused.
- Bundle: `@tiptap/*` + `mammoth` + `jszip` not dynamic-imported (~admin-only code in main chunk).
- Missing: ESLint config to mechanically enforce Rules 6, 9, 11, 12, 13, 16.
- Module symmetry: `Coordination` has backend but no web frontend; `TaxOptimisation` agent has no parallel module structure on frontend.

**Low (backlog)**:
- Rule #4: one outlier in `LpaUploadForm.vue` (emits `uploaded` not `save`).
- Rule #10: MPAA / NRB / RNRB / DB-writes acronyms on a few pages (Insights and admin).
- Rule #15: one `it.skip()` in `tests/frontend/components/Dashboard/AlertsPanel.test.js:66`.
- Spelling: `PortfolioOptimizer.vue` user-facing "Optimization Failed".
- Console: 17 `console.warn/info` calls in Vue (use `logger`).
- Migration comment mentioning removed class.
- CLAUDE.md asset counts drifted (729 vs 733, 32 vs 38, 488 vs 509, 33 vs 35).
- `package.json`: `axios` in `devDependencies` but runtime-used.
- `CSJTODO.md` historical sections reference rejected `FynPersonaOrchestrator` work.
- Repo-root orphan files (`Marketplace.md`, `System Map.md`, `Persona Data.md`, `Home.md`, `create_trial.php`, `tech-debt-report.md`).

---

## What's Clean (Worth Calling Out)

- **Rule #5** (canonical enums) — completely clean; no `'sole'` anywhere.
- **Rule #9** (no amber/orange) — completely clean; only meta-comments reference the ban.
- **Rule #1** (manual upload) — no auto-deploy/zip scripts.
- **Rule #8** (PreviewWriteInterceptor) — middleware is current and exhaustive.
- **Two-Fyn architecture** — no `FynPersonaOrchestrator` / `FynPersonaInvoker` / `FynPersonaRegistry` / `DataCapturePromptBuilder` classes; `AdviceFyn::WRITE_TOOLS` is explicit; dispatch is a single if-statement; no `persona_state_change` SSE leakage; no "capturing pill" UI.
- **`vite.config.js`** — every iOS mandate is honoured.
- **Mobile patterns** — `auth/mobileLogout`, `VITE_API_BASE_URL` fallback both correct.
- **All PHP files have `declare(strict_types=1)`** — 0 violations across `app/`.
- **No `dd()` / `var_dump()` / `print_r()` debugging stubs** in `app/`.
- **No raw use of `DB` facade** for module work (sampled).
- **`PreviewWriteInterceptor::EXCLUDED_ROUTES`** has the right shape for every auth-adjacent endpoint.
- **`composer.json`** — appropriate split between `require` and `require-dev`. Pest, Mockery, Faker, Pint, Ignition correctly in dev.
- **`tailwind.config.js`** — only palette tokens; no leaked `amber`/`orange`/`gray`/`red`/`green` (semantic green/red used in safelist for risk-level meters; defensible because they map to the spring/raspberry palette).
- **Joint-asset pattern** — `joint_owner_id` consistently used; trait `CalculatesOwnershipShare` exists; backend story is clean (frontend mirror is the only gap).

---
*Audit completed 2026-05-12.*
