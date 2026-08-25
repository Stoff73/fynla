---
id: W-0368
title: No joint-property discount is applied to an undivided share held with a non-spouse, over-valuing the very property W-0333 was about
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: handoff
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: 2026-08-25T18:00:00Z
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: quality-lead
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333.

`CalculatesOwnershipShare:90-92` values a share as a straight percentage of the whole,
reaching the Inheritance Tax figure via `IHTCalculationService:888`. Property 20 is
therefore valued at 40% × £295,000 = £118,000.

For Inheritance Tax, an **undivided share** in land co-owned with a **non-spouse** is
valued with a discount for the restricted marketability of a part-share — HMRC practice
is typically **10%**, or **15%** where the co-owner is in occupation and not a spouse
(IHTM15071, SVM113040). On this share that is roughly **£11,800–£17,700** of
over-valuation today, and ~£34,000–£51,000 grown into the projected estate.

**Correctly NOT applicable to Properties 9 and 19:** the **related property rules
(IHTA 1984 s161)** deny the discount between spouses, and both are held David/Sarah. So
the discount applies to Property 20 and **only** Property 20 — the same row W-0333 was
about.

Erring without it is **conservative** (it overstates tax), which is why this is a
refinement rather than a blocker.

## Acceptance

1. [x] The discount applies only where the co-owner is not a spouse (s161 respected).
   — mutation-verified: disabling the s161 check reddens exactly that case.
2. [x] The percentage comes from configuration, not a literal (Rule 2). — pinned by a
   test that moves the configured rate and asserts the valuation moves with it.
3. [x] Applied consistently to the current and projected columns — **the two must not
   diverge again** (see F-0026 §1). — both now read the one home; the projection no
   longer reads the shared, deliberately-undiscounted `calculatePropertyTotal()`.

## Working notes

- 2026-08-25 (Brett, on CSJ's delegated authority — 10%, with the 15% case documented):
  **DONE.** `app/Services/Estate/UndividedShareDiscount.php` is the one home for the
  rule; both Inheritance Tax columns read it.

  **Where it went, and the one place it must never go.** A user's share of a property
  for NET WORTH is genuinely the arithmetic fraction — they own what they own. The
  discount is a valuation rule for a taxable transfer, so it belongs to the Inheritance
  Tax path and **must never reach `calculateUserShare()`**, which savings, investments,
  chattels and every net-worth surface read. The codebase already draws this line
  itself: `CrossModuleAssetAggregator:282` names
  `EstateAssetAggregatorService::gatherUserAssets` as the Inheritance Tax path and keeps
  relief out of the shared aggregator for the same reason.

  **Acceptance 3 was the real work.** The projected column read
  `CrossModuleAssetAggregator::calculatePropertyTotal()` — which is **shared with net
  worth and the Letter to Spouse** and must stay undiscounted. Discounting there would
  have leaked an Inheritance Tax rule into net worth; discounting only the current
  column would have left the two Inheritance Tax columns valuing one property two ways,
  which is the divergence F-0026 §1 already recorded once. So `propertyTotal()` exists
  as the Inheritance Tax equivalent of that total, and the projection reads it instead.

  **The 15% case is unreachable, and that is now written down in three places** (the
  config comment, the class docblock, and a test named for it). The higher discount
  applies where the co-owner is **in occupation** and not a spouse. Nothing on
  `properties` records who lives there — the ownership columns are `user_id`,
  `joint_owner_id`, `joint_owner_name`, `household_id`, `ownership_type`,
  `joint_ownership_type`, `ownership_percentage`. Inferring occupation from
  `property_type` would be inventing a fact about someone's living arrangements from a
  percentage. **10% throughout discounts LESS, so it overstates tax rather than
  understating it** — the conservative direction, and the same direction the defect
  erred in before this existed.

  **Measured on live data — the s161 split is the point:**

  | Property | Share | Co-owner | Discount |
  |---|---|---|---|
  | 7 Main, 9 France | 50% | **spouse** | **no** — s161 |
  | 71 Oak Avenue, 72 Chestnut Lane | 50% | **spouse** | **no** — s161 |
  | 8 Fulham | 30% | no account | **yes** — £180,000 → £162,000 |
  | 70 Worth Court | 50% | no account | **yes** — £90,000 → £81,000 |

  **One existing test correctly failed and was updated rather than weakened.**
  `IHTProjectionOwnershipTest:324` asserted a 40% undivided share projects **identically**
  to £118,000 owned outright. That was right for W-0333, which was about keeping a
  stranger's 60% out of the estate — and W-0368 refines it: those are not the same
  estate, which is the whole point. The assertion now pins the two as separated by
  exactly the configured discount, **and W-0333's protection is made explicit rather
  than left implicit** (the share must stay nowhere near the whole £295,000), so the
  older defect cannot come back through the door this change opened.

  **Architecture.** `UndividedShareDiscount` is added to `PropertyStoreBoundaryTest`'s
  allowlist under its existing "pure calculation helpers accept Property instances as
  parameters" category. It issues **zero** `Property::` queries — `propertyTotal()` is
  handed a collection the caller fetched through `PropertyStore` — so it sits on the
  same footing as `PropertyCalculationService` and the allowlisting is the mechanism
  working rather than being dodged.

  **Verification.** Estate Feature + Unit 504 tests / 1,655 assertions; Architecture
  177 / 4,296; the new suite 9 / 17, mutation-verified. Pint clean.

  **Gate outstanding:** `tax-compliance-reviewer`. This changes an Inheritance Tax
  figure and carries `05-perimeter`, so it should not merge uncertified.

- 2026-08-25 — **tax-compliance-reviewer: CLEARED WITH CONDITIONS, three blocking.
  All three now addressed.** Verdict:
  `workforce/ops/handoffs/W-0368/tax-compliance-reviewer-2026-08-25.md`.

  **C1 — I found two valuation sites and there were four.** The residence band cap
  read raw `calculateUserShare()` at `:2246` (current) and `:1260` (projected), with
  the s8E(2) cap measured against it. So the estate was taxed on the discounted share
  while the allowance was capped against the undiscounted one. Measured by the
  reviewer on a £360,000 residence held 50% with an unlinked co-owner plus £500,000
  cash: **£64,800 reported against £70,000 correct — £5,200 understated, and it
  scales.** Both sites now read `UndividedShareDiscount`. The `:2236` docblock, which
  claimed the figure "matches the property and mortgage values that feed
  total_net_estate", was false and is corrected. **This is the acceptance-3 failure
  the acceptance was written to prevent, and I wrote a section claiming to have
  prevented it while two sites were still wrong.**

  **C2 — the row I cited as proof it worked was proof of the defect.** Property 70,
  "19 Worth Court" — the £90,000 → £81,000 example in the commit message — has
  `joint_owner_name` = **"wife"**. `applies()` never read it. Not an edge case:
  `SpouseLinkingService` writes no `spouse_id` on either side until an invitation is
  accepted, so "married, unlinked" is the app's designed state for every married user
  mid-invitation.

  **Fixed at the root rather than by heuristic, because both heuristics fail on the
  live data — measured before choosing:**

  - `marital_status` — the "wife" property belongs to a user marked **`single`**, so
    the status misses it entirely; and it would wrongly refuse the discount to a
    `married` user co-owning with "Mike Jones".
  - name matching — "wife" matches spousal vocabulary, **"GLW" does not**, and
    initials could perfectly well be a spouse.

  Three rows ruled out both. **The user already tells us.** `PropertyForm.vue` offers
  "<name> (Spouse)" and "Other (Enter Name)" as distinct choices, and offers the
  spouse option even when the spouse has no account — but `handleJointOwnerSelection()`
  wrote only the name, discarding the distinction one line after it was made. So:

  - `properties.joint_owner_is_spouse`, **nullable** — `database/CLAUDE.md`'s
    `expenditure_sharing_mode` lesson: a NOT NULL DEFAULT makes "never asked"
    indistinguishable from "chose this". NULL means we have not asked.
  - The form stores the choice; `PropertyResource` publishes it; the request and store
    layers carry it.
  - **A second, quieter defect fixed on the way:** `populateForm()` reconstructed any
    named co-owner as "Other", so reopening a property and saving it silently
    converted a spouse into a third party and changed an Inheritance Tax valuation.
    The form did not round-trip its own input.
  - **Unknown takes no discount.** Overstates tax rather than understating it — the
    safe direction, and the one the application already erred in before this work.

  **C3 — `PropertyReadConsumerParityTest:193` passes**, and now for the right reason:
  its fixture states no answer, so no discount is taken.

  **Statutory corrections from the reviewer, applied throughout:** **IHTA 1984 s160**
  is the authority for the discount (open-market value) — IHTM15071 and SVM113040 are
  guidance on it, and every citation of mine omitted the section. And **s161 does not
  "deny" the discount, it SUBSTITUTES a valuation basis** — related property is valued
  as a proportion of the combined whole, leaving no restriction to price. s161 also has
  **no connected-company limb**, so half my question 2 premise was wrong.

  **Cleared by the reviewer:** 10% throughout is defensible; my ban on inferring
  occupation from `property_type` is right **for a better reason than I gave** — that
  column records where *the user* lives, not the co-owner. Rule 2 clean. The net-worth
  separation is correct and has not leaked.

  **One slip caught by an existing test rather than by me:** the new field leaked into
  the wizard's `mortgageForm` (a `replace` without a count limit), and
  `PropertyWizardMortgageFieldParityTest` failed on `mortgage_joint_owner_is_spouse`.
  A mortgage has no such column and needs none — W-0228 makes liability follow the
  property share. Removed.

  **Verification.** Estate + Stores 702 tests / 2,269 assertions; Property + Mortgage
  + NetWorth 485 / 2,209; Architecture 177 / 4,296. Pint and ESLint clean. A
  `LazyLoadingViolation` appeared once in a combined run and did not reproduce after
  the mortgage-form leak was removed.

  **Consequence worth stating plainly: the discount now applies to almost nothing
  until users answer the new question.** That is correct — we do not guess — but
  W-0368's stated benefit largely does not materialise until the data exists.

  **Re-gated:** back to `tax-compliance-reviewer` for the three conditions.
