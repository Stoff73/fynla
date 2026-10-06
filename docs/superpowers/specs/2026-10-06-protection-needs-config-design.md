# Protection needs: sourced figures, correct maths, one place (item 8b)

**Status:** approved (CSJ 2026-10-06): D1, D2, D4, D5, D6, D7 as recommended; D3 keeps 3 x gross earned income, shown to the user as a rule of thumb ("making sure that the user is aware this is an arbituary figure"; critical illness cover is "usually based on affordability").

**Asked (CSJ 2026-10-06):** "these figures need to be placed into the taxconfig file under protection needs calculations, with the appropriate headings, and surfaced in admin, so they can be updated as necessary and fetched from one place. Do the necessary research as well, to ensure that we have all the necessary figures and correct math."

---

## 1. How the need is worked out today

Every "You need £X" on the Protection page, the protection cards, the plan and Fyn comes from `CoverageGapAnalyzer::calculateProtectionNeeds` (life cover) plus two side rules for critical illness and income protection.

| Cover | Today's formula | Where | Figure's source |
|---|---|---|---|
| Life: income replacement | (user's net earned income − partner's net income − rental − dividends) ÷ **4.7%** | `CoverageGapAnalyzer.php:36-45` | none |
| Life: debts | the user's share of every mortgage and liability | `:66-127` | the user's records |
| Life: education | **£9,000** × (21 − age) for every child | `:134-146` | none |
| Life: final expenses | **£7,500** | `:151-154` | none |
| Critical illness | **3 ×** gross earned income | `AdequacyScorer.php:72`, `ProtectionCoverPosition.php:77` | none |
| Income protection | **60%** of gross earned income | `CoverageGapAnalyzer.php:478-479` | none (see 2.4) |
| Employer cover reliance | death in service over **50%** of life cover | `protection.dis_reliance_percent` | product rule (2026-09-29) |
| Statutory Sick Pay, ESA | already sourced in `benefits.ssp`, `benefits.esa` | | gov.uk |

A second engine, `ComprehensiveProtectionPlanService::generateOptimizedStrategy` (`:495`, `:515`), works out critical illness as `3 ×` income and income protection as `70%` of net income, typed into the code. The web plan page (`ProtectionCurrentSituation.vue:46`, `:113`, `:169`) prints "at 4.7% drawdown", "3 × your gross annual income" and "70% of your net monthly income" as fixed words. The 70% line is wrong on screen today: the Carter household reads "70% of your net monthly income (£4,505/month)" beside a need of £3,750, which is 60% of gross (£75,000 × 60% ÷ 12).

Unused config: `protection.income_multipliers.life_cover` (10 ×) is read by nothing but the admin screen.

## 2. Research

### 2.1 Turning a lost income into a lump sum
The law already has a rate for exactly this: the **Personal Injury Discount Rate**, used "to help calculate how much defendants have to pay in damages … when the damages are paid in the form of a lump sum" for future losses ([GOV.UK, Personal injury discount rate](https://www.gov.uk/guidance/personal-injury-discount-rate)). It is **+0.5%** in England and Wales from 11 January 2025 (Damages Act 1996, as amended by the Civil Liability Act 2018), and **+0.5%** in Scotland and Northern Ireland from 27 September 2024 ([Department of Justice NI](https://www.justice-ni.gov.uk/news/new-discount-rate-personal-injury-claims-set)). It is a real rate (after earnings inflation), so it pairs with today's money.

The lump sum that pays an income `A` a year for `n` years at rate `r` is `A × (1 − (1 + r)^−n) ÷ r`.

Today's 4.7% has no source, and dividing by it pays the income **for ever** (a perpetuity). At 4.7%, £30,000 a year needs £638,298; for 25 years at 0.5% it needs £703,369; for 10 years, £291,912. The term matters more than the rate.

### 2.2 Final expenses
SunLife Cost of Dying Report 2025 (2024 data): the **cost of dying is £9,797** ("This includes the funeral, plus professional fees and send-off costs"); a **simple funeral is £4,285** ([SunLife report, PDF](https://sunlife.co.uk/siteassets/documents/cost-of-dying/sunlife-cost-of-dying-report-2025.pdf)). Today's £7,500 matches neither.

### 2.3 Children
- **Cost of raising a child to 18:** £250,000 for a couple, £290,000 for a lone parent, including rent, childcare and council tax (CPAG, The Cost of a Child in 2025, 23 October 2025, [cpag.org.uk](https://cpag.org.uk/news/cost-child-2025)).
- **University tuition:** the fee cap for 2026/27 is £9,790 in England (Department for Education, November 2025). In England tuition is paid by the student's tuition fee loan, not by parents.

Today's education figure charges £9,000 for **every** year from now until 21: a newborn gets £189,000 of "tuition". Children's day-to-day costs are already inside the household's spending (2.6), so a separate child figure on top double-counts them.

### 2.4 Income protection
Insurers cap the benefit at a share of gross income, stepping down above a threshold:
- **Legal & General:** "60% of your annual income for the first £60,000 and 50% of your annual income over £60,000", paid "less any continuing income" (Low Start Income Protection Policy Summary, QGI16002 04/25, [legalandgeneral.com, PDF](https://www.legalandgeneral.com/asset/4a18ed/globalassets/adviser/files/protection/policy-summary/qgi16002.pdf/)).
- **Aviva:** 65% of the first £60,000 and 45% above.

Today's flat 60% overstates the need for anyone earning over £60,000 (£100,000 → £60,000 today; £56,000 under the L&G limit).

### 2.5 Critical illness
No authoritative UK multiple was found. Guidance from MoneyHelper (via search; the page blocks automated reading) and comparison sites says the amount depends on debts, outgoings and recovery time. The "3 × income" in the app is a rule of thumb with no source. The ABI's figure is an average paid claim of £67,600 in 2024 ([ABI, July 2025](https://www.abi.org.uk/news/news-articles/2025/7/record-8bn-paid-out-in-vital-protection-claims-during-2024/)), which describes claims, not need.

### 2.6 The income the family loses (maths)
Today: user's net income − partner's net income − rental − dividends. That subtracts the partner's whole income from the user's, so a family where the partner earns the same as the user "loses nothing", although the household loses half its income. The standard method (and the comparison guidance found) works from what the household **spends**: spending that continues, less income that continues, for a chosen period. The app records monthly spending (`ResolvesExpenditure::resolveMonthlyExpenditure`).

Debts: the life need adds the debts to be cleared **and** an income that still includes paying them. Once the mortgage is cleared, its payments stop; the income need should not include them.

## 3. Proposed method

- **Life cover need** = debts to clear + final expenses + lump sum for the family's income gap.
  - Income gap a year = household spending (excluding mortgage and loan payments, which the debt part clears) − income that continues (partner's net income, rental profit, dividends).
  - Lump sum = income gap × (1 − (1 + r)^−n) ÷ r, with `r` the Personal Injury Discount Rate and `n` the term (decision D2).
  - No spending recorded: ask for it (as the pension cards do since #1027), never fall back to a guess.
- **Critical illness need**: decision D3.
- **Income protection need** = tiered limit on gross earned income (L&G's 60% to £60,000, 50% above), less income that continues while unable to work.
- **One engine**: `generateOptimizedStrategy` reads these needs instead of its own 3 × and 70%. Screens print the working the server sends (Rule 20), never fixed words.

## 4. Configuration and admin

Every figure moves under `protection.needs_calculation` in the tax configuration, one heading per cover, each with its source text, read only through one accessor (`TaxConfigService::getProtectionNeeds()`):

```
protection.needs_calculation
  life_cover
    income_replacement: discount_rate 0.005, discount_rate_source, term (D2)
    final_expenses: amount 9797, source
  critical_illness: (D3)
  income_protection
    benefit_tiers: [{up_to: 60000, rate: 0.60}, {above: 60000, rate: 0.50}], source
  employer_cover
    reliance_share 0.50
```

The admin Tax Settings "Module Config" tab gets a "Protection needs calculations" section with these headings, each figure editable with its source shown beside it. Removed: `income_multipliers.life_cover` (unused), `education_cost_per_year`, `withdrawal_rates.human_capital` and the code fallbacks (`?? 0.047`, `?? 9000`, `?? 7500`, `?? 3`).

## 5. Decisions (CSJ)

- **D1 Lump sum rate.** Replace the 4.7% perpetuity with the Personal Injury Discount Rate (0.5%, GOV.UK) over a term. *Recommend: yes.*
- **D2 Term of the income gap.** Until the user's State Pension age (when their earnings would have stopped), or until the youngest child is 18 (21 in full-time education). *Recommend: the later of the two, capped at State Pension age.*
- **D3 Critical illness need.** No source exists for a multiple. (a) Keep 3 × gross earned income, labelled in admin as Fynla's planning assumption; or (b) debts to clear + a recovery period of household spending, the period set in admin. *Recommend: (b), with the recovery period yours to set.*
- **D4 Final expenses.** SunLife's cost of dying, £9,797 (funeral, professional fees and send-off), or the simple funeral, £4,285. *Recommend: £9,797.*
- **D5 Children.** Remove the separate education figure: children's costs sit in the household spending the income gap already replaces, until the term in D2 ends; tuition is funded by the student's loan in England. *Recommend: remove.*
- **D6 Income protection limit.** L&G's 60% to £60,000 then 50%, or Aviva's 65% then 45%. *Recommend: L&G's (the lower need), editable in admin.*
- **D7 Income gap from spending (2.6).** Use recorded spending, less debt payments, less income that continues; ask for spending when none is recorded. *Recommend: yes.*

## 6. Out of scope, listed

- `AdequacyScorer` 0-100 scores and `getCoverageStatus` "Fair"/"Critical" (Rule 12) feed the plan's ratings; separate item.
- `protection.withdrawal_rates.scenario` 3% in `ScenarioBuilder` (death scenario projections): same source question; moves to the Personal Injury Discount Rate if D1 is approved.

## 7. Done when

- Every protection need reads `protection.needs_calculation` through one accessor; no figure or fallback in code; the second engine and the fixed screen words are gone.
- Admin shows and saves each figure with its source, and a saved change moves the need on web, /m and Fyn.
- Tests pin each formula against worked examples from this document.
- Walked on csjones and fynla.org, web 1440 and /m 390.
