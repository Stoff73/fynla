# Task Plan — seeded 2026-05-19; SP3 done 2026-05-22 sess 1; SP1 Pass 2 active 2026-05-22 sess 2

> Authoritative SP3 plan: `docs/superpowers/plans/2026-05-19-sub-project-3-mobile-iframe-scaffold.md` (DONE, merged via PR #342 on 2026-05-22).
> Authoritative SP1 Pass 2 plan: `docs/superpowers/plans/2026-05-22-sub-project-1-pass-2-reference-data.md` (active).

## Current phase
SP1 Pass 2 (reference-data canonical stores) — R4 / R3 / R2 tracks all SHIPPED and merged to `dev`. R1 (Tax Config, 6 PRs) and final review remain. **17 of 26 PRs done.**

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

## Phases — SP1 Pass 2 (active, 17 of 26 PRs done)
- [x] PR 0 — shared `ReferenceDataStore` base + `ReferenceDataUpdated` event (#346)
- [x] Plan doc (#347)
- [x] R4 × 5 — SavingsMarketRate (#348–#352)
- [x] R3 × 5 — ActuarialLifeTable (#354–#358)
- [x] R2 × 5 — CurrencyRate (#359–#363)
- [ ] R1.0 — Tax Config B2 browser-interactive audit (BLOCKED on CSJ at browser)
- [ ] R1.1 — TaxConfigurationStore facade + arch boundary
- [ ] R1.2 — Admin CRUD + Vue panel for TaxConfiguration
- [ ] R1.3 — Consumer migration (TaxConfigService et al.)
- [ ] R1.4 — Seeder migration
- [ ] R1.5 — Boundary lock-down
- [ ] R1.6 — TaxConfigurationAudit table integration
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
