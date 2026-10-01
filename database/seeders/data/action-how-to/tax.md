# How-to steps: tax actions

This file is the one source for the steps on each tax action's detail card. `ActionHowToSeeder` reads it, and only entries marked `status: approved` ever reach a user. CSJ reviews each entry and changes `draft` to `approved`, or edits it.

**How an entry works (CSJ 2026-09-28).** The user's own records pick which steps they see, and their own figures fill them in (`app/Services/Actions/ActionHowTo.php`, `ActionHowToFacts.php`).
- `why:` (or `why when <condition>:`) starts the lines for "Why this matters for you" on the card.
- `outcome:` (or `outcome when <condition>:`) starts the lines for "What this changes" on the card: the user's tax before and after, what it costs them, what their pay does. `when`/`always` go back to steps.
- `learn:` (or `learn when <condition>:`) starts "Find out more" links, written `Label | /path` to a page of Fynla's own help, such as `/help#avcs`.
- `when <condition>:` starts a branch. Its steps show only when the condition holds for this user. `always:` starts steps everyone sees.
- A condition is `fact`, `not fact`, `fact is a or b`, or `fact is not a or b`, joined with `and`.
- `{name}` is filled from the user's figures. A step whose figure is missing is left out, so no one ever sees a blank.
- Figures come from the strategy that raised the action, the user's accounts, and tax config. None is typed into this file (Rule 2).

**What you can branch on.**
- **Everyone:** `band` (basic, higher or additional); `above_basic`; `has_workplace_pension`, `has_salary_sacrifice`, `has_personal_pension`, `has_no_pension`; `has_cash_isa`, `has_stocks_isa`, `has_lifetime_isa_account`, `has_gia`, `isa_with_gia_provider`; `has_spouse`; `months_left`.
- **Each action:** the figures its strategy publishes, named in its entry below.

**What you can fill in.**
- **Names:** `{workplace_pension}`, `{personal_pension}`, `{cash_isa}`, `{stocks_isa}`, `{lifetime_isa_account}`, `{gia}`, `{spouse}` (`{spouse_start}` at the start of a sentence).
- **Dates:** `{tax_year_end}`, `{months_left}`.
- **Pension payments:**
  - `{contribution}`: the gross amount going into the pension.
  - `{contribution_per_month_left}`: the same, spread over the months left in the tax year.
  - `{net_payment}`: what you pay to a personal pension.
  - `{provider_relief}`: the basic-rate relief the provider adds.
  - `{extra_relief}`: the relief above the basic rate that you claim back.
- **Outcome:** `{tax_now}`, `{tax_after}`, `{tax_saved}` (this action alone, priced by the tax engine), `{net_cost}` (a pension payment after the tax comes back), `{take_home_per_month_left}`, `{ni_saved}`.
- **Tax config:** `{basic_rate}`, `{personal_allowance}`, `{normal_minimum_pension_age}`, `{carry_forward_years}`, `{isa_allowance}`, `{junior_isa_allowance}`, `{taper_threshold}`, `{taper_per_pound}`, `{sacrifice_cap}`, `{sacrifice_cap_date}`, and the Lifetime ISA and Junior ISA ages.

Rules for these steps:
- **Every step rests on the sources named under its heading** (Rule 23).
- **Guidance, not advice.** The steps say how, not whether.
- **No banned words** (Rule 9): never "harvest".

## pension_tax_relief
status: approved
source: https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief (net pay and relief at source; relief at source in some workplace pensions); https://www.gov.uk/guidance/salary-sacrifice-and-the-effects-on-paye (National Minimum Wage floor); Pensions Act 2008 s3 automatic enrolment (https://www.legislation.gov.uk/ukpga/2008/30/section/3); https://www.gov.uk/workplace-pensions/joining-a-workplace-pension; The Pensions Regulator, AVCs alongside defined benefit schemes (https://helpfiles.thepensionsregulator.gov.uk/members/dbschememembership); Finance Act 2004 s192 relief at source (https://www.legislation.gov.uk/ukpga/2004/12/section/192); s188 relief for the tax year paid (https://www.legislation.gov.uk/ukpga/2004/12/section/188)
figures: contribution, net_payment, provider_relief, extra_relief, relief_rate, tax_band
why when above_basic:
1. {contribution} of your income is taxed at {relief_rate}.
2. A pension payment gets tax relief at that rate, so money that would have gone in tax goes into your pension instead.
why when not above_basic:
1. Paying in {contribution} more this year saves {tax_saved} of income tax.
2. A pension payment gets tax relief at {relief_rate}, so money that would have gone in tax goes into your pension instead.
when has_salary_sacrifice:
1. Ask your employer to increase your salary sacrifice into {workplace_pension} by {contribution_per_month_left} a month for the {months_left} months left in this tax year. Your cash pay after the sacrifice must not fall below the National Minimum Wage. The amount comes off your pay before tax, so there is no relief to claim.
when has_workplace_pension and not has_salary_sacrifice:
1. Ask your employer first whether they offer salary sacrifice. If they do, paying in that way saves National Insurance as well as Income Tax.
2. Otherwise, ask your employer to increase your contribution to {workplace_pension} by {contribution_per_month_left} a month for the {months_left} months left in this tax year, or pay a one-off {contribution} if the scheme allows it.
3. Ask payroll how the scheme gives tax relief. Most workplace schemes take your contribution before Income Tax, so the relief comes through your pay. Some use relief at source instead: your contribution comes out after tax, the scheme adds {provider_relief} of basic-rate relief.
when auto_enrolled and not has_workplace_pension and not has_db_pension:
1. You are {age}, employed and earn {employment_pay}, so the law requires your employer to enrol you in a workplace pension and pay in at least {ae_min_employer} of your qualifying earnings. You are in it unless you opted out. Ask payroll which scheme it is, and whether they offer salary sacrifice, before paying in anywhere else.
when employed and not auto_enrolled and not has_workplace_pension and not has_db_pension:
1. Your employer does not have to enrol you automatically, but you can usually ask to join their pension and they cannot refuse. Ask payroll, and whether they offer salary sacrifice.
when has_personal_pension and not has_workplace_pension:
4. Pay {net_payment} into {personal_pension}. The provider claims {provider_relief} of basic-rate relief from HM Revenue and Customs (HMRC) and adds it, so {contribution} goes into your pension.
when has_personal_pension and has_workplace_pension:
5. Or pay {net_payment} into {personal_pension} instead. The provider claims {provider_relief} of basic-rate relief from HM Revenue and Customs (HMRC) and adds it, so {contribution} goes into your pension.
when has_db_pension_only:
1. Your {db_pension} is a defined benefit scheme, which pays a pension based on your salary and years of service. Ask the scheme administrator how you can pay in more. The scheme may let you buy extra pension, or pay additional voluntary contributions (AVCs) into a separate defined contribution pot, often with another provider.
2. If the scheme offers neither, open a personal pension or self-invested personal pension (SIPP) and pay {net_payment} into it. The provider claims {provider_relief} of basic-rate relief and adds it, so {contribution} goes in.
learn when has_db_pension:
1. Paying more in alongside a defined benefit pension | /help#avcs
when has_no_pension:
6. You have no pension recorded. Open a personal pension or self-invested personal pension (SIPP) with a provider, then pay {net_payment} into it. The provider claims {provider_relief} of basic-rate relief and adds it, so {contribution} goes in.
when above_basic and not has_salary_sacrifice:
7. If the money goes into a pension that uses relief at source, claim the other {extra_relief} on your Self Assessment tax return. If you do not file one, GOV.UK explains how to claim from HM Revenue and Customs (HMRC).
when above_basic and has_salary_sacrifice and has_personal_pension:
7. If you pay into {personal_pension} instead, claim the other {extra_relief} on your Self Assessment tax return. If you do not file one, GOV.UK explains how to claim from HM Revenue and Customs (HMRC).
always:
8. Pay it in by {tax_year_end}. Relief goes to the tax year you pay in.
outcome:
1. Doing this alone, your Income Tax for the year falls from {tax_now} to {tax_after}: {tax_saved} less.
2. {contribution} goes into your pension, and after the tax relief it costs you {net_cost}.
outcome when has_salary_sacrifice:
3. National Insurance falls by {ni_saved} as well, so your take-home pay drops by about {take_home_per_month_left} a month for the {months_left} months left, not by the full amount.

## salary_sacrifice_ni
status: approved
source: https://www.gov.uk/guidance/salary-sacrifice-and-the-effects-on-paye; National Insurance Contributions (Employer Pensions Contributions) Act 2026 (https://commonslibrary.parliament.uk/research-briefings/cbp-10423/)
figures: annual_contribution, employee_ni_saving, employer_ni_rebate_pct, employer_ni_rebate_saving
why:
1. You pay {annual_contribution} a year into {workplace_pension} from your pay, after National Insurance has been taken.
2. Through salary sacrifice that pay is not subject to your National Insurance, which saves you {employee_ni_saving} a year.
always:
1. You pay {annual_contribution} a year into {workplace_pension} from your pay. Ask your employer whether they offer salary sacrifice for pension contributions.
2. If they do, agree the change in writing. Your contract must show your new cash pay, {annual_contribution} lower, and the same amount paid in by your employer instead. Your cash pay must not fall below the National Minimum Wage.
3. National Insurance is then worked out on the lower pay, which saves you {employee_ni_saving} a year.
when employer_ni_rebate_pct:
4. Your employer passes on {employer_ni_rebate_pct} of their own National Insurance saving, which adds {employer_ni_rebate_saving} a year to your pension. Ask them to confirm it in writing.
when not employer_ni_rebate_pct:
5. Ask whether your employer passes on any of their own National Insurance saving to your pension.
always:
6. A lower cash salary can reduce earnings-related benefits and statutory pay, such as Maternity Allowance. Ask your employer how it affects yours.
7. Check your first payslip after the change, to see that your pay and your pension contribution match what you agreed.
when over_sacrifice_cap:
8. From {sacrifice_cap_date}, only the first {sacrifice_cap} a year of salary sacrifice pension contributions is free of National Insurance. You pay {annual_contribution} a year, so from then National Insurance will be charged on the amount above {sacrifice_cap}, for you and for your employer.
when not over_sacrifice_cap:
9. From {sacrifice_cap_date}, only the first {sacrifice_cap} a year of salary sacrifice pension contributions is free of National Insurance. Your {annual_contribution} a year is within it.
outcome:
1. Your take-home pay rises by {employee_ni_saving} a year, about {employee_ni_saving_monthly} a month, and the same {annual_contribution} still goes into {workplace_pension}.
outcome when employer_ni_rebate_pct:
2. Your pension also gets {employer_ni_rebate_saving} a year from your employer's own saving.

## isa_topup_vs_psa
status: approved
source: https://www.gov.uk/individual-savings-accounts/how-isas-work
figures: suggested_transfer_amount, isa_remaining, taxable_interest_sheltered, target_accounts
why:
1. Your {non_isa_balance} of savings outside an ISA earns about {annual_interest} a year in interest.
2. As a {band} taxpayer your Personal Savings Allowance is {personal_savings_allowance}, so interest above it is taxed.
when has_cash_isa:
1. Pay {suggested_transfer_amount} into your cash ISA with {cash_isa}.
when not has_cash_isa:
2. Open a cash ISA with a bank or building society, then pay {suggested_transfer_amount} into it.
always:
3. Take the money from {target_accounts}, where its interest is taxed.
4. That moves about {taxable_interest_sheltered} a year of taxed interest into the ISA. You do not pay tax on interest on cash in an ISA.
5. You have {isa_remaining} of your {isa_allowance} ISA allowance left. The allowance is set per tax year, so pay in {isa_remaining} by {tax_year_end}.
outcome when not priced_after_pension:
1. About {taxable_interest_sheltered} a year of interest stops being taxed. Your Income Tax for the year falls from {tax_now} to {tax_after}: {tax_saved} less.
outcome when priced_after_pension:
2. Once your {pension_paid_first} pension contribution is paid, about {taxable_interest_sheltered} a year of interest stops being taxed. Your Income Tax for the year then falls from {tax_now} to {tax_after}: {tax_saved} less.

## isa_topup_spouse
status: approved
source: https://www.gov.uk/individual-savings-accounts/how-isas-work; https://www.gov.uk/inheritance-tax/gifts
figures: available_allowance
why:
1. You told us {spouse} has no ISA, so their {available_allowance} ISA allowance for this tax year is unused.
always:
1. You told us {spouse} has no ISA, so they open a cash ISA in their own name.
2. They can pay in up to {available_allowance} this tax year, from their own savings or from money you give them.
3. There is no Inheritance Tax on gifts between spouses or civil partners who live in the UK permanently. Once it is given, the money belongs to them.
4. You do not pay tax on interest on cash in an ISA. The allowance is set per tax year, so pay in by {tax_year_end}.
outcome:
1. Interest on up to {available_allowance} of savings becomes tax-free for {spouse} this tax year.
outcome when tax_saved:
2. Your household pays about {tax_saved} less tax a year.

## bed_and_isa
status: approved
source: https://www.gov.uk/individual-savings-accounts/how-isas-work; https://www.gov.uk/tax-sell-shares; https://www.gov.uk/capital-gains-tax/rates; https://www.gov.uk/capital-gains-tax/allowances; TCGA 1992 s106A(3) and (5), the 30-day rule and "same capacity" (https://www.legislation.gov.uk/ukpga/1992/12/section/106A); HMRC CG51560 (https://www.gov.uk/hmrc-internal-manuals/capital-gains-manual/cg51560); TCGA 1992 s151, ISA gains outside Capital Gains Tax (https://www.legislation.gov.uk/ukpga/1992/12/section/151)
figures: estimated_proceeds_to_transfer, realisable_within_aea, annual_exempt_amount, isa_remaining, cgt_rate
why:
1. Your investments outside an ISA hold about {total_unrealised_gain} of gains, and their dividends and gains stay taxable for as long as they are there.
always:
1. A "Bed and ISA" sells investments you hold in your general investment account and buys them back inside your stocks and shares ISA. Once inside, their dividends and gains are free of tax.
when isa_with_gia_provider:
1. Ask {gia} for a "Bed and ISA" transfer from your general investment account into your stocks and shares ISA with them.
when has_stocks_isa and not isa_with_gia_provider:
2. Your general investment account is with {gia} and your stocks and shares ISA is with {stocks_isa}. Ask {gia} whether they can do a "Bed and ISA" into an ISA with them. If not, sell with {gia}, move the cash and buy the same investments in your ISA with {stocks_isa}.
when not has_stocks_isa:
3. You have no stocks and shares ISA recorded. Ask {gia} whether they offer one with a "Bed and ISA" transfer, or open one with another provider.
always:
4. Shares you already own cannot be moved into an ISA unless they come from an employee share scheme. So they are sold in your general account and bought back inside the ISA.
4. There is no 30-day wait. The 30-day rule matches a sale with a purchase of the same shares within 30 days only when both are made in the same capacity. Shares bought inside your ISA are held in the ISA, where gains are outside Capital Gains Tax, so the provider can buy them back straight away.
4. Before you go ahead, ask the provider how long your money is out of the market between the sale and the purchase, and what they charge. Prices can move in between.
5. Move up to {estimated_proceeds_to_transfer}. The sale then makes a gain of about {realisable_within_aea}, which your {annual_exempt_amount} Capital Gains Tax allowance covers if you have no other gains this tax year. It also fits within the {isa_remaining} of ISA allowance you have left.
6. Selling more than that makes gains above your allowance, taxed at {cgt_rate}.
7. Both allowances are set per tax year, so do it by {tax_year_end}.
outcome:
1. About {tax_saved} of Capital Gains Tax is avoided on these investments, and from then on their dividends and gains are tax-free inside the ISA.

## dividend_allowance_harvest
status: approved
source: https://www.gov.uk/tax-on-dividends; https://www.gov.uk/tax-on-dividends/how-much-tax-youll-pay; https://www.gov.uk/individual-savings-accounts/how-isas-work
figures: unused_allowance, dividend_rate
why:
1. Your dividends outside an ISA are {unused_allowance} below your dividend allowance this tax year.
always:
1. You have {unused_allowance} of your dividend allowance unused this tax year. Dividends from shares you hold outside an ISA pay no tax up to your allowance.
when has_gia:
2. Your investments outside an ISA are with {gia}. Their dividends use the allowance first.
always:
3. As a {band} taxpayer, you pay {dividend_rate} on dividends above the allowance.
4. You do not pay tax on dividends from shares in an ISA.
outcome:
1. Up to {unused_allowance} more of dividends outside an ISA would pay no tax this year.

## pension_aa_carry_forward
status: approved
source: Pensions Act 2008 s3 automatic enrolment (https://www.legislation.gov.uk/ukpga/2008/30/section/3); https://www.gov.uk/workplace-pensions/joining-a-workplace-pension; https://www.gov.uk/tax-on-your-private-pension/annual-allowance; Finance Act 2004 s228A (https://www.legislation.gov.uk/ukpga/2004/12/section/228A); s190 relief limited to earnings (https://www.legislation.gov.uk/ukpga/2004/12/section/190); HMRC PTM055100, carry forward cannot raise the money purchase annual allowance (https://www.gov.uk/hmrc-internal-manuals/pensions-tax-manual/ptm055100)
figures: current_year_input, annual_allowance, current_year_headroom, unused_carry_forward_total, lookback_years, contribution, net_payment, provider_relief, extra_relief
why:
1. You have {unused_carry_forward_total} of pension allowance unused from the last {lookback_years} tax years.
2. The earliest year's unused allowance can no longer be carried forward after {tax_year_end}.
always:
1. Your pension payments this tax year come to {current_year_input}, against an annual allowance of {annual_allowance}. You also have {unused_carry_forward_total} unused from the last {lookback_years} tax years.
when current_year_headroom:
2. This year's allowance is used first: {current_year_headroom} more fills it. Carry forward only starts after that.
when not current_year_headroom:
3. You have already used this year's allowance, so what you pay in now uses carried-forward allowance.
always:
4. Unused allowance from earlier years is used earliest year first. Only years in which you were a member of a registered pension scheme count.
5. Before you pay, ask each pension provider for your pension input amounts in those years, to confirm the unused allowance.
6. Paying in {contribution} beyond this year's allowance uses carried-forward allowance. Tax relief only covers payments up to your earnings for the year, so the amount stops there.
when auto_enrolled and not has_workplace_pension and not has_db_pension:
1. You are {age}, employed and earn {employment_pay}, so the law requires your employer to enrol you in a workplace pension and pay in at least {ae_min_employer} of your qualifying earnings. You are in it unless you opted out. Ask payroll which scheme it is, and whether they offer salary sacrifice, before paying in anywhere else.
when employed and not auto_enrolled and not has_workplace_pension and not has_db_pension:
1. Your employer does not have to enrol you automatically, but you can usually ask to join their pension and they cannot refuse. Ask payroll, and whether they offer salary sacrifice.
when has_workplace_pension:
7. Ask your employer whether {workplace_pension} takes a one-off payment of {contribution}.
when has_personal_pension:
8. Or pay {net_payment} into {personal_pension}. The provider adds {provider_relief} of basic-rate relief, so {contribution} goes in.
when has_db_pension_only:
9. Your {db_pension} is a defined benefit scheme, which pays a pension based on your salary and years of service. Ask the scheme administrator how you can pay in more. The scheme may let you buy extra pension, or pay additional voluntary contributions (AVCs) into a separate defined contribution pot, often with another provider.
9. If the scheme offers neither, open a personal pension or self-invested personal pension (SIPP) and pay {net_payment} into it. The provider claims {provider_relief} of basic-rate relief and adds it, so {contribution} goes in.
learn when has_db_pension:
1. Paying more in alongside a defined benefit pension | /help#avcs
when has_no_pension:
10. You have no pension recorded. Open a personal pension or self-invested personal pension (SIPP), then pay {net_payment} into it. The provider adds {provider_relief} of basic-rate relief, so {contribution} goes in.
when above_basic:
11. If the money goes into a pension that uses relief at source, claim the other {extra_relief} on your Self Assessment tax return.
always:
12. Pay it in by {tax_year_end}. After that, the earliest of the {lookback_years} years drops out and its unused allowance can no longer be carried forward.
outcome:
1. Doing this alone, your Income Tax for the year falls from {tax_now} to {tax_after}: {tax_saved} less.
2. {contribution} goes into your pension, and after the tax relief it costs you {net_cost}.

## tapered_annual_allowance
status: approved
source: https://www.gov.uk/tax-on-your-private-pension/annual-allowance; Finance Act 2004 s228ZA (https://www.legislation.gov.uk/ukpga/2004/12/section/228ZA); s229-s234 pension input amounts (https://www.legislation.gov.uk/ukpga/2004/12/section/234)
figures: threshold_income, adjusted_income, threshold_income_gate, adjusted_income_gate, standard_annual_allowance, tapered_annual_allowance, annual_allowance_charge_avoided
why:
1. Your adjusted income of {adjusted_income} cuts your annual allowance from {standard_annual_allowance} to {tapered_annual_allowance}.
always:
1. Your threshold income is {threshold_income} and your adjusted income is {adjusted_income}. Both are over the limits of {threshold_income_gate} and {adjusted_income_gate}, so your annual allowance falls from {standard_annual_allowance} to {tapered_annual_allowance}.
2. Keep your pension savings this tax year within {tapered_annual_allowance}. That counts payments by you and your employer into defined contribution pensions, plus the growth in any defined benefit pension. Unused allowance from the last {carry_forward_years} tax years can be added on top.
when has_workplace_pension:
3. Ask your employer what they and you will have paid into {workplace_pension} by {tax_year_end}, and reduce future payments if the total would go over.
always:
4. If you do go over, report the charge in the "Pension savings tax charges" section of your Self Assessment return.
outcome:
1. Staying within {tapered_annual_allowance} avoids an annual allowance charge of about {annual_allowance_charge_avoided}.

## pa_taper_rescue
status: approved
source: Pensions Act 2008 s3 automatic enrolment (https://www.legislation.gov.uk/ukpga/2008/30/section/3); https://www.gov.uk/workplace-pensions/joining-a-workplace-pension; https://www.gov.uk/income-tax-rates/income-over-100000; https://www.gov.uk/guidance/adjusted-net-income; https://www.gov.uk/guidance/salary-sacrifice-and-the-effects-on-paye (National Minimum Wage floor); ITA 2007 s58 (https://www.legislation.gov.uk/ukpga/2007/3/section/58)
figures: contribution, effective_marginal_rate, net_payment, provider_relief, extra_relief
why:
1. Above {taper_threshold} you lose £1 of Personal Allowance for every {taper_per_pound}, so that income is taxed at an effective {effective_marginal_rate}.
always:
1. Your adjusted net income is above {taper_threshold}, so your Personal Allowance goes down by £1 for every {taper_per_pound} over it. Income in that range is taxed at an effective {effective_marginal_rate}.
2. Pension payments reduce your adjusted net income. For a pension that uses relief at source, the amount taken off is the gross {contribution}, not just what you pay.
when has_salary_sacrifice:
3. Ask your employer to increase your salary sacrifice into {workplace_pension} by {contribution_per_month_left} a month for the {months_left} months left in this tax year. Your cash pay after the sacrifice must not fall below the National Minimum Wage.
when has_workplace_pension and not has_salary_sacrifice:
1. Ask your employer first whether they offer salary sacrifice. If they do, paying in that way saves National Insurance as well as Income Tax.
4. Otherwise, ask your employer to increase your contribution to {workplace_pension} by {contribution_per_month_left} a month for the {months_left} months left in this tax year, or pay a one-off {contribution} if the scheme allows it.
when auto_enrolled and not has_workplace_pension and not has_db_pension:
1. You are {age}, employed and earn {employment_pay}, so the law requires your employer to enrol you in a workplace pension and pay in at least {ae_min_employer} of your qualifying earnings. You are in it unless you opted out. Ask payroll which scheme it is, and whether they offer salary sacrifice, before paying in anywhere else.
when employed and not auto_enrolled and not has_workplace_pension and not has_db_pension:
1. Your employer does not have to enrol you automatically, but you can usually ask to join their pension and they cannot refuse. Ask payroll, and whether they offer salary sacrifice.
when has_personal_pension and not has_workplace_pension:
5. Pay {net_payment} into {personal_pension}. The provider adds {provider_relief} of basic-rate relief, so {contribution} goes in.
when has_personal_pension and has_workplace_pension:
6. Or pay {net_payment} into {personal_pension} instead. The provider adds {provider_relief} of basic-rate relief, so {contribution} goes in.
when has_db_pension_only:
1. Your {db_pension} is a defined benefit scheme, which pays a pension based on your salary and years of service. Ask the scheme administrator how you can pay in more. The scheme may let you buy extra pension, or pay additional voluntary contributions (AVCs) into a separate defined contribution pot, often with another provider.
2. If the scheme offers neither, open a personal pension or self-invested personal pension (SIPP) and pay {net_payment} into it. The provider claims {provider_relief} of basic-rate relief and adds it, so {contribution} goes in.
learn when has_db_pension:
1. Paying more in alongside a defined benefit pension | /help#avcs
when has_no_pension:
7. You have no pension recorded. Open a personal pension or self-invested personal pension (SIPP), then pay {net_payment} into it. The provider adds {provider_relief} of basic-rate relief, so {contribution} goes in.
when not has_salary_sacrifice:
8. If the money goes into a pension that uses relief at source, claim the other {extra_relief} on your Self Assessment tax return. That claim also gives back the Personal Allowance.
when has_salary_sacrifice and has_personal_pension:
8. If you pay into {personal_pension} instead, claim the other {extra_relief} on your Self Assessment tax return. That claim also gives back the Personal Allowance.
always:
9. Pay it in by {tax_year_end}. Relief goes to the tax year you pay in.
outcome:
1. Doing this alone, your Income Tax for the year falls from {tax_now} to {tax_after}: {tax_saved} less.
2. {contribution} goes into your pension, and after the tax relief it costs you {net_cost}.
outcome when has_salary_sacrifice:
3. National Insurance falls by {ni_saved} as well, so your take-home pay drops by about {take_home_per_month_left} a month for the {months_left} months left, not by the full amount.

## additional_rate_avoidance
status: approved
source: Pensions Act 2008 s3 automatic enrolment (https://www.legislation.gov.uk/ukpga/2008/30/section/3); https://www.gov.uk/workplace-pensions/joining-a-workplace-pension; https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief; https://www.gov.uk/guidance/adjusted-net-income; https://www.gov.uk/guidance/salary-sacrifice-and-the-effects-on-paye (National Minimum Wage floor); Finance Act 2004 s192 (https://www.legislation.gov.uk/ukpga/2004/12/section/192)
figures: contribution, additional_rate_slice, net_payment, provider_relief, extra_relief
why:
1. {additional_rate_slice} of your income is taxed at the additional rate, the highest rate of Income Tax.
always:
1. {additional_rate_slice} of your income is taxed at the additional rate. A pension payment gets relief at the rate the income it covers is taxed at, so the part covering that slice gets additional-rate relief.
when has_salary_sacrifice:
2. Ask your employer to increase your salary sacrifice into {workplace_pension} by {contribution_per_month_left} a month for the {months_left} months left in this tax year. Your cash pay after the sacrifice must not fall below the National Minimum Wage.
when has_workplace_pension and not has_salary_sacrifice:
1. Ask your employer first whether they offer salary sacrifice. If they do, paying in that way saves National Insurance as well as Income Tax.
3. Otherwise, ask your employer to increase your contribution to {workplace_pension} by {contribution_per_month_left} a month for the {months_left} months left in this tax year, or pay a one-off {contribution} if the scheme allows it.
when auto_enrolled and not has_workplace_pension and not has_db_pension:
1. You are {age}, employed and earn {employment_pay}, so the law requires your employer to enrol you in a workplace pension and pay in at least {ae_min_employer} of your qualifying earnings. You are in it unless you opted out. Ask payroll which scheme it is, and whether they offer salary sacrifice, before paying in anywhere else.
when employed and not auto_enrolled and not has_workplace_pension and not has_db_pension:
1. Your employer does not have to enrol you automatically, but you can usually ask to join their pension and they cannot refuse. Ask payroll, and whether they offer salary sacrifice.
when has_personal_pension and not has_workplace_pension:
4. Pay {net_payment} into {personal_pension}. The provider adds {provider_relief} of basic-rate relief, so {contribution} goes in.
when has_personal_pension and has_workplace_pension:
5. Or pay {net_payment} into {personal_pension} instead. The provider adds {provider_relief} of basic-rate relief, so {contribution} goes in.
when has_db_pension_only:
1. Your {db_pension} is a defined benefit scheme, which pays a pension based on your salary and years of service. Ask the scheme administrator how you can pay in more. The scheme may let you buy extra pension, or pay additional voluntary contributions (AVCs) into a separate defined contribution pot, often with another provider.
2. If the scheme offers neither, open a personal pension or self-invested personal pension (SIPP) and pay {net_payment} into it. The provider claims {provider_relief} of basic-rate relief and adds it, so {contribution} goes in.
learn when has_db_pension:
1. Paying more in alongside a defined benefit pension | /help#avcs
when has_no_pension:
6. You have no pension recorded. Open a personal pension or self-invested personal pension (SIPP), then pay {net_payment} into it. The provider adds {provider_relief} of basic-rate relief, so {contribution} goes in.
when not has_salary_sacrifice:
7. If the money goes into a pension that uses relief at source, claim the other {extra_relief} on your Self Assessment tax return.
when has_salary_sacrifice and has_personal_pension:
7. If you pay into {personal_pension} instead, claim the other {extra_relief} on your Self Assessment tax return.
always:
8. Pay it in by {tax_year_end}. Relief goes to the tax year you pay in.
outcome:
1. Doing this alone, your Income Tax for the year falls from {tax_now} to {tax_after}: {tax_saved} less.
2. {contribution} goes into your pension, and after the tax relief it costs you {net_cost}.
outcome when has_salary_sacrifice:
3. National Insurance falls by {ni_saved} as well, so your take-home pay drops by about {take_home_per_month_left} a month for the {months_left} months left, not by the full amount.

## gift_aid_higher_rate_relief
status: approved
source: https://www.gov.uk/donating-to-charity/gift-aid; ITA 2007 s414 gift aid (https://www.legislation.gov.uk/ukpga/2007/3/section/414)
figures: annual_donations, uses_gift_aid, charity_gift_aid, estimated_annual_tax_saved
why when uses_gift_aid:
1. The charity claims basic-rate tax on your {annual_donations} of gifts. Because you pay a higher rate, the rest of the relief is yours to claim.
why when not uses_gift_aid:
1. You give about {annual_donations} a year without Gift Aid, so the charity misses {charity_gift_aid} it could claim.
when uses_gift_aid:
1. You give about {annual_donations} a year with Gift Aid. Make sure you have given a Gift Aid declaration to each charity you give to.
2. The charity claims basic-rate tax on your gifts. Because you pay tax above the basic rate, you can claim the difference yourself: about {tax_saved} a year.
3. Claim it on your Self Assessment tax return, or ask HM Revenue and Customs (HMRC) to change your tax code.
when not uses_gift_aid:
1. You give about {annual_donations} a year without Gift Aid. Give each charity a Gift Aid declaration: a short form saying you are a UK taxpayer. It can cover future gifts and some earlier ones.
2. The charity then claims {charity_gift_aid} a year on top from HM Revenue and Customs (HMRC), at no cost to you.
3. You must have paid at least that much Income Tax or Capital Gains Tax in the tax year. If you pay less, HMRC may ask you to pay the difference.
when not uses_gift_aid and tax_saved:
4. Because you pay tax above the basic rate, you can also claim {tax_saved} back yourself on your Self Assessment tax return, or by asking HMRC to change your tax code.
outcome when uses_gift_aid:
1. Once claimed, your Income Tax for the year falls from {tax_now} to {tax_after}: {tax_saved} back.
outcome when not uses_gift_aid:
1. The charities get {charity_gift_aid} a year more from the same gifts.
outcome when not uses_gift_aid and tax_saved:
2. Your Income Tax for the year falls from {tax_now} to {tax_after}: {tax_saved} back.

## marriage_allowance_transfer
status: approved
source: https://www.gov.uk/marriage-allowance; https://www.gov.uk/marriage-allowance/eligibility; https://www.gov.uk/marriage-allowance/how-to-apply; ITA 2007 s55B (https://www.legislation.gov.uk/ukpga/2007/3/section/55B) and s55C (https://www.legislation.gov.uk/ukpga/2007/3/section/55C); starting rate for savings s12 (https://www.legislation.gov.uk/ukpga/2007/3/section/12), Personal Savings Allowance s12B (https://www.legislation.gov.uk/ukpga/2007/3/section/12B), dividend nil rate s13A (https://www.legislation.gov.uk/ukpga/2007/3/section/13A); s45 Married Couple's Allowance (https://www.legislation.gov.uk/ukpga/2007/3/section/45)
figures: amount_transferred, transfer_direction, user_income, spouse_income, transferor_extra_tax, estimated_annual_tax_saved
why when not transferor_uses_whole_allowance:
1. One of you has Personal Allowance going unused while the other pays Income Tax at the basic rate.
why when transferor_uses_whole_allowance:
1. One of you can give part of your Personal Allowance away and still pay no Income Tax above the basic rate, while the other pays Income Tax at the basic rate.
eligibility (checked before the action is shown, TaxStrategyMath::marriageAllowance): married or in a civil partnership; the person giving it, once their allowance is smaller by the amount given (s55B(6)), pays no rate above the basic rate, dividends counted in full (s55C(1)(c), (ca); CSJ 2026-09-30 "widen to law": GOV.UK's "income below your Personal Allowance" is s55C(2), which binds only a non-resident under s55C(1)(d)); the person receiving it pays no rate above the basic rate, dividends counted in full (s55B(2)(b), (ba)). The spouse's income must be known: a linked spouse's own records or an amount entered. "Does not work" alone leaves the action waiting on "Add your spouse's income" (CSJ 2026-09-28). Scottish rates are not modelled, so recipients above the Scottish limit get a caveat line (CSJ 2026-09-28).
when transfer_direction is to_user:
1. The claim is made by {spouse}. They transfer {amount_transferred} of their Personal Allowance to you.
when transfer_direction is to_user and spouse_income_is_nil:
2. {spouse_start} has no income recorded, so their {personal_allowance} Personal Allowance goes unused.
when transfer_direction is to_user and not spouse_income_is_nil and not transferor_uses_whole_allowance:
2. {spouse_start}'s income of {spouse_income} is below the {personal_allowance} Personal Allowance, so part of it goes unused.
when transfer_direction is to_user and transferor_uses_whole_allowance:
2. {spouse_start}'s income of {spouse_income} uses all of their {personal_allowance} Personal Allowance. They can still give part of it: after the transfer, none of their income is taxed above the basic rate.
when transfer_direction is to_user and transferor_pays_more:
2. With a smaller allowance, {spouse} pays about {transferor_extra_tax} more Income Tax a year. The saving below already takes that off.
when transfer_direction is to_user and transferor_uses_whole_allowance and not transferor_pays_more:
2. {spouse_start} pays no more tax: the income their smaller allowance no longer covers is still taxed at 0%, under the starting rate for savings, the Personal Savings Allowance or the dividend allowance.
when transfer_direction is to_user:
3. You qualify to receive it because you pay Income Tax at the basic rate and no higher.
when transfer_direction is to_spouse:
1. You make the claim. You transfer {amount_transferred} of your Personal Allowance to {spouse}.
when transfer_direction is to_spouse and user_income_is_nil:
2. You have no income recorded, so your {personal_allowance} Personal Allowance goes unused.
when transfer_direction is to_spouse and not user_income_is_nil and not transferor_uses_whole_allowance:
2. Your income of {user_income} is below the {personal_allowance} Personal Allowance, so part of it goes unused.
when transfer_direction is to_spouse and transferor_uses_whole_allowance:
2. Your income of {user_income} uses all of your {personal_allowance} Personal Allowance. You can still give part of it: after the transfer, none of your income is taxed above the basic rate.
when transfer_direction is to_spouse and transferor_pays_more:
2. With a smaller allowance, you pay about {transferor_extra_tax} more Income Tax a year. The saving below already takes that off.
when transfer_direction is to_spouse and transferor_uses_whole_allowance and not transferor_pays_more:
2. You pay no more tax: the income your smaller allowance no longer covers is still taxed at 0%, under the starting rate for savings, the Personal Savings Allowance or the dividend allowance.
when transfer_direction is to_spouse:
3. {spouse_start} qualifies to receive it because, on their income of {spouse_income}, they pay Income Tax at the basic rate and no higher.
when transfer_direction is to_user and above_scottish_ma_limit:
3. If you live in Scotland, this does not apply to you: there the person receiving it must pay no more than the Scottish intermediate rate, which usually means income up to {scottish_ma_limit}.
when transfer_direction is to_spouse and above_scottish_ma_limit:
3. If {spouse} lives in Scotland, this does not apply: there the person receiving it must pay no more than the Scottish intermediate rate, which usually means income up to {scottish_ma_limit}.
when mca_possible:
4. One of you was born before {mca_born_before}, so Married Couple's Allowance may give you more. You cannot have both, so check it on GOV.UK before you apply.
always:
5. Apply online on GOV.UK, or through the Marriage Allowance section of a Self Assessment return.
6. You can also backdate the claim for earlier tax years you were eligible, as far back as GOV.UK allows.
7. It carries on each year until it is cancelled or your circumstances change.
outcome:
1. Your household pays about {tax_saved} less Income Tax a year.

## savings_to_spouse
status: approved
source: ITTOIA 2005 s626 (https://www.legislation.gov.uk/ukpga/2005/5/section/626); ITA 2007 s12B (https://www.legislation.gov.uk/ukpga/2007/3/section/12B); https://www.gov.uk/inheritance-tax/gifts
figures: suggested_transfer_amount, annual_interest_moved, spouse_stacked_interest_capacity
why:
1. Your savings earn about {annual_interest_moved} a year in interest that is taxed at your rate.
2. {spouse_start} has allowances that would cover that interest.
always:
1. Move {suggested_transfer_amount} into an account in {spouse}'s own name.
2. The gift must be outright, with no conditions and no way for the money to come back to you. The {annual_interest_moved} a year of interest it earns is then theirs, taxed under their own allowances.
3. With no other income recorded for them, their Personal Allowance, starting rate for savings and Personal Savings Allowance cover up to {spouse_stacked_interest_capacity} of interest a year.
4. There is no Inheritance Tax on gifts between spouses or civil partners who live in the UK permanently. Once it is given, the money belongs to them.
outcome when not priced_after_pension:
1. Your household pays about {tax_saved} less tax a year.
outcome when priced_after_pension:
2. Once your {pension_paid_first} pension contribution is paid, your household pays about {tax_saved} less tax a year.

## gia_to_spouse
status: approved
source: https://www.gov.uk/capital-gains-tax/gifts; TCGA 1992 s58 no gain, no loss between spouses (https://www.legislation.gov.uk/ukpga/1992/12/section/58); ITTOIA 2005 s626 (https://www.legislation.gov.uk/ukpga/2005/5/section/626); https://www.gov.uk/inheritance-tax/gifts
figures: (none published; names only)
why:
1. Dividends and gains on your investments outside an ISA use only your allowances. {spouse_start} has their own dividend and Capital Gains Tax allowances.
when has_gia:
1. Ask {gia} to transfer the investments into {spouse}'s name.
always:
2. You pay no Capital Gains Tax on a gift to a spouse or civil partner you live with. The cost is not reset: the investments pass to them at what you paid, with no gain and no loss.
3. Their dividends are then theirs, taxed at their rates, as long as the gift is outright with no way for it to come back to you.
4. If they sell later, their gain is worked out from what you originally paid. Give them your records of the purchase price.
5. There is no Inheritance Tax on gifts between spouses or civil partners who live in the UK permanently.
outcome when tax_saved:
1. Your household pays about {tax_saved} less tax a year.

## gia_rebalance
status: approved
source: https://www.gov.uk/tax-on-dividends/how-much-tax-youll-pay; https://www.gov.uk/capital-gains-tax/gifts; ITTOIA 2005 s626 (https://www.legislation.gov.uk/ukpga/2005/5/section/626)
figures: user_dividend_rate, spouse_dividend_rate
why:
1. You pay {user_dividend_rate} on dividends above your allowance, and {spouse} pays {spouse_dividend_rate}.
always:
1. On the income recorded, you pay {user_dividend_rate} on dividends above your allowance, and {spouse} pays {spouse_dividend_rate}. The rate depends on each person's own Income Tax band.
2. Investments given outright to {spouse} pay dividends that are theirs, taxed at their rate.
3. A transfer between spouses or civil partners who live together is free of Capital Gains Tax. If they sell later, their gain is worked out from what you originally paid.
when has_gia:
4. Ask {gia} to transfer the holdings into {spouse}'s name.
outcome when tax_saved:
1. Your household pays about {tax_saved} less tax a year.

## isa_coordination
status: approved
source: https://www.gov.uk/individual-savings-accounts/how-isas-work; https://www.gov.uk/inheritance-tax/gifts
figures: (none published; tax config only)
why:
1. You have used your ISA allowance this tax year, and {spouse}'s {isa_allowance} allowance is unused.
always:
1. You have used your ISA allowance this tax year. No ISA is recorded for {spouse}, who has their own {isa_allowance} allowance.
2. They can open a cash or stocks and shares ISA in their own name and pay in, from their own money or money you give them. There is no Inheritance Tax on gifts between spouses or civil partners who live in the UK permanently.
3. You do not pay tax on interest, dividends or gains in an ISA.
4. The allowance is set per tax year, so pay in by {tax_year_end}.
outcome:
1. Interest, dividends and gains on up to {isa_allowance} more a year become tax-free for {spouse}.

## joint_savings_psa_split
status: approved
source: ITA 2007 s836 jointly held property (https://www.legislation.gov.uk/ukpga/2007/3/section/836); s837 declarations of unequal interests (https://www.legislation.gov.uk/ukpga/2007/3/section/837); https://www.gov.uk/apply-tax-free-interest-on-savings; ITA 2007 s12B (https://www.legislation.gov.uk/ukpga/2007/3/section/12B)
figures: sole_balance, annual_interest, user_psa, estimated_annual_tax_saved
why:
1. Your {sole_balance} in your sole name earns about {annual_interest} a year, more than your {user_psa} Personal Savings Allowance.
always:
1. Your {sole_balance} of savings in your sole name earns about {annual_interest} a year, more than your {user_psa} Personal Savings Allowance.
2. Hold the money in a joint account in both your names. While you and {spouse} live together, the interest is taxed as half each.
3. A different split applies only if you own the money in different shares and tell HM Revenue and Customs (HMRC) in a joint declaration.
4. Each of you has your own Personal Savings Allowance, and your half of the interest uses yours.
outcome when not priced_after_pension:
1. Your household pays about {tax_saved} less tax a year.
outcome when priced_after_pension:
2. Once your {pension_paid_first} pension contribution is paid, your household pays about {tax_saved} less tax a year.

## non_earner_spouse_pension
status: approved
source: https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief; Finance Act 2004 s188 relief only before age 75 (https://www.legislation.gov.uk/ukpga/2004/12/section/188); s189 relevant UK earnings (https://www.legislation.gov.uk/ukpga/2004/12/section/189); s190 basic amount (https://www.legislation.gov.uk/ukpga/2004/12/section/190)
figures: net_contribution, gross_contribution, government_uplift, spouse_existing_pension_balance (or net_cost, gross_capacity, spouse_annual_earnings)
why:
1. {spouse_start} has no earnings, but a pension payment for them still gets basic-rate relief added: {net_contribution} becomes {gross_contribution}.
why when net_cost and spouse_annual_earnings:
1. {spouse_start} earns {spouse_annual_earnings} from work, so a pension payment for them gets basic-rate relief on up to {gross_capacity} a year: {net_cost} becomes {gross_capacity}.
why when net_cost and not spouse_annual_earnings:
1. Without earnings from work, a pension payment for {spouse} still gets basic-rate relief on up to {gross_capacity} a year: {net_cost} becomes {gross_capacity}.
when net_contribution and spouse_existing_pension_balance:
1. A pension is already recorded for {spouse}. Check with the provider that it takes personal payments, or open a personal pension in their name.
when net_contribution and not spouse_existing_pension_balance:
2. Open a personal pension in {spouse}'s name.
when net_cost:
3. Use a personal pension in {spouse}'s name, or open one.
always:
4. Pay in {net_contribution}. The provider claims {government_uplift} of basic-rate relief and adds it, so {gross_contribution} goes in, even with no earnings.
5. Pay in {net_cost}. The provider claims {government_uplift} of basic-rate relief and adds it, so {gross_capacity} goes in.
6. Relief is only given on payments made before they reach {relief_max_age}.
7. Pay it in by {tax_year_end}, and ask the provider to confirm that the relief has been claimed.
outcome:
1. {gross_contribution} goes into {spouse}'s pension for {net_contribution} from you.
2. {gross_capacity} goes into {spouse}'s pension for {net_cost} from you.

## lifetime_isa
status: approved
source: https://www.gov.uk/lifetime-isa; https://www.gov.uk/lifetime-isa/withdrawing-money-from-your-lifetime-isa
figures: suggested_contribution, government_bonus, user_age
why:
1. At {user_age} you can still open a Lifetime ISA. On {suggested_contribution} paid in, the government adds {government_bonus}.
when has_lifetime_isa_account:
1. Pay {suggested_contribution} into your Lifetime ISA with {lifetime_isa_account}.
when not has_lifetime_isa_account:
2. Open a Lifetime ISA with a provider and pay in {suggested_contribution}. You are {user_age}; the first payment must be made before you are {lisa_first_payment_before}.
always:
3. The government adds {government_bonus} to what you pay in. You can keep paying in until you are {lisa_pay_in_until}.
4. It counts towards your {isa_allowance} overall ISA allowance.
5. You can take money out without a charge to buy your first home costing {lisa_home_price_limit} or less, at least {lisa_home_min_months} months after your first payment. You can also take it out from age {lisa_free_withdrawal_age}, or if you are terminally ill. Taking it out for any other reason means a {lisa_withdrawal_charge} withdrawal charge.
6. The allowance is set per tax year, so pay in by {tax_year_end}.
outcome:
1. {suggested_contribution} paid in becomes {lisa_total} with the government bonus.

## junior_isa
status: approved
source: https://www.gov.uk/junior-individual-savings-accounts
figures: children_under_18, total_jisa_capacity
why:
1. Money saved for {each_child} in a Junior ISA grows free of tax.
always:
when children_under_18 is 1:
1. Your child can have a Junior ISA, with up to {junior_isa_allowance} paid in each tax year.
when children_under_18 is not 1:
2. Each of your {children_under_18} children under {jisa_withdraw_age} can have a Junior ISA, with up to {junior_isa_allowance} paid in for each child every tax year: {total_jisa_capacity} across them all.
always:
2. A parent or guardian with parental responsibility opens it. It can be a cash one, a stocks and shares one, or both.
3. A child who has a Child Trust Fund cannot also have a Junior ISA. Ask the Junior ISA provider to transfer the trust fund into it.
3. Pay in from the account shown under "Fund from". The money belongs to the child.
4. A child can take control of their account at {jisa_control_age}, and can take the money out from {jisa_withdraw_age}.
5. The allowance is set per tax year, so pay in by {tax_year_end}.
outcome when children_under_18 is 1:
1. Up to {junior_isa_allowance} a year can grow free of tax for your child.
outcome when children_under_18 is not 1:
1. Up to {total_jisa_capacity} a year can grow free of tax for your children.

## junior_pension
status: approved
source: https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief; Finance Act 2004 s189(1)(b) (https://www.legislation.gov.uk/ukpga/2004/12/section/189): relief covers anyone resident in the UK, with no minimum age; s279 normal minimum pension age (https://www.legislation.gov.uk/ukpga/2004/12/section/279)
figures: children_under_18, net_contribution_per_child, gross_contribution_per_child, total_government_uplift
why:
1. Pension payments for {each_child} get basic-rate relief added even with no earnings: {net_contribution_per_child} becomes {gross_contribution_per_child}.
always:
1. Open a personal pension for {each_child} with a provider that offers one for children.
when children_under_18 is 1:
2. Pay in up to {net_contribution_per_child} a year. The provider claims basic-rate relief and adds it, so {gross_contribution_per_child} goes in.
when children_under_18 is not 1:
3. Pay in up to {net_contribution_per_child} a year for each child. The provider claims basic-rate relief and adds it, so {gross_contribution_per_child} goes in for each: {total_government_uplift} of relief in all.
always:
3. The {gross_contribution_per_child} limit is for each child in total, from everyone who pays in.
always:
4. A child cannot take money out of their pension until they reach the normal minimum pension age, currently {normal_minimum_pension_age}.
4. Pay it in by {tax_year_end}. Relief goes to the tax year you pay in.
outcome:
1. {gross_contribution_per_child} goes into each child's pension for {net_contribution_per_child} from you: {total_government_uplift} of relief in all.
