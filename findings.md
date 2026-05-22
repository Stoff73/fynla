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
