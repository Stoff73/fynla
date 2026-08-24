---
id: F-0030
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/05-perimeter.md, core/constitution/08-process.md]
surfaces: [web]
consistency_checked: 2026-08-23T03:00:00Z
status: active
---

# F-0030 — Cycle 4: a document that hands a third party's money to the estate, and two labels that describe deductions they do not make

**Agent:** build-lead (`fix-cycle4-letter-income`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0421, W-0422, W-0423 (fixed) · W-0424, W-0425, W-0426 (raised) · **ID block:** W-0421 – W-0430
**F number:** taken after listing `fixes/` — **F-0029 was the highest, not F-0028** (the
wills agent holds F-0029), so **F-0030**. Team-lead was told the number taken rather than
the agent choosing silently (`FORMATS.md` §"Branch-document numbers").

**Predecessors, read before touching anything here:** `W-0138` put the share arithmetic in
`CrossModuleAssetAggregator`. `W-0187` put the debt side at the user's share in
`UserProfileService::calculateLiabilitiesSummary`. `W-0228` made a mortgage's share follow
the property securing it. `W-0238` added `atUserShare`. `W-0022` rewrote the letter's
generators once already — for phantom column names, not for shares.

---

## 1. The defect, and why this one is different

Reported as D-24. Four figures in a document addressed to a bereaved spouse:

| Section | Letter showed | Aggregator (every other surface) |
|---|---|---|
| Bank Accounts & Savings | £102,000 | £99,750 |
| Investments | £220,000 | £172,500 |
| Properties | £1,570,000 | £755,500 |
| Liabilities & Debts | £365,000 | £170,500 |

The same arithmetic error this cycle has found seven times. **What makes this instance
different is where it ends up.** It sits under *"Your current financial position"*, in a
document the app describes as *"crucial information for your spouse to manage financial
affairs after your death"*, with a **"Print / Save PDF"** control beside it. Of the
Manchester unit's £295,000, **£177,000 belongs to Mike Barrett** — a co-owner with no
account here — and **£72,000** of its mortgage is his. Both were in the estate, on screen
and in the exported file. **A saved or emailed PDF outlives every later fix.**

## 2. What the report did not have: the letter is TWO mechanisms

The report found the client-side one. There is a second, server-side, and it writes into
the stored row.

| # | Mechanism | Where |
|---|---|---|
| 1 | six `reduce()` calls at 100% | `LetterToSpouse.vue:981-986` |
| 2 | per-item raw column reads | `:338 :395 :425 :486` |
| 3 | a `switch (type)` naming a different raw column per section, **for the printed document** | `buildFinancialHtml:1573-1633` |
| 4 | **generated prose, at 100%, persisted to `letters_to_spouse`** | `LetterToSpouseService:240 268 295 385 389 419 431` |

`generateRealEstateInfo` wrote *"Current Value: £295,000.00"*; `generateLiabilitiesInfo`
wrote *"Outstanding: £120,000.00"*. **Fixing only the cards would have left the PDF
handing Mike Barrett's money over in the paragraph underneath the corrected card.**

### The prose is STORED — a corrected generator does not fix a letter written last week

Raised by team-lead and measured rather than assumed. **Seven `letters_to_spouse` rows
exist.** Five (users 23, 24, 25, 27, 28) hold `auto_populated_fields = NULL` and null
prose — nothing to repair. Two (users 16, 17) hold generated prose, and on **both** all
five money-bearing sections are still in `auto_populated_fields`, so
`refreshAutoPopulatedSections` regenerates them on the next read. **Verified: after the
fix, zero stored rows carry a 100% figure.**

That is measured, not inferred — the sweep grepped every row for nine stale markers.
**It returned one hit, and the hit was a false positive**: `Current Value: £95,000.00` in
David's investment prose is the Hargreaves Lansdown account, which is *individually held*
at £95,000 and correctly stated whole. It collided with the AJ Bell account's £95,000
**full** value. **Third collision of the batch** (see §8) — this one in a detection
marker rather than a fixture, and the same lesson: *a figure is only evidence if no other
figure in the data equals it.*

**The hole this does NOT close, stated as a decision rather than left silent.** Editing a
section drops it from `auto_populated_fields` permanently (W-0022 — deliberate; a letter
to a grieving partner is not the place to overwrite someone's words). So a section a user
**edited before this fix** keeps the figures Fynla generated at 100%, and nothing here
changes that. **Zero live rows are in that state.** A test asserts both halves — the
owned section repairs, the user-owned one is left exactly as they left it — so the
limitation is recorded behaviour rather than an untested gap.

### Two further defects found in the same file

- **`generateRealEstateInfo` was primary-owner-only.** Sarah (17) is `joint_owner_id` on
  both jointly held homes, so her own letter's property section was **empty** while her
  cards listed two properties. *For any shared record, the non-owning side is the untested
  side* — the third time this cycle.
- Its `Use:` line read **`$property->property_use`, which is not a column on
  `properties`**, and printed "Primary_residence" for every property including the
  buy-to-let. Removed rather than corrected: `property_type` states that one line above.

## 3. The fix — routed, not rebuilt

**Prior-art outcome: route.** Six sources checked (`registry/capabilities.md`, code,
artisan commands, open branches, vault, `.claude/`). Every reader already existed:

| Question | The one home |
|---|---|
| reach + fraction, assets | `CrossModuleAssetAggregator::getSavingsAssets/getInvestmentAssets/getPropertyAssets` and the matching totals |
| the debt side, itemised at the user's share | `UserProfileService::calculateLiabilitiesSummary` (the profile and `/protection` reader) |
| a mortgage's share | `calculateUserMortgageShare` → `propertyOwnershipFor` (W-0228) |

`LetterToSpouseService::financialPosition()` composes presentation over those and nothing
else. `GET /api/user/letter-to-spouse/financial-position` serves it. **All four
mechanisms above now read it**, including the prose generators and the print builder.
`calculateLiabilitiesSummary` went `private` → `public` — a one-word diff, no behaviour
change — rather than the letter growing a fourth itemisation of debt.

**Deliberately still un-consolidated, and stated so it is not mistaken for an oversight:**

- **Pensions and protection stay on their own module endpoints.** A defined-contribution
  pension is individual, so no share applies. Which policies reach a given user is the
  protection module's question, answered by `LifeCoverReach` after W-0186/W-0384 — and
  two agents were live in protection during this batch. Re-answering either inside the
  letter would have rebuilt the very thing being removed, and would have re-introduced
  a defect fixed hours earlier.
- **`generateImmediateFundsInfo` still states the WHOLE balance** of a joint account. It
  is the one figure in the letter that should not be a share: a surviving joint holder
  reaches the whole account by survivorship, and halving it would understate what is
  available to someone with funeral costs to meet this week. The line now says *"(full
  account balance)"* so it cannot be read as a share.

### Also fixed, being rewritten anyway

The screen said **"TIC"** where the printed document said "Tenants in Common". Both now
come from one `itemBadges()` decision. A **failed load** now says so on screen and in the
document, instead of printing £0 totals that read as a household owning nothing.

**Two defects the rewrite exposed, one of them introduced by the rewrite itself:**

- **The investment ISA badge had never once fired.** Both the card and the print builder
  tested `account_type === 'stocks_and_shares_isa'`. `investment_accounts.account_type`
  holds `isa`, `gia`, `vct` — `stocks_and_shares_isa` is an **`isa_type`** value, and
  `TaxProductInfoService:52` maps the two together. A dead `v-if` carried forward would
  have been a defect shipped in code written today, so it is fixed rather than inherited.
- **A regression I introduced and caught before the browser.** Routing the account-type
  line through a generic `humanise()` turned `strtoupper`'s "ISA/GIA/VCT" into
  **"Isa", "Gia", "Vct"** — two meaningless acronyms and one that is no longer the
  acronym. `accountTypeLabel()` now spells them out, keeping ISA as Rule 9's single
  permitted abbreviation. Everything else still humanises as it always did.

## 4. W-0422 — a label that names three deductions and makes two

```
total income        159,289.60
income tax           51,883.32   (52,663.32 before the £780 Section 24 credit)
National Insurance    4,910.60
pension contribution 11,600.00
net_income          102,495.68  = 159,289.60 - 51,883.32 - 4,910.60
```

*"Net Income (after tax, pension contributions and tax credits)"* — the pension is not
among them, and **National Insurance, which is always deducted, appeared in no variant of
the label.** The pension reduces the TAX (income tax is charged on Threshold Income of
£147,690, three panels above), which is exactly why it does not also reduce this line.

The comment above the code said the same false thing — *"Use detailed net_income
(includes pension contributions)"* — and is corrected, not left beside a corrected label.
**Third comment this cycle asserting a relationship its code does not honour.**

**The arithmetic was deliberately not changed. See §6.**

## 5. W-0423 — a header that charges National Insurance on rent

One card holds everything taxed at the main rates — employment, self-employment, **rental
profit** and **pension income in payment** — and was labelled `'Earned Income'`
unconditionally with a flat **"NI Applies"** badge beside the combined gross. The
computation beneath was always right ("Class 1 (Employment)", working on £145,000). On
the one page whose whole value is that a reader can check it by hand, **a mislabelled
header over a right number is the claim they have no way to verify.**

The label is now built from the kinds present ("Earned and Rental Income"), and the badge
names its base. **The bands cannot supply that base** — they start at the primary
threshold and sum to £132,430 against pay of £145,000 — so `ni_breakdown.class_1.base`
is published by the calculator. `gross_amount` is untouched; the number was never wrong.

Rule 9: `NI` / `No NI` were acronyms in user-facing text, and are spelled out.

## 6. The part deliberately NOT done, and why — raised as W-0424

The report's second half is right: pension money is not spendable, so take-home is
£90,896 and **Disposable Income is overstated by £11,600** (£64,501.28 measured against
roughly £52,901). **But deducting the pension from `net_income` would double-count.**

| Mechanism | Reads | Reaches expenditure? |
|---|---|---|
| tax path — `calculateAnnualPensionContributions` | `employee_contribution_percent × annual_salary` | no |
| spending path — `getFinancialCommitments()` `retirement` | `dc_pensions.monthly_contribution_amount` | **yes**, via `annual_expenditure` |

David's DC#9 carries the percentage and **`monthly_contribution_amount = NULL`**, so his
£11,600 is in the tax path and nowhere in the spending path. Subtracting it from
`net_income` fixes him and charges twice anyone who records the contribution as a monthly
amount. **Zero seeded pensions populate both fields, so no test would go red and it would
ship invisible.**

The root cause is one missing bridge in `getFinancialCommitments` — the expenditure path
this batch fenced off for another agent. Raised as **W-0424** with the full consumer
census, including the two callers that bypass `DisposableIncomeAccessor` and re-derive
`net − expenditure` themselves.

## 7. Untouched, on instruction

The **£780 Section 24 credit** could not be derived from the persona (David's buy-to-let
interest share implies roughly £1,461 at 20%, or £934 interest-only). Left alone.

**One measurement changes the shape of that question, though.**
`mortgages.monthly_interest_portion` is **NULL on all thirteen rows in the database**, not
merely on this household's three. So it is not "this persona was seeded without it" — **no
write path anywhere populates it.** If it is the intended input to the Section 24
calculation, the intended input is empty system-wide, and whoever takes the tax-rules
question needs that before they start.

## 8. Verification

**Figures, live, both accounts** (`php artisan tinker`, dev database):

```
u16 David   savings 99,750.00  investments 172,500.00  properties 755,500.00  liabilities 170,500.00
u17 Sarah   savings 31,030.00  investments 132,500.00  properties 637,500.00  liabilities 122,500.00
```

Each equals the aggregator to the penny. Manchester now reads `value=118,000.00
full=295,000.00 mortgage=48,000.00` — Mike Barrett's £177,000 and £72,000 are out.
Household debt **£293,000** (170,500 + 122,500), as the W-0228 ruling requires.

**Pest** — `DB_DATABASE=laravel_testing_s`:

| Suite | Result |
|---|---|
| `tests/Feature/UserProfile/LetterFinancialPositionTest.php` (new) | 16 passed, 78 assertions |
| `tests/Unit/Services/Tax/CombinedIncomeCardLabellingTest.php` (new) | 10 passed, 19 assertions |
| `tests/frontend/.../LetterToSpousePrint.test.js` (new, Vitest) | 10 passed |
| UserProfile + Tax + Stores + Estate families | **910 passed**, 2,908 assertions |
| Plans + Protection + Api + Architecture families | **844 passed**, 1 skipped (known) |

**Test design, against `tests/CLAUDE.md` §4 (all five variants read first):**

- **Equality between mechanisms**, not against remembered figures: the letter's totals are
  asserted equal to `CrossModuleAssetAggregator`'s answers. Cannot pass while the letter
  has arithmetic of its own; cannot be satisfied by hardcoding.
- **Collision** — nothing is symmetric. Splits are 40/60, 70/30 and 30/70. **The suite
  caught a collision in its own fixture**: a £200,000 mortgage made the owner's 40% of the
  debt and 40% of the property both £80,000, and one assertion silently passed on the
  other. Moved to £220,000 so every figure in the fixture is distinct.
- **Fixture** — carries the two shapes `peak_earners` lacks and therefore never exercised:
  a **non-mortgage liability**, and a shared account held as the **secondary** owner.
- **Decoy** — every file resolves and calls the class its title names.
- **Empty-result guard** — the liability case asserts the list is non-empty *and* still
  contains the mortgages, so a fix that empties it cannot pass.

**Mutation-tested in both directions — nine mutations, each reverted:**

| Mutation | Reddened | Left green |
|---|---|---|
| M1 savings items at 100% | secondary-owner case, section-sums case | totals, property, liability, prose (6) |
| M2 per-property mortgage from `outstanding_mortgage` | property prose case | 7 others |
| M3 `valueLine` always prints the whole | property prose case | 7 others |
| T1 label back to hardcoded `'Earned Income'` | the 3 mixed-label cases | "still says Earned Income", NI, net income (7) |
| T2 Class 1 `base` inflated to the combined gross | the 3 National Insurance cases | labels, net income (7) |
| F1 print builder back to `full_value` | 1 print case | 7 others |

`grep -c MUTATION` returns 0 in both files.

**Two mutations added for the later fixes**, same method, each reverted:

| Mutation | Reddened | Left green |
|---|---|---|
| M4 drop the account-type mapping | account-type case | 15 others |
| M5 ISA flag back to the `isa_type` spelling | ISA case | 15 others |
| M6 disable section regeneration | the 2 repair-path cases | 14 others |

**Note on what "asymmetric" is not sufficient to guarantee.** The fixture was built
deliberately asymmetric — 40/60, 70/30, 30/70 — and *still* produced a collision: at the original £200,000 mortgage, the owner's **40% of the debt** and the
owner's **40% of the property** were both £80,000, and an assertion about one silently
passed on the other. **Two different 40%s of two different bases can coincide.** The
requirement is not asymmetric splits but **mutually distinct figures**: every amount the
fixture can produce must differ from every other. Three collisions turned up in this
batch — that one, the stale-marker false positive in §2, and the £95,000 pair behind it.

**Rule 19.** `/m` has **no letter screen** and none of these labels — `IncomeDetail.vue`
shows "Adjusted net income", a different figure. No `resources/mobile/` file changed, so
**no `/m` bundle is required**; the new endpoint is surface-agnostic in any case.

**Browser — 2026-08-23, Playwright, localhost:8000, both accounts.**

**Identity established per surface from `GET /api/auth/user` on the token in use**, never
from a name and never from a figure. The tab was handed over described as authenticated as
nobody; it was **authenticated as Sarah Jones (17) on both token stores**. One password
attempt per account, both first time; MFA from `email_verification_codes`.

| | Sarah (17) | David (16) |
|---|---|---|
| Savings | £31,030 | £99,750 |
| Investments | £132,500 | £172,500 |
| Properties | £637,500 | £755,500 |
| Liabilities | £170,500 → **£122,500** | £170,500 |

Every card equals the endpoint's answer, fetched from the page in the same breath.
`£1,570,000` and `£365,000` appear nowhere. On David's letter:

```
Unit 12, Victoria Mill, Ancoats · Buy to Let · Tenants in Common
Your share £118,000  of £295,000   ·   Your mortgage share £48,000
```

**Sarah's property prose is populated** — the discriminating case, empty under the
primary-owner-only reach while her cards listed two homes.

**The exported document**, real click on Print / Save PDF, real handler, `print()` called,
**20,109 bytes**: property and liability sections at the user's share, £177,000 and £72,000
absent, "Tenants in Common" spelled out with no "TIC" anywhere. Sarah's verified the same
way.

**Two honest notes on method.** The popup calls `print()` and self-closes in one second, so
`window.open` was stubbed to capture what the application wrote — the click and the handler
are real; the stub is only how the artefact is read. The **first** stub lacked
`document.querySelector`, which `triggerPrint` calls: it threw, nothing was written, and the
button stuck at "Preparing…". Diagnosed from the console rather than reported as a broken
export. Separately, the **printed property card carries no mortgage line** where the screen
shows one — pre-existing and unchanged here; the £48,000 is in the document's Liabilities
section and in the prose.

**Income page, David:** *"Net Income (after tax and National Insurance, including tax
credits): £102,496"* with the note beneath, header **"Earned and Rental Income £159,290"**,
badge **"National Insurance on £145,000"**. Threshold Income £147,690 and **the praised copy
intact, word for word**. On Sarah, "National Insurance on £120,000" over a £128,880 card and
**no pension note**, correctly — she has no employee contributions.

**A console error chased down rather than reported as a find.** A
`Cannot read properties of null (reading 'toLocaleString')` render error was in the log; it
comes from the **`/dashboard`** render at session start. Clean navigations to the letter tab
and the income tab each produce **zero console errors**. Neither currency helper can throw it
— both guard null.

**What the browser could NOT settle, stated rather than presented as proof.** Sarah's
mortgage shares are **£32,500 and £90,000 — identical to David's**, and her property values
likewise, because both homes are 50/50. A reader consulting the wrong record returns the same
numbers on her data. The 40/60 Manchester property and the asymmetric Pest fixture are what
separate the hypotheses; her account confirms reach and rendering, not the share rule.

Screenshots: `W-0421-letter-properties-david.png`, `W-0422-net-income-label-david.png`.

## 8a. One pattern, three independent instances this cycle

> **For any shared record, the non-owning side is the untested side.**

Not an observation any more. Three instances, three modules, all found by looking at the
account that does *not* hold the record:

| Instance | What the non-owner saw |
|---|---|
| W-0384 / W-0401 (protection) | £0 total cover above the £500,000 policy covering her |
| W-0187 (liabilities) | none of the household debt, while the owner was charged all of it |
| **W-0421 (this)** | **an empty property section in her own letter, beside cards listing two properties** |

**And a fourth phantom-column instance:** `$property->property_use` is not a column on
`properties`. Read off a model it returns `null` silently, so the letter printed
"Use: Primary_residence" for every property including the buy-to-let. Same signature as
`db_pensions.transfer_value` and `mortgages.end_date` earlier in the cycle — *silent off a
model, fatal off a query builder.*

## 8b. The new endpoint is exactly as gated as the letter — which is less than it looks

Checked rather than assumed, because the route serves the household's whole balance sheet.
`CheckSubscription::CAPABILITY_ROUTE_MAP` maps `api/user/letter-to-spouse` to the
`letter_to_spouse` capability and `str_starts_with` covers the new path — so far so good.

**But `isExcludedPath()` runs first, and `READ_ONLY_EXCLUDED_PATHS` contains `api/user/`,
which returns early for any GET.** So the capability is **write-only in practice** and
`GET /api/user/letter-to-spouse` has never been read-gated either. Raised as **W-0426**;
it is a product decision about the letter, not about this route.

**Nothing here widens exposure** — the letter's own GET already returns the same figures
in its prose. The property that IS mine to guarantee is pinned in
`PremiumCapabilityEnforcementTest`: the financial position can never be *more* permissive
than the letter it belongs to. That case asserts **parity**, deliberately, rather than a
flat 403 — asserting a 403 would assert a behaviour the application does not have, and a
green test making a false claim is worse than no test (§4, Decoy).

**Why the existing suite never saw it:** every row in that test's dataset is a POST, a PUT,
or a GET outside `api/user/`. The dataset's shape and the exclusion's shape are
complementary, so the gap sits precisely where neither looks.

## 9. Files

**Backend**
- `app/Services/UserProfile/LetterToSpouseService.php` — `financialPosition()`, `positionItem()`, `valueLine()`; five generators rewritten onto it
- `app/Services/UserProfile/UserProfileService.php` — `calculateLiabilitiesSummary` public; false `net_income` comment corrected
- `app/Http/Controllers/Api/LetterToSpouseController.php` — `financialPosition()`
- `routes/api.php` — one route
- `app/Services/UKTaxCalculator.php` — `combinedIncomeLabel()`, `base` on both National Insurance breakdowns

**Frontend**
- `resources/js/components/UserProfile/LetterToSpouse.vue` — four reducers and the print `switch` deleted; `itemBadges()`, `isSharedItem()`, `positionItems()`; failed-load state
- `resources/js/components/UserProfile/TaxIncomeCard.vue` — `nationalInsuranceBadge`
- `resources/js/components/UserProfile/IncomeOccupation.vue` — `netIncomeLabel`, pension note
- `resources/js/services/letterService.js` — `getFinancialPosition()`

**Tests**
- `tests/Feature/UserProfile/LetterFinancialPositionTest.php` (new)
- `tests/Unit/Services/Tax/CombinedIncomeCardLabellingTest.php` (new)
- `tests/frontend/components/UserProfile/LetterToSpousePrint.test.js` (new)
- `tests/Feature/Stores/SavingsReadConsumerParityTest.php` — two reflection cases updated to the new generator signature

## 10. For whoever picks this up

- `financialPosition()` runs inside `getOrCreateLetter()` as well as on its own endpoint,
  so the letter page calls it **twice** per load. Correct, not cheap. Worth a request-scoped
  memo if the page is ever measured.
- `outstandingLiabilities()` / `outstandingLiabilityCount()` were **left alone** —
  `LetterEstateValidationService` (Estate, another agent's scope) reads them, and their
  primary-only reach is that checker's contract, not the letter's display.
- Two pre-existing ESLint errors in `LetterToSpouse.vue:1126` and
  `IncomeOccupation.vue:672,679` (unused `catch` bindings) are **untouched and pre-date
  this work** — confirmed against `HEAD`. Everything changed here lints clean.
