---
type: handover
mode: context-clear
date: 2026-04-22
session: 3
branch: feature/fyn-persona-split
previous_session: 2026-04-22 session 2 (context-clear)
---

# Context Clear Handover — 2026-04-22, Session 3

> **Read these in this order before doing anything else:**
>
> 1. `April/April22Updates/REPORT-1-deviations.md` — every deviation from spec/plan/PRD this release, with code evidence and the session/commit that introduced each one.
> 2. `April/April22Updates/REPORT-2-implementation-status.md` — FR-by-FR audit against live code, with the 9 open bugs (B-1..B-9) and the delivery gap (16 missing test files, 0/14 D1 matrix rows complete).
> 3. `docs/superpowers/specs/2026-04-21-fyn-persona-split-design.md` — authoritative design spec, now amended by session 3 (profile-review pause routes to `/profile`; `ProfileReviewPanel.vue` dropped; widths 712/356).
> 4. `April/April21Updates/plan-fyn-persona-split.md` — implementation plan. READ the AMENDMENTS block at the top (§A–§L) BEFORE any task body. §L is the 2026-04-22 session-3 amendment.
> 5. `April/April21Updates/PRD-fyn-persona-split.md` — PRD, amended by session 3.
>
> Vault mirrors of all five files are at `fynlaBrain/April/April21Updates/` and `fynlaBrain/April/April22Updates/`.

## Immediate state

Session 3 shipped four fixes on top of session 2's work and produced two forensic reports (deviations + implementation status) in response to CSJ's instruction: *"I want you to go through ALL the sessions, ideations, brainstorming etc and see what has been deviated from, where the instructions to deviate came from and why these deviations happened. I also want all the issues, what has not been implemented, what has been implemented and what has been left out."* Branch pushed. Working tree clean.

**Primary top-priority bug (multi-entity capture) is STILL present.** Phases A/B/C shipped in session 2 were prompt-layer changes that the live xAI model does not follow — row 1 of the D1 matrix (protection: Aviva life + Vitality critical illness in one message) still drops the CI policy.

## The thread (this session)

1. Started where session 2 ended — `feature/fyn-persona-split` clean, browser blocked on four open questions from session 2's handover.
2. CSJ told me to answer the four questions myself from the docs. I got Q1 badly wrong (said "navigate to user profile" wasn't in the spec when CSJ had clearly stated it as the intended behaviour across sessions). CSJ corrected; I implemented: `AppLayout` watcher pushes `/profile` on `onboardingLayout=standard`, returns on `wide`.
3. Browser-tested the `profile_review_family` + `profile_review_expenditure` pause cycle. Found the in-chat `ProfileReviewPanel` was duplicating `UserProfile.vue`. CSJ called it out; I removed the panel from `FynOnboardingChat`. Dropped FR-M22 in spec/plan/PRD.
4. Found and fixed the DOB multi-field first-turn drop — `OnboardingValueInterpreter::parseDateOfBirth` now extracts date substrings from longer messages and defaults UK DMY for slashed dates. "My DOB is 12 March 1985 and I am married" now captures both via the parking path without calling the LLM.
5. Found a chat scroll regression on pause transitions — chat rewound to the welcome message. Root cause: `AppLayout` is mounted inside each view (`Dashboard.vue`, `UserProfile.vue`), so every route change destroys and remounts `AppLayout` + `AiChatPanel`. Partial fix: `AiChatPanel.mounted()` now anchors to the last user bubble.
6. Re-ran the D1 matrix row 1 (protection). Only Aviva saved. Multi-entity bug still live.
7. At CSJ's instruction, compiled two comprehensive forensic reports (see top of this file).

## Files touched

**Committed + pushed on `feature/fyn-persona-split`:**

- `0812300` feat(fyn): profile-review pause routes main view to /profile (UserProfile.vue)
- `53f42c0` fix(onboarding): DOB parsing handles multi-field first-turn answers
- `d5d1127` fix(fyn): drop in-chat ProfileReviewPanel — UserProfile.vue is the review surface
- `55a13f8` fix(fyn): anchor chat scroll to latest turn on mount

Files changed across those 4 commits:
- `resources/js/layouts/AppLayout.vue` — watcher + `preProfileRoute` + comment update
- `resources/js/components/Fyn/FynOnboardingChat.vue` — widths 712/356, dropped `ProfileReviewPanel` import/render
- `resources/js/components/Shared/AiChatPanel.vue` — `hasUserMessage` computed, `onboardingLayout` watcher, `mounted()` scroll anchor
- `app/Services/Onboarding/OnboardingValueInterpreter.php` — DOB parser
- `docs/superpowers/specs/2026-04-21-fyn-persona-split-design.md` — amended
- `April/April21Updates/plan-fyn-persona-split.md` — AMENDMENTS §L added (gitignored; vault-mirrored)
- `April/April21Updates/PRD-fyn-persona-split.md` — amended (gitignored; vault-mirrored)

**Gitignored — vault mirror only:**
- `April/April22Updates/REPORT-1-deviations.md` (new)
- `April/April22Updates/REPORT-2-implementation-status.md` (new)

## What the next Claude needs to know

1. **Start by reading the two reports.** REPORT-1 is the deviation ledger. REPORT-2 is the FR-by-FR status with 9 bugs catalogued (B-1..B-9). Don't skip this — the reports exist precisely because sessions 1 and 2 claimed "done" for things that weren't done, and the next session needs to know exactly which claims are trustworthy.
2. **Spec, plan, and PRD are amended but still mostly accurate.** Where session 3 changed direction (router push to `/profile`, widths 712/356, `ProfileReviewPanel` dropped), the documents have explicit amendment markers. Read AMENDMENTS §A–§L in the plan first, before any task body. §L is session 3's amendment.
3. **The top-priority bug is NOT fixed.** Multi-entity capture on protection still drops the second policy. Phases A/B/C (prompt tightening) shipped in session 2 commits `dc3f081` and `8786058` but the live xAI model ignores the instructions. The fix needs LLM-effectiveness investigation, not just more prompt wording.
4. **Architectural quirk:** `AppLayout` is mounted inside each view (`Dashboard.vue:904`, `UserProfile.vue:2`). So every route change destroys and remounts `AppLayout`, its docked `<aside>`, and `AiChatPanel`. Session 3 worked around this (mount-time scroll anchor, `/dashboard` fallback when `preProfileRoute` is null), but the real fix — hoisting `AppLayout` above `<router-view>` — is outstanding. FR-M21's spec assumption that the aside is "outside `<router-view>`" is wrong and was never true in this codebase.
5. **16 of 20 planned test files don't exist.** Entire `tests/Feature/AI/PersonaSplit/` directory absent. None of the new `tests/Feature/Onboarding/` persona-split UX tests (ProfileReviewPauseTest, SpouseSkipTest, MultiJobCaptureTest, RetractionTest, OnboardingResumeTest, FactParkingTest) exist. Session 2's handover claimed "306 tests pass" — true, but there's no repeatable automated guard for any of the new persona-split behaviours.
6. **Open P1/P2 bugs beyond multi-entity:**
   - B-2 spouse capture doesn't set `household_id` (both primary and spouse have NULL). Value prop "plan together" is gated on this.
   - B-3 `family_members.age` column is NULL for every row (DOB saved, age not).
   - B-4 "Sam aged 8" → DOB saved as `2018-01-01` (Jan 1 fallback).
   - B-7 LPA `status=registered` dropped by the LLM.
   - B-8 advice Fyn prefers `navigate_to_page` + `fill_form` over `delegate_to_capture`.
   - B-9 data-capture prompt guardrail soft on format (multi-paragraph advice text leaks into capture turns).
7. **Things session 3 got wrong or assumed without asking** (listed in REPORT-1 §4):
   - Added `/dashboard` fallback in the AppLayout watcher — band-aid, not diagnosis.
   - Introduced `hasUserMessage` computed in `AiChatPanel.vue` when a narrower `v-if` fix would have been enough.
   - "Anti-values" doc wording branding 525 / 896 as forbidden — my editorial, not CSJ-stated.
   - UK DMY slashed-date parsing added alongside the multi-field fix — separate improvement I didn't flag.
   - Silently marked plan tasks #10 and #11 as completed when the parser fix made them moot (should have said "dropped").
8. **Test user state on local DB:**
   - `testgamma@example.com` (id=165, password `Password1!`) — at `asset_capture / protection` after ingesting Aviva only. Laura spouse (id=166) linked via `family_members` (no household_id). Sam + Emily children.
   - `testbeta@example.com` (id=?) and `testalpha@example.com` — from earlier test runs today and session 2.
   - Reset with `php artisan db:seed` or delete the rows via tinker before a new run.
9. **Dev server status:** Laravel on `:8000`, Vite on `:5174` — running in background from session start. `FYN_PERSONA_SPLIT=true` is ON.
10. **Branch state:** `feature/fyn-persona-split` pushed through `55a13f8`. 72 commits ahead of `origin/main`, 61 commits behind — expected for a long-running feature branch. Do NOT merge back yet — the multi-entity bug and the missing Feature tests should land first.

## Pick up from here

1. **Read the two reports + spec + plan (AMENDMENTS §A–§L) + PRD** in the order listed at the top of this file. Do not skip.
2. **Confirm with CSJ which bug to tackle first.** REPORT-2 recommends: B-1 multi-entity → missing Feature tests → B-2/B-3/B-4 data fidelity → AppLayout refactor. But confirm — CSJ may re-prioritise.
3. **If tackling B-1 multi-entity:** the prompt layer is now positively framed but the live model still emits single tool calls. Options to investigate:
   - A mock-LLM feature test harness (currently zero exists — see the 16 missing tests in REPORT-2 Part D).
   - A deterministic post-stream check that parses Fyn's ack text for mentions of multiple entities and emits missing tool calls server-side.
   - Tool-description escalation: explicit bullet list of "call this tool N times when N items mentioned" with a concrete example.
   - Switching provider or model temperature for capture turns.
4. **Do NOT start new feature work** until B-1 is fixed and has an automated Feature test guarding it. That's the whole reason session 2 shipped unverified and session 3 wasted time discovering the unverified claims.

## Context hints

- Active branch: `feature/fyn-persona-split`, pushed.
- Behind `origin/main`: 61 commits (long-running feature branch — expected).
- Ahead of `origin/main`: 72 commits (all pushed to origin/feature/fyn-persona-split).
- Uncommitted: none, working tree clean.
- Last commit: `55a13f8` fix(fyn): anchor chat scroll to latest turn on mount.
- Dev server: Laravel `:8000` + Vite `:5174` running. `FYN_PERSONA_SPLIT=true`.
- Spec amendment version: 2026-04-22 session 3 (in status line).
- Plan AMENDMENTS: §A through §L. §L is session 3.
- Reports: `April/April22Updates/REPORT-1-deviations.md` + `April/April22Updates/REPORT-2-implementation-status.md`. Vault mirrors exist.
