---
id: W-0007
title: Investment account modal reports £0 Cash ISA usage — overstates remaining ISA allowance and lets the £20,000 limit be breached
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
owner: build-lead
status: handoff
surfaces: [web, m, ios]
created: 2026-08-20T22:20:00Z
claimed: 2026-08-21T10:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21T10:30:00Z
prior_art_found: ['savings store isaAllowance state + currentYearISASubscription getter', 'savingsService.getISAAllowance (zero callers)', 'GET /api/savings/isa-allowance/{taxYear} endpoint', 'ISATracker::getISAAllowanceStatus']
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **Sarah Jones (spouse)**, user id 17.

**Surface:** desktop web, `/net-worth/investments` → "Add Account" → Account Type =
"ISA (Stocks & Shares)" → "Show additional information".

Severity: **high**. This is the guard on a statutory limit. It does not merely
display a wrong number — it withholds the warning that stops the user breaching the
£20,000 annual ISA allowance.

### Expected

Persona file `tests/Persona/peak_earners.md:242-251` — Sarah's Cash ISA (Nationwide,
£22,500) has **ISA Subscription This Year: £10,000**.

With that entered, the ISA Allowance Usage panel in the investment account modal
should read `Cash ISA: £10,000` and `£10,000 remaining`, and entering a further
£15,000 Stocks & Shares ISA subscription should trigger the existing
over-subscription warning (`AccountForm.vue:1116-1119`).

### Actual

On a **fresh load** of `/net-worth/investments` the panel reads:

```
ISA Allowance Usage:  £20,000 remaining
Cash ISA: £0                       <-- actual £10,000
Other Stocks & Shares ISAs: £0
This account: £0
```

Entering £15,000 into "Already Subscribed This Tax Year" then gives:

```
ISA Allowance Usage:  £5,000 remaining     <-- actual: £5,000 OVER the limit
Cash ISA: £0
This account: £15,000
```

and **no warning is shown**. Real position is £10,000 + £15,000 = £25,000 against a
£20,000 allowance. The UI tells the user they have £5,000 of headroom left when they
are £5,000 over.

### The backend is correct — this is frontend-only

`ISATracker::getISAAllowanceStatus(17, '2026/27')` returns, verified via tinker:

```
tax_year               = "2026/27"
total_allowance        = 20000
cash_isa_used          = 10000
stocks_shares_isa_used = 0
total_used             = 10000
remaining              = 10000
```

DB row backing it: `savings_accounts.id = 26`, `institution = Nationwide`,
`account_type = cash_isa`, `is_isa = true`, `isa_type = cash`,
`isa_subscription_year = 2026/27`, `isa_subscription_amount = 10000.00`,
`ownership_type = individual`, `ownership_percentage = 100`.

### Root cause

`resources/js/components/Investment/AccountForm.vue:569-571`:

```js
cashISAUsed() {
  return this.$store.getters['savings/currentYearISASubscription'] || 0;
},
```

`resources/js/store/modules/savings.js:82-84`:

```js
currentYearISASubscription: (state) => {
    return state.isaAllowance?.cash_isa_used || 0;
},
```

`state.isaAllowance` is populated only when the **savings** store's ISA-allowance
action runs, which happens on the cash/savings screens. Nothing on
`/net-worth/investments` (neither `InvestmentList.vue` nor `AccountForm.vue`)
dispatches it — grep for `dispatch('savings` in either file returns nothing. So on
that route `state.isaAllowance` is `null`, the getter falls through to `0`, and every
figure derived from it is wrong.

The value is not merely cosmetic. It flows into the guard:

- `AccountForm.vue:605-607` — `totalISAUsed = cashISAUsed + otherStocksISAUsed + thisAccountSubscription`
- `AccountForm.vue:610-612` — `totalWithPlanned = totalISAUsed + plannedAnnualContribution`
- `AccountForm.vue:1116-1119` — `if (this.totalWithPlanned > this.isaAnnualAllowance) { ... isValid = false }`

With `cashISAUsed` stuck at 0, `totalWithPlanned` is understated by the whole Cash ISA
subscription, so the guard does not fire and `isValid` stays true.

The identical panel on the savings side (`SaveAccountModal.vue:416`) reads the
allowance correctly, because that route does load it. Two components render the same
concept from two different readiness states — Rule 20: the fix is one source both
consumers read, not a second dispatch bolted onto the investment page.

### Repro

1. Log in as a user with no data.
2. `/net-worth/cash` → Cash ISAs → Add Account → Nationwide, £22,500, 4.25%,
   "Already Subscribed This Tax Year" = £10,000. Save.
3. Navigate to `/net-worth/investments` and **hard-reload the page**.
4. Add Account → Account Type "ISA (Stocks & Shares)" → "Show additional information".
5. Panel reads `Cash ISA: £0`, `£20,000 remaining`.
6. Enter £15,000 in "Already Subscribed This Tax Year" — reads `£5,000 remaining`,
   no warning.

(Step 3's reload matters: arriving from the savings page in the same SPA session can
leave `state.isaAllowance` warm and mask the fault. A user landing on the
investments URL directly always sees the broken figure.)

### Evidence

**No screenshot** — this defect was found and proved during the entry phase, which predates the run's screenshot rule. It is fully reproducible from the steps above; the DB row and the `ISATracker` return value are quoted verbatim.
Report: `reports/R-01-pass-a-entry.md`.

## Acceptance

- [ ] The ISA allowance figures shown in the investment account modal match
      `ISATracker::getISAAllowanceStatus` for the same user and tax year, on a cold
      load of `/net-worth/investments`.
- [ ] With £10,000 Cash ISA used, entering £15,000 in the investment ISA subscription
      raises the over-allowance error and blocks the save.
- [ ] One source feeds both `SaveAccountModal.vue` and `AccountForm.vue` (Rule 20) —
      not a duplicated fetch in each component.
- [ ] Checked on `/m` and iOS wherever the ISA allowance is surfaced (Rule 19).
- [ ] Re-verified live in the browser by the persona run.

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. Backend confirmed correct; fault is
  frontend store readiness. Root cause diagnosed to file:line above; not fixed by me
  — routed to build-lead.

- 2026-08-21 build-lead: **FIXED — verified live on a cold load, including the guard.**

  The diagnosis in the item was right about the cause and **understated the
  duplication**. Three mechanisms, now one:

  1. `resources/js/mixins/isaAllowanceMixin.js` (NEW) — the one home. Exposes
     `cashISAUsed` and `totalStocksISAUsed` from the single store getters and
     loads the allowance in `created()`. Used by `AccountForm.vue`,
     `StandardInvestmentFields.vue` and `SaveAccountModal.vue`.
  2. `savings/ensureISAAllowance` (NEW action) — idempotent; fetches only when
     `state.isaAllowance` is null. A no-op on the savings screens, which already
     load it in bulk. The getter's own comment referred to a `fetchISAAllowance`
     action that **did not exist**.
  3. `GET /api/savings/isa-allowance/{taxYear?}` — the tax year is now optional
     and resolved server-side from `TaxConfigService` (Rule 2), so no caller has
     to know the active year. `savingsService.getISAAllowance()` already existed
     with **zero callers**.

  **A second, separate fault was found while verifying and is fixed.** With the
  store correctly holding `cash_isa_used: 10000`, the panel still rendered £0.
  Instrumenting the live component tree:
  ```
  storeGetter        10000
  AccountForm computed 10000
  child vnode prop   10000
  child RESOLVED prop     0   <-- StandardInvestmentFields
  ```
  `AccountForm` was hand-threading copies down as `:cash-isa-used` /
  `:total-stocks-isa-used` props, and the child's props were not being updated
  from the new vnode. Reproducible after a hard reload, so not an HMR artefact.
  The prop hop **is** the second mechanism: both props are DELETED and
  `StandardInvestmentFields` reads the mixin — the same source
  `SaveAccountModal` reads. Two components, one source, no copies.

  **Live, Sarah Jones (17), cold load of `/net-worth/investments` → Add Account →
  ISA (Stocks & Shares) → Show additional information:**
  ```
  ISA Allowance Usage:  £10,000 remaining       (was £20,000)
  Cash ISA: £10,000                              (was £0)
  Other Stocks & Shares ISAs: £0
  This account: £0
  ```
  Matches `ISATracker::getISAAllowanceStatus(17, '2026/27')` exactly.

  **The guard fires (the point of the item).** Entering £15,000 in "Already
  Subscribed This Tax Year", then Add Account:
  ```
  Warning: Your planned ISA contributions would exceed the £20,000 allowance
  by £5,000. Consider reducing your regular contributions or lump sum.
  ```
  and the modal stayed open — the save was blocked. Previously the panel claimed
  £5,000 of headroom and let it through. £10,000 + £15,000 = £25,000, £5,000
  over: correct.

  Note the panel's "remaining" is floored at zero (`Math.max(0, …)`), so it reads
  "£0 remaining" rather than "£5,000 over". Pre-existing display behaviour; the
  warning carries the overage. Flagged, not changed.

  Tests: `tests/frontend/store/savingsIsaAllowance.test.js` (6 cases — loads once,
  does not refetch, honours `force`, survives a failed fetch, and the getter's
  zero-while-unloaded behaviour that caused this).

  **GAP:** `/m` does not surface an ISA allowance panel in any entry form, so
  there is nothing to fix there; `/m/app/savings/:id` shows ISA contribution
  history, which reads its own endpoint and is unaffected. iOS not checked.

- 2026-08-21 build-lead: batch handover (CLAUDE.md Rule 22) — `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md`. Carries the dispatch verbatim, the joint-share consolidation reasoning, decisions taken, dead ends ruled out, and environment state.
