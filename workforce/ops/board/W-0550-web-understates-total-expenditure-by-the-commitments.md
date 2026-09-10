---
id: W-0550
title: Web shows total monthly expenditure excluding financial commitments — £1,800 where /m and the API both say £4,878, understating spending by £36,936 a year
mission: new-user-run-2026-09-07
branch: null
owner: null
reviewers: [quality-lead, build-lead]
status: queued
severity: high
surfaces: [web]
created: 2026-09-09
source: production fynla.org, live account, 2026-09-09
prior_art_checked: 2026-09-09
prior_art_found: [W-0011, W-0413, W-0495]
prior_art_outcome: none — W-0011 was the free tier being unable to save at all (fixed, re-verified working today); this is the saved figure being totalled wrongly
constitution_refs: [07-quality-bar]
---

## Intent

Reproduced on **production** with a live account: one property (mortgage £1,685
plus £643 running costs) and two investment contributions (£500 + £250), then a
simple monthly expenditure of £1,800 entered on
`/valuable-info?section=expenditure`.

The page renders:

```
Monthly Expenditure:        £1,800
Annual Expenditure:         £21,600
Financial Commitments
  Auto-calculated           £3,078
Total Monthly Expenditure   £1,800     <-- excludes the £3,078 above it
Annual Equivalent           £21,600
```

The commitments line and the total that is supposed to contain it are **on the
same screen**, four lines apart, and disagree.

Before any figure was entered the same "Total Monthly Expenditure" read
**£3,078** — the commitments alone. So the total included commitments at £0
manual spend and stopped including them once a manual figure was entered.

## The backend is right

`GET /api/user/profile` -> `expenditure.presentation`:

```json
{"active_monthly_total": 4878, "active_annual_total": 58536,
 "manual_monthly_total": 1800, "commitments_monthly_total": 3078,
 "total_basis": "Monthly summary plus financial commitments",
 "reconciles": true}
```

The server computes £4,878, and ships a `total_basis` string that says in words
what the total is meant to be. The UI renders `manual_monthly_total` in a field
captioned "Total Monthly Expenditure".

## /m gets it right — web does not (Rule 19)

`resources/mobile/views/Expenditure.vue:60`

```js
monthly() { return Number(this.presentation.active_monthly_total) || 0; },
```

`grep -rn "active_monthly_total" resources/js/` -> **no matches.** The web SPA
never reads the field at all and totals it locally instead.

**The same household sees £4,878 on `/m` and £1,800 on web.** One of the two
numbers is wrong on whichever surface the user happens to open.

## Where to look

`resources/js/components/UserProfile/ExpenditureForm.vue:1586`

```js
const totalMonthlyWithCommitments = computed(() => totalMonthlyExpenditure.value + commitmentsTotal.value);
```

That reads correctly, so the fault is upstream of it: on the simple-entry path
`commitmentsTotal` resolves to 0 while the Financial Commitments section beside
it renders `financialCommitments?.totals?.total` = 3078 (line 236). Two
different variables for one quantity, and the simple-entry path is bound to the
empty one.

## Why it matters beyond the screen

Expenditure feeds affordability and emergency-fund runway. Understating spending
by £3,078 a month overstates surplus and overstates how long a cash buffer lasts
— the same class of harm as W-0495, from the opposite direction.

## Acceptance

- [ ] Web renders the server's `active_monthly_total` / `active_annual_total`
      rather than recomputing, so web and `/m` cannot disagree (Rule 20).
- [ ] Correct on both the simple and detailed entry paths, and for a married
      household with separate expenditure.
- [ ] Anything consuming the total for runway or affordability re-checked.
- [ ] A test pins that a manual figure plus commitments totals to their sum.

## Working notes

2026-09-09 — Found while testing modules on production. Also confirmed here that
**W-0011 is genuinely fixed**: a free-tier account saved a simple monthly total
(`PUT /api/user/profile/expenditure` -> 200) with no premium gate.
