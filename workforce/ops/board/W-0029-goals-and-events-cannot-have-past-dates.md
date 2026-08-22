---
id: W-0029
title: Goals and life events cannot be dated today or earlier — completed and missed milestones are unrecordable
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0006-batch-d-protection-goals.md
owner: build-lead
reviewers: [product-lead]
status: handoff
severity: medium
surfaces: [web, m, ios]
created: 2026-08-21T10:30:00Z
claimed: 2026-08-21T11:00:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21
prior_art_found: [UpdateGoalRequest, UpdateLifeEventRequest, CoordinatingAgent::handleCreateLifeEvent]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **`/m` discovery sweep** (entry phase), local
`localhost:8000`, premium. Account **David Jones (16)**.

**Surface:** desktop web, `/goals` (Create Goal) and `/goals?tab=events` (Add Life
Event). Not touched by Batch A, B or C.

Flagged for `product-lead` because the correct answer may be "working as intended" —
but if so the persona is unrepresentable and that needs saying explicitly.

### Expected

`tests/Persona/peak_earners.md` contains four dated records at or before today
(2026-08-21):

| Record | Date | Persona note |
|---|---|---|
| Goal: Max Pension Contributions | 2026-04-05 | target £60,000, current £45,000, streak 36 months |
| Goal: Charlotte's Gap Year Fund | 2026-08-01 | target £15,000, current £12,000 |
| Event: Previous Inheritance (David's Aunt) | 2020-03-15 | £45,000, **"Confirmed (Completed)"** |
| Event: Annual Bonus | 2026-04-01 | £35,000, likely |

The inheritance is explicitly marked **Completed** — a past event the persona intends
to be on record.

### Actual

None of the four can be created. The date input carries a `min` of **tomorrow**:

```
Target Date input:  min = "2026-08-22"
validationMessage:  "Value must be 22/08/2026 or later."
form.checkValidity(): false
```

The submit is blocked client-side — no request is issued at all (verified with an
XHR hook: zero calls to `/api/goals`). Backend agrees: `StoreGoalRequest` requires
`target_date` after today, and `StoreLifeEventRequest` requires `expected_date` after
today.

Note `min` is tomorrow, not today — so a goal or event dated **today** is also
rejected.

### Why this is worth a decision rather than a silent constraint

- A **completed** life event is a normal thing to record. The persona's £45,000
  inheritance in 2020 is family financial history that belongs in the estate and
  gifting picture; `LifeEvent` even has a `status` enum including `completed`, and
  `POST /api/life-events/{id}/complete` exists — so the model clearly anticipates past
  events, while creation forbids them.
- A **missed** goal is also normal. A user who did not hit their April target still
  wants it on the books.
- There is a knock-on: an existing goal presumably becomes uneditable once its target
  date passes, because editing re-validates against the same rule. **I did not test
  that** — see Acceptance.

### Evidence

`min` attribute, `validationMessage` and `checkValidity()` read from the live DOM;
absence of any outbound request confirmed with an `XHR` hook.

**No screenshot** — for a real user the browser shows its native validation bubble on
the date field, so this is *not* a silent failure from their side; my programmatic
submit simply bypassed the bubble. Recording that distinction so severity is not
overstated.

Report: `reports/R-07-m-sweep.md`.

### Repro

1. `/goals` → Add Goal → any type, fill everything, set Target Date to any date on or
   before today.
2. Create Goal does nothing; the date field reports "Value must be 22/08/2026 or later."
3. Same on `/goals?tab=events` → Add Life Event with a past Expected Date.

## Acceptance

- [ ] `product-lead` decides: are past-dated goals and completed past events supposed
      to be recordable? The `completed` status and the `/complete` endpoint suggest
      yes for events at least.
- [ ] If yes: relax `min` and the `after:today` rules, at minimum for events, and allow
      today as a valid date in both.
- [ ] If no: the persona file needs its dates refreshed, and that is a deliberate
      decision to record — not something a tester should quietly do.
- [ ] Either way, confirm whether an **existing** goal or event can still be edited
      once its date passes. If not, every goal in the system eventually becomes
      read-only, which is almost certainly unintended.
- [ ] `/m` and iOS entry paths carry whatever rule is chosen (Rule 19).

## Working notes

(append-only)

- 2026-08-21 persona-tester: found while entering the persona's goals and life events
  for the `/m` sweep. Not fixed by me. **I did not edit the persona file** to work
  around it — 4 of 16 records are simply not entered, and that is recorded in
  `reports/R-07-m-sweep.md`.
- Related observation, not raised separately: the persona gives contribution **streaks**
  (36 and 60 months) for two goals, but `contribution_streak` is earned through
  recorded contributions and there is no field to seed it. The persona's streaks are
  therefore unrepresentable by design rather than by defect.
- Also noted: "William's House Deposit Help" auto-assigned to module **property**
  (`GoalAssignmentService` maps `home_deposit` → property) where the persona says
  **Savings**. The app's mapping is deliberate; the persona line looks stale. Not
  raised.

- 2026-08-21 build-lead: **relaxed, per the assumption stated below — product-lead
  still owns the ratification.** The lead assigned this as a fix, and the evidence
  for "past dates are supposed to be recordable" is strong enough to act on rather
  than block:
  - `UpdateGoalRequest` and `UpdateLifeEventRequest` have always accepted any date;
    only creation refused one. A rule that lets you edit a record into a state you
    cannot create is an inconsistency, not a policy.
  - The consuming services already guard past dates rather than assuming they cannot
    exist: `FinancialForecastService.php:222` and `GoalAffordabilityService.php:250`
    skip events before now, `GoalsProjectionService.php:514` only projects
    `target_date > now`, and `GoalCalculationService.php:34,48` clamp with
    `max(0, …)`. Nothing divides by a negative term.
  - `LifeEvent` carries a `completed` status and `POST /life-events/{id}/complete`.

  **Changed:** `StoreGoalRequest` `target_date` and `StoreLifeEventRequest`
  `expected_date` are now `sometimes|date` (today included); the stale
  "must be in the future" messages are gone; the `min="tomorrow"` computed is gone
  from `GoalFormModal.vue` and `LifeEventForm.vue`.

  **Rule 20 — the second home:** `CoordinatingAgent::handleCreateGoal` carried its
  own `target_date => required|date|after:today`. Fyn is the only goal entry route
  `/m` and native have, so relaxing only the form would have left `/m` rejecting
  what the web accepts. Relaxed to match. (`handleCreateLifeEvent` had always
  accepted any date — so Fyn could already record a past event the web form
  refused.)

  **Your open question answered:** an existing goal whose target date has passed is
  still editable — the backend never blocked it (covered by a test), and the form's
  `min` is now gone, so the client no longer blocks it either.

  **Live verification (localhost:8000, David Jones 16):** the persona's
  "Max Pension Contributions" goal (2026-04-05, £60,000 target, £45,000 current) and
  "Previous Inheritance (David's Aunt)" (£45,000 income, 2020-03-15, confirmed) both
  created through the forms — goal id 59, life event id 82. `/m/app/goals` shows the
  goal as "Target date passed" and the event dated 15 Mar 2020.

  **Tests:** `tests/Feature/Goals/PastDatedRecordsTest.php` — 6 pass, both
  past-dated creates confirmed RED against HEAD's request rules first.

  **Left alone, deliberately:**
  - The persona's inheritance is recorded but its status is `expected`, not
    `completed`: there is no "mark as completed" control anywhere in the UI, though
    `POST /life-events/{id}/complete` exists and the detail panel displays
    "Status: Expected". Adding that control is a feature nobody has specified — for
    `product-lead`, and the reason the persona record is not fully faithful.
  - `Investment/GoalForm.vue` and `Savings/SaveGoalModal.vue` carry the same
    `minDate = tomorrow` computed but write different entities (investment goals,
    the legacy savings-goal model) with their own backend rules. Out of this item's
    scope; four copies of one rule is worth consolidating if the decision extends.
  - Contribution streaks remain unrepresentable, as you recorded — no field to seed.
