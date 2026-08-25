# W-0175 — build-lead (`cycle1-tax`) → quality-lead

## Done

One rental figure, one home, and the page no longer disagrees with itself.

- **`PropertyService::annualRentalTaxPosition()`**
  (`app/Services/Property/PropertyService.php:24-88`) — the one definition: rental profit,
  Section 24 credit, per-property composition.
- **`UserProfileService::calculateAnnualRentalIncome()`** (`:235-242`) and
  **`IncomeDefinitionsService::calculateRentalIncome()`**
  (`app/Services/Tax/IncomeDefinitionsService.php:99-114`) both compose from it. The
  duplicate gross implementation and its own copy of the ownership-share arithmetic are
  gone; `IncomeDefinitionsService` takes `PropertyService` in place of `PropertyStore`.
- Labelled: **"Rental Profit"** in the tax card and the definitions panel, with the
  deducted expenses named in the card's note.
- Income components now carry a stable `key`; the client's per-property drill-down matches
  on `component.key === 'rental'` instead of the literal string `'Rental Income'`.

Sarah: £128,880 total income, £8,880 rental, adjusted net income £128,880, threshold
income £128,880 — the £1,920 gap gone. David: £159,289.60 / £14,289.60.

Tests: `tests/Unit/Services/Tax/RentalIncomeOneDefinitionTest.php` (7), green.

## Not done, and why

- **Acceptance 4 — `/plans/estate`.** That is W-0173 and is a different mechanism; see
  below. Deliberately not widened into.
- **Whether the maintenance reserve should be deductible** — an open question, not a
  guess I was willing to make. Flagged to team-lead and recorded on the item.
- **No browser verification, no commit, no PR, no deploy, no bundle rebuild.** By
  instruction.

## What you need that isn't obvious from the artefacts

**The item's diagnosis has one thing the wrong way round, and it changes what "correct"
means.** It reads the £8,880 as "net of exactly two of the four cost fields — not a
definition anyone can reconcile", and concludes the likely fix is to keep both figures and
label them.

`PropertyService::calculateTaxPosition()` (`app/Services/Property/PropertyService.php:85-97`)
deducts **eight** fields: gas, electricity, water, buildings insurance, contents
insurance, service charge, ground rent, managing agent fee. That is the HMRC
allowable-letting-expenses set. The persona had populated two of them. So the profit
figure was already principled and the *gross* figure was the wrong one.

**I therefore collapsed to one figure rather than keeping two.** Under ITA 2007 s23
Step 1, property income enters total income as the profits of the property business
(ITTOIA 2005 Part 3), and both adjusted net income (s58) and threshold income
(FA 2004 s228ZA) build on total income. The profit is the right base on both halves of
the page. **This is the judgement most worth your independent check** — I raised it with
team-lead before building and was not told to hold.

**Note the interaction with W-0174 that the item predicted.** The taper base for these
personas is now £128,880 rather than £130,800. Both are far above £100,000 so the
allowance is £0 either way and no figure moves as a result — but a household near the
threshold would previously have been tapered against a base the tax computation on the
same page contradicted.

**W-0173's cause, found while in here, recorded so it is not re-derived.**
`users.annual_rental_income` is written only by `updateIncomeOccupation`, so every surface
reading that column directly can disagree with the computed figure:
`PersonalAccountsService.php:68` and `:196`, `ResolvesIncome`, `CashFlowProjector.php:116`.
Sarah's column holds `0.00` (never written); David's holds `17280.00` — the **gross**
share, from before this fix. Routing those onto `annualRentalTaxPosition()` is the W-0173
fix. Whoever takes W-0173 should also expect David's column to be rewritten to the profit
figure the next time his income form is saved.

**One test-shape change to know about.** `tests/Unit/Services/PropertyServiceTest.php`
constructed `new PropertyService` with no arguments; `PropertyService` now takes
`PropertyStore`, so it resolves from the container instead. No behaviour asserted there
changed — the 12 failures were `ArgumentCountError` at construction, all green after.

## Assumptions I made

- **That "Rental Profit" is acceptable wording without asking.** The item's acceptance
  required the net figure to be labelled as net; this is the HMRC term and it is short
  enough for the definitions panel's inline list. If design or CSJ want different wording
  it is two strings and one note.
- **That maintenance reserve and "other" are correctly excluded** from the allowable list
  — a reserve is not a paid expense, and capital improvements are never allowable. Stated
  as an assumption, not a finding, and flagged for a decision.
- **That the seeded persona's populated cost fields are representative.** The eight-field
  list is what the code deducts; I did not survey how often each is populated in practice.

## Surfaces covered / not covered

- **web** — covered: two components relabelled, one match-key change. Not browser-verified.
- **`/m`** — covered by inheritance. It shows
  `income_occupation.total_annual_income` (`resources/mobile/views/PersonalInformation.vue:144`)
  and picks up the corrected figure; it renders neither the definitions panel nor the
  per-property drill-down, so there is nothing to build.
- **iOS** — same; `ios-native/Fynla/Features/Profile/PersonalInformationModels.swift`
  decodes `total_annual_income` only.

The item is marked `surfaces: [web]` and I have left that as issued, since the two web
components are the only per-surface work. The underlying figure reaches all three.
