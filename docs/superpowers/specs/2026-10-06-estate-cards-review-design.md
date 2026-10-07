# Estate cards: review and design

**Status:** APPROVED. CSJ answered D1 to D6 on 2026-10-07: yes to all six, and "a clt is only a clt if it exceeds the nil rate band for iht, same for a pet" (3.1).
**Date:** 2026-10-06
**Item:** `todoCurrent/TODO.md` item 9 (the item 7 and 8 shape: review, decisions, fixes, how-tos, walk, release).

## 1. What the review found

### 1.1 The 12 definitions, and which reach a card

`EstateActionDefinitionSeeder` holds 12 rows: 8 `agent`, 4 `strategy`.

- **The 4 `strategy` rows** (`strategy_make_a_will`, `strategy_register_lpa`, `strategy_reduce_iht_exposure`, `strategy_gift_to_reduce_estate`) are catalogue rows for the plan composer (`EstateStrategySource::metadataRows`). They are never cards and need no how-to.
- **The 8 `agent` rows are the actions list's estate cards.** The list reads them through `EstateStrategySource::recommendations` → `EstateActionDefinitionService::evaluateActions` (`RecommendationsAggregatorService:71-83`, composed path, on by default). Every card opens the Estate overview (`semanticDestinations.js:21`, `/estate`; /m `Estate.vue`).

**A second engine.** `EstateAgent::generateRecommendations` (`EstateAgent.php:489`) is a separate rule set, the "seven-step" Inheritance Tax plan: charitable bequest for the 36% rate, liquidity, existing life cover, annual gifts, whole of life cover, gifts that start the seven-year clock, gifts into trust, plus "Will wishes require trust structures" and "Will review recommended". It feeds the Estate plan page (`EstatePlanService:125`) and `CoordinatingAgent:631`, never the actions list. So the list's one Inheritance Tax card says only "Review estate planning strategies such as gifting, trust arrangements, or charitable giving", while the steps with figures sit on another page. Protection had the same shape until 8b (item 27 is its leftover).

### 1.2 Which keys fire

csjones, 152 households, 83 with the Estate module open (2026-10-06, `evaluateActions` per user):

| Key | Estate open | All |
|---|---|---|
| `no_lpa` | 81 | 150 |
| `no_lpa_health` | 81 | 150 |
| `no_will` | 77 | 146 |
| `beneficiary_review` | 58 | 60 |
| `iht_exceeds_nrb` | 9 | 12 |
| `policy_not_in_trust` | 7 | 7 |
| `gifts_pet_window` | 4 | 4 |
| `trust_review_due` | 2 | 2 |

Local, 56 households, 30 open: the same pattern (28, 28, 22, 22, 7, 3, 5, 2).

Demo households (local list, `aggregateRecommendations`):
- **Entrepreneur:** two estate "Life Policy Not Held in Trust" cards and two protection "Place your life insurance policy in trust" cards for the same two policies: four cards, one action.
- **Bennett (retired couple), Harold:** "2 gift(s) totalling £6,000 are within the seven-year Potentially Exempt Transfer window". Both are annual exemption gifts (`gift_type` `annual_exemption`, 6 April 2024 and 2025). Patricia: "4 gift(s) totalling £46,000", of which £6,000 is annual exemption gifts.
- **Mitchell, David:** the £150,000 gift into the children's trust (`clt`, 1 September 2020) is called a Potentially Exempt Transfer. "Trust Arrangement Review Due: … was last reviewed on never."

### 1.3 The same action on two cards

- **`policy_not_in_trust` exists in Protection and in Estate,** with the same key, both on the list (above). Protection's has an approved how-to sourced to HMRC IHTM20012 (`protection.md`); Estate's has none.
- **`no_lpa` and `no_lpa_health`** fire together for 81 of 83 households: two cards for one sitting (the health and welfare and the property and financial affairs Lasting Power of Attorney are made the same way and registered the same way, Mental Capacity Act 2005 Sch 1).
- **Inheritance Tax:** the list's `iht_exceeds_nrb` and the plan page's seven steps answer one question, "what can I do about my Inheritance Tax", and can disagree (the list names no figure for any step).

### 1.4 Figures and rules on the cards that are wrong (Rule 23)

- **`gifts_pet_window` counts every gift of the last seven years** (`EstateActionDefinitionService:263`, no `gift_type` filter). Annual exemption, small gifts and wedding gifts are exempt when made (Inheritance Tax Act 1984 s19, s20, s22) and never become chargeable; a Potentially Exempt Transfer is a gift to another individual or to certain trusts that would otherwise be chargeable (s3A(1A)). A gift into a discretionary trust is a chargeable lifetime transfer, not a Potentially Exempt Transfer (s2, s3A(1A)(c)). The description also says the gifts "may be subject to taper relief", which reads as a charge; taper relief reduces the tax on a chargeable gift made three to seven years before death (s7(4): 80%, 60%, 40%, 20%).
- **`trust_review_due` reads `last_valuation_date` as the last review** and prints "never" when it is empty; its 12-month threshold has no source. The dated event the law sets for a relevant property trust is its ten-year anniversary charge (s64(1)), at three tenths of the effective rate (s66(1); `trust_charges.periodic.max_rate` 6% in the config).
- **A draft Lasting Power of Attorney clears both LPA cards** (`evaluateNoLpa` takes any row; `status` can be `draft` or `completed`). An LPA is not created until the instrument is registered under Schedule 1 (Mental Capacity Act 2005 s9(2)(b)).
- **`policy_not_in_trust` and `iht_exceeds_nrb` type the rate into the text:** "Inheritance Tax at 40%" (seeder descriptions; Rule 2). `iht_exceeds_nrb` calls the total allowances (nil rate band, residence band, transferred bands) "the nil-rate band".
- **`beneficiary_review` fires for anyone with any pension or life policy** (`evaluateBeneficiaryReview`), whatever is recorded, and never clears. `dc_pensions.beneficiary_id` / `beneficiary_name` record a nomination, so the card can name the pension that has none. From 6 April 2027 most unused pension funds and death benefits come into the estate ([GOV.UK, Inheritance Tax on unused pension funds and death benefits](https://www.gov.uk/government/publications/reforming-inheritance-tax-unused-pension-funds-and-death-benefits/inheritance-tax-on-unused-pension-funds-and-death-benefits)); the engine already models it (`pension_iht_inclusion`).
- **The plan page's steps (second engine)** type figures the config already holds: "Small gifts of £250 per recipient", "Wedding gifts up to £5,000 (parents) or £2,500 (grandparents)" (`EstateAgent.php` step 4; config `gifting_exemptions`), "max 6% every 10 years" (step 7; `trust_charges.periodic.max_rate`). And figures with no source: liquid assets must cover "at least 50%" of the tax (step 2, `$liquidityRatio < 0.5`); whole of life cover "only if age ≤ 50 (premiums become prohibitive after 50)" (step 5); `DEFAULT_CURRENT_AGE` 50 and `DEFAULT_LIFE_EXPECTANCY` 85; the downsizing scenario's £200,000 equity release (`EstateAgent.php:1802`, reads `estate.onboarding_estimates.property`, a setup estimate); `ComprehensiveEstatePlanService:221` `85 - age` years to death.
- **Gifts out of surplus income** (`GiftingStrategyOptimizer:228`, `PersonalizedGiftingStrategyService:362`) suggest half the surplus and nothing below £1,000 (`safe_surplus_fraction` 0.5, `minimum_annual_gift` £1,000, `TaxConfigService:560`). Section 21 sets no fraction and no minimum: a gift is exempt when it is part of normal expenditure, out of income, and leaves the giver's usual standard of living (s21(1)).
- **Setup estimates** (`EstateOnboardingFlow:259`, `estate.onboarding_estimates`: property £300,000, investments £50,000, savings £25,000, business £100,000) give an estate value to anyone who ticks "I have property" without a figure.

### 1.5 Card text and data

- Title Case titles ("No Will in Place", "Beneficiary Designations Review"), third person ("the individual's wishes", "if the individual were to pass away"), "your adviser", "consult".
- `no_will` and the LPA cards say "Arrange a will with a solicitor"; web has its own will builder (`/estate/will-builder`) and LPA builder (`/estate/lpa/create/:type`). /m has neither.
- `policy_not_in_trust` opens the Estate overview, not the policy (`protection_policy_detail` exists, `semanticDestinations.js`).
- No `figures` reach estate cards' how-tos except the template variables.

### 1.6 Where this law applies

The will, intestacy and LPA cards rest on the law of England and Wales (Wills Act 1837 s18 extends to E+W+NI; Administration of Estates Act 1925 s46 extends to E+W; the Mental Capacity Act 2005 "extends to England and Wales only", s68(4)). Scotland and Northern Ireland have their own (the will builder already says so, `WillDocumentService.php:354`). Users have no country of residence recorded (`users`: `domicile_status`, `country_of_birth` only).

### 1.7 Checked and not a defect

- **Business Property Relief is capped:** `EstateAssetAggregatorService::applyBusinessPropertyRelief` applies the £2,500,000 allowance from 6 April 2026, pro rata (s124D(7)), from the config. The vault note "applied flat and uncapped" is out of date.
- **Agricultural Property Relief** cannot be recorded (no asset type) and the Estate page says so (`IHTCalculationService:364`). A schema change, not a card fix; registered as W-0463.
- **`iht_exceeds_nrb` reads `IHTCalculationService`** (W-0501), the figure the Estate page shows, with ownership shares and the residence band conditions.

## 2. The rules this rests on

- Rule 2 (no typed tax figures, including inside sentences); Rule 12 (no ratings); Rule 20 / 7a (one figure, computed once on the server, read by every surface: the card and the page beside it agree); Rule 23 (every figure and rule sourced; an invented figure is removed on sight).
- CSJ 2026-10-01: no signposting to named services; at most "speak to a financial adviser" where needed.
- CSJ 2026-10-05/06 (7a): the Holistic Plan follows the actions list.
- Item 8 precedent: one position card where several rules answer one question; cards carry their figures; shorter descriptions so the approved how-to is not repeated.

## 3. Design

### 3.1 Fix the gifts card (no decision needed; defect)
Count only gifts that can still become chargeable: `pet` and `clt` within seven years (s3A, s2); exempt gifts (`annual_exemption`, `small_gift`, `wedding`, `normal_expenditure`, `exempt`) never count. For each, taking earlier gifts first: the part above the nil rate band carries tax of its own ("chargeable in its own right", [IHTM14512](https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm14512)), with taper relief only there ([GOV.UK](https://www.gov.uk/inheritance-tax/gifts), s7(4)); the part within it carries no tax of its own but uses that much of the band the estate would get if death came first ([IHTM14503](https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm14503)). Name the date each gift leaves the seven years. (CSJ 2026-10-07: a gift is only taxed as a CLT or a PET above the nil rate band.) Harold Bennett gets no card; Patricia gets "2 gifts, £40,000"; David Mitchell's card names the £150,000 gift into trust as a chargeable lifetime transfer, out of the seven years on 1 September 2027.

### 3.2 LPA: only a registered one counts (no decision needed; defect)
`status = registered` (or `uploaded` with `is_registered_with_opg`) clears a card; a draft or completed but unregistered LPA keeps it, worded "registered" (s9(2)(b)).

### 3.3 Text and data (no decision needed; defects)
Rates and bands from the config, never typed (Rule 2); "allowances" not "nil-rate band" where the figure is the total; sentence case titles; second person; no "your adviser"; the will and LPA cards open the web builders; the trust card opens the policy. The plan page's typed gift limits and 6% read the config. Removed (no source): the 50% liquidity test, the "age ≤ 50" rule, the 85 and 50 defaults where a recorded date of birth or the actuarial table is missing (say the figure is missing instead), the £200,000 equity release default, `safe_surplus_fraction` and `minimum_annual_gift` (the surplus itself is the figure, s21), the setup estimates (an unanswered value stays unanswered).

### 3.4 One Inheritance Tax position card (decision D1)
Replace the generic `iht_exceeds_nrb` card with one "your Inheritance Tax position" card, as Protection and Investment have: the tax today, the allowances it uses, and the steps that reduce it with each step's figure, from the one engine the Estate plan page uses. The plan page and the card then show the same steps and figures.

**The steps' maths, corrected (found while building, 2026-10-07; Rule 23, no decision).** The plan page's steps carry figures the card would inherit, and several are wrong:

| Step | Was | Now (source) |
|---|---|---|
| 1 Charity | 36% rate when 10% of the baseline goes to charity (W-0451, correct) | Unchanged (Sch 1A) |
| 2 Paying the tax | Fires when liquid assets are under 50% of the tax (no source) | Fires when liquid assets are under the tax; the shortfall in pounds. The tax is due six months after the end of the month of death (s226(1)); tax on land and buildings can be paid in ten yearly instalments (s227(1), (2)) |
| 3 Cover in trust | "Usable" cover = cover in trust minus every debt (no source); a third "place policies in trust" card | Cover in trust on your own life, in full (its payout is outside the estate, IHTM20012); the trust placement suggestion is Protection's card only (D2) |
| 4 Annual gifts | £3,000 × years to life expectancy × 40% | Per year: the annual exemption (s19, `gifting_exemptions.annual_exemption`, plus last year's if unused, s19(2)) and the tax it saves at the estate's rate; small gifts and wedding gifts from the config (s20, s22) |
| 5 Life cover | Only if aged 50 or under (no source) | Cover equal to the tax still due after steps 1 to 4, written in trust; no age gate, no premium |
| 6 Larger gifts | One nil rate band per seven years × life expectancy | The nil rate band not used by gifts of the last seven years: a gift up to that carries no tax of its own even if death comes within seven years (IHTM14512), and once survived saves tax at the estate's rate (s3A, s7) |
| 7 Gifts into trust | Treats the tax as the size of the gift | No amount: taxed at 20% now on the part above the nil rate band available (`chargeable_lifetime_transfers.lifetime_rate`), up to 6% every ten years (s64, s66(1); `trust_charges.periodic.max_rate`), more if death within seven years |

The life expectancy and age defaults (85, 50) and the three-year "will review" (no source) leave the steps.

### 3.5 Life policy in trust: one card (decision D2)
Drop Estate's `policy_not_in_trust` from the list; Protection's card (approved how-to, IHTM20012) is the one, and it opens the policy.

### 3.6 Lasting Powers of Attorney: one card (decision D3)
One card naming which LPA is missing or not yet registered (property and financial affairs, health and welfare, or both).

### 3.7 Beneficiaries: only where one is missing (decision D4)
Fire per defined contribution pension with no beneficiary recorded (`beneficiary_id` and `beneficiary_name` empty), naming the scheme; no card where every pension has one. The yearly reminder for everyone goes.

### 3.8 Trusts: the ten-year anniversary, not a 12-month review (decision D5)
For a relevant property trust (discretionary), fire in the two years before its ten-year anniversary (s64), naming the date, from the trust's creation date. Other trust types: no dated card.

### 3.9 England and Wales (decision D6)
Word the will, intestacy and LPA cards and how-tos as England and Wales law ("In England and Wales, …"), and log a country of residence question as its own item (with the parked Scottish Income Tax programme).

## 4. Decisions for CSJ

- **D1** One Inheritance Tax position card on the list, carrying the plan page's steps and figures, in place of the generic `iht_exceeds_nrb` card. *Recommend: yes.*
- **D2** One life policy trust card: Protection's; Estate's leaves the list. *Recommend: yes.*
- **D3** One Lasting Power of Attorney card naming which is missing or unregistered, in place of two. *Recommend: yes.*
- **D4** The beneficiaries card fires only for a pension with no beneficiary recorded, naming it. *Recommend: yes.*
- **D5** The trust card fires in the two years before a discretionary trust's ten-year anniversary, in place of the 12-month review. *Recommend: yes.*
- **D6** Will, intestacy and LPA wording says England and Wales; a country of residence question becomes its own list item. *Recommend: yes.*

## 5. How-tos

After the decisions: one entry per card that remains (`no_will`, the LPA card, the Inheritance Tax position card, `gifts_pet_window`, the beneficiaries card, the trust card), drafted in `database/seeders/data/action-how-to/estate.md` for CSJ's approval, each sourced (legislation.gov.uk sections above, HMRC IHTM, GOV.UK). Also for approval: `protection.md` `life_cover_position` still says "your children's education" (step 1 and reason 5), which 8b removed from the need.

## 6. Testing

Named files only: `EstateActionDefinitionService` unit tests per evaluator (the Bennett and Mitchell gift cases, a draft LPA, a trust anniversary, a pension with and without a beneficiary); a no-typed-rate check on rendered descriptions; the list's estate cards for the demo households; web 1440 and /m 390 walks on csjones, then fynla.org.
