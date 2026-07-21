# Blind-Spot Remediation — Implementation Plan

**Spec:** [blindspot-remediation-spec.md](blindspot-remediation-spec.md) · **Audit:** [blindspot-audit-2026-07-07.md](blindspot-audit-2026-07-07.md)
**For:** Opus 4.8 implementation. Each task is self-contained: files, change, tests, acceptance, dependencies, `/m` note.

## How to use this plan

- Tasks are ordered by dependency. **Do WS-A and WS-B first** — they make every later regression visible and every "queued" job actually async.
- Each task branches off `dev` (Rule: `feature → dev → main`). Group related tasks per PR (lean cadence, Rule 17), but every user-facing item gets a live csjones browser-verify before "done" (Rule 14).
- **Before touching any module**, read its vault docs (`fynlaBrain/`, table in root CLAUDE.md) and dispatch sub-agents with that context (MANDATORY sub-agent vault context rule).
- Tax values: **never invent** — confirm every 2027/28 figure with CSJ / `TaxConfigService` sourcing (Rule 2).
- Design: read `fynlaDesignGuide.md` before the tax admin UI + dashboard error states. Rules 10/11/12/15 apply.
- Do NOT run `migrate:fresh`/`refresh`; `db:seed` after any migration. Reseed after each session.

Legend: **[BE]** backend (reaches /m free) · **[FE]** frontend · **[FE-m]** mobile /m surface · **[ADMIN]** desktop-admin only, no /m · **[INFRA]** config/deploy · **[TEST]**

---

# WS-A — Observability & Alerting  (do first)

### A1 [INFRA] Install + configure Sentry
- **Files:** `composer.json` (add `sentry/sentry-laravel`), `config/sentry.php` (published), `.env.example`, both `deploy/*/.env.production`.
- **Change:** `composer require sentry/sentry-laravel`; publish config; add `SENTRY_LARAVEL_DSN=` to `.env.example` (empty), real DSN to both prod envs. Set `traces_sample_rate` low/0 (errors only, no APM). In `config/sentry.php` set `send_default_pii => false`.
- **Acceptance:** with an empty DSN locally, app boots and nothing is sent (no crash). With a DSN on csjones, a thrown test exception appears in Sentry.
- **Dep:** none.

### A2 [BE] Wire `reportable()` + PII scrubbing
- **File:** `app/Exceptions/Handler.php:36-38`.
- **Change:** in the empty `reportable` closure, attach user-id context (`Sentry\configureScope`) and forward. Add a `before_send` scrubber (in `config/sentry.php`) that strips `national_insurance_number`, email, and any `annual_*_income`/currency values from event payloads. Keep the existing `renderable` API-JSON path unchanged.
- **Acceptance:** an unhandled exception in an authenticated request creates a Sentry event tagged with the user id and **no** PII in breadcrumbs/extra.
- **Dep:** A1.

### A3 [INFRA] Add `sentry` log channel to the stack
- **File:** `config/logging.php:55-59`, `:76-83`.
- **Change:** add a `sentry` channel (driver `sentry`, level `error`); set `stack.channels => ['single', 'sentry']`. Wire `slack` as optional: include only when `LOG_SLACK_WEBHOOK_URL` is set (leave out of the default stack, keep as opt-in secondary). Keep `single`.
- **Acceptance:** `Log::error(...)` on csjones reaches Sentry; `Log::warning(...)` does not (below threshold); local (no DSN) still writes `single`.
- **Dep:** A1.

### A4 [BE] Scheduler failure hooks + overlap guards
- **File:** `app/Console/Kernel.php`.
- **Change:** add a private helper `alertOnFailure(string $command)` returning a closure that calls `report(new \RuntimeException("Scheduled command failed: {$command}"))`. Chain `->onFailure($this->alertOnFailure('<name>'))` onto **every** `$schedule->command(...)`/`->job(...)`. Add `->withoutOverlapping()` to `accounts:purge-after-retention`, `accounts:execute-scheduled-deletions`, `accounts:execute-grace-deletions`, `subscriptions:check-overdue`, `tier:sync-revolut`, `audit:purge`, `fyn:episodic:cold-archive`, `fyn:episodic:reconcile`, the `AiAuditRetentionJob`.
- **Acceptance:** a command forced to throw raises a Sentry event naming it; two overlapping runs of a guarded command don't both execute.
- **Dep:** A1–A3.

### A5 [BE] Scheduler heartbeat (Sentry cron monitor)
- **File:** `app/Console/Kernel.php`, plus a tiny `app/Console/Commands/SchedulerHeartbeat.php`.
- **Change:** register a Sentry cron check-in (Laravel Sentry supports `->sentryMonitor()` on scheduled tasks) on a `->everyFifteenMinutes()` heartbeat command. If the scheduler stops, Sentry's missed-check-in alerts.
- **Acceptance:** stopping cron on csjones triggers a Sentry missed-check-in within the window.
- **Dep:** A1.

### A6 [BE] Failed-jobs watchdog
- **File:** `app/Console/Commands/AlertFailedJobs.php` (new), `Kernel.php` (`->dailyAt('06:00')` + `onFailure`).
- **Change:** query `failed_jobs`; if non-empty, `report()` the count + oldest UUID + failed_at to Sentry.
- **Acceptance:** with a seeded `failed_jobs` row, the command raises a Sentry event; empty table = silent.
- **Dep:** A1, and WS-B (the `failed_jobs` table is only populated once the queue is non-sync — but the command is safe to add now).

---

# WS-B — Queue infrastructure

### B1 [INFRA] Ensure queue tables exist
- **Files:** `database/migrations/*` — confirm `jobs`, `job_batches`, `failed_jobs` migrations exist (Laravel defaults; `config/queue.php` already references them). Create any missing via `php artisan queue:table` / `queue:failed-table` / `queue:batches-table`.
- **Acceptance:** `php artisan migrate` creates all three; `db:seed` after.
- **Dep:** none.

### B2 [INFRA] Switch driver + worker cron
- **Files:** both `deploy/*/.env.production:41` → `QUEUE_CONNECTION=database`; `deploy/DEPLOY.md` (document the worker cron); `.env.example` (note: `sync` local ok).
- **Change:** add server cron: `* * * * * cd <laravel-root> && php artisan queue:work --stop-when-empty --max-time=55 --tries=3 --sleep=3 >> storage/logs/worker.log 2>&1`. `ponytail:` every-minute self-terminating worker — the SiteGround-safe pattern; no daemon, no supervisor, no Redis. Upgrade path: a persistent `queue:work` under a process manager if throughput ever needs it.
- **Acceptance:** on csjones, a dispatched job creates a `jobs` row and is processed by the next worker tick, not inline in the request.
- **Dep:** B1.

### B3 [BE] `failed()` handlers on every job
- **Files:** `app/Jobs/*.php` — `RunMonteCarloSimulation`, `RecalculateRiskProfileJob`, `ConversationSummariserJob`, `AiAuditRetentionJob`, `AiIdempotencyCleanupJob`, `PublishScheduledInsightsJob`, `FireAwinConversionJob` (already has one).
- **Change:** add `public function failed(\Throwable $e): void { report($e); }`; for `RunMonteCarloSimulation` also mark the simulation row `status='failed'` (it already does this in its own catch — ensure the terminal `failed()` also does, for retry-exhaustion). Remove the swallow-and-return-success in `RecalculateRiskProfileJob:75-80` so a real failure retries/lands in `failed_jobs`.
- **Acceptance:** a job that exhausts retries lands in `failed_jobs` AND raises Sentry; Monte Carlo failure marks the row failed (user sees an error, not a spinner forever).
- **Dep:** B1, A1.

### B4 [TEST] Async proof
- **File:** `tests/Feature/Queue/DatabaseQueueTest.php` (new).
- **Change:** with `QUEUE_CONNECTION=database`, dispatch a marker job, assert a `jobs` row exists and the marker is NOT yet written (would be written inline under sync). Keep the app test-suite on `sync` otherwise (deterministic).
- **Dep:** B1–B2.

---

# WS-C — Silent-failure remediation

> Pattern for all: replace "log at warning + return zero/empty/success" with "report to Sentry (`report($e)`), return an explicit error/unavailable marker, never a fabricated value". Dashboard tiles already have a `_error` path via `safeSummary` — route to it.

### C1 [BE][FE][FE-m] Dashboard all-zero laundering
- **Files:** `app/Services/Dashboard/DashboardAggregator.php:139,161,188,215,242` (+ summary methods `:255,294,334,373,396`); frontend dashboard cards that render module summaries (web `resources/js/` + `/m` `resources/mobile/views/` equivalents).
- **Change:** in each analysis catch, `report($e)` and set an `_error` marker so the summary method returns an error state rather than all-zeros. Frontend: render "We couldn't load this section right now" (Rule 15 — **no icon**), never £0. Mirror on `/m`.
- **Acceptance:** force an agent to throw → the tile shows an error state on web AND `/m`, not £0 net worth. Sentry event raised. **Browser-verify on csjones both surfaces.**
- **Dep:** A1–A3.

### C2 [BE] Dashboard alerts dropped
- **File:** `DashboardAggregator.php:101,464,553,650,731,815`.
- **Change:** `report($e)`; surface an "alerts unavailable" flag rather than a silent `[]`.
- **Acceptance:** forced failure raises Sentry + the UI indicates alerts couldn't load. **Dep:** A1.

### C3 [BE] Strategy sources — no log
- **Files:** `app/Services/Coordination/PlanSources/ProtectionStrategySource.php:85`, `EstateStrategySource.php:39`.
- **Change:** add `report($e)` inside the `catch (Throwable)` (behaviour `return []` may stay).
- **Acceptance:** forced failure raises Sentry. **Dep:** A1.

### C4 [BE] IHT £0 laundering
- **File:** `app/Agents/EstateAgent.php:132` (+ response build `:305-306`, gifting `:165-172`).
- **Change:** on IHT calc failure, `report($e)` and return `success=false` + error marker; do NOT emit `iht_liability=0, effective_tax_rate=0, success=true`; do NOT run the gifting calc against a fabricated £0.
- **Acceptance:** forced IHT failure → estate tile shows error, not "£0 IHT". **Browser-verify.** **Dep:** A1, C1.

### C5 [BE][FE-m] Mobile net worth £0
- **File:** `app/Services/Mobile/MobileDashboardAggregator.php:369` (use the `:97` unavailable-marker pattern).
- **Change:** return an explicit unavailable marker, not `total=0.0`.
- **Acceptance:** forced failure → `/m` dashboard shows unavailable, not £0.00. **Browser-verify on /m.** **Dep:** A1.

### C6 [BE] Revolut cancel returns success on failure
- **File:** `app/Http/Controllers/Api/PaymentController.php:972`.
- **Change:** if `cancelSubscription()` throws, `report($e)` and return an actionable error; do NOT mark local cancelled + `success:true`. Leave the subscription active (or retry) so billing state matches reality.
- **Acceptance:** simulated Revolut-cancel failure → user gets an error, local state not falsely cancelled. **Dep:** A1.

### C7 [BE] Revolut webhook returns 200 on processing failure
- **File:** `app/Http/Controllers/Api/WebhookController.php:191,212,223,234` (+ signature verify `:46-49`).
- **Change:** on processing exception, `report($e)` and return **non-2xx** (e.g. 500) so Revolut retries. Signature-verify failure: `report()` with context.
- **Acceptance:** a handler that throws returns non-2xx (Revolut will retry); a paid order that failed processing eventually activates on retry. **Dep:** A1.

### C8 [BE] Verification email success-on-failure
- **File:** `app/Http/Controllers/Api/AuthController.php:113-126` (register), `:274-286` (MFA).
- **Change:** wrap the `Mail::send` so a throw returns an error response (match `:698-715` which already returns 500) instead of `success:true "check your email"`. `report($e)`.
- **Acceptance:** simulated SMTP failure at register/MFA → user sees a real error, not a false "check your email". **Dep:** A1.

### C9 [BE] pointerTools() silent strip
- **Files:** `app/Services/AI/XaiToolDefinitions.php:196-201`, `AiToolDefinitions.php:225-227`.
- **Change:** `report($e)` in the `catch` before `return []`.
- **Acceptance:** forced failure raises Sentry (Fyn degradation now traceable). **Dep:** A1.

### C10 [BE] TrustObserver CLT gift
- **File:** `app/Observers/TrustObserver.php:48`.
- **Change:** `report($e)`; flag the trust as needing its CLT (a column or a reconcile queue) so a missing chargeable lifetime transfer is recoverable, not silently wrong.
- **Acceptance:** forced failure raises Sentry + the trust is flagged for reconcile. **Dep:** A1.

---

# WS-D — GDPR / data-lifecycle

### D1 [BE][TEST] Purge completeness audit + extension
- **Files:** `app/Services/Account/RetentionPurgeService.php:259` (`getDeletionOrder`), new `tests/Feature/GDPR/PurgeCompletenessTest.php`.
- **Change:** enumerate every table with a `user_id` (or user FK) by scanning `app/Models/` + migrations; diff against `getDeletionOrder()`; add all missing (spec WS-D#1 list). For each addition confirm it is not FCA-retention-protected younger than 6y (advice content). Write the completeness test: create a user with a row in every user-keyed table, purge, assert zero rows remain (except the deliberate re-registration tombstone).
- **Acceptance:** the test enumerates tables and fails if any user-keyed table is missing from the purge — this becomes the permanent guard.
- **Dep:** none (but coordinate with D2).

### D2 [BE] Hard-delete users row as final purge step + fix docblocks
- **File:** `RetentionPurgeService.php:105` (Phase 8), `:22-24,167-169` (docblocks), `:103` (email preservation).
- **Change:** after all children are deleted (D1), **hard-delete** the users row so FK cascades become real/self-maintaining. Preserve re-registration email in a dedicated tombstone table, not the live users row. Correct the false "SET NULL fires" docblocks.
- **Acceptance:** post-purge, the users row is gone; a fresh registration with the same email works; docblocks accurate. **Dep:** D1, D3 (joint policy must run before the hard delete).

### D3 [BE][TEST] Joint-record purge = reassign to spouse
- **File:** `RetentionPurgeService.php:64-70`, new `SpouseReassignmentService` (or inline), `tests/Feature/GDPR/JointRecordPurgeTest.php`.
- **Change:** before deleting A's records, for each joint record where `joint_owner_id = B`, reassign primary ownership to B (`user_id → B`, `joint_owner_id → null`, `ownership_percentage → 100 - A_pct`), and `report`/log each. Only delete when there is no joint owner.
- **Acceptance:** purge A → B still sees the formerly-joint property/savings at B's correct share; nothing of B's is destroyed. **Dep:** none (run before D2's hard delete).

### D4 [BE] fyn:user:erase FCA age guard
- **File:** `app/Console/Commands/FynUserErase.php:61-73`.
- **Change:** skip hard-deleting `ai_messages`/`ai_advice_logs` younger than the FCA SYSC 6-year minimum; print an explicit note of what was retained and why.
- **Acceptance:** erasing a user with recent advice content retains it (with a printed reason) and erases older content. **Dep:** none.

### D5 [BE] Export cleanup
- **Files:** `app/Services/GDPR/DataExportService.php:260` (`cleanupExpiredExports` — wire it), new `app/Console/Commands/CleanupExpiredExports.php`, `Kernel.php` (`->dailyAt('03:45')` + onFailure), `RetentionPurgeService.php:341` (delete export **files**, not just rows).
- **Change:** schedule the cleanup; at purge, delete `storage/app/exports/user_{id}_*` files.
- **Acceptance:** an expired export file is removed by the daily job; purge removes export files. **Dep:** A4.

### D6 [BE] Fix the `salary`-column break + copy
- **File:** `app/Http/Controllers/Api/GDPRController.php:569-575` (delete-data), `:292` (`confirmErasure` copy).
- **Change:** remove the non-existent `salary` key; null the **real** columns: `annual_employment_income`, `annual_self_employment_income`, `annual_rental_income`, `annual_dividend_income`, `annual_interest_income`, `annual_other_income`, `annual_trust_income`, `employer`, `national_insurance_number`, `employment_status`. Correct the success copy so it states exactly what was cleared (do NOT claim "all financial data deleted" while accounts/pensions remain) — or, if CSJ wants delete-data to cascade to financial records, do that instead (ASK if unclear; default = correct copy). Fix `confirmErasure` copy to state the 7-year retention window.
- **Acceptance:** the delete-data endpoint no longer 500s (no unknown-column error); after calling it, the named columns are null; copy is truthful. **Browser-verify the endpoint end-to-end (passes 2FA, succeeds).** **Dep:** none — this is a live break, prioritise.

---

# WS-E — Joint-ownership authorization  (D2: validate + revoke)

### E1 [BE] Write-time spouse validation (F1)
- **Files:** new `app/Rules/IsAcceptedSpouse.php`; apply in `StorePropertyRequest:44`, `UpdatePropertyRequest:44`, `StoreMortgageRequest:73`, `Savings/StoreSavingsAccountRequest:62`, `Savings/UpdateSavingsAccountRequest:56`, `StoreInvestmentAccountRequest:68`, `UpdateInvestmentAccountRequest:65`, `Goals/StoreGoalRequest:38`, `Chattel/StoreChattelRequest:41`, `BusinessInterest/StoreBusinessInterestRequest:48`, `StoreLifeEventRequest:36`; and service-store guards (`MortgageStore:312`, `SavingsStore:268`, etc.).
- **Change:** the rule passes when `joint_owner_id` is null OR equals the acting user's accepted spouse (`$user->hasAcceptedSpousePermission()` + `spouse_id` match). Replace bare `exists:users,id`.
- **Acceptance:** setting `joint_owner_id` to a non-spouse id is rejected 422; a genuine spouse still works; individual (null) works. Add `tests/Feature/JointOwnership/JointOwnerValidationTest.php`.
- **Dep:** none. **Reaches /m free** (shared FormRequests).

### E2 [BE][TEST] Revoke on unlink (F3)
- **Files:** new `app/Services/Family/SpouseUnlinkService.php`; call from `FamilyMembersController::unlink:561-602` and `CoordinatingAgent:1270-1280` (divorce/marital change).
- **Change:** in one transaction, null `joint_owner_id` on every record where the unlinked pair are the two owners, set the primary owner's `ownership_percentage → 100`. Cover all `HasJointOwnership` models (Property, Mortgage, Savings, Investment, Goal, Chattel, BusinessInterest, LifeEvent, Liability, JointAccountLog).
- **Acceptance:** after unlink, the ex-spouse no longer sees any formerly-joint asset; the owner retains the full asset. `tests/Feature/JointOwnership/UnlinkRevokesAccessTest.php`.
- **Dep:** none. **Reaches /m free.**

### E3 [BE] Low-severity parity fixes (F5/F6/F7)
- **Files:** `EstateController.php:92` (liabilities → `forUserOrJoint`), `InvestmentController.php:477,662` (scope query not load-then-`!==`), `User.php:55` (remove `is_admin` from `$fillable`).
- **Acceptance:** joint owner sees joint liabilities; investment update/destroy scopes correctly; `User::create(['is_admin'=>true])` from a non-admin path can't set admin. **Dep:** none.

---

# WS-F — Tax-year rollover  (D3: full admin UI)

> Confirm every 2027/28 figure with CSJ before seeding (Rule 2). Read `fynlaBrain` UKTaxes.md + `fynlaDesignGuide.md` first.

### F1 [BE] Date-driven activation
- **Files:** `app/Services/Stores/TaxConfigStore.php:219-227`; new `app/Console/Commands/ActivateCurrentTaxYear.php`; `Kernel.php` (`->dailyAt('00:30')` + onFailure).
- **Change:** the command sets `is_active=true` on the row whose `effective_from <= now() < effective_to` (Europe/London), false on others; idempotent. `activeConfig()` unchanged for consumers. Keep the loud RuntimeException when no row matches.
- **Acceptance:** with a 2027/28 row seeded, running the command on a simulated 2027-04-06 activates 2027/28. `tests/Feature/Tax/DateDrivenActivationTest.php` with `setTestNow`.
- **Dep:** F2 (needs a successor row to activate), A4.

### F2 [BE] Seed 2027/28 + stop re-pinning
- **File:** `database/seeders/TaxConfigurationSeeder.php:36-43,~70` (`ACTIVE_TAX_YEAR`).
- **Change:** add the 2027/28 config (CSJ-confirmed values); remove the hard `ACTIVE_TAX_YEAR` re-pin — seed all years, let F1's date-activation choose. Reseeding after 2027-04-06 must not force 2026/27.
- **Acceptance:** `db:seed` then the F1 command activates the date-correct year; a reseed on a 2027 clock does not revert to 2026/27. **Dep:** none (but F1 consumes it).

### F3 [ADMIN][FE] Admin config-authoring UI
- **Files:** new admin Vue view under `resources/js/views/Admin/` (wrapped in `AppLayout`, Rule 13); new API controller `app/Http/Controllers/Api/Admin/TaxConfigurationController.php` (`permission:admin.access`); routes in `routes/api.php` admin block; writes via `TaxConfigStore` (boundary rule).
- **Change:** list tax years; **clone** a year forward to a draft; edit every band/threshold; set `effective_from/to`; activate. Rules 10/11 design tokens, Rule 12 no scores, Rule 15 no decorative icons (admin is "ASK CSJ" surface — default no icons). No `/m` counterpart (admin is desktop-only by design, Rule 19 exception).
- **Acceptance:** an admin can clone 2027/28 → 2028/29 draft, edit, set dates, activate; a non-admin gets 403 on the API. **Browser-verify on csjones as admin.**
- **Dep:** F1 (activation semantics).

### F4 [BE] Successor-config warning cron
- **File:** new `app/Console/Commands/CheckSuccessorTaxConfig.php`; `Kernel.php` (`->monthlyOn(1,'07:00')` Jan–Apr, + onFailure).
- **Change:** if the active year has no successor config ≥ 6 weeks before its `effective_to`, `report()` a Sentry warning.
- **Acceptance:** with no 2028/29 config in Feb-2028 sim, the command alerts. **Dep:** A1, F1.

### F5 [TEST] ISA reset proof
- **File:** `tests/Unit/Services/Savings/ISATrackerResetTest.php`.
- **Change:** with `setTestNow` crossing 2027-04-06 and the active year advanced, assert a fresh £20k allowance (no carry of the 2026/27 used amount). **Dep:** F1.

### F6 [BE][TEST] Boundary predicate bug (TB-4/TB-7)
- **Files:** `app/Http/Requests/StorePersonalAccountLineItemRequest.php:25`, `app/Services/Retirement/RetirementStrategyService.php:1387-1390`.
- **Change:** replace `month >= 4 && day >= 6` with `month > 4 || (month === 4 && day >= 6)`. 
- **Acceptance:** frozen-clock tests at 2026-05-03, 2026-12-03, 2027-04-05, 2027-04-06 return the correct tax year in both sites. **Dep:** none.

### F7 [BE] Salary-sacrifice date-aware copy (TB-5)
- **Files:** `app/Services/Retirement/SalarySacrificeAnalyzer.php:293-335`, `RetirementActionDefinitionService.php:1284-1298`.
- **Change:** read `nic_exemption_cap_effective_date` (2027-04-06 per config + memory) instead of hardcoded "April 2029"; rename `post_2029_*`/`exceeds_2029_cap` to date-derived keys. Confirm the year with CSJ.
- **Acceptance:** copy + gating reflect the configured date; no "2029" literals. **Dep:** none.

### F8 [BE] AI knowledge facts (TB-9)
- **File:** `app/Constants/FinancialPlanningKnowledge.php:15,108,111,132`.
- **Change:** remove hardcoded worked £ figures (defer to the `get_tax_information` tool); correct/date-gate the "NRB frozen until 2028" + pension-age-57-in-2028 facts.
- **Acceptance:** no stale hardcoded tax figures in the prompt constant. **Dep:** none.

### F9 [BE] Fallback telemetry (TB-11)
- **Files:** new helper (e.g. `TaxConfigService::getOrReport($key,$fallback)`); apply at the highest-traffic `?? 12570`/`?? 50270`/`?? 268275` sites (`TaxStrategyCalculator`, `DecumulationPlanner`, `SaveTaxEstimateService`, `TaxConfigService:190`).
- **Change:** emit a Sentry breadcrumb/warning when a fallback is hit.
- **Acceptance:** a simulated config-lookup miss raises a warning instead of silently substituting. **Dep:** A1. `ponytail:` don't sweep all 30 — cover the hot sites; the rest stay as-is.

---

# WS-G — Concurrency  (WS-B first)

### G1 [BE][FE-m] Cache invalidation for Property/Investment/Mortgage
- **Files:** `PropertyController`, `InvestmentController`, `MortgageController` (create/update/delete); `MobileDashboardAggregator.php:37` (comment fix).
- **Change:** call `CacheInvalidationService::invalidateForUserAndSpouse($userId)` after each write (mirror `SavingsController`). Fix the "5 min" comment to "24h".
- **Acceptance:** edit a property on web → `/m` dashboard + investment/estate module summaries reflect it immediately (no 24h stale). **Browser-verify web→/m.** **Dep:** none.

### G2 [BE] Server-side idempotency on module creates
- **Files:** `routes/api.php` (module `store` routes), reuse `app/Http/Middleware/*` `idempotent` + `ai_request_idempotency`-style table (generalise its name if needed).
- **Change:** apply the existing idempotency middleware to savings/property/investment/goals/policy `store` routes. `ponytail:` reuse the mechanism already applied to AI sendMessage; do not build a new one.
- **Acceptance:** a double-submitted create with the same idempotency key creates one row. `tests/Feature/Idempotency/ModuleCreateIdempotencyTest.php`. **Dep:** none.

### G3 [BE][TEST] Lost-update locks
- **Files:** `app/Traits/TracksGoalContributions.php:53-69`, `app/Services/Goals/GoalProgressService.php:79-93`, `app/Services/Gamification/PointsService.php:50-60`.
- **Change:** wrap read-modify-write in `DB::transaction` + `lockForUpdate`, or use atomic `increment()`/`decrement()` for goal `current_amount` and `user_gamification.total_points`.
- **Acceptance:** concurrency test (two simultaneous contributions) shows no lost delta. **Dep:** WS-B (real async makes this reachable), but the fix is unconditionally correct.

### G4 [BE] Monte Carlo ownership race
- **File:** `app/Http/Controllers/Api/InvestmentController.php:216-231`.
- **Change:** write the `monte_carlo_status_{jobId}` owner key (in a transaction) **before** `dispatch()`; add per-user dispatch dedupe (reject a second concurrent MC job for the same user).
- **Acceptance:** under the database queue, the owner never reads null → no false 404; a stacked second job is rejected. **Dep:** WS-B.

### G5 [BE] Inflight lock robustness
- **File:** `app/Http/Controllers/Api/AiChatController.php:207,397`, `app/Services/AI/Loop/ConcurrentTurnQueue.php:135-143`.
- **Change:** set the `fyn:inflight` lock TTL above the max tool-loop turn (or renew mid-stream); when a queued turn is swept to `expired`, `report()` it (no silent drop).
- **Acceptance:** a >5-min turn doesn't let a second stream write concurrently; an expired queued turn raises Sentry. **Dep:** A1.

### G6 [BE] Atomic debounce marker
- **File:** `app/Observers/RiskRecalculationObserver.php:28-33`.
- **Change:** `Cache::add($key, true, 5)` (atomic) instead of `has`-then-`put`.
- **Acceptance:** two concurrent saves dispatch one recalc, not two. **Dep:** none.

---

# WS-H — Scale cliffs  (WS-B first)

### H1 [BE] PlanningProgressService distribution → scheduled aggregate
- **Files:** `app/Services/Mobile/PlanningProgressService.php:92-96`; new `app/Console/Commands/BuildPlanningDistribution.php`; `Kernel.php` (`->dailyAt('05:00')` + onFailure); a cache/table for the precomputed distribution.
- **Change:** the nightly command computes the cohort distribution once; the request reads the precomputed percentile instead of loading all users.
- **Acceptance:** `/api/v1/mobile/dashboard` issues O(1) queries for the percentile, not O(users). **Browser-verify /m dashboard still shows the percentile.** **Dep:** A4.

### H2 [BE] Monte Carlo off the cold dashboard path
- **Files:** `app/Services/Retirement/RetirementProjectionService.php:111,184`, `RetirementAgent.php:95`.
- **Change:** compute MC via the queue / a precomputed cache; the dashboard reads the last result and shows "updating" if the input hash changed. `ponytail:` reuse the existing input-hash cache key; just move the compute off-request.
- **Acceptance:** a cold dashboard load after a pension change returns fast (no inline 1000-iteration run); the fresh result appears after the worker runs. **Dep:** WS-B.

### H3 [BE] Summariser scan + dispatch
- **Files:** `app/Console/Commands/SummariseStaleConversationsCommand.php:53-84`.
- **Change:** replace the unindexable `JSON_EXTRACT(metadata,...)` predicate with an indexed column (add a migration for a `summary_state`/`last_summarised_message_id` column + index); dispatch `ConversationSummariserJob` to the real queue (post-WS-B) so calls aren't sequential-inline.
- **Acceptance:** the stale-scan uses an index (EXPLAIN shows no full scan); summaries run via the worker. **Dep:** WS-B.

### H4 [BE] HasAiChat column list
- **File:** `app/Traits/HasAiChat.php:1194-1198`.
- **Change:** `->select(['id','role','content','conversation_id','created_at'])` — never hydrate `system_prompt`/`assembled_context` longText on every turn.
- **Acceptance:** the query selects only needed columns (assert the SQL / model attributes). **Dep:** none.

### H5 [BE] ai_messages created_at index / admin aggregate
- **Files:** new migration adding a `created_at` index to `ai_messages`; `AdminController.php:55-93`.
- **Change:** add the index so the admin dashboard `sum()`/`where created_at` queries don't full-scan; OR move admin metrics to an aggregate table. `ponytail:` the index is the one-line fix — take it first, aggregate table only if it's still slow.
- **Acceptance:** EXPLAIN on the admin queries uses the index. **Dep:** none.

### H6 [BE] Chunk audit purge + cap transcript
- **Files:** `app/Console/Commands/PurgeAuditLogs.php:68-69`, `app/Http/Controllers/Api/AiChatController.php:99-102`.
- **Change:** chunk the audit DELETE (mirror `AiAuditRetentionJob`); paginate/cap the transcript in `show()`.
- **Acceptance:** purge runs in bounded chunks; `show()` on a 2,000-turn conversation returns a bounded page. **Dep:** none.

---

# WS-I — Test coverage  (parallel; gates "done")

### I1 [TEST] IHT / RNRB taper
- **Files:** new `tests/Unit/Services/Estate/IHTCalculationServiceTest.php`, `SpouseNRBTrackerServiceTest.php`; extend `tests/Feature/Estate/EstateApiTest.php`.
- **Change:** pin exact £ for NRB, RNRB, **RNRB taper at £2,000,000 / £2,000,001 / £2,350,000** (£1-per-£2), TNRB, gifts/PETs. Add a feature test pinning an IHT **£ figure** from `/api/estate/calculate-iht`. Seed `TaxConfigurationSeeder` explicitly (real values).
- **Acceptance:** tests fail if RNRB taper maths is wrong by any £. **Dep:** none.

### I2 [TEST] Zero-test money services
- **Files:** new tests for `InvestmentProjectionService`, `ChattelCGTService`, `PSACalculator`, `FSCSAssessor`, `WhatIfCalculator`, `WhatIfScenarioService`, the Markowitz/rebalancing stack, `CalculatesOwnershipShare` trait.
- **Change:** boundary + characterisation tests pinning exact outputs. Regenerate the untested-services list to prioritise.
- **Acceptance:** each named class has ≥1 value-pinning test. **Dep:** none.

### I3 [TEST] Kill cannot-fail tests
- **Files:** `tests/Feature/RetirementIntegrationTest.php:395-406`, `tests/Feature/Estate/EstateIntegrationTest.php:248-249`, `tests/Feature/Protection/ProtectionApiTest.php`.
- **Change:** replace `expect(true)->toBeTrue()` with a real cache assertion; replace self-consistency IHT with a pinned £; add DB-row assertions to the Protection CRUD status-only tests.
- **Acceptance:** each rewritten test can actually fail if the behaviour regresses. **Dep:** none.

### I4 [TEST] Tax boundary inputs
- **File:** `tests/Unit/Services/UKTaxCalculator*Test.php`.
- **Change:** exact-boundary inputs £50,270 / £50,271 / £100,000 / £125,140 / £125,141.
- **Acceptance:** boundary crossings pinned. **Dep:** none.

### I5 [TEST][FE][FE-m] ownership.js + currency + taxConfig
- **Files:** new `tests/frontend/utils/ownership.test.js`, `currency.test.js`, `taxConfig.test.js`.
- **Change:** vitest tests for `ownership.js` share maths (mirror `CalculatesOwnershipShare` — prevents FE/BE drift), `currency.js` rounding, `taxConfig.js getCurrentTaxYear()` boundary.
- **Acceptance:** `npm test` green with the new suites; ownership maths matches the backend trait's outputs. **Dep:** relates to E1/E2 (joint) and WS-I I2 (`CalculatesOwnershipShare`).

---

# WS-J — Framework EOL  (SEPARATE PROJECT — do NOT start here, D1)

Referenced only. Own branch + own plan: Laravel 10→12, Sanctum 3→current, PHP 8.2/8.3 posture, replace abandoned `vuex-persistedstate` + superseded Vite/Capacitor/Vuex majors. Full regression + browser passes, staged `feature → dev → main`. Recorded so it isn't lost.

---

## Suggested PR grouping (lean cadence, Rule 17)

1. **PR-1 Observability** (A1–A6) — foundational, low-risk, ship first.
2. **PR-2 Queue** (B1–B4) + `.env`/deploy doc — foundational.
3. **PR-3 GDPR live break** (D6) — the `salary` 500 is live; can ship independently, fast.
4. **PR-4 Silent failures** (C1–C10) — needs A. Browser-verify dashboards web+/m.
5. **PR-5 GDPR lifecycle** (D1–D5) — needs the purge tests.
6. **PR-6 Joint ownership** (E1–E3) — self-contained, reaches /m free.
7. **PR-7 Tax rollover** (F1–F9) — biggest; the admin UI (F3) is its own review. Confirm 2027/28 figures with CSJ first.
8. **PR-8 Concurrency** (G1–G6) — G1 (cache) can ship early; locks need WS-B.
9. **PR-9 Scale** (H1–H6) — needs WS-B.
10. **PR-10 Tests** (I1–I5) — land alongside each WS or as a consolidating PR; I1 (IHT) is highest value.

Each PR: diagnose → implement → `./vendor/bin/pest` (+ `npm test` for FE) → **live browser-verify on csjones for user-facing items** (Rule 14) → `feature → dev`. Nothing to `main` without csjones verification (Rule: main via dev only).

## Open items to confirm with CSJ before coding the affected task
- **F2/F3:** the exact 2027/28 HMRC figures (bands, thresholds, allowances) — never invent (Rule 2).
- **F7:** salary-sacrifice cap effective year — config says 2027-04-06, code says 2029; confirm which is law.
- **D6:** should "Delete my Data" cascade to financial records, or only clear the profile columns + fix the copy? (Default: fix copy.)
- **D2:** confirm hard-deleting the users row (vs. keeping soft-delete + permanent explicit-list maintenance) is acceptable given the re-registration-email tombstone approach.
