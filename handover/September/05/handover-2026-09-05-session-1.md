---
type: handover
mode: session-end
date: 2026-09-05
session: 1
repo: fynla
branch: chore/board-verification-31-august
---

# Session Handover — 2026-09-05, Session 1

The session ran on 4 September from 13:17; the wrap is being written at 08:25 on
5 September, so the folder date is the 5th and the work date is the 4th.

## Where things stand

W-0540 was one `git rm` away from deleting 79 Vue components when CSJ stopped it and
asked for proof it was a defect, an explanation of why it was happening, and then
whether the code was not simply needed. The answer, verified two independent ways
against `dev` as served on csjones, is that **152 components are unreachable, not 79,
and they are four different things**: roughly a hundred superseded by live rebuilds, a
dozen that are a built and still-advertised feature with no screen (the advanced
investment analytics), two never mounted, and one live dead end at the end of every
journey. The full report with a recommendation per cluster is at
`September/September4Updates/dead-components-verification-2026-09-04.md`, mirrored to
the vault. **Nothing was deleted and no code changed.** The branch is otherwise where
yesterday's handover left it: `31c2bc4ad` (W-0532/0533/0534) is still not on `dev`, and
the board still says `queued` for those three items.

## Priorities for the next session

1. **BLOCKED ON CSJ — the six decisions in the report, section 10.** Ask first, in
   this order: (a) the analytics cluster: re-mount as an "Optimisation" detail route,
   or retire the claims in `Help.vue`, README and vault now and park the capability;
   the recommendation is retire the claims today, then decide the re-mount as a
   specified mission; (b) are the financial statements (balance sheet, cash flow, P and
   L under `UserProfile/*`) still a feature; (c) amortisation schedule and savings rate
   benchmarking, keep or drop; (d) approve the superseded deletions on the condition of
   a per-file read before each directory goes; (e) approve two new board items, the
   journey dead end and the campaign CTA panel; (f) approve replacing the guard with
   the import-graph walk plus the reverse unregistered-tag check. Until (a) to (c) are
   answered nothing in report sections 6.2, 6.6 or 6.7 is deleted.
2. **BLOCKED ON CSJ — carried from yesterday:** the Rule 15 lint (narrow to diff lines,
   allowlist, or clear the four hits), and whether W-0535 is closed or stays plan-first.
   Detail is in yesterday's handover; it has not changed.
3. **Update the W-0540 board item** with the report: count is 152 plus two views, the
   "delete the 79" framing is withdrawn, the item becomes the per-cluster decision list,
   and the 12 May conventions review is cited as prior art. No approval needed.
4. **Close W-0532, W-0533, W-0534 on the board.** Fixed and committed at `31c2bc4ad`,
   still `queued` in `workforce/ops/board/` and `tasks.md`. Board-loop step 3 first,
   open the code, then step 9.
5. **Get `31c2bc4ad` onto `dev`.** The red guard commit `960f23308` sits after it on
   this branch and must not reach `dev`, and the sweep it waits for is now gated on
   priority 1. So cherry-pick `31c2bc4ad` onto a fresh branch off `dev`, PR, admin-merge,
   then rebuild `public/build/` with the csjones script and upload. Do not PR this branch
   whole.
6. **Browser-verify the journey dead end on csjones** before raising it: Planning
   Journeys, start a journey, complete the last step, and look at what renders.
   `OnboardingWizard.vue:248` renders a component deleted on 20 March. Chrome extension
   tools work; the Playwright MCP did not connect either day.
7. **After (f):** port `September/September4Updates/reach.mjs` into the Pest guard or a
   vitest, add the reverse check from `unreg.mjs`, and prove both by mutation.
8. **After (d):** superseded deletions directory by directory with a per-file read,
   findings to the board before the file goes, the 32 specs with them, the two constants
   specs' path lists edited, and the `FreemiumCopyContractTest` entry for `CalculatorCard`
   moved to `deleted`. Full frontend suite and a production build after each directory.
9. **After (e):** mount `Insights/InsightCtaPanel.vue`; `growth-lead` owns it.

## Context to load

- `September/September4Updates/dead-components-verification-2026-09-04.md` — the
  report. Everything above derives from it. Sections 1, 6 and 10 at minimum; section 2
  is the product brief CSJ said had always been missing.
- `September/September4Updates/README.md` — the instruments preserved beside the
  report (`reach.mjs`, `unreg.mjs`, the two lists) and how to run them. The scratchpad
  they came from is per-session.
- `workforce/ops/board/W-0540-a-component-can-lose-its-last-importer-and-nothing-fails.md`
  — to be rewritten under priority 3; still says 79 and describes a wide haystack that
  is not in use.
- `tests/Architecture/EveryComponentIsRenderedSomewhereTest.php` — the guard to replace;
  red by design at 79 orphans.
- `handover/September/04/handover-2026-09-04-session-1.md` — the Rule 15 lint and
  W-0535 detail, the csjones deploy notes, and the "things that will bite you" list,
  all still true.
- `tasks.md` and `.claude/skills/board-loop/SKILL.md` — the board and the loop CSJ
  treats as law.

## Completed this session

- Session-start briefing, then W-0540 taken off the board and stopped at step 3 by CSJ.
- Verified the orphan claim with a second instrument, an import-graph walk from
  `resources/js/app.js` and `resources/mobile/main.js`, validated on known-live files:
  152 of 527 components and 2 of 166 views unreachable; all 79 guard hits inside the 152.
- Verified against the csjones build of 4 September 09:24 at `41771cca0`: all 268 JS
  chunks downloaded, 151 of 152 dead names absent, the one hit a name collision with a
  live view, nine live controls present. Component tree and import graph identical
  between `origin/dev` and this branch.
- Established why: Vue's silent unresolved tags, Vite's silent exclusion, vitest
  specs mounting dead code, and lint removing "unused" imports. Dated the parent
  deletions (the March 2026 rewrites, the 16 July lint commit `9e865394c`).
- Read the product properly on CSJ's instruction: constitution, public site, README,
  tier seeder, routers, side menu, Fyn prompt, vault overview, GitHub. Written up as
  report section 2.
- Classified all 152 by cluster with a live replacement named where one exists, and
  found the lost analytics cluster (backend maintained with tests, routes live, no
  screen, no Fyn tool, Help still promising it).
- Found the journey completion dead end and the dead branch on the protection view.
- Wrote the report to `September/September4Updates/` and mirrored it to the vault,
  with the scripts and lists beside it.
- Memory saved: `feedback_verify_product_need_before_deleting`.

## Verification state

No test result changed this session; no code was touched.

- `EveryComponentIsRenderedSomewhereTest`: still RED by design, 79 orphans, at `9fe6f6d28`.
- The 152 and the bundle check: verified as described above, against `41771cca0`.
- Not verified: the journey dead end in a browser; whether savings market rates reach a
  user through a recommendation; the `UserProfile` statements' replacement; whether the
  32 specs assert anything a live component does not already cover; what the Myrtle brief
  cron wrote to `workforce/ops/`.

## Decisions and dead ends

- **CSJ: "before you delete anything, verify that this is in fact an issue."** The
  "delete the 79, blocked on `git rm` approval" framing in yesterday's handover,
  CSJTODO and the board item is withdrawn. It was the wrong shape, not just the wrong
  number.
- **CSJ: verify against dev on csjones.co/fynla**, which carries the latest builds.
- **CSJ: the product understanding has always been missing** despite session-start,
  handovers and the vault. The `vault-context` skill has `general` and per-module modes
  and no product mode; report section 2 is the first written brief. Suggest adding a
  product mode that reads the constitution and the public site; not done, it is CSJ's
  skill.
- **A comment is not an importer.** `InvestmentList.vue:178` says "components still
  available for detail views"; four of five got detail routes, the fifth never did, and
  the lint fix then cut the imports. Parking is a real pattern in this codebase and it
  is invisible to every instrument.
- **`comm` with mixed collations lied**: two "guard false positives" mid-session were an
  artefact. `LC_ALL=C sort` both sides.
- **Resolving routes to views with awk was wrong** for routes that reference a named
  import rather than an inline `import()`. Resolve by view file instead.
- **An unquoted file-list variable in a grep produced 190KB of garbage.** Use `xargs`
  over a file.
- **macOS grep has no `-P`**; unicode classes need `perl -CSD`.
- **Per-file `git log` over 152 files takes minutes** on this repo; background it.

## Things that will bite you

- The scratchpad is per-session; everything reusable was copied to
  `September/September4Updates/`.
- The Myrtle brief cron leaves `workforce/ops/log/*` modified and drops
  `workforce/ops/reports/brief-*.md` untracked. Two briefs are there now (2 and 4
  September). Left uncommitted; not this session's work.
- `ssh-add ~/.ssh/fynlaDev` may be needed again; it was loaded yesterday.
- The Playwright MCP failed to connect two days running. The Chrome extension tools
  did everything needed, including fetching the csjones build.
- Re-mounting any of the dead files is a rebuild, not a re-import: 15 carry Rule 12
  score strings, 11 carry emoji or glyphs, 4 carry tax literals.

## Tech debt deferred

No code changed, so the tech-debt pass has nothing to audit. The report's section 6
is the debt list for this item. The guard stays red until the decisions land.

## Branch and deploy state

- Branch: `chore/board-verification-31-august`
- Unpushed commits: none before this handover commit
- Not on `dev`: `31c2bc4ad`, `960f23308`, `d04ffd1c5`, `9fe6f6d28`, plus this commit
- `dev`: `41771cca0`; csjones on `dev` at that commit, web build 4 September 09:24,
  `/m` build 1 September
- Production: untouched
