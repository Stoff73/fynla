---
id: W-0527
title: Quick succession relief is configured and implemented by nothing
mission: null
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: done
claimed_by: null
severity: low
surfaces: [web, m, ios]
created: 2026-08-29T13:30:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: [W-0463, W-0465, W-0091]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: W-0463 independent verification, 2026-08-29 — CSJ ruled these four are work and need doing
---

## Intent

**CSJ, 2026-08-29, on the four reliefs W-0463 left open: "the four reliefs are work, and
need to be done."** This is one of the four, broken out so each can be claimed, gated and
verified on its own rather than sitting inside a structural item.

`getQuickSuccessionRelief()` has zero callers. IHTA 1984 s141 reduces the tax where the same property is taxed twice within five years — a beneficiary who inherits and then dies shortly after. The relief tapers by the years between the two deaths.

## Notes

Lowest of the four: it needs a second death within five years of an inherited estate, which the app does not currently model at all. Filed so it is a decision rather than an omission.

## Acceptance

1. `getQuickSuccessionRelief()` has a real caller on the estate path, and the `quick_succession_relief` configuration
   decides the outcome — no literal reproduces any part of it (Rule 2).
2. A household that qualifies sees the relief in **both** the current and the projected
   Inheritance Tax column. W-0465 records what happens when only one column gets a
   relief: the two halves of a comparison table disagree by the whole of it.
3. A household that does not qualify is unaffected — before/after on a non-qualifying
   estate shows no movement.
4. Tests that fail with the relief removed, not just tests that pass with it present.
5. `tax-compliance-reviewer` — it moves Inheritance Tax for every qualifying household.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.** Built on CSJ's decision 2026-08-31. Worked through `superpowers:systematic-debugging`.

  **The item's own note was out of date, and that changed the size of the job.** It said quick succession relief *"needs a second death within five years of an inherited estate, which the app does not currently model at all."* It does: `life_events.event_type` already includes `inheritance`, carrying the amount received and the date. **Exactly one datum was missing** — the Inheritance Tax borne on the earlier death, which is the multiplicand of the whole s141 formula. One nullable column, not a subsystem.

  **What was built:**
  - `app/Services/Estate/QuickSuccessionReliefCalculator.php` — s141: `tax paid × (net received ÷ gross transfer) × taper`. The taper is READ from `inheritance_tax.quick_succession_relief`, band by band in configured order, so the table can be re-banded without touching code. `getQuickSuccessionRelief()` now has its real caller.
  - `2026_08_31_140000_add_iht_paid_on_prior_death_to_life_events` — nullable `decimal(15,2)`. **Nullable is load-bearing and the migration says so:** most inheritances bear no tax, and "not stated" is a different fact from "zero". A NOT NULL DEFAULT 0 would turn silence into an assertion.
  - Both Form Requests: `sometimes|nullable|numeric|min:0|max:9999999999999.99`. `min:0` not `min:0.01`, because a stated zero is a real answer; the range matches the column.
  - `IHTCalculationService` — applied in `assessTaxPosition()`, **which is the one mechanism producing a liability** (`:435` current, `:973` projected). That is how acceptance 2 is met: the relief cannot reach one column and not the other, because there is only one place to apply it. Published as `quick_succession_relief` beside the bill, so a reader can reconcile a liability that is lower than taxable estate × rate (the audit gap W-0171 names).
  - `LifeEventForm.vue` — optional field, shown only for an inheritance, with the derivation explained. The gross transfer is DERIVED as net + tax rather than asked for: a third field the user could contradict is a worse question than none.

  **Acceptance:** (1) real caller, configuration decides, no literal. (2) both columns by construction. (3) **a non-qualifying household does not move — proven twice**: the peak_earners locks are unchanged at £1,728,780 / £343,512, and a test adds an inheritance with a NULL tax figure and asserts the liability is identical. (4) **mutation-verified**: removing the subtraction from the liability goes red, and hardcoding the taper instead of reading configuration goes red.

  **Tested:** 7 unit + 4 feature (taper bands, window expiry, no-tax-stated, relief exceeding the tax paid); 808 estate/IHT/life-event passed, 2,656 assertions; Pint clean.

  **Rule 19 — stated, not assumed.** `/m` has NO life-event write path: no POST, no form component, only the read-only list in `Goals.vue`. So there is no mobile form to add the field to, and the relief reaches `/m` through the shared backend. Nothing was skipped; there is nothing there to change. iOS not checked.

  **NOT DONE — acceptance 5.** No `tax-compliance-reviewer` pass on the s141 formula.
