---
id: W-0327
title: Editing a savings account from its detail page silently does nothing — the modal emits `save`, the page listens for `saved`
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: queued
severity: high
surfaces: [web]
created: 2026-08-22T23:55:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0257]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

**Reproduced in the browser** while attempting the W-0263 savings verification.
The same silent-failure shape as W-0257 fault 2, from a different cause.

### The defect

`resources/js/views/Savings/SavingsAccountDetail.vue:181-186`:

```vue
<SaveAccountModal
  v-if="showEditModal"
  :account="account"
  @close="showEditModal = false"
  @saved="handleAccountSaved"
/>
```

`resources/js/components/Savings/SaveAccountModal.vue:1018` emits:

```js
this.$emit('save', accountData);
```

**`save` versus `saved`.** Nothing is listening, so pressing "Update Account"
runs the modal's validation, emits into the void, and stops. No request, no
error, no message, and the modal stays open.

This is a direct violation of the project's own convention — root `CLAUDE.md`
Rule 3: *"Form modals emit `save` (not `submit`)... Parent handles API call and
closes modal on success."* The modal is correct; the listener is not.

**The sibling host is wired correctly.** `components/Savings/AccountDetails.vue:145`
uses `@save="handleSaveAccount"` and calls `updateAccount`. So the same modal works
from one host and silently fails from the other.

**One behaviour, two mechanisms, one broken — Rule 20's exact shape.** "Save this
savings account" is implemented twice, and because it is implemented twice the two
were free to drift; nothing held them together, so one host could be rewired or
written fresh without the other noticing. **Consolidating to a single save path is
therefore part of the fix, not a follow-up** — correcting `@saved` to `@save` in
one file restores the behaviour but leaves the second copy standing and the next
divergence just as available. Rule 20 is explicit that editing copies in lockstep
is a violation rather than a fix.

**And the failure mode is the one this whole batch keeps meeting:** the user gets
no error, no request and no message — the button simply does nothing, exactly as
in W-0257 before its fix.

`SavingsAccountDetail` also passes `:account` but **not** `:is-editing`, which
`AccountDetails` does pass, so the modal may not be in edit mode either.

### Reproduction

As David (16), `http://localhost:8000/savings/account/28` (Nationwide Cash ISA,
4.25%) → Edit → change Interest Rate to 12.5 → Update Account.

Observed: `formData.interest_rate` is `12.5`, `submitting` is `false`,
`isaAllowanceError` is `null`, no invalid form control, **and no network request
of any kind.** The database is unchanged. Verified again via `form.requestSubmit()`
to rule out a click-dispatch artefact — still no request.

### Why it matters beyond the save

It blocked the browser verification of W-0263's savings acceptance (a rate above
10% saving). That path is proven at the API level
(`tests/Feature/Validation/ValidatedRangeReachesTheColumnTest.php` — 12.5% saves
via `POST /api/savings/accounts`, 25% is a 422), but **the form route to it does
not work**, and no test would have caught that because the defect is in the event
name binding two components together.

## Acceptance

1. `SavingsAccountDetail` listens for `save` and calls the update action, passing
   `:is-editing` as `AccountDetails` does.
2. Editing a savings interest rate from `/savings/account/:id` saves — browser
   verified, with the database checked afterwards.
3. Worth a sweep for the same mismatch elsewhere: `@saved` on any component whose
   child emits `save`. Rule 3 makes `save` canonical, so `@saved` is the tell.

## Working notes

- 2026-08-22 build-lead (`fix-cycle4-columns`): `resources/js/views/Savings/` is
  outside this batch's scope, so reported rather than fixed. The fix is one
  attribute plus a handler.
