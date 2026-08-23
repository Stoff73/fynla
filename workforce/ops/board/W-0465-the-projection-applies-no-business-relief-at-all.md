---
id: W-0465
title: The Inheritance Tax projection applies no Business Property Relief at all, so the current and projected columns disagree by the whole relief
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: null
reviewers: [tax-compliance-reviewer]
status: gated
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-23T19:20:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0091, W-0463]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer finding R6, re-review of a1d36b90b, 2026-08-23 — surfaced while verifying the F2 taper-base fix, not caused by it
---

## Intent

`$projectedBusiness` sums `current_value` over non-exempt business assets and
`$projectedNetEstate` subtracts no relief. So a £6m trading business shows **£4.25m of
relief today and none at death** — the two columns of the same table disagree by the
whole relief, on a screen whose purpose is to compare them.

**This makes a comment in `assessTaxPosition()` accidentally true.** It says the
projection "does not model business relief separately, so its net estate is already
relief-free", which is why passing `$projectedNetEstate` as its own taper base is
correct. That reasoning holds **only because the projection is wrong about relief.**
Fixing this item invalidates that comment and the taper base must be revisited in the
same change.

## Acceptance

1. The projection applies the same capped, pro-rata relief as the current calculation —
   one mechanism (`EstateAssetAggregatorService::applyBusinessPropertyRelief()`), not a
   second implementation (Rule 20).
2. `projected_business_relief_deduction` is published and rendered beside the current
   figure, web and `/m` (Rule 19). `IHTPlanning.vue` already reads the key with a
   fallback to the current value; that fallback becomes wrong once this lands.
3. The projected taper base is re-derived, since it can no longer be assumed relief-free.
4. Business values are projected forward before relief is applied — relieving a
   present-day value against a future cap understates the charge.
5. `tax-compliance-reviewer` on the change.

## Working notes

- 2026-08-23 — Raised from the re-review. **No persona can exercise it**: the largest
  business interest on the dev database is £750,000, below the cap, so the relief is
  100% in both columns and the disagreement is invisible on every fixture. Needs the
  purpose-built shape used in `BusinessPropertyReliefCapTest`.

- 2026-08-23 — **Fixed.** The projection reads the relief the ONE allocator already
  stamped onto `iht_relief_amount` (`applyBusinessPropertyRelief()`, called by the same
  `gatherUserAssets()` the projected values come from). No second allocation, per
  acceptance 1 and Rule 20.

- 2026-08-23 — **Acceptance 4, stated honestly rather than ticked.** Business values are
  deliberately NOT projected forward — they enter the projection at present-day worth, as
  the code has always said. So relief struck on present-day values IS relief on the values
  being taxed, and there is nothing to project first. **A comment marks the trap for
  whoever adds business growth**: the £2,500,000 allowance does not grow with the
  business, so relief allocated on today's value against a grown value would UNDERSTATE
  the charge — the allocator must be re-run over the projected values, never scaled.

- 2026-08-23 — **Acceptance 3.** `assessTaxPosition()` was handed `$projectedNetEstate`
  as its own taper base on reasoning that was true only because the projection was wrong.
  The base is now struck explicitly as gross-before-reliefs less liabilities
  (IHTM46023 on s8D(5)(d)), mirroring `$estateForTaper` in the current column, with
  wholly relieved businesses added back and partly relieved ones NOT double counted (R2).

- 2026-08-23 — **The result block ENUMERATES the projected keys**, so returning the figure
  from `calculateProjectedValues()` was not enough — it had to be named at the merge too,
  and again in the controller, and again in `IHTPlanning.vue`'s hand-written mapping. Four
  places, the same shape as W-0134/W-0399. The first test run caught it as an undefined key.

- 2026-08-23 — `IHTPlanning.vue`'s fallback to the CURRENT deduction is removed, per
  acceptance 2. **That fallback was a Collision**: it made the projected column display
  the right number for the wrong reason, so a browser check before the fix would have
  looked correct.

- 2026-08-23 — **Mutation-tested.** Reverting the taper base reddens the taper case;
  removing the relief from the projected net estate reddens the net-estate case. Both
  guards discriminate. The taper pair required a fixture with a main residence AND a
  direct descendant — without both, `rnrb_status` is 'none' and every taper assertion
  passes against a band that was already zero. **The pre-existing pre-relief taper case in
  the same file sits in exactly that trap** and is not fixed here.

- 2026-08-23 — **Browser-verified** on a £6,000,000 business (no persona can produce one).
  The projected column reads 10,669,753 − 3,500 − 4,250,000 = **6,416,253** and reconciles.
  It read 10,666,253 before, with the relief row above it ignored.

- 2026-08-23 — **`/m` needs nothing**: after W-0469 its estate screen shows no Inheritance
  Tax breakdown and hands off to web, so Rule 19 is satisfied by that decision, not skipped.

- 2026-08-23 — **Adjacent, filed as W-0470, not folded in here.** `IHTController` recomputes
  `projected_net_estate` on a liabilities figure the service's `projected_taxable_estate`
  was never struck on (£3,500 against £0), so those two rows still disagree by that amount.
  Putting the relief back made the Net Estate row reconcile and left the gap visible one
  row down. Fixing it means changing which liabilities the projection uses, which moves the
  taper base — a different change.
