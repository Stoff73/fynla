---
id: W-0506
title: The consistency sweep reports 91 findings and nothing has been reading it
mission: M-0001-state-truth
owner: archivist
reviewers: [chief-of-staff]
status: queued
severity: low
surfaces: [none]
source: run during session-open hygiene, 2026-08-28
prior_art_checked: 2026-08-28
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

`workforce/ops/sweep.sh` — the Archivist's three checks, made runnable — completes in
about three minutes and reports **91 findings across 78 checks**. None of them is new
today; the sweep simply has not been run in long enough for the debt to accumulate
unnoticed.

    83  BROKEN    a backticked filename in a trunk or ops document resolves nowhere
     7  OVER      a trunk file exceeds its size budget
     1  RESTATED  "never verifies their own work" appears in four trunk files

**Today's changes are not the cause**, checked rather than assumed: the basenames
deleted by the two commits on 2026-08-28 were intersected with the basenames of every
broken reference, and the intersection is empty. The `.agents/skills` files that moved
still resolve, because `.agents/skills` is a symlink to `.claude/skills`.

## What the findings actually are

The 83 broken references are mostly **relative filenames inside persona-run reports**
(`reports/R-01-pass-a-entry.md` and siblings, 8 and 5 and 4 occurrences each) and
**test or component filenames quoted as evidence** (`TierResolverTest.php`,
`VoiceInputButton.vue`, `main-DTjymbsC.js`). A quoted filename in a report is a
citation, not a link — the sweep cannot tell the two apart, and treating every one as
a defect would be policing prose.

`GATE-0002-claude-md-context-repair.md` alone accounts for ten of them, which is
expected: that gate exists *because* those references are dead.

So the finding count is not 91 real defects. **What is worth fixing is the signal
quality** — a check nobody runs because its output is mostly citations is a check that
will not be believed the day it catches something real.

## Acceptance

1. The orphan check distinguishes a **reference** from a **citation** — one plausible
   rule is that only paths containing a `/` are treated as links, since a bare
   `Foo.php` in a report is almost always a name.
2. The four `OVER` trunk files are either split or their budget is restated with a
   reason. `capabilities.md` at 29,436 against a 8,000 budget is the extreme.
3. The `RESTATED` finding is resolved by choosing one home for the clause and pointing
   the other three at it — the same treatment CLAUDE.md's rules got on 2026-08-28.
4. The sweep is run on a rhythm rather than on discovery, and the rhythm is written
   into `workforce/core/registry/rhythm.md`.

## Not fixed here

Nothing. This item exists so a three-minute check with a real finding in it does not
get rediscovered in three weeks as if it were news.
