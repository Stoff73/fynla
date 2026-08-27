---
id: W-0245
title: The web and /m dashboards duplicate the whole card-building computed, so a card can be fixed on one surface and not the other
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: review
severity: medium
surfaces: [web, m]
created: 2026-08-22T20:50:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0238, W-0015]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found while fixing **W-0238**, partly addressed there and filed for the remainder.

### The defect

`resources/js/views/GamifiedDashboard.vue` and `resources/mobile/views/Dashboard.vue`
each build the same five cards from the same endpoint with their own near-identical
computed. They had already drifted:

| | web | `/m` |
|---|---|---|
| retirement headline fallback | `pot_value` → `income_gap` → `value` | `pot_value` → **`net_worth…assets.pensions`** → `income_gap` → `value` |

The `/m`-only reach into the net worth block is the shape of the disease: one
surface was patched for a symptom and the other was not, and nothing made them
disagree loudly.

### What W-0238 did about it

Extracted **only the retirement headline rule** into
`resources/js/utils/retirementHeadline.js`, imported by both (the `ownership.js`
precedent, where `/m` imports the web util by relative path). Both fallback chains
are deleted.

**The other four cards are still duplicated** — net worth, protection, savings,
investment — including the emergency-fund bar, the equity percentage and the
holdings caption.

### Why the rest was not folded in

The two computeds differ in ways that are **not** drift and must be preserved: the
labels (`Savings` vs `Bank Accounts`), the routes (`/net-worth/cash` vs `/savings`),
and the visualisation parameters. Separating the shared derivation from the genuine
per-surface presentation is a refactor of two large files with its own verification,
and doing it inside a defect fix would have put an untested rewrite behind a
one-line correction.

## Acceptance

1. One home for every card's **derivation**; labels, routes and visualisation stay
   per-surface.
2. `retirementHeadline.js` is the pattern to follow, not a second one to add to.
3. Both dashboards verified against the same payload after the extraction, on web
   AND `/m` — noting `/m` serves `public/m-build/` and never Vite, so it needs a
   bundle rebuild to verify at all.

## Done 2026-08-26

### What was extracted

`resources/js/utils/dashboardCards.js` — `dashboardFigures(payload)`, following the
`retirementHeadline.js` precedent rather than adding a second mechanism (acceptance
2). It imports `retirementHeadline` itself, so each surface makes **one** call and
the retirement rule keeps its existing home.

Both computeds now read:

    const f = dashboardFigures(this.data);

and everything after that line is presentation.

**Shared** — the numbers, and the captions. "Emergency fund on track", "Cover in
place", "Add your investments", "6 holdings" were byte-identical strings in two
files; Rule 20 says one wording has one home, so a change to any of them now reaches
both surfaces by construction.

**Per-surface, deliberately left alone** (acceptance 1) — labels (`Savings` vs
`Bank Accounts`), routes (`/net-worth/cash` vs `/savings`), the visualisation each
card uses, and `/m`'s **"Target not set"** empty state on the retirement bar where
web prints `0%`. That last one is `/m` being better, not `/m` drifting, so it stayed.

### The drift the item named

Web read `net_worth.breakdown.total_assets`; `/m` summed
`net_worth.breakdown.assets` under a comment stating *"there is no flat nw.assets
field in the payload"*. **True of `nw.assets`, false of `breakdown.total_assets`,
which does exist.** Checked against a live payload: both give **£1,635,000**, so this
was duplicate work rather than divergent answers — but two reads of one fact can part
company when the backend changes and one cannot. Now read once, with a test pinning
the flat field against the sum of the map.

### Verified on both surfaces, one payload (acceptance 3)

`peak_earners`, `GET /api/v1/mobile/dashboard`, `/m` rebuilt via
`npm run build:mobile` since it never serves Vite:

| Card | web | `/m` |
|---|---|---|
| Net worth | £1,464,500 · £1,635,000 assets | identical |
| Protection | £700,000 · Cover in place | identical |
| Savings | £74,750 · Emergency fund on track | identical |
| Retirement | £500,000 · Towards your target | identical |
| Investment | £172,500 · 6 holdings | identical |

Labels differ only where intended.

**One false alarm worth recording.** A first pass showed web at Protection **£0 /
"Add your cover"** against `/m`'s £700,000, which looked exactly like the defect this
item exists to remove. It was a lapsed preview session — the page had redirected to
`/login` and the payload came back empty. Re-run with a fresh login, the two agree.
**Reported here rather than as a finding, because a stale session and a real
divergence present identically** and the next person to verify this will hit it too.

### Tests

`tests/frontend/utils/dashboardCards.test.js` — 6 cases pinning the derivation
against the live payload shape: every card's figures, the flat-field/summed-map
equivalence, modules arriving as an array as well as a map, an empty payload not
dividing by zero, a portfolio being the whole of the assets when nothing else is
recorded, and percentages clamped at both ends since both surfaces render them
straight into a ring.

Existing suites unchanged and green: `views/Dashboard.test.js`,
`mobile/Dashboard.test.js`, `utils/retirementHeadline.test.js` and
`components/Dashboard` — **67 passed**. ESLint clean on all four touched files.

### Raised, not fixed

**W-0504** — three of `/m`'s five donut rings are filled to constants (`72`, `85`,
`72`) from the 2026 redesign commit, not to derived figures. The net worth ring
renders at 72% beside a printed `+0%`, and the investment ring at 72% where
investments are 11% of that household's assets. Left as found: a constant is not a
derivation, and choosing what each ring should show is a design decision. The derived
values are now in scope at each site and the `ponytail:` comments there point at the
item.

### Noted

`resources/js/CLAUDE.md` states *"Nothing under `resources/js/` is shared with it
[`/m`] — a fix here does not reach `/m`."* That is now wrong for three files:
`ownership.js`, `retirementHeadline.js` and `dashboardCards.js` are all imported by
`/m` by relative path, which is the precedent this item was told to follow. **Not
amended here** — it is a shared conventions doc and correcting it is a separate
change from the one this item asked for.
