---
type: handover
mode: context-clear
date: 2026-05-11
session: 4
branch: fix/family-members-include-linked-spouse
trigger: context-handover skill (187k tripwire fired after CSJ said "deploy", before multi-step csjones deploy began)
previous_session: 2026-05-11 session 3 (settings restructure PR #272 opened, three deferred items flagged)
---

# Context Clear Handover — 2026-05-11, Session 4

## Immediate state

Just opened **PR #273** (`fix/family-members-include-linked-spouse → dev`) — linked-spouse disappearance fix, browser-verified GREEN end-to-end as John Smith. CSJ then typed "deploy" — meaning deploy PR #273 (and the still-open PR #272 from session 3) to csjones via the established gate. Tripwire fired before deploy started. **No deploy has happened this session.**

## The thread

- **Session opened** with auto-resume from session-3 handover. PR #272 (Settings restructure) was open against `dev`, no reviews, no comments. Session-3's "Pick up from here" defaulted me to step 2(b): start the linked-spouse bug fix on a fresh branch off `dev` since CSJ hadn't given direction on the three deferred items.
- **Switched to `dev`**, branched `fix/family-members-include-linked-spouse`.
- **Root-caused the spouse-disappearance bug**: divergence between `GET /api/user/profile` and `GET /api/user/family-members`. `UserProfileService::getFamilyMembersWithSharing` constructs a virtual spouse record from `User` model when `users.spouse_id` is set but no `family_members` row with `relationship='spouse'` exists. `FamilyMembersController::index` had no such fallback. Component mounts via `/profile` (sees virtual spouse), refetches via `/family-members` after Add (spouse vanishes).
- **Reproduced exactly** on John Smith (id=246, spouse_id=247, zero family_members rows).
- **Fix**: promoted `getFamilyMembersWithSharing` from private to public on `UserProfileService`; delegated `FamilyMembersController::index` to it. Single source of truth. Eliminated 74 lines of duplicate merge logic.
- **Browser-verified GREEN** as john@example.com: pre-fix Jane Smith vanished after adding a child; post-fix Jane Smith persists (rendered with `id: null` and "Account Linked" badge). Network response confirmed `/family-members` GET now returns the virtual spouse alongside real `family_member` rows. Test family_member id=859 force-deleted; John back to baseline.
- **Squash-committed** as `b726477` and pushed. PR #273 opened against `dev`, NOT admin-merged. Awaiting CSJ review.
- **CSJ said "deploy"** — interpreting as "deploy PR #273 + PR #272 to csjones for verification before admin-merge", per `feedback_deploy_gate_csjones_before_admin_merge.md`. Tripwire fired before any deploy work began.

## Files touched this session

```
app/Http/Controllers/Api/FamilyMembersController.php   (+9 -73)   delegated index() to service; injected UserProfileService
app/Services/UserProfile/UserProfileService.php        (+8 -2)    getFamilyMembersWithSharing promoted private→public; docblock
```

Net: 2 files changed, +12 / −74 lines. Standing carry-over (`FCA/`, `FCAsuperchargeApp.md`, `Fynla-Narrative-Memo-Template.docx`, `May/May1Updates/deployFynFix.md`, `campaigns/`, `fyn/`, `personas/`, `prompts/`, `tools/`, `FCA-Supercharged-Sandbox-Application-Draft.md`) remains DELIBERATELY untracked per the ~18-session pattern.

## WIP commit

**None.** Tree is clean. All session-4 work is in commit `b726477` (`fix(family-members): include linked spouse in /api/user/family-members`), already pushed to `origin/fix/family-members-include-linked-spouse`. Next session does NOT need to push or amend anything before deploy.

## PRs open (priority order for deploy)

- **PR #273** — https://github.com/Stoff73/fynla/pull/273
  - Title: `fix(family-members): include linked spouse in /api/user/family-members`
  - Base: `dev` · Head: `fix/family-members-include-linked-spouse`
  - Status: **OPEN, awaiting CSJ review**. NOT admin-merged.
  - This session's fix.
- **PR #272** — https://github.com/Stoff73/fynla/pull/272
  - Title: `feat(settings): unify /settings and /profile into single tabbed hub`
  - Base: `dev` · Head: `userSettingsFixes`
  - Status: **OPEN since session 3, awaiting CSJ review**. NOT admin-merged.
  - 4-phase settings restructure + privacy bug fixes from session 3. Three deferred items flagged (spouse-disappearance bug — NOW FIXED via PR #273, "Choose a Plan" 3-button dedup, family-tab plan-gating regression).

**Both PRs target `dev`.** Neither can be admin-merged until they've been deployed to csjones and browser-verified per `feedback_deploy_gate_csjones_before_admin_merge.md`.

## Open decisions (pending CSJ)

### 1. Order of operations for deploy

Two reasonable paths — next session should pick the most-recent direction-of-travel default unless CSJ says otherwise:

- **Path A (recommended default)**: deploy PR #273 first (smaller, just-verified fix), then PR #272 (larger restructure). Each one through the full gate: `git fetch + git checkout <branch>` on csjones, build `public/build/` locally with `./deploy/csjones-fynla/build.sh`, scp the build, SSH in for cache/optimize, browser-verify on csjones, only THEN admin-merge if CSJ approves.
- **Path B**: merge PR #273 into dev first (dev → csjones deploys via `git pull origin dev`), then test PR #272 on top of merged-#273. Cleaner final state but requires admin-merging #273 without csjones verification first — violates the gate per the feedback memory.

**Default if CSJ doesn't redirect**: Path A. Deploy PR #273 to csjones via checkout-the-feature-branch (no merge yet), verify the spouse-fix browser-test reproduces on csjones, get CSJ sign-off, admin-merge #273. Then repeat for #272.

### 2. The three session-3 deferred items

PR #273 resolves item 1 (linked-spouse bug) — the other two remain open:

- **"Choose a Plan" 3-button dedup on /settings/subscription** — DESIGN CALL. Three CTAs (top-nav banner, side-menu, on-page) all visible during trial.
- **Family-tab plan-gating regression** — student/standard plans can now see Family tab. Old `UserProfile.vue` hid it. Need decision: hide tab vs show with upsell.

Neither blocks the deploy. They're follow-up PRs once #272 lands.

## Pick up from here (auto-continue contract)

Default auto-continue path (in order):

1. **Check PR #273 status.** `gh pr view 273 --json reviewDecision,state,mergeable`. Likely still open with no comments — CSJ said "deploy" before reviewing.
2. **Check PR #272 status.** `gh pr view 272 --json reviewDecision,state,mergeable`. Probably also still open since session 3.
3. **Execute Path A deploy of PR #273 first**:
   - SSH to csjones: `ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co` (if `ssh-add -l` doesn't show `fynlaDev`, add it first — see `reference_csjones_ssh_access.md`)
   - On server: `cd ~/www/csjones.co/fynla-app && git fetch origin && git checkout fix/family-members-include-linked-spouse`
   - Locally: `./deploy/csjones-fynla/build.sh` (builds with `VITE_BASE_PATH=/fynla/build/`, `VITE_ROUTER_BASE=/fynla/`)
   - Upload `public/build/` to `~/www/csjones.co/fynla-app/public/build/` (scp or File Manager). Use the merge-on-upload pattern (`feedback_warn_before_spa_rebuild.md`).
   - On server: `php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && composer dump-autoload -o && php artisan optimize`
   - Browser-verify on `https://csjones.co/fynla/profile?tab=family` as john@example.com — confirm Jane Smith persists after adding a test child, clean up test data.
4. **Repeat steps for PR #272** on csjones with branch `userSettingsFixes` (settings restructure).
5. **If both verify GREEN on csjones**, ask CSJ for admin-merge approval per `feedback_admin_merge_pattern_for_solo_reviewer_prs.md` (gh pr merge <N> --merge --admin).

If CSJ explicitly says "merge first / skip csjones verification" → admin-merge directly. Don't make this call autonomously — the deploy gate is owned by CSJ.

## What the next Claude needs to know

- **PR #273 is independent of PR #272.** They touch entirely different files. PR #273 fixes a backend endpoint divergence; PR #272 restructures the Settings frontend. There's no merge conflict between them.
- **PR #273 resolves session-3 deferred item 1** (linked-spouse disappearance). The PR #272 body still lists it as deferred — that's stale but harmless. Next session may want to comment on #272 noting that #273 fixes that item, so when #272 merges the dedupe-bug list can be updated.
- **Bug repro on John was non-obvious**: John has `spouse_id=247` set but ZERO family_members rows. This is the exact scenario the virtual-spouse fallback was added for. If a different test user has a REAL family_member row with `relationship='spouse'`, the bug won't manifest. Verify John's state pre-test if browser repro doesn't reproduce.
- **Service method semantics changed subtly**: the duplicate-detection on shared spouse children now compares full `name` field (service's `name === name`) instead of `first_name + last_name` (controller's old). Same result for correctly-built names — flagged in PR #273 body.
- **csjones is a real git checkout** since 2026-05-06 session 4. Don't rsync. `git fetch + git checkout <branch>` works for any branch, not just dev. See `feedback_csjones_deploy_via_git_pull.md`.
- **vite is on :5173** (canonical port). Don't drift to :5174 or pkill vite (kills sibling project).
- **Dev server still running locally** at :8000 + :5173. John is still logged in (Playwright session persisted across navigations this session).
- **Vault-sync still deferred** — sessions 6/7/8/9/10/11/12 of May 8 + session 1/2/3/4 of May 11. Carry over to next session-end. Batch via Haiku 4.5 subagent.
- **The fix was verified at the network layer too**: response body of post-Add `/api/user/family-members` GET now includes a virtual spouse record with `id: null` alongside the real new child. Saved this as the canonical evidence in the PR body.

## Branch / deploy state

- **Branch:** `fix/family-members-include-linked-spouse` at `b726477`
- **Behind origin:** 0
- **Ahead of origin:** 0
- **PR open:** **#273** (`fix/family-members-include-linked-spouse → dev`) — awaiting CSJ review, NOT admin-merged
- **Other PR open:** **#272** (`userSettingsFixes → dev`) — still awaiting CSJ review from session 3
- **Dev (csjones.co/fynla):** at `8085e27` (origin/dev) — contains NEITHER PR #273 NOR PR #272 work. Both deploys pending via Path A (feature-branch checkout, not merged-into-dev).
- **Production (fynla.org):** at `9f9d271` (origin/main) — unchanged this session.
- **Local DB state:** John Smith fully reverted to baseline (family_member id=859 force-deleted; back to 0 family_members rows, spouse_id=247 unchanged).
