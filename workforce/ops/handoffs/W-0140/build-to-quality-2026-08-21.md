# W-0140 — build-lead (`cycle1-surfaces`) → quality-lead

**Built to the decision recorded at the bottom of the item by the team lead, not to the
acceptance criteria at the top.** Read that decision first; acceptances 1 and 4 are
superseded and satisfied respectively, and building to the top of the item would build the
wrong thing. **No arithmetic value changes anywhere.** This is a disclosure fix end to end.

## Done

The figure keeps its meaning — recorded entries **plus** financial commitments, because
Disposable Income has to subtract commitments to be true. What changed is that the plans
stop discarding the composition the profile already computes.

### Backend — one home, already existing, now finished and read

- `UserProfileService::expenditurePresentation()`
  (`app/Services/UserProfile/UserProfileService.php:512-548`) already returned
  `manual_monthly_total`, `commitments_monthly_total` and `total_basis`. It now also
  returns `manual_annual_total`, `commitments_annual_total` and
  `has_recorded_expenditure`, and **`total_basis` no longer names a component the user does
  not have**: with nothing recorded it reads *"Financial commitments only — no expenditure
  recorded"* instead of *"Category entries plus financial commitments"*. No second
  breakdown was written; `getExpenditureBreakdown()` is untouched.
- `DisposableIncomeAccessor::getForUser()`
  (`app/Services/Plans/DisposableIncomeAccessor.php:30-64`) — the single method all four
  plan services already call — composes an `expenditure_composition` array from that
  presentation. It recalculates nothing.
- The four plan services pass it into `personal_information` unchanged:
  `EstatePlanService.php:615`, `ProtectionPlanService.php:239`,
  `RetirementPlanService.php:295`, `InvestmentPlanService.php:508`.

### Frontend — all five surfaces, one source of labels

- `resources/js/utils/expenditureComposition.js` **(new)** — the one home for the row
  labels, the `'None recorded'` string, and the decision about when the basis note appears.
- `resources/js/components/Plans/Shared/PlanExpenditureComposition.vue` **(new)** — one
  copy of the markup, composing from that util, formatting via `currencyMixin` (Rule 5).
- The four byte-identical panels each gained **one line**, immediately below the Annual
  Expenditure row: `EstatePersonalInformation.vue:69`,
  `InvestmentPersonalInformation.vue:69`, `RetirementPersonalInformation.vue:69`,
  `ProtectionPersonalInformation.vue:69`.
- `planPrintMixin.js:2029-2033, 2039` — the adviser print pack builds its rows from the
  **same util**, so the pack and the screens cannot drift.

What a user sees now, under the unchanged "Annual Expenditure: £14,820":

```
Recorded Expenditure:      None recorded
Financial Commitments:     £14,820
Financial commitments only — no expenditure recorded
```

and for David, "Annual Expenditure: £52,394" followed by `£29,400` recorded and `£22,994`
of commitments. The basis note renders **only** when nothing is recorded.

### Verified against the persona household, read-only

No write touched users 16 or 17. Invoking the real accessor:

| | annual_expenditure | recorded_annual | commitments_annual | has_recorded |
|---|---|---|---|---|
| David 16 | 52,394.40 | **29,400.00** | **22,994.40** | true |
| Sarah 17 | 14,820.00 | **0.00** | **14,820.00** | **false** |

Reconciles to the penny, and the figures are identical to the ones before the change.

### Tests

- `tests/Unit/Services/Plans/PlanExpenditureCompositionTest.php` — 3 tests through the real
  service with real records. Pins the harm: a user with **no** recorded expenditure gets
  `has_recorded_expenditure = false`, `recorded_annual = 0.0`, and a basis saying so, while
  the composed total keeps its value; and where expenditure is recorded, the two components
  sum exactly to the composed total.
- `resources/js/components/__tests__/Plans/PlanExpenditureComposition.spec.js` — 7 tests.
  Three on the shared component, and **one per panel** asserting all four render "None
  recorded" — so deleting the line from any one panel goes red.
- `resources/js/components/__tests__/Plans/planPrintExpenditureComposition.spec.js` — 3
  tests on the fifth surface, asserting the generated print HTML carries the same statement.

**Two existing test doubles were stale after the contract change and I updated them**
(`EstatePlanRefactorTest.php:42-54`, `DisposableIncomeAccessorTest.php:29-42,54-67`). They
mocked `getForUser()` without the new key. I did **not** add a `??` fallback in the plan
services to hide it — the accessor is the one home and always returns the key.

## Not done, and why

1. **A property-costs line will openly carry a known ownership error.** The commitments
   component includes the Manchester mortgage at 50% where 40% is due (**W-0172**,
   `fix-batch-F`). Showing the composition makes that visible instead of buried inside one
   unreconcilable number, **it self-corrects the moment W-0172 lands, and it must not be
   read as a new defect.** Please tell the tester the same.
2. **Acceptance 4 ("both accounts derive it the same way") — nothing was built.** It was
   already satisfied: one rule, `(manual + commitments) × 12`, verified to the penny on both
   accounts. It only looked like two rules because Sarah's manual component is zero.
3. **The £2,520 was never unexplained** — David's protection premiums, £210/month, correctly
   included. Not hunted, per the item's own correction.
4. **Acceptance 5 (browser verification on both accounts) is outstanding** — by instruction,
   the tester closes that loop. **The item is not done until that is green.**
5. **`IncomeOccupation.vue:193` on the profile shows the same composed figure** under
   "Annual Expenditure:" with no composition beside it, inside the Disposable Income block.
   It is a different surface from the five in scope and was not named in the decision.
   **Reported, not fixed** — one line if wanted, and the contract it needs is now there.

## What you need that isn't obvious from the artefacts

- **`users.expenditure_entry_mode` is `NOT NULL DEFAULT 'category'`.** So Sarah — who
  entered nothing — is in *category* mode with every category null. That is why the old
  basis line claimed "Category entries" for a user with no entries, and why a test fixture
  must omit the column rather than set it null (it throws).
- **`summary_only_reason` had the same disease** and I corrected it in the same method: a
  user in *simple* mode with nothing recorded was told "Only a monthly summary has been
  entered". It now says none has been recorded. **This string renders on `/m`**
  (`resources/mobile/views/Expenditure.vue:18`), as does `total_basis` (`:17`) — so `/m`'s
  Expenditure screen gets the corrected statement too, from the same one home.
- The composition falls back to zeros / `false` / `null` when a profile carries no
  expenditure presentation. It is never invented; `DisposableIncomeAccessorTest` pins that.
- `planPrintMixin.js` carries **three pre-existing `no-unused-vars` lint errors** at lines
  133-135 (`enabledActions`, `disabledActions`, `whatIf`). **Confirmed present at HEAD** by
  linting the stashed file — untouched by this change, but `npm run lint` lints changed
  files, so they will surface on this branch. Not fixed: not mine to change.

## Assumptions I made

- **Wording.** "Recorded Expenditure" and "Financial Commitments". The second is the app's
  existing term (the Expenditure tab); the first is mine, chosen over the tab's "Manual
  Expenditure Total" because a plan reader needs to know which half they typed. Both live
  in `EXPENDITURE_COMPOSITION_LABELS` — **one edit changes them everywhere**, including the
  print pack. Design lead may well want a different word; that is cheap now.
- That the basis note belongs **only** where nothing is recorded. Showing "Category entries
  plus financial commitments" to everyone adds a sentence that repeats the two rows above it.
- That the two composition rows should render for every user, including one whose
  commitments are £0, so there is one code path rather than a conditional in five places.

## Surfaces covered / not covered

- **Covered — desktop web (four plan panels) and the adviser print pack.** All five, per
  the item's "all five or none".
- **Not covered, by design — `/m` and native iOS have no plan Personal Information panel.**
  Verified: no `personal_information` plan panel exists in `resources/mobile/` or
  `ios-native/`; both have a *profile* screen at `/personal-information`, a different
  surface fed by `getCompleteProfile`. **`/m`'s Expenditure screen does benefit** from the
  corrected `total_basis` and `summary_only_reason`, since it reads the same contract.
- No `/m` bundle rebuild is needed for this item — no file under `resources/mobile/` changed.
