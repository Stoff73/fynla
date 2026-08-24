---
id: W-0473
title: Every /m Insights reader looks for its data one level above where the agent puts it, so the feature has never produced a single insight
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [quality-lead]
status: queued
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
