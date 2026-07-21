# Blind-Spot Remediation — Specification

**Source audit:** [blindspot-audit-2026-07-07.md](blindspot-audit-2026-07-07.md)
**Companion:** [blindspot-remediation-plan.md](blindspot-remediation-plan.md)
**Date:** 2026-07-07
**Status:** Ready to implement (Opus 4.8)

This spec defines WHAT to build and the acceptance criteria (definition of done) for each workstream. The plan defines HOW (ordered tasks, files, code shape, tests). Read this first; implement from the plan.

---

## 0. Decisions locked by CSJ (2026-07-07)

| # | Decision | Consequence for scope |
|---|----------|-----------------------|
| D1 | **Framework EOL upgrade = separate tracked workstream.** | WS-J is a referenced project, NOT implemented here. Do not begin the Laravel/Sanctum/PHP upgrade as part of this plan. |
| D2 | **Joint ownership = validate + revoke on unlink.** | Write-time: `joint_owner_id` must be an accepted spouse link. Unlink/divorce: null `joint_owner_id` on affected records, primary owner keeps the whole asset. No new invite/accept UI. |
| D3 | **Tax rollover = full admin config-authoring UI** + date-driven activation + 2027/28 seed + warning cron. | Build an admin screen to author/clone/activate future tax years, not just a minimal seed. |
| D4 | **Alerting = Sentry.** | Add `sentry/sentry-laravel`, wire `reportable()`, add a `sentry` log channel to the stack. Slack channel stays as a secondary/optional sink. |

## Verified ground truth (spot-checked against the live tree, not just the audit)

- `config/queue.php:16` default `sync`; **both** `deploy/fynla-org/.env.production:41` and `deploy/csjones-fynla/.env.production:41` set `QUEUE_CONNECTION=sync`, `CACHE_DRIVER=file`, `LOG_CHANNEL=single`.
- `config/logging.php:55-59` stack = `['single']`; a `slack` channel exists (`:76-83`, level `critical`) but is not in the stack.
- `app/Exceptions/Handler.php:36-38` `reportable()` is an empty stub.
- `app/Console/Kernel.php` — 25 scheduled entries; only `ai:conversations:summarise-stale --pause` (`:31-33`) has `withoutOverlapping`; none has `onFailure`/`pingOnFailure`/`emailOutputOnFailure`.
- `app/Services/Stores/TaxConfigStore.php:219-227` `activeConfig()` = `TaxConfiguration::where('is_active', true)->first()` — flag-driven, **no** date comparison.
- **No `users.salary` column exists.** Income columns are `annual_employment_income`, `annual_self_employment_income`, `annual_rental_income`, `annual_dividend_income`, `annual_interest_income`, `annual_other_income`, `annual_trust_income`, plus `employer`, `national_insurance_number`, `employment_status`. `GDPRController.php:571-573` writes `employment_status`, **`salary`**, `national_insurance_number` → `salary` is a non-existent column and `User` uses `$guarded`, so `update()` will throw → 500 after 2FA. **Confirmed live break.**

---

## Workstream map & sequencing

```
WS-A Observability ─┐
WS-B Queue ─────────┼─► foundations (do first; make failures visible + async)
                    │
WS-C Silent-fail ───┤
WS-D GDPR/lifecycle ├─► correctness & compliance
WS-E Joint-owner ───┘
                    │
WS-F Tax rollover ──► tax integrity (depends on WS-A for the warning alert)
WS-G Concurrency ───► (cache/idempotency/locks; depends on WS-B for real async)
WS-H Scale ─────────► (depends heavily on WS-B)
WS-I Tests ─────────► (can run in parallel; gates every other WS's "done")
WS-J Framework EOL ─► SEPARATE PROJECT (referenced only, D1)
```

Hard dependency edges: **WS-A before everything** (so remediation regressions surface), **WS-B before WS-G/WS-H** (locks and off-request work assume a real queue), **WS-A before WS-F** (rollover warning needs the alert channel). Everything else is parallelisable.

**Rule 19 (/m parity):** every backend change reaches `/m` for free (shared endpoints). Frontend-touching items — dashboard error state (WS-C), tax-rollover admin UI (WS-F, desktop-admin only, no `/m` counterpart), cache invalidation (WS-G), `ownership.js` tests (WS-I) — carry an explicit `/m` note in the plan. Admin surfaces have no `/m` counterpart by design.

---

## WS-A — Observability & Alerting

**Problem:** every error terminates in an unwatched `laravel.log`. No error tracking, empty `reportable()`, no scheduler failure hooks, no `failed_jobs` monitoring, no scheduler heartbeat.

**Definition of done:**
1. Sentry (`sentry/sentry-laravel`) installed; DSN via `SENTRY_LARAVEL_DSN` env (empty in local = disabled, no crash). `reportable()` forwards unhandled throwables to Sentry with user-id context (no PII beyond user id — no email/NINO/financial values in breadcrumbs).
2. `config/logging.php` stack includes a `sentry` channel at `error`+; existing `single` retained. Slack channel wired as optional secondary (active only if `LOG_SLACK_WEBHOOK_URL` set).
3. Every scheduled command in `Kernel.php` has `->onFailure(fn () => report(...))` (or a shared helper) that raises a Sentry event naming the command; the money/compliance-critical ones (`accounts:*`, `subscriptions:check-overdue`, `tier:sync-revolut`, `audit:purge`, `fyn:episodic:*`) also `->withoutOverlapping()`.
4. A scheduler **heartbeat**: `->everyFifteenMinutes()` job pings a Sentry cron-monitor (or writes a `scheduler_last_run` cache key that a lightweight `/api/health/scheduler` endpoint checks; pick the Sentry cron monitor since Sentry is in). If the scheduler stops, the missed check-in alerts.
5. A `failed_jobs` watchdog: daily command `jobs:alert-failed` that reports the count + oldest failed job to Sentry if `failed_jobs` is non-empty.
6. The two "return success on a failed side-effect" bugs are fixed at the source (verification-email send failure and Revolut webhook processing failure) — see WS-C; they belong to WS-C but their *visibility* is WS-A.

**Non-goals:** no APM/tracing spans, no custom dashboard. `ponytail:` reuse the framework's `report()` + Sentry's Laravel integration; do not hand-roll an alerting service.

---

## WS-B — Queue infrastructure

**Problem:** `QUEUE_CONNECTION=sync` in both envs makes every "queued" job run inline in the request; Monte Carlo start-then-poll is fiction, debounce is bypassed, summariser makes inline 60s LLM calls.

**Definition of done:**
1. `database` queue driver adopted (no Redis on SiteGround shared hosting). `jobs` + `job_batches` + `failed_jobs` migrations present and run.
2. Both `.env.production` files set `QUEUE_CONNECTION=database`. Local `.env.example` documents both (`sync` acceptable locally for test determinism; tests keep `sync`).
3. A worker runs on each server: cron entry `* * * * * cd <path> && php artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> storage/logs/worker.log 2>&1` (every-minute, self-terminating — the SiteGround-safe pattern; no daemonised supervisor). Documented in `deploy/DEPLOY.md`.
4. Every existing `ShouldQueue` job has a `failed(Throwable $e)` handler that reports to Sentry (WS-A) and, where a user-visible row exists (Monte Carlo), marks it failed.
5. Jobs verified to run correctly async: risk recalculation debounce now actually debounces; Monte Carlo start returns immediately with a pending status row.

**Non-goals:** no Horizon, no Redis, no SQS. `ponytail:` database driver is the correct laziest fit for this hosting; the every-minute `--stop-when-empty` worker avoids a long-running daemon shared hosting will kill.

**Acceptance test:** dispatch a job, assert a `jobs` row is created and NOT executed inline within the request (assert via a job that writes a marker only a worker would write).

---

## WS-C — Silent-failure remediation

**Problem:** crashed calculations render as legitimate-looking zeros or empty lists; two paths return success on a failed side-effect.

**Definition of done (each catch below stops laundering the failure):**

| ID | Site | Required behaviour |
|----|------|--------------------|
| C1 | `DashboardAggregator` analysis catches (`:139,161,188,215,242`) | On agent failure, the tile must render an explicit **error state** (`_error` marker already exists via `safeSummary`), NOT all-zeros. Frontend renders "We couldn't load this right now" (Rule 15: no icon), never £0. Report to Sentry at `error`. |
| C2 | `DashboardAggregator` alert catches (`:101,464,553,650,731,815`) | On failure, surface a non-blocking "some alerts unavailable" state rather than silently dropping FSCS/IHT/protection alerts. Report to Sentry. |
| C3 | `ProtectionStrategySource:85`, `EstateStrategySource:39` | Add logging + Sentry report (currently zero log). Behaviour (return `[]`) may stay, but the failure must be visible. |
| C4 | `EstateAgent:132` (IHT) | On IHT calc failure, do **not** return `iht_liability=0, success=true`. Return `success=false` with an error marker so the estate tile shows an error, not "£0 — you have no IHT liability". Report to Sentry. Downstream gifting calc must not run on a fabricated £0. |
| C5 | `MobileDashboardAggregator:369` (net worth) | On failure, return an explicit unavailable marker (pattern already exists at `:97`), not `total=0.0`. |
| C6 | `PaymentController:972` (Revolut cancel) | If the Revolut-side cancel throws, do **not** mark local cancelled + return success. Either retry, or leave subscription active and return an actionable error; never tell the user "cancelled" while billing continues. Report to Sentry. |
| C7 | `WebhookController` order/subscription handlers (`:191,212,223,234`) | On processing failure, return **non-2xx** so Revolut retries (its webhooks retry on non-2xx). Signature-verify failures (`:46-49`) report to Sentry. |
| C8 | `AuthController` register (`:113-126`) + MFA (`:274-286`) | If the verification email send throws, return an error (match the resend path `:698-715` which already returns 500) instead of `success:true "check your email"`. |
| C9 | `XaiToolDefinitions:196-201`, `AiToolDefinitions:225-227` (`pointerTools()`) | Add logging + Sentry (currently zero log) when the pointer catalogue is stripped. |
| C10 | `TrustObserver:48` | Failed CLT-gift auto-create must be visible (Sentry) — a missing chargeable lifetime transfer silently corrupts the user's IHT position. Consider a reconcile path (flag the trust as needing its CLT). |

**Non-goals:** do not add try/catch to the core calculators that currently fail loud (`UKTaxCalculator`, `TaxConfigService`, etc.) — that is correct as-is.

---

## WS-D — GDPR / data-lifecycle

**Problem:** the 7-year hard purge soft-deletes the users row, so FK cascades are dead code; purge coverage is only the explicit table list, which misses ~24 tables incl. full chat transcripts and financial snapshots. Plus the live `salary`-column break and joint-record data loss.

**Definition of done:**
1. **Purge completeness.** `RetentionPurgeService::getDeletionOrder()` (`:259`) extended to cover every user-keyed table that survives today: `ai_messages`, `ai_conversations`, `ai_advice_logs`, `lasting_powers_of_attorney` (+`lpa_attorneys`, `lpa_notification_persons`), `will_documents`, `pension_input_history`, `what_if_scenarios`, `tax_strategy_household_inputs`, `plan_action_funding_selections`, `notification_preferences`, `device_tokens`, `referrals`, `invoices`, `discount_code_usages`, `feedback_responses`, `point_awards`, `user_gamification`, `user_milestones`, `advisor_clients`, `client_activities`, `account_deletion_reminder_log`, `lifecycle_email_log`, `ai_cost_attribution`, `ai_daily_usage`, `ai_abort_events`, plus per-user semantic facts on disk (`UserSemanticStore::forget`) and `proposed_semantic_facts`, plus orphan `households` rows when the purged user was the last member. **Method:** build the authoritative list by diffing every model/table with a `user_id`/`*_id`→users FK against the current deletion order; each addition needs an FCA-retention check (see #4).
2. **Fix the dead-cascade root cause OR make it explicit.** Either hard-delete the users row at the end of purge (after all children are gone — preferred, makes FK cascades real and self-maintaining) OR keep the soft-delete but delete the completeness burden is on the explicit list forever. **Chosen:** hard-delete the users row as the final purge step (children already removed), and correct the docblocks at `:22-24,167-169` that falsely claim SET NULL fires. Preserve the deliberately-retained email-for-reregistration via a separate tombstone row, not the live users row.
3. **Joint-record purge policy.** When purging user A, for each joint record (`joint_owner_id = B`): **reassign** primary ownership to B with `ownership_percentage` recomputed to B's effective share (`100 - A_pct`), rather than deleting B's data. Only delete if there is no joint owner. Log each reassignment. (This makes purge non-destructive to the surviving spouse.)
4. **GDPR-vs-FCA guard.** `fyn:user:erase --force` must not hard-delete advice content (`ai_messages`/`ai_advice_logs`) younger than the FCA SYSC 6-year minimum; add an age guard + explicit acknowledgement in the command output.
5. **Export cleanup.** Schedule `DataExportService::cleanupExpiredExports` (currently zero callers) as a daily command; and at purge, delete the export **files** from disk, not just the `data_exports` rows.
6. **Fix the `salary` break.** `GDPRController` delete-data (`:569-575`) must null the real income columns (`annual_employment_income`, `annual_self_employment_income`, `annual_rental_income`, `annual_dividend_income`, `annual_interest_income`, `annual_other_income`, `annual_trust_income`, `employer`, `national_insurance_number`, `employment_status`), remove the non-existent `salary` key, and either cascade to the actual financial records or **correct the success copy** so it does not claim "all your financial data has been deleted" when accounts/pensions/policies remain. Decide with CSJ if unsure whether delete-data should cascade; default = correct the copy to describe exactly what it clears.
7. **Transparency copy.** `GDPRController:292` (`confirmErasure`) must not say "all associated data has been deleted" while data is retained 7 years; state the retention window.

**Non-goals:** do not change the FCA 7-year retention window; do not alter the scheduled-deletion lifecycle (it is correct).

---

## WS-E — Joint-ownership authorization (D2: validate + revoke on unlink)

**Problem:** `joint_owner_id` is an unauthenticated pointer — accepts any user id (F1), spouse-linking is unilateral (F2 — out of scope per D2, validation covers the exploit), and unlink never clears it (F3).

**Definition of done:**
1. **Write-time validation (F1).** Every FormRequest that accepts `joint_owner_id` must validate it is the acting user's **accepted spouse** (via `hasAcceptedSpousePermission()` / the established `spouse_id` link), not merely `exists:users,id`. Introduce one reusable rule (`Rules/IsAcceptedSpouse` or a FormRequest trait) and apply to: `StorePropertyRequest`, `UpdatePropertyRequest`, `StoreMortgageRequest`, `Savings/Store+UpdateSavingsAccountRequest`, `Store+UpdateInvestmentAccountRequest`, `Goals/StoreGoalRequest`, `Chattel/StoreChattelRequest`, `BusinessInterest/StoreBusinessInterestRequest`, `StoreLifeEventRequest`, and the corresponding service-store paths (`MortgageStore`, `SavingsStore`, etc.). A `null` (individual) value stays valid.
2. **Revoke on unlink (F3).** `FamilyMembersController::unlink` (`:561-602`) and any divorce/marital-status path (`CoordinatingAgent:1270-1280`) must, in the same transaction, null `joint_owner_id` on every record where the unlinked pair are the two owners, leaving the primary owner with the full asset (`ownership_percentage → 100`, or documented default). Add a `SpouseUnlinkService` so both call sites share one implementation.
3. **F5 (low).** `EstateController:92` liabilities list uses `user_id` only though `Liability` has `joint_owner_id` — make it `forUserOrJoint` for read-parity with other modules.
4. **F6/F7 (low).** `InvestmentController:477,662` scope the query instead of load-then-`!==` (align with other modules); remove `is_admin` from `User::$fillable` (`:55`) so it can never be mass-assigned.

**Non-goals (D2):** no invite→accept handshake UI (F2). The married-mutual shortcut stays; validation on write closes the injection exploit without it.

---

## WS-F — Tax-year rollover (D3: full admin config-authoring UI)

**Problem:** no rollover mechanism; no 2027/28 config; seeder re-pins the stale year; ISA never resets; live boundary bug; stale AI knowledge & salary-sacrifice copy.

**Definition of done:**
1. **Date-driven activation.** Activation derives from `effective_from`/`effective_to` vs `now()` (Europe/London), not a manual `is_active` flag alone. Add `tax:activate-current-year` scheduled daily that sets the correct row active based on date; `activeConfig()` semantics unchanged for consumers. Idempotent.
2. **2027/28 config seeded** with correct HMRC values (confirm each with CSJ / `TaxConfigService` sourcing — do not invent). Seeder no longer hard-pins `ACTIVE_TAX_YEAR`; it seeds all years and lets date-activation choose. Reseed after 2027-04-06 must not re-pin 2026/27.
3. **Admin config-authoring UI** (desktop admin only, no `/m`): a screen to list tax years, **clone** a year forward as a draft, edit every band/threshold, set `effective_from/to`, and activate. Server-gated (`permission:admin.access`). Writes go through `TaxConfigStore` (boundary rule from `Services/CLAUDE.md §5.1`). Rule 10/11 design compliance; Rule 12 no scores; Rule 15 no decorative icons.
4. **Warning cron.** `tax:check-successor-config` scheduled (e.g. monthly from Jan) reports to Sentry when the current tax year has no successor config ≥ N weeks before 6 April. Never silent again.
5. **ISA reset** follows automatically once the active year is date-driven (`ISATracker` keys by active-config year); add a test proving allowance resets when the active year advances.
6. **TB-4 / TB-7 boundary bug.** Fix the `month >= 4 && day >= 6` predicate in `StorePersonalAccountLineItemRequest:25` and `RetirementStrategyService:1387-1390` to `month > 4 || (month === 4 && day >= 6)`. Add frozen-clock tests at boundary dates (May 3, Dec 3, Apr 5, Apr 6).
7. **TB-5 salary-sacrifice copy.** `SalarySacrificeAnalyzer:293-335` and `RetirementActionDefinitionService:1284-1298` must read the configured `nic_exemption_cap_effective_date` (2027-04-06) instead of hardcoding "April 2029"; reconcile the date with CSJ (memory `project_salary_sacrifice_2k_upcoming_law` says treat as 2027/28 — "2029" copy is wrong).
8. **TB-9 AI knowledge.** `FinancialPlanningKnowledge.php` dated facts (`:15,108,111,132`) — remove hardcoded worked figures (defer to `get_tax_information` tool) and correct the "NRB frozen until 2028" / pension-age facts, or date-stamp + gate them.
9. **TB-11 fallback telemetry.** The ~30 `?? 12570`-style inline fallbacks are sanctioned but must emit a Sentry breadcrumb/warning when hit (a config-shape regression should not be silent). Add a single helper `taxConfigOrReport($key, $fallback)` used at the highest-traffic sites; full sweep optional.

**Non-goals:** TB-13 public-page hardcoded copy and TB-14 leap-year skew are low — fix opportunistically, not gating.

---

## WS-G — Concurrency

**Definition of done:**
1. **Cache invalidation (high).** `PropertyController`, `InvestmentController`, `MortgageController` create/update/delete must call `CacheInvalidationService::invalidateForUserAndSpouse` (as Savings etc. already do). Fix the stale 24h `/m` dashboard + module summaries. Correct the `MobileDashboardAggregator` comment ("5 min" → "24h"). **/m note:** this is the primary fix for the stale mobile dashboard.
2. **Server-side idempotency (medium).** Apply the existing `idempotent` middleware (currently only on AI sendMessage) to all module `store` routes, OR add a natural-key guard. `ponytail:` reuse the existing middleware + `ai_request_idempotency`-style table; do not build a new mechanism.
3. **Lost-update locks (medium).** Wrap the read-modify-write in `TracksGoalContributions:53-69`, `GoalProgressService:79-93` (goal `current_amount`) and `PointsService:50-60` (gamification aggregate) in `DB::transaction` + `lockForUpdate`, or use atomic `increment()`. Add concurrency tests.
4. **Monte Carlo ownership race (medium).** In `InvestmentController:216-231`, write the `monte_carlo_status` owner key **before** dispatching the job (and inside a transaction), so an async worker never reads a null owner and 404s the real user. Add per-user dispatch dedupe.
5. **Inflight lock (medium).** `AiChatController` — make the `fyn:inflight` lock TTL exceed the max tool-loop turn, or renew it mid-stream; ensure a hard-killed stream doesn't strand a queued turn (the `ConcurrentTurnQueue::expireStale` → silent drop path). At minimum, report to Sentry when a turn is swept to `expired`.
6. **Debounce marker (low).** `RiskRecalculationObserver:28-33` use `Cache::add` (atomic) instead of `has`-then-`put`.

---

## WS-H — Scale cliffs (depends on WS-B)

**Definition of done:**
1. **PlanningProgressService::distribution (critical).** Stop loading all users + ~10 queries each per request. Move the cohort distribution to a **scheduled aggregate** (nightly command writes a `planning_distribution` summary row/cache; the request reads the precomputed percentile). 
2. **Monte Carlo off-request (high).** Once WS-B lands, ensure `RetirementProjectionService` MC runs happen via the queue / precomputed cache, not synchronously inside a cold dashboard load. The dashboard reads the last computed result and shows "updating" if stale.
3. **Summariser (high).** `summarise-stale` — replace the unindexable `JSON_EXTRACT` scan with an indexed column, and (post-WS-B) dispatch to the real queue so calls aren't sequential-inline.
4. **HasAiChat column list (high).** `HasAiChat:1194-1198` `buildMessageHistory()` must `select(['role','content', ...])` — never hydrate the longText `system_prompt`/`assembled_context` columns on every chat turn.
5. **Admin sums index (high).** Add a `created_at`-only index to `ai_messages` (or restructure the admin dashboard `sum()` queries `AdminController:55-93` to use an aggregate table). 
6. **Unchunked purge (medium).** `PurgeAuditLogs:68-69` chunk the DELETE (mirror `AiAuditRetentionJob`'s chunking).
7. **Unbounded transcript (medium).** `AiChatController::show:99-102` paginate/cap the returned transcript.

**Non-goals:** SSE worker-pinning (cliff 5) is a hosting ceiling, not a code fix — document it, don't chase it.

---

## WS-I — Test coverage (gates every other WS's "done")

**Definition of done:**
1. **IHT / RNRB (critical).** New unit tests for `IHTCalculationService` maths: NRB, RNRB, RNRB **taper at estates > £2M** (pin exact £ at £2,000,000 / £2,000,001 / £2.35M — the £1-per-£2 taper), TNRB via `SpouseNRBTrackerService`, gifts/PETs. A feature test pinning an IHT **£ figure** from `/api/estate/calculate-iht` (not just JSON shape).
2. **Zero-test money services (high).** Add characterisation/boundary tests for the highest-stakes untested classes: `InvestmentProjectionService`, `ChattelCGTService`, `PSACalculator`, `FSCSAssessor`, `WhatIfCalculator`, the Markowitz/rebalancing stack, `SpouseNRBTrackerService`, `CalculatesOwnershipShare` trait. Full list: `scratchpad/untested_services.txt` from the audit (regenerate).
3. **Cannot-fail tests (high).** Replace `expect(true)->toBeTrue()` cache test (`RetirementIntegrationTest:395-406`) and the self-consistency-only IHT test (`EstateIntegrationTest:248-249`) with real assertions. Add DB-row assertions to the status-only Protection policy CRUD tests.
4. **Boundary inputs.** Feed exact £50,270 / £50,271 / £100,000 / £125,140 / £125,141 into `UKTaxCalculator` tests.
5. **Ownership.js (high, /m-relevant).** Add vitest tests for `resources/js/utils/ownership.js` (the frontend mirror of `CalculatesOwnershipShare`) so backend/frontend joint-share maths can't drift. `currency.js` rounding + `taxConfig.js getCurrentTaxYear()` boundary too.

---

## WS-J — Framework EOL (SEPARATE PROJECT — reference only, D1)

Not implemented here. Tracked as its own effort: Laravel 10→12, Sanctum 3→current, PHP 8.2/8.3 posture, plus abandoned-package replacements (`vuex-persistedstate`, superseded Vite/Capacitor/Vuex majors). Approach: dedicated branch, full regression via the 5,500-test suite + browser passes, staged `feature → dev (csjones) → main`. Risk: breaking changes cascade through Sanctum auth + the AI streaming path. This spec records it so it is not lost; it does not schedule it.

---

## Global acceptance (the whole remediation is "done" when)

- All WS-A–I acceptance criteria met and **browser-verified per Rule 14/Testing** on csjones for user-facing items (dashboard error states, tax admin UI, cache freshness on `/m`).
- `./vendor/bin/pest` green including the new WS-I tests; `npm test` (vitest) green including new frontend tests.
- No new Rule 10/11/12/15 violations (design, scores, icons) in the tax admin UI or dashboard error states.
- Sentry receiving events from csjones; a deliberately-failed scheduled command and a deliberately-failed job both raise alerts.
- Each change flows `feature → dev → main` per the branch workflow; nothing to `main` without csjones verification.
