---
id: W-0421
title: The Letter to Loved Ones states a financial position it computes itself, at 100% of every record, and exports it as a PDF addressed to the bereaved spouse
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0030-cycle4-letter-and-income-labels.md
owner: build-lead (fix-cycle4-letter-income)
status: handoff
severity: high
surfaces: [web]
created: 2026-08-23T02:05:00Z
claimed: 2026-08-23T02:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-23
prior_art_found: [W-0138, W-0187, W-0228, W-0238, W-0022]
prior_art_outcome: route
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

Reported as D-24. The report was accurate and the measurement below confirms every
figure in it. What the report did not have is that **the letter is two un-shared
mechanisms, not one**, and the second is server-side and lands in the stored row.

| Section | Letter showed | Aggregator (every other surface) |
|---|---|---|
| Bank Accounts & Savings | £102,000 | £99,750 |
| Investments | £220,000 | £172,500 |
| Properties | £1,570,000 | £755,500 |
| Liabilities & Debts | £365,000 | £170,500 |

### The two mechanisms

1. **Client-side.** `LetterToSpouse.vue:981-986` — six `reduce()` calls summing
   `current_balance` / `current_value` at 100%. Per-item values read the same raw
   columns (`:338`, `:395`, `:425`, `:486`), and `buildFinancialHtml:1573-1633`
   carried a **third** copy as a `switch (type)` naming a different raw column per
   section, for the printed document.
2. **Server-side, and not in the report.** `LetterToSpouseService` generates the
   letter's stored prose from raw records at 100%: `:240 :268 :295 :385 :389 :419
   :431`. `generateRealEstateInfo` wrote *"Current Value: £295,000.00"* for the
   Manchester unit and `generateLiabilitiesInfo` wrote *"Outstanding:
   £120,000.00"* for its mortgage. **Fixing only the cards would have left the PDF
   handing a stranger's money over in the paragraph underneath the corrected
   card.**

### Why this one is worse than the same arithmetic elsewhere

It is addressed to the surviving spouse, under *"Your current financial
position"*; the app describes it as *"crucial information for your spouse to
manage financial affairs after your death"*; and it **leaves the application** via
"Print / Save PDF". A wrong figure in a saved or emailed document outlives every
later fix.

Measured, live, on `peak_earners`: of the Manchester unit's £295,000, **£177,000
belongs to Mike Barrett**, an off-platform co-owner, and **£72,000** of its
£120,000 mortgage is his. Both were in the estate.

### Two further defects found in the same file

- `generateRealEstateInfo` was **primary-owner-only**, so Sarah (17) — recorded as
  `joint_owner_id` on both jointly held homes — got an **empty property section**
  in her own letter while her cards listed two properties. The non-owning side is
  the untested side, again.
- Its `Use:` line read `$property->property_use`, **not a column on `properties`**,
  so it printed "Primary_residence" for every property including the buy-to-let.

## Acceptance

1. Every letter figure equals the corresponding backend figure, on both accounts.
2. The prose and the exported document state the same figures as the cards.
3. Mike Barrett's £177,000 is not in the estate; his £72,000 is not charged to the
   household.
4. Tests assert an **equality between the letter and the aggregator**, on an
   asymmetric fixture, and mutation-test in both directions.

## Notes

**Prior art outcome: route.** `CrossModuleAssetAggregator` already answers reach
and fraction; `UserProfileService::calculateLiabilitiesSummary` already itemises
debt at the user's share (the `/protection` and profile reader, whose mortgage
share follows the securing property per W-0228). Nothing new derives a share.

---

## Outcome — 2026-08-23, build-lead (`fix-cycle4-letter-income`)

**FIXED.** Branch doc: `workforce/branches/fixes/F-0030-cycle4-letter-and-income-labels.md`

### What was done

`LetterToSpouseService::financialPosition()` is now the letter's one financial position,
composed over `CrossModuleAssetAggregator` (assets) and
`UserProfileService::calculateLiabilitiesSummary` (debt, made `public` — a one-word diff,
no behaviour change). Served by `GET /api/user/letter-to-spouse/financial-position`.

**All four mechanisms read it**: the cards, the per-item values, the print builder's
`switch (type)`, and the four prose generators. Nothing on the page does share arithmetic
any more.

### Verified, browser, both accounts

| | Sarah (17) | David (16) |
|---|---|---|
| Savings / Investments | £31,030 / £132,500 | £99,750 / £172,500 |
| Properties / Liabilities | £637,500 / £122,500 | £755,500 / £170,500 |

Manchester: `Your share £118,000 of £295,000 · Your mortgage share £48,000`. **£177,000 and
£72,000 appear nowhere** — screen, prose or exported document. Export verified by real click
(`print()` called, 20,109 bytes). Household debt £293,000.

### What was NOT done, and why

- **Pensions and protection still come from their own modules.** A DC pension is individual
  so no share applies; policy reach is the protection module's question, answered by
  `LifeCoverReach` after W-0186/W-0384, and two agents were live in protection during this
  batch. Re-answering either inside the letter would have rebuilt what was being removed and
  re-introduced a defect fixed hours earlier.
- **`generateImmediateFundsInfo` still states the WHOLE balance** of a joint account —
  deliberate. A surviving joint holder reaches the whole account by survivorship; halving it
  would understate what is available for funeral costs. The line now says *"(full account
  balance)"*.
- **A section the user has EDITED is not repaired.** Editing drops it from
  `auto_populated_fields` permanently (W-0022). A section edited before this fix keeps its
  100% figures. **Zero live rows are in that state**; both halves are asserted by test.

### What the receiver needs that is not obvious

1. **`bank_accounts_info` and `investment_accounts_info` are in the model's `$hidden`**,
   with `password_manager_info` and `cryptocurrency_info`. Their corrected figures are
   stored but never returned by the API. Deliberate — those sections carry sort codes and
   credential prompts. The two sections carrying the third party's money
   (`real_estate_info`, `liabilities_info`) **are** surfaced and are correct.
2. **`financialPosition()` runs inside `getOrCreateLetter()` as well as on its own
   endpoint**, so the page calls it twice per load. Correct, not cheap.
3. **The printed property card carries no mortgage line** where the screen shows one.
   Pre-existing; the figure is in the document's Liabilities section and prose.
4. **Sarah's account cannot discriminate the share rule** — both her homes are 50/50, so her
   figures equal David's. Reach and rendering are what her account proves.

### Assumptions made

- That the debt itemisation `/protection` and the profile read is the right one for the
  letter — i.e. mortgage share follows the securing property (W-0228), not the mortgage row.
- That widening `generateRealEstateInfo` and `generateLiabilitiesInfo` from primary-owner-only
  to the cards' reach is correct: the prose and the cards must agree, and the non-owning
  spouse's own letter should not be empty.
- That `outstandingLiabilities()` / `outstandingLiabilityCount()` are the letter *checker's*
  contract, not the letter's display, so they were left alone. `LetterEstateValidationService`
  (Estate, another agent) reads them.

### Tests

`tests/Feature/UserProfile/LetterFinancialPositionTest.php` (16 cases, 78 assertions) ·
`tests/frontend/components/UserProfile/LetterToSpousePrint.test.js` (10) ·
`tests/Feature/Stores/SavingsReadConsumerParityTest.php` (2 reflection cases updated to the
new generator signature). Six mutations against this item, each reverted, each reddening
only its own cases.
