# W-0368 — tax-compliance-reviewer re-gate

**Reviewer:** tax-compliance-reviewer · **Date:** 2026-08-25 · **Requested by:** build-lead (Brett)
**Branch:** `feature/icecube/w0368-undivided-share-discount` · **Commit:** `eed073645`
**Prior verdict:** `workforce/ops/handoffs/W-0368/tax-compliance-reviewer-2026-08-25.md`
**Constitution:** `05-perimeter`, `07-quality-bar`

## VERDICT: C1 DISCHARGED · C2 STILL BLOCKING · C3 DISCHARGED

C1 is fixed, correctly, in both columns, and I measured it rather than reading it.
C3 is green for the right reason. The three specific mechanical checks you asked
me to make on C2 all hold.

C2 nonetheless still blocks, because the mechanism it introduces does not survive
contact with the only surface that can populate it. **`populateForm()` never copies
`joint_owner_is_spouse` onto the form**, so the round-trip you believe you fixed is
dead code, every edit-and-save wipes the stored answer, and there is a reachable
branch that writes `false` onto a spouse's property — the discount on a spouse, the
understatement C2 exists to prevent. Two further staleness routes reach the same
outcome, both measured.

You asked me to be adversarial about a record of believing you had found every
site. On C1 you have. On C2 the pattern repeated one layer up: you fixed the read
in `applies()` and did not follow the value back to where it is written.

---

## Authority and verification

Isolated database `laravel_testing_tax368b`, created for this re-gate. Every figure
below is from a run, not from reading. All probe files removed afterwards
(`git status` clean but for the pre-existing `package-lock.json`).

Active `TaxConfiguration`: **2026/27**. `undivided_share_discount_percent` = `0.1`.
Statute relied on: **IHTA 1984 s160** (open-market value — the authority for the
discount), **s161(1)–(3)** (related property), **s8D(5)/s8E(2)** (residence band and
its residence cap), **s162** (liabilities). HMRC practice: IHTM15071, SVM113040.

| Suite | Result |
|---|---|
| `tests/Feature/Estate` + `tests/Unit/Services/Estate` + `tests/Feature/Stores` | **702 passed**, 2,269 assertions |
| `--testsuite=Architecture` | **177 passed**, 4,296 assertions, 1 skipped |
| `tests/Feature/Property`, `tests/Feature/NetWorth`, `NetWorthServiceTest`, `PropertyServiceTest`, `PropertyTaxServiceTest`, `MortgageServiceTest`, `MortgageServiceOwnershipTest`, `PersonalAccountsServiceTest`, `tests/Unit/Services/Mobile`, `tests/Unit/Services/Shared` | **266 passed**, 1,011 assertions |
| `HouseholdPlanningServiceTest`, `EstatePlanRefactorTest`, `EstateAgentGoalsTest`, `RecommendationsAggregatorServiceTest`, `PensionStoreHttpIntegrationTest`, Mortgage/Savings read-consumer parity, `ResolvesIncomeGrossTotalTest` | **132 passed**, 463 assertions |

702 matches your figure exactly.

---

# C1 — DISCHARGED

Re-ran the probe: single user, one child (`FamilyMember` `relationship = 'child'`),
main residence £360,000 held 50% tenants in common with an unlinked co-owner, plus
£500,000 cash. Three runs, one per value of `joint_owner_is_spouse`.

| `joint_owner_is_spouse` | net estate | RNRB available | cap reduction | allowances | **liability** |
|---|---|---|---|---|---|
| `NULL` | 680,000 | 175,000 | 0 | 500,000 | 72,000 |
| `false` (discount applies) | **662,000** | **162,000** | **13,000** | **487,000** | **70,000** |
| `true` | 680,000 | 175,000 | 0 | 500,000 | 72,000 |

**The £5,200 is gone.** The discounted row now reports £70,000 — the figure I
derived by hand in the first gate as the consistent answer. The s8E(2) cap is
measured against £162,000, the same value the estate is taxed on. One property, one
valuation.

The projected column is fixed too, and I checked it separately because "both sites"
claims are what failed last time. At £360,000 the projected residence grows past the
band so the cap does not bite in either state, which proves nothing — so I re-ran at
£120,000 to force it:

| | projected property total | projected RNRB | projected cap reduction |
|---|---|---|---|
| `NULL` | 145,635.75 | 145,635.75 | 29,364.25 |
| `false` | **131,072.17** | **131,072.17** | **43,927.83** |

The projected RNRB equals the projected property total in both states — the cap and
the estate agree. And 131,072.17 / 145,635.75 = 0.9 exactly, the configured rate.

`sumMainResidenceNetShare` (`app/Services/Estate/IHTCalculationService.php:2260`)
and `projectMainResidenceNetValue` (`:1266`) both read
`UndividedShareDiscount::shareValue()`. The mortgage leg stays at face value, which
is right (s162). The `:2240` docblock now states what the method does.

## The sweep for a fifth site

I checked every service that derives an Inheritance Tax figure — the twenty-four
files that read `getInheritanceTax()` — and traced each one's property valuation.

**Clear.** `ComprehensiveEstatePlanService`, `app/Services/Plans/EstatePlanService.php`,
`EstateAgent`, `IHTController`, `GiftingController`, `PersonalizedGiftingStrategyService`,
`PersonalizedTrustStrategyService` all value property through
`EstateAssetAggregatorService::gatherUserAssets()`, which reads the one home at
`:91`. They inherit the discount and cannot drift from it. `MobileDashboardAggregator`
and `DailyInsightService` read `iht_liability` off the estate summary, so `/m` and
native inherit it too. The Letter services carry no Inheritance Tax figure —
`LetterToSpouseService:327` reads `calculatePropertyTotal()`, which is a net-worth
figure and correctly undiscounted. `RecommendationPersonaliser:234` is asset-
allocation context, not tax. The Capital Gains path is untouched.

**Found — the fifth site.** `EstateActionDefinitionService::estimateEstateValue()`
(`app/Services/Estate/EstateActionDefinitionService.php:340`, property sum at
`:348-350`) feeds `evaluateIhtExceedsNrb()` (`:156-187`), which computes and
**publishes a pound-figure Inheritance Tax liability** to the user as
`iht_liability` and `estimated_impact`. Measured on the canonical row — £295,000
held 40% with a stated third party:

```
EstateActionDefinitionService::estimateEstateValue   295,000.00
arithmetic share (40%)                               118,000.00
Inheritance Tax value of the share                   106,200.00
```

It reads `->sum('current_value')` — **not the ownership share at all**, let alone the
discount. It counts a 40% interest at 100% of the whole property. That is a 2.78×
overstatement of that asset in a figure the user is shown as their Inheritance Tax
exposure.

Direction is overstatement, so by the standard I applied to C5 this is **not
blocking**. But state it plainly: the missing ownership share pre-dates W-0368 and
is the larger error; W-0368 widens the gap from 2.50× to 2.78×. File it with C5 —
`app/Services/Coordination/HouseholdPlanningService.php:482`, still undiscounted —
as the completion of the same sweep. Those two are now the whole remainder.

---

# C2 — STILL BLOCKING

## What you asked me to confirm — all three hold

**NULL cannot be coerced to false anywhere in the chain.** I did not read this, I
drove it through the HTTP boundary with seven input shapes and read the raw column
back out of the database:

| sent | status | raw DB column | resource output |
|---|---|---|---|
| key omitted | 201 | `NULL` | `null` |
| `null` | 201 | `NULL` | `null` |
| `""` | 201 | `NULL` | `null` |
| `false` | 201 | `0` | `false` |
| `0` | 201 | `0` | `false` |
| `"false"` | **422** rejected | — | — |
| `true` | 201 | `1` | `true` |

`ConvertEmptyStringsToNull` turns `""` into `null` before validation, so the empty
string lands as NULL and not as false — the coercion you were right to worry about
does not happen. Laravel's `boolean` cast is in `$primitiveCastTypes`, so
`castAttribute` short-circuits on null and NULL survives the model. A `PUT` that
says nothing about the field leaves the stored value alone. `PropertyResource:51`
publishes the key on both `show` and `index`, unwrapped, with no `?? false`.
`PropertyNormaliser::fromForm()` is pass-through for unrecognised keys, so nothing
is dropped between the request and the store.

**The linked-spouse path still excludes**, and the stored flag cannot override it:

| linked co-owner | flag | owner side | spouse side |
|---|---|---|---|
| spouse | `NULL` | no discount | no discount |
| spouse | `false` | no discount | no discount |
| spouse | `true` | no discount | no discount |
| non-spouse | `true` | discount ✓ | — |

That last row matters: `handleJointOwnerSelection()` writes `true` for **any**
`linked_*` pick, including a linked non-spouse, so the column can hold a factually
false `true`. It is harmless today because `applies()` never reads it on the linked
branch. Note it as a latent trap for the next consumer of this column, not as a
current defect.

**NULL takes no discount.** Confirmed at the class and in the full calculation
(the C1 table above: `NULL` reports the undiscounted 680,000 / 72,000).

## Why it still blocks — three routes to the discount landing on a spouse

### (a) The stored answer never reaches the edit form, and every save wipes it

`populateForm()` (`resources/js/components/NetWorth/Property/PropertyForm.vue:1513`)
assigns fourteen top-level fields, the address block, the valuation block, the
monthly costs, the rental block, the managing-agent block and the mortgage block
from `this.property`. **`joint_owner_is_spouse` is not among them.** There is no
`Object.assign`, no spread of `this.property`, nowhere else in the file that writes
it from the property. The only writes are `:1717`, `:1722`, `:1725` — all inside
`handleJointOwnerSelection()`, which fires only on the select's `@change`.

So at `:1542`:

```js
this.jointOwnerSelection = this.form.joint_owner_is_spouse === true && this.spouse
  ? 'spouse_name'
  : 'other';
```

`this.form.joint_owner_is_spouse` is the `data()` default `null`, always, on every
edit. `=== true` can never be satisfied. **The read is dead code, and the defect you
describe in the commit message as fixed — "populateForm() reconstructed any named
co-owner as Other" — is still present.** A spouse recorded by name still comes back
selected as "Other (Enter Name)".

It is worse than not fixing it. `handleSubmit()` sends the whole form object:

```js
const cleanedProperty = { ...this.form };   // :1864
```

`joint_owner_is_spouse: null` therefore rides on **every property edit**, and
`UpdatePropertyRequest`'s `sometimes|nullable|boolean` accepts it. A stored `true`
and a stored `false` are both **overwritten with NULL by a no-op edit**. The answer
self-erases. That direction is safe (NULL takes no discount, tax overstated), but it
means the column cannot be relied on to hold what the user said, which is the entire
premise of the C2 fix.

And there is an unsafe branch. Open a property whose co-owner is a spouse recorded
by name (`joint_owner_is_spouse = true`). It renders as "Other (Enter Name)" with
the spouse's name in the free-text box. Touch that select and land on "Other" —
`handleJointOwnerSelection()` writes **`false`**. The next estate calculation
discounts a share held with a spouse. That is the s161 breach, reached through the
form, on the primary surface, by a user who was trying to be accurate.

Minimum fix is one line in `populateForm()`, and it must use `??` not `||` — `||`
would map a stored `false` to `null` and quietly disable the feature:

```js
this.form.joint_owner_is_spouse = this.property.joint_owner_is_spouse ?? null;
```

That also makes `:1542` live, at which point the spouse-by-name round trip works as
the commit message says it does.

### (b) The answer goes stale when the co-owner changes, and nothing invalidates it

Measured. Start from a property correctly recorded as co-owned with a third party
(`joint_owner_is_spouse = false`, £360,000 at 50%). Change the co-owner through any
write path that does not carry the column:

```
after_co_owner_change
  joint_owner_name        "Jane (my wife)"
  joint_owner_is_spouse   false          <- stale, nothing cleared it
  applies                 true
  shareValue              162,000.00     <- against an undiscounted 180,000
```

**£18,000 of a spouse's undivided share discounted away.** s161 says that share is
valued as the appropriate portion of the aggregate; the app values it at 90% of the
arithmetic fraction.

The paths that reach this are not exotic. `PropertyNormaliser::fromToolParams()`
whitelists the keys Fyn may write and `joint_owner_is_spouse` is not in the list, so
**every Fyn write leaves the old answer standing** — and Fyn is the only write path
on `/m` and on native. On the web form, editing the free-text co-owner name without
touching the select does the same thing, because `handleJointOwnerSelection()` only
fires on the select.

And the slowest version needs no write at all: a user marries. "Ruth Alderton" was
truthfully `false` when they answered. Nothing re-asks. The answer is now wrong and
stays wrong.

`ownership_type` switched to `individual` also leaves both `joint_owner_name` and
`joint_owner_is_spouse` in place (verified: `raw = 1`, name `"Ruth Alderton"`, on an
individually-held property). No tax effect while it is individual, because
`SharedOwnership::isShared()` gates first — but it is a live value waiting to be
resurrected if the property is made shared again.

The fix belongs in `PropertyStore::update()`, the one home every write path goes
through: when `joint_owner_id` or `joint_owner_name` changes and the update does not
itself supply a new `joint_owner_is_spouse`, set it to NULL. Re-ask rather than
carry forward. That degrades to the safe direction and is the Rule 20 shape — one
place, all surfaces, rather than teaching four writers about the column.

### (c) A deleted spouse account turns the discount on

Also measured, and this one is my miss from the first gate — I cleared the linked
path on the strength of `SpouseLinkingService` refusing a half link, and did not
test what happens when the link is broken by deletion rather than by never forming.

```
deleted_spouse_account
  liveSpouseId   null
  applies        true
  shareValue     162,000.00     <- against 180,000
```

`applies()` compares `coOwnerId !== $user->liveSpouseId()`. `liveSpouse()` returns
null once `deleted_at` is set, while `properties.joint_owner_id` still points at
that person. The comparison therefore succeeds and the discount is applied to a
share held with the user's spouse.

This is the same disease as the original C2: a **relationship** question answered
from an **account-system** fact. And it fires precisely when an estate calculation
matters most. The app already knows about this state — `PropertyResource:52`
publishes `joint_owner_deactivated` — so the signal is to hand.

## Summary of C2

The three checks you asked for pass. The mechanism around them does not hold the
answer: it cannot read it back (a), does not invalidate it (b), and can be bypassed
by an account deletion (c). All three end at the same place — a discount applied to
a spouse — which is the understatement the condition exists to prevent, and the
direction this work item's own reasoning treats as the one that matters.

---

# C3 — DISCHARGED, and for the right reason

`tests/Feature/Stores/PropertyReadConsumerParityTest.php` is green; the whole
`Estate` + `Stores` scope is 702 passed, 2,269 assertions.

I checked the reason rather than the result. The third-party property at `:178-184`
sets no `joint_owner_is_spouse`, and `database/factories/PropertyFactory.php` has no
default for it, so the row is NULL — never asked — and takes no discount. The
618,000 : 500,000 ratio the comment at `:186-188` describes is the arithmetically
correct answer for that fixture. It is not passing because the discount reaches
nothing in general; it is passing because it reaches nothing **on that row**, which
is the semantics the fixture states.

It remains a live sentinel in the direction that matters: if NULL ever starts taking
a discount the ratio moves to 606,200 : 500,000 and the test reddens. It does not
pin the W-0368 separation itself — there is no discounted case in that file — but
that pinning is now in `IHTProjectionOwnershipTest:301`, where
`joint_owner_is_spouse => false` was added with a comment saying why. That is the
right division.

---

# Citations — you under-corrected in two places, not over

The corrections themselves are right and not overreaching. s160 as the authority
with IHTM15071/SVM113040 as guidance on it is correct. "s161 substitutes a valuation
basis, valuing related property as a proportion of the combined whole" is a fair
statement of s161(1) with s161(3). Removing the connected-company limb is correct —
it never existed. The seeder comment at `database/seeders/TaxConfigurationSeeder.php:353-358`
states the scope correctly, including the charity and qualifying-bodies limb.

Two sites still carry the old wording, so "throughout" is not throughout:

1. **`app/Services/Estate/EstateAssetAggregatorService.php:87`** —
   *"IHTA 1984 s161 **denies** it between spouses"*, citing only IHTM15071 and
   SVM113040, **with no s160**. This is the one place in the application that
   actually applies the discount. It is the exact wording C6.1 and C6.2 asked you to
   correct, in the most load-bearing comment of the set.

2. **`tests/Feature/Estate/UndividedShareDiscountTest.php:142`** —
   `describe('s161 denies the discount between spouses')`, three lines of code below
   its own file docblock at `:17` which says s161 *substitutes*. One file, two
   statements of the law.

One genuine overreach, minor: *"the discount turns entirely on whether the co-owner
is a spouse"* (`UndividedShareDiscount.php:26-28`). s161(2)(b)'s charity limb also
removes it, and s161(1) bites only where the related-property basis produces the
higher figure — it is a floor, not an unconditional substitution. Neither point
moves a number and neither needs handling in code; the sentence just claims more
than the section does. Nothing else is overstated.

---

# Carried forward, unchanged

**C4 — open.** `undiscounted_share` and `undivided_share_discount`
(`EstateAssetAggregatorService.php:92-93`) are still computed and still read by
nothing — `grep` across `app/`, `resources/` and `ios-native/` returns only the two
write sites. The user still sees "(Tenancy in Common - 40%) £106,200" against a
property they know is worth £295,000, with nothing accounting for the difference, on
web, `/m` and iOS alike. Consumer Duty explainability, not a calculation error.

Sharper now than it was: `PersonalizedGiftingStrategyService:328` builds a
downsizing recommendation reading *"Sell £X home, buy £Y property"* where X is the
gathered `current_value` — i.e. the **discounted share**. It now quotes a sale price
that is neither the property's value nor the user's share of it. That text is new
collateral from W-0368 and belongs with C4.

**C5 — open**, and now joined by the fifth site above.

**C7 nits — open.** `config(['dummy' => null]);` still at `UndividedShareDiscountTest:74`.
`rate()` still has no bounds check. The `db:seed --class=TaxConfigurationSeeder --force`
deploy note is still needed, and matters more now: the feature is already close to
dormant, and a missing config row makes it silently and completely inert.

---

# The two judgement calls you asked for

## 1. Is shipping a correct-but-dormant valuation rule the right outcome?

**Yes, and "unknown takes no discount" is the right call — refusing a discount the
user may be entitled to is not a misstatement.** Take that head-on, because it is a
fair question.

A discount not taken overstates the estate. The user is told their Inheritance Tax
exposure is larger than it may prove to be. That is a conservative error, and it is
the error the application was already making before this branch existed. A discount
wrongly taken understates a tax liability — it tells a user their estate owes less
than the statute says, and they plan around it. Those are not symmetrical, and the
asymmetry is not merely prudential: s160 asks what the share would fetch on the open
market, and where the co-owner's identity is unknown you cannot answer that
question, because whether s161 substitutes a different basis turns on it. Declining
to value on facts you do not have is correct valuation practice, not caution.

There is a real cost and it should be named rather than waved past: 10%/15% are
Valuation Office **practice** figures, negotiable on the facts, and in a genuine
estate the range runs from nil to above 15%. So the "entitlement" being withheld was
never a settled number. Withholding a negotiable practice discount pending a fact is
a much smaller misstatement than asserting one against a spouse.

**But the backfill is the wrong question, and it does not need answering first.**
You established there is no reliable signal — I re-confirmed both heuristics fail on
your three rows. The question that does need answering is *prompting*, not
backfilling. As it stands the rule fires only for users who happen to open a
property form and re-pick a dropdown. Two otherwise identical households will get
materially different Inheritance Tax figures based on nothing but whether one of
them edited a property recently. That is a Consumer Duty consistency problem rather
than a coverage one, and the fix is to **ask** — a targeted prompt on co-owned
properties where the answer is NULL, on all three surfaces — not to guess and not to
wait.

Which brings the two halves together: the answer has to reach `/m` and native too.
`PropertyNormaliser::fromToolParams()` does not carry the column, so Fyn cannot
record it, so on the only write path `/m` and native have, this feature can never
apply. Web-only is not "done" under Rule 19. It overstates, so it does not block on
my axis — but ship it as a web-only field and the dormancy becomes permanent for two
of three clients.

## 2. Was fixing `populateForm()` in scope, and is there a data-integrity consequence?

**In scope — necessarily so.** You cannot ship a stored answer that decides a tax
valuation without the form that captures it being able to read it back. A field that
does not round-trip is not a stored answer, it is a coin flip with an audit trail.
This was not scope creep; it was the other half of C2. Do not treat it as a bonus.

**But it is not fixed** — see (a) above. You fixed the branch and left the value
unpopulated, so the fix cannot fire. Worth sitting with, because it is the same
shape as the C1 miss one layer up: you traced the value forwards from where it is
consumed and did not trace it back to where it is produced.

**Data-integrity consequence for rows already saved that way: none.** This is the
part I think you may be bracing for unnecessarily. Before this branch the column did
not exist, so the old `populateForm()` defect destroyed no stored answer — it
corrupted `jointOwnerSelection`, which is component state and was never persisted.
Every pre-branch row is NULL, and NULL is exactly what it would have been anyway.
There is nothing to reconstruct and nothing to repair.

The damage is entirely **prospective**. It begins the moment the column ships, and
under the current code it begins immediately and universally — every property edit
writes NULL over whatever the user said. That is precisely why it has to be fixed
before merge rather than after: merge it as it stands and you create the corrupted
rows you are asking me whether you already have.

---

## Does this merge?

**No — C2 remains open.** Fix the `populateForm()` population (one line, `??` not
`||`), null the answer in `PropertyStore::update()` when the co-owner identity
changes, and close the deleted-spouse-account branch in `applies()`; then re-run
`tests/Feature/Estate` + `tests/Unit/Services/Estate` + `tests/Feature/Stores` and
Architecture, and it comes back to this gate. C1 and C3 are discharged and I will
not re-open them.
