# R-23 — Cycle 4, batch 5

**Agent:** `peak-earners-c4` (persona-tester) · **Persona:** `peak_earners`
**Surface:** web, local · **Account:** David (16)
**Batch closed:** 2026-08-22 ~18:56 · Continues R-19 … [R-22](R-22-cycle4-batch-4.md)

Goals, fees, risk, and the investment projection. **D-20 is the most serious finding of
the run so far.**

---

## Done — three more persona gaps closed, three fixes confirmed green

### The sixth goal is entered — and W-0029 is GREEN
"Charlotte's Gap Year Fund" created through the form with **target date 2026-08-01, three
weeks in the past.** The date input carries no `min` attribute and the backend accepted it.

Row is right against the persona: custom type "Child Support", £15,000 target, £12,000
current, 2026-08-01, `assigned_module savings`, priority low, £400/month. Renders as
"Charlotte's Gap Year Fund · Savings · 80% complete · £15,000". All six persona goals now
exist.

(`ownership_type individual` and `is_essential 0` are W-0038, still `queued` — not re-raised.)

### Adviser fee, platform fee and account risk — W-0008 and W-0052 are GREEN
David's Hargreaves Lansdown ISA edit form carries **"Platform Fee"**, **"Adviser Fee
(% per year)"** and a **"Risk Level for This Account"** control (Low / Lower-Medium /
Medium / Upper-Medium / High).

Set 0.45%, 0.75% and High, saved with **no 500**, and all three persisted:
`platform_fee_percent 0.4500 · advisor_fee_percent 0.7500 · risk_preference high`.
**The three holdings survived the account update intact** — relevant to W-0009.

Screenshots: `137`, `139`

---

## Defects found — 3 (D-19, D-20, D-21)

### D-20 (CRITICAL) — The projected value shown to the user bears no relation to the simulation behind it, and tells a £172,500 investor they will have £4,650 in five years

**Reproduced in the browser, then traced against the simulator directly.**

David's portfolio, £172,500, no contributions, captioned "Using Upper-Medium risk profile
(7.07% expected return)", **80% probability** band:

| Horizon | "Projected Value (80%)" on screen | Implied annual rate |
|---|---|---|
| **5 years** | **£4,650** | **−58% a year** |
| 10 years | £217,451 | +2.34% |
| 20 years | £528,482 | +5.76% |
| 30 years | £767,649 | +5.10% |

The 5-year chart's y-axis tops out at £50.0K, so the whole curve is collapsed, not just the
headline. A user with £172,500 invested is being told, on a *conservative-but-not-worst*
band, that they will have **£4,650 in five years — a 97.3% loss.**

**The simulator is not the problem.** I ran `MonteCarloSimulator::simulate()` directly with
the same inputs the service passes (£172,500, no contributions, 7.07%, 12% volatility,
1,000 iterations, life-event map `[2 => −55000, 4 => −25000]`):

| | Simulator, 5-year final year | Simulator, 10-year |
|---|---|---|
| 10th percentile | **£88,914** | £304,491 |
| 25th percentile | £113,091 | £346,583 |
| 50th percentile | **£143,340** | £406,703 |
| 75th percentile | £181,095 | £482,806 |
| 90th percentile | £222,875 | £568,765 |

Its `year_by_year` and `final_percentiles` agree with each other, and the numbers are
sensible: £172,500 less £80,000 of life-event outflows, plus growth, gives a £143,340
median at five years. **Correct.**

And `InvestmentProjectionService::extractProbabilityBands()` (`:317-320`) computes the 20th
percentile by interpolation:
```php
$spread = $p25 - $p10;
$p20 = $p10 + ($spread * 0.67);
```
= 88,914 + (113,091 − 88,914) × 0.67 = **£105,113**.

**So the value that should be on screen at 5 years is about £105,113. The screen says
£4,650.** The fault therefore sits **between the band extraction and the rendered figure**
— not in the Monte Carlo, and not in the interpolation arithmetic. That is where a fixer
should start.

Two method problems worth fixing while in there, independent of the display bug:
- **The "20th percentile" is not a percentile.** It is a linear interpolation between the
  10th and 25th, presented to the user as "80% Probability".
- **The 5th percentile is extrapolated *below* the simulated range** (`:323`,
  `$p5 = $p10 - ($spread * 0.33)`) — a number outside anything the simulation produced.
- `blendValue()` (`:359-362`) pulls years 1 and 2 toward the start value by 30% and 10%,
  which flattens the early curve for presentational reasons.

Screenshots: `140-web-david-risk-5.41-to-7.07-projection-unchanged-217451.png`,
`141-web-david-5yr-projection-172500-to-4650.png`

### D-21 (HIGH) — Changing the risk level changes the caption and the label, and does not change the projection by a single pound

Directly observed, before and after saving the ISA's risk level as High:

| | Before | After |
|---|---|---|
| Panel label | Medium Risk | **Upper-Medium Risk** |
| Caption | "Using Medium risk profile (**5.41%** expected return)" | "Using Upper-Medium risk profile (**7.07%** expected return)" |
| Projected Value (80%), 10 years | **£217,451** | **£217,451** |

The expected return moved 5.41% → 7.07% and the projected value did not move at all. Over
ten years that gap should be worth roughly £50,000 on this portfolio.

The projection **is** live — changing the horizon changes the number (£4,650 / £217,451 /
£528,482 / £767,649) — so this is not a dead panel. A likely mechanism is the cache key at
`InvestmentProjectionService.php:160-162`:

```php
$cacheKey = empty($contributionOverrides)
    ? "user_{$user->id}_portfolio_{$years}y_e{$eventHash}"
    : null;
```

**It keys on user, horizon and a life-event hash — and not on the risk parameters**, which
are computed immediately above at `:140-143`. A risk change therefore cannot invalidate it.
I could not confirm the cache was populated at the moment of the test (the keys read `no`
when I checked afterwards), so I am giving the mechanism as the strongest candidate rather
than as proven. **The observed behaviour is proven; the cause is a lead.**

This matters for **W-0217**: if a projection can be served against a risk state other than
the current one, then "the lower-risk portfolio outgrows the higher-risk one" is exactly
the symptom you would expect, and comparing two users' projections is meaningless.

### D-19 (HIGH) — Every overdue goal reports "On track", and the page congratulates the user

David's goals page shows "Charlotte's Gap Year Fund · On track · 80% complete" — for a goal
whose target date passed on 2026-08-01, three weeks ago. "Max Pension Contributions"
likewise: target 2026-04-05, four and a half months past, 75% complete, **"On track"**. The
page summary reads **"All goals on track! Keep up the great progress"**.

Traced exactly. `app/Services/Goals/GoalCalculationService.php:56-81`:

```php
$totalDays  = $goal->start_date->diffInDays($goal->target_date);
if ($totalDays <= 0) { return $this->calculateProgressPercentage($goal) >= 100; }
$daysElapsed = $goal->start_date->diffInDays(now());
$expectedProgress = min(($daysElapsed / $totalDays) * 100, 100);
return $this->calculateProgressPercentage($goal) >= ($expectedProgress - 10);
```

For the goal I created: `start_date 2026-08-22` (auto-set to today at creation),
`target_date 2026-08-01`. **The start date is after the target date.** And
`Carbon::diffInDays()` returns an **absolute** value, verified:

```
diffInDays(2026-08-22 → 2026-08-01) = 21      (not −21)
daysElapsed(2026-08-22 → now)       = 0
expectedProgress                    = 0
→ 80 >= (0 − 10)  →  true
```

So the `$totalDays <= 0` guard at `:72`, which exists precisely to catch a non-positive
span, **never fires on an inverted range** — the absolute value hides the inversion. Every
overdue goal then compares its progress against an expected progress of ~0 and passes.

Two things to fix: `start_date` should never be set later than `target_date`, and a passed
target date should be evaluated as overdue rather than run through the elapsed-time
formula.

**This is a direct consequence of W-0029's fix.** Making past-dated goals creatable was
right; the on-track maths was never updated to cope with them. A regression pass is exactly
how that gets caught.

Screenshot: `138-web-david-goals-overdue-shown-on-track.png`

---

## Not done, and why

- **Sarah's side of D-19, D-20 and D-21 not re-checked** after these changes — her earlier
  readings (R-20, R-21) already show the same projection shape, so I have not spent a
  second login on it.
- The joint account's "Cash" placeholder (R-21 D-11) — **still awaiting the coordinator's
  decision**, untouched.
- David's SIPP holdings — blocked by R-22 D-17.
- `dc_pensions.annual_allowance_used_gbp = 38.67` and `/m` parity remain queued.

## Assumptions

- For the direct simulator run I used volatility 12%, since the service passes
  `$riskParams['volatility'] / 100` and I did not read the Upper-Medium volatility constant.
  The conclusion does not depend on it: the screen shows £4,650 against a simulated 10th
  percentile of £88,914, a factor of nineteen, which no plausible volatility closes.
- I read the "80% Probability" band as "80% chance of at least this value", per the chart
  legend. On the opposite reading D-20 is worse, not better.

## Needs

- Board IDs for **D-19, D-20, D-21**. **D-20 is the one to move first** — it is a headline
  financial figure, shown to the user, that is wrong by a factor of twenty in the direction
  that causes alarm, and I have localised it to a specific hand-off point between two
  components that are each individually correct.
- **D-21 should go to whoever takes W-0217**, with the cache-key lead. The two are very
  likely the same story.

## Noticed

- The **goals page is slow to become interactive** — its Add Goal control needs several
  seconds after load before it responds, and clicking early does nothing silently. I logged
  this as a false positive once already (R-21, the savings Add Account). It is the same
  shape and it will catch the next tester too.
- `has_custom_risk` stayed `0` after I set the account risk to High via the per-account
  control. The risk did reach the label and the caption, so this may be nothing — flagging
  in case that column is meant to gate whether the account-level choice is honoured
  downstream.
