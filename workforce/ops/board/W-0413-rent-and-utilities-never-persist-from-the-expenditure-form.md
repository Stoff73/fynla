---
id: W-0413
title: rent and utilities never persist from the expenditure form — both endpoints accept them and neither validates them
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
status: done
severity: medium
surfaces: [web, m]
created: 2026-08-23T02:30:00Z
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [SharedExpenditure, W-0412]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Found while enumerating expenditure write paths for W-0412; **not fixed there**, because
it is a different defect with its own blast radius.

`ExpenditureForm` posts `rent` and `utilities` with every detailed save — they are the
first two entries in `allEssentialFields` and they are included in the `allFields` loop
that builds the payload. Neither `UserProfileController::updateExpenditure` nor
`updateSpouseExpenditure` lists them in its `validate()` array, so Laravel drops them from
`$validated` and **they are never written**. Silent: the form closes as though it saved.

They are also absent from `SharedExpenditure::SHARED_FIELDS` and from the field list in
`OnboardingService::processExpenditureInfo`, so no path shares or stores them.

Masked for `peak_earners` because the persona owns a main residence, and `essentialFields`
filters both fields out of the visible grid when `hasMainResidence` is true. **A renting
persona would lose their largest single category.**

## Acceptance

1. Decide whether `rent` and `utilities` are household running costs that divide (they
   look like it) or per-account costs that do not — this decides whether they join
   `SHARED_FIELDS`, and that decision changes what `HouseholdExpenditureWriter` mirrors.
2. Whatever is decided, the two endpoints, the onboarding field list and the sharing rule
   agree.
3. A fixture with **no main residence**, so the fields are actually rendered and posted —
   the current persona suite cannot see this at all.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed. One missing line, and a second half nobody had noticed.**

  **Root cause: `$request->validate()` returns only what it validated.** `UserProfileController::updateExpenditure()`'s rule list started at `food_groceries`; `rent` and `utilities` were absent, so both were stripped before the write, silently. **Everything else was already in place** — the columns exist on `users`, `User:176-177` casts them, `ExpenditureForm.vue:1415-1416` collects them, and `CoordinatingAgent:5236` lists them among the expenditure fields. Only the rule was missing.

  **The people losing the data were the ones who could least afford to.** Both fields are shown ONLY to a user with no main residence (`ExpenditureForm.vue:1426`), because a homeowner enters housing costs against the property. So a renter typed their rent into a box that discarded it, and had no property record to hold it either.

  **The second half, which the item did not name and a write-only fix would have missed:** the read-back was broken too. Neither field was on `UserResource`, and `getProfile()` returns its own shape entirely — so even after the write worked, the form showed empty boxes over populated columns, **which is indistinguishable from the original defect.** Both are now returned.

  **Placed OUTSIDE the `categories` block, deliberately**, and this was the last thing to get right: `categories` is gated on detailed-expenditure entitlement and is absent altogether from a summary-only profile. Putting rent inside would have hidden a renter's rent behind a Premium gate — precisely where W-0011 found free-tier users and put them back.

  **Tested:** `tests/Feature/UserProfile/RentAndUtilitiesPersistTest.php` — 2 passed, a **round trip** (write then read) rather than a write assertion, plus a case proving a stated 0 overwrites a previous value rather than being treated as nothing entered. 266 expenditure/profile/tier tests pass, 901 assertions. Pint clean.
