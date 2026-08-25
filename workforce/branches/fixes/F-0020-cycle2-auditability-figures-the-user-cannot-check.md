---
id: F-0020
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web]
consistency_checked: 2026-08-22T07:20:00Z
status: active
---

# F-0020 — Cycle 2: a figure the user cannot check

**Agent:** build-lead (`cycle2-audit`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0189, W-0134 (residual), W-0132 (both halves)
**ID block:** W-0204 – W-0205 · **Number and ID block issued by team-lead.**

**Predecessors, read before touching anything here:**
`F-0015-cycle1-estate-tax-figures.md` (the estate allowance rows; the
second-death vocabulary this branch reuses verbatim) · `W-0134`'s working notes ·
`W-0132`'s working notes, **from the bottom** · `W-0020` (the recorded will became
the instrument for the Inheritance Tax rate) · `W-0175` (the rental figure's one
home, on the same panel as W-0189).

---

## 1. The principle

**A figure a user is shown must be one they can check, using only what is on the
page.** Three defects, one disease. None of them is an arithmetic error — in all
three the computed number was already right — and that is exactly why they survived
browser passes. What failed was the account the page gave of itself.

Cycle 1 set the standard on the estate allowance column: the gift deduction got its
own row, the spouse's band was labelled as modelled on second death, and the tester
confirmed *"the column now adds up in both directions — I checked every row by
hand."* These three are the same failure elsewhere.

### The three forms it takes

| Form | Instance | What the reader met |
|---|---|---|
| **A step shown that was never applied** | W-0189 | "Less employee pension contributions −£11,600" above an unchanged total |
| **Prose contradicting the rows above it** | W-0134 residual | "£650,000 available" over rows itemising £500,000 |
| **A question asked of someone whose answer is already recorded** | W-0132 | "Do you wish to leave anything to charity?" → "Not set", on a will holding £10,000 |
| **A label naming a rate its own figure was not calculated at** | W-0132 | "Inheritance Tax Liability (40%) … £397,651", where £397,651 is 36% of the estate beside it |
| **A client-side model standing in for the user's real position** | W-0132 | a £148,444 donation nobody made, deducted because a toggle said Yes |

### The rule each fix follows

**Establish whether the number or the account of it is wrong, before changing
either.** In all three cases it was the account. **No number changed in this
branch.** Where a step was not applied it stopped being displayed as one; where
prose disagreed with rows it was rebuilt from the applied figure; where a question
had an answer, the answer is stated and the question withdrawn.

### What this is NOT

It is not a new mechanism for any of the three. W-0132 in particular: there were
already three answers to "is this person leaving money to charity", and the
`/settings/family` card was a **fourth**. It now reads the same instrument the
estate calculation reads. **The count of mechanisms goes down, not up.**

On the estate surface the same rule produced **deletion rather than correction**.
Pointing the broken label at `iht_rate_percent` and stopping would have left the
worse half in place: a client-side model that deducted an assumed donation of 10% of
baseline — £148,444 for Priya, a gift she has not made and which is not in her will —
and applied the reduced rate to the remainder. The server already reads the recorded
will, pools the household's legacies for the s23 exemption and runs the 10% test
against the survivor's estate (W-0020, W-0154). **So the frontend renders what the
API returns and computes nothing.** Three computeds, two props, a whole alternate
table layout and the toggle that drove them are gone; the net change to
`IHTPlanning.vue` and `IHTCalculationTable.vue` is a deletion.

**A finding that vindicates stating the two columns separately.** Priya's current
rate is **36%** and her projected rate is **40%** — the projection re-runs the 10%
test against a much larger estate, where her £10,000 legacy no longer clears the
threshold (`projected_iht_liability` £472,662 ÷ `projected_taxable_estate` £1,181,656
= 40%, verified read-only 2026-08-22). The old label printed one rate across both
columns, so it was **accidentally right for the projected column and wrong for the
current one**, with nothing on the page to tell a reader which. A single label was
never going to be correct here.

---

## 2. Prior art

Checked 2026-08-22 across `registry/capabilities.md`, the code, custom artisan
commands, open PRs and in-flight branches, the vault, and `.claude/skills|agents`.

| Instance | Prior art found | Outcome |
|---|---|---|
| W-0189 income chain | `IncomeDefinitionsService` is the single home for threshold and adjusted income; every other consumer (`AnnualAllowanceChecker`, `TaxStrategyMath`, `ChildBenefitService`) reads it. Its arithmetic was corrected by an earlier pass — see the "old code double-deducted to 225000" comments at `IncomeDefinitionsServiceTest.php:129-188` | **extend** — the service was right, the panel was never told |
| W-0134 footnote | `IHTCalculationService` builds `nrb_message`; cycle 1's `nilRateBandRows()` already carries the row vocabulary | **extend** — rebuild the message from the applied band, reusing cycle 1's wording |
| W-0132 charity answer | `Bequest::isCharitable()` — declared in its own docblock as "The ONE home for this decision (Rule 20)"; `WillAnalysisService::getCharitableBequestTotal()` / `hasUnvaluedCharitableGifts()` read it; `determineIHTRate()` was moved onto the recorded will by W-0020 | **extend** — one summary method in the class that already owns the question, no new heuristic |
| Serving it to `/settings/family` | `UserProfileService::getCompleteProfile()`, already loaded by the page; `income_summary` is the precedent for an additive top-level block | **route** — no new endpoint, no new request |

**Explicitly rejected:** appending `is_charitable` to the `Bequest` model and letting
the Vue card filter and total the rows itself. That would have put a second copy of
the totalling rule — including the deliberate exclusion of unvalued gifts — in the
frontend. Rule 20 forbids it.

---

## 3. Constraints honoured

- **Rule 20** one behaviour, one home; cycle 1's second-death wording reused verbatim
  rather than paraphrased.
- **Rule 9** no acronyms — `nrb_message` said "IHT"; the rewritten line says
  "Inheritance Tax".
- **Rules 12 / 15** no scores, no ratings, no decorative icons introduced on any of
  the three surfaces, all of which are banned surfaces.
- **Rule 19** `/m` and native named per item. **None of the three has a counterpart**
  — verified by grep, not assumed. See §5.
- British spelling; `declare(strict_types=1)`; imports and first usage landed together.
- Persona users 16 and 17 read-only. Every test builds its own fixtures.

---

## 4. Status

| Item | Status |
|---|---|
| W-0134 residual — footnote states the applied band | **DONE**, tests green |
| W-0189 — income definitions chain | **DONE**, tests green |
| W-0132 — `/settings/family` reads the will | **DONE**, tests green |
| W-0132 — `/estate` rate label reads the calculation | **DONE**, tests green |
| W-0132 — the assumed-donation recomputation deleted | **DONE**, tests green |
| W-0132 — the `charitable_bequest` toggle removed | **DONE**, tests green |

### Changed

| File | Change |
|---|---|
| `app/Services/Estate/IHTCalculationService.php` | `nrb_message` built after the gift deduction, via new private `buildNrbMessage()` |
| `app/Services/Tax/IncomeDefinitionsService.php` | publishes `pension_arrangement`; docblocks name the base each definition branches from |
| `resources/js/components/UserProfile/IncomeDefinitionsPanel.vue` | two false steps replaced by stated working from Total Income; one name for one quantity |
| `app/Services/Estate/WillAnalysisService.php` | new `charitableBequestSummary()` |
| `app/Services/UserProfile/UserProfileService.php` | additive `charitable_bequests` block on the profile |
| `resources/js/components/UserProfile/FamilyMembers.vue` | card states the recorded will instead of the `users.charitable_bequest` toggle |
| `app/Http/Controllers/Api/Estate/IHTController.php` | `iht_summary.current` publishes `iht_rate_type` and `iht_rate_message` |
| `resources/js/components/Estate/IHTPlanning.vue` | rate label from `iht_rate_percent` per column; assumed-donation recomputation, toggle, and three dead what-if computeds deleted |
| `resources/js/components/Estate/IHTCalculationTable.vue` | the toggle-driven alternate layout deleted; `ihtRateLabel` replaces a string prop that defaulted to `'40%'` |

### Tests

| File | Count |
|---|---|
| `tests/Unit/Services/Estate/IHTHouseholdConsistencyTest.php` | +5 (17 passed) |
| `tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php` | +5 (20 passed) |
| `tests/frontend/components/UserProfile/IncomeDefinitionsPanel.test.js` | +8, new file |
| `tests/Unit/Services/Estate/WillAnalysisCharitableBequestTest.php` | +8 (22 passed) |
| `tests/Feature/Api/UserProfileCharitableBequestsTest.php` | +4, new file |
| `resources/js/components/__tests__/UserProfile/FamilyMembers.spec.js` | +6 (24 passed) |
| `tests/frontend/components/Estate/IHTCalculationTable.test.js` | +6 (15 passed) |
| `tests/frontend/components/Estate/IHTPlanningRateLabel.test.js` | +8, new file |
| `tests/Feature/Api/IhtRateIsPublishedWithItsFigureTest.php` | +5, new file |

### The technique — asserting relationships, not values

**A test that shares the code's misconception cannot fail.** Every test on this
branch is written to be immune to that, by one of two moves. Both are reusable and
neither is specific to these defects.

**1. Assert the RELATIONSHIP between two things the page prints, never a value the
fixture supplied.**

Asserting "the rendered total equals `definitions.total_income`" passes on the broken
layout — the component was rendering the right numbers in a lying arrangement, so
every value assertion was already green. The relationship is what was false:

```js
// Parse the figures back OUT of the rendered working sentence, then do the sum
// against the rendered rows.
const [base, deduction] = poundsIn(workingSentence);
expect(base).toBe(rowFigure(wrapper, 'Total Income'));
expect(base).not.toBe(rowFigure(wrapper, 'Adjusted Net Income'));  // the old base
expect(base - deduction).toBe(rowFigure(wrapper, 'Threshold Income'));
```

Same move three more times: the footnote tests regex the headline figure out of the
prose and assert it equals `nrb_available`, so the sentence cannot be updated in
lockstep with a wrong number and still pass; the estate table tests divide the printed
liability by the printed taxable estate and require the answer to be the rate in the
label; the API test does that same division inside one response body. **In every case
both sides of the assertion are read back from the output, so the test cannot inherit
the assumption that produced it.**

**2. Set the input that would give the WRONG answer, so a stale mechanism cannot pass.**

The inverse of the same idea, for "which source does this read" rather than "do these
reconcile". Every W-0132 test sets `users.charitable_bequest` to whichever value
contradicts the will — `NULL` with a legacy, `false` with a legacy, `true` with none —
so a card, endpoint or label still consulting the column fails on its own fixture.
The W-0189 base case does it with data rather than a flag: it uses a **Gift Aid
donor**, because Gift Aid reduces net income and does not reduce threshold income, so
the two are provably different numbers and the assertion proves which base was used
instead of restating the fixture.

**The rule of thumb:** if you can imagine the old, broken code passing the test you
just wrote, the test asserts a value. Find the relationship it violated instead.

## 5. Surfaces

Rule 19, stated per item rather than assumed:

- **W-0134 footnote** — server-side, so it reaches any surface rendering
  `nrb_message`. Web renders it at `IHTPlanning.vue:360-362`. **No `/m` or native
  counterpart:** zero hits for `nrb_message` in `resources/mobile` or `ios-native`,
  consistent with cycle 1's finding that `resources/mobile/views/modules/Estate.vue`
  shows no allowance itemisation (W-0138).
- **W-0189 panel** — web only. Zero hits for "Threshold Income" / `threshold_income`
  / "Adjusted Net Income" in `resources/mobile` or `ios-native`.
- **W-0132 estate surfaces** — web only. `/estate` and `/estate/inheritance-tax` are
  the same component (`IHTPlanning.vue`, the drill-down passing `:table-only="true"`),
  so both are fixed by the same change and cannot disagree with each other again. The
  `iht_rate_type` / `iht_rate_message` addition is server-side and reaches any surface
  that reads the summary. **`/m` renders no Inheritance Tax figure and no rate**
  (W-0138), and `ios-native` renders neither — zero hits for `iht_rate` in both.
- **W-0132 card** — web only. Zero hits for "charit" in `resources/mobile` or
  `ios-native` other than the `charitable_donations` expenditure row on
  `resources/mobile/views/Expenditure.vue`, which is a different thing entirely.
  `resources/mobile/views/PersonalInformation.vue` reads `family_members` for
  dependants only and shows no charitable card.

## 6. Raised, not fixed

Two defects found while establishing W-0189's verdict, both out of scope and both
requiring a decision this branch should not take alone. See §7 for the ID block.

- **Salary sacrifice is not added back to threshold income** (FA 2004 s228ZA(3)).
  `IncomeDefinitionsService::getPensionContributions()` ignores
  `dc_pensions.salary_sacrifice` entirely. Cannot be fixed honestly because nothing
  records whether `annual_employment_income` is the pre- or post-sacrifice figure —
  pre-sacrifice needs an add-back, post-sacrifice means the current deduction is a
  double-count. Currently latent: **zero** rows have the flag set. The panel now
  *names* the arrangement without claiming a treatment that was not applied, so the
  gap is visible rather than hidden.
- **`net_income` deducts the Gift Aid gross-up** (`IncomeDefinitionsService:35`).
  Statutorily Gift Aid does not reduce net income (ITA 2007 s23 Step 2); it reduces
  adjusted net income (s58). The end figure for Adjusted Net Income is right and the
  displayed chain still adds up as printed, so W-0189's acceptance 1 is met — but the
  row labelled "Net Income" is not net income for a Gift Aid donor.

## 7. Environment

- Branch `dev`, shared working tree, other agents editing concurrently. No commits,
  no PR, no deploy, no bundle rebuild, no tool-schema capture.
- Tests: `DB_DATABASE=laravel_testing_c ./vendor/bin/pest <paths>`;
  `npx vitest run <paths>` for the frontend.
- Persona household: David Jones id 16, Sarah Jones id 17, Priya Raman id 20.
  **Read-only — fixtures only for tests.**
- No browser verification of my own work, by instruction. Quality certifies.
- **`IHTPlanning.vue` was assigned to this branch by team-lead at 07:35** after
  `cycle2-projection` was routed off it. The estate half of W-0132 was deliberately
  NOT started before that, because another agent had the file open at 07:03.
