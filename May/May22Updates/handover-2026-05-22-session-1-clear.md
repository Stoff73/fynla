---
type: handover
mode: context-clear
date: 2026-05-22
session: 1
branch: dev
trigger: context-watch tripwire at ~923k tokens (>97.5% of 800k Fynla budget)
---

# Context Clear Handover — 2026-05-22, Session 1

## Immediate state

R4 track of SP1 Pass 2 (reference-data stores) is **COMPLETE on dev**. `SavingsMarketRate` is now fully behind the canonical store — boundary locked, allowlist = `[Store, Factory]` only. 17 tasks remain in pass 2 (R3 × 5, R1.0–R1.6, R2 × 5, final review). csjones deploy of today's work is **deferred** — CSJ chose "defer until R4 track complete" earlier, then the tripwire fired before we could deploy.

## The thread

Today was a marathon session that did, in order:

1. **Started on SP3 mobile-iframe fallout** — admin-merged PR #342 (SP3 main feature), PR #343 (ProviderSwapLockTest env-coupling fix + CSJTODO refresh), PR #344 (vite.mobile.config.js publicDir bug found during csjones build), PR #345 (subdir-aware iframe + inner router for csjones `/fynla/` prefix — caught when CSJ reported mobile 404).
2. **csjones deploy for SP3** — built locally, scp'd `public/build` (8.6M) + `public/m-build` (100K, after publicDir fix), `git pull origin dev`, cache clears, smoke-tested. All endpoints 200; phone-UA redirect 302→`/fynla/m`→200; iframe loads `/fynla/m/app` correctly post-PR#345.
3. **Investigated SP1 status** — pass 1 (Savings) shipped via PRs #305–#323; SP2 (freemium) shipped 2026-05-19 via #336/#340; **pass 2 (reference data R1–R4) NOT YET STARTED**. Wrote the pass-2 implementation plan (2369 lines) and shipped it as PR #347.
4. **Started SP1 Pass 2 execution** via subagent-driven-development:
   - **PR #346 PR 0** — shared `ReferenceDataStore` abstract base + `ReferenceDataUpdated` event. Clean subagent dispatch.
   - **PR #348 R4.1** — `SavingsMarketRateStore` + arch boundary. Subagent truncated; finished arch test inline.
   - **PR #349 R4.2** — admin CRUD + Vue panel for SavingsMarketRate. Subagent truncated mid-task at ~10 of 15 files; finished backend + entire frontend + Carbon-date round-trip bug fix inline. Reviewer approved.
   - **PRs #350 R4.3, #351 R4.4, #352 R4.5** — RateComparator consumer migration, seeder migration, lock-down. All inline (CSJ switched to inline-only after the truncation pattern).

## Files touched (committed and pushed)

13 PRs merged to `dev` today (#342 → #352). Working tree: clean except for pre-existing untracked (`May/May19Updates/patch-notes-*`, `brettTesting/`, `test-results/`).

`dev` is currently at `951ac9e` (Merge PR #352).

## What the next Claude needs to know

- **csjones deploy of today's SP1 Pass 2 work is pending.** R4 track shipped to `dev` (5 PRs) plus PR 0 (shared infra) and the plan doc PR. csjones is at `418e3ba` (the SP3 merge from earlier today) and is 7 commits behind dev. Per CSJ: deploy AFTER R4 complete — that condition is now met, the tripwire just fired before action.
- **The subagent dispatch pattern keeps truncating** on PRs with >5–6 files. Sonnet model. CSJ explicitly switched to **inline-only execution** for the remaining 17 PRs. Honour that — don't dispatch implementer subagents for R3.2 / R1.2 / R2.3 (the big admin-CRUD PRs).
- **Plan reconciliations to remember when starting R3, R1, R2:**
  - Schema field names may not match the plan's canonical names (e.g. R4 plan said `product_type`/`provider`/`rate_aer`, actual schema was `rate_key`/`label`/`rate`/`tax_year`/`effective_from`). Always read the actual model + migration FIRST.
  - Use existing `permission:admin.access` route gate, NOT new per-entity permissions. SKIP the permission migrations the plan calls for.
  - No `ConfirmDeleteModal` component exists — use `window.confirm()` inline.
  - Form modals inline in the panel (no separate sub-component) — simpler review.
  - FormRequest `authorize()` returns true and defers to the route middleware (not `$user->can('admin.access')` — that uses Laravel gates, not our custom `PermissionService`).
  - Carbon date casts serialise to ISO 8601 in `toArray()`. If the store's update path partial-merges existing+input, override `read()` to canonicalise dates to `Y-m-d` (see `SavingsMarketRateStore::read()` for the pattern).
  - Arch boundary final state should be `[Store, Factory]` only. Add `findEloquent(int $id): ?Model` to the store so the controller doesn't need direct model access for response shaping.
- **PR R1.0 (B2 audit) is still pending and CSJ-interactive.** It needs CSJ at a browser to verify which TaxSettings.vue fields round-trip. Defer until they're ready; not on the critical path until PR R1.5.
- **Vault-sync was SKIPPED this session** (context tripwire — heavy skill). Next end-of-day session-end must run it. Carry items into the vault:
  - The pass-2 plan doc (`docs/superpowers/plans/2026-05-22-sub-project-1-pass-2-reference-data.md`) — already tracked in git, so the next vault-sync will pick it up.
  - This handover, plus tomorrow's handovers.

## Pick up from here

1. **csjones deploy of today's SP1 Pass 2 R4 + PR 0 work.** Local build → upload `public/build` (8.6M) + `public/m-build` (100K) → SSH csjones → `git pull origin dev` → migrate (none new) → cache clear + view clear + route clear + composer autoload + optimize → smoke. The `app.access` admin route is at `/api/admin/savings-market-rates` (GET/POST/PATCH/DELETE); the new admin tab is "Savings Rates" in `AdminPanel.vue`.
2. **Continue SP1 Pass 2 inline.** R3 track is next (5 PRs: R3.1 ActuarialLifeTableStore facade → R3.2 admin CRUD → R3.3 migrate 3 consumers — TrustService + FutureValueCalculator + ComprehensiveEstatePlanService → R3.4 seeder → R3.5 lock-down). Mirror the R4 pattern exactly. Inline, no subagents.
3. **Then R1 (Tax Config — 6 PRs, B2 work).** R1.0 needs CSJ at a browser first.
4. **Then R2 (Currency Rates — 5 PRs, greenfield).**
5. **Then final review + finishing-a-development-branch.**

## Task list state (from TaskList tool)

- ✅ #2 PR 0 — Shared ref-data infra
- ✅ #3 PR R4.1 — SavingsMarketRateStore facade + arch boundary
- ✅ #4 PR R4.2 — Admin CRUD + Vue panel for SavingsMarketRate
- ✅ #5 PR R4.3 — Migrate RateComparator
- ✅ #6 PR R4.4 — Migrate SavingsMarketRatesSeeder
- ✅ #7 PR R4.5 — Lock-down R4 boundary
- ⏳ #1 PR R1.0 — B2 audit (browser-interactive, deferred)
- ⏳ #8–#23 R3 / R1 / R2 PRs — pending
- ⏳ #24 Final review + finishing-a-development-branch

## Known issues / blockers

- **Tripwire fired before vault-sync ran** — next EOD wrap must run it.
- **Subagent truncation pattern** — inline-only is now the chosen path per CSJ.
- **Pre-existing Pest baseline failures:** 1× `CassetteModelProvenanceTest` (documented C1 — deliberately RED awaiting `php artisan eval:record --providers=xai`), 1× unidentified failure (compact-mode rewrote progress on full run today). No NEW failures introduced by today's work.
