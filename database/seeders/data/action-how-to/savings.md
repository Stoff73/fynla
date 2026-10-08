# How-to steps: savings actions

This file is the one source for the steps on each savings action's detail card. `ActionHowToSeeder` reads it, and only entries marked `status: approved` ever reach a user. CSJ reviews each entry and changes `draft` to `approved`, or edits it. The grammar is the tax file's (`tax.md`): `why:`, `when …:`, `always:`, `outcome:`, `learn:`, conditions, and `{placeholders}`.

**One entry, several keys.** A heading can name several keys (`## rate_below_market, rate_poor`) when they are the same action at a different urgency or scope. The entry is written once and each card fills in its own figures.

**The figures.** Each card's own figures reach its steps: the ones its title and description were written from (`SavingsActionDefinitionService`, `buildRecommendation`), listed under each entry. They arrive already written, for example `{balance}` as "£4,500" and `{account_rate}` as "1.10" (add the % in the step). A step whose figure is missing is left out.

**Also available:** everything in `tax.md` ("What you can branch on", "What you can fill in"), plus the savings figures from tax config: `{psa}` (this user's Personal Savings Allowance), `{psa_basic}`, `{psa_higher}`, `{psa_additional}`, `{starting_rate_band}`, `{fscs_limit}`, `{fscs_joint_limit}`, `{fscs_high_balance_limit}`, `{fscs_high_balance_months}`, `{parental_settlement_limit}`, `{dividend_allowance}`, `{cgt_allowance}`; `has_bond` and `{bond}` (an onshore or offshore bond recorded on the Investment page); and the household: `isa_full` and `{isa_left}` (the user's own ISA room this tax year, one sum for cash and stocks and shares: `TaxStrategyMath::estimateIsaSubscriptionsThisYear`), `has_spouse` and `{spouse_start}`, `has_children` and `{children}` (under 18: `DependantsReach::minorChildrenOf`).

**Not written, on purpose (the card still shows):**
- `strategy_build_emergency_fund`, `strategy_move_to_high_interest`, `strategy_regular_savings_habit`: internal fallback types (`SavingsRecommendationAdapter`), never a card.
- `missing_date_of_birth`, `missing_income`, `missing_expenditure`, `missing_employment_status`: data prompts; the card's "Add it now" opens Fyn's form.
- `cash_isa_not_needed`, `goal_nearly_achieved`, `psa_headroom_available`: information with nothing to do.

Rules for these steps:
- **Every step rests on the sources named under its heading** (Rule 23), or on the card's own figures.
- **Guidance, not advice.** The steps say how, not whether.
- **Speak to the user about their own money** (CSJ 2026-09-29). The target, the plan and the goal are theirs: "your target", never "Fynla's". A step never names Fynla; where something acts, it is Fyn or a named page.
- **Spell out** the Financial Services Compensation Scheme and the Personal Savings Allowance (Rule 9). ISA is fine.

## emergency_fund_critical, emergency_fund_low, emergency_fund_building
status: approved
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
status: approved
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
status: approved
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
status: approved
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
status: approved
source: Fynla emergency fund target (`PlanConfigService::emergency_fund.target_months`); https://www.gov.uk/individual-savings-accounts (no tax on interest, income or gains; one owner per ISA); https://www.gov.uk/individual-savings-accounts/how-isas-work (allowance per tax year); https://www.gov.uk/apply-tax-free-interest-on-savings/how-much-is-tax-free (Personal Savings Allowance per person); https://www.gov.uk/junior-individual-savings-accounts (limit, tax-free, money belongs to the child, withdrawal at 18); ISA room from `TaxStrategyMath::estimateIsaSubscriptionsThisYear`
figures: runway_months, excess_months, excess_amount; household: isa_full, isa_left, has_spouse, has_children, children
why:
1. Your emergency fund covers {runway_months} months of spending, {excess_months} months more than your target: {excess_amount}.
always:
1. Leave your target amount in easy access, and decide what the {excess_amount} above it is for.
when not isa_full:
2. You have {isa_left} of your ISA allowance left before {tax_year_end}. Interest, income and gains inside an ISA are not taxed.
when not isa_full and has_cash_isa:
3. Your Cash ISA with {cash_isa} can take some of it, if it takes new money.
when isa_full:
2. Your ISA allowance for this tax year is used. A new allowance starts in the tax year after {tax_year_end}.
when has_spouse:
4. {spouse_start} has their own ISA allowance and Personal Savings Allowance. Money you give them becomes theirs, so only do it if you are both content with that.
when has_children:
5. A Junior ISA can take up to {junior_isa_allowance} a year for each child, and it is tax-free. For {children}, the money becomes theirs, and they cannot take it out until {jisa_withdraw_age}.
outcome:
1. Your emergency fund stays at its target, and the money above it goes where it earns more or is sheltered from tax.
learn:
1. The ISA allowance | /help#investment-savings

## rate_below_market, rate_poor, zero_rate_account
status: approved
source: https://www.gov.uk/individual-savings-accounts/transferring-your-isa (transfer, do not withdraw); market rates: MoneySavingExpert best-buy tables, refreshed quarterly (`MarketRateRefreshService`, F20 CSJ 2026-09-08), with the provider and date of the row used (`RateComparator`); the line is left out when no stored rate backs it
figures: account_name, account_rate, market_rate, market_label, market_provider, market_as_of, rate_gap, potential_gain, institution, balance, is_isa
why:
1. {account_name} pays {account_rate}%. The best {market_label} rate in MoneySavingExpert's best-buy tables on {market_as_of} was {market_rate}%, from {market_provider}.
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
status: approved
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
status: approved
source: the card's own figures; {product} is "regular saver ISA" when the account is an ISA and "regular saver account" otherwise, with {wrapper_note} to keep an ISA's interest tax-free (`evaluateRegularSaverOpportunity`)
figures: account_name, monthly_contribution, current_rate, product, is_isa
why:
1. {monthly_contribution} a month goes into {account_name} at {current_rate}%. A {product} usually pays more on regular monthly deposits.
why when is_isa:
2. {account_name} is an ISA, so choose a regular saver ISA and keep the money inside it, so the interest stays tax-free.
always:
1. Compare {product}s, and check each one's monthly limit, its term and whether you can take money out early.
2. Open the one you choose and move your {monthly_contribution} standing order to it.
3. Before the term ends, check what the account will pay afterwards, and move the balance if it drops.
outcome:
1. The same {monthly_contribution} a month earns more interest.

## psa_breached, psa_approaching, psa_additional_rate, cash_isa_recommended
status: approved
source: https://www.gov.uk/apply-tax-free-interest-on-savings/how-much-is-tax-free (Personal Savings Allowance by band, per person); https://www.gov.uk/apply-tax-free-interest-on-savings/how-you-pay-tax-on-savings-interest (tax code, Self Assessment); https://www.gov.uk/individual-savings-accounts (no tax on interest on cash in an ISA; one owner per ISA); https://www.gov.uk/individual-savings-accounts/how-isas-work (ISA allowance, tax year); https://www.gov.uk/junior-individual-savings-accounts (tax-free); ITTOIA 2005 s629 (a parent's gift to a child: interest over £100 taxed as the parent's) https://www.legislation.gov.uk/ukpga/2005/5/section/629; ISA room from `TaxStrategyMath::estimateIsaSubscriptionsThisYear`
figures: annual_interest, psa_amount, breach_amount, headroom, utilisation_percent, isa_allowance, tax_band
why:
1. Your savings earn about {annual_interest} of interest a year.
2. Your Personal Savings Allowance is {psa_amount}, and interest above it is taxed.
3. {breach_amount} of your interest is above your allowance.
when not isa_full:
1. You have {isa_left} of your ISA allowance left before {tax_year_end}. Interest in a Cash ISA is tax-free and does not use your Personal Savings Allowance.
2. Move savings you do not need day to day into a Cash ISA, starting with the account that pays the most interest.
when not isa_full and has_cash_isa:
3. You can add to your Cash ISA with {cash_isa}, if it takes new money.
when isa_full:
1. Your ISA allowance for this tax year is used, so a Cash ISA cannot take more until the tax year after {tax_year_end}.
when has_spouse:
4. {spouse_start} has their own Personal Savings Allowance and ISA allowance. Interest on savings in their name uses their allowances, not yours, but the money becomes theirs.
when has_children:
5. A Junior ISA for {children} is tax-free. Savings you give them outside one still count as your income once their interest is over {parental_settlement_limit} a year.
always:
6. For interest already over the allowance, HM Revenue and Customs (HMRC) usually collects the tax through your tax code. If you file a Self Assessment return, include the interest there.
outcome:
1. Interest on money moved into an ISA is not taxed and no longer counts towards your Personal Savings Allowance.
learn:
1. The ISA allowance | /help#investment-savings

## starting_rate_unused
status: approved
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
status: approved
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
status: approved
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

## excess_cash_bond
status: approved
source: HMRC Insurance Policyholder Taxation Manual IPTM2005 (an investment bond is "generally a unit-linked, single premium whole of life or endowment policy … An investment rather than insurance in the general sense") https://www.gov.uk/hmrc-internal-manuals/insurance-policyholder-taxation-manual/iptm2005; HMRC helpsheet HS320 Gains on UK life insurance policies (2026) (gains are taxable as income when a chargeable event happens: a full or part surrender, maturity, death or assignment; single premium policies, "although additional premiums may be allowed"; part surrenders are set against "unused one twentieth of the premiums paid in the year and each previous year", up to 100%; "Tax at basic rate may be treated as paid on the gain in which case further tax will only be due from higher, or additional rate, taxpayers"; top slicing relief) https://www.gov.uk/government/publications/gains-on-uk-life-insurance-policies-hs320-self-assessment-helpsheet/hs320-gains-on-uk-life-insurance-policies-2026; HMRC helpsheet HS321 Gains on foreign life insurance policies (2026) ("unlike gains on UK policies, do not attract a non-repayable basic rate tax credit") https://www.gov.uk/government/publications/gains-on-foreign-life-insurance-policies-hs321-self-assessment-helpsheet/hs321-gains-on-foreign-life-insurance-policies-2026; https://www.fscs.org.uk/what-we-cover/investments/ ("their value can go down as well as up"); https://www.gov.uk/individual-savings-accounts (no tax on interest, income or gains in an ISA); ISA room from `TaxStrategyMath::estimateIsaSubscriptionsThisYear`; the card's own figures (`evaluateCashDragRisk`)
figures: surplus_amount, total_savings
why:
1. You hold {total_savings} in cash, {surplus_amount} of it above your emergency fund target.
why when isa_full:
2. Your ISA allowance is used for this year.
why when not isa_full:
2. You still have {isa_left} of ISA allowance this year. Interest, income and gains inside an ISA are not taxed.
always:
1. An investment bond is a single premium life insurance policy that holds investments. Its value can go down as well as up.
2. Gains are not taxed while they stay in the bond. They are taxed as income when you cash the bond in, in full or in part, or when it ends.
3. Each insurance year you can take out up to one twentieth (5%) of what you paid in without a gain arising at the time, up to the whole amount you paid in. Any part you do not take carries forward.
4. With a UK bond, tax at the basic rate can be treated as already paid on a gain, so only higher and additional rate taxpayers pay more. A bond from a provider outside the UK carries no such credit.
5. If a gain would take your income into a higher tax band, top slicing relief can reduce the tax on it.
when has_bond:
6. You can add to your bond with {bond}, if it takes further payments.
when not has_bond:
6. Before you buy, ask the provider for the bond's charges.
outcome:
1. Growth inside the bond is not taxed until you cash it in, or take out more than the yearly one twentieth.

## excess_cash_gia
status: approved
source: https://www.gov.uk/tax-sell-shares (Capital Gains Tax on shares and fund units not in an ISA, when total gains are above the allowance); https://www.gov.uk/capital-gains-tax/allowances (the tax-free allowance, "called the Annual Exempt Amount"); https://www.gov.uk/tax-on-dividends (dividend allowance; "You do not pay tax on dividends from shares in an ISA"); Income Tax Act 2007 s13A (dividend nil rate) https://www.legislation.gov.uk/ukpga/2007/3/section/13A; Taxation of Chargeable Gains Act 1992 s1K (annual exempt amount) https://www.legislation.gov.uk/ukpga/1992/12/section/1K; https://www.fscs.org.uk/what-we-cover/investments/ (compensation when the provider has gone out of business; no claims "for poor investment performance"); allowances from tax config (`dividend_tax.allowance`, `capital_gains_tax.annual_exempt_amount`); ISA room from `TaxStrategyMath::estimateIsaSubscriptionsThisYear`; the card's own figures (`evaluateSurplusAboveEmergencyFund`)
figures: surplus_amount, target_amount
why:
1. You hold {surplus_amount} in cash above your emergency fund target of {target_amount}.
why when isa_full:
2. Your ISA allowance is used for this year.
why when not isa_full:
2. You still have {isa_left} of ISA allowance this year. Dividends and gains inside an ISA are not taxed.
always:
1. A General Investment Account holds shares and funds outside an ISA or pension, so the dividends and gains they make can be taxed.
when has_gia:
2. You can add to your General Investment Account with {gia}.
when not has_gia:
2. Choose a provider and compare its charges before you open one.
always:
3. Dividends above your {dividend_allowance} dividend allowance are taxed each year.
4. When you sell, gains above your {cgt_allowance} Capital Gains Tax allowance for the tax year are taxed.
5. The value of investments can go down as well as up. The Financial Services Compensation Scheme can pay compensation if the firm holding your investments goes out of business, not if their value falls.
outcome:
1. Money above your emergency fund is invested rather than held as cash.

## fscs_breach, fscs_approaching
status: approved
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
status: approved
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

## offset_mortgage_better
status: approved
source: HMRC Business Income Manual BIM45695 (offset accounts combine loans, savings and current accounts; "Interest is computed on the net borrowing from the bank") https://www.gov.uk/hmrc-internal-manuals/business-income-manual/bim45695; Income Tax (Trading and Other Income) Act 2005 s369 ("Income tax is charged on interest") https://www.legislation.gov.uk/ukpga/2005/5/section/369; FCA Handbook MCOB 12.3.1R (an early repayment charge must be able to be expressed as a cash value and be a reasonable pre-estimate of the lender's costs) https://www.handbook.fca.org.uk/handbook/MCOB/12/3.html; MCOB 7.5.3R(4)(d) (the mortgage statement shows "the early repayment charge that applies, expressed as a monetary amount") https://www.handbook.fca.org.uk/handbook/MCOB/7/5.html; the card's own figures (`evaluateMortgageRateComparison`)
figures: mortgage_rate, average_savings_rate, surplus_amount
why:
1. Your mortgage costs {mortgage_rate}% a year. Your savings earn {average_savings_rate}% on average, and interest on savings can be taxed.
2. You hold {surplus_amount} in savings above your emergency fund target.
always:
1. Keep your emergency fund where it is.
2. Ask your mortgage lender whether they offer an offset mortgage. With one, your savings sit in an account linked to the mortgage, and interest is charged on the mortgage less those savings.
3. Check your latest mortgage statement for the early repayment charge that applies. It is shown as a cash amount. Ask your lender whether moving to an offset mortgage before your current deal ends would trigger it.
4. If you keep your current mortgage, ask your lender how much you can overpay each year without an early repayment charge, and overpay from savings above your emergency fund.
outcome:
1. Savings above your emergency fund cut the interest you pay at {mortgage_rate}%, where in savings they earn {average_savings_rate}%.

## goal_off_track, goal_no_contribution, goal_underfunded, goal_deadline_approaching
status: approved
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
status: approved
source: Fynla Goals (`/goals`)
figures: goal_name, target_amount
why:
1. '{goal_name}' has no savings account linked, so your progress towards {target_amount} cannot be shown.
always:
1. On the Goals page, open '{goal_name}' and link the account you are saving into.
outcome:
1. Your progress on '{goal_name}' updates as the account balance grows.

## goal_wrong_account_type
status: approved
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
status: approved
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
status: approved
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
status: approved
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
status: approved
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
status: approved
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
status: approved
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
status: approved
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
status: approved
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
status: approved
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
