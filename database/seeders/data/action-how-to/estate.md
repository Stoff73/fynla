# How-to steps: estate actions

This file is the one source for the steps on each estate action's detail card. `ActionHowToSeeder` reads it, and only entries marked `status: approved` ever reach a user. CSJ reviews each entry and changes `draft` to `approved`, or edits it. The grammar is the tax file's (`tax.md`): `why:`, `when …:`, `always:`, `outcome:`, `learn:`, conditions, and `{placeholders}`.

**Where the cards come from.** Estate cards are the estate action definitions (`EstateActionDefinitionService::evaluateActions`), typed by their key. The review and CSJ's decisions D1 to D6 are in `docs/superpowers/specs/2026-10-06-estate-cards-review-design.md` (item 9, CSJ 2026-10-07).

**The figures.** Each card's own figures reach its steps, already written (for example `{iht_liability}` as "£349,112"); `true`/`false` figures are conditions. A step whose figure is missing is left out.

**Where the law applies (D6).** The will, intestacy and Lasting Power of Attorney steps are the law of England and Wales and say so; no country of residence is recorded yet.

**Not written, on purpose:** `strategy_*` (the composer's catalogue rows, never a card); `policy_not_in_trust`, `iht_exceeds_nrb`, `no_lpa_health`, `trust_review_due`, `beneficiary_review` (disabled, replaced by the cards below; the life policy trust card is Protection's, with its approved how-to in `protection.md`).

Rules for these steps:
- **Every step rests on the sources named under its heading** (Rule 23), or on the card's own figures. Rates and allowances come from the tax configuration through the card's figures, never typed (Rule 2).
- **Guidance, not advice.** The steps say how, not whether, and never name a firm or a product.
- **Speak to the user about their own estate and their own family.** A step never names Fynla.

## no_will
status: draft
source: Administration of Estates Act 1925 s46, extends to England and Wales (an intestate's estate is distributed under the intestacy rules; https://www.legislation.gov.uk/ukpga/Geo5/15-16/23/section/46); Wills Act 1837 s9 (a will must be in writing, signed by the testator in the presence of two witnesses present at the same time, who each sign or acknowledge; https://www.legislation.gov.uk/ukpga/Will4and1Vict/7/26/section/9), s15 (a gift to a witness, or to a witness's spouse or civil partner, is void; https://www.legislation.gov.uk/ukpga/Will4and1Vict/7/26/section/15), s18(1) (marriage revokes a will; https://www.legislation.gov.uk/ukpga/Will4and1Vict/7/26/section/18); Children Act 1989 s5(3) and (5) (a parent with parental responsibility may appoint a guardian in writing, dated and signed, including by will; https://www.legislation.gov.uk/ukpga/1989/41/section/5); Fynla Will Builder and Estate page
figures: none
why:
1. You have no will recorded. In England and Wales, without a will your estate passes under the intestacy rules, which may not leave it to the people you would choose.
always:
1. Decide who should receive your estate, and who your executors will be: the people who deal with it after you die.
when has_children:
2. Name guardians for {children} in your will, so you choose who would care for them.
always:
3. In England and Wales a will must be in writing and signed by you in front of two witnesses who are both there at the same time, and who then sign it.
4. Do not ask anyone who receives something in the will, or their husband, wife or civil partner, to be a witness: what the will leaves them would be lost.
when not has_spouse:
5. Getting married cancels a will made before the marriage, so make a new one if you marry.
always:
6. Once it is signed and witnessed, record it on the Estate page.
outcome:
1. Your estate goes to the people you choose, and your executors know your wishes.
learn:
1. When to make a will | /learn/when-should-i-make-a-will

## no_lpa
status: draft
source: Mental Capacity Act 2005 s9(1) (an LPA can cover personal welfare, and property and affairs), s9(2) (an LPA is not created unless made and registered under Schedule 1, by someone 18 or over with capacity; https://www.legislation.gov.uk/ukpga/2005/9/section/9), s68(4) (extends to England and Wales only), Schedule 1 para 4 (registration is applied for to the Public Guardian; https://www.legislation.gov.uk/ukpga/2005/9/schedule/1); https://www.gov.uk/power-of-attorney (two types, health and welfare and property and financial affairs; must be registered with the Office of the Public Guardian before it can be used; 18 or over with mental capacity; a fee to register); `LpaService::markAsRegistered`; the card's own figures
figures: missing_text, missing_financial, missing_health, has_unregistered
why:
1. You have no registered {missing_text} Lasting Power of Attorney. In England and Wales one only exists once it is registered.
why when has_unregistered:
2. One you have recorded is not marked as registered.
always:
1. A Lasting Power of Attorney lets people you choose, your attorneys, make decisions for you if you cannot. There are two kinds, made separately: one for property and financial affairs, one for health and welfare.
2. You can make one while you are 18 or over and able to make your own decisions.
when missing_financial:
3. Make one for your property and financial affairs: your money, your home and your bills.
when missing_health:
4. Make one for your health and welfare: your care, your treatment and where you live.
always:
5. Register each one with the Office of the Public Guardian. It cannot be used until it is registered, and there is a fee to register.
when has_unregistered:
6. If one you recorded is already registered, open it on the Power of Attorney page and mark it as registered.
always:
7. Once registered, record each one on the Power of Attorney page.
outcome:
1. People you trust can act for you if you cannot.
learn:
1. What is a Lasting Power of Attorney | /learn/what-is-an-lpa

## iht_position
status: draft
source: `IHTCalculationService` (the tax, estate and allowances on the Estate page) and the Estate plan page's steps (`EstateAgent::generateRecommendations`, item 9 D1); Inheritance Tax Act 1984 Schedule 1A (the reduced rate when the charity test is met; https://www.legislation.gov.uk/ukpga/1984/51/schedule/1A), s19 (annual exemption, last year's unused amount added once; https://www.legislation.gov.uk/ukpga/1984/51/section/19), s21 (gifts out of income that leave the usual standard of living; https://www.legislation.gov.uk/ukpga/1984/51/section/21), s3A and s7 (gifts to people leave the estate after seven years), s226(1) (tax due six months after the end of the month of death), s227 (tax on land and buildings in ten yearly instalments), s64 and s66 (the trust's ten-year charge); HMRC IHTM14512 (a gift carries tax of its own only above the nil rate band; https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm14512), IHTM20012 (a policy in trust pays out outside the estate); Consumer Insurance (Disclosure and Representations) Act 2012 s2 and s4; the card's own figures
figures: iht_liability, estate_text, when_text, when_start, net_estate, allowances, rate_percent, has_charity_step, charity_gift, charity_saving, reduced_rate_percent, charity_threshold_percent, has_payment_gap, payment_gap, has_cover_in_trust, cover_in_trust, annual_exemption, annual_saving, has_cover_gap, cover_needed, has_gift_band, gift_band, gift_band_saving, has_trust_step, clt_rate_percent, periodic_max_percent
why:
1. {when_start}, {iht_liability} of Inheritance Tax would be due: {net_estate} less allowances of {allowances}, at {rate_percent}%.
why when has_payment_gap:
2. Your cash and savings are {payment_gap} short of the tax, which is due six months after the end of the month of death. Tax on land and buildings can be paid in ten yearly instalments.
why when has_cover_in_trust:
3. You have {cover_in_trust} of life cover in trust. It pays out outside your estate and can be used to pay the tax.
always:
1. Check the figures behind the tax on the Estate page: your assets, your debts and the allowances your estate uses.
when has_charity_step:
2. Leaving {charity_gift} more to charity in your will takes your charity gifts to {charity_threshold_percent}% of the estate the test measures, so the rate falls to {reduced_rate_percent}% and the tax falls by {charity_saving}.
always:
3. Each tax year you can give away {annual_exemption} free of Inheritance Tax at once, and last year's {annual_exemption} too if you did not use it. Each year's gifts save {annual_saving} of tax.
4. Regular gifts out of your income are free of Inheritance Tax at once, as long as you can keep up your usual standard of living. Keep a record of your income, your spending and each gift.
when has_gift_band:
5. Larger gifts to people of up to {gift_band} carry no tax of their own even if you die within seven years, and once you survive seven years they save up to {gift_band_saving}. Only give what you will not need: a gift you keep using is not a gift for Inheritance Tax.
when has_cover_gap:
6. Whole of life cover of {cover_needed} written in trust would pay the tax without selling anything. Answer every health and lifestyle question fully and accurately.
when has_trust_step:
7. A gift into a trust leaves your estate while trustees keep control, but is taxed at {clt_rate_percent}% now on the part above the nil rate band available, and the trust pays up to {periodic_max_percent}% every ten years. Setting one up needs a solicitor.
always:
8. The Estate plan page shows each step with its working.
outcome:
1. Your family keeps more of your estate, or has the money to pay the tax without selling your home.
learn:
1. How Inheritance Tax is worked out | /help#estate

## gifts_pet_window
status: draft
source: Inheritance Tax Act 1984 s3A(1A) (a gift to an individual is a potentially exempt transfer; a gift into a discretionary trust is not; https://www.legislation.gov.uk/ukpga/1984/51/section/3A), s7(4) (taper relief: tax on a gift made three to four years before death is 80% of the full tax, falling to 20% at six to seven years; https://www.legislation.gov.uk/ukpga/1984/51/section/7), s19, s20, s22 (exempt gifts never count); HMRC IHTM14512 (a gift carries tax of its own only above the nil rate band, after earlier gifts; CSJ 2026-10-07), IHTM14503 (gifts of the seven years before death are added to the death estate and use its band; https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm14503); https://www.gov.uk/inheritance-tax/gifts (taper relief only applies above the threshold; the person who received the gift pays its tax); `FailedGiftTaxCalculator`; the card's own figures
figures: gift_count, gifts_text, gift_total, band_used, gift_tax, has_gift_tax, has_trust_gift, next_clear_date
why:
1. You gave {gifts_text} totalling {gift_total} in the last seven years. If you died today they would use {band_used} of your nil rate band, leaving less of it for the rest of your estate.
why when has_gift_tax:
2. Together they are above the nil rate band, so {gift_tax} of tax would fall on the gifts themselves, paid by the people who received them.
always:
1. Keep a record of each gift: the date, who received it and what it was worth. Your executors will need it.
2. Each gift drops out of the calculation seven years after you made it. The first drops out on {next_clear_date}.
when has_gift_tax:
3. Tax on a gift made three to seven years before death is reduced by taper relief: the longer ago the gift, the less tax.
when has_trust_gift:
4. A gift into a trust was taxed when you made it on any part above the nil rate band, and more may be due if you die within seven years of it.
outcome:
1. Your executors have the record they need, and you know when each gift leaves the calculation.
learn:
1. How Inheritance Tax is worked out | /help#estate

## trust_anniversary_due
status: draft
source: Inheritance Tax Act 1984 s64(1) (a ten-year charge on the relevant property in a trust; https://www.legislation.gov.uk/ukpga/1984/51/section/64), s66(1) (at three tenths of the effective rate; https://www.legislation.gov.uk/ukpga/1984/51/section/66); https://www.gov.uk/guidance/trusts-and-inheritance-tax (the trustees pay the charge on each ten-year anniversary when relevant property is above the threshold, on its net value the day before, and report it to HMRC on form IHT100); `TrustService::calculateNextPeriodicChargeDate`; the card's own figures
figures: trust_name, anniversary_date, max_rate_percent
why:
1. {trust_name} reaches its ten-year anniversary on {anniversary_date}. The trust then pays Inheritance Tax of up to {max_rate_percent}% on what it holds above the threshold.
always:
1. The trustees value what the trust holds as it stands the day before the anniversary.
2. The trustees report it to HMRC on form IHT100 and pay any tax from the trust.
3. Once the trustees have the value, update the trust on the Estate page.
outcome:
1. The trustees are ready for the charge, and the trust's value on the Estate page is current.

## pension_no_beneficiary
status: draft
source: https://www.gov.uk/government/publications/reforming-inheritance-tax-unused-pension-funds-and-death-benefits/inheritance-tax-on-unused-pension-funds-and-death-benefits (from 6 April 2027 most unused pension funds and death benefits come into the estate for Inheritance Tax); Inheritance Tax Act 1984 s18 (what passes to a spouse or civil partner is exempt; https://www.legislation.gov.uk/ukpga/1984/51/section/18); `dc_pensions.beneficiary_id` / `beneficiary_name`; the card's own figures
figures: pension_name
why:
1. {pension_name} has no beneficiary recorded.
always:
1. Ask the provider of {pension_name} how to tell it whom you would like to receive the money when you die, and complete its form.
2. From 6 April 2027 most unused pension money counts towards your estate for Inheritance Tax. What passes to a husband, wife or civil partner is free of it.
3. Check the form again after a marriage, a divorce, a death or a birth in your family.
4. Then record the beneficiary on the pension, on the Retirement page of the web app. You can mark this action as done on any device.
outcome:
1. The scheme knows whom you would like the money to go to.
