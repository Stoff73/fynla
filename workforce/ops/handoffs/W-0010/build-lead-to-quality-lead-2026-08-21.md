# W-0010 — build-lead → quality-lead

## Done

- The **Add Pension** / **Upload Statement** CTA row moved out of the
  Defined Contribution-only projections branch to the end of the
  `activeTab === 'current'` container: `resources/js/components/NetWorth/PensionList.vue:355-388`.
  One control, one place — the old copy inside `.pension-cards-column` is gone,
  not duplicated.
- The `guaranteed-income-summary` Defined Benefit rows and State Pension row are
  now clickable into the pension detail (`PensionList.vue:88-96`, `:122-127`;
  style `.guaranteed-item-clickable` at `:1413-1421`). Without this a Defined
  Benefit-only user still could not open, edit or delete the pension they had
  entered, which also blocks verifying W-0017 against Sarah's existing row.
- `resources/js/components/__tests__/NetWorth/PensionListAddControl.spec.js`
  (5 specs) covers all four entry orders, the zero-pension empty state, and the
  newly clickable Defined Benefit row.

## Not done, and why

- **No live browser verification.** The brief scoped me to code plus targeted
  tests and reserved the live persona re-run for the persona-tester. Rule 14's
  loop is therefore NOT closed on this item by me — the vitest specs mount the
  component and assert the DOM, but nobody has clicked the button in Playwright,
  and nobody has entered Sarah's State Pension through it. **That is the single
  most important thing to do next.**
- The completeness banner is untouched. It was correct; the control was missing.
- The `/m` and iOS surfaces needed no change (see below).

## What you need that isn't obvious from the artefacts

- The branch that renders for a Defined Benefit-only user is
  `guaranteed-income-summary` (`PensionList.vue:64`), gated on
  `!projections || !projections.pension_pot_projection?.dc_pension_count`. To
  reproduce, the account needs at least one Defined Benefit or State Pension row
  and **zero** Defined Contribution rows. Sarah Jones (user 17) is exactly that.
- `activeTab` is plain Vuex state (`store/modules/retirement.js:44`) with no route
  or query binding, so you cannot deep-link into the income tab to check the
  alternative State Pension route. The fix deliberately does not rely on it.
- "Add Pension" opens `UnifiedPensionForm` → `DCPensionForm` (not
  `DBPensionForm`); its dropdown is where State Pension and Defined Benefit live.

## Assumptions I made

- I assumed the CTA row belongs at the bottom of the page for every pension mix,
  matching where it already sat for Defined Contribution users. If CSJ wants it
  in the card column specifically, that is a design call I have not taken.
- I assumed making the guaranteed-income rows clickable is desirable rather than
  a deliberate omission. It is consistent with `pension-card-standalone` in the
  other branch, but nobody specified it — it is scope I added because the item's
  "hard dead-end" framing is only half-closed without it. Flag it if unwanted.

## Surfaces covered / not covered

- **web** — fixed, unit-tested, NOT browser-verified.
- **`/m`** — checked, no equivalent dead-end. `resources/mobile/views/modules/Retirement.vue:190-197`
  builds the `action: 'add'` contextual conversation request unconditionally
  (suppressed only at the tier cap), and `RetirementPensionDetail.vue:190-205`
  does the same for edit. No change made, nothing to verify beyond confirming
  that add affordance still appears for a Defined Benefit-only account.
- **iOS** — same contextual-conversation mechanism as `/m`; not separately built,
  not separately verified.
