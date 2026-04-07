<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Defines all AI query types, their classifications, KYC requirements,
 * mandatory tool sequences, and decision tree trigger mappings.
 *
 * Used by QueryClassifier, KycGateChecker, SystemPromptBuilder, and QueryKnowledge.
 */
final class QuerySchemas
{
    // ─── Query Type Constants ────────────────────────────────────────

    public const PROTECTION_COVER = 'protection_cover';

    public const PROTECTION_POLICY = 'protection_policy';

    public const SAVINGS_EMERGENCY = 'savings_emergency';

    public const SAVINGS_ACCOUNTS = 'savings_accounts';

    public const SAVINGS_DEBT = 'savings_debt';

    public const RETIREMENT_CONTRIBUTION = 'retirement_contribution';

    public const RETIREMENT_READINESS = 'retirement_readiness';

    public const RETIREMENT_DECUMULATION = 'retirement_decumulation';

    public const INVESTMENT_PORTFOLIO = 'investment_portfolio';

    public const INVESTMENT_FEES = 'investment_fees';

    public const INVESTMENT_TAX = 'investment_tax';

    public const ESTATE_IHT = 'estate_iht';

    public const ESTATE_PLANNING = 'estate_planning';

    public const GOALS_PROGRESS = 'goals_progress';

    public const TAX_OPTIMISATION = 'tax_optimisation';

    public const PROPERTY = 'property';

    public const INCOME = 'income';

    public const HOLISTIC_HEALTH = 'holistic_health';

    public const GENERAL = 'general';

    public const DATA_ENTRY = 'data_entry';

    public const NAVIGATION = 'navigation';

    public const AFFORDABILITY = 'affordability';

    // ─── Type Groups ─────────────────────────────────────────────────

    /**
     * Types that go through the full FCA 6-step advice process.
     */
    public const ADVICE_TYPES = [
        self::PROTECTION_COVER,
        self::PROTECTION_POLICY,
        self::SAVINGS_EMERGENCY,
        self::SAVINGS_ACCOUNTS,
        self::SAVINGS_DEBT,
        self::RETIREMENT_CONTRIBUTION,
        self::RETIREMENT_READINESS,
        self::RETIREMENT_DECUMULATION,
        self::INVESTMENT_PORTFOLIO,
        self::INVESTMENT_FEES,
        self::INVESTMENT_TAX,
        self::ESTATE_IHT,
        self::ESTATE_PLANNING,
        self::GOALS_PROGRESS,
        self::TAX_OPTIMISATION,
        self::PROPERTY,
        self::INCOME,
        self::HOLISTIC_HEALTH,
        self::AFFORDABILITY,
    ];

    /**
     * Types that bypass the FCA process entirely — data entry and navigation.
     */
    public const BYPASS_TYPES = [
        self::DATA_ENTRY,
        self::NAVIGATION,
    ];

    /**
     * Types that need no mandatory tools or KYC — factual queries.
     */
    public const FACTUAL_TYPES = [
        self::GENERAL,
    ];

    // ─── Module Mapping ──────────────────────────────────────────────

    /**
     * Maps query types to the application modules they relate to.
     */
    public const MODULE_MAP = [
        self::PROTECTION_COVER => ['protection'],
        self::PROTECTION_POLICY => ['protection'],
        self::SAVINGS_EMERGENCY => ['savings'],
        self::SAVINGS_ACCOUNTS => ['savings'],
        self::SAVINGS_DEBT => ['savings'],
        self::RETIREMENT_CONTRIBUTION => ['retirement'],
        self::RETIREMENT_READINESS => ['retirement'],
        self::RETIREMENT_DECUMULATION => ['retirement'],
        self::INVESTMENT_PORTFOLIO => ['investment'],
        self::INVESTMENT_FEES => ['investment'],
        self::INVESTMENT_TAX => ['investment'],
        self::ESTATE_IHT => ['estate'],
        self::ESTATE_PLANNING => ['estate'],
        self::GOALS_PROGRESS => ['goals'],
        self::TAX_OPTIMISATION => ['tax'],
        self::PROPERTY => ['property'],
        self::INCOME => ['income'],
        self::HOLISTIC_HEALTH => ['savings', 'investment', 'retirement', 'protection', 'estate', 'goals', 'tax', 'property', 'income'],
        self::AFFORDABILITY => ['savings', 'income'],
        self::GENERAL => [],
        self::DATA_ENTRY => [],
        self::NAVIGATION => [],
    ];

    // ─── Implicit Related Types ──────────────────────────────────────

    /**
     * When a primary type is classified, these related types are always added.
     * Ensures cross-cutting concerns are never missed.
     */
    public const IMPLICIT_RELATED = [
        self::RETIREMENT_CONTRIBUTION => [self::TAX_OPTIMISATION, self::SAVINGS_EMERGENCY, self::AFFORDABILITY],
        self::RETIREMENT_READINESS => [self::RETIREMENT_CONTRIBUTION, self::TAX_OPTIMISATION],
        self::RETIREMENT_DECUMULATION => [self::TAX_OPTIMISATION],
        self::SAVINGS_EMERGENCY => [self::AFFORDABILITY],
        self::SAVINGS_ACCOUNTS => [self::AFFORDABILITY],
        self::SAVINGS_DEBT => [self::AFFORDABILITY],
        self::INVESTMENT_PORTFOLIO => [self::AFFORDABILITY],
        self::INVESTMENT_TAX => [self::TAX_OPTIMISATION],
        self::ESTATE_IHT => [self::PROPERTY],
        self::HOLISTIC_HEALTH => [self::SAVINGS_EMERGENCY, self::AFFORDABILITY, self::TAX_OPTIMISATION],
        self::GOALS_PROGRESS => [self::AFFORDABILITY],
        self::PROTECTION_COVER => [],
        self::PROTECTION_POLICY => [],
        self::ESTATE_PLANNING => [],
        self::TAX_OPTIMISATION => [],
        self::PROPERTY => [],
        self::INCOME => [],
        self::AFFORDABILITY => [],
        self::GENERAL => [],
        self::DATA_ENTRY => [],
        self::NAVIGATION => [],
        self::INVESTMENT_FEES => [],
    ];

    // ─── Keyword Patterns ────────────────────────────────────────────

    /**
     * Keyword patterns for classifying queries. Checked in order — first match wins as primary.
     * Each entry: query_type => array of regex patterns.
     */
    public const KEYWORD_PATTERNS = [
        self::DATA_ENTRY => [
            '/\bi\s+have\s+(a|an|my)\b/i',
            '/\bi\s+earn\s+£/i',
            '/\bi\s+earn\s+\d/i',
            '/\bi\s+spend\s+£/i',
            '/\bi\s+pay\s+£/i',
            '/\badd\s+(a|an|my)\b/i',
            '/\bmy\s+\w+\s+is\s+£/i',
            '/\bi\s+(got|received|inherited)\s+(a|an|£|\d)/i',
            '/\bupdate\s+(my|the)\b/i',
            '/\bchange\s+(my|the)\b/i',
            '/\bset\s+my\b/i',
            '/\bi\s+put\s+(money|£)/i',
            '/\bi\'ve\s+(got|paid|saved|put|deposited)\b/i',
        ],
        self::NAVIGATION => [
            '/\b(take|go|navigate|show)\s+(me\s+)?(to|the)\b/i',
            '/\bgo\s+to\b/i',
            '/\bopen\s+(my|the)\b/i',
            '/\bshow\s+me\s+(my|the)\b/i',
        ],
        self::HOLISTIC_HEALTH => [
            '/\b(financial|total|overall|full)\s+(health|review|position|picture|summary|overview)\b/i',
            '/\bwhat\s+should\s+i\s+(focus|prioriti[sz]e|do\s+first|do\s+with)\b/i',
            '/\b(bonus|windfall|lump\s+sum|inheritance).*\b(what|how|where|should)\b/i',
            '/\bwhat\s+(should|can)\s+i\s+do\s+with\b/i',
            '/\bhow\s+am\s+i\s+doing\s+(financially|overall|in\s+general)\b/i',
            '/\bbest\s+use\s+of\s+(my\s+)?money\b/i',
        ],
        self::RETIREMENT_CONTRIBUTION => [
            '/\bpension\s+contribution/i',
            '/\b(maximis|maximiz)e?\s+(my\s+)?pension/i',
            '/\bhow\s+much\s+.*pension/i',
            '/\bannual\s+allowance\b/i',
            '/\bpension\s+tax\s+relief\b/i',
            '/\bemployer\s+(match|contribut)/i',
            '/\bsalary\s+sacrifice\b/i',
            '/\bcarry\s+forward\b/i',
        ],
        self::RETIREMENT_READINESS => [
            '/\b(when|can)\s+.*retire/i',
            '/\b(am\s+i|are\s+we)\s+on\s+track.*retire/i',
            '/\bretirement\s+(income|readiness|target|gap)/i',
            '/\bhow\s+much\s+.*need.*retire/i',
            '/\bretirement\s+age\b/i',
        ],
        self::RETIREMENT_DECUMULATION => [
            '/\bdrawdown\b/i',
            '/\b(access|take|withdraw).*pension/i',
            '/\btax[- ]free\s+lump\s+sum\b/i',
            '/\bpension\s+(access|withdrawal|income)/i',
            '/\bdecumulation\b/i',
        ],
        self::PROTECTION_COVER => [
            '/\b(life|death)\s+(cover|insurance)\b/i',
            '/\b(enough|adequate|sufficient)\s+(life\s+)?cover\b/i',
            '/\bcoverage\s+gap\b/i',
            '/\bincome\s+protection\b/i',
            '/\bcritical\s+illness\b/i',
            '/\bprotection\s+(need|gap|review|cover)/i',
            '/\bdependants?\s+(protect|cover|need)/i',
        ],
        self::PROTECTION_POLICY => [
            '/\bpolicy\s+(review|renew|premium|trust)/i',
            '/\bpremium/i',
            '/\bemployer\s+(benefit|group\s+life|death\s+in\s+service)/i',
            '/\btrust\s+placement\b/i',
        ],
        self::SAVINGS_EMERGENCY => [
            '/\bemergency\s+fund\b/i',
            '/\bcash\s+buffer\b/i',
            '/\brainy\s+day\b/i',
            '/\b(enough|adequate)\s+(savings|cash|liquid)/i',
            '/\bmonths?\s+(of\s+)?cover\b/i',
        ],
        self::SAVINGS_ACCOUNTS => [
            '/\bsaving(s)?\s+(account|rate)/i',
            '/\bisa\s+(allowance|rate|account|subscription)/i',
            '/\bjisa\b/i',
            '/\b(junior|children|child).*isa\b/i',
            '/\bfscs\b/i',
            '/\bcash\s+isa\b/i',
            '/\bbest\s+(savings|interest)\s+rate\b/i',
        ],
        self::SAVINGS_DEBT => [
            '/\b(pay\s+off|repay).*mortgage\b.*\b(or|vs|versus)\b.*\b(invest|save)\b/i',
            '/\b(invest|save)\b.*\b(or|vs|versus)\b.*\b(pay\s+off|repay).*mortgage\b/i',
            '/\bdebt\s+(vs|versus|or)\s+(sav|invest)/i',
            '/\boffset\s+mortgage\b/i',
            '/\bcredit\s+card\s+(debt|balance|repay)/i',
        ],
        self::INVESTMENT_PORTFOLIO => [
            '/\bportfolio\s+(risk|allocation|balance|review)/i',
            '/\basset\s+allocation\b/i',
            '/\bdiversif/i',
            '/\brebalance?\b/i',
            '/\brisk\s+profile\b/i',
        ],
        self::INVESTMENT_FEES => [
            '/\bfund\s+fee/i',
            '/\bplatform\s+fee/i',
            '/\btotal\s+(cost|expense|fee)/i',
            '/\bongoing\s+charge/i',
        ],
        self::INVESTMENT_TAX => [
            '/\bisa\s+vs\s+gia\b/i',
            '/\bbed[- ]and[- ]isa\b/i',
            '/\btax[- ]loss\s+harvest/i',
            '/\binvestment\s+tax\b/i',
            '/\b(gia|general\s+investment)\s+(tax|wrap)/i',
        ],
        self::ESTATE_IHT => [
            '/\binheritance\s+tax\b/i',
            '/\biht\b/i',
            '/\bnil\s+rate\s+band\b/i',
            '/\bestate\s+(value|tax|liability)/i',
            '/\bhow\s+much\s+.*estate\s+.*tax/i',
        ],
        self::ESTATE_PLANNING => [
            '/\bwill(s)?\b/i',
            '/\btrust(s)?\b/i',
            '/\blast(ing)?\s+power\s+of\s+attorney\b/i',
            '/\blpa\b/i',
            '/\bgifting\s+(strategy|plan)/i',
            '/\bbeneficiar/i',
            '/\bestate\s+plan/i',
        ],
        self::GOALS_PROGRESS => [
            '/\bgoal\s+(track|progress|on\s+track)/i',
            '/\b(am\s+i|are\s+we)\s+on\s+track\b/i',
            '/\bcontribution\s+(adequate|enough|target)/i',
        ],
        self::TAX_OPTIMISATION => [
            '/\btax\s+(plan|optimi[sz]|efficien|strateg|saving|position)/i',
            '/\bspousal\s+transfer\b/i',
            '/\bcapital\s+gains\s+tax\b/i',
            '/\bcgt\b/i',
            '/\bdividend\s+(tax|allowance|planning)/i',
            '/\bisa\s+allowance\b/i',
        ],
        self::PROPERTY => [
            '/\bproperty\s+(value|equity|portfolio)/i',
            '/\bmortgage\s+(balance|rate|term|review)/i',
            '/\brental\s+(income|yield)/i',
            '/\bhouse\s+(price|value)/i',
            '/\bhow\s+much\s+.*property\s+worth/i',
        ],
        self::INCOME => [
            '/\bincome\s+(breakdown|source|type|tax\s+position)/i',
            '/\bdisposable\s+income\b/i',
            '/\btax\s+band\b/i',
            '/\bhow\s+much\s+do\s+i\s+(earn|make|take\s+home)/i',
        ],
        self::AFFORDABILITY => [
            '/\bsurplus\b/i',
            '/\bcan\s+i\s+afford\b/i',
            '/\bdisposable\s+income\b/i',
            '/\bbudget\b/i',
            '/\bhow\s+much\s+.*spare\b/i',
            '/\bhow\s+much\s+.*left\s+over\b/i',
        ],
        self::GENERAL => [
            '/\bnet\s+worth\b/i',
            '/\bwhat\s+(do\s+i\s+have|are\s+my|is\s+my)\b/i',
            '/\bhow\s+much\s+(do\s+i|is|are)\b/i',
            '/\blist\s+(my|all)\b/i',
            '/\bsummar/i',
        ],
    ];

    // ─── KYC Requirements ────────────────────────────────────────────

    /**
     * Universal KYC requirements checked for all advice types.
     */
    public const UNIVERSAL_KYC = [
        'date_of_birth' => 'Date of birth',
        'marital_status' => 'Marital status',
        'employment_status' => 'Employment status',
        'income' => 'Gross annual income',
        'expenditure' => 'Monthly expenditure',
    ];

    /**
     * Additional KYC requirements per module (on top of universal).
     */
    public const MODULE_KYC = [
        'protection' => [
            'family_members' => 'Dependants and their ages',
            'existing_protection' => 'Existing protection policies (or confirmed none)',
            'liabilities' => 'Debts and liabilities',
        ],
        'savings' => [
            'savings_accounts' => 'Existing savings accounts',
        ],
        'retirement' => [
            'pensions' => 'At least one pension record',
            'target_retirement_age' => 'Target retirement age',
        ],
        'investment' => [
            'risk_profile' => 'Completed risk profile',
            'investment_accounts' => 'At least one investment account',
        ],
        'estate' => [
            'assets' => 'At least one asset (property, savings, investments, or pensions)',
            'family_members' => 'Family members',
        ],
    ];

    // ─── Required Tools Per Query Type ───────────────────────────────

    /**
     * Mandatory tool calls per query type. Merged from primary + related types.
     */
    public const REQUIRED_TOOLS = [
        self::RETIREMENT_CONTRIBUTION => [
            'get_tax_information(pension_allowances)',
            'get_tax_information(income_definitions)',
            'get_module_analysis(retirement)',
            'list_records(dc_pension)',
        ],
        self::RETIREMENT_READINESS => [
            'get_module_analysis(retirement)',
            'get_tax_information(pension_allowances)',
            'get_tax_information(state_pension)',
        ],
        self::RETIREMENT_DECUMULATION => [
            'get_module_analysis(retirement)',
            'get_tax_information(pension_allowances)',
            'get_tax_information(income_tax)',
        ],
        self::SAVINGS_EMERGENCY => [
            'get_module_analysis(savings)',
            'list_records(savings_account)',
        ],
        self::SAVINGS_ACCOUNTS => [
            'get_module_analysis(savings)',
            'list_records(savings_account)',
        ],
        self::SAVINGS_DEBT => [
            'get_module_analysis(savings)',
            'list_records(savings_account)',
            'list_records(liability)',
        ],
        self::INVESTMENT_PORTFOLIO => [
            'get_module_analysis(investment)',
            'list_records(investment_account)',
        ],
        self::INVESTMENT_FEES => [
            'get_module_analysis(investment)',
            'list_records(investment_account)',
        ],
        self::INVESTMENT_TAX => [
            'get_tax_information(isa_allowances)',
            'list_records(savings_account)',
            'list_records(investment_account)',
        ],
        self::PROTECTION_COVER => [
            'get_module_analysis(protection)',
            'list_records(life_insurance)',
        ],
        self::PROTECTION_POLICY => [
            'get_module_analysis(protection)',
            'list_records(life_insurance)',
        ],
        self::ESTATE_IHT => [
            'get_tax_information(inheritance_tax)',
            'get_module_analysis(estate)',
            'list_records(property)',
        ],
        self::ESTATE_PLANNING => [
            'get_module_analysis(estate)',
        ],
        self::GOALS_PROGRESS => [
            'get_module_analysis(goals)',
        ],
        self::TAX_OPTIMISATION => [
            'get_tax_information(income_tax)',
            'get_tax_information(isa_allowances)',
            'get_tax_information(pension_allowances)',
        ],
        self::PROPERTY => [
            'list_records(property)',
        ],
        self::INCOME => [
            'get_tax_information(income_tax)',
        ],
        self::HOLISTIC_HEALTH => [
            'get_recommendations()',
            'get_module_analysis(holistic)',
            'generate_financial_plan()',
        ],
        self::AFFORDABILITY => [
            'get_module_analysis(savings)',
        ],
        self::GENERAL => [],
        self::DATA_ENTRY => [],
        self::NAVIGATION => [],
    ];

    // ─── Relevant Triggers Per Query Type ────────────────────────────

    /**
     * ActionDefinition trigger keys relevant to each query type.
     * Merged from primary + related types when building prompt.
     */
    public const RELEVANT_TRIGGERS = [
        self::RETIREMENT_CONTRIBUTION => [
            'employer_match',
            'contribution_increase',
            'tax_relief',
            'annual_allowance_exceeded',
            'personal_allowance_reclaim',
        ],
        self::RETIREMENT_READINESS => [
            'retirement_income_gap',
            'retirement_age_target',
            'state_pension_gap',
        ],
        self::RETIREMENT_DECUMULATION => [
            'drawdown_sequence',
            'tax_free_lump_sum',
        ],
        self::SAVINGS_EMERGENCY => [
            'emergency_fund_critical',
            'emergency_fund_low',
            'emergency_fund_building',
            'emergency_fund_excess',
        ],
        self::SAVINGS_ACCOUNTS => [
            'rate_below_market',
            'fixed_maturity_warning',
            'cash_isa_recommended',
            'fscs_breach',
            'child_no_jisa',
        ],
        self::SAVINGS_DEBT => [
            'debt_rate_exceeds_savings',
            'offset_mortgage_better',
        ],
        self::PROTECTION_COVER => [
            'life_insurance_gap',
            'income_protection_gap',
            'critical_illness_gap',
            'dependants_no_life_cover',
            'self_employed_no_ip',
        ],
        self::PROTECTION_POLICY => [
            'policy_review_due',
            'policy_not_in_trust',
            'employer_group_life',
        ],
        self::INVESTMENT_PORTFOLIO => [
            'risk_profile_missing',
            'rebalance_portfolio',
            'low_diversification',
        ],
        self::INVESTMENT_FEES => [
            'high_total_fees',
            'high_fund_fees',
            'high_platform_fees',
        ],
        self::INVESTMENT_TAX => [
            'open_isa',
            'use_isa_allowance',
            'consider_bonds',
            'isa_not_maxed',
        ],
        self::ESTATE_IHT => [
            'iht_exceeds_nrb',
            'policy_not_in_trust',
            'gifts_pet_window',
            'no_will',
            'no_lpa',
        ],
        self::ESTATE_PLANNING => [
            'no_will',
            'no_lpa',
            'beneficiary_review',
            'trust_review',
        ],
        self::GOALS_PROGRESS => [
            'goal_behind_schedule',
            'goal_contribution_gap',
        ],
        self::TAX_OPTIMISATION => [
            'spousal_transfer_beneficial',
            'cgt_allowance_unused',
            'high_dividend_in_gia',
            'pension_carry_forward_available',
        ],
        self::PROPERTY => [],
        self::INCOME => [],
        self::HOLISTIC_HEALTH => [], // ALL triggers — handled in code
        self::AFFORDABILITY => [],
        self::GENERAL => [],
        self::DATA_ENTRY => [],
        self::NAVIGATION => [],
    ];

    // ─── Knowledge Domain Mapping ────────────────────────────────────

    /**
     * Maps query types to FinancialPlanningKnowledge domain methods.
     */
    public const KNOWLEDGE_DOMAINS = [
        self::RETIREMENT_CONTRIBUTION => ['getPensionKnowledge', 'getIncomeClassifications', 'getAffordabilityRules'],
        self::RETIREMENT_READINESS => ['getPensionKnowledge', 'getIncomeClassifications'],
        self::RETIREMENT_DECUMULATION => ['getPensionKnowledge'],
        self::SAVINGS_EMERGENCY => ['getAffordabilityRules'],
        self::SAVINGS_ACCOUNTS => ['getAffordabilityRules'],
        self::SAVINGS_DEBT => ['getAffordabilityRules'],
        self::INVESTMENT_PORTFOLIO => ['getInvestmentTaxWrappers'],
        self::INVESTMENT_FEES => ['getInvestmentTaxWrappers'],
        self::INVESTMENT_TAX => ['getInvestmentTaxWrappers'],
        self::PROTECTION_COVER => ['getProtectionConcepts'],
        self::PROTECTION_POLICY => ['getProtectionConcepts'],
        self::ESTATE_IHT => ['getEstatePlanningConcepts'],
        self::ESTATE_PLANNING => ['getEstatePlanningConcepts'],
        self::GOALS_PROGRESS => [],
        self::TAX_OPTIMISATION => ['getIncomeClassifications', 'getInvestmentTaxWrappers'],
        self::PROPERTY => [],
        self::INCOME => ['getIncomeClassifications'],
        self::HOLISTIC_HEALTH => [], // ALL domains — handled in code
        self::AFFORDABILITY => ['getAffordabilityRules', 'getIncomeClassifications'],
        self::GENERAL => [],
        self::DATA_ENTRY => [],
        self::NAVIGATION => [],
    ];

    // ─── Record Type Mapping ─────────────────────────────────────────

    /**
     * Maps query types to relevant record types for filtering buildExistingRecordsSummary.
     */
    public const RECORD_TYPES = [
        self::RETIREMENT_CONTRIBUTION => ['dc_pension', 'db_pension'],
        self::RETIREMENT_READINESS => ['dc_pension', 'db_pension'],
        self::RETIREMENT_DECUMULATION => ['dc_pension', 'db_pension'],
        self::SAVINGS_EMERGENCY => ['savings_account'],
        self::SAVINGS_ACCOUNTS => ['savings_account'],
        self::SAVINGS_DEBT => ['savings_account', 'liability'],
        self::INVESTMENT_PORTFOLIO => ['investment_account'],
        self::INVESTMENT_FEES => ['investment_account'],
        self::INVESTMENT_TAX => ['investment_account', 'savings_account'],
        self::PROTECTION_COVER => ['life_insurance', 'critical_illness', 'income_protection', 'family_member'],
        self::PROTECTION_POLICY => ['life_insurance', 'critical_illness', 'income_protection'],
        self::ESTATE_IHT => ['property', 'trust', 'gift', 'liability', 'family_member'],
        self::ESTATE_PLANNING => ['property', 'trust', 'gift', 'family_member'],
        self::GOALS_PROGRESS => ['goal'],
        self::TAX_OPTIMISATION => ['savings_account', 'investment_account', 'dc_pension'],
        self::PROPERTY => ['property', 'mortgage'],
        self::INCOME => [],
        self::HOLISTIC_HEALTH => [], // ALL records
        self::AFFORDABILITY => ['savings_account'],
        self::GENERAL => [], // ALL records
        self::DATA_ENTRY => [], // ALL records (for duplicate detection)
        self::NAVIGATION => [],
    ];

    // ─── Holistic Priority Order ─────────────────────────────────────

    /**
     * Priority order for holistic health reviews (section F of the plan).
     */
    public const HOLISTIC_PRIORITY = [
        1 => 'Liquidity — emergency fund adequacy (liquid assets vs 3-6 months expenses)',
        2 => 'High-interest debt — repayment before investment',
        3 => 'Protection gaps — life, income, critical illness coverage',
        4 => 'Pension contributions — employer match, tax relief, Personal Allowance reclaim at £100,000-£125,140',
        5 => 'Individual Savings Account allowance — use it or lose it (tax year sensitive)',
        6 => 'Further investment/pension — surplus allocation beyond Individual Savings Account',
        7 => 'Estate planning — Inheritance Tax, wills, Lasting Powers of Attorney, gifting strategies',
        8 => 'Goal funding — savings targets and life event preparation',
    ];

    // ─── Helper Methods ──────────────────────────────────────────────

    /**
     * Check if a query type bypasses the FCA process.
     */
    public static function isBypassType(string $type): bool
    {
        return in_array($type, self::BYPASS_TYPES, true);
    }

    /**
     * Check if a query type is an advice type requiring FCA process.
     */
    public static function isAdviceType(string $type): bool
    {
        return in_array($type, self::ADVICE_TYPES, true);
    }

    /**
     * Get all modules for a classification (primary + related).
     */
    public static function getModulesForClassification(array $classification): array
    {
        $modules = self::MODULE_MAP[$classification['primary']] ?? [];

        foreach ($classification['related'] ?? [] as $related) {
            $modules = array_merge($modules, self::MODULE_MAP[$related] ?? []);
        }

        return array_values(array_unique($modules));
    }

    /**
     * Get merged required tools for a classification (primary + related, deduplicated).
     */
    public static function getRequiredToolsForClassification(array $classification): array
    {
        $tools = self::REQUIRED_TOOLS[$classification['primary']] ?? [];

        foreach ($classification['related'] ?? [] as $related) {
            $tools = array_merge($tools, self::REQUIRED_TOOLS[$related] ?? []);
        }

        return array_values(array_unique($tools));
    }

    /**
     * Get merged relevant triggers for a classification (primary + related).
     * For holistic_health, returns ALL triggers from all types.
     */
    public static function getRelevantTriggersForClassification(array $classification): array
    {
        if ($classification['primary'] === self::HOLISTIC_HEALTH) {
            $allTriggers = [];
            foreach (self::RELEVANT_TRIGGERS as $triggers) {
                $allTriggers = array_merge($allTriggers, $triggers);
            }

            return array_values(array_unique($allTriggers));
        }

        $triggers = self::RELEVANT_TRIGGERS[$classification['primary']] ?? [];

        foreach ($classification['related'] ?? [] as $related) {
            $triggers = array_merge($triggers, self::RELEVANT_TRIGGERS[$related] ?? []);
        }

        return array_values(array_unique($triggers));
    }
}
