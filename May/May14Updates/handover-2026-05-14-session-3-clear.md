---
type: handover
mode: context-clear
date: 2026-05-14
session: 3
branch: dev
previous_session: 2026-05-14 session 2 (context-clear, tripwire at ~230k — PR #292 + PR #293 admin-merged + vault rogue-file resolved)
tripwire: fired at ~229k tokens — session-end ran lean (no vault-sync, no tech-debt re-run; the PR work itself was clean and shipped via review)
---

# Context Clear Handover — 2026-05-14, Session 3

## Immediate state

**PR #294 (`taxconfig-hydrate-from-backend`) admin-merged into `dev` at `c8bac98`.** REVIEW §4 High #28 first-of-three PRs shipped — backend `/api/tax/config` endpoint + Vuex store hydration rail laid. CSJTODO updated and pushed (`3d8179d`). Working tree clean. Tripwire fired at ~229k; ran session-end lean.

## The thread

- Session opened from `handover-2026-05-14-session-2-clear.md` recommending REVIEW §4 High #28 (frontend `taxConfig.js` hydrate from backend) as the next priority — half-day scope.
- Phase 5 of session-start auto-resumed with that recommendation. Investigated current state: discovered `taxConfig.js` was already documented as fallback-only, `taxConfig` Vuex store + `App.vue` boot dispatch already existed, but the store only fetched the tax-year label from `/api/tax-year/current` — no full snapshot endpoint, components still imported scalar constants directly.
- Built the rail in one PR (intentional scope): new `TaxConfigController` returning curated payload via `TaxConfigService`, route `GET /api/tax/config`, store extended with ~40 named getters (`isaAnnualAllowance`, `cgtAnnualAllowance`, `ihtNilRateBand`, etc.), all 3 dispatch sites switched to `fetchConfig`, 7 Pest feature tests (97 assertions). Architecture + sibling tax tests clean.
- Browser-verified end-to-end as `john@example.com` — endpoint fires on boot, Vuex getters return seeded 2026/27 values.
- PR opened, all 3 CI checks green (GitGuardian, logic-guard, Snyk), admin-merged same session per the established solo-reviewer pattern. CSJTODO updated to reflect the merge + the follow-up migration PRs surfaced.
- Tripwire fired at ~229k. CSJ invoked `/session-end context clear`.

## Files touched (uncommitted or recently committed)

All work landed via PR #294 and a follow-up doc commit. No loose ends in the working tree.

- `d79523d` feat(tax): hydrate frontend taxConfig from backend snapshot (squashed into merge `c8bac98`)
- `3d8179d` docs(csjtodo): record session 3 — PR #294 (taxConfig hydrate rail) merged

Files in the merged feature:
- `app/Http/Controllers/Api/TaxConfigController.php` — NEW, 117 lines (curated payload + dot-notation lookups against `TaxConfigService`)
- `routes/api.php` — new `GET tax/config` route + controller import
- `resources/js/store/modules/taxConfig.js` — extended from year-only to full snapshot store; 40+ named getters; `fetchActive` kept as a back-compat alias delegating to `fetchConfig`
- `resources/js/App.vue` — boot dispatch now `taxConfig/fetchConfig`
- `resources/js/store/modules/auth.js` — login `fetchUser` now dispatches `taxConfig/fetchConfig`
- `resources/js/components/Admin/TaxSettings.vue` — admin year-switch dispatch now `taxConfig/fetchConfig`
- `tests/Feature/Api/TaxConfigEndpointTest.php` — NEW, 7 tests / 97 assertions

Pre-existing untracked items at session close (all out of scope — pre-existing from earlier sessions): `FCA-Supercharged-Sandbox-Application-Draft.md`, `FCA/`, `FCAsuperchargeApp.md`, `Fynla-Narrative-Memo-Template.docx`, `May/May1Updates/deployFynFix.md`, `campaigns/`, `fyn/`, `personas/`, `prompts/`, `tools/`.

## What the next Claude needs to know

- **The rail is laid; the trains have not moved.** PR #294 shipped the backend endpoint + Vuex store getters but DID NOT migrate any of the ~30 Vue components that still `import { ISA_ANNUAL_ALLOWANCE, … } from '@/constants/taxConfig'`. That's deliberate — the migration is 4-6 follow-up PRs, one per module. `taxConfig.js` is unchanged.
- **`fetchActive` is now a back-compat alias** that delegates to `fetchConfig`. Anything that still dispatches `taxConfig/fetchActive` will hit the full snapshot endpoint and hydrate everything — no breakage.
- **No cross-request controller cache in `TaxConfigController`.** `TaxConfigService` is already request-scoped, and a longer-lived cache would mask admin-edits to the active config (the existing public `TaxAllowancesController` suffers this 1-hour staleness — separate follow-up). Verified by Pest case "reflects edits to the active config across requests".
- **Curated payload shape, not a dump.** The endpoint returns `{ tax_year, effective_from, effective_to, isa, pension, income_tax, national_insurance, capital_gains_tax, inheritance_tax, dividend_tax, gifting_exemptions, other }` — mirroring what `taxConfig.js` exports. Future expansion (e.g. SDLT/LBTT/LTT band tables) is additive — add nested keys to the controller `buildPayload()` + store getters; no breaking change.
- **State pension weekly is derived** from `pension.state_pension.full_new_state_pension / 52` — the seeder only stores the annual figure. Verified 12547.6 / 52 = 241.3 in browser.
- **csjones is now 4 PRs behind** `dev` (#291 + #292 + #293 + #294). Bundle next deploy. Smoke check should verify `GET /api/tax/config` returns 200 with the seeded snapshot AND that Vuex devtools on the dev site show `taxConfig.config` populated.
- **Vault-sync deferred for the 2nd session in a row** (sessions 2 + 3 both tripwire-led). Next EOD session-end should catch up.
- **Stale worktree `cranky-lewin-6bc99c`** still present (clean, tracking origin/main). Local git is 2.10.1 — use `rm -rf .claude/worktrees/cranky-lewin-6bc99c && git worktree prune` when convenient.

## Pick up from here

1. **Start the component migration — PR #295 (Estate module).** Migrate these 6 files from `import { X } from '@/constants/taxConfig'` to `mapGetters('taxConfig', ['xCamelCased'])`:
   - `resources/js/components/Estate/TrustPlanningStrategy.vue` (uses `IHT_NIL_RATE_BAND`)
   - `resources/js/components/Estate/NRBRNRBTracker.vue` (uses `IHT_NIL_RATE_BAND`, `IHT_RESIDENCE_NIL_RATE_BAND`, `IHT_RNRB_TAPER_THRESHOLD`)
   - `resources/js/components/Estate/GiftForm.vue` (uses `ANNUAL_GIFT_EXEMPTION`)
   - `resources/js/components/Estate/IHTPlanning.vue` (uses `IHT_NIL_RATE_BAND`, `IHT_STANDARD_RATE`, `IHT_REDUCED_RATE`, `ANNUAL_GIFT_EXEMPTION`)
   - `resources/js/components/Estate/GiftingStrategy.vue` (uses `ANNUAL_GIFT_EXEMPTION`)
   - `resources/js/components/Estate/IHTCalculationTable.vue` (uses `IHT_NIL_RATE_BAND`)

   Getter names already exist on the store: `ihtNilRateBand`, `ihtResidenceNilRateBand`, `ihtRnrbTaperThreshold`, `annualGiftExemption`, `ihtStandardRate`, `ihtReducedRate`. Each component change is mechanical: remove the import, add `...mapGetters('taxConfig', [...])` to `computed`, swap usages. Browser-verify the Estate flow after the migration (IHT calculation page, gift form, NRB/RNRB tracker on the dashboard).

2. **Then sequentially: PR #296 Investment, PR #297 Savings, PR #298 Retirement, PR #299 Shared/Dashboard/Insights.** Each PR is ~1-2 hours. CSJTODO has the full file list per module.

3. **Final PR — remove hardcoded fallbacks** from `resources/js/constants/taxConfig.js` once `grep -rn "from '@/constants/taxConfig'" resources/js/` is empty (or empty except for non-tax-year constants like SDLT band shapes if those aren't in the backend snapshot).

4. **Deploy csjones when CSJ says so.** Bundle 4 PRs (#291 + #292 + #293 + #294). Smoke check: log in on csjones, check Network tab shows `GET /fynla/api/tax/config` → 200, then run the Vuex eval from session 3 to confirm getters hydrated.

5. **Eventually:** release PR `dev → main` (now 14 PRs deep — #281 through #294). Smoke notes for the release PR body: PR #294 introduces a new authenticated endpoint + Vuex boot dispatch; no behaviour change to existing endpoints; no DB migration.

## Context hints

- Active branch type: **mainline** (currently on `dev` after the PR #294 merge)
- Behind origin/dev by: **0** — synced after merge + CSJTODO push
- Ahead of `main`: 61 commits (audit batch + taxConfig rail = 14 PRs total)
- Uncommitted: none of my work; only pre-existing untracked notes/folders
- Last commit on dev: `3d8179d` docs(csjtodo): record session 3 — PR #294 (taxConfig hydrate rail) merged
- Test sweep: 7 new feature + 51 sibling tax + 95 Architecture all green this session
- CSJTODO updated: yes (this session, pushed)
- Vault sync: **deferred for the 2nd session running** — tripwire fired before vault-sync could run. Next EOD session-end should catch up.
