---
type: handover
mode: session-end
date: 2026-10-15
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-10-15, Session 1

## Where things stand

The two sessions before this one (13 October afternoon, 14 October morning) ran out of context without handovers. This session pieced together their state, consolidated every open item into a new `todoCurrent/TODO.md` (created 2026-10-15; the old list is archived), and committed everything outstanding.

No code was written for Fynla. fynla.org is still on release ac (main `ed6eb011f`). Dev (`89133ddfb`) and csjones (`db1f47fca`) carry 25 plan tasks walked on csjones and not yet released. T28 is merged and deployed to csjones but was never walked.

CSJ then decided to build a simplified Fynla in a new folder, `/Users/CSJ/Desktop/fynlaSimple`, so this session also made a full restore point of this version.

## Priorities for the next session

The order is `todoCurrent/TODO.md`.

0. **ASK CSJ FIRST — which project is today's work in?** CSJ's last instruction was to start on the simplified version in `fynlaSimple`, and its last open question went unanswered: should I set up its `.env` (a new local database, for example `fynla_simple`), create that database, and run `composer install` and `npm install`?
   - If the work is in fynlaSimple, it needs its own session opened in that folder. Its hooks now point there.
   - Never copy the full Fynla's `.env` into fynlaSimple as-is: a `migrate` would then change the full Fynla's local database.
1. **T28's Dev walk (item 1).** Walk web 1440 and /m 390 as a Free user with a mortgage and a debt. Then tick the plan's Dev walk box.
2. **The `$potentialSaving` production error (item 2).** The retirement plan fails on fynla.org for pensions whose funds charge above 0.5% (`RetirementActionDefinitionService.php:2746`). I put it ahead of the plan order myself because it is live; CSJ can move it.
3. **T29 onward, in list order (items 3 to 34).**
4. **Decisions waiting on CSJ:** 29 `DECISION (CSJ)` lines, mostly in item 49. The session hook shows them on every prompt. None blocks items 1 to 3.

## Context to load

- `todoCurrent/TODO.md`: the order of work, "Where things stand", "Waiting for release" and what that release needs. Created this session.
- `October/October12Updates/walk-2026-10-11-fix-plan.md`: the evidence log and six boxes for every T-task, and rules R1 to R5. T28's log entry was added this session.
- `/Users/CSJ/FynlaArchive/2026-10-15/README.md`: the restore point, CSJ's decisions for the simplified version, and the things to handle when it replaces fynla.org.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/project_full_fynla_restore_point_2026_10_15.md`: never remove these tags, the `archive/*` branches or `~/FynlaArchive`.

## Completed this session

- **Delta of the list against reality.** The main checkout was 108 commits behind `origin/dev`, so CSJ's editor showed a stale fix plan. I fast-forwarded it, after removing 120 untracked screenshots that were byte-identical to the committed copies.
- **#1341: the new `todoCurrent/TODO.md` (dated 2026-10-15).** The old list was moved to `todoCurrent/archive/TODO-2026-09-30-to-2026-10-14.md`, with a banner. The new list carries:
  - the archive's open items 18 to 49;
  - 35 open "Found (not fixed)" lines from crossed-off items, each with its `archive:NN` line;
  - every open plan task;
  - the unwalked sections E to H of the 9 October regression walk;
  - all the decisions.

  The plan's T28 row got its Code, Test and Dev boxes, with evidence. The `handover-priorities` hook was checked against the new file.
- **#1342: everything uncommitted in the main checkout.** Brett's 11 October walk docx; the removal of the old `brettTest/` screenshots; the Fyn widget plan (`October/October14Updates/`); the 12 October session 2 app-map handover; the ops briefs for 9 to 14 October and their logs. Each file was scanned first, because **the repo is public**: no secrets were found, and the only emails are test accounts already committed.
- **#1343: a Found line under item 1.** The session 5 audit worktree held an uncommitted Child Benefit rounding change. It is now committed and pushed on `fix/session5-audit` (`4b65cde10`), not merged. Its test file: 7 passed.
- **Restore point for the full Fynla:**
  - tags `full-fynla/prod-2026-10-15` (= `ed6eb011f`) and `full-fynla/dev-2026-10-15` (= `89133ddfb`), pushed;
  - laptop-only refs pushed: branch `fix/retirement-lock-tests-carry-forward`, and the two stashes as `archive/stash-2026-08-09-dev-pint-formatting` and `archive/stash-2026-05-27-property-store-pr5a`;
  - `~/FynlaArchive/2026-10-15/`: `fynla-full-repo.bundle` (483 MB, every ref; verified, and a test clone restored both tags and 309 branches), `fynla-local-untracked-files.tar.gz` (110 MB, 23,952 ignored files that cannot be rebuilt, including the local `.env`), and `README.md`.
- **fynlaSimple:**
  - cloned from GitHub dev at `89133ddfb` into `/Users/CSJ/Desktop/fynlaSimple`;
  - the remote was renamed `fynla-full`, with its push URL disabled;
  - local commit `2aef2676f` repoints `.claude/settings.json` and four hooks from `/Users/CSJ/Desktop/fynla` to fynlaSimple (vault paths unchanged).
- **CSJTODO.md:** the header and "Deploy state" were brought up to date (they still read 13 October, release ab).

## Verification state

- Only docs changed in Fynla; no Fynla tests were needed. `ChildBenefitServiceTest` (`fix/session5-audit` worktree): 7 passed.
- Restore: `git bundle verify` reported the bundle okay, and a clone from it held both `full-fynla/*` tags.
- fynlaSimple: `.claude/settings.json` parses, and every hook passes `bash -n`. **Not verified:** the app itself. It has no `.env`, `vendor` or `node_modules`, and was never run.
- Not checked: whether `fix/session5-audit`'s reading of ITEPA 2003 s681C(3)(a) is right. The statute was not read.

## Decisions and dead ends

- **CSJ, 2026-10-15: no production backup.** The simplified Fynla gets a new database, a new server folder (fynla.org is pointed at it) and a new iOS app. The full version's database, `.env`, `storage/app` and build stay untouched in `~/www/fynla.org/public_html/`; to restore, point fynla.org back there. I had started reading release ac's backup script; nothing ran on production.
- **CSJ, 2026-10-15: deploy the simplified version to a new folder.** `deploy/fynla-org/build.sh`, `deploy/DEPLOY.md` and the release skill still name `public_html`, both here and in fynlaSimple. They must change in fynlaSimple before its first deploy.
- **Raised to CSJ for the switch-over (not decided):**
  - the current iOS app on people's phones calls `/api/v1/native/*` on fynla.org and will fail against the simplified backend;
  - existing users and the paying legacy customers are not in the new database;
  - payment webhooks point at fynla.org.
- **The `$potentialSaving` placement** ahead of plan order was my call (a live production error); CSJ has not commented.
- **Vault sync and the tech-debt pass were skipped.** Vault sync dispatches a background agent (CSJ 2026-10-12: no sub-agents; plan R1). R5 rules out tech-debt audits, and only docs changed. This handover is mirrored to the vault directly.

## Things that will bite you

- **The repo is public.** Anything committed is published. Scan before committing briefs, reports or walk documents.
- **FileVault is OFF on this Mac** (`fdesetup status`). `~/FynlaArchive` holds the local `.env` unencrypted. I told CSJ.
- **fynlaSimple carries the full Fynla's `CLAUDE.md` and `todoCurrent/TODO.md`.** A session opened there runs the TODO hook against that copy until CSJ replaces it.
- **Both projects use Vite `:5173` with strictPort,** so only one can run at a time unless fynlaSimple changes its port.
- **Old worktrees:** 32 linger under other sessions' scratchpads and `.claude/worktrees/`. All are clean, and all are merged or pushed (the three `fix/one-figure-*` wip branches are pushed and marked "not to merge as-is" by CSJ). They can be removed; I did not remove them.

## Branch and deploy state

- Branch: `dev` at `89133ddfb` before this handover's PR. Unpushed commits: none.
- csjones: `db1f47fca` (T28). fynla.org: `ed6eb011f` (release ac).
- fynlaSimple: branch `dev`, 1 local commit (`2aef2676f`), no push remote.
