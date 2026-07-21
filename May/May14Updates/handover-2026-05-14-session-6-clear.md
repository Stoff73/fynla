---
type: handover
mode: context-clear
date: 2026-05-14
session: 6
branch: dev
previous_session: 2026-05-14 session 4 (context-clear, PRs #295–#299 admin-merged — taxConfig migration completed across all authenticated components)
parallel_session: 2026-05-14 session 5 (worktree `claude/cranky-lewin-6bc99c`, system-overhaul brainstorming sub-project 1 design — independent of this work, see `handover-2026-05-14-session-5-clear.md`)
---

# Context Clear Handover — 2026-05-14, Session 6

## Immediate state

**2 PRs (#300, #301) admin-merged into `dev` at `0b3c262`.** Session 4's "Pick up from here" steps 1+2+3 fully shipped — backend payload extension + public dispatch + CalculatorsPage migration. Step 4 (delete `taxConfig.js`) BLOCKED — grep after #301 surfaced 4 importers session 4's audit missed (3 mobile files + 1 Vuex store). Working tree clean (only pre-existing untracked carry-overs). CSJTODO updated.

## The thread

- Session opened from `handover-2026-05-14-session-4-clear.md` recommending steps 1+2+3+4 of the "Pick up from here" roadmap. Phase 5 of session-start auto-resumed.
- **Step 1+2 → PR #300.** Bundled the backend payload extension (Option A from the handover: extend the snapshot, no auth-posture change to `/api/tax/config`) with the public dispatch infra. Extracted `TaxConfigSnapshotService` so both controllers share one projection. Added `Api\Public\TaxConfigController` + `GET /api/public/tax-config` route (throttle:60,1) serving byte-identical payload unauthenticated. PublicLayout's `mounted` hook fires `taxConfig/fetchPublicConfig` only when `isLoaded` is false (guards against double-fetch for logged-in users on public routes). 13 feature tests / 178 assertions / Architecture suite green. Browser-verified end-to-end: unauth `/calculators` → `GET /api/public/tax-config` → store hydrated; auth `/dashboard` → `GET /api/tax/config` → store hydrated, no public call. CI green, admin-merged.
- **Step 3 → PR #301.** CalculatorsPage migration — 25 constants → 25 store getters via `mapGetters('taxConfig', [...])`. Local `higherRateThreshold()` computed dropped (store getter has same name + return shape). Replacement order was longest-pattern-first to avoid prefix collisions (`PERSONAL_ALLOWANCE_TAPER_THRESHOLD` before `PERSONAL_ALLOWANCE`, `NI_BASIC_RATE` before `BASIC_RATE`, etc.). Browser-verified all 4 buyer types × 3 countries (England SDLT home mover £400k → £10,000; England FTB £350k → £2,500 saves £5,000 vs standard; Scotland LBTT £400k → £13,350 across 4 bands; Wales LTT £400k → £10,500; Wales LTT £600k → £25,500 incl. £15,000 in 7.5% non-integer band) + student loan calculator (Plan 2, £45k loan, £30k salary → £20/mo, confirms 9% rate × (£30,000 − £27,295) ÷ 12). Zero console errors. CI green, admin-merged.
- **Step 4 BLOCKED.** Pre-deletion grep revealed 4 more importers of `@/constants/taxConfig` that session 4's audit missed:
  1. `mobile/learn/learnTopics.js` — uses constants in template-literal interpolation at module-load time, needs architectural rework (function-with-store pattern)
  2. `mobile/views/RetirementDetail.vue` — mobile Vue, mapGetters-able but requires iOS build + device test per CLAUDE.md mobile section
  3. `mobile/views/EstateDetail.vue` — same
  4. `store/modules/investment.js` — Vuex store fallback, needs `rootGetters` pattern (easy, web-only)
  Migrating these is a meaningful new chunk of work — mobile changes need `./deploy/mobile/build-ios.sh` + iOS testing per `feedback_ios_testing_checklist.md`. Out of scope for the session-4 handover. Surfaced in CSJTODO with file:line evidence and migration patterns for each.
- **Parallel session 5 noted.** While my work was in flight, CSJ ran a separate brainstorming agent on worktree `cranky-lewin-6bc99c` and committed `handover-2026-05-14-session-5-clear.md` to dev directly at 12:12:55 (between my PR #300 merge and PR #301 commit). That work is the system-overhaul sub-project 1 design doc — independent of mine. Used "Session 6" for this handover to avoid collision.

## Files touched (all merged this session)

No loose ends in the working tree. Everything below is in `origin/dev`.

- PR #300 — merge `31fe6a6`, squashed from `c344809`. 9 files (3 new):
  - new: `app/Services/TaxConfigSnapshotService.php`
  - new: `app/Http/Controllers/Api/Public/TaxConfigController.php`
  - new: `tests/Feature/Api/Public/TaxConfigEndpointTest.php`
  - modified: `app/Http/Controllers/Api/TaxConfigController.php` (slimmed to delegate)
  - modified: `database/seeders/TaxConfigurationSeeder.php` (lbtt + ltt + student_loan)
  - modified: `routes/api.php` (public/tax-config route)
  - modified: `resources/js/store/modules/taxConfig.js` (12 getters + fetchPublicConfig)
  - modified: `resources/js/layouts/PublicLayout.vue` (mounted hook)
  - modified: `tests/Feature/Api/TaxConfigEndpointTest.php` (3 new cases + structure assertion expanded)
- PR #301 — merge `0b3c262`, squashed from `838ec40`. 1 file:
  - modified: `resources/js/views/Public/CalculatorsPage.vue` (25 constant→getter swaps)
- This handover commit (when pushed)
- CSJTODO update commit (when pushed)

Pre-existing untracked items at session close (all out of scope, carried from earlier sessions): `FCA-Supercharged-Sandbox-Application-Draft.md`, `FCA/`, `FCAsuperchargeApp.md`, `Fynla-Narrative-Memo-Template.docx`, `May/May1Updates/deployFynFix.md`, `campaigns/`, `fyn/`, `personas/`, `prompts/`, `tools/`.

## What the next Claude needs to know

- **The taxConfig rail extension is functionally complete.** All authenticated AND unauthenticated touchpoints on the web/desktop path now hydrate from the backend. The remaining 4 importers are mobile (Capacitor iOS) + one internal Vuex fallback — they're true outstanding work, not nits.
- **The handover from session 4 was wrong in one detail.** It claimed `CalculatorsPage` was "the only remaining importer" — `grep -rn "@/constants/taxConfig" resources/js/` after #301 returned 4 more (paths above). When session 4's "Pick up from here" said "step 4: delete the constants file", it was implicitly assuming this audit was complete. It wasn't. Don't trust handover audits — re-grep before any deletion.
- **`taxConfig.js` self-describes as "FALLBACK values only".** Leaving it for now is functionally safe — components and the JS data file fall through to the constants only when the API call fails before render. The rail goal is full removal, but there's no urgency.
- **Mobile work needs the iOS build chain.** Per CLAUDE.md mobile section, ANY change under `resources/js/mobile/` requires `./deploy/mobile/build-ios.sh` (NOT raw `vite build`) + iOS device test. See `feedback_ios_testing_checklist.md`. Don't try to ship a mobile migration without that chain.
- **`learnTopics.js` is non-trivial.** It's a static array built at module-load time with template-literal interpolation:
  ```js
  summary: `You can contribute up to £${PENSION_ANNUAL_ALLOWANCE.toLocaleString()} per year with tax relief.`
  ```
  `mapGetters` won't work here (no Vue component context). The right migration is to convert `learnTopics` from an exported array to an exported function `getLearnTopics(store)` that resolves values on demand from the live store. Then the 1-2 callers need to invoke it with the Vuex store instance. That's a touch invasive — worth its own PR.
- **`store/modules/investment.js` is the easy one.** A single 1-line getter change: add `rootGetters` 4th arg to `isaAllowancePercentage` and fall through `rootState.savings?.isaAllowance?.total_allowance || rootGetters['taxConfig/isaAnnualAllowance'] || 20000`. The "20000" is a last-resort safety default for the case where neither the savings nor taxConfig store has been hydrated. Web-only, no iOS impact, ~5 minute change.
- **csjones is now 11 PRs behind dev** (#291 through #301). The bundle is ready to deploy when CSJ says so. Post-deploy smoke checklist now ALSO needs to verify the new `/api/public/tax-config` route works on csjones.
- **Vault-sync deferred for the 4th session running** (sessions 2 + 3 + 4 + 6 all tripwire/wrap-up-led). Next EOD session-end should catch up.
- **Stale worktree `cranky-lewin-6bc99c`** is no longer "stale" — CSJ's parallel session-5 brainstorm pushed real work there (`docs/superpowers/specs/2026-05-14-module-canonical-store-design.md`, 774 lines). DO NOT remove the worktree without confirming with CSJ that the system-overhaul design doc is preserved or merged elsewhere.

## Pick up from here

The session-4 handover's roadmap (steps 1–4) is **3/4 complete**. Step 4 is blocked by the 4-importer migration. Two reasonable next-session sequences:

**Sequence A (finish the rail completion):**
1. **Migrate `store/modules/investment.js`** — easy 1-line getter change. Web-only PR, no iOS impact.
2. **Migrate the 3 mobile files** — single PR for `learnTopics.js` (architectural rework to `getLearnTopics(store)` function) + `RetirementDetail.vue` + `EstateDetail.vue` (mapGetters swap). MUST run `./deploy/mobile/build-ios.sh` and verify in iOS sim/device for both views. Per `feedback_ios_testing_checklist.md`.
3. **Final cleanup PR — delete `resources/js/constants/taxConfig.js`** once grep returns zero hits for both `from '@/constants/taxConfig'` AND `@/constants/taxConfig` patterns.
4. **Deploy csjones** — bundle now 11+ PRs depending on what (1)–(3) add. Smoke includes both `/api/tax/config` (auth) and `/api/public/tax-config` (no auth) plus mobile dashboard if mobile PRs land.

**Sequence B (defer the rail completion, ship what's already in dev):**
1. **Deploy csjones first** with the 11 PRs already in dev — proves the public dispatch path works in staging.
2. **Then** do the 4-importer migration in a future session.

Either sequence is valid. Sequence A is more disciplined (finish the planned work before deploying); Sequence B gets staging proof faster.

**Other priorities standing from session 4 (unchanged):**
- REVIEW §4 High #32 (CoordinatingAgent `forUserOrJoint` scope) — self-contained, ~1 hour
- REVIEW §4 High #33 / Rule #5 — 9 tables need `tenants_in_common`
- Eventually a `dev → main` release PR (now 21 PRs / 69 commits ahead)

## Context hints

- Active branch type: **mainline** (currently on `dev` after the PR #301 merge)
- Behind origin/dev: **0** — synced after every merge
- Ahead of `main`: 69 commits (audit batch + taxConfig rail + 5 module migration PRs from session 4 + 2 rail-extension PRs from session 6 + handover commits = 21 PRs total)
- Uncommitted: only this handover + CSJTODO update (about to commit), plus pre-existing untracked notes/folders that are out of scope
- Last commit on dev before this handover: `0b3c262` Merge pull request #301
- Test sweep: TaxConfig feature suites (10 + 3 = 13 cases / 178 assertions) + Architecture (95 passed / 416 assertions) all green this session. No broader sweep run — controller refactor was tightly scoped to the snapshot extraction
- CSJTODO updated: yes (this session, about to push)
- Vault sync: **deferred for the 4th session running** — needs catch-up next EOD session-end
- Parallel session 5 (worktree brainstorm): see `handover-2026-05-14-session-5-clear.md` — sub-project 1 of the major Fynla overhaul, design doc complete, ready for `superpowers:writing-plans` invocation when CSJ resumes that worktree
