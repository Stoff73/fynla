# How-to steps: investment actions

This file is the one source for the steps on each investment action's detail card. `ActionHowToSeeder` reads it, and only entries marked `status: approved` ever reach a user. CSJ reviews each entry and changes `draft` to `approved`, or edits it. The grammar is the tax file's (`tax.md`): `why:`, `when …:`, `always:`, `outcome:`, `learn:`, conditions, and `{placeholders}`.

**Where the cards come from.** Investment cards are the investment action definitions (`InvestmentActionDefinitionService::evaluateAgentActions`), reached through `InvestmentAgent::generateRecommendations` and typed by their key. The review and CSJ's decisions are in `docs/superpowers/specs/2026-10-06-investment-cards-review-design.md` (item 8, 2026-10-06).

**The figures.** Each card's own figures reach its steps, listed under each entry. They arrive already written, for example `{annual_fees}` as "£1,104" and `{total_fee_percent}` as "1.16". A step whose figure is missing is left out.

**Also available:** everything in `tax.md` ("What you can branch on", "What you can fill in"): for example `has_stocks_isa`, `has_gia`, `isa_with_gia_provider`, `{gia}`, `{stocks_isa}`, `{isa_allowance}`, `{tax_year_end}`.

**Not written, on purpose:**
- **Folded into one card per account (CSJ 2026-10-06):**
  - `rebalance_portfolio` and `low_diversification` are `allocation_position` (D3, per account as the page).
  - `high_total_fees`, `high_fund_fees` and `high_platform_fees` are `account_charges` (D4).
- **Disabled, Savings carries them (D1):** `emergency_fund_critical`, `emergency_fund_grow`, `switch_savings_rate`, `isa_allowance_remaining`, `surplus_to_isa`, `surplus_to_pension`, `surplus_to_bond`.
- `risk_profile_missing`: never a card. A missing risk profile blocks the module, and the module's unlock card asks for it instead.
- `goal_no_contribution`, `goal_behind_schedule`, `goal_deadline_approaching`: Investment plan page only (`InvestmentPlanService`), never a card.
- `strategy_*`: the composer's catalogue rows, never a card.

Rules for these steps:
- **Every step rests on the sources named under its heading** (Rule 23), or on the card's own figures.
- **Guidance, not advice.** The steps say how, not whether, and never name a provider or a product.
- **Speak to the user about their own investments.** A step never names Fynla; where something acts, it is Fyn or a named page.
- **No tax figure is typed in** (Rule 2): rates and allowances come from the card's figures or tax config.
- **No banned words** (Rule 9): never "harvest".

## allocation_position
status: approved
source: the account's rebalancing panel (`AccountDriftService`: the risk level that applies to the account, its own or the user's, and the account's rebalancing threshold; `DriftAnalyzer` places money in funds with no recorded mix where it closes the gaps first); https://www.gov.uk/tax-sell-shares (selling shares can make a gain liable to Capital Gains Tax); https://www.gov.uk/individual-savings-accounts (no tax on gains inside an ISA); the card's own figures
figures: account_name, current_text, asset_label, target_percent, risk_label, allocation_summary, unrecorded_percent, has_unrecorded, is_isa
why:
1. {account_name} is measured against the {risk_label} risk level: {allocation_summary}. That is outside the account's rebalancing threshold.
why when has_unrecorded:
2. {unrecorded_percent}% of it is in funds whose mix of shares, bonds and other assets is not recorded, so these figures assume that money sits where it closes the gaps first. The real gap may be larger.
when has_unrecorded:
1. Add what each of those funds holds to its holding on {account_name}, from the fund's factsheet, so the figures are exact.
always:
2. Check that the {risk_label} risk level still fits you. If it does not, change it on {account_name}, and its target changes with it.
3. If it fits, moving money from the asset classes above their target to those below it brings the account back within its threshold. Money you pay in can go to the classes below target first, which needs no selling.
when not is_isa:
4. Selling in a General Investment Account can make a gain liable to Capital Gains Tax. Check the gain on anything you would sell before you sell it.
when is_isa:
4. Selling and buying inside an ISA makes no Capital Gains Tax.
always:
5. Ask the provider what it charges to buy and sell.
outcome:
1. {account_name} is back within its rebalancing threshold for the {risk_label} risk level.

## account_charges
status: approved
source: the account page's recorded charges (`FeeAnalyzer::recordedCharges`: platform, adviser and fund charges as recorded on the account and its holdings); https://www.gov.uk/individual-savings-accounts/transferring-your-isa (transfer an ISA to keep it tax-free; do not withdraw); https://www.gov.uk/tax-sell-shares (selling shares can make a gain liable to Capital Gains Tax); the card's own figures
figures: account_name, annual_fees, total_fee_percent, charges_list, platform_fee_percent, weighted_ocf, has_adviser_fee, is_isa
why:
1. {account_name} costs {annual_fees} a year in charges, {total_fee_percent}% of its value: {charges_list}.
always:
1. Check these charges against your latest statement from the provider, and correct them on {account_name} if they differ.
when has_adviser_fee:
2. Ask your adviser what their ongoing fee pays for, and whether you still use that service.
always:
3. Look up each fund's ongoing charge on its factsheet. A fund with similar holdings and a lower charge costs less every year.
4. Compare what other providers would charge to hold the same investments.
when is_isa:
5. If you move to another provider, ask the new provider to transfer the ISA. Taking the money out yourself loses its ISA tax status.
when not is_isa:
5. If you move to another provider, ask whether it can take your holdings as they are, without selling them. Selling can make a gain liable to Capital Gains Tax.
outcome:
1. You know what {account_name} costs you each year, and whether the same investments can be held for less.

## tax_loss_harvesting
status: approved
source: https://www.gov.uk/capital-gains-tax/losses (deduct losses from gains in the same tax year first; report a loss within 4 years of the end of the tax year of the sale); HMRC Capital Gains Manual CG21500 (losses of the year are set against that year's gains before the annual exempt amount; https://www.gov.uk/hmrc-internal-manuals/capital-gains-manual/cg21500); TCGA 1992 s16(2A) (a loss counts once notified; https://www.legislation.gov.uk/ukpga/1992/12/section/16); Taxes Management Act 1970 s43 (four years); TCGA 1992 s106A(3) and (5), the 30-day rule and "same capacity" (https://www.legislation.gov.uk/ukpga/1992/12/section/106A); HMRC CG51560; `ChargeableGains` (General Investment Accounts only, at the user's share); `capital_gains_tax.annual_exempt_amount` in tax config; the card's own figures
figures: holdings_count, holdings_word, holdings_verb, total_losses, annual_exempt_amount
why:
1. {holdings_count} {holdings_word} in your General Investment Account {holdings_verb} worth {total_losses} less than you paid.
always:
1. Add up the gains you have made, or expect to make, this tax year from selling investments, property or other assets.
2. A loss is set against your gains in the same tax year first, before the {annual_exempt_amount} tax-free allowance. If your gains this year are already under the allowance, selling now uses the loss for no saving.
3. Any loss left over carries forward to later tax years, if you report it to HM Revenue and Customs (HMRC) within four years of the end of the tax year you sold in.
4. If you sell and buy the same holding back within 30 days in your General Investment Account, the sale is matched with the new purchase and the loss is not available. Buying it back inside your ISA is not matched, as the ISA holds it in a different capacity.
outcome:
1. The loss is on record, ready to reduce the Capital Gains Tax on your gains this year or in a later one.

## use_isa_allowance
status: approved
source: https://www.gov.uk/individual-savings-accounts/how-isas-work (ISA allowance per tax year); https://www.gov.uk/individual-savings-accounts (no tax on income or gains inside an ISA); unused allowance does not carry over (CSJ ruling 2026-09-29); https://www.gov.uk/tax-sell-shares (selling shares can make a gain liable to Capital Gains Tax); this card shows only when the General Investment Account holds no gains to shelter, otherwise the Tax plan's Bed & ISA carries the move (CSJ 2026-10-06, D2); `ISATracker::usedThisTaxYear` (allowance left); the card's own figures
figures: isa_remaining, gia_value
why:
1. You have {isa_remaining} of ISA allowance left this tax year, and {gia_value} in your General Investment Account, where dividends and gains are taxable.
always:
1. Investments you already hold cannot be moved straight into an ISA. They are sold in the General Investment Account and bought back inside the ISA, which providers often call a "Bed and ISA".
when isa_with_gia_provider:
2. Ask {gia} for a "Bed and ISA" from your General Investment Account into your stocks and shares ISA with them.
when has_stocks_isa and not isa_with_gia_provider:
2. Your General Investment Account is with {gia} and your stocks and shares ISA is with {stocks_isa}. Sell with {gia}, move the cash and buy the investments in your ISA with {stocks_isa}.
always:
3. Check the gain on anything you would sell first: selling at a gain can make Capital Gains Tax due.
4. Ask the provider what it charges, and how long your money is out of the market between the sale and the purchase.
5. Move up to {isa_remaining} by {tax_year_end}. Allowance you do not use this tax year does not carry over to the next.
outcome:
1. Up to {isa_remaining} more of your investments is held where its dividends and gains are free of tax.

## open_isa
status: approved
source: https://www.gov.uk/individual-savings-accounts (stocks and shares ISAs; no tax on income or gains); https://www.gov.uk/individual-savings-accounts/how-isas-work (ISA allowance per tax year, across all your ISAs); unused allowance does not carry over (CSJ ruling 2026-09-29); https://www.gov.uk/tax-sell-shares (selling shares can make a gain liable to Capital Gains Tax); this card shows only when the General Investment Account holds no gains to shelter, otherwise the Tax plan's Bed & ISA carries the move (CSJ 2026-10-06, D2); the card's own figures
figures: isa_allowance
why:
1. Your investments are in a General Investment Account, where dividends and gains are taxable, and you have no stocks and shares ISA recorded.
always:
1. Open a stocks and shares ISA. If {gia} offers one, holding both with the same provider makes moving investments across simpler.
2. Each tax year you can put up to {isa_allowance} into your ISAs in total.
3. Investments you already hold are sold in the General Investment Account and bought back inside the ISA, which providers often call a "Bed and ISA". Check the gain on anything you would sell first: selling at a gain can make Capital Gains Tax due.
4. Use the allowance by {tax_year_end}. Allowance you do not use this tax year does not carry over to the next.
5. Add the new ISA on the Investment page.
outcome:
1. New money and the investments you move are held where their dividends and gains are free of tax.

## consider_bonds
status: approved
source: Income Tax (Trading and Other Income) Act 2005 s461 (gains on life insurance policies, including investment bonds, are charged to income tax; https://www.legislation.gov.uk/ukpga/2005/5/section/461), s484 (chargeable events: the bond ending, cashing it in in full or in part, assigning it for value, the death that ends it), s507 (each year 5% of what was paid in can be withdrawn with the tax deferred; unused amounts carry forward, up to the full amount paid in; https://www.legislation.gov.uk/ukpga/2005/5/section/507), s530 (a gain on a UK policy is treated as having had basic-rate tax paid on it; https://www.legislation.gov.uk/ukpga/2005/5/section/530); Income Tax Act 2007 s535 (top-slicing relief; https://www.legislation.gov.uk/ukpga/2007/3/section/535); TCGA 1992 s210 (not a chargeable disposal for the original owner); CSJ 2026-10-06 (onshore and offshore bonds, top-slicing relief, the cumulative 5%); the card's own figures
figures: gia_value
why:
1. You hold {gia_value} in your General Investment Account, where dividends and gains are taxed each year as they arise or when you sell.
always:
1. An investment bond is another way to hold investments. Gains inside it are taxed as income, not as capital gains, when the bond ends, when you cash in all or part of it, when it is assigned, or on the death that ends it.
2. Each year you can take back up to 5% of what you paid in, with no tax at the time. Any 5% you do not take builds up for later years, until you have taken back the full amount you paid in. The tax is deferred, not removed: those withdrawals count in the gain when the bond ends.
3. An onshore bond, from a UK insurer, pays tax inside the fund as it grows. When the bond ends, the gain is treated as having had basic-rate tax paid on it already, so only tax above the basic rate is left to pay.
4. An offshore bond, from an insurer outside the UK, grows with no UK tax taken along the way. All the tax is deferred, and the whole gain is taxed at your income tax rates when the bond ends, with no basic-rate tax treated as paid.
5. Top-slicing relief can reduce the tax on a bond gain. The gain is divided by the number of full years the bond has run, and that slice is used to work out how much of the gain falls into the higher rates, so a gain built up over many years is not all taxed as if it arose in one.
6. Moving money from your General Investment Account into a bond means selling there first. Check the gain on anything you would sell: selling at a gain can make Capital Gains Tax due.
7. Speak to a financial adviser before buying a bond. Which kind suits you, the charges, and the tax when it ends depend on your circumstances.
outcome:
1. You know how an onshore or an offshore bond would be taxed, compared with your General Investment Account.

## no_holdings
status: approved
source: the account page (`InvestmentAccount` holdings); charges, mix and tax are read from the holdings (`FeeAnalyzer::recordedCharges`, `AccountDriftService`, `ChargeableGains`)
figures:
why:
1. Your investment accounts have no holdings recorded, so their charges, their mix and the tax on them cannot be checked.
always:
1. Open each account on the Investment page and add the funds or shares it holds, with how much each is worth today.
2. Add what you paid for each holding, from your provider's statement, so gains and losses can be worked out.
3. Add each fund's ongoing charge from its factsheet, so the account's charges are complete.
outcome:
1. Your investment cards and the Investment page use what you actually hold.
