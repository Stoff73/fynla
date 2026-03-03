# Fynla Financial Planning Application - Complete Feature Documentation

**Version**: v0.7.0
**Last Updated**: February 6, 2026
**Modules**: Protection, Savings, Investment, Retirement, Estate Planning, Goals, Net Worth, User Profile

---

## Table of Contents

1. [Protection Module](#1-protection-module)
2. [Savings Module](#2-savings-module)
3. [Investment Module](#3-investment-module)
4. [Retirement Module](#4-retirement-module)
5. [Estate Planning Module](#5-estate-planning-module)
6. [Goals Module](#6-goals-module)
7. [Net Worth Module](#7-net-worth-module)
8. [User Profile Module](#8-user-profile-module)
9. [Dashboard & Holistic Planning](#9-dashboard--holistic-planning)
10. [Authentication & Security](#10-authentication--security)
11. [Onboarding & Preview Mode](#11-onboarding--preview-mode)
12. [Admin & Management](#12-admin--management)
13. [Documents & Data Import](#13-documents--data-import)
14. [GDPR & Data Privacy](#14-gdpr--data-privacy)
15. [Trusts Management](#15-trusts-management)
16. [Tax Management](#16-tax-management)
17. [Coordination & Conflict Resolution](#17-coordination--conflict-resolution)
18. [Risk Management](#18-risk-management)
19. [Business Interests & Chattels](#19-business-interests--chattels)
20. [Spouse Linking & Joint View](#20-spouse-linking--joint-view)

---

## 1. Protection Module

### 1.1 Life Insurance Policy Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Life Insurance Policy Management |
| **Category** | Protection |
| **What it does** | Allows users to add, edit, view, and delete life insurance policies. Tracks policy details including provider, coverage amount (sum assured), monthly/annual premiums, policy type (term, whole life, decreasing term), start date, end date, and beneficiary information. Supports multiple policies per user with status tracking (active, lapsed, claimed). |
| **Why it exists** | Life insurance is a fundamental protection tool for UK families. This feature enables users to maintain a comprehensive record of all life cover, ensuring they understand their total protection level and can identify gaps in coverage relative to their family's needs. |
| **Integrates with** | Protection Needs Calculator, Coverage Gap Analysis, Estate Planning (IHT life cover assessment), Net Worth Module, Protection Recommendations Engine, Family Members data |

---

### 1.2 Critical Illness Coverage Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Critical Illness Coverage Tracking |
| **Category** | Protection |
| **What it does** | Records critical illness insurance policies with sum assured amounts, premiums, covered conditions, deferred periods, and policy terms. Tracks whether cover is standalone or combined with life insurance. Monitors policy status and renewal dates. |
| **Why it exists** | Critical illness cover provides a tax-free lump sum on diagnosis of specified serious illnesses. UK families need to track this protection separately as it serves different purposes than life cover - paying off mortgages, funding treatment, or replacing income during recovery. |
| **Integrates with** | Protection Needs Calculator, Coverage Gap Analysis, What-If Scenarios (critical illness scenario), Mortgage data, Income data |

---

### 1.3 Income Protection Policy Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Income Protection Policy Management |
| **Category** | Protection |
| **What it does** | Tracks income protection policies including monthly benefit amounts, benefit period (short-term or long-term), deferred/waiting periods, definition of incapacity (own occupation, suited occupation, any occupation), and premium payment details. Calculates replacement ratio against current income. |
| **Why it exists** | Income protection replaces a percentage of income if unable to work due to illness or injury. This feature helps users understand whether their income protection adequately covers their expenses during periods of incapacity, which is crucial for maintaining financial stability. |
| **Integrates with** | User Profile (income data), Expenditure data, Protection Needs Calculator, What-If Scenarios (disability scenario), Affordability Analysis |

---

### 1.4 Disability Insurance Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Disability Insurance Tracking |
| **Category** | Protection |
| **What it does** | Records disability insurance policies with coverage types, benefit amounts, and policy terms. Distinguishes between short-term and long-term disability cover, tracks coverage levels, and monitors policy status. |
| **Why it exists** | Disability insurance provides financial protection against the risk of becoming unable to work. This feature enables users to see their total disability protection and ensure adequate coverage for their circumstances. |
| **Integrates with** | Income Protection, Protection Needs Calculator, What-If Scenarios, Coverage Gap Analysis |

---

### 1.5 Protection Needs Calculator

| Attribute | Details |
|-----------|---------|
| **Feature** | Protection Needs Calculator |
| **Category** | Protection |
| **What it does** | Calculates recommended protection levels based on user's financial situation. Considers: annual income (all sources), monthly expenditure, number and ages of dependents, years to retirement, outstanding mortgage balance, other debt obligations, existing savings, and desired coverage period. Outputs recommended life cover, critical illness cover, and income protection amounts. |
| **Why it exists** | Most UK households are underinsured. This calculator provides personalised, evidence-based protection recommendations so users understand exactly how much cover they need rather than relying on arbitrary multiples of salary. |
| **Integrates with** | User Profile (income, expenditure), Family Members, Mortgage data, Savings data, Retirement data (retirement age), Protection policies, Coverage Gap Analysis |

---

### 1.6 Coverage Gap Analysis

| Attribute | Details |
|-----------|---------|
| **Feature** | Coverage Gap Analysis |
| **Category** | Protection |
| **What it does** | Compares existing protection coverage against calculated needs. Identifies gaps for life cover, critical illness, and income protection. Quantifies shortfall amounts and expresses gaps as percentages. Highlights policies nearing expiration or with inadequate terms. |
| **Why it exists** | Knowing you have insurance policies isn't enough - users need to understand whether those policies actually provide adequate protection. This analysis reveals specific coverage deficiencies requiring action. |
| **Integrates with** | Protection Needs Calculator, All protection policies, Recommendations Engine, Adequacy Scoring, What-If Scenarios |

---

### 1.7 Protection Adequacy Scoring - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Protection Adequacy Scoring |
| **Category** | Protection |
| **What it does** | Generates a 0-100 adequacy score based on coverage completeness. Evaluates life cover adequacy, critical illness adequacy, income protection adequacy, and policy quality factors. Provides insights explaining score components and improvement opportunities. |
| **Why it exists** | A single score makes complex protection analysis accessible. Users can quickly understand their overall protection status and track improvements over time, while detailed insights guide specific actions. |
| **Integrates with** | Coverage Gap Analysis, Protection Needs Calculator, All protection policies, Dashboard overview cards |

---

### 1.8 Protection What-If Scenarios - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Protection What-If Scenarios |
| **Category** | Protection |
| **What it does** | Models financial outcomes under different protection events: death scenarios (immediate family impact, survivor income, lump sum needs), critical illness scenarios (treatment costs, income loss, mortgage impact), disability scenarios (short-term vs long-term, benefit adequacy), and premium change impacts. Shows current coverage vs needs under each scenario. |
| **Why it exists** | Abstract protection needs become tangible when users see concrete scenario outcomes. This feature demonstrates the real-world impact of protection gaps and motivates appropriate action. |
| **Integrates with** | All protection policies, Income data, Expenditure data, Mortgage data, Family Members, Savings data, Estate Planning |

---

### 1.9 Protection Recommendations Engine

| Attribute | Details |
|-----------|---------|
| **Feature** | Protection Recommendations Engine |
| **Category** | Protection |
| **What it does** | Generates personalised protection recommendations based on coverage gaps, family circumstances, and affordability. Prioritises recommendations by urgency and impact. Includes specific actions: increase life cover by £X, add critical illness cover, extend income protection period, etc. |
| **Why it exists** | Identifying problems without solutions isn't helpful. This engine transforms gap analysis into actionable recommendations users can implement, prioritised to address the most critical needs first. |
| **Integrates with** | Coverage Gap Analysis, Adequacy Scoring, Affordability Analysis (surplus income), Family Members, Coordinating Agent (cross-module prioritisation) |

---

### 1.10 Protection Scenario Builder - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Protection Scenario Builder |
| **Category** | Protection |
| **What it does** | Creates detailed financial scenarios for various protection events. Models income continuation, expense coverage, asset liquidation needs, family financial timeline, and long-term sustainability. Compares current state with scenarios including recommended protection improvements. |
| **Why it exists** | Enables users to explore "what if" situations in depth, understanding the full financial cascade of protection events and the value of adequate coverage. |
| **Integrates with** | What-If Scenarios, All financial modules, Family Members, Cash flow projections |

---

## 2. Savings Module

### 2.1 Savings Account Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Savings Account Tracking |
| **Category** | Savings |
| **What it does** | Records multiple savings accounts with current balance, interest rate (AER), account type (easy access, notice, fixed term, regular saver), institution name, account name/number, and maturity dates for fixed-term accounts. Tracks contribution history and balance changes over time. |
| **Why it exists** | UK savers often have multiple accounts across different providers. Centralising this information provides a complete picture of liquid savings and enables comparison and optimisation. |
| **Integrates with** | Emergency Fund Analysis, ISA Allowance Tracker, Liquidity Profiling, Net Worth Module, Goals Module, Rate Comparison |

---

### 2.2 Emergency Fund Analysis - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Emergency Fund Analysis |
| **Category** | Savings |
| **What it does** | Calculates emergency fund adequacy against the standard 6-month expenditure benchmark. Computes current runway in months, identifies shortfall amount, calculates required monthly top-up to reach target, and provides an adequacy score. Considers only highly liquid savings (instant/easy access). |
| **Why it exists** | Emergency funds are the foundation of financial security. This analysis ensures users maintain adequate liquid reserves before pursuing other financial goals, following UK financial planning best practices. |
| **Integrates with** | Savings accounts (instant/easy access), User expenditure data, Liquidity Profiling, Savings Recommendations, Goals Module, Coordinating Agent |

---

### 2.3 Liquidity Profiling - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Liquidity Profiling |
| **Category** | Savings |
| **What it does** | Categorises all savings by accessibility: instant access (immediate withdrawal), easy access (7-30 day notice), medium-term (3-12 month notice/term), fixed-term (12+ months). Calculates totals and percentages in each category. Identifies concentration risks. |
| **Why it exists** | Not all savings are equally accessible. This profiling helps users understand their true liquidity position and ensures they're not over-concentrated in inaccessible funds when they need emergency access. |
| **Integrates with** | Savings accounts, Emergency Fund Analysis, Liquidity Ladder, Cash Flow Analysis, Estate Planning (liquidity for IHT) |

---

### 2.4 Liquidity Ladder Builder - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Liquidity Ladder Builder |
| **Category** | Savings |
| **What it does** | Recommends optimal distribution of savings across liquidity tiers. Suggests amounts for instant access (1-2 months expenses), easy access (2-4 months), medium-term (variable), and longer-term savings. Balances accessibility needs against earning higher interest on less-liquid funds. |
| **Why it exists** | Optimal savings allocation maximises interest earnings while maintaining necessary liquidity. This tool automates the ladder strategy commonly recommended by UK financial advisers. |
| **Integrates with** | Liquidity Profiling, Emergency Fund Analysis, Expenditure data, Savings accounts, Rate Comparison |

---

### 2.5 ISA Allowance Tracker

| Attribute | Details |
|-----------|---------|
| **Feature** | ISA Allowance Tracker |
| **Category** | Savings |
| **What it does** | Tracks Cash ISA contributions against the annual £20,000 ISA allowance. Shows current year usage, remaining allowance, and historical ISA balances. Monitors tax year context (April 6 - April 5) and alerts users as the tax year end approaches. |
| **Why it exists** | ISA allowances are "use it or lose it" - unused allowance cannot be carried forward. This tracker ensures users maximise tax-free savings opportunities within the annual limit. |
| **Integrates with** | Savings accounts (ISA type), Tax Configuration Service, Investment Module (Stocks & Shares ISA), Savings Recommendations, Tax year management |

---

### 2.6 Interest Rate Comparison

| Attribute | Details |
|-----------|---------|
| **Feature** | Interest Rate Comparison |
| **Category** | Savings |
| **What it does** | Compares account interest rates against market benchmarks and category averages. Calculates potential additional earnings from switching to higher-rate accounts. Identifies underperforming accounts and quantifies the opportunity cost. |
| **Why it exists** | Savings rate inertia costs UK savers billions annually. This comparison highlights where users are earning below-market rates and quantifies the benefit of switching. |
| **Integrates with** | Savings accounts, Rate Improvement Recommendations, Market benchmark data, Savings Adequacy Scoring |

---

### 2.7 Savings Goals Tracking - ## CSJ notes - this is not shown in the ui, logic works  

| Attribute | Details |
|-----------|---------|
| **Feature** | Savings Goals Tracking |
| **Category** | Savings |
| **What it does** | Creates and tracks individual savings goals with target amount, current progress, target date, monthly contribution amount, and linked savings account. Calculates progress percentage, projected completion date based on current contributions, and required contribution to meet target on time. |
| **Why it exists** | Goal-based saving improves motivation and outcomes. This feature transforms abstract "save more" intentions into concrete, trackable objectives with clear progress indicators. |
| **Integrates with** | Savings accounts, Goals Module, Contribution tracking, Affordability Analysis, Savings Recommendations |

---

### 2.8 Savings Goal Prioritisation - ## CSJ notes _ this is in not sure it works well enough tho

| Attribute | Details |
|-----------|---------|
| **Feature** | Savings Goal Prioritisation |
| **Category** | Savings |
| **What it does** | Ranks savings goals by priority level (critical, high, medium, low) and progress status. Considers goal urgency (time to target date), importance (emergency fund vs discretionary), and achievability. Helps users focus limited resources on most important goals. |
| **Why it exists** | With limited disposable income, users must prioritise competing savings goals. This feature provides a framework for allocation decisions aligned with financial planning principles. |
| **Integrates with** | Savings Goals, Emergency Fund Analysis, Affordability Analysis, Goals Module, Coordinating Agent |

---

### 2.9 Rate Improvement Recommendations - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Rate Improvement Recommendations |
| **Category** | Savings |
| **What it does** | Identifies savings accounts with below-market interest rates and recommends switching. Calculates annual earnings improvement from rate optimisation. Considers account type (can't recommend fixed-term if user needs liquidity) and practical switching factors. |
| **Why it exists** | Many savers remain in legacy accounts paying minimal interest. These recommendations make rate optimisation actionable with specific improvement amounts. |
| **Integrates with** | Interest Rate Comparison, Savings accounts, Liquidity Profiling, Savings Recommendations Engine |

---

### 2.10 Savings What-If Scenarios - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Savings What-If Scenarios |
| **Category** | Savings |
| **What it does** | Models future savings outcomes under different assumptions: increased monthly savings projections, goal achievement timelines at various contribution levels, impact of interest rate changes, and lump sum contribution effects. |
| **Why it exists** | Helps users understand the impact of different savings behaviours and make informed decisions about allocation changes. |
| **Integrates with** | Savings accounts, Savings Goals, Affordability Analysis, Cash Flow projections |

---

## 3. Investment Module

### 3.1 Portfolio Analysis

| Attribute | Details |
|-----------|---------|
| **Feature** | Portfolio Analysis |
| **Category** | Investment |
| **What it does** | Provides comprehensive portfolio metrics including total portfolio value, asset allocation percentages by class (equities, bonds, property, alternatives, cash), return calculations (absolute and percentage returns), diversification scoring (0-100), and period comparisons. Aggregates across all investment accounts. |
| **Why it exists** | Understanding overall portfolio composition is fundamental to investment management. This analysis provides the foundation for allocation decisions, rebalancing, and performance evaluation. |
| **Integrates with** | All investment accounts, Holdings data, Risk metrics, Asset allocation targets, Rebalancing tools, Performance Attribution |

---

### 3.2 Holdings Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Holdings Management |
| **Category** | Investment |
| **What it does** | Tracks individual investment holdings with fund/stock name, ticker/ISIN, current value, quantity/units, purchase price and date, unrealized gains/losses, ongoing charges figure (OCF), total expense ratio (TER), dividend yield, and sector/geography classification. Supports manual entry and statement upload. |
| **Why it exists** | Detailed holdings data enables sophisticated analysis including fee optimisation, tax planning, and diversification assessment. Users need granular visibility into exactly what they own. |
| **Integrates with** | Investment accounts, Portfolio Analysis, Fee Analysis, Tax-Loss Harvesting, Performance tracking, Correlation Analysis |

---

### 3.3 Investment Account Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Investment Account Management |
| **Category** | Investment |
| **What it does** | Manages multiple investment accounts by type: Stocks & Shares ISA, General Investment Account (GIA), SIPP, work pension, onshore bonds, and offshore bonds. Tracks account provider, current value, contribution history, and tax wrapper status. Calculates aggregated metrics by account type. |
| **Why it exists** | UK investors typically hold investments across multiple account types with different tax treatments. This feature organises holdings by tax wrapper for strategic asset location decisions. |
| **Integrates with** | Holdings Management, Tax Optimisation, ISA Allowance Tracker, Asset Location Optimizer, Retirement Module (pensions) |

---

### 3.4 Efficient Frontier Analysis - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Efficient Frontier Analysis |
| **Category** | Investment |
| **What it does** | Implements Markowitz mean-variance optimisation to generate the efficient frontier curve. Displays risk-return tradeoff visualisation, identifies efficient portfolios at different risk levels, calculates correlation matrices between holdings, performs covariance analysis, and plots current portfolio position relative to the frontier. |
| **Why it exists** | Modern Portfolio Theory demonstrates that diversification can improve risk-adjusted returns. This analysis shows users whether their portfolio is efficiently constructed or whether they could achieve better returns for the same risk level. |
| **Integrates with** | Holdings data, Risk metrics, Correlation Analysis, Asset allocation, Model Portfolios, Rebalancing recommendations |

---

### 3.5 ISA Allowance Optimisation

| Attribute | Details |
|-----------|---------|
| **Feature** | ISA Allowance Optimisation |
| **Category** | Investment (Tax Optimisation) |
| **What it does** | Maximises use of annual £20,000 ISA allowance across Cash and Stocks & Shares ISAs. Tracks current year contributions, remaining allowance, and historical usage. Recommends which assets to shelter in ISA for maximum tax benefit. Alerts users to utilise allowance before tax year end. |
| **Why it exists** | ISA allowances provide valuable tax-free growth and income. Optimising ISA usage across the household can save significant tax over time, making this a critical wealth-building strategy. |
| **Integrates with** | Investment accounts (ISA), Savings Module (Cash ISA), Tax Configuration Service, Asset Location Optimizer, Tax year management |

---

### 3.6 Tax-Loss Harvesting

| Attribute | Details |
|-----------|---------|
| **Feature** | Tax-Loss Harvesting |
| **Category** | Investment (Tax Optimisation) |
| **What it does** | Identifies holdings with unrealized losses that could be sold to offset capital gains. Calculates potential tax savings from harvesting, considers wash sale rules (30-day repurchase restriction), suggests replacement investments to maintain exposure, and tracks annual CGT allowance usage. |
| **Why it exists** | Strategic loss realisation can reduce or eliminate capital gains tax liability. This feature automates identification of harvesting opportunities that many investors miss. |
| **Integrates with** | Holdings data (cost basis), CGT calculations, Annual CGT allowance (£3,000), Investment Recommendations, GIA holdings |

---

### 3.7 Bed & ISA Strategy

| Attribute | Details |
|-----------|---------|
| **Feature** | Bed & ISA Strategy |
| **Category** | Investment (Tax Optimisation) |
| **What it does** | Recommends selling GIA holdings and repurchasing within ISA to shelter future growth from tax. Prioritises holdings with highest expected growth and dividend yield. Calculates tax implications of the sale, considers dealing costs, and models long-term tax savings from the transfer. |
| **Why it exists** | Bed & ISA is a legitimate UK tax planning strategy that moves taxable investments into tax-free wrappers. This feature identifies optimal candidates and quantifies the benefit. |
| **Integrates with** | GIA holdings, ISA accounts, ISA Allowance Tracker, CGT calculations, Investment Recommendations |

---

### 3.8 Capital Gains Tax Calculator

| Attribute | Details |
|-----------|---------|
| **Feature** | Capital Gains Tax Calculator |
| **Category** | Investment (Tax Optimisation) |
| **What it does** | Calculates realised and unrealized capital gains across the portfolio. Applies annual CGT allowance (£3,000), determines applicable tax rates (10% basic, 20% higher for non-property), identifies gains in each tax band, and projects CGT liability from potential sales. |
| **Why it exists** | Understanding CGT exposure is essential for tax-efficient investing and disposal planning. This calculator helps users optimise sale timing and utilise allowances. |
| **Integrates with** | Holdings data (cost basis), User tax band, Annual CGT allowance, Tax-Loss Harvesting, Tax Configuration Service |

---

### 3.9 Asset Location Optimizer - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Asset Location Optimizer |
| **Category** | Investment (Tax Optimisation) |
| **What it does** | Recommends optimal placement of different asset types across tax wrappers. Suggests high-growth assets for ISA/pension (tax-free growth), bonds for pension (higher tax relief), income-generating assets for ISA (tax-free dividends), and tax-efficient funds for GIA. Calculates projected tax savings from optimal location. |
| **Why it exists** | Asset location can significantly impact after-tax returns over time. This optimiser applies tax-aware placement strategies typically available only from professional advisers. |
| **Integrates with** | All investment accounts by type, Holdings data, Tax rates by asset class, Tax Configuration Service, Investment Recommendations |

---

### 3.10 Tax-Drag Analysis - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Tax-Drag Analysis |
| **Category** | Investment (Tax Optimisation) |
| **What it does** | Measures the impact of taxes on portfolio returns over time. Calculates tax drag from dividends, interest, and capital gains in taxable accounts. Compares after-tax returns with theoretical tax-free returns. Quantifies long-term cost of suboptimal tax structuring. |
| **Why it exists** | Tax drag compounds over time, significantly reducing terminal wealth. This analysis makes the invisible cost of taxes visible, motivating tax-efficient restructuring. |
| **Integrates with** | Holdings data, Tax rates, Account types, Long-term projections, Investment Recommendations |

---

### 3.11 Fee Analysis

| Attribute | Details |
|-----------|---------|
| **Feature** | Fee Analysis |
| **Category** | Investment |
| **What it does** | Provides comprehensive fee breakdown including fund OCFs (Ongoing Charges Figures), platform fees, total cost analysis, comparison to low-cost alternatives, annual savings calculations, high-fee holding identification, and platform cost comparison. Calculates fee burden as percentage of portfolio and absolute annual cost. |
| **Why it exists** | Investment fees compound dramatically over time. A 1% fee difference can cost hundreds of thousands over a lifetime. This analysis reveals true fee impact and identifies savings opportunities. |
| **Integrates with** | Holdings data (OCF, TER), Platform fees, Cost Impact Analysis, Low-cost fund alternatives, Investment Recommendations |

---

### 3.12 Cost Impact Analysis

| Attribute | Details |
|-----------|---------|
| **Feature** | Cost Impact Analysis |
| **Category** | Investment |
| **What it does** | Calculates long-term impact of fees on portfolio value over 5, 10, 20, and 30-year horizons. Shows "fee drag" - the difference between current fee structure and a low-cost alternative. Quantifies potential savings from fee reduction in pounds. |
| **Why it exists** | Abstract percentages don't motivate action. Showing users they could have £50,000 more in retirement by switching to lower-cost funds creates urgency for optimisation. |
| **Integrates with** | Fee Analysis, Portfolio projections, Monte Carlo Simulations, Investment Recommendations |

---

### 3.13 Model Portfolios - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Model Portfolios |
| **Category** | Investment |
| **What it does** | Provides pre-built portfolio strategies aligned to risk profiles (cautious, balanced, adventurous). Defines asset allocation targets by risk level, suggests fund selections for each asset class, sets diversification guidelines, and establishes rebalancing thresholds. |
| **Why it exists** | Many investors lack the expertise to construct portfolios from scratch. Model portfolios provide evidence-based starting points aligned with the user's risk tolerance. |
| **Integrates with** | Risk Profile Assessment, Asset allocation, Holdings comparison, Rebalancing tools, Investment Recommendations |

---

### 3.14 Drift Analysis

| Attribute | Details |
|-----------|---------|
| **Feature** | Drift Analysis |
| **Category** | Investment (Rebalancing) |
| **What it does** | Measures deviation of current allocation from target allocation for each asset class. Calculates absolute drift (percentage points) and relative drift (percentage of target). Highlights asset classes requiring rebalancing attention. Tracks drift over time. |
| **Why it exists** | Market movements naturally cause portfolios to drift from target allocations. This analysis identifies when rebalancing is needed to maintain intended risk exposure. |
| **Integrates with** | Current asset allocation, Target allocation (from Model Portfolios or user-set), Rebalancing triggers, Rebalancing Actions |

---

### 3.15 Threshold-based Rebalancing

| Attribute | Details |
|-----------|---------|
| **Feature** | Threshold-based Rebalancing |
| **Category** | Investment (Rebalancing) |
| **What it does** | Triggers rebalancing recommendations when any asset class drifts beyond a defined threshold (e.g., 5% absolute or 20% relative). Calculates exact buy/sell amounts needed to return to target allocation. Considers transaction costs and tax implications. |
| **Why it exists** | Systematic rebalancing maintains risk discipline and potentially enhances returns through "buy low, sell high" mechanics. Thresholds prevent excessive trading while ensuring meaningful drift is addressed. |
| **Integrates with** | Drift Analysis, Target allocation, Transaction costs, Tax implications, Rebalancing Actions |

---

### 3.16 Tax-Aware Rebalancing - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Tax-Aware Rebalancing |
| **Category** | Investment (Rebalancing) |
| **What it does** | Generates rebalancing recommendations that minimise tax impact. Prioritises selling losses over gains, uses ISA/pension sales before GIA, considers CGT allowance utilisation, and suggests using new contributions to rebalance rather than selling. Calculates tax cost of different rebalancing approaches. |
| **Why it exists** | Naive rebalancing can trigger unnecessary capital gains taxes. This feature optimises the how of rebalancing, not just the what, preserving more wealth for the investor. |
| **Integrates with** | Rebalancing calculations, CGT position, Account types, Tax-Loss Harvesting, Contribution plans |

---

### 3.17 Rebalancing Actions Generator - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Rebalancing Actions Generator |
| **Category** | Investment (Rebalancing) |
| **What it does** | Produces specific, actionable buy/sell recommendations to achieve target allocation. Lists exact holdings to sell, amounts, and replacement purchases. Considers practical factors like minimum trade sizes and available funds. Prioritises tax-efficient execution. |
| **Why it exists** | Abstract rebalancing advice isn't actionable. This generator provides the specific trades needed, removing guesswork and enabling immediate implementation. |
| **Integrates with** | Drift Analysis, Tax-Aware Rebalancing, Holdings data, Target allocation, Investment Recommendations |

---

### 3.18 Monte Carlo Simulations

| Attribute | Details |
|-----------|---------|
| **Feature** | Monte Carlo Simulations |
| **Category** | Investment |
| **What it does** | Runs thousands of simulated portfolio outcomes over 5, 10, 20, and 30-year horizons. Models return variability and sequence of returns risk. Calculates probability of achieving target values, shows confidence intervals (10th, 50th, 90th percentile outcomes), and models contribution impact. Presents conservative, balanced, and aggressive scenario outcomes. |
| **Why it exists** | Point estimates of future value are misleading. Monte Carlo simulations show the range of possible outcomes and probabilities, enabling better planning under uncertainty. |
| **Integrates with** | Portfolio data, Expected returns by asset class, Volatility estimates, Contribution assumptions, Goal Progress Tracking |

---

### 3.19 Investment Goal Progress Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Investment Goal Progress Tracking |
| **Category** | Investment |
| **What it does** | Tracks progress toward investment goals with target amount, target date, current value, progress percentage, and on-track status. Calculates probability of achievement using Monte Carlo, identifies shortfall amount, and determines required contribution to meet target. Links goals to specific accounts. |
| **Why it exists** | Investment without purpose leads to poor decisions. Goal-linked investing keeps users focused on outcomes and enables probability-based planning rather than hope. |
| **Integrates with** | Investment accounts, Monte Carlo Simulations, Goals Module, Contribution tracking, Investment Recommendations |

---

### 3.20 Performance Attribution - ## CSJ notes - this is not shown in the ui, logic works needs links

| Attribute | Details |
|-----------|---------|
| **Feature** | Performance Attribution |
| **Category** | Investment |
| **What it does** | Analyses sources of portfolio returns including alpha (manager skill/excess returns), beta (market exposure), benchmark comparisons, and performance attribution by asset class. Breaks down returns into allocation effect, selection effect, and interaction effect over various periods. |
| **Why it exists** | Understanding why performance occurred (market movement vs fund selection vs allocation) enables better decisions about what to keep, change, or improve in the portfolio. |
| **Integrates with** | Holdings performance data, Benchmark indices, Risk metrics (alpha, beta), Historical returns |

---

### 3.21 Investment Recommendations Engine

| Attribute | Details |
|-----------|---------|
| **Feature** | Investment Recommendations Engine |
| **Category** | Investment |
| **What it does** | Generates personalised investment recommendations covering: risk profile completion, holdings additions, diversification improvements, fee reduction opportunities (with specific low-cost alternatives), allocation rebalancing, tax wrapper optimisation (Bed & ISA candidates), and tax-loss harvesting opportunities. Prioritises by impact and urgency. |
| **Why it exists** | Transforms complex analysis into clear actions. Consolidates insights from multiple analyses into a prioritised action list users can implement to improve their investment outcomes. |
| **Integrates with** | All Investment analyses, Risk Profile, Fee Analysis, Tax Optimisation, Diversification scoring, Coordinating Agent |

---

### 3.22 Investment Scenario Builder - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Investment Scenario Builder |
| **Category** | Investment |
| **What it does** | Models portfolio outcomes under different scenarios: conservative growth (4% annual return), balanced growth (7% return), aggressive growth (10% return), increased contribution scenarios, and volatility impact modeling. Compares scenarios side-by-side with current trajectory. |
| **Why it exists** | Helps users understand the range of possible futures and the impact of different assumptions on their investment outcomes. Supports more robust planning. |
| **Integrates with** | Current portfolio, Monte Carlo Simulations, Contribution assumptions, Goal Progress Tracking |

---

### 3.23 Correlation Analysis - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Correlation Analysis |
| **Category** | Investment |
| **What it does** | Calculates and visualises correlation coefficients between all holdings in a correlation matrix. Identifies highly correlated holdings that don't provide diversification benefit. Highlights opportunities to improve diversification through uncorrelated additions. |
| **Why it exists** | True diversification requires uncorrelated assets. This analysis reveals whether holdings actually diversify risk or merely create an illusion of diversification. |
| **Integrates with** | Holdings data, Efficient Frontier Analysis, Diversification scoring, Investment Recommendations |

---

### 3.24 Geographic Allocation Map - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Geographic Allocation Map |
| **Category** | Investment |
| **What it does** | Displays portfolio exposure by geographic region (UK, US, Europe, Asia-Pacific, Emerging Markets, etc.) in visual map format. Calculates regional percentages and compares to global market weights. Identifies regional concentration risks. |
| **Why it exists** | Home bias is common among UK investors. This visualisation reveals geographic concentration and supports decisions about international diversification. |
| **Integrates with** | Holdings data (regional classification), Portfolio Analysis, Diversification scoring |

---

### 3.25 Investment Plan Generator

| Attribute | Details |
|-----------|---------|
| **Feature** | Investment Plan Generator |
| **Category** | Investment |
| **What it does** | Creates comprehensive investment plans incorporating risk profile, target allocation, contribution strategy, tax wrapper strategy, fund selection, rebalancing rules, and review schedule. Consolidates all investment decisions into a coherent documented plan. |
| **Why it exists** | Ad-hoc investment decisions lead to poor outcomes. A documented plan provides discipline, accountability, and a framework for ongoing management. |
| **Integrates with** | Risk Profile, Model Portfolios, Tax Optimisation, Rebalancing strategy, All investment features |

---

### 3.26 Diversification Scoring

| Attribute | Details |
|-----------|---------|
| **Feature** | Diversification Scoring |
| **Category** | Investment |
| **What it does** | Calculates a 0-100 diversification score based on number of holdings, correlation between holdings, asset class spread, geographic spread, sector spread, and concentration in top holdings. Provides detailed breakdown explaining score components. |
| **Why it exists** | Diversification is investment's only "free lunch" - reducing risk without reducing expected return. This score quantifies diversification quality and highlights improvement areas. |
| **Integrates with** | Holdings data, Correlation Analysis, Geographic Allocation, Portfolio Analysis, Investment Recommendations |

---

### 3.27 Risk Metrics Dashboard

| Attribute | Details |
|-----------|---------|
| **Feature** | Risk Metrics Dashboard |
| **Category** | Investment |
| **What it does** | Displays comprehensive portfolio risk metrics: volatility (standard deviation of returns), beta (market sensitivity), alpha (excess returns), Sharpe ratio (risk-adjusted return), maximum drawdown (worst peak-to-trough decline), Value at Risk (VaR - potential loss at confidence level), and downside deviation (volatility of negative returns only). |
| **Why it exists** | Risk awareness is essential for appropriate portfolio construction. These metrics enable users to understand and compare risk across investments and against their tolerance. |
| **Integrates with** | Holdings data, Portfolio Analysis, Risk Profile, Benchmark comparison, Efficient Frontier Analysis |

---

## 4. Retirement Module

### 4.1 DC Pension Management

| Attribute | Details |
|-----------|---------|
| **Feature** | DC (Defined Contribution) Pension Management |
| **Category** | Retirement |
| **What it does** | Tracks defined contribution pensions including workplace and personal pensions (SIPP). Records current fund value, employer name/scheme name, monthly contributions (employee and employer), fund selections and performance, and projected retirement value at target age. Supports multiple DC pensions per user. |
| **Why it exists** | Most UK workers now have DC pensions. This feature centralises pension tracking and enables projection of retirement income from multiple DC sources. |
| **Integrates with** | Retirement Income Projections, Contribution Optimisation, Annual Allowance Tracker, DC Portfolio Analysis, Holdings Management |

---

### 4.2 DC Pension Portfolio Analysis

| Attribute | Details |
|-----------|---------|
| **Feature** | DC Pension Portfolio Analysis |
| **Category** | Retirement |
| **What it does** | Analyses DC pension investments with risk metrics, asset allocation breakdown, diversification scoring, fee analysis, and performance tracking. Supports glide-path strategies (de-risking as retirement approaches). Compares allocation to age-appropriate targets. |
| **Why it exists** | DC pension investment decisions significantly impact retirement outcomes. This analysis ensures pension investments are appropriately managed for the user's time horizon and risk tolerance. |
| **Integrates with** | DC Pension holdings, Investment Module analytics, Risk Profile, Age-based glide paths, Fee Analysis |

---

### 4.3 DB Pension Management

| Attribute | Details |
|-----------|---------|
| **Feature** | DB (Defined Benefit) Pension Management |
| **Category** | Retirement |
| **What it does** | Records defined benefit pension details including scheme name, employer, accrued annual pension, normal retirement age (NRA), revaluation basis (CPI, fixed, none), spouse's pension percentage, and commutation factors (lump sum exchange rates). Calculates current and projected values. |
| **Why it exists** | DB pensions (including public sector schemes like NHS, Teachers, Civil Service) provide valuable guaranteed income. Accurate recording enables comprehensive retirement planning including optimal commutation decisions. |
| **Integrates with** | Retirement Income Projections, Decumulation Planning, Estate Planning (death benefits), Spouse retirement planning |

---

### 4.4 State Pension Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | State Pension Tracking |
| **Category** | Retirement |
| **What it does** | Tracks State Pension entitlement including National Insurance years completed, years required for full pension (35 years), current forecast amount, State Pension age (based on date of birth), and gaps in NI record. Calculates projected State Pension at retirement. |
| **Why it exists** | The State Pension forms the foundation of most UK retirement plans. Understanding current entitlement and gaps enables optimisation through voluntary NI contributions or deferral strategies. |
| **Integrates with** | Retirement Income Projections, State Pension Optimizer, NI gap analysis, User date of birth, Tax Configuration Service |

---

### 4.5 Retirement Income Projections

| Attribute | Details |
|-----------|---------|
| **Feature** | Retirement Income Projections |
| **Category** | Retirement |
| **What it does** | Models total projected retirement income from all sources: DC pensions (using 4% sustainable withdrawal rate), DB pension income, State Pension, other income sources, and investment income. Compares against target retirement income, calculates income gap, and shows income timeline through retirement. |
| **Why it exists** | Retirement planning requires understanding whether combined income sources will meet needs. This projection provides the complete picture and identifies if additional saving or adjustments are needed. |
| **Integrates with** | All pension types, Investment accounts, User target income, Life Expectancy Modeling, Decumulation Planning |

---

### 4.6 Contribution Optimisation

| Attribute | Details |
|-----------|---------|
| **Feature** | Contribution Optimisation |
| **Category** | Retirement |
| **What it does** | Calculates optimal pension contribution levels considering: affordability (surplus income), tax relief benefits (basic/higher/additional rate), employer matching (maximising free money), Annual Allowance headroom, and target retirement income requirements. Recommends specific contribution amounts. |
| **Why it exists** | Pension contributions offer valuable tax relief, but optimal levels depend on individual circumstances. This optimisation ensures users contribute enough (but not too much) given their situation. |
| **Integrates with** | DC Pensions, Income data (tax band), Affordability Analysis, Annual Allowance Tracker, Retirement Income Projections, Employer contribution rates |

---

### 4.7 Annual Allowance Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Annual Allowance Tracking |
| **Category** | Retirement |
| **What it does** | Monitors pension contributions against the £60,000 Annual Allowance. Tracks current year contributions (employee + employer + personal), calculates remaining allowance, manages carry-forward from previous three years, applies tapered Annual Allowance for high earners (£260,000+ threshold income), and alerts for excess contributions. |
| **Why it exists** | Exceeding the Annual Allowance triggers a tax charge. This tracking prevents inadvertent breaches and maximises allowance utilisation including carry-forward opportunities. |
| **Integrates with** | All pension contributions, Income data (for tapering), Tax Configuration Service, Previous year allowances, Contribution Optimisation |

---

### 4.8 Decumulation Planning

| Attribute | Details |
|-----------|---------|
| **Feature** | Decumulation Planning |
| **Category** | Retirement |
| **What it does** | Plans retirement income strategies including: sustainable withdrawal rates, sequence of returns risk modeling, income drawdown vs annuity comparison, tax-efficient withdrawal sequencing (which pots to draw first), pension commencement lump sum (25% tax-free) optimisation, and income smoothing strategies across retirement phases. |
| **Why it exists** | How you withdraw from pensions is as important as how you save. Poor decumulation decisions can dramatically reduce retirement income sustainability. This planning ensures efficient income generation. |
| **Integrates with** | DC Pensions, DB Pensions, State Pension, Investment accounts, Life Expectancy Modeling, Tax bands |

---

### 4.9 Life Expectancy Modeling

| Attribute | Details |
|-----------|---------|
| **Feature** | Life Expectancy Modeling |
| **Category** | Retirement |
| **What it does** | Uses UK actuarial life tables to project life expectancy based on current age, gender, and health status. Applies mortality improvements for future longevity increases. Provides planning horizon recommendations for retirement income and models longevity risk (chance of outliving savings). |
| **Why it exists** | Retirement planning requires a time horizon assumption. This modeling provides evidence-based projections and highlights longevity risk - the risk of living longer than expected. |
| **Integrates with** | User age and gender, Health Information, Retirement Income Projections, Decumulation Planning, Monte Carlo Simulations |

---

### 4.10 State Pension Optimizer - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | State Pension Optimizer |
| **Category** | Retirement |
| **What it does** | Generates recommendations for optimising State Pension including: voluntary NI contribution analysis (cost vs benefit of buying years), deferral strategies (increased pension for delayed claiming), spousal implications (impact on spouse's entitlement), and international considerations for those with overseas NI. |
| **Why it exists** | State Pension optimisation can add significant value over retirement. Voluntary contributions and deferral decisions require analysis to determine optimal strategy. |
| **Integrates with** | State Pension Tracking, NI record, User age and retirement plans, Financial projections |

---

### 4.11 Retirement Scenarios - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Retirement Scenarios |
| **Category** | Retirement |
| **What it does** | Models and compares different retirement scenarios: current trajectory (no changes), increased contribution scenarios (impact of saving more), later retirement age scenarios (working longer), lower target income scenarios (spending less), and combinations. Quantifies impact on retirement readiness of each change. |
| **Why it exists** | Retirement planning involves trade-offs between current spending, future income, and retirement timing. Scenario comparison helps users find their preferred balance. |
| **Integrates with** | Retirement Income Projections, Contribution Optimisation, User preferences, All pension data |

---

### 4.12 Retirement Strategy Recommendations

| Attribute | Details |
|-----------|---------|
| **Feature** | Retirement Strategy Recommendations |
| **Category** | Retirement |
| **What it does** | Generates personalised retirement advice on: contribution levels, investment strategy changes (glide-path, de-risking), retirement age adjustments, drawdown strategy selection, income replacement targets, and employer matching optimisation. Prioritises recommendations by impact. |
| **Why it exists** | Consolidates retirement analysis into clear, actionable recommendations. Helps users understand the most impactful steps to improve their retirement outcomes. |
| **Integrates with** | All Retirement analyses, Contribution Optimisation, Income Projections, Coordinating Agent |

---

### 4.13 Unified Pension Form

| Attribute | Details |
|-----------|---------|
| **Feature** | Unified Pension Form |
| **Category** | Retirement |
| **What it does** | Provides a single interface for adding any pension type (DC, DB, or State). Dynamically shows relevant fields based on pension type selection. Validates pension-specific data requirements and maintains consistent user experience across pension types. |
| **Why it exists** | Users shouldn't need to navigate different forms for different pension types. This unified approach simplifies data entry while handling pension-specific requirements. |
| **Integrates with** | DC Pension Management, DB Pension Management, State Pension Tracking |

---

## 5. Estate Planning Module

### 5.1 Net Worth Aggregation

| Attribute | Details |
|-----------|---------|
| **Feature** | Net Worth Aggregation (Estate Context) |
| **Category** | Estate Planning |
| **What it does** | Calculates gross estate value by aggregating all assets: properties, investments, pensions, savings, business interests, chattels, and personal property. Deducts liabilities to calculate net estate. Categorises assets by IHT treatment (exempt, potentially exempt, taxable). |
| **Why it exists** | Estate planning begins with knowing what's in the estate. This aggregation provides the foundation for IHT calculations and estate planning strategies. |
| **Integrates with** | Net Worth Module, All asset modules, Liability tracking, IHT Calculation |

---

### 5.2 Inheritance Tax Calculation

| Attribute | Details |
|-----------|---------|
| **Feature** | Inheritance Tax (IHT) Calculation |
| **Category** | Estate Planning |
| **What it does** | Calculates IHT liability applying UK rules: Nil Rate Band (£325,000), Residence Nil Rate Band (£175,000 for qualifying estates), spousal exemption (unlimited transfers to UK-domiciled spouse), chargeable lifetime transfers, potentially exempt transfers within 7 years, and taper relief. Calculates IHT at 40% on amounts above thresholds. |
| **Why it exists** | IHT can claim 40% of estate value above thresholds. Accurate calculation is essential for understanding exposure and motivating mitigation planning. |
| **Integrates with** | Net Worth Aggregation, Tax Configuration Service, Spousal exemption tracking, Gift tracking, Trust data |

---

### 5.3 IHT Mitigation Strategies

| Attribute | Details |
|-----------|---------|
| **Feature** | IHT Mitigation Strategies |
| **Category** | Estate Planning |
| **What it does** | Presents and analyses IHT reduction strategies: lifetime gifting programs, trust creation, life insurance policies (whole of life to cover IHT), property downsizing, Business Property Relief (BPR) qualifying investments, Agricultural Property Relief (APR), charitable giving (36% rate reduction), and pension death benefit optimisation. |
| **Why it exists** | IHT is often called a "voluntary tax" because legitimate planning can significantly reduce liability. This feature educates users on available strategies and models their impact. |
| **Integrates with** | IHT Calculation, Gifting Strategy, Trust Planning, Life Cover Assessment, Asset data |

---

### 5.4 Gifting Strategy Optimisation

| Attribute | Details |
|-----------|---------|
| **Feature** | Gifting Strategy Optimisation |
| **Category** | Estate Planning |
| **What it does** | Develops optimal lifetime gifting strategies using: annual exemption (£3,000 per person, £6,000 for couples), small gifts exemption (£250 per recipient), wedding gifts (£5,000 for children, £2,500 for grandchildren), gifts out of normal expenditure (surplus income gifting), and Potentially Exempt Transfers (PETs) with 7-year rule. Models IHT savings and taper relief. |
| **Why it exists** | Strategic lifetime gifting is the most accessible IHT mitigation strategy. This optimisation ensures users utilise all available exemptions and understand PET implications. |
| **Integrates with** | IHT Calculation, Income/expenditure data, Family Members, Gift tracking, 7-year timeline |

---

### 5.5 Trust Planning

| Attribute | Details |
|-----------|---------|
| **Feature** | Trust Planning |
| **Category** | Estate Planning |
| **What it does** | Analyses and recommends appropriate trust structures: Discretionary trusts (flexible beneficiary access), Life Interest trusts (income for life, capital to remaindermen), Bare trusts (simple, for minors), Interest in Possession trusts. Calculates periodic charges (10-year anniversary), exit charges, and IHT entry charges on creation. |
| **Why it exists** | Trusts are powerful estate planning tools but complex to understand. This feature explains options and models tax implications to support informed decisions. |
| **Integrates with** | IHT Calculation, Trust Management, Family Members, Asset values, Tax Configuration Service |

---

### 5.6 Asset Liquidity Analysis (Estate)

| Attribute | Details |
|-----------|---------|
| **Feature** | Asset Liquidity Analysis (Estate Context) |
| **Category** | Estate Planning |
| **What it does** | Assesses estate liquidity to cover IHT liability: identifies liquid assets (cash, investments), semi-liquid assets (easily sold), and illiquid assets (property, business interests). Calculates if liquid assets cover IHT due within 6 months of death. Identifies potential forced sale situations. |
| **Why it exists** | IHT is due within 6 months, regardless of asset liquidity. Estates with insufficient liquid assets face forced sales or payment plans. This analysis highlights liquidity risks. |
| **Integrates with** | All asset data, IHT Calculation, Life Cover Assessment, Savings data |

---

### 5.7 Will & Estate Distribution

| Attribute | Details |
|-----------|---------|
| **Feature** | Will & Estate Distribution Planning |
| **Category** | Estate Planning |
| **What it does** | Supports will planning including: recording will existence and date, modelling distribution under current will, calculating intestacy outcomes (if no will), beneficiary designation tracking, and executor planning. Shows how estate would be distributed under different scenarios. |
| **Why it exists** | Dying without a valid will (intestate) means the law determines distribution, often not matching wishes. This feature encourages proper will planning and shows intestacy implications. |
| **Integrates with** | Net Worth data, Family Members, IHT Calculation, Beneficiary designations |

---

### 5.8 Spouse NRB Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Spouse NRB (Nil Rate Band) Tracking |
| **Category** | Estate Planning |
| **What it does** | Tracks both spouses' IHT positions including: each spouse's individual estate, unused NRB and RNRB available for transfer to surviving spouse (up to 100% transferable), combined estate IHT position, and optimal ordering of deaths for IHT purposes. |
| **Why it exists** | Spousal NRB/RNRB transfer can double available allowances (up to £1M combined). Understanding both positions enables planning that maximises transferred allowances. |
| **Integrates with** | IHT Calculation, Spouse data, Joint asset allocation, Estate distribution |

---

### 5.9 Life Cover Assessment (IHT)

| Attribute | Details |
|-----------|---------|
| **Feature** | Life Cover Assessment for IHT |
| **Category** | Estate Planning |
| **What it does** | Evaluates whether life insurance adequately covers projected IHT liability. Compares existing whole of life cover against IHT exposure. Recommends additional cover amounts and suggests policies written in trust (to avoid increasing estate). Calculates premium affordability. |
| **Why it exists** | Life insurance written in trust can fund IHT without increasing the taxable estate. This assessment identifies if existing cover is sufficient or additional protection is needed. |
| **Integrates with** | IHT Calculation, Protection policies, Trust data, Asset Liquidity Analysis |

---

### 5.10 Estate Scenarios - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Estate Scenarios |
| **Category** | Estate Planning |
| **What it does** | Models and compares estate outcomes under different scenarios: current estate position (no changes), optimised plan with gifting, property downsizing impact, trust creation scenarios, and combined strategy outcomes. Shows IHT liability under each scenario with savings quantified. |
| **Why it exists** | Estate planning involves trade-offs and multiple strategies. Scenario comparison helps users see the impact of different approaches and choose their preferred path. |
| **Integrates with** | IHT Calculation, All mitigation strategies, Gifting Strategy, Trust Planning |

---

### 5.11 Estate Health Score - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Estate Health Score |
| **Category** | Estate Planning |
| **What it does** | Generates a 0-100 score assessing estate planning completeness: IHT profile accuracy, IHT liability level (lower exposure = higher score), trust adequacy, liquidity sufficiency, spouse planning, and document preparation (will, LPA). Provides component breakdown and improvement recommendations. |
| **Why it exists** | A single score makes complex estate planning accessible. Users can quickly understand overall estate readiness and track improvement over time. |
| **Integrates with** | All Estate Planning features, IHT Calculation, Document tracking, Dashboard overview |

---

### 5.12 Cash Flow Projections (Estate)

| Attribute | Details |
|-----------|---------|
| **Feature** | Cash Flow Projections (Estate Context) |
| **Category** | Estate Planning |
| **What it does** | Projects estate value over time considering: asset growth, income continuation, expenditure patterns, gifting strategies, and longevity assumptions. Shows years of sustainable cash flow and inheritance timing. Models impact of different spending/gifting rates on final estate. |
| **Why it exists** | Estate planning must balance lifetime needs against inheritance goals. These projections help users understand how long their wealth will last and what they can afford to give away. |
| **Integrates with** | Net Worth data, Income/expenditure, Life Expectancy Modeling, Gifting Strategy |

---

## 6. Goals Module

### 6.1 Goal Creation & Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Goal Creation & Tracking |
| **Category** | Goals |
| **What it does** | Creates and tracks financial goals with: goal name and description, target amount, target date, current amount, monthly contribution, goal type (emergency fund, house deposit, holiday, education, retirement, other), priority level (critical, high, medium, low), and status (active, paused, completed). |
| **Why it exists** | Goal-based financial planning improves motivation and outcomes. Clear goals with deadlines create accountability and enable progress tracking. |
| **Integrates with** | Savings Module, Investment Module, Progress Tracking, Contribution Tracking, Affordability Analysis |

---

### 6.2 Goal Assignment to Modules

| Attribute | Details |
|-----------|---------|
| **Feature** | Goal Assignment to Modules |
| **Category** | Goals |
| **What it does** | Links goals to appropriate financial modules: savings goals (linked to Savings Module), investment goals (linked to Investment Module), property goals, and retirement goals. Enables module-specific tracking and recommendations. |
| **Why it exists** | Different goals require different strategies. Linking goals to modules ensures appropriate tracking, projections, and recommendations for each goal type. |
| **Integrates with** | Savings Module, Investment Module, Retirement Module, Module-specific analytics |

---

### 6.3 Progress Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Goal Progress Tracking |
| **Category** | Goals |
| **What it does** | Tracks goal progress including: progress percentage (current/target), days/months remaining to deadline, on-track status (based on current trajectory), contribution consistency, and milestone achievements. Updates in real-time as contributions are made. |
| **Why it exists** | Progress visibility maintains motivation and enables early intervention when goals fall behind. Visual tracking reinforces positive financial behaviours. |
| **Integrates with** | Goal data, Contribution Tracking, Linked accounts (savings/investment), Dashboard display |

---

### 6.4 Contribution Streaks

| Attribute | Details |
|-----------|---------|
| **Feature** | Contribution Streaks |
| **Category** | Goals |
| **What it does** | Tracks consecutive months of goal contributions including: current streak length, longest streak achieved, streak milestones (3, 6, 12 months), and streak recognition. Monitors contribution consistency across all goals. |
| **Why it exists** | Gamification through streaks encourages consistent saving behaviour. Recognition of streaks reinforces the habit of regular contributions. |
| **Integrates with** | Contribution Tracking, Goal Progress, Recommendations (streak recognition), Dashboard highlights |

---

### 6.5 Affordability Analysis

| Attribute | Details |
|-----------|---------|
| **Feature** | Goal Affordability Analysis |
| **Category** | Goals |
| **What it does** | Assesses whether goal contributions are sustainable given income and expenses. Calculates total monthly commitment across all goals, compares to available surplus, identifies overcommitment situations, and suggests contribution reallocation if needed. |
| **Why it exists** | Unrealistic goals lead to abandonment. This analysis ensures goal contributions fit within the user's actual financial capacity, preventing overcommitment. |
| **Integrates with** | User income data, Expenditure data, All active goals, Coordinating Agent (cross-module affordability) |

---

### 6.6 Goal Risk Assessment

| Attribute | Details |
|-----------|---------|
| **Feature** | Goal Risk Assessment |
| **Category** | Goals |
| **What it does** | Evaluates risk factors for each goal: probability of achievement (based on projections), shortfall likelihood, market risk exposure (for investment goals), and time risk (insufficient time to recover from setbacks). Flags high-risk goals requiring attention. |
| **Why it exists** | Not all goals have equal certainty of achievement. This assessment helps users understand which goals are at risk and may need strategy adjustments. |
| **Integrates with** | Goal Progress, Monte Carlo Simulations (investment goals), Time to target, Market volatility data |

---

### 6.7 Goal Scenario Modeling

| Attribute | Details |
|-----------|---------|
| **Feature** | Goal Scenario Modeling |
| **Category** | Goals |
| **What it does** | Models goal outcomes under different scenarios: 20% contribution increase, 6-month timeline acceleration, 20% target reduction, one-time lump sum contribution, and required contribution calculation to meet target on time. Shows impact of each scenario on goal achievement. |
| **Why it exists** | When goals are at risk, users need options. Scenario modeling shows the trade-offs between different adjustment approaches. |
| **Integrates with** | Goal Progress, Contribution tracking, Affordability Analysis, Investment projections |

---

### 6.8 Goal Recommendations

| Attribute | Details |
|-----------|---------|
| **Feature** | Goal Recommendations |
| **Category** | Goals |
| **What it does** | Generates personalised goal recommendations: alerts for goals behind schedule, affordability warnings for overcommitment, emergency fund reminders (prioritise if missing), contribution streak recognition, priority rebalancing suggestions, and goal achievement celebrations. |
| **Why it exists** | Proactive recommendations keep users engaged and guide corrective action when needed. Celebrates successes to reinforce positive behaviour. |
| **Integrates with** | All Goals features, Emergency Fund Analysis, Affordability Analysis, Coordinating Agent |

---

### 6.9 Dashboard Goal Overview

| Attribute | Details |
|-----------|---------|
| **Feature** | Dashboard Goal Overview |
| **Category** | Goals |
| **What it does** | Displays goal summary on main dashboard: top 5 goals by priority, overall progress percentage, count of on-track vs at-risk goals, best contribution streaks, and completed goals this year. Provides quick access to goal details. |
| **Why it exists** | Goals should be front-and-centre in financial planning. Dashboard visibility keeps goals top-of-mind and celebrates progress. |
| **Integrates with** | Goals data, Progress Tracking, Dashboard Module, Streak tracking |

---

## 7. Net Worth Module

### 7.1 Comprehensive Net Worth Calculation

| Attribute | Details |
|-----------|---------|
| **Feature** | Comprehensive Net Worth Calculation |
| **Category** | Net Worth |
| **What it does** | Aggregates all assets and liabilities to calculate total net worth. Assets include: properties, investments, pensions, savings, cash, business interests, chattels, and personal accounts. Liabilities include: mortgages, loans, credit cards, and other debts. Calculates gross assets, total liabilities, and net worth. |
| **Why it exists** | Net worth is the fundamental measure of financial health. This calculation provides a complete picture of where users stand financially. |
| **Integrates with** | All asset modules, All liability tracking, Estate Planning, Dashboard summary, Historical tracking |

---

### 7.2 Asset Category Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Asset Category Tracking |
| **Category** | Net Worth |
| **What it does** | Organises and tracks assets by category: properties (owner-occupied, buy-to-let, secondary), investments (by account type), pensions (DC, DB, State), savings accounts, cash accounts, business interests, chattels (valuable personal property), and personal accounts. Shows totals and percentages by category. |
| **Why it exists** | Category breakdown reveals asset concentration and diversification. Understanding asset mix supports better allocation decisions. |
| **Integrates with** | All asset data, Wealth Summary visualisation, Concentration analysis |

---

### 7.3 Liability Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Liability Tracking |
| **Category** | Net Worth |
| **What it does** | Records all liabilities including: mortgages (by property, with interest rate, term, type), personal loans, credit cards (balance and limit), car finance, student loans, and other debts. Tracks interest rates, monthly payments, and remaining terms. |
| **Why it exists** | Liabilities reduce net worth and create cash flow obligations. Complete liability tracking enables debt management and cash flow planning. |
| **Integrates with** | Net Worth calculation, Cash Flow Analysis, Mortgage data by property, Protection (debt cover) |

---

### 7.4 Net Worth Trends - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Net Worth Trends |
| **Category** | Net Worth |
| **What it does** | Tracks net worth over time showing growth/decline patterns, period-over-period comparisons (monthly, quarterly, annually), asset class contribution to growth, and milestone tracking (crossing £100k, £500k, £1M). Maintains historical snapshots. |
| **Why it exists** | Seeing progress over time motivates continued good financial behaviour. Trend analysis reveals which assets are driving growth or decline. |
| **Integrates with** | Net Worth calculation, Historical data, Dashboard charts, Progress celebrations |

---

### 7.5 Wealth Summary Visualisation

| Attribute | Details |
|-----------|---------|
| **Feature** | Wealth Summary Visualisation |
| **Category** | Net Worth |
| **What it does** | Provides visual representation of wealth including: donut chart of asset allocation, category percentage breakdown, individual asset values, growth attribution by category, and geographic exposure for investments. Interactive visualisations for exploration. |
| **Why it exists** | Visual presentation makes complex data accessible. Charts reveal patterns and concentrations that numbers alone might obscure. |
| **Integrates with** | All asset data, Investment geographic data, Dashboard display |

---

### 7.6 Joint Assets Handling

| Attribute | Details |
|-----------|---------|
| **Feature** | Joint Assets Handling |
| **Category** | Net Worth |
| **What it does** | Records joint ownership of assets including: joint owner identification (typically spouse), ownership percentage for each owner, ownership type (joint tenancy vs tenants in common), and separate tracking of each owner's share. Calculates individual and combined net worth. |
| **Why it exists** | Many UK households have jointly owned assets, especially property. Accurate ownership tracking is essential for individual net worth and estate planning. |
| **Integrates with** | Family Members (spouse), Property data, All asset types, Estate Planning (ownership on death) |

---

### 7.7 Property Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Property Management |
| **Category** | Net Worth |
| **What it does** | Manages property assets including: purchase price and date, current market value, property type (main residence, secondary, buy-to-let), address, ownership details, associated mortgage, rental income (if applicable), and property expenses. Supports disposal scenario modeling. |
| **Why it exists** | Property is typically the largest asset for UK households. Comprehensive property tracking is essential for net worth, tax planning, and estate planning. |
| **Integrates with** | Mortgage tracking, Net Worth calculation, Estate Planning (RNRB qualification), CGT calculations (for non-PPR), Rental income |

---

### 7.8 Business Interest Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Business Interest Tracking |
| **Category** | Net Worth |
| **What it does** | Records business ownership including: company name, sector classification, ownership percentage, current valuation, business details, dividend income received, and CGT cost basis. Tracks eligibility for Business Property Relief (BPR) for IHT purposes. |
| **Why it exists** | Business interests can be significant assets with special tax treatment. Tracking enables accurate net worth calculation and estate planning with BPR consideration. |
| **Integrates with** | Net Worth calculation, Estate Planning (BPR eligibility), Income data (dividends), CGT calculations |

---

### 7.9 Chattel (Personal Property) Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Chattel Tracking |
| **Category** | Net Worth |
| **What it does** | Records valuable personal property including: item description, category (jewellery, art, antiques, vehicles, collectibles), current valuation, insurance status, purchase cost (CGT basis), and ownership. Tracks items above chattels exemption threshold for CGT. |
| **Why it exists** | Valuable chattels contribute to net worth and estate value. Tracking ensures complete asset picture and enables CGT planning for high-value items. |
| **Integrates with** | Net Worth calculation, Estate Planning, CGT calculations (chattels exemption £6,000), Insurance tracking |

---

### 7.10 Personal Accounts

| Attribute | Details |
|-----------|---------|
| **Feature** | Personal Accounts |
| **Category** | Net Worth |
| **What it does** | Tracks additional accounts the user controls individually, separate from main tracked accounts. Records account name, institution, current value, purpose/notes, and ownership. Provides flexibility for assets not fitting other categories. |
| **Why it exists** | Users may have assets that don't fit neatly into defined categories. Personal accounts provide flexibility while ensuring complete net worth tracking. |
| **Integrates with** | Net Worth calculation, User Profile |

---

## 8. User Profile Module

### 8.1 Personal Information

| Attribute | Details |
|-----------|---------|
| **Feature** | Personal Information |
| **Category** | User Profile |
| **What it does** | Records core personal details: full name (first and last), date of birth, gender, nationality, UK domicile status (UK domiciled, non-UK domiciled, deemed domiciled), and marital status. Validates data completeness for dependent calculations. |
| **Why it exists** | Personal information drives calculations throughout the application: age affects retirement projections, gender affects life expectancy, domicile affects IHT treatment. |
| **Integrates with** | Life Expectancy Modeling, Estate Planning (domicile rules), Tax calculations, All modules requiring age |

---

### 8.2 Income Profile

| Attribute | Details |
|-----------|---------|
| **Feature** | Income Profile |
| **Category** | User Profile |
| **What it does** | Records all income sources: employment income (salary, bonus), self-employment income, rental income, dividend income, interest income, trust income, pension income (if receiving), and other income. Tracks gross and net amounts, payment frequency, and employment details. |
| **Why it exists** | Income data is fundamental to financial planning: determines tax band, affordability, protection needs, and retirement planning. Comprehensive income capture ensures accurate analysis. |
| **Integrates with** | Tax calculations, Affordability Analysis, Protection Needs Calculator, Retirement projections, Cash Flow Analysis |

---

### 8.3 Expenditure Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Expenditure Tracking |
| **Category** | User Profile |
| **What it does** | Records monthly expenditure by category: housing (rent/mortgage payments, utilities, council tax), transport, food & groceries, healthcare, insurance, communications (mobile, internet, TV), subscriptions, clothing, entertainment & dining, holidays & travel, education, childcare, pet care, and miscellaneous. Calculates totals and identifies surplus. |
| **Why it exists** | Expenditure determines affordability for savings, protection, and other commitments. Detailed tracking enables accurate emergency fund calculations and retirement income targeting. |
| **Integrates with** | Emergency Fund Analysis, Affordability Analysis, Retirement income targeting, Protection Needs Calculator, Cash Flow Analysis |

---

### 8.4 Family Members Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Family Members Management |
| **Category** | User Profile |
| **What it does** | Records family members including: spouse/partner details (name, date of birth, income, employment), dependents (children with ages, elderly parents, others), relationship type, and dependency status. Tracks family structure for planning purposes. |
| **Why it exists** | Family composition drives many planning decisions: protection needs for dependents, estate planning for beneficiaries, and spousal retirement planning. |
| **Integrates with** | Protection Needs Calculator, Estate Planning (beneficiaries), Retirement planning (spouse), Joint assets, IHT spousal exemption |

---

### 8.5 Health Information

| Attribute | Details |
|-----------|---------|
| **Feature** | Health Information |
| **Category** | User Profile |
| **What it does** | Records health-relevant information: general health status (excellent, good, fair, poor), lifestyle factors (smoking status, alcohol consumption, exercise level), pre-existing medical conditions (relevant to insurance), and current medications. Maintains privacy while enabling relevant calculations. |
| **Why it exists** | Health affects life expectancy projections and insurance considerations. This information enables more accurate longevity modeling and protection planning. |
| **Integrates with** | Life Expectancy Modeling, Protection Module (underwriting considerations), Retirement planning horizon |

---

### 8.6 Letter to Spouse Feature

| Attribute | Details |
|-----------|---------|
| **Feature** | Letter to Spouse |
| **Category** | User Profile |
| **What it does** | Stores important guidance for surviving spouse including: financial account details and access information, adviser contact details, important document locations, wishes for estate distribution, emergency contacts, and ongoing financial management guidance. Securely stored and accessible to authorised users. |
| **Why it exists** | Financial complexity can overwhelm grieving spouses. This feature ensures critical information is documented and accessible when needed most. |
| **Integrates with** | Family Members (spouse), Document storage, Estate Planning |

---

### 8.7 Profile Completeness Checking

| Attribute | Details |
|-----------|---------|
| **Feature** | Profile Completeness Checking |
| **Category** | User Profile |
| **What it does** | Assesses data completeness across the profile: identifies required vs optional fields completed, calculates completion percentage overall and by section, flags missing critical data, and provides data quality scoring. Guides users to complete essential information. |
| **Why it exists** | Incomplete data reduces analysis accuracy. This feature encourages users to provide comprehensive information and identifies gaps affecting specific calculations. |
| **Integrates with** | All profile sections, Onboarding guidance, Module readiness assessment, Recommendations |

---

### 8.8 Tax Information Summary

| Attribute | Details |
|-----------|---------|
| **Feature** | Tax Information Summary |
| **Category** | User Profile |
| **What it does** | Summarises user's tax position: current tax band (basic, higher, additional), personal allowance status (standard, tapered, none), marriage allowance eligibility, National Insurance category, and estimated annual tax liability. Based on income profile data. |
| **Why it exists** | Tax band affects multiple calculations including pension relief, dividend tax, and CGT rates. This summary ensures users understand their tax position. |
| **Integrates with** | Income Profile, Tax calculations throughout app, Tax Configuration Service, Pension contribution optimisation |

---

### 8.9 Financial Summary

| Attribute | Details |
|-----------|---------|
| **Feature** | Financial Summary |
| **Category** | User Profile |
| **What it does** | Provides overview of financial position: income statement view (total income, deductions, net income), expenditure analysis, cash flow summary (income minus expenses equals surplus/deficit), and simplified balance sheet view (assets minus liabilities). |
| **Why it exists** | A quick financial summary helps users understand their overall position at a glance before diving into detailed module analysis. |
| **Integrates with** | Income Profile, Expenditure Tracking, Net Worth Module, Dashboard |

---

## 9. Dashboard & Holistic Planning

### 9.1 Overview Cards

| Attribute | Details |
|-----------|---------|
| **Feature** | Overview Cards |
| **Category** | Dashboard |
| **What it does** | Displays quick-view summary cards for each module: Net Worth (total assets, liabilities, net worth), Retirement (projected income, target, gap), Investments (portfolio value, return, allocation), Savings (total savings, emergency fund status), Protection (coverage level, gaps), Estate (IHT liability, estate health), Goals (progress, on-track count). |
| **Why it exists** | Users need an at-a-glance view of their complete financial picture. Overview cards provide immediate status awareness without navigating to each module. |
| **Integrates with** | All financial modules, Real-time data updates, Navigation to module details |

---

### 9.2 Financial Health Score

| Attribute | Details |
|-----------|---------|
| **Feature** | Financial Health Score |
| **Category** | Dashboard |
| **What it does** | Calculates composite 0-100 score based on: protection adequacy, emergency fund adequacy, retirement readiness, investment diversification, estate planning completeness, and goal progress. Shows component breakdown, trend over time, and benchmark comparison. |
| **Why it exists** | A single score simplifies complex multi-dimensional financial health into an understandable metric. Enables quick assessment and progress tracking. |
| **Integrates with** | All module adequacy scores, Historical tracking, Dashboard display, Recommendations priority |

---

### 9.3 Alerts Panel

| Attribute | Details |
|-----------|---------|
| **Feature** | Alerts Panel |
| **Category** | Dashboard |
| **What it does** | Consolidates and displays alerts from all modules: critical alerts (immediate action required), warnings (attention needed), and action items (recommended improvements). Prioritises by urgency and impact. Allows dismissal and tracking of resolved alerts. |
| **Why it exists** | Proactive alerts ensure important issues aren't overlooked. Centralised alerts prevent users from missing critical items buried in individual modules. |
| **Integrates with** | All module recommendations, Priority ranking, Alert dismissal tracking, Notification system |

---

### 9.4 Executive Summary

| Attribute | Details |
|-----------|---------|
| **Feature** | Executive Summary |
| **Category** | Dashboard |
| **What it does** | Generates narrative summary of financial position: overall financial snapshot, key strengths (what's working well), key vulnerabilities (areas of concern), top 3-5 priorities (most impactful actions), and overall financial health assessment. Written in accessible language. |
| **Why it exists** | Not all users want to interpret charts and numbers. This narrative summary provides clear, plain-language guidance on financial position and priorities. |
| **Integrates with** | All module analyses, Recommendations Engine, Financial Health Score |

---

### 9.5 Net Worth Projection Chart

| Attribute | Details |
|-----------|---------|
| **Feature** | Net Worth Projection Chart |
| **Category** | Dashboard |
| **What it does** | Visualises 20-year net worth projection showing: baseline trajectory (no changes), optimised scenario (with recommendations implemented), confidence intervals (10th, 50th, 90th percentile), and recommendation impact quantification. Interactive chart with hover details. |
| **Why it exists** | Long-term projections help users understand the impact of current decisions on future wealth. Comparing baseline to optimised shows the value of taking action. |
| **Integrates with** | Net Worth data, All recommendations, Monte Carlo Simulations, Investment projections |

---

### 9.6 Cash Flow Allocation Chart

| Attribute | Details |
|-----------|---------|
| **Feature** | Cash Flow Allocation Chart |
| **Category** | Dashboard |
| **What it does** | Visualises how available surplus should be allocated: shows available monthly surplus, recommended allocation by module (savings, pension, protection, debt), current allocation, and gap between recommended and actual. Helps balance competing priorities. |
| **Why it exists** | Limited surplus must be allocated across multiple priorities. This visualisation supports trade-off decisions and shows optimal allocation based on module needs. |
| **Integrates with** | Income/expenditure data, All module recommendations, Coordinating Agent, Affordability Analysis |

---

### 9.7 Prioritised Recommendations

| Attribute | Details |
|-----------|---------|
| **Feature** | Prioritised Recommendations |
| **Category** | Dashboard |
| **What it does** | Aggregates recommendations from all modules, resolves cross-module conflicts, ranks by priority and impact, and presents top recommendations with action guidance. Shows estimated impact of each recommendation. Tracks implementation status. |
| **Why it exists** | Users are overwhelmed by too many recommendations. Prioritisation focuses attention on highest-impact actions while conflict resolution prevents contradictory advice. |
| **Integrates with** | All module recommendations, Coordinating Agent, Impact quantification, Implementation tracking |

---

### 9.8 Module Summaries

| Attribute | Details |
|-----------|---------|
| **Feature** | Module Summaries |
| **Category** | Dashboard |
| **What it does** | Provides summary card for each module showing: key metrics (2-3 most important), status indicator (green/amber/red), recommendation count, and quick link to module detail. Consistent format across all modules. |
| **Why it exists** | Quick module status overview helps users identify which areas need attention without navigating to each module individually. |
| **Integrates with** | Each financial module, Status calculations, Recommendation counts |

---

## 10. Authentication & Security

### 10.1 User Registration

| Attribute | Details |
|-----------|---------|
| **Feature** | User Registration |
| **Category** | Authentication |
| **What it does** | Enables new user signup with: email address (unique), password (with strength requirements), email verification requirement, and initial profile setup. Validates email format and password complexity. Creates secure user account. |
| **Why it exists** | Secure registration is the foundation of account security. Email verification confirms identity ownership and enables account recovery. |
| **Integrates with** | Email verification system, Password security, User Profile creation, Onboarding flow |

---

### 10.2 Login System

| Attribute | Details |
|-----------|---------|
| **Feature** | Login System |
| **Category** | Authentication |
| **What it does** | Authenticates users with email and password. Implements rate limiting (5 attempts per minute), tracks failed login attempts, provides account lockout after excessive failures, and logs successful logins with IP/device information for security audit. |
| **Why it exists** | Secure login protects user accounts and sensitive financial data. Rate limiting and lockout prevent brute force attacks. |
| **Integrates with** | MFA verification, Session Management, Audit logging, Account security |

---

### 10.3 Multi-Factor Authentication (MFA)

| Attribute | Details |
|-----------|---------|
| **Feature** | Multi-Factor Authentication |
| **Category** | Authentication |
| **What it does** | Provides TOTP-based MFA (Time-based One-Time Password) compatible with Google Authenticator, Authy, etc. Includes: QR code generation for setup, verification code entry, recovery codes (10 single-use codes), MFA enable/disable, and recovery code regeneration. |
| **Why it exists** | MFA significantly reduces account compromise risk even if password is exposed. Essential security for applications containing sensitive financial data. |
| **Integrates with** | Login System, Password Reset, Recovery code management, Account security settings |

---

### 10.4 Email Verification

| Attribute | Details |
|-----------|---------|
| **Feature** | Email Verification |
| **Category** | Authentication |
| **What it does** | Sends verification codes (6-digit) to user email for: new account verification, sensitive action confirmation, and password reset verification. Codes expire after set time. Supports resend with rate limiting (10 per minute). |
| **Why it exists** | Email verification confirms account ownership and adds security layer for sensitive operations. Prevents account takeover through email compromise. |
| **Integrates with** | Registration, Password Reset, GDPR Data Erasure, Email delivery system |

---

### 10.5 Password Reset Flow

| Attribute | Details |
|-----------|---------|
| **Feature** | Password Reset Flow |
| **Category** | Authentication |
| **What it does** | Secure password reset with multiple verification steps: email verification code, MFA verification (if enabled), recovery code support (if MFA enabled), and secure token generation. Rate limits each step independently. Logs reset attempts. |
| **Why it exists** | Password reset is a common attack vector. Multi-step verification with MFA integration ensures only legitimate account owners can reset passwords. |
| **Integrates with** | Email Verification, MFA system, Recovery codes, Audit logging |

---

### 10.6 Session Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Session Management |
| **Category** | Authentication |
| **What it does** | Manages user sessions with: active session tracking (multiple sessions supported), session termination (individual or all sessions), configurable session timeout, device and location information, and last activity tracking. Enables "logout from all devices" functionality. |
| **Why it exists** | Session management enables users to control their active logins and terminate suspicious sessions. Essential for security on shared or compromised devices. |
| **Integrates with** | Login System, Account security settings, Device/IP logging |

---

### 10.7 Account Security Settings

| Attribute | Details |
|-----------|---------|
| **Feature** | Account Security Settings |
| **Category** | Authentication |
| **What it does** | Provides security management interface: password change, MFA setup/management, recovery code viewing/regeneration, active session management, login history viewing, and security recommendations. |
| **Why it exists** | Users need control over their account security settings. Centralised security management enables informed security decisions. |
| **Integrates with** | All authentication features, Audit logs, Security recommendations |

---

## 11. Onboarding & Preview Mode

### 11.1 Onboarding Wizard

| Attribute | Details |
|-----------|---------|
| **Feature** | Onboarding Wizard |
| **Category** | Onboarding |
| **What it does** | Guides new users through step-by-step data collection: focus area selection (which modules matter most), personal information, income details, expenditure breakdown, family information, assets declaration (property, savings, investments, pensions), liabilities declaration, protection policies, will and domicile status, and trust information. Supports skip with reasoning. |
| **Why it exists** | Comprehensive data collection can be overwhelming. Guided onboarding breaks the process into manageable steps and collects data in logical sequence. |
| **Integrates with** | All data modules, Profile completeness, Skip tracking, Module activation |

---

### 11.2 Preview Mode

| Attribute | Details |
|-----------|---------|
| **Feature** | Preview Mode |
| **Category** | Onboarding |
| **What it does** | Allows visitors to explore the application using pre-populated demo personas: Young Family (James & Emily Carter - mortgage, workplace pensions), Peak Earners (David & Sarah Mitchell - multiple properties, complex pensions), Widow (Margaret Thompson - estate planning focus), Entrepreneur (Alex Chen - SIPP, business interests). Personas are isolated from real user data. |
| **Why it exists** | Prospective users want to see the application with realistic data before committing to registration. Preview mode demonstrates value without requiring personal data entry. |
| **Integrates with** | Persona data seeding, Data isolation, Landing page, Registration conversion |

---

### 11.3 Persona Switching

| Attribute | Details |
|-----------|---------|
| **Feature** | Persona Switching |
| **Category** | Onboarding |
| **What it does** | Enables switching between preview personas from landing page. Each persona has distinct financial characteristics showing different application use cases. Switch is instant with complete data refresh. Maintains persona isolation. |
| **Why it exists** | Different users have different situations. Persona variety demonstrates application breadth and helps visitors find scenarios similar to their own. |
| **Integrates with** | Preview Mode, Persona data, Landing page UI |

---

## 12. Admin & Management

### 12.1 User Management

| Attribute | Details |
|-----------|---------|
| **Feature** | User Management |
| **Category** | Admin |
| **What it does** | Administrative interface for user management: user list with search and filtering, user creation, user details viewing/editing, user deactivation, role assignment (admin/user), and account status management. Restricted to admin users. |
| **Why it exists** | Application administrators need to manage user accounts for support purposes, compliance, and operational management. |
| **Integrates with** | User accounts, Role-based access control, Audit logging |

---

### 12.2 Tax Settings Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Tax Settings Management |
| **Category** | Admin |
| **What it does** | Administrative interface for UK tax configuration: tax year settings (currently 2025/26), allowance configuration (Personal Allowance £12,570, ISA £20,000, Pension AA £60,000), tax band thresholds and rates, IHT thresholds (NRB £325,000, RNRB £175,000), and CGT allowances. Updates affect all calculations. |
| **Why it exists** | UK tax rates and allowances change annually. Centralised configuration ensures easy updates without code changes when budgets are announced. |
| **Integrates with** | Tax Configuration Service, All tax calculations, Annual updates |

---

### 12.3 Database Backup

| Attribute | Details |
|-----------|---------|
| **Feature** | Database Backup |
| **Category** | Admin |
| **What it does** | Provides database backup functionality: manual backup creation, backup download, backup restoration capability, and backup scheduling. Ensures data protection and disaster recovery capability. |
| **Why it exists** | Data protection is critical for financial applications. Regular backups ensure recovery capability and compliance with data protection requirements. |
| **Integrates with** | Database, Storage system, Admin access control |

---

### 12.4 Database Metrics

| Attribute | Details |
|-----------|---------|
| **Feature** | Database Metrics Dashboard |
| **Category** | Admin |
| **What it does** | Displays database health metrics: database size, table statistics, row counts, user count, data growth trends, and storage utilisation. Helps monitor application health and plan capacity. |
| **Why it exists** | Administrators need visibility into database health for operational management, capacity planning, and performance monitoring. |
| **Integrates with** | Database monitoring, Admin dashboard |

---

## 13. Documents & Data Import

### 13.1 Document Upload

| Attribute | Details |
|-----------|---------|
| **Feature** | Document Upload |
| **Category** | Documents |
| **What it does** | Enables users to upload financial documents: supports PDF, JPEG, PNG, WebP, Excel (XLSX, XLS), and CSV formats. Maximum 100MB file size. User-specific secure storage. MIME type validation. S3 or local storage options. |
| **Why it exists** | Users have financial documents (pension statements, insurance policies, investment reports) that contain data for the application. Upload capability enables data extraction and record keeping. |
| **Integrates with** | Document storage, AI extraction, Document Management, User storage allocation |

---

### 13.2 Document Type Detection

| Attribute | Details |
|-----------|---------|
| **Feature** | Document Type Detection |
| **Category** | Documents |
| **What it does** | Automatically detects document type from uploaded files: pension statements (DC/DB), insurance policies (life, critical illness, income protection), investment statements, bank statements, and other document categories. Uses AI classification. |
| **Why it exists** | Accurate type detection enables appropriate data extraction. Automation reduces user effort in categorising documents. |
| **Integrates with** | Document Upload, AI extraction, Field mapping |

---

### 13.3 AI-Powered Data Extraction

| Attribute | Details |
|-----------|---------|
| **Feature** | AI-Powered Data Extraction |
| **Category** | Documents |
| **What it does** | Uses AI to extract data from uploaded documents: identifies relevant fields, extracts values, maps to database models, and presents for user verification before saving. Supports manual correction of extracted values. |
| **Why it exists** | Manual data entry is tedious and error-prone. AI extraction reduces effort and improves accuracy by reading data directly from source documents. |
| **Integrates with** | Document Upload, Field Mapping, Data verification, All data modules |

---

### 13.4 Field Mapping

| Attribute | Details |
|-----------|---------|
| **Feature** | Field Mapping |
| **Category** | Documents |
| **What it does** | Maps extracted document data to database fields using specialised mappers: DB Pension mapper (scheme name, accrued pension, NRA, commutation), DC Pension mapper (fund value, contributions, funds), Life Insurance mapper (sum assured, premium, beneficiaries), Investment mapper (holdings, values, gains). |
| **Why it exists** | Different document types contain different fields. Specialised mappers ensure accurate data capture for each document category. |
| **Integrates with** | AI extraction, Database models, Data validation |

---

### 13.5 Document Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Document Management |
| **Category** | Documents |
| **What it does** | Manages uploaded documents: document listing with type and date, document deletion, extraction history, processing status tracking, re-extraction capability, and document-to-record linking. |
| **Why it exists** | Users need to manage their uploaded documents, track what's been processed, and maintain organised document storage. |
| **Integrates with** | Document Upload, Storage system, Extraction logging |

---

## 14. GDPR & Data Privacy

### 14.1 Consent Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Consent Management |
| **Category** | GDPR |
| **What it does** | Tracks user consent for data processing: consent categories (marketing, analytics, essential, preferences), consent recording with timestamps, consent history maintenance, and consent withdrawal capability. Ensures GDPR-compliant consent management. |
| **Why it exists** | GDPR requires explicit, documented consent for personal data processing. This feature ensures legal compliance and user control over data usage. |
| **Integrates with** | User registration, Privacy settings, Cookie management, Marketing systems |

---

### 14.2 Data Export (Portability)

| Attribute | Details |
|-----------|---------|
| **Feature** | Data Export |
| **Category** | GDPR |
| **What it does** | Implements GDPR Right to Data Portability: allows users to export all their personal data in JSON or CSV format, complete data export across all modules, export request tracking, temporary download URL generation, and export logging for audit. |
| **Why it exists** | GDPR Article 20 grants data portability rights. Users must be able to obtain and transfer their personal data. |
| **Integrates with** | All user data modules, Export generation, Audit logging |

---

### 14.3 Data Erasure Request

| Attribute | Details |
|-----------|---------|
| **Feature** | Data Erasure Request |
| **Category** | GDPR |
| **What it does** | Implements GDPR Right to Erasure (Right to be Forgotten): erasure request initiation, email verification step, MFA verification (if enabled), 30-day execution delay (cooling-off period), cancellation capability, cascading deletion of all related data, and permanent account removal. |
| **Why it exists** | GDPR Article 17 grants erasure rights under certain conditions. This feature enables users to request complete data deletion with appropriate verification. |
| **Integrates with** | Email Verification, MFA system, All data modules, Audit logging |

---

### 14.4 Audit Logging

| Attribute | Details |
|-----------|---------|
| **Feature** | Audit Logging |
| **Category** | GDPR |
| **What it does** | Maintains comprehensive audit trail: user action tracking, timestamp recording, data change logging, admin action logging, export/erasure request logging, and login/security event logging. Supports compliance reporting and security investigation. |
| **Why it exists** | GDPR requires demonstrable compliance. Audit logs provide evidence of data processing activities and enable security incident investigation. |
| **Integrates with** | All data operations, Security events, Admin actions, Compliance reporting |

---

## 15. Trusts Management

### 15.1 Trust Record Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Trust Record Management |
| **Category** | Trusts |
| **What it does** | Creates and manages trust records: trust name, trust type (discretionary, life interest, bare, other), settlor information, trustee details, beneficiary list, creation date, asset values held in trust, and registration status. |
| **Why it exists** | Trusts are common estate planning vehicles. Recording trust details enables comprehensive estate analysis and trust strategy recommendations. |
| **Integrates with** | Estate Planning, IHT calculations, Trust Planning, Net Worth (trust assets) |

---

### 15.2 Trust Type Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Trust Type Management |
| **Category** | Trusts |
| **What it does** | Supports different UK trust types with appropriate characteristics: Discretionary trusts (flexible beneficiary access, IHT entry charge, periodic charges), Life Interest trusts (income for life beneficiary), Bare trusts (simple, for minors), Interest in Possession trusts. Applies correct tax treatment by type. |
| **Why it exists** | Different trust types have different legal and tax characteristics. Correct type identification ensures accurate IHT calculations and appropriate recommendations. |
| **Integrates with** | Trust Records, IHT calculations, Trust Planning recommendations |

---

### 15.3 Trust Strategy Recommendations

| Attribute | Details |
|-----------|---------|
| **Feature** | Trust Strategy Recommendations |
| **Category** | Trusts |
| **What it does** | Generates personalised trust recommendations: appropriate trust type for user circumstances, IHT efficiency analysis, asset protection benefits, beneficiary considerations, and optimal trust timing. Based on estate size, family situation, and goals. |
| **Why it exists** | Trust planning is complex. Recommendations help users understand when and what type of trust might benefit their estate planning. |
| **Integrates with** | Estate Planning, IHT calculations, Family Members, User goals |

---

### 15.4 Periodic Charge Calculation

| Attribute | Details |
|-----------|---------|
| **Feature** | Periodic Charge Calculation |
| **Category** | Trusts |
| **What it does** | Calculates trust tax charges: 10-year anniversary charges (up to 6% of value above NRB), exit charges (proportionate charge on distributions), and charge mitigation strategies. Projects future charge dates and amounts. |
| **Why it exists** | Trusts face periodic tax charges that reduce their value. Calculating and projecting these charges enables planning to minimise tax impact. |
| **Integrates with** | Trust Records, IHT calculations, Tax Configuration Service |

---

## 16. Tax Management

### 16.1 UK Tax Calculation

| Attribute | Details |
|-----------|---------|
| **Feature** | UK Tax Calculation |
| **Category** | Tax |
| **What it does** | Calculates comprehensive UK tax liability: income tax (basic 20%, higher 40%, additional 45%), National Insurance (employee and self-employed), dividend tax (8.75%, 33.75%, 39.35%), savings income tax, and total tax burden. Applies personal allowance and other reliefs. |
| **Why it exists** | Understanding tax liability is essential for financial planning. Accurate calculations enable tax-efficient decision making across all modules. |
| **Integrates with** | Income Profile, Tax Configuration Service, All tax-related features |

---

### 16.2 Tax Year Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Tax Year Management |
| **Category** | Tax |
| **What it does** | Manages UK tax year context (April 6 - April 5): current tax year identification (2025/26), tax year transitions, historical tax year data, and allowance tracking by tax year. Ensures correct tax parameters are applied. |
| **Why it exists** | UK taxes operate on a specific tax year cycle different from calendar year. Correct tax year context ensures accurate allowance tracking and calculations. |
| **Integrates with** | All tax calculations, Allowance tracking, Tax Configuration Service |

---

### 16.3 Tax Configuration Service

| Attribute | Details |
|-----------|---------|
| **Feature** | Tax Configuration Service |
| **Category** | Tax |
| **What it does** | Centralised service providing all UK tax rates and allowances: personal allowance (£12,570), tax band thresholds, tax rates by band, NI rates and thresholds, ISA allowance (£20,000), pension Annual Allowance (£60,000), CGT allowance (£3,000), IHT thresholds (NRB £325,000, RNRB £175,000). Single source of truth for all calculations. |
| **Why it exists** | Tax rates change annually. Centralised configuration ensures consistency and enables easy updates when budgets are announced. |
| **Integrates with** | All modules using tax data, Admin Tax Settings |

---

### 16.4 Tax Product Information

| Attribute | Details |
|-----------|---------|
| **Feature** | Tax Product Information |
| **Category** | Tax |
| **What it does** | Provides information on tax-advantaged products: ISA types and limits (Cash ISA, Stocks & Shares ISA, LISA, Junior ISA), pension types and rules (SIPP, workplace, contribution limits), bond wrappers (onshore, offshore), and VCT/EIS schemes. Supports product selection decisions. |
| **Why it exists** | Understanding tax-advantaged products helps users make optimal choices. Product information supports investment and savings decisions. |
| **Integrates with** | Investment Module, Savings Module, Retirement Module, Tax Optimisation |

---

## 17. Coordination & Conflict Resolution

### 17.1 Multi-Module Orchestration

| Attribute | Details |
|-----------|---------|
| **Feature** | Multi-Module Orchestration |
| **Category** | Coordination |
| **What it does** | Coordinating Agent simultaneously analyses all financial modules, aggregates data across modules, generates cross-module insights, and produces holistic financial assessment. Ensures comprehensive view rather than siloed module analysis. |
| **Why it exists** | Financial planning requires holistic thinking. Individual module analysis misses interactions and trade-offs between different financial areas. |
| **Integrates with** | All financial modules, All Agents, Dashboard holistic views |

---

### 17.2 Conflict Identification & Resolution

| Attribute | Details |
|-----------|---------|
| **Feature** | Conflict Identification & Resolution |
| **Category** | Coordination |
| **What it does** | Identifies and resolves conflicting recommendations: protection vs savings conflicts (both demanding limited surplus), ISA allowance conflicts (Cash ISA vs Stocks & Shares), prioritisation conflicts (multiple high-priority items), and cash flow conflicts. Applies resolution strategies and explains trade-offs. |
| **Why it exists** | Individual modules may generate conflicting recommendations. Conflict resolution ensures users receive coherent, implementable guidance. |
| **Integrates with** | All module recommendations, Priority ranking, Affordability Analysis |

---

### 17.3 Cash Flow Coordination

| Attribute | Details |
|-----------|---------|
| **Feature** | Cash Flow Coordination |
| **Category** | Coordination |
| **What it does** | Coordinates cash flow allocation across modules: calculates available surplus (income minus expenses minus commitments), aggregates contribution demands from all modules, optimises allocation across competing priorities, identifies shortfalls, and generates balanced allocation recommendation. |
| **Why it exists** | Limited surplus must serve multiple priorities. Coordination ensures optimal allocation based on urgency, importance, and module-specific needs. |
| **Integrates with** | Income/expenditure data, All module contribution needs, Priority ranking, Affordability Analysis |

---

### 17.4 Priority Ranking

| Attribute | Details |
|-----------|---------|
| **Feature** | Cross-Module Priority Ranking |
| **Category** | Coordination |
| **What it does** | Ranks recommendations across all modules by: urgency (time-sensitive items), impact (financial benefit), user context (family situation, age, goals), and module preference weights. Assigns actionable priority levels. Quantifies impact of each recommendation. |
| **Why it exists** | Users can't act on dozens of recommendations simultaneously. Priority ranking focuses attention on highest-value actions first. |
| **Integrates with** | All module recommendations, User profile, Impact calculations |

---

### 17.5 Holistic Planning - ## CSJ notes - this is not shown in the ui, logic works

| Attribute | Details |
|-----------|---------|
| **Feature** | Holistic Planning |
| **Category** | Coordination |
| **What it does** | Produces comprehensive financial plan: complete financial snapshot, 20-year projections with scenarios, overall risk assessment, financial health scoring, strategic priorities, and integrated recommendations. Considers all modules as interconnected system. |
| **Why it exists** | True financial planning is holistic. This feature delivers the integrated planning approach typically available only from professional financial advisers. |
| **Integrates with** | All modules, Multi-Module Orchestration, Dashboard Executive Summary |

---

## 18. Risk Management

### 18.1 Risk Profile Assessment

| Attribute | Details |
|-----------|---------|
| **Feature** | Risk Profile Assessment |
| **Category** | Risk Management |
| **What it does** | Assesses user's investment risk tolerance through questionnaire: evaluates attitude to risk, capacity for loss, investment time horizon, and investment experience. Categorises into risk profiles (cautious, moderately cautious, balanced, moderately adventurous, adventurous). |
| **Why it exists** | Investment recommendations must align with risk tolerance. Assessment ensures appropriate portfolio construction and prevents unsuitable recommendations. |
| **Integrates with** | Investment Module, Model Portfolios, Asset allocation recommendations |

---

### 18.2 Portfolio Risk Metrics

| Attribute | Details |
|-----------|---------|
| **Feature** | Portfolio Risk Metrics |
| **Category** | Risk Management |
| **What it does** | Calculates comprehensive portfolio risk measures: volatility (standard deviation), beta (market sensitivity), alpha (excess returns), Sharpe ratio (risk-adjusted return), maximum drawdown, Value at Risk (VaR at 95% confidence), and downside deviation. |
| **Why it exists** | Understanding portfolio risk enables informed decisions about risk/return trade-offs and comparison to risk tolerance. |
| **Integrates with** | Investment holdings, Performance data, Risk Profile comparison |

---

### 18.3 Longevity Risk Assessment

| Attribute | Details |
|-----------|---------|
| **Feature** | Longevity Risk Assessment |
| **Category** | Risk Management |
| **What it does** | Evaluates longevity risk for retirement planning: models probability of living to various ages, calculates portfolio depletion risk, assesses income sustainability, and recommends planning horizons. Based on UK actuarial data. |
| **Why it exists** | Outliving savings is a major retirement risk. Longevity assessment ensures retirement plans account for realistic life expectancy scenarios. |
| **Integrates with** | Life Expectancy Modeling, Retirement projections, Decumulation Planning |

---

### 18.4 Portfolio Stress Testing

| Attribute | Details |
|-----------|---------|
| **Feature** | Portfolio Stress Testing |
| **Category** | Risk Management |
| **What it does** | Tests portfolio resilience under adverse conditions: historical scenario testing (2008 financial crisis, 2020 COVID crash), hypothetical stress scenarios (30% equity decline, interest rate shock), and recovery time analysis. Shows potential impact and recovery path. |
| **Why it exists** | Understanding how portfolios behave in crises helps users prepare mentally and financially. Stress testing reveals vulnerabilities before they materialise. |
| **Integrates with** | Portfolio holdings, Historical market data, Risk metrics |

---

## 19. Business Interests & Chattels

### 19.1 Business Interest Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Business Interest Management |
| **Category** | Assets |
| **What it does** | Records business ownership: company name and registration, sector classification, ownership percentage, current valuation methodology, dividend income received, capital gains cost basis, and Business Property Relief (BPR) eligibility assessment for IHT. |
| **Why it exists** | Business interests can be significant assets with special IHT treatment (100% BPR for qualifying businesses). Accurate tracking enables comprehensive planning. |
| **Integrates with** | Net Worth Module, Estate Planning (BPR), Income Profile (dividends), CGT calculations |

---

### 19.2 Chattel Management

| Attribute | Details |
|-----------|---------|
| **Feature** | Chattel (Valuable Items) Management |
| **Category** | Assets |
| **What it does** | Records valuable personal property: item description, category (jewellery, art, antiques, classic cars, collectibles), current valuation, insurance status and cover amount, purchase cost (CGT basis), and ownership. Tracks items for CGT and estate purposes. |
| **Why it exists** | Valuable chattels contribute to net worth and estate. Items above £6,000 may be subject to CGT on disposal. Tracking ensures complete asset visibility. |
| **Integrates with** | Net Worth Module, Estate Planning, CGT calculations (chattels exemption), Insurance tracking |

---

### 19.3 Chattels Tax Analysis

| Attribute | Details |
|-----------|---------|
| **Feature** | Chattels Tax Analysis |
| **Category** | Tax |
| **What it does** | Calculates CGT implications for chattel disposals: applies £6,000 chattels exemption (exempt if sold for less), calculates gain above cost basis, applies 5/3 rule for chattels sold above £6,000, and determines CGT liability. |
| **Why it exists** | Chattels have special CGT rules different from other assets. Accurate analysis ensures users understand disposal implications. |
| **Integrates with** | Chattel records, CGT calculations, Tax Configuration Service |

---

## 20. Spouse Linking & Joint View

### 20.1 Spouse Account Linking

| Attribute | Details |
|-----------|---------|
| **Feature** | Spouse Account Linking |
| **Category** | Multi-User |
| **What it does** | Enables two Fynla users (spouses/partners) to link their separate accounts together. The primary user sends an invitation to their spouse's email address. Upon acceptance, accounts become linked, establishing a formal relationship that enables data sharing and joint planning. Either party can unlink accounts at any time, reverting to individual-only access. |
| **Why it exists** | UK financial planning is inherently household-based. Spouses need visibility into each other's finances for accurate retirement projections, estate planning (spousal exemption, NRB transfer), and coordinated decision-making. Linked accounts enable true household planning without requiring shared login credentials. |
| **Integrates with** | User Profile, Family Members, Estate Planning (spousal exemption), Retirement Planning (joint projections), Net Worth (combined view) |

---

### 20.2 Invitation & Acceptance Flow - ## CSJ notes - this is automatic now, but I still have this logic in

| Attribute | Details |
|-----------|---------|
| **Feature** | Spouse Invitation System |
| **Category** | Multi-User |
| **What it does** | Manages the linking invitation process: primary user initiates link by entering spouse's email, system sends secure invitation email with unique token, spouse receives email and clicks link to accept, acceptance requires spouse to be logged into their Fynla account, invitation expires after 7 days, pending invitations can be cancelled or resent. Tracks invitation status (pending, accepted, expired, cancelled). |
| **Why it exists** | Account linking requires explicit consent from both parties. The invitation flow ensures both users actively agree to share financial data, maintaining privacy and security while enabling household planning. |
| **Integrates with** | Email system, Authentication, User Profile, Family Members data |

---

### 20.3 Joint View Toggle

| Attribute | Details |
|-----------|---------|
| **Feature** | Individual/Joint View Toggle |
| **Category** | Multi-User |
| **What it does** | Provides a toggle in the application header allowing linked users to switch between: Individual View (shows only the logged-in user's data - default), and Joint View (shows combined household data from both linked accounts). Toggle state persists during session. All modules respect the current view mode, displaying individual or combined data accordingly. |
| **Why it exists** | Users need flexibility to view their finances individually or as a household. Some planning decisions require individual perspective (personal tax), while others require combined view (estate planning, retirement income). The toggle enables both without data duplication. |
| **Integrates with** | All financial modules, Dashboard, Net Worth, Estate Planning, Retirement |

---

### 20.4 Combined Net Worth View

| Attribute | Details |
|-----------|---------|
| **Feature** | Household Net Worth Aggregation |
| **Category** | Multi-User |
| **What it does** | In Joint View mode, aggregates net worth from both linked accounts: combines all assets (properties, investments, pensions, savings) with proper handling of jointly-owned assets (counted once, not double-counted), combines all liabilities, calculates true household net worth. Shows breakdown by owner (User 1, User 2, Joint) and by asset category. |
| **Why it exists** | Household financial planning requires understanding combined wealth. Proper aggregation with joint asset handling prevents double-counting while giving accurate household position for estate planning and retirement. |
| **Integrates with** | Net Worth Module, Joint Assets Handling, Property data, All asset types |

---

### 20.5 Joint Estate Planning View

| Attribute | Details |
|-----------|---------|
| **Feature** | Household Estate Planning |
| **Category** | Multi-User |
| **What it does** | In Joint View mode, provides comprehensive household estate analysis: combined estate value for IHT purposes, both spouses' NRB and RNRB positions, spousal exemption modelling (assets passing to surviving spouse), first death vs second death IHT scenarios, optimal asset allocation between spouses, and household gifting strategy coordination. |
| **Why it exists** | Estate planning for couples is fundamentally about both estates. IHT spousal exemption, NRB transfer, and RNRB qualification depend on understanding both positions. Joint view enables proper household estate optimisation. |
| **Integrates with** | Estate Planning Module, IHT Calculation, Spouse NRB Tracking, Gifting Strategy |

---

### 20.6 Joint Retirement Projections

| Attribute | Details |
|-----------|---------|
| **Feature** | Household Retirement Planning |
| **Category** | Multi-User |
| **What it does** | In Joint View mode, combines retirement planning for both spouses: aggregates all pension entitlements (State Pension for both, DC pensions, DB pensions), models joint retirement income timeline (accounting for different retirement ages), shows combined income against household expenditure needs, models survivor scenarios (income after first death), and coordinates pension drawdown strategies. |
| **Why it exists** | Retirement planning for couples must consider both partners' pensions and the survivor's position. Joint projections ensure household income adequacy and proper survivor planning. |
| **Integrates with** | Retirement Module, State Pension, DC/DB Pensions, Life Expectancy Modeling, Expenditure data |

---

### 20.7 Data Privacy Controls

| Attribute | Details |
|-----------|---------|
| **Feature** | Linked Account Privacy Settings |
| **Category** | Multi-User |
| **What it does** | Provides granular privacy controls for linked accounts: users can choose which data categories to share (all, or select modules), sensitive fields can be hidden (specific account balances, income details), audit log of what data has been accessed by linked spouse, and instant ability to unlink accounts (removes all access immediately). Each user controls their own sharing preferences. |
| **Why it exists** | Even between spouses, financial privacy may be desired for certain items. Granular controls ensure users share what they're comfortable with while enabling sufficient data access for joint planning. |
| **Integrates with** | All data modules, User Profile, GDPR consent, Audit Logging |

---

### 20.8 Unlink Accounts

| Attribute | Details |
|-----------|---------|
| **Feature** | Account Unlinking |
| **Category** | Multi-User |
| **What it does** | Allows either linked user to terminate the account link: one-click unlink option in settings, confirmation required before unlinking, immediate effect (no grace period), removes all joint view access, preserves individual data integrity, notifies the other party of unlinking, and maintains audit record of link/unlink history. |
| **Why it exists** | Circumstances change (separation, divorce, or simply preferring individual planning). Users must have full control to terminate data sharing at any time without requiring the other party's consent. |
| **Integrates with** | User Profile, Privacy settings, Notification system, Audit Logging |

---

### 20.9 Joint Dashboard

| Attribute | Details |
|-----------|---------|
| **Feature** | Household Dashboard View |
| **Category** | Multi-User |
| **What it does** | In Joint View mode, the dashboard displays household-level metrics: combined net worth, household financial health score, aggregated retirement readiness, combined protection coverage, household estate position, and joint goals progress. Overview cards show data attributed by owner where relevant. Alerts from both accounts are consolidated and prioritised. |
| **Why it exists** | The dashboard is the entry point for financial planning. A joint dashboard provides immediate household-level visibility, enabling couples to understand their combined position at a glance. |
| **Integrates with** | Dashboard Module, All financial modules, Alert aggregation, Financial Health Score |

---

### 20.10 Joint Goal Tracking

| Attribute | Details |
|-----------|---------|
| **Feature** | Household Goals |
| **Category** | Multi-User |
| **What it does** | In Joint View mode, enables shared financial goals: goals can be marked as joint (shared between linked accounts), contributions from either account count toward joint goals, progress tracking shows contributions by each party, affordability analysis considers combined household surplus, and goal recommendations consider both users' capacity. |
| **Why it exists** | Many financial goals (house deposit, holiday, children's education) are household goals. Joint tracking enables coordinated effort and accurate progress measurement. |
| **Integrates with** | Goals Module, Contribution Tracking, Affordability Analysis, Savings/Investment links |

---

## Summary Statistics

| Metric | Count |
|--------|-------|
| Total Features Documented | 150+ |
| Vue Components | 282 |
| PHP Services | 137 |
| Controllers | 62 |
| Models | 65 |
| Vuex Stores | 21 |
| Agents | 8 |
| Main Modules | 5 (Protection, Savings, Investment, Retirement, Estate) |
| Supporting Modules | 4 (Goals, Net Worth, User Profile, Trusts) |

---

## Key Integrations Summary

| Integration Type | Components |
|------------------|------------|
| Tax Configuration | TaxConfigService used by all modules for UK tax rates/allowances |
| User Context | User Profile data (income, expenditure, family) used across all planning modules |
| Net Worth | Aggregates data from all asset modules |
| Coordinating Agent | Orchestrates all module agents for holistic analysis |
| Estate Planning | Integrates with all asset modules for IHT calculations |
| Goals | Links to Savings and Investment modules for progress tracking |
| Authentication | Secures all module access with MFA and session management |
| GDPR | Audit logging and data export/erasure across all user data |
| Spouse Linking | Enables linked accounts to share data for joint planning across all modules |

---

*Document generated: January 28, 2026*
