# R-22 — Cycle 4, batch 4

**Agent:** `peak-earners-c4` (persona-tester) · **Persona:** `peak_earners`
**Surface:** web, local · **Account:** David (16)
**Batch closed:** 2026-08-22 ~18:45 · Continues R-19, R-20, [R-21](R-21-cycle4-batch-3.md)

Batch 4 finished the entry work batch 3 started, and covered pensions and the second will.

---

## Done — and two persona gaps closed for real

### David's Stocks & Shares ISA holdings — all three entered, all three exact

Entered through the form, verified in the browser and against the row:

| Holding | Displayed | Persona | Row |
|---|---|---|---|
| Fundsmith Equity (FUND, GB00B41YBW71) | Fund · 36.80% · £35,051 · 0.95% | £35,051 · 36.8% · 0.95% | qty 351, £85.50/£99.86, cost_basis £30,010.50 |
| Vanguard FTSE All-World (VWRL, IE00B3RBWM25) | ETF · 36.90% · £34,977 · 0.22% | £34,977 · 36.9% · 0.22% | qty 318, £93.00/£109.99, cost_basis £29,574.00 |
| Scottish Mortgage (SMT, GB00BLDYK618) | UK Equity · 26.30% · £25,000 · 0.34% | £25,000 · 26.3% · 0.34% | qty 2500, £8.40/£10.00, cost_basis £21,000.00 |

Weighted average Ongoing Charge Figure computed to **0.52%**, which reconciles by hand:
(35,051×0.95 + 34,977×0.22 + 25,000×0.34) ÷ 95,028 = 0.521%. The "Cash (unallocated)"
remainder row correctly disappeared once allocation reached 100%.

**W-0039 and the holding create path are GREEN**, subject to the D-16 blocker in R-21.

### State pension — entered for David, and correct

"Add Pension → State Pension" offers Forecast Weekly Amount, Qualifying Years, Forecast
Date and a National Insurance gaps flag. Entered £221.20 weekly and 30 qualifying years.

- Renders **"State Pension · UK State Pension · Annual Pension £11,502"** — the persona's
  figure exactly (£221.20 × 52 = £11,502.40).
- Row: `ni_years_completed 30`, `ni_years_required 35`, `ni_completion_pct 85.71`,
  `state_pension_forecast_annual 11502.40`.
- The completeness panel moved from "5 of 7 · OUTSTANDING (2)" to **"6 of 7"** with "Your
  State Pension forecast" now COMPLETED.

**No State Pension Age field, correctly** — it is statutory and derivable from date of
birth, so asking would be wrong.

Screenshots: `133-web-david-isa-three-holdings-entered-correct.png`,
`136-web-david-state-pension-before-save.png`

### Also verified GREEN
- The **pension pot projection is well-behaved**: £500,000 → £847,943 over 11 years at a
  stated 5.00%. £500,000 × 1.05¹¹ = £855,000, so the 80% band sits just below the mean —
  exactly as it should. **This sharpens R-20 D-09 / W-0217: the misbehaviour is specific to
  the investment projection, not to the Monte Carlo generally.**
- David's will executors are **correct** — "Sarah Jones · Barclays Wealth", as the persona
  requires. The mirror-will executor fault is on Sarah's side only (R-21 D-13).
- David's charity bequest is correctly **Cancer Research UK**.
- "Any final salary or career average pensions" is correctly OUTSTANDING for David — the
  NHS scheme is Sarah's.

---

## Defects found — 2 new (D-17, D-18), 2 strengthened

### D-17 (HIGH) — A Defined Contribution pension's holdings cannot be entered at all

David's SIPP detail view offers three tabs: **Overview · Projections · Documents**. There is
no Holdings tab, no Add Holding control, and no other route to a pension's holdings.

The data model supports them — `holdings.holdable_type` accepts
`App\Models\DCPension`, and a seeded pension (id 16) carries three holdings with units,
prices and Ongoing Charge Figures. So the capability exists in the schema and in seeded
data, and is absent from the UI.

This **blocks 3 of the persona's 10 holdings** — David's SIPP should hold Vanguard Global
Equity (VHVG, 4,211 units, £160,018, 50%), BlackRock Corporate Bond (SLXX, 800 units,
£96,000, 30%) and L&G UK Property (LGUKP, 50,000 units, £64,000, 20%). A £320,000 pension
therefore has no asset allocation, no fee analysis and no look-through at all, and the
detail panel reports **"Platform Fee 0.00% · Total Annual Cost 0.00% · Annual Fee Impact
£0/year"** on a SIPP the persona charges at 0.25%.

Screenshot: `134-web-david-sipp-detail-age-67-vs-row-60-no-holdings-tab.png`

### D-18 (MEDIUM) — The pension detail shows Retirement Age 67 where the row says 60, on a page whose own header says 60

David's SIPP detail, "Pension Details → **Retirement Age: 67**".

- `dc_pensions.id=10.retirement_age` = **60**. So does id 9. Both were entered as 60.
- The same page's header reads **"Age you want to retire · 60"**.
- The persona sets David's target retirement age to 60 and both pensions' retirement age to 60.

`resources/js/components/NetWorth/PensionDetailInline.vue:483-485`
```js
userRetirementAge() {
  return this.user?.target_retirement_age || 67;
},
```
Rendered at `:142` under the label "Retirement Age:".

Two faults in three lines:

1. **It renders the wrong field.** On a *pension* detail panel, under a label a user reads
   as *this pension's* retirement age, it prints the *user's* target retirement age — never
   `pension.retirement_age`, which is what the pension's own form captures
   (`DCPensionForm.vue:289-292`).
2. **A hardcoded `67` fallback**, which is what is actually showing, since
   `this.user.target_retirement_age` is not populated in this component.

**Route to W-0196.** That item enumerates seven backend `DEFAULT_RETIREMENT_AGE` constants
and four copies of the priority chain; this is an **eighth default, in the frontend**, that
its inventory does not list — and W-0196's stated goal is that the question has one answer
in one place. The wrong-field half is a separate bug and should be called out as such.

### D-15 strengthened — both wills show the identical £1,716,780

R-21 raised this on Sarah's will. David's will shows **the same number**:

> Spouse as Primary Beneficiary — Yes — **100% to spouse (£1,716,780)**

So each spouse is told they leave the other £1,716,780. It matches neither estate:
David's net worth is £1,477,500, Sarah's £739,280, the household £2,216,780, and
£1,716,780 is household-minus-David's-pensions. **One figure, shown to two people, wrong
for both.**

Screenshot: `135-web-david-will-same-1716780-as-sarah.png`

### D-14 confirmed on both accounts — "Specific Gifts: £10,000 to"

The dangling render appears identically on David's will. Not a Sarah-side artefact.

---

## Corroboration for existing board items — not raised again

- **W-0197** (*which state pension age a forward projection reads*): the row I just created
  has `state_pension_age = NULL` and `years_to_state_pension_age = NULL`, both with a
  `_calculated_at` timestamp set — so the derivation ran, against a known date of birth
  (1976-11-08), and produced null.
- **Projected Gross Income did not move** after the state pension was added (£39,853 before
  and after). That is arguably *correct* — David retires at 60 and State Pension Age is 67,
  so it should not count yet — but nothing on screen says so, and W-0197 is exactly the item
  for that ambiguity.

---

## Not done, and why

- **The joint account's "Cash" placeholder (R-21 D-11) is untouched** — I asked the
  coordinator before mutating it, since resolving it means editing or deleting a row and
  W-0009 (*holding edit discards its payload*) is at `handoff`, so it doubles as that
  item's live test. Awaiting the call.
- **The SIPP's 3 holdings cannot be entered** — blocked by D-17, not by me.
- Still queued: "Charlotte's Gap Year Fund" (missing goal, and its target date 2026-08-01
  is in the past, which is W-0029 territory), adviser fee 0.75% on four accounts, David's
  platform fee 0.45%, his Upper Medium risk preference, `annual_allowance_used_gbp = 38.67`,
  and `/m` parity for everything raised in R-19 through R-22.

## Assumptions

- Fund Type "Equity Fund" for Fundsmith; Asset Type UK Equity for Scottish Mortgage
  (an investment trust listed in London) and ETF for Vanguard FTSE All-World. The persona
  names the type for each of these, and I followed it.
- Dividend Yield 0 on all three new holdings — the persona states none, and 0.0000 is the
  column default. See R-21 D-16 for why leaving it blank is not an option.
- I left Forecast Date blank on the state pension; the persona gives no forecast date.

## Needs

- Board IDs for **D-17** and **D-18**. D-18 should attach to **W-0196**.
- The D-11 decision (replace the joint account's "Cash" row, or leave it as evidence).

## Noticed

- The retirement completeness panel updated correctly on reload but **not immediately after
  the save** — it still listed "Your State Pension forecast" as OUTSTANDING on the same
  render that showed the new pension. Cosmetic staleness, one reload clears it; not raising.
