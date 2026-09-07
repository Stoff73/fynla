---
id: W-0243
title: The native iOS retirement card cannot show a guaranteed income, so a defined-benefit-only spouse still reads £0 there
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: deferred-ios
severity: medium
surfaces: [ios]
created: 2026-08-22T20:40:00Z
claimed: null
blocked_by: [W-0238]
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0238]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Parity gap left open by **W-0238**, whose acceptance named web and `/m` only.
Filed rather than skipped (Rule 19: surfaces are named individually).

### The gap

W-0238 added `guaranteed_income` to `modules.retirement` on
`GET /api/v1/mobile/dashboard`, and web and `/m` now lead with it when there is no
defined contribution pot. `ios-native/Fynla/Features/Dashboard/DashboardModels.swift`
decodes `pot_value`, `projected_income`, `target_income` and `income_gap` through an
explicit `CodingKeys` enum and has no case for the new key.

**Nothing breaks** — an unlisted key is ignored by `Codable`, so the native app is
unaffected by the change and needs no coordinated release. It simply still shows
£0 for a household whose whole retirement provision is a defined benefit scheme,
which is the defect W-0238 fixed everywhere else.

### The change

1. `guaranteedIncome: Decimal?` on the retirement summary plus
   `case guaranteedIncome = "guaranteed_income"`.
2. The same two-line rule the shared JavaScript helper now holds
   (`resources/js/utils/retirementHeadline.js`): lead with the pot; where there is
   none and there is secured income, show the income and label it per year. Never
   render an annual income as a balance.
3. `FinancePanelsView.swift` renders it.

**Read the JavaScript helper before writing the Swift** — it is the one home for
the rule, and a third independent implementation of "which number does this card
lead with" is what W-0238 existed to remove.

## Acceptance

1. Native shows £35,000/year for Sarah Jones and £500,000 for David Jones.
2. Verified in the simulator against csjones staging, per the TestFlight trap in
   root `CLAUDE.md` (the app reads the **staging** database).
3. The pot/income rule matches web and `/m` exactly, including the caption.

---

## Deferred 2026-09-01 — iOS is out of scope for the board loop

CSJ ruled on 2026-08-31 that the board loop covers web and `/m` only, and every iOS
item defers rather than being worked. This item's `surfaces` is `[ios]` alone, so all
of it defers. No Swift was changed and nothing was verified on a simulator.

The backend and `/m` halves named in the item are unaffected and remain available to
whoever picks the native work up.
