---
id: W-0198
title: Two columns hold one life expectancy — the override now agrees everywhere, the fallbacks still do not
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T07:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [F-0004, F-0018]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: `cycle2-projection` while closing R7 in `F-0018`. Raised, not built —
merging two columns is a data-model decision, not a defect fix.

### Expected

"How long does this person expect to live" is one fact with one home.

### Actual

Two columns hold it, and three modules combine them differently:

| Where | Rule |
|---|---|
| `RetirementAgent:178` | `life_expectancy_override ?? retirement_profiles.life_expectancy ?? 85` |
| `DecumulationController:62` | `life_expectancy_override ?? retirement_profiles.life_expectancy ?? 85` |
| `FutureValueCalculator::getLifeExpectancy():39` | `life_expectancy_override`, else the Office for National Statistics actuarial tables |

`F-0018` routed the estate's inheritance tax projection through
`FutureValueCalculator::getLifeExpectancy(User)`, so **the override is now honoured
everywhere**. It previously called `getLifeExpectancyYears(int $age, string $gender)`,
the one method that never receives the user and therefore could not see the override
however it was written.

**The fallbacks still disagree.** A household that has filled in
`retirement_profiles.life_expectancy` but not `users.life_expectancy_override` is
answered by that profile figure in retirement and decumulation, and by the actuarial
tables in the estate. `retirement_profiles.spouse_life_expectancy` is a third field
nothing outside `RetirementAgent` reads.

### Impact

The horizon scales every projected figure: the estate, the tax on it, life-cover
sizing and any decumulation plan. Two modules answering "when do I die" differently is
not a rounding difference.

### Acceptance

1. One column, or one resolver both columns feed, with a stated precedence.
2. Retirement, decumulation and the estate return the same number for the same person
   in every combination of the two columns being set.
3. Whatever the user typed still wins over anything derived.
4. `spouse_life_expectancy` is either wired up or removed — not left unread.

---

## Closed 2026-09-01 — one resolver, both columns feeding it

**Acceptance 1 — one resolver with a stated precedence.** Both columns stay (they mean
different things to the user: one is set in Personal Information, the other in the
retirement module), and both feed
`FutureValueCalculator::getLifeExpectancy()`, which was already the fullest
implementation and only lacked the middle step:

> `users.life_expectancy_override` → `retirement_profiles.life_expectancy` → the Office
> for National Statistics actuarial tables → 85 where there is no date of birth.

The order is written at the code, with its reason: **both of the first two are the
user's own statement and beat anything derived** (acceptance 3); the override wins
between them because it is the more deliberate — it exists only when someone has gone
to Personal Information to set it. The response says which one answered, via `source`.

**Acceptance 2 — every consumer returns the same number.** Four ad-hoc combinations
removed:

| Site | Was | Now |
|---|---|---|
| `RetirementAgent:196` | `override ?? profile ?? 85` | the resolver |
| `DecumulationController:62` | `override ?? profile ?? 85` | the resolver |
| `EstateAgent:194` | `override ?? 85` — could not see the profile figure at all | the resolver |
| `EstateAgent:393` | same | the resolver |

**Acceptance 4 — `spouse_life_expectancy` is wired up, and finding out how it was used
turned up a second defect.** It was captured, prompted for when the user is married,
and **read by nobody as a number**: both consumers used it only as `!== null` to mean
"has a spouse". So the figure the user typed was discarded, **and a married user who
left the field blank was compared against a single-life annuity** in
`compareAnnuityVsDrawdown()`.

Both halves are now separate questions with correct answers:
- `getSpouseLifeExpectancy()` — a **linked** spouse is resolved as themselves, because
  their override and their retirement profile are their own statements; only an
  unlinked partner falls to the figure the primary user typed on their behalf.
- `hasSpouse()` — reads `spouse_id` or the marital status, **including civil
  partnerships**, since reading `['married']` alone is the W-0508 defect.

### Tests

`tests/Unit/Services/Estate/LifeExpectancyOneAnswerTest.php` — 9 tests: each precedence
step in turn, the spouse figure read, a linked spouse resolved as themselves, a married
user with a blank field still having a spouse, a civil partnership counted, a single
user not given one, and a guard that reads the three consumer files and fails if any
re-introduces its own combination.

The guard filters comment lines deliberately — the sites quote the old expressions so a
reader arriving at the line learns what was wrong, not only what is there now.

**Regression:** 626 tests across estate services, retirement features and the agents.

**Rule 19:** the resolution is server-side on the shared path, so web, `/m` and Fyn all
get the same answer. No frontend combines these columns — `grep` for
`life_expectancy_override` across `resources/` returns only form bindings.
