---
id: W-0009
title: Every holding edit silently discards its payload — updateHolding store action destructures a key no caller sends
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
owner: build-lead
status: done
surfaces: [web, m, ios]
created: 2026-08-20T22:48:00Z
claimed: 2026-08-21T10:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21T10:30:00Z
prior_art_found: ['resources/js/store/modules/investment.js updateHolding', 'investmentService.updateHolding', 'UpdateHoldingRequest all-nullable rules', 'HoldingForm.vue self-close']
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **Sarah Jones (spouse)**, user id 17.

**Surface:** desktop web. Affects **all three** holding-edit entry points, so every
route that can edit a holding.

Severity: **high** — silent data loss with a 200 OK and a success-looking UI. Nothing
tells the user their edit was thrown away.

### Expected

Persona file `tests/Persona/peak_earners.md:302-304` — Sarah's Stocks & Shares ISA
holding:

| Holding | Ticker | ISIN | Type | Units | Cost/Unit | Price/Unit | Value | Allocation | Fee |
|---|---|---|---|---|---|---|---|---|---|
| Vanguard LifeStrategy 80 | VGLS80 | GB00B4PQW151 | Fund | 333 | £225.00 | £255.00 | £84,915 | 100% | 0.22% |

Entering those in the holding **Details** form and clicking "Update Holding" should
persist ticker, ISIN, purchase price, current price and OCF.

### Actual

The modal closes as though saved. No error, no toast, no validation message. The DB
row is unchanged:

```
holdings.id = 32   (holdable_id = 13, InvestmentAccount, Sarah's HL ISA)
security_name  = 'Vanguard LifeStrategy 80'
asset_type     = 'fund'
allocation_percent = '100.00'
cost_basis     = '74925.00'
ticker         = NULL     <-- entered VGLS80
isin           = NULL     <-- entered GB00B4PQW151
sub_type       = NULL     <-- selected Mixed Fund
purchase_price = NULL     <-- entered 225.00
current_price  = NULL     <-- entered 255.00
ocf_percent    = '0.0000' <-- entered 0.22
quantity       = NULL
```

### Root cause — caller/action key mismatch

Instrumenting `XMLHttpRequest` in the live page caught the actual request:

```
PUT http://localhost:8000/api/investment/holdings/32
status: done
request body: null            <-- the payload never leaves the browser
response: {"success":true,"data":{"id":32,...,"ticker":null,"isin":null,
           "current_price":null,"purchase_price":null,"ocf_percent":"0.0000",...}}
```

`resources/js/store/modules/investment.js:523`:

```js
async updateHolding({ commit, dispatch }, { id, holdingData }) {
    ...
    const response = await investmentService.updateHolding(id, holdingData);
```

It destructures **`holdingData`**. Every caller sends **`data`**:

- `resources/js/components/Investment/AccountForm.vue:1177-1180` — `{ id: holdingData.id, data: holdingData }`
- `resources/js/components/Investment/InvestmentHoldings.vue:224` — `{ id: formData.id, data: formData }`
- `resources/js/components/NetWorth/InvestmentProjections.vue:1163` — `{ id: holdingData.id, data: holdingData }`

So `holdingData` inside the action is `undefined`,
`investmentService.updateHolding(id, undefined)`
(`resources/js/services/investmentService.js:160-162`) issues
`api.put('/investment/holdings/{id}', undefined)`, and axios sends no body.

Server-side nothing objects: every rule in `UpdateHoldingRequest` is `nullable`
(`asset_type`, `security_name`, `ticker`, `isin`, `allocation_percent`,
`purchase_price`, `purchase_date`, `current_price`, `current_value`,
`dividend_yield`, `ocf_percent` — lines 29-40), so an empty body validates cleanly
and `InvestmentController::updateHolding` (`app/Http/Controllers/Api/InvestmentController.php:787`)
returns 200 with the untouched model. The UI reads success and closes.

All three callers agree with each other; the store action is the single odd one out,
so **one rename in `investment.js:523` fixes every surface at once** — that is the
Rule 20 shape of this fix, not three call-site edits.

`createHolding` (`investment.js:502`) takes the payload positionally and is NOT
affected — creation works, only editing is broken.

### Repro

1. `/net-worth/investments` → open an account → Edit → "Show additional information".
2. Add a holding row (name, type, allocation), save the account so the holding gets an id.
3. Edit again → "Show additional information" → click the holding's **Details** link.
4. Fill Ticker, ISIN, Purchase Price, Current Price, OCF. Click **Update Holding**.
5. Modal closes, no error. Query the `holdings` row — every one of those fields is
   still NULL / 0.
6. Network tab (or an XHR hook) shows `PUT /api/investment/holdings/{id}` with an
   empty request body and a 200 response.

### Evidence

**No screenshot** — the evidence here is the captured request, not the screen: `PUT /api/investment/holdings/32` with a `null` body returning 200, quoted verbatim above from an `XMLHttpRequest` hook in the live page. A screenshot would show a modal closing successfully, which is precisely the problem.
Report: `reports/R-01-pass-a-entry.md`.

## Acceptance

- [ ] `updateHolding` in `resources/js/store/modules/investment.js:523` reads the key
      its callers actually send (`data`), fixed **in the store**, not by editing the
      three call sites.
- [ ] Editing a holding persists ticker, ISIN, sub_type, purchase_price,
      current_price, purchase_date, dividend_yield and ocf_percent — verified by DB
      row, from all three entry points (`AccountForm`, `InvestmentHoldings`,
      `InvestmentProjections`).
- [ ] A failed holding save surfaces an error to the user instead of closing the
      modal silently.
- [ ] Consider whether `UpdateHoldingRequest` should reject a wholly empty payload
      rather than 200-ing it — that is what let this ship unnoticed.
- [ ] `/m` and iOS holding-edit paths checked for the same mismatch (Rule 19).
- [ ] Re-verified live in the browser by the persona run.

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. Diagnosed by hooking XHR in the live
  page and reading the null request body. Not fixed by me — routed to build-lead.
- Persona entry for ALL holdings (David's 3 ISA holdings, 3 joint GIA holdings, 3 SIPP
  holdings, Sarah's 1) is blocked on this: units/ticker/ISIN/prices/OCF cannot be
  recorded until it lands. Related but separate: the holding Details form exposes no
  **quantity/units** input at all, so the persona's unit counts have nowhere to go
  even once this is fixed.

- 2026-08-21 build-lead: **FIXED — verified live, DB row confirmed.**

  One rename in the store, as the item required:
  `resources/js/store/modules/investment.js:527` now destructures `{ id, data }`,
  the key all three callers already send. No call sites edited.

  **A SECOND defect was blocking the same journey and is fixed in the same loop.**
  Driving the repro live, the save never left the browser at all: `HoldingForm`
  reported "Account is required" because `formData.investment_account_id` was
  empty. The edit-mode watcher spread the holding record, and a holding
  references its account as `holdable_id` — and the inline editor passes a
  trimmed shape (`{id, security_name, asset_type, allocation_percent,
  cost_basis}`) carrying neither. `HoldingForm.vue:382` now resolves
  `investment_account_id ?? holdable_id ?? defaultAccountId`.

  **Silent-close fixed too** (acceptance bullet 3): `HoldingForm.submitForm()`
  called `this.closeModal()` immediately after emitting `save`, so the modal shut
  before the parent's API call even resolved — regardless of outcome. It no
  longer self-closes (CLAUDE.md Rule 3: the parent owns the call and closes on
  success). One error display was added inside `HoldingForm` (a `saveError` prop),
  fed by all three parents, rather than three different error surfaces.
  `InvestmentProjections` had no `error` data key at all, so its failures were
  invisible; it now has `holdingSaveError`.

  **`UpdateHoldingRequest` now rejects a wholly empty payload** (acceptance bullet
  4). Every rule was `nullable`, so an empty body validated cleanly and returned
  200 with an untouched model — that is what let this ship unnoticed.

  **Live verification, Sarah Jones (17), holding 32 on her Hargreaves Lansdown
  ISA**, entering the persona's own values (`peak_earners.md:302-304`):
  ```
  before: ticker NULL, isin NULL, sub_type NULL, purchase_price NULL,
          current_price NULL, ocf_percent 0.0000
  after:  ticker VGLS80, isin GB00B4PQW151, sub_type mixed_fund,
          purchase_price 225.0000, current_price 255.0000, ocf_percent 0.2200
  ```
  Values left in place — they are what the persona specifies, so this restores
  intended data rather than disturbing it.

  Tests: `tests/frontend/store/investmentHoldings.test.js` asserts the action
  forwards the caller's payload (and that the payload is not `undefined`), and
  that it rethrows so the caller can keep the modal open.

  **GAPS:**
  - Verified live from the **AccountForm** entry point only. `InvestmentHoldings`
    and `InvestmentProjections` are covered by the store-level test, not a live
    click-through.
  - The item's closing note stands: **the holding Details form still exposes no
    quantity/units input**, so the persona's unit counts have nowhere to go. Not
    in this item's acceptance — needs its own item.
  - `/m` has no holding-edit path, so there is no mismatch to fix there.

- 2026-08-21 build-lead: batch handover (CLAUDE.md Rule 22) — `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md`. Carries the dispatch verbatim, the joint-share consolidation reasoning, decisions taken, dead ends ruled out, and environment state.
