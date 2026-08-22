---
id: W-0028
title: "/m page titled \"Goals and life events\" renders no life events — it never fetches them"
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0006-batch-d-protection-goals.md
owner: build-lead
status: handoff
severity: high
surfaces: [m]
created: 2026-08-21T10:25:00Z
claimed: 2026-08-21T11:00:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21
prior_art_found: [GET /api/life-events, LifeEvent::$appends display_event_type]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **`/m` discovery sweep**, local
`localhost:8000`, premium. Account **David Jones (16)**.

**Surface:** `/m` mobile web, `/m/app/goals`. Not touched by Batch A, B or C.

Rule 19: "done" = web AND `/m`. Life events exist on web and are entirely absent from
`/m`.

### Expected

Persona `tests/Persona/peak_earners.md:654-667` gives ten life events. Eight are
currently recorded for David (two could not be entered — see Working notes):

| Event | Amount | Date | Impact | Certainty |
|---|---|---|---|---|
| Kitchen & Extension | £85,000 | 2027-04-01 | expense | likely |
| Replace BMW X5 | £55,000 | 2028-03-01 | expense | confirmed |
| Charlotte's University Costs | £45,000 | 2028-09-01 | expense | likely |
| William's Wedding Contribution | £25,000 | 2030-08-01 | expense | speculative |
| Parents' Estate (David) | £200,000 | 2035-06-01 | **income** | possible |
| Grandchildren Education Fund | £100,000 | 2041-01-01 | expense | speculative |
| Downsizing Property Sale | £350,000 | 2046-06-01 | **income** | possible |
| World Cruise | £45,000 | 2046-11-08 | expense | speculative |

A page whose own title is "Goals and life events" should show them.

### Actual

`/m/app/goals` renders **only goals**. The complete page text is:

```
Good morning, David | Goals and life events |
Your financial milestones and how they're tracking |
Goals on track | 3 of 3 | £308,000 of £740,000 saved so far. |
OVERALL PROGRESS | ... | YOUR GOALS |
Early Retirement Fund ... | ISA Wealth Building ... | William's House Deposit Help ...
```

No events section, no tab, no toggle. Verified against the DOM, not just the visible
viewport: none of `Kitchen`, `Inheritance`, `World Cruise` or `Downsizing` appears
anywhere in `document.body.innerHTML`, and there is no tab control in the page.

### Root cause

`resources/mobile/views/modules/Goals.vue:2`:

```vue
<MobileChrome title="Goals and life events"
              subtitle="Your financial milestones and how they're tracking" ...>
```

`:174-175` — the only data it fetches:

```js
apiGet('/api/goals', store.token),
apiGet('/api/goals/dashboard-overview', store.token),
```

Grepping the file for `life.event|lifeEvent|life_event` returns **zero hits**. The
page never calls `/api/life-events`, has no rendering for events, and no route exists
for them on `/m` (`resources/mobile/router.js` has `/goals` and `/goals/:id` only).

Desktop has the equivalent at `/goals?tab=events`, where all eight render correctly.

### Why it matters

Life events are not decoration — they drive the net-worth projection, the financial
forecast and the estate life-events impact. A `/m` user planning a £350,000 downsizing
and a £200,000 inheritance sees no sign the app knows about either, on the one page
that claims to cover them. The title makes it worse than a missing feature: it tells
the user the data should be there.

### Evidence

Full page text and the DOM check quoted above from the live page. Desktop equivalent
verified against the database (all 8 rows correct — amounts, dates, impact direction,
certainty).

**No screenshot of the absence** — a screenshot of a page that simply lacks a section
evidences little; the complete page text plus the zero-hit grep are stronger.

Report: `reports/R-07-m-sweep.md`.

### Repro

1. Account with life events recorded (visible on desktop at `/goals?tab=events`).
2. `/m/app/goals`.
3. Page reads "Goals and life events" and lists goals only.

## Acceptance

- [ ] `/m/app/goals` fetches and renders life events, or the page title stops claiming
      them.
- [ ] Events show amount, date, impact direction (income vs expense) and certainty —
      the four fields the persona specifies and desktop displays.
- [ ] Consider a `/m` route for a single event, matching desktop's detail view.
- [ ] Check whether the `/m` net-worth projection already consumes life events through
      a different endpoint — if it does, the data is reaching `/m` and only this page
      omits it, which is a smaller fix.
- [ ] Verified on both accounts (Rule 19).

## Working notes

(append-only)

- 2026-08-21 persona-tester: found during the re-tasked `/m` sweep. Not fixed by me —
  routed to build-lead.
- **Two of the persona's ten events could not be entered at all**, so they are not part
  of this item's expected set: "Previous Inheritance (David's Aunt)" £45,000
  2020-03-15 and "Annual Bonus" £35,000 2026-04-01. Both have **past** dates and the
  Expected Date input carries `min` = tomorrow. Same constraint blocks two of the six
  persona goals (Max Pension Contributions 2026-04-05, Charlotte's Gap Year Fund
  2026-08-01). Raised separately as W-0029.
- What is right on this page: the goals half is accurate. £308,000 of £740,000, 42%
  complete, and every "months left" figure was recomputed by hand and matches (77, 104,
  13). Sarah's individual goal correctly does not appear on David's page.

- 2026-08-21 build-lead: **fixed.** `resources/mobile/views/modules/Goals.vue` now
  fetches `/api/life-events` alongside the two goal endpoints and renders a
  "Life events" section: name, `display_event_type`, signed amount (spring for
  income, raspberry for expense), date, and certainty — plus an
  "£X expected in / £Y expected out" pair mirroring the web tab's income/expense
  summary. Empty state: "You haven't recorded any life events yet."

  **Rule 20 check, done and reported:** the web tab
  (`EventsTab.vue` → Vuex `goals/lifeEvents` → `goalsService`) and `/m` now read the
  **same endpoint** — that endpoint is the shared mechanism. `/m` is an isolated
  bundle with its own `api.js` and store by architecture (root `CLAUDE.md` → Mobile
  Clients), so there is no shared JS module to consolidate into; what mattered was
  not growing a second vocabulary. The type label comes from `LifeEvent`'s appended
  `display_event_type`, not a copied map, and the certainty label is the record's own
  value capitalised. `MobileDashboardAggregator` does not carry life events, so there
  was no cheaper existing source on `/m`.

  **Live verification (localhost:8000, `/m/app/goals`, David Jones 16):** all nine of
  David's events render in date order — "Previous Inheritance (David's Aunt)
  Inheritance +£45,000 15 Mar 2020 CONFIRMED" through "World Cruise Large Purchase
  -£45,000 08 Nov 2046 SPECULATIVE" — with totals "£595,000 expected in" and
  "£355,000 expected out", both reconciled by hand against the database rows.
  `public/m-build` was rebuilt (`npm run build:mobile`) to verify; it is gitignored.
  **I could not capture a screenshot** — the Playwright screenshot call timed out
  twice waiting on fonts under the parallel-agent load. The full page text above is
  the evidence.

  **Tests:** `resources/mobile/views/modules/__tests__/Goals.spec.js` — 5 pass
  (fetch made, every field rendered, completed status labelled over certainty,
  income/expense split, empty state).

  **Not done, deliberately:** no `/m` route for a single event ("consider" in
  acceptance). The rows carry every field the persona specifies and desktop displays,
  so a detail route would add a tap target with nothing new behind it. Say the word
  and it is a small addition.
