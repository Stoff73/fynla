---
id: W-0014
title: Joint investment accounts save a 100% owner share — spouse gets nothing, primary owner double-counts
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
owner: build-lead
status: handoff
surfaces: [web, m, ios]
created: 2026-08-21T00:05:00Z
claimed: 2026-08-21T10:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21T10:30:00Z
prior_art_found: ['app/Services/Stores/Normalisers/{Investment,Property,Savings,Mortgage}Normaliser (four copies of the joint 50% default)', 'PropertyController::store/update inline copy', 'InvestmentController::store/update isset-only default', 'MortgageService::createFromPropertyData inline copy']
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **David Jones (primary)**, user id 16, joint owner Sarah
Jones, user id 17.

**Surface:** desktop web, `/net-worth/investments`.

Severity: **high**. This is a wrong number on a joint asset — exactly the class of
fault the persona run exists to catch. It inflates the primary owner's net worth by
the whole account and erases it from the spouse's.

### Expected

Persona file `tests/Persona/peak_earners.md:306-316` — Joint General Investment
Account, AJ Bell, £95,000, **Ownership: Joint**.

Per Rule 6 (single record, `ownership_percentage` = primary owner's share) this must
store `ownership_percentage = 50` and render **£47,500 to David and £47,500 to
Sarah**.

### Actual

```
investment_accounts.id = 14
account_type         = 'gia'
provider             = 'AJ Bell'
current_value        = 95000.00
ownership_type       = 'joint'      ok
joint_owner_id       = 17           ok
ownership_percentage = 100.00       WRONG — must be 50
risk_preference      = 'upper_medium'
```

Rendered on `/net-worth/investments` for David:

```
General Investment Account | AJ Bell
Full Value            £95,000
Your Share (100.00%)  £95,000        <-- should be (50.00%) £47,500
```

Per `app/Traits/CalculatesOwnershipShare.php:30-65`, the joint owner receives
`fullValue * (100 - percentage) / 100`, so **Sarah's share computes to £0** and the
account will not contribute anything to her side of the household.

The **same account on the same screen** proves the app knows how to do this properly:
David's joint property renders "Full Property Value £850,000 / Your Share (50.00%)
£425,000" correctly.

### Root cause — investment lacks the coercion property has

`app/Http/Controllers/Api/PropertyController.php:154-158` (correct):

```php
// For joint/tenants_in_common, default to 50/50 split if user left ownership_percentage at 100.
$ownershipType = $validated['ownership_type'] ?? 'individual';
if (in_array($ownershipType, ['joint', 'tenants_in_common'], true)
    && ($validated['ownership_percentage'] ?? 100.00) == 100.00) {
    $validated['ownership_percentage'] = 50.00;
}
```

It coerces an explicit 100 down to 50 for a shared asset.

`app/Http/Controllers/Api/InvestmentController.php:346-350` (store) and `:509-514`
(update) only fill in a default when the key is **absent**:

```php
if ($validated['ownership_type'] === 'joint' && isset($validated['joint_owner_id'])) {
    $validated['ownership_percentage'] = $validated['ownership_percentage'] ?? 50.00;
}
```

The form always sends a value — `ownership_percentage` is in the submitted field list
(`resources/js/components/Investment/AccountForm.vue:1000`) and is initialised to the
individual default of 100 (`:249`, `:1209`) with no input anywhere to change it when
the user selects Joint Owner. So `isset()` is true, 100 survives untouched, and every
joint investment account is stored 100/0.

The controller's own docblock at `InvestmentController.php:59` states the intended
contract — "ownership_percentage = primary owner's share (default 50 for joint)" —
which the code does not implement.

### Three modules, three behaviours for one concept

Worth fixing together (Rule 20), because a persona tester meets all three in one run:

| Module | Joint save behaviour | Result |
|---|---|---|
| Property | coerces 100 → 50 | correct, 50/50 |
| Investment | leaves 100 | **wrong, 100/0** |
| Savings | hard-rejects with "An explicit ownership share is required" | **cannot save at all** (W-0013) |

### Repro

1. Link a spouse account.
2. `/net-worth/investments` → Add Account → General Investment Account, provider
   "AJ Bell", Current Value 95000. Save.
3. Open the account → Edit → "Show additional information" → Ownership Type =
   "Joint Owner" → Joint Owner = the spouse → Update Account.
4. `investment_accounts.ownership_percentage` is 100.00.
5. The list renders "Your Share (100.00%) £95,000".

Note step 3: on the **create** modal, selecting Joint Owner did not reveal the Joint
Owner select at all in my run and the account saved as `individual` — joint ownership
was only reachable via the edit form. Worth checking as part of this item.

### Evidence

Screenshot: `tests/Persona/20-08-2026_run/pass-a-web/05-web-sarah-investments-your-share-100pct-95000.jpg` — "Full Value £95,000 / Your Share (100.00%) £95,000". Captured on Sarah's login; David's screen is identical and was read from the live DOM but not captured.
Screenshot: `.../07-web-sarah-wealth-summary-household-split.jpg` — the same account counted at £47,500 in the household table.
Report: `reports/R-02-pass-a-verification.md` RED-1, RED-2.

## Acceptance

- [ ] A joint investment account saves `ownership_percentage = 50` unless the user
      explicitly sets another share, matching `PropertyController.php:154-158`.
- [ ] David's joint GIA renders "Your Share (50.00%) £47,500" and Sarah's account
      renders £47,500 for the same single record.
- [ ] The create modal reveals the Joint Owner select when Joint Owner is chosen, and
      the chosen ownership type survives the save.
- [ ] The 50% coercion lives in ONE place that property, investment and savings all
      use (Rule 20) — not three copies. Fix alongside W-0013.
- [ ] Household/net-worth roll-ups do not double-count the account across both
      spouses after the fix.
- [ ] `/m` and iOS joint rendering checked (Rule 19).
- [ ] Re-verified live in the browser by the persona run, from BOTH accounts.

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. Root cause diagnosed to file:line
  above, with the property controller as the proven-correct reference. Not fixed by
  me — routed to build-lead.

- 2026-08-21 build-lead: **FIXED.**

  Root cause confirmed as diagnosed. The fix is not in `InvestmentController` —
  it is one rule in `app/Support/SharedOwnership::primaryOwnerPercentage()`:
  a shared asset arriving with no share, **or with the individual default of
  100**, is stored at 50. The controller's `isset()`-only default and
  `PropertyController`'s inline 100→50 copy are both DELETED; both now route
  through the Store normalisers, which call the one rule. `MortgageService` and
  the savings Fyn-ingest path carried their own copies too — also routed.

  Per acceptance:
  - Joint investment account saves `ownership_percentage = 50` unless another
    share is explicitly set (70 was tested and preserved).
  - Both spouses render £47,500 against the single £95,000 record (see W-0015).
  - The 50% coercion lives in ONE place that property, investment, savings and
    mortgages all use.
  - Household roll-ups no longer double-count:
    `CrossModuleAssetAggregator::calculateInvestmentTotal(16)` = 47500,
    `(17)` = 132500, matching the wealth summary exactly.

  **Legacy data:** `investment_accounts.id 14` was the one row in the local DB
  stored 100/0. The migration in W-0015 normalised it to 50.00 — verified.

  **NOT done, and it is a real gap:** the create-modal defect noted in the repro
  ("selecting Joint Owner did not reveal the Joint Owner select") did NOT
  reproduce for me — `AccountForm` renders the Joint Owner select and the chosen
  type survives the save (covered by the new HTTP test). If the persona tester
  can reproduce it, it needs its own item with the DOM evidence.

  Tests: `tests/Feature/Stores/InvestmentAccountHttpIntegrationTest.php` — a
  joint account posted with `ownership_percentage: 100` stores 50, the response
  gives the primary owner 47500, and `GET /api/investment` as the SPOUSE gives
  47500 with `is_primary_owner: false`. Plus `tests/Unit/Support/SharedOwnershipTest.php`.

- 2026-08-21 build-lead: batch handover (CLAUDE.md Rule 22) — `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md`. Carries the dispatch verbatim, the joint-share consolidation reasoning, decisions taken, dead ends ruled out, and environment state.
