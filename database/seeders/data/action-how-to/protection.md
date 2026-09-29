# How-to steps: protection actions

This file is the one source for the steps on each protection action's detail card. `ActionHowToSeeder` reads it, and only entries marked `status: approved` ever reach a user. CSJ reviews each entry and changes `draft` to `approved`, or edits it. The grammar is the tax file's (`tax.md`): `why:`, `when …:`, `always:`, `outcome:`, `learn:`, conditions, and `{placeholders}`.

**Where the cards come from.** Protection cards are the protection action definitions (`ProtectionActionDefinitionService::evaluateActions`), the same recommendations as the Protection Plan page (CSJ 2026-09-29). Before that they came from seven fixed rules in `RecommendationEngine`, which no how-to could reach.

**One entry, several keys.** A heading can name several keys when they are the same action at a different urgency or scope. The entry is written once and each card fills in its own figures.

**The figures.** Each card's own figures reach its steps: the ones its title and description were written from (`ProtectionActionDefinitionService`, `buildRecommendation`), listed under each entry. They arrive already written, for example `{gap_amount}` as "£618,555" and `{deferred_weeks}` as "26". A step whose figure is missing is left out. `{provider}` reads "your insurer" when the policy names none.

**Also available:** everything in `tax.md` ("What you can branch on", "What you can fill in"), and the household: `has_spouse` and `{spouse}` / `{spouse_start}`, `has_children` and `{children}` (under 18: `DependantsReach::minorChildrenOf`), `employed`.

**Not written, on purpose (the card still shows):**
- **Folded into a position card (CSJ 2026-09-29):** `life_insurance_gap`, `dependants_no_life_cover`, `mortgage_no_decreasing_term`, `education_funding_gap`, `dis_reliance_warning`, `non_earning_spouse_no_cover` (life); `critical_illness_gap`, `no_ci_with_mortgage`, `ci_combined_risk` (critical illness); `income_protection_gap`, `ip_gap_after_state_benefits`, `self_employed_no_ip`, `ip_any_occupation_definition`, `group_ip_any_occupation`, `ip_short_benefit_period`, `ip_long_deferred_period` (income). They no longer show as cards; their approved entries below are the source the position entries were built from.
- `protection_profile_missing`: a data prompt, and it never reaches a card; a user with no protection profile gets no protection cards (`ProtectionStrategySource`).
- `strategy_protection_*`: the composer's catalogue rows (claim tier, locking), never a card.
- `increase_life_cover`, `add_critical_illness`, `add_income_protection`: disabled definitions.

Rules for these steps:
- **Every step rests on the sources named under its heading** (Rule 23), or on the card's own figures.
- **Guidance, not advice.** The steps say how, not whether, and never name an insurer or a product to buy.
- **Speak to the user about their own cover and their own family.** A step never names Fynla; where something acts, it is Fyn or a named page.
- **Health questions.** Wherever a step sends the user to apply for cover, it says to answer the insurer's questions fully and accurately: the Consumer Insurance (Disclosure and Representations) Act 2012 s2 puts that duty on the customer, and s4 with Schedule 1 lets the insurer refuse or reduce a claim when it is broken.

## life_insurance_gap, dependants_no_life_cover
status: approved
source: Fynla protection shortfall (`CoverageGapAnalyzer`, `/help#protection`: debts, your family's income need, final expenses, education); https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/term-insurance (level term keeps the same cover; single or joint life policies); Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4 https://www.legislation.gov.uk/ukpga/2012/6/section/2; the card's own figures
figures: gap_amount, need_amount, coverage_amount, dependant_count
why when coverage_amount is £0:
1. You have no life cover, and your family would need {need_amount}.
why when coverage_amount is not £0:
1. Your life cover of {coverage_amount} is {gap_amount} short of the {need_amount} your family would need.
why:
2. {dependant_count} people depend on your income, and you have no life cover.
always:
1. Check the figures behind your shortfall on the Protection page: your mortgage and other debts, your family's yearly income need, and your children's education.
2. Get quotes for level term life cover of about {gap_amount}, for as long as your family would need it. A protection adviser or a comparison service can quote several insurers at once.
3. Answer every health and lifestyle question fully and accurately. An insurer can refuse or reduce a claim if an answer was careless or wrong.
when has_spouse:
4. Ask for quotes on single life policies for each of you and on a joint policy, and compare what each pays out and when.
always:
5. Once it starts, add the policy on the Protection page with Add New Policy, so your shortfall updates.
outcome:
1. If you died, your family would have a lump sum to clear debts and replace your income.
learn:
1. How your protection shortfall is worked out | /help#protection

## no_policies_warning
status: approved
source: Fynla protection shortfall (`CoverageGapAnalyzer`, `/help#protection`); https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/ (the key types of protection insurance); Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4; the card's own figures
figures: total_gap
why:
1. You have no life insurance, critical illness or income protection policy recorded, and your shortfall across them is {total_gap}.
always:
1. If you already hold a policy, through a mortgage or an old job, add it on the Protection page with Add New Policy first. Your shortfall may be smaller than it looks.
2. Your Protection page shows the shortfall for each kind of cover separately, so you can see which is largest.
3. Get quotes for that cover, and answer every health and lifestyle question fully and accurately.
outcome:
1. Your household has cover where its shortfall is largest.
learn:
1. How your protection shortfall is worked out | /help#protection

## education_funding_gap
status: approved
source: Fynla protection shortfall (`CoverageGapAnalyzer`, `/help#protection`: education for each child to 21); the card's own figures
figures: gap_amount
why:
1. If you died, there would be {gap_amount} less than your children's education would cost to age 21.
always:
1. Check the education figure on the Protection page, and the children it counts.
when has_children:
2. It counts {children}.
always:
3. When you get quotes for life cover, include {gap_amount} in the amount.
4. Once the cover starts, add the policy on the Protection page.
outcome:
1. Your children's education is paid for even if you are not there.
learn:
1. How your protection shortfall is worked out | /help#protection

## mortgage_no_decreasing_term
status: approved
source: https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/term-insurance (decreasing term covers a reducing debt such as a repayment mortgage); Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4; the card's own figures
figures: mortgage_amount
why:
1. You owe {mortgage_amount} on your mortgage, and no life policy you have recorded is set up to pay it off.
always:
1. If you already hold life cover for your mortgage, open it on the Protection page and tick "Is this to pay off your mortgage?".
2. Otherwise, check your mortgage statement for the balance, the years left and whether it is repayment or interest only.
3. For a repayment mortgage, get quotes for decreasing term cover of {mortgage_amount} over the years left. The cover falls as the balance does. For interest only, the balance does not fall, so ask for level term cover instead.
4. Answer every health and lifestyle question fully and accurately.
outcome:
1. If you died, your mortgage would be paid off.

## critical_illness_gap, no_ci_with_mortgage
status: approved
source: https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/critical-illness-cover (a tax-free lump sum; every policy covers cancer, heart attack and stroke at set severities; cover varies between insurers); Fynla protection shortfall (`CoverageGapAnalyzer`, `/help#protection`); Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4; the card's own figures
figures: gap_amount, need_amount, coverage_amount, mortgage_amount
why when coverage_amount is £0:
1. You have no critical illness cover, and your need is {need_amount}.
why when coverage_amount is not £0:
1. Your critical illness cover of {coverage_amount} is {gap_amount} short of your need.
why:
2. You owe {mortgage_amount} on your mortgage and have no critical illness cover.
always:
1. Critical illness cover pays a tax-free lump sum if you are diagnosed with a condition the policy covers.
2. Get quotes for about {gap_amount} of cover.
2. Get quotes for at least {mortgage_amount} of cover, enough to clear your mortgage.
3. Compare which conditions each policy covers and how severe each must be to pay. Every policy covers cancer, heart attack and stroke, and the rest varies between insurers.
4. Answer every health and lifestyle question fully and accurately.
5. Once it starts, add it on the Protection page with Add New Policy.
outcome:
1. A serious diagnosis would come with a lump sum to clear debts or cover time off work.
learn:
1. How your protection shortfall is worked out | /help#protection

## income_protection_gap, ip_gap_after_state_benefits
status: approved
source: https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/income-protection (pays when illness or injury stops you working; not on redundancy); https://www.gov.uk/statutory-sick-pay (weekly rate, up to 28 weeks); Statutory Sick Pay rate and weeks from tax config (`benefits.ssp`); Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4; the card's own figures
figures: gap_amount, need_amount, coverage_amount, ssp_total, ssp_weekly, ssp_weeks, ip_gap
why when coverage_amount is £0:
1. You have no income protection. If illness or injury stopped you working, you would be {gap_amount} a month short of the income you need.
why when coverage_amount is not £0:
1. Your income protection pays {coverage_amount} a month, which leaves you {gap_amount} a month short of the income you need if illness or injury stopped you working.
why:
2. Statutory Sick Pay pays up to {ssp_weekly} a week for up to {ssp_weeks} weeks, {ssp_total} in all, and leaves you {ip_gap} a month short.
always:
1. Check what your employer pays when you are off sick, and for how long. Your contract or staff handbook says.
2. Get quotes for income protection of about {gap_amount} a month. It pays a monthly income while illness or injury stops you working. It does not pay if you are made redundant.
2. Get quotes for income protection of about {ip_gap} a month. It pays a monthly income while illness or injury stops you working. It does not pay if you are made redundant.
3. Choose when it starts paying, the deferred period, to begin when your sick pay ends or your savings would run out.
4. Answer every health and lifestyle question fully and accurately.
5. Once it starts, add it on the Protection page with Add New Policy.
outcome:
1. Your household keeps an income if you cannot work.
learn:
1. How your protection shortfall is worked out | /help#protection

## self_employed_no_ip
status: approved
source: https://www.gov.uk/statutory-sick-pay/eligibility (you must be classed as an employee); https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/income-protection; Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4
figures: none
why:
1. You are self-employed, so you cannot get Statutory Sick Pay, and you have no income protection. If you could not work, your income would stop.
always:
1. Work out how many months your savings would cover your bills if you stopped earning.
2. Get quotes for income protection that pays a monthly income from when your savings would run out.
3. Answer every health and lifestyle question fully and accurately.
4. Once it starts, add it on the Protection page with Add New Policy.
outcome:
1. You keep an income if illness or injury stops you working.

## policy_not_in_trust
status: approved
source: HMRC Inheritance Tax Manual IHTM20012 (proceeds of a policy the deceased owned on their own life form part of their estate; settled policies are treated as settled property) https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm20012; https://www.gov.uk/applying-for-probate (probate before the estate can be dealt with); Fynla policy form ("Is this policy in Trust?")
figures: provider
why:
1. Your life policy with {provider} is not in trust. When a policy you own on your own life pays out, the money is part of your estate. It can be taxed as part of your estate and waits for probate.
always:
1. Ask {provider} for its trust form.
2. Choose the trustees who will receive the money and pass it on, and the people it is for.
3. Complete and return the form to {provider}, and keep a copy with your will.
4. On the Protection page, edit the policy and tick "Is this policy in Trust?".
outcome:
1. The payout goes to your trustees for the people it is for, rather than into your estate.
learn:
1. Life policies and Inheritance Tax | /help#estate

## policy_not_joint_married
status: approved
source: https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/term-insurance (single or joint life policies); Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4
figures: provider
why:
1. You are married, and your life policy with {provider} covers only you.
always:
1. Check whether {spouse} has life cover of their own, and add it on the Protection page if so.
2. If not, get quotes for a joint policy covering you both and for a single policy for {spouse}, and compare the price and what each pays out.
3. Keep your policy with {provider} until any new cover has started.
outcome:
1. You both have life cover, in the way that costs your household least.

## policy_expiring_soon, policy_expired
status: approved
source: the card's own figures; Fynla protection shortfall (`/help#protection`); Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4
figures: provider, end_date
why:
1. Your life policy with {provider} ends on {end_date}.
2. Your life policy with {provider} ended on {end_date}, so it no longer covers you.
always:
1. Check on the Protection page whether you still have a shortfall without this policy: debts, a family who depend on you, or children in education.
2. If you do, get quotes for new cover before {end_date}, so there is no gap between the two.
3. Answer every health and lifestyle question fully and accurately. The new insurer looks at your age and health as they are now.
4. Once the new policy starts, add it on the Protection page, and delete the old one once it has ended.
outcome:
1. You stay covered for as long as your family needs it.

## ip_any_occupation_definition, group_ip_any_occupation
status: approved
source: the card's own figures (the policy's definition, as recorded); https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/income-protection; Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4
figures: provider
why:
1. Your income protection with {provider} pays only if you cannot do any job at all, not just your own.
always:
1. Read the definition of incapacity in your policy document, or your employer's scheme booklet, to confirm it.
2. Ask {provider} whether the policy can change to an "own occupation" definition, which pays if you cannot do your own job, and what that would cost.
3. Compare that with quotes for a personal policy on an own occupation definition, and answer every health question fully and accurately.
4. Keep your current cover until any new policy has started.
outcome:
1. Your income protection pays if illness or injury stops you doing your own job.

## ip_short_benefit_period
status: approved
source: the card's own figures; https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/income-protection
figures: benefit_months, provider
why:
1. Your income protection with {provider} pays for up to {benefit_months} months for each claim.
always:
1. Work out how long your household could manage if a long illness stopped you working after the {benefit_months} months.
2. Ask {provider} what it would cost to extend the benefit period, up to your retirement age.
3. Keep your current cover until any change has started, and update the policy's benefit period on the Protection page.
outcome:
1. Your income continues for as long as a long illness keeps you off work.

## ip_long_deferred_period
status: approved
source: the card's own figures; https://www.gov.uk/statutory-sick-pay (weekly rate, up to 28 weeks); Statutory Sick Pay from tax config (`benefits.ssp`)
figures: deferred_weeks, provider
why:
1. Your income protection with {provider} starts paying {deferred_weeks} weeks after you stop work.
always:
1. Check how long your employer pays you when you are off sick. Your contract or staff handbook says.
2. Work out whether your sick pay and your savings would cover your bills for the {deferred_weeks} weeks.
3. If not, ask {provider} what a shorter deferred period would cost, or build up savings to cover the gap.
4. Update the deferred period on the Protection page if it changes.
outcome:
1. There is no gap in your income between stopping work and your cover paying out.

## ci_combined_risk
status: approved
source: https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/critical-illness-cover; the card's own figures
figures: provider
why:
1. Your policy with {provider} combines life and critical illness cover in one policy.
always:
1. Check in your policy document whether a critical illness claim ends the life cover too.
2. If your family would still need life cover after an illness, get quotes for a separate life policy or separate critical illness cover, and compare the total with what you pay now.
3. Keep your current policy until any new cover has started.
outcome:
1. A critical illness claim would leave your family's life cover in place.

## dis_reliance_warning
status: approved
source: https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/group-life-cover (paid by your employer; usually a multiple of salary; ends if you leave); Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4
figures: none
why:
1. More than half of your life cover is death in service from your employer. It ends if you leave, change job or are made redundant.
always:
1. Check your employer's benefits booklet for how much the death in service pays.
2. Get quotes for a personal life policy that would replace it, and answer every health question fully and accurately.
3. Once it starts, add it on the Protection page with Add New Policy.
outcome:
1. Your family keeps its life cover if you change job.

## non_earning_spouse_no_cover
status: approved
source: the card's own figures; Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4
figures: none
why:
1. {spouse_start} has no earned income and no life cover. If they died, you would pay for the childcare and running of the home they now provide.
always:
1. Work out what childcare and help at home would cost each year, and for how many years.
2. Get quotes for life cover on {spouse}'s life for that amount, or a joint policy for you both. {spouse_start} answers the health questions, fully and accurately.
3. Once it starts, add the policy on the Protection page.
outcome:
1. Your household could pay for childcare and help at home if {spouse} died.

## review_existing_policies, consolidate_policies
status: approved
source: the card's own figures; Fynla protection shortfall (`/help#protection`)
figures: policy_count
why:
1. You have {policy_count} protection policies, and your cover is still short of your need.
always:
1. On the Protection page, open each policy and check its cover, premium, end date and whether it is in trust against your paperwork.
2. Compare the cover with your shortfall on the same page, and note what each policy is for.
3. Ask your insurers, or a protection adviser, whether a change would close the gap for less. Keep every policy until any new cover has started.
outcome:
1. Your policies match what your family needs, with no gaps and no cover paid for twice.
learn:
1. How your protection shortfall is worked out | /help#protection

## high_premium_cost, premium_affordability_warning
status: approved
source: the card's own figures; Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4
figures: annual_premiums, premium_percent
why:
1. Your protection premiums are {annual_premiums} a year, {premium_percent}% of your income.
always:
1. On the Protection page, list what each policy pays for and what it costs.
2. Check whether any policy covers something you no longer need, such as a debt that is paid off, or covers the same thing twice.
3. Get quotes for the cover you still need. A new insurer asks about your health as it is now, so answer every question fully and accurately.
4. Keep every policy until any new cover has started.
outcome:
1. You pay for the cover your family needs, and no more.

## no_employer_benefits_recorded
status: approved
source: https://www.abi.org.uk/policy-and-guidance/general-insurance/health-protection-insurance/protection-insurance/group-life-cover (paid by your employer; usually a multiple of salary; ends if you leave); Fynla protection shortfall (`CoverageGapAnalyzer`: death in service x salary counts as life cover, group income protection % of salary as income cover, group critical illness as critical illness cover); the employer benefits form (web Protection page, `/m` Protection screen and Fyn: `EmployerBenefitsWriter`)
figures: none
why:
1. You are employed, and you have not told us what cover your job gives you. Death in service, group income protection and group critical illness cover all count towards your shortfall.
always:
1. Check your employer's benefits booklet, staff handbook or HR team for death in service, group income protection, group critical illness cover and private medical insurance.
2. Enter them under Employer benefits on the Protection page, or tell Fyn. If your job gives you none of these, say so there.
outcome:
1. Your protection shortfall counts the cover your job already gives you.
learn:
1. How your protection shortfall is worked out | /help#protection

## life_cover_position
status: approved
source: every source under life_insurance_gap, mortgage_no_decreasing_term, education_funding_gap, dis_reliance_warning and non_earning_spouse_no_cover below (approved 2026-09-29); the cover position (`ProtectionCoverPosition`: need = total need, cover = life policies reaching you plus death in service)
figures: need, own_cover, employer_cover, short_by, over_by, employer_share, is_short, is_over, depends_on_job, mortgage_amount, dependant_count, education_gap; reasons: life_insurance_gap, dependants_no_life_cover, mortgage_no_decreasing_term, education_funding_gap, dis_reliance_warning, non_earning_spouse_no_cover
why when is_short:
1. Your family would need {need}. Your own policies give {own_cover} and your job {employer_cover}, so your life cover is {short_by} short.
why when is_over:
1. Your family would need {need}. Your own policies give {own_cover} and your job {employer_cover}, so you have {over_by} more life cover than you need.
why when depends_on_job:
2. {employer_share}% of your life cover comes through your job. It ends if you leave, change job or are made redundant.
why when dependants_no_life_cover:
3. {dependant_count} people depend on your income, and you have no life cover.
why when mortgage_no_decreasing_term:
4. You owe {mortgage_amount} on your mortgage, and no life policy you have recorded is set up to pay it off.
why when education_funding_gap:
5. Your children's education would be {education_gap} short.
why when non_earning_spouse_no_cover:
6. {spouse_start} has no earned income and no life cover. If they died, you would pay for the childcare and running of the home they now provide.
always:
1. Check the figures behind your need on the Protection page: your mortgage and other debts, your family's yearly income need, and your children's education.
when is_short:
2. Get quotes for level term life cover of about {short_by}, for as long as your family would need it. A protection adviser or a comparison service can quote several insurers at once.
when is_short and has_spouse:
3. Ask for quotes on single life policies for each of you and on a joint policy, and compare what each pays out and when.
when mortgage_no_decreasing_term:
4. If you already hold life cover for your mortgage, open it on the Protection page and tick "Is this to pay off your mortgage?". For a repayment mortgage, the cover for it can be decreasing term over the years left, falling as the balance does; for interest only, ask for level term.
when depends_on_job:
5. Check your employer's benefits booklet for how much the death in service pays, and get quotes for a personal policy that would replace it if you left.
when non_earning_spouse_no_cover:
6. Work out what childcare and help at home would cost each year, and get quotes for life cover on {spouse}'s life for that amount. {spouse_start} answers the health questions, fully and accurately.
when is_over:
2. Check whether you still need all of it, for example cover taken out for a debt you have since paid off. Do this before the policy next renews, and keep every policy until any change has started.
when not is_over:
7. Answer every health and lifestyle question fully and accurately. An insurer can refuse or reduce a claim if an answer was careless or wrong.
8. Once any new cover starts, add it on the Protection page with Add New Policy.
outcome when not is_over:
1. If you died, your family would have a lump sum to clear debts and replace your income, whatever happens to your job.
outcome when is_over:
1. You pay for the life cover your family needs, and no more.
learn:
1. How your protection shortfall is worked out | /help#protection

## critical_illness_position
status: approved
source: every source under critical_illness_gap, no_ci_with_mortgage and ci_combined_risk below (approved 2026-09-29); the cover position (need = gross earned income x `protection.income_multipliers.critical_illness`)
figures: need, own_cover, employer_cover, short_by, over_by, employer_share, is_short, is_over, depends_on_job, mortgage_amount, provider; reasons: critical_illness_gap, no_ci_with_mortgage, ci_combined_risk
why when is_short:
1. You would need {need} if a serious illness stopped you working. Your own policies give {own_cover} and your job {employer_cover}, so your cover is {short_by} short.
why when is_over:
1. You would need {need}. Your own policies give {own_cover} and your job {employer_cover}, so you have {over_by} more critical illness cover than you need.
why when depends_on_job:
2. {employer_share}% of your critical illness cover comes through your job, and it ends if you leave.
why when no_ci_with_mortgage:
3. You owe {mortgage_amount} on your mortgage.
why when ci_combined_risk:
4. Your policy with {provider} combines life and critical illness cover in one policy.
always:
1. Critical illness cover pays a tax-free lump sum if you are diagnosed with a condition the policy covers. Every policy covers cancer, heart attack and stroke, and the rest varies between insurers.
when is_short:
2. Get quotes for about {short_by} of cover, and compare which conditions each policy covers and how severe each must be to pay.
when no_ci_with_mortgage:
3. Include enough to clear your {mortgage_amount} mortgage.
when ci_combined_risk:
4. Check in your policy document whether a critical illness claim ends the life cover too. If your family would still need life cover after an illness, compare separate policies with what you pay now.
when is_over:
2. Check whether you still need all of it before the policy next renews, and keep every policy until any change has started.
when not is_over:
5. Answer every health and lifestyle question fully and accurately, and once any new cover starts, add it on the Protection page.
outcome when not is_over:
1. A serious diagnosis would come with a lump sum to clear debts or cover time off work.
outcome when is_over:
1. You pay for the critical illness cover you need, and no more.
learn:
1. How your protection shortfall is worked out | /help#protection

## income_protection_position
status: approved
source: every source under income_protection_gap, ip_gap_after_state_benefits, self_employed_no_ip, ip_any_occupation_definition, ip_short_benefit_period and ip_long_deferred_period below (approved 2026-09-29); the cover position (need = `protection.income_multipliers.income_protection_max_benefit` of gross earned income, a month)
figures: need, own_cover, employer_cover, short_by, over_by, employer_share, is_short, is_over, depends_on_job, ssp_weekly, ssp_weeks, ssp_total, provider, benefit_months, deferred_weeks; reasons: income_protection_gap, ip_gap_after_state_benefits, self_employed_no_ip, ip_any_occupation_definition, group_ip_any_occupation, ip_short_benefit_period, ip_long_deferred_period
why when is_short:
1. If illness or injury stopped you working, you would need {need} a month. Your own policies give {own_cover} a month and your job {employer_cover} a month, so you are {short_by} a month short.
why when is_over:
1. You would need {need} a month. Your own policies give {own_cover} a month and your job {employer_cover} a month, so you have {over_by} a month more than you need.
why when depends_on_job:
2. {employer_share}% of your income protection comes through your job, and it ends if you leave.
why when ip_gap_after_state_benefits:
3. Statutory Sick Pay pays up to {ssp_weekly} a week for up to {ssp_weeks} weeks, {ssp_total} in all.
why when self_employed_no_ip:
3. You are self-employed, so you cannot get Statutory Sick Pay.
why when ip_any_occupation_definition:
4. Your income protection with {provider} pays only if you cannot do any job at all, not just your own.
why when group_ip_any_occupation:
4. Your employer's income protection pays only if you cannot do any job at all, not just your own.
why when ip_short_benefit_period:
5. Your income protection with {provider} pays for up to {benefit_months} months for each claim.
why when ip_long_deferred_period:
6. Your income protection with {provider} starts paying {deferred_weeks} weeks after you stop work.
always:
1. Check what your employer pays when you are off sick, and for how long. Your contract or staff handbook says.
when is_short:
2. Get quotes for income protection of about {short_by} a month. It pays a monthly income while illness or injury stops you working. It does not pay if you are made redundant.
3. Choose when it starts paying, the deferred period, to begin when your sick pay ends or your savings would run out.
when ip_any_occupation_definition:
4. Read the definition of incapacity in your policy document, and ask {provider} whether the policy can change to pay if you cannot do your own job, and what that would cost.
when group_ip_any_occupation:
4. Read the definition of incapacity in your employer's scheme booklet, and get quotes for a personal policy that pays if you cannot do your own job.
when ip_short_benefit_period:
5. Ask {provider} what it would cost to extend the benefit period, up to your retirement age.
when ip_long_deferred_period:
6. Work out whether your sick pay and savings would cover your bills for the {deferred_weeks} weeks, and if not, ask {provider} what a shorter deferred period would cost.
when is_over:
2. Check whether you still need all of it before the policy next renews, and keep every policy until any change has started.
when not is_over:
7. Answer every health and lifestyle question fully and accurately, and once any new cover starts, add it on the Protection page.
outcome when not is_over:
1. Your household keeps an income if you cannot work.
outcome when is_over:
1. You pay for the income protection you need, and no more.
learn:
1. How your protection shortfall is worked out | /help#protection
