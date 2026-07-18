# Findings — seeded 2026-05-19 (SP3 mobile-iframe scaffold)

## 2026-07-18 — Native Swift Package 3 handoff

- Package 3 being green does not mean `/m` is fully ported. Packages 4–7 own entitlements/StoreKit, dashboard/Fyn, every financial screen, and platform/release responsibilities respectively.
- Laravel remains the API implementation; native Swift consumes typed contracts. Do not plan an API rewrite in Swift.
- A SwiftUI root view's `onDisappear` can fire during a legitimate authenticated-state transition. Cancelling the in-flight authentication action there reverted successful login/restoration flows to sign-in on clean iOS 26.5. The fixed boundary clears secrets on ordinary disappearance and cancels only explicit departures.
- Parent accessibility identifiers can propagate to SwiftUI descendants on newer iOS versions. Stable UI tests should identify actionable descendants directly and avoid assigning a root identifier that replaces every child identifier.
- Local CoreSimulator/XCTest worker materialisation can fail while a clean GitHub Xcode runner remains healthy. Keep the local limitation honest and use clean-runner evidence rather than weakening UI tests.
- Package 3 PR #633 is current with `origin/dev` at the handover point (19 ahead, 0 behind), all current-head workflows green, and Save Tax untouched in the primary checkout.
- Browser law: installed Google Chrome via connector only; never Chromium or the in-app browser. If disconnected, defer the browser gate and continue independent implementation.
- User-approved PR structure: Packages 4, 5, 6 and 7 each have a separate branch and PR. Package 6's five waves are independently closed within one Package 6 PR rather than split into five package-level PRs.

## Resolved gotchas
- **Auth is Bearer-token, not session-cookie.** `/api/auth/login` returns `requires_verification` at TOP LEVEL with `data:{challenge_token,email}` nested; `/api/auth/verify-code` → `data.access_token`. The original spec/plan wrongly described cookie auth and nested `requires_verification` — corrected in plan commit `664c9c6b` (caught in Task 5 code review; would have broken login entirely).
- **`SecurityHeaders` sets `X-Frame-Options: DENY` globally** with no `frame-ancestors`. Task 3 scopes `SAMEORIGIN` + `frame-ancestors 'self'` to `/m` and `/m/app*` only — the spec's confirmed HIGH risk, real.
- **Legacy `resources/js/mobile/` was NOT isolated** (plan assumed it was). Real cross-refs: `app.js` (native bootstrap import), `AppLayout.vue` (`OfflineBanner` on every authed web page), `auth.js:143` (mobileDashboard/clearCache dispatch), `api.js`/`preview.js` (native-guarded `/m/*` pushes). Resolved via CSJ-approved Task 8 scope expansion + Task 8b.
- **`m_full_site` cookie must be in `EncryptCookies::$except`** to be readable as plaintext for the pin check; tests use `assertPlainCookie` not `assertCookie`.

## Open / to verify next session
- **`/m/app/` trailing-slash frame-header gap (from 2026-05-20 tech-debt audit).** `SecurityHeaders.php:25` carve-out matches `m`/`m/app`/`m/app/*` but NOT the exact path `/m/app/` (trailing slash, no sub-segment). Inner router is `createWebHistory('/m/app/')`; a refresh on bare `/m/app/` can fall through to default `DENY` and break the frame. Fix = add `m/app/` to the match set + Pest assertion. Fold into Task 9.
- **Pest baseline: 60 failed vs documented ~15.** Task 8b argued this is pre-existing DB-contamination/test-ordering (8b changed only 2 frontend JS files never loaded by Pest; isolated run of a "failing" class passed). Logically sound but NOT independently re-verified — stash/isolation-check before Task 9. Task 8's 15-failure set WAS proven pre-existing via stash-compare (root cause `app.ai_audit_hmac_key` not configured, `AuditChainService.php:53` — local env gap).

## Process note
- Subagent-driven-development: controller must NOT commit on the branch while a background implementer runs (`git add -A` in its commit step). One git-race occurred (plan-doc commit collided with Task 5 amend) — recovered cleanly; thereafter controller doc-commits held to between-task gaps.

---

# SP1 Pass 2 findings (2026-05-22 session 2)

## Resolved gotchas (R3 + R2)

- **Plan vs. reality field names** — R3 plan called for fields the schema didn't have; actual schema is `age, gender, life_expectancy_years, probability_of_death, table_year, table_source`. R2 schema came from the plan and was clean. **Lesson:** read the actual model + migration FIRST before writing the store. Carry into R1.
- **Per-entity `permission:admin.<entity>`** — plan called for new permission migrations per entity. CSJ's standing decision: use existing `permission:admin.access` and skip per-entity perms. Done for R3 and R2; apply to R1 by default.
- **DATETIME canonicalisation in `Store::read()` override** — Carbon `toArray()` serialises datetimes to ISO 8601, which doesn't round-trip through MySQL `DATETIME` columns on a partial-merge update. The fix is re-emit as `Y-m-d H:i:s` in `read()`. R4 needed this for DATE columns too. **Carry into R1** if any tax-config field is DATETIME.
- **JSON float serialisation strips trailing `.0`** — `19.0` becomes `19` in JSON, breaks `assertJsonPath('data.x', 19.0)`. Use non-integer test values (`19.5`).
- **PostToolUse Pint hook removes "unused" imports between Edit calls** — added `use App\Http\Controllers\Api\Admin\CurrencyRateController;` in edit 1, hook reformatted and dropped it before edit 2 added the route references using it. Diagnostic was `Target class [CurrencyRateController] does not exist`. **Fix:** add use + first reference in the same Edit call (or batch both into one tool turn), or re-add after the second edit. Same risk for any new controller import in R1.

## R3 / R2 / R4 lock-down pattern verified

- Boundary final state for each of R4, R3, R2: `[Store, Factory]` only.
- Controller, Resource, Seeder all stop importing the model:
  - Controller uses `$store->findEloquent()` — return type declared in the store, so no model import needed.
  - Resource extends `JsonResource` and accesses dynamic properties on the wrapped model — no class-level import.
  - Seeder uses `app(Store::class)` + `findByX()` helpers + `$store->create/update` with `IngestSource::SEEDER`.
- R5 lock-down is a pure assertion tightening — no code changes, just shrink the allowlist. **R1.5 should be the same.**

## Open / to verify next session

- **CLAUDE.md metrics drift** — 5 counts diverged. Refresh during a routine pass.
- **csjones R2 deploy** — server still on R3 head; the deploy needs `migrate --force` (new `currency_rates` table) + `db:seed --class=CurrencyRatesSeeder --force`. Full procedure in `May/May22Updates/deploy-2026-05-22-r3-r2.md`.
- **Tech-debt-session deferred** for this session (context budget). Low risk because pattern was a tight mirror — but worth running after R1 to catch any drift across the three reference-data tracks.

## Session 3 (2026-05-22) — R1 findings

### Plan-vs-reality reconciliations
- **`getModel()` was dead code** — the plan implicitly assumed `TaxConfigService` exposes the model via `getModel()` for "relationships". Greppable as a public method on the service, but `grep -rn '->getModel()\b\|getModel(): ?TaxConfiguration'` returns ZERO callers across `app/` and `tests/`. Removing it during R1.4 closed the last `TaxConfiguration` type reference in the service. **Lesson**: a method advertised "for X" is not the same as actually used for X. Grep before assuming public API needs preserving.
- **`getCalculations()` is hardcoded UK tax bands** — 125-line method in `TaxSettingsController` returning literal display strings ("£0 - £12,570 (0%)", "£325,000 (transferable between spouses)"). Pre-existing; R1.2 rewrote the controller but preserved this method verbatim per scope discipline. **Surfaced as W1 in `tech-debt-report.md`** — natural bundle with R1.5.
- **Audit FK cascades on delete** — `tax_configuration_audits.tax_configuration_id` is `ON DELETE CASCADE` at the DB level. So writing a `deleted` audit row would be immediately cascade-removed. Either accept no delete-audit trail, or migrate the FK to `ON DELETE SET NULL` with a nullable column. R1.4 chose the former (matches pre-store behaviour); flagged for R1.5 if B2 audit cares.
- **Seeder `setActive` writes one extra audit row per re-seed** — for an admin-only operation; aligns with controller audit semantics. Acceptable noise.

### R3 regression caught at session start
- **`ComprehensiveEstatePlanService.php`** had a missing `use App\Services\Stores\ActuarialLifeTableStore;` import after PR #356 (R3.3). PHP was resolving the type-hinted constructor dep to `App\Services\Estate\ActuarialLifeTableStore` (the current namespace) which doesn't exist. Container resolution crashed any code path that touched `RecommendationCacheObserver` — including `db:seed`. Hotfixed via direct push to `dev` as `3506d70`.
- **Lesson** — Pint reformatted file imports during multi-edit sessions can mask missing-import bugs that only fail at runtime via reflection-driven container resolution. Worth a follow-up Pest test that explicitly resolves every Estate service through the container to catch this class of regression.

### TaxConfig store memo
- **Two-level cache** between `TaxConfigService` (request-scoped `$config` array) and `TaxConfigStore` (instance-scoped `$activeMemo`) created a staleness bug: when a test mutated `tax_configurations.is_active` out-of-band and called `$service->clearCache()`, the service emptied its cache but the store's memo persisted, returning the stale value on the next `loadActiveConfig()` call. **Fix**: `TaxConfigStore::forgetActive()` is now public; `TaxConfigService::clearCache()` calls both. **Lesson** — when a consumer holds its own cache on top of a memoised store, the consumer's cache-clear must propagate to the store.

### Boundary lock-down pattern (now verified 4×)
- R4 → R3 → R2 → R1 all locked at `[Store, Factory]` for greenfield entities, or `[Store, AuditModel, Factory]` for entities with a model-on-model audit relation (R1 is the only one with this — `TaxConfigurationAudit::belongsTo(TaxConfiguration::class)`). Pattern is now stable.
- `TaxConfigService` was the unique R1 challenge — it has a typed property + typed return on `getModel()`, so just removing `TaxConfiguration::` static calls wasn't enough. Removing the dead `getModel()` method + its property finished the migration.

## 2026-06-11 session 4 — savetax campaign E2E findings
- The campaign's two memory systems can desync: `users.funnel_answers` (greetings, section skips) vs profile routing columns (`household_calculation_mode`). Any funnel answer that maps cleanly to a routing column needs an explicit `FunnelAnswersMapper` line — there's no automatic inference (PR #529 root cause).
- `create_investment_account` silently dropped any user-stated fact without a schema field (the £800 dividends case) — when iterating on captures, diff the recorded `tool_calls` in `ai_messages` against the user's verbatim message to spot dropped facts (PR #531).
- `buildCaptureAck` is a state-keyed table covering only base states; campaign grouped_extract states need explicit entries — delegated turns ack via LLM, grouped_extract turns don't.
- csjones `php artisan cache:clear` invalidates live user tokens — every deploy needs re-login in browser tests.

## 2026-07-15 — Native Swift programme Package 1

- The primary checkout can show the Save Tax branch while programme work remains correctly isolated in a separate worktree. Every programme command must set `workdir=/private/tmp/fynla-freemium-economic-api-readiness`; never switch `/Users/CSJ/Desktop/fynla` away from Save Tax.
- Registration checkout intent is a paid-intent contract, not a general tier selector. `free` must be rejected by registration validation, and public Free calls to action must use plain `/register` rather than `plan=free`.
- Checkout routing must be derived from the verified `PendingRegistration`, never from the verification request or current query string. The resulting user still starts Free and no `Subscription` is created before payment.
- The historical `.agents/skills` files are absent from the current checkout, but their last committed copies are recoverable from commit `ff5520b` when a named repository skill must be followed.
- Session tech-debt audit found no critical blockers. Deferred items are ownership-share duplication and orchestration length in `BalanceHistoryService`, plus the broad collection responsibility in `AdviserExportPackService`.
- All approved current plans are now canonical under `codex/plans/`: three programme plans and seven iOS package plans. Older plans found elsewhere remain historical references rather than missing current inputs.
- Task 7's earlier CI failures were real whole-PR drift: seeded canonical tier rows made duplicate fixture creates fail, Free savings limits still expected the retired value of three, Premium-only estate/document tests used Free users, and whole-range frontend/policy lint exposed stale Vue and mobile code. The fixtures/expectations and lint errors are corrected in PR #622; final run `29463239284` is fully green.
- The settled dev-server fact remains zero current paid subscriptions. Local fixture drift was separate: one synthetic admin subscription and six legacy trial-shaped rows were normalised/retired, then the database was migrated through Tasks 1–10 and reseeded.
- `ConvertTrialUsersToFree` originally soft-deleted converted subscriptions without clearing trial-only fields/status, so the Task 12 audit still counted historical shape. Task 10 now normalises the row to `expired`, clears trial dates and retention countdown, then soft-deletes it; its regression test locks the audit-safe result.
- Task 12's plan gate is intentionally cross-environment. A local green audit does not authorise schema removal until the same exact command exits 0 on csjones and production and evidence is saved. PR #625 must be merged/deployed before those hosts can satisfy the command contract.
- The canonical status response key is now `subscription_status`. The retired `/api/payment/trial-status` endpoint is an authenticated 404 tombstone because an unregistered API GET otherwise falls through to the web single-page-app catchall and incorrectly returns 200.
- Task 12 migration-test cleanup must restore both `000000_collapse_tier_identity_to_free_premium` and `000001_support_unbounded_premium_quotas`; restoring only `000000` leaks the temporary 90-day Premium snapshot window into later tests.
- Payment finalisation replay tests must freeze time at second precision. The settlement path recomputes `now()->addMonth()`, so an unfrozen assertion can cross a second boundary and fail despite correct idempotent effects.
- Task 12 is locally migrated and green in draft PR #627, but this does not satisfy the cross-environment gate. No remote migration is authorised until the exact audit is green and saved on both csjones and production.
- Task 13 now has a single repository source of truth at `codex/plans/canonical/01-freemium-economic-contract.md`. The implemented signup spec and design guide point to it, while older commercial designs are visibly historical rather than silently competing contracts.
- The corresponding vault documents remain materially stale about trials and former paid tiers. They have been inventoried for a gated update, not edited ahead of the Task 12 cross-environment audit.
