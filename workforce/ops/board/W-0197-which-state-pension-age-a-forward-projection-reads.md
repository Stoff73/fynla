---
id: W-0197
title: State Pension age is legislated by cohort, and the application holds two static keys — a projection decades out needs a resolver, not a choice between 66 and 67
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: chief-of-staff
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T07:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [F-0018, W-0154]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

Found by: `cycle2-projection` while closing R2 in `F-0018`. **This needs a decision.
It was deliberately not taken, because taking it would have been inventing a third
answer to a question two modules already answer differently.**

### The two answers

`TaxConfigurationSeeder:291-292` seeds both, and both are correct facts:

```
'current_spa' => 66,   // Current State Pension Age
'future_spa'  => 67,   // Rising to 67 between April 2026 and April 2028;
                       // further rise to 68 planned 2044-2046
```

| Key | Read by |
|---|---|
| `current_spa` | `RetirementIncomeService:566`, `AssumptionsService:424`, `AssetLocationController:258` |
| `future_spa` | `PensionEstimateService:219`, whose docblock calls it "the canonical value" for a projection |

### Why it matters here

The estate projection runs to a second death decades away. Nobody in the persona
household reaches State Pension age before the statutory rise completes, so `66` is
the wrong figure for them — and the further rise to 68 planned for 2044-2046 makes it
wrong for a larger group still.

`F-0018` routed the estate to `current_spa` to match the canonical resolver
(`RetirementIncomeService::getStatePensionStatus()`) rather than pick a third answer,
and recorded it rather than silently choosing.

### Impact

One year of State Pension income, per person, on every forward projection — and a
household is told one State Pension age by the retirement module and, potentially, a
different one by a marketing estimate of the same thing.

### The real fix — neither key is right

**State Pension age is legislated by birth cohort and changes over time.** `current_spa`
and `future_spa` are two snapshots of a moving statutory schedule, so *choosing between
them* cannot be correct for a projection running decades out — it is only ever less
wrong. A 46-year-old and a 26-year-old do not share a State Pension age, and a single
static key gives them one.

**What is needed is a resolver that takes a date of birth (or a date) and returns the
applicable State Pension age**, reading a schedule rather than a scalar — the same
effective-from shape as `project_salary_sacrifice_2k_upcoming_law`. That is the Rule 20
answer, and it replaces both keys rather than picking one.

### Interim, taken deliberately

`F-0018` routed the estate to **`current_spa`**, matching
`RetirementIncomeService::getStatePensionStatus()` — **the consistent option, not the
marginally-more-accurate one**, chosen on purpose while three services read one key and
one reads the other. Being uniformly one year out beats two modules disagreeing about
the same person, and it leaves exactly one place to change when the resolver lands.

### Acceptance

1. One resolver, taking a cohort or a date, reading a schedule of statutory State
   Pension ages including the 2026-2028 rise to 67 and the 2044-2046 rise to 68.
2. `current_spa` and `future_spa` are retired, not left beside it as a third answer.
3. All five current readers go through it: `RetirementIncomeService:566`,
   `AssumptionsService:424`, `AssetLocationController:258`,
   `PensionEstimateService:219`, and `HouseholdCashFlowProjector::statePensionAgeFor()`.
4. A person's own recorded `state_pensions.state_pension_age` continues to win over
   anything derived.
5. Two people of different ages in one household get different State Pension ages, and
   the estate, retirement and decumulation modules all agree on each.
