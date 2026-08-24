---
id: W-0210
title: A goal is counted and labelled as a life event — Sarah has zero life events and the module reports "1 cash outflow events £400K"
mission: persona-run-peak_earners-2026-08-20
branch: F-0021
owner: build-lead
status: gated
severity: medium
surfaces: [web]
created: 2026-08-22T01:45:00Z
claimed: 2026-08-22T08:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0206]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Cycle 3 journey re-walk, local, both persona accounts, read-only.
**Surface:** `/goals` → Financial Projection → Life Events summary.

### Expected

Goals and life events are separate records with separate modules and separate tabs. A
summary headed **Life Events** counts life events.

### Actual

**Sarah has no life events at all:**

```
SELECT COUNT(*) FROM life_events WHERE user_id = 17 AND deleted_at IS NULL   →  0
SELECT goal_name, target_amount FROM goals WHERE user_id = 17                →  Sarah's ISA, 400000.00
```

Her `/goals` page reports:

```
Life Events
  0 cash inflow events    £0
  1 cash outflow events   £400K
```

**The single "cash outflow event" is her ISA goal**, target £400,000 — a savings target
she is contributing *towards*, presented as money flowing *out* of her position, under a
heading for events that have nothing to do with it.

David's page shows the same conflation at larger scale: **"9 cash outflow events £1.1M"**
against **6** expense life events totalling **£355,000** on his own Life Events tab. Two
tabs of one module disagree about how many events he has and by how much.

### Impact

A savings goal is the opposite of an outflow — treating a £400,000 ISA target as money
leaving makes the projection read as though the user is committed to spending it. For
Sarah it is the *only* thing in her projection, so her entire cash-flow picture is one
inverted figure.

It also makes the two tabs of one module contradict each other on David's account, which
is how a user would notice — and there is no way to tell from the summary which records
it is counting.

### Repro

1. `sarah.jones@example.com` → `/goals`, wait ~15s → "1 cash outflow events £400K".
2. `/goals?tab=events` → she has none.
3. `david.jones@example.com` → `/goals` → "9 cash outflow events £1.1M"; `?tab=events` →
   "Expected Expenses £355,000 · 6 events".

### Acceptance

1. The Life Events summary counts life events only, and matches the Life Events tab on
   both count and value.
2. If goal contributions belong in the cash-flow projection, they are a **separate,
   labelled** series — and a savings target is not an outflow.
3. Verified on an account with goals and no life events, one with both, and one with
   neither.

---

## Outcome — DONE

**One home:** `LifeEventService::summariseUpcoming()`.
**Fixed at:** `app/Services/Goals/GoalsProjectionService.php::buildSummary()`.

### One cause covers both accounts, and the arithmetic closes exactly

The item warned against assuming a single explanation. There is one, and it is not
assumed — the numbers reproduce on both accounts with nothing left over.

`buildSummary()` partitioned `$events` by **`impact`**, and `buildEventsArray()` stamps
every goal `'impact' => 'expense'` on the way in. So the card titled "Life Events"
counted goals as life events.

- **David:** 6 expense life events = £355,000 (matching his own events tab, which was
  right). Three goals qualify for the projection — £40,000 + £500,000 + £200,000 =
  £745,000; a fourth (£60,000, dated 2026-04-05) is already past and excluded by
  `getGoalsForProjection`'s `target_date > now()`. **6 + 3 = 9 events**, and
  **355,000 + 745,000 = £1,095,000 → "£1.1M"**. Both reported figures reproduce.
- **Sarah:** 0 life events + 1 goal (£400,000) = **"1 cash outflow events £400K"**.

**Neither account is double-counting.** The two looked like different faults only
because one holds goals *and* events while the other holds goals *only*.

### Measured after the fix

| | before | now |
|---|---|---|
| David cash outflow events | 9 / £1.1M | **6 / £355,000** — now identical to his events tab |
| Sarah cash outflow events | 1 / £400K | **0 / £0** |
| Goals still counted as goals | — | David `goal_count` 3, Sarah 1 |

### The projection arithmetic is deliberately unchanged

A goal is still applied as an outflow in the year it falls due — that is what the chart
models and it was never wrong. Only the labelled count stopped calling a goal a life
event. A test pins this explicitly so the fix is not later "corrected" in the wrong
direction by someone reading the diff and concluding goals had been dropped from the
projection.

### Surfaces

**No `/m` or native counterpart** — established by grep. Neither surface consumes
`/api/goals/projection`; `grep -rn "goals/projection" resources/mobile` and a grep of
`ios-native` for `expense_event|income_event|starting_net_worth` both return nothing.
The /m goals screen already keeps goals and life events in separate cards, so the mixing
this item describes has no /m equivalent to reproduce.

### Tests

`tests/Feature/Goals/LifeEventTotalsCountWhatTheySayTest.php`, `describe('a goal is not a
life event')` — **3 passing**: the goal-only account reads zero outflow events, a mixed
account counts only its life events, and the projection still models the goal as an
outflow.

**One of these caught the clamp trap in my own test** (`tests/CLAUDE.md` §4, added the
previous day). The third test asserted `ending_net_worth` falls when a goal is added, and
passed against an asset-less fixture because cash is floored at zero — *"Failed asserting
that 0.0 is less than 0.0."* I had written the fixture variant and the clamp variant in
one assertion. Fixed by giving the household £900,000 so the outflow is visible above the
floor, plus a guard asserting the baseline is greater than zero so the probe cannot
silently sink back onto the floor.
