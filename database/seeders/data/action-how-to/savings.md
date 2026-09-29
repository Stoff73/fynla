# How-to steps: savings actions

This file is the one source for the steps on each savings action's detail card. `ActionHowToSeeder` reads it, and only entries marked `status: approved` ever reach a user. CSJ reviews each entry and changes `draft` to `approved`, or edits it. The grammar is the tax file's (`tax.md`): `why:`, `when …:`, `always:`, `outcome:`, `learn:`, conditions, and `{placeholders}`.

**One entry, several keys.** A heading can name several keys (`## rate_below_market, rate_poor`) when they are the same action at a different urgency or scope. The entry is written once and each card fills in its own figures.

**The figures.** Each card's own figures reach its steps: the ones its title and description were written from (`SavingsActionDefinitionService`, `buildRecommendation`), listed under each entry. They arrive already written, for example `{balance}` as "£4,500" and `{account_rate}` as "1.10" (add the % in the step). A step whose figure is missing is left out.

**Also available:** everything in `tax.md` ("What you can branch on", "What you can fill in"), plus the savings figures from tax config: `{psa}` (this user's Personal Savings Allowance), `{psa_basic}`, `{psa_higher}`, `{psa_additional}`, `{starting_rate_band}`, `{fscs_limit}`, `{fscs_joint_limit}`, `{fscs_high_balance_limit}`, `{fscs_high_balance_months}`, `{parental_settlement_limit}`.

**Not written, on purpose (the card still shows):**
- `strategy_build_emergency_fund`, `strategy_move_to_high_interest`, `strategy_regular_savings_habit`: internal fallback types (`SavingsRecommendationAdapter`), never a card.
- `missing_date_of_birth`, `missing_income`, `missing_expenditure`, `missing_employment_status`: data prompts; the card's "Add it now" opens Fyn's form.
- `cash_isa_not_needed`, `goal_nearly_achieved`, `psa_headroom_available`: information with nothing to do.
- `offset_mortgage_better`, `excess_cash_bond`, `excess_cash_gia`: no source could be verified yet (Rule 23); CSJ to supply or approve one.

Rules for these steps:
- **Every step rests on the sources named under its heading** (Rule 23), or on the card's own figures.
- **Guidance, not advice.** The steps say how, not whether.
- **Speak to the user about their own money** (CSJ 2026-09-29). The target, the plan and the goal are theirs: "your target", never "Fynla's". A step never names Fynla; where something acts, it is Fyn or a named page.
- **Spell out** the Financial Services Compensation Scheme and the Personal Savings Allowance (Rule 9). ISA is fine.

## emergency_fund_critical, emergency_fund_low, emergency_fund_building
status: edited
source: Fynla emergency fund target (`PlanConfigService::emergency_fund.target_months`, `/help#investment-savings`); the card's own figures
figures: runway_months, target_months, shortfall, monthly_top_up, adequacy_percent
why when runway_months is 0.0:
1. You have no savings set aside for emergencies yet. Your target is {target_months} months of your spending.
why when runway_months is not 0.0:
1. Your savings cover {runway_months} months of your spending. Your target is {target_months} months.
why:
2. You need {shortfall} more to reach it.
always:
1. Choose one easy access account to hold your emergency fund, kept apart from the account you spend from.
2. Set up a standing order of {monthly_top_up} a month into it, on the day after you are paid.
2. Set up a standing order into it on the day after you are paid, and keep it going until you have put aside {shortfall}.
3. On the Savings page on the web, edit that account and tick "This forms part of my emergency fund", so it counts towards your target.
outcome:
1. Your emergency fund covers {target_months} months of your spending, so an unexpected bill or a gap in income comes out of savings.
learn:
1. Your emergency fund target | /help#investment-savings

## emergency_fund_no_data
status: edited
source: Fynla emergency fund target (`PlanConfigService::emergency_fund.target_months`, `/help#investment-savings`)
figures: target_months, total_cash
why:
1. Your emergency fund target is set from what you spend each month, and your spending is not recorded yet.
always:
1. Tell Fyn roughly what you spend in a month, or add it under your spending on the web.
outcome:
1. You can see how many months of spending your {total_cash} of savings covers, against your target of {target_months} months.
learn:
1. Your emergency fund target | /help#investment-savings

## create_emergency_fund_goal
status: draft
source: Fynla Goals (`/goals`); Fynla emergency fund target (`PlanConfigService::emergency_fund.target_months`)
figures: runway_months, target_amount, target_months
why when runway_months is 0.0:
1. You have no savings set aside for emergencies yet. A goal shows your progress as they build.
why when runway_months is not 0.0:
1. Your savings cover {runway_months} months of your spending. A goal shows your progress towards the rest.
always:
1. On the Goals page, add a goal for your emergency fund with a target of {target_amount}.
2. Link the easy access account you are saving into, so your progress updates as the balance grows.
outcome:
1. You can see your progress towards {target_amount}, which covers {target_months} months of your spending.

## emergency_fund_no_designated
status: draft
source: Fynla savings account form (`SaveAccountModal.vue`, "This forms part of my emergency fund")
figures: none
why:
1. None of your savings accounts is marked as your emergency fund, so your emergency fund cannot be measured.
always:
1. Decide which account you would draw on first in an emergency. It should be easy access.
2. On the Savings page on the web, edit that account and tick "This forms part of my emergency fund".
outcome:
1. You can see how many months of your spending that account covers.
learn:
1. Your emergency fund target | /help#investment-savings

## emergency_fund_excess
status: draft
source: Fynla emergency fund target (`PlanConfigService::emergency_fund.target_months`); https://www.gov.uk/individual-savings-accounts/how-isas-work (ISA allowance, tax year)
figures: runway_months, excess_months, excess_amount
why:
1. Your emergency fund covers {runway_months} months of spending, {excess_months} months more than your target: {excess_amount}.
always:
1. Leave your target amount in easy access, and decide what the {excess_amount} above it is for.
2. For money you will not need soon, you can pay up to {isa_allowance} a year into ISAs before {tax_year_end}, where the interest or growth is tax-free.
when has_cash_isa:
3. Your Cash ISA with {cash_isa} can take some of it, if it takes new money.
outcome:
1. Your emergency fund stays at its target, and the money above it goes where it earns more or is sheltered from tax.
learn:
1. The ISA allowance | /help#investment-savings

## rate_below_market, rate_poor, zero_rate_account
status: draft
source: https://www.gov.uk/individual-savings-accounts/transferring-your-isa (transfer, do not withdraw); the card's own figures (Fynla market rates, `RateComparator`)
figures: account_name, account_rate, market_rate, rate_gap, potential_gain, institution, balance, is_isa
why:
1. {account_name} pays {account_rate}%. The best comparable account pays {market_rate}%.
2. {account_name} holds {balance} at no interest.
always:
1. Compare rates for the same kind of account, easy access or fixed, and open the one you choose.
2. Move the money across, keeping whatever you need for day-to-day spending where it is.
when is_isa:
3. {account_name} is an ISA, so ask the new provider to transfer it with an ISA transfer form. Withdrawing it yourself loses its ISA status, and you cannot pay that part back in.
always:
4. Update the account's rate on the Savings page, or tell Fyn.
outcome:
1. About {potential_gain} a year more interest on the same money.

## fixed_maturity_warning, fixed_maturity_urgent, promo_rate_expiring
status: draft
source: https://www.gov.uk/individual-savings-accounts/transferring-your-isa (transfer, do not withdraw); the card's own figures
figures: account_name, balance, institution, days_to_maturity, maturity_date, days_to_expiry, expiry_date, is_isa
why:
1. {account_name} at {institution} ends its fixed term on {maturity_date}, in {days_to_maturity} days.
2. The promotional rate on {account_name} ends on {expiry_date}, in {days_to_expiry} days.
always:
1. Ask {institution} what happens to the {balance} when the rate ends and what rate it will pay then.
2. Compare rates now, so the money can move as soon as the rate ends.
when is_isa:
3. {account_name} is an ISA, so move it with the new provider's ISA transfer form rather than withdrawing it.
always:
4. Once it has moved, update the account on the Savings page, or tell Fyn.
outcome:
1. The {balance} keeps earning a current rate instead of dropping to whatever {institution} pays after the term.

## regular_saver_opportunity
status: draft
source: the card's own figures
figures: account_name, monthly_contribution, current_rate, product, wrapper_note
why:
1. {monthly_contribution} a month goes into {account_name} at {current_rate}%. A {product} usually pays more on regular monthly deposits.
always:
1. Compare {product}s, and check each one's monthly limit, its term and whether you can take money out early.
2. Open the one you choose and move your {monthly_contribution} standing order to it.
3. Before the term ends, check what the account will pay afterwards, and move the balance if it drops.
outcome:
1. The same {monthly_contribution} a month earns more interest.

## psa_breached, psa_approaching, psa_additional_rate, cash_isa_recommended
status: draft
source: https://www.gov.uk/apply-tax-free-interest-on-savings/how-much-is-tax-free (Personal Savings Allowance by band); https://www.gov.uk/apply-tax-free-interest-on-savings/how-you-pay-tax-on-savings-interest (tax code, Self Assessment); https://www.gov.uk/individual-savings-accounts (no tax on interest on cash in an ISA); https://www.gov.uk/individual-savings-accounts/how-isas-work (ISA allowance, tax year)
figures: annual_interest, psa_amount, breach_amount, headroom, utilisation_percent, isa_allowance, tax_band
why:
1. Your savings earn about {annual_interest} of interest a year.
2. Your Personal Savings Allowance is {psa_amount}, and interest above it is taxed.
3. {breach_amount} of your interest is above your allowance.
always:
1. Interest in a Cash ISA is tax-free and does not use your Personal Savings Allowance. You can pay up to {isa_allowance} into ISAs before {tax_year_end}.
2. Move savings you do not need day to day into a Cash ISA, starting with the account that pays the most interest.
when has_cash_isa:
3. You can add to your Cash ISA with {cash_isa}, if it takes new money.
always:
4. For interest already over the allowance, HM Revenue and Customs (HMRC) usually collects the tax through your tax code. If you file a Self Assessment return, include the interest there.
outcome:
1. Interest on money moved into an ISA is not taxed and no longer counts towards your Personal Savings Allowance.
learn:
1. The ISA allowance | /help#investment-savings

## starting_rate_unused
status: draft
source: https://www.gov.uk/apply-tax-free-interest-on-savings/how-much-is-tax-free (starting rate for savings, reduced by other income over the Personal Allowance)
figures: available, unused, annual_interest
why:
1. With your other income, up to {available} of savings interest can be tax-free under the starting rate for savings.
always:
1. The starting rate for savings is up to {starting_rate_band}. It falls by £1 for every £1 of your other income above the Personal Allowance of {personal_allowance}.
2. If you have paid tax on savings interest you did not owe, contact HM Revenue and Customs (HMRC) to claim it back. GOV.UK explains how.
outcome:
1. Up to {available} of interest is tax-free this year.

## isa_allowance_remaining, excess_cash_isa_available
status: draft
source: https://www.gov.uk/individual-savings-accounts (ISA types; no tax on interest, income or gains); https://www.gov.uk/individual-savings-accounts/how-isas-work (ISA allowance per tax year, splitting it); unused allowance does not carry over (CSJ ruling 2026-09-29); Fynla emergency fund target (`PlanConfigService`)
figures: isa_remaining, tax_year, excess_amount
why:
1. You have {isa_remaining} of your ISA allowance left for {tax_year}.
2. You hold {excess_amount} above your emergency fund target.
always:
1. Choose a Cash ISA, a Stocks and Shares ISA, or both. Interest on cash in an ISA, and income and gains on investments in one, are not taxed.
2. Pay into an ISA before {tax_year_end}. Allowance you do not use this tax year does not carry over to the next. You can split it across Cash, Stocks and Shares, Innovative Finance and Lifetime ISAs.
when has_cash_isa:
3. You can add to your Cash ISA with {cash_isa}, if it takes new money.
when has_stocks_isa:
3. You can add to your Stocks and Shares ISA with {stocks_isa}.
outcome:
1. Interest and growth on money inside an ISA are tax-free.
learn:
1. The ISA allowance | /help#investment-savings

## excess_cash_pension
status: draft
source: https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief (relief on pension payments); https://www.gov.uk/tax-on-your-private-pension/annual-allowance (Annual Allowance); Finance Act 2004 s279 normal minimum pension age (https://www.legislation.gov.uk/ukpga/2004/12/section/279)
figures: annual_allowance, pension_amount
why:
1. Your ISA allowance is used for this year, and you have savings above your emergency fund.
always:
1. A payment into a pension gets tax relief. Money in a pension cannot usually be taken until you are {normal_minimum_pension_age}.
when has_workplace_pension:
2. Ask your employer whether you can pay more into {workplace_pension}, and whether they offer salary sacrifice.
when has_personal_pension:
2. Or pay into {personal_pension}. The provider adds basic-rate relief on top of what you pay.
always:
3. Keep all your pension payments this year within your Annual Allowance of {annual_allowance}.
outcome:
1. Money that would have gone in tax goes into your pension instead.

## fscs_breach, fscs_approaching
status: draft
source: https://www.fscs.org.uk/check/check-your-money-is-protected/ (limit per person per banking licence; brands sharing a licence count as one); https://www.fscs.org.uk/making-a-claim/claims-process/temporary-high-balances/ (temporary high balances); limits from tax config (`savings.fscs_*`)
figures: institution_name, total_balance, fscs_limit, breach_amount, excess, headroom
why:
1. You hold {total_balance} with {institution_name}. The Financial Services Compensation Scheme protects up to {fscs_limit} per person at each bank.
2. {breach_amount} of it is above that limit.
always:
1. Check which of your banks share a banking licence with the scheme's protection checker. Brands that share one count as one bank for the limit.
2. Move {breach_amount} to a bank with a different licence.
3. Money held briefly after a house sale, redundancy or retirement can be protected up to {fscs_high_balance_limit} for {fscs_high_balance_months} months. Check the scheme's rules on temporary high balances if that is why the balance is high.
outcome:
1. All your savings sit within the protection limit.

## debt_rate_exceeds_savings
status: draft
source: Consumer Credit Act 1974 s94 (right to repay early, in full or in part; not loans secured on land) https://www.legislation.gov.uk/ukpga/1974/39/section/94; the card's own figures
figures: debt_rate, savings_rate, rate_difference, lender
why:
1. Your debt with {lender} costs {debt_rate}% a year, while your savings earn {savings_rate}%.
2. Each £1 you repay stops {debt_rate}% a year of interest, where the same £1 earns {savings_rate}% in savings.
always:
1. Keep your emergency fund where it is.
2. Ask {lender} for a settlement figure, and whether any early repayment charge applies. For most personal loans and credit cards you have a legal right to repay early, in full or in part.
3. Repay from savings above your emergency fund, starting with the highest-rate debt.
outcome:
1. You stop paying {debt_rate}% on the amount repaid.

## goal_off_track, goal_no_contribution, goal_underfunded, goal_deadline_approaching
status: draft
source: Fynla Goals (`/goals`); the card's own figures
figures: goal_name, required_monthly, current_monthly, shortfall, progress, months_remaining, target_amount
why:
1. '{goal_name}' needs {required_monthly} a month to reach its target on time.
2. You pay in {current_monthly} a month now.
3. '{goal_name}' is {progress}% of the way to {target_amount}, with {months_remaining} months left.
always:
1. Set up or raise a standing order of {required_monthly} a month into the account linked to '{goal_name}'.
1. Work out what you can add each month for the {months_remaining} months left, and set up a standing order for it.
2. Update the goal's monthly contribution on the Goals page.
3. If that is more than you can afford, move the goal's target date or lower its target on the Goals page.
outcome:
1. '{goal_name}' is back on track for {target_amount}.

## goal_no_linked_account
status: draft
source: Fynla Goals (`/goals`)
figures: goal_name, target_amount
why:
1. '{goal_name}' has no savings account linked, so your progress towards {target_amount} cannot be shown.
always:
1. On the Goals page, open '{goal_name}' and link the account you are saving into.
outcome:
1. Your progress on '{goal_name}' updates as the account balance grows.

## goal_wrong_account_type
status: draft
source: Fynla Goals (`/goals`); the card's own figures
figures: goal_name, account_name, reason, timeline, target_date
why:
1. '{goal_name}' is due on {target_date}, and {account_name} may not suit that timescale: {reason}.
always:
1. Check when you need the money for '{goal_name}', and whether {account_name} lets you take it out then without losing interest.
2. If it does not, move the money to an account that matches the date, and link that account to the goal on the Goals page.
outcome:
1. The money for '{goal_name}' is in an account that matches when you need it.

## goal_multi_account_rebalance
status: draft
source: Fynla Goals (`/goals`); the card's own figures
figures: goal_count, allocated, account_name, balance, shortfall
why:
1. {goal_count} goals count {allocated} against {account_name}, which holds {balance}, so {shortfall} is counted more than once.
always:
1. On the Goals page, set how much of {account_name} each goal uses, so the total is no more than {balance}.
2. Or open a separate account for one of the goals and link it.
outcome:
1. Each goal shows the money that is really set aside for it.

## life_event_cash_buffer
status: draft
source: Fynla Goals and Life Events (`/goals`); the card's own figures
figures: event_name, months_until, amount, monthly_saving
why:
1. {event_name} is expected in {months_until} months and costs {amount}.
always:
1. Set up a standing order of {monthly_saving} a month into an easy access account, kept apart from your emergency fund.
2. Add a goal for {event_name} on the Goals page and link that account, so you can see your progress.
outcome:
1. {amount} is ready when {event_name} arrives, without touching your emergency fund.

## child_no_jisa, child_no_savings
status: draft
source: https://www.gov.uk/junior-individual-savings-accounts (who can open, eligibility, annual limit, control at 16, withdrawal at 18)
figures: child_name, jisa_allowance
why:
1. {child_name} has no Junior ISA. Up to {junior_isa_allowance} a year can go into one, and interest and growth are tax-free.
always:
1. A parent or guardian with parental responsibility opens it, for a child under 18 who lives in the UK. Choose a cash or a stocks and shares Junior ISA, or both.
2. Anyone can then pay in, up to {junior_isa_allowance} a year in total, before {tax_year_end}.
3. The money belongs to {child_name}. They can manage the account from {jisa_control_age}, but cannot take money out until {jisa_withdraw_age}.
outcome:
1. Savings for {child_name} grow tax-free until they are {jisa_withdraw_age}.

## child_jisa_allowance_remaining
status: draft
source: https://www.gov.uk/junior-individual-savings-accounts (annual limit); https://www.gov.uk/junior-individual-savings-accounts/add-money-to-an-account (anyone can pay in, within the limit); the limit is per tax year and unused allowance does not carry over (CSJ ruling 2026-09-29)
figures: child_name, jisa_remaining, remaining, jisa_allowance, tax_year
why:
1. {child_name}'s Junior ISA can take {jisa_remaining} more in {tax_year}.
always:
1. Pay in before {tax_year_end}. Allowance you do not use this tax year does not carry over to the next.
2. Family and friends can pay in too, within the {junior_isa_allowance} total.
outcome:
1. More of {child_name}'s savings grow tax-free.

## child_jisa_cash_vs_ss
status: draft
source: https://www.gov.uk/junior-individual-savings-accounts/manage-an-account (change from cash to stocks and shares, or provider); https://www.gov.uk/junior-individual-savings-accounts
figures: child_name, years_to_18, balance
why:
1. {child_name} has {years_to_18} years until they turn 18, and their {balance} is in a cash Junior ISA.
always:
1. As the registered contact, you can change the account from cash to stocks and shares, or move it to another provider, by contacting the provider.
2. A child can have a cash and a stocks and shares Junior ISA at the same time, so you can move part of it.
outcome:
1. Money {child_name} will not need for {years_to_18} years can be invested for the long term, inside the same tax-free wrapper.

## child_parental_settlement
status: draft
source: Income Tax (Trading and Other Income) Act 2005 s629 (income from a parent's gift to their child taxed as the parent's, over £100 a year) https://www.legislation.gov.uk/ukpga/2005/5/section/629; https://www.gov.uk/junior-individual-savings-accounts (tax-free)
figures: child_name, annual_interest, threshold
why:
1. Money you gave {child_name} earns {annual_interest} of interest a year. Over {parental_settlement_limit} a year, interest on a parent's gift is taxed as the parent's income.
always:
1. Money in a Junior ISA is tax-free, so moving the savings into {child_name}'s Junior ISA keeps the interest out of your tax.
2. Gifts from grandparents or others are not caught by this rule.
outcome:
1. {child_name}'s interest is no longer added to your income.

## child_turning_18
status: draft
source: https://www.gov.uk/junior-individual-savings-accounts/manage-an-account (turns into an adult ISA at 18; can take money out)
figures: child_name
why:
1. {child_name} turns 18 within a year.
always:
1. At 18, {child_name}'s Junior ISA turns into an adult ISA automatically, and they can take money out.
2. Talk with {child_name} about what the money is for before then.
outcome:
1. {child_name} takes over their savings with a plan for them.

## spouse_psa_shift
status: draft
source: https://www.gov.uk/apply-tax-free-interest-on-savings/how-much-is-tax-free (Personal Savings Allowance per person, by band)
figures: user_utilisation, spouse_headroom, spouse_psa
why:
1. You use {user_utilisation}% of your Personal Savings Allowance, and your spouse has {spouse_headroom} of theirs unused.
always:
1. Each of you has your own Personal Savings Allowance. Interest on savings in your spouse's name uses theirs.
2. Moving savings into your spouse's sole name makes the money theirs, so only do it if you are both content with that.
outcome:
1. Up to {spouse_headroom} more interest a year can be tax-free across the two of you.

## spouse_isa_coordination
status: draft
source: https://www.gov.uk/individual-savings-accounts/how-isas-work (each person's own ISA allowance, tax year); https://www.gov.uk/individual-savings-accounts ("You cannot hold an ISA with someone else"); Individual Savings Account Regulations 1998 reg 4(1B)(c), one subscriber per account (https://www.legislation.gov.uk/uksi/1998/1870/regulation/4); CLAUDE.md Rule 6, joint ISAs do not exist (CSJ)
figures: user_isa_remaining, spouse_isa_remaining, combined_remaining, tax_year
why:
1. Between you, {combined_remaining} of ISA allowance is left for {tax_year}: {user_isa_remaining} yours and {spouse_isa_remaining} your spouse's.
always:
1. Each of you has your own ISA allowance of {isa_allowance} a year, and each ISA belongs to one person: ISAs cannot be held jointly.
2. Direct new savings into the ISA of whichever of you still has allowance, before {tax_year_end}.
outcome:
1. More of your household's savings earn tax-free interest.
learn:
1. The ISA allowance | /help#investment-savings
