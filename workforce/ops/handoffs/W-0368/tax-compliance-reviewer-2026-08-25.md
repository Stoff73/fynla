# W-0368 — tax-compliance-reviewer statutory gate

**Reviewer:** tax-compliance-reviewer · **Date:** 2026-08-25 · **Requested by:** build-lead (Brett)
**Branch:** `feature/icecube/w0368-undivided-share-discount` · **Commit:** `0f4273c6e`
**Constitution:** `05-perimeter`, `07-quality-bar`

## VERDICT: CLEARED WITH CONDITIONS — three of them BLOCK

The approach is right. The statutory shape of the rule is right, the s161 exclusion
is the correct exclusion to draw, the placement in the Inheritance Tax path and out
of `calculateUserShare()` is correct, and acceptance 3 is genuinely met between the
two columns it names.

It does not merge, because the change introduces **two new understatements of
Inheritance Tax** and leaves **one red test** outside the scopes the evidence pack
ran. Every blocking condition is in the same direction — tax too low — which is the
direction this work item's own reasoning treats as the one that matters.

The hole you flagged (point 4) is real. It is also **live in the data you measured
against**, and it is **not undetectable**: the app carries three signals for it and
the class consults none of them. And it is not the biggest one — C1 is.

---

## Authority used

Read the live configuration, not my standing reference table.

| | |
|---|---|
| Active `TaxConfiguration` row | **2026/27** |
| `inheritance_tax.undivided_share_discount_percent` | `0.1` — present in the active row |
| `nil_rate_band` / `residence_nil_rate_band` / `standard_rate` | `325000` / `175000` / `0.4` |

Statute and practice relied on below: **IHTA 1984 s160** (market value — the
authority for the discount), **s161(1)–(3)** (related property), **s8D(5)/s8E(2)**
(residence nil rate band and its residence cap), **s162/s162B** (liabilities);
HMRC practice at **IHTM15071** and **SVM113040**.

## Verification I ran myself

Isolated database `laravel_testing_tax368`, created for this review. No code changed.

| Suite | Result |
|---|---|
| `tests/Feature/Estate` + `tests/Unit/Services/Estate` | **504 passed**, 1,659 assertions |
| `--testsuite=Architecture` | **177 passed**, 4,296 assertions, 1 skipped |
| Pint on the four changed app/database files | passed |
| Adjacent suites that read the same engines (`HouseholdPlanningServiceTest`, `EstatePlanRefactorTest`, `EstateAgentGoalsTest`, Mortgage/Savings parity, `ConfiguredRulesHaveConsumersTest`, two Protection reach suites) | **134 passed**, 472 assertions |
| `tests/Feature/Stores/PropertyReadConsumerParityTest` | **1 failed**, 6 passed — see C3 |

504 matches your figure; the assertion count is 1,659 against your 1,655, which I do
not consider meaningful.

---

# Blocking conditions

## C1 — BLOCKS. The Residence Nil Rate Band cap still values the same house undiscounted, and it hands the user allowance the estate does not contain

You said acceptance 3 was the real work: one property must not be valued two ways
inside one Inheritance Tax calculation. There is a **third** valuation site, and you
did not change it.

`app/Services/Estate/IHTCalculationService.php:2246` (`sumMainResidenceNetShare`,
the current column) and `:1260` (`projectMainResidenceNetValue`, the projected one)
both value the main residence with the raw `calculateUserShare()`. Both feed
`$residenceNetValue` into `calculateRNRB()`, where **`:1829`** reads:

```php
$residenceCapReduction = max(0.0, $taperedRNRB - $residenceNetValue);
```

That is the s8E(2) cap. It is now measured against a residence value **10% higher
than the value of the same residence inside the estate being taxed**. Where the cap
would otherwise bite, the user is granted residence nil rate band up to the whole
discount, and the tax falls.

**Measured, not asserted.** Single user, one child, main residence £360,000 held
50% tenants in common with an unlinked co-owner, plus £500,000 cash:

```
total_net_estate               662,000.00   <- residence in at 162,000 (discounted)
rnrb_available                 175,000.00
rnrb_residence_cap_reduction         0.00   <- cap measured against 180,000 (undiscounted)
total_allowances               500,000.00
iht_liability                   64,800.00
```

Valued consistently the cap bites at £162,000: allowances £487,000, taxable
£175,000, **liability £70,000**. The branch reports **£64,800** — **£5,200 of
Inheritance Tax understated** on a £662,000 estate, and it scales with the discount.

The statutory point is not arguable. s8E(2) limits the residential enhancement to
the value transferred attributable to the qualifying residential interest. That
value is its s160 market value — the same discounted figure the estate carries. You
cannot tax the share at £162,000 and cap the allowance at £180,000.

Note also that `sumMainResidenceNetShare`'s own docblock (`:2236-2239`) says it
"uses the shared CalculatesOwnershipShare logic so the figure matches the property
and mortgage values that feed total_net_estate". **That sentence is now false**, and
it is exactly the kind of load-bearing comment that let the last mismatch through
(the trait's own `calculateUserMortgageAmountShare` docblock, per W-0228).

**Fix:** both sites read `UndividedShareDiscount::shareValue()` for the value leg;
the mortgage leg stays at face value (s162 — a liability is deducted in full).
Both methods are private and used only by the residence band, so nothing else moves.

## C2 — BLOCKS. An unlinked co-owner is assumed not to be a spouse, and the app holds three signals saying otherwise

`UndividedShareDiscount::applies()` at `app/Services/Estate/UndividedShareDiscount.php:75-79`
returns `true` whenever no `joint_owner_id` is set. Your working note calls this
"an unlinked co-owner is by definition not a spouse". It is not, and the branch
proves it against its own evidence.

**Live in the data your evidence table cites.** Of the co-owned properties in the
seeded database, the two that take a discount from the unlinked branch are:

| Property | Owner | `marital_status` | `joint_owner_name` | Discounted? |
|---|---|---|---|---|
| 74 Unit 12 (40%, £295,000) | David (104) | married, linked to 105 | **`Mike Jones`** | yes — correct, this is the W-0368 target row |
| 70 19 Worth Court (50%, £180,000) | Chris (101) | single | **`wife`** | yes — **wrong; s161 denies it** |

Worth Court is the row your commit message cites as the feature working
("£90,000 to £81,000"). Its co-owner is recorded, in the database, as **`wife`**.
The class never reads `joint_owner_name` — the only column that identifies an
unlinked co-owner — so it discounted a share the statute values as related property.

**And this is not an exotic state; it is a state the app deliberately creates.**
`SpouseLinkingService` writes **no `spouse_id` on either side** until the invitee
accepts (`:246-258`, `:421-433`, W-0347/W-0350 — "half a link is what every gate in
the application mistakes for a whole one"), while writing the caller's own
`FamilyMember` row with `relationship = 'spouse'` and `linked_user_id = null`. So
every married user with a pending or never-completed spouse invitation sits in
precisely the state where `liveSpouseId()` is null and their spouse is real.

**Three signals exist, and `applies()` consults none:**

1. `users.marital_status ∈ {married, civil_partnership}` — set on that very path.
2. A `FamilyMember` with `relationship = 'spouse'` (and `linked_user_id` null), carrying the spouse's name.
3. `properties.joint_owner_name`, the recorded co-owner.

**Fix (either form is acceptable; both are strictly in the safe direction):**

- Deny the discount where the co-owner is unidentified **and** the user has a spouse
  or civil partner on record by any of (1)–(3), **unless** `joint_owner_name`
  positively names someone other than that spouse. This keeps "Mike Jones"
  discounted and stops the ticket becoming a no-op.
- Plus a lexical guard on `joint_owner_name` for bare spousal terms (`wife`,
  `husband`, `spouse`, `my wife`, `civil partner`). `App\Services\Onboarding\OwnershipPhrasings`
  already carries `(?:spouse|partner|wife|husband)` and is the Rule 20 home to
  compose from — do not write a second vocabulary.

Where identity genuinely cannot be established, **do not discount**. Not discounting
overstates tax, which is where this defect erred before the branch existed and is
the only defensible default at a perimeter.

## C3 — BLOCKS. A red test the evidence pack did not run

`tests/Feature/Stores/PropertyReadConsumerParityTest.php:193` — *"the estate
projection counts a joint property once and a third party's share not at all"* —
fails on this branch:

```
Failed asserting that 1.2124000033099016 matches expected 1.236.
```

The comment at `:189-191` states the semantics: "The £295,000 property adds only the
household's £118,000 share — a ratio of 618,000 : 500,000". Under W-0368 the right
figure is £106,200, giving 606,200 : 500,000 = 1.2124 — exactly the observed value,
exactly the configured discount. It needs the same treatment you correctly gave
`IHTProjectionOwnershipTest`: updated to pin the W-0333 protection **and** the
W-0368 separation, not weakened.

It is red because it lives in `tests/Feature/Stores/`, outside both scopes the
evidence pack ran (`tests/Feature/Estate` + `tests/Unit/Services/Estate`, and
Architecture). The claim "one existing test correctly failed and was updated" is
therefore incomplete — a second one failed and was never seen. I found no others:
I ran every remaining suite that references `IHTCalculationService`,
`gatherUserAssets` or `EstateAssetAggregator`.

---

# Non-blocking conditions

## C4 — The user is now shown a percentage and a value that contradict each other, and the explanation is computed and thrown away

`EstateAssetAggregatorService` puts `undiscounted_share` and
`undivided_share_discount` on every property asset (`:92-94`). Nothing reads them.
`IHTFormattingService:118-125` drops both and emits `value` (discounted) alongside
`ownership_percentage` (40), and `resources/js/components/Estate/IHTAssetBreakdown.vue:39`
renders that as **"(Tenancy in Common - 40%) £106,200"** against a property the user
knows is worth £295,000. Nothing on any surface explains the missing £11,800 —
`grep` for `undivided`/`undiscounted` across `resources/` and `ios-native/` returns
nothing, so this reads the same on web, `/m` and iOS (Rule 19).

Two things follow. A user reconciling the estate screen against their net worth
screen finds the same property at two values with no account of the difference. And
the app is presenting an HMRC *practice* discount — negotiable with the Valuation
Office, not a statutory rate — as an unexplained hard number. File it; it is a
Consumer Duty explainability item, not a calculation error, and the fields are
already there to fix it cheaply.

## C5 — `HouseholdPlanningService` is a fourth Inheritance Tax valuation, still undiscounted

`app/Services/Coordination/HouseholdPlanningService.php:481-482` builds a
first/second-death Inheritance Tax position (`nil_rate_band`, `residence_nil_rate_band`,
`inheritanceTaxRate` at `:341-344`) from raw `calculateUserShare()` property values.
Direction: overstates — conservative, hence non-blocking. But it means "the one home
for the rule, and both columns read it" is true of the estate module's two columns
and not of the app's Inheritance Tax figures generally. File it, with C1, as the
completion of the same sweep.

## C6 — Citations: the authority for the discount is missing, and one assertion in your question is wrong

You asked me to assume anything asserted rather than measured is wrong. Three
corrections, none of which moves a number:

1. **s160 is absent everywhere.** The class docblock, the commit message, the config
   comment and the tests cite only IHTM15071/SVM113040 — HMRC *guidance* — and s161.
   The discount's actual authority is **IHTA 1984 s160**: the price the property
   might reasonably be expected to fetch if sold in the open market. The open-market
   price of an undivided share is below the fraction of the whole; that is the whole
   argument, and it is the one provision not cited. Manual pages are not authority.

2. **s161 does not "deny the discount"** — it substitutes a valuation basis.
   s161(1): where property in a person's estate would be valued at less than the
   *appropriate portion* of the value of the aggregate of it and any related
   property, it is valued at the appropriate portion instead (s161(3) defines the
   portion). The effect is to remove the discount; the mechanism is a different
   valuation. Worth stating precisely in a file behind a perimeter gate.

3. **Your premise in question 2 is half wrong.** s161(2) defines related property as
   (a) property comprised in the estate of the person's **spouse or civil partner**;
   and (b) property that is, or was within the preceding five years, the property of
   a **charity, registered club, political party or national body** (Sch 3), having
   become so under an exempt transfer by the person or their spouse after 15 April
   1976. **There is no "company connected to the estate" limb.** So the narrow
   reading does under-exclude — but via limb (a) reached by name rather than by
   account link (which is C2), and via limb (b) charity, which requires the user or
   their spouse to have given the share away to a charity within five years by an
   exempt transfer. That is undetectable from `properties` and vanishingly rare; I
   do not ask you to handle it. **Civil partners are covered**, both by s161(2)(a)
   and by the app's single `spouse_id` link — checked.

## C7 — Nits

- `undivided_share_discount_percent` holds `0.10`, a fraction. In the same file
  `charity_threshold_percent => 0.10` is a fraction while `relief_percent => 20`,
  `tax_percent => 80` and `rebalancing_drift_percent => 5` are whole numbers. The
  suffix means both things in one config. `rnrb_taper_rate => 0.5` is the fraction
  convention; `_rate` would have been the safer name. `rate()` also has no bounds
  check, so a value entered as `10` would yield `1 - 10` and a negative property
  valuation — I confirmed the seeder is the only writer (no admin write route to
  `tax_configurations`), which is why this is a nit and not a condition.
- The config lives in database rows, and `rate()` falls back to `0.0` when the key
  is absent. That fallback is the right choice — it degrades to the pre-W-0368
  conservative behaviour rather than to a wrong rate — but it is **silent**.
  Deploying this code to csjones or production without
  `php artisan db:seed --class=TaxConfigurationSeeder --force` leaves the feature a
  no-op with no error and no test that would notice. Put the reseed in the deploy
  note.
- `UndividedShareDiscountTest:68` carries a stray `config(['dummy' => null]);`.
- `UndividedShareDiscountTest`'s Rule 2 test asserts only that the value *moves*
  when the rate moves. It does catch a hardcoded literal, so it holds; asserting the
  exact figure at the substituted rate would be stronger.

---

# Answers to the six questions

## Q1 — Is 10% defensible, and is the statutory basis right?

**Yes on the figure; the basis is right but incompletely cited (C6.1).**

10%/15% is the conventional HMRC/Valuation Office practice split, and the pairing is
the right way round: the larger discount attaches to the case where the *other*
co-owner is in occupation, because the buyer of the share cannot obtain vacant
possession. 10% is the standard case and the correct default for a let or
non-occupied co-owned property, which is what most of the qualifying rows in this
app are.

Two cautions to carry into any user-facing copy: these are **practice figures,
negotiable with the Valuation Office on the facts** — not statutory rates, and in
real cases the range runs from nil (co-owners who would plainly sell together) to
above 15% (very small fractional interests). And the authority is s160, not the
manual pages. See C4 and C6.1.

## Q2 — Is the s161 exclusion correctly scoped?

**The exclusion you drew is the right one. The scope is too narrow, and not for the
reason you thought.**

- The "connected company" limb you were worried about **does not exist** in s161 (C6.3).
- The charity limb (s161(2)(b)) does exist, is genuinely broader than a spouse, and
  is undetectable here. Not blocking, not your problem.
- The real under-exclusion is limb (a) reached by name instead of by account link —
  **C2**, and it is live in your own measured data.

One thing I checked and can clear: **the spouse link cannot be one-sided.**
`SpouseLinkingService:299-350` writes both `spouse_id`s in one transaction under
lock and refuses a half link, so `applies()` cannot give the two spouses different
answers about the same property. Civil partners are covered by the same field.

## Q3 — Is applying 10% to an unreachable 15% case defensible, or a misstatement?

**Defensible. Clear it — and your stated reasoning is right for a better reason than
you gave.**

You banned inferring occupation from `property_type`. That ban is correct on the
substance, not just on caution: `property_type = 'main_residence'` records that
**the user** lives there. The 15% case turns on whether the **co-owner** does. The
column does not answer the question being asked, so reading it would not be a
conservative approximation — it would be a different fact.

And 10% is not a compromise invented to fill a gap; it is HMRC's own figure for the
case where occupation is not established. Applying it where the facts are unknown
values the share **above** what the higher-discount case would give, so the error is
an overstatement of tax. That is the same direction the app erred in before this
existed, and the direction to prefer at a perimeter. Not a misstatement of the
user's position — provided the number is not presented to them as a settled
valuation, which is C4.

This was the question you were least sure of. It is the one I am most comfortable
clearing.

## Q4 — The unlinked co-owner. Severity, and does it block?

**Severity: high. It blocks. See C2.**

It is not merely a hypothetical wrong direction. It is (a) present in the seeded
data on one of the two rows your commit message cites as the feature working, where
the co-owner is recorded as `wife`; (b) the app's *designed* pre-acceptance state for
every married user whose spouse invitation has not completed; and (c) detectable —
the app carries `marital_status`, a `FamilyMember` row with `relationship = 'spouse'`,
and `joint_owner_name`, and `applies()` reads none of them.

You said you could find no way to distinguish it from the data. The data has three
ways. That is the finding.

## Q5 — Rule 2 on all changed files

**Clean.** No hardcoded tax value in any changed file.

- `UndividedShareDiscount.php` — the rate comes from `TaxConfigService` at `:57`; the
  only literals are `1 -` and the `0.0` fallback.
- `EstateAssetAggregatorService.php`, `IHTCalculationService.php` — no literals in
  the changed hunks.
- `TaxConfigurationSeeder.php:354` — `0.10` is the config home, which is where it
  belongs; 2026/27 inherits it from 2025/26 at `:1324` and the active row carries it.
- Tests use `295000` / `0.40` as fixtures while reading the rate from configuration,
  which is correct.

Naming and fallback nits at C7; neither is a Rule 2 violation.

## Q6 — Does the discount belong in the estate path only?

**Your separation is correct. Confirm it, and keep it.**

A user's share of a property for net worth is the arithmetic fraction; the discount
is a valuation rule for a taxable transfer. I verified it has not leaked:
`calculateUserShare()` is untouched, `CrossModuleAssetAggregator::calculatePropertyTotal()`
is untouched, and `LetterToSpouseService:327` still reads the undiscounted total
(`LetterFinancialPositionTest` passes). It must also never reach the Capital Gains
path, where a part-share disposal is a different question — it has not.

But **"estate path only" is not the same as "every Inheritance Tax figure"**, and
that is where the branch is incomplete rather than wrong: the residence nil rate
band cap (**C1**, blocking) and `HouseholdPlanningService`'s death modelling
(**C5**, non-blocking) are both Inheritance Tax surfaces that still value the same
house undiscounted. The line you drew is in the right place. It has not yet been
drawn all the way round.

---

## Does this merge?

**No.** Close C1, C2 and C3 — and re-run `tests/Feature/Stores/` alongside the
Estate and Architecture scopes — then it comes back to this gate.
