---
type: handover
mode: session-end
date: 2026-09-30
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-30, Session 1

## Where things stand

- **The release is live on fynla.org.** `main` `c9d789135` has the same tree as `dev` `cf963f0e3`. It carries every PR from 2026-09-29 session 3: the `is_estimate` migration, `ActionHowToSeeder`, the `fyn-memory/` rsync, the config, and both bundles.
- **The spouse-income repair ran with `--force`** (CSJ). Spouse 760: copied row 43 was removed, and their total went from £64,000 to £32,000.
- **The production walk (web at 1440px and `/m`) is done.** Every #99x/#100x item passed. The one exception is "HMRC adds £720", which was not walked.
- **The walk found three web defects**, fixed in **PR #1009**. It is open against `dev`, not merged, and not yet on csjones.
- **It also found one onboarding bug**, which is not fixed (priority 2 below).

## Priorities for the next session

1. **PR #1009: take it through csjones, walk it there, merge to `dev`, then release.**
   - Branch `fix/walk-2026-09-29-profile-dob`, worktree `/Users/CSJ/Desktop/fynla-wt-dob`, commit `3ff98d101`.
   - The date of birth moved back a day on every web Personal Info save. This is live data corruption on production today.
   - **BLOCKED ON CSJ, one decision:** repair dates of birth that have already slipped on production? Candidates are web Personal Info saves of summer birthdays, found through the audit log. Nothing has been touched.
2. **BUG: a question at the onboarding "Which of these do you have?" step is taken as ticking that account.**
   - On fynla.org `/m`, typing "What is the ISA allowance this year?" ticked ISA: the next prompt was "Anything else?" with ISA removed. The question was never answered.
   - CSJ: "Why would adding an account then asking about it, still leading to adding it be intended."
   - Find the director's multi-select free-text path first (Rule 20: every mechanism, web and `/m`). A question must answer and leave the holdings unchanged.
3. **Spouse transfer, still open from session 3:** extend `WalkFormPrefill` so each walk form the transfer answered opens that record as an edit (savings, ISA, investments, pension, other income). Then walk a spouse's full onboarding on web and `/m`. See `CSJTODO.md` NEXT.
4. Create patch notes following the previous formats from Spetember/September28Updates folder, as well as 27,26 and 25.
5. **Retirement how-to batch (26), then investment (17), then estate (12).** CSJ parked these until after the release, which has now happened.
6. **Vault sync for 2026-09-29 session 3 and this session:** run `vault-sync` once.

## Context to load

- `CSJTODO.md` (NEXT section, top three entries): the release marked done, the onboarding-question bug, and PR #1009 with the date-of-birth repair decision.
- `https://github.com/Stoff73/fynla/pull/1009` (body): the cause, the fixes, the tests and the local walk for the three defects.
- `resources/mobile/mixins/onboardingChat.js` and `app/Services/Onboarding/OnboardingChatDirector.php`: where the free text at the multi-select step is interpreted (priority 2).
- `handover/September/29/handover-2026-09-29-session-3.md`: priority 3 (spouse transfer) and the rulings behind it.

## Completed this session

- **Released `dev` → `main` (`c9d789135`)**, using the scratchpad script `release-prod-2026-09-30.sh` that CSJ ran with `!`:
  - migration ran; seeder, corpus check and config cache done;
  - `/`, `/m` and `/pensioncheck/plan` returned 200;
  - no ERROR lines in the log.
- **Spouse repair:** the dry run listed spouse 760 only. CSJ ran `--force`.
- **Walked on fynla.org:**
  - **#1007:** no invented social proof on `/pensioncheck/plan` or `/savetax/plan/v4`.
  - **#1001:** "Your cover" agrees with Coverage gaps (Carter demo: £131,953 life; £3,750 a month income protection).
  - **#1002:** Fyn opens on sign-up, stays open while moving around the app, and a new visit starts collapsed.
  - **#1003:** the partner-employment screen shows "3 of 4", and the partner in the 60% trap gets their own line.
  - **#998/#1000:** Personal Info saves with England and with Scotland born.
  - **#1005:** Try again replaced the cut-off reply, and a relaunch resumes the step.
- **PR #1009:**
  - `User.date_of_birth` cast changed to `date:Y-m-d`; the store no longer uses `toISOString`.
  - "Full-Time" added to Personal Info.
  - `SaveTaxEstimateService::reclaims()` says "reclaims all but £20 of" when rounding leaves income over £100,000 (ITA 2007 s35).
- Walk account 763 was purged from production with the ssh-fynla MCP tool. Its users, tokens and pending rows are all at 0.

## Verification state

- **PR #1009:**
  - `UserProfileControllerTest` + `SaveTaxEstimateServiceTest`: 53 passed.
  - `PersonalInformation.spec.js`: 17 passed.
  - The new DOB test fails on the old cast (`1985-04-30T23:00:00.000000Z`), and the status spec fails without the component change.
  - Walked locally at 1440px on the worktree build (port 8011, now stopped). Saved 1 May 1985 twice and it read 1 May both times. Full-Time shows. The partner card reads "reclaims all but £20".
- **Not verified:**
  - PR #1009 on csjones or on production;
  - "HMRC adds £720" (the non-earner branch of #1003) on production;
  - iOS.

## Decisions and dead ends

- **A new Fyn session never replays old turns.** It greets "Welcome back… Continue / Something else" and resumes the step. That is the design. I wrongly reported #1005 as failing; CSJ corrected it, and it is now in memory (`reference_fyn_new_session_resumes_step_not_turns`).
- **Walks use the Playwright MCP browser, driven as a user:** click, type, screenshot. No evaluate-greps standing in for looking. An offline Chrome extension is never a blocker. See memory `feedback_never_walk_headless_use_chrome`.
- **Production reads (verification codes, purges) go through the `mcp__ssh-fynla__ssh_exec` tool**, never Bash `ssh` (auto mode blocks it) and never a command handed to CSJ. Before verification the code is on `PendingRegistration.verification_code`; after, on `EmailVerificationCode`. Each sign-in issues a new code.
- **The "£0" on the landing Save Tax block** is a count-up animation, not a defect.
- **The date fix is at the source (the model cast), not per client.** iOS and `/m` read `personal_info.date_of_birth`, which `UserProfileService.php:88` already sends as `Y-m-d`, so neither was affected.

## Things that will bite you

- **The local dev database was missing the `is_estimate` migration.** I ran that one file locally; web income saves had been failing with a 500.
- **The worktree needs its own `.env` before `composer dump-autoload`** (`package:discover` fails without it) and a `node_modules` symlink for Vitest.
- **Playwright `F5` goes to the page, not the browser.** To reload, navigate to the URL again.
- **Signed-in users are redirected from `/savetax/plan` to the dashboard.** Sign out first to see the plan page.
- **The release script's labels say 2026-09-30:** the backup folder `~/release-backups/2026-09-30-dev-release/` and the PR title. The release happened on the evening of 2026-09-29.

## Tech debt deferred

- `resources/js/components/UserProfile/PersonalInformation.vue:736` and `resources/js/components/Onboarding/ProfileReviewPanel.vue:86`: two employment-status label maps on web, written separately ("Full-Time" vs "Full-time"). One shared map would stop them drifting.

## Branch and deploy state

- **Branch:** `dev`, with this handover and the `CSJTODO.md` update committed on top of `cf963f0e3`. CSJ's own excalidraw and `workforce/` files are left uncommitted.
- **Worktree:** `/Users/CSJ/Desktop/fynla-wt-dob` (PR #1009, pushed, in flight). Remove it once #1009 merges.
- **Production:** `main` `c9d789135`.
- **csjones:** still on `dev` `ac5deecea`. The release content and #1009 are not on it.
