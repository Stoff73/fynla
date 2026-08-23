---
id: W-0207
title: A completed 2020 life event is counted as future expected income and displayed as happening "In 0 years"
mission: persona-run-peak_earners-2026-08-20
branch: F-0021
owner: build-lead
status: gated
severity: medium
surfaces: [web, m]
created: 2026-08-22T01:40:00Z
claimed: 2026-08-22T08:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0029, W-0206, W-0210]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Cycle 3 journey re-walk, local, `david.jones@example.com`, read-only.
**Surface:** `/goals?tab=events`.

### Expected

The page is headed **"Life Events — Future occurrences that will impact your financial
position"**. The persona's "Previous Inheritance (David's Aunt)" is dated **2020-03-15**
and marked **Confirmed (Completed)** — money already received six years ago. It is not a
future occurrence and must not be counted as expected income.

### Actual

```
Expected Income   £595,000   3 events

+ Inheritance   Confirmed
  Previous Inheritance (David's Aunt)
  Amount    +£45,000
  Expected  15 Mar 2020
  In        0 years
```

Listed as a future occurrence, counted in the **£595,000** of expected income (45,000 +
200,000 + 350,000), and labelled **"In 0 years"** — the elapsed-time calculation floors a
six-year-old date at zero rather than recognising it as past.

### Impact

£45,000 of money already spent or absorbed is presented as income still to come, on the
module that feeds cash-flow projections and goal trajectories. "Expected 15 Mar 2020 · In
0 years" is also self-contradictory on its face.

The persona deliberately contains two past-dated events for exactly this reason; the
second (Annual Bonus, 2026-04-01) is not in the database, so this is the only one
currently exercising the case.

### Repro

1. `david.jones@example.com` → `/goals?tab=events`, wait ~15s.
2. Read the header: "Future occurrences that will impact your financial position".
3. Read "Expected Income £595,000 · 3 events".
4. First card: dated 15 Mar 2020, marked Confirmed, "In 0 years", counted in the total.

### Acceptance

1. A past-dated event is excluded from "expected" totals, or the totals are relabelled to
   include history and the two are shown separately.
2. Elapsed time for a past date reads as past, never "In 0 years".
3. A completed event is visually distinguishable from a pending one.
4. Verified with both a past-dated and a future-dated event, on web and `/m`.

---

## Outcome — DONE

**One home:** `LifeEvent::hasOccurred()` (the predicate, served as `has_occurred`) +
`LifeEventService::summariseUpcoming()` (the totals) +
`resources/mobile/utils/lifeEvents.js` (the shared frontend helper).

### The clamp destroyed the evidence at source

`LifeEvent::getYearsUntilEventAttribute()` ended `return max(0, (int) ceil($diff));`.

**The sign was the only thing carrying "this has already happened", and the clamp threw
it away before any consumer saw it.** From the accessor outwards, a 2020 event and one
happening this year were byte-identical, so no surface could have got this right however
carefully it read the value. The reported "In 0 years" *is* the clamp. Removed; the
arithmetic for events still to come is unchanged.

### Four implementations, none of which asked the question

A fourth site turned up while tracing, and it was the worst of them:

| Site | What it showed |
|---|---|
| `GoalsProjectionService::buildSummary` | £595,000 expected income |
| `EventsTab.vue` (web) | "Expected Income £595,000" under "Future occurrences" |
| `resources/mobile/views/modules/Goals.vue` (`/m`) | "£395,000 expected in" |
| **`LifeEventIntegrationService::getModuleImpactSummary`** | fields *named* `upcoming_income` / `upcoming_expense`, filtered by date **not at all** — and since `next_event` takes the earliest date, **the 2020 inheritance was named as David's NEXT event on five module dashboards** (estate, protection, savings, investment, retirement) in a panel headed "Upcoming Life Events", six years after the fact |

**Consolidated 4 → 1** per Rule 20. The predicate lives once on the model and is served
to every client; the totals live once in the service; web and `/m` share one helper.
`getEventsForModule()` is filtered too — the panel says "Upcoming", so it now is.

### Measured, live database

| | before | now |
|---|---|---|
| David expected income | £595,000 / 3 events | **£550,000 / 2 events** |
| `years_until_event` (event 82) | 0 | **−5** |
| `has_occurred` (event 82) | not served | **true** |
| Estate panel "next event" | Previous Inheritance (David's Aunt) | **Kitchen & Extension** |
| Card timing row | "In 0 years" | **"Already happened"** |

### A judgement call, stated plainly

The past event **stays in the list** and is excluded only from forward-looking
**totals**. Hiding it would leave the user able to see its effects and unable to reach it
to correct or complete it. **W-0029** deliberately made past-dated events creatable — and
created this very record, `"Previous Inheritance (David's Aunt)"`, in its own test file.
The fault was never that the record exists; it is that nothing downstream asked whether
it had happened. `status = 'completed'` alone is insufficient for the same reason:
nothing obliges a user to mark a past-dated event complete, and this one is still
`expected` six years on.

### Surfaces

- **`/m`: had the counterpart, now fixed.** "expected in" / "expected out" on the goals
  screen now come from the shared helper.
- **Native: no counterpart.** `GoalsClient.swift` calls only `api/goals`,
  `api/goals/dashboard-overview`, `api/goals/{id}`; grepping all of `ios-native` for
  `life_event|LifeEvent|has_occurred|years_until` returns **zero hits**.

### The `/m` test was asserting the bug

`resources/mobile/views/modules/__tests__/Goals.spec.js` held a fixture event named
**"Previous Inheritance (David's Aunt)", `status: 'completed'`, dated 2020-03-15**, and
asserted `'£395,000 expected in'` — £350,000 plus that inheritance. **It named the
offending record and asserted it as expected income in the same breath**, so it could
never have failed on this defect: a textbook fixture-variant of *a test that shares the
code's misconception cannot fail*. Corrected to £350,000, with a second test pinning the
exclusion by name.

### Tests

`tests/Feature/Goals/LifeEventTotalsCountWhatTheySayTest.php`, `describe('an event that
has already happened is not money still to come')` — **6 passing**, covering the
projection summary, the served API summary, the model predicate, the module panel, and
both boundaries: an event dated **today** still counts (the day is not out), and an event
the user marked **completed** does not, even with a future date.
