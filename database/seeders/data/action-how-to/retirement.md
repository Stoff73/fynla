# How-to steps: retirement actions

This file is the one source for the steps on each retirement action's detail card. `ActionHowToSeeder` reads it, and only entries marked `status: approved` ever reach a user. CSJ reviews each entry and changes `draft` to `approved`, or edits it. The grammar is the tax file's (`tax.md`): `why:`, `when …:`, `always:`, `outcome:`, `learn:`, conditions, and `{placeholders}`.

**Where the cards come from.** Retirement cards are the retirement action definitions (`RetirementActionDefinitionService::evaluateAgentActions`), typed by their key since 2026-10-01 (spec `docs/superpowers/specs/2026-10-01-retirement-cards-review-design.md`). Before that the adapter typed them from their category, so no how-to could reach them.

**The figures.** Each card's own figures reach its steps, listed under each entry. They arrive already written, for example `{shortfall}` as "£10,857" and `{employee_percent}` as "3.0". A step whose figure is missing is left out.

**Also available:** everything in `tax.md` ("What you can branch on", "What you can fill in"): for example `has_workplace_pension`, `has_personal_pension`, `has_no_pension`, `above_basic`, `employed`, `auto_enrolled`, `{workplace_pension}`, `{personal_pension}`, `{ae_min_employer}`, `{carry_forward_years}`, `{normal_minimum_pension_age}`.

**Not written, on purpose:**
- **Folded into a consolidated card (CSJ 2026-10-01):**
  - `contribution_increase`, `adjust_retirement_age` and `start_contributions` are reasons on `retirement_income_position` (D2).
  - `high_pension_total_fees`, `high_pension_platform_fees` and `high_pension_fund_fees` are reasons on `pension_charges_review` (D3).
  - `auto_enrolment_below_minimum` is a reason on `employer_match` when both fire (D3); its own entry covers it when it fires alone.
- **Disabled, the Tax plan carries them (D1):** `tax_relief` (Tax plan `pension_tax_relief`, `pa_taper_rescue`, `additional_rate_avoidance`) and `salary_sacrifice_available` (Tax plan `salary_sacrifice_ni`).
- `care_costs_not_modelled`: there is nowhere to enter care costs. No form, API or Fyn tool writes `retirement_profiles.care_cost_annual`, so no step could be followed (see `todoCurrent/TODO.md` item 7).
- `goal_no_contribution`, `goal_behind_schedule`, `goal_deadline_approaching`: Retirement plan page only (`RetirementPlanService`), never a card.
- `strategy_*`: the composer's catalogue rows, never a card.

Rules for these steps:
- **Every step rests on the sources named under its heading** (Rule 23), or on the card's own figures.
- **Guidance, not advice.** The steps say how, not whether, and never name a provider or a product.
- **Speak to the user about their own pensions.** A step never names Fynla; where something acts, it is Fyn or a named page.
- **No tax figure is typed in** (Rule 2): rates, allowances and ages come from the card's figures or tax config.

## retirement_income_position
status: approved
source: the Retirement page's projection (`RetirementAgent::analyze` summary: target, projected income, shortfall; `PensionProjector`); the contribution that closes it inverts the same projection (`PensionProjector::extraContributionForIncome`); what can be paid is capped by `PensionAffordability` (CSJ 2026-09-30, "affordability check always") and by the relief limit: Finance Act 2004 s190 relevant UK earnings less what the member already pays this year, the basic amount only on relief at source (https://www.legislation.gov.uk/ukpga/2004/12/section/190, s191(7)), s227ZA and s227G Money Purchase Annual Allowance once a pension is flexibly accessed; s192 relief at source (https://www.legislation.gov.uk/ukpga/2004/12/section/192); s188(3)(a) no relief from 75 (`pension.relief_max_age`); https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief (relief at source and net pay; claiming higher-rate relief); https://www.gov.uk/guidance/salary-sacrifice-and-the-effects-on-paye ("A salary sacrifice arrangement must not reduce an employee's cash earnings below the National Minimum Wage"); the card's own figures
figures: summary, shortfall, target_income, projected_income, target_age, years_to_retirement, needed_monthly, affordable_monthly, payable_monthly, payable_net_monthly, age_to_close, short_at_last_age, last_age, affordability_known, closes_gap, pays_something, contribution_increase, start_contributions, adjust_retirement_age, scheme_name
why:
1. At {target_age} your retirement income is on course for about {projected_income} a year. Your target is {target_income}, so you are about {shortfall} a year short.
why when start_contributions:
2. Nothing is being paid into your {scheme_name} at the moment.
when not affordability_known:
1. About {needed_monthly} a month more going into your pensions from now until {target_age} would close the gap.
2. Add your monthly spending to your profile, so Fyn can work out how much of that you can afford.
when affordability_known and closes_gap:
1. {payable_monthly} a month more going into your pensions from now until {target_age} would close the gap, and your spending leaves room for it.
when affordability_known and pays_something and not closes_gap:
1. Closing the whole gap would take about {needed_monthly} a month more going into your pensions. After your spending, you can afford about {payable_net_monthly} a month, which becomes {payable_monthly} in a personal pension once the provider adds basic-rate relief.
when affordability_known and not pays_something:
1. Closing the gap would take about {needed_monthly} a month more going into your pensions, and your spending leaves nothing spare to pay in at the moment.
when affordability_known and not closes_gap and age_to_close:
2. Paying in what you can and retiring at {age_to_close} instead of {target_age} would reach your target.
when affordability_known and not closes_gap and short_at_last_age:
2. Even retiring at {last_age}, you would still be about {short_at_last_age} a year short. Lowering your target is the other way to close the gap.
when has_workplace_pension and pays_something:
3. To pay through work, ask your employer to raise your contribution to {workplace_pension}, and ask whether they offer salary sacrifice. What it costs you depends on how the scheme gives tax relief, so ask payroll. Your cash pay after any sacrifice must not fall below the National Minimum Wage.
when has_personal_pension and pays_something:
4. Or pay {payable_net_monthly} a month into {personal_pension}. The provider claims basic-rate relief from HM Revenue and Customs (HMRC) and adds it, so {payable_monthly} goes in.
when has_no_pension and pays_something:
4. You have no pension recorded. Open a personal pension or self-invested personal pension (SIPP) and pay {payable_net_monthly} a month into it. The provider claims basic-rate relief and adds it, so {payable_monthly} goes in.
when above_basic and pays_something:
5. If you pay into a pension that uses relief at source, claim the relief above the basic rate on your Self Assessment tax return. If you do not file one, GOV.UK explains how to claim from HM Revenue and Customs (HMRC).
always:
6. Check the projection on the Retirement page after any change.
outcome when closes_gap:
1. Your projected retirement income reaches your target of {target_income} at {target_age}.
outcome when not closes_gap:
1. Your projected retirement income moves closer to your target of {target_income}.
learn:
1. How your retirement projection works | /help#retirement

## employer_match
status: approved
source: https://www.gov.uk/workplace-pensions/what-you-your-employer-and-the-government-pay (minimum "8%" in total, at least "3%" from the employer, on earnings between the lower and upper qualifying earnings limits; schemes may pay more than the minimum); Pensions Act 2008; `pension.auto_enrolment` in tax config; the card's own figures
figures: scheme_name, employee_percent, additional_percent, auto_enrolment_below_minimum, total_percent, minimum_percent, shortfall_annual
why:
1. You pay {employee_percent}% of your salary into {scheme_name}.
why when auto_enrolment_below_minimum:
2. You and your employer pay {total_percent}% in total, below the {minimum_percent}% auto-enrolment minimum on qualifying earnings: about {shortfall_annual} a year less than the minimum.
always:
1. Ask your employer, or check the scheme's rules, how their contribution to {scheme_name} changes with yours, and the most they will add.
2. If paying more brings in more from them, ask payroll to raise your contribution to the level that gets their full contribution.
when auto_enrolment_below_minimum:
3. Ask payroll to check your contributions against the minimum: at least {minimum_percent}% of qualifying earnings in total, with at least {ae_min_employer} from your employer.
always:
4. Update the contribution rates on {scheme_name} on the Retirement page once they change.
outcome:
1. More goes into {scheme_name} each month: from you, and from your employer if their contribution rises with yours.
learn:
1. How your retirement projection works | /help#retirement

## auto_enrolment_below_minimum
status: approved
source: https://www.gov.uk/workplace-pensions/what-you-your-employer-and-the-government-pay (minimum "8%" in total, at least "3%" from the employer, on qualifying earnings); Pensions Act 2008; `pension.auto_enrolment` in tax config; the card's own figures
figures: total_percent, minimum_percent, shortfall_annual
why:
1. You and your employer pay {total_percent}% of your qualifying earnings into your workplace pension. The minimum is {minimum_percent}%, so about {shortfall_annual} a year less goes in than the minimum.
always:
1. Check your payslip for what you and your employer each pay into the pension.
2. Ask payroll to check them against the minimum: at least {minimum_percent}% of qualifying earnings in total, with at least {ae_min_employer} from your employer.
3. Update the contribution rates on the Retirement page once they are corrected.
outcome:
1. At least the legal minimum goes into your workplace pension.

## annual_allowance_exceeded
status: approved
source: https://www.gov.uk/tax-on-your-private-pension/annual-allowance ("If you go over your annual allowance, either you or your pension provider must pay the tax"; reported on a Self Assessment tax return; "You might be able to carry over any annual allowance you did not use from the previous 3 tax years"; the lower "money purchase annual allowance" once you flexibly access your pension); Finance Act 2004 s228A carry forward (https://www.legislation.gov.uk/ukpga/2004/12/section/228A), s227ZA Money Purchase Annual Allowance; the excess is after the carry forward recorded (`AnnualAllowanceChecker`); `pension.carry_forward_years` in tax config; the card's own figures
figures: excess_amount, carry_forward_years, carry_forward_recorded, mpaa_applies
why:
1. You have paid {excess_amount} more into pensions this tax year than your Annual Allowance and the unused allowance from earlier years that is recorded.
why when mpaa_applies:
2. You have taken money flexibly from a pension, so the lower Money Purchase Annual Allowance applies to what you pay into defined contribution pensions, and unused allowance from earlier years cannot cover payments above it.
when not mpaa_applies:
1. Check that all your unused allowance from the previous {carry_forward_years} tax years is recorded on the Retirement page. Carry forward can only use years in which you were a member of a registered pension scheme, and any not yet recorded may cover part of the excess.
always:
2. Whatever is not covered is taxed. Report it on your Self Assessment tax return.
3. Either you or your pension provider must pay the tax. Ask your provider whether it can pay it from your pension.
outcome:
1. Any Annual Allowance tax is reported and paid on time.
learn:
1. Annual allowance | /help#retirement

## ni_gaps
status: approved
source: https://www.gov.uk/check-national-insurance-record (gaps, National Insurance credits, whether voluntary contributions would benefit you, the cost, and how your forecast would change); https://www.gov.uk/voluntary-national-insurance-contributions ("check if you're eligible for National Insurance credits … before deciding to pay voluntary contributions"; check your State Pension forecast first; at or past State Pension age, contact the Pension Service); `pension.state_pension.qualifying_years` in tax config; the card's own figures
figures: years_short, years_until_spa
why:
1. You need {years_short} more qualifying years for the full State Pension, and you have {years_until_spa} years until State Pension age.
always:
1. Check your National Insurance record on GOV.UK. It shows any gaps, what filling them would cost, and how your State Pension forecast would change.
2. Before paying, check whether you can get National Insurance credits for a gap instead, for example for years spent caring or claiming certain benefits.
3. Pay only for the years your record says would increase your State Pension. You can pay online from the record where it offers that.
4. Update your qualifying years on the Retirement page.
outcome:
1. Your State Pension forecast moves towards the full amount.

## state_pension_no_forecast
status: approved
source: https://www.gov.uk/check-state-pension (how much you could get, when, and whether you can increase it; online, in the HMRC app, by form BR19 or the Future Pension Centre if State Pension age is more than 30 days away); `pension.state_pension.full_new_state_pension` in tax config; the card's own figures
figures: full_state_pension
why:
1. The full new State Pension is {full_state_pension} a year. Until your forecast is in, your retirement plan cannot count what you will actually get.
always:
1. Get your forecast on GOV.UK ("Check your State Pension forecast") or in the HMRC app. It shows how much you could get, when, and whether you can increase it.
2. If you cannot use the online service and your State Pension age is more than 30 days away, ask for a forecast by post with form BR19, or call the Future Pension Centre.
3. Add the yearly amount and your qualifying years on the Retirement page.
outcome:
1. Your retirement projection counts the State Pension you are on course to get.

## pension_value_unknown
status: approved
source: https://www.gov.uk/find-pension-contact-details (finds a provider's contact details; "will not tell you whether you have a pension, or what its value is"); the card's own figures
figures: scheme_name
why:
1. Your retirement projection counts {scheme_name} as worth nothing until its value is in.
always:
1. Find the value on the latest annual statement for {scheme_name}, or in the provider's online account or app.
2. If you no longer have the provider's details, GOV.UK's pension tracing service can find them. It cannot tell you the value, so ask the provider.
3. Add the value to {scheme_name} on the Retirement page, or tell Fyn.
outcome:
1. Your projection uses the real value of {scheme_name}.

## approaching_decumulation
status: approved
source: https://www.gov.uk/personal-pensions-your-rights/how-you-can-take-pension (annuities, "regular payments for life", income depending on "your age and gender", "the size of your pension pot", "interest rates" and "your health (sometimes)"; flexi-access drawdown; cash sums; usually a tax-free lump sum; "not normally before 55"); https://www.gov.uk/personal-pensions-your-rights/get-help ("If you're over 50 you can book a free appointment to talk about your options"; Pension Wise does not cover the State Pension or defined benefit pensions); `pension.normal_minimum_pension_age` in tax config; the card's own figures
figures: years_to_retirement
why:
1. You are {years_to_retirement} years from your target retirement age.
always:
1. Look at the ways you can take a defined contribution pension: an annuity (regular payments for life), drawdown (taking money from a pot that stays invested), or cash sums. You can usually take part of it as a tax-free lump sum.
2. If you are over 50, book a free Pension Wise appointment to talk through your options for these pensions.
3. Compare the options on the Retirement page.
outcome:
1. You know how you plan to take your pension before you retire.
learn:
1. How your retirement projection works | /help#retirement

## pension_consolidation_opportunity
status: approved
source: https://www.gov.uk/transferring-your-pension/transferring-to-a-uk-pension-scheme (check the scheme allows a transfer and the new one accepts it; transfer fees; you might lose the right to take your pension at a specific age, fixed or enhanced protection, or a tax-free lump sum above the usual amount; contact both providers); https://www.gov.uk/transferring-your-pension (free information from MoneyHelper; independent financial advisers for paid advice); Pension Schemes Act 2015 s48, advice before transferring safeguarded benefits (https://www.legislation.gov.uk/ukpga/2015/8/section/48); the card's own figures
figures: pension_count
why:
1. You have {pension_count} defined contribution pensions, each with its own charges and paperwork.
always:
1. Ask each provider whether the pension allows a transfer, whether there is a transfer fee, and whether you would lose anything by moving it: the right to take it at a particular age, fixed or enhanced protection, or a tax-free lump sum above the usual amount.
2. If a pension has a guarantee, such as a guaranteed annuity rate, ask the provider whether you must take regulated financial advice before it can be transferred.
3. Check that the pension you want to move them into will accept the transfer.
4. MoneyHelper gives free information; an independent financial adviser can advise, for a fee.
5. Start the transfer through the provider you are moving to.
6. Update your pensions on the Retirement page once the transfer completes.
outcome:
1. Fewer pensions to keep track of, with charges you have compared.

## pension_charges_review
status: approved
source: the card's own figures (`RetirementActionDefinitionService::consolidateCharges`: platform and adviser fees and the holdings' weighted ongoing charge); https://www.gov.uk/transferring-your-pension/transferring-to-a-uk-pension-scheme (transfer fees and what you might lose before moving)
figures: pension_name, charges_list, total_fee_percent, annual_fees, platform_fee_percent, weighted_ocf, high_pension_total_fees, high_pension_platform_fees, high_pension_fund_fees
why:
1. {pension_name} has {charges_list}.
2. Charges come out of the pot every year, so they reduce what it grows to.
always:
1. Find the full charges on the latest annual statement or in the provider's online account: the platform or administration fee, each fund's ongoing charge, and any adviser fee.
when high_pension_fund_fees:
2. Look at the other funds your provider offers and their ongoing charges.
when high_pension_platform_fees:
3. Compare the platform fee with what other providers charge.
when high_pension_total_fees:
3. Compare the total charges with what other providers charge.
always:
4. Before moving to another provider, ask about transfer fees and anything you would lose by moving.
5. Update the charges on {pension_name} on the Retirement page after any change.
outcome:
1. You know what {pension_name} costs you each year, and whether a cheaper option is worth the move.

## salary_sacrifice_floor_warning
status: approved
source: https://www.gov.uk/guidance/salary-sacrifice-and-the-effects-on-paye ("A salary sacrifice arrangement must not reduce an employee's cash earnings below the National Minimum Wage (NMW) rates"); the card's own figures
figures: scheme_name, post_sacrifice_salary
why:
1. Salary sacrifice on {scheme_name} would leave you pay of {post_sacrifice_salary}.
2. A salary sacrifice arrangement must not take your cash pay below the National Minimum Wage, and the National Minimum Wage depends on your age and the hours you work.
always:
1. Ask your employer to check the amount against the National Minimum Wage for your age and hours before you agree to it.
2. If it would take you below, sacrifice less and pay the rest into a pension another way.
outcome:
1. Your pension payments go in without taking your pay below the legal minimum.

## enhanced_annuity_eligible
status: approved
source: https://www.gov.uk/personal-pensions-your-rights/how-you-can-take-pension (an annuity's payments depend on "your age and gender", "the size of your pension pot", "interest rates" and "your health (sometimes)"); https://www.gov.uk/personal-pensions-your-rights/get-help (free Pension Wise appointment over 50, for defined contribution pensions)
figures:
why:
1. What an annuity pays can depend on your health, so a provider may offer you more than its standard rates.
always:
1. When you ask for annuity quotes, give every provider your health details.
2. Compare quotes from several providers before you choose.
3. If you are over 50, a free Pension Wise appointment can talk you through your options for a defined contribution pension.
outcome:
1. Your annuity quotes take your health into account.
