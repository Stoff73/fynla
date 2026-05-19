---
type: handover
mode: context-clear
date: 2026-05-14
session: 7
branch: dev
previous_session: 2026-05-14 session 6 (context-clear, PRs #300–#301 admin-merged — taxConfig rail extended with stamp duty + student loan + public dispatch; CalculatorsPage migrated; 4 missed importers surfaced)
parallel_session: 2026-05-14 session 5 (worktree `claude/cranky-lewin-6bc99c`, system-overhaul brainstorming sub-project 1 design — independent of this work, see `handover-2026-05-14-session-5-clear.md`)
---

# Context Clear Handover — 2026-05-14, Session 7

## Immediate state

**Sequence A steps 1+2 shipped. PR #302 admin-merged into `dev` at `ccff3a7`. PR #303 OPEN awaiting CSJ iOS verification.** Step 3 (delete `taxConfig.js`) BLOCKED behind #303 — cannot land until the 3 mobile importers it contains are merged, otherwise the Vite build breaks. Working tree clean (only pre-existing untracked carry-overs). CSJTODO updated. Context cleared at CSJ's request before running `./deploy/mobile/build-ios.sh` (CSJ explicitly said "do not build the ios build, not necessary" in the session-end args — that step is deferred to CSJ).

## The thread

- Opened from `handover-2026-05-14-session-6-clear.md` which left Sequence A as the canonical roadmap (steps 1→2→3, with 4 importers surfaced). Phase 5 of session-start auto-resumed with Sequence A (the disciplined sequence) over Sequence B (deploy-first).
- **Step 1 → PR #302.** `store/modules/investment.js` — dropped `ISA_ANNUAL_ALLOWANCE` constant import; `isaAllowancePercentage` getter signature changed from `(state, getters, rootState)` to `(state, getters, rootState, rootGetters)`; allowance now resolves via `rootState.savings?.isaAllowance?.total_allowance || rootGetters['taxConfig/isaAnnualAllowance'] || 20000`. Browser-verified live on `/net-worth/investments` as `john@example.com`: empty-account path returns 0% (taxConfig store hydrated with 20000, math: 0/20000); injected mock ISA account with `isa_subscription_current_year=5000` via `investment/setAccounts` mutation returned **20%** (savings store had `total_allowance=25000` hydrated, so it wins over taxConfig 20000 — fallback order correct). Zero console errors. CI green (logic-guard, GitGuardian, Snyk). Admin-merged at `ccff3a7` per `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`.
- **Step 2 → PR #303 (OPEN).** Three mobile files migrated in one PR:
  - `mobile/views/RetirementDetail.vue` — `ANNUAL_ALLOWANCE` constant → `mapGetters('taxConfig', ['pensionAnnualAllowance'])` + new `annualAllowanceLimit` computed (`pensionAnnualAllowance || 60000`); template fallback chain updated for `Standard allowance` / `Remaining` rows.
  - `mobile/views/EstateDetail.vue` — `IHT_NIL_RATE_BAND` + `IHT_RESIDENCE_NIL_RATE_BAND` constants → `mapGetters('taxConfig', ['ihtNilRateBand', 'ihtResidenceNilRateBand'])` chained through `nrb()` / `rnrb()` computed (`planning?.iht_summary?.current?.nil_rate_band || planning?.user_iht_calculation?.nil_rate_band || this.ihtNilRateBand || 325000`).
  - `mobile/learn/learnTopics.js` — **architectural rework** per session-6 handover's call: static exported array → `getLearnTopics(store)` function building topics with values resolved from the live taxConfig store at render time; `getTopicById(store, topicId)` signature change. The interpolated values (`PENSION_ANNUAL_ALLOWANCE`, `STATE_PENSION_WEEKLY`, `TAX_YEAR`) now resolve through `taxConfig/pensionAnnualAllowance`, `taxConfig/statePensionWeekly`, `taxConfig/activeTaxYear`.
  - **2 callers updated:** `mobile/views/LearnHub.vue` (was `topics: learnTopics` in `data()` → now `topics()` computed returning `getLearnTopics(this.$store)`); `mobile/views/LearnTopicDetail.vue` (was `getTopicById(this.$route.params.topic)` → now `getTopicById(this.$store, this.$route.params.topic)`).
  - **Browser-verified on web dev server** at `localhost:8000` (Vite HMR picked up changes):
    - `/m/learn` → all 8 topic cards render with their (grandfathered) emoji icons, zero console errors.
    - `/m/learn/pensions` → DOM contains `"contribute up to £60,000 per year"` (PENSION_ANNUAL_ALLOWANCE via taxConfig) and `"State Pension is £241.30 per week (2026/27)"` (STATE_PENSION_WEEKLY + TAX_YEAR via taxConfig). All three dynamic interpolations work.
    - `/m/module/retirement` → with `SET_DC_PENSIONS` injecting one pension + `SET_ANNUAL_ALLOWANCE` setting `{ standard_allowance: null, used: 15000 }` to force the fallback path: DOM (via `textContent`, since the accordion was collapsed) contains £60,000 / £15,000 / £45,000 — `60000 - 15000 = 45000` confirms `annualAllowanceLimit` resolves to 60000 via the new mapGetter.
    - `/m/module/estate` → John's live `secondDeathPlanning` payload has `nrb_individual: 325000` and `rnrb_individual: 0` but does NOT have `nil_rate_band` or `residence_nil_rate_band` keys. The fallback chain in `nrb()` and `rnrb()` therefore skips the first two links and lands on `this.ihtNilRateBand=325000` / `this.ihtResidenceNilRateBand=175000` — exactly what the migration was designed to do.
  - **Production iOS-style build clean:** `VITE_BASE_PATH=/ VITE_API_BASE_URL=https://fynla.org VITE_PLATFORM=ios VITE_DISABLE_PWA=true npm run build` completed in 1m 24s with no compile errors and no missing imports.
  - **NOT yet tested on real iOS sim/device.** CSJ MUST run `./deploy/mobile/build-ios.sh` (which also runs `npx cap sync ios`) and exercise the three views in iOS sim / TestFlight / device before this PR can land per CLAUDE.md mobile section + `feedback_ios_testing_checklist.md`.
- **Step 3 BLOCKED.** Cannot open the cleanup PR (`git rm resources/js/constants/taxConfig.js`) until #303 merges to `dev` — deleting the constants file while dev still has 3 mobile importers would fail the Vite build at import-resolution. Once #303 lands, the cleanup PR is a single-commit single-file change.

## Files touched (all merged or pushed to PR branches this session)

No loose ends in the working tree. Everything below is either in `origin/dev` or on a pushed feature branch.

- PR #302 — merge `ccff3a7`, squashed from `3bbb052`. 1 file:
  - modified: `resources/js/store/modules/investment.js` (drop `ISA_ANNUAL_ALLOWANCE` import; add `rootGetters` 4th arg; replace constant with `rootGetters['taxConfig/isaAnnualAllowance'] || 20000` in `isaAllowancePercentage`)
- PR #303 (OPEN) — feature branch `mobile-taxconfig-migration` at `0a5f4a0`. 5 files:
  - modified: `resources/js/mobile/views/RetirementDetail.vue`
  - modified: `resources/js/mobile/views/EstateDetail.vue`
  - rewrote (96%): `resources/js/mobile/learn/learnTopics.js`
  - modified: `resources/js/mobile/views/LearnHub.vue`
  - modified: `resources/js/mobile/views/LearnTopicDetail.vue`
- This session's CSJTODO update — commit `a2bdcd5` on `dev` (pushed)
- This handover commit (about to push)

Pre-existing untracked items at session close (all out of scope, carried from earlier sessions): `FCA-Supercharged-Sandbox-Application-Draft.md`, `FCA/`, `FCAsuperchargeApp.md`, `Fynla-Narrative-Memo-Template.docx`, `May/May1Updates/deployFynFix.md`, `campaigns/`, `fyn/`, `personas/`, `prompts/`, `tools/`.

## What the next Claude needs to know

- **The cleanup PR cannot be opened yet.** Step 3 of Sequence A requires zero importers in `dev`. Right now `dev` has 3 (the three mobile files in PR #303). Open the cleanup PR ONLY after #303 has been admin-merged. If you grep `from '@/constants/taxConfig'` and it returns 0 hits, you're clear.
- **CSJ owns the iOS verification gate for #303.** Don't admin-merge #303 yourself. The test plan in the PR body explicitly defers iOS sim verification to CSJ. Browser dev-server testing of `/m/*` routes exercises the Vue components but NOT the Capacitor wrapper — there are known iOS-only failure modes documented in `mobile_capacitor_patterns.md` (WKWebView MIME types, VITE config rules, biometric flow) that only manifest in the iOS app.
- **The `learnTopics.js` rework changed the export contract.** Old: `export default learnTopics` (array) and `export function getTopicById(topicId)`. New: `export function getLearnTopics(store)` and `export function getTopicById(store, topicId)`. There is no default export anymore. If any other caller exists that imports the default or calls `getTopicById` with one arg, it will break — grep `mobile/learn/learnTopics` to verify only `LearnHub.vue` and `LearnTopicDetail.vue` consume it (those are the two I updated).
- **Emoji in `learnTopics.js` are grandfathered per Rule #16.** The original file used hex-escaped emoji (`💷`) — I converted them to literal characters in the rewrite. Per `feedback_rule_16_grandfather_existing.md`, this is preserving existing grandfathered violations during a refactor, NOT introducing new ones. Don't strip them in a follow-up audit.
- **The `20000` / `60000` / `325000` / `175000` hardcoded fallbacks** in PR #302 and PR #303 are last-resort safety defaults for the scenario where BOTH the savings/retirement/estate domain store AND the taxConfig store are unhydrated (e.g. a render before any API call completes). Same pattern session 4 + 6 used elsewhere. They are not duplicates of the deleted constants file — the constants file had 50+ values; these are 4 specific values inlined at their consumption points.
- **`taxConfig.js` self-describes as "FALLBACK values only".** Leaving it for a few more days while #303 awaits iOS verification is functionally safe — components fall through to it only when the API call fails before render, and that's exactly what it's designed for.
- **csjones is now 13 PRs behind dev** (#291 through #302). #303 will make it 14 once it lands.
- **Vault-sync deferred for the 5th session running** (sessions 2 + 3 + 4 + 6 + 7 all tripwire/wrap-up-led). Next EOD session-end MUST catch up — it's now meaningfully overdue.
- **Stale worktree `cranky-lewin-6bc99c`** still NOT to be removed without CSJ confirming the parallel session-5 system-overhaul design doc is preserved or merged elsewhere.

## Pick up from here

**Immediate next session (after CSJ does the iOS step on #303):**

1. **CSJ-side first:** `./deploy/mobile/build-ios.sh` → open `ios/App/App.xcworkspace` in Xcode → run iOS sim → log in with a seeded test user → navigate the three mobile views:
   - `/m/learn` — confirm all 8 topic cards render
   - `/m/learn/pensions` — confirm £60,000 / £241.30 / 2026/27 interpolate correctly
   - `/m/module/retirement` — open the Annual allowance accordion, confirm Standard allowance and Remaining values render against real backend data
   - `/m/module/estate` — open the Inheritance tax analysis section, confirm Nil-rate band / Residence nil-rate band rows render
   - If green: `gh pr merge 303 --merge --admin`
2. **Once #303 is on dev**, the next Claude opens the **Sequence A step 3 cleanup PR**:
   - Branch off the new `dev` tip
   - `git rm resources/js/constants/taxConfig.js`
   - Pre-commit verification: `grep -rn "from '@/constants/taxConfig'" resources/js/` and `grep -rn "@/constants/taxConfig" resources/js/` must BOTH return zero. (Note: `App.vue:34` has a code-comment reference — that's fine, not an import.)
   - PR title: `chore(taxconfig): remove constants/taxConfig.js — all importers migrated to taxConfig Vuex store`
   - Body should reference PRs #295 → #303 as the migration history.
   - One commit, one file deletion. Browser smoke: visit `/dashboard`, `/calculators` (unauthenticated), `/m/learn/pensions`, `/m/module/retirement` → confirm taxConfig store still hydrates and no missing-import console errors.
   - Admin-merge after CI green.
3. **Then deploy csjones** with the 14 PR bundle (`#291–#303` + cleanup). Smoke must include `/api/tax/config` (auth) + `/api/public/tax-config` (no auth) + mobile dashboard if mobile PRs land in the same csjones push.

**Sequence B (deploy-first) is still on the table** if CSJ defers iOS verification of #303 — deploy csjones with the 13-PR bundle currently in dev (excluding #303), then come back to #303 + cleanup later.

**Other priorities standing from earlier sessions (unchanged):**

- REVIEW §4 High #32 (CoordinatingAgent `forUserOrJoint` scope) — self-contained, ~1 hour
- REVIEW §4 High #33 / Rule #5 — 9 tables need `tenants_in_common`
- Eventually a `dev → main` release PR (now 22 PRs / 72 commits ahead)

## Context hints

- Active branch type: **mainline** (currently on `dev` after the PR #302 merge and the CSJTODO docs commit)
- Behind origin/dev: **0** — pushed last commit (`a2bdcd5`) before tripwire
- Ahead of `main`: 72 commits (21 PRs from sessions 4 + 6 + #302 from this session + handover/CSJTODO commits = 22 PRs total)
- Uncommitted: only this handover itself (about to commit and push), plus the pre-existing untracked notes/folders that are out of scope
- Last commit on dev before this handover: `a2bdcd5` (`docs(csjtodo): record session 7 — PR #302 admin-merged + PR #303 open awaiting iOS verification`)
- PR #303 feature branch tip: `0a5f4a0` on `origin/mobile-taxconfig-migration`
- Test sweep: not run this session — both PRs were small/isolated and browser-tested. Architecture suite is presumed stable from session 6.
- CSJTODO updated: yes (commit `a2bdcd5`, about to be on top of)
- Vault-sync: **deferred for the 5th session running** — needs catch-up next EOD session-end (overdue)
- Parallel session 5 (worktree brainstorm): see `handover-2026-05-14-session-5-clear.md` — worktree must NOT be deleted
- iOS build skipped this session per CSJ's session-end args ("do not build the ios build, not necessary") — the production `npm run build` was run earlier during step-2 verification but `npx cap sync ios` was never invoked
