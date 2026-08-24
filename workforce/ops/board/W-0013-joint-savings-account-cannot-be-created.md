---
id: W-0013
title: Joint savings accounts cannot be created — form never sends ownership_percentage, validator always rejects
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
owner: build-lead
status: done
surfaces: [web, m, ios]
created: 2026-08-20T23:50:00Z
claimed: 2026-08-21T10:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21T10:30:00Z
prior_art_found: ['StoreSavingsAccountRequest/UpdateSavingsAccountRequest hard reject', 'SavingsAccountNormaliser::fromForm', 'SavingsStore::validateCanonical share guard', 'PropertyController 100->50 coercion (proven-correct reference)']
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **David Jones (primary)**, user id 16.

**Surface:** desktop web, `/net-worth/cash` → Current Accounts → Add Account →
Ownership Type = "Joint Owner".

Severity: **high**. Joint savings is a documented, first-class feature (Rule 6
single-record joint ownership) and it is completely unreachable from the UI.

### Expected

Persona file `tests/Persona/peak_earners.md:221-229`:

| Field | Value |
|---|---|
| Provider | Nationwide |
| Type | Current Account |
| Balance | £4,500 |
| Ownership | Joint |

Saving that should create one row with `ownership_type='joint'`,
`ownership_percentage=50`, `joint_owner_id=17`, `current_balance=4500` — and render
£2,250 to each spouse.

### Actual

The save fails every time with a red "The given data was invalid." banner.

Captured request (XHR hook in the live page):

```
POST /api/savings/accounts   →  422
request: {"institution":"Nationwide","account_type":"current_account",
          "account_number":null,"current_balance":4500,"interest_rate":0,
          "access_type":"immediate","notice_period_days":null,
          "maturity_date":null,"is_emergency_fund":false,"is_isa":false,
          "country":"United Kingdom","isa_type":null, ...}
response: {"success":false,"message":"The given data was invalid.",
           "errors":{"ownership_percentage":
             ["An explicit ownership share is required for a shared account."]}}
```

The Ownership Type was set to Joint and the Joint Owner select resolved to Sarah
(value `17`), both confirmed in the DOM before submit.

### Root cause

**Backend** `app/Http/Requests/Savings/StoreSavingsAccountRequest.php:78-80`:

```php
if (in_array($ownershipType, ['joint', 'tenants_in_common'], true)
    && ! $this->filled('ownership_percentage')) {
    $v->errors()->add('ownership_percentage',
        'An explicit ownership share is required for a shared account.');
}
```

`ownership_percentage` itself is only `nullable` in the rules array (:61), so nothing
supplies a default — the `after` validator makes it mandatory for joint accounts.

**Frontend** `resources/js/components/Savings/SaveAccountModal.vue`: grepping the
entire component for `ownership_percentage` returns **zero hits**. The modal binds
`ownership_type` (:484) and shows a Joint Owner select (:493-503), and the payload it
builds (:1046-1047) sends only:

```js
ownership_type: this.formData.ownership_type,
joint_owner_id: this.formData.ownership_type === 'joint' ? this.formData.joint_owner_id : null,
```

There is no ownership-share input in the form and no default is supplied, so the
required field can never be filled. Every joint savings save is rejected.

`app/Http/Requests/Savings/UpdateSavingsAccountRequest.php:77` carries the identical
rule, so an existing account cannot be converted to joint either.

Note the contrast with the **property** wizard, which handles the same concept
correctly: it takes ownership type and joint owner and lets the server default a
joint share to 50% — that path produced a correct joint record in this same run.

### Repro

1. Link a spouse account so the Joint Owner select is populated.
2. `/net-worth/cash` → Current Accounts → Add Account.
3. Institution "Nationwide", Current Balance 4500, Ownership Type "Joint Owner",
   Joint Owner = the spouse.
4. Add Account → "The given data was invalid."
5. Network: 422 with `ownership_percentage` required.

### Impact on this persona run

Two persona records **could not be entered**:

- Joint Current Account, Nationwide, £4,500 (`peak_earners.md:221-229`)
- Premium Bonds, NS&I, £50,000, Joint (`peak_earners.md:253-261`)

That removes the joint-savings half of the ownership-rendering check this run exists
to perform, and £54,500 from the household cash position.

### Evidence

**No screenshot** — entry-phase finding. The captured 422 request/response pair above is the evidence.
Report: `reports/R-01-pass-a-entry.md`.

## Acceptance

- [ ] A joint savings account can be created from the UI: either the modal exposes an
      ownership-share input, or it defaults to 50 and sends it — matching whatever the
      property wizard does, so there is ONE behaviour for joint share across modules
      (Rule 20).
- [ ] The saved row has `ownership_type='joint'`, `ownership_percentage` set,
      `joint_owner_id` set, and `current_balance` holding the FULL balance (Rule 6).
- [ ] Both spouses see their correct share of it (£2,250 each on a £4,500 joint
      account).
- [ ] The same fix covers the update path (`UpdateSavingsAccountRequest.php:77`).
- [ ] `tenants_in_common` on savings is either supported end-to-end or rejected in the
      UI rather than at the validator.
- [ ] `/m` and iOS savings entry checked for the same missing field (Rule 19).
- [ ] Re-verified live in the browser by the persona run, both accounts.

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. Root cause diagnosed to file:line
  above; not fixed by me — routed to build-lead.

- 2026-08-21 build-lead: **FIXED — verified live end to end, the exact persona record.**

  The hard reject at `StoreSavingsAccountRequest:78-80` (and the identical one in
  `UpdateSavingsAccountRequest`) is gone. Both requests now resolve the share in
  `prepareForValidation()` through `SharedOwnership::primaryOwnerPercentage()`,
  so `ownership_percentage` is always present and in range by the time the rules
  run. The range guard is kept but can no longer fire from the UI. This is the
  same rule property and investment use — one behaviour across modules, which was
  the acceptance criterion.

  Note: `SavingsStore::validateCanonical:349-355` carried a **third** copy of the
  same requirement. It is left in place as a store-layer guard (it can no longer
  be reached from any UI path) and is now consistent with the other two.

  **Live browser, logged in as Sarah Jones (17), `/net-worth/cash`:**
  Institution Nationwide · Current Account · £4,500 · Ownership Joint · Joint
  Owner David Jones → **`POST /api/savings/accounts` → 201 Created** (was 422).

  Row written: `id 29, ownership_type='joint', ownership_percentage=50.00,
  joint_owner_id=16, current_balance=4500.00` — Rule 6 satisfied, FULL balance on
  one row. Shares: Sarah £2,250, David £2,250. Card rendered
  "Nationwide (Joint) · £2,250 · Total: £4,500".

  **The test record was then DELETED** (`DELETE /api/savings/accounts/29` → 200)
  so the persona household is exactly as I found it — the persona re-run should
  enter this record under David as the persona file specifies. Confirmed 4
  savings rows for users 16/17, no stray Nationwide current account.

  `tenants_in_common` on savings: the modal's Ownership Type select offers only
  `individual` and `joint` — verified in the live DOM. So the acceptance bullet is
  satisfied by "not offered in the UI", not by a validator rejection.

  Tests: three new cases in `tests/Feature/Savings/SavingsApiTest.php` — create
  without a share, create with a stale 100, and convert an existing individual
  account to joint.

  **GAP — `/m` savings entry NOT checked live** (same reason as W-0015: needs a
  `public/m-build/` rebuild against a live dev server). `/m` posts to the same
  endpoint, and `resources/mobile/views/modules/SavingsAccount.vue` now renders
  the share and counterparty from the shared helper.

- 2026-08-21 build-lead: batch handover (CLAUDE.md Rule 22) — `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md`. Carries the dispatch verbatim, the joint-share consolidation reasoning, decisions taken, dead ends ruled out, and environment state.
