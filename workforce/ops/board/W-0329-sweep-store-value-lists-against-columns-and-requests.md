---
id: W-0329
title: The fifth axis — sweep every Store's accepted-value lists against BOTH the column definitions and the matching request rules, because Fyn and /m write through the Stores
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: done
closed: 2026-08-29
severity: medium
surfaces: [web, m, ios]
created: 2026-08-23T00:10:00Z
claimed: 2026-08-26T00:00:00Z
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0326, W-0263, W-0324]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

**`resources/mobile/api.js` has no post, put or patch helper anywhere in the
bundle. Fyn is not one of `/m`'s write paths — it is the ONLY one. So sweeping
`app/Http/Requests/` says nothing whatever about how `/m` writes.**

That is the whole item in one line, and it is easy to miss because the backend
*looks* shared. It is shared at the endpoint and diverges one layer down: Fyn's
capture handlers in `CoordinatingAgent` write through the **Stores**
(`InvestmentAccountStore::create`, `MortgageStore`), which carry their own
validation, not through the form requests.

**A rule that holds in `app/Http/Requests/` and not in `app/Services/Stores/`
therefore means the web form and the Fyn capture path accept different things —
and `/m` and native follow the Store, not the request.**

F-0025 measured four axes of rule-versus-schema disagreement and every one of them
lives in the request layer. This is the fifth, and it is the layer the mobile
surface actually writes through.

W-0326 was that, live and user-visible: `MortgageStore` alone of four layers
rejected `mixed`, so a part-fixed part-variable mortgage could not be recorded at
all, and the failure was swallowed by the calling component.

## What has already been measured

A first pass over the Stores is done, so this item starts from numbers rather
than a guess:

- **17** `in:` rules across the mapped Stores.
- **1 fixed** — `MortgageStore::rate_type` (W-0326), which was wrong in both
  directions at once.
- **2 remaining**, both the "column stores what the rule refuses" direction:

| Store | Field | Column stores, rule refuses | Verdict |
|---|---|---|---|
| `MortgageStore` | `ownership_type` | `tenants_in_common`, `trust` | **Deliberate.** `MortgageNormaliser:79-81` coerces tenants_in_common to joint before the Store sees it, and documents that mortgages do not support it. Not drift. |
| `InvestmentAccountStore:254` | `ownership_type` | `trust` | **Not recorded as deliberate.** `investment_accounts.ownership_type` stores `trust`, and Store/UpdateInvestmentAccountRequest BOTH allow it via `Rule::in(['individual','joint','trust'])` — but the Store refuses it, with no coercion of the kind that legitimises the mortgage case. |

**A drift guard already exists:** `tests/Unit/Database/StoreEnumRulesMatchColumnsTest.php`,
two tests, both proven to fail. It checks both directions and carries an explicit
exception list, because **a rule narrower than its column is not automatically a
defect** — see the mortgage row above.

## What is still open

1. **Resolve `InvestmentAccountStore::ownership_type`.** Latent rather than live —
   no form offers trust ownership on an investment account today — but the request
   layer permits it, so any client, including Fyn's tool schema, can send it. Then
   remove its line from `DELIBERATELY_NARROWER`.
2. **The sixth axis: numeric bounds at the Store layer.** This pass covered enum
   lists only, and the numeric half is a distinct axis rather than a detail of
   this one — **it is F-0025's headline axis (range) repeated one layer over**,
   where nobody has swept it.

   It is already known to diverge:

   | Store | Field | Bound | Consequence |
   |---|---|---|---|
   | `MortgageStore:306` | `interest_rate` | `max:100` | fine |
   | `MortgageStore` | `fixed_interest_rate`, `variable_interest_rate` | **none** | the column is the only limit Fyn's path meets |
   | `InvestmentAccountStore` | `platform_fee_percent` | **none** | **Fyn accepts a 12% platform fee where the form returns a 422** |

   Note the shape: these are the *same fields* F-0025 fixed in the request layer.
   The widened columns mean they no longer 500 — which is why this is now a
   divergence in bound rather than a crash — but the two surfaces still disagree
   about what a user may say, and **the unbounded ones are exactly the
   "absence of a bound" axis (§4.3) in a layer that was never swept for it.**
3. **Map the three unmapped Stores** the sweep skipped, and the pension Stores,
   which write several tables from one class.

## Acceptance

1. Every Store's accepted-value list reconciled against its column AND its
   matching request rule, with each intentional narrowing recorded **and naming
   the mechanism that guarantees the excluded value never arrives** — an
   exception without a reason is a place to hide drift, not a decision.
2. Numeric bounds swept as their own axis, not as a footnote to the enums.
3. The guard extended to cover whatever the sweep adds, so the next divergence
   fails a test rather than a user's save.

## Working notes

- 2026-08-23 build-lead (`fix-cycle4-columns`): raised on team-lead's instruction.
  The measured half is done and the guard is in place; the open half is the
  numeric bounds and the unmapped Stores.
- 2026-08-26 (branch `Bug-fixes-2`): all three open pieces closed. Detail below.

## Resolution — 2026-08-26

### 1. `InvestmentAccountStore::ownership_type` (open piece 1)

`trust` added to the Store rule and to `InvestmentAccountNormaliser`, and the
`DELIBERATELY_NARROWER` entry removed.

**The normaliser was the live half, and it was worse than the Store rule.** Its
fallback listed only `individual` and `joint`, so `trust` did not hit the Store's
`in:` and 422 — it was **silently rewritten to `individual`** before the Store ever
saw it. A caller saying an account is held in trust was told it saved, and it was
recorded as solely owned. Only the tenants-in-common coercion above it was ever a
decision; `trust` was collateral from the same catch-all.

Grounds for widening rather than recording the narrowing as deliberate: the column
stores `trust`, both requests permit it, and `SavingsStore:315` and
`LiabilityStore:135` — the two sibling Stores on the same un-normalised Fyn update
path at `CoordinatingAgent:6265` — both allow the full set. `tenants_in_common`
stays excluded and stays in the exception list, because that one has the coercion
that legitimises it.

### 2. The sixth axis, numeric bounds (open piece 2)

**15 genuine divergences**, all in the "request bounds it, Store does not"
direction. All 15 closed by mirroring the request rule exactly rather than
inventing a bound:

| Store | Fields | Added |
|---|---|---|
| `MortgageStore` | `repayment_percentage`, `interest_only_percentage`, `fixed_rate_percentage`, `variable_rate_percentage`, `fixed_interest_rate`, `variable_interest_rate` | `min:0\|max:100` |
| `InvestmentAccountStore` | `platform_fee_percent`, `advisor_fee_percent` | `max:10` |
| | `interest_rate`, `current_ownership_percent`, `cliff_percentage`, `performance_vesting_min_percent`, `performance_vesting_max_percent` | `max:100` |
| | `saye_monthly_savings` | `max:500` |
| | `saye_option_discount_percent` | `max:20` |

**The count moved three times before it was right, and each correction removed a
false positive rather than finding more:**

- The first sweep reported **0**, which was a false negative. The parser read only
  string-syntax rules and skipped any field absent from the Store — i.e. precisely
  the case the axis is about. Rewritten to start from the request's bounds and look
  for the Store's, which is the direction that can see an absence.
- Then **25**, which included `ownership_percentage` — not a finding.
  `ValidationLimits::percentageRules()` carries `max:100` without the literal
  appearing in the source.
- Then 25 minus **nine** `mortgage_*` fields that `StorePropertyRequest` bounds but
  which are **not columns on `properties`**. Request-only, handed to the mortgage
  path. Reporting them would have tripled the count with noise.

**Why an absent rule is a defect and not a no-op**, which is the fact the whole
axis rests on: every Store validates with `Validator::make($canonical, $rules)` and
throws on failure — **none calls `validated()`**, and the write persists
`$canonical`. Laravel ignores keys with no rule, so a field absent from a ruleset
is not filtered out of the payload. It is written unchecked.

`platform_fee_percent` is the concrete case: a 12% platform fee was a 422 on the
web form and a successful save through Fyn.

### 3. The unmapped Stores (open piece 3)

`PensionStore` mapped, and it is the only unmapped file that had an `in:` rule.
`CurrencyDisplayService`, `TierGate` and `IngestSource` are not Stores;
`RetirementProfileStore`, `TaxConfigStore` and `TierConfigurationStore` have no
`in:` rules to check.

**Mapping it required changing how the guard resolves a rule to a table, and the
obvious approach was wrong.** `PensionStore` writes three tables — `dc_pensions`,
`db_pensions`, `state_pensions` — from one class. The first implementation let a
Store map to several tables and checked each field against whichever of them had
that column.

That is unsound, and this Store is the proof: **`scheme_type` exists on both
`dc_pensions` (`workplace, sipp, personal`) and `db_pensions` (`final_salary,
career_average, public_sector`) — same column name, disjoint enums.**
First-match-wins checked the DB ruleset against the DC column and reported a defect
in *both* directions at once, on a rule that is entirely correct.

Fixed by attributing each rule to its enclosing method and mapping
method → table (`validateDcCanonical` → `dc_pensions`, and so on). A rule outside a
mapped ruleset is skipped rather than checked against an arbitrary table.

With that in place both directions pass — the DC and DB rulesets each match their
own column exactly.

## Guards

| File | Tests | Proven to fail |
|---|---|---|
| `tests/Unit/Database/StoreEnumRulesMatchColumnsTest.php` | 2 | Yes — see below |
| `tests/Unit/Database/StoreNumericBoundsMatchRequestsTest.php` (new) | 2 | Yes — see below |

**Both guards were mutation-tested rather than trusted because they went green.**

The numeric guard, by removing `platform_fee_percent`'s bound and setting
`advisor_fee_percent` to `max:99`:

```
InvestmentAccountStore::platform_fee_percent has no ceiling;
  UpdateInvestmentAccountRequest bounds investment_accounts.platform_fee_percent at max:10
InvestmentAccountStore::advisor_fee_percent allows max:99;
  UpdateInvestmentAccountRequest allows max:10
```

The enum guard, by mutating `PensionStore`'s DB ruleset to drop `career_average`
and add `workplace`:

```
PensionStore::scheme_type permits [workplace], which db_pensions.scheme_type
  cannot store (enum: final_salary, career_average, public_sector)
PensionStore::scheme_type refuses [career_average], which db_pensions.scheme_type stores
```

**That second mutation is the one that matters**, and it was chosen for it:
`workplace` is a legitimate value on `dc_pensions`, so a resolver that picked the
wrong table would have accepted it. Catching it, attributed to `db_pensions`, is
what demonstrates the method-based attribution actually works — a green run alone
would not have, since the naive version was also green on the unmutated source.

## Raised, not fixed

**W-0505 — the seventh axis: eighteen enum columns with no accepted-value list in
their Store at all**, thirteen of them bounded in full by the matching request.
`GoalStore` and `LifeEventStore` contain no `Validator::make` at all;
`GoalStore::create` passes `$canonical` straight to `Goal::create`, and Fyn is one
of its callers.

**This guard cannot see that class**, by construction — it checks lists that exist,
and an absent list has nothing to diverge from. Left out of this item deliberately:
writing thirteen new rules and two new rulesets is introducing validation where
there was none, which can break callers currently sending something sloppy, and
needs its own verification rather than a footnote in this evidence pack.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`review`.

- **Delivered by:** Phailanx
- **Evidence:** merged in #731

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
