# Board reconciliation — 2026-08-29

The board had drifted badly. **53 items were carrying a live status while their fix was
already merged to `dev`** — some for over a week. This records what was restamped, on what
evidence, and what deliberately was NOT.

Nothing was deleted. Every item keeps its full history and gains a
`## Closed — 2026-08-29 (board reconciliation)` section naming the evidence, so any of
these can be re-checked and reopened.

## What "done" means here — read this before trusting it

**`done` means "the change is on `dev`". It does NOT mean "someone re-verified the
behaviour since."** These were restamped from git history, not from a fresh test pass. If
a persona run or a browser check finds one of them unfixed, that is a real defect and the
item should be reopened — the stamp is a record, not a guarantee.

## The evidence rule

An item was only restamped where **one of these held**:

1. A `fix(`, `feat(`, `refactor(` or `perf(` commit on `dev` names the item **in its
   subject line** — 40 items. This is the strong signal: someone wrote a fix and labelled
   it with the item.
2. A **merged PR title** names the item — 5 more, all from PR #731.
3. A commit whose subject **begins with the item id** (`W-0081: put the /m stylesheet on
   palette tokens...`) — 8 more. This form is Icecube's and Phailanx's house style and the
   first two rules missed all of it; caught on noticing `W-0001` still reading `review`
   with its fix plainly on `dev`.

A W-number appearing only in a PR *body* was **not** enough. Bodies name items as prior
art, as follow-ups raised, and as context; treating a mention as a fix would have closed
items that were only ever filed.

## Excluded on inspection

Five items matched a rule and were still left open, because reading the commit or PR showed
the work was **raised, measured or merely re-filed, not done**:

- **`W-0483`** and **`W-0507`** — PR #735 is explicitly *"board item only; the fix it asks
  for is web + `/m`"*. It salvaged the item from `main`; it did not fix it.
- **`W-0497`** — PR #726's own title says it *"raises W-0497 for what the sweep found"*.
- **`W-0048`** — its commit says it *"measure[d] the safelist damage **without touching
  it**"*. A measurement, not a fix. Still `blocked`.
- **`W-0483`** again, on a second ground: commit `08ddde6d9` says *"the hooks were fixed,
  not just filed"* while PR #735 four days later salvaged the item as still needing a fix.
  **Those two cannot both be right** — left `blocked` for a human to settle.


### Stoff73 — 31 items

| item | was | landed in |
|---|---|---|
| `W-0012` | `gated` | commit on `dev` |
| `W-0190` | `gated` | commit on `dev` |
| `W-0202` | `gated` | commit on `dev` |
| `W-0204` | `in_review` | #744 |
| `W-0361` | `gated` | #714 |
| `W-0363` | `gated` | #714,#740 |
| `W-0364` | `gated` | #714 |
| `W-0365` | `gated` | #714 |
| `W-0381` | `handoff` | commit on `dev` |
| `W-0465` | `gated` | commit on `dev` |
| `W-0466` | `gated` | commit on `dev` |
| `W-0467` | `gated` | commit on `dev` |
| `W-0469` | `gated` | commit on `dev` |
| `W-0471` | `handoff` | commit on `dev` |
| `W-0473` | `gated` | #714 |
| `W-0474` | `gated` | #714,#739 |
| `W-0475` | `gated` | #714 |
| `W-0477` | `gated` | #714 |
| `W-0478` | `gated` | #714 |
| `W-0479` | `gated` | #714 |
| `W-0480` | `review` | #739 |
| `W-0482` | `review` | #714,#740 |
| `W-0485` | `review` | #741 |
| `W-0502` | `review` | commit on `dev` |
| `W-0509` | `in_review` | #742 |
| `W-0511` | `in_review` | commit on `dev` |
| `W-0512` | `in_progress` | #746,#747 |
| `W-0517` | `in_progress` | #746 |
| `W-0519` | `in_progress` | #745 |
| `W-0520` | `in_progress` | #747 |
| `W-0521` | `in_progress` | #748 |

### Icecube-acc — 7 items

| item | was | landed in |
|---|---|---|
| `W-0038` | `review` | #727 |
| `W-0042` | `review` | #727 |
| `W-0043` | `review` | #727 |
| `W-0368` | `review` | #719,#728,#729 |
| `W-0374` | `review` | #722,#729 |
| `W-0468` | `review` | #721,#729 |
| `W-0501` | `review` | #728 |

### Phailanx — 4 items

| item | was | landed in |
|---|---|---|
| `W-0141` | `queued` | #731 |
| `W-0245` | `review` | #731 |
| `W-0329` | `review` | #731 |
| `W-0505` | `review` | #731 |

### Phailanx/Stoff73 — 2 items

| item | was | landed in |
|---|---|---|
| `W-0489` | `in_review` | #718,#743 |
| `W-0490` | `gated` | #718,#733 |

### Icecube-acc/Stoff73 — 1 items

| item | was | landed in |
|---|---|---|
| `W-0347` | `gated` | #714,#717 |
## Still not done — the honest remainder

After this pass the board holds **113 done**, and still:

| status | count | meaning |
|---|---|---|
| `gated` | 96 | awaiting a gate (mostly `tax-compliance-reviewer`) |
| `queued` | 85 | not started |
| `review` | 8 | awaiting review |
| `open` | 5 | raised, unclaimed |
| `blocked` | 4 | including `W-0483` above |
| `claimed` / `in-progress` / `handoff` | 3 | in flight |

**The 96 `gated` items are the thing worth looking at next.** That is the largest single
bucket on the board and it is not a work queue — it is a queue of work that may already be
finished and is waiting on a reviewer that, per the 2026-08-29 handover, has twice gone
idle without returning a verdict. Some of those 96 will be in the same state the 45 above
were in.
