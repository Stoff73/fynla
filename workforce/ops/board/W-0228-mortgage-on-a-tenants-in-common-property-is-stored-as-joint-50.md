---
id: W-0228
title: A mortgage secured on a tenants-in-common property is stored as joint 50% instead of matching the property's 40% share — overstating the owner's debt by £12,000
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0022-cycle4-dashboard-module-totals-and-cache.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T18:40:00Z
claimed: 2026-08-22T20:15:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0216, W-0226, W-0203, W-0187, W-0015]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found by: coordinator DB reconciliation during persona run `peak_earners`, cycle 4
pre-pass, local `laravel` database. Accounts **David Jones (16)** / **Sarah Jones (17)**.

**A debt secured on an asset must be shared on the same basis as the asset.**

### Observed

The Manchester property and the mortgage secured on it declare two different
ownership bases:

```
properties 20  Victoria Mill    £295,000  tenants_in_common  40%  joint_owner_id NULL
mortgages  16  (on property 20) £120,000  joint              50%  joint_owner_id NULL
```

The property is correctly `tenants_in_common` at **40%** — the persona's Manchester
unit is co-owned with **Mike Barrett**, an off-platform third party. The mortgage
secured on that same property is stored as `joint` at **50%**.

### Consequence

| Figure | Shown | Correct | Error |
|---|---|---|---|
| David's share of mortgage 16 | £60,000 | £48,000 | **£12,000** |
| Household debt | £305,000 | £293,000 | **£12,000** |

**This has already been signed off wrong.** `F-0021` measured household debt at
**£305,000** and recorded it as correct. Any cycle-3 conclusion resting on that
figure — protection debt need, net worth, estate liabilities — inherits the error.

### Not a defect, do not "fix" it

`joint_owner_id = NULL` on **both** rows is expected here, not a bug. The co-owner is
off-platform, and off-platform co-owners are first-class in this codebase (there is a
`joint_owner_name` column; see W-0025's `prior_art_found`). Do not conflate the NULL
owner id with the ownership-type mismatch. **Only the `ownership_type` /
`ownership_percentage` pair on the mortgage is wrong.**

### Expected

A mortgage's ownership basis derives from, or is validated against, the property it is
secured on. `tenants_in_common 40%` on the property means `tenants_in_common 40%` on
its mortgage. Where they disagree, the property is authoritative.

### Scope note

Same family as **W-0216** (property projection counts a tenants-in-common share at
100%) and **W-0226** (liabilities breakdown ignores the ownership share). Check
whether one shared reader fixes all three before writing a fourth copy of the rule —
Rule 20 applies: if more than one mechanism computes a debt share, consolidating them
is part of the fix, not a follow-up.

### Verification

Not browser-verified — raised from the database. The fix must be confirmed on web
**and** `/m`, on **both** accounts, per Rule 19.

---

## CSJ RULING, 2026-08-22 — liability follows the PROPERTY SHARE

**Decided by CSJ. Not open for re-litigation by any agent, plan, PR or sub-agent
(Rule 18).**

The question escalated was: does a mortgage's liability share follow the **borrowers
named on the mortgage**, or the **ownership share of the property securing it**? Two
mechanisms implemented it differently and both were live in one panel.

**CSJ's ruling: a debt is shared exactly as the asset securing it is shared.**

Consequences, all in scope for the fix:

1. **`CalculatesOwnershipShare.php:110-111` is WRONG and its docblock must be deleted.**
   It documents *"mortgage liability follows the mortgage borrower(s), not the ownership
   percentage recorded on the linked property."* That is now contrary to the ruling.
2. **Mechanism 1 is the bug.** `CalculatesOwnershipShare.php:124-140` reads the ownership
   pair off the mortgage row. It must instead resolve from the linked property.
3. **Mechanism 2 has the right behaviour** — `EstateController.php:112-113` already copies
   the property's ownership onto the mortgage. **But per Rule 20 it must not simply be
   left standing as a second implementation.** Consolidate to ONE reader that every
   consumer calls.
4. **The Liabilities page and property detail were right**, and the Mortgage tab and
   property list card were wrong.
5. **Accepted limitation, stated explicitly so nobody "fixes" it later:** this model
   **cannot express a mortgage held in one spouse's sole name against a jointly-owned
   property.** CSJ accepted that trade-off knowingly. Do not add a borrower-split field
   to work around it, and do not raise it as a defect.

**Correct figures for this persona under the ruling:**

| Figure | Correct |
|---|---|
| David's share of mortgage 16 | £48,000 |
| Household debt | £293,000 |
| Wealth Summary roll-up | £170,500 / £122,500 / £293,000 |

`F-0021`'s signed-off £182,500 / £122,500 / £305,000 is **wrong** and must be corrected.

---

## Working notes — DONE 2026-08-22, handed to quality-lead

Branch document: `workforce/branches/fixes/F-0022-cycle4-dashboard-module-totals-and-cache.md` §10–18.

**The ruling is implemented at the reader, not by repairing the row.** Mortgage 16
still stores `joint 50%`; the property is authoritative and every consumer resolves
through it, so the stored contradiction no longer changes any figure. New and
edited mortgages now mirror their property's ownership (W-0236), so the
contradiction stops being created.

### Measured, live local database

| Figure | Before | After | Ruling |
|---|---|---|---|
| David's share of mortgage 16 | £60,000 | **£48,000** | £48,000 |
| David's mortgages | £182,500 | **£170,500** | £170,500 |
| Sarah's mortgages | £122,500 | £122,500 | £122,500 |
| Household debt | £305,000 | **£293,000** | £293,000 |

### Prior art: four mechanisms, not two

`calculateUserMortgageAmountShare` (the bug) · `EstateController::index` ·
`PropertyService::calculateTaxPosition` · `PropertyService::calculateUserEquity`
(**zero callers** — dead code kept correct by hand). Plus a fifth, client-side, in
`LiabilityCard.vue` — see **W-0237**. All five route through one reader;
`app/Support/SecuringPropertyResolver.php` resolves *which record* the share comes
from, and `calculateUserShare` still answers *what it is*. **No new share
arithmetic was written.**

### The reach moved with the fraction

`MobileDashboardAggregator::sumMortgageShares()` / `sumMortgageJointOwnerShares()`
reached by the owner columns on the **mortgage row**. Under the ruling a user can
owe part of a mortgage whose row names someone else, so that reach cannot see it —
a correct share of an incomplete set. Both deleted in favour of
`CrossModuleAssetAggregator::calculateMortgageTotal()`, which reaches both legs.
This closes the cross-link edge (a mortgage held by a third party on the user's
property).

### Acceptance

1. ✅ The share derives from the linked property. 2. ✅ The `:110-111` docblock is
deleted — the rule lives in `propertyOwnershipFor()` now, not in prose beside the
code. 3. ✅ `EstateController` composes rather than copying. 4. ✅ One reader; the
count of implementations went **down**. 5. ✅ Verified on both accounts.

**Accepted limitation restated:** this cannot express a mortgage in one spouse's
sole name against a jointly-owned property. Do not add a borrower-split field.

### Evidence

`tests/Feature/NetWorth/MortgageShareFollowsThePropertyTest.php` — 10 passing.
Every case makes the property and the mortgage row disagree on purpose, and the
key case asserts the answer **moves** when the property share moves, with the
mortgage row untouched. That case is what exposed the trait-static trap recorded in
F-0022 §12.

- 2026-08-30 CSJ: **RULING AMENDED. "W-0228 can allow mortgage share that is not the same
  as ownership share."**

  The original ruling (2026-08-22) made the property authoritative for every mortgage
  secured on it, and it is enforced by a hard throw —
  `CalculatesOwnershipShare::refuseRecordWhoseShareFollowsAnother()` — with
  `SecuringPropertyResolver` resolving the property for every consumer. That enforcement
  now overreaches: it forbids a case CSJ has said is legitimate, a co-owner who borrowed
  alone owing alone.

  **What this does NOT settle, and must not be guessed:** whether the existing
  `mortgages.ownership_percentage` column becomes authoritative for rows already written.
  It cannot simply be trusted — the persona's Manchester mortgage carries `joint 50%`
  against a property held `tenants_in_common 40%`, and that 50% is exactly the unreviewed
  value the original ruling existed to stop being believed. Reading it as authoritative
  would move that household's liabilities from £293,000 to £305,000 and change a verified
  figure on the strength of data nobody has confirmed.

  So the implementation needs a way for a user to SAY "I borrowed alone" — an explicit
  signal — rather than a silent reinterpretation of a column. That is a schema and form
  question, and it belongs to W-0483, which this unblocks.

- 2026-08-31 build-lead: **CLOSED — verified against `dev`.**
  `CalculatesOwnershipShare::refuseRecordWhoseShareFollowsAnother()` (:157) throws a
  `FinancialCalculationException` for any `Mortgage` or record carrying `property_id`, directing
  the caller to `calculateUserMortgageShare()`, which resolves the property. It throws rather than
  falling through deliberately: a silent wrong share is the failure mode this whole family is made
  of. One home, called by both `atUserShare` and `userShareFraction` (Rule 20) — the guard lived
  in only one of them until W-0425.
  **CSJ amended this ruling on 2026-08-30** — a mortgage share MAY differ from the ownership
  share. That amendment is **W-0483**, and it is engineering on top of this, not a reason to
  reopen this item.
