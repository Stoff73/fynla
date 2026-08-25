# PR #716 certification — quality-lead, 2026-08-25

Sibling to `cycle4-certification-2026-08-23.md` and `cycle4-recertification-2026-08-24.md`.
Same standard: **CERTIFIED** · **REJECTED** (unmet criterion named) · **CANNOT CERTIFY**
(what is missing).

Scope: `feature/icecube/w0008-w0146-projection-fees-and-dead-service` at `d5a91bfa8`,
10 commits ahead of `origin/dev`, 0 behind. Nine board items plus a lint fix.
https://github.com/Stoff73/fynla/pull/716

**I did not write any of this code.** Every claim below was re-measured rather than
read. Where a working note states a figure, I reproduced it from the database; where it
states something is unchanged, I mutated the code and watched what the tests did.

**Headline: seven of nine items CERTIFIED. Two blocked, both on the same undischarged
gate, neither on a code defect. Three claims in the record do not survive checking, and
one of them is a number.**

---

## What I found that the notes do not say

### 1. The £8,329 adviser-fee attribution does not reproduce. It is £3,847.

The working note and the PR body both state:

> Isolating this item's own figure: removing just the 0.75% adviser fee from David's ISA
> moves 10y p20 from £182,938 to £191,267 — **the adviser fee is worth £8,329.**

**The "after" figure reproduces to the penny. The "before" figure does not.** Measured on
the live persona data inside a rolled-back transaction, through **both** call sites that
can produce it:

```
ISA 124 AFTER  p20=182,938.24 net=6.2792 gross=8.0000 drag=1.7208
ISA 124 adviser-fee removed only: p20=186,784.87 drag=0.9708

PORTFOLIO-PATH 124 AFTER  p20=182,938.24 drag=1.7208 net=6.2792
PORTFOLIO-PATH 124 no-adv p20=186,784.87 drag=0.9708 net=7.0292 DELTA=3,846.63
```

`getAccountProjectionWithRiskOverride()` and the account breakdown inside
`getPortfolioProjections()` agree with each other and disagree with the note by
**£4,482**. The drag moves 1.7208 → 0.9708, exactly the 0.75 the fee is worth, so the
input is right and the output is a different number from the one recorded.

**Why this happens, and why it is worth writing down rather than just correcting.**
`MonteCarloEngine::runCoreSimulation()` seeds `mt_srand()` from the simulation's inputs
(`:115-123`), so changing the fee changes the expected return, which changes the seed,
which draws **a different sample**. The difference between two projections is therefore
the fee effect *plus* a fresh sampling error. I measured that error directly by holding
the economics fixed and re-rolling the seed through the iteration count:

```
iters=1000 p20=110,632.54     iters=1003 p20=112,088.49
iters=1001 p20=112,795.05     iters=1004 p20=112,960.42
iters=1002 p20=114,164.86     iters=1005 p20=112,540.70
iters=1006 p20=115,001.47     iters=1007 p20=115,071.40
spread: min=110,633 max=115,071 range=3.92% of mean
```

**Roughly 4% peak-to-peak on p20 at 1,000 iterations**, against a 0.75% fee worth about
7% over ten years. And against closed form the single-draw error runs the same way:

```
fee 0.75% p50: 191,959 -> 171,167 | observed -10.83% | closed-form  -6.76%
fee 1.00% p50: 191,959 -> 168,583 | observed -12.18% | closed-form  -8.92%
fee 1.50% p50: 191,959 -> 163,917 | observed -14.61% | closed-form -13.10%
```

**So "the adviser fee is worth £X" is not a well-defined quantity in this engine**, and
neither £8,329 nor my £3,847 should be published as one. The honest statement is the
drag: **0.75 percentage points a year, about 7% of the terminal value over ten years.**
The record should say that instead of a pound figure derived from two independent draws.

**This does not touch the item's acceptance.** The criterion twice rejected was that the
fee reaches the projection at all, and it demonstrably does.

### 2. The highest-consequence call site has no test at all

`getAccountProjectedValue80()` is the method that feeds `RetirementIncomeService` — **8
call sites**, `:379`, `:412`, `:451`, `:493`, `:1989`, `:2011`, `:2033`, `:2055`. It is
named in the PR body's blast-radius callout as the reason the change is product-wide.

**`grep -rn getAccountProjectedValue80 tests/` returns nothing.** The new
`ProjectionIsNetOfFeesTest` exercises `getAccountProjectionWithRiskOverride()` and
`getPortfolioProjections()`. It never touches the one that changes retirement income.

I verified the behaviour myself rather than leave it unknown:

```
PROBE A getAccountProjectedValue80 10y p20: before=121507.51 after=108566.73 delta=-12940.78
PROBE B OCF: before=121507.51 after=117667.4
```

**It is net of fees, and of the OCF, and it works.** But the PR does not pin it, so the
next person to touch this class can un-fix retirement income and the suite will not
notice. That is a coverage gap on the single riskiest line of the change, and it should
be one more `it()` in the file that already exists.

**Corroborating observation, and it is the uncomfortable one:** the **entire Retirement
suite stayed green** through a change that moves retirement income for every user with an
investment account. That is not a defect in this PR — it is what those tests are — but it
means nothing in the repository was watching the figure that moved.

### 3. The fee is an unbounded, unclamped input to a compounding simulation

`annualFeePercent()` is subtracted from the gross return and the result is compounded
monthly. Nothing bounds it. The validation on its three components does not close this:

| Component | Rule | Ceiling |
|---|---|---|
| `platform_fee_percent` | `Store/UpdateInvestmentAccountRequest` | `max:10` |
| `advisor_fee_percent` | same | `max:10` |
| `holdings.*.ocf_percent` | `Store/UpdateHoldingRequest` and both account requests | `max:100` |
| `platform_fee_amount` | `'nullable\|numeric\|min:0'` | **no maximum** |

Measured, with every value passing the live FormRequest rules:

```
PROBE C maxed-legal-fees: clean=121507.51 maxed=0.39 fee_drag=120 gross=5 net=-115
PROBE D typo-fee:         clean=1181.70   p20=-0 p50=-0 fee_drag=1200 net_return=-1195
```

PROBE C is 10 + 10 + 100, all validation-legal: the ten-year projection collapses to
**39 pence**. PROBE D is a £1,000 *monthly* fixed platform fee on a £1,000 account — the
shape of a user typing the balance into the fee field, and `platform_fee_amount` has no
upper bound to stop them — giving a −1,195% return and a projection of **negative zero**.

**Both figures then travel**: into the chart, into retirement income at 8 sites, and into
`IHTCalculationService:1414`'s projected estate.

**Stated fairly, this is a latent edge and not a live one.** I checked the whole database:

```
max total drag across all 19 investment accounts = 1.9533%
(acct 38: 0.45 platform + 1.00 adviser + 0.5033 OCF)
```

Nothing anywhere near the return. **Before this PR the same data produced a normal
projection because no fee reached the simulation; after it, a bad fee entry silently
destroys the figure.** That is a new failure mode the change introduces, it is untested,
and `tests/CLAUDE.md` §4 is a whole section about exactly this shape. It wants either a
clamp on the net return or a `max:` on `platform_fee_amount`, plus the test.

**Note for whoever fixes it:** `PensionProjector::projectDCPension()` — the pattern this
was taken from — has the same unclamped subtraction and has had it all along.

### 4. "Reverted the fix and confirmed 5 tests go red." Four go red.

W-0205's working note offers this as proof that none of its new cases is a Collision:

> **Reverted the fix and confirmed 5 tests go red**, so none of them is a Collision that
> passes either way.

I reverted it — both lines, back to `$netIncome = $totalIncome - $pensionRelief -
$giftAidGross` and `$adjustedNetIncome = $netIncome - $bpa` — and ran the block:

```
⨯ gives a Gift Aid donor the same net income as an identical non-donor
⨯ deducts the grossed-up donation once, between net income and adjusted net income
⨯ deducts Gift Aid and the Blind Person's Allowance at the same step
✓ leaves threshold income and adjusted income untouched by a donation
⨯ tapers the Personal Allowance on the figure that includes the donation

Tests:    4 failed, 1 passed (9 assertions)
```

Isolated, to be certain which one:

```
--filter="leaves threshold income and adjusted income untouched"  →  1 passed
--filter="tapers the Personal Allowance"                          →  1 failed
```

**`it('leaves threshold income and adjusted income untouched by a donation')` is green
against the pre-fix code.** It is a Collision by the book: threshold income has never
read the intermediate, so donor and non-donor were equal before the fix and are equal
after, and the assertion cannot distinguish the two hypotheses.

**It is not a bad test.** Acceptance 3 says *"adjusted_net_income and threshold_income are
unchanged by the fix. If either moves, the fix is wrong"* — a test that pins an
invariant is precisely what that asks for, and it will earn its keep against a future
change. **The defect is the claim, not the case.** The claim was offered as the evidence
that no Collision survived, and one did; anyone reading the note would stop looking.

The other four are genuine, and one of them is strong: the taper case moves a figure the
user is charged on.

Restored and verified: `git diff --stat` shows `package-lock.json` only.

### 5. The Rule 19 declaration is incomplete — it omits the item with the most `/m` reasoning

The PR body opens `**Mobile impact: shared-backend**` with a per-item table. The table has
**seven rows**: W-0008, W-0146, W-0162, W-0205, W-0221, W-0279, W-0343.

**W-0328 is not in it.** Neither is the lint fix. The body also still says *"Two board
items, worked alone"* immediately below, which was true when it was written and is not
true of the nine items on the branch.

This is not cosmetic. W-0328 is the item whose own test docblock reasons about `/m` most
carefully:

> Fyn is `/m`'s ONLY write path — `resources/mobile/api.js` has no post, put or patch
> helper — so a value absent from `CoordinatingAgent`'s own copies of this list is a
> value web can record and `/m` and native cannot.

The work is right — both `CoordinatingAgent` sites carry `capped` and `offset`
(`:3570`, `:3710`) — so `/m` and native genuinely can record them through Fyn. **The
declaration simply does not say so.** The table was not updated when the item landed.

**Every row that IS in the table, I checked, and every one is accurate:**

| Row | Checked how | Verdict |
|---|---|---|
| W-0008 `/m` has no investment form or projection consumer | `grep` over `resources/mobile/` for `projection`/`advisor_fee`/`platform_fee` — zero | accurate |
| W-0146 no surface | class deleted, zero live references | accurate |
| W-0162 documentation only | column comment applied; enum, collation, default and indexes unchanged | accurate |
| W-0205 no `/m` or native counterpart | no income-definitions panel in `resources/mobile/` or `ios-native/` | accurate |
| W-0221 no `/m` or native reader | swept `resources/mobile/`, `ios-native/`, `ios/` — nothing | accurate |
| W-0279 is the `/m` change | correct, and see the native finding below | accurate |
| W-0343 no surface | dead private method, never reached a response | accurate |

**Verdict on the declaration: accurate in what it says, incomplete in what it covers.**
Add the W-0328 row and correct the "two board items" line.

### 6. W-0279 fixes `/m` and leaves the identical defect standing on iOS

The item declares `surfaces: [m]`, so this is **not** a rejection — it is scoped
correctly and delivered against its scope. But the product defect it describes is
verbatim true of the native app:

```
ios-native/Fynla/Features/Investment/InvestmentView.swift
  63:  if let risk = snapshot.riskLabel {
  64:      riskCard(risk)
  88:  private func riskCard(_ risk: String) -> some View {
  90:      Text("Risk profile".uppercased())
  96:      Text("Attitude to risk")
 100:      Text(risk)
```

The same bare conclusion, the same absence of any route behind it. The PR adds
`case riskProfile = "risk_profile"` to the native enum and pins it in
`WebHandoffClientTests`, **and no native view calls it** — `grep` for `riskProfile`
outside the enum and its tests returns only unrelated `InvestmentModels` decoding keys.

So native gets the destination and not the button. That is the right half to ship first
(a server destination without its native mirror is W-0044's defect), and it should be
said out loud that the user-visible half is still open. **Raise a new item.**

Small irony worth one line, since this PR also deletes a service for having no callers:
the native enum now carries a case nothing invokes, pinned green by a test. It is
justified — the test's stated purpose is to mirror the server allowlist exactly — but it
is the same shape wearing a different hat.

### 7. Recording a *capped* mortgage records a label with nothing in it

W-0328's scope decision — record the type, don't model the arithmetic — is well argued
for **offset**, and I accept it: the payment, balance and rate are user-entered and
already have the offset in them.

**It reads less well for capped.** A capped mortgage is defined by its ceiling and by
when the cap ends, and neither has anywhere to go:

- `PropertyForm.vue:533` — `v-if="mortgageForm.rate_type === 'fixed'"` gates the
  rate-fix-end-date input, so a capped mortgage cannot record its cap period.
- `PropertyDetailInline.vue:327` — `v-if="mortgage.rate_type === 'fixed' && …"` gates
  the display of the same field.
- `MortgageResource.php:58-66` sends `fixed_rate_percentage` for `['fixed','mixed']` and
  `variable_rate_percentage` for `['variable','mixed']`. A capped or offset mortgage gets
  **neither**, and only the bare `interest_rate` survives.

The user picks "Capped" from the dropdown and the app then knows nothing more about it
than it did before. Not a defect in the delivered scope, and not a merge blocker — but
the item's premise is that these are real products the column could not hold, and holding
the word is not the same as holding the product. **Worth an item.**

### 8. The one full-suite failure is a date bomb, and it went live today

The full run came back **1 failed**. It is not this branch.

```
FAILED  Tests\Feature\Mobile\InsightsTest > `Daily Insights API` → it reads the savings figures…
Failed asserting that 'Your emergency fund covers 3.5 months of expenses…' contains "12,000.00".
at tests/Feature/Mobile/InsightsTest.php:77
```

`git diff origin/dev...HEAD --name-only | grep -i insight` returns nothing — the branch
touches neither the test nor `DailyInsightService`. The mechanism is at
`DailyInsightService.php:252`:

```php
$selected = $insights[(int) now()->format('z') % count($insights)];
```

The fixture composes **two** savings insights (an emergency-fund runway and an ISA
allowance), so the selector is `dayOfYear % 2`. The assertion looks for the ISA figure.
The test comment says *"the day-of-year rotation is a no-op"* — it is not; it is a coin
flip that the calendar tosses.

Proved rather than reasoned, with a temporary probe freezing the clock:

```
2026-08-24 (z=235, z%2=1): CONTAINS 12,000.00
2026-08-25 (z=236, z%2=0): does NOT contain 12,000.00
2026-08-26 (z=237, z%2=1): CONTAINS 12,000.00
2026-08-27 (z=238, z%2=0): does NOT contain 12,000.00
```

**It passed yesterday and fails today, and will fail every other day forever.** A latent
defect since it was written, surfaced by nothing but the date. **Raise it; it is not
PR #716's to fix, and it should not be allowed to sit red.**

---

## THE ITEMS

### W-0008 — a fee that was entered, displayed, and never charged → **CANNOT CERTIFY**

**The criterion I rejected twice is met.** The fee reaches the projection, it is the right
fee, and it reaches every consumer. This is a real fix and a good one.

**Verified independently, not read:**

- **The premise.** `git show origin/dev:app/Services/Investment/InvestmentProjectionService.php`
  contains no reference to any fee. All four simulation calls ran on
  `$riskParams['expected_return_typical']`. Confirmed.
- **The headline movement, re-measured on the persona inside a rolled-back transaction:**

  ```
  AFTER (fees charged)  p20=382,832.79 p50=499,130.27  net=6.1888 gross=7.5870 drag=1.3981
  BEFORE (fees zeroed)  p20=404,771.28 p50=535,859.29  net=7.5870 gross=7.5870 drag=0.0000
  PORTFOLIO 10y p20 movement: 404,771 -> 382,833 = -5.42%

  ISA 124 AFTER  p20=182,938.24 net=6.2792 gross=8.0000 drag=1.7208
  ISA 124 BEFORE p20=202,338.57 net=8.0000
  ```

  **£404,771 → £382,833, −5.42%, and £202,339 → £182,938 — the note's two headline
  figures, to the penny.** Persona fee columns verified unchanged afterwards.
- **Rendered, in the browser, as David Mitchell via the landing-page persona selector**,
  `/net-worth/investments`, 2026-08-25T15:44:25Z:

  ```
  Using High risk profile (7.59% expected return, less 1.40% in charges)
  £382,833   (and no "404,77" anywhere on the page)
  ```

  Screenshot captured. **The figure on screen is the figure I measured from the service**,
  and the caption's 1.40% is the `fee_drag_percent` of 1.3981 I measured, rounded. The
  D-21 trap — a caption stating the gross return over a chart compounding the net one —
  is genuinely avoided, and `InvestmentPerformance.vue:97` passes
  `gross_expected_return ?? expected_return` so the number beside "expected return" is
  still the risk profile's. No JavaScript errors on the page.
- **The fee card reconciles.** Queried directly: account 124 is 0.45 + 0.75 + 0.5208 =
  **1.7208**; the portfolio's value-weighted drag is 1.3981. Both match what is displayed.
- **Cache.** I went looking for the obvious defect — a fee that changes nothing because
  the projection is cached — and it is not there. `MonteCarloSimulator::fingerprintKey()`
  hashes the actual `$expectedReturn` into the key, so a fee change is a cache miss by
  construction. Already handled; recorded so nobody re-checks it.
- **Joint accounts.** The fixture set is entirely `individual`, which is the Fixture
  variant's favourite hiding place, so I built the missing case: a 50/50 joint account
  with a 2.0% adviser fee gives `portfolio fee_drag = 2.0`. Correct — a percentage is
  share-invariant — and now checked rather than assumed.

**Mutation-tested, which the note does not claim and which is the strongest thing here.**
Forcing `annualFeePercent()` to `return 0.0`:

```
Tests: 9 failed, 1 passed
```

The survivor is `it('states no drag when no fee is recorded')`, which is the negative
control and *should* pass. **9/10 is a strong suite.**

Then the subtler mutant — the fee applied at 1/100 of its size:

```
Tests: 4 failed, 6 passed
```

**Six of ten survive a 100× scale error.** The seven `toBeLessThan` cases are
direction-only and individually blind to magnitude; the file is carried by two magnitude
assertions and two `fee_drag_percent` equality assertions. It does kill the mutant, so
this is an observation and not a finding — but a reader should know the suite's weight
sits in four assertions, not ten.

**Five-variant sweep on `ProjectionIsNetOfFeesTest`:** no Mock (real service via `app()`,
real factories). No Clamp *in the tests* — but see finding 3 for the clamp the code
should have. Fixture gap closed above (joint). No Collision: the weighting case expects
1.0% where unweighted would give 2.0, sum 4.0 and first-account 0 — all distinct. No
Decoy: every case resolves and calls the service it names. **The fixed-fee case's
`toBe($asPercentage)` is safe** — the engine seeds from inputs, so identical economics
give an identical draw, and the assertion means what it says.

**Why I cannot certify it, and it is not the code.**

**The statutory gate is undischarged and I am barred from discharging it.** The evidence
pack requires `tax-compliance-reviewer` on tax services **or projections**
(`08-process.md` §2). `08-process.md` §4.1's blast-radius list — *"treat as gated until
ruled on"* — names tax services, and this change flows into `IHTCalculationService:1414`.
The PR body says so itself: *"No tax-compliance-reviewer gate was run — CSJ's 2026-08-24
standing instruction bans agents."* **My dispatch instructs me not to spawn agents
either**, so I cannot close it. **I COULD NOT RUN THIS GATE.**

**Assessed on the merits, as asked, since I am the one holding it up.** My own reading is
that the *direction* is unarguable and the *magnitude* is understated in the record:

- Charging fees against a projection is correct and is what the pension side has always
  done. Presenting a gross-of-charges projection beside a fee card reading 1.72% is the
  worse of the two states, and closer to a Consumer Duty problem than the fix is.
- **But this is a projection of a customer's money that drops for every user with an
  investment account** — 5.42% on the persona's portfolio, and 8 retirement-income sites
  and the projected estate move with it. Nobody outside this branch has looked at it.
- The scope call — total fees, not the adviser fee alone — was taken by the implementer
  and recorded as *"asked and answered by Brett"*. It is the right call. It is also a
  product decision about what a headline projection means, taken inside the item that
  needed it.

**What would close it:** `tax-compliance-reviewer` on the projection change, and CSJ or
`product-lead` acknowledging the product-wide drop. Both are cheap. **Neither is my call
to make, and neither has been made.**

**Also open, and small:** the untested `getAccountProjectedValue80` (finding 2) and the
missing clamp (finding 3). The first is one test; the second is one `max:` rule.

**Reported, not raised as a defect** — `annualFeePercent()` is now the third home for
"total fee percent", alongside `InvestmentProjections.vue:658` (display) and
`PensionProjector::projectDCPension()` (pensions, inline). The note discloses the first.
It does not mention that the third **excludes the OCF while its own comment says it
includes it** — `// Account for all fees: platform + advisor + weighted OCF` above code
that sums platform and adviser only. So after this PR, investments charge fund OCF and
pensions do not. Pre-existing; the PR widens the gap rather than creating it.

---

### W-0146 — delete `SpouseNRBTrackerService` → **CERTIFIED**

All four acceptance criteria met, each checked.

- **The file is gone.** `ls app/Services/Estate/SpouseNRBTrackerService.php` → no such file.
- **Zero live references**, re-swept myself across `app/ config/ bootstrap/ routes/
  database/ tests/ resources/`. Two hits remain, both **comments deliberately kept as
  history** at `IHTCalculationService:383` and `:2504`. No container binding, no service
  provider, no test, no factory.
- **No gap left behind.** `calculateNRBDeductionForGifts()` iterates `pooledMembers()`
  and caps each member at their own band, which is the subject the dead class overlapped.
  Estate is green in the full run.
- **The reasoning for not wiring it up is sound and I agree with it.** A transferable band
  derived from a *living* spouse's gift history measured against `Carbon::now()` is not
  what IHTA 1984 s8A does — the claim arises on the survivor's death. Wiring it in would
  have created the second contradictory answer Rule 20 exists to prevent.
- **Both comments rewritten rather than deleted** is the right instinct and the item's own
  point: someone grepping the name lands on an explanation.

**On the `reviewers: [tax-compliance-reviewer]` question the note leaves to me: it does
not apply.** This deletes code that never executed and rewrites two comments. No
calculation changed, no figure moves for any user, and there is nothing for a statutory
reviewer to review. **Certified without the gate, deliberately.**

---

### W-0162 — the mortgages ownership column states its own constraint → **CERTIFIED**

Zero behaviour change, and I confirmed that rather than assuming it.

- **The comment is on the column**, verified against the live database:

  ```
  ownership_type | enum('individual','joint','tenants_in_common','trust') | utf8mb4_unicode_ci
  | NO | | individual |
  Comment: W-0162: only individual|joint are writable. tenants_in_common and trust are
  coerced away before any write, at Store/UpdateMortgageRequest, MortgageNormaliser and
  MortgageStore::validateCanonical. …
  ```
- **The `MODIFY` did not drift the column.** This is the risk with a comment-only `ALTER`
  and it is the thing to check: enum values, `NOT NULL`, `DEFAULT 'individual'`, and the
  `utf8mb4_unicode_ci` collation all match `database/schema/mysql-schema.sql:30`. No index
  exists on the column, so none was dropped.
- **Rollback verified by me, on my own database**, rather than taken from the note —
  `migrate:rollback --step=3` then `migrate`: the comment clears and returns, the enum is
  unchanged throughout.
- **The decision is right, and better argued than "it is only a label".** Tenants in
  common describes how a title is held, not how a debt is held; and since W-0228 the
  mortgage's share resolves from the property, so widening the enum would add
  expressiveness to a column that ruling deliberately demoted. Acceptance 2 would have
  required converting seven `=== 'joint'` comparisons that are **exhaustive and correct**
  under the NO decision — it would have created the problem it then had to fix.
- The stale line references in `MortgageService`'s docblock were corrected
  (`UserProfileService` :931 → :967, `LetterToSpouse` :482 → :1101). Small, and the right
  kind of small.

---

### W-0205 — Gift Aid moves from net income to adjusted net income → **CANNOT CERTIFY**

**The fix is correct and acceptance 3 holds. The block is the same gate as W-0008.**

**Acceptance 3 verified twice, by construction and by measurement.** Before:
`net = total − pension − giftAid`, `adjusted = net − bpa`. After: `net = total − pension`,
`adjusted = net − giftAid − bpa`. Both expand to `total − pension − giftAid − bpa`, so
`adjusted_net_income` cannot move; `threshold_income` branches from **total** income and
never touched the intermediate. Measured on five personas:

```
user 104 (David)    gift_aid=true  donations=2400 | net=147,689.60 adj_net=144,689.60 threshold=147,689.60 adj_inc=170,889.60
user 106 (Alex)     gift_aid=true  donations= 600 | net=231,000.00 adj_net=230,250.00 threshold=231,000.00
user 108 (Patricia) gift_aid=true  donations=1200 | net= 30,000.00 adj_net= 28,500.00 threshold= 30,000.00
user 105 (Sarah)    gift_aid=false                | net=128,880.00 adj_net=128,880.00 threshold=128,880.00
```

**David's four figures are the note's four figures exactly**, and the two that had to stay
still stayed still.

**The statutory reading is right.** ITA 2007 s23 Step 2 is total income less the s24
reliefs; Gift Aid is not one of them — a qualifying donation extends the basic rate band.
The gross-up belongs at s58 with the Blind Person's Allowance, which is where it now is,
and `IncomeDefinitionsPanel.vue` renders the row in that position.

**The consumer sweep is sound, and I re-ran it.** Six consumers of
`IncomeDefinitionsService::calculate()`, none reads `net_income`. The `net_income`
collision with `UKTaxCalculator`'s take-home figure is real and correctly identified as a
collision rather than a shared figure.

**Corroboration the note claims and I confirmed:** the tax breakdown higher on the same
page taxes £147,690, and the panel now agrees with it. Two figures for one concept on one
screen, reconciled.

**Findings:**

1. **The "5 tests go red" claim is wrong — 4 do.** Finding 4 above, with the isolated
   run naming the survivor. The record needs correcting; the test does not.
2. **`gate: null` in the frontmatter is wrong for this item.** It edits
   `app/Services/Tax/IncomeDefinitionsService.php` and relabels two statutory
   definitions. The note itself says a statutory reviewer may want it. **I COULD NOT RUN
   `tax-compliance-reviewer`** — same bar as W-0008.
3. **Adjacent, and I want it recorded because the note raised it and nobody has picked it
   up.** The salary-sacrifice question at the end of the working note is a real one: under
   FA 2004 s228ZA a post-8-July-2015 sacrifice is added back to threshold income
   precisely to stop it being used to dodge the taper, and `getPensionContributions()`
   knows the arrangement while the arithmetic does not branch on it. **If that is right, a
   sacrificing high earner's threshold income is understated and the Annual Allowance
   taper can be missed** — a figure a user is charged on. The note is properly careful not
   to claim it. **It belongs on the board as its own item and in the same reviewer's
   in-tray.**
4. **Minor, checked and clear.** `IncomeDefinitionsService:81-82` clamps `net_income` and
   `adjusted_net_income` at zero. Per `tests/CLAUDE.md` §4 a clamped value is not a probe
   — but no fixture in the suite approaches the floor (£80k, £110k, £145k), so nothing is
   sitting on it. Noted so the next person choosing a fixture knows the floor is there.

**Everything except the gate and the corrected claim is certified.**

---

### W-0221 — drop `users.charitable_bequest` and close both writes → **CERTIFIED**

- **The column is gone**, confirmed against the live schema and by
  `Schema::hasColumn('users','charitable_bequest') === false` in the suite.
- **Both write paths closed**: the `UpdatePersonalInfoRequest` rule and
  `OnboardingService::processFamilyInfo()`. Swept for a third and found none.
- **No reader left anywhere.** The remaining `charitable_bequest` hits are the unrelated
  recommendation **category** label (`EstateAgent:826,868`, `EstateRecommendationAdapter`,
  `RecommendationPersonaliser`, `EstatePlanService`) — correctly identified in the
  migration docblock as *"Different thing, same name"*, and correctly not touched.
- **Migration rollback verified by me**: `down()` restores a nullable `tinyint(1)`, re-up
  drops it again, both clean.
- **The honesty in the test file is the best thing in this item.** The note could have
  claimed the endpoint case guards the removed validation rule. Instead it measured, found
  that Eloquent's `isGuardableColumn()` consults the live schema so a re-added rule is
  silently skipped rather than 500ing, wrote that down, and **added a second test that
  asserts the rule's absence directly**. That is a self-identified Collision, disclosed
  and then closed. It is the standard the other items should be held to.

**One finding, and it is the Decoy variant.** Four cases in
`WillAnalysisCharitableBequestTest` had their decoy removed and **kept their names**:

```
it('reports a recorded legacy on an account whose toggle was never answered')
it('reports a recorded legacy even where the user answered the toggle No')
it('reports nothing recorded where the user answered the toggle Yes but left no legacy')
it('reports nothing recorded for a user with no will at all')
```

Every body is now `User::factory()->create()` — **no toggle is answered in any of them**,
because there is no toggle to answer. `tests/CLAUDE.md` §4 on the Decoy: *"The defect is
the name. Once the name existed, nobody re-read the body."*

The sibling file got this right — `UserProfileCharitableBequestsTest` renamed its cases
("answers no where a **will is recorded but carries no charitable legacy**"). The Unit
file did not, in the same commit.

**And two of the four are now the same test.** Cases 1 and 2 have byte-identical setup and
assert the same fact; the decoy value was the only thing distinguishing them. One should
go, and the others should be renamed to describe what they do.

**Not a merge blocker** — the behaviour is genuinely pinned and `Schema::hasColumn` is a
stronger guarantee than any fixture, exactly as the note argues. It is a tidy-up in the
file, and it is the fifth variant appearing in a PR that was hunting for the first four.

---

### W-0279 — `/m` risk card hands off to the web risk profile → **CERTIFIED**

Certified for its declared surface, `surfaces: [m]`.

- **Server side is complete and behavioural, not just declarative.**
  `WebHandoffDestination::RISK_PROFILE = 'risk_profile'` with `path()` → `/risk-profile`,
  and `WebHandoffTest` asserts **both** the endpoint accepting it (`assertCreated`, row
  persisted with the enum case) **and** the path it resolves to. The second is the one
  that matters: an allowlisted name landing on the wrong screen is a handoff to the wrong
  place and the allowlist test alone cannot see it.
- **`/risk-profile` exists** — `resources/js/router/index.js:907`, with
  `/risk-profile/levels` at `:920`.
- **The CSS classes exist.** I checked this specifically because of the `--violet-800`
  incident in the 2026-08-24 pack, where a caveat was invisible on `/m` because its token
  was undefined. `.m-btn` (`style.css:79`), `.m-err` (`:82`) and `.m-sub` (`:84`) are all
  defined. **The button and its error message are visible.**
- **The `/m` component uses the shared helper** `issueWebHandoff` from
  `resources/mobile/navigation/webHandoff.js` — one home, not a second copy (Rule 20).
- **Rules 12 and 15**: the card is text and a labelled button. No icon, no glyph, no
  score. Design-lint checks re-run manually and clean (see below).
- **Vitest green**: 4/4, including the failure path asserting the user is told rather than
  left with a dead button, and the empty-state case where no risk level means no card.

**The mock is at the right boundary.** `issueWebHandoff` is mocked in the `/m` spec, so
the spec proves the component asks for `'risk_profile'` and not that the server accepts
it — but the server side is pinned separately in `WebHandoffTest`, so the pair is
complete. That is the correct division, not a Mock-variant blind spot.

**Findings, neither blocking:** the native app still shows the same bare risk card with no
route (finding 6), and the native enum case has no caller. **Raise the native gap.**

---

### W-0343 — delete the dead `getExistingLifeCover()` → **CERTIFIED**

- **The method was dead.** Its name appeared once in `IHTController`, at its own
  declaration.
- **Acceptance 2 — leftover, not omission — is established in the three steps the note
  claims**, and I confirmed the third, which is the one that matters:
  `LifeCoverReach::householdCoverInTrust()` owns the question, `EstateAgent:140` calls it
  and publishes the keys, and `EstatePlanService:636,871` read them. **Computed, published
  and consumed** — so nothing the estate response was meant to carry went missing.
- **The `LifeInsurancePolicy` import went with it**, which is the `tests/CLAUDE.md` §2
  trap; Architecture (177 tests) is green in the full run and is what would catch an
  unresolvable import.
- **The dead copy was genuinely worse than the live one**, in both ways claimed: a raw
  `where('user_id')` misses a joint-life policy the spouse is also assured under (W-0186),
  and it bypassed the live/reciprocal spouse gate (W-0278), so it would have disclosed a
  deleted partner's cover. Deleting it removes a trap that would have reintroduced two
  fixed defects at once.
- The pointer comment naming `LifeCoverReach` is the right residue — it is what stops it
  growing back, which is the item's actual concern.
- The `EstateAssetAggregatorService` method of the same name is **live** and untouched.
  Correctly distinguished; a grep on the name alone would not have.

---

### W-0328 — mortgages record `capped` and `offset` → **CERTIFIED**

- **I swept for the sites myself rather than counting the item's nine.** Every
  enumeration of `rate_type` in `app/`, `resources/`, `database/migrations/` and
  `ios-native/` now carries both values: `StoreMortgageRequest:53`,
  `UpdateMortgageRequest:53`, `StorePropertyRequest:98`, `MortgageStore:331`,
  `CoordinatingAgent:3570` and `:3710`, `MortgageMapper:34`, `AIExtractionService:604`,
  and the migration. **I found no site that was missed.**
- **The migration is well judged.** The enum widens; `down()` narrows and **will fail if
  any row holds a new value**, with a docblock saying so and saying why — *"a rollback
  must not silently rewrite a user's stated product as something else"*. That is the
  January migration's trap, seen and handled. Rollback verified by me on my own database:
  7-value enum → 5-value → 7-value, clean each way.
- **The document mapper is the site that would have been missed**, and the test for it is
  the best one in the file: `parseEnum()` coerces an unrecognised value to its default, so
  before this a capped mortgage read off a statement was **silently stored as variable**.
- **The scope decision — record the type, don't model the arithmetic — is correct for
  offset** and consistent with W-0228: the payment is user-entered, stored as given and
  read back as stored, and deriving an offset benefit would put a second mechanism against
  a figure the user has already stated. The two "what this deliberately does not do" cases
  pin that so a later reader does not "finish" it.

**Findings, neither blocking:**

1. **The Fyn-validation test is a source-text assertion, not a behavioural one.** It
   `file_get_contents`es `CoordinatingAgent.php` and regex-matches two `Rule::in` lists.
   This is the shape I rejected in the 2026-08-24 pack for W-0012 — **but it is the
   better-behaved version of it.** A semantically-identical rewrite (`Rule::in(self::RATE_TYPES)`)
   makes `expect($lists)->toBe(2)` **fail loudly** rather than pass blindly, so it degrades
   into noise rather than into a false green. Acceptable. It still proves a string is
   present and not that Fyn accepts the value; a `handleCreateMortgage` call with
   `rate_type: 'capped'` would.
2. **A capped mortgage cannot record its cap or its cap end date** — finding 7. Raise it.
3. **Observation, not this item's problem.** `AIExtractionService:604` is a prompt string
   living outside `app/Services/AI/Prompts/**`, which is the path the blast-radius list
   gates. The gate's coverage and the codebase's prompt locations do not match. Worth
   `08-process.md` §4.1 knowing when it is ruled on.

---

### Lint fix — unused `CHART_COLORS` import → **CERTIFIED**

`InvestmentProjectionChart.vue` no longer imports `CHART_COLORS`; the remaining
`PRIMARY_COLORS`, `SUCCESS_COLORS`, `ERROR_COLORS`, `BORDER_COLORS`, `CHART_DEFAULTS` are
all used. The `lint` job is green on `d5a91bfa8`.

---

## THE TEST RUN — complete, and it closes the standing gap

**A full suite has not completed since `19bd1c83f`. It has now.** Run alone on a private
database (`laravel_testing_qa`), nothing else running, per the deadlock warning:

```
DB_DATABASE=laravel_testing_qa ./vendor/bin/pest

  Tests:    1 failed, 32 skipped, 7982 passed (127640 assertions)
  Duration: 661.87s
EXIT_CODE=1
```

**11 minutes, not the 64 that failed to finish on 2026-08-24.** The difference is the
private database — the earlier attempt's 209 interleaved `⨯` marks were contention, and
this run has none.

**The single failure is `InsightsTest.php:77`, proven date-dependent and unrelated to
this branch** (finding 8). Every other test in the repository is green at
`d5a91bfa8`, including both new files:

```
PASS  Tests\Feature\Investment\ProjectionIsNetOfFeesTest
PASS  Tests\Feature\Property\CappedAndOffsetRateTypesTest
```

**Frontend:**

```
npx vitest run resources/mobile/views/__tests__/InvestmentRiskProfileHandoff.spec.js \
               tests/frontend/components/UserProfile/IncomeDefinitionsPanel.test.js \
               resources/js/components/__tests__/UserProfile/FamilyMembers.spec.js
 Test Files  3 passed (3)      Tests  38 passed (38)
```

**Pint:** all 28 PHP files in the diff pass individually. A whole-repo `pint --test`
returns `fail`, and **every file it names is pre-existing and outside this PR** —
`public/pages/*` and `workforce/ops/ui/index.php`. Not this branch's.

**`design-lint.sh` could not be run as shipped** — `.claude/hooks/design-lint.sh:15` is
`cd /Users/CSJ/Desktop/fynla || exit 0`, a hardcoded path that does not exist on this
machine, so it silently exits 0 and lints nothing. **A design gate that returns success
without reading a file is not a gate.** I applied its three checks by hand to the nine
changed `.vue`/`.js` files:

- **Rule 8/11 banned colour tokens** (`amber-*`, `orange-*`, `gray-N`, `primary-N`,
  `secondary-N`): clean.
- **Rule 11 hardcoded hex in `<style>` blocks**: clean.
- **Rule 15 emoji / Unicode-as-icon**: one hit, in `PropertyForm.vue` at lines 457 and
  632, **not added by this PR** and therefore grandfathered under Rule 15's forward-only
  clause. Not raised, per the rule's own instruction.

**Migrations:** all three applied, and all three rollback-verified by me rather than taken
from the note — `migrate:rollback --step=3` then `migrate`, with the column comment, the
dropped column and the widened enum each checked before, after and after again.

---

## CI — `browser-smoke` is not this branch, and I can prove where it comes from

**Confirmed. It fails on `dev` itself**, with the identical signature.

`dev` @ `efc0e67b7`, 2026-08-24:

```
✘ [desktop-chromium] › tests/E2E/smoke/desktop.spec.js:3:1 › @smoke desktop landing and preview dashboard boot
  Test timeout of 60000ms exceeded.
  Error: locator.click: Test timeout of 60000ms exceeded.
  - locator resolved to <a href="#" class="hero__sublink open-demo-modal">See our demo</a>
```

This branch @ `d5a91bfa8`, 2026-08-25:

```
✘ [desktop-chromium] › tests/E2E/smoke/desktop.spec.js:3:1 › @smoke desktop landing and preview dashboard boot
  Test timeout of 60000ms exceeded.
  Error: locator.click: Test timeout of 60000ms exceeded.
  - locator resolved to <a href="#" class="hero__sublink open-demo-modal">See our demo</a>
```

**Same spec, same line, same locator, same element.** `a10210c49` on `dev` fails the same
way. **Your read is correct: `browser-smoke` is inherited, not caused.**

**A retraction, because I nearly filed a phantom.** Trying to reproduce it locally, my
trusted mouse click on "See our demo" did not open the modal, and `#demo-modal` stayed
`hidden` across two attempts with the link unobscured and top-of-stack at its own centre
(`document.elementFromPoint` returned the link itself). I was one paragraph from
reporting a broken demo call-to-action on the public landing page. **Then I dispatched a
synthetic `.click()` and the modal opened** — `wireDemoModal()` is bound and works. So my
failure was a harness artefact in my own browser tooling, not a product defect, and the
inference was wrong.

**I therefore cannot tell you the root cause of `browser-smoke`, and I am not going to
guess at one.** What I can tell you is that it predates this branch and reproduces on the
base. **It should not be left red** — a smoke job that is always failing is a smoke job
nobody reads — but it is not PR #716's to fix.

The rest of CI on `d5a91bfa8`: `lint` SUCCESS, `php-tests (Architecture)` SUCCESS,
`logic-guard` SUCCESS, `frontend-tests`/`builds`/`php-tests (Integration|Eval)` SUCCESS
on the prior commit. `php-tests (Unit)`, `php-tests (Feature)` and `iOS Native` were still
running when I read them; **my own full local run covers Unit and Feature.**

---

## WHAT I COULD NOT DO

Named, not buried. Each of these blocks rather than fails.

1. **`tax-compliance-reviewer` — I COULD NOT RUN THIS.** My dispatch forbids spawning
   agents. It is required by the evidence pack for W-0008 (a projection feeding the
   estate calculation) and wanted for W-0205 (a statutory relabel in a tax service).
   **This is the only thing holding the merge.**
2. **`security-reviewer` — I COULD NOT RUN THIS.** Same bar. My reading is that it is not
   required here: no auth change, no payment change, and the only user-input surface is
   two enum values added to existing validated rules. Recorded so the absence is a
   decision rather than an oversight.
3. **The branch is not deployed to csjones and has not been browser-verified there.** The
   release skill stands in full on that point: *deploy the feature branch to csjones
   BEFORE any merge, browser-verify there.* **I did not deploy it.** My browser
   verification was local, against `localhost:8000` at this HEAD.
4. **`/m` NOT verified in a browser.** W-0279 is the only `/m` change and I verified it by
   vitest (4/4), by confirming its CSS classes are defined, and by confirming the server
   destination and path. **I did not click the button on `/m`.** Per `verify-m` that
   requires csjones, which follows from (3).
5. **iOS NOT built, NOT launched, NOT checked.** Carried forward from the PR body
   unchanged. Playwright cannot drive the native app; **W-0279 touches the native
   `WebHandoffDestination` enum, so this needs CSJ's device verification** on the same
   footing as PR #303 — though the native change is an unused enum case, which is about as
   low-risk as a native diff gets.
6. **`tech-debt-session` not run as a skill** (agent ban). Done by hand instead: the debt
   this PR adds is the third home for "total fee percent" and the duplicate/misnamed cases
   in `WillAnalysisCharitableBequestTest`. Both are above.

---

## RECOMMENDATION

**Do not merge yet. The block is narrow, named, and cheap to clear — and it is not a code
defect.**

I want to be plain, because seven items are clean and the two that are not are held on
process rather than on anything wrong: **as far as I can measure, all nine items do what
they say.** The full suite is green but for a date bomb someone else planted. The
migrations roll back. The figures reproduce. The mutation testing is better than the notes
claimed for W-0008 and worse than they claimed for W-0205, and I have said which.

**Three things, in order of cost:**

1. **Correct two claims in the record.** The £8,329 adviser-fee attribution does not
   reproduce and is not a well-defined quantity in a seeded Monte Carlo — state the drag
   (0.75pp a year, ~7% over ten years) instead. And "5 tests go red" is 4, with the
   survivor named. **Minutes.** The record is the thing the gate certifies against; it has
   to be true.
2. **Add the W-0328 row to the Rule 19 table** and fix the stale "two board items" line.
   **Minutes.**
3. **Run `tax-compliance-reviewer` on W-0008's projection change and W-0205's statutory
   relabel, and get one product-level acknowledgement that every user's investment
   projection is about to drop.** This is the real gate. W-0008 changes a figure about a
   customer's money on every surface that shows one, and the only people who have looked
   at it are the person who wrote it and me. `08-process.md` §4.1 says treat the
   blast-radius list as gated until CSJ rules; tax services are on it; this flows into
   `IHTCalculationService`. **I am barred from discharging it and will not pretend
   otherwise.**

**On the merits — since I am the one holding it up, I should say what I think.** The
change is right. A projection compounding a gross return above a card reading "Total Fees
1.72%" is the worse of the two states, and closer to a Consumer Duty problem than the fix
is. **I expect the gate to clear.** I am not asking for it because I doubt the answer; I
am asking for it because a gate that only fires when the answer is in doubt is not a gate.

**Two follow-ups should land with or before it**, both small and both inside W-0008's own
file: a test on `getAccountProjectedValue80` (8 production call sites, zero coverage), and
a bound on the fee — a `max:` on `platform_fee_amount` or a floor on the net return — so
that a mistyped fee cannot turn a projection into negative zero and carry it into
retirement income and the projected estate.

**Everything else on this branch is merge-ready today.**

---

## RAISE AS NEW ITEMS

Not blockers. All found while gating this PR; none belongs to it.

1. **`InsightsTest.php:77` fails on even days of the year.** `DailyInsightService:252`
   rotates by `dayOfYear % count($insights)` and the fixture composes two, so the
   assertion is a coin flip the calendar tosses. Proved with a frozen clock across four
   consecutive dates. **Currently red and will be red every other day.**
2. **Native still shows a bare risk level with no route behind it.**
   `InvestmentView.swift:88-100`. W-0279 fixed `/m` and added the native enum case; no
   native view calls it.
3. **A capped mortgage has nowhere to record its cap or its cap end date.**
   `PropertyForm.vue:533`, `PropertyDetailInline.vue:327`, `MortgageResource.php:58-66`.
4. **Threshold income may not add back a post-2015 salary sacrifice** (FA 2004 s228ZA).
   Raised by the W-0205 note, properly hedged, not picked up. **This one reaches a figure
   a user is charged on** — the Annual Allowance taper — and needs the same statutory eye
   as W-0205 itself.
5. **`design-lint.sh` hardcodes `/Users/CSJ/Desktop/fynla` and exits 0 on any other
   machine.** Both copies — `.claude/hooks/` and `.codex/hooks/`. It has been passing by
   not running.
6. **`PensionProjector::projectDCPension()`'s comment says it charges the fund OCF and the
   code does not.** After W-0008, investments charge OCF and pensions do not — two
   projection engines in one product disagreeing about whether fund charges reduce a
   projection.
7. **`WillAnalysisCharitableBequestTest` — four case names describe a toggle their bodies
   no longer set, and two of the four are now the same test.** The Decoy variant, in the
   PR that was hunting for the other four.
8. **`browser-smoke` has been red on `dev` since at least 2026-08-24** and nobody owns it.
   A permanently-red smoke job is a smoke job nobody reads.

---

## WHAT THIS GATE STOPPED

Per the weekly audit's standing question — *a gate that has never blocked anything is not
a gate.*

**Blocked: 2 of 9 items (W-0008, W-0205), on one undischarged statutory gate.**

**Corrections forced into the record: 3.** A published pound figure that does not
reproduce; a mutation-testing claim overstated by one case; an incomplete Rule 19
declaration.

**Defects found that the notes did not declare: 3.** No test on the 8-site retirement call
site; no bound on a fee that now compounds; the Decoy-renamed cases in
`WillAnalysisCharitableBequestTest`.

**Pre-existing defects surfaced: 8**, listed above, including one that is red in CI today.

**Claims I checked and confirmed rather than merely accepted: 14**, including every row
of the Rule 19 table, all three migration rollbacks, both headline W-0008 figures, all
four W-0205 persona figures, and the `browser-smoke` inheritance.

**One inference I formed and retracted before publishing it** — the landing-page demo
modal. Recorded because the 2026-08-24 pack's phantom-failure incident is the reason I
checked twice, and it worked.
