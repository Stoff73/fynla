# Fynla Blind-Spot Audit — 2026-07-07

Seven-dimension parallel pass targeting failure classes that feature-focused and security-focused audits structurally miss. **Deliberately did NOT re-run** the recent full-app security review (0 vulns) or tech-debt/campaign audits. Read-only; nothing changed.

Dimensions: (1) time bombs, (2) silent operational failure, (3) data-lifecycle/GDPR, (4) concurrency/races, (5) test-coverage inversions, (6) scale cliffs, (7) authorization matrix.

Severity/confidence are the sub-agents'. Coverage not judgement — everything is listed.

---

## THE FIVE THINGS THAT SHOULD SCARE YOU MOST

1. **6 April 2027 is a silent non-event.** No 2027/28 tax config exists, nothing activates or warns on rollover, `WHERE is_active=true` just keeps serving 2026/27 forever. The seeder's pinned `ACTIVE_TAX_YEAR` actively re-pins the stale year on every reseed. ISA allowances never reset; public pages say "2027/28" while the app says "2026/27".
2. **The 7-year GDPR hard purge soft-deletes the users row** — so every `ON DELETE CASCADE`/`SET NULL` FK is dead code, and purge coverage is only the hand-maintained table list. `ai_messages`, `ai_advice_logs` (full financial snapshots), LPA/will PII, and ~20 more tables survive erasure forever.
3. **`QUEUE_CONNECTION=sync` in prod + dev.** Every "queued" job runs inline in the HTTP request. Monte Carlo start-then-poll is a fiction; risk-recalc debounce is bypassed; the 3-minute summariser makes sequential inline 60s xAI calls.
4. **Every error terminates in an unwatched `laravel.log`.** No Sentry/Slack channel, empty `reportable()` stub, zero `onFailure()` on ~25 scheduled commands, zero `failed_jobs` monitoring, no scheduler heartbeat.
5. **`joint_owner_id` is an unauthenticated pointer** — accepts any user id (inject a fake asset into a stranger's dashboard), spouse-linking has no consent handshake, and unlink/divorce never clears it (ex-spouse keeps access forever).

Two force-multipliers: **Laravel 10 + Sanctum 3 are ~17 months past security EOL**, and the **IHT engine (1,672 lines) has 2 tests, both about DB persistence, zero about the maths.**

---

## 1. TIME BOMBS

### Critical
- **TB-1 — No tax-year rollover mechanism.** `TaxConfigStore.php:219-227` selects on `is_active` only; `effective_to` never compared to `now()` anywhere; no scheduled activation/warning. On 2027-04-06 all backend tax calcs silently continue on 2026/27. Loud only if *no* row is active (`TaxConfigService.php:527`). certain.
- **TB-2 — No 2027/28 config to activate; seeder re-pins stale year.** `TaxConfigurationSeeder.php:36-43` seeds 6 years ending 2026/27; `self::ACTIVE_TAX_YEAR` re-activates 2026/27 on every reseed (incl. mandated per-session reseed). certain.
- **TB-3 — Laravel 10 + Sanctum 3 past security EOL.** `composer.json:14` `^10.10` (lock 10.50.2); L10 security fixes ended 2025-02-04. ~17 months unpatched on a production financial app. certain.

### High
- **TB-4 — Broken tax-year boundary, wrong days 1-5 May-Dec.** `StorePersonalAccountLineItemRequest.php:25`: `month>=4 && day>=6` files business line items in the wrong tax year on the 1st-5th of every month May-Dec. Recurring, live now. certain.
- **TB-5 — Salary-sacrifice cap: "April 2029" copy contradicts configured 2027-04-06 date, two services never check the date.** `SalarySacrificeAnalyzer.php:293-335` hardcodes "From April 2029" + `post_2029_*` keys, never reads effective date; `RetirementActionDefinitionService.php:1284-1298` same. Only `RetirementStrategyService.php:1180-1200` gates correctly. certain.
- **TB-6 — ISA allowance never resets 2027-04-06** (consequence of TB-1). `ISATracker.php:29-31` keys rows by active-config year; stale config = contributions keep accruing to 2026/27 bucket, users appear to have £0 new allowance. likely.

### Medium
- **TB-7** — same broken predicate in refund timing `RetirementStrategyService.php:1387-1390` (display only). certain.
- **TB-8** — public vs authenticated show two different "current" tax years on rollover day (`dateFormatter.js:298-305` vs stale backend). certain.
- **TB-9** — AI system-prompt knowledge hardcodes dated facts/figures: `FinancialPlanningKnowledge.php:15` ("verified 1 April 2026, 2025/26 concepts"), `:108` worked PA taper example, `:111` pension age 57-in-2028, `:132` "NRB frozen until 2028" (freeze extended in real law). certain contents.
- **TB-10** — PHP 8.2 permitted (`composer.json:8` `^8.2`); 8.2 security EOL 2026-12-31 (~6mo). certain dates.
- **TB-11** — ~30 inline `?? 12570 / ?? 50270 / ?? 268275` fallbacks + frozen `taxConfig.js` / `TaxDefaults.php` are stale-silent with no telemetry if config shape regresses. certain mechanism.
- **TB-12** — `vuex-persistedstate ^4.1.0` archived upstream, sits in auth/state path. likely.

### Low
- **TB-13** — ~8 public insight pages hardcode 2026/27 (one card mislabels "ISA Allowance 2026/27" dated April 2025). certain.
- **TB-14** — leap-year skew 29 Feb 2028: `PortfolioAnalyzer.php:50` subYear, `GoalStrategyService.php:192` addYear. possible.
- **TB-15** — superseded majors: Vite 5, Capacitor 6, Vuex 4 (maintenance). likely.

### Clean
Missing-config fails loud (RuntimeException); correct boundary math in `AnnualAllowanceChecker::getCalendarTaxYear`, `CGTHarvestingCalculator:505`, frontend `getTaxYearStart/End`; timezone consistent Europe/London; `Cache::flush()` on year activation; short-lived signed URLs; salary-sacrifice gating in `calculateNetCostOfContribution`; no `dev-`/fork constraints; mobile has no hardcoded tax values.

---

## 2. SILENT OPERATIONAL FAILURE

### The through-line
Every error path terminates in `storage/logs/laravel.log` (manually watched ~15 min post-deploy). No Sentry/Bugsnag/Flare; `slack` channel exists but not in stack; `Handler.php:35` `reportable()` empty stub; `config/logging.php:55-59` default `['single']`.

### High
- **Dashboard all-zero laundering.** `DashboardAggregator.php:139,161,188,215,242` catch any agent `analyze()` throwable → log **warning** → return null; summary methods render null as all-zeros (£0 net worth / £0 IHT / £0 portfolio / 0 adequacy), indistinguishable from "no data". Bypasses the intended `_error` marker path. certain.
- **Dashboard alerts dropped.** `DashboardAggregator.php:101,464,553,650,731,815` catch → warning → `[]`; FSCS-over-£85k, protection-adequacy, IHT, retirement-gap alerts silently suppressed. certain.
- **Strategy sources swallow with NO log.** `ProtectionStrategySource.php:85`, `EstateStrategySource.php:39`: `catch (Throwable) { return []; }`. Wrapped inside `RecommendationsAggregatorService` try/catch so even the outer warning never fires. certain.
- **Revolut cancel says success on failure.** `PaymentController.php:972` catches Revolut-side cancel throwable → warning → marks local cancelled → returns "Subscription cancelled." Revolut keeps charging. likely.
- **IHT £0 on failure.** `EstateAgent.php:132` catches IHT calc exception → `report($e)` → leaves `iht_liability=0, effective_tax_rate=0` with `success=true`; feeds gifting calc. Headline figure silently wrong. likely.
- **Mobile net worth £0.00 on failure.** `MobileDashboardAggregator.php:369` → logError → returns total 0.0. certain.
- **Verification email failure returns success.** `AuthController.php:113-126` (register) & `:274-286` (MFA): `Mail::send` failure caught, logged, still returns `success:true` "check your email". Feeds the known resend-cap dead-end. Resend path `:698-715` correctly returns 500 — inconsistent. High/High.
- **Revolut webhook returns 200 on processing failure.** `WebhookController.php:191-196` (+212,223,234): catch → log → top-level returns 200; Revolut never retries → paid user's subscription never activates. High/High mechanics.
- **Scheduler blind.** `Kernel.php:20-62`: none of ~25 commands has `onFailure/emailOutputOnFailure/pingOnFailure`; no `schedule:run` heartbeat — if cron dies, trials never expire / alerts stop, only downstream symptoms. High.

### Medium
- `RecommendationsAggregatorService.php:71-250` per-module catch → warning → module silently omitted from recs list.
- `EstateAgent.php:148,173,185,195,220` trust/gifting/will/charitable/policy recs → `report` → `[]`, partial estate plan shown as complete.
- `MobileDashboardAggregator.php:487` alerts → `[]`.
- `RetirementAgent.php:544` empty catch → falls back to medium-risk growth rate, wrong projected income, no log.
- `LifeEventAllocationService.php:431,489,574` allowance checks → null → waterfall skips ISA/pension step, money to worse-tax destination.
- `LifeEventAllocationService.php:84` → `[]` → life event persists zero allocations.
- `InvestmentPlanService.php:291` comment says "fall back to trigger recs" but returns `[]` — trigger recs discarded too.
- `ProtectionPlanService.php:81` `catch(\Exception){return [];}` no log (sibling `:29` correctly surfaces error).
- `TracksGoalContributions.php:70` → warning → goal progress silently drifts.
- `SavingsAgent.php:136,146` PSA/FSCS → `report` → null → FSCS-over-£85k alert not generated.
- `RetentionPurgeService.php:216` empty catch on document dir delete → residual PII files persist after erasure.
- `TrustObserver.php:48` (only catch in all 12 observers) failed CLT-gift auto-create → warning → IHT position wrong (missing chargeable lifetime transfer).
- `XaiToolDefinitions.php:196-201` / `AiToolDefinitions.php:225-227` `pointerTools()` catch → `[]` NO log → Fyn silently loses all CoALA pointer tools.
- `FynLoop.php:251,306,337,365,450,502` episodic memory writes warning-only → FCA SYSC 9.1 records silently stop while chat works.
- No `failed_jobs` monitoring anywhere; `RecalculateRiskProfileJob.php:75-80` catches+returns (reports success, no retry/failed()).

### Clean
`AiChatController.php:309-345` mid-stream SSE error + FR-M9 resume + `finally` release; `AuthController.php:698-715` resend returns 500; `FireAwinConversionJob` rethrows + `failed()`; `RunMonteCarloSimulation` sets `status=failed`; webhook HMAC verify rejects 401; core `Retirement/Tax/NetWorth/Protection/Savings/Risk` calculators + `UKTaxCalculator`/`TaxConfigService` have NO catch blocks (fail loud).

---

## 3. DATA LIFECYCLE / GDPR

**Root cause:** `RetentionPurgeService.php:105` Phase 8 soft-deletes the users row via `update()` — users are NEVER hard-deleted, so every FK cascade is dead code. Purge coverage = exactly `getDeletionOrder()` (`:259`).

### Critical
- **ai_messages + ai_conversations survive purge forever** — absent from purge list; carry `content`, `system_prompt`, `assembled_context`, `tool_results`. Distinct from the known live-column-bloat deferral; this is post-purge whole-row survival. certain.
- **"Delete my Data" path references non-existent `salary` column.** `GDPRController.php:569-575` does `$user->update(['salary'=>null])`; no `salary` column exists (User uses `$guarded`) → SQL error → 500 after 2FA. Also only nulls 3 fields while claiming "financial data deleted" — every account/pension/property/policy/goal/chat remains. certain coverage gap / likely SQL error.

### High
- **ai_advice_logs survive** — `user_data_snapshot` full financial snapshot per turn, `user_id` intact forever.
- **Per-user semantic facts survive** — `UserSemanticStore::forget` called by `fyn:user:erase` but NOT `purgeUser`; disk facts + `proposed_semantic_facts` rows survive.
- **LPA tree survives** — `lasting_powers_of_attorney` + `lpa_attorneys.full_name` (third-party PII) + notification persons, absent from purge.
- **will_documents survive** — testator name/address/DOB/funeral wishes/digital-executor; `will_id` merely `nullOnDelete`.
- **pension_input_history survives** — even though `BackfillPensionDerivedColumns.php:22` comments it's "purged wholesale."
- **Joint records: spouse B loses their share at A's purge** — `:64-70` deletes all `WHERE user_id=A` incl. joint records; B's (100−pct)% share vanishes, no reassignment/notify/export.
- **Docblock factually wrong** — `:22-24,167-169` claim "SET NULL when user deleted"; soft-delete means it never fires; B's records keep `joint_owner_id=A` pointing at anonymised husk forever; `CalculatesOwnershipShare` keeps deducting for a non-existent person.
- **GDPR export files never cleaned** — `DataExportService::cleanupExpiredExports` (`:260`) has ZERO callers; full-PII `user_{id}_*.json` dumps accumulate forever; purge deletes the `data_exports` row but not the file → orphan PII.

### Medium
- ~20 more user-keyed tables absent from purge (`what_if_scenarios`, `notification_preferences`, `device_tokens`, `referrals`, `invoices`, `point_awards`, `user_gamification`, `advisor_clients`, `ai_cost_attribution`, `ai_daily_usage`, etc.).
- `fyn:episodic:purge` (6-year FCA hard-delete) is NOT scheduled — episodic data older than 6 years accrues indefinitely.
- `fyn:user:erase --force` hard-deletes advice content of ANY age with no FCA 6-year guard.
- Legacy `confirmErasure` (`GDPRController.php:292`) says "all data has been deleted" while everything retained 7 years.
- No guard warns a deleting user that they own joint records affecting a spouse.

### Clean
Value snapshots + holdings cascade correctly (parents get real deletes); reverse refs (`spouse_id`, beneficiary, spouse_permissions) cleaned; login_attempts/pending_registrations/idempotency cleaned; document files + dir swept at purge; cold episodic archive covered by shared `EpisodeBlobLocator::eraseForUser`; 7-year window satisfies FCA SYSC for the main flow; scheduled-deletion lifecycle complete.

---

## 4. CONCURRENCY / RACES

### High
- **Web edits never invalidate 24h mobile/dashboard cache for Property/Investment/Mortgage.** `MobileDashboardAggregator.php:37` TTL 86400 (comment says "5 min" — stale); `CacheInvalidationService::invalidateForUser` called from Savings/Estate/Retirement/Protection/Plans/Profile/Family/Fyn but ZERO calls in PropertyController/InvestmentController/MortgageController. Edit property on web → /m shows pre-edit figures up to 24h. likely.

### Medium
- **No server-side idempotency on any module create.** `idempotent` middleware applied to exactly ONE route (AI sendMessage, `api.php:1409`); every `store()` has no idempotency key / no natural-key unique → double-submit double-creates an asset, double-counts net worth. certain.
- **Risk-recalc debounce drops trailing updates under sync.** `RiskRecalculationObserver` + `delay(5)`: under sync, delay ignored, changes #2-5 in the window hit `Cache::has`→skipped with no trailing job → risk reflects first change only. (confirms sync-queue finding). certain mechanics.
- **Goal `current_amount` lost update** — `TracksGoalContributions.php:53-69` + `GoalProgressService.php:79-93`: unlocked `current + delta` RMW; concurrent auto+manual contributions lose a delta, `goal_balance_after` audit inconsistent. certain pattern.
- **Gamification aggregate lost update** — `PointsService.php:50-60`: `firstOrCreate` without `lockForUpdate` then `+= points`; two different-dedup-key awards → one vanishes from aggregate (ledger row survives). likely.
- **Monte Carlo ownership race** — `InvestmentController.php:216-231` dispatches job BEFORE writing `monte_carlo_status` owner key; async worker reads null → 404s the real owner forever. No per-user dispatch dedupe. certain interleaving.
- **fyn:inflight lock TTL 300s < unbounded tool-loop turn** — `AiChatController.php:207,397`: a >5min turn lets a second send acquire a fresh lock, both write interleaved into same conversation. possible.
- **Hard death leaks lock + drops queued turn** — hard kill skips `finally`; queued turn is frontend-`done`-driven, a dead stream never emits `done`, `ConcurrentTurnQueue::expireStale` sweeps user message to `expired` after 10min → silently dropped. likely.
- **Spouse-side staleness** — `WillController.php:110`/`TrustController.php:83` invalidate acting user only, not spouse. possible.

### Low
- Login-streak double-increment (web+/m simultaneous), data-entry cap TOCTOU, ISA cross-column staleness (self-heals), debounce marker `Cache::has`-then-`put` (should be `add`), depth-cap TOCTOU, registration double-verify raw 500, tier-gate cap TOCTOU (+1 over cap), file cache lock per-server-only.

### Clean (best-defended surface = payments)
Revolut webhook vs poll both `lockForUpdate` + `status==completed` idempotency — fully serialised; `ownership_percentage>100%` structurally impossible; `point_awards` unique `(user_id, dedup_key)`; observer cycles none (event-less query-builder backfill, field-filtered UserRiskObserver); Monte Carlo NOT observer-triggered; TaxConfigService request-memo no stampede; AI token budget + audit chain use `lockForUpdate`.

---

## 5. TEST COVERAGE INVERSIONS

**Headline:** the product's highest-stakes calc (`IHTCalculationService`, 1,672 lines) has 2 tests, both DB-persistence; its CRUD periphery has 14 status/shape assertions. Investment analytics/rebalancing (27/55 services incl. hand-rolled Markowitz optimiser driving trade recs) is entirely test-free while its Vuex normalisers are exhaustively tested.

### Zero-test money services (80/427 = 18.7%; full list in scratchpad `untested_services.txt`)
Per-module zero-mention: Investment 27/55, Tax 12/23, Estate 5/27, Goals 5/12, WhatIf 1/1, Plans 2/11, Savings 2/9, Retirement 1/12, Protection 1/7. (Coordination 0/26, Risk 0/2 — well covered.)

- **critical** — `IHTCalculationService.php` IHT maths untested; **RNRB £2M taper (`:1176-1229`) has zero calculation-level tests** (up to £70k error on £2M+ estates).
- **high** — `SpouseNRBTrackerService`, `InvestmentProjectionService`, `ChattelCGTService`, `PSACalculator`+`FSCSAssessor`, `WhatIfCalculator`+`WhatIfScenarioService`, the Markowitz/Correlation/Covariance analytics stack, `RebalancingCalculator`+`DriftAnalyzer`, 5 Goals services.
- **medium** — `PensionPortfolioAnalyzer`, `GoalProbabilityCalculator`+`ShortfallAnalyzer`, `BedAndISACalculator`, `OCFImpactCalculator`, `CalculatesOwnershipShare` trait (canonical Rule-6 split, only incidental coverage), `TaxBandTracker` (indirect via UKTaxCalculator).
- Downgraded: `Tax/Strategies/*` 10/14 zero-mention but DI-injected into the 1,614-line `TaxStrategyCalculator` suite (real indirect coverage by output key).

### Tests that cannot fail
- **high** — `RetirementIntegrationTest.php:395-406` `expect(true)->toBeTrue()` cache test; `EstateIntegrationTest.php:248-249` self-consistency IHT only (both could be wrong); no feature test anywhere pins an IHT £ from the live endpoint.
- **medium** — `ProtectionApiTest` update/delete policy = HTTP 200, no DB verify (24 status-only feature tests total); `PensionProjectorTest` loose ranges (`toBeGreaterThan(300000)` — 20% error passes).
- Band boundaries: PA-taper £100k, £50,270 crossing, £260k pension gate, SDLT/CGT all exactly pinned — GOOD; but no exact-boundary `UKTaxCalculator` inputs at £50,270/£50,271/£125,140/£125,141; RNRB £2M taper zero (repeat).

### Frontend
vitest EXISTS (34 files vs 677 components ≈ 5%). Highest-risk untested: `ownership.js` (frontend mirror of Rule-6 split — both sides now untested, drift unguarded), `currency.js` rounding, `taxConfig.js` `getCurrentTaxYear()` (date-derived client-side).

### Time-dependence
Structurally safe — active year pinned by seeder const not system date, so 110 hardcoded year strings break LOUD (maintenance cliff) not silent on April 6. Only 9/710 files use `setTestNow` but date-relative fixtures are deterministic.

### Well-covered
`TaxStrategyCalculator` (1,614 lines), `UKTaxCalculator` PA-taper/starting-rate, `AnnualAllowanceChecker`+carry-forward, `DecumulationPlanner`, Monte Carlo engine+simulator, `PropertyTaxService`, `NetWorthService`, Stores suite, Coordination 26/26, `IntestacyCalculator`/`GiftingStrategy`/`AvailableNrbCalculator` (NRB only).

---

## 6. SCALE CLIFFS

**Posture:** "queue-shaped code on a sync queue." One fix (real queue driver: database driver + cron `queue:work --stop-when-empty`, works on SiteGround) defuses cliffs 1, 3, 4 + GDPR export.

1. **critical — `QUEUE_CONNECTION=sync` prod+dev** (`deploy/*/.env.production:41`). `RunMonteCarloSimulation` (300s) blocks the "start" request inline → poll architecture fiction; `RecalculateRiskProfileJob` debounce ignored, full recalc inline on every asset save; `ConversationSummariserJob` inline 60s xAI. Falls over ~50 concurrent users on shared FPM.
2. **critical — `PlanningProgressService::distribution()`** (`:92-96`) loads ALL users, ~10 queries each, 1h cache. 1k users ≈ 10k queries/request; 10k ≈ times out hourly, takes the /m dashboard with it.
3. **high — Retirement Monte Carlo synchronous in cold dashboard load** (`RetirementProjectionService.php:111,184`, input-hash cache key so any change = full recompute). 0.5-3s CPU/user; morning burst saturates shared CPU.
4. **high — `summarise-stale` every 3min** — unindexable `JSON_EXTRACT` scan + sequential inline xAI dispatch; 100 stale convos = 100 sequential external calls, `withoutOverlapping` then backs up.
5. **high — SSE pins a PHP-FPM worker for the whole turn** (`AiChatController.php:240,350`). ~10-20 concurrent Fyn chats = every worker occupied = whole site 503. Hosting ceiling, not code.

Further high: `HasAiChat.php:1194-1198` `->get()` no column list hydrates longText `system_prompt`+`assembled_context` every chat turn (1-4MB); `AdminController.php:55-93` `sum()` over ai_messages with no `created_at`-only index (full scans of fattest table); `DataExportService.php:213-219` loads all audit_logs into PHP array in a sync request.

Medium: `SendProtectionAlerts.php:78-90` whole-table load nightly; `FynEpisodicReconcile.php:26` all blob paths in one array; `PurgeAuditLogs.php:68-69` unchunked multi-million-row DELETE (contrast `AiAuditRetentionJob` which chunks); `AiChatController.php:99-102` unbounded transcript (recall the 17,509-msg conversation); file cache no stampede protection + no GC.

Clean: episodic commands chunkById(200); good indexes on ai_messages/audit_logs/point_awards/user_sessions; joint_owner_id indexed (`2026_01_26_150000`, index_merge available); admin user listing paginated; AI token budgets from aggregated table; MobileDashboardAggregator eager-loads.

---

## 7. AUTHORIZATION MATRIX

**One structural hole, three faces — root cause: `joint_owner_id` is never validated against a consented spouse link at write time, nor re-validated at read time.**

### High
- **F1 — `joint_owner_id` accepts ANY user id.** Every joint FormRequest uses `exists:users,id`, never tied to spouse link (StorePropertyRequest:44, StoreSavingsAccountRequest:62, StoreInvestmentAccountRequest:68, StoreGoalRequest:38, +more). Read scope is pure OR (`HasJointOwnership.php:20-26`). Exploit: user injects a fabricated £2m property with `joint_owner_id=<stranger>`; stranger's dashboard/estate folds it in. One-directional injection (not read-exfil). certain.
- **F2 — Spouse linkage unilateral, no consent.** `FamilyMembersController.php:215-259` writes `spouse_id` on both users + auto-creates `SpousePermission status='accepted'`; married-mutual shortcut `User.php:683-688` makes `hasAcceptedSpousePermission()` true with no record (request/accept UI is dead code); can even create a full User for a non-consenting email. certain.
- **F3 — No unlink/divorce cleanup of `joint_owner_id`.** `FamilyMembersController.php:561-602` clears `spouse_id` + deletes permissions but never touches `joint_owner_id`; only owner manually editing each asset nulls it. Ex-spouse keeps read access + net-worth inclusion of formerly-joint assets forever. certain.

### Medium/Low
- F4 — joint OR-aggregations perform no permission check (amplifier for F1/F3).
- F5 — `EstateController.php:92` lists liabilities `user_id` only though Liability has `joint_owner_id` — joint owner won't see the liability (inverse inconsistency). low.
- F6 — `InvestmentController.php:477,662` loads unscoped row then `!==` guards (safe but only module doing it). low.
- F7 — `is_admin` mass-assignable (`User.php:55` in `$fillable`) — latent, only reachable via admin-gated paths today. low.

### Clean matrix
Joint owner UPDATE/DELETE primary-owner-only + consistent across ALL modules; Protection no joint columns (all primary via `PolicyCRUDTrait`); mobile↔web write parity (identical FormRequest, actor from `$request->user()->id`); mobile inherits full `api` middleware; admin API server-gated (`permission:admin.access`), client guard is defense-in-depth; advisor impersonation re-validated every request; PreviewWriteInterceptor EXCLUDED_ROUTES reviewed — nothing lets a preview persona mutate real shared state (register creates real user only; password reset needs emailed code; eval bypass fail-closed).

---

## SUGGESTED SEQUENCING (not started — for CSJ to decide)

**Do before April 2027 / already overdue:** TB-1/2 rollover mechanism + 2027/28 config + warning job; TB-3 Laravel/Sanctum upgrade.
**Highest leverage single fixes:** real queue driver (scale 1/3/4 + GDPR export); wire a critical alert channel + `reportable()` + Kernel `onFailure()` (all of §2); make Revolut webhook return non-2xx on failure + stop returning success on verification-email failure.
**Correctness/compliance:** purge table-list completeness + the joint-record-purge spouse-data-loss; `joint_owner_id` consent validation (F1-F3); IHT/RNRB-taper test coverage.
**Cache:** add `CacheInvalidationService` calls to Property/Investment/Mortgage controllers.
