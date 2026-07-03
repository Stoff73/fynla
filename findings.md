# Findings — seeded 2026-05-19 (SP3 mobile-iframe scaffold)

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

## 2026-06-24 — deploy gotchas (PR #572 SEO/news-bar release)
- **csjones `public/.htaccess` skip-worktree pull-abort.** The file is `git update-index --skip-worktree` AND locally modified (csjones's `/fynla` RewriteBase subdirectory version). When an incoming pull *changes that file in the repo* (PR #572 edited `public/.htaccess`), `git pull` ABORTS with "Your local changes would be overwritten" despite skip-worktree. Fix: backup local → `--no-skip-worktree` → `git checkout -- public/.htaccess` → pull → restore backup → re-`--skip-worktree`. DEPLOY.md:61 documents the skip-worktree but not this abort behaviour. The PR scoped its htaccess change to `public/.htaccess` + `deploy/fynla-org/.htaccess` (prod) only — correctly never touched `deploy/csjones-fynla/.htaccess`, so the prod-only `/public/→fynla.org` 301 must NOT reach csjones.
- **`FYNLA_BASE` class of bug.** Any app-internal URL built in `public/pages/*.js` must use `(window.FYNLA_BASE||'')` (= `/fynla` on csjones, `''` on prod). `index.js:310` hardcoded `/insights/<slug>` → invisible on root-deploy prod, 404 on the subdirectory dev. Editing those static JS files requires bumping the `?v=` token in `index.php` (1-year asset max-age, cache-bust keyed on the query).
- **prod layout reminder.** `public_html/` = Laravel root (artisan, .env); `public_html/public/` = web docroot; served htaccess = `public_html/public/.htaccess` (RewriteBase `/`). There is also a tiny top-level `public_html/.htaccess` (763 B) — leave it. Non-git manual upload; rsync heals page drift (prod `public/pages/` had ~23 files drifted from main).

## 2026-07-03 — WP-5c build gotchas
- **GitHub stacked-PR close-on-base-delete.** Merging a stacked PR with `--delete-branch` CLOSES the dependent PR (base ref gone) instead of retargeting; a closed PR can't be re-based or reopened once its base ref is deleted. Recovery = fresh PR from the same branch. Merge stacks bottom-up, retargeting each PR to dev BEFORE deleting its former base — or keep branches until the whole stack lands (#604 → #606).
- **Pint PostToolUse hook strips just-added imports** when the code referencing them doesn't exist yet in the file — add `use` lines together with (or after) the code that uses them, or the next test run fails "Class not found".
- **/m dashboard level wheel spills a transparent hit-area.** `.md-level--button` is `z-index: 2` and its absolutely-positioned pie SVG overflows ~40px below the button; anything placed under the hero must sit at `z-index: 3+` or taps are swallowed. Found only by a real Playwright click; diagnosed with `elementFromPoint` + computed-style walk.
- **Wills/LPAs are profile rows, not facts.** `Will.has_will` can be false ("I have no will"); LPAs only count when `status='registered'` (drafts exist). Any "X in place" detection must filter, not `exists()`.
- **The tax allowance grid shows the STANDARD Personal Allowance from config, not a per-user tapered figure** — anything needing "did the user escape the taper" needs adjusted net income exposed from the tax engine first (why pa_restored was deferred).
