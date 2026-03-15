# Investment Decision Tree: Gap Analysis

**Date:** 2026-03-14
**Analysed document:** `investmentTree/investment-decision-tree.md` (v0.8.0, 2026-02-11)
**Purpose:** Identify missing decisions, products, analysis areas, and outcomes not currently covered

---

## 1. Missing Product Types / Wrappers

### 1.1 Innovative Finance ISA (IFISA)
**What is missing:** The IFISA wrapper is entirely absent from the decision tree. IFISAs allow peer-to-peer lending within an ISA wrapper, offering tax-free interest on P2P loans.

**Why it matters:** Users with higher risk tolerance who have exhausted their S&S ISA appetite may benefit from the diversification and potentially higher yields. However, IFISA investments are NOT covered by the FSCS (beyond the P2P platform's own protections), making suitability assessment critical.

**Data needed:**
- `risk_level` (must be medium or above)
- `investment_experience` (must be intermediate or above)
- `comfortable_with_capital_loss` (must be true)
- `isa_remaining` (shares the same 20,000 ISA allowance)
- Portfolio size (IFISA should be a small allocation)

**Decision logic:**
```
Step 2 (S&S ISA) could branch:
+-- Has existing P2P lending experience AND risk_level >= medium?
    AND comfortable_with_capital_loss = true?
    AND ISA allowance remaining?
    YES -> INFO: "Innovative Finance ISA allows tax-free interest on peer-to-peer loans.
           Not covered by FSCS -- only suitable as a small portfolio allocation."
    NO -> Skip
```

**Priority:** LOW -- Niche product. The IFISA market has contracted significantly since 2020, with several major platforms (Funding Circle, Zopa, RateSetter) leaving the P2P space. Remaining platforms are small and liquidity can be poor.

---

### 1.2 Investment Trusts (Closed-Ended Funds)
**What is missing:** The tree covers ISAs, GIAs, bonds, VCTs, and EIS but never distinguishes between open-ended funds (OEICs/unit trusts) and closed-ended funds (investment trusts). Investment trusts trade on the stock exchange at a premium or discount to NAV, can use gearing, and have revenue reserves for dividend smoothing.

**Why it matters:** Investment trusts are relevant to the recommendation engine in two ways:
1. **Discount/premium analysis** -- a GIA or ISA holding in an investment trust trading at a wide discount may represent a buying opportunity, while a premium may warrant a transfer recommendation
2. **Income investors** -- investment trusts with revenue reserves offer more reliable income streams than open-ended equivalents (relevant for retirees)

**Data needed:**
- `holding.fund_type` (open-ended vs closed-ended) -- this field does not currently exist on holdings
- `holding.discount_to_nav` -- would need price vs NAV comparison
- `holding.revenue_reserve_months` -- for income reliability assessment

**Decision logic:**
This is primarily a **fund selection / holding analysis** concern rather than a wrapper decision. It would sit within a new Phase (or sub-phase of Transfer Scans):
```
For each investment trust holding in GIA/ISA:
+-- Trading at premium > 10%?
    YES -> INFO: "Your holding in {name} trades at a {premium}% premium to NAV.
           Consider whether the premium is justified or if an open-ended equivalent
           offers better value."
+-- Trading at discount > 15%?
    YES -> INFO: "Your holding in {name} trades at a {discount}% discount to NAV.
           This may represent value if the discount narrows."
```

**Priority:** LOW -- Requires holding-level data fields that don't exist. The current system doesn't distinguish fund types within a wrapper.

---

### 1.3 ETFs vs Active Funds
**What is missing:** The tree makes no distinction between ETFs (passive, low cost, exchange-traded) and actively managed funds. There is no decision logic for recommending passive over active or vice versa based on asset class, charges, or evidence.

**Why it matters:** The charges impact is significant. UK investors in active funds pay roughly 0.75-1.5% OCF vs 0.05-0.30% for index trackers. Over a 30-year accumulation period, this difference compounds enormously. The FeeAnalyzer and OCFImpactCalculator services exist but are not connected to the recommendation decision tree.

**Data needed:**
- `holding.is_passive` or `holding.management_style` (active/passive/smart_beta)
- `holding.ocf` (ongoing charges figure)
- `account.weighted_average_ocf`
- Benchmark comparison data

**Decision logic:**
This belongs as a new Transfer Scan (Scan 8):
```
SCAN 8: FEE DRAG ANALYSIS
|
+-- Any holding with OCF > 0.75%?
    AND a passive equivalent exists for the same asset class?
    YES -> [T8] "Your {holding} charges {ocf}% annually. A passive equivalent
           in the same asset class charges approximately {passive_ocf}%.
           Over {years} years, this difference costs an estimated {impact}."
    Priority: medium (if difference > 0.5%), low (otherwise)
```

**Priority:** MEDIUM -- The FeeAnalyzer and OCFImpactCalculator already exist. The data fields for OCF exist on holdings. This is primarily a wiring/integration task.

---

### 1.4 Absolute Return Funds
**What is missing:** No specific handling for absolute return funds, which aim to deliver positive returns regardless of market direction. These are relevant for risk-averse investors who want equity-like returns without full equity risk.

**Why it matters:** Limited impact. Absolute return funds are a fund selection concern rather than a wrapper decision. The tree correctly focuses on wrappers (ISA, pension, GIA, bond) rather than what goes inside them.

**Decision logic:** Would only be relevant as a note within glide path / approaching retirement logic:
```
approaching_retirement AND risk_level reducing:
    note: "Consider absolute return or target-date funds to reduce volatility
           while maintaining growth potential."
```

**Priority:** LOW -- Fund selection is outside the current scope of the wrapper-focused decision tree.

---

### 1.5 Property Funds (REITs)
**What is missing:** No specific decision logic for REIT allocation within ISA/GIA wrappers. REITs offer property exposure without the illiquidity, leverage, and concentration risk of direct property investment.

**Why it matters:** Users with significant direct property exposure (buy-to-let) may benefit from a recommendation to diversify away from direct property OR, conversely, users wanting property exposure without BTL complexity could be directed to REITs.

**Data needed:**
- `properties` (existing) -- to calculate direct property exposure as % of net worth
- `holdings.asset_class = 'property'` -- to identify existing REIT exposure

**Decision logic:**
```
Direct property > 40% of net worth AND no REIT holdings in portfolio?
    INFO: "Your net worth is heavily concentrated in direct property ({percent}%).
          Consider property funds (REITs) within your ISA or pension for
          diversified property exposure with daily liquidity."
```

**Priority:** LOW -- This is an asset allocation concern, not a wrapper decision. Could be part of a broader diversification analysis (see section 2.1).

---

### 1.6 Gold / Commodities
**What is missing:** No decision logic for commodity allocation (gold ETCs, commodity funds). Gold is commonly recommended as a portfolio diversifier and inflation hedge.

**Why it matters:** Gold and commodities have different tax treatment in a GIA (CGT on disposal, no income tax as no yield), and physical gold (sovereign coins) is CGT-exempt. These nuances are not captured.

**Data needed:**
- `holdings.asset_class` values to include 'commodity' or 'gold'
- Portfolio allocation percentages

**Decision logic:** Asset allocation concern -- would sit in a diversification analysis rather than the waterfall:
```
Portfolio has 0% commodity/gold allocation AND time_horizon > 10 years?
    INFO: "A small allocation (3-10%) to gold or commodities can improve
          portfolio diversification. Gold sovereigns are CGT-exempt."
```

**Priority:** LOW -- Niche, and the current tree correctly focuses on wrapper efficiency rather than asset selection.

---

### 1.7 Cryptocurrency
**What is missing:** No mention of cryptocurrency. The FCA classifies crypto as high-risk, unregulated, and not covered by FSCS.

**Why it matters:** Users may hold or ask about crypto. The decision tree should explicitly address this, even if only to flag the risks and regulatory position.

**Data needed:**
- Could be tracked as `account_type = 'crypto'` or within 'other'
- No ISA or pension wrapper currently accepts crypto directly in the UK

**Decision logic:**
```
User holds crypto assets OR asks about crypto?
    WARN: "Cryptocurrency is classified as a high-risk investment by the FCA.
          It is not covered by FSCS, cannot be held in a UK ISA or pension,
          and gains are subject to Capital Gains Tax. This should represent
          only a small portion of a diversified portfolio."
```

**Priority:** LOW -- The app is a financial planning tool, not a crypto platform. A warning note would be sufficient. However, CGT reporting on crypto gains is increasingly important and could tie into the tax loss harvesting logic.

---

### 1.8 Friendly Society Tax-Exempt Savings Plans
**What is missing:** Friendly Society tax-exempt savings plans (max 25/month or 270/year) are not in the tree.

**Why it matters:** Very limited relevance. The 25/month maximum makes these trivial compared to ISA (20,000/year) and pension (60,000/year). The only niche use case is for additional-rate taxpayers who have exhausted all other allowances, as the plan grows completely tax-free.

**Data needed:**
- `tax_band` = additional
- All other allowances exhausted (ISA, pension, carry forward)
- Existing friendly society plan holdings

**Decision logic:**
```
Would sit as Step 8.5 (between VCT/EIS and GIA):
+-- tax_band = additional AND ISA exhausted AND pension exhausted?
    AND no existing friendly society plan?
    YES -> INFO: "Friendly society tax-exempt savings plans offer completely
           tax-free growth on up to 25/month. A small but fully sheltered allocation."
    Priority: low
```

**Priority:** LOW -- Extremely small amounts involved. Worth mentioning as an informational note for additional-rate taxpayers only.

---

### 1.9 National Savings Certificates
**What is missing:** NS&I Savings Certificates (index-linked and fixed-interest) are not specifically addressed. Note: these have not been on general sale since 2011, but existing holdings can be rolled over.

**Why it matters:** Users with existing NS&I Certificates benefit from tax-free, index-linked returns. On maturity, they should be advised whether to roll over or reinvest elsewhere.

**Data needed:**
- Existing NS&I Certificate holdings (maturity date, value, type)
- Current index-linked rate vs alternative rates

**Decision logic:**
```
Transfer Scan addition:
+-- User holds NS&I Certificates approaching maturity?
    YES -> INFO: "Your NS&I Savings Certificates mature on {date}. As certificates
           are no longer on general sale, consider whether the rollover rate
           is competitive vs ISA alternatives."
```

**Priority:** LOW -- Only relevant for existing holders. A rare edge case.

---

### 1.10 SEIS (Seed Enterprise Investment Scheme) -- Separate Treatment
**What is missing:** The tree groups VCT, EIS, and SEIS together in Step 8 with a single allocation note. SEIS has significantly different characteristics: 50% income tax relief (vs 30%), 100,000/year limit, and the CGT reinvestment relief has been reformed.

**Why it matters:** Grouping them together means the tree doesn't address:
- SEIS-first ordering is mentioned in a note but not enforced in allocation logic
- SEIS has its own annual limit (200,000 from April 2023) separate from EIS (1m/2m)
- SEIS companies are much higher risk (pre-revenue startups)

**Data needed:** Already captured via `account_type` (eis, vct exist; seis would need adding or could be captured under eis with a sub-type flag)

**Decision logic:**
```
Step 8 should be split into sub-steps:
8a: SEIS (50% relief, highest risk, smallest allocation)
8b: EIS (30% relief, high risk)
8c: VCT (30% relief, more diversified, listed on LSE)

Each with separate eligibility gates and allocation caps.
```

**Priority:** MEDIUM -- The current grouping is pragmatic but loses important distinctions. SEIS investors get significantly better tax treatment.

---

## 2. Missing Analysis Areas

### 2.1 Asset Allocation Analysis (Portfolio Diversification)
**What is missing:** The decision tree has NO asset allocation analysis phase. It determines WHERE to put money (which wrapper) but not WHAT to invest in or whether the resulting portfolio is appropriately diversified.

**Why it matters:** A user could follow every waterfall recommendation perfectly and still end up with a poorly diversified portfolio (e.g., 100% UK equities across ISA, pension, and GIA). The DiversificationAnalyzer and AssetAllocationOptimizer services exist but are not integrated into the recommendation engine.

**Data needed:**
- `holdings` with asset class breakdown (UK equity, international equity, bonds, property, cash, alternatives)
- Target allocation by risk level (already in ModelPortfolioBuilder)
- Geographic allocation (UK vs international)

**Decision logic:** This should be a new Phase 4.5 (between Contribution Waterfall and Transfer Scans):
```
PHASE 4.5: PORTFOLIO HEALTH CHECK
|
+-- CHECK 1: ASSET CLASS CONCENTRATION
|   +-- Any single asset class > 60% of total portfolio?
|       YES -> WARN: "Your portfolio is {percent}% concentrated in {class}.
|              Consider diversifying across asset classes."
|
+-- CHECK 2: GEOGRAPHIC CONCENTRATION
|   +-- UK allocation > 70% of equity?
|       YES -> INFO: "Your equity allocation is {percent}% UK. The UK represents
|              only ~4% of global market capitalisation. Consider increasing
|              international diversification."
|
+-- CHECK 3: SINGLE STOCK CONCENTRATION
|   +-- Any single holding > 15% of total portfolio?
|       YES -> WARN: "Your holding in {name} represents {percent}% of your portfolio.
|              Single stock concentration increases risk significantly."
|
+-- CHECK 4: SECTOR CONCENTRATION
|   +-- Any single sector > 30% of equity?
|       YES -> INFO: "{sector} represents {percent}% of your equity allocation.
|              Sector concentration can amplify market downturns."
```

**Priority:** HIGH -- This is the most significant gap in the entire decision tree. The services exist (DiversificationAnalyzer, AssetAllocationOptimizer, ModelPortfolioBuilder) but are not connected to the recommendation pipeline. The waterfall tells you WHERE to invest but not WHAT to invest in.

---

### 2.2 Pound Cost Averaging vs Lump Sum
**What is missing:** The tree distinguishes `contributionType` (regular vs lump_sum) for skip conditions but never recommends one approach over the other. When a user has a windfall, there is no guidance on whether to invest it all at once or drip-feed it.

**Why it matters:** Academic evidence suggests lump sum investing outperforms pound cost averaging approximately 66% of the time (Vanguard research). However, for risk-averse investors or in volatile markets, DCA can reduce regret risk and behavioural anxiety.

**Data needed:**
- `contribution_type` (already in context)
- `risk_level` / `risk_tolerance`
- `windfall_amount` (from life event)
- `time_horizon`

**Decision logic:**
```
In LifeEventAssessmentService, when handling windfall events:
+-- windfall_amount > 10,000 AND risk_level = low/very_low?
    YES -> note: "Consider investing your {amount} windfall in stages over
           6-12 months (pound cost averaging) to reduce the impact of
           market timing. This suits your lower risk tolerance."
+-- windfall_amount > 10,000 AND risk_level >= medium?
    YES -> note: "Evidence suggests investing a lump sum immediately
           outperforms drip-feeding in most market conditions. With your
           {risk_level} risk profile and {time_horizon}-year horizon,
           a lump sum approach is appropriate."
```

**Priority:** MEDIUM -- Common question from users receiving windfalls. The logic is straightforward and the data already exists.

---

### 2.3 Tax Loss Harvesting
**What is missing:** The tree mentions CGT exemption in Bed & ISA (T2) and spouse CGT sharing (SP1) but has no explicit "tax loss harvesting" scan. The CGTHarvestingCalculator service exists but is not referenced in the decision tree.

**Why it matters:** Users with GIA holdings sitting at a loss should be prompted to crystallise losses before the tax year end, which can offset gains elsewhere. This is time-sensitive (tax year end) and can save real money.

**Data needed:**
- `holdings` with cost basis and current value (GIA holdings specifically)
- `realised_gains_ytd` (gains already crystallised this tax year)
- `carried_forward_losses` (losses from prior years)
- Time of year (urgency increases near April 5)

**Decision logic:**
```
SCAN 8: TAX LOSS HARVESTING (new Transfer Scan)
|
+-- Any GIA holding with unrealised loss > 500?
    AND (realised_gains_ytd > 0 OR user is higher/additional rate)?
    YES -> [T8] "Your {holding} in GIA has an unrealised loss of {loss}.
           Crystallising this loss creates a capital loss that can offset
           gains this tax year or be carried forward indefinitely."
    Priority: high (if within 3 months of tax year end), medium (otherwise)
    Note: "Bed and breakfast rules: do not repurchase the same shares within
           30 days. You can repurchase via ISA immediately (Bed & ISA) or
           buy a similar but not identical fund."
```

**Priority:** HIGH -- The service already exists (CGTHarvestingCalculator). Time-sensitive, directly saves users tax. Should be integrated as a Transfer Scan.

---

### 2.4 Rebalancing Recommendations
**What is missing:** The tree has no rebalancing phase. The DriftAnalyzer and RebalancingCalculator services exist but are not integrated. The decision tree tells users what to buy but never reviews whether their existing portfolio has drifted from target and needs rebalancing.

**Why it matters:** After market movements, a portfolio's actual allocation drifts from target. A user who started at 60/40 equity/bonds may now be 75/25 after a bull market, taking on more risk than intended. Rebalancing is a core investment discipline.

**Data needed:**
- `actual_allocation` per asset class (from holdings)
- `target_allocation` per risk level (from ModelPortfolioBuilder)
- `drift_threshold` (e.g., >5% absolute deviation)
- `last_rebalance_date`

**Decision logic:**
```
PHASE 4.5b: REBALANCING CHECK (alongside Portfolio Health Check)
|
+-- Total drift > 5% absolute deviation from target?
    YES -> MEDIUM: "Your portfolio has drifted from its target allocation.
           {asset_class} is {actual}% vs {target}% target. Consider
           rebalancing to maintain your intended risk level."

    +-- Drift > 10%?
        YES -> HIGH: "Significant portfolio drift detected. Your {risk_level}
               risk profile targets {target}% equity, but your actual
               allocation is {actual}%. Rebalance to avoid unintended risk."

    +-- last_rebalance_date > 12 months ago?
        YES -> INFO: "Your portfolio has not been rebalanced in over 12 months.
               Regular rebalancing helps maintain your target risk level."
```

**Priority:** HIGH -- Core investment discipline. Services already exist. Should be added as a post-waterfall check.

---

### 2.5 Platform Selection / Consolidation
**What is missing:** The tree has no logic for recommending platform consolidation or platform switching based on fees. Users with accounts spread across multiple platforms pay unnecessary platform fees.

**Why it matters:** The PlatformComparator service already exists and compares fee structures across major UK platforms (Hargreaves Lansdown, Vanguard, AJ Bell, etc.). Platform fees can significantly impact long-term returns, especially for larger portfolios where percentage-based fees become expensive.

**Data needed:**
- `accounts` with platform, platform_fee_percent, platform_fee_amount
- Total value per platform
- Number of distinct platforms

**Decision logic:**
```
SCAN 9: PLATFORM CONSOLIDATION (new Transfer Scan)
|
+-- User has > 3 investment platforms?
    YES -> INFO: "You have accounts across {count} platforms. Consolidating
           to fewer platforms can reduce total fees and simplify management."

+-- Any platform charges > 0.45% on portfolio > 50,000?
    YES -> MEDIUM: "Your {platform} charges {fee}% on {value}. At this
           portfolio size, a flat-fee platform could save {saving}/year."

+-- Any platform charges flat fee on portfolio < 20,000?
    YES -> INFO: "Your {platform} charges {fee}/year flat fee on {value}.
           At this portfolio size, a percentage-fee platform may be cheaper."
```

**Priority:** MEDIUM -- PlatformComparator service exists. Directly saves users money. Common advice area.

---

### 2.6 Sequencing Risk (Pre-Retirement)
**What is missing:** The "approaching_retirement" derived event triggers a glide path note, but there is no detailed sequencing risk analysis. Sequencing risk (the risk that poor returns early in retirement devastate a portfolio) is one of the most important risks for those within 5-10 years of drawdown.

**Why it matters:** The current tree says "gradually shift towards lower-risk assets" but provides no specific guidance on: what percentage to de-risk per year, whether to use cash buffers, or how to structure drawdown to avoid selling in down markets.

**Data needed:**
- `years_to_retirement` (existing)
- `pension_values` and `other_investment_values`
- `target_retirement_income`
- `guaranteed_income_sources` (state pension, DB pension, annuity)

**Decision logic:**
```
In approaching_retirement handling:
+-- years_to_retirement <= 5 AND income gap > 0?
    (income gap = target retirement income - guaranteed income)
    YES -> HIGH: "Your income shortfall of {gap}/year in retirement will
           rely on portfolio drawdown. Consider holding 2-3 years of
           income gap ({buffer}) in cash/near-cash to avoid selling
           investments during market downturns."

+-- years_to_retirement <= 3?
    YES -> Additional note: "Review whether your pension fund offers a
           lifestyling or target-date option that automatically
           adjusts your investment mix as retirement approaches."
```

**Priority:** MEDIUM -- Bridges to the retirement module. The approaching_retirement event exists but the analysis is superficial.

---

### 2.7 ESG/Ethical Investment -- Beyond the Flag
**What is missing:** The `esg_preference` boolean flag exists and is used only once (NS&I Green Savings Bonds note in Step 4B). There is no broader ESG integration: no screening of existing holdings for ESG compliance, no recommendation to switch to ESG equivalents, no distinction between negative screening, positive screening, and impact investing.

**Why it matters:** ESG investing is a growing priority for UK investors. The current single boolean flag is too crude to provide meaningful guidance.

**Data needed:**
- `esg_preference` (existing boolean) -- ideally expanded to: none, light_screen, positive_tilt, impact_focused, exclusion_based
- `holdings.esg_rating` -- would need holding-level ESG data
- `exclusion_preferences` -- sectors to avoid (fossil fuels, weapons, tobacco, gambling)

**Decision logic:**
```
If esg_preference = true:
+-- For each waterfall recommendation:
    Append note: "Look for funds with an ESG mandate or sustainability label
    when selecting investments for your {wrapper}."

+-- For GIA/ISA recommendations:
    note: "ESG-focused funds are available across all major UK platforms.
          Look for funds labelled as 'Article 8' or 'Article 9' under
          the UK Sustainability Disclosure Requirements."
```

**Priority:** LOW -- The existing boolean is minimally functional. A richer ESG framework would require significant data infrastructure. Low priority for the decision tree but may be important for user engagement.

---

### 2.8 Decumulation Strategy Bridge
**What is missing:** The tree has no explicit decumulation logic. When a user is IN retirement (not just approaching it), the waterfall should shift from accumulation to decumulation: which accounts to draw from first, natural yield vs capital drawdown, and tax-efficient withdrawal ordering.

**Why it matters:** The waterfall currently assumes accumulation mode. A retired user with surplus income from pensions and state pension may need guidance on: "You have excess income -- where should you save it?" which is different from "You have surplus income from employment."

**Data needed:**
- `employment_status` = retired
- `pension_income` (drawdown amount, state pension, DB pension)
- `total_income_in_retirement` vs `expenditure`
- `portfolio_value` and composition

**Decision logic:**
```
If employment_status = 'retired' AND has investment portfolio:
+-- Surplus exists?
    YES -> Standard waterfall, but:
    - Skip pension contribution if age >= 75
    - Prioritise ISA (no income tax on withdrawals)
    - Note: "In retirement, ISA becomes the primary wrapper as
      withdrawals are completely tax-free."

+-- Deficit exists (expenditure > income)?
    YES -> New path: DRAWDOWN WATERFALL
    - Draw from GIA first (use CGT exemption)
    - Then draw from ISA
    - Pension drawdown last (or alongside to use personal allowance)
    - Note: "Draw from GIA first, using your 3,000 annual CGT
      exemption. Preserve ISA and pension wrapper benefits."
```

**Priority:** MEDIUM -- This bridges to the retirement module. The decision tree currently has no retired-user path beyond "age >= 75, cannot contribute to pension."

---

### 2.9 Investment Consolidation
**What is missing:** No recommendation for consolidating multiple accounts of the same type. A user with 4 separate S&S ISAs from previous years, or 3 old workplace pensions, receives no consolidation guidance.

**Why it matters:** Multiple small accounts across providers means: higher total fees, administrative burden, difficulty tracking overall portfolio allocation, and inability to rebalance efficiently.

**Data needed:**
- Count of accounts by type (ISA, GIA, pension)
- Value per account
- Platform per account

**Decision logic:**
```
SCAN 10: ACCOUNT CONSOLIDATION (new Transfer Scan)
|
+-- > 2 ISA accounts across different providers?
    YES -> INFO: "You have {count} ISA accounts across {providers}. Consider
           consolidating via ISA transfer to reduce fees and simplify management.
           ISA transfers preserve your tax-free status."

+-- > 2 non-workplace pensions (SIPPs)?
    YES -> INFO: "You have {count} pension accounts. Consolidating into a
           single SIPP can reduce fees and make drawdown planning simpler."
    Note: "Check for exit penalties and any guaranteed benefits (GAR, protected
          tax-free cash) before transferring."

+-- Any account with value < 1,000 AND not goal-linked?
    YES -> INFO: "Your {account} has a balance of {value}. Small balances
           may be eroded by platform fees. Consider consolidating."
```

**Priority:** MEDIUM -- Common planning advice. Data already exists in the account model.

---

## 3. Missing Safety Checks

### 3.1 Student Loan Impact
**What is missing:** Student loans are excluded from debt checks (`excluding mortgage and student_loan`) but their impact on disposable income is not factored into the surplus calculation. Plan 1/2/4/5 repayments can significantly reduce net income.

**Why it matters:** A user on Plan 2 with a 45,000 salary repays 9% of income above 27,295, which is approximately 1,593/year or 133/month. This directly reduces the surplus available for investment but is not captured in the `net_monthly_income` estimate (`gross * 0.7 / 12`).

**Data needed:**
- `student_loan_plan` (1, 2, 4, 5, postgrad, none)
- `student_loan_balance` (to determine if repayment is active)
- Repayment thresholds per plan (from TaxConfigService)

**Decision logic:**
```
In UserContextBuilder, adjust net_monthly_income:
+-- Has active student loan?
    YES -> Deduct estimated monthly repayment from net_monthly_income
           Plan 1: 9% of income above 24,990
           Plan 2: 9% of income above 27,295
           Plan 4: 9% of income above 31,395
           Plan 5: 9% of income above 25,000
           Postgrad: 6% of income above 21,000

    Surface as INFO in Safety Checks:
    "Your Plan {plan} student loan repayments of approximately {amount}/month
     reduce your available surplus. This has been factored into your
     contribution recommendations."
```

**Priority:** HIGH -- Directly affects surplus accuracy. The current `gross * 0.7` estimate does not account for student loan deductions, which can be 100-300/month for many younger users. This is the demographic most likely to use a digital financial planning tool.

---

### 3.2 Childcare Costs
**What is missing:** No consideration of childcare costs as a drain on disposable income. Childcare is often the single largest household expenditure for families with young children (average 1,000-2,000/month for full-time nursery).

**Why it matters:** If childcare costs are not captured in `monthly_expenditure`, the surplus calculation will be dramatically overstated. If they ARE captured in expenditure, this check is less critical -- but a note about childcare ending (children starting school at 4/5) would be useful for projections.

**Data needed:**
- `has_dependents` (existing)
- `youngest_dependent_age` (existing)
- `monthly_expenditure` should already include childcare if entered correctly

**Decision logic:**
```
In Safety Checks, after emergency fund:
+-- has_dependents AND youngest_dependent_age < 5
    AND monthly_expenditure seems low relative to income?
    YES -> INFO: "With dependents under 5, check that your monthly expenditure
           includes childcare costs. These can be 1,000-2,000/month and
           significantly affect your investment capacity."

+-- youngest_dependent_age approaching school age (4-5)?
    YES -> INFO: "When your youngest starts school, reduced childcare costs
           may free up additional capacity for investment."
```

**Priority:** LOW -- If the user has entered accurate expenditure, this is already captured. Only relevant as a sanity check / prompt.

---

### 3.3 Known Future Expenditure Commitments
**What is missing:** The life events system captures one-off events (wedding, large purchase) but not recurring future commitments that will change the expenditure profile (e.g., school fees starting in 2 years, mortgage rate resetting from fixed to variable).

**Why it matters:** A user whose fixed-rate mortgage expires in 12 months could see monthly payments increase by 500+. If this isn't captured, the surplus used for waterfall recommendations will be overstated once the rate resets.

**Data needed:**
- `mortgage.fixed_rate_end_date` (may already exist)
- `mortgage.current_rate` vs estimated revert rate
- School fee start dates (from education_fees life event)

**Decision logic:**
```
In Safety Checks or Life Event Assessment:
+-- Mortgage fixed rate ending within 12 months?
    YES -> WARN: "Your fixed-rate mortgage expires on {date}. Monthly
           payments may increase by approximately {increase} at the
           current revert rate. Factor this into your investment plans."
    surplus_adjustment: reduce by estimated increase amount

+-- Education fees starting within 12 months (from life event)?
    YES -> Already handled by education_fees life event
```

**Priority:** MEDIUM -- Mortgage rate reset is a genuinely significant risk that could make waterfall recommendations unaffordable. The mortgage data likely exists.

---

### 3.4 Relationship Financial Vulnerability
**What is missing:** No assessment of financial dependency risk. If one partner earns 100% of household income and has no life or income protection, the investment recommendations may be premature.

**Why it matters:** The protection safety check (S9) is lightweight -- it only checks if dependents exist and prompts a generic protection review. It doesn't quantify the gap or block investment if protection is dangerously inadequate.

**Data needed:**
- `spouse.gross_annual_income` (existing)
- `user.gross_annual_income` (existing)
- `protection_profiles` (life cover, income protection amounts)
- `mortgage_outstanding` (existing)

**Decision logic:**
```
In Safety Check 3 (Protection Gaps), enhance:
+-- has_dependents AND one_earner_household?
    AND (life_cover < 5x gross_income OR no_income_protection)?
    YES -> HIGH: "Your household relies on a single income of {income}.
           Without adequate life cover (currently {cover} vs recommended
           {target}) and income protection, investing surplus should
           be secondary to protection."
    surplus_effect: reduce by 25%
```

**Priority:** MEDIUM -- The protection check exists but is weak. Strengthening it doesn't require new data, just more detailed analysis of existing protection data.

---

## 4. Missing Life Events

### 4.1 Starting a Business
**What is missing:** No life event type for starting a business. A user transitioning from employment to self-employment faces significant changes: loss of employer pension matching, need for larger emergency fund, potential for irregular income, and initial business funding needs.

**Why it matters:** This is distinct from "career_change" (which exists) because starting a business has specific implications: self-employed emergency fund target is 9 months (vs 6), employer match is lost, and the user may need liquidity for business capital.

**Data needed:**
- `event_type = 'starting_business'` (not in current enum)
- `business_funding_needed` (amount needed for startup)

**Decision logic:**
```
type = "starting_business"
|-- action: TRIGGER
|-- employment_status_override: self_employed (raises emergency fund to 9 months)
|-- liquidity_priority: true
|-- affordability_override: true
|-- blocked_wrappers: offshore_bond, onshore_bond, vct, eis, seis
|-- Sub-actions:
|   +-- review_emergency_fund (increase to 9-12 months)
|   +-- review_pension_loss (employer match ending)
|   +-- consider_sipp (self-employed pension planning)
|-- Message: "Starting a business changes your investment priorities.
|   Build emergency reserves to 9-12 months, account for lost employer
|   pension contributions, and ensure sufficient liquidity for business needs."
```

**Priority:** MEDIUM -- Distinct from career_change. Affects emergency fund target, pension strategy, and liquidity needs.

---

### 4.2 Emigration / Returning to UK
**What is missing:** No handling for users who are emigrating or have recently returned to the UK. ISA eligibility requires UK residency, pension access rules change, and CGT crystallisation events may apply on departure.

**Why it matters:** A user planning to emigrate should be advised to maximise ISA contributions before leaving (cannot contribute while non-resident), consider CGT position on UK assets, and understand pension access from overseas. A user returning to the UK regains ISA eligibility and may have overseas assets to repatriate.

**Data needed:**
- `event_type = 'emigration'` or `'returning_to_uk'` (not in current enum)
- `uk_resident` (existing)
- Target country (for double tax treaty implications)

**Decision logic:**
```
type = "emigration"
|-- action: BLOCK
|-- blocked_wrappers: all ISA types (cannot contribute when non-resident)
|-- Sub-actions:
|   +-- maximise_isa_before_departure
|   +-- review_cgt_position (departure CGT may apply)
|   +-- review_pension_access (still accessible from overseas)
|   +-- review_uk_tax_status
|-- Message: "Emigration ends your ISA eligibility. Maximise contributions
|   before departure. Existing ISA holdings can remain invested. Review
|   your CGT position on UK assets."

type = "returning_to_uk"
|-- action: TRIGGER
|-- prioritised_wrappers: stocks_shares_isa, pension
|-- Sub-actions:
|   +-- open_isa (regain eligibility)
|   +-- review_overseas_assets (repatriation)
|   +-- check_double_tax_relief
|-- Message: "Returning to the UK restores ISA eligibility. Consider
|   maximising ISA contributions and reviewing any overseas assets
|   for tax-efficient repatriation."
```

**Priority:** LOW -- Edge case. The `uk_resident` flag exists but the life event types don't.

---

### 4.3 Receiving Pension Commencement Lump Sum (PCLS)
**What is missing:** The `pension_lump_sum` life event exists and is grouped with other windfall events. However, PCLS (tax-free lump sum, typically 25% of pension fund) has specific decision logic that differs from a generic windfall: it is already tax-free (so ISA wrapper advantage is diminished for the tax-free portion), and it often coincides with the start of drawdown.

**Why it matters:** A 200,000 pension pot generating a 50,000 PCLS has different investment implications than a 50,000 bonus. The PCLS recipient is typically at or near retirement, needs income, and should be directed towards accessible, lower-risk investments rather than pensions.

**Data needed:** Already captured via `pension_lump_sum` event type. The distinction is in the handling.

**Decision logic:**
```
Enhanced handling for pension_lump_sum windfall:
+-- User age >= 55 (or 57 from April 2028)?
    YES -> Modify windfall handling:
    - Do NOT prioritise pension (money has just left pension wrapper)
    - Prioritise ISA (shelters growth from further tax)
    - Consider cash reserve for first 2-3 years of drawdown
    - Note: "Your pension commencement lump sum is tax-free. Prioritise
      ISA to shelter future growth, and consider keeping 2-3 years
      of income need in accessible cash."
```

**Priority:** MEDIUM -- The event type exists but the handling is generic. A PCLS is a fundamentally different windfall than a bonus.

---

### 4.4 Children Leaving Home
**What is missing:** No life event for children becoming financially independent (leaving home, finishing university). This reduces household expenditure and protection needs, potentially freeing up significant surplus for investment.

**Why it matters:** An empty nest can free up 500-1,000+/month in reduced expenditure. The user's protection needs decrease (fewer dependents), and the investment horizon may shift to retirement focus.

**Data needed:**
- `event_type = 'child_leaving_home'` (not in current enum)
- `number_of_dependents` reduction

**Decision logic:**
```
type = "child_leaving_home"
|-- action: TRIGGER
|-- Sub-actions:
|   +-- review_expenditure (reduced household costs)
|   +-- review_protection_cover (fewer dependents)
|   +-- increase_pension_contributions (freed-up surplus)
|-- Message: "Reduced household costs may free up additional surplus
|   for investment. Review your expenditure, protection needs,
|   and consider increasing pension contributions."
```

**Priority:** LOW -- Could be handled manually by the user updating their expenditure. An explicit event type would be helpful but not critical.

---

### 4.5 Reaching Pension Access Age (55, then 57 from April 2028)
**What is missing:** No derived event for reaching pension access age. The tree handles "approaching retirement" (within 5 years of retirement_age) but not the separate milestone of becoming eligible to access pension funds, which can happen years before planned retirement.

**Why it matters:** A user who reaches 55 (57 from April 2028) can access their pension even if retirement is 10+ years away. This is relevant for: partial drawdown to use personal allowance, PCLS for debt clearance, or MPAA implications if they access flexibly.

**Data needed:**
- `age` (existing)
- `pension_access_age` (currently 55, changing to 57 from 6 April 2028)

**Decision logic:**
```
Derived event (auto-detected):
+-- age is within 2 years of pension_access_age?
    AND age < retirement_age?
    YES -> derive "pension_access_approaching" event
    |-- action: INFO
    |-- Note: "You will be eligible to access your pension from age {access_age}.
    |   Flexible access triggers the Money Purchase Annual Allowance,
    |   reducing your pension annual allowance to 10,000. Consider
    |   whether early access is appropriate."
```

**Priority:** MEDIUM -- The pension access age change (55 to 57) is a known upcoming change. The current tree uses a hard-coded 75 upper limit for pension contributions but doesn't flag the lower access threshold.

---

### 4.6 Caring Responsibilities
**What is missing:** No life event for taking on caring responsibilities (elderly parent, disabled family member). This can reduce income (if reducing work hours), increase expenditure, and change time horizon priorities.

**Why it matters:** Carers may need to reduce working hours or stop working entirely, similar in impact to redundancy but with less financial support. Their emergency fund and protection needs increase.

**Data needed:**
- `event_type = 'caring_responsibilities'` (not in current enum)
- Impact on income and expenditure

**Decision logic:**
```
type = "caring_responsibilities"
|-- action: TRIGGER
|-- liquidity_priority: true
|-- Sub-actions:
|   +-- review_emergency_fund (increase target if income reduced)
|   +-- review_carer_benefits (Carer's Allowance, Carer's Credit)
|   +-- review_pension_impact (reduced contributions, NI credits)
|-- Message: "Caring responsibilities may affect your income and investment
|   capacity. Check eligibility for Carer's Allowance and Carer's Credit
|   (protects your State Pension entitlement)."
```

**Priority:** LOW -- Important for those affected but a relatively small subset of users.

---

## 5. Missing Transfer Scans

### 5.1 ISA Consolidation
**What is missing:** No scan for consolidating multiple ISAs from previous tax years. A user may have cash ISAs, S&S ISAs, and LISAs across multiple providers from different years.

**Why it matters:** Multiple ISAs across providers means: higher aggregate platform fees, difficulty tracking total ISA portfolio, inability to efficiently rebalance, and administrative burden.

**Data needed:**
- Count of ISA accounts by provider
- ISA values per provider
- Fee structures per provider

**Decision logic:**
```
SCAN: ISA CONSOLIDATION
|
+-- > 2 ISA accounts across > 1 provider?
    AND no ISA has guaranteed rate/bonus that would be lost on transfer?
    YES -> INFO: "You have {count} ISA accounts across {providers}. Consider
           consolidating via ISA-to-ISA transfer to reduce fees and simplify
           your portfolio. Previous years' ISAs can be transferred without
           affecting your current year's allowance."
    Note: "Only transfer to a platform that accepts ISA transfers in specie
          (avoids selling and repurchasing investments)."
```

**Priority:** MEDIUM -- Common scenario. Simple to implement. Already have account data.

---

### 5.2 GIA to Pension (Carry Forward Utilisation)
**What is missing:** No explicit scan for selling GIA assets to fund pension contributions using carry forward. The carry forward step (Step 7) and Bed & ISA scan (Scan 2) exist separately, but there's no combined "sell GIA to fund pension" recommendation.

**Why it matters:** A user with carry forward available and GIA holdings could benefit from selling GIA holdings (using CGT exemption), funding a pension contribution (getting 40%+ tax relief), and reducing future tax drag. This is one of the most valuable tax planning strategies.

**Data needed:**
- `carry_forward_available` (existing)
- `gia_holdings` with unrealised gains (existing)
- `cgt_allowance_remaining` (existing)
- `tax_band` (existing)

**Decision logic:**
```
SCAN: GIA TO PENSION VIA CARRY FORWARD
|
+-- carry_forward_available > 0
    AND gia_total_value > 5,000
    AND tax_band = higher/additional?
    YES -> HIGH: "You have {carry_forward} of unused pension carry forward.
           Consider selling GIA holdings (using your {cgt_allowance}
           CGT exemption) to fund pension contributions at {rate}%
           tax relief. This converts taxable GIA growth into tax-free
           pension growth."
    Note: "Carry forward expires after 3 years -- use oldest year first."
```

**Priority:** HIGH -- Combines two of the most valuable tax planning strategies (carry forward + CGT exemption). The data already exists in the tree.

---

### 5.3 Bed and Breakfast in Pension
**What is missing:** No scan for "Bed and Pension" -- selling holdings in GIA and repurchasing within a pension wrapper. Unlike Bed & ISA, this triggers CGT on the sale but gains the pension tax relief.

**Why it matters:** For higher/additional rate taxpayers with pension allowance remaining, the 40-45% tax relief on the pension contribution can far outweigh the CGT liability (18-24%) on the GIA sale. Net benefit = tax relief - CGT.

**Data needed:**
- `pension_aa_remaining` (existing)
- `gia_holdings` with gains (existing)
- `cgt_allowance_remaining` (existing)
- `tax_band` (existing)

**Decision logic:**
```
SCAN: BED AND PENSION
|
+-- pension_aa_remaining > 0
    AND gia_total_value > 0
    AND tax_band = higher/additional
    AND isa_allowance fully used (or near-fully used)?
    YES -> Calculate: tax_relief = amount * marginal_rate
           CGT_cost = gains * cgt_rate (if gains > exemption)
           net_benefit = tax_relief - CGT_cost
    +-- net_benefit > 0?
        YES -> MEDIUM: "Selling {amount} of GIA holdings and contributing
               to your pension generates {tax_relief} tax relief against
               {cgt_cost} CGT, a net benefit of {net_benefit}."
```

**Priority:** MEDIUM -- More complex than Bed & ISA. Only relevant when ISA allowance is exhausted and pension allowance remains.

---

### 5.4 Trust Asset Distribution
**What is missing:** No scan for trust assets becoming distributable when a beneficiary reaches a trigger age (typically 18 or 25).

**Why it matters:** When a bare trust beneficiary reaches 18, or a discretionary trust's distribution age is reached, assets should be reviewed for tax-efficient distribution (potentially using the beneficiary's lower tax band).

**Data needed:**
- `trust_id` on accounts (existing)
- Trust terms (distribution age trigger) -- not currently stored
- Beneficiary ages

**Decision logic:**
```
SCAN: TRUST DISTRIBUTION TRIGGER
|
+-- Trust-linked account AND beneficiary approaching distribution age?
    YES -> INFO: "The trust beneficiary is approaching the distribution age.
           Review the most tax-efficient method of distributing assets,
           considering the beneficiary's marginal tax rate."
```

**Priority:** LOW -- Requires trust data that may not be fully captured. Edge case.

---

## 6. Missing Spouse Strategies

### 6.1 Pension Sharing on Divorce
**What is missing:** The divorce life event blocks spouse optimisation but doesn't specifically address pension sharing orders. Pension assets are often the second-largest marital asset after property.

**Why it matters:** During divorce, pensions can be: offset (one partner keeps pension, other keeps property), earmarked (attachment order on future benefits), or shared (pension sharing order splits the fund). Each has different investment implications.

**Data needed:**
- `divorce` life event (existing)
- Pension values for both parties
- Whether pension sharing order is in place

**Decision logic:**
```
In divorce life event sub-actions, add:
+-- Combined pension value > 50,000?
    YES -> Sub-action: pension_sharing_review
    Message: "Pensions are often the second-largest marital asset. A pension
    sharing order splits the fund; pension offsetting trades pension for
    other assets. Seek independent financial advice on the pension element
    of your divorce settlement."
```

**Priority:** LOW -- Already partially handled by the divorce life event. This would be an additional sub-action.

---

### 6.2 Death Benefit Nominations
**What is missing:** No scan to check whether pension death benefit nominations are current. Pension funds can be passed outside the estate if properly nominated, but nominations lapse or become outdated (e.g., after divorce, new children).

**Why it matters:** A pension fund with an outdated nomination (e.g., to an ex-spouse) could result in assets going to the wrong person. The pension provider's discretion typically follows the latest nomination.

**Data needed:**
- `pension_accounts` (existing)
- `last_nomination_review_date` -- not currently stored
- Family member changes since last review

**Decision logic:**
```
STRATEGY 7: DEATH BENEFIT NOMINATION REVIEW (new Spouse Strategy)
|
+-- Any pension account AND (no nomination date recorded OR nomination > 2 years old)?
    YES -> INFO: "Review your pension death benefit nominations. Pensions
           can pass outside your estate for IHT purposes, but nominations
           should be reviewed after any change in family circumstances."

+-- Divorce or death_of_partner life event in last 2 years?
    YES -> HIGH: "Following your change in circumstances, review all
           pension death benefit nominations urgently."
```

**Priority:** MEDIUM -- Simple to implement. Genuinely important financial planning advice. Could be a sub-action of multiple life events (marriage, divorce, new_baby, death_of_partner).

---

### 6.3 Joint Investment Account Beneficial Ownership
**What is missing:** No strategy for beneficial ownership elections on joint accounts. For married couples, interest and dividends from joint accounts are assumed to be split 50:50 by HMRC unless a Form 17 declaration is made.

**Why it matters:** If one spouse is a basic-rate taxpayer and the other is higher-rate, declaring beneficial ownership as (say) 90:10 in favour of the lower earner can reduce the overall household tax bill on investment income.

**Data needed:**
- Joint investment/savings accounts (existing via `joint_owner_id`)
- Both partners' tax bands (existing)
- Current ownership percentages (existing via `ownership_percentage`)

**Decision logic:**
```
STRATEGY 7b: BENEFICIAL OWNERSHIP DECLARATION
|
+-- Joint savings/investment accounts AND partners in different tax bands?
    AND account generates > 500/year income?
    YES -> INFO: "Your joint accounts generate {income}/year. A Form 17
           declaration to HMRC can change the income split from 50:50
           to match actual beneficial ownership. This could save
           {saving} if more income is allocated to the lower earner."
    Note: "Form 17 only applies to income from property. For savings
          interest, the split follows actual beneficial ownership
          without a formal declaration."
```

**Priority:** LOW -- Niche. The interaction between Form 17 (property income), beneficial ownership (savings), and dividend income is complex and may be better handled by the coordination module.

---

## 7. Regulatory / Compliance Considerations

### 7.1 FCA Consumer Duty
**What is missing:** No explicit compliance checks against FCA Consumer Duty requirements. Under Consumer Duty (effective July 2023), firms must act to deliver good outcomes for retail customers, focusing on: products and services, price and value, consumer understanding, and consumer support.

**Why it matters:** While Fynla is a planning tool (not a regulated adviser), the recommendations it generates should align with Consumer Duty principles. Specifically: recommendations should not suggest products the user doesn't understand, fee impact should be transparent, and vulnerable customers should receive additional safeguards.

**Data needed:**
- `knowledge_level` (existing on risk profile)
- `investment_experience` (existing)
- Vulnerability indicators (age, recent life events, financial distress)

**Decision logic:**
```
Compliance layer (applied to all recommendations):
+-- investment_experience = 'none' AND recommendation is for bonds/VCT/EIS?
    YES -> Appropriateness warning: "This recommendation involves a complex
           product. Consider seeking independent financial advice."

+-- User shows vulnerability indicators (recent bereavement, redundancy,
    serious illness, very young, very old)?
    YES -> All recommendations get additional note: "Given your current
           circumstances, consider speaking to a financial adviser before
           making significant investment decisions."
```

**Priority:** MEDIUM -- This is not strictly a gap in the decision tree but a governance layer. The existing skip conditions for bonds and VCT/EIS partially address this (experience gates), but there's no explicit vulnerability check.

---

### 7.2 Suitability Alignment Check
**What is missing:** No post-waterfall check that the overall recommendation set aligns with the user's risk profile. The waterfall determines wrapper priority but doesn't verify that the combined effect of all recommendations produces a portfolio consistent with the stated risk level.

**Why it matters:** A cautious investor could end up with recommendations for S&S ISA, pension (equity-heavy), and VCT -- all equity-like investments. While each individual wrapper recommendation may be correct, the aggregate may exceed the user's risk tolerance.

**Data needed:**
- `risk_level` (existing)
- Recommendation set from waterfall
- Expected asset allocation of recommended wrappers

**Decision logic:**
```
Post-waterfall suitability check:
+-- risk_level = 'low' AND total equity-oriented recommendations > 50% of surplus?
    YES -> Note on all equity recommendations: "Based on your low risk profile,
           consider cash or bond funds within these wrappers rather than
           equity funds."

+-- risk_level = 'high' AND significant cash recommendations (not emergency fund)?
    YES -> Note: "Based on your higher risk tolerance and long time horizon,
           you may want to minimise cash holdings beyond your emergency fund."
```

**Priority:** MEDIUM -- Currently, the tree recommends wrappers but not what goes in them. A suitability alignment check bridges this gap.

---

### 7.3 Ongoing Review Triggers
**What is missing:** No recommendation for when to review. All recommendations are generated at a point in time with no guidance on when they should be reassessed.

**Why it matters:** Financial plans become stale. A tax year ending, a life event occurring, or a significant market movement should trigger a recommendation refresh.

**Data needed:**
- `last_recommendation_date` -- not currently stored
- Current date vs tax year end
- Portfolio drift since last review

**Decision logic:**
```
Always include in output:
+-- Within 3 months of tax year end (Jan-Mar)?
    YES -> Note: "Tax year ends on 5 April. Review your ISA, pension,
           and CGT position before the deadline."

+-- Last review > 12 months ago?
    YES -> Note: "It has been over 12 months since your last review.
           Circumstances and tax rules may have changed."

+-- Significant life event since last review?
    YES -> Note: "Your recent {event} may have changed your investment
           priorities. Review your recommendations."
```

**Priority:** HIGH -- Low implementation effort, high user value. The current tree generates recommendations but never tells the user when to come back and check again.

---

## 8. Constants / Thresholds That May Need Updating

### 8.1 Current Thresholds (2025/26 Tax Year) -- Verified Correct

| Threshold | Tree Value | 2025/26 Actual | Status |
|-----------|-----------|----------------|--------|
| ISA annual limit | 20,000 | 20,000 | CORRECT |
| LISA annual limit | 4,000 | 4,000 | CORRECT |
| LISA property price limit | 450,000 | 450,000 | CORRECT |
| Pension AA | 60,000 | 60,000 | CORRECT |
| MPAA | 10,000 | 10,000 | CORRECT |
| Personal allowance | 12,570 | 12,570 | CORRECT (frozen to 2028) |
| Basic rate band | < 50,270 | < 50,270 | CORRECT (frozen to 2028) |
| Higher rate band | < 125,140 | < 125,140 | CORRECT |
| CGT annual exemption | 3,000 | 3,000 | CORRECT |
| CGT rate (basic) | 18% | 18% | CORRECT |
| CGT rate (higher/additional) | 24% | 24% | CORRECT |
| PSA (basic) | 1,000 | 1,000 | CORRECT |
| PSA (higher) | 500 | 500 | CORRECT |
| PSA (additional) | 0 | 0 | CORRECT |
| Dividend allowance | 500 | 500 | CORRECT |
| IHT NRB | 325,000 | 325,000 | CORRECT (frozen to 2030) |
| IHT RNRB | 175,000 | 175,000 | CORRECT (frozen to 2030) |
| Marriage Allowance transfer | 1,257 | 1,257 | CORRECT |
| Marriage Allowance saving | 252 | 252 | CORRECT |
| Non-earner pension gross | 3,600 | 3,600 | CORRECT |
| Premium Bonds max | 50,000 | 50,000 | CORRECT |
| Junior ISA limit | 9,000 | 9,000 | CORRECT |
| PA taper threshold | 100,000 | 100,000 | CORRECT |
| Pension AA taper (threshold) | 200,000 | 200,000 | CORRECT |
| Pension AA taper (adjusted) | 260,000 | 260,000 | CORRECT |

### 8.2 Missing Thresholds

| Threshold | Value | Where Needed |
|-----------|-------|-------------|
| Pension access age (current) | 55 | Pension access age derived event |
| Pension access age (from 6 Apr 2028) | 57 | Future-proofing pension access logic |
| State pension age | 66 (rising to 67 by 2028, 68 TBC) | Retirement proximity |
| Auto-enrolment minimum (employee) | 5% | Employer match safety check context |
| Auto-enrolment minimum (employer) | 3% | Employer match safety check context |
| Auto-enrolment minimum (total) | 8% | Employer match safety check context |
| SEIS annual investment limit | 200,000 | VCT/EIS/SEIS split (if implementing 1.10) |
| EIS annual investment limit | 1,000,000 (2m for knowledge-intensive) | VCT/EIS/SEIS split |
| VCT annual investment limit | 200,000 | VCT/EIS/SEIS split |
| Student loan Plan 1 threshold | 24,990 | Student loan safety check (if implementing 3.1) |
| Student loan Plan 2 threshold | 27,295 | Student loan safety check |
| Student loan Plan 4 threshold | 31,395 | Student loan safety check |
| Student loan Plan 5 threshold | 25,000 | Student loan safety check |
| Postgrad loan threshold | 21,000 | Student loan safety check |
| High Income Child Benefit Charge threshold | 50,000 (tapered to 60,000) | Already referenced in LE9a but threshold not in constants |
| HICBC full clawback | 60,000 | Needed for complete HICBC logic |

### 8.3 Upcoming Changes to Flag

| Change | Date | Impact |
|--------|------|--------|
| Pension access age: 55 to 57 | 6 April 2028 | Affects PCLS timing and MPAA trigger |
| State pension age: 66 to 67 | By 2028 | Affects approaching_retirement derived event |
| IHT reforms (Autumn Statement 2024) | From April 2027 | Pensions may be brought into IHT calculation |
| ISA reforms (potential simplification) | TBC | May affect LISA/ISA/Cash ISA distinction |
| CGT rate changes from Oct 2024 Budget | Already in effect | 18%/24% rates are current (previously 10%/20%) |

---

## 9. Priority Summary

### HIGH Priority (should be in the tree)
1. **Asset Allocation / Diversification Analysis** (2.1) -- Most significant gap. Services exist.
2. **Tax Loss Harvesting Scan** (2.3) -- Service exists (CGTHarvestingCalculator). Time-sensitive, saves real tax.
3. **Rebalancing Recommendations** (2.4) -- Services exist (DriftAnalyzer, RebalancingCalculator). Core discipline.
4. **Student Loan Impact on Surplus** (3.1) -- Directly affects surplus accuracy for younger users.
5. **GIA to Pension via Carry Forward** (5.2) -- Combines two high-value strategies. Data exists.
6. **Ongoing Review Triggers** (7.3) -- Low effort, high value.

### MEDIUM Priority (should be considered)
1. **ETF/Active Fund Fee Analysis** (1.3) -- FeeAnalyzer exists, needs wiring.
2. **SEIS Separate Treatment** (1.10) -- Different relief rates and limits warrant separation.
3. **Pound Cost Averaging vs Lump Sum** (2.2) -- Common user question. Data exists.
4. **Platform Consolidation** (2.5) -- PlatformComparator exists.
5. **Sequencing Risk** (2.6) -- Bridges to retirement module.
6. **Decumulation Strategy** (2.8) -- No retired-user path in waterfall.
7. **Investment Consolidation** (2.9) -- Common planning advice.
8. **Mortgage Rate Reset Warning** (3.3) -- Significant surplus impact.
9. **Protection Gap Strengthening** (3.4) -- Existing check is too weak.
10. **Starting a Business** (4.1) -- Distinct from career_change.
11. **PCLS Enhanced Handling** (4.3) -- Event type exists but handling is generic.
12. **Pension Access Age Milestone** (4.5) -- Known upcoming change.
13. **ISA Consolidation Scan** (5.1) -- Common scenario.
14. **Bed and Pension** (5.3) -- Valuable for higher-rate taxpayers.
15. **Death Benefit Nominations** (6.2) -- Simple, important advice.
16. **FCA Consumer Duty Compliance Layer** (7.1) -- Governance overlay.
17. **Suitability Alignment Check** (7.2) -- Wrapper vs content gap.

### LOW Priority (informational / niche)
1. **IFISA** (1.1) -- Market contracting.
2. **Investment Trusts** (1.2) -- Requires new data fields.
3. **Absolute Return Funds** (1.4) -- Fund selection, not wrapper.
4. **Property Funds / REITs** (1.5) -- Asset allocation concern.
5. **Gold / Commodities** (1.6) -- Asset allocation concern.
6. **Cryptocurrency** (1.7) -- Warning note only.
7. **Friendly Society Plans** (1.8) -- Trivial amounts.
8. **NS&I Certificates** (1.9) -- Rare edge case.
9. **ESG Enhancement** (2.7) -- Requires new data infrastructure.
10. **Childcare Costs** (3.2) -- Should be in expenditure already.
11. **Emigration** (4.2) -- Edge case.
12. **Children Leaving Home** (4.4) -- Manual update sufficient.
13. **Caring Responsibilities** (4.6) -- Small subset of users.
14. **Trust Distribution** (5.4) -- Requires trust data.
15. **Pension Sharing on Divorce** (6.1) -- Already partially covered.
16. **Beneficial Ownership** (6.3) -- Niche, complex.

---

## 10. Structural Observations

### 10.1 The Wrapper vs Content Gap
The single most important structural observation is that the decision tree is excellent at determining **WHERE** to put money (which wrapper) but says almost nothing about **WHAT** to invest in within those wrappers. This is by design (the tree is a contribution waterfall), but the gap means:

- A user following all recommendations could end up 100% in UK equities
- No asset class guidance is provided alongside wrapper recommendations
- The ModelPortfolioBuilder, DiversificationAnalyzer, and AssetAllocationOptimizer services exist but are disconnected from the recommendation engine

Adding a post-waterfall "Portfolio Health Check" phase (as described in 2.1 and 2.4) would bridge this gap without changing the waterfall's wrapper-focused architecture.

### 10.2 Missing Retired-User Path
The tree assumes accumulation mode. The only retirement-related logic is:
- Approaching retirement (within 5 years): glide path note
- Age >= 75: pension contributions blocked
- MPAA triggered: ISA priority boost

There is no path for users who are already retired and in drawdown. This should be a separate entry point into the waterfall, or a pre-waterfall branch that modifies the waterfall's priorities for decumulation.

### 10.3 No Tax Year Awareness
The tree references "tax year end" in one place (ISA urgency note in Step 2) but has no systematic tax-year-end planning. A comprehensive tree should intensify certain recommendations (use CGT exemption, maximise ISA, review carry forward expiry) as the tax year end approaches.
