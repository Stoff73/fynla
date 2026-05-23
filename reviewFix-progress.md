# reviewFix branch — progress checklist

Tracks remediation of issues catalogued in `docs/tech-debt-report-full.md` (2026-05-23 audit).
Branch off `dev`. Working on top of `99400ce`.

**Total issues:** 178 (14 critical, 7 high, 76 warning, 81 suggestion)

**Legend:** `[x]` done · `[~]` partial / needs follow-up · `[ ]` not started · `[!]` blocked (needs CSJ decision)

---

## Immediate (Quick Wins — Critical Security & Trivial Fixes)

- [x] Q1 — `composer update symfony/*` — all 7 CVEs cleared (`composer audit` now reports "No security vulnerability advisories found")
- [~] Q2 — `npm audit fix` applied (non-breaking shifts only); 8 vulnerabilities remain (3 high / 5 moderate) but all require breaking-change major upgrades (Capacitor CLI 7→8, Vite 5→8, vite-plugin-pwa, native-biometric 7→8) — **deferred to dedicated dependency-bump branch** (needs mobile Xcode rebuild + Vite migration testing)
- [x] Q3 — Deleted `app/Services/Estate/IHTStrategyGeneratorService.php` (489 lines, dead, flagged twice)
- [x] Q4 — Fixed `LetterEstateWarnings.vue:125` → `/estate/letter-validation` (drops the duplicate `/api/` prefix)
- [x] Q5 — Replaced joint-ISA-success test with 2 rejection tests (cash_isa account_type + is_isa=true paths). Added `withValidator()` guard on both StoreSavingsAccountRequest + UpdateSavingsAccountRequest. Pest green (27/27 + 45/45 in Savings + Property/Mortgage suites).
- [x] Q6 — Deleted `store/modules/dashboard.js` + `household.js` + 4 references in `store/index.js` (imports, registrations, persistence path)
- [x] Q7 — Deleted `AssetLocationController::clearUserAssetLocationCache` + `InvestmentController::calculateAccountAnnualisedReturn`
- [x] Q8 — Added `user_id` (via `User::factory()`) to PropertyFactory / MortgageFactory / ChattelFactory / BusinessInterestFactory + `joint()` state on all 4 + Property `trust()` state. Defaulted all 4 to individual ownership (was random). Also reworked CashAccountFactory + SavingsAccountFactory: added `joint()` + `isa()` states with explicit safeguards that joint state forces is_isa=false and isa state forces ownership_type=individual (UK joint-ISA illegality)
- [x] Q9 — `php artisan schema:dump` refreshed dump from 244 → 384 migrations (140 catch-up)
- [!] Q10 — Soft-delete fate for 11 financial models (Mortgage, Property, Goal, SavingsAccount, CashAccount, Investment/Holding, Subscription, Estate/Will, LifeInsurancePolicy, DBPension, DCPension) — **needs CSJ call: add SoftDeletes everywhere OR drop unused `deleted_at` columns**

## Short-term (Critical UI / Convention)

- [x] S11 — Stripped 8 Rule #13 score badges: RiskAnalysisSection (Current Risk Score /10, Target Risk Score /10, Risk Alignment %), FeeAnalysisSection (Fee Efficiency Score %), TaxStrategySection (Tax Efficiency Score % + "Optimisation score" caption), AssetLocationOptimizer (radial Asset Location Score), AccountRebalancingPanel ("Drift Score" → "Average drift from target"), DiversificationTab (score badge pill → descriptive label), TaxOptimizationOverview (4× % progress bars → descriptive bullet list of what's analysed). Each replaced with descriptive text + real metrics (£ / time periods / categorical labels). PortfolioStrategyPanel was already compliant (uses getTaxEfficiencyLabel text, not a number) — no change.
- [x] S12 — Wrapped `views/NetWorth/CashOverview.vue` in `<AppLayout>` — added import + component registration, nested existing root div, closes Rule #14 violation
- [x] S13 — Renamed `components/Protection/CurrentSituation.vue` → `ProtectionModuleOverview.vue` and `components/Savings/CurrentSituation.vue` → `SavingsModuleOverview.vue` (avoided collision with existing `Plans/Protection/ProtectionCurrentSituation.vue`). Updated `name:` declarations + the two view imports. Also deleted `components/Investment/GoalCard.vue` (zero references — true dead code, not a duplicate concern any more).
- [ ] S14 — Extract `IHTPlanningPayloadBuilder` service to break LifePolicyController→IHTController inter-controller injection
- [x] S15 — `AssetLocationController` `buildDefaultTaxProfile()` + `calculateIncomeTaxRate()` now fail loud via new `requireTaxValue()` helper — no more silent 2026/27 substitution when TaxConfigService is missing a key. `$annualIncome ?? 50000` also tightened to `?? 0` (income is a profile field, not tax data, and 0 is a legitimate non-earner value)
- [ ] S16 — Migrate 8 legacy PHPUnit-style tests to Pest (Property + Mortgage cluster first)
- [x] S17 — Swept 31 Feature test files (210 calls) replacing bare `$this->actingAs($user)` with `$this->actingAs($user, 'sanctum')` for `/api/*` endpoints. Initially tried `Sanctum::actingAs($user)->fooJson(...)` but that chain breaks because Sanctum::actingAs returns the User, not the TestCase — reverted and used the canonical second-arg form per `tests/CLAUDE.md`. Smoke green: 1546 of 1547 tests passing (1 pre-existing PaymentWebhookRaceTest failure, untouched by sweep).
- [~] S18 — Created `tests/Helpers/TaxConfigFixture` with `inheritanceTax()`, `incomeTax()`, `isaAllowances()`, `pensionAllowances()` static methods + an `apply(MockInterface)` helper that wires all four into a Mockery mock in one call. All values sourced from `TaxDefaults::*` so a tax-year roll updates the helper, not 7 tests. **Migrating the existing 7 tests to consume it is deferred** to keep this branch from sprawling — the helper exists, the migration is mechanical when each test is next touched.
- [x] S19 — Added 5 missing keys to `.env.example` (ADMIN_EMAILS, AGENT_INTERNAL_TOKEN, OPENAI_API_KEY, SSH_PASSPHRASE, VITE_INSIGHTS_CMS_ENABLED)
- [x] S20 — Added `use Auditable` to User (with high-frequency exclusion list for last_login_at / failed_login_count / locked_until / etc. to avoid drowning the audit table), Household, NotificationPreference, SavingsGoal, SubscriptionPlan. Pest auth suite (81 tests) still green.
- [ ] S21 — Consolidate `familyMembersService` (delete dupe methods from `userProfileService`, repoint consumers)

## Medium-term (God-file decomposition — surgical wins, flag rest)

- [~] M22 — Action-Definition splits (Savings 3690 / Retirement 2719 / Protection 2350 / Investment 1486) — **too large for one branch; deferred to dedicated PRs per service**
- [~] M23 — God controller splits (Payment 1159 / Investment 1074 / Admin 893 / Auth 871 / Goals 792) — **too large for one branch; deferred to dedicated PRs per controller**
- [~] M24 — Vue god component splits (TaxSettings 3068 / ExpenditureForm 2574 etc.) — **too large for one branch; deferred to dedicated PRs per component**
- [~] M25 — Removed 12 orphan mutations after verifying ZERO `commit('mut'` and `commit('module/mut'` references for each: estate/setRecommendations, estate/addLiability, protection/setRecommendations, savings/setRecommendations, investment/setOptimizationResult, plans/clearPlan, goals/CLEAR_GOAL_DEPENDENCIES, trusts/clearError, trusts/SET_PREVIEW_MODE, businessInterests/clearError, chattels/clearError, spousePermission/clearError. **73 orphan Vuex actions + ~83 dead service methods deferred** — they need per-call verification against admin tooling + platform-specific paths that grep can miss; not a job for a passive sweep.
- [ ] M26 — Walk 30 dead model scopes, delete confirmed-dead
- [~] M27 — Introduce `TaxProductReferenceStore` — **deferred to SP1 Pass 2 R5 sub-track**
- [~] M28 — Collapse `AiToolDefinitions` + `XaiToolDefinitions` via shared registry — **large refactor, deferred**
- [x] M29 — AdvicePromptBuilder now `use`s ResolvesIncome + ResolvesExpenditure traits. `calculateTotalUserIncome()` delegates to `resolveGrossAnnualIncome()`, `calculateTotalExpenditure()` delegates to `resolveMonthlyExpenditure()['amount']`. Closes the Fyn-advice tax-band estimation regression where annual_expenditure-only users got 0.

## Backlog

- [x] B30 — Created `App\Constants\SignificanceThresholds` (IMPORTANT=100000, CRITICAL=200000) + `TaxConfigService::getCLTLifetimeRate()` helper. Both replace literals — see B43, B44 entries.
- [x] B31 — Swept 4 service files (dashboardService 4 calls, authService 1, investmentService 2, occupationService 1) replacing `console.error/warn` with `logger.error/warn` and adding the import. Kept api.js raw console (audit notes circular-dep risk with logger) and consoleCapture.js (it IS the capture mechanism).
- [~] B32 — Switch `MonteCarloSimulator` from `DB::table` to Eloquent model — **needs migration; deferred**
- [x] B33 — Added `UserFactory::preview()` (sets `is_preview_user=true`) and `UserFactory::advisor()` (sets `is_advisor=true`).
- [x] B34 — Dropped `ai_chat_enabled` orphan column via `2026_05_23_080000_drop_ai_chat_enabled_from_users_table.php`. Removed the lone ChrisUserSeeder reference. Migration applied + reseeded clean.
- [x] B35 — Added `tests/Architecture/AdviceFynWriteToolParityTest.php` enforcing that every tool whose name starts with `create_` / `update_` / `delete_` / `capture_` / `set_` and that AiToolDefinitions exposes must appear in `AdviceFyn::WRITE_TOOLS`. Catches the regression where a new write-prefix tool gets added to the catalogue but forgotten on the strip list. PASS at 6 assertions.
- [~] B36 — Split 22 services in 500–800 line range — **deferred**
- [ ] B37 — Fix `Estate/IHTController:163` response shape (wrap in `data` envelope) — **frontend-breaking, deferred to dedicated PR**
- [~] B38 — Investigated: both `Estate/LpaDetailView.vue` and `WillBuilder/WillBuilderReviewStep.vue` have explicit inline `/* Print document — exact hex values required for print/document fidelity. … These are inside :deep() document renderers and must not use Tailwind utilities. */` comments. The hex is documented and intentional (print/PDF rendering inside :deep() blocks where Tailwind tokens don't apply). Not a real violation — left as-is.
- [x] B39 — Fixed `Shared/ModuleStatusBar.vue:239` `border-bottom: 1px solid #FECDD3;` → `@apply border-b border-raspberry-100;`.
- [x] B40 — Replaced triplicated `['#5854E6', '#E83E6D', '#20B486', '#E6C9A8', '#6C83BC', '#1F2A44']` palette literal in `Advisor/{AdvisorClientDetail,AdvisorClientList,AdvisorDashboard}.vue` with `CHART_COLORS` imported from `@/constants/designSystem`. (Note: the imported CHART_COLORS sequence differs slightly from the inlined sequence — the canonical order from designSystem.js is now used; this is the canonical-source-of-truth fix the audit asked for.)
- [x] B41 — Sweep-replaced 8 sites of `#E8326E` → canonical `#E83E6D` (raspberry-500) in `views/Public/learn/LearnHubPage.vue`.
- [~] B42 — Investigated: all 3 keyframes have a justified reason to stay local. `JourneyMap.nodeGlow` (filter:drop-shadow) and `OnboardingWizard.nodePulse` (box-shadow) both have inline `/* TODO: similar … kept separate intentionally. */` comments explaining the divergent effects. `NetWorthOverviewCard.loading` is a background-position shimmer that's too specific to globalize. Left as-is.
- [x] B43 — Promoted 11 of the 12 `> 100000` / `> 200000` literals across PriorityRanker (5), HolisticPlanner (4), DashboardAggregator (2) to `SignificanceThresholds::IMPORTANT` / `SignificanceThresholds::CRITICAL`. PHP syntax verified clean.
- [x] B44 — `TaxConfigService::getCLTLifetimeRate(): float` now wraps the lookup-with-0.20-fallback. Migrated all 3 duplicates: PersonalizedTrustStrategyService:167 + :468, GiftingStrategyOptimizer:292.
- [x] B45 — `LifeStage/LifeStageService:105` `200000` literal promoted to `private const PENSION_INDEPENDENT_THRESHOLD = 200000;` with a docblock explaining the heuristic.
- [~] B46 — `HoldingsAllocationService` extraction deferred — touches controllers that are themselves on the M23 god-controller split list, better done as part of that.
- [~] B47 — Goals/{GoalCalculationService,GoalProgressService} consolidation deferred — needs a careful look at all consumers; flagged for a follow-up Goals-module PR.
- [x] B48 — Added `2026_05_23_080001_add_funding_source_index_to_plan_action_funding_selections.php` — adds `funding_source_id` index + `(funding_source_type, funding_source_id)` composite for polymorphic lookups. Migration applied.
- [x] B49 — Added `User::markAsAdvisor()` mutator + `UserFactory::advisor()` state. AdvisorClientSeeder now calls `$advisor->markAsAdvisor()` instead of `DB::table('users')->update(['is_advisor' => true])`.
- [~] B50 — PreviewController seed-flow extraction deferred — refactor with too much risk on a passive cleanup branch; the controller IS itself a god surface (M23 candidate).
- [x] B51 — PreviewController error path now uses `$this->errorResponse($e, 'Persona seeding')` via the SanitizedErrorResponse trait — no more raw `$e->getMessage()` interpolation, full exception details logged server-side via Log::error, sanitised message returned to client (per the trait's existing contract).
- [x] B52 — Added `app/Http/Resources/DCPensionResource.php` (mirrors InvestmentAccountResource pattern, surfaces every fillable column explicitly + holdings relation). RetirementController:storeDCPension now wraps `$pension` via `new DCPensionResource($pension)` instead of returning the raw Eloquent model.
- [x] B53 — Investigated `tests/Architecture/EvalScenarioCountTest.php:18` — the `->skip()` is conditional on `config('fyn_eval.enforce_minima')` and the closure documents the plan reference + the `FYN_EVAL_ENFORCE_MINIMA=true` activation path. Not a tracking ticket leak; intentional gate. No fix.
- [x] B54 — Renamed `tests/Browser/scenarios/document-articles-end-to-end.php` → `BS-03-document-articles-end-to-end.php` (BS-03 was the lowest free number in the sequence). Updated the docblock title from `BS — Document Articles end-to-end` to `BS-03 — Document Articles end-to-end`.
- [x] B55 — All 6 factories now have `joint()` state methods (covered by Q8's expanded scope: Mortgage, Property, Chattel, BusinessInterest had user_id + joint() added; Cash + Savings already had user_id, gained joint() + isa() with the UK joint-ISA illegality safeguard). Verified via grep.

---

## Out-of-scope for this branch (large refactors needing dedicated PRs)

These are tracked above with `[~]` and explained inline. Rough scoping for follow-up:

- **God-file decomposition** (M22–M24, M28, B36) — each god file is its own design + review surface. Bulk-splitting on one branch creates an unmergeable mess. Each goes in a per-file PR.
- **`TaxProductReferenceStore`** (M27) — belongs to the SP1 Pass 2 R5 sub-track, not this cleanup branch.
- **`MonteCarloCacheEntry` model** (B32) — needs a migration. Could fit here but is best paired with a SoftDeletes decision sweep.
- **`IHTController` response envelope** (B37) — frontend-breaking; needs coordinated frontend update.

---

## Commit groups (planned)

1. Critical security: composer + npm updates → `chore(security): patch symfony CVEs + npm vulns`
2. Quick wins dead code: Q3 + Q6 + Q7 + Q11 (Rule #13 partials) → `chore: delete dead code surfaced by audit`
3. Frontend bug + Rule #14 + Rule #13 sweep: Q4 + Q5 + S11 + S12 → `fix(ui): close score-badge / Rule #14 / API bug audit findings`
4. Factories + schema: Q8 + Q9 + B55 → `chore(db): fix factory FKs + refresh schema dump`
5. Convention sweeps: S17 + S18 + S19 + S20 + S21 → `refactor: convention drift surfaced by audit`
6. ... and so on.
