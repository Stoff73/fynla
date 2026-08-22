# W-0134 (residual, acceptance 4) — build-lead (`cycle2-audit`) → quality-lead

Supplements `build-to-quality-2026-08-21.md`, which covers acceptances 1–3. Branch
document: `branches/fixes/F-0020-cycle2-auditability-figures-the-user-cannot-check.md`

## Done

**Acceptance 4 — "the nil-rate-band footnote states the figure actually applied, not
a pre-deduction one".** Cycle 1 made the rows add up; this sentence was the last
figure on the page that could not be reconciled with them.

Cause: `nrb_message` was built **before** the gift deduction and then had "Reduced by
£150,000…" appended, so it opened "Combined Nil Rate Band of £650,000 available"
above rows itemising £500,000.

Construction moved after the deduction into a new private
`IHTCalculationService::buildNrbMessage()`. Married with gifts now reads:

> Combined Nil Rate Band of **£500,000 applied**: £325,000 each, less £150,000 of
> allowance used by gifts made within the last 7 years. Your spouse's £325,000 is
> modelled on second death — there is no transferable allowance while you are both
> alive. Transfers between spouses are exempt from Inheritance Tax on the first death.

"available" is replaced by "applied" in the widowed and single branches too. The
second-death clause is cycle 1's `nrb-spouse-modelled` row note **verbatim**, not a
paraphrase — one behaviour, one wording. "IHT" became "Inheritance Tax" (Rule 9).

Tests: 5 appended to `tests/Unit/Services/Estate/IHTHouseholdConsistencyTest.php`
(17 passed, 57 assertions). Regression `tests/Unit/Services/Estate/`: 284 passed, 922
assertions.

## Not done, and why

- **No browser verification.** By instruction.
- **Acceptance 6 (`/m`) still has nothing to verify against.** Unchanged from cycle 1:
  zero hits for `nrb_message` in `resources/mobile` or `ios-native`, and
  `resources/mobile/views/modules/Estate.vue` renders no Inheritance Tax figure at all
  (W-0138).

## What you need that isn't obvious from the artefacts

- **Check the sentence against the rows, not against this note.** Add the itemised
  allowance rows by hand, then read the first figure in the footnote. They must match.
  The tests do exactly this with a regex, so if they pass and the browser disagrees,
  the payload and the render have parted company and that is a new defect.
- **Both persona accounts now say £500,000.** When W-0134 was raised, David's screen
  showed the gift deduction and Sarah's did not. W-0154's pooling fixed that, so
  seeing the same figure on both accounts is correct now and is **not** a symptom.
- **The no-gift wording differs and should.** A married household with no gifts inside
  seven years reads "Combined Nil Rate Band of £650,000 applied (£325,000 each)" —
  £650,000 is right there because it is what was applied. The word to look for is
  "available", which should appear nowhere.

## Assumptions I made

- **That "states the figure actually applied" means leading with it**, and that
  keeping the working visible (£325,000 each, less £150,000) serves auditability
  better than printing the applied figure alone. A reader can now reach it by hand.
- **That the "transfers between spouses are exempt" sentence was worth keeping.** It
  is true and it explains why nothing is taxed on the first death. I moved it after
  the second-death clause so it reads as context rather than as the reason the band is
  £650,000 today.

## Surfaces covered / not covered

- **Web:** covered — server-side, rendered at `IHTPlanning.vue:360-362`.
- **`/m`:** no counterpart. **Native iOS:** no counterpart. Both verified by grep,
  not assumed.
