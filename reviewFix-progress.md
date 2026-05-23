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

- [ ] S11 — Strip 9 Rule #13 score badges in Investment surfaces (RiskAnalysisSection, FeeAnalysisSection, TaxStrategySection, AssetLocationOptimizer, AccountRebalancingPanel, DiversificationTab, PortfolioStrategyPanel, TaxOptimizationOverview)
- [x] S12 — Wrapped `views/NetWorth/CashOverview.vue` in `<AppLayout>` — added import + component registration, nested existing root div, closes Rule #14 violation
- [ ] S13 — Rename duplicate Vue filenames (CurrentSituation.vue ×2, GoalCard.vue ×2) with module prefixes
- [ ] S14 — Extract `IHTPlanningPayloadBuilder` service to break LifePolicyController→IHTController inter-controller injection
- [x] S15 — `AssetLocationController` `buildDefaultTaxProfile()` + `calculateIncomeTaxRate()` now fail loud via new `requireTaxValue()` helper — no more silent 2026/27 substitution when TaxConfigService is missing a key. `$annualIncome ?? 50000` also tightened to `?? 0` (income is a profile field, not tax data, and 0 is a legitimate non-earner value)
- [ ] S16 — Migrate 8 legacy PHPUnit-style tests to Pest (Property + Mortgage cluster first)
- [ ] S17 — Sweep 48 `$this->actingAs` API tests to `Sanctum::actingAs`
- [ ] S18 — Extract `tests/Helpers/TaxConfigFixture::withCurrentTaxConfig()`
- [x] S19 — Added 5 missing keys to `.env.example` (ADMIN_EMAILS, AGENT_INTERNAL_TOKEN, OPENAI_API_KEY, SSH_PASSPHRASE, VITE_INSIGHTS_CMS_ENABLED)
- [x] S20 — Added `use Auditable` to User (with high-frequency exclusion list for last_login_at / failed_login_count / locked_until / etc. to avoid drowning the audit table), Household, NotificationPreference, SavingsGoal, SubscriptionPlan. Pest auth suite (81 tests) still green.
- [ ] S21 — Consolidate `familyMembersService` (delete dupe methods from `userProfileService`, repoint consumers)

## Medium-term (God-file decomposition — surgical wins, flag rest)

- [~] M22 — Action-Definition splits (Savings 3690 / Retirement 2719 / Protection 2350 / Investment 1486) — **too large for one branch; deferred to dedicated PRs per service**
- [~] M23 — God controller splits (Payment 1159 / Investment 1074 / Admin 893 / Auth 871 / Goals 792) — **too large for one branch; deferred to dedicated PRs per controller**
- [~] M24 — Vue god component splits (TaxSettings 3068 / ExpenditureForm 2574 etc.) — **too large for one branch; deferred to dedicated PRs per component**
- [ ] M25 — Walk 73 orphan Vuex actions / 12 orphan mutations / ~83 dead service methods — verify before deleting (some may be dynamic-dispatch reachable)
- [ ] M26 — Walk 30 dead model scopes, delete confirmed-dead
- [~] M27 — Introduce `TaxProductReferenceStore` — **deferred to SP1 Pass 2 R5 sub-track**
- [~] M28 — Collapse `AiToolDefinitions` + `XaiToolDefinitions` via shared registry — **large refactor, deferred**
- [ ] M29 — Fix AdvicePromptBuilder ResolvesIncome/Expenditure drift

## Backlog

- [ ] B30 — Promote `SignificanceThresholds` / `CLT_LIFETIME_RATE` / `TaxDefaults` rate constants
- [ ] B31 — Replace raw `console.*` in services with `utils/logger.js`
- [~] B32 — Switch `MonteCarloSimulator` from `DB::table` to Eloquent model — **needs migration; deferred**
- [ ] B33 — Add `preview()` state to UserFactory
- [x] B34 — Dropped `ai_chat_enabled` orphan column via `2026_05_23_080000_drop_ai_chat_enabled_from_users_table.php`. Removed the lone ChrisUserSeeder reference. Migration applied + reseeded clean.
- [ ] B35 — Add Architecture test for AdviceFyn write-tool parity
- [~] B36 — Split 22 services in 500–800 line range — **deferred**
- [ ] B37 — Fix `Estate/IHTController:163` response shape (wrap in `data` envelope) — **frontend-breaking, deferred to dedicated PR**
- [ ] B38 — Fix `Estate/LpaDetailView.vue` + `WillBuilder/WillBuilderReviewStep.vue` hardcoded hex (`#ddd`, `#1F2A44`, `#000` → `@apply`)
- [ ] B39 — Fix `Shared/ModuleStatusBar.vue:239` hardcoded `#FECDD3` → `@apply border-raspberry-100`
- [ ] B40 — Fix `Advisor/{ClientDetail,ClientList,Dashboard}.vue` triplicated palette literal → import CHART_COLORS
- [ ] B41 — Fix `views/Public/learn/LearnHubPage.vue` palette drift `#E8326E` → canonical `#E83E6D`
- [ ] B42 — Move 3 custom `@keyframes` to app.css (Journey/JourneyMap, Dashboard/NetWorthOverviewCard, Onboarding/OnboardingWizard)
- [ ] B43 — Replace `Coordination/{PriorityRanker,HolisticPlanner}` + `Dashboard/DashboardAggregator` £100k/£200k thresholds with SignificanceThresholds constants
- [ ] B44 — Replace duplicated CLT lifetime-rate lookup (Estate/PersonalizedTrustStrategyService ×2 + GiftingStrategyOptimizer) with TaxConfigService helper
- [ ] B45 — Promote `LifeStage/LifeStageService:105` hardcoded £200,000 to a private const
- [ ] B46 — Extract holdings-with-cash-remainder duplication (Investment + Retirement controllers) into `HoldingsAllocationService`
- [ ] B47 — Goals/GoalCalculationService ↔ Goals/GoalProgressService duplication consolidation
- [ ] B48 — Add missing index on `plan_action_funding_selections.funding_source_id`
- [ ] B49 — AdvisorClientSeeder bypass (`DB::table->update is_advisor`) → User `markAsAdvisor()` mutator
- [ ] B50 — PreviewController:283 — extract 13-method seed flow into PreviewPersonaSeeder service
- [ ] B51 — PreviewController:320 — use SanitizedErrorResponse trait instead of raw `$e->getMessage()` interpolation
- [ ] B52 — RetirementController:369-373 — create DCPensionResource, stop returning raw Eloquent model
- [ ] B53 — `tests/Architecture/EvalScenarioCountTest.php:18` — investigate unconditional `->skip()` (count invariant currently unenforced)
- [ ] B54 — `tests/Browser/scenarios/document-articles-end-to-end.php` — assign BS-NN number with docblock OR move to `tests/Browser/manual/`
- [ ] B55 — Add `joint()` state method to 6 joint-capable factories (Mortgage/Property/Cash/Savings/BusinessInterest/Chattel) — overlaps with Q8 but Q8 only adds user_id

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
