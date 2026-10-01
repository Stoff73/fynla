# Retirement cards: review and design

**Status:** APPROVED. CSJ answered D1 to D5 on 2026-10-01, each as recommended.
**Date:** 2026-10-01
**Item:** `todoCurrent/TODO.md` item 7.

## 1. What the review found

### 1.1 Cards do not carry their definition, so no how-to can reach them

`RetirementActionDefinitionService` (`app/Services/Retirement/`) evaluates 19 agent definitions. Each `evaluate*` method builds its own rec array; none sets `definition_key` or `figures`. `RetirementRecommendationAdapter` then types each rec from a seven-row category map (`:23-31`):

| Category | Becomes type | Definitions behind it |
|---|---|---|
| `Contribution_increase`, `Employer_match`, `Start_contributions` | `increase_pension_contribution` | `contribution_increase`, `employer_match`, `start_contributions` |
| `Salary Sacrifice` | `salary_sacrifice_pension` | `salary_sacrifice_available`, `salary_sacrifice_floor_warning` |
| `Tax Planning` | `carry_forward_unused_allowance` | `tax_relief`, `annual_allowance_exceeded` |
| `Retirement Planning` | `plan_retirement_income` | `adjust_retirement_age` |

So `ActionCardService::howTo` (`:200`) gets no key and returns nothing for any retirement card. The ids are wrong as well. On csjones, "Consider Adjusting Retirement Age" has the id `retirement_plan_retirement_income`, and "Optimise Pension Tax Relief" has the id `retirement_carry_forward_unused_allowance`. Two `Tax Planning` cards on one list collide and are told apart by a headline hash (user 379: `…_308254c2`, `…_0433029b`). This is where protection stood before #972.

### 1.2 Which keys fire (csjones, 18 households with a retirement profile, 2026-10-01)

| Key | Households |
|---|---|
| `contribution_increase` | 12 |
| `state_pension_no_forecast` | 9 |
| `adjust_retirement_age` | 9 |
| `salary_sacrifice_available` | 7 |
| `auto_enrolment_below_minimum` | 5 |
| `tax_relief` | 5 |
| `start_contributions` | 3 |
| `care_costs_not_modelled` | 3 |
| `employer_match` | 2 |
| `high_pension_total_fees`, `ni_gaps`, `annual_allowance_exceeded` | 1 each |

These never fired: `salary_sacrifice_floor_warning`, `approaching_decumulation`, `pension_value_unknown`, `enhanced_annuity_eligible`, `pension_consolidation_opportunity`, `high_pension_platform_fees`, `high_pension_fund_fees`.

**The three goal definitions are not cards.** `goal_no_contribution`, `goal_behind_schedule` and `goal_deadline_approaching` are evaluated by `evaluateGoalActions`, which only the Retirement plan page calls (`RetirementPlanService:58`). They never reach the actions list, so they need no how-to. (CORRECTED 2026-10-01: the first draft said nothing calls it; a truncated search hid `RetirementPlanService:58`.)

**`pension_value_unknown` is missing from the local database** (25 rows, seeder 26). That is seed drift; a reseed fixes it.

### 1.3 The same action on two cards

**Across modules: the Tax plan already carries three of them.** Two engines give one piece of advice (Rule 20), and the retirement copy has none of the Tax plan's checks:

| Retirement card | Tax plan card | Live evidence (csjones) |
|---|---|---|
| `salary_sacrifice_available`, "Consider Salary Sacrifice Arrangement… save you £75.00" | `salary_sacrifice_ni`, "Save around £75 a year by moving your pension contributions to salary sacrifice" | James (375) and David (377) get both cards on one list |
| `tax_relief`, "you can save £24,000.00 in tax by contributing an additional £60,000.00" | `pension_tax_relief` / `pa_taper_rescue` / `additional_rate_avoidance` (approved how-tos, capped by `PensionAffordability`) | James (375): £60,000 suggested. The retirement rule offers the whole Annual Allowance with no affordability check, the defect item 1 removed from the Tax plan ("affordability check always", CSJ 2026-09-30). |
| `annual_allowance_exceeded`, "exceeded your annual allowance by £16,995.00" | `tapered_annual_allowance`, `pension_aa_carry_forward` | Alex (379) is told he is £16,995 over the allowance and, on the same list, to pay in another £20,004 for relief |

`tax_relief` also uses its own band rule: employment income only, with typed-in 45/40/20 relief rates (`:791-796`). Config holds them in `pension.tax_relief`.

**Within Retirement: the cards about not saving enough for the target overlap.**
- `contribution_increase` ("contribute an additional £X a month"), `adjust_retirement_age` ("retire at 68 instead of 65") and `start_contributions` all answer one shortfall. Nine households get two or three of them.
- `contribution_increase` does not size its amount from the shortfall. It offers the whole remaining allowance plus carry forward, capped at earnings (`:640-664`), with no affordability check: £4,375 a month for James, and £3,066.67 for David.
- `auto_enrolment_below_minimum` and `employer_match` fire on the same workplace pension.
- `high_pension_total_fees`, `high_pension_platform_fees` and `high_pension_fund_fees` can fire three cards for one pension and one action: reviewing its charges.

### 1.4 Saver cards for people drawing their pension

Item 6 defined who is drawing: retired, past a recorded retirement date, or a pension in drawdown (`RetirementDrawdownPosition::isDrawing`). Only `adjust_retirement_age` checks this (`:1177`). On csjones:
- Patricia (381) and Harold (382), retired preview personas, are told "Increase Pension Contributions… an additional £300.00 per month". Their £300 is the £3,600 non-earner limit divided by 12, not a figure from their position.
- Pensionm (405), retired in 2022, gets "Start Pension Contributions".

**Pat (439), drawing with no retirement profile, gets no retirement card at all.** `evaluateAgentActions` returns nothing without a profile (`:48`), so even `state_pension_no_forecast` and the fee cards are lost.

### 1.5 Card text that breaks the rules (seeder `RetirementActionDefinitionSeeder.php`)

- **Unsourced figures (Rule 23):**
  - "the average annual cost of residential care in the UK exceeds £35,000" (`:325`);
  - "Enhanced annuities typically pay 15-25% more" (`:307`);
  - "index tracker funds with ongoing charges below 0.25%" (`:439`).
- **Typed-in figures (Rule 2):**
  - the 8% auto-enrolment minimum, in the text (`:289`) and the code (`:1642`);
  - `conservative_proxy_floor` falls back to 10000 (`:1439`).
- **Product names:** "Platforms like Vanguard and Fidelity" (`:420`). Every card says "Fynla does not recommend products" (`ActionCardService::DISCLAIMER`).
- **Tone:** "This is free money!" (`:102`).
- **Cold acronym:** "Pension Commencement Lump Sum" needs no acronym, but the decision trace's "PCLS" reaches Fyn.
- **Title Case titles,** unlike every approved module ("Consider Salary Sacrifice Arrangement").

### 1.6 Is there a "your position" view?

**On the page, yes. On the cards, no.**
- **The page:** savers see projected income against the target on the Retirement page, and people drawing see item 6's drawing view (`RetirementDrawdownPosition`).
- **The cards:** no card states the shortfall the page shows. Each contribution card works out its own amount from the allowance instead, so card and page can disagree (1.3).

## 2. The rules this rests on

- **Relief is limited to the greater of relevant UK earnings and the basic amount** (Finance Act 2004 s189-190, https://www.legislation.gov.uk/ukpga/2004/12/section/190). There is no relief after 75 (s188(3)(a)). Config keys: `pension.relevant_earnings_minimum`, `pension.relief_max_age`.
- **Once a pension is flexibly accessed, the Money Purchase Annual Allowance applies** to further defined contribution saving (FA 2004 s227ZA–227ZF; gov.uk https://www.gov.uk/tax-on-your-private-pension/annual-allowance). Config: `pension.mpaa` (£10,000).
- **Auto-enrolment minimum:** "8%" in total, at least "3%" from the employer, on earnings between £6,240 and £50,270 (gov.uk, https://www.gov.uk/workplace-pensions/what-you-your-employer-and-the-government-pay; Pensions Act 2008). The rates and the earnings band go into tax config, not the code.
- **Salary sacrifice cannot take pay below the National Minimum Wage:** "A salary sacrifice arrangement must not reduce an employee's cash earnings below the National Minimum Wage (NMW) rates" (gov.uk, https://www.gov.uk/guidance/salary-sacrifice-and-the-effects-on-paye). The floor's fallback of 10000 goes; the floor comes from config only.
- **Affordability:** `App\Services\Tax\PensionAffordability`, the one rule every pension suggestion uses (item 1 spec, `docs/superpowers/specs/2026-09-30-partner-pension-affordability-design.md`).

## 3. Design

### 3.1 Cards carry their definition (no decision needed; copies #972)

- `evaluateAgentActions` sets `definition_key` on each rec from its definition. Each evaluator adds `figures` (its template vars, scalars only), as `ProtectionActionDefinitionService::evaluateStrategyCondition` does.
- `RetirementRecommendationAdapter` types a rec by its `definition_key`, falling back to the category map. It carries `definition_key` and `figures` in `extra`.
- **Ids become `retirement_<key>`,** plus `_a<pension id>` for per-pension rules (they already carry `account_id`). Done marks on today's category ids lapse once, as protection's did.
- `RecommendationRouting` maps the new rule keys to the same destinations as today.
- **Locally:** reseed `RetirementActionDefinitionSeeder` (`pension_value_unknown`).

### 3.2 One engine per action (decision D1)

**Approved and built:**
- `tax_relief` and `salary_sacrifice_available` stop producing cards (disabled in the seeder, kept in the file with a note pointing at the Tax plan type that carries each one).
- The Tax plan's approved cards carry these actions, with affordability and how-tos already in place.
- `salary_sacrifice_floor_warning` stays: no Tax plan card checks the pay floor.

**Corrected during the build (2026-10-01):** `annual_allowance_exceeded` stays enabled. The first draft said the Tax plan carries it through `tapered_annual_allowance` and `pension_aa_carry_forward`. It does not:
- `TaperedAnnualAllowanceStrategy` warns only about the taper;
- no Tax plan card warns someone who has already paid in more than their allowance.

D1's intent was to leave to the Tax plan what it already carries, so this card stays, rewritten from gov.uk ("If you go over your annual allowance, either you or your pension provider must pay the tax", https://www.gov.uk/tax-on-your-private-pension/annual-allowance).

Alex's contradiction still goes: the Tax plan's relief card stops once the allowance is used (`PensionTaxReliefStrategy` reads `TaxStrategyMath::availableAnnualAllowance`), and the retirement `tax_relief` card is gone.

### 3.3 One retirement income card (decision D2)

**Recommended, the protection pattern:** a `retirement_income_position` definition folds in `contribution_increase`, `adjust_retirement_age` and `start_contributions` as reasons.
- **It fires when** the projection the page shows has an income gap.
- **Title:** "Your retirement income is about £X a year short of your target". Its figures come from the page's projection (`summary.income_gap`, target and projected income), so card and page agree.
- **The how-to gives the real numbers** (memory `feedback_compute_real_numbers_never_either_or`):
  - the monthly contribution that closes the gap;
  - the part of it `PensionAffordability` can fund;
  - when that falls short, the retirement age that closes the rest.
- **Its branches cover the reasons:** no contributions at all; contributions being made.
- `employer_match` and `auto_enrolment_below_minimum` stay separate (each is a specific change on one pension), but they become one card per pension when both fire (D3).

### 3.4 Cards for people drawing (decision D4)

**Recommended:**
- **For anyone `isDrawing` and no longer working:** no contribution, salary sacrifice, employer match, auto-enrolment or retirement-age card.
- **For someone drawing while still working:** contribution cards stay, capped at the Money Purchase Annual Allowance once a pension has been flexibly accessed.
- **Without a retirement profile,** the definitions that need none still run: State Pension forecast, National Insurance gaps, fees, consolidation and care costs. Pat (439) gets them.

### 3.5 Fees: one card per pension (decision D3)

**Recommended:** `high_pension_total_fees`, `high_pension_platform_fees` and `high_pension_fund_fees` fold into one card per pension: "Review the charges on {pension}", listing whichever charges are above their thresholds. The same applies to `employer_match` with `auto_enrolment_below_minimum` on one workplace pension.

### 3.6 Text (no decision needed; defects)

- **Remove** the unsourced figures (£35,000, 15-25%, 0.25%), the product names and "free money".
- **Move** the 8% into tax config, with the legislation source.
- **Write titles** in sentence case with the user's figure, as the approved modules do ("Pay 2% more into {scheme} to get your employer's full match").
- **Approval:** the new titles go to CSJ with the how-to batch.

### 3.7 Goal definitions (D5, withdrawn)

Withdrawn after approval: the premise was wrong (1.2, corrected). The goal definitions feed the Retirement plan page and stay as they are.

## 4. Decisions for CSJ

- **D1.** Retirement stops carrying the actions the Tax plan already carries (3.2). Built for two of the three: the Annual Allowance excess has no Tax plan card, so it stays.
- **D2.** One "retirement income position" card replaces the contribution, start and retire-later cards (3.3)?
- **D3.** One card per pension for charges, and for employer match with the auto-enrolment minimum (3.5)?
- **D4.** No saver cards for someone drawing who no longer works; drawing while working is capped by the Money Purchase Annual Allowance (3.4)?
- **D5.** Withdrawn (3.7): the goal definitions do run, on the Retirement plan page.

## 5. How-tos

Written after the decisions, in `database/seeders/data/action-how-to/retirement.md` (the `savings.md` / `protection.md` format), with `'retirement' => [RetirementActionDefinition::class, 'key']` in `ActionHowToSeeder::SOURCES`. Every entry starts as `draft` and every claim is sourced. The draft is merged to `dev` before CSJ reviews it.

## 6. Testing

- **Unit:**
  - each definition carries its key and figures;
  - ids are `retirement_<key>`;
  - the folds produce one card carrying the right reasons;
  - no saver card for a drawing retiree;
  - cards without a profile.
- **Cards:** `ActionCardService` returns an approved retirement how-to by key.
- **Live walk on csjones, web 1440 + `/m` 390:** James (375), Alex (379), Patricia (381) and Pat (439). Check card titles, ids, no duplicates with the Tax plan, and that card and page figures agree.
