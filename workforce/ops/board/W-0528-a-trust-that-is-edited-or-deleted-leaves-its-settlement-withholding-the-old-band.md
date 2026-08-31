---
id: W-0528
title: A trust that is edited or deleted leaves its settlement gift behind, withholding the wrong nil rate band
mission: null
branch: fix/w-0528-trust-edit-delete-leaves-the-settlement-gift-behind
owner: build-lead
reviewers: [tax-compliance-reviewer]
status: gated
claimed_by: null
severity: high
surfaces: [web, m, ios]
created: 2026-08-29T15:40:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: ["W-0523 the multi-cycle trust death charge", "W-0463 taper relief on failed transfers", "W-0023 bequests.will_document_id marks the rows a sync owns"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found fixing W-0523; raised by CSJ, 2026-08-29 — "we do need to sort the edit and delete of trusts so the estate is updated correctly as well as the gifting"
---

## Intent

A settlement into a trust is a chargeable lifetime transfer, and the `Gift` row
`TrustObserver` writes for it is what withholds the settlor's nil rate band for seven
years. **The observer handled `created` and nothing else**, and nothing linked the gift
to the trust, so from the moment a trust changed the band was worked from a settlement
that no longer existed:

- **Edit the settled amount up** and the estate kept withholding the smaller original
  figure — band the settlement had already used, handed back to the estate. Raising a
  £200,000 settlement to £300,000 left £100,000 of band available that should not have
  been, understating the bill by £40,000 at the standard rate.
- **Edit it down** and the estate kept withholding the larger figure, overstating it.
- **Delete the trust** and the gift outlived it, withholding a band for a settlement
  that no longer existed.
- **Rename the trust** and any name-matching reconciliation lost the gift entirely.

The same hole ran the other way. `PUT`/`DELETE /api/estate/gifts/{id}` let a user edit
or delete the mirrored gift straight from the gifting module, releasing the band while
the trust still stood — and the next edit to the trust put it back, so the two records
fought and the estate answered differently depending on which had been touched last.

## Acceptance

- [x] The settlement gift follows the trust's amount, date and name for the whole life
      of the trust, not just its creation.
- [x] Deleting a trust stops it withholding band; restoring it withholds again.
- [x] Editing the settled amount down to nothing releases the band; editing it back up
      withholds again.
- [x] A rename does not break the link — the link is a foreign key, not a name match.
- [x] A chargeable lifetime transfer the **user** entered by hand is never touched.
- [x] The gifting module cannot move a band the trust owns.
- [x] Asserted on the **band** through `FailedGiftTaxCalculator`, not only on the gift
      row, because the band is what the user is shown and what was wrong.

## Working notes

(append-only)

- 2026-08-29 build-lead: **`gifts.trust_id`, following the `bequests.will_document_id`
  precedent (W-0023)** — the sync owns the rows it stamped, and a NULL means the user
  entered the gift themselves. Matching on the trust NAME was the alternative and does
  not survive a rename, which is the edit most likely to happen. Migration backfills the
  gifts the observer already wrote, narrowed by the observer's own note so a transfer the
  user entered by hand is never claimed even where it matches on every other column.

- 2026-08-29 build-lead: **`TrustObserver` now handles `created`, `updated`, `deleted`,
  `restored` and `forceDeleted`**, all through one `syncSettlementGift()`. `notes` is
  deliberately not mirrored on update — the user may have annotated the gift and nothing
  about the trust contradicts what they wrote. Errors are still logged and swallowed, as
  the original write was: a stale gift is recoverable, a throw would stop the user saving
  the trust at all.

- 2026-08-29 build-lead: **both write paths go through the model**
  (`TrustController::updateTrust` uses `$trust->update()`, `deleteTrust` uses
  `$trust->delete()`), so the events fire and `UserDataCacheObserver` invalidates the
  cached calculation. Verified rather than assumed — a query-builder delete would have
  fired nothing.

- 2026-08-29 build-lead: **the gifting side is now refused**, 422, with a message naming
  the trust to change instead. One record, one owner.

- 2026-08-29 build-lead: **verification.** 574 passed across `tests/Feature/Estate`,
  `tests/Unit/Observers` and `tests/Unit/Services/Estate`, plus 197 across Architecture,
  Feature Tax and Constants. **Mutation-verified**: 7 of 8 observer tests and 3 of 5
  estate-band tests fail against the pre-fix observer, and both refusal tests fail
  against the pre-fix controller. The tests that pass both ways are regression guards,
  not change claims, and are marked as such.

- 2026-08-29 build-lead: **trap for whoever writes the next estate feature test.** The
  gifting write endpoints sit behind `estate.full`, so a test needs
  `TierConfigurationSeeder` **and** a premium user
  (`User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium'])`).
  Without the seeder every `/api/estate/*` call returns **404 "Endpoint not found"**, not
  a 403 — the route matches and the tier resolution fails behind it, so it looks like a
  missing route and is not one. Cost me four debugging cycles.

- 2026-08-29 build-lead: **reported, not fixed.** The gifting UI on web and `/m` still
  renders edit and delete controls for a trust-owned gift; they now fail with a clear
  422 rather than silently moving the band, but the control should not be offered.
  `GiftResource` does not expose `trust_id`, which is what a client would need to hide
  it. Left out deliberately rather than shipping an unused field.

- 2026-08-30 build-lead: **merged to `dev` as PR #752** — the settlement gift tracking its trust for life. Left `gated` rather than
  `done` because the reviewer gate named above has not run; `done` here would mean the
  change is on `dev`, which is true, and would hide that nobody has certified it.
