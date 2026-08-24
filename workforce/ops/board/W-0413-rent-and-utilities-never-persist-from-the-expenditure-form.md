---
id: W-0413
title: rent and utilities never persist from the expenditure form — both endpoints accept them and neither validates them
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
status: queued
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
