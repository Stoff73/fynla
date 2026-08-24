---
id: W-0473
title: Every /m Insights reader looks for its data one level above where the agent puts it, so the feature has never produced a single insight
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: main-inference
reviewers: [quality-lead]
status: gated
claimed_by: null
severity: high
surfaces: [m]
created: 2026-08-24T07:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0466, W-0471]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found while fixing W-0466 F3, 2026-08-24. The tax reviewer reported this surface printing an unqualified Inheritance Tax figure; measurement showed it prints nothing at all
---

## Intent

`InsightsController::extractInsights()` reads every module at the **top level** of
the agent's response:

| Line | Reads |
|---|---|
| 94 | `$savings['emergency_fund']` |
| 107 | `$savings['isa_allowance']` |
| 122 | `$protection['gaps']` |
| 135 | `$retirement['annual_allowance']` |
| 151 | `$estate['iht_liability']` |
| 177 | `$goals['has_goals']` |

Every agent extends `BaseAgent` and returns `$this->response(...)`, whose shape is
fixed by convention (`app/Services/CLAUDE.md`):

```
top keys: success, message, data, timestamp
```

Measured on user 14:

```
$estate['iht_liability']          → ABSENT
$estate['data']['summary'] keys   → gross_estate, net_estate, total_liabilities,
                                     iht_liability, effective_tax_rate
```

**Every read misses by exactly one level (`data`), so every branch is skipped and
`$insights` comes back empty.** This is not the estate branch being wrong — it is
all six.

## Why it was reported as the opposite

`tax-compliance-reviewer` (round four, F3) recorded this surface as *printing*
`"Your estimated Inheritance Tax liability is £X…"` without the W-0466 caveat.
Reading the code, that is exactly what it looks like. It is only visible as dead
by **running the agent and printing the keys** — the read is a `??` chain, so a
miss is silent and indistinguishable from "this household has no estate".

**This is the phantom-read family again** — the same silent shape as W-0471's
`users.spouse_user_id`. A missing key on an array read yields null and the
branch politely does nothing.

## Acceptance

1. All six readers take the agent's `data` payload — ideally by unwrapping once at
   the call site rather than six times, so the seventh module cannot get it wrong.
2. Before/after on a household with an estate, a protection gap and unused ISA
   allowance: the endpoint returns insights where it now returns `[]`.
3. **The Inheritance Tax insight must carry `unmodelled_relief_caveat`.** The line
   is already in place (`InsightsController:151-170`, added 2026-08-24) and is
   currently unreachable; it must not be lost when the reader is corrected.
   `EstateAgent` publishes the caveat in its summary as of the same date.
4. A test that would fail if a reader drifts a level again — asserting non-empty
   insights for a seeded household is enough, and there is none today.
5. `/m` only; the web insights surface is separate and unexamined here.

## Working notes

- 2026-08-24 — Found while adding the W-0466 caveat to this surface. The caveat line
  was kept deliberately even though the branch cannot run: when the reader is fixed,
  the figure and its qualification arrive together rather than the caveat being
  re-discovered as missing.
- 2026-08-24 — **Not fixed here.** Reviving a whole dead feature is not a caveat
  commit's business: it changes what `/m` users see, and it needs its own before/after.

- 2026-08-24 — **Fixed.** The diagnosis in Intent was one level short of the truth:
  the top-level key is `module_analysis`, **not `modules`**, so neither the primary
  read nor its `?? $analysis['savings']` fallback could ever resolve. Two further
  misses only visible once the level was corrected: the tax module is keyed
  `tax_optimisation` (read as `tax`), and the pension figure is
  `annual_allowance.remaining_allowance` (read as `remaining`) — that branch was dead
  twice over. Each module entry is the coordinator's flat map with the agent's own
  payload under `full_analysis`, so the unwrap merges the two ONCE at the call site
  (`InsightsController:96-100`).
- 2026-08-24 — **The endpoint never returned `[]`.** It fell through to the generic
  catch-all at the foot of `extractInsights`, so every household saw *"Keeping your
  financial data up to date…"* every day. Measured on user 14: 0 module insights
  before, 5 after — ISA allowance £14,540, a protection gap, £55,600 of Annual
  Allowance, the £58,500 Inheritance Tax figure **carrying its
  `unmodelled_relief_caveat`**, and 2 tax strategies.
- 2026-08-24 — **The protection predicate was wrong independently of the level.**
  `! empty($gaps)` fires for every analysed household, because the gaps structure is
  always present. User 14 has `total_gap: 0` with `income_protection_gap: 21000`, so
  neither the structure's existence nor `total_gap` alone is the question — the reader
  now asks whether any figure in it is above zero.
- 2026-08-24 — **The existing test was one that could not fail.** It mocked
  `['modules' => ...]`, a shape `CoordinatingAgent::analyze()` has never produced, and
  asserted only that a non-empty string came back — which the catch-all guarantees.
  Rewritten to the real shape with figure-level assertions; **both mutations
  (level reverted, unwrap removed) turn 4 tests red**, restored green 10/10.
- 2026-08-24 — **Adjacent finding, NOT fixed here: nothing calls this endpoint.**
  `GET /api/v1/mobile/insights/daily` lives on `routes/api_v1.php` (the native
  surface) and has no client anywhere — not `resources/mobile/`, not `ios-native/`.
  What `/m` actually renders under "Today's insight" is `fyn_insight` from
  `MobileDashboardAggregator::generateFynInsight()`, a **second, unrelated**
  insight mechanism reading its own aggregate. Filed as **W-0478** — whether to wire
  a client to this endpoint or delete it in favour of the aggregator is a CSJ call,
  and Rule 20 says the two should not both exist.
