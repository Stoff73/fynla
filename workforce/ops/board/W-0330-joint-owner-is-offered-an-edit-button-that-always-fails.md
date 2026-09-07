---
id: W-0330
title: A joint owner is shown Edit and Delete buttons on a shared investment account that can only ever return 404
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: done
severity: medium
surfaces: [web]
created: 2026-08-23T00:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0322, W-0257]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

**Reproduced in the browser** while verifying W-0322.

Signed in as **Sarah (17)**, the joint General Investment Account (account **14**,
`user_id` 16, `joint_owner_id` 17) shows a detail view with **Edit** and **Delete**
buttons. Opening Edit, changing the provider and pressing Update produces:

```
PUT /api/investment/accounts/14  →  404
{"success":false,"message":"Investment account not found or access denied."}
```

`InvestmentController::updateAccount:497-500` is explicit and its docblock says so:

```php
// Only primary owner (user_id) can update.
$account = $this->investmentAccountStore->find($id, $user);
if ($account === null || $account->user_id !== $user->id) {
    return $this->notFoundResponse('Investment account');
}
```

**The rule is fine. Offering a control that cannot work is not.** The joint owner
fills in a form, presses a button, and is told the account does not exist — of an
account they are looking at, whose balance is on their own dashboard.

Two things are wrong independently:

1. **The control should not be there** for a non-primary owner, or should say why
   it is unavailable.
2. **"not found or access denied" is the wrong message** for someone with legitimate
   read access. It reads as data loss rather than a permissions boundary.

`MortgageController::update:219-222` carries the identical primary-owner check, so
the mortgage tab is likely to have the same shape — worth checking rather than
assuming.

## Acceptance

1. A joint owner either does not see Edit and Delete on a record they cannot
   modify, or sees them disabled with a reason.
2. If the request happens anyway, the response distinguishes "you may not change
   this" from "this does not exist".
3. The mortgage equivalent checked, and any other primary-owner-only write with a
   control exposed to the joint owner.

## Working notes

- 2026-08-23 build-lead (`fix-cycle4-columns`): found while verifying W-0322 as
  Sarah; the 404 is why that verification moved to account 13. Whether joint owners
  *should* be able to edit shared records is a separate product question and is
  NOT what this item asks — it asks only that the interface stop offering an action
  it will refuse.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.**

  **The backend rule was right and is unchanged.** `InvestmentController:498` refuses any update where `$account->user_id !== $user->id`, so a joint owner's Edit or Delete failed at the API. What was wrong is that the UI offered the control anyway: `InvestmentProjections.vue:47-61` rendered both buttons unconditionally, so a co-owner got a dead end that failed with no explanation on screen.

  `canManageAccount` now gates them, and it asks the SAME question the server does rather than inventing a second rule — `isPrimaryOwner()` from `@/utils/ownership`, which prefers the API's own `is_primary_owner` flag and falls back to comparing `user_id` with the viewer. A helper already existed for exactly this; adding a local check would have been the second mechanism (Rule 20).

  **The control is replaced, not merely removed.** A button that silently vanishes reads as a bug to the person it vanished for, so the co-owner is told *"[Name] manages this account"*, using `coOwnerName()` from the same utility. That is the difference between a restriction and a glitch.

  **Tested:** 821 frontend tests pass, including the 744 component tests.

  **NOT DONE.** Not browser-verified — `public/build/` is a csjones build, and this is precisely the kind of change that wants a second account logged in to confirm. The equivalent affordance on `/m` and native was not checked; **W-0496 already covers the native side** and is not double-counted here.
