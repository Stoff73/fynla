---
id: GATE-0003
workstream: quality
item: null
action: Add a screenshot-filing convention to root CLAUDE.md so agent-generated PNGs land in screenshots/YYYY-MM/ instead of the repo root
raised: 2026-08-17T07:45:00Z
decided_by: null
decided_at: null
decision: null
status: proposed
---

## What is being asked

CSJ, 2026-08-17: *"We also need to file all the png files that were created, as
well as ensure that the png files created by the coding agent are always filed
and not in root."*

The **filing half is done** and needed no gate. The **prevention half** requires
one edit to root `CLAUDE.md`, which `oversight-guard.sh:39` gates to a founder
(`(^|/)CLAUDE\.md`, rank-1 under `00-precedence` §1). Proposed here.

## What was already done (not gated)

- **173 loose root PNGs moved** into `screenshots/YYYY-MM/` by file mtime:
  `2026-05/` (7), `2026-06/` (112), `2026-07/` (54). 19 MB total.
  Root went from 297 entries to 127.
- **`.gitignore`** gained `/screenshots/` alongside the existing `/*.png`.

Nothing was deleted. The PNGs were already gitignored (`/*.png`), so none of
them had ever reached the repo — this is filesystem hygiene, not history.

## Why an instruction is the only available mechanism

Checked before proposing:

- **Playwright is not the source.** `playwright.config.js:8` sets
  `testDir: './tests/E2E'` and `:23` `screenshot: 'only-on-failure'`; its
  artifacts go to `test-results/`, not the root. Changing `outputDir` would fix
  nothing.
- **The source is agents saving browser screenshots to the working directory** —
  Claude in Chrome / Playwright MCP captures written to `cwd`, which is the repo
  root. Filenames confirm it: `azlan-01-m-home.png`, `b2-income-turn.png`,
  `batch1-hero.png`, `coala-task14-session-log.png`.

There is no config knob for "where an agent decides to save a file". The only
mechanism that reaches every agent on every task is the rank-1 instruction file.
Hence the gate.

## The edit

Root `CLAUDE.md`, appended to the existing **Scratchpad Directory** guidance
(which already tells agents where temporary files go, but says nothing about
screenshots, which are deliberately *kept*):

```
NEW (add as its own short subsection):

### Screenshots

Browser/Playwright screenshots you intend to keep go in
`screenshots/YYYY-MM/` (create the month folder if absent) — **never the repo
root**. The directory is gitignored, so nothing here reaches the repo; the rule
exists because 173 loose PNGs made the root unreadable by 2026-08-17.

Throwaway captures taken mid-investigation belong in the session scratchpad
instead, not in `screenshots/`.
```

That is the whole change: one subsection, no rule renumbering, no edit to any
existing line.

## What happens if held

The root re-accumulates. The May–July run was 173 files in ~11 weeks, so roughly
16 a week. `.gitignore` keeps them out of git either way, so the cost is purely
that the repo root stops being readable — which is the same problem the 2026-08-15
handover logged as "scoped, not started" and which this gate is the second half
of fixing.

Holding is a fine outcome. The 173 existing files are already filed regardless of
this decision.

## Decision and reasoning

_Pending CSJ._
