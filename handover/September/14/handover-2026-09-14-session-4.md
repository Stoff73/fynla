---
type: handover
mode: session-end
date: 2026-09-14
session: 4
repo: fynla
branch: dev
---

# Session Handover — 2026-09-14, Session 4

## Where things stand

Twelve mapping bugs were fixed today on eleven feature branches, each verified live in Playwright on the surface it touches, and all are open as PRs to `dev` (#829 to #839). **Nothing is merged.** CSJ's instruction at 21:12 BST: rebase as needed, merge in order so nothing is lost, then PR to prod (`/release`, which only CSJ can invoke), and make sure the root `bugsFixed.md` ledger covers every fix and every PR and lands on dev, on main and in the local checkout. The session ended before any merge started. Local checkout is clean on `dev` at `9d4476c2d` plus this handover; the fixes live only on their branches.

## Priorities for the next session

1. **Merge the PRs to dev, in this order, rebasing each onto dev after the previous merge.** Every fix branch edits `September/September14Updates/mappingBugs2026-09-14.md` on adjacent lines (the decisions register rows and the entries), so the second merge onward will conflict — resolve by keeping both sides (all rows and all status text), never by dropping a branch's edit. Order: #829 (MB-27/47) → #832 (MB-56/57/58, **stacked on #829**: after #829 merges, rebase #832 onto dev and its diff shrinks to one commit) → #830 (MB-48) → #831 (MB-24) → #834 (MB-18/19/20) → #835 (MB-50) → #836 (MB-26) → #837 (MB-28) → #838 (MB-33) → #839 (MB-35) → #833 (ledger). Merge with `gh pr merge <N> --merge --admin` per the admin-merge memory, after the csjones gate below. Run the relevant Pest and Vitest families once after all merges rather than per PR (Rule 17; the per-PR runs are recorded in each PR body).
2. **csjones gate before the merges land**, per `feedback_deploy_gate_csjones_before_admin_merge`: check out each feature branch (or, pragmatically with eleven small PRs, the merged dev tip) on csjones, upload BOTH bundles built with `./deploy/csjones-fynla/build.sh` (four PRs change `resources/mobile/`: #831, #834, #835, #836 — `/m` needs `build:mobile`), clear caches (never `optimize`/`route:cache`), and browser-check the flows in the PR bodies. Then put csjones back on dev.
3. **Release dev → main.** CSJ must type `/release`; the Skill tool refuses it. Before that, check what prod actually runs (`CSJTODO.md` § Deploy state: main `e6d4f4a18`). Prod rsync set per `deploy/DEPLOY.md`; no migrations today; corpus unchanged (`fyn-memory/` untouched — verify with `git diff --stat main..dev -- fyn-memory/`).
4. **The ledger.** `bugsFixed.md` exists only on branch `docs-bugs-fixed-log` (#833, two commits: seed + evening rows). It lists all twelve fixes (MB-18, 19, 20, 24, 26, 27, 28, 33, 35, 47, 48, 50, 56, 57, 58) plus MB-54 as partial, with PR numbers. Its rows say "Fixed, PR open" — after merging, change those to "Fixed" and add the dev merge SHAs and the release tag, then make sure the file is on dev and main and pulled into the local checkout root (it will be, once #833 merges and `git pull` runs on dev).
5. **BLOCKED ON CSJ — the decisions register** at the top of the bugs file (unchanged today): MB-23, MB-44, MB-37, MB-45, MB-25, MB-38, MB-54 (cause corrected today: the model refused and the deterministic backstop wrote the degraded row; the backstop now keeps the provider and reads a stated pot; the open question is a Save Tax pot loop for the no-pot case), MB-53, MB-49, and the dead-code and copy groups (MB-01/09/29/30/31, MB-39, MB-41/42, MB-43, MB-08, MB-10, MB-11, MB-06, MB-32, MB-34, MB-40, MB-14/15, MB-21).
6. **Remaining no-decision fixes, after the release:** MB-17 (`/m` lacks two lifecycle switches), MB-22 (privacy toggle under the open Fyn panel at 1440 px), MB-16 (`Registered` listener never fires), MB-46 (corpus prompt hardcodes "£40,000"), MB-51 (four copy strings and the `assets` vs `pensions` key lookup), MB-52 (`/m` collapses multi-paragraph advice; `resources/mobile/utils/fynText.js`), MB-55 (subject to MB-44). Same discipline: one MB per branch, test red first, live on web and `/m`.
7. **Do not resume mapping** (section 03 onwards) until CSJ says so.

## Context to load

- `September/September14Updates/mappingBugs2026-09-14.md` — the register and every entry. NOTE: on `dev` this file still shows today's fixed items as open; the status edits live on the fix branches and merge in with them. Read the merged version after priority 1.
- `handover/September/14/handover-2026-09-14-session-3.md` — the mapping session's handover: test accounts, the Playwright dead ends, the map evidence pointers.
- `.claude/skills/fyn-architecture/SKILL.md` — Rule 20 governs MB-46, MB-51, MB-52; the evening's fixes each named the one mechanism they changed.
- `deploy/DEPLOY.md` — the prod procedure for priority 3.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_check_codebase_before_raising_bugs.md` — written today after CSJ's correction; read before raising or attributing any new bug.

## Completed this session

- **MB-27 + MB-47** (#829, `mb-47-fyn-navigation-refresh`): `AiChatPanel.handleNavigation()` refreshes the cached user and profile before every push and fires `fyn-screen-refresh` when the resolved route is unchanged; new `fynScreenRefreshMixin` on investments, pensions, savings and dashboard. Live: verify edit updated in place; three same-page Pension Check verifies; expenditure verify showed £2,750 where the map saw £0.
- **MB-48** (#830): `UserProfileService::spouseIncomeSources()` returns the household figures for a zero-income spouse. Live web and `/m`, user 90.
- **MB-24** (#831): `/m` `chooseBubble` routes on an `action` flag, not the shared `skip` id. Live `/m`, user 88.
- **MB-56, MB-57, MB-58** (#832, stacked on #829): `OnboardingPromptBuilder::WALK_PROFILE_SCOPE` enforced at dispatch by `HasAiChat::onboardingProfileScopeError()` (carried with the unified focus through `FynLoop::stream`); the delegated loop counts landed writes per tool and the gap-fill stays quiet when the model landed at least as many as the extractor found; the occupational extractor keeps a multi-word provider and reads a stated pot; one `DONT_KNOW_TOKENS` vocabulary on the state machine used by `nextFromPensionPots`, the substantive-answer check and the zero-output guard; `statesZeroPot()` exits the loop. Three live Pension Check walks (users 93, 94, 95).
- **MB-18, MB-19, MB-20** (#834): `/m` login gains the authenticator, recovery and restore steps; web `RestoreAccountModal` handles 422 `requires_mfa` from `restore`. Live with user 85 (two-factor), deleted and restored twice through `AccountDeletionService`.
- **MB-50** (#835): `/m` expenditure screen shows the entered figure and the commitments beside the total. Live, user 91.
- **MB-26** (#836): `UserResource.onboarding_fyn_needs_start` is the one decision; `/m` mixin and web dashboard/panel read it. Live web, user 89 got the front door.
- **MB-28** (#837): pre-pause route lives in the `aiChat` store; return leg keys on it. Live, user 96 returned to `/dashboard`.
- **MB-33** (#838): `SaveStepProgressRequest` applies the profile rules for the personal and income steps, filtered to `OnboardingService::PERSONAL_INFO_FIELDS` / `INCOME_FIELDS`. Live through the wizard, user 96.
- **MB-35** (#839): British "Dependants", "Civil partnership" option. Live, user 96.
- **Ledger** (#833): `bugsFixed.md` at the repo root, all twelve fixes plus MB-54 partial.
- Register corrections: MB-54's cause (backstop after a refusal, not the model's write) in the register and in `docs/app-map/02b-campaigns.md` § 2.5 and § 3; MB-58's cause (backstop, not a second model call).
- Memory written: `feedback_check_codebase_before_raising_bugs.md`, indexed in `MEMORY.md`.

## Verification state

- Each PR body records its own test runs. Notable: scoped Pest run (onboarding, AI, retirement, stores) 2240 passed at the #832 tree; full web + mobile Vitest 510 passed at the #834 tree; every new test was red before its fix.
- Live: every fix driven in Playwright on local web and, where it applies, on the rebuilt local `/m` bundle. Not driven on csjones or prod. Native not touched.
- Two first drafts failed live and were corrected before the PR opened, both reproduced in tests first: MB-28 (route stored in component data that the remount destroys) and MB-33 (email uniqueness rule under the `data.` prefix → 500).
- The MB-26 live check for a second user (88) failed only because that branch was cut from dev without the MB-26 commit; the API showed the flag missing on that branch. Not a defect; a consequence of unmerged stacked work.
- The full Pest suite was NOT run today; dev's Quality Gate was already red on two pre-existing Unit/Feature failures (`CSJTODO.md` § Known issues). The `tech-debt-session` pass was NOT run (CSJ ended the session); see Tech debt deferred.

## Decisions and dead ends

- **CSJ (18:09–18:33):** "should have been fixed, check" means find the existing guard and why the live path bypasses it — never raise a bug from the chat and database alone. MB-58 and MB-54 had been misattributed to the model; both were the deterministic occupational backstop. Recorded as a memory.
- **CSJ (18:33):** MB-56/57/58 jump the queue; fixed and PR'd.
- **CSJ (19:33):** `bugsFixed.md` at the repo root as the running ledger.
- **CSJ (21:05, 21:12):** stop after MB-35; merge in order with rebases; PR to prod; ledger on dev, main and locally.
- **`/release` cannot be invoked by the model** (disable-model-invocation); CSJ types it.
- **Branch discipline used today:** every fix branch off `dev`, one MB per branch, so later branches lack earlier fixes locally; that is why the register will conflict on merge and why #832 was stacked on #829 (it corrects register entries #829 introduced).
- **Backend design facts relied on:** the MFA challenge token is consumed on the first attempt whatever the outcome (`MFAController::validateChallengeToken`), so a wrong code on `/m` returns to sign-in; `current_fund_value` is NOT NULL DEFAULT 0, so a stated £0 pot still reads as "not entered" on re-entry (MB-53 territory; a confirmed-zero flag is the upgrade path, noted in code).
- **MB-26 known edge, unchanged:** a user parked by "Something else" through the message path has a null `onboarding_fyn_context`, so `onboarding_fyn_needs_start` is true and they get the front door again. Keeping `paused_at_step` on that path is MB-23/MB-25 (pending decisions).
- **Dead end:** `browser_wait_for` with text has a 5 s timeout; delegated xAI turns took 30 s to 6.5 min — use time waits and re-read. The level-up celebration dialog on web intercepts clicks — "Keep going" first. A plain `/register` sends a new user to the wizard, not the Fyn walk; `/dashboard?openFyn=journey` starts the walk.
- **Dead end:** Playwright screenshots with a relative filename land in the repo root; use absolute paths under `docs/app-map/screenshots/mb-fixes/`, and recreate that folder after a branch switch (it is tracked only on branches that committed into it).

## Things that will bite you

- Test accounts, all `MapTest2!`: users 90–96 at example.com (`map-camp-w`, `map-camp-m`, `mb47-pc-web`, `mb58-pc-a`, `mb56-pc-b`, `mb56-pc-c`, `mb28-web` with the date suffix `-2026-09-14`), users 85–89 from sessions 1–2. User 85 (`map-tester-…`) has two-factor enabled with recovery codes regenerated today (`MFAService::regenerateRecoveryCodes`; one used). User 92's income is £500 (MB-57 happened to it before the fix). Users 89 and 96 are mid Fyn journey; user 96 also went through the wizard (marital `civil_partnership`).
- Local `.env` still sends real email; codes come from `email_verification_codes` (login) and `pending_registrations.verification_code` (registration).
- Dev servers left running (8000 and Vite 5173); the local `/m` bundle was rebuilt several times today and reflects the last branch built (mb-35's tree, which lacks the mobile changes of the other branches).
- The web Playwright tab is signed in as user 96 on `/onboarding`; the `/m` storage was cleared.
- The workforce ops files (`workforce/ops/log/*`, `workforce/ops/reports/brief-*.md`) remain uncommitted and belong to the daily brief agent; left alone all four sessions.

## Tech debt deferred

The `tech-debt-session` pass was not run. Observed while working:

- `resources/js/components/Shared/AiChatPanel.vue` — `handleRecordView()` still refreshes `auth/fetchUser` on its own before pushing; `handleNavigation()` now does the same plus the profile. One refresh helper would remove the copy (`AiChatPanel.vue:919-928` and the new `handleNavigation`).
- `app/Services/Onboarding/OnboardingChatDirector.php` — the delegated path (~3830) and the verify-edit path (~4238) carry identical `capture_write_result` accounting blocks; only the delegated one counts `$landedWriteTools`.
- `app/Services/Onboarding/OnboardingService.php` — `PERSONAL_INFO_FIELDS` / `INCOME_FIELDS` are declared beside `processPersonalInfo()` / `processIncomeInfo()` but those methods still assign explicit arrays; the three lists must be kept in step by hand.
- `resources/mobile/views/Login.vue` — the six-box code entry is duplicated for the emailed code and the authenticator code (same `digits` state, two templates); a small component would fold them.
- The comment at `OnboardingChatDirector.php:6407` still says `advance_on_answered_question` on the pot state makes "not sure" advance; the actual mechanism is now the shared vocabulary in the zero-output guard.
