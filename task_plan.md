# Task Plan — seeded 2026-05-19; SP3 done 2026-05-22 sess 1; SP1 Pass 2 active 2026-05-22 sess 2; R1 track shipped 2026-05-22 sess 3

> Authoritative SP3 plan: `docs/superpowers/plans/2026-05-19-sub-project-3-mobile-iframe-scaffold.md` (DONE, merged via PR #342 on 2026-05-22).
> Authoritative SP1 Pass 2 plan: `docs/superpowers/plans/2026-05-22-sub-project-1-pass-2-reference-data.md` (active).

## Current phase
Native Swift programme, Package 1 economic/API readiness. Tasks 1–11 are checkpointed across PRs #622–#626. Task 12 is active but paused before schema removal pending exact read-only audit evidence from csjones and production. Package 2/Xcode work remains blocked until Package 1 is fully green.

## Phases — Native Swift programme (active)
- [x] Package 1 Task 1 — two-tier identity and payment cutover
- [x] Package 1 Task 2 — approved Free/Premium economic matrix
- [x] Package 1 Task 3 — Premium balance history and adviser export on web and `/m`
- [x] Package 1 Task 4 — canonical subscription and entitlement contract
- [x] Package 1 Task 5 — non-entitling pending checkout state and trial-remnant audit
- [x] Package 1 Task 6 — approved Free/Premium public pricing contract
- [x] Package 1 Task 7 — preserve Premium checkout intent through registration (#622)
- [x] Package 1 Task 8 — upgrade and limit guidance (#623)
- [x] Package 1 Task 9 — canonical subscription UI (#624)
- [x] Package 1 Task 10 — pure-freemium lifecycle semantics (#625)
- [x] Package 1 Task 11 — obsolete trial-copy removal (#626)
- [ ] Package 1 Task 12 — data-gated trial schema and compatibility removal; local gate green, remote audit evidence pending
- [ ] Package 1 Task 13 and final verification gates
- [ ] Package 2 — native SwiftUI foundation; blocked on Package 1 completion

## Phases — SP3 (DONE)
- [x] Brainstorm → spec → implementation plan (committed)
- [x] Task 1 — isolated mobile Vite build pipeline
- [x] Task 2 — `/m` host + `/m/app` routes & Blades
- [x] Task 3 — scoped SAMEORIGIN frame headers
- [x] Task 4 — phone-UA redirect middleware
- [x] Task 5 — Login/Verify/Dashboard scaffold screens
- [x] Task 6 — Capacitor repoint
- [x] Task 7 — two-env deploy wiring
- [x] Task 8 — legacy `resources/js/mobile/` retirement
- [x] Task 8b — residual `/m/*` nav cleanup (reviews completed 2026-05-22 session 1)
- [x] Task 9 — Playwright E2E + `resources/mobile/README.md` + spec §5.3 cookie→Bearer fix + PR `iFrames`→`dev` (merged via PR #342 with mid-merge follow-ups in #343–#345)

## Phases — SP1 Pass 2 (active, 22 of 26 PRs done)
- [x] PR 0 — shared `ReferenceDataStore` base + `ReferenceDataUpdated` event (#346)
- [x] Plan doc (#347)
- [x] R4 × 5 — SavingsMarketRate (#348–#352)
- [x] R3 × 5 — ActuarialLifeTable (#354–#358)
- [x] R2 × 5 — CurrencyRate (#359–#363)
- [x] R1.1 — `TaxConfigStore` facade + arch boundary (#364)
- [x] R1.2 — `TaxSettingsController` writes routed via store (#365)
- [x] R1.3 — `TaxConfigurationSeeder` writes via store (#366)
- [x] R1.4 — `TaxConfigService` internal reads via store; dead `getModel()` removed (#367)
- [x] R1.6 — Boundary LOCKED at `[TaxConfigStore, TaxConfigurationAudit, TaxConfigurationFactory]` (#368)
- [x] Hotfix `3506d70` — missing `use App\Services\Stores\ActuarialLifeTableStore;` import in `ComprehensiveEstatePlanService` (R3 PR #356 regression)
- [x] csjones deploy — R1 + R2 + hotfix combined, HEAD `d3e1cf6`, smoke green
- [ ] R1.0 — Tax Config B2 browser-interactive audit (BLOCKED on CSJ at browser)
- [ ] R1.5 — B2 admin-edit gap fix (DEPENDS ON R1.0; natural place to also fix W1 tech-debt — `getCalculations()` hardcoded values)
- [ ] Final pass-wide review
- [ ] `finishing-a-development-branch` (per superpowers skill)

## Decisions log
- SP3 = scaffolding only; redesigned mobile UI is future work; scaffold screens disposable.
- Architecture: same-origin iframe, same repo, separate Vite build `resources/mobile/`→`public/m-build/`.
- Desktop/tablet unchanged; phones (web+native) → `/m`; native iOS not a live concern (scaffold acceptable).
- Task 8 scope expanded with CSJ approval: relocate `OfflineBanner` to `components/Common/`; clean dead `app.js`/`auth.js` refs.
- Task 8b cleanup of 4 inert native-guarded `/m/*` nav refs chosen by CSJ over deferring.

### SP1 Pass 2 decisions (2026-05-22)
- **Inline-only execution** for all Pass 2 PRs after the subagent-truncation pattern emerged on R4.1 / R4.2. Honoured across R3 and R2 — no implementer subagents dispatched.
- **`permission:admin.access` route gate** for every admin CRUD endpoint — skip per-entity permission migrations the plan calls for. Applied to R4.2, R3.2, R2.3; carry into R1.2.
- **Lock-down final state is `[Store, Factory]`** for every reference-data entity. Controller, Resource, Seeder drop off because they don't directly import the model after the migrations.
- **csjones deploys** of R4 (session 1 mid-afternoon) and R3 (session 2 mid-session) verified via curl smoke. R2 deploy deferred to next session — procedure in `May/May22Updates/deploy-2026-05-22-r3-r2.md`.

### Session 3 (2026-05-22) decisions
- **`getModel()` removal in R1.4** — zero callers across app/ and tests/ (verified by grep). The method was advertised "for relationships" but was genuinely dead. Removing closed the last `TaxConfiguration` type reference in `TaxConfigService` and unblocked the boundary lock-down without needing a follow-up PR.
- **R1.6 is docblock-only** — R1.4 already shrunk the allowlist to its 3 permanent entries (`TaxConfigStore`, `TaxConfigurationAudit` belongs-to relation, `TaxConfigurationFactory` fixtures). R1.6 only updates the docblock language to "LOCKED" pattern matching #352 / #358 / #363.
- **Delete-audit row deferred** — `tax_configuration_audits.tax_configuration_id` FK cascades on delete at the DB level, so writing a `deleted` audit row would be immediately cascade-removed. Preserving deletion audit trail requires a schema change (FK → `ON DELETE SET NULL` + nullable column). Out of scope for R1; revisit in R1.5 if B2 audit identifies it as a gap.
- **`Cache::flush()` stays at controller layer**, not in store. Keeps the store HTTP/cache-agnostic.
- **`setActive` from seeder writes one extra `activated` audit row per re-seed** — accepted noise for an admin-only operation; matches the controller's audit semantics.
- **csjones deploy of R1 + R2 + hotfix combined** — single deploy covered all 12 commits since the R3 mid-session deploy. Build + scp + git pull origin dev + migrate (R2 `currency_rates` table) + seed (R2 currency rates, R1.3 path verified via TaxConfigurationSeeder re-run) + cache clears + autoload + optimize. Smoke green.
