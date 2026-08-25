---
type: handover
mode: end-of-day
date: 2026-08-15
session: 1
branch: fix/widow-persona-cleanup
previous_session: 2026-08-14 (no prior handover file — first of this series)
---

# Handover — 2026-08-15, Session 1

## Where we left off

The day started as an iOS bug hunt and turned into a documentation and tooling
audit. The iOS "bug" was diagnosed as user error and needs no code. What's
actually left open is the **guard consolidation** (`workforce/ops/proposed-guard.sh`,
written and tested but NOT installed — `.claude/hooks/` is founder-gated) and a
**repo-root cleanup** that was scoped but never started.

## What shipped today

- `705bf9b` docs(claude): repair CLAUDE.md context, add ios-native, drop dead references
- `6bbb7c5` chore(workforce): track the workforce tree, agents and enforcement hooks
- `f30023e` chore(lint): shorten fully-qualified imports and tidy unused ones
- `0c01cf7` chore: track August update folders and the Mission Control launcher

All pushed to `origin/fix/widow-persona-cleanup` (new remote branch).

### The iOS investigation (no code changed — closed as user error)

A tester registered via `/m` on **fynla.org** then could not log into the iOS
app. Root cause: the only installable build is TestFlight **`Fynla-Staging`**,
hard-wired in `Configurations/Staging.xcconfig` to `https://csjones.co/fynla` —
a different database.

Evidence: production had **zero** failed-login rows on 13 Aug (both 401 branches
write `login_attempts` before returning), while csjones had **four**
`reason: user_not_found` rows for `test23@phailanx.co.uk` at 12:30–12:47 — seven
minutes after the account was created on prod at 12:23:52.

Why it can't just be repointed: production has **no** `/api/v1/native/*` routes.
Probe `GET /api/v1/native/health` — prod returns `200 text/html` (SPA fallback =
route absent), csjones returns `400 application/json`. Fixing that is a
`dev → main` release, not a code change. **Testers must register on
csjones.co/fynla.** Now documented in root `CLAUDE.md` → Mobile Clients and in
the `project_ios_programme_status` memory.

### The CLAUDE.md audit

Full report: `August/Aug14Updates/claude-context-audit-2026-08-14.md`.

The headline was that `resources/js/CLAUDE.md`'s Mobile section documented an
architecture that no longer exists — **9 of 12 concrete file references were
dead**, including `normaliseModule()`, whose only occurrence in the entire repo
was the sentence describing it. Root `CLAUDE.md` pointed at that section as
authoritative, and Rule 19 makes an agent read it on nearly every task.

Also created `ios-native/CLAUDE.md` — 240 Swift files previously had no doc at
all beyond `TESTFLIGHT.md`.

### Worktrees

13 → 4, **~2.2 GB reclaimed**, 7 stale `CLAUDE.md` copies gone (grepping a rule
now returns 7 real files, not 40-odd conflicting ones). No branch was deleted —
every removed worktree's commits survive on its branch.

## What's in flight (NOT done)

- **Guard consolidation is NOT installed.** `workforce/ops/proposed-guard.sh`
  replaces all five PreToolUse guards; 59-case regression suite passes
  (`.claude/hooks/tests/guard-cases.tsv` + `guard-cases2.tsv` — rescued into the
  repo at session close). Installation is three shell commands plus a
  settings.json edit, both founder-gated. See GATE-0002.

  One thing to decide with it: the proposal widens `PROTECTED` to the
  `.claude/hooks` **prefix**, which gates `.claude/hooks/tests/` too — an agent
  fixing a guard bug could no longer add its own regression case. Probably
  correct (guard changes are gated regardless), but choose it deliberately
  rather than inheriting it.
- **Repo-root cleanup — scoped, not started.** 297 entries at root, **173 loose
  `.png`** session screenshots. They ARE gitignored (`.gitignore:51 /*.png`) so
  they never reached the repo, but they re-accumulate every session and make the
  root unreadable. Also loose: `create_trial.php`, a ProjectionLab account-data
  JSON with CSJ's email in the filename, `addepar.md`/`addeparIntegrate.pdf`.
  Nothing was verified as safe to delete.
- **`.worktrees/` holds 14 clones of OTHER repositories** — `Fynla/FynlaMCP`
  (9 branches), `fynla-agents`, `fynla-control`, `fynlaBrain` — ~250 MB, all
  clean, all touched 12–13 Aug. Left untouched: active work in a confusing
  place. Worth relocating.
- **`fynla-marketing-review` worktree has 10 modified tracked files + 2 new test
  files, uncommitted** (pipeline controllers, `DocumentArticle`,
  `ClipApprovalService`, `routes/web.php`, `public/pages/index.php`,
  `PostApprovalTest.php`, `SignedClipDownloadTest.php`). Deliberately not
  touched. Decide whether to commit or discard.

## Deploy status

**Nothing to deploy.** Today was documentation, configuration and repo hygiene.
The one commit touching `app/` (`f30023e`) is a behaviour-neutral import tidy
that was already in the working tree at session start.

Branch is **10 ahead / 161 behind `origin/dev`** — rebase before opening a PR.

## Tech debt found this session

- **Guard has two structural holes** (both now fixed in the proposal, neither
  live): `PROTECTED` matched hook *files* but never the hooks *directory*, so
  `rmdir .claude/hooks/` and `find .claude/hooks -delete` were allowed — one
  command, every guard gone. And `(^|/)CLAUDE\.md` assumes a path, so
  `rm CLAUDE.md` was allowed while `rm ./CLAUDE.md` was denied.
- **Hook commands use absolute paths** (`/Users/CSJ/Desktop/fynla/...`) inside
  tracked `settings.json` — breaks on any other clone path. `$CLAUDE_PROJECT_DIR`
  fixes it. Gated.
- **19 agent definitions with overlapping remits and no routing rule** —
  `product-manager` vs `product-lead`; `design-lead` vs `premium-ui-designer` vs
  `ux-writing-expert` vs `frontend-developer`. Nothing tells an agent which to
  pick.
- **`enabledPlugins.github`** is `true` in `settings.json`, `false` in
  `settings.local.json`. Local wins; one is a leftover.
- **git is 2.10.1 (2016).** No `worktree remove` (2.17), no
  `branch --show-current` (2.22), no `stash push -- <path>` (2.13). Several
  commands failed today because of it. `brew install git`.

## Known issues / blockers

- **Nothing mechanically gates a merge.** Verified live: `main` and `dev` both
  have `required_approving_review_count: 0`, `require_code_owner_reviews: false`,
  `required_status_checks: null`, `enforce_admins: false`. This **falsifies
  GATE-0001's stated premise** ("branch protection is still in place and may
  still hold — unverified"), which was approved on the assumption something
  might. `claude.yml`'s `gh pr merge --auto --squash` has nothing standing in its
  way. Worth revisiting that gate.
- CODEOWNERS was removed in `ab339eb` — but it was **never enforced anyway**
  (`require_code_owner_reviews: false`), so nothing changed operationally.

## Rules reinforced this session

- **Say what you actually checked.** Asked "so you checked the entire root?" the
  honest answer was no — two things were audited, not the root. Scope claims get
  tested.
- **The oversight guard is real and correct.** It blocked the first CLAUDE.md
  edit; the right response was to route through `workforce/ops/` and raise a
  gate, not to work around it.
- Memory updated: `project_ios_programme_status.md` — rewritten with the
  TestFlight/csjones trap stated plainly, plus the `MEMORY.md` index line.

## Next session should

1. **Install the guard consolidation** if you want it: `mv` the proposal to
   `.claude/hooks/guard.sh`, `chmod +x`, delete the five originals, collapse the
   `PreToolUse` block to one entry with matcher
   `Write|Edit|Bash|mcp__ssh-fynla__ssh_exec`. Then re-run the 59-case suite
   against the installed path — it must stay 59/59.
2. **Move the two test-case `.tsv` files into the repo** (suggest
   `.claude/hooks/tests/`) — they're currently only in the session scratchpad and
   will be lost.
3. **Revisit GATE-0001** with the branch-protection evidence above.
4. **Decide on the root sweep** — 173 PNGs into a dated folder or binned, and a
   verdict on `create_trial.php` and the ProjectionLab JSON.
5. **Rebase on `origin/dev`** (161 behind) before opening a PR for this branch.

## Context hints

- Active branch type: mixed (docs + config + repo hygiene)
- Ahead of `origin/dev` by: 10 commits · Behind by: 161
- Uncommitted: none, working tree clean
- Last commit: `0c01cf7` chore: track August update folders and the Mission Control launcher
- Open gates: GATE-0002 (applied), GATE-0001 (approved — premise now falsified)
