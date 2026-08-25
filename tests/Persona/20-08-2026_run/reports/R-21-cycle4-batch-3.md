# R-21 — Cycle 4, batch 3

**Agent:** `peak-earners-c4` (persona-tester) · **Persona:** `peak_earners`
**Surface:** web, local · **Accounts:** Sarah (17) then David (16)
**Batch closed:** 2026-08-22 ~18:35 · Continues [R-19](R-19-cycle4-batch-1.md), [R-20](R-20-cycle4-batch-2.md)

Batch 3 is the **entry-side** pass: holdings and wills. Unlike batches 1 and 2 this one
drove a form to submit and checked the row.

---

## Done

- Opened David's Hargreaves Lansdown Stocks & Shares ISA and **created a real holding
  through the form**: Fundsmith Equity, FUND, GB00B41YBW71, Fund, 351 units, £85.50 cost,
  £99.86 price, 36.8%, 0.95% Ongoing Charge Figure.
- Opened the Add Holding form on Sarah's account and audited its fields.
- Read Sarah's `/estate/will-builder` end to end.
- Opened both spouses' investment **account detail** views and their projections.

### W-0039 is GREEN — with evidence
The holding form now carries **"Units Held (Optional)"** and computes from it live:
the form previewed *"Value: £35,051 — units × current price"* before submit. The saved row
is exact:

```
id 62 · Fundsmith Equity · FUND · GB00B41YBW71 · fund/equity_fund
quantity 351.000000 · purchase_price 85.5000 · current_price 99.8600
current_value 35050.86 · allocation_percent 36.80 · ocf_percent 0.9500
cost_basis 30010.50          (= 351 × 85.50, correct)
```

The list then rendered "Fundsmith Equity · Fund · 36.80% · £35,051 · 0.95%" and added a
correct **"Cash (unallocated) · 63.2% · £60,040"** remainder row. Good behaviour.

### A lead of mine that was wrong, corrected
R-19 said "all 10 persona holdings are absent". **Wrong — my query output was truncated.**
The true position before this batch:

| Account | Persona expects | Actual |
|---|---|---|
| Sarah's Hargreaves Lansdown ISA | 1 (Vanguard LifeStrategy 80) | **present and correct** — 333.333333 units, £225.00/£255.00, 0.22% |
| Joint General Investment Account | 3 (SWDA, VGOV, SGLN) | **1 placeholder row: "Cash", asset_type `cash`, £95,000, 100%** |
| David's Hargreaves Lansdown ISA | 3 (Fundsmith, Scottish Mortgage, Vanguard FTSE All-World) | 0 before this batch, 1 after |
| David's SIPP | 3 (VHVG, SLXX, LGUKP) | 0 |

So **9 of 10 were missing**, not 10, and the joint account is worse than empty — see D-11.

---

## Defects found — 6 (D-11 … D-16)

### D-16 (CRITICAL) — A field labelled "(Optional)" is NOT NULL, and leaving it blank prints raw SQL to the user

**Blocks holdings entry entirely.** Reproduced in the browser, twice.

Fill the Add Holding form leaving **"Dividend Yield % (Optional)"** blank — exactly as the
label invites — and submit. The page renders:

```
SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'dividend_yield'
cannot be null (Connection: mysql, SQL: insert into `holdings` (`asset_type`,
`sub_type`, `security_name`, `ticker`, `isin`, `allocation_percent`,
`purchase_price`, `purchase_…
```

Three faults in one:

1. **The "(Optional)" field is mandatory.** `holdings.dividend_yield` is
   `decimal(5,4) NOT NULL DEFAULT '0.0000'`. The column has a usable default; the code
   passes an explicit `null`, which overrides it.
2. **The raw database exception, including the INSERT statement and column list, is
   rendered to the end user.** That is information disclosure on top of a broken form.
3. **An earlier submit failed silently** — no row, no message, modal left open (that one
   was the conditional "Fund Type" select, which appears only after Asset Type = Fund and
   is enforced with no error text and no `required` attribute).

Root cause, and it is the **exact W-0052 pattern** — a `nullable` validation rule on a
NOT NULL column:

`app/Http/Requests/Investment/StoreHoldingRequest.php:44-45`
```php
'dividend_yield' => 'nullable|numeric|min:0|max:100',
'ocf_percent'    => 'nullable|numeric|min:0|max:100',
```
Same two lines in `UpdateHoldingRequest.php:44-45`.

**`ocf_percent` carries the identical latent 500** — also `decimal(5,4) NOT NULL DEFAULT
'0.0000'`. It did not fire here only because I filled 0.95. A user who leaves the Ongoing
Charge Figure blank gets the same raw SQL error.

**Confirmed workaround and confirmed diagnosis:** setting Dividend Yield to `0` made the
identical submit succeed on the next attempt.

Screenshots: `130-web-david-holding-fundsmith-before-save.png`,
`131-web-david-holding-optional-dividend-yield-cannot-be-null-sql-error.png`

### D-11 (HIGH) — The joint General Investment Account holds a placeholder "Cash" row for its entire £95,000

`holdings.id=33` on account 14: `security_name "Cash"`, `asset_type "cash"`,
`current_value 95000.00`, `allocation_percent 100.00`, `ocf_percent 0.0000`.

The persona's three holdings — iShares Core MSCI World (SWDA, 625 units, £50,000, 52.6%,
0.20%), Vanguard UK Government Bond (VGOV, 1,316 units, £25,004, 26.3%, 0.12%) and iShares
Physical Gold (SGLN, 84 units, £19,992, 21.1%, 0.12%) — are absent.

This is worse than an empty account. **The household's largest non-pension investment is
classified as 100% cash**, which feeds asset allocation, the diversification insights, the
rebalancing status and the risk engine. The £95,000 is 55% of David's portfolio and 72% of
Sarah's, so both spouses' allocation pictures are materially wrong, and in a direction that
would drive advice toward taking more equity risk.

### D-12 (MEDIUM) — The holdings table never shows units, cost or price — the fields the form now captures

Columns rendered: **Fund Name · Type · Allocation · Value · Ongoing Charge Figure.**

Not rendered anywhere: **Units Held, Purchase Price, Current Price, Purchase Date** —
all four are captured by the form and stored (row 62 above proves it). The persona records
units, cost per unit and price per unit for all ten holdings; a user can enter them and
never see one of them again.

This is the display half of W-0039. That item's fix made the fields enterable; nothing made
them visible.

### D-13 (HIGH) — Sarah's will still names Sarah as her own executor

`/estate/will-builder` as Sarah:

> **Executors** — Sarah Jones · Barclays Wealth

Persona (`peak_earners.md`, Sarah's Will): executor is **"David Jones & Barclays Wealth"**.
`wills.id=12` holds `executor_name = "Sarah Jones, Barclays Wealth"` — copied verbatim
from David's will.

This is **W-0024's exact symptom**, and W-0024 is at `status: handoff`. What I cannot tell
from the outside is whether the fix works for newly generated mirror wills and this row is
pre-fix residue, or whether the fix did not land. **Either way the user-visible state today
is wrong**, so it needs a re-generation test or a backfill, not just a code read.

Part of W-0024 *is* confirmed fixed: Sarah's charity is correctly **British Heart
Foundation**, not David's Cancer Research UK.

Screenshot: `129-web-sarah-will-self-executor-blank-gift-1716780.png`

### D-14 (MEDIUM) — "Specific Gifts: £10,000 to" — the beneficiary is missing

Rendered verbatim on Sarah's will page, under **Specific Gifts**:

> £10,000 to

Nothing follows "to". The money has an amount and no recipient. The bequest itself is
intact further down the page ("Specific Bequests — British Heart Foundation — £10,000"), so
this is the Specific Gifts summary failing to resolve the name it is formatting.

### D-15 (HIGH) — Sarah's will says she leaves her spouse £1,716,780; her estate is £739,280

Rendered on her will page:

> **Spouse as Primary Beneficiary** — Yes — **100% to spouse (£1,716,780)**

- Sarah's net worth, per the Wealth Summary and her own dashboard: **£739,280**
- The household's combined net worth: £2,216,780
- £2,216,780 − £500,000 (David's pensions) = **£1,716,780** — the figure shown

So a **household-derived** number is presented as what *she* leaves *him* on first death.
It counts David's own assets as passing from Sarah to David, and overstates her estate by
2.3×.

`resources/js/components/Estate/WillPlanning.vue:512-514`
```php
spouseAmount() {
  return this.netEstateValue * (this.form.spouse_bequest_percentage / 100);
}
```
`netEstateValue` is loaded at `:627` from `response.data.iht_summary.current.net_estate`.
Note the mobile dashboard payload gives Sarah `estate.net_estate = 739280` for the same
user in the same session — so **two "net estate" figures for Sarah differ by £977,500.**
Same family as W-0154 and W-0188.

---

## Reinforcement for W-0217 / R-20 D-09 — the subset now projects higher than the set that contains it

Account-level projections, both accounts, 10-year horizon, 80% band, "Medium Risk" label:

| | Whole portfolio | One account inside it |
|---|---|---|
| **David** | £172,500 → **£217,451** | Hargreaves Lansdown ISA £95,000 → **£325,309** |
| **Sarah** | £132,500 → **£316,777** | Hargreaves Lansdown ISA £85,000 → **£326,015** |

David's £95,000 account projects **50% higher** than the £172,500 portfolio that contains
it. Sarah's £85,000 account projects higher than her £132,500 portfolio. The account
projection and the portfolio projection are two mechanisms and they cannot both be right;
a subset outgrowing its superset is a contradiction on the face of it, on one page, on one
screen.

Note David's ISA has **no holdings at all** and still returns "Projected Value (80%)
£325,309" with "Annualised Return N/A".

Screenshot: `128-web-sarah-isa-85000-projects-326015-higher-than-whole-portfolio.png`

---

## Not done, and why

- **Remaining holdings not yet entered**: David's Scottish Mortgage and Vanguard FTSE
  All-World, his three SIPP holdings, and the joint account's three (which also needs its
  "Cash" placeholder resolved first). Now unblocked — the workaround is known — and this is
  my next action unless redirected.
- **David's will page not read this batch** — needed to see whether the £1,716,780 figure
  is shown identically on both wills. Next.
- Still queued from R-20: state pension, "Charlotte's Gap Year Fund", adviser fee,
  David's Upper Medium risk preference, `annual_allowance_used_gbp = 38.67`, `/m` parity.

## Assumptions

- I set Dividend Yield to `0` for the Fundsmith holding. The persona does not state a
  dividend yield, and `0.0000` is the column's own default, so this records "not stated"
  rather than inventing a figure.
- I chose Fund Type "Equity Fund" for Fundsmith Equity. The persona does not specify a fund
  sub-type and the field is enforced; Fundsmith Equity is an equity fund.

## Needs

- Board IDs for D-11 … D-16. **D-16 should jump the queue** — it blocks all remaining
  holdings entry, and it prints raw SQL to the user, which is a disclosure issue as well as
  a bug. It is also a two-line fix in two files, plus the same sweep W-0052 should already
  have prompted: **grep for `nullable` validation rules on NOT NULL columns across
  `app/Http/Requests/`.** This is the third instance of that pattern this run.
- D-13 needs a decision from whoever owns W-0024: re-generate the mirror will to test the
  fix, or backfill the row. I can drive either from the browser once told which.

## Noticed

- **A transient HTTP 500** at 18:30 — `Unable to find observer:
  App\Providers\UserDataCacheObserver`. The `use App\Observers\UserDataCacheObserver;`
  import at `EventServiceProvider.php:55` landed a moment later and the page recovered on
  retry. Another agent mid-write in the shared tree, almost certainly fixing R-19 D-05.
  Not a defect, but it is the `reference_formatter_strips_new_use_import` trap and it took
  the whole app down for about a minute.
- **The 6-box verification code input** cannot be driven by `pressSequentially` on the first
  box, and individual key presses failed on the third login of the session (`attempts` stayed
  null, so nothing reached the API). Setting each box's value with a native setter plus an
  `input` event works every time. Worth putting in the playbook.
- **Unicode-as-icons on a detail view** — the investment account Tax Treatment panel uses
  `✓ ! ⊘` and Diversification Insights uses `⚠ ℹ`. Rule 15 bans Unicode-as-icons and detail
  views are a banned surface, but the rule is forward-only and I cannot tell whether these
  predate it. **Routing as an observation, not a defect** — functional first, per the
  standing instruction that design items are parked.
- Sarah's Add Holding form offers only "Hargreaves Lansdown - ISA" in its Account dropdown;
  the joint General Investment Account she part-owns is absent, so she can see the account
  but not manage its holdings. Defensible by design (Rule 6 single record, David's row) —
  flagging, not raising.
