---
id: W-0197
title: State Pension age is legislated by cohort, and the application holds two static keys — a projection decades out needs a resolver, not a choice between 66 and 67
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: chief-of-staff
status: done
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

---

## Closed 2026-09-01 — a schedule, not a scalar

**Acceptance 1 — one resolver, reading a schedule.**
`app/Services/Retirement/StatePensionAgeResolver.php` takes a date of birth (or an age,
for the marketing funnel, which never asks for one) and returns the age that applies to
that cohort. The schedule lives in tax configuration —
`TaxConfigurationSeeder` `pension.state_pension.age_schedule` — as bands of
`from`/`to`/`age`, covering the 2026-2028 rise to 67 and the legislated 2044-2046 rise
to 68. Sources cited at the seeded block: Pensions Act 1995 Sch 4, 2007, 2011, and 2014
s26.

**Acceptance 2 — both scalars retired, not left beside it.** `current_spa` and
`future_spa` no longer exist in the seeder, and no service reads them. There is
deliberately **no scalar fallback**: a missing schedule throws, naming this item, rather
than silently standing in with a number that is wrong for most cohorts.

**Acceptance 3 — all five readers go through it.**

| Reader | Was | Now |
|---|---|---|
| `RetirementIncomeService` | `current_spa` | cohort of the user being projected |
| `AssumptionsService` | `current_spa`, with a RETIREMENT-age default behind it | cohort |
| `AssetLocationController` | `current_spa` | `forUser()` |
| `PensionEstimateService` | `future_spa` — the only reader of the other key | `forCurrentAge()` on the visitor's band |
| `HouseholdCashFlowProjector` | `current_spa` | cohort, per household member |

`AssumptionsService` is worth noting: it used its own **retirement-age** default as the
fallback for **State Pension age** — two different questions answering each other. Both
now go to the service that owns them (W-0196 for the first, this for the second).

**Acceptance 4 — a recorded forecast still wins.** `forUser()` returns
`state_pensions.state_pension_age` when the user holds one, before consulting any
cohort. They may have a forecast we cannot reproduce, and overriding it with our own
arithmetic would be telling them their own statement is wrong.

**Acceptance 5 — two people in one household get different answers**, and every module
agrees on each, because every module asks the same resolver. Asserted directly.

### Tests

`tests/Unit/Services/Retirement/StatePensionAgeResolverTest.php` — 10 tests: the three
cohort bands, two household members differing, the recorded override, the unknown date
of birth, both keys gone from configuration, no service reading a retired key, and the
missing-schedule throw.

**A test that encoded the defect, corrected rather than deleted.**
`PensionEstimateServiceTest` hardcoded `$retirementAge = 67; // future_spa` in nine
places. That literal WAS the defect — one State Pension age for every age band — so
every case now resolves for its own band exactly as the service does. A test that
hardcodes the answer cannot tell whether the service reads the schedule or ignores it.
The reasoning is written at the first occurrence.

**Regression:** 1,075 tests across estate, retirement, investment and stores; 112 across
marketing and investment features.

**Rule 19:** no State Pension age literal exists in `resources/mobile`. The web
components' `state_pension_age || 67` fallbacks are display fallbacks for a value the
API supplies and are W-0516's, which this item does not pre-empt.
