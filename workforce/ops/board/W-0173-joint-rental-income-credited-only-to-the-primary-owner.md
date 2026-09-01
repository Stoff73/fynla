---
id: W-0173
title: Rental income from a jointly-owned buy-to-let reaches only the primary owner — the spouse's 50% share is credited to nobody
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0019-cycle2-ownership-applied-one-side-only.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T21:50:00Z
claimed: 2026-08-22T01:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [W-0172, W-0140, W-0154]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local, both persona accounts, after entering the
household's two buy-to-let properties through the module forms.

**Surface:** `/plans/estate` → Personal Information → Financial Overview → "Gross
Income". Reaches every income-derived figure — disposable income, affordability,
retirement projections.

### Expected

Rule 6. A jointly-owned property is **one** record with `ownership_percentage` for the
primary owner and `100 − ownership_percentage` for the joint owner. Its rental income
splits the same way. `PASS-PLAYBOOK.md` §2.3, derived from `peak_earners.md`:

| | David | Sarah |
|---|---|---|
| Employment | £145,000 | £120,000 |
| City Centre Flat, joint 50% of £1,800/month | £10,800/yr | **£10,800/yr** |
| Manchester, tenants in common 40% of £1,350/month | £6,480/yr | £0 — she is not an owner |
| **Gross income** | **£162,280** | **£130,800** |

### Actual

```
properties.19   user_id 16   joint_owner_id 17   ownership_type joint
                ownership_percentage 50.00       monthly_rental_income 1800.00
users.16 annual_employment_income 145,000
users.17 annual_employment_income 120,000
```

| | Shown on `/plans/estate` | Expected | |
|---|---|---|---|
| David — Gross Income | **£162,280** | £162,280 | **GREEN** |
| Sarah — Gross Income | **£120,000** | £130,800 | **RED — £10,800 missing** |

**David's share is calculated correctly**: £162,280 − £145,000 = £17,280, which is
exactly 50% of the City Centre Flat's £21,600 plus 40% of Manchester's £16,200. So the
ownership split *is* being applied — the primary owner receives his percentage and not
the whole.

**The other half simply stops there.** Sarah's gross income is her salary alone. Her 50%
of a property she half-owns, £10,800 a year, is credited to neither spouse and appears in
no household figure.

### Impact

**This is the same shape as W-0172 in a different module**, and finding both on one
household is the point: the ownership percentage is applied to the owner's side and the
counterparty's side is dropped. There, £60,000 of a mortgage belonged to nobody; here,
£10,800 a year of rent does.

For this household the understatement is 8.3% of Sarah's gross income, and it compounds:
her disposable income, her affordability, her retirement projection and any advice
quantified against them all inherit it. It also makes the two accounts disagree about the
household's total income, which is the disease W-0154 was filed for.

The direction matters too — understating a spouse's income is the direction that makes
the household look *less* able to act, so it will suppress recommendations rather than
inflate them.

### Repro

1. Premium married couple, linked, `SpousePermission` accepted both ways.
2. As the primary owner, add a Buy-to-Let with **Ownership Type: Joint Tenancy**, joint
   owner the spouse, and **Monthly Rental Income £1,800**.
3. `/plans/estate` as the primary owner → Financial Overview → Gross Income includes
   £10,800 of rent. **Correct.**
4. `/plans/estate` as the spouse → Gross Income is her salary only. **The £10,800 is
   absent.**
5. `php artisan tinker` confirms one property row with `ownership_percentage 50.00` and
   `joint_owner_id` set to the spouse.

### Acceptance

1. Rental income splits by `ownership_percentage` on **both** sides, from one
   calculation, the way the value and the chattels already do (Rule 20 — this must not
   become a second income aggregation).
2. Sarah's gross income reads **£130,800**; David's stays **£162,280**; the household
   total is £293,080 and is the same figure from either login.
3. Where a co-owner is a third party rather than a linked user (the Manchester property),
   their share is credited to no account — which is correct — and David's 40% is
   unaffected.
4. Verified in a browser on both accounts, and on `/m` and native, which read the same
   income figures.
5. Check the same split for property *costs* while in there: **W-0140**'s re-derivation
   shows Sarah's "Annual Expenditure" is exactly her 50% share of the property
   commitments, so the cost side does reach her. Income and costs currently disagree
   about whether she is an owner.

## Working notes

**2026-08-22 — build-lead (`cycle2-ownership`). Fixed.** Branch document:
`workforce/branches/fixes/F-0019-cycle2-ownership-applied-one-side-only.md`.

### The cause, exactly

`PropertyController::syncUserRentalIncome()` — the surviving third implementation of
the rental figure. It did two things wrong at once:

1. **It wrote only the acting user.** `$user->update(['annual_rental_income' => …])`,
   where `$user` is whoever was logged in when the property was saved. A joint
   buy-to-let recorded by David therefore wrote David's column and left Sarah's at
   `0.00` — which it had been since her account was created. Nothing else ever wrote
   it: `UserProfileService::updateIncomeOccupation()` also writes the acting user
   only, and the onboarding path writes the user whose flow it is.
2. **It carried its own ownership arithmetic** — a fake `(object)` shaped like an
   asset, passed to `calculateUserShare` to borrow the share rule, over **gross**
   rent. So even the side it did write disagreed with the income page, which uses the
   property-business profit.

`users.annual_rental_income` is a denormalised cache with roughly forty readers
across estate, retirement, protection, coordination, the plans, Fyn, `/m` and native.
A cache that N write paths must each remember to sync for BOTH sides is the defect;
patching the two known writers would have left the third and every future one.

### One home

The fact belongs to the Property record, so the sync hangs off the canonical write path
for that record:

- **`app/Listeners/Property/SyncOwnerRentalIncome.php`** (NEW) — on
  `PropertyCreated` / `PropertyUpdated` / `PropertyDeleted` / `PropertyRestored`,
  recomputes `annual_rental_income` for **every user the record reaches**: `user_id`,
  `joint_owner_id`, and (on update) whoever those were *before* the write, so a
  co-owner who has just been removed stops being credited with rent they no longer
  receive. Registered in `app/Providers/EventServiceProvider.php` against all four
  events. Every real write path — Fyn, the onboarding flow, the module forms, the
  stores — goes through `PropertyStore`, so every path arrives here (Rule 20 — all
  surfaces means all paths).

  **This was first written as an Eloquent observer and that was the wrong mechanism.**
  It put `App\Models\Property` inside `app/Observers/` and turned the property
  store-boundary suite red. The boundary was right about more than the file:
  `PropertyStore` is the canonical write path, it already emits domain events, and
  `RecalculatePropertyOutstandingMortgage` is the established shape for exactly this
  job — react to a store event, recompute a derived value elsewhere, never name the
  model. **No allowlist entry was added**; the observer was a parallel mechanism beside
  one that already worked. (The allowlist *would* have taken it — there is an explicit
  spec §14.2 observer category with `PropertyRiskObserver` in it — which is precisely
  why it was worth not reaching for.)

  **Two event signatures had to be extended, and both gaps were real:**
  `PropertyDeleted` carried only `entityId` and the acting user, so once the row was
  gone the co-owner — Sarah's side, the whole point of this item — was unreachable; it
  now carries `?int $jointOwnerId`, passed from `PropertyStore::delete()` which already
  has the record in hand. `PropertyUpdated`'s `$changes` is `getDirty()`, the NEW
  values, so a listener reacting to a change of ownership could not see who was
  *removed*; it now carries `array $previous`, the originals of the changed keys. Both
  additive with defaults, and there were **no existing listeners on any Property
  event**, so the blast radius was nil.

  **Consequence for whoever writes tests here:** a `Property::factory()->create()` does
  **not** sync — only store writes do. Every real write path goes through the store, so
  user behaviour is unchanged, but a test that arranges a property by factory and
  expects `annual_rental_income` to move will not see it.
- **The arithmetic is not repeated.** It calls
  `PropertyService::annualRentalTaxPosition()` — W-0175's one home, already
  reach-complete (`forUserByType` is joint-aware) and fraction-correct
  (`calculateTaxPosition($property, $userId)` resolves the requesting user's side).
- **`PropertyController::syncUserRentalIncome()` is deleted**, with its three call
  sites (`PropertyController.php:486` carries the note). Implementation count of the
  rental figure goes from two to one.

### The figure is the PROFIT, not the gross — a deliberate deviation from Acceptance 2

Acceptance 2 above asks for Sarah **£130,800** and David **£162,280**, which are
salary plus **gross** rent. That expectation was superseded by the correction at the
top of **W-0175**, recorded by `cycle1-tax` and confirmed by team-lead: property
income enters total income as the **profits of the property business** (ITA 2007 s23
Step 1 over ITTOIA 2005 Part 3), so the profit is the correct base and the gross
figure had no correct use. `/valuable-info` already shows the profit.

Had I written the gross here, `/plans/estate` would have been made to agree with a
figure `/valuable-info` had just been corrected away from — three figures for one
person's income would have become three again, differently. The measured result:

| | Was | Now | `/valuable-info` (W-0175, already fixed) |
|---|---|---|---|
| Sarah's rental | £0.00 | **£8,880.00** | £8,880 |
| David's rental | £17,280.00 (gross) | **£14,289.60** | £14,289.60 |
| Sarah's gross income | £120,000 | **£128,880** | £128,880 |
| David's gross income | £162,280 | **£159,289.60** | £159,289.60 |

Verified against the live persona rows before the change:
`PropertyService::annualRentalTaxPosition()` returns `14289.6` for user 16 and `8880`
for user 17. **Acceptance 2's household total of £293,080 is therefore £288,169.60**,
and it is the same from either login. Flagging rather than silently restating.

### Acceptance 3 — the third party

Property 20 (Manchester) is `tenants_in_common` 40% to David with `joint_owner_id`
NULL, because Mike Barrett has no account. `annualRentalTaxPosition` resolves the
requesting user's side of the split and no other, so David receives 40% and the
remaining 60% is credited to **no account** — it does not fall through to the spouse.
Pinned by a test, not by inspection.

### Existing rows — the migration is written and NOT applied

`database/migrations/2026_08_22_000100_sync_rental_income_to_every_owner.php`
recomputes the column for every user holding a buy-to-let. Deliberately narrow: a
user with no buy-to-let is left alone, because a figure on such an account did not
come from a property and the migration has no standing to decide where it did.

**It is Pending, not Ran.** Applying it writes to users 16 and 17, which this
dispatch explicitly forbade ("Do NOT write to users 16 or 17. Fixtures only"). The
code fix is complete and correct for every future write; the persona's *existing*
rows will still read £17,280 / £0.00 until someone with standing runs
`php artisan migrate`. **Whoever verifies this must apply it first, or W-0173 will
read as unfixed.** It was the only Pending migration at time of writing.

### Tests

`tests/Feature/Property/JointRentalIncomeReachesBothOwnersTest.php` — **9 passing,
18 assertions.** Every case drives `PropertyStore` end to end — the path a user actually
takes — rather than the factory, which would bypass both the store and the event and
prove nothing. No mock supplies a figure.

1. A joint buy-to-let credits **both** owners £8,880 — the harm, pinned.
2. The two owners' shares sum to the property's whole annual profit — nothing is lost
   between them.
3. Both columns equal `annualRentalTaxPosition()` — the one home, not a restatement.
4. **A third party's share is charged to nobody**: a tenants-in-common 40% credits the
   owner £5,409.60 and leaves the linked spouse at £0.00. The invariant.
5. Changing the rent moves both owners together.
6. Removing a co-owner returns them to £0.00 and gives the remaining owner the whole.
7. Deleting the property clears both.
8. Restoring a deleted property credits both owners again.
9. A main residence contributes nothing.

Blast radius re-run green: `RentalIncomeOneDefinitionTest`, `UserProfileServiceTest`,
`PersonalAccountsServiceTest`, `PropertyTaxServiceTest`, `ResolvesIncomeGrossTotalTest`,
`PropertyHttpIntegrationTest`, `UserProfileControllerTest`,
`PersonalAccountsControllerTest`, `RetractionTest` — **59 + 57 passing**.
`./vendor/bin/pint` on the touched paths: passed.

### Surfaces

Server-side, on a shared column. `/m` and `ios-native/` render income figures the
server computes and carry **no rental arithmetic of their own** (checked: zero matches
in `resources/mobile/` and `ios-native/`), so both inherit the fix with no client
change. Nothing on `/m` has no counterpart here.

**Not done: browser verification, by instruction.**

- 2026-08-31 build-lead: **CLOSED — verified against `dev`.**
  `app/Listeners/Property/SyncOwnerRentalIncome.php` recomputes `users.annual_rental_income` for
  **every user the record reaches**, not just the acting one, so a joint buy-to-let's rent no
  longer stops at the owner who entered it. Wired at `EventServiceProvider:86`, reacting to the
  `PropertyStore` domain event — the established shape, chosen over an Eloquent observer that
  would have put the model inside `app/Observers/`. The figure written is the rental profit at the
  user's share, from `PropertyService::annualRentalTaxPosition()`.
