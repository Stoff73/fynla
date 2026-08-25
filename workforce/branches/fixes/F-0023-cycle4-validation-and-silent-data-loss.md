---
id: F-0023
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-22T00:00:00Z
status: active
---

# F-0023 — Cycle 4: the validation layer as a source of data loss

**Agent:** build-lead (`fix-cycle4-validation`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0261, W-0262, W-0242 · **ID block:** W-0261 – W-0270
**Number and ID block issued by team-lead.**

**Predecessors, read before touching anything here:**
`F-0002-batch-a-ownership-net-worth.md` §10 — **W-0052**, the direct ancestor. It
established the `NOT_NULL_WITH_DEFAULT` mechanism, the drop-the-null decision, and
the schema-drift test. This batch is the third instance of that bug and the second
generalised fix of it.
Board items **W-0008** (the rule that caused W-0052), **W-0009** (a payload
discarded on edit), **W-0026** (validated but not fillable — the same mismatch in
reverse), **W-0241** (the Defined Benefit exclusion ruling W-0242 implements).

---

## 1. The principle

**The validation layer is not a filter in front of the write. It IS the write's
allowlist, and every disagreement between it and the schema below it is either a
500 or a silently discarded field.**

Three defects, one sentence apart:

| | The rule says | The column says | What the user got |
|---|---|---|---|
| **W-0261** | `dividend_yield` is `nullable` | NOT NULL DEFAULT `0.0000` | a raw `INSERT` statement on the page |
| **W-0262** | `risk_preference` — *no rule at all* | a perfectly good enum column | a save that reported success and lost the field |
| **W-0242** | — | `db_pensions.transfer_value` does not exist | a 500 from a query builder |

The first two are the same mistake in opposite directions: **a rule that admits
something the column cannot hold, and no rule for something the column holds
happily.** `validated()` is what makes both fatal — it passes exactly the keys with
rules and drops the rest, so an over-permissive rule reaches the database and a
missing rule never arrives.

**The lists at §6 are the deliverable, not the three fixes.** Each of the three is
a handful of lines. What they are worth is the measurement of how many more of each
are sitting there — which is the lesson W-0052 wrote down and this batch is the
proof of, because it happened twice more anyway.

---

## 2. W-0261 — an "(Optional)" field that was mandatory

### 2.1 What the user did

Filled Add Holding, left **"Dividend Yield % (Optional)"** blank exactly as its
label invites, submitted, and the page rendered:

```
SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'dividend_yield'
cannot be null (Connection: mysql, SQL: insert into `holdings` (`asset_type`,
`sub_type`, `security_name`, `ticker`, `isin`, `allocation_percent`, …
```

Three faults, fixed as three, because they fail independently.

### 2.2 Fault 1 — the `nullable` rule on a NOT NULL column

`holdings.dividend_yield` and `holdings.ocf_percent` are both
`decimal(5,4) NOT NULL DEFAULT '0.0000'`. Both are validated `nullable` in
`StoreHoldingRequest:44-45` and `UpdateHoldingRequest:44-45`.
`HoldingForm.vue:374` initialises `dividend_yield: null`, so the blank field is sent
as an explicit null, survives validation, and reaches a column that refuses it.

**`ocf_percent` carried the identical latent 500.** Nothing but the tester's choice
of test data — they filled in 0.95 — stopped it failing the same way. It is fixed
here, not left for the next run to find.

**The fix — `HoldingValuation::NOT_NULL_WITH_DEFAULT`**
(`app/Support/HoldingValuation.php`), a drop loop running before the float casts.
`(float) null` is `0.0`, which on an update would write a zero where the caller
meant "leave it alone"; dropping the key is what lets the default apply on create
and leaves the stored value untouched on update.

**Why there and not in the two form requests.** `reconcile()` is the one boundary
every holding write already crosses. Fixing it in the validators would have covered
two of five paths:

| Path | Calls `reconcile()` |
|---|---|
| `InvestmentController::storeHolding` / `::updateHolding` (the web form) | yes |
| `RetirementController::seedHoldingsForDcPension` | yes |
| `DCPensionHoldingsController::store` / `::update` | yes |
| `CoordinatingAgent::handleCreateHolding` (Fyn) | yes |
| `HoldingsImportService::applyHoldings` (upload) | **no** — see §7 |

**The per-column judgement, not a blanket rule.** `current_value`, `security_name`
and `asset_type` are also NOT NULL on `holdings` and are deliberately **absent**
from the list: they have no database default, so there is nothing to fall back to
and dropping the key would fabricate a figure rather than defer to one. Zero is
right for a dividend yield and an ongoing charge because the column's own default
is `0.0000` and "not stated" and "zero" are the same fact for a rate. The tester
reached the same conclusion independently when they used `0` as their workaround.

### 2.3 Fault 2 — the raw statement was rendered to the user

**A separate fault with a separate fix, because it is a disclosure issue and not a
consequence of the validation bug.** `QueryException::getMessage()` embeds the
driver error, the connection name, the failing statement and its full column list.
`Handler::handleApiException` returned `$exception->getMessage()` and sanitised it
only behind `! config('app.debug')`.

That check is not enough on its own for two reasons: `app.debug` is true on every
developer machine and on any server where it has been left on, and **the disclosure
is a property of the exception, not of the environment.**

`app/Exceptions/Handler.php` now intercepts `QueryException` and `PDOException`
ahead of the generic branch, logs the real message with the path, method and user
id, and returns a civil sentence. **One handler, so web, `/m` and native are all
covered at once** — the sanitisation is not a per-controller concern and was not
implemented as one. Ordinary exceptions still surface their message in debug; the
narrowing is scoped to database exceptions deliberately, so this is not a second
change wearing this one's clothes.

### 2.4 Fault 3 — an earlier submit failed silently

Choosing Asset Type "Fund" and leaving the conditional **"Fund Type"** select blank
submitted, 422'd, and looked like nothing had happened.

**The server was never the problem.** `sub_type` carries
`required_if:asset_type,fund` and returns a clear message
("The sub type field is required when asset type is fund."). Three things on the
client hid it:

1. The `sub_type` select was the only field in the form with **no error binding and
   no error paragraph** — every sibling has both.
2. There was no client-side check for it in `validateForm()`.
3. The parent's `catch` sets `this.error` from `data.message`, which for a 422 is
   the generic "The given data was invalid." — and it renders in a box at the **top
   of a modal the user has scrolled to the bottom of** to reach the submit button.
   The useful part, `data.errors`, was never passed down at all.

Fixed by giving `sub_type` the same treatment as its siblings and by adding a
`fieldErrors` prop that lands any 422's per-field messages **on the fields that
caused them**. That third part is general: every field in the form benefits, not
just this one.

---

## 3. W-0262 — a control the validator silently discarded

### 3.1 The evidence, and why the control matters

The tester ruled out "the click never landed" before reporting it:

| | Field set in the same submit | Result |
|---|---|---|
| `dc_pensions.9` | `platform_fee_percent` → 0.35 | **saved** |
| `dc_pensions.9` | `risk_preference` → `upper_medium` | **still `medium`** |
| `investment_accounts.26` | `risk_preference` → `high` (different form) | **saved** |

`updated_at` moved, so the request succeeded and the row was written. The fee
survived **because a fee had a rule**; `StoreDCPensionRequest` had none for
`risk_preference`, so `validated()` dropped it before the controller saw it.

### 3.2 It was six fields, not one

Reading the enforcing layer rather than the descriptive one settles it.
`PensionStore::validateDcCanonical` — the inner validator the write actually passes
through — **explicitly accepts** `risk_preference`, `has_custom_risk`,
`expected_return_percent`, `salary_sacrifice`, `employer_matching_limit` and
`employer_ni_rebate_pct`. Its own comment reads *"Mirrors StoreDCPensionRequest"*.
It did not. Three of the six are `v-model`-bound on `DCPensionForm.vue` today
(`risk_preference:323`, `expected_return_percent:221`, `salary_sacrifice:360`), so
three fields were being dropped, not one.

`PensionThreeIngestParityTest`'s docblock confirms this is restoring the intended
design rather than inventing one: it lists these as **"Form-only DC fields"** — the
form path is where they are meant to be expressible.

### 3.3 The half that would have looked fixed and done nothing

**Storing `risk_preference` alone leaves the feature inert.** Every reader gates on
the pair:

- `RetirementController:865` — `if ($pension->has_custom_risk && $pension->risk_preference)`
- `PensionProjector:291` — same test, this is the one that changes the projection
- `PortfolioPresentationService:204` — same test

And **`has_custom_risk` has never been written by any client.** A grep of the whole
repository finds exactly three writers, all seeders (`PreviewUserSeeder:935,996`,
`ChrisUserSeeder:219,253`). For every real user it has sat at its column default of
`0` since the column was created — on investment accounts as well as pensions — so
the per-product risk override has never done anything for anyone who was not seeded.

Fixing only the validation would have produced a field that saves, displays, and
changes no behaviour: **the worse of the two failures, because it looks fixed.**

`PensionNormaliser::fromFormDc` now derives the flag — choosing a level IS the act
of overriding, so a second control saying "and mean it" would be a mechanism the
user has to operate to make the first one work. Only when the key was sent, so an
edit that omits it leaves the stored flag alone; that is the same discipline
`fromFormDb` already applies to `scheme_status`.

### 3.4 A latent 500 closed on the way past

`PensionStore`'s rule was `risk_preference => 'sometimes|nullable|string|max:64'`,
but the column is `enum('low','lower_medium','medium','upper_medium','high')`. Any
other string passed validation and would have died as a `QueryException` at the
column — **the same shape as `inflation_protection`, which is documented as exactly
that failure eight lines below it in the same method.** Tightened to the enum.

The vocabulary was retyped inline in four places already
(`RiskPreferenceController` twice, `AccountProjectionsRequest`,
`AutoRiskCalculator::$riskOrder`). Rather than adding a fifth,
`InvestmentDefaults::RISK_PREFERENCES` is now the one home and both new rules read
it. **The existing four copies are left alone** — consolidating them touches another
agent's live scope — but they are recorded in §7.

Note it is deliberately NOT derived from `InvestmentDefaults::RISK_LEVEL_MAP`, which
also carries legacy aliases (`cautious`, `balanced`, `growth`, `aggressive`) the
columns reject outright. A validator reading that map's keys would admit five values
the database refuses.

---

## 4. W-0242 — a 500 on a column that does not exist

`LifeStageService:211` summed `db_pensions.transfer_value` through the **query
builder**, so it reached MySQL as `select sum(transfer_value) …` and threw
`SQLSTATE[42S22]`. The identical mistake in `MobileDashboardAggregator:427` reads
over a **Collection**, where a missing attribute silently sums to zero — which is
precisely why one copy was invisible and this one was fatal.

Reachable by a user who is `mid_career`, over 48, and does **not** have all children
aged 18 or over. The `||` short-circuits, so a childless user is safe (`every()`
over an empty set is true) and a user with one child under 18 is not. Unguarded, no
try/catch on the path.

**The fix removes the term rather than valuing the pension — permanently.** Zero is
what every other reader in the app already contributes for a Defined Benefit
pension, so dropping it makes this path agree with them instead of 500ing.

**CSJ ruled on 2026-08-22 (W-0241, option 3): Defined Benefit schemes are EXCLUDED
from net worth by decision**, with the surfaces stating so where the figure is
shown. A `transfer_value` column, migration or form field is explicitly out of
scope, as is any capitalisation multiple on `accrued_annual_pension`. The ruling
names this very reader as in scope for deletion, so this batch implements the
ruling rather than deferring to it.

**An earlier draft of the comment at the site told the next reader to add the term
back "when W-0241 lands"** — written while that item was still open. With the ruling
made, that instruction pointed at the one change the ruling forbids, so it now
records the exclusion as settled and says do NOT restore. Caught by the pensions
agent, who correctly declined to edit a file it does not own.

**The sweep the board asked for is clean.** 354 aggregate call sites inspected
across `app/`; after this fix there are **zero** query-builder aggregates over a
column that does not exist. The Collection twin at `MobileDashboardAggregator:427`
is left untouched per instruction — another agent's file, and the W-0241 ruling
assigns its deletion to that item.

---

## 5. What is NOT fixed here, and why

- **`MobileDashboardAggregator:427`** — the Collection sum of the same phantom
  column. Another agent's in-flight file; the W-0241 ruling assigns its deletion
  to that item. Delete the reader; do NOT add the column.
- **The four inline copies of the risk vocabulary** (§3.4). Consolidating them
  reaches into live scope; recorded in §7 instead.
- **`HoldingsImportService::applyHoldings`** — bypasses `reconcile()` and passes
  `'current_value' => $holding['current_value'] ?? null` and
  `'security_name' => … ?? null` into NOT NULL columns with **no default**. Two
  latent 500s on the upload path, in a different module and outside this brief.
  Recorded in §7; it cannot trip the two columns fixed here because it never sends
  them.
- **The remaining `nullable`-on-NOT-NULL list** (§6.1) — 192 rows, of which the
  live ones are fixed. The rest need a per-column decision each and are a work item,
  not a batch.
- **Unicode-as-icons on the investment detail view** (`✓ ! ⊘`, `⚠ ℹ`) — the tester
  routed it as an observation. Design item, parked per standing instruction.

---

## 6. The sweeps

Both were built mechanically against `information_schema` and the live rule arrays
(`rules()` called on real instances, so inheritance and conditionals are included),
not read by eye. Scripts and full output in the batch report.

### 6.1 `nullable` validation rules on NOT NULL columns

**192 rows across 40 model-backed form requests.** Ranked by whether a client
actually sends a null today:

**Live or one form change away — a client sends an explicit null (23 columns).**
`holdings.dividend_yield`, `holdings.ocf_percent` — **fixed here**.
`investment_accounts.advisor_fee_percent`, `.country`, `.current_value`,
`.contribution_frequency` — **already covered** by
`InvestmentAccountNormaliser::NOT_NULL_WITH_DEFAULT` (W-0052).
**Not covered, and a form sends the null:** `business_interests.country`,
`.current_valuation` · `chattels.country`, `.current_value` ·
`dc_pensions.current_fund_value` · `goals.contribution_frequency` ·
`mortgages.country`, `.mortgage_type`, `.outstanding_balance`, `.rate_type`,
`.remaining_term_months` · `properties.country` ·
`protection_profiles.annual_income`, `.retirement_age` ·
`savings_accounts.country`, `.current_balance`, `.interest_rate` ·
`wills.has_will`.

**Six of those have NO database default** — `mortgages.mortgage_type`,
`protection_profiles.annual_income`, `.monthly_expenditure`,
`db_pensions.scheme_type`, `disability_policies.benefit_amount`,
`sickness_illness_policies.benefit_amount`, plus
`holdings.asset_type/current_value/security_name` and
`investment_goals.goal_name/goal_type/target_amount/target_date`. **These cannot be
fixed by dropping the null** — there is nothing to fall back to. They need either a
nullable column or a required rule, and that is a per-column product decision.

**The remaining ~150** are NOT NULL with a usable default where no client currently
sends a null — every `investment_accounts` employee-share-scheme column, the
`notification_preferences` booleans, the `life_events` display flags, and so on.
Latent, in the exact sense W-0052 meant: one `payload.x = null` in a form away.

### 6.2 Fillable, offered by a client, and absent from the rules

**95 rows.** Filtered to fields that are actually `v-model`-bound in
`resources/js` or `resources/mobile`, or named in `ios-native` — a field that is
fillable but nothing offers is not a defect.

**Confirmed silent data loss, web `v-model` bound:**

| Table.field | Request |
|---|---|
| `dc_pensions.risk_preference` | **fixed here** |
| `dc_pensions.expected_return_percent` | **fixed here** |
| `dc_pensions.salary_sacrifice` | **fixed here** |
| `goals.show_in_household_view`, `.show_in_projection`, `.status` | `StoreGoalRequest` / `UpdateGoalRequest` |
| `liabilities.ownership_type`, `.ownership_percentage`, `.joint_owner_id`, `.country`, `.trust_id` | `StoreLiabilityRequest` / `UpdateLiabilityRequest` |
| `savings_accounts.beneficiary_id`, `.beneficiary_name`, `.beneficiary_dob`, `.contribution_frequency`, `.regular_contribution_amount`, `.planned_lump_sum_amount`, `.planned_lump_sum_date` | `StoreSavingsAccountRequest` / `UpdateSavingsAccountRequest` |
| `investment_accounts.badr_*` (8 fields), `.bond_purchase_date`, `.bond_withdrawal_taken`, `.trust_id` | `StoreInvestmentAccountRequest` / `UpdateInvestmentAccountRequest` |
| `mortgages.ownership_percentage` | `StoreMortgageRequest` / `UpdateMortgageRequest` |
| `holdings.cost_basis` | derived by `HoldingValuation`, not a defect |
| `bequests.notes`, `will_documents.executors`, `.status` | Estate requests |
| `protection_profiles.employer_name` | `StoreProtectionProfileRequest` |

**`liabilities.ownership_type` / `.ownership_percentage` and
`mortgages.ownership_percentage` are the ones to look at first** — they are the
ownership family this run has already spent three items on (W-0226, W-0228,
W-0015), and a discarded ownership field is a wrong figure rather than a missing
one.

**Caveat on the iOS column:** it is a substring match over `ios-native/*.swift`, so
it is a lead, not a finding. The `v-model` columns are exact.

---

## 7. Recorded for someone else

1. `MobileDashboardAggregator:427` — Collection sum of `transfer_value`. W-0241
   ruling: delete the reader, do NOT add the column.
2. `HoldingsImportService:96` — `current_value` and `security_name` nulled into
   NOT NULL columns with no default. Two latent 500s on the upload path.
3. The four inline copies of the risk-level vocabulary (§3.4).
4. `CoordinatingAgent:3288` — `$payload['current_value'] ??= 0.0;`, a per-path
   special case for exactly the class generalised here. Now redundant in spirit but
   left alone: it is W-0122's file and removing it is riskier than keeping it.
5. `MonteCarloEngine` / `MonteCarloSimulator` signature incompatibility — an
   app-wide fatal observed at 21:01 while this batch was running. Reported to the
   team lead immediately and not touched; **the team lead landed the fix** by
   widening the parent signature. Method note worth keeping: a plain bootstrap
   never loads that class, so a successful boot check said nothing. **For a
   signature-compatibility fatal, reflect on the class** —
   `new ReflectionClass('App\Services\Investment\MonteCarloSimulator')`.

---

## 7a. What the browser caught that the tests did not

**Two defects found during browser verification, both of which the 21 tests were
structurally incapable of catching.** Recorded here because the shape matters more
than either bug.

### The regression this batch introduced in itself

Giving the six W-0262 fields validation rules stopped `validated()` stripping
them — which is the fix — and **that exposed them to the canonical store for the
first time**. `PensionStore` validated `salary_sacrifice` as `sometimes|boolean`
with no `nullable`, against a column that **is** nullable
(`tinyint(1) NULL`). `DCPensionForm` serialises its whole model, so it sends
`salary_sacrifice: null` on every save. Result: **a 422 on a save the user had
every right to make**, live in the browser, first attempt.

Fixed by making the store rule `sometimes|nullable|boolean` to match its column.

**Why no test caught it.** Every case in `DCPensionRiskPreferenceTest` sent the one
or two fields under test. The real form sends thirty. This is `tests/CLAUDE.md` §4's
**fixture** variant aimed squarely at my own work: *a payload narrower than the real
one cannot enter the branch that breaks.* There is now a case that posts the
browser's payload verbatim, nulls and all, plus a `DC_NOT_NULL_WITH_DEFAULT` drop
(the third table to need the W-0052 mechanism) closing the latent
`current_fund_value` 500 the sweep at §6.1 had already flagged.

### A third defect class — W-0263

`holdings.dividend_yield` is `decimal(5,4)`; it stops at **9.9999**. The rule said
`max:100`. A yield of **50** passes validation, reaches MySQL and raises
`SQLSTATE[22003] Out of range`. That is the genuine `QueryException` used to prove
fault 2 live.

Swept: **18 rules across 11 requests permit values their column cannot store.**
Worst by ordinariness of the breaking input: **`mortgages.fixed_interest_rate` and
`variable_interest_rate`, `decimal(5,4)` with `max:100` — any rate of 10% or more
500s today.** Full table and ranking in W-0263. Only the two holdings fields are
fixed here; capping a mortgage rate at 9.9999 would trade a crash for a limit the
user cannot work around, and the column is what is wrong.

**This class is invisible to both sweeps in §6.** Nullability is correct, the field
is validated and fillable, every layer looks right in isolation. It appears only
when the rule's **range** is compared to the column's **precision** — a third axis.
*"The rule and the column disagree" has at least three shapes, and a sweep for one
says nothing about the other two.*

---

## 8. Tests

| File | Cases | Proves |
|---|---|---|
| `tests/Feature/Investment/HoldingNotNullColumnsTest.php` | 8 | W-0261 fault 1, the schema-drift guard, and the W-0263 range cap |
| `tests/Feature/Security/DatabaseExceptionDisclosureTest.php` | 4 | W-0261 fault 2, with `app.debug` forced ON |
| `tests/Feature/Retirement/DCPensionRiskPreferenceTest.php` | 10 | W-0262, the rule-parity guard, the browser payload, the DC NOT NULL drop |
| `tests/Feature/LifeStageTransitionTest.php` | 4 | W-0242 |

**315 tests green** across Investment, Retirement, Stores, Security, LifeStage and
Support — nothing regressed.

**Every one was run against the un-fixed code and confirmed red first**, because a
green test that was never red proves only that it agrees with the code:

- Disabling the `NOT_NULL_WITH_DEFAULT` loop: **3 of 6 fail.** The three that stay
  green are the ones that do not enter the branch (keys absent, a supplied figure,
  the drift guard) — correct.
- Removing the six `StoreDCPensionRequest` rules: **6 of 7 fail.**
- Keeping the rules but disabling the `has_custom_risk` derivation: **2 of 7 fail** —
  so both halves of the W-0262 fix are independently load-bearing, which is the
  point of §3.3.

### Test-design notes

The `tests/CLAUDE.md` §4 **fixture** variant governs all four files:

- **W-0261** — a test that supplies `dividend_yield` cannot fail. Every case sends
  it as null or omits the key, the way the user did.
- **W-0262** — asserting the request returned 200 is the assertion that passed
  throughout the bug. Every case asserts the stored value **moved**, with the
  starting value set explicitly so "moved" cannot be confused with "was already
  that".
- **W-0242** — the throwing line sits behind a short-circuiting `||`. A fixture with
  no children never reaches it, because `every()` over an empty set is true. Every
  case states a child's age explicitly, because the child's age is the fixture
  property that decides whether the code under test runs at all. There is a positive
  case, a negative case and the short-circuit case, so "it did not throw" cannot pass
  by the check having been deleted.
- **W-0261 fault 2** — running with debug off would pass on the old code and prove
  nothing. `app.debug` is forced ON.

---

## 9. Surfaces

**Backend is shared, so all three clients are covered by architecture** — one
`Handler`, one `HoldingValuation`, one `StoreDCPensionRequest`, one
`PensionNormaliser`, one `LifeStageService`.

| Fix | web | `/m` | iOS |
|---|---|---|---|
| W-0261 fault 1 (holdings null-drop) | server-side | server-side; `/m` has no holding-entry surface | server-side |
| W-0261 fault 2 (no SQL to the client) | one handler, all three |||
| W-0261 fault 3 (field errors surfaced) | **web only — the form is web-only** | n/a | n/a |
| W-0262 (pension risk) | server-side | server-side | server-side |
| W-0242 (life stage 500) | server-side | server-side | server-side |

**One client change**, `HoldingForm.vue` + `InvestmentHoldings.vue`, and the holdings
form exists only on the web SPA — `HoldingValuation`'s own docblock records that
`/m` reads holdings and never writes them. **No `/m` counterpart is missing;** there
is nothing to build there.

**The web SPA bundle needs rebuilding** for the client changes to reach csjones or
production. Build artefacts are the coordinator's — **not built here.** `/m`
(`public/m-build/`) is unaffected and needs no rebuild.

**Note — `HoldingForm` has TWO parents.** `InvestmentHoldings.vue` and
`InvestmentProjections.vue` both mount it, and the account-detail drill-down (the
surface the tester actually used) is the second one. Fixing only the first would
have left the field-error behaviour reaching one of the two entry points — the
Rule 20 shape, in miniature, inside this batch's own fix. Both are wired.

---

## 10. Browser verification

All three items verified live as David (16) on `localhost:8000`, through the MFA
gate, with codes fetched from the database.

| Check | Result |
|---|---|
| Add Holding, Dividend Yield **and** Ongoing Charge Figure both blank | **Saved.** Row 68, `dividend_yield=0.0000`, `ocf_percent=0.0000`, £1,250 = 100 units x £12.50. No SQL on the page. |
| Asset Type "Fund", Fund Type blank | **"Fund type is required when the asset type is Fund"** at the field, red border. Screenshot `W-0261-fundtype-error-now-visible.png`. |
| A real `QueryException` through `POST /api/investment/holdings` | Civil message to the user, **zero SQL**; full statement + path + method + user_id in `laravel.log`. |
| Pension 9 risk set to Upper-Medium | `medium -> upper_medium`, `has_custom_risk false -> true`, `updated_at` moved, `current_fund_value` intact. |
| `GET /api/life-stage/progress`, mid_career 49-year-old with a 16-year-old child | **200**, `suggested_transition: "peak"`. The removed line still throws `SQLSTATE[42S22]` against the live database, so the green is not a coincidence. |

**Database hygiene.** The holding created during verification was removed — it is
not persona data. Pension 9 was **left** at Upper-Medium: that is what the persona
specifies and what the tester was trying to achieve. David's `life_stage` was set
to `mid_career` through the app's own API to reach the W-0242 path, and restored to
`NULL` as found.
