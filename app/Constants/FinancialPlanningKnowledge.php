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
            self::KNOWLEDGE_CAVEAT,
        ]);
    }

    private const KNOWLEDGE_CAVEAT = <<<'TEXT'
IMPORTANT: The above is conceptual knowledge only. NEVER quote specific rates, thresholds, or allowance amounts from this section. ALWAYS use the get_tax_information tool to retrieve current figures before stating any number.
TEXT;

    private const INCOME_CLASSIFICATIONS = <<<'TEXT'
INCOME CLASSIFICATIONS (UK):
- Total Income: sum of all income sources (employment, self-employment, rental, dividend, interest, trust, other)
- Net Income: total income minus pension relief and Gift Aid gross-up
- Adjusted Net Income (ANI): net income minus blind person's allowance. Used for Personal Allowance taper (PA reduces by £1 for every £2 above threshold)
- Threshold Income: ANI minus employee pension contributions. Used for Annual Allowance taper test 1
- Adjusted Income: threshold income plus employer pension contributions. Used for Annual Allowance taper test 2
- "Relevant UK Earnings" for pension contribution relief: ONLY employment income and self-employment profits. Rental income, dividends, interest, trust income, and pension income are NOT relevant UK earnings and do NOT support pension tax relief
- Dividend income: taxed at special dividend rates (lower than income tax rates), with a separate dividend allowance
- Savings interest: may be covered by Personal Savings Allowance (amount depends on tax band) — basic rate taxpayers get a higher allowance than higher rate
- Rental income: taxed as property income, not earned income. Mortgage interest relief on buy-to-let is capped
- High Income Child Benefit Charge: clawback begins at a threshold and is fully reclaimed at a higher threshold — check with get_tax_information for current figures
TEXT;

    private const PENSION_KNOWLEDGE = <<<'TEXT'
PENSION KNOWLEDGE (UK):
- Annual Allowance (AA): maximum tax-relieved pension contributions per tax year across ALL schemes. Use get_tax_information for current limit
- AA Taper: AA reduces for high earners but BOTH tests must be met: threshold income exceeds first limit AND adjusted income exceeds second limit. Reduction is £1 AA lost per £2 of adjusted income over the threshold, down to a minimum. Use get_tax_information for thresholds
- Money Purchase Annual Allowance (MPAA): triggered when a user flexibly accesses a defined contribution pension. Permanently reduces available AA. Check with get_tax_information
- Carry Forward: unused AA from the previous 3 tax years can be carried forward — the user must have been a member of a registered pension scheme in those years
- Tax Relief: contributions receive relief at the member's marginal income tax rate. Relief at source: provider claims basic rate; member claims higher/additional via self-assessment. Net pay: employer deducts before tax (full relief immediate)
- Salary Sacrifice: employer contributes instead of employee — saves both employer and employee National Insurance. The contribution counts as employer (not employee) for taper calculations
- Relevant UK Earnings cap: personal contributions cannot exceed your relevant UK earnings in the tax year (even if AA is higher)
- 25% Tax-Free Lump Sum: up to 25% of the pension can typically be taken tax-free. The remainder is taxed as income
- Pension access age: currently 55, rising to 57 in 2028
- Defined Benefit pensions: provide guaranteed annual income linked to salary and service years. Spouse entitlement typically 50-67%. Transfer values available but regulated advice required for transfers above threshold
- State Pension: requires minimum National Insurance qualifying years. Deferral increases weekly amount. Use get_tax_information for current rates
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
