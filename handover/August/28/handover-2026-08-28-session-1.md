---
type: handover
mode: session-end
date: 2026-08-28
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-08-28, Session 1

## Where things stand

**This was a hygiene session, not a board session. No board defect was fixed.** The
morning went on clearing the tree, merging the backlog of open PRs, pruning 106 dead
branches, and diagnosing why `main` and `dev` had diverged. `dev` is clean at
`5f07bad7a`, nothing unpushed, six PRs merged.

**The one thing genuinely outstanding is a decision, not work: PR #736.** It reconciles
`main` with `dev` and is deliberately unmerged, because merging it makes main's tree
equal dev's — which is a release. Everything else on the board is where it was.

The board is **304 items, 232 open** (5 critical, 92 high, ~104 medium, 17 low). Nothing
from the tax-moving queue was started.

## Priorities for the next session

1. **PR #736 — BLOCKED ON CSJ.** `main` and `dev` diverged at `a10210c49` and neither
   contained the other. #736 is a `-s ours` merge that keeps dev's tree whole and records
   `main` as a parent, so the next release replays a shared history instead of being
   hand-resolved. **Merging it equals a release** — timing is CSJ's, and the normal gates
   (csjones deploy, browser verification) still apply. **Do not merge it to tidy up.**
   Ask at the start of the day; if the answer is "not yet", leave it open and move on.

2. **Branch protection on `main` — BLOCKED ON CSJ.** Two single actions caused the
   divergence and protection would have refused both. Current settings:
   `enforce_admins: false`, `required_approving_review_count: 0`. Turning on
   enforce-admins stops the `git add -A` route. **Stopping the second route needs a
   workflow, not a setting** — GitHub cannot restrict a PR's *source* branch natively, so
   it wants a job that fails any PR into `main` whose head is not `dev` or `release/*`.
   Offered and **not built**; CSJ has not said yes. Roughly fifteen lines, and note it
   only goes live once `main` has it (workflows fire from `main`).

3. **The queued-high tax-moving run — the real work, untouched.** In order:
   `W-0480` (four services still read `married` alone, so a civil partnership gets the
   wrong answer), `W-0482` (projected estate needs the unused pension fund, not the pot),
   `W-0485` (blind person's allowance subtracted from adjusted net income), `W-0489`
   (migrating savings to cash would double-count every household), `W-0204` (salary
   sacrifice not added back to threshold income). Each is diagnosed to file:line in its
   board item — read the item, do what Acceptance says, do not re-derive.

4. **Then the rest of the queued high**, in board order: `W-0037 W-0050 W-0133 W-0138
   W-0139 W-0144 W-0155 W-0171 W-0222 W-0226 W-0227 W-0462 W-0486 W-0490 W-0495`, then
   the medium, then the low.

5. **One full-suite run, alone, as a consolidation point.** The 24 August handover said
   the suite had not completed since `19bd1c83f`; that claim now predates 100+ commits
   and eight merged PRs, so **re-establish it rather than repeating it**. PR #730 fixed
   the redeclaration that stopped the suite running at all, and #731 explicitly deferred
   the full suite and the frontend suite to CI. **One Pest process at a time** — two share
   `laravel_testing` and deadlock into 0-assertion failures indistinguishable from real
   breakage.

6. **W-0490 acceptance 3 — cannot be done here.** It wants a clean clone plus `git reset`
   on Windows and there is no Windows machine in this environment. The item is `gated`
   for that reason alone; acceptances 1 and 2 are done and guarded.

## Context to load

- `CSJTODO.md` — rewritten twice today and now current. The `2026-08-28` section carries
  the board counts, the ranked next-steps, and the full `main` reconciliation record.
  **Read this before the board itself.**
- `workforce/ops/board/W-0480-four-services-still-read-married-alone-so-a-civil-partnership-gets-the-wrong-answer.md`
  — priority 3's first item, fully diagnosed. Start here.
- `workforce/ops/board/W-0490-colon-paths-make-every-windows-index-rebuild-drop-tracked-files.md`
  — the Windows path bug, and the `## Resolution — 2026-08-28` section stating exactly
  which acceptance is unmet and why.
- `workforce/ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md` — **read before
  assuming any `gated` item is close to done.** Most are `CANNOT CERTIFY` for missing
  evidence, not wrong code. 115 of the 232 open items are gated.
- `tests/Feature/Workforce/BoardItemsAreWellFormedTest.php` — the two board guards, and
  the docblocks explaining what each one is for. Read before editing any board item in
  bulk.
- `workforce/ops/board/W-0506-the-consistency-sweep-has-91-findings-and-nothing-was-reading-it.md`
  — only if picking up doc/trunk hygiene. Not on the critical path.

## Completed this session

**Six PRs merged, all to `dev`.**

- **`b31b0412a` / #732 — CLAUDE.md and AGENTS.md consolidation.** CSJ's own uncommitted
  work from 10:15, committed and landed. 37 files, 293 insertions, **10,573 deletions**,
  no doctrine lost — the 22 rules and their cross-references are intact. Three new project
  skills hold the essays that were inlined: `data-integrity-traps`, `fyn-architecture`,
  `test-failure-forensics`. `.agents/skills` became a symlink to `.claude/skills` so Codex
  and Claude read one tree. Every deleted `.agents/skills/` file was verified present under
  `.claude/skills/` first; the two absent — `session-start`, `session-end` — have been
  user-level skills in `~/.claude/skills/` since 17 August.
- **#730** — the `linkedSpouses()` redeclaration that stopped the suite running at all.
- **#731** — W-0329/W-0505 Store validation, W-0245 dashboard derivation, W-0141 life-cover
  premium.
- **`9cee8a3b9` / #733 — W-0490 acceptances 1 and 2.** `August/bugs/ios:17August/` renamed
  to `ios-17August/`. Added `RepositoryPathsSurviveAWindowsCheckoutTest`, a duplicate-id
  check on the board guard, and fixed `wf.sh`'s item lookup.
- **`56a1c4b83` / #734 — salvaged `PremiumTestPersonaSeeder`** from `main`.
- **`61fd35ad0` / #735 — salvaged W-0483 from `main` as W-0507.**

**Branches: remote 117 → 12, local 103 → 9.** Every deletion was fully merged into `dev`.

**PR #736 opened against `main` and left unmerged**, with all 403 main-only paths
accounted for in its body.

**268 April/June docs copied into the fynlaBrain vault** — 203 already byte-identical, 3
vault copies longer than main's and left untouched, 62 copied. All 268 verified present.

## Verification state

- **`tests/Feature/Workforce/`: 3 passed, 8 assertions** at `5f07bad7a`. Pint clean.
- **Both new guards were mutation-tested, not trusted for going green.** A staged
  `probe:colon.txt` reddens the Windows-path guard; a copy of W-0505 carrying
  `id: W-0489` reddens the duplicate-id guard. Both pass once the probe is removed.
- **The salvaged seeder was verified by running it**, not by reading it:
  `php artisan db:seed --class=PremiumTestPersonaSeeder --force` completes against dev's
  schema, 81 commits past the one it was written for.
- **PR #736 verified structurally:** its tree SHA is identical to `dev`'s, and
  `git merge-base --is-ancestor origin/main HEAD` passes.
- **Board integrity:** 304 items, no duplicate ids, no malformed items, every filename
  matches its `id`.
- **NOT verified:** the full Pest suite (never run this session), the frontend suite, `/m`,
  iOS, anything in a browser. **No user-facing behaviour changed today**, which is why that
  is acceptable rather than a gap — but it means nothing here is browser-evidence for
  anything.
- **NOT verified:** W-0490 acceptance 3 (Windows checkout). **I COULD NOT TEST THIS.**

## Decisions and dead ends

**CSJ decisions this session — do not re-litigate:**

- **Merge both #730 and #731**, prune all 104 merged branches (remote and local).
- **The 268 April/June docs go to the vault, not the repo.** CSJ first said "salvage into
  dev", then redirected once I surfaced that `/April/` is gitignored *deliberately*
  (`.gitignore:66`, "Planning/review docs (local only)"). **The ignore rule stands.**

**Settled by inspection — do not "restore" these from `main`:**

- `SpouseNRBTrackerService.php` was deleted on `dev` **deliberately**, under W-0146. It is
  a dead class with no callers. `main` is stale, not ahead.
- `main`'s copy of W-0279 is the item **before** the work: it reverts a `done` +
  `CERTIFIED 2026-08-25` back to `queued` and deletes the entire working-notes section.
- `main`'s `tests/E2E/fixtures/app.js` is superseded by W-0492. Both sides fixed the
  consent fixture independently; `main` writes **both** `localStorage` and a cookie, dev
  removed the localStorage key no production code reads.
- `main`'s W-0484 board item is already on `dev`, split into W-0492 and W-0493.

**Where the divergence came from, so nobody re-diagnoses it:**

1. **PR #715 merged `estate-copy-and-m-handoff` straight to `main`**, bypassing `dev`.
2. **`75aa0d4f6` "manual merging all files"** — 356 files, 113,323 insertions, **zero
   deletions**. Someone commented out `.gitignore` rules (`/April/` → `# /April/`,
   `.superpowers/` → `# .superpowers/`, deleted `workforce/ops/log/*.log`) and ran
   `git add -A`. That is the origin of main's 250 April docs, 105 `.superpowers/` files
   and a 2.6 MB `myrtle-listen.log`. **It is the ignore rules failing, not history being
   recovered.**

**A correction worth carrying:** the 24 August handover listed W-0347's forged consent
rows and the Rule 9 "(AIM)" question as waiting on CSJ. **Both had been answered before
that handover was written** — W-0347 at 18:19 the same evening (dev only, database
restarted, G4/G5/G6 closed not deferred). `CSJTODO.md` carried them as open for four days.
Check the item before believing a "waiting on CSJ" line.

## Things that will bite you

- **BSD `xargs` has no `-a`.** `xargs -a file git push origin --delete` silently did
  nothing and reported success; the batch delete looked like it had run. Use
  `cat file | xargs -n N ...`. A `grep -c` on the output hid the error entirely.
- **A `status:` line can appear inside a fenced code block.** `grep -h '^status:'` across
  the board double-counts W-0009, whose HTTP transcript contains one. Count with
  `grep -m1` per file.
- **`wf.sh` item lookup was broken for 297 of 302 items** and is now fixed — but that means
  any script that built `workforce/ops/board/$ID.md` by hand has the same bug.
- **The consistency sweep takes about three minutes.** A `timeout 180` kills it mid-check-1
  and the pipeline still reports exit 0, so it looks like a clean run with no findings.
- **Never write a file with `open(p,'w').write(open(p).read() + note)`.** Python evaluates
  the write-open first, truncating before the read. It destroyed three board items on
  24 August. Read into a variable, then open for write. (Used correctly throughout today.)
- **`.superpowers/`, `test-results/` and `workforce/ops/log/*.log` are gitignored for good
  reasons.** If a diff ever shows them tracked, someone has commented out the rules.
- **Guards written against an already-clean repository prove nothing.** Both guards added
  today were mutation-tested before being believed; do the same for the next one.

## Tech debt deferred

The session changed docs, board items, two test files, one shell line and one salvaged
seeder. The debt pass covered the only file not written here:

- **`database/seeders/PremiumTestPersonaSeeder.php` — clean.** No emoji or Unicode-as-icons
  (Rule 15), `declare(strict_types=1)` present, canonical `individual` ownership enum
  (no `'sole'`), and its numeric literals are persona figures rather than tax constants,
  which is correct for a seeder (Rule 2 not engaged).

Carried, not introduced today:

- **W-0506** — the consistency sweep reports 91 findings (83 broken references, 7 oversize
  trunk files, 1 restatement). **None caused by today**, checked by intersecting today's
  deleted basenames against every broken reference: the intersection is empty. Most are
  citations rather than links, which is the actual finding.
- **12 open board items carry no `severity`** — mostly `W-0001`–`W-0023`, which predate the
  field, plus `W-0176` and `W-0177`. Assigning one is triage, not cleanup, so they were
  left alone. **Do not add a guard for this** — it would redden on 12 grandfathered items.
- **Two local branches survive with no remote and unmerged work:**
  `codex/fynla-org-repository-migration` (3 commits ahead of `dev`) and `pr674-ci-green`
  (0 ahead, held by a worktree). Neither was deleted.

## Branch and deploy state

- **Branch: `dev`** at `5f07bad7a`, level with `origin/dev`. **Tree clean.**
- **Unpushed commits: none.**
- **Remote branches: 12** (from 117). **Local: 9** (from 103).
- **Open PRs: #736** into `main` (deliberately unmerged, see priority 1) and **#249**, the
  parked Python Agent SDK sidecar — leave it alone.
- **Deploy: nothing deployed this session.** csjones and production untouched. `main` still
  carries its pre-reconciliation tree until #736 is merged.
