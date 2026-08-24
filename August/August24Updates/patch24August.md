# Patch Notes — 24 August 2026

**Branch**: `estate-copy-and-m-handoff` → `dev`
**Commits**: 43 (`c5e678131..d0aa2ac85`)
**Files changed**: 265 (+25,455 / −1,077 lines)

Seventeen defects, all of them figure-level or consent-level, and **none of them
visible to a person clicking through the application**. That is the theme of this
patch: every fix below sat behind a screen that looked correct. A silent `??` miss
renders as a plausible sentence; a wrong tax figure renders as a number. So each fix
carries a guard that was mutation-checked — broken deliberately to prove it goes red —
because a test that cannot fail is how these survived in the first place.

---

## Inheritance Tax — figures that were wrong

### W-0474: A civil partnership was taxed on two estates against one person's allowances (High)

**Impact**: £130,000 of overstated projected tax, plus a £30,000 current bill that
should never have existed.

`IHTCalculationService` decided whose records a calculation covered in two places, two
different ways. The headline read `marital_status === 'married'`; every projection
branch read data-sharing alone, which consults no marital status at all. A civil
partnership therefore had **both** partners' projected assets assessed against **one**
person's £325,000 + £175,000. Nine sibling services already read
`['married','civil_partnership']` — including the docblock of the migration that
introduced the status, which asserted this service did too.

The question is now asked once, by `App\Support\HouseholdPooling`. Verified against
IHTA 1984 s18, s8A, s8G and HMRC IHTM11031/IHTM43001 — the "spouse or civil partner"
wording is inserted by SI 2005/3229 reg 7, under the power in Civil Partnership Act
2004 s.246.

The same predicate ran the other way for an **unmarried couple** who had linked
accounts: a projection pooling two estates against a single band and a spouse exemption
they are not entitled to. Closed with it.

**Files**: `app/Support/HouseholdPooling.php`, `app/Services/Estate/IHTCalculationService.php`, `app/Services/Estate/HouseholdCashFlowProjector.php`, `app/Services/Estate/IHTFormattingService.php`

---

### W-0361: The projected nil rate band was charged for gifts long out of cumulation (High)

**Impact**: £500,000 shown where £650,000 is correct — £60,000 of overstated projected tax.

`projected_nrb_available` reused the current column's band, whose gift deduction is
measured from today. A chargeable transfer made in 2020 still consumed £150,000 of the
band at a death modelled in 2062 — thirty years after IHTA 1984 s7(1) drops it out.

The defence in the docblock said the band is "a statutory amount reduced by chargeable
transfers already made, neither of which is a function of the estate's size". True, and
beside the point: it **is** a function of the date of death, and the two columns have
different ones. `FailedGiftTaxCalculator` now takes the date being modelled.

**Files**: `app/Services/Estate/FailedGiftTaxCalculator.php`, `app/Services/Estate/IHTCalculationService.php`

---

### W-0475: Four of the five asset types vanished from the projected estate (High)

**Impact**: Understated projected tax, and a smaller taper base, for anyone recording an
asset outside the four main tables.

The current estate is built from the aggregated asset collection; the projection was
built from **source tables**. Only chattels and business read the collection, so an
`assets` row was counted today and gone at death. The board item named the `other`
bucket; measurement showed it was four of the five types Fyn can record — tell Fyn "I
own a plot of land worth £200,000" as type `property` and it disappeared from the
projection.

The fix keys on **provenance**, not type, because a row typed `property` is "covered" by
name and invisible to the property table. A new asset type now falls into the residual
automatically instead of vanishing.

**Files**: `app/Services/Estate/IHTCalculationService.php`

---

### W-0365: A joint owner was refused the residence band their share paid for (High)

**Impact**: Overstated tax by up to the whole residence nil rate band.

The eligibility check required being the **primary owner** of the property record, and
said so deliberately — "to match the pre-PR-5a semantics". That is a statement about
this codebase's history, not about the statute. IHTA 1984 s8H(2) asks about an
*interest* in a dwelling-house, and a beneficial co-owner has one.

The file contradicted itself: the cap calculation used the joint-aware reader and
counted that same user's share toward the cap on a band they were being refused.

**Files**: `app/Services/Estate/IHTCalculationService.php`

---

### W-0364: The 2027 pension scenario grew the estate and kept the small estate's allowances (High)

**Impact**: Understated the post-2027 bill by up to £350,000 for a couple.

Adding the pension pots enlarges the estate, and both tests that turn on estate size
were skipped because the scenario reused the pre-pension allowances: the residence band
taper (IHTA 1984 s8D(5)) and the 10% charitable rate test (Sch 1A). An estate at £1.7m
with a £600k pension crosses £2,000,000 and loses its band — and kept it.

**Files**: `app/Services/Estate/IHTCalculationService.php`

---

### W-0363: The projected estate excludes pensions, and now says so (High)

**Impact**: The understatement remains; it is no longer silent.

From April 2027 unused defined contribution pensions form part of the estate. The
projection excludes them.

**The obvious fix was not shipped, deliberately.** Adding the pot would double count —
the cash-flow projector already turns that pension into income and carries it in
projected cash. What belongs in the estate is the *unused* fund at death, which is a
real piece of work (**W-0482**). Rather than replace an understatement with a wrong
number that looks right, the exclusion is now stated at the point the figure is shown,
on web and `/m`, from one sentence the engine owns.

**Files**: `app/Services/Estate/IHTCalculationService.php`, `app/Http/Controllers/Api/Estate/IHTController.php`, `resources/js/components/Estate/IHTCalculationTable.vue`, `resources/js/components/Estate/IHTPlanning.vue`, `resources/mobile/views/ModuleDetail.vue`

---

## Consent and spouse linking

### W-0347: Spouse linking forged the other party's consent (Critical)

**Impact**: One party could link accounts, write the other person's user row, and have
the system record their acceptance — with nothing from them.

The complete consent flow already existed and had never been reachable: request,
accept, decline and revoke were all written, and the screen was mounted nowhere. So
consent was *unobtainable*, and the backend forged it to make the product work.

Now: linking an existing account records an **invitation**; nothing about the other
account is written until they accept. Withdrawal is no longer a one-way door — a
settled request can be asked again, on both surfaces. Rows nobody granted become
unanswered requests rather than being grandfathered in (CSJ's decision: re-ask, not
retrospectively legitimise). The consent notice now states that the grant is **mutual**,
that the accounts are recorded as one household, that both parties are recorded as
married or in a civil partnership, and that stopping sharing does not undo that.

Also fixed: a state the re-ask migration itself created — households linked *and*
holding a pending request — which `/m` rendered as "Sharing is off" with a button that
returned an error.

**Files**: `app/Services/Onboarding/SpouseLinkingService.php`, `app/Http/Controllers/Api/SpousePermissionController.php`, `app/Http/Controllers/Api/FamilyMembersController.php`, `app/Models/User.php`, `database/migrations/2026_08_24_130000_reask_spouse_permissions_nobody_granted.php`, `database/seeders/PreviewUserSeeder.php`, `resources/js/components/UserProfile/SpouseDataSharing.vue`, `resources/mobile/views/SpouseSharing.vue`

---

### W-0477: A deleted spouse left the survivor's spending stored as halves (Medium)

**Impact**: Household spending understated by half, disposable income overstated —
the figure every affordability statement rests on.

Under joint mode each account stores its half of the household's spending. When one
account goes, the halves do not change, so the survivor keeps £600 of groceries that
means £1,200. Every reader downstream took the half for the whole.

The survivor's figures are now restored to household terms at the moment the household
stops being two accounts — via an observer, so it applies however the deletion is
issued rather than only at the endpoints someone remembered.

**Files**: `app/Support/SharedExpenditure.php`, `app/Services/Expenditure/HouseholdExpenditureWriter.php`, `app/Observers/SurvivingSpouseExpenditureObserver.php`, `app/Services/Account/RetentionPurgeService.php`

---

## Protection and insights

### W-0479: The protection gap count was zero for every household (Critical)

**Impact**: Households with real shortfalls were told **"Your protection now covers what
your family would need."**

Two dashboards counted gaps by reading keys the analyzer has never emitted — one looked
for `gap`, the other for `shortfall`. Both read zero for every household in the
application's history. Worse, the count gates a milestone: with it permanently zero,
**any household holding a single policy was awarded "protection adequate"**. Three such
milestones existed on the dev database, one of them for a household with a £21,000
income-protection shortfall.

The analyzer now publishes the count itself and both dashboards read it, so a consumer
cannot guess a shape it is handed. Supplementary categories are excluded from the count —
one uncovered income is one gap, not three.

**Files**: `app/Services/Protection/CoverageGapAnalyzer.php`, `app/Services/Mobile/MobileDashboardAggregator.php`, `app/Services/Dashboard/DashboardAggregator.php`

---

### W-0473 / W-0478: The daily insight, and the two mechanisms producing it (High)

**Impact**: Every household saw the same generic line every day.

Every reader looked one level above the data, so all six branches were skipped. It never
errored — it fell through to a catch-all, which is why nothing looked broken. Two
further misses only became visible once the level was corrected: the tax module is keyed
differently, and the pension figure was read under the wrong name.

Then the deeper problem: there were **two** insight composers. The rich one, with real
figures and the Inheritance Tax caveat, had no client at all; the prose-only one is what
`/m` and native actually showed. One composer now, read by both.

**Files**: `app/Services/Mobile/DailyInsightService.php`, `app/Http/Controllers/Api/V1/Mobile/InsightsController.php`, `app/Services/Mobile/MobileDashboardAggregator.php`

---

## Documentation

**Rule 9 amended (CSJ)**: an acronym may be used once it has been spelled out to the
reader on the surface they are looking at. Applied to the Inheritance Tax caveat, which
now reads "the Alternative Investment Market (AIM)" and then "AIM".

---

## Also filed, not yet fixed

| Item | Severity | What |
|---|---|---|
| **W-0480** | High | Four Estate and Tax services still read `['married']` alone, so a civil partnership gets the wrong answer on adjacent screens |
| **W-0482** | High | Including pensions in the projected estate needs the *unused fund* at death, not today's pot |
| **W-0481** | Low | `AssetFactory` randomises two fields into values their columns reject |

---

## Verification

Every fix carries a guard, and every guard was **mutation-checked** — the fix reverted,
the test confirmed red, the fix restored. Targeted suites green throughout: estate 495,
estate feature + agents 595, plans/tax/tiers 410, consent and profile 148, mobile 218,
protection/dashboard/integration 416. Pint clean. Full suite run as a consolidation
point before deployment to dev.

Browser-verified end to end on **web and `/m`, both accounts**: the consent journey
(request → decline → ask again → accept) with the database checked at every step.
