---
id: W-0172
title: A tenants-in-common property saves its mortgage as joint 50% — the owner is charged 50% of a debt they hold 40% of, and the other 50% belongs to nobody
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0013-batch-f-ownership-boundary.md
owner: build-lead
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T20:45:00Z
claimed: 2026-08-21T21:05:00Z
claimed_by: fix-batch-F
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21
prior_art_found: [W-0154, W-0134]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local `localhost:8000`, entering the persona's
third property through the **Add Property** wizard as **David Jones `users.id 16`**.

**Surface:** `/net-worth/property` → Add Property → the mortgage step. Reaches the
estate, net worth and liability figures on every surface, because they all read the
mortgage's ownership share.

This is the persona's only third-party co-ownership and the record the whole ownership
question turns on, so it is worth stating plainly: **the property saved perfectly; the
mortgage attached to it did not.**

### Expected

Rule 6 and the canonical enums. A property held **tenants in common** with a
**non-spouse** co-owner, at a stated **40%** share, must have its mortgage recorded on
the same basis:

```
properties   ownership_type tenants_in_common   ownership_percentage 40
             joint_owner_id NULL   joint_owner_name 'Mike Barrett'
mortgages    ownership_type tenants_in_common   ownership_percentage 40
             joint_owner_id NULL   joint_owner_name 'Mike Barrett'
```

David's liability share of the £120,000 NatWest mortgage is **£48,000**. Mike Barrett's
£72,000 is not the household's and must appear nowhere in it.

### Actual

The property is right. The mortgage is not:

```
properties.20   buy_to_let   tenants_in_common   40.00   joint_owner_id NULL
                joint_owner_name 'Mike Barrett'          current_value 295,000     ✓

mortgages.16    property_id 20                           outstanding_balance 120,000
                ownership_type 'joint'   ownership_percentage 50.00                ✗
                joint_owner_id NULL      joint_owner_name 'Mike Barrett'
```

The co-owner's **name** carried across correctly, so the wizard knew this was a
third party — it simply did not carry the ownership **shape**. `ownership_type` was
written as `joint` where the property says `tenants_in_common`, and the share as **50%**
where the property says **40%**.

**It is visible on screen.** `/estate/inheritance-tax` as David, expanded
(`88-web-david-iht-full-household.png`):

```
David's Liabilities                                        -£182,500
  15 Chestnut Lane repayment (Joint)                        -£32,500
  Flat 42, Riverside Apartments interest_only (Joint)       -£90,000
  Unit 12, Victoria Mill, Ancoats repayment (Joint)         -£60,000   ← should be £48,000
Sarah's Liabilities                                        -£122,500
  (two mortgages only — correctly not on the Manchester loan)
Less: Total Liabilities                                    -£305,000   ← should be £293,000
```

Note the second-order effect, which is the part that makes this more than a rounding
issue: the row is labelled **"(Joint)"** and charged at 50%, but Sarah correctly does
**not** hold it. So the app simultaneously believes the mortgage is a two-person joint
debt and shows only one person holding it. **£60,000 of the £120,000 belongs to nobody**
— it is neither David's, nor Sarah's, nor attributed to Mike Barrett.

### Impact

- **David's liabilities are overstated by £12,000**, so the household net estate reads
  **£1,716,780** where the persona's arithmetic gives **£1,728,780**, and the Inheritance
  Tax is understated by £4,800.
- The error is in the direction that flatters the user's tax position while worsening
  their apparent net worth, and it will do so on every surface that reads the mortgage —
  estate, net worth, liabilities, affordability.
- It defeats the one record in this persona that exists to test third-party
  co-ownership. The persona was written so that **£177,000 of the Manchester property
  must never appear** in the household's figures; the value side honours that exactly
  (£118,000 to David, £0 to Sarah) and the debt side does not.
- A 50/50 assumption is unsafe generally: tenants in common exists precisely because the
  shares are unequal.

### Repro

1. `/net-worth/property` → **Add Property**, as a premium married user.
2. Step 1: Property Type **Buy to Let**; Ownership Type **Tenants in Common**; Your
   Ownership Share **40**; Co-Owner **Other (Enter Name)** → `Mike Barrett`; tick
   **This property has a mortgage**.
3. Step 2: any lender, Outstanding Balance **120000**, Borrower(s) **Joint borrowers**,
   Joint borrower **Other (Enter Name)** → `Mike Barrett`.
4. Steps 3–4: costs and Buy-to-Let details; **Save Property** → `POST /api/properties → 201`.
5. Inspect the rows:
   ```
   php artisan tinker --execute='echo json_encode(DB::table("mortgages")->latest("id")->first(["ownership_type","ownership_percentage"]));'
   → {"ownership_type":"joint","ownership_percentage":"50.00"}
   ```
6. `/estate/inheritance-tax` → **Expand All** → the mortgage appears under the owner's
   liabilities at £60,000, labelled "(Joint)", and on no one else's.

### Acceptance

1. A mortgage on a `tenants_in_common` property inherits that `ownership_type` and the
   property's `ownership_percentage` — one source for the share, not a second default
   (Rule 20).
2. Where the co-owner is not a linked user, `joint_owner_id` stays NULL and
   `joint_owner_name` carries the name on the mortgage as it already does on the
   property.
3. No share of a liability is unattributed: the owner's percentage is charged to them
   and the remainder is explicitly the third party's, never silently absent.
4. Existing rows are checked — `mortgages` where the parent property is
   `tenants_in_common` and the mortgage is not — and corrected or reported. Do not
   rewrite user data without CSJ's decision.
5. Verified in a browser: entering the persona's Manchester property produces
   **£48,000** against David and household liabilities of **£293,000**, and the household
   net estate reads **£1,728,780**.
6. Re-verify on `/m` and native, which read the same share.

### Notes

- The **property** side is correct and should not be touched: `tenants_in_common`, 40%,
  `joint_owner_id` NULL, `joint_owner_name` "Mike Barrett", value share £118,000 to
  David and £0 to Sarah. This item is only about the mortgage.
- Unconfirmed and **not raised** as part of this item: `mortgages.16.rate_fix_end_date`
  is NULL although **2026-09-15** was typed into the "Rate Fix End Date" field during
  entry. A re-render on the same step may have cleared it before save, which would make
  it tester error rather than a defect, and it has not been re-tested cleanly. Recorded
  here so it is not lost; it needs its own clean reproduction before anyone acts on it.


## Working notes

### 2026-08-21 — fix-batch-F — FIXED (the share), and one thing I nearly got wrong

**Root cause, two layers.**

`MortgageService::createFromPropertyData()` built the mortgage's share with
`SharedOwnership::primaryOwnerPercentage($type, $validated['mortgage_ownership_percentage'] ?? null)`.
The wizard's mortgage step has a Borrower(s) selector but **no share input**, so
that key is never sent — the share resolved to the shared default of **50**, next
to a property that says 40. Two sources for one fact, which is the Rule 20 shape.

`normalizeMortgageOwnershipType()` separately folds `tenants_in_common` into
`joint`, which is why the type disagreed too.

**The fix — acceptance 1's "one source for the share".** The share now comes from
the parent property when the mortgage states none:

```php
$mortgageData = SharedOwnership::applyTo(
    $mortgageData + ['ownership_percentage' => $validated['mortgage_ownership_percentage'] ?? null],
    $mortgageOwnershipType,
    $property,
);
```

Same supplied-beats-inherited rule as W-0040, one level out: a stated mortgage share
still wins (two people can own 40/60 and borrow on a different split), and where
none is stated the property — which *is* the arrangement — supplies it. Nothing is
invented beside it.

**What I nearly shipped, and why I did not.** I first removed the TIC → joint
coercion as well, on the strength of finding
`2026_01_17_100145_add_tenants_in_common_to_mortgages_ownership_type` — a migration
that widened the enum to include TIC, making the code comment ("mortgages only
support individual and joint") look plainly stale. **It is stale about the column
and correct about the application.** `MortgageStore::validateCanonical:304` is
`'in:individual,joint'`, and at least seven consumers decide shared-ness by testing
`ownership_type === 'joint'` exactly (`UserProfileService:931`,
`PropertyCard.vue:153`, `PropertyDetailInline.vue:382,388,814`,
`PropertyFinancials.vue:443`, `LetterToSpouse.vue:482`). A TIC mortgage would read
as **individual** to all of them and charge **100%** of the debt — worse than the
defect being fixed, on more surfaces.

So the coercion stays and only its justification changes; the comment now says what
is actually true. The contradiction between column and code is filed as **W-0162**.
**The share is no longer flattened along with the type, which is what the defect
actually was.**

### Acceptance, item by item

1. **"inherits that `ownership_type` and the property's `ownership_percentage`" —
   the share: DONE. The type: NOT done, deliberately, and this needs a decision.**
   The wizard's Borrower(s) control offers only "Just me" / "Joint borrowers", so it
   **states** `joint`. Honouring the type clause literally means overriding a value
   the caller stated — the exact thing W-0040 established must never happen — and it
   would also break `MortgageServiceOwnershipTest::test_joint_borrowers_are_saved_separately_from_the_property_ownership_split`,
   which pins a deliberate prior decision that mortgage liability is configured
   independently of property ownership. **Every figure in this item comes right
   without it.** If the label matters, the honest fix is to let the Borrower(s)
   control express tenants in common so the user states the right thing — a UI change
   beyond this item, and gated on W-0162.
2. **DONE.** `joint_owner_id` stays NULL and `joint_owner_name` carries "Mike
   Barrett" onto the mortgage, unchanged from before.
3. **DONE.** 40% is charged to the owner and the remaining 60% is attributable to the
   named third party rather than silently absent.
4. **Existing rows: reported, not rewritten.** See below.
5. **£48,000 proven at the endpoint, not in a browser.** I do not browser-verify my
   own work; a persona-tester closes Rule 14's loop. The HTTP evidence is
   `tests/Feature/NetWorth/MortgageInheritsPropertyShareTest.php`, which posts the
   persona's exact Manchester payload to `POST /api/properties` and asserts the
   stored share is 40 and that 40% of £120,000 is £48,000.
6. **`/m` and native need no change and were not changed.** The share is stored
   server-side and every surface reads the same column; there is no property-entry
   wizard on either.

### Existing rows

**Checked, and there is nothing to correct in this database.** A mortgage whose
parent property is `tenants_in_common` while the mortgage is not: the only such row
is `mortgages.16` on `properties.20` — **the persona row this item was raised from**,
created by the tester through the wizard today.

Repairing it is the same class of decision as W-0043 and W-0161: it changes a user's
net worth, so it is CSJ's call and not a silent migration. **Sweep all three
together** — one query, one decision, one migration reporting rows and before/after
values per the W-0030 standard. Re-entering the property through the fixed wizard
would also do it, and for a persona row that may be simpler than a migration.

### Tests — 12 new, all green under `DB_DATABASE=laravel_testing_b`

- `tests/Unit/Services/MortgageServiceOwnershipTest.php` — 7 pass. Three new: the
  property's share is inherited when none is stated; a stated mortgage share still
  wins; a TIC mortgage is stored as joint **but keeps the property's share**. The
  four pre-existing cases, including the "configured independently" one, still pass
  untouched.
- `tests/Feature/NetWorth/MortgageInheritsPropertyShareTest.php` — 5 pass, through
  the real endpoint.

### The unconfirmed `rate_fix_end_date` note

I was in `MortgageService` and did not go hunting, per instruction. For the record:
`'rate_fix_end_date' => $validated['mortgage_rate_fix_end_date'] ?? null` **is**
present and correct in the payload (it was added by W-0012, which fixed exactly the
"silently discarded" version of this). So if the value was NULL after save, the loss
is upstream of the service — in the form or the request — not here. **That is a
narrowing, not a diagnosis, and it still needs its own clean reproduction.**
