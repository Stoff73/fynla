---
id: W-0236
title: The mortgage share cannot be entered — the form offers only "Me only" or "Joint borrowers" and hardcodes 50%
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0022-cycle4-dashboard-module-totals-and-cache.md
owner: build-lead
status: gated
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T18:45:00Z
claimed: 2026-08-22T20:15:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0228, W-0015]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Raised as D-01 by the tester. `PropertyForm.vue` offered **"Me only" / "Joint
borrowers"** and hardcoded `ownership_percentage = 50` on the joint branch, so a
40/60 tenants-in-common mortgage was not expressible. The persona case: David 40%
and Mike Barrett 60% on the Manchester unit.

### What CSJ's ruling changed about this item

**It gets simpler, not harder.** A debt is shared exactly as the asset securing it
is shared (W-0228), so the mortgage's basis is not a separate fact and should never
have been collected. Adding a percentage input would have made it *worse* — a
second place to state something the property already says, free to disagree with
it, which is precisely how the Manchester unit came to be a 40% property carrying a
50% mortgage.

The old copy stated the contradiction outright: *"Choose who is legally responsible
for this mortgage. **This can be different from the property ownership split.**"*

## Acceptance

1. The form stops asking for a borrower split.
2. The user can see what their share will be, derived from the property.
3. A saved mortgage's stored ownership agrees with its property.
4. Web and `/m` named individually.

## Working notes — DONE 2026-08-22, handed to quality-lead

**Stated, not asked.** The "Borrower(s)" select, the joint-borrower picker, the
free-text borrower name and the 50% hardcode are all removed. In their place, a
read-only line derived from the property ownership already set on the same form:

> *You are responsible for 40% of this mortgage, matching your share of the
> property. The remaining 60% belongs to Mike Barrett.*

**The stored row is repaired going forward.**
`mirrorPropertyOwnershipToMortgage()` copies the property's `ownership_type`,
`ownership_percentage`, `joint_owner_id` and `joint_owner_name` onto the mortgage
payload, so new and edited mortgages stop contradicting their property. Existing
rows are unaffected and do not need repair — the reader resolves through the
property regardless (W-0228).

Three watchers, not one: the percentage and the co-owner can each change without
the type changing (tenants-in-common 40 to 60; a different co-owner on the same
basis).

**One trap avoided, recorded because it is silent:** a `'form.ownership_type'`
watcher already existed. Adding a second entry with the same key would have
**replaced** it with no error and no warning, quietly dropping the default-setting
behaviour it carries. The mirror call was added inside the existing watcher
instead.

### Surfaces

- **web** — done. `resources/js/components/NetWorth/Property/PropertyForm.vue`.
- **`/m`** — **no counterpart exists.** `resources/mobile/` has no property form;
  `/m` reads property and mortgage data and hands off to the web app for entry.
  Nothing to change, and nothing skipped. Verified by search rather than assumed.
- **iOS** — no native property form either.

### Evidence

`npx vitest run resources/js/components/__tests__/NetWorth` — 59 passing.
The derived figures this feeds are covered by
`tests/Feature/NetWorth/MortgageShareFollowsThePropertyTest.php` (10 passing).
