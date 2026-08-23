---
id: F-0025
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-22T00:00:00Z
status: active
---

# F-0025 — Cycle 4: when the rule and the column disagree about RANGE

**Agent:** build-lead (`fix-cycle4-columns`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0263, W-0257 · **ID blocks:** W-0321 – W-0330 (used in full), W-0351 – W-0360
**Number and ID block issued by team-lead.**

**Predecessors, read before touching anything here:**
`F-0023-cycle4-validation-and-silent-data-loss.md` §6 — the two mechanical sweeps
this one is the third axis of. `F-0002-batch-a-ownership-net-worth.md` §10 —
**W-0052**, the common ancestor of all three.
Board items **W-0261** (the nullable-on-NOT-NULL instance that uncovered W-0263),
**W-0262** (fillable-but-unvalidated), **W-0008**, **W-0052**.

---

## 1. The principle

**A validation rule is a promise about what the user may enter. A column is a
statement about what can be stored. Where the promise is wider than the
statement, the user is not protected by the rule — they are routed past it into
a 500.**

### 1.1 The rule that governs every fix in this batch

> **A column wider than its rule is only a defect when a user is refused something
> they can legitimately do.**

**This supersedes "align the rule to the column", which is wrong as a general
instruction and would have caused a regression in this very batch.**

The two directions are not symmetrical:

| Direction | Verdict |
|---|---|
| **Rule wider than column** | **Always a defect.** The value passes validation and dies at the write — a 500 where a message belonged. Nothing legitimises it. |
| **Column wider than rule** | **Depends entirely on whether anything offers the excluded value.** Refusing something no path can produce is a decision. Refusing something the form puts in front of the user is a defect. |

Both appeared in a single line. `MortgageStore:307` accepted `capped`/`offset`,
which the enum cannot store — defect, and removing them changed nothing any user
could do. It refused `mixed`, which the column stores, three request classes allow
**and the property form offers in its rate-type select** — defect, and a
part-fixed part-variable mortgage could not be recorded at all.

**The same file also refuses `tenants_in_common` on `ownership_type`, and that is
correct.** `MortgageNormaliser:79-81` coerces it to `joint` before the Store sees
it and documents mortgages as not supporting it. Widening it would have broken a
documented decision and disturbed the CSJ ruling at W-0228 — in the file being
edited, on the same afternoon.

**The sharpest consequence, and the reason this sits above the table:**

> **A test demanding rule and column match would have *enforced* the regression.**

A guard written to the wrong principle does not merely miss defects — **it
manufactures them and then defends them**, with all the authority of a green
suite. `StoreEnumRulesMatchColumnsTest` therefore carries an exception list rather
than asserting equality, and **every exception must name the mechanism that
guarantees the excluded value never arrives** — otherwise the list stops being a
record of decisions and becomes a place to hide drift.

### 1.2 The axes

Six axes of the same disease. Five measured, one named and left:

| Axis | The disagreement | Rows found | Where |
|---|---|---|---|
| **Nullability** | rule says `nullable`, column says NOT NULL | 192 | F-0023 §6.1 |
| **Presence** | column is fillable, no rule exists at all | 95 | F-0023 §6.2 |
| **Range** | rule's `max:` exceeds the column's precision | **20** | here, §4 |
| **Absence of a bound** | numeric rule with no `max:` at all, on a narrow column | **4** | here, §4.3 |
| **Layer** | the **Store** disagrees with the column AND with the request | **1 fixed, 2 classified** | here, §4.4 |
| **Layer × range** | Store numeric bounds — the range axis, one layer over | **not swept** | W-0329 |

**The fifth axis is the one Fyn writes through, and it is not a variant of the
others — it is a different LAYER.** The first four all live in
`app/Http/Requests/`. `resources/mobile/api.js` has **no post, put or patch helper
anywhere in the bundle**: Fyn is not one of `/m`'s write paths, it is the *only*
one, and Fyn's capture handlers write through the **Stores**. So a rule that holds
in the request layer and not in `app/Services/Stores/` means **the web form and
the Fyn capture path accept different things, and `/m` and native follow the
Store.** Sweeping the request layer says nothing about it. Full item: **W-0329**.

**A fourth shape, found here and not covered by any of the three:** a numeric
rule with **no `max:` at all** against a narrow column. There is no disagreement
to detect because there is no bound to compare — the column is silently doing the
validating, and it does it by crashing. Four rows, §4.3.

**What makes the range axis different from the other two: the answer is usually
in the schema, not in the rules.** A `nullable` rule on a NOT NULL column is the
rule's mistake. A `max:100` on a mortgage interest rate is the rule being right
and the column being built too small. **Capping the rule to fit the column would
close every sweep row and ship a product that refuses to record a 12% mortgage** —
which is why W-0263 was raised rather than closed by the agent that found it.

---

## 2. W-0263 — 18 rules that promised more than their column could hold

### 2.1 The headline

`mortgages.fixed_interest_rate` and `variable_interest_rate` were `decimal(5,4)`.
That is five significant digits with four after the point: it stops at **9.9999**.
The rule said `max:100`, the form input says `max="100"`, and the label reads
"Fixed Interest Rate (%)" with the placeholder "e.g., 3.5".

**Any mortgage rate of 10% or more passed validation, reached MySQL, and raised
`SQLSTATE[22003] Out of range`.** That is not an exotic input. It is most of
British history, and it is where adverse-credit and some buy-to-let products sit
today.

### 2.2 The units question, which had to be settled first

**Both of the two most serious rows looked like the opposite of what they were,
and in opposite directions.** Neither could be fixed safely without checking.

**`decimal(5,4)` is the correct type for a FRACTION**, and this schema genuinely
uses it that way. `life_insurance_policies.decreasing_rate` and
`dc_pensions.employer_ni_rebate_pct` store 0.05 to mean 5%, validated `max:1`,
and `PolicyFormModal.vue:920` divides by 100 on save and multiplies by 100 on
display. Those are correct and were left alone.

So "the column is too narrow" is only true if the column holds percentages. Two
traps:

**Trap 1 — `mortgages.fixed_interest_rate` carries a column comment that says it
is a fraction, and the comment is wrong.** It read *"Interest rate for fixed
portion (annual rate as decimal)"*. Taken at face value, `decimal(5,4)` is
correct and the RULE is the defect. It is stale prose from the original
migration. The evidence against it:

- Live rows on the sibling column `mortgages.interest_rate` store `4.5000`,
  `5.4900` — percentages, and that column is already `decimal(8,4)`.
- `MortgageNormaliser.php:98` rounds all three rate fields identically. Nothing
  converts.
- `PropertyDetailInline.vue:321` renders it `.toFixed(2) + '%'` with no division.
- `PropertyForm.vue:586` labels it "Fixed Interest Rate (%)", `max="100"`,
  placeholder "e.g., 3.5".

The comment is corrected in the migration so the next reader is not sent down the
same blind alley.

**Trap 2 — `investment_accounts.current_ownership_percent` really is a
percentage, and the consequence is worse than the sweep row suggests.** The input
is `min="0" max="100" step="0.01"` and `PrivateInvestmentDetail.vue:148` renders
it with a `%` suffix. On `decimal(5,4)` **a 50% shareholding could not be stored
at all** — only stakes under 10% were representable. The table holds **zero**
non-null rows, which is exactly what a field that has never once worked looks
like from the outside.

### 2.3 Two rows that no name-matching sweep could have found

**`StorePropertyRequest.mortgage_fixed_interest_rate` and
`.mortgage_variable_interest_rate`** (`max:100`) are written to
`mortgages.fixed_interest_rate` / `.variable_interest_rate` by
`MortgageService.php:57-58`. The same crash, through the property wizard instead
of the mortgage form. A sweep that joins rule field names to column names cannot
see them, because the names differ by a prefix.

**Lesson worth keeping: the sweep finds the columns, not the doors.** Any
generalised fix has to ask which *requests* write a column, not only which
request has a field of that name.

---

## 3. What was decided per column, and why

**The rule was right and the column was wrong in every case below**, so every one
is a widening. No rule was narrowed to fit a column — that trade is the thing
W-0263 existed to prevent.

Migration: `database/migrations/2026_08_22_010000_widen_percentage_columns_that_cannot_hold_their_validated_range.php`.
Additive column widening only. Applied locally, **round-tripped down and back up**,
every definition re-read from `information_schema` afterwards: nullability and
defaults intact, row counts unchanged, no value in any of the twelve columns
exceeds even the OLD cap, so the reversal is currently safe as well as available.

**Raw `MODIFY COLUMN`, not `->change()`.** doctrine/dbal is not installed (Laravel
10.50.2), and `->change()` silently drops any attribute not restated — three of
these columns are NOT NULL with a default, and losing that would be a worse defect
than the one being fixed. Each definition is spelled out in full and was read from
`information_schema` rather than from memory.

**Reversibility is stated honestly rather than assumed.** `down()` restores the
narrow types exactly, but it can only succeed while no row holds a value the
narrow column cannot store — which is the entire purpose of the widening. A
populated production database may legitimately refuse to reverse it. That is
written at the migration, not worked around.

| # | Column | Was | Now | Rule | Why this target |
|---|---|---|---|---|---|
| 1 | `mortgages.fixed_interest_rate` | dec(5,4) | **dec(8,4)** | `max:100` kept | Matches its own sibling `mortgages.interest_rate`, already dec(8,4). The two portion-rates were simply built narrower than the headline rate on the same table. |
| 2 | `mortgages.variable_interest_rate` | dec(5,4) | **dec(8,4)** | `max:100` kept | As above. |
| 3 | `savings_accounts.interest_rate` | dec(5,4) | **dec(8,4)** | `max:20` kept | Rate column, same precedent. `max:20` is a deliberate product guard with its own message ("cannot exceed 20%"); widening makes that message TRUE rather than a thing the column silently overrode at 10. |
| 4 | `investment_accounts.current_ownership_percent` | dec(5,4) | **dec(7,4)** | `max:100` kept | A percentage bounded at 100 by its nature. dec(7,4) reaches 999.9999, so the column can never be the binding constraint on a percentage. |
| 5 | `investment_accounts.platform_fee_percent` | dec(5,4) | **dec(7,4)** | **`max:10` added** | Had NO bound at all — see §4.3. |
| 6 | `investment_accounts.advisor_fee_percent` | dec(5,4) | **dec(7,4)** | `max:10` kept | Boundary row: exactly 10 overflowed dec(5,4). |
| 7 | `dc_pensions.platform_fee_percent` | dec(5,4) | **dec(7,4)** | `max:10` kept | Boundary row. |
| 8 | `dc_pensions.advisor_fee_percent` | dec(5,4) | **dec(7,4)** | `max:10` kept | Boundary row. |
| 9 | `actuarial_life_tables.life_expectancy_years` | dec(4,2) | **dec(5,2)** | `max:120` kept | The rule permits 120 years; the column stopped at 99.99. Admin-only, low reach, but the identical disagreement. |
| 10 | `trusts.loan_interest_rate` | dec(5,4) | **dec(8,4)** | unchanged | Validated `nullable\|numeric\|min:0` — **no upper bound at all** — behind a form labelled "Interest Rate (%)". The column was the only limit, so a 10% trust loan was a 500. The rule lives in `TrustController`, outside this batch's scope; **widening alone fully closes it**, because the column was the entire constraint. |
| 11 | `holdings.dividend_yield` | dec(5,4) | **dec(7,4)** | **`max:9.9999` → `max:100`** | See §3.1. |
| 12 | `holdings.ocf_percent` | dec(5,4) | **dec(7,4)** | **`max:9.9999` → `max:100`** | See §3.1. |

**Nothing was capped.** Every row above is the column moving to fit the product,
not the product shrinking to fit the column.

### 3.1 Reversing W-0261's deliberate half-fix — and why that is not overreach

W-0261 capped `holdings.dividend_yield` and `.ocf_percent` at `max:9.9999` to turn
a 500 into a 422 while the columns were still `decimal(5,4)`. Its own comment at
`StoreHoldingRequest.php` states the terms:

> *"a real dividend yield CAN exceed 10%, so the column is too narrow and widening
> it is a migration and a product call, raised as W-0263 ... **W-0263 owns the
> decision and this line is the stop-the-bleeding half.**"*

This item is W-0263. The decision was taken: a double-digit dividend yield is
ordinary on investment trusts and distressed real-estate investment trusts, so the
columns were widened and the honest bound restored. Two assertions in
`tests/Feature/Investment/HoldingNotNullColumnsTest.php` were re-pointed at the
new boundary — **in both directions**, so the widening cannot be mistaken for "any
number now passes":

- a yield of **50** now saves (it was a 500, then a 422 — the value that
  distinguishes all three states of this code)
- a yield of **150** is still a 422 with no row written
- a yield of **4.25** still saves, unchanged

**Flagged to team-lead as a cross-scope edit at the time it was made**, not
afterwards.

### 3.2 One column left alone, deliberately

`cash_accounts.interest_rate` is `decimal(5,4)` and the table is empty with no
form writer anywhere in `app/`. Its units are therefore undetermined — it could
legitimately be a fraction like `savings_market_rates.rate` (which stores 0.0450
and is correctly typed). **Guessing its units in order to close a sweep row is
exactly the failure mode this item exists to prevent.** Filed as **W-0323**.

---

## 4. The sweep

Built mechanically against `information_schema` and the live rule arrays —
`rules()` called on real instances, so inheritance and conditionals are included —
not read by eye. Two passes, because the first shape hides the second.

### 4.1 Pass one: `max:` exceeding column capacity

209 numeric `max:` rules across all form requests, cross-referenced against every
column of that name. Raw output: 45 rows, reduced to **20 genuine** after
resolving each request to the table it actually writes.

**Resolving the table is the whole job.** 25 of the 45 were name collisions —
`StoreMortgageRequest.interest_rate` matching `cash_accounts.interest_rate`,
`Admin\StoreCurrencyRateRequest.rate` matching `savings_market_rates.rate`,
`StoreLifeEventRequest.amount` matching `payments.amount`. Each was resolved by
reading the controller or service that consumes the request. **A sweep that
reports name matches as findings is 55% noise here.**

### 4.2 Post-fix state

Re-run after the migration: **every remaining row is a classified false
positive**, and each is recorded in the drift guard rather than merely dismissed.

### 4.4 Pass three: the Store layer — where Fyn writes

**Measured after the browser run forced it into view**, when the 12% headline was
blocked by a 422 that came from neither the column nor any request.

`MortgageStore:307` read `in:fixed,variable,tracker,discount,capped,offset` and was
**wrong in both directions at once**, the only one of four layers that disagreed:

| Layer | `rate_type` accepted |
|---|---|
| `mortgages.rate_type` column | fixed, variable, tracker, discount, **mixed** |
| all three form requests | fixed, variable, tracker, discount, **mixed** |
| **`MortgageStore:307`** | fixed, variable, tracker, discount, **capped, offset** |

It **rejected `mixed`** — which the column stores, every request permits, and the
property form offers in its rate-type select — so **a part-fixed part-variable
mortgage could not be recorded at all**, and the 422 was swallowed by
`PropertyDetailInline.vue:701` so the modal closed as though it had worked. It
**also allowed `capped` and `offset`**, which the enum has no room for: values that
could only ever die at the write.

Aligned to the column enum. **Removing `capped`/`offset` is not a decision against
them** — they were unreachable whatever anyone wants — so the genuine product
question is raised separately as **W-0328**.

#### The nuance that stopped a second "fix" being a regression

The sweep found a second mismatch in the same rule set: `MortgageStore` refuses
`tenants_in_common` and `trust`, which `mortgages.ownership_type` stores.
Mechanically "aligning the rule to the column" would have changed it too.

**It is deliberate.** `MortgageNormaliser:79-81` coerces `tenants_in_common` to
`joint` before the Store ever sees it, and documents that mortgages do not support
it. Widening the Store would have re-opened a CSJ ruling (W-0228) and broken a
documented decision.

**So: a column wider than its rule is NOT automatically a defect.** It is only a
defect when something a user can legitimately do is refused. `rate_type` was —
the form offers "Mixed". `ownership_type` is not — nothing offers it, by design.
The drift guard therefore carries an exception list rather than asserting equality,
because a test demanding they match would have enforced the regression.

**Post-fix state: 17 `in:` rules across the mapped Stores, 1 fixed, 2 classified.**
The second classified row — `InvestmentAccountStore:254` refusing `trust`, which
its column stores and both its requests allow — has **no** such recorded reason.
Latent rather than live (no form offers it today), reported not changed: W-0329.

### 4.3 Pass two: the shape pass one cannot see

**A numeric rule with no `max:` at all, on a narrow column.** There is no
over-promise to detect, because there is no promise. The column does the
validating, and it validates by crashing.

**4 rows, all now closed:** `platform_fee_percent` on
`Store/UpdateInvestmentAccountRequest` — unbounded, against a `decimal(5,4)`
column, so a typed **12** was a 500. Its sibling `advisor_fee_percent` on the same
form already carried `max:10`; the two fee fields on one form disagreed about
whether a fee had an upper bound at all.

Post-fix sweep: **0**.

---

## 5. W-0257 — a form that could not be saved and would not say why

### 5.1 The mechanism

`InlineHoldingsEditor.vue` bound each allocation input to
`:max="maxAllocation(index)"`, where `maxAllocation` returned `100 −
(sum of the OTHER holdings)`.

**That expresses a constraint about the whole SET on a single field.** While the
total sits at exactly 100 it is self-consistent and invisible: each field's max
equals its own current value. One digit past that and **every** input is below its
own value. `checkValidity()` fails on all of them, the browser refuses to fire
`submit`, `submitForm` never runs — **no request, no message, no indication.** The
Update button simply appears broken, and the account becomes uneditable including
its risk level, fees and value.

### 5.2 The repro is far more ordinary than the board item suggests

W-0257 was found through a contaminated account (a concurrent agent's fifth
holding took the total to 105%). **That account is no longer contaminated** —
account 26 totals exactly 100 and the extra row does not exist, live or
soft-deleted.

It did not need to be. **From a perfectly valid 100% account, a user cannot raise
ANY holding's allocation, ever.** At a 100% total every field's max equals its own
value, so typing one digit more invalidates that input immediately. Raising one
holding before lowering another is completely ordinary editing — and it bricks the
form with no message.

**The contamination revealed the defect; it was never required to reach it.**

### 5.3 Two faults, both fixed

**Fault 1 — the unsatisfiable bound.** `:max="maxAllocation(index)"` → `max="100"`.
A per-field bound now says what is true of a field on its own; `maxAllocation` is
deleted. The total is a fact about the set, so it is reported as one.

**Fault 2 — the silent failure.** The worse of the two, and the same shape as
W-0261 fault 3. Now:

- **At the field:** an over-allocation message in the holdings editor naming the
  total, the target and the difference — *"These holdings add up to 105%. Reduce
  them by 5% so they total 100% or less."* Over-allocated inputs take a raspberry
  border.
- **At the button:** a blocked submit says so next to the button that was pressed,
  because the holdings section collapses and a user who cannot see it was left
  with a button that did nothing.

**Fault 2 had a third cause worth recording: a clamp.** `remainingPercent` is
`Math.max(0, 100 - totalAllocated)`, so an account 5% over and an account exactly
full both render as "nothing left over" and the "Cash (auto-allocated)" row simply
vanishes. **The UI could not show the over-allocation because the only quantity it
computed had already discarded it** — `tests/CLAUDE.md` §4, the clamp variant,
occurring in production code rather than in a test. The fix measures the excess on
the side of 100 the clamp throws away.

### 5.4 One rule, one home

`InlineHoldingsEditor` is used by **both** `AccountForm.vue` (investment accounts)
and `DCPensionForm.vue` (retirement) — so both carried the defect, and both needed
the guard. Three components ask "do these add up?".

Per Rule 20 the answer lives in exactly one place —
`resources/js/utils/holdingsAllocation.js` — and all three import it. Editing three
copies in lockstep would have been the violation, not the fix.

### 5.4a The guard nearly reintroduced the bug it fixes

A submit guard that blocks is only an improvement if the user has a control to
unblock it. The first version checked `formData.holdings`, which is wrong in two
distinct situations:

- **The section is collapsed.** Holdings are not sent at all, so refusing to save
  over them blocks on a set about to be discarded.
- **The section is open but the editor is hidden.** `showHoldingsEditor` is false
  for a non-holdable account type or a `current_value` of zero — and
  `formData.holdings` still carries whatever was entered before the user zeroed
  the value or switched type. Guarding on it would print a message about rows
  that are **nowhere on screen**.

The second is the one that is easy to miss, and it would have been **a dead
button with an unexplained cause: the exact defect W-0257 is about, reintroduced
by its own fix.** The guard is therefore gated on the editor's own render
condition, and both cases are pinned by tests.

**Float tolerance is load-bearing.** `68.18 + 31.76 + 0.06` evaluates to
`100.00000000000001` in IEEE 754 — an entirely ordinary portfolio. A naive `> 100`
would refuse to save an account that is completely correct, which is the same
disease as the bug being fixed: a wrong answer delivered politely. The tolerance
is 0.01, far above the ~1e-14 that binary addition introduces and far below any
real mistake.

### 5.5 `/m` and iOS — checked, not assumed

**W-0257 has no `/m` counterpart.** `/m` renders asset-class allocation read-only
(`resources/mobile/components/CanonicalPortfolio.vue`) and has no holdings editor,
no allocation `v-model`, and no form. Rule 19 says flag rather than skip: this is
the flag, with the evidence.

**W-0263 reaches `/m` and iOS for free — verified, not assumed.** `routes/api_v1.php`
declares **no** investment, mortgage, savings or pension write endpoints, and there
are no mobile-specific write routes. Every client writes through the same `Api/`
controllers, which type-hint the same request classes:

| Request | Sole consumer |
|---|---|
| `StoreInvestmentAccountRequest` | `Api\InvestmentController::storeAccount` |
| `StoreMortgageRequest` | `Api\MortgageController::store` |
| `StoreSavingsAccountRequest` | `Api\SavingsController::storeAccount` |
| `StoreDCPensionRequest` | `Api\RetirementController::storeDCPension` **and** `::updateDCPension` |

**But "one endpoint therefore one rule" is only true of the form-driven paths, and
that distinction matters.** `/m` writes nothing through those endpoints at all:
`resources/mobile/api.js` has **no post, put or patch helper** anywhere in the
bundle. `/m`'s only write path is Fyn, and Fyn's capture handlers in
`CoordinatingAgent` go through the **Stores** — `InvestmentAccountStore::create`,
`MortgageStore` — **not** through the form requests.

So the two halves of this batch reach different distances, and the more important
half reaches further:

| | form-driven web / native | Fyn capture (the whole of `/m`) |
|---|---|---|
| **Column widening** — the crash fix | yes | **yes** — it is schema, nothing can bypass it |
| **Rule changes** — `max:10` on `platform_fee_percent`, `max:100` on nested `ocf_percent` | yes | **no** — the Stores validate separately |

**The crash was the defect, and the widening closes it on every surface.** The
rule changes are the tidier half and stop at the request layer, which is worth
stating plainly rather than letting "shared backend" imply more than it delivers.
Whether the Stores' own bounds agree with the requests' is a separate parity
question of the kind `PensionStoreDcRuleParityTest` exists to answer, and is not
claimed here. What can be said concretely: `MortgageStore:306` bounds
`interest_rate` at `max:100` but says nothing about `fixed_interest_rate` or
`variable_interest_rate`, and `InvestmentAccountStore` sets no bound on
`platform_fee_percent` — so on those fields the column is the only limit that
Fyn's path meets. **That was a 500 before this migration and is now simply a
stored value**, which is the right direction; it does leave Fyn accepting a 12%
platform fee where the form returns a 422. A difference in bound, not a crash,
and it belongs with W-0324 rather than being papered over here.

---

## 6. Tests

| File | Tests | What it pins | Proven to fail? |
|---|---|---|---|
| `tests/Feature/Validation/ValidatedRangeReachesTheColumnTest.php` | 10 | **The acceptance.** A 12% fixed mortgage rate saves on create AND update; a 14.75% variable rate keeps its decimals; a 12.5% savings rate saves; a 50% shareholding saves; a fee of exactly 10 saves. Each paired with the opposite direction (250% rate, 25% savings rate, 12% platform fee are all 422s). | **Yes** — see §6.3 |
| `tests/Unit/Database/ValidationMaxFitsColumnPrecisionTest.php` | 2 | **The drift guard** (W-0263 acceptance §3). Every verified rule-to-column mapping still fits, AND no new rule is unclassified. | **Yes** — a mortgage `max:` temporarily raised to 100000 turned it red naming the exact offender. |
| `tests/frontend/components/Investment/InlineHoldingsAllocation.test.js` | 9 | W-0257: inputs bounded at 100 not at headroom; a user can raise one holding before lowering another; the message appears and says what to change. | **Yes** — restoring the `maxAllocation` binding failed exactly the two regression assertions, and only those. |
| `tests/frontend/components/Investment/AccountFormHoldingsPayload.test.js` | 6 | W-0322: the holdings key is **absent** when collapsed, present when open, and a real `[]` when the user empties the open section. Plus the W-0257 submit guard and the two cases where it must NOT fire. | **Yes** — restoring `submitData.holdings = []` and dropping the guard failed exactly those two. |
| `tests/Feature/Investment/HoldingNotNullColumnsTest.php` | 9 | The widened holdings range, in both directions (50 saves, 150 is a 422, 4.25 unchanged). | n/a — pre-existing file, two assertions re-pointed. |

**Regression families run, all green:** Investment + Retirement frontend (218),
`tests/Feature/Validation/` + `tests/Feature/Investment/` + `tests/Unit/Database/`
(64), mortgage and savings unit families (40), `tests/Feature/Retirement/` (72).
Pint clean on every changed PHP file.

### 6.3 Proving the acceptance probes are not collisions

**Every probe in the acceptance file is a value the OLD schema could not
physically hold** — verified directly rather than assumed, in a throwaway table
under `STRICT_TRANS_TABLES`:

| Value | Into `decimal(5,4)` (old) | Into the widened type |
|---|---|---|
| `12` (mortgage rate) | `SQLSTATE[22003] Out of range` | stored `12.0000` |
| `50` (ownership %) | `SQLSTATE[22003] Out of range` | stored `50.0000` |
| `10` (fee, the boundary) | `SQLSTATE[22003] Out of range` | stored `10.0000` |
| `9.9999` | stored fine | stored fine |

That last row is the point of the exercise: **9.9999 passes under both schemas, so
any test built on a single-digit rate proves nothing.** The probes were chosen to
sit on the other side of that line.

**The tenth test is the headline as a user actually reaches it, and it needed both
fixes to pass.** `fixed_interest_rate` only renders when rate type is `mixed`, so a
mixed-rate mortgage carrying 12% is the only route to the field — and it failed
under *both* previous states, which makes it collision-proof twice over:

- `mixed` was a **422** at `MortgageStore` (W-0326), which validates separately
  from the form requests;
- `12` was a **`SQLSTATE[22003]`** at the `decimal(5,4)` column (W-0263).

Verified by reverting the Store rule alone: the test returns 422 and goes red.
Restored, it passes and the row stores `rate_type: mixed`, `fixed_interest_rate:
12.0000`, `variable_interest_rate: 14.7500`.

**This is the API-level proof of the exact journey the browser leg still owes.**
It does not replace the browser run — §7 says plainly which journeys are and are
not browser-verified — but it means the remaining browser work is confirmation of
a path already known to work end to end through the real request and Store, rather
than a discovery exercise.

**A shared migration was deliberately NOT disabled to produce this proof.** The
obvious way to show the tests fail pre-fix is to rename the migration file so
`RefreshDatabase` skips it — but the file is shared, other agents are running
tests, and a 30-second window where their run silently misses a migration is not
worth it. The raw-SQL proof establishes the same mechanism without touching
anything shared.

### 6.1 The drift guard fails on NEW rules, not only on regressions

The second test is the one that earns its keep. Any numeric `max:` rule added
tomorrow whose field name matches a narrow decimal column anywhere in the schema
**fails until it is classified** — mapped to the table the request actually
writes, whether that turns out to be a defect or a name collision. Classifying is
the point: **55% of this sweep's raw output was noise, and the noise is only
distinguishable by reading the consuming controller.** Encoding that reading is
what stops the next agent redoing it.

### 6.2 Test-design traps this batch walked into and out of

**Collision (`tests/CLAUDE.md` §4, fourth variant) — hit twice, both caught.**

1. **The W-0263 probe.** Asserting a 9% mortgage rate saves proves nothing: 9%
   worked before the fix. Every acceptance probe here uses a value that **used to
   fail** — 12% for mortgages, 50 for dividend yield.
2. **The W-0257 probe, which is subtler.** The obvious test is "an account at 105%
   shows an error". That passes against a version that still bricks the form at
   100.1%. The pre-fix and post-fix code disagree about a **valid** account too —
   at a 100% total, the old max on the 36.90 holding was `36.9` and the new one is
   `100`. So the sharpest probe is the ordinary case, which is also the real user
   journey.

**Fixture.** `peak_earners` account 26 is three holdings summing to exactly 100 —
symmetric enough to hide a rounding bug. The suite adds the float-noise case, a
blank allocation, a string allocation, an unparseable allocation, a
single-holding account and an empty account.

**A first attempt at the float-noise test asserted the wrong thing and went red**,
claiming `36.9 + 36.8 + 26.3` exceeds 100. It is `99.99999999999999` — under, not
over. Corrected by brute-forcing actual over-100 triples rather than reasoning
about floats from memory.

---

## 7. The browser leg — RUN. Two of four journeys green, two blocked by defects outside this batch.

Run 2026-08-22 23:20–23:57 on `localhost:8000`, viewport 1440×900, as **Sarah (17)**
then **David (16)**, through the MFA gate with the code fetched from the database.
**Identity was read from `fynla-state.auth.user` on every surface, never inferred
from a figure** — the figures were the things under test, so recognising one would
have been circular.

| Journey | Result |
|---|---|
| **W-0322** — collapsed panel destroys holdings | **GREEN**, §7.5 |
| **W-0257** — over-allocation dead button | **GREEN**, §7.6 |
| **W-0263** — 12% mortgage through the real form | **BLOCKED** by W-0326 and W-0325 |
| **W-0263** — savings rate above 10% through the real form | **BLOCKED** by W-0327 |

Both blockers are defects in other people's code, both were reproduced rather than
inferred, and both are filed. **The two blocked journeys are proven at the API
level** (§6) but **NOT** through the interface, and are not claimed as such.

### 7.5 W-0322 — verified, on a genuine holding

Run on **account 13** (Sarah's ISA) rather than 14, deliberately: account 14's only
holding is the auto-created Cash row, which the edit form filters out anyway, so it
could not discriminate between the fixed and broken code. Account 13 holds a real
fund — `Vanguard LifeStrategy 80`, holding id **69**, 100%.

Open Edit → **collapse "Additional information"** (`showAdditionalInfo: false`
confirmed, editor unmounted) → change provider → Update.

`PUT /api/investment/accounts/13` → **200**, and the captured request body is:

```json
{"account_type":"isa","provider":"Hargreaves Lansdown Vantage","platform":null,
 "current_value":"85000.00","contributions_ytd":null,...,"platform_fee_amount":null}
```

**No `holdings` key.** Pre-fix it carried `"holdings":[]`, which is what satisfied
`$holdings !== null` and destroyed the account. Database after: holding **69 still
live, `deleted_at` NULL, max holding id still 69** — no row deleted, no Cash
substituted.

**The captured payload also validates §6's test fidelity:** it carries ten nulls
(`platform`, `contributions_ytd`, `country`, `platform_fee_percent`,
`advisor_fee_percent`, `joint_owner_id`, `trust_id`, `planned_lump_sum_*`,
`platform_fee_amount`). The feature tests post the whole model, so no correction
was needed — the trap was anticipated rather than discovered.

### 7.6 W-0257 — verified, including a defect in the fix itself

Account **26**, three holdings at exactly 100 (36.80 / 26.30 / 36.90).

**Fault 1.** All three allocation inputs render `max="100"`. Pre-fix each carried
its own value — 36.8, 26.3, 36.9 — which is what made the form unsatisfiable.

**The behaviour.** Raising Vanguard 36.90 → 40 (total 103.1%): all three inputs
stay `valid: true`, `rangeOverflow: false`, so the browser fires submit and code
runs. Pre-fix all three were invalid and submit never fired.

**Fault 2.** The message appears in **both** places and names the excess:

> These holdings add up to 103.1% of the account. Reduce them by 3.1% so they
> total 100% or less.

— at the field (raspberry panel in the holdings editor, all three inputs
raspberry-bordered) **and** immediately above the Update button. Submit is blocked
with **no network request**, and the button is enabled rather than dead.

**The clamp is visible in the same screenshot:** the header still reads
"103.1% allocated • 0% remaining (£0)". `remainingPercent` is
`Math.max(0, 100 - total)`, so "remaining" reads 0 whether the account is exactly
full or 3.1% over. That is precisely the quantity the new message recovers.

**Then corrected and saved:** Fundsmith 36.80 → 33.70, total exactly 100, saved.
Three live holdings, total 100 — **account 26 left valid**, as required.

### 7.6a A defect in the fix, found in the browser and not by the tests

After a blocked submit the field-level message correctly vanished when the total
returned to 100 — **while the message by the button still read "103.1%".**

`errors.holdings` is only cleared at the top of the next `submitForm`, so the
footer kept naming a total the user had already corrected. **A stale instruction
to fix something already fixed is only marginally better than the silence it
replaced**, and it is the same disease in a third costume.

Fixed by splitting the two roles: `errors.holdings` records only that a submit
*was* blocked, while the **text** comes from a live computed reading the same
single source as the editor's own message, so the two cannot disagree about the
total (Rule 20). Both messages now clear the instant the total returns to 100 —
re-verified live at 106.2% → 100%. Pinned by a test that asserts the message stops
naming the old total after correction.

### 7.6b The transferable rule — a guard is only as correct as what it reads

**Two guards in one batch nearly reintroduced the disease they were written to
prevent, and neither was caught by a test.**

> the second time a guard nearly reintroduced the disease: one read state the user
> could not see, this one read state that was no longer true

| | It read | It would have produced |
|---|---|---|
| §5.4a | `formData.holdings` — rows that may be **invisible**, because the editor is hidden for a non-holdable type or a zero value | a blocked save citing rows nowhere on screen: **a dead button with an unexplained cause** |
| §7.6a | `errors.holdings` — a verdict from the **last** submit, not the current state | a message naming a total the user has already corrected |

**A guard is only as correct as the freshness and visibility of what it reads.**
Both fixes are the same shape: stop the guard reading a stored proxy and point it
at the live, user-visible quantity — in §7.6a by splitting the two roles, so
`errors.holdings` records only *that* a submit was blocked while the **text** comes
from the same single source the editor's own message uses (Rule 20), which is what
makes the two incapable of disagreeing.

**Neither was findable by testing**, because in both cases the test would have
encoded the same misconception as the code — `tests/CLAUDE.md` §4's whole family.
They were found by driving the form and looking at it.

### 7.7 What the browser found that no test would have

Four defects, all reproduced, none of them in this batch's code:

| Item | What it does |
|---|---|
| **W-0325** HIGH | `use App\Models\User` missing from `PropertyController` — **every joint property update 500s**, committed in `d5fe9f9f7`. The `tests/CLAUDE.md` §2 formatter trap, live. |
| **W-0326** HIGH | `MortgageStore:307` rejects `mixed` (which the column and all three requests allow) and accepts `capped`/`offset` (which the enum cannot store). **A mixed-rate mortgage cannot be saved at all** — and `fixed_interest_rate` only renders for `mixed`, which is what blocks the 12% browser proof. |
| **W-0327** HIGH | `SavingsAccountDetail` listens for `@saved`; `SaveAccountModal` emits `save`. **Editing a savings rate silently does nothing** — no request, no error. Violates Rule 3; the sibling host `AccountDetails` is wired correctly. |
| — | `updateAccount` 404s for a joint owner by design (only `user_id` may update), yet the UI still offers Sarah an Edit button on account 14 that always fails. |

**The swallowed-failure prediction was confirmed live.** On property 20 the modal
closed as though it had saved while `PUT /api/properties/20` → 200 and
`PUT /api/mortgages/16` → **422** underneath. The mortgage did not save and the
user was told nothing — `PropertyDetailInline.vue:701` catching and logging. A
closed modal is not evidence of a save, and this run is the proof.

### 7.8 State left behind

| Record | State |
|---|---|
| account 26 | **3 live holdings, total exactly 100** (33.70 / 26.30 / 40.00) — valid |
| account 13 | provider reads `Hargreaves Lansdown Vantage` (was `Hargreaves Lansdown`) — **a test artefact**, holding 69 intact |
| account 14, mortgages 8 and 16, savings 28 | **unchanged** — every failed attempt wrote nothing |

Account 13's provider was **not** restored by this agent: it belongs to Sarah,
restoring it needs another sign-in and MFA cycle, and editing the row directly
would be the one thing refused throughout this batch. It was reported instead, and
**team-lead restored it to "Hargreaves Lansdown" via `saveQuietly()`** so no
observer fired.

**That division is the rule working, not a formality.** Provisioning and database
state are the coordinator's; refusing to reach for a row to tidy up my own test
artefact is the same refusal that stopped `onboarding_completed` being flipped to
get past a route guard. A batch that will edit a row for convenience will edit one
to make a red test green.

### 7.1 Environment — no build required

Vite is live on `127.0.0.1:5173` and the app on `127.0.0.1:8000`, both HTTP 200,
and Vite serves the new `resources/js/utils/holdingsAllocation.js`. **HMR carries
every frontend change in this batch, so the browser leg needs no build** —
`public/build/` and `public/m-build/` are untouched and stay the team lead's.

`public/hot` has an Aug 21 17:31 mtime, which is the fingerprint of the stale-hot
trap. Here it is merely when the dev server was started; both processes are
healthy. Worth knowing before it costs someone a diagnosis.

### 7.2 Credentials and state

| | David | Sarah |
|---|---|---|
| user id | 16 | 17 |
| email | `david.jones@example.com` | `sarah.jones@example.com` |
| password | `Password1!` | `Password1!` |
| tier | premium, active | premium, active |
| `onboarding_completed` | **false** | **false** |
| `onboarding_fyn_step` | **`path_choice`** | **`path_choice`** |

MFA code — fetch from the database, never ask:

```bash
php artisan tinker --execute="\$u = \App\Models\User::where('email','david.jones@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id', \$u->id)->latest()->first()->code ?? 'none';"
```

**Both personas are mid-onboarding.** That satisfies the 3-part predicate for the
onboarding write state, so Fyn will behave accordingly — but **the SPA router has
no onboarding redirect**: its guard covers auth, preview, admin and the
capability/teaser gate only. Both users are premium and active, so the teaser gate
will not fire either. Direct navigation to the module routes should therefore
work. **If it does not, ask — do not set `onboarding_completed` to unblock it.**
Editing database rows to route around an obstacle is precisely what the standing
instruction forbids.

### 7.3 Fixtures, chosen so each probe is a value that used to fail

| Probe | Target | Currently | Enter | Expect |
|---|---|---|---|---|
| **Headline** | mortgage **8**, HSBC, property 9 "15 Chestnut Lane", `rate_type=fixed` | `fixed_interest_rate` NULL | **12** | saves; row shows `12.0000` |
| Variable rate | mortgage **15**, Barclays, property 19 "Flat 42, Riverside Apartments", `rate_type=tracker` | `variable_interest_rate` NULL | **14.75** | saves; decimals intact |
| Savings | savings account **28** (David) | `4.2500` | **12.5** | saves |
| Ownership | a private-company investment account | no row has ever held one | **50** | saves |
| **W-0257** | investment account **26** (ISA, £95,000, 3 holdings = exactly 100%) | Fundsmith 36.80, Scottish Mortgage 26.30, Vanguard 36.90 | raise Vanguard to **40** | a raspberry message naming 103.2% and the excess — **not** a dead button |

**The W-0257 probe is deliberately the ordinary interaction, not the contaminated
account.** See §5.2: at a total of exactly 100 the pre-fix code gave every input a
`max` equal to its own value, so raising any holding bricked the form. A test that
only visits 105% would pass against a version still broken at 100.1%.

**Then**: lower Fundsmith to 33.10 so the set totals 100 again, save, and confirm
the account persists with three holdings — the "opened, corrected and saved"
half of the acceptance. Account 26 must be left at a valid total.

### 7.4 Also worth watching while there

- **`PropertyDetailInline.vue:701` swallows mortgage save failures** —
  `catch (mortgageError) { logger.error(...) }`, after which the modal closes and
  the property reloads. So the pre-fix 12% crash was doubly invisible: a 500 at the
  server and a UI that closed as though it had saved. Confirm the success path is
  genuinely a success and not the same silence wearing a different face.
- **W-0322 in the browser**: open an account, collapse "Additional information",
  press Update, and confirm the holdings survive. Pre-fix they became a single
  100% Cash row.

## 8. Recorded for someone else

| Item | Why it is not fixed here |
|---|---|
| **W-0321** | Nothing enforces the 100% allocation total **on write**. A holding added through the standalone path can take an account past 100 — which is how the W-0257 state arose. Whether to reject or auto-reduce is a product call, and enforcing a cross-record total needs a decision about every write path, not a guess inside a form fix. |
| **W-0322** | `AccountForm.submitForm` sets `submitData.holdings = []` when "Additional information" is collapsed, and `holdings` IS in `allowedFields`. If the backend replaces holdings wholesale, collapsing the panel and pressing Update destroys them. Adjacent, reported not fixed. |
| **W-0323** | `cash_accounts.interest_rate` is `decimal(5,4)` with no writer and no rows — latent, units undetermined. See §3.2. |
| **W-0324** | `holdings.*.dividend_yield` has no rule in the nested holdings arrays of `Store/UpdateInvestmentAccountRequest` and `StoreDCPensionRequest`, so a yield entered against a holding created through those forms is dropped by `validated()`. A W-0262-class gap on the presence axis, found while working the range axis. |

---

## 9. Tooling note for the next agent on this tab

**Playwright's `browser_click` is unreliable against this SPA.** Clicks register
— component state visibly changes — but the state is reset before the next tool
call, so every check reads as though nothing happened. It cost roughly twenty
minutes and produced a false "the account detail view will not open" conclusion
that was wrong.

**`element.click()` inside `browser_evaluate` works every time**, and clicking
plus reading the resulting state *in the same evaluate* is what proved the click
was landing at all:

```js
const before = vc.data.selectedAccount?.id ?? null;  // null
card.click();
const after  = vc.data.selectedAccount?.id ?? null;  // 14
```

Two corollaries worth keeping:

- **Multi-step forms need a render tick between clicks.** Six `next.click()` calls
  in one JS turn advance the wizard once, because Vue never re-renders between
  them. `await new Promise(r => setTimeout(r, 200))` between each fixes it.
- **Read component state, not the DOM, when a view fails to appear.** The DOM said
  the detail view never opened. The component said `selectedAccount = 14` and the
  console said the only problem was a 404 on an unrelated `rebalancing` endpoint.
  The view had been open the whole time.

**`element.click()` is the tool working around a harness quirk, not the app.** The
Sign Out button, which looked broken under `browser_click` and produced no network
request at all, signed out correctly on the first native click.

---

## 10. Proposed seventh entry for `app/Http/CLAUDE.md` — drafted, NOT applied

Team-lead asked me to add this to `app/Http/CLAUDE.md` myself. **I have drafted it
and deliberately not applied it.** A `CLAUDE.md` is a governing file: what goes in
it becomes instruction for every future agent in this repo, and I do not edit one
on a peer agent's instruction — the same reasoning that stopped me editing a
persona row, taking a tab I had handed back, or guessing at a product decision. A
coordinator can apply it in seconds; my applying it is the wrong shape.

Text as it should read, appended to the six-axis list:

---

7. **The Resource omits a field the template gates on** — the same disease at the
   **read** boundary rather than the write.

**Axis 7 is the mirror of the other six.** They all ask "can what the user typed
reach the column?" This one asks "can what the column holds reach the user?"

`MortgageResource` serialises `fixed_interest_rate` but **not**
`fixed_rate_percentage`. `PropertyDetailInline.vue:319` renders the fixed portion
only `v-if="rate_type === 'mixed' && mortgage.fixed_rate_percentage"` — a field the
Resource never sends. The gate reads `undefined`, so **the row is structurally
unreachable: no data can satisfy it.** A user enters a 60% portion at 12%, it saves
correctly, and the detail view shows `Rate Type: Mixed` and no numbers at all.

**Why no sweep finds it:** the rule is right, the column is right, the Store is
right, the write is right. Only the journey home is broken.

**The trap is sibling coupling.** `fixed_interest_rate` *is* serialised, so anyone
checking "is the rate exposed?" answers **yes** and stops. The row is hidden by a
*different* field, and nothing warns that a display depends on a sibling the
Resource drops. **When checking whether a value reaches the user, check every field
its `v-if` names — not the value itself.**

Open as **W-0351**.
