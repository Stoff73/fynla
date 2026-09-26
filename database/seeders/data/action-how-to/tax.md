# How-to steps: tax actions

This file is the one source for the steps on each tax action's detail card. `ActionHowToSeeder` reads it, and only entries marked `status: approved` ever reach a user. CSJ reviews each entry and changes `draft` to `approved`, or edits it.

An entry marked `unverified:` has a step that goes beyond what I checked on the named page. Confirm or change it before approving.

Rules for these steps:
- **No figures.** The card shows the user's own figures beside the steps (CLAUDE.md Rule 2).
- **Every step rests on the source named under its heading** (Rule 23).
- **Guidance, not advice.** The steps say how, not whether.

## pension_tax_relief
status: draft
source: https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief
1. Decide how much more to pay in, up to the amount on this card.
2. For a workplace pension, ask your employer to increase your contribution through payroll. The relief is given through your pay.
3. For a personal pension or SIPP, pay into it directly. Your provider claims basic-rate relief and adds it to your pot.
4. If you pay tax above the basic rate, claim the extra relief on your Self Assessment tax return, or through HMRC's online service if you do not file one.

## salary_sacrifice_ni
status: draft
source: https://www.gov.uk/guidance/salary-sacrifice-and-the-effects-on-paye
1. Ask your employer whether they offer salary sacrifice for pension contributions.
2. If they do, agree the change in writing. Your contract must show your new cash pay and the pension contribution your employer makes instead.
3. Ask whether your employer passes on any of their own National Insurance saving to your pension.
4. Check your first payslip after the change, to see that your pay and your pension contribution match what you agreed.

## isa_topup_vs_psa
status: draft
source: https://www.gov.uk/individual-savings-accounts
1. Choose a cash ISA, or use one you already have.
2. Move the amount shown from the account you pick under "Fund from".
3. Pay it in before the tax year ends. The allowance is set per tax year.

## isa_topup_spouse
status: draft
source: https://www.gov.uk/individual-savings-accounts
1. Your spouse or civil partner opens a cash ISA in their own name, or uses one they already have.
2. They pay in the amount shown from their savings, or from money you give them. Gifts between spouses and civil partners are not taxed.
3. The ISA allowance is set per tax year, so pay in before it ends.

## bed_and_isa
status: draft
unverified: I did not fetch the source page for this entry.
source: https://www.gov.uk/capital-gains-tax
1. Ask your platform or provider whether they offer a "Bed and ISA" transfer.
2. They sell the investments in your general account and buy them back inside your stocks and shares ISA, up to your remaining ISA allowance.
3. Any gain on the sale counts for Capital Gains Tax in this tax year, so check it against your annual exempt amount before you go ahead.

## dividend_allowance_harvest
status: draft
unverified: I did not fetch the source page. Step 2 reads close to advice, so it needs your wording.
source: https://www.gov.uk/tax-on-dividends
1. Look at the dividends your investments outside an ISA pay each year.
2. Hold the investments that pay dividends up to your allowance outside the ISA, and keep the rest inside ISAs and pensions, where dividends are not taxed.

## pension_aa_carry_forward
status: draft
unverified: The source confirms three years of carry forward. It does not confirm the order of use (step 2) or that unused allowance is lost at the end of the tax year (step 3).
source: https://www.gov.uk/tax-on-your-private-pension/annual-allowance
1. Ask your pension provider or providers for your contributions in each of the last three tax years.
2. Use this year's annual allowance first, then any unused allowance from the earliest of the three years.
3. Pay in the extra before the tax year ends. The oldest year's unused allowance is lost after that.

## tapered_annual_allowance
status: draft
source: https://www.gov.uk/tax-on-your-private-pension/annual-allowance
1. Work out your threshold income and your adjusted income for this tax year. Your pension provider or an adviser can help.
2. If both are over the limits, your annual allowance is reduced. Keep your total contributions, including your employer's, within the reduced amount.
3. If you go over it, report the charge in the "Pension savings tax charges" section of your Self Assessment return.

## pa_taper_rescue
status: draft
source: https://www.gov.uk/income-tax-rates/income-over-100000; ITA 2007 s58 (https://www.legislation.gov.uk/ukpga/2007/3/section/58)
1. Your Personal Allowance is reduced once your adjusted net income goes above the limit.
2. Pension contributions and Gift Aid donations reduce your adjusted net income.
3. Pay the amount shown into your pension. For a workplace pension, ask your employer; for a personal pension, pay the provider directly and claim the extra relief on your Self Assessment return.

## additional_rate_avoidance
status: draft
source: https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief
1. Paying into a pension gives relief at your highest rate.
2. Pay the amount shown, using the same routes as for pension tax relief.
3. Claim the extra relief on your Self Assessment tax return.

## gift_aid_higher_rate_relief
status: draft
source: https://www.gov.uk/donating-to-charity/gift-aid
1. Make sure you have given a Gift Aid declaration to each charity you give to.
2. Claim the extra relief on your Self Assessment tax return, or ask HMRC to change your tax code.

## marriage_allowance_transfer
status: draft
source: https://www.gov.uk/marriage-allowance/how-to-apply
1. The partner who earns less makes the claim.
2. Apply online on GOV.UK, or through the Marriage Allowance section of a Self Assessment return.
3. If the claim succeeds, the change is backdated to the start of the tax year.

## savings_to_spouse
status: draft
source: https://www.gov.uk/capital-gains-tax/gifts; https://www.gov.uk/apply-tax-free-interest-on-savings
1. Move the amount shown into an account in your spouse's or civil partner's name.
2. The interest is then theirs and uses their allowances.
3. A gift between spouses or civil partners who live together is not taxed. Once it is given, the money belongs to them.

## gia_to_spouse
status: draft
source: https://www.gov.uk/capital-gains-tax/gifts
1. Ask your platform to transfer the investments shown into your spouse's or civil partner's name.
2. You pay no Capital Gains Tax on a gift to a spouse or civil partner you live with.
3. If they sell later, their gain is worked out from what you originally paid.

## gia_rebalance
status: draft
unverified: Step 1 (comparing rates) is my wording, not from the source.
source: https://www.gov.uk/capital-gains-tax/gifts
1. Compare which of you pays the lower rate on dividends and gains.
2. Transfer investments to that partner. A transfer between spouses or civil partners who live together is not taxed.

## isa_coordination
status: draft
unverified: The ordering in step 2 is my wording.
source: https://www.gov.uk/individual-savings-accounts
1. Each of you has your own ISA allowance every tax year.
2. Use both before the tax year ends, starting with the savings or investments that would be taxed.

## joint_savings_psa_split
status: draft
unverified: Step 3 is my wording.
source: https://www.gov.uk/apply-tax-free-interest-on-savings
1. HMRC splits interest on a joint account equally between the account holders.
2. If the money should be split differently, contact HMRC.
3. Hold savings so that each of you uses your own Personal Savings Allowance.

## non_earner_spouse_pension
status: draft
source: https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief
1. Open a personal pension in your spouse's or civil partner's name, or use one they already have.
2. Pay in up to the amount shown. The provider claims basic-rate relief and adds it to the pot, even with no earnings.
3. Ask the provider to confirm that the relief has been claimed.

## lifetime_isa
status: draft
source: https://www.gov.uk/lifetime-isa
1. Open a Lifetime ISA with a provider. There is an age limit for the first payment, so check it on GOV.UK first.
2. Pay in from the account shown under "Fund from". The government adds a bonus to what you pay in.
3. It counts towards your overall ISA allowance, and you can use it for a first home or for later life.

## junior_isa
status: draft
source: https://www.gov.uk/junior-individual-savings-accounts
1. A parent or guardian with parental responsibility opens a Junior ISA for the child. They can have a cash one, a stocks and shares one, or both.
2. Pay in up to the yearly limit, from the account shown under "Fund from".
3. The child can take control of the account, and later withdraw the money, at the ages set out on GOV.UK.

## junior_pension
status: draft
unverified: The source covers relief at source generally. It does not say that pensions for children qualify.
source: https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief
1. Open a personal pension for the child with a provider that offers one.
2. Pay in up to the amount shown. The provider claims basic-rate relief and adds it to the pot.
