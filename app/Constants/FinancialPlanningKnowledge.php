<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * FinancialPlanningKnowledge - UK financial planning concepts for the AI assistant.
 *
 * These are CONCEPTUAL explanations, not current tax rates/thresholds.
 * The AI must always use get_tax_information to retrieve current figures.
 *
 * Structured for token efficiency — bullet format, no prose.
 *
 * Last verified: 1 April 2026 (2025/26 tax year concepts)
 */
final class FinancialPlanningKnowledge
{
    /**
     * Returns the complete financial knowledge block for the system prompt.
     * Approximately 1,600-1,800 tokens.
     */
    public static function getSystemPromptKnowledge(): string
    {
        return implode("\n\n", [
            self::INCOME_CLASSIFICATIONS,
            self::PENSION_KNOWLEDGE,
            self::INVESTMENT_TAX_WRAPPERS,
            self::ESTATE_PLANNING_CONCEPTS,
            self::PROTECTION_CONCEPTS,
            self::RECOMMENDATION_FRAMEWORK,
            self::AFFORDABILITY_RULES,
        ]);
    }

    // ─── Per-Domain Accessors (used by QueryKnowledge in Phase 3) ───

    public static function getIncomeClassifications(): string
    {
        return self::INCOME_CLASSIFICATIONS;
    }

    public static function getPensionKnowledge(): string
    {
        return self::PENSION_KNOWLEDGE;
    }

    public static function getInvestmentTaxWrappers(): string
    {
        return self::INVESTMENT_TAX_WRAPPERS;
    }

    public static function getEstatePlanningConcepts(): string
    {
        return self::ESTATE_PLANNING_CONCEPTS;
    }

    public static function getProtectionConcepts(): string
    {
        return self::PROTECTION_CONCEPTS;
    }

    public static function getRecommendationFramework(): string
    {
        return self::RECOMMENDATION_FRAMEWORK;
    }

    public static function getAffordabilityRules(): string
    {
        return self::AFFORDABILITY_RULES;
    }

    public static function getKnowledgeCaveat(): string
    {
        return self::KNOWLEDGE_CAVEAT;
    }

    private const AFFORDABILITY_RULES = <<<'TEXT'
AFFORDABILITY — ALWAYS CHECK BEFORE RECOMMENDING CONTRIBUTIONS:
Before suggesting ANY new contribution, increased contribution, or lump sum payment, you MUST check:
1. Monthly surplus/shortfall from <financial_context> — if the user has a shortfall (expenditure exceeds income), they CANNOT afford new contributions. Say so clearly.
2. Emergency fund adequacy — if the user has less than 3 months' expenses in liquid savings, building the emergency fund takes priority over pension/ISA contributions. For self-employed users, the target is 6 months.
3. High-interest debt — if the user has credit card, overdraft, or other high-interest debt, repaying it typically beats investment returns. Always flag this.
4. Active goals and life events — check if the user has upcoming financial commitments (home purchase, education, wedding) that require saving. These compete with pension/ISA contributions for the same surplus.
5. Disposable income — the maximum affordable contribution is the monthly surplus MINUS emergency fund top-up MINUS goal contributions MINUS debt repayments. Never suggest contributing more than this.
6. Relevant UK earnings cap — personal pension contributions cannot exceed relevant UK earnings (employment + self-employment income). A user earning £10,000 from employment can only get tax relief on £10,000 of personal contributions, even if their total income is higher.
TEXT;

    // KNOWLEDGE_CAVEAT removed — rules consolidated into Layer 2 (ComplianceRules)
    private const KNOWLEDGE_CAVEAT = '';

    private const INCOME_CLASSIFICATIONS = <<<'TEXT'
INCOME CLASSIFICATIONS (UK):
- Total Income: sum of all income sources (employment, self-employment, rental, dividend, interest, trust)
- Net Income: total income minus tax and National Insurance — this is what the user actually receives
- Adjusted Net Income: used for Personal Allowance taper. PA reduces by £1 for every £2 above £100,000
- "Relevant UK Earnings" for pension contribution relief: ONLY employment income and self-employment profits. Rental income, dividends, interest, trust income, and pension income are NOT relevant UK earnings and do NOT support pension tax relief
- Dividend income: taxed at special dividend rates (lower than income tax rates), with a separate dividend allowance
- Savings interest: may be covered by Personal Savings Allowance (amount depends on tax band)
- Rental income: taxed as property income. Mortgage interest relief on buy-to-let is capped
- High Income Child Benefit Charge: clawback applies at certain income thresholds — use get_tax_information
TEXT;

    private const PENSION_KNOWLEDGE = <<<'TEXT'
PENSION KNOWLEDGE (UK):
- Annual Allowance: maximum tax-relieved pension contributions per tax year. Use get_tax_information for current limit
- Tax Relief: contributions receive relief at the member's marginal income tax rate
- Personal Allowance reclaim (60% effective relief): when income is between £100,000 and £125,140, the Personal Allowance tapers away at £1 for every £2 above £100,000. Pension contributions reduce adjusted net income — so contributing enough to bring income to or below £100,000 restores the Personal Allowance (£12,570). This creates an effective 60% tax relief rate on the taper band (40% higher rate + 20% Personal Allowance restoration). ALWAYS flag this when relevant UK income exceeds £100k. This applies even if income is well above £125,140 — for example, a user earning £145,000 who contributes £45,000 to their pension reduces adjusted net income to £100,000, fully restoring the £12,570 Personal Allowance. The 60% effective relief applies specifically to the £12,570 that was tapered away, saving an additional £2,514 beyond the normal 40% higher-rate relief. When calculating, show: (1) the contribution needed to reach £100,000, (2) the restored Personal Allowance amount, and (3) the total tax saving including the Personal Allowance restoration
- Relevant UK Earnings cap: personal pension contributions cannot exceed relevant UK earnings (employment + self-employment). A user earning £100,000 from employment can get relief on up to £100,000 of contributions
- 25% Tax-Free Lump Sum: up to 25% of the pension can typically be taken tax-free
- Pension access age: currently 55, rising to 57 in 2028
- Defined Benefit pensions: guaranteed annual income linked to salary and service years
- State Pension: requires minimum National Insurance qualifying years. Use get_tax_information for rates
TEXT;

    private const INVESTMENT_TAX_WRAPPERS = <<<'TEXT'
INVESTMENT TAX WRAPPERS:
- Individual Savings Account (ISA): no income tax on interest/dividends, no Capital Gains Tax on growth. Annual allowance applies (check get_tax_information). ISA assets DO count towards the estate for Inheritance Tax
- General Investment Account (GIA): fully taxable — dividends use dividend allowance then dividend rates, interest uses Personal Savings Allowance, capital gains use annual exempt amount then Capital Gains Tax rates. Consider bed-and-ISA to move GIA holdings into ISA wrapper
- Lifetime ISA: government bonus on contributions (25%), age restricted (18-39 to open, contributions until 50). Penalty for non-qualifying withdrawals. First home purchase or age 60+
- Onshore Investment Bond: internal 20% tax credit, 5% annual tax-deferred withdrawals (cumulative), top-slicing relief on chargeable events. Gains taxed as income not capital gains
- Offshore Investment Bond: gross roll-up (no internal tax), same 5% withdrawal rule, time apportionment relief for periods of non-UK residence. No tax credit — gains taxed in full as income
- Venture Capital Trust (VCT): income tax relief on subscription (30%), tax-free dividends, no Capital Gains Tax on disposal. 5-year minimum hold. High risk
- Enterprise Investment Scheme (EIS): 30% income tax relief, Capital Gains Tax deferral on gains reinvested, Capital Gains Tax exemption on EIS shares after 3 years, loss relief. High risk, illiquid
- Seed Enterprise Investment Scheme (SEIS): 50% income tax relief, Capital Gains Tax exemption, loss relief. Very high risk, early-stage companies
- Self-Invested Personal Pension (SIPP): pension tax relief on contributions (same as other pensions), tax-free growth, 25% tax-free lump sum. Full investment control. Cannot access until pension access age
- Workplace Pension: employer contributions (often matched), auto-enrolment minimum rates apply. Same tax treatment as SIPP but investment choice may be limited
TEXT;

    private const ESTATE_PLANNING_CONCEPTS = <<<'TEXT'
ESTATE PLANNING CONCEPTS (UK):
- Nil Rate Band (NRB): amount that can pass free of Inheritance Tax. Frozen until 2028. Use get_tax_information for amount
- Residence Nil Rate Band (RNRB): additional allowance when main residence passes to direct descendants (children/grandchildren). Tapers for estates above threshold. Not available for trusts. Use get_tax_information for amounts
- Transferable NRB/RNRB: unused allowance from a deceased spouse can be transferred to the surviving spouse's estate (up to 100% of the allowance)
- Potentially Exempt Transfer (PET): gifts to individuals. Exempt from Inheritance Tax if donor survives 7 years. Taper relief reduces tax if death occurs between years 3-7
- Chargeable Lifetime Transfer (CLT): gifts into most trusts. 20% lifetime charge on amount above NRB. Becomes PET-like after 7 years
- Business Property Relief (BPR): 100% relief for trading company shares/business assets held 2+ years. 50% for land/buildings/machinery used by the business. Reduces Inheritance Tax liability significantly
- Business Asset Disposal Relief (BADR): Capital Gains Tax at reduced rate on qualifying business disposals. Lifetime limit applies. Conditions: 5%+ shareholding, 2+ year ownership, trading company, employee/officer
- Agricultural Property Relief: 100% or 50% based on tenancy type, 2-year ownership minimum
- Normal Expenditure from Income: gifts from surplus income (not capital) that form a regular pattern are exempt from Inheritance Tax with no 7-year rule
- Deed of Variation: beneficiaries can redirect an inheritance within 2 years of death for Inheritance Tax and Capital Gains Tax purposes
- Life insurance in trust: policy proceeds paid outside the estate — avoids Inheritance Tax on the payout. Relevant life policies for employees are tax-deductible for the employer
TEXT;

    private const PROTECTION_CONCEPTS = <<<'TEXT'
PROTECTION CONCEPTS:
- Life insurance: level term (fixed cover for fixed period), decreasing term (cover reduces — matches mortgage repayment), whole of life (covers entire lifetime, includes investment element). Joint policies are cheaper but only pay once
- Income protection: replaces income if unable to work due to illness/injury. "Own occupation" definition is strongest (unable to do YOUR job). "Any occupation" is weakest (unable to do ANY job). Benefit typically 50-70% of gross income. Deferred period (waiting period) affects premium — longer deferral = cheaper
- Critical illness: lump sum on diagnosis of specified conditions. "Standalone" pays independently of life cover. "Accelerated" reduces life cover by the amount paid — standalone preferred for comprehensive protection
- Relevant life policy: employer-funded life cover for employees. Not a benefit in kind (no tax charge), premiums tax-deductible for employer, proceeds outside estate. Ideal for directors/key employees
- Trust placement: life and critical illness policies should ideally be written in trust to keep proceeds outside the estate for Inheritance Tax. Does not affect the policyholder's access or claims process
- State benefits: Statutory Sick Pay, Employment and Support Allowance, Personal Independence Payment provide baseline but typically insufficient to maintain living standards
TEXT;

    private const RECOMMENDATION_FRAMEWORK = <<<'TEXT'
RECOMMENDATION FRAMEWORK:
The application generates personalised recommendations using decision trees across 6 modules. When explaining recommendations to the user, connect to these concepts:

SAVINGS: Emergency fund adequacy (target 3-6 months expenses, more for self-employed), interest rate optimisation (compare to market rates), ISA allowance utilisation, Financial Services Compensation Scheme limits per institution, debt comparison (high-interest debt vs savings rate)

INVESTMENT: Risk profile alignment (actual vs target allocation), diversification across asset classes/sectors/geographies, fee analysis (platform fees + fund ongoing charge figures), tax wrapper efficiency (surplus waterfall: ISA first → pension → bond → GIA), rebalancing triggers when allocation drifts from target

RETIREMENT: Employer pension match maximisation (free money), contribution increase to close income gap, tax relief at marginal rate, National Insurance qualifying year gaps, salary sacrifice for National Insurance savings, fee comparison across pension providers, pension consolidation benefits, decumulation sequence (which accounts to draw from first)

PROTECTION: Coverage gap analysis (life cover need = income replacement + mortgage + dependant costs minus existing cover), policy term alignment with need duration, employer group benefits assessment, self-employed income protection gaps

ESTATE: Will existence and currency, Lasting Power of Attorney (financial + health), Inheritance Tax liability above nil rate bands, gifting strategies (annual exemptions, PETs, normal expenditure), trust structures for tax efficiency, policy trust placement, beneficiary review

TAX: ISA allowance maximisation, pension carry forward utilisation, spousal transfers to lower-rate taxpayer, Capital Gains Tax annual exempt amount usage, dividend allowance planning

Recommendations are ranked by urgency (critical → high → medium → low) and allocated across competing demands using available surplus. Cross-module conflicts are resolved (e.g. pension contribution vs ISA vs debt repayment priorities).
TEXT;
}
