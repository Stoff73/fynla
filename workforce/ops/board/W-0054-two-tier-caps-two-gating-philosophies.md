---
id: W-0054
title: Two tier caps, two gating philosophies — life events block before entry, detailed expenditure blocks after submit with a silent 403
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-21T14:15:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: design-lead
prior_art_checked: 2026-08-21
prior_art_found: [W-0011]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **cap-lift test**, local `localhost:8000`.
Throwaway `users.id 31` (Tomas Weber), free tier. Raised at team-lead's direction as a
finding independent of the cap-lift result.

**Surface:** any free-tier capped module. Observed on `/goals?tab=events` and, via
W-0011, on `/valuable-info?section=expenditure`.

### Expected

A user at a tier cap learns one consistent thing about what "capped" means, whichever
module they are in.

### Actual

Two capped capabilities, two entirely different experiences:

| | `life_event` (cap 1) | `expenditure_detailed` (W-0011) |
|---|---|---|
| Where the block happens | **Before entry** | **After submit** |
| The Add control | visible and enabled | form fully editable |
| Clicking it | **form does not open**; an Upgrade affordance appears | form opens, accepts every field |
| On save | never reached | `PUT /api/user/profile/expenditure` → **403 `capability_denied`** |
| What the user sees | a prompt to upgrade | the form closes, **no error**, nothing saved |
| What the user learns | "this needs premium" | "I typed all that and the app lost it" |

Verified live at the cap: on `/goals?tab=events` with one life event recorded, the
"Add Life Event" button is `visible: true, enabled: true`, a **real pointer click**
leaves `formOpen: false`, and an Upgrade control renders.

The life-event behaviour is the **better** of the two — gate before entry, never after
work is done. W-0011 already calls for the expenditure gate to move before entry; this
item records that the inconsistency is the general problem, not a one-module bug, and
that the life-event path is the pattern to converge on.

### Impact

The cheaper harm is confusion. The real harm is the W-0011 shape: a user fills fifteen
expenditure categories and the app discards them silently. Someone who meets that once
has no reason to trust that any other form saved either.

There is also a commercial cost: a gate that appears *before* entry can offer an
upgrade at the moment of intent. A 403 after submit converts nobody and loses their
work.

### Repro

**Life events (gated correctly):**
1. Free-tier account. `/goals?tab=events` → add one life event (free cap is 1).
2. Click **Add Life Event** again. The form does not open; an Upgrade affordance shows.

**Detailed expenditure (gated incorrectly — W-0011):**
1. Same account. `/valuable-info?section=expenditure` → Detailed View.
2. Fill categories, Save. Form closes, no error, nothing persisted; the network shows
   403 `capability_denied`.

### Evidence

- `tests/Persona/20-08-2026_run/pass-a-web/40-web-FREE-tier-life-event-cap-baseline-user31.png`
- W-0011's own evidence for the expenditure path
- Free-tier `count_caps` read live: `property 1, investment 2, goal 2, life_event 1,
  pension_account 2, savings_account 2, mortgage 10`
- `TeaserGate::allows()` returned `false` for `life_events` at the cap; `mode` was
  `limited`

## Acceptance

- [ ] ONE gating philosophy across every tier-capped capability (Rule 20): **gate before
      entry**, never after submit, matching the life-event behaviour.
- [ ] Any capability at its cap presents the same shape of message and the same upgrade
      affordance, wherever it appears. `design-lead` owns that copy and pattern.
- [ ] No capped path can accept user input and then discard it. Where a 403 is still
      possible (a race, a cap reached in another tab), it surfaces as an error the user
      can see, and the entered data is not lost.
- [ ] Audit every capability in the matrix for which of the two shapes it currently
      uses — `property`, `investment`, `goal`, `savings_account`, `pension_account`,
      `expenditure_detailed`, `letter_to_spouse`, `estate`, `holistic_plan`,
      `document_upload`, `investments_exotic`, `joint_household_view`.
- [ ] Fixed alongside **W-0011**, which is the same problem seen from one module.
- [ ] `/m` and iOS carry the same single philosophy (Rule 19).
- [ ] Re-verified live in the browser by the persona run, at the cap, on at least two
      different capped capabilities.

## Working notes

Surfaced while establishing a baseline for the cap-lift test, not while looking for it.
The lift test itself passed cleanly (see R-15) — this is a separate observation about
what a capped user experiences before any upgrade happens.
