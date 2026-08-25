# Ops Formats

Every artefact the workforce produces. **Copy these; do not improvise.** A format
nobody follows is a format that cannot be swept, indexed, or read by the control
centre.

---

## Work item — `board/W-NNNN-slug.md`

```yaml
---
id: W-0142
title: Write the PS25/22 targeted-support position paper
mission: 2026-08-13-fca-targeted-support
branch: branches/research/ps2522-position/
owner: compliance-lead
status: queued            # queued|claimed|in_progress|handoff|review|gated|blocked|done
surfaces: [web, m, ios]   # Rule 19 — explicit, never "the app"
created: 2026-08-13T09:14:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: null   # REQUIRED before claimed
prior_art_found: []
prior_art_outcome: null   # none|route|extend
constitution_refs: [05-perimeter, 03-hard-nos]
---

## Intent
## Acceptance
## Working notes
(append-only)
```

**An item cannot reach `claimed` without `prior_art_checked`.** `charter.md` §11.

**Claiming** is setting `owner` + `claimed` and committing. Git rejects the second
writer, so two agents cannot hold one item.

---

## Handoff note — `handoffs/W-NNNN/<from>-to-<to>-<date>.md`

**An item cannot move to `handoff` with an empty note.** Unstated assumptions are
how agent chains silently degrade.

```markdown
# W-0142 — compliance-lead → product-lead

## Done
## Not done, and why
## What you need that isn't obvious from the artefacts
## Assumptions I made
(stated as assumptions, never as facts)
## Surfaces covered / not covered
```

---

## Gap record — `gaps/G-NNNN-slug.md`

```yaml
---
id: G-0009
class: knowledge   # access|tool|credential|context|knowledge|capability|contradiction
agent: growth-lead
severity: blocking # blocking|degrading|info
opened: 2026-08-13
action: interview  # fix|interview|escalate
blocking: [W-0144]
status: open
---
## The gap
## Evidence
## Resolution
```

---

## Gate request — `gates/GATE-NNNN-slug.md`

```yaml
---
id: GATE-0031
workstream: quality
item: W-0131
action: Merge dev → main and deploy to fynla.org
raised: 2026-08-13T15:50:00Z
decided_by: null    # MUST name a founder
decided_at: null
decision: null      # approve|hold
---
## What is being asked
## Evidence
## What happens if held
## Decision and reasoning
```

**Every decision records its author.** A decision that cannot be attributed to a
named founder is not a decision (`workforce/core/registry/people.md` §3.1).

**Timeout:** 48h → escalate once → park the item and move on. Agents never idle
waiting on a human. Outside CSJ's contact window the clock pauses.

---

## Provisioning request — `provisioning/PR-NNNN-slug.md`

```yaml
---
id: PR-0001
needs: Slack workspace authorisation
kind: connector    # connector|skill|hook|agent|access|credential|subscription
requested_by: chief-of-staff
blocking: [phase-3]
spend: none        # none | £X — anything non-zero is also a spend gate
status: open
---
## What it unlocks
## Cost of not having it
(the workaround in use, and what that workaround costs — "we need this" is not a case)
## Setup
## Decision
```

**One thing per request, never bundled** — so a founder can approve some and
decline others. Declined requests keep their reason; the workaround becomes
permanent rather than being re-requested monthly.

---

## Event log — `log/YYYY-MM.jsonl`

Append-only, one line per state change. **The control centre reads only this.**

```json
{"ts":"2026-08-13T11:41:19Z","agent":"compliance-lead","item":"W-0142","event":"handoff","to":"product-lead","note":"handoffs/W-0142/compliance-to-product-2026-08-13.md"}
```

**Item lifecycle:** `claimed` · `started` · `progress` · `handoff` · `review` ·
`judged` · `gated` · `blocked` · `done` · `gap_opened` · `probe` · `triage` · `deploy` ·
`claim_blocked`

**Governance and channel:** `decision` · `note` · `brief` · `ratified` · `named` ·
`gate_approve` · `monday_plan` · `friday_delta` · `slack_connected` · `slack_posted` ·
`slack_blocked` · `slack_failed`

Reconciled against actual usage 2026-08-21, after the consistency sweep found event
names outside the enum. **The enum was the stale half, not the log** — most of the
governance names had been in weekly use for a fortnight. They are adopted rather than
renamed, because renaming would falsify history to satisfy a list.

**`decision` is the one to protect: it carries CSJ's rulings**, which are otherwise
recoverable only by reading the working notes of whichever item they landed on. Seven
were recorded against it on 2026-08-21 alone. Never rename it, and never fold a decision
into a `note`.

**Two known weaknesses, recorded rather than fixed** (consistency sweep, 2026-08-21):
the log knows **12 of 69 items** — most notably 2 `handoff` events against 36 items at
`handoff` — so it cannot yet be the sole liveness source it is described as; and **4
timestamps are out of order**, so "last line wins" is unsafe. Sort by `ts` before
deriving a state, and corroborate against the board rather than trusting log silence as
evidence that nothing happened.

**COORDINATOR OBLIGATION, added 2026-08-23.** The rule below is not self-enforcing and
was not enforced. In a thirteen-batch run the coordinator **never once asked an agent to
emit an event**, and emitted none itself; agents reported in messages instead. **The log
knew 12 of 69 items before that run and it was not the agents' failure alone.**

**Require log events in the dispatch, name them, and treat their absence as a defect** —
not as a documentation preference. The cost is concrete: for two hours one agent's state
was derivable only from the messages it sent, which is exactly what led the coordinator to
ask whether it had browser tools at all. **That diagnostic question was the log's absence
showing through.** An agent that narrates instead of emitting is invisible to every observer
that is not personally reading its messages — which is the whole point of the log.

**`progress` matters most.** Liveness is derived from the log, not from reports —
a working agent emits events, a dead one does not. **If a status cannot be derived
from the log, the missing event is the bug**, and agents are never asked to
compensate by narrating.

---

## Branch document — `branches/<type>/<slug>/`

```yaml
---
id: F-0042
type: feature      # feature|fix|maintenance|research|meeting
parent: core/constitution/07-quality-bar.md   # MUST resolve
applies: [core/constitution/05-perimeter.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-13T18:00:00Z
status: active     # active|superseded|archived
---
```

**A branch with no resolving parent is invalid and blocks until linked.** Branches
apply rules; they never author them (`charter.md` §11 / Archivist).

---

## Checkpoint report — `reports/`

Written by an agent **only** on completion, handoff, hard block, or context
handover. **Never on a clock.** A mid-work agent reports nothing; the Chief of Staff
observes it from the log.

```markdown
## Done
## Not done, and why        (never omitted)
## Assumptions
## Needs                    (gate | answer | access | provisioning)
## Noticed                  (outside my remit — routed to whoever owns it)
```

---

## Daily brief — `reports/brief-YYYY-MM-DD.md`

Generated by the Chief of Staff at 17:30. **≤300 words.** If a day needs more, it
is a meeting.

**Shipped** · **Moving** (right now, observed) · **Needs you** (ranked by what they
block) · **Watch** · **Read** (one line of judgement).

Ends with what is still in flight. **Never a summary implying completion.**

## Board ID allocation — blocks, not next-free

**Do not scan the board for the next free number.** With several agents running, `ls`
and write are not atomic: two agents both read "next is W-0030" and both write it.
This happened on 2026-08-21 and produced duplicate W-0035 and W-0036.

**Each agent is allocated a block by the coordinator when dispatched, and uses only
that block.** The coordinator keeps the ledger below and never issues the same block
twice. An agent that exhausts its block asks for another — it does not take the next
free number.

If a collision does occur, **the item with the fewest external references gets
renumbered**, not the one that happens to be newest. Cross-references in playbooks,
handovers and branch documents are the expensive part; the file itself is cheap.

### Allocated blocks — see the single ledger

> **MERGED 2026-08-21 into "Block ledger — 2026-08-21, current" further down this file.
> There is now ONE W-block table. Allocate from it.**
>
> This table used to live here and stopped at W-0069. The Archivist found the file
> carrying **two** W-block ledgers 180 lines apart, only the lower one marked current —
> which is the exact append-a-correction-far-from-the-claim shape whose rule was written
> into this same file an hour earlier, committed by the coordinator who wrote it. **An
> agent reading top-down would have allocated from a table stopping at W-0069**, which is
> how an unallocated id gets issued even with a ledger in place. Its rows are preserved
> verbatim in the merged table below, marked as the earlier half.

## Migrations on the shared dev database — always `--path`, never bare `migrate`

**`php artisan migrate` applies EVERY pending migration, including other agents' data
migrations.** On a shared local dev database that makes it a cross-batch action: an
agent running it for its own schema change silently applies everyone else's pending
work, taking a decision that belonged to those owners.

Observed 2026-08-21. Batch 48 on local dev contains two unrelated migrations from two
different agents; Batch A's `normalise_shared_ownership_percentage` went in the same
way at batch 46. Neither owner chose the moment. The coordinator had deliberately
**not** run the spouse-pension migration precisely because it changes data and that
was its owner's call — bare `migrate` removed that choice from everyone.

**The rule: run exactly the migration you own.**

```
php artisan migrate --path=database/migrations/<your_file>.php
```

This is the only form that respects ownership on a shared database.

**Data migrations additionally must report what they changed** — row count and
before/after values, per W-0030's standard. "Zero rows" is a finding and must be
stated, not inferred from silence. A data migration whose effect nobody recorded
cannot be verified afterwards.

**Never** use the destructive table-dropping migration commands (see CLAUDE.md), and
never the testing-environment flag (`--env=testing`) — it resolves to the dev database and wipes
it. Use a `DB_DATABASE=` override against an isolated test database instead. Reseed
with `php artisan db:seed` after anything that disturbs seeded data — and note that
reseeding recreates preview-persona rows with **new ids**, which can look like data
loss to anyone holding an id from before.

## Claiming a board item — the coordinator claims at dispatch, not the agent after starting

**The coordinator sets `status: claimed`, `claimed_by:` and `claimed_at:` on the board
file at the moment it dispatches the item** — before the agent has read anything.

Do not rely on the agent to claim it after it starts. The gap between "dispatched" and
"agent gets round to updating the board" is exactly wide enough to dispatch the same
item twice.

Observed 2026-08-21. `W-0036` read `status: queued, claimed: null` while an agent was
actively implementing it — the coordinator had told that agent to stand down, its
close-out had crossed the dispatch, and a replacement was sent the same item. The
replacement checked file mtimes against its own dispatch time, found live code from an
agent it had been told was retired, and **stopped without writing**. Two agents were
one edit away from clobbering each other in the single file both fixes routed through.

**The board is the only shared record of who holds what.** An agent's report is a
point-in-time claim that can be stale by the time it is read; the working tree is the
truth about what has been written; the board is what stops two agents being sent to the
same place. If it lags, it is not doing its job.

**Corollaries:**

- **Trust the tree over the board for an item's NARRATIVE — not for its `status`.**
  Refined 2026-08-21 by the consistency sweep, which chased every apparent
  status contradiction on 71 items and found **all of them correct**. The three
  incidents that cost time that day had three different causes and **not one was a
  wrong `status`**. What lies is the narrative around an item: a `branch:` field left
  `null` while a branch document sits beside it, a working note corrected 70 lines
  further down, an event the log never recorded. So: read `status` and believe it;
  check mtimes on the files an item touches before writing, especially where a sibling
  agent has recently worked; and **read an item's working notes from the BOTTOM**,
  because corrections are appended, never applied, so the newest truth is always last.
- **Crossing messages are normal with several agents running.** A stand-down that
  crosses a dispatch, or a report that crosses an instruction, will happen — the board
  is what makes it recoverable rather than destructive.
- Release the claim when the agent returns the item, so the next dispatch sees it free.

### Correcting an append-only document — mark the stale claim, not only the correction

**Reading from the bottom is the reader's half of the fix. This is the writer's half,
and it is the half that actually works** — a rule that depends on every reader
remembering to skip to the end fails the first time someone skims.

**A correction to an append-only document places a marker at the stale claim as well as
the correction at the bottom.**

```markdown
> **SUPERSEDED — see the `2026-08-21 build-lead` note below ("short quoted phrase").**
> One or two lines saying what is now true, with the file:line evidence.
> Left as the record of what was believed.
```

**Rules for the marker:**

1. **Marker, never rewrite.** The stale text stays exactly as written. A claim honestly
   made and later disproved is the record of what was believed, and deleting it destroys
   the only evidence of how the mistake happened.
2. **Point by note date, author and a quoted phrase — never by line number.** Inserting
   the marker shifts every line below it, so a line-number citation is stale the instant
   it is written. This is the mistake the rule exists to prevent, made inside the fix.
3. **Say which way it moved.** `SUPERSEDED` for a claim now false; `QUALIFIED` for one
   still true but materially narrowed. They need different reactions from a reader.
4. **Better still, do not leave stale text at all.** W-0045 is the model: the correction
   says *"an earlier revision of this item said X. That was wrong and is retracted"* and
   the wrong text is gone, characterised rather than preserved. Nothing to mark, nothing
   to trip over. Use a marker when the original wording is itself evidence; use W-0045's
   pattern when it is not.
5. **Never mark another agent's live item.** Items at `claimed` or `queued`, and every
   branch document, belong to whoever is holding them. List them for the coordinator and
   let the marker land when that agent stands down.

**Where this came from.** W-0018 read *"No code changed."* at line 199 of 281 while its
own correction sat 47 lines below, and a coordinator nearly redid work that `git diff`
showed was already done. That cost an hour on 2026-08-21. Five board items and two
sections of `tests/Persona/20-08-2026_run/COORDINATOR-HANDOVER.md` carried the same
shape and were marked the same day.

### Whole-catalogue captures — one agent, last, and it is the coordinator

**Added 2026-08-21**, after a re-record swept in another agent's uncommitted work.

`ToolSchemaGoldenMasterTest` / `XaiToolSchemaGoldenMasterTest` snapshot the **entire
assembled tool catalogue**. There is no way to capture only one tool. So a re-record
run by an agent that changed one schema also **pins every other agent's in-flight
`fyn-memory/procedural/tool_schema/**` edit**, whether or not those edits are finished
or correct.

**Rule: an agent whose work changes the assembled catalogue does NOT capture. It tells
the coordinator and stops.** The coordinator runs one capture at the consolidation
point, after the corpus has settled:

```
CAPTURE_TOOL_SCHEMA_GOLDEN=1 CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1 \
  ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php \
                    tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php
```

**Why not "revert and let the other agent capture":** it is worse. Reverting leaves the
gate RED for everyone, and stashing another agent's corpus file to capture around them
means writing in their working tree. **A capture that truthfully matches the corpus on
disk is the correct interim state** — the fault is in *who owns the next one*, not in
the snapshot. The agent that hit this handled it exactly right by capturing and
declaring what it had swept in.

**Same shape, same rule, for any other all-or-nothing shared artefact:** built bundles
(`public/build/`, `public/m-build/`), and shared test config (`tests/Pest.php` — see
`tests/CLAUDE.md`, where editing it mid-run is named a collision, not a fix).

### Folding duplicate items — count mechanisms, not generality

**Added 2026-08-21**, after three same-shape pairs landed on the board in one day and got
two different answers. Two items that look like "a general problem and a specific
instance of it" are **not** automatically a fold. The test is **how many mechanisms the
pair names**, established by reading the code, never by comparing the titles:

| Pair | Mechanisms | Answer |
|---|---|---|
| W-0048 / W-0082 | one — the Tailwind safelist | **fold** |
| W-0102 / W-0103 | one — a role-conflict check that does not exist; W-0102 is one instance of it | **fold** |
| W-0121 / W-0122 | **two** — `HoldingValuation`, and a second valuation written inline in `CoordinatingAgent` | **do NOT fold** |

**Folding a one-mechanism pair prevents the Rule 20 disease. Folding a two-mechanism pair
causes it** — the fix lands in one, the item closes, and the second copy survives
unrecorded. Verify by grepping who reads the shared class before deciding.

**Related trap, same day, same family:** an acceptance criterion that both **enumerates**
places and **cites** a rule will be read as whichever half suits the reader. W-0121's
criterion 5 listed "a controller, a request, or a component" while citing Rule 20, which
enumerates nothing — so an agent tool handler was outside the list and inside the rule,
and the item was simultaneously signed-off-able and not. **Cite the rule or list the
places, never both.**

### Block ledger — 2026-08-21, current

**Allocate from this table and extend it in the same edit. An ID issued past the end of
the ledger is an unallocated ID**, which is the exact condition that produced the
duplicate W-0035/W-0036 collision on the morning of 2026-08-21 and the two `F-0005`
documents that afternoon. The consistency sweep flagged three items already past the end
(W-0121, W-0122, W-0131) — the ledger below closes that.

| Block | Holder | State |
|---|---|---|
| W-0006 – W-0029 | persona-tester (original) | exhausted, agent retired |
| W-0030 – W-0034 | team-lead (coordinator) | exhausted |
| W-0035 – W-0038 | persona-passA2 (tester) | exhausted |
| W-0039 – W-0049 | team-lead (coordinator) | exhausted |
| W-0050 – W-0059 | persona-passA2 (tester) | exhausted |
| W-0060 – W-0069 | fix batches, on request | issued |
| **W-0070 – W-0089** | **UNALLOCATED — gap found 2026-08-21** | **free, but see note** |
| W-0090 – W-0099 | fix-batch-E | issued — **W-0090 used** (native never says a target was inferred); corrected 20:35, the row previously read "unused" and the tree disagreed |
| W-0100 – W-0110 | fix-batch-G | **exhausted** — all ten used on the powers-of-attorney audit |
| W-0111 – W-0120 | fix-batch-I | issued |
| W-0121 – W-0130 | fix-batch-J | issued — W-0121, W-0122 used |
| W-0131 – W-0140 | persona-passA3 | issued — W-0131 used, W-0132/W-0133 pending live confirmation |
| W-0141 – W-0150 | fix-batch-G, second block | issued |
| W-0151 – W-0160 | team-lead (coordinator) | issued — W-0151 to W-0157 used |
| W-0161 – W-0170 | fix-batch-F, second block | issued 18:55 |
| W-0171 – W-0175 | persona-passA3, second block | issued 20:18 — **self-claimed and announced**, ratified by team-lead; W-0171, W-0172 used |
| W-0176 – W-0185 | `cycle1` fix agents | issued 20:50 |
| W-0186 – W-0195 | persona-passA3, third block | issued 20:50 — W-0171–W-0175 exhausted |
| W-0196 – W-0205 | `cycle2` fix agents | issued 22:10 |
| W-0206 – W-0215 | persona-passA3, fourth block | issued 22:10 |
| W-0216 – W-0220 | `cycle2-projection`, second block | issued 07:45 — reconciliation findings |
| W-0221 – W-0225 | team-lead (coordinator) | issued 07:56 |
| W-0226 – W-0230 | `cycle2-ownership`, second block | issued 08:05 |
| W-0231 – W-0240 | `cycle3` fix agents | issued 08:50 |
| W-0241 – W-0250 | **unallocated — next block** | free |


**W-0070 – W-0089 appeared in neither table and are unallocated.** Found by the Archivist
while installing the mission ledger. They are free — **but if any were issued verbally
they are invisible to both tables and to this one.** Check the board for existing items in
that range before allocating from it.

**Agents holding no block of their own** — `compliance-lead`, `archivist`, `design-lead` —
**route a finding to the coordinator rather than picking a number.** That is what
happened with W-0151 and it worked; do not change it to self-service without also
giving those agents blocks.

### Mission ledger — `mission:` identifiers, 2026-08-21

**`W-NNNN` was not the only id space, and it was the only one with a ledger.** `M-0002`
was issued twice — to two unrelated programmes — by the identical mechanism that produced
the W-0035/W-0036 collision before the table above existed.

**A mission identifier is allocated here before use.** Same rule as the W-blocks: agents
holding no block — `compliance-lead`, `archivist`, `design-lead` — route to the
coordinator rather than picking one.

Snapshot **2026-08-21 20:00, 101 board items.** The board was moving throughout; treat
the counts as of that moment.

| Identifier | Holder / programme | File in `ops/missions/` | Items |
|---|---|---|---|
| `M-0001-state-truth` | make recorded state match actual state | **Yes** | 3 |
| `M-0002-capability-map` | prior-art discovery — **the legitimate holder of M-0002** | **Yes** | 1 (`W-0004`) |
| `M-0003-unblock-merge` | make the evidence gate operative | **Yes** | 1 |
| `M-0002-persona-fidelity` | persona-run defects found **by fix agents** | **No — and it collides with M-0002** | 47 |
| `persona-run-peak_earners-2026-08-20` | persona-run defects found **by the tester** | **No** | 49 |
| **`M-0004` onward** | **unallocated — next free** | — | — |

**96 of 101 items cite a mission that does not resolve to a file.** Five resolve. That is
a larger defect than the collision and it is not fixed by this ledger — it needs mission
files written, which is the Chief of Staff's call, not an indexing correction.

**The last two rows are a real distinction, not a mistake.** Across 70 classifiable items
there is **zero crossover**: the tester-found id never appears on a fixer-found item or
the reverse. The convention works; it has no home and one of its two ids took an occupied
number. **Do not "correct" those 96 items** — naming the convention and renumbering is one
CSJ decision, batched, and renumbering goes last, in a single pass, once the fix agents
stand down.

#### The identifier form is unsettled, and this ledger does not settle it

**The work-item template and the missions directory disagree, and nothing adjudicates.**

- The template at the top of this file gives `mission: 2026-08-13-fca-targeted-support` —
  **a date-slug**.
- `ops/missions/` holds three `M-NNNN-slug.md` files and nothing else.

So `persona-run-peak_earners-2026-08-20` follows **this file's own example** and `M-NNNN`
follows **the directory**, which is the opposite of what three files in a directory imply.
`fix-batch-J` matched the parent item rather than the directory and **was right to** — the
directory is not the spec.

**Recording both forms above is deliberate and is not an endorsement of either.** Which is
canonical is a doctrine question and goes to CSJ with the rest. Until it is answered,
**allocate from this ledger in whichever form the parent work already uses, and never
invent a third.**

### Branch-document numbers — issued at dispatch, never chosen at close-out

#### Issued so far — the live table

| Number | Holder | State |
|---|---|---|
| F-0001 – F-0011 | see `workforce/branches/fixes/` | in use |
| **F-0012** | `fix-batch-G` — inheritance tax household (W-0154) | issued 20:22 |
| **F-0013** | `fix-batch-F` — ownership boundary continuation | issued 20:25 |
| **F-0014** | `fix-batch-I` — expenditure derivation (W-0140) | issued 20:32 |
| **F-0015** | `cycle1-estate` | issued 20:46 |
| **F-0016** | `cycle1-surfaces` | issued 20:46 |
| **F-0017** | `cycle1-tax` | issued 20:50 |
| **F-0018** | `cycle2-projection` | issued 22:10 |
| **F-0019** | `cycle2-ownership` | issued 22:10 |
| **F-0020** | `cycle2-audit` | issued 22:10 |
| **F-0021** | `cycle3-goals` | issued 08:50 |
| **F-0022** | `cycle3-surfaces` | issued 08:50 |
| **F-0023** | **next free** | — |

**A third near-miss, on top of the two collisions below: W-0154's `branch:` field was set
to `F-0010`, already held, by the COORDINATOR at 20:15 — minutes before writing this very
rule.** Caught by the Archivist before the duplicate file existed, so nothing needed
renumbering. **The mechanism does not care who is running it**, which is why the fix is
"issued from a table" rather than "be careful".

**`F-NNNN` is issued by the coordinator with the work-item block, in the same breath.**
It is part of the dispatch contract. **The agent never chooses its own `F` number and
never reads `branches/fixes/` to find the next one.**

**Why this space needed a different fix from a ledger.** `F-NNNN` was the only identifier
an agent allocated **itself, at close-out, from a directory listing** — and a ledger would
not have helped, because the agent writing its branch document at 20:02 is not reading a
ledger at that moment. It is the same `ls`-then-write race the work-item blocks stop,
in the one space nobody extended them to.

**It collided twice on 2026-08-21, in one day:**

| Collision | Detail | Resolution |
|---|---|---|
| **`F-0005` twice** | design-lead and batch D | resolved that afternoon |
| **`F-0009` twice** | `batch-g-native-handoff` (19:08, 3 inbound refs) and `batch-i-onboarding-spouse` (20:02, 6 inbound) | **batch G renumbered to `F-0011`; batch I kept `F-0009`** |

**Note which one moved.** Batch G's was written **first** and still moved, because the
collision rule is **fewest external references, not first-come or newest**. Moving a file
is cheap; moving nine citations is not.

**Next free: `F-0012`.** `F-0001` to `F-0011` are issued.

### Claiming a gap, gate or provisioning number — in the frontmatter, before the body

**One rule for `G-NNNN`, `GATE-NNNN` and `PR-NNNN`.** All three are low-volume and any
agent may write one.

**Claim the number in the file's frontmatter and save the file before writing the body.
Never take a number from a directory listing.** A listing tells you what existed when you
looked, which is not what exists when you write.

**`G-NNNN` is clean — and that is luck, not method.** `G-0005` was filed by reading the
directory and taking the next number, which is the method behind **every collision this
workforce has had**: `W-0035`/`W-0036`, `F-0005`, `F-0009`, `M-0002`, `Q-09`. It held only
because just two agents have ever written a gap. `GATE` and `PR` are single-author for the
same reason and carry the same unsafe method. **Do not read the absence of a collision as
evidence the method works.**

Next free, as at 2026-08-21 20:13: **`G-0006`** · **`GATE-0005`** · **`PR-0002`**.

### Interview records — `S-NN`, a space this file did not mention

`ops/interviews/` holds ratified doctrine-interview records `S01` to `S07`. **Next free:
`S08`.** Single-author today, and the claim-in-frontmatter rule above applies if that
changes. Recorded because **a space this file does not mention at all is worse than one
with a weak rule** — nobody can follow a convention they cannot find.

`ops/interviews/divergences.md` carries no id, in a space where everything else does.

### Report filenames — there is no convention, and that is the decision

**Decided 2026-08-21: reports are not named to a pattern, and none is being imposed.**
Six forms are in live use — `YYYY-MM-DD-slug`, `YYYY-MM-DD-W-NNNN-slug`,
`YYYY-MM-DD-F-NNNN-slug`, `brief-YYYY-MM-DD`, `friday-YYYY-MM-DD`, `monday-YYYY-MM-DD` —
and nothing is broken by it: reports are found by reading the directory, not by resolving
an id. `friday-` and `monday-` pair with the `friday_delta` and `monday_plan` log events.

**This is recorded as a decision rather than left as a silence**, because the previous
state was neither a convention nor a choice, and an agent could not tell which. **Imposing
one later is CSJ's call, not a tidy-up.**

### Three things in `ops/` that are in no index

- **`ops/branches/` is an empty directory, and it is a trap.** The real branch documents
  live at `workforce/branches/<type>/`. This file says `branches/<type>/<slug>/`, which
  **resolves to the empty directory if read relative to `ops/`** — so an agent checking
  "does that path exist?" gets **yes**, and writes to the wrong place. Nothing has been
  written there yet. It is the Class B shape exactly: the check succeeds and the answer
  is still wrong.
- **`ops/ui/index.php`** — 15,220 bytes, referenced by nothing.
- **`ops/triage/`** — empty.

## Sweeping the board against the working tree — always `-uall`

**`git status --porcelain` collapses untracked files inside a wholly-new directory to
the directory alone.** It reports `?? tests/Feature/Chattels/`, never the file inside it.

**So every board item whose evidence is a NEW test file scores zero changed files, and a
sweep that trusts the default output accuses precisely the agents who wrote tests.** That
is the exact inverse of the truth, in an audit whose entire authority rests on being
right.

Measured on 2026-08-21: the default output gave **621** paths and sixteen collapsed
directories, among them `app/Services/Consent/`, `tests/Feature/Goals/` and
`tests/Unit/Support/`. The first pass of that day's sweep reported W-0025 and W-0029 as
`handoff` with zero evidence. Both were wrong; both had new test files on disk with that
morning's mtimes.

**Use `-uall`:**

```
git status --porcelain -uall
```

It gave **681** paths on the same tree and turned W-0025 and W-0029 from 0-of-2 to
2-of-2. Caught before the report went out, and recorded here so the next sweep does not
rediscover it the expensive way.

**Two companion rules for the same correlation, learned in the same sweep:**

- **"This item's named files are changed" is a lead, never a finding.** Several agents
  share one tree and items name shared paths. Every apparent contradiction is chased by
  reading the item before it is written down — on 2026-08-21 not one survived that check.
- **`claimed` with no code is correct, not drift.** The coordinator claims at dispatch,
  before the agent has written anything.

---

## The board is prior art — a queued item is a decision, not an absence

**Added 2026-08-23.** An agent ran a full prior-art check — code, artisan commands, open
PRs, in-flight branches, the vault, skills and agents — **and did not sweep
`workforce/ops/board/`.** It then built a third of its batch straight through **W-0202**,
which was `queued` and carried the coordinator's own words: *"NOT to be built this cycle."*

**The coordinator approved that work.** Asked for a veto, it gave an approval instead of
checking the board. **So the miss was two-sided, and the rule binds both roles.**

> **A queued item is not an absent item — it is a decision someone has already taken, and
> building past it is not initiative.**

**`workforce/ops/board/` is a mandatory prior-art source.** Sweep it before building, by
subject, not only by ID. It held 234 items on the day this was written.

**Read a queued item's acceptance criteria in ORDER, and honour their dependencies.**
W-0202's criterion 1 read *"This must be settled first; branch three is unbuildable until
it is."* The agent built criterion 2 without criterion 1 — the mechanism without the
prerequisite it depends on.

**Why that prerequisite was real, and worth generalising:** the blocker was **disclosure,
not arithmetic.** `users.expenditure_sharing_mode` is `NOT NULL DEFAULT 'joint'`, so **a
household that has never been asked is indistinguishable from one that chose** — 19 users
on dev, every one on the default, not one `separate`. The web form may halve on that
default **because it says so on screen while the user types**; Fyn says nothing. **The
difference between the two surfaces is disclosure, not arithmetic** — so the same change
is defensible on one and not the other.

**When you must stop, leave the parked work CHEAPER, not merely untouched:**
- build the mechanism the parked item will need, and say so;
- record any obstacle its acceptance anticipated that no longer exists;
- add any path it does not yet name;
- **pin current behaviour with a test whose docblock says pinned, not endorsed** — so when
  the parked item is built, the change surfaces as a deliberate red test rather than a
  silent diff.

---

## A consolidation stops at the edge of the diff — check every line that READS the thing you centralised

**Added 2026-08-23**, from a Rule 2 sweep that found **nine routings, eight of them in files a
previous consolidation had already visited.**

- `TaxConfigService`'s own docblock recorded the `?? 0.36` duplication as consolidated
  across six sites. **Two were still standing** — a record claiming completion is how the
  next reader stops looking.
- `WillAnalysisService` **computed** the charitable threshold from configuration at `:55`
  and **hardcoded** it in the sentence describing that same threshold at `:351`.
- `ContributionWaterfallService` **read** the Lifetime ISA configuration at `:152` and
  **hardcoded** the bonus at `:155`.

**The pattern is not "someone forgot".** A fix lands where the defect was noticed and stops
at the edge of the diff. The author was looking at a bug report, not at the file.

> **Not "check every layer" — check every LINE that reads the thing you just centralised.**

**Three concrete checks when you centralise a value:**
1. **`grep` the whole repo for the literal**, in prose, in arithmetic, and **in comparisons**
   — a threshold inside an `if ($x < 10)` gate decides **whether a recommendation is
   generated at all**, so there is no wrong sentence to notice; **there is no sentence.**
2. **Read the file you are editing end to end**, not the region around the diff. Two of the
   worst instances above sat three hundred lines from a correct call in the same class.
3. **If a docblock or note claims a consolidation is complete, verify it and correct it.**
   Leaving a false completion note is worse than leaving the duplicate.

**And do not replace one count with another.** On 2026-08-23 the same count was wrong
**three times by three authors**: the original docblock said complete; the first correction
named one survivor; a compliance reviewer named two; `grep -rn '?? 0.36' app/` found
**four** — including one inside the admin screen that displays the tax settings themselves.

> **The number is not the durable thing. The check is.**

**A completion note should record HOW TO VERIFY, not what the answer was** — the two grep
commands, and any site that legitimately looks like a violation and is not, so nobody
"fixes" it. A count is stale the moment someone adds a line; a command is not.

**But the command decays too — and faster than anyone expects.** Recorded 2026-08-23, **one
cycle after the rule above was written.** `grep -rn '?? 0.36' app/` returned five hits, and
**four were the comments written to explain the fixes — two of them inside the note itself.**

> **A grep-based check degrades as the fix it checks for gets documented.**

**So write the exclusions into the command**, and name any known survivor beside it. A check
that carries only a pattern becomes noise the moment the codebase starts describing itself:
```
grep -rn '?? 0.36' app/ --include='*.php' | grep -v '^\s*//' | grep -v '\*'
# known and deliberate: TaxSettingsController:330 (not yet routed)
```
**A check, its exclusions, and the one thing it did not fix** — all three, or the next reader
inherits five hits and no way to tell which matter.

**And the ORDER matters — verify FIRST, then correct.** All three wrong counts were written
the same way: by reasoning about which sites the author remembered touching, then running
the check afterwards, or not at all. The agent that produced the second one put it plainly:
*"I corrected, then verified. Had I grepped first there would have been one correction
instead of two."* **An artefact updated from memory rather than from the codebase is a
correction that needs correcting** — the same shape as *a correction made in conversation is
not a correction made in the artefact.*

> **The artefact is the only thing that survives the conversation.**

Written by an agent standing down after four batches, 2026-08-23: *"everything I was right
about tonight is in a file or a test; everything I merely said is gone."* **A run generates
far more correct reasoning than it records.** The board item, the branch document, the test
and the `CLAUDE.md` entry are the run's output; the messages are not. **If a finding matters,
it goes in a file before the agent that found it stands down.**

**Related:** `app/Http/CLAUDE.md`'s seven rule-versus-schema axes and the read-boundary
("testing the ends does not test the join") entry. This is the same failure one level up —
those concern a value's journey between layers; this concerns **every site that reads it.**

---

## The single-browser protocol — handshake, acknowledge, release

**Added 2026-08-23, after two collisions and one false diagnosis cost roughly an hour.**

There is **one** Playwright tab. Two agents driving it interleave: one typed a verification
code into another's login form; another had the tab taken mid-call.

**Selecting your own tab does NOT isolate you.** An agent opened a second tab, selected it,
had the selection confirmed, and its very next call still executed against the first —
because the other driver's navigation re-focuses between calls. **A second tab is more
dangerous than sharing one, because you believe you are isolated.**

### The protocol

1. **The coordinator allocates. A tab transfers only when the coordinator says so AND the
   previous holder has confirmed it is off — in words.** No inference from silence.
2. **"Pass complete" is not a release.** One agent said that, then went back on to take a
   measurement. **The holder says "I am off."**
3. **The taker sends a one-line "taking it now" BEFORE starting**, and reports on release.
4. **Never sign anyone out** to reach a login form until they have confirmed they are off.
5. **Read the current page before acting.** A form mid-fill, a verification screen or an
   unsaved edit means someone is mid-flow — back off and report.

### Do not infer availability from quiet

**Silence is not completion.** An agent already logged in and reading four screens issues no
MFA codes and writes no rows. A coordinator wrote a self-serve rule based on MFA quiet
**immediately after proving to itself that silence proves nothing**, and it caused a
collision. **A cheap non-destructive probe** — MFA issuance, row `updated_at`, a git status
— tells you whether an agent is *working* or *stuck*, which are different states.

### And a silent agent is indistinguishable from a toolless one

**Ask the diagnostic question early: "do you actually HAVE browser tools?"** An agent replied
six times that it was blocked on the browser while the coordinator granted it six times; the
grants and its reports were simply crossing, and it had gone quiet for the duration of a
pass. **The coordinator invented a tool-availability explanation rather than checking the
ordering.** Its own conclusion: *"a silent agent and a toolless agent look identical, and you
were right to test which one I was."* **Test it in one line rather than re-sending the
grant.**

### Session state decays — do not relay it

**Seven coordinator relays of "who is signed in" were wrong in one night** (nobody / Sarah /
David / nobody / Sarah / nobody / Sarah), and one relay of a file path pointed into a fenced
directory. **Do not relay session state. Tell the agent to establish it itself:**
```
GET /api/auth/user      # on the token in use — NEVER a client store, which goes stale
```
**Two token stores on one origin can hold different users simultaneously:**
`sessionStorage.auth_token` (desktop), `localStorage.m_scaffold_token` (`/m`).
**Never identify an account by recognising a figure** — the figures are what is under test.

### Two tooling failures that mimic app defects — diagnose before filing
- **HMR remount:** the field visibly shows your text while the framework's state stays empty
  and submit fires nothing. Another agent editing the source tree remounts the component
  mid-interaction. **Make fill-and-click atomic in ONE evaluation.** Tell: request count
  climbing with no navigation.
- **Wedged input channel:** `fill()` works while `click()` and `press()` produce **zero**
  events, and a plain `<a href>` will not navigate. **Open a new tab.**
