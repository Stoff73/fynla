---
id: W-0506
title: The consistency sweep reports 91 findings and nothing has been reading it
mission: M-0001-state-truth
owner: archivist
reviewers: [chief-of-staff]
status: done
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

## 2026-09-01 — CLOSED

Re-run first: **99 BROKEN** (up from the item's 83), 7 OVER, 1 RESTATED.

### Acceptance 1 — the item's proposed rule was measured and rejected

The item suggested treating only paths containing a `/` as links. **Measured before
implementing: it fails on the item's own example.** Of 99 broken references, 41 contained
a slash — and **25 of those were `reports/…`**, the persona-run citations the item itself
names as citations. The rule would have kept the noise and hidden nothing.

**The actual cause is the basename index**, `sweep.sh:31-32`. It was built from a fixed
list of directories that omitted **`tests`, `public`, `ios-native`, `routes` and
`fyn-memory`** — so every citation of a persona report under `tests/Persona/…/reports/`,
a test filename quoted as evidence, an iOS fixture, a built asset under `m-build/`, or a
Fyn tool schema was unresolvable **by construction**. Not broken: invisible. That is all
four example shapes the item lists, and it accounted for 55 of the 99.

No citation heuristic was added and none is wanted — guessing at intent would hide the
real findings with the noise. The illustrative placeholders (`Foo.php`,
`branches/fixes/F-....md`, `.php/.blade.php/.html`) join the existing `NNNN` / `YYYY` /
`<slug>` filter, which is a list of literal patterns, not a guess.

### Acceptance 2 — budgets restated, with the reason

`00-precedence.md` §2.4 says it in terms: *"Budgets are advisory — crossing one triggers a
review, not an automatic cut."* They were nonetheless counted in the same total as broken
references, which put seven standing reviews into a headline number and is part of why
nobody read it. **Advisories are now counted and reported separately.**

The registry is budgeted apart from doctrine at **32k**: `capabilities.md` and
`sources.md` ENUMERATE — they grow with the system by design, and holding a list to the
doctrine budget reported permanent breach for doing its job. 8k was the wrong number for
a list, not evidence of bloat. The constitution and charter keep 8k, because doctrine
nobody finishes reading binds nobody.

### Acceptance 3 — already satisfied, and the check had the same flaw

The three "restatements" of *"never verifies their own work"* are **already references**:
`07-quality-bar.md:65` and `capabilities.md:200` both name `08-process.md` §2.4 in the
same sentence, and `00-precedence.md:147` is the sweep's own note about this finding. So
no trunk prose needed editing — **the check could not tell a duplicate from a citation
with attribution**, exactly like the orphan check. It now knows where a clause lives and
does not flag a file that points at that home.

### Acceptance 4 — the rhythm

`workforce/core/registry/rhythm.md` §4ter: weekly, at the Monday planning meeting, read
there rather than filed. It records the two rules that keep it worth reading — findings
and advisories are different numbers, and a **rising** count is the signal rather than the
absolute one, because some references are permanently unresolvable (a build hash quoted as
deploy evidence) and chasing those to zero is how a check gets gamed rather than fixed.

### Result, measured

| | before | after |
|---|---:|---:|
| BROKEN | 99 | **34** |
| OVER | 7 (as findings) | **5 (as advisories)** |
| RESTATED | 1 | **0** |
| headline | 91 findings | **34 findings, 5 advisories** |

The 34 that remain are largely real — genuinely deleted or renamed files
(`SpouseNRBTrackerService.php`, `MigrateSavingsToCash.php`, `EstateOverviewCard.vue`,
`VoiceInputButton.vue`, `MobileLoginScreen.vue`), plus stale build hashes cited as deploy
evidence, which are correctly unresolvable and will not go away.

**Guard:** `tests/Architecture/ConsistencySweepIndexesWhatItCitesTest.php` fails if a root
is dropped from the index or the placeholder filter is narrowed. Architecture suite: 153
passed, 0 failed.

**Not done:** the 34 remaining broken references are not chased — several are real
deletions whose citing documents are historical reports, and rewriting history to satisfy
a checker is the failure this item is about. The `chief-of-staff` reviewer on this item's
front matter was not run; no agent was dispatched, per the session instruction.
