---
id: W-0507
title: The free-tier estate teaser prints a second-death Inheritance Tax figure with none of the caveats the full table carries
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [compliance-lead]
status: done
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-24T19:05:00Z
claimed: null
blocked_by: []
gate: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0466, W-0363, W-0467]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
source: observed on csjones while verifying the 24 August deploy — the peak_earners demo estate page
---

## Intent

Observed live on `csjones.co/fynla/estate` as the `peak_earners` demo, 2026-08-24:

> "Your household could face up to **£343,512** in Inheritance Tax on the second death."
> "Estimated Inheritance Tax exposure: **£343,512**"

**"On the second death" is the projected model.** Two caveats now attach to that figure
on the full table and neither reaches the teaser:

- `unmodelled_relief_caveat` (W-0466) — Agricultural Property Relief and the
  Alternative Investment Market are not modelled.
- `projected_pension_exclusion_caveat` (W-0363) — unused defined contribution pensions
  are excluded from the projected estate, and form part of it from April 2027.

Both live in `IHTCalculationTable.vue`, which the teaser does not render — the teaser is
its own component behind the upgrade gate. **This is the W-0466 F3 shape exactly**: the
caveat was put where the figure was thought to live, and a second surface prints the
same figure without it.

**Who sees it:** every free-tier user, which is every user who has not upgraded — and
the demo personas, which is the first thing a prospective customer sees.

## Acceptance

1. The teaser carries the same caveats as the full table when they apply, from the same
   engine-published strings (Rule 20 — no second copy of either sentence).
2. `/m`'s estate teaser too (Rule 19).
3. A guard that a surface printing an Inheritance Tax figure also renders whatever
   caveats the engine published with it — the third instance of this, so it wants a
   test rather than a third manual fix.
4. `compliance-lead` on the copy.

## Working notes

- 2026-08-24 — Found while browser-verifying the deploy, not by a test. Worth noting how:
  the estate page for a preview persona is the free-tier teaser, so anyone verifying the
  caveat work on a demo account sees a page where it legitimately does not appear — and
  could easily read that as "verified".

## Provenance — renumbered on salvage, 2026-08-28

Raised as **W-0483 on `main`** (commit `526327655`, 2026-08-24) and never merged to
`dev`, because that work went to `main` directly. `dev` had meanwhile assigned W-0483 to
an unrelated item — "a co-owner who borrowed alone cannot be shown as owing alone" — so
this takes the next free id rather than displacing it.

Nothing else in the file was touched: the observation, the two named caveats, the
acceptance criteria and the `compliance-lead` reviewer are as originally written. The
cited prior art (W-0466, W-0363, W-0467) all exist on `dev` under those ids, so the
`extend` outcome still reads correctly.

This is the second item to survive the `main` reconciliation; the first was
`PremiumTestPersonaSeeder` in PR #734. Everything else `main` held was either stale,
deliberately deleted here, or superseded.

## 2026-09-01 — CLOSED

**Acceptance 1 — both caveats, from the engine's own strings.** The engine already
published both as finished sentences; the teaser detector passed one and the web teaser
rendered neither. `EstateIhtExposureDetector::detect()` now returns
`projected_pension_inclusion_caveat` beside `unmodelled_relief_caveat`, and
`resources/js/views/Estate/EstateDashboard.vue:52-63` renders both under the figure.
No sentence is written in a component — a guard below proves it.

**Acceptance 2 — `/m`.** `resources/mobile/views/modules/Estate.vue` already rendered
the relief caveat and now renders the pension one at `:31`. Both surfaces read the same
two keys from the same detector.

**Acceptance 3 — the guard, because this is the third instance.**
`tests/Feature/Estate/EveryIhtFigureCarriesItsCaveatsTest.php` asserts four things: the
engine publishes both as sentences; the teaser detector passes both through; **every
surface printing `estimated_liability_gbp` renders both keys**; and neither engine
sentence is duplicated into a frontend bundle. A manual fix has not held three times, so
the correspondence is now measured rather than remembered.

**A distinction the guard cost a round to get right, and worth recording.**
`IHTPlanning.vue:620-630` carries a component-authored sentence — "£X of pension savings
is left out of the figures above… that changes on {date}". It is about the **current**
column, not the projected one, the engine publishes no equivalent, and it is true. It is
therefore not duplicated caveat copy and the guard does not fail it. **It is worth
publishing from the engine one day so the teaser can say it too** — named here rather
than fixed, because deleting a true sentence to satisfy a guard would be the wrong
direction.

`EstateIhtExposureDetectorTest:51` asserts the detector's exact key set — a Rule 12
tripwire against a score appearing in the free-tier payload. Extended deliberately, with
the reasoning at the line, rather than loosened.

Tests: 22 passed across the two detector/caveat files; **842 passed, 1 failed → 0** on
the Estate/IHT/Teaser filter (the one failure was that key-set assertion, now updated);
frontend **466 passed, 60 files**.

**Not done:** acceptance 4, `compliance-lead` on the copy — no copy was written here.
Both sentences are the ones compliance already reviewed for W-0466 and W-0482; this
change moves where they are rendered, not what they say. No browser drive.
