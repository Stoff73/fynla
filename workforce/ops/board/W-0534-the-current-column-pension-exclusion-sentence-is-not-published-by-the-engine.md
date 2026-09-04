---
id: W-0534
title: The pension-exclusion sentence about the current column is written in a component, so the free-tier teaser cannot say it
mission: board-verification-31-august
owner: null
reviewers: [compliance-lead]
status: queued
severity: low
surfaces: [web, m]
created: 2026-09-04
source: named while closing W-0507, 2026-09-01
prior_art_checked: 2026-09-04
prior_art_found: [W-0507, W-0466]
prior_art_outcome: extends — W-0507 published the projected-column caveat from the engine; this is the current-column equivalent, deliberately left
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

W-0507 made every surface printing an Inheritance Tax figure render the caveats
the **engine** publishes with it, and added
`EveryIhtFigureCarriesItsCaveatsTest` to keep that true. One sentence was
deliberately left outside that mechanism, and the reasoning was recorded rather
than acted on.

`resources/js/components/Estate/IHTPlanning.vue:620-630` renders:

> £X of pension savings is left out of the figures above, because pension funds
> sit outside the estate for Inheritance Tax. That changes on {date}, when unused
> pots start counting towards the estate.

It is **true**, it is about the **current** column (not the projected one W-0507
covered), and the engine publishes no equivalent — so the guard correctly does not
fail it, and deleting it to satisfy the guard would be the wrong direction.

## Why it is still a defect

The component is behind the upgrade gate. A **free-tier user** sees the estate
teaser, which prints an Inheritance Tax figure computed with the same exclusion
and cannot say so, because the sentence lives in a component they never reach.
That is the W-0466 F3 shape a third time: the caveat put where the figure was
thought to live, while a second surface prints the figure bare.

Every preview persona is free tier, so this is also what a prospective customer
sees first.

## Acceptance

1. The engine publishes the current-column exclusion as a finished sentence beside
   `projected_pension_inclusion_caveat` and `unmodelled_relief_caveat`, with the
   date from configuration (Rule 2 — the date is never written in a component).
2. `IHTPlanning.vue:620-630` renders the published string instead of its own copy,
   so there is one sentence (Rule 20).
3. The teaser detector passes it through and both surfaces render it (Rule 19).
4. `EveryIhtFigureCarriesItsCaveatsTest` extends to the third key, including its
   "not duplicated into a frontend bundle" assertion.
5. `compliance-lead` — the sentence moves surfaces and now reaches free-tier and
   demo users, which is a change in who is told what.
