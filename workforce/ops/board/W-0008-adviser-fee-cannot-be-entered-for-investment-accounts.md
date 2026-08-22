---
id: W-0008
title: Adviser fee cannot be entered for investment accounts — displayed and charged in projections, but always £0
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
owner: build-lead
status: handoff
surfaces: [web, m, ios]
created: 2026-08-20T22:26:00Z
claimed: 2026-08-21T10:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21T10:30:00Z
prior_art_found: ['DCPensionForm.vue advisor_fee_percent (proven pattern)', 'StoreDCPensionRequest advisor_fee_percent rule', 'InvestmentAccount model fillable + cast already present', 'InvestmentAccountNormaliser already casts advisor_fee_percent']
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **Sarah Jones (spouse)**, user id 17 — and it applies to
David's three investment accounts equally.

**Surface:** desktop web, `/net-worth/investments` → Add Account (and the edit form),
all account types.

### Expected

Persona file `tests/Persona/peak_earners.md` gives an **Adviser Fee** for every
investment account:

| Account | Line | Platform Fee | Adviser Fee |
|---|---|---|---|
| David's Stocks & Shares ISA | :276-277 | 0.45% | **0.75%** |
| Sarah's Stocks & Shares ISA | :296-297 | 0.45% | **0.75%** |
| Joint General Investment Account | :314-315 | 0.25% | **0.75%** |
| Venture Capital Trust Holdings | :334 | — | **0.75%** |

Entering an account should let both fees be recorded, and
`investment_accounts.advisor_fee_percent` should hold `0.75`.

### Actual

There is **no adviser fee input** anywhere in the investment account form. The modal
offers Platform Fee only ("Platform Fee | % | £ | Monthly | Quarterly | Annually |
Platform fee as a percentage of assets per year"). Searching the rendered modal for
`/Advis/i` returns false.

After saving Sarah's Stocks & Shares ISA the DB row is:

```
investment_accounts.id = 13
provider          = Hargreaves Lansdown
current_value     = 85000.00
platform_fee_percent = 0.4500     <-- entered, correct
advisor_fee_percent  = 0.0000     <-- persona says 0.75, no way to enter it
```

So the persona's adviser fee is unenterable through the UI, for every investment
account.

### Why it matters — the value is consumed, not ignored

`advisor_fee_percent` is read by at least four display/calculation surfaces:

- `resources/js/components/Investment/FeeBreakdown.vue:27` renders an "Advisor Fees"
  line; `:168` computes it from `account.advisor_fee_percent`
- `resources/js/components/NetWorth/InvestmentList.vue:756`
- `resources/js/components/NetWorth/InvestmentProjections.vue:76`, `:286`, `:647` —
  adviser fee is shown on the projections screen and fed into the projection
- vault `Architecture/v083/09-MODULES.md` — `FeeAnalyzer` "breaks down by type (fund
  OCF, platform, advisor)"

Every one of those renders £0 / 0% permanently. Fee-drag figures and net-of-fee
projections are therefore understated by the adviser fee for all users — the
projection is not merely missing a label, it compounds a fee that was never deducted.

### The pension form already does this correctly

`resources/js/components/Retirement/DCPensionForm.vue:275` has
`v-model.number="formData.advisor_fee_percent"`, and
`app/Http/Requests/Retirement/StoreDCPensionRequest.php:68` validates
`'advisor_fee_percent' => ['nullable','numeric','min:0','max:10']`.

The investment side has neither: `app/Http/Requests/StoreInvestmentAccountRequest.php:56`
validates `platform_fee_percent` and stops there — there is no `advisor_fee_percent`
rule, so even a hand-crafted request would be stripped.

### Repro

1. `/net-worth/investments` → Add Account → any account type → "Show additional
   information".
2. Search the form for an adviser/advisor fee input — there is none.
3. Save the account; `investment_accounts.advisor_fee_percent` is `0.0000`.
4. Open the account's projections view — "Advisor Fee" displays 0%.

### Evidence

**No screenshot** — entry-phase finding, predates the screenshot rule. Verifiable in seconds: open the Add Account form and search it for an adviser-fee input.
Report: `reports/R-01-pass-a-entry.md`.

## Acceptance

- [ ] The investment account form (add and edit) accepts an adviser fee, matching the
      pattern already used in `DCPensionForm.vue:275`.
- [ ] `StoreInvestmentAccountRequest` and `UpdateInvestmentAccountRequest` validate
      `advisor_fee_percent` (same rule shape as `StoreDCPensionRequest.php:68`).
- [ ] Entering 0.75 persists `advisor_fee_percent = 0.7500` and the value appears in
      `FeeBreakdown.vue` and `InvestmentProjections.vue`.
- [ ] Fee-drag and net-of-fee projections change accordingly (verify the projected
      value moves down, by roughly the right magnitude).
- [ ] `/m` and iOS equivalents accept and display it (Rule 19).
- [ ] Re-verified live in the browser by the persona run.

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. Not fixed by me — routed to
  build-lead. Entry for the four persona accounts is blocked on this: adviser fee
  will read 0% until it lands.

- 2026-08-21 build-lead: **FIXED — input verified present in the live modal.**

  - `resources/js/components/Investment/StandardInvestmentFields.vue:249-266` —
    an "Adviser Fee (% per year)" input, same shape as the proven
    `DCPensionForm.vue:275`, shown under "Show additional information" for every
    non-NS&I account type.
  - `StoreInvestmentAccountRequest:59` and `UpdateInvestmentAccountRequest:60` —
    `['nullable','numeric','min:0','max:10']`, the same rule as
    `StoreDCPensionRequest.php:68`.
  - `AccountForm.vue` — `advisor_fee_percent` added to `formData`, `resetForm()`,
    the `allowedFields` submit allowlist (without which it was stripped),
    `hasAdditionalInfoData()` so editing an account that has one auto-expands the
    panel, and the collapsed-clear block.

  No model or store change was needed: `InvestmentAccount` already had it
  fillable and cast, and `InvestmentAccountNormaliser` already cast it to float.
  The column and every display of it existed; only the way to enter it did not.

  **Live, `/net-worth/investments` → Add Account → ISA → Show additional
  information:** label "Adviser Fee (% per year)", `#advisor_fee_percent` present.
  Also confirmed on the EDIT form for Sarah's Hargreaves Lansdown ISA (rendered
  0.0000, the current stored value).

  Test: `tests/Feature/Stores/InvestmentAccountHttpIntegrationTest.php` — POST
  with `advisor_fee_percent: 0.75` persists 0.75 alongside `platform_fee_percent`
  0.45, and a PUT to 0.50 persists.

  **GAPS:**
  - **I did not verify the fee-drag / net-of-fee projection figures move**
    (acceptance bullet 4). The value now reaches `FeeBreakdown.vue` and
    `InvestmentProjections.vue`, which already read `account.advisor_fee_percent`,
    but I did not measure the projected value before and after. Routed to Quality.
  - `/m` has no investment account create/edit form, so there is no input to add
    there. iOS outside this dispatch.

- 2026-08-21 build-lead: batch handover (CLAUDE.md Rule 22) — `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md`. Carries the dispatch verbatim, the joint-share consolidation reasoning, decisions taken, dead ends ruled out, and environment state.
