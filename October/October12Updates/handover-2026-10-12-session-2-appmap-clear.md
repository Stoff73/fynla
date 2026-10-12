---
type: handover
mode: context-clear
date: 2026-10-12
session: 2
branch: docs/app-map-refresh-2026-10-12
trigger: context-handover skill (CSJ: "there is an agent working, so can we write the handover now please and stop")
---

# Context Clear Handover — 2026-10-12, Session 2 (app-map refresh)

## Immediate state

The five existing application maps and the MB-01 to MB-58 log are being refreshed to `f00076906`. MB recheck, map 17, map 01 and the overview are done, committed and pushed on `docs/app-map-refresh-2026-10-12`. Maps 02a (onboarding) and 02b (campaigns) are not started. I had just asked how to tackle them when CSJ said to stop and write this handover.

## The thread

- CSJ asked to find "the mappings I was doing" and continue them. There were two candidates: the app-map programme (`docs/app-map/`, `app-map` skill, started 2026-09-14) and `chrisMapping/` (agentsMap, techStack, solutions, AngAnswers; stamped `a7c13608a`, 2026-09-22). **CSJ chose the app-map programme**, specifically: bring the five existing maps up to date and recheck the MB entries, then continue with section 03 Dashboard.
- Another Claude instance is working in the main checkout on `dev` (CSJ said so). All my work is in a scratchpad worktree on branch `docs/app-map-refresh-2026-10-12`, cut from `origin/dev` `f00076906`. Nothing was committed in the main checkout, and `todoCurrent/TODO.md` was **not** edited, because the other agent has it modified. This job is CSJ-named and is not a TODO item.
- **MB recheck (done).** A section "Recheck 2026-10-12" is appended to `September/September14Updates/mappingBugs2026-09-14.md`, and the register's "Decided" cells are updated.
  - MB-07 is **withdrawn**: it was wrong when raised. `RiskRecalculationObserver` is the base class of seven registered risk observers, which dispatch the job.
  - MB-23 and MB-54 are fixed (`fe1fddee7`, `347fbe88b`). MB-45 is fixed on web and `/m` (`19fbd433a`); the iOS map still drifts.
  - MB-38, MB-40 and MB-51 are partly fixed or narrowed.
  - MB-08 is corrected: `api/household/death-scenario` has a caller on `/planning/what-if/death-of-spouse`, a page no link reaches.
  - MB-01 is now 150 files. The September method matched by file name and missed 5; corrected, the stamp count was 158.
  - MB-22, MB-49 and MB-52 need a live walk and are not rechecked.
- **New entries** in `October/October12Updates/mappingBugs2026-10-12.md`:
  - MB-59: `SpouseAccountCreated` and `SpouseAccountLinked` mailables are never sent. Map 17 had wrongly said they were.
  - MB-60: `/m` `tokens.js` has no importer, and `/m` `utils/lifeEvents.js` is used only by web, which `CLAUDE.md` does not mention.
- **Maps 17, 01 and 00 refreshed**, each with a "Refresh 2026-10-12" paragraph under its header:
  - The commit stamp is `f00076906`.
  - Citations were line-tracked; moved lines were re-pointed, and changed lines re-read and rewritten.
  - Each map's scoped tests were run by file name: map 17, 18 files, 188 passed; map 01, 24 files, 250 passed, plus two Vitest specs; the overview had its inventory recounted with methods reproduced at the stamp.
  - The overview's Appendix A was regenerated with 150 files.
  - The 2026-09-14 browser walks were **not** repeated. Statuses that say "driven" now say they date from 2026-09-14.
- Rejected: patching 02a and 02b by auto-anchoring alone. In tables, a shorthand `:N` citation refers to the File column of an earlier row. The first heuristic anchored it to class names (`User::create`) and produced wrong shifts. Fixed: across rows, only a row's FIRST full file citation anchors. `SHIFT-X` lines in the tracker output must still be eyeballed.

## Files touched this session (branch, `f00076906..HEAD`)

- `September/September14Updates/mappingBugs2026-09-14.md`: recheck section and register cells
- `October/October12Updates/mappingBugs2026-10-12.md`: new, MB-59 and MB-60
- `docs/app-map/17-emails-notifications.md`, `01-auth-registration-sessions.md`, `00-overview.md`: refreshed
- `October/October12Updates/appmap-refresh-tools/`: `maplines.py` (citation line tracker, `--apply` re-points moved lines), `citecheck.py` (cited files changed since a stamp), `reach.py` (reachability sweep), `overview_counts.py` (overview inventory). These are committed with this handover.
- This handover (copy on the branch, a copy here in the main checkout's untracked `October/October12Updates/`, and the vault mirror)

## WIP commit

- Commits on the branch: `c8b0cafda` (MB recheck, map 17, MB-59), `3e9fafe95` (map 01), `facfea07b` (overview, MB-60), then the handover commit.
- Pushed: yes, `origin/docs/app-map-refresh-2026-10-12`. **No PR opened and not merged to `dev`**: CSJ said stop.

## Open decisions

- **How to refresh 02a and 02b.** I asked: serially by me, two agents with my review, or mark them stale and go to 03. CSJ did not choose; he said stop. Default if he does not say: **serially, by me** (memory: no subagents without CSJ asking). Each map has roughly 80 to 95 changed citations, plus about 20 cross-row rewrites to eyeball.
- **When to open the PR and merge to `dev`.** Memory says always PR to `dev` and merge, and that review files must be on `dev` before handing over. Default: open the PR and admin-merge once CSJ is back, or straight away if the other agent has finished. Today's untracked `October/October12Updates/` in the main checkout belongs to the other agent. My branch adds files to that folder with names that do not clash (`mappingBugs2026-10-12.md`, `appmap-refresh-tools/`, this handover).
- The MB decisions themselves are unchanged and still CSJ's (see the register in the September file).

## Pick up from here (auto-continue contract)

- `todoCurrent/TODO.md` current item is 18 (tax plan working). This job is separate, CSJ-named on 2026-10-12, and not on the list.
- Recreate the worktree: `git worktree add <scratch>/wt-appmap docs/app-map-refresh-2026-10-12` (track the origin branch). For the stamp comparison, add a detached worktree at `28194e804` or `e4ddc4e3f` or `6b365dddc` as needed, and remove it after.
- Next action: refresh **02a** `docs/app-map/02-onboarding.md` (stamp `e4ddc4e3f`).
  1. `python3 -I October/October12Updates/appmap-refresh-tools/maplines.py <wt> docs/app-map/02-onboarding.md e4ddc4e3f` lists CHANGED (81), SHIFTED (62, of which about 20 are SHIFT-X to check by eye) and BARE? (8). Check every SHIFT-X anchor before `--apply`. In this map, the rows around map lines 392-401 anchor shorthand to `PreviewWriteInterceptor.php`, and they probably mean `OnboardingService.php`. Fix the anchors by hand.
  2. Re-read each CHANGED claim in the current code. The director (`OnboardingChatDirector.php`) grew from about 6,600 to about 8,300 lines; the state machine was reworked (campaigns forced, Save Tax-only, registration bubbles).
  3. Diff the scope for anything new (`git diff --stat e4ddc4e3f HEAD -- app/Services/Onboarding app/Http/Controllers/Api/AiChatController.php resources/js/components/Shared/AiChatPanel.vue resources/js/store/modules/aiChat.js resources/mobile/mixins/onboardingChat.js fyn-memory/procedural/workflow/onboarding`).
  4. Run the map's listed test files by name. The hook blocks directory runs; list them with `git ls-files`.
  5. Add the refresh paragraph, update statuses and findings, and commit.
- Then **02b** `docs/app-map/02b-campaigns.md` (stamp `6b365dddc`, 93 CHANGED), the same way.
- Then update `docs/app-map/INDEX.md` rows: commit `f00076906`, date 2026-10-12, status `mapped` (refreshed), MB ids including MB-59 and MB-60, and MB-07 withdrawn.
- Then PR to `dev` and merge. Then run `vault-sync`: the vault `fynlaBrain/appMapping/` is missing `02-onboarding.md` and `02b-campaigns.md` and has the old copies of the rest.
- Then section **03 Dashboard**, a new map per the skill, in INDEX order.

## What the next Claude needs to know

- **A test hook blocks any Pest command line containing a directory, or even the text "vendor/bin/pest" with a placeholder** (it fired on a markdown string inside a python heredoc). Name the test files explicitly.
- `npx vitest run <file>` from the main checkout also runs copies in the three stale agent worktrees under `.claude/worktrees/` (`agent-a441910f…`, `agent-aa7d9d61…`, `agent-aef72dea…`, branches `fix/one-figure-*`). Report only the main-path results. Those worktrees are not mine; leave them alone, or ask CSJ.
- zsh eats `$c:resources/...` (`:r` modifier). Use `${c}` or `bash -c`.
- The overview's 2026-09-14 "largest service groups" list and the route-group sizes could not be reproduced by any method. They are marked replaced or "not recounted". The router count method that reproduces 161 is: top-level `^    path: ` plus child `^        path: `.
- `php artisan route:list --json` was run in the main checkout, which is the same commit, `f00076906`. The other agent's uncommitted changes were only TODO and log files at the time.
- Evidence rules (`app-map` skill): "driven" needs a walk this run. Tests earn "Working in test" only when the named file passed this run. Anything else is Unverified or "I COULD NOT VERIFY".

## Branch / deploy state

- Branch: `docs/app-map-refresh-2026-10-12`, pushed; 0 behind `origin/dev` at cut (`f00076906`), 4 commits ahead (3 + this handover commit).
- Main checkout: untouched by this session apart from this file and the vault mirror. It is still on `dev` with the other agent's changes.
- Scratchpad worktree `wt-appmap` removed after the push; the stamp worktree `wt-old` was removed.
- Deploy: not deployed (docs only).
