# Consistency sweep — 2026-08-21

**Agent:** archivist · **Snapshot:** 18:13:52 to 18:25:45, 2026-08-21
**Scope:** 69 board items, 6 branch documents, 6 handoff directories, 6 reports dated
2026-08-21, `ops/log/2026-08.jsonl`, correlated against 681 changed paths in the working
tree.
**Nothing was repaired.** No status changed, no branch document edited, no application
code touched. Five fix agents and a tester were writing throughout.

> **The board moved while I swept it.** 68 items at 18:13, 69 at 18:17, **71 at 18:25**
> — W-0121 and W-0122 arrived mid-sweep, and W-0033, W-0043 and W-0044 went
> `queued` to `claimed` between my two correlation passes. Every count below is as at
> 18:13–18:25. Two items created after 18:17 are named but not analysed.

---

## Done

### The headline, because it changes the working rule

**The board's `status:` field is not what has been lying to you.** I compared all 69
items against the tree and the status field is right in almost every case I could
verify. Of the apparent contradictions, every one I chased resolved to *correct*:

- **W-0050 `queued`** with four of its files changed — deliberate. Its own notes say
  *"Status left `queued`, severity unchanged. Do not re-raise as a gate."* The changed
  files are `fix-batch-F`'s W-0047/W-0049 work, which W-0050 explicitly declines to
  absorb.
- **W-0101–W-0110 `queued`** while `fix-batch-G` runs — correct. F-0008 §5 states
  plainly *"W-0101 was raised, not fixed"*. The changed files are W-0100's fix touching
  shared paths.
- **W-0082 `done`** with no code — correct. Closed as a duplicate of W-0048; the log
  records it at 16:31.
- **W-0048, W-0081 `blocked`** with no code — correct, parked by CSJ at 16:31.
- **W-0044, W-0049 `claimed`** with no code — correct. `claimed` is set at dispatch by
  design, before the agent has written anything (`FORMATS.md`).

**All three incidents that cost you time today were caused by a wrong *narrative*, never
a wrong status.** *"Trust the tree over the board"* is the right rule for the **branch
link, the working notes and the log** — and unnecessary for the status field itself.

### Two root causes, not thirty findings

Everything below resolves to one of two mechanisms. Fixing either is cheap; fixing the
symptoms one at a time is not.

**RC-1 — the branch document is not wired into the index, in either direction.** A
branch document is the richest artefact any agent produces today (F-0001 is 50,823
bytes) and it hangs off nothing.

- *Board-facing half:* items do not point at branch documents — **items 1 and 2 below**.
  F-0005 and F-0006 exist with zero inbound references; F-0004 is referenced and absent.
- *Trunk-facing half:* branch documents do not point at the trunk — **`G-0004`**. Four of
  six carry no `parent`/`applies`/`consistency_checked`, so a trunk amendment propagates
  to none of them. Recorded in full there; not restated here.

**Both `fix-batch-D` reading as "state unknown" and `fix-batch-E` reading as "barely
started" are RC-1.** In each case the answer was already written down and unreachable.

**RC-2 — corrections are appended, never applied.** The newest truth sits at the bottom
of every artefact and the reader starts at the top — **item 3 below**.

**Correcting one thing in your brief:** the W-0018 incident is **RC-2, not a symptom of
`G-0004`**. Frontmatter on a branch document would not have prevented it — the false
"No code changed." was inside the item's own working notes, 80 lines above its own
correction. Collapsing it into `G-0004` would point the fix at the wrong mechanism, so
the two root causes need separate fixes. Items 4 to 9 are hygiene and singletons that
belong to neither.

---

### 1. Two branch documents exist that no board item points at — this is what made batch D "state unknown"

**F-0005 and F-0006 are on disk. Zero board items reference either.**

```
F-0005-design-lead-palette-and-copy.md    7,142 bytes   14:24
F-0006-batch-d-protection-goals.md        8,331 bytes   17:30
```

`grep -l 'F-0005\|F-0006' workforce/ops/board/*.md` returns **nothing**. Batch D's four
items — W-0026, W-0027, W-0028, W-0029 — all carry `branch: null` while F-0006 sits
beside them describing exactly that work. Design-lead's items (W-0045, W-0080, W-0048,
W-0081, W-0082) have no `branch:` field at all, while F-0005 describes their work.

**So `fix-batch-D`'s state was fully discoverable from 17:30 onward and you could not
reach it, because the board had no edge to it.** You fell back to file mtimes to prove
an agent had been working, when a written handover was already on disk. This is the
single cheapest thing on this list to fix and the one that cost the most.

**Obvious and safe fix, your call:** set `branch:` on W-0026–W-0029 to F-0006 and on the
five design-lead items to F-0005. Nine one-line edits, no status changes. I have not
made them.

### 2. `F-0004` is referenced by an item and does not exist

One item carries `branch: fixes/F-0004-batch-e-retirement-income.md`. There is no
F-0004 in `workforce/branches/fixes/`. `fix-batch-E` was respawned at 16:25 — the log
note reads *"with W-0035, respawned after the original never reported"* — and the
original's branch document was never written.

**This is the orphan you were reading as "barely started".** W-0032 names no file paths
at all, so nothing in the board or the tree can be correlated to it; W-0035 shows four
of five named paths changed, most recently 18:18, so E is demonstrably working now.
Expect F-0004 when it closes out; until then this reference does not resolve.

### 3. Corrections are appended, never applied — so the newest truth is always at the bottom

This is the mechanism behind the W-0018 incident, and it is systemic rather than
one agent's slip.

**W-0018** is 281 lines. Line 199 reads **"No code changed."** The correcting
evidence — 163 passed, 568 assertions, Pint clean, the docblock rewritten — is at lines
~270–281, and the log records the correction at 17:05 as *"docblock already in tree"*.
A reader arriving at line 199 is 70% through the file and gets a false statement; the
truth is 80 lines further on.

**`tests/Persona/20-08-2026_run/COORDINATOR-HANDOVER.md`** is the same shape at scale —
567 lines, and its own section titles admit it:

| Section | Line | State |
|---|---|---|
| §3 Agents at power-down | 42 | **Stale for batch B**, by §14's own heading |
| §9 Tree snapshot | 163 | Superseded by §13 and the R-sections |
| §13 RESOLVED — batch D's state is no longer unknown | 356 | Supersedes §3 |
| §14 BATCH B COMPLETED — §3 IS STALE FOR BATCH B | 397 | Supersedes §3 |
| R1–R5 Verified on resume | 490–562 | Supersede §9 |

The corrections are all present and all honest. **They are just 350 lines below the text
they correct**, and a reader starts at the top. An artefact that can only be trusted when
read to the end is one nobody can safely skim, which is precisely when a coordinator
skims it.

**No safe mechanical fix** — editing the superseded text is a judgement call about
another agent's record, and several of these documents are live. The cheap mitigation is
a superseded-marker at the point of the stale claim rather than only at the correction.
Your call; I have not touched them.

### 4. The event log knows 12 of 69 items

`FORMATS.md`: *"Liveness is derived from the log, not from reports."* It cannot be.

| Measure | Count |
|---|---|
| Events logged today | 34 |
| **Distinct board items named** | **12** — W-0018, 0019, 0024, 0032, 0047, 0048, 0049, 0050, 0051, 0081, 0082, 0100 |
| Board items with a 2026-08-21 mtime | 64 |
| **`handoff` events logged** | **2** |
| **Items sitting at `status: handoff`** | **36** |

Thirty-four of the thirty-six handoffs happened without an event. `FORMATS.md` says *"If
a status cannot be derived from the log, the missing event is the bug"* — so this is 34
bugs by its own definition, and it is why the control centre cannot see what you can.

**Two further log defects:**

- **Four out-of-order timestamps** — line 17 (16:31:03 after 18:30:00), line 19, line 26
  (16:35 after 17:05), line 28 (16:20 after 16:36). The file is append-only but not
  time-ordered, so **"last line wins" is not safe** and anything deriving current state
  by tailing it will be wrong.
- **Four event names outside the `FORMATS.md` enum:** `decision` (5), `note` (2),
  `slack_posted` (1), `brief` (1). `decision` in particular is carrying CSJ rulings,
  which is load-bearing and undeclared. Either add them to the enum or route them
  elsewhere — right now a strict reader drops them.

`progress` is the most-used event at 11, which is the one thing the log is doing right.

### 5. Thirty-one of thirty-six `handoff` items have no handoff note

`FORMATS.md`: *"An item cannot move to `handoff` with an empty note."*

**Notes present (5 of 36):** W-0006, W-0010, W-0011, W-0017, W-0100.
Plus W-0050, which has a note directory but is at `queued`.

**Missing (31):** W-0007, W-0008, W-0009, W-0012, W-0013, W-0014, W-0015, W-0016,
W-0018, W-0019, W-0020, W-0021, W-0022, W-0023, W-0024, W-0025, W-0026, W-0027, W-0028,
W-0029, W-0030, W-0031, W-0034, W-0036, W-0039, W-0041, W-0045, W-0046, W-0052, W-0053,
W-0080.

You asked for the count and the list, not a fix. **In mitigation, and worth knowing
before anyone treats this as 31 defects:** the batch agents put the handover content in
their *branch documents* instead, which for a batch of ten items is arguably the better
artefact — F-0001 is 50,823 bytes, F-0003 is 50,504. The rule was written for one item
handed to one agent. **What is genuinely lost is per-item addressability**: `quality-lead`
receiving W-0022 has no note at `handoffs/W-0022/` and must know to look in F-0003.
Whether the rule or the practice is wrong is a doctrine question, not a correction — it
is the `00-precedence.md` §2.2 practice-drift check, and it belongs in the quarterly
review rather than in 31 catch-up notes.

### 6. Three different path conventions for `branch:`

| Form | Count |
|---|---|
| `workforce/branches/fixes/F-....md` | 12 |
| `branches/fixes/F-....md` | 17 |
| `fixes/F-....md` | 2 |
| `null` | 12 |
| `fix/widow-persona-cleanup` (a git branch, not a document) | 1 |

`FORMATS.md`'s own example is `branches/research/ps2522-position/`, so the 17 are
canonical and the other 14 are not. This is cosmetic until something resolves the path
mechanically, at which point two thirds of them break. **Safe fix:** normalise to
`branches/…`. Not made.

### 7. Two schema defects, both new items

- **`W-0131` uses `status: open`.** Not in the `FORMATS.md` enum
  (`queued|claimed|in_progress|handoff|review|gated|blocked|done`). One item.
- **`W-0131`, `W-0121`, `W-0122` sit outside every allocated block.** The ledger in
  `FORMATS.md` stops at **W-0119**. W-0120 onward has no recorded holder, which is
  exactly the condition that produced the duplicate W-0035 and W-0036 earlier today.
  **This is the live risk on this list** — the mechanism that prevented the last
  collision is out of numbers.

**Not a defect, so you can stop looking at it:** `W-0009` line 69 reads `status: 200`.
That is an HTTP status inside working notes, not frontmatter. My first pass flagged it;
it is a false positive.

### 8. One item stale since 2026-08-18

**W-0001** — `status: review`, `owner: build-lead`, `branch: fix/widow-persona-cleanup`,
`evidence: partial`, mtime 08-18 21:13. Three days at `review` with no event today. Its
body records *"php -l and pint NOT run (no PHP in sandbox)"*, a constraint that no longer
applies. W-0002–W-0005 are `done` from the same date and are fine.

### 9. The trunk file amended today is 2.5x its budget — and there is a structural answer

Stating it plainly rather than leaving it in a handover: **`05-perimeter.md` went from
8,329 to 19,758 characters against an 8,000 budget.** It is now the largest file in
`core/`, ahead of `charter.md` at 15,021. Under `00-precedence.md` §2.4 that is a review
trigger, not a cut, so it blocks nothing.

**The structural answer is not the sibling-file split, and it is not about length.** It
is that §1.2 is in the wrong kind of document.

`index.md` separates `constitution/` — *"doctrine: what we believe, what we forbid"* —
from `registry/` — *"where things live"*. By that taxonomy the two halves of the map
belong in different places:

- **The table (§1.1) is doctrine.** Which regimes this file covers, and that naming one
  asserts nothing about the law, is a position. It is gated, it changes rarely, and it
  belongs in the constitution.
- **The expanded rows (§1.2) are a registry.** `routes/api.php:1155`,
  `EnsureFullEstateAccess.php:37-39`, `app/Http/Kernel.php:106`, six named
  `UserConsent` constants — these are *where things live*, and the Cartographer already
  maintains exactly this class of fact.

**The evidence that this matters is in this sweep.** Every path and line number in §1.2
is a `00-precedence.md` §2.1 verifiable fact that drifts whenever the code moves — I
corrected two of them (`1151` to `1155`, five consent types to six) on the way in, and
that was against a document one day old. Housing them in a CSJ-gated constitution file
means routine drift correction churns a gated document permanently. §2.1 makes those
corrections autonomous, so nothing is blocked — but the gate exists to make amendment
deliberate, and a file that must be edited every time a route moves defeats it.

**The cost, stated honestly:** the anchoring is what makes the map usable at the point of
use. An agent landing on "unmapped" wants the code location in the same breath as the
status, and splitting them makes that two lookups instead of one. That is a real
trade-off, not a free win, and it is CSJ's call — the map was installed in §1 on CSJ's
ruling.

**Not a recommendation to act on now.** Offered because you asked whether the sweep
turned up a structural answer, and because the quarterly review under §2.2 is where it
should be argued rather than mid-run.

---

### Addendum, 18:30 — the four items that arrived during and after the snapshot

Closing the two I named but had not analysed, plus two that arrived since. **The board
went 68 to 72 items in seventeen minutes.**

| Item | Status | Branch reference | Verdict |
|---|---|---|---|
| W-0121 | `handoff` | `…/F-0010-batch-j-consolidation-red.md` | **Consistent.** F-0010 exists (batch J). The edge is wired in both directions. |
| W-0122 | `queued`, `branch: null` | — | **Consistent.** Its own `source` says it was found while fixing W-0121 and is "explicitly out of that scope". |
| W-0131 | `open` | `null` | Schema defect, item 7. |
| W-0151 | `queued` | `branches/fixes/F-0008-batch-g-lpa.md` | **Consistent**, and correctly wired. |

**Good news that refines item 1: five of seven branch documents are correctly wired.**
F-0001, F-0002, F-0003, F-0008 and F-0010 all have inbound references. **Only F-0005 and
F-0006 have none.** So RC-1's board-facing half is not a systemic inability — most agents
do it — it is two specific documents, which makes the nine one-line edits an even
cheaper fix than I said.

**Item 7's block-ledger breach is escalating, not static.** The ledger stops at W-0119.
**Four items are already past it** — W-0121, W-0122, W-0131, W-0151 — and W-0151 was
created at 18:29, after I first raised it. This is the live risk on the list and it is
still growing.

**One thing worth a precedence look, not mine to decide.** W-0122 is titled *"Fyn's
holding creation carries a second copy of the units/price/value rule"* and is filed
`severity: medium`, `constitution_refs: [07-quality-bar]`. A second copy of one rule
inside Fyn is the exact shape of **CLAUDE.md Rule 20**, which `00-precedence.md` §1 ranks
above everything including the Chief of Staff, and which makes consolidating the copies
*part of the fix* rather than an improvement. **I am not reclassifying it** — severity is
the owner's. Flagging that its constitution reference may be the wrong one.

---

## Closed — deliberate non-actions, not omissions

Recorded so a later sweep does not reopen them as gaps.

- **The trunk is not mirrored to the fynlaBrain vault, and should not be.** No workforce
  or constitution content is mirrored there. The vault's convention for workforce
  material is dated month-folder documents — the only such artefact is
  `August/August13Updates/2026-08-13-fynla-workforce-design.md`, which names
  `05-perimeter.md` without digesting it and is therefore **not falsified** by today's
  amendment. Starting a `core/` mirror would invent a convention rather than follow one.
  **Agreed and closed by team-lead, 2026-08-21.** Nothing stale to repair.
- **`00-precedence.md` §2.7's size table was left at its 2026-08-13 figures.** A dated
  measurement is a record of what was true on that date, not a typo. The current figure
  is in item 9 above.
- **The dated body of `2026-08-21-perimeter-regime-map-proposal.md` was left as written**,
  with the two mechanical corrections recorded in an appended installation record rather
  than edited into it. Same principle.

---

## Not done, and why

- **No repairs of any kind.** Statuses, branch documents, working notes and application
  code are untouched, per the dispatch. Every "safe fix" above is described, not applied.
- **W-0121 and W-0122 are not analysed.** They were created at 18:24 and 18:25, after my
  correlation pass. Named so the next sweep does not treat them as pre-existing.
- **I did not verify that each item's *fix* is correct** — only whether the tree shows
  activity consistent with its recorded status. Correctness is `quality-lead`'s.
- **`F-0004` is absent rather than wrong.** I did not chase `fix-batch-E` for it.
- **No per-item contradiction table for all 69.** After correction (below) the honest
  finding is that the status field is largely true, so a 69-row table of "consistent"
  would bury the eight things that matter.

## Assumptions

- **That "files this item names are changed" is weak evidence.** It is: six agents share
  this tree and items name shared paths. I therefore treated every apparent contradiction
  as a lead to chase by reading the item, never as a finding. That is why the
  contradiction section is short — the leads did not survive being checked.
- That `claimed` with no code is correct rather than drift, because `FORMATS.md` has the
  coordinator claim at dispatch.
- That items W-0002–W-0005, dated 08-18 and `done`, are genuinely closed from a prior
  run and out of today's scope.

## Method — and one correction, because the next sweep must not repeat it

My first correlation pass reported **W-0025 and W-0029 as `handoff` with zero tree
evidence**. Both were wrong, and I caught it before reporting because the working notes
named test files that felt too specific to be fictional.

**Cause:** `git status --porcelain` collapses untracked files inside a wholly-new
directory to the directory alone — `?? tests/Feature/Chattels/`, not the file. Sixteen
new directories were collapsed that way, including `app/Services/Consent/`,
`tests/Feature/Goals/` and `tests/Unit/Support/`. Every board item whose evidence is a
**new** test file therefore scored zero.

**Fix: `git status --porcelain -uall`.** It took the change set from 621 paths to 681 and
turned W-0025 from 0/2 to 2/2 and W-0029 from 0/2 to 2/2. **Anyone re-running this sweep
must use `-uall`** — without it the report accuses exactly the agents who wrote new tests.

## Needs

- **Your call on nine one-line `branch:` edits** (item 1) — W-0026–W-0029 to F-0006, the
  five design-lead items to F-0005. Highest value on this list.
- **Your call on the block ledger** (item 7) — W-0120+ has no allocated holder and three
  items are already there.
- **A doctrine question, not a correction** (item 5) — branch documents have replaced
  per-item handoff notes in practice. That is `00-precedence.md` §2.2 practice drift and
  it goes to the quarterly review, or to CSJ, not into 31 retrospective notes.

## Noticed — outside my remit, routed

- **chief-of-staff:** the event log cannot support the liveness derivation `FORMATS.md`
  assigns it — 12 of 69 items, 2 of 36 handoffs, and not time-ordered. The daily brief
  and any control-centre view built on it are reading a twelfth of the run.
- **chief-of-staff / whoever owns `FORMATS.md`:** four event names in live use
  (`decision`, `note`, `slack_posted`, `brief`) are outside the declared enum, and
  `decision` is carrying CSJ's rulings.
- **build-lead:** W-0001 has been at `review` since 2026-08-18 with `evidence: partial`
  and a blocker (no PHP in sandbox) that no longer applies.

---

# Delta pass — 2026-08-21, 18:37

**Scope:** the 29 board items created or modified after the 18:13:52 snapshot, the three
branch documents that did not exist then (F-0007, F-0009, F-0010), and the four fixes
team-lead made from the first pass. **Nothing re-swept that the first pass cleared.**
Nothing repaired here either.

## Your four fixes, verified

Not taking them on report — checked against the tree.

| Fix | Verified |
|---|---|
| **Nine `branch:` edges repaired** | **Closed, and better than that: every one of the nine branch documents now has an inbound reference.** F-0005 and F-0006 were the only unreferenced pair and both now resolve. RC-1's board-facing half is shut. |
| **Block ledger through W-0170** | **Holding.** Every ID issued since is inside its holder's block — W-0111–0113 (`fix-batch-I`), W-0121–0122 (`fix-batch-J`), W-0131 (`persona-passA3`), W-0151 (coordinator, filed for `compliance-lead`). No unallocated ID in the delta. |
| **W-0131 `open` to `queued`** | Confirmed. No status outside the enum anywhere on the board. |
| **Event enum reconciled** | Accepted, and **you were right that the enum was the stale half** — I framed the log as the defect and the direction of the correction was the other way for five of the names. `decision` carrying CSJ's rulings is the case that would have done real damage if renamed. |

## What the delta adds

### D1. Handoff notes are not dying out — the afternoon agents write them

The first pass found 31 of 36 `handoff` items with no note and filed it as practice
drift. **The delta sharpens that: the newer batches are writing them.** W-0047, W-0049
(`fix-batch-F`) and W-0051 (`fix-batch-I`) all have notes, written this afternoon.
W-0121 (`fix-batch-J`) does not.

So the picture is not "the rule is dead" but "the morning batches used branch documents
instead and the afternoon ones do both". That is a **practice converging on the rule**,
which is a materially different input to the quarterly review than practice abandoning
it. Your decision to leave the 31 unwritten is unaffected — this is about how to read
them, not whether to backfill.

### D2. Two orphans remain, both already known, neither new

- **`fixes/F-0004-batch-e-retirement-income.md`** — still referenced, still absent.
  `fix-batch-E` has not written it. The only unresolved branch reference on the board.
- **W-0001's `branch: fix/widow-persona-cleanup`** — a git branch in a field that holds
  document paths everywhere else. Cosmetic, and part of item 8's stale item.

**Three path conventions persist** (item 6): `branches/…` is now clearly dominant, with
F-0002 and F-0010 on `workforce/branches/…` and F-0001 and F-0004 on `fixes/…`.

### D3. No status contradictions in the delta

All 29 delta items are consistent. The new `queued` items with `branch: null` —
W-0111, W-0112, W-0113, W-0122, W-0131 — are correctly queued: each was raised by an
agent working a different item and explicitly scoped out of it, which their `source`
fields say.

---

## The folding question — do not fold W-0121 and W-0122, and here is the rule that makes all three answers consistent

You asked whether they are the same shape as the two pairs you have already folded. **They
are not**, and the distinction is mechanical rather than a matter of taste.

**The test is how many mechanisms the pair names, not how general each item is.**

| Pair | Mechanisms | Verdict |
|---|---|---|
| W-0048 / W-0082 | **One** — the Tailwind safelist | Fold. Your call was right. |
| W-0102 / W-0103 | **One** — an LPA role-conflict check that does not exist; W-0102 is one instance of it | Fold. Your call was right. |
| **W-0121 / W-0122** | **Two** — `app/Support/HoldingValuation.php`, and a separate inline copy in `CoordinatingAgent` | **Do not fold.** |

**Why one item, one fix is right for the first two and wrong for the third.** Folding a
one-mechanism pair is what prevents the Rule 20 disease: two items produce two fixes that
someone then has to keep in lockstep. Folding a **two**-mechanism pair causes it — the
fix lands in one mechanism, the item closes, and the second copy survives unrecorded.

**Verified in the code, not inferred from the titles:**

- `app/Support/HoldingValuation.php` exists (W-0039's one home) and is read by
  `InvestmentController`, `StoreHoldingRequest` and `UpdateHoldingRequest` — **and by
  nothing else**.
- `app/Agents/CoordinatingAgent.php:3172-3176` computes its own valuation inline from an
  allocation percentage, with `quantity` never set. It does not touch `HoldingValuation`.

W-0121 is a **branch-order bug inside** the shared mechanism — an inherited unit count
beating a figure the user typed. W-0122 is **a second mechanism existing at all**. Fixing
one does not fix the other, and the work is different in kind: one is a conditional, the
other is routing a Fyn tool through a shared helper.

### The part that actually needs your decision — W-0121 cannot be signed off as written

**W-0121 is at `handoff` and its acceptance criterion 5 reads:**

> *"The rule lives only in `app/Support/HoldingValuation.php`. No branch of it in a
> controller, a request, or a component (Rule 20)."*

**The enumeration and the cited rule disagree.** `CoordinatingAgent` is an agent — not a
controller, a request, or a component — so the criterion **as worded is met**. Rule 20
enumerates nothing and admits no exception, so the criterion **as cited is not met**, and
W-0122 is the proof sitting on the board.

`quality-lead` judging W-0121 will reach opposite conclusions depending on which half of
that sentence it weighs. **That is a defect in the criterion's wording, not in the item
boundary** — and it is exactly the failure the trunk's one-home rule exists to stop.

**Recommendation, yours to take:** keep the two items, and reword acceptance 5 to say
which it means. Either *"no branch of it anywhere, including agent tool handlers — see
W-0122"*, which makes W-0122 a prerequisite for W-0121's sign-off, or drop the Rule 20
citation and let criterion 5 mean only what it lists. **I have not touched it** — it is
`build-lead`'s item and `quality-lead`'s judgement.

Batch J already did the honest half of this in code: W-0122 records that the class
docblock's claim *"Fyn has no holding-entry surface"* was wrong, and has corrected it to
point at W-0122.

## Not done, and why

- **`fix-batch-E`'s F-0004 not chased.** Still the one unresolved reference.
- **W-0132 and W-0133 do not exist yet** — the ledger marks them "pending live
  confirmation" for `persona-passA3`. Nothing to sweep.
- **Acceptance criterion 5 left as written**, per the same report-do-not-repair rule as
  the first pass.

## Needs

- **Your call on W-0121's acceptance 5** — the wording, not the item boundary. It is the
  only thing in this delta that can produce a wrong sign-off.

---

# RC-2 remediation — superseded-markers placed, 2026-08-21 18:45

**Seven markers placed at the point of the stale claim.** The stale text was left exactly
as written in every case — a claim honestly made and later disproved is the record of how
the mistake happened, and deleting it destroys the only evidence of that.

## Board items marked (all at `handoff`)

| Item | The stale claim | What supersedes it |
|---|---|---|
| **W-0018** | *"No code changed."* | The `team-lead` note ("Found on arrival") — two of four acceptance items were already in the tree. **The worked example; this is the one that cost the hour.** |
| **W-0031** | *"three values no surface offers are dead options"* | The `build-lead` note ("the item's premise was wrong") — `PersonalInformation.vue:326-334` offered all three live, and all three returned HTTP 500. |
| **W-0017** | *"One form, four gaps, one fix"* | The `build-lead` note ("Important correction to the item as filed") — there are **two** DB pension forms, and they had drifted. |
| **W-0053** | *"there is no route back to it"* | The `build-lead` note ("the route back DOES exist") — `WillPlanning.vue:97-101` renders "View Will" whenever `markComplete()` has run. |
| **W-0100** | *"Live on production since 2026-03-16 — five months"* | Marked **QUALIFIED**, not SUPERSEDED: the badge is real and the overclaim stands, but reaching "Compliant" needs a specific register-then-Save-Draft sequence. Changes urgency, not wrongness. |

## `COORDINATOR-HANDOVER.md` marked (your file, on your authority)

- **§3 Agents at power-down** — marked superseded in part, naming which rows: batch B by
  §14, batch D by §13, batch E by its 16:25 respawn, and current agents by §R4.
- **§9 Tree snapshot** — marked superseded, correcting three things a reader would
  otherwise act on: the 548-path figure (681 at 18:13, and only with `-uall`), the claim
  that `F-0005` is batch D's document (it is design-lead's; batch D's is `F-0006`), and
  the branch-document list (nine now exist, all referenced, `F-0004` alone still absent).

## Nothing marked on a live item — and none needed it

I scanned all 27 items at `claimed`, `queued` or `review` for the same shape, since you
asked for a list rather than edits. **The list is empty.** One keyword hit, W-0104:89,
is subject-matter ("wrong for a missing date"), not a self-correction. **No branch
document was touched.**

## W-0045 is the pattern that needs no marker, and the rule now says so

W-0045 corrected itself the better way: *"An earlier revision of this item said CSJ had
to decide 'fix or grandfather'... That was wrong and is retracted."* **The wrong text is
gone** — characterised rather than preserved — so there is nothing stale left to trip
over. `FORMATS.md` now names this as the preferred form, with markers reserved for cases
where the original wording is itself evidence.

## The rule, written into `FORMATS.md`

New section, *"Correcting an append-only document — mark the stale claim, not only the
correction"*, placed directly beneath your read-from-the-bottom corollary, because the
two are the reader's half and the writer's half of one fix. **The writer's half is the
one that works** — a rule depending on every reader remembering to skip to the end fails
the first time someone skims.

Five rules travel with it: marker never rewrite; **point by note date, author and quoted
phrase, never by line number** (inserting a marker shifts every line below it, so a
line-number citation is stale the instant it is written — the rule's own failure mode,
made inside the fix); distinguish SUPERSEDED from QUALIFIED; prefer W-0045's pattern;
never mark another agent's live item.

## Method, and its limit

Candidates were found by grepping all 47 eligible items for self-correction language
(*correction*, *superseded*, *was wrong*, *retracted*, *no code changed*, *overtaken*,
*stale*), then **reading each hit to separate a document correcting itself from the word
"stale" used about subject matter.** Most hits were the latter — cache staleness, a stale
`ownership_percentage` — and were discarded.

**The limit: this finds corrections that announce themselves.** A later note that quietly
contradicts an earlier one without saying so would not be caught by any keyword. Only
reading all 47 items end to end would find those, which is the cost the `FORMATS.md` rule
is meant to stop us paying again.

---

# Trunk pointer correction — `registry/sources.md`, 2026-08-21 19:00

**Third and fourth autonomous corrections under `00-precedence.md` §2.1**, recorded here
with the two from the regime-map installation, which is where this precedent was set.

## The judgement — why this was mine and not CSJ's

Two sentences in `05-perimeter.md` said the dated source register did not exist. It now
does, so both were **false statements of fact, not doctrine**. That is §2.1's class:
correcting a number or a path to match reality is not a doctrinal change.

**The clause that needed checking** was the one addition beyond a pure pointer —
*"It is scoped to what has been read so far — an absence there is not clearance."* If
that authored a rule, it was CSJ's, because a branch may apply rules but never write
them and this file is the trunk.

**It does not.** `registry/sources.md` §5 already states it of itself: *"Absence of a
regime is not clearance. `05-perimeter.md` §1.1 is the map of what is covered; this is a
map of what was read. They are different questions."* The register points at §1.1; this
makes §1.1 point back. **A reciprocal cross-reference to an existing statement, forbidding
nothing new** — and §7.3 already prohibits treating anything as clearance. Applied.

## Verified before installing, not assumed

| Claim in the proposed text | Checked |
|---|---|
| The register exists | `core/registry/sources.md`, 12,301 bytes, created 18:53 |
| Path form `registry/sources.md` | **Confirmed canonical.** The constitution uses `registry/<file>.md` in seven places — `registry/people.md` §3.2 at `05-perimeter.md:4`, `registry/capabilities.md`, `registry/rhythm.md`. Compliance flagged it was trusting the pattern; the pattern holds. |
| "carries the standing commencement warnings" | True — §4 *Standing warnings*, covering the 2023 Act's unread commencement section and DMCC Chapter 2 |
| House style, `Owner: CSJ. Amendments gated.` | True |

**One correction to the relay:** the register holds **13** source rows, not eleven —
A1–A10, B1, C1–C2. No count entered the trunk, so nothing installed is wrong; flagged
because it is mechanically checkable and this is the desk that checks them.

## Added beyond the relay, and flagged as such

The relay was two sentences. **I added a third, and it should be reverted if you disagree
rather than left because I wrote it.**

§1.1's commencement warning was narrower than the evidence now supports. The register
records a **third** failure mode: the lasting power of attorney registration fee moved
£82 to £92 on 17 November 2025, and **the amount is not in the provision anyone would
cite** — that regulation says only that a fee shall be payable. An agent citing it and
dutifully re-reading it finds nothing wrong, twice. Nine months of a wrong number shown
to users on three surfaces (W-0109).

Leaving §1.1 saying *"commencement is the repeat failure mode"* would make the trunk hold
a **partial copy** of a taxonomy whose complete form lives in `registry/sources.md` §2 —
the one-home problem (`index.md` rule 6), committed inside a correction. So §1.1 now
**points at §2 and does not restate it**, naming only the case that matters most to a
reader: the value is not where you would look.

## A finding about staleness itself, offered not acted on

The register's §2 organises sources by **where they live, because that predicts how they
go stale** — body text moves by visible amendment, a Schedule or an externally-set amount
moves under a separate order leaving the parent unchanged, an undated page changes with
no signal at all.

**That taxonomy describes this desk's own facts, and the middle class is the one that
bites.** A line number cited in the trunk moves when someone edits *above* it: the symbol
it names is untouched, so an agent re-reading the symbol finds nothing wrong. **That is
the fee case exactly, one level down.** It is not hypothetical — the superseded-marker
rule written into `FORMATS.md` earlier today forbids line-number citations for precisely
this reason, after my own first draft used them.

The verifiable facts §2.1 makes me check split the same way: **Class A**, a file path or a
command name, which breaks loudly; **Class B**, a line number or a count, which goes wrong
silently while the thing it points at is still correct; **Class C**, a claim about the
world with no local signal at all, like the vault's document count.

**Not written into doctrine.** It is a proposal, and the trunk's own rule is that reviews
propose and CSJ ratifies. Raising it here so it survives the run; it belongs in the
`00-precedence.md` §2.2 quarterly review alongside the §1.2-is-registry-material argument,
which it strengthens.

## Untouched, as instructed

Compliance's four new §6 open questions (their report §7, numbered 7–10) are **not** in
the trunk — adding them is CSJ's. The **§7.3 product-binding amendment is batched for CSJ
and was not pre-empted.** `G-0003` updated but **left `open`**: its migration scope was
deliberately dropped, and `sources.md` covers one instrument family with no rows for
privacy, consumer protection or advertising. Closing is a judgement for whoever judges it,
and it is no longer blocked by the trunk contradicting the file.

---

# Class B audit of the index's own facts — 2026-08-21 19:20

Team-lead asked whether anything in my world has the shape compliance found in
`registry/sources.md` §2: **a fact the index tracks by watching the wrong thing, so the
watcher is reassured while the value moves.**

**It does, it is the most-cited fact form in the trunk, and my own tooling is structurally
blind to it. The worked example is a correction I made this afternoon that was wrong
within the hour.**

## The shape, translated out of legal sources

| Class | In the register | In this index | Signal when it moves |
|---|---|---|---|
| **A** | Act or regulation body text | a file path, a command name, a class name | **Loud.** The reference breaks; `sweep.sh` catches it. |
| **B** | a Schedule, or an amount set outside the operative provision | **a line number, and any derived count** | **None.** The file still exists, the symbol is still in it, only the number moved — and nobody edited the thing being cited. |
| **C** | an undated web page | a claim about the world with no local source | **None, ever.** `CLAUDE.md`'s "693 vault docs" against an actual 1,514. |

## Finding 1 — `sweep.sh` cannot see a single line-number citation

The orphan check greps for `` `path.ext` `` with the closing backtick **immediately after
the extension**:

```
grep -oE '`[A-Za-z0-9._/-]+\.(md|php|js|json|sh|vue)`'
```

`` `routes/api.php:977` `` has `:977` before the backtick, so **it does not match — those
citations are not checked loosely, they are never seen at all.** And the resolution test
underneath is `[ -e "$ROOT/$ref" ]`: existence, never line accuracy. **The tool I own
watches the parent and is reassured while the value moves.** That is the fee case exactly,
one level down.

**Scope: 48 distinct line-number citations across `workforce/core/`**, 45 resolvable to a
file, 12 into files edited today.

## Finding 2 — the rot is accumulated, not today's churn

Checked against `HEAD` as well as the working tree, so today's uncommitted work is not
carrying the blame:

| Citation | Trunk claims | Actually points at | Wrong at HEAD too? |
|---|---|---|---|
| **`CLAUDE.md:449–456`** (`07-quality-bar.md:25`) | the browser-testing rules, cited as **already-binding doctrine** | `- Tax Year: April 6 - April 5` | **Yes.** Rules were at 461 at HEAD, are at **511** now. Off by 12 then, **62 now.** |
| `app/Console/Kernel.php:35` | a job running "at 08:30" | `->everyThreeMinutes()` | **Yes.** Identical at HEAD. Pure rot. |
| `routes/api.php:1477` | a bug-report endpoint | `postcode-lookup` | **Yes** — it was a What-If route at HEAD, and moved again today. |
| `app/Console/Kernel.php:18-66` | "27 entries", the schedule block | `*/`, a docblock terminator | Yes, minor. |

**`CLAUDE.md:449–456` is the one that matters.** `07-quality-bar.md` cites it as *"not new
doctrine — already binding"*, and `CLAUDE.md` is precedence rank 1. An agent following that
pointer to verify the claim lands in **UK Tax Context** and finds nothing about browsers.
The doctrine is real and correctly described; only the address is wrong, which is precisely
why nobody noticed.

A structural heuristic over the 38 code citations — does the cited line hold nothing but
`}`, `*/`, or whitespace — flags **5 more** as almost certainly displaced.

## Finding 3 — the demonstration, and it is mine

**This afternoon I corrected `routes/api.php:1151` to `:1155` for `cancel-subscription`,
verified it by grep, and wrote it into a CSJ-gated trunk document as a §2.1 fact
correction.**

**It is now at line 1163.** Another agent added 13 net lines to `routes/api.php` above it.
The route is untouched. The symbol greps instantly. Only the number moved, and nothing
anywhere signalled it.

Of the four line-number citations I wrote into the trunk today, checked at 19:20:

| Citation | Status |
|---|---|
| `routes/api.php:1155` (cancel-subscription) | **Wrong — now 1163** |
| `routes/api.php:977-986` (the `lpa` group) | **Wrong — now 985** |
| `config/services.php:60` (Revolut) | Correct |
| `EnsureFullEstateAccess.php:37-39` | Correct |
| `app/Http/Kernel.php:106` (`CaptureAwcCookie`) | Correct |

**Two of four wrong within three hours**, both in the one file another agent was editing.

**So the conclusion is not "check line numbers more often". A line number into a live file
is stale before it is read**, which means the §2.1 correction regime cannot fix this class —
it can only re-break it on a slower cycle. My "mechanical correction" produced a number that
was wrong by the time anyone opened the document.

**Fixed, in a form that cannot rot the same way** — both citations now name the symbol:
`the Route::prefix('lpa') group in routes/api.php`, and
`POST /api/payment/cancel-subscription, in routes/api.php`. Greppable, unambiguous, and
indifferent to anything inserted above.

## What I propose, and am not doing

Two changes, both for the `00-precedence.md` §2.2 quarterly review, since **reviews propose
and CSJ ratifies**:

1. **A citation rule: name the symbol, not the line.** `Route::prefix('lpa')`,
   `CaptureAwcCookie::class`, `### CRITICAL — Browser Testing Rules`. Cite a line only
   where nothing greppable exists, and mark those as Class B so they are re-checked rather
   than trusted. This is the same rule `FORMATS.md` now carries for superseded-markers,
   generalised from one artefact to every citation the trunk makes.
2. **A sweep check that can see them.** Extend `sweep.sh`'s regex to capture `:NNN`, then
   verify the line exists and — where the citing sentence names a symbol — that the symbol
   is on or near it. That converts an invisible class into a loud one.

**Not touched:** the other 43 line-number citations, including `CLAUDE.md:449–456`.
`07-quality-bar.md` is a ratified trunk file I was not asked to amend, and fixing 43
addresses one at a time is the treatment that does not work — the rule is the fix. Listed
here so the review has the evidence.

**One reading correction of my own, while I am at it.** I told team-lead earlier that
"commencement is the repeat failure mode in this codebase's legal citations". That was
true and too narrow: **the repeat failure mode is Class B in general** — a value that
lives somewhere other than the thing you would check. Commencement is one instance,
the registration fee is another, and a trunk line number is a third. §1.1 now points at
`registry/sources.md` §2 rather than restating a partial taxonomy.

---

# Second trunk pointer — §6 and `ops/open-questions.md`, 2026-08-21 19:45

**Refused as proposed, and applied in a narrower form that fixes the harm without CSJ.**
Team-lead asked me to judge the line rather than assume it; the line falls in a different
place than it did for the first pointer.

## Verified first

| Claim | Checked |
|---|---|
| `ops/open-questions.md` exists, sixteen questions | True — `Q-01` to `Q-16`, 18,823 bytes |
| Stable IDs, legacy mapping table | True — §3 maps the trunk's six, W-0050's (a)(b)(c), the LPA report's 7–10 and the consent review's 11–14 |
| Allocation rule stated **in that file** | True — §2.1 and §2.2, including *"Never number from `05-perimeter.md` §6"* |
| `Q-17` is the next free ID | True — §2.1. It appears in the file as the next marker, not as an allocated question |

## Why the pointer-only version is an amendment, not a correction

Three independent reasons, any one sufficient.

**1. It deletes ratified content.** The first pointer replaced a **false sentence** — the
register did not exist, then it did. This removes a **list CSJ ratified on 2026-08-13**.
`00-precedence.md` §2.5.2 is explicit that removal is riskier than addition and that a
deletion proposal must state what breaks. That is a proposal for CSJ, not a §2.1
fact correction.

**2. Seven citations inside this same file depend on §6's numbering** — §6.1, §6.4,
§6.5 (three times) and §6.1–6.4 (twice) — plus `registry/meetings.md:75` and
`registry/sources.md:129`. The legacy table resolves them, but at the cost of a hop to
another file for citations that today resolve in place. **Applying the relay literally
would have created, in one edit, the orphan-and-indirection damage documented three
sections above this one.**

**3. It moves regulatory questions out of the trunk.** `index.md` separates
`constitution/` (doctrine) from `ops/` (state), and `00-precedence.md` §1 makes
`05-perimeter.md` supreme for anything regulatory while `ops/` carries no precedence rank
at all. Where the open legal questions live is a standing decision, not a filing tidy-up.

## What was applied instead, and why it is inside the line

**The trunk's own §2 already establishes the pattern:** *"`ComplianceRules.php` is
canonical and is never restated here. In summary only, so this file is readable"* — with
a summary kept beside a named canonical source. §6 now does the same:

- **The six stay**, explicitly labelled `Q-01` to `Q-06` and **non-canonical**, with
  *"where the two differ, the consolidated file wins."*
- **The incompleteness is stated** — sixteen exist, ten never reached the trunk. That is
  the factual defect, and it is corrected.
- **The allocation rule is carried**, including that the next is `Q-17` and that numbering
  from §6 is what duplicated `Q-09`. It is quoted from `open-questions.md` §2, so this is
  a pointer to an existing statement, not authored doctrine — the same test that let the
  first pointer through.
- **The proposed deletion is recorded in §6 as proposed and not applied**, naming what it
  would break, so CSJ has the analysis staged rather than having to redo it.

**This is not two live versions.** One is labelled canonical, the other is labelled a
restatement that loses ties. That is the arrangement §2 has run since ratification.

## One thing found on the way

**`ops/open-questions.md` §2.2 asserts *"§6 is a pointer to this file"*.** It is not, and
was not when written — the file describes a trunk state that CSJ has not ratified. Not
mine to edit, and the §6 paragraph I added names the discrepancy so a reader hitting
either side sees it. **Flagged to `compliance-lead` via team-lead.**

## Untouched, as instructed

The three questions raised in a ruling whose gate was never actioned remain CSJ's. The
new file only prevents duplication for agents who know it exists, which is the point the
pointer addresses and does not solve.

---

# Mission-directory mismatch — 2026-08-21 20:05

**Report only. Nothing repaired** — the fix is a batch edit to `mission:` on 45 items,
which the dispatch excluded, and the one-line rename that would have been mine is the
wrong fix.

Board at time of check: **97 items** (72 at 18:25 — the run is still producing).

## The three questions, answered

### 1. Which is authoritative? `M-0002-capability-map`, and it is not close.

| | `M-0002-capability-map` | `M-0002-persona-fidelity` |
|---|---|---|
| File in `ops/missions/` | **Yes** | **No** |
| First use | 2026-08-18, committed in `6bbb7c523` | 2026-08-21 |
| Citing items | 1 — `W-0004`, `owner: cartographer`, `done` | 45 |
| What it is | *"Know what already exists before building anything"* — the prior-art mission | the persona-run defect programme |

**The number was allocated first and the file still exists.** `M-0002-persona-fidelity`
took a number that was already in use.

### 2. How many cite each — and the number that matters is not 45

| `mission:` value | Items | Resolves? |
|---|---|---|
| `persona-run-peak_earners-2026-08-20` | **47** | **No file** |
| `M-0002-persona-fidelity` | **45** | **No file** |
| `M-0001-state-truth` | 3 | Yes |
| `M-0003-unblock-merge` | 1 | Yes |
| `M-0002-capability-map` | 1 | Yes |

**92 of 97 board items cite a mission that does not resolve.** Five resolve. The
`mission:` field is decorative for 95% of the board, and `M-0002` is the small part
of that.

### 3. Renamed, or two missions sharing a number? Both — and the third thing is the finding

**`M-0002` is a genuine collision.** Prior-art discovery and persona-run defects are
unrelated programmes. Not a rename, and the same class as the duplicate W-0035/W-0036
and the two `F-0005` documents.

**But the persona work being split across two ids is not drift. It is a real distinction,
consistently applied, that nobody wrote down.** Tested across all 92 items by what each
says found it:

| `mission:` | Found by the tester | Found by a fix agent | Unclear |
|---|---|---|---|
| `persona-run-peak_earners-2026-08-20` | **41** | **0** | 6 |
| `M-0002-persona-fidelity` | **0** | **29** | 16 |

**Zero crossover in 70 classifiable items.** One id means *found by the persona tester in
a pass*; the other means *found by a fix agent while fixing something else*. Nothing in
`FORMATS.md`, the trunk or the missions directory defines either.

**This is `00-precedence.md` §2.2 practice drift in its textbook form** — the branches
invented a consistent convention against a doctrine that says nothing, and §2.2's rule is
that the likely fault is in the trunk, not in seventy items. **The convention is worth
keeping; it just needs a home and a number that is free.**

## Two more defects underneath it

**The id form is undefined, and the directory is the misleading half.** `FORMATS.md`'s own
work-item example reads `mission: 2026-08-13-fca-targeted-support` — a **date-slug**, not
`M-NNNN`. So `persona-run-peak_earners-2026-08-20` is the **conformant** form and
`M-NNNN` is the undocumented convention, which is the opposite of what the directory's
three files imply. `fix-batch-J` matched the parent item rather than the directory and was
right to; the directory is not the spec.

**There is no ledger for `M-NNNN` ids.** `FORMATS.md`'s block ledger covers `W-NNNN` only.
**That is precisely how `M-0002` was taken twice** — the identical mechanism that produced
the duplicate `W-0035`/`W-0036` before the W-ledger was written, reappearing in the one id
space nobody extended it to.

## Why nothing was applied, including the part that looked safe

- **Renaming `M-0002-capability-map.md` is wrong.** It is the legitimate holder.
- **Creating `M-0002-persona-fidelity.md` is worse** — it would entrench the collision by
  giving both claimants a file.
- **Creating `persona-run-peak_earners-2026-08-20.md`** would resolve 47 dangling
  citations with zero item edits, and it was tempting. **It is still not mine:** writing a
  mission file is defining a mission, which is the Chief of Staff's remit
  (*"turns CSJ's intentions into specified missions"*). I report that the index does not
  resolve; I do not decide what the missions are.
- **Renumbering the 45** is the batch edit the dispatch excluded, and several of those
  items sit at `handoff` awaiting a quality pass.

## What the fix is, for whoever takes it

1. **Name the convention** the board already follows — tester-found versus fixer-found —
   or decide it is one mission and collapse it. Seventy items are already consistent
   either way.
2. **Give each a file and a free number.** `M-0002` stays with `capability-map`.
3. **Settle the id form** in `FORMATS.md`: `M-NNNN-slug` or date-slug, one of them, since
   the example and the directory currently disagree.
4. **Extend the block ledger to `M-NNNN`**, or the next collision is already scheduled.

**Renumber last, and in one pass when the fix agents stand down** — 45 items at various
statuses, several mid-quality-pass, is exactly the shape the board-lag rule warns about.

---

# Mission ledger installed — 2026-08-21 20:15

**Snapshot: 101 board items, 20:00.** The board moved from 72 to 101 during this sweep and
is still moving; every count here is as of that moment and says so in the file too.

## Done

**`FORMATS.md` now carries a mission ledger**, in the shape of the W-block table and
directly beneath it: all five `mission:` identifiers in live use, which resolve to a file,
how many items cite each, `M-0004` named as next free, and the same rule that a mission id
is allocated before use with block-less agents routing to the coordinator.

**`M-0002-capability-map` is recorded as the legitimate holder**, with
`M-0002-persona-fidelity` marked as colliding with it rather than replacing it.

**The identifier-form contradiction is named, not resolved.** The work-item template gives
a date-slug (`2026-08-13-fca-targeted-support`); the directory holds three `M-NNNN` files;
nothing adjudicates. The ledger records **both forms deliberately and says it endorses
neither**, with an interim rule — allocate in whichever form the parent work already uses,
never invent a third — and the question routed to CSJ.

**The counts are carried into the file as findings, not corrections:** 96 of 101 items
cite a mission that does not resolve; the tester-found and fixer-found ids show **zero
crossover across 70 classifiable items**; and the file states explicitly that those 96
must not be "corrected", because naming the convention and renumbering is one CSJ decision
that goes last, in one pass, when the fix agents stand down.

## Found while installing it — `FORMATS.md` had two W-block ledgers

Not a third table added to two; **a second table already existed.**

| Table | Range | State |
|---|---|---|
| *"Allocated blocks — 2026-08-21 persona run"* | W-0006 – W-0069 | no `State` column, **unmarked** |
| *"Block ledger — 2026-08-21, current"* | W-0090 – W-0180 | marked current |

**180 lines apart, and only the lower one said it was current** — the exact RC-2 shape,
in the file where the rule against it was written an hour earlier. An agent reading top-down
allocates from a table that stops at W-0069.

**Marked, not merged**, using the rule as written: a superseded-marker at the earlier table
pointing at the current one, the text left intact because those blocks were really issued.
Merging them is a judgement about someone else's live record and is not mine.

**And the marker surfaced a gap neither table covers: `W-0070` – `W-0089` are unallocated
and appear in neither.** Named in the marker so the next allocation does not land there by
accident.

## Not done, and why

- **No mission file created.** Writing one defines a mission — the Chief of Staff's, not
  the index's. The ledger records that 96 citations do not resolve; it does not resolve
  them.
- **No `mission:` field edited** on any item.
- **The two W-ledgers were not merged**, only marked.
- **The id form was not decided.**

---

# Identifier-space audit — 2026-08-21 20:30

**Report and proposal only. No ledger created**, per the dispatch: the spaces to govern
are named below and are yours to approve.

**Snapshot 20:30, 102 board items.**

## The live one first — `F-0009` is issued twice, right now

```
F-0011-batch-g-native-handoff-protection-ownership.md   19:08   12,939 b   3 inbound
F-0009-batch-i-onboarding-spouse.md                     20:02   42,774 b   6 inbound
```

Two different batches, one number, **both active**. Referenced by nine board items between
them — W-0033, W-0043, W-0044 against batch G; W-0051 and W-0111 to W-0115 against batch I.

**`FORMATS.md`'s own collision rule decides it: the fewest external references gets
renumbered, not the newest.** So **batch G's renumbers to `F-0011`** and batch I keeps
`F-0009`, even though batch G's was written **first**. Worth stating plainly, because the
instinct is first-come-first-served and the rule deliberately says otherwise — the cheap
thing to move is the file, not nine citations.

**Not renumbered by me:** both agents are live and it is their file. Escalated.

**`F-0004` has landed** (19:48, 31,706 bytes). The orphan reported at 18:13 is closed —
every branch reference on the board now resolves.

## Why `F-NNNN` collides when `W-NNNN` stopped

**It is the only space where the id is chosen by the agent, at close-out, by reading the
directory** — rather than issued by the coordinator at dispatch. Two agents finishing
within an hour of each other both see `F-0008` as the highest and both take `F-0009`.
That is the same `ls`-then-write race the W-block ledger was built to stop, in the one
space nobody extended it to. **This is the second F-collision today** — the two `F-0005`
documents were the first.

## Every identifier space in the tree

| Space | Ledger | Rule in `FORMATS.md` | In use | Collisions | Next free |
|---|---|---|---|---|---|
| `W-NNNN` board | **Yes** | Yes | 102 items | W-0035/W-0036, fixed this morning | W-0181 |
| `M-NNNN` mission | **Yes** — added 20:15 | Yes | 5 identifiers | **M-0002 twice** | M-0004 |
| **`F-NNNN` branch doc** | **No** | **No** | F-0001 – F-0010 | **F-0005 (resolved), F-0009 (LIVE)** | **F-0011** |
| `G-NNNN` gap | **No** | Shape only, no allocation rule | G-0001 – G-0005 | **None** | G-0006 |
| `GATE-NNNN` | **No** | Shape only | GATE-0001 – GATE-0004 | **None** | GATE-0005 |
| `PR-NNNN` provisioning | **No** | Shape only | PR-0001 | **None** | PR-0002 |
| **`S-NN` interview** | **No** | **Not mentioned at all** | S01 – S07 | **None** | S08 |
| `Q-NN` question | n/a | **Yes** — in `ops/open-questions.md` §2, not here | Q-01 – Q-16 | Q-09 duplicated, merged | Q-17 |

**`G-NNNN` is clean.** `G-0005` was filed by reading the directory and taking the next
number, which is the method that produced every collision so far — **it worked this time
because only two agents have ever written a gap.** The method is unsafe; the outcome was
lucky. Same for `GATE` and `PR`, both single-author.

**`S-NN` is a space nobody has enumerated** — seven ratified interview records, no
allocation rule, not referenced in `FORMATS.md` in any form. Low risk today because one
agent writes them, identical in kind to `G-NNNN`.

## Two naming rules, one of which does not exist

**Handoff notes have a rule and it is half-followed.** `FORMATS.md` specifies
`handoffs/W-NNNN/<from>-to-<to>-<date>.md`. Live: **12** use the full agent names
(`build-lead-to-quality-lead-2026-08-21.md`), **6** use short forms
(`build-to-quality-2026-08-21.md`). Both resolve for a human; neither is mechanically
matchable to an agent name. One directory, `persona-peak_earners-2026-08-20/`, is a
run-slug rather than a `W-NNNN` — the same id-form split the mission ledger records.

**Reports have no naming rule at all.** `FORMATS.md` gives a body template and specifies
only `brief-YYYY-MM-DD.md`. **Six forms are in live use:** `YYYY-MM-DD-slug`,
`YYYY-MM-DD-W-NNNN-slug`, `YYYY-MM-DD-F-NNNN-slug`, `brief-`, `friday-`, `monday-`.
Nothing is broken by this — reports are found by reading the directory — but it is the
one artefact class with no convention, and `friday-`/`monday-` pair with the
`friday_delta`/`monday_plan` log events adopted this afternoon.

## Three unenumerated things in `ops/`

- **`ops/branches/` is an empty directory.** The real branch documents live at
  `workforce/branches/`. `FORMATS.md` says `branches/<type>/<slug>/`, which resolves to
  the empty one if read relative to `ops/`. **A trap, not a defect yet** — nothing has
  been written there.
- **`ops/ui/index.php`**, 15,220 bytes. Not a document, not in any index.
- **`ops/interviews/divergences.md`** carries no id in a space where everything else does.
- `ops/triage/` is empty.

## What I propose to govern, and in what order

**Not created. Name which of these you want and I will build them.**

1. **`F-NNNN` — urgently, and differently from the others.** Two collisions today, and it
   is agent-allocated at close-out rather than coordinator-issued at dispatch. **A ledger
   alone will not fix it**, because the agent writing the file is not reading the ledger
   at that moment. The fix is either to **issue the `F` number at dispatch alongside the
   W-block**, or to name the document by its batch rather than a number
   (`F-batch-i-onboarding-spouse.md`), which removes the shared counter entirely. **I
   recommend issuing at dispatch** — it keeps sortable ids and puts allocation back with
   the coordinator, where it already works.
2. **`G-NNNN`, `GATE-NNNN`, `PR-NNNN` — one shared rule, not three ledgers.** All three
   are low-volume and multi-author-capable. A single line in `FORMATS.md` — *allocate by
   claiming the number in the file's frontmatter before writing the body, and never
   from a directory listing* — costs nothing and closes the mechanism.
3. **`S-NN` — record that it exists.** One line naming the space and its next free number.
4. **Report naming — a convention, or an explicit decision that there is none.** Either
   is fine; the current state is neither.

**Nothing here needs doing tonight except `F-0009`.**

---

# Identifier governance built — 2026-08-21 20:20

**Snapshot 20:13: 102 board items, 11 branch documents.** All four decisions installed in
`FORMATS.md`, beneath the mission ledger, so every allocation rule now sits in one place.

**`F-0009` verified resolved before building on it:** `F-0011-batch-g-native-handoff` and
`F-0009-batch-i-onboarding-spouse` both present, no duplicate remaining.

## What was installed

**1. `F-NNNN` issued at dispatch, as part of the dispatch contract, not a ledger.** The
agent never chooses its own number and never reads the directory. The section states why a
ledger would not have worked — the agent writing its branch document at 20:02 is not
reading a ledger at that moment — and records both collisions as the evidence, with
`F-0012` named next free.

**The detail worth keeping:** batch G's document was written **first** and still moved,
because the rule is fewest external references. Written down so the next person meets the
reasoning rather than assuming a mistake.

**2. One rule for `G`, `GATE` and `PR`** — claim the number in the frontmatter and save
before writing the body, never from a directory listing. **`G-NNNN` is recorded as clean
and the method as unsafe**, in those words: it held because two agents have ever written a
gap, and the same method produced `W-0035`/`W-0036`, `F-0005`, `F-0009`, `M-0002` and
`Q-09`. **"Do not read the absence of a collision as evidence the method works."** Next
free recorded for all three.

**3. `S-NN` recorded** — `S01` to `S07`, next `S08`, with the reason: a space this file
does not mention at all is worse than one with a weak rule, because nobody can follow a
convention they cannot find. `divergences.md` noted as carrying no id.

**4. Report filenames — recorded as a decision that there is no convention.** Six forms
listed, nothing broken, `friday-`/`monday-` tied to the log events. Written as a decision
rather than left as silence, because the previous state was neither a convention nor a
choice and an agent could not tell which. Imposing one later is CSJ's.

**5. The three unenumerated things**, with `ops/branches/` first and framed as the trap it
is: this file's own `branches/<type>/<slug>/` **resolves to the empty directory when read
relative to `ops/`**, so an agent checking whether the path exists gets **yes** and writes
to the wrong place. **The Class B shape exactly — the check succeeds and the answer is
still wrong.**

## Not done

- **No renumbering of anything.** `F-0009` was already resolved by the coordinator.
- **No ledger for `F-NNNN`** — deliberately, per the decision. A dispatch-contract line
  replaces it; a second ledger would have re-created the problem it was meant to solve.
- **No report-naming convention imposed.**
- **`ops/branches/`, `ops/ui/index.php` and `ops/triage/` recorded, not removed.** Deleting
  directories in a live tree is not an indexing correction.

## Standing caveat

**The branch space is not settled.** Two more branch documents are expected tonight as
agents close out, and `F-0012` onward must be issued at dispatch from now on rather than
chosen. The rule is only as good as the first dispatch that follows it.
