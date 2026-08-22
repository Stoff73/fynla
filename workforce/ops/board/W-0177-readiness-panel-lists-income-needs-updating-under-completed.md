---
id: W-0177
title: The readiness panel lists "Income needs updating" under COMPLETED while reporting OUTSTANDING (0) — All items complete
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0017-cycle1-tax-income-and-allowances.md
owner: build-lead
status: handoff
surfaces: [web]
created: 2026-08-21T23:55:00Z
claimed: 2026-08-22T20:15:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22T20:15:00Z
prior_art_found: ["app/Services/UserProfile/ModuleDataRequirementsService — the one declaration of what each module needs", "resources/js/utils/moduleMap.js — the one route-to-module resolution shared by ModuleStatusBar and InfoGuidePanel", "app/Http/Controllers/Api/InfoGuideController — the one endpoint serving it"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local `localhost:8000`, **David Jones
(`users.id` 16)**.

**Surface:** desktop web, the readiness panel (`ModuleStatusBar`) at the top of
`/valuable-info?section=income`, which resolves to the `profile` module.

### Expected

A panel whose entire job is telling the user what is still missing must not contradict
itself.

### Actual

> **COMPLETED (9)**
> · Income needs updating
> · Your date of birth
> · …
>
> **OUTSTANDING (0)** — All items complete

"Income needs updating" is listed as a thing the user has *completed*, on the same panel
that says nothing is outstanding.

**The backend was behaving exactly as declared; the declaration was wrong.**
`income_needs_update` is a staleness flag raised when employment status changes.
`ModuleDataRequirementsService::isFieldFilled()` inverts it — flag down means "filled" —
and the loop then files every filled requirement under `filled`, label and all. So the
lowered flag was counted as an achievement and printed as its own problem statement.

It is declared on **two** modules, `profile` and `protection`, so both surfaces carried
it.

### Impact

Small in pounds, disproportionate in trust. This is the one component a user consults to
find out what is left to do; a completeness panel that miscounts is worse than no panel,
because it is consulted *instead of* looking. It also inflated the denominator: a fully
complete profile could never read 100% while the flag existed as a countable item.

### Repro

1. Log in as `david.jones@example.com` (`users.income_needs_update` = 0).
2. Any page resolving to the `profile` module — `/valuable-info?section=income`.
3. Expand the readiness panel: COMPLETED (9) with "Income needs updating" first,
   OUTSTANDING (0) beneath it.

### Acceptance

1. A lowered flag produces no entry at all — not under Completed, not under Outstanding.
2. It is not counted in the total, so a complete profile reads 100%.
3. A raised flag appears under Outstanding.
4. One rule covering every module that declares such a flag (Rule 20), not a per-module
   patch.
5. Ordinary requirements are unaffected — a genuinely missing field still reports.

## Working notes

**2026-08-22 — build-lead (`cycle1-tax`). Fixed.**

`ModuleDataRequirementsService::FLAG_REQUIREMENTS` names the class of requirement that
exists only while unsatisfied; the field loop skips a satisfied one.
`app/Services/UserProfile/ModuleDataRequirementsService.php:602-614` (the constant),
`:641-646` (the skip). One constant covers `profile` and `protection` together.

Measured against the live persona (flag flipped **in memory only, never saved** —
`isDirty()` true, no `save()`, nothing written to user 16):

| Flag | Panel |
|---|---|
| down | filled 8, total 8, **100%**, missing empty, no contradictory entry |
| up | filled 8, total 9, "Income needs updating" under OUTSTANDING |

Tests: `tests/Unit/Services/UserProfile/FlagRequirementCompletionTest.php` — 6 passing,
including the `protection` module and an ordinary missing field still reporting.

**Surfaces.** Web only, stated rather than assumed: neither `resources/mobile/` nor
`ios-native/Fynla/` renders the info-guide panel — grepped for `info-guide`,
`infoGuide` and `ModuleStatusBar` across both. The fix is server-side, so any future
surface inherits it.

Not done: browser verification, by instruction.
