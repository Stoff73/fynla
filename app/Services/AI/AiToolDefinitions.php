<?php

declare(strict_types=1);

namespace App\Services\AI;

class AiToolDefinitions
{
    /**
     * Get all tool definitions for the Anthropic Messages API.
     */
    public function getTools(bool $isPreviewMode = false): array
    {
        $tools = [
            ...$this->navigationTools(),
            ...$this->analysisTools(),
            ...$this->taxTools(),
            ...$this->planGenerationTools(),
            ...$this->billingTools(),
        ];

        if (! $isPreviewMode) {
            $tools = array_merge(
                $tools,
                $this->whatIfTools(),
                $this->dataCreationTools(),
                $this->additionalCreationTools(),
                $this->dataModificationTools(),
                $this->profileTools(),
                $this->expenditureTools(),
                $this->campaignSaveTaxTools(),
            );
        }

        // Return tools in native format (name, description, parameters)
        // The HasAiChat trait handles provider-specific wrapping:
        // - xAI/OpenAI: wraps in {type: "function", function: {name, description, parameters}}
        // - Anthropic: converts parameters → input_schema
        if (\Illuminate\Support\Facades\Cache::get('ai_provider', config('services.ai_provider', 'anthropic')) === 'xai') {
            return $tools; // Already in the right shape for OpenAI wrapping
        }

        // Anthropic format: parameters → input_schema
        return array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'input_schema' => $tool['parameters'],
        ], $tools);
    }

    private function navigationTools(): array
    {
        return [
            [
                'name' => 'navigate_to_page',
                'description' => 'Navigate the user to a specific page in the application. Use this when the user asks to go somewhere or when showing them relevant information would be helpful.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'route_path' => [
                            'type' => 'string',
                            'description' => 'The application route path. Valid routes: '
                                .'MAIN: /dashboard, /profile, /settings, /settings/security, /settings/assumptions, /help. '
                                .'INCOME & EXPENDITURE: /valuable-info?section=income (Income tab), /valuable-info?section=expenditure (Expenditure tab), /valuable-info?section=letter (Letter to Spouse tab), /valuable-info?section=risk (Risk Profile summary tab). '
                                .'NET WORTH: /net-worth/wealth-summary, /net-worth/property, /net-worth/investments, /net-worth/retirement, /net-worth/cash (Bank Accounts & Savings), /net-worth/business, /net-worth/chattels, /net-worth/liabilities. '
                                .'PROTECTION: /protection. '
                                .'ESTATE: /estate (Estate Planning dashboard), /estate/will-builder (Will Builder), /estate/power-of-attorney (Power of Attorney). '
                                .'TRUSTS: /trusts. '
                                .'GOALS: /goals (Goals & Life Events), /goals?tab=events (Life Events tab). '
                                .'RISK: /risk-profile. '
                                .'PLANS: /plans (all plans), /plans/investment, /plans/retirement, /plans/protection, /plans/estate, /holistic-plan (Holistic Financial Plan). '
                                .'ACTIONS: /actions. '
                                .'PLANNING: /planning/journeys, /planning/what-if (What-If Scenarios). '
                                .'NEVER use /savings or /investment — these are legacy redirects. Use /net-worth/cash and /net-worth/investments instead.',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Brief explanation of why navigating here is helpful',
                        ],
                    ],
                    'required' => ['route_path', 'description'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function analysisTools(): array
    {
        return [
            [
                'name' => 'list_records',
                'description' => 'List existing records of a given type with IDs, key details, balances, interest rates, and values. Use this BEFORE calling update_record to find the correct entity_id. Use this for factual questions about the user\'s accounts — balances, interest rates, providers, policy details. For example: "how much interest will I earn?" → list_records(savings_account). "What pensions do I have?" → list_records(dc_pension). Returns raw data; for full module analysis use get_module_analysis instead.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'entity_type' => [
                            'type' => 'string',
                            'enum' => ['savings_account', 'investment_account', 'dc_pension', 'db_pension', 'property', 'mortgage', 'life_insurance', 'critical_illness', 'income_protection', 'trust', 'business_interest', 'chattel', 'estate_liability', 'estate_gift', 'family_member'],
                            'description' => 'The type of record to list.',
                        ],
                    ],
                    'required' => ['entity_type'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'list_goals',
                'description' => 'List all of the user\'s financial goals with their current progress, status, and IDs. Use this when the user asks about their goals, wants to see progress, or before updating/deleting a specific goal. This is a lightweight call — use it instead of get_module_analysis(goals) when you just need the goal list.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'list_life_events',
                'description' => 'List all of the user\'s life events with dates, amounts, and IDs. Use this when the user asks about their life events, upcoming events, or before updating/deleting a specific event. This is a lightweight call — use it instead of get_module_analysis(goals) when you just need the event list.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'get_module_analysis',
                'description' => 'Get detailed financial analysis for a specific module. Returns personalised analysis based on the user\'s actual financial data.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'module' => [
                            'type' => 'string',
                            'enum' => ['protection', 'savings', 'investment', 'retirement', 'estate', 'goals', 'holistic'],
                            'description' => 'The financial planning module to analyse',
                        ],
                    ],
                    'required' => ['module'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'get_recommendations',
                'description' => 'Get the user\'s personalised financial recommendations ranked by priority across all modules.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'search_conversation_index',
                'description' => 'Search the user\'s prior conversations for context on a topic or entity. Returns up to 10 prior conversations matching the supplied keywords/entity types, ordered by recency. Use ONLY when the `<known_facts>` block is silent on the field you need and you need to know what the user has discussed in earlier sessions (e.g. they say "as we talked about last time" — search for the relevant topic to recover the thread). Do NOT use this as a substitute for list_records or get_module_analysis — those return current authoritative data; this returns historical conversational context.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topic_keywords' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Module-level topic tags to match against the conversation index `topics` field. Allowed values: protection, savings, investment, retirement, estate_planning, goals_life_events, tax_optimisation, family, property, mortgage, billing, general.',
                        ],
                        'entity_types' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Entity types to match against the `entities_mentioned` field. Allowed values: life_insurance_policy, dc_pension, db_pension, isa, gia, savings_account, property, mortgage, credit_card, family_member, goal, life_event, will, trust, business_interest, chattel.',
                        ],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function taxTools(): array
    {
        return [
            [
                'name' => 'get_tax_information',
                'description' => 'Get current UK tax year information for a specific topic. ALWAYS use this tool when the user asks about tax thresholds, allowances, rates, or any financial product tax treatment. Never state tax values from memory — always retrieve them. Use income_definitions to get the user\'s detailed income breakdown including adjusted net income, threshold income, and tapered pension allowances.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => [
                            'type' => 'string',
                            'enum' => [
                                'income_tax', 'national_insurance', 'capital_gains', 'dividend_tax',
                                'inheritance_tax', 'gifting_exemptions', 'stamp_duty',
                                'isa_allowances', 'pension_allowances', 'state_pension',
                                'benefits', 'savings_config', 'assumptions',
                                'investment_bonds', 'venture_capital',
                                'protection_config', 'retirement_config', 'domicile',
                                'income_definitions',
                            ],
                            'description' => 'The tax or financial configuration topic to retrieve. Use income_definitions for the user\'s adjusted net income, threshold income, and tapered allowances.',
                        ],
                    ],
                    'required' => ['topic'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function planGenerationTools(): array
    {
        return [
            [
                'name' => 'generate_financial_plan',
                'description' => 'Generate a comprehensive holistic financial plan for the user. Analyses all modules (protection, savings, investment, retirement, estate, goals) and returns an executive summary, top recommendations, overall score, and action plan. Use this when the user asks for a financial plan, overview of their position, or wants to know what they should prioritise.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function whatIfTools(): array
    {
        return [
            [
                'name' => 'create_what_if_scenario',
                'description' => 'Create a persistent what-if scenario showing how changes would affect the user\'s financial plan. The scenario is saved and the user is navigated to the What If dashboard to see the comparison. Use this when the user asks "what if" questions about their finances.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Short descriptive name for the scenario (e.g. "Retire at 55", "Sell Main Residence")',
                        ],
                        'scenario_type' => [
                            'type' => 'string',
                            'enum' => ['retirement', 'property', 'family', 'income', 'custom'],
                            'description' => 'Category of the what-if scenario',
                        ],
                        'parameters' => [
                            'type' => 'object',
                            'description' => 'The what-if parameter overrides. Keys: retirement_age, pension_contribution, sell_property, buy_property, divorce, marriage, new_child, income_change, job_loss, inheritance',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Your explanation of what this scenario models and the key assumptions',
                        ],
                    ],
                    'required' => ['name', 'scenario_type', 'parameters', 'description'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function dataCreationTools(): array
    {
        return [
            // Goals & life events
            ...$this->goalAndEventTools(),
            // Financial accounts
            ...$this->accountCreationTools(),
            // Property & mortgage
            ...$this->propertyCreationTools(),
            // Protection policies
            ...$this->protectionCreationTools(),
            // Estate planning
            ...$this->estateCreationTools(),
        ];
    }

    private function goalAndEventTools(): array
    {
        return [
            [
                'name' => 'create_goal',
                'description' => 'Create a new financial goal for the user. Use this when the user says they want to save for something specific. You MAY call this tool multiple times in the same turn when the user mentions multiple goals.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name of the goal (e.g., "Holiday Fund", "House Deposit")',
                        ],
                        'target_amount' => [
                            'type' => 'number',
                            'description' => 'Target amount in pounds',
                        ],
                        'target_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Target date in YYYY-MM-DD format',
                        ],
                        'priority' => [
                            'type' => 'string',
                            'enum' => ['critical', 'high', 'medium', 'low'],
                            'description' => 'Priority level of the goal',
                        ],
                        'goal_type' => [
                            'type' => 'string',
                            'enum' => ['emergency_fund', 'house_deposit', 'holiday', 'education', 'wedding', 'car', 'retirement_supplement', 'other'],
                            'description' => 'Type of goal',
                        ],
                        'monthly_contribution' => [
                            'type' => 'number',
                            'description' => 'Optional monthly contribution amount in pounds. If provided, Fyn will assess whether this is sufficient to reach the target by the deadline.',
                        ],
                    ],
                    'required' => ['name', 'target_amount', 'target_date', 'priority', 'goal_type'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_life_event',
                'description' => 'Create a future life event that may impact the user\'s financial plan. You MAY call this tool multiple times in the same turn when the user mentions multiple events.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'event_type' => [
                            'type' => 'string',
                            'description' => 'Type of life event (e.g., "marriage", "graduation", "career_change", "property_purchase", "retirement")',
                        ],
                        'event_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Expected date in YYYY-MM-DD format',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Description of the event',
                        ],
                        'estimated_cost' => [
                            'type' => 'number',
                            'description' => 'Estimated cost in pounds (if applicable)',
                        ],
                    ],
                    'required' => ['event_type', 'event_date', 'description'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function accountCreationTools(): array
    {
        return [
            [
                'name' => 'create_savings_account',
                'description' => 'Create a savings account for the user. Use this when the user mentions a savings account, Cash Individual Savings Account, or cash deposit. You MAY call this tool multiple times in the same turn when the user mentions multiple accounts.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'account_name' => [
                            'type' => 'string',
                            'description' => 'Name of the account (e.g., "Nationwide Cash ISA", "Halifax Easy Saver")',
                        ],
                        'account_type' => [
                            'type' => 'string',
                            'enum' => ['easy_access', 'notice', 'fixed_term', 'regular_saver'],
                            'description' => 'Type of savings account. Default to "easy_access" if not specified.',
                        ],
                        'institution' => [
                            'type' => 'string',
                            'description' => 'Bank or building society name (e.g., "Nationwide", "Halifax")',
                        ],
                        'current_balance' => [
                            'type' => 'number',
                            'description' => 'Current balance in pounds',
                        ],
                        'interest_rate' => [
                            'type' => 'number',
                            'description' => 'Annual interest rate as a percentage (e.g., 4.5 for 4.5%)',
                        ],
                        'is_isa' => [
                            'type' => 'boolean',
                            'description' => 'Whether this is a Cash Individual Savings Account. Default false.',
                        ],
                        'is_emergency_fund' => [
                            'type' => 'boolean',
                            'description' => 'Whether this forms part of the emergency fund. Default false.',
                        ],
                        'regular_contribution_amount' => [
                            'type' => 'number',
                            'description' => 'Monthly contribution amount in pounds, if any',
                        ],
                    ],
                    'required' => ['account_name', 'current_balance'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_investment_account',
                'description' => 'Create an investment account for the user. Use this when the user mentions any investment: ISA, GIA, bond, VCT, EIS, private company shares, crowdfunding, employee share schemes (SAYE, CSOP, EMI, share options, RSUs), or other investments. You MAY call this tool multiple times in the same turn when the user mentions multiple accounts.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'account_name' => [
                            'type' => 'string',
                            'description' => 'Name of the account (e.g., "Vanguard Stocks & Shares ISA", "Hargreaves Lansdown GIA", "Octopus VCT")',
                        ],
                        'account_type' => [
                            'type' => 'string',
                            'enum' => [
                                'stocks_shares_isa', 'lifetime_isa', 'personal_investment_account',
                                'onshore_bond', 'offshore_bond', 'vct', 'eis',
                                'private_company', 'crowdfunding', 'saye', 'csop',
                                'emi', 'unapproved_options', 'rsu', 'other',
                            ],
                            'description' => 'Type of investment account. Use "stocks_shares_isa" for Stocks & Shares ISA, "lifetime_isa" for Lifetime ISA, "personal_investment_account" for GIA, "vct" for Venture Capital Trust, "eis" for Enterprise Investment Scheme, "private_company" for private company shares, "crowdfunding" for crowdfunding investments, "saye" for Save As You Earn/Sharesave, "csop" for Company Share Option Plan, "emi" for Enterprise Management Incentives, "unapproved_options" for unapproved share options, "rsu" for Restricted Stock Units, "other" for anything else. Default to "personal_investment_account" if not specified.',
                        ],
                        'provider' => [
                            'type' => 'string',
                            'description' => 'Platform, provider, or company name (e.g., "Vanguard", "Hargreaves Lansdown", "Octopus Investments")',
                        ],
                        'current_value' => [
                            'type' => 'number',
                            'description' => 'Current value in pounds',
                        ],
                        'monthly_contribution_amount' => [
                            'type' => 'number',
                            'description' => 'Monthly contribution amount in pounds, if any',
                        ],
                        'platform_fee_percent' => [
                            'type' => 'number',
                            'description' => 'Annual platform fee as a percentage (e.g., 0.15 for 0.15%)',
                        ],
                        // Bond-specific fields (onshore_bond, offshore_bond)
                        'bond_purchase_date' => [
                            'type' => 'string',
                            'description' => 'Bond purchase date in YYYY-MM-DD format. Only for onshore_bond or offshore_bond.',
                        ],
                        'bond_withdrawal_taken' => [
                            'type' => 'number',
                            'description' => 'Total 5% tax-deferred withdrawals taken to date in pounds. Only for onshore_bond or offshore_bond.',
                        ],
                        // Private company / Crowdfunding fields
                        'company_legal_name' => [
                            'type' => 'string',
                            'description' => 'Legal name of the company. For private_company or crowdfunding types.',
                        ],
                        'company_registration_number' => [
                            'type' => 'string',
                            'description' => 'Companies House registration number. For private_company or crowdfunding types.',
                        ],
                        'crowdfunding_platform' => [
                            'type' => 'string',
                            'enum' => ['Seedrs', 'Crowdcube', 'Republic', 'Wefunder', 'other'],
                            'description' => 'Crowdfunding platform name. Only for crowdfunding type.',
                        ],
                        'investment_date' => [
                            'type' => 'string',
                            'description' => 'Date of investment in YYYY-MM-DD format. For private_company, crowdfunding, vct, eis.',
                        ],
                        'investment_amount' => [
                            'type' => 'number',
                            'description' => 'Original investment amount in pounds. For private_company, crowdfunding, vct, eis.',
                        ],
                        'number_of_shares' => [
                            'type' => 'number',
                            'description' => 'Number of shares held. For private_company, crowdfunding, vct, eis.',
                        ],
                        'price_per_share' => [
                            'type' => 'number',
                            'description' => 'Price per share in pounds. For private_company, crowdfunding, vct, eis.',
                        ],
                        'instrument_type' => [
                            'type' => 'string',
                            'enum' => ['ordinary_shares', 'preference_shares', 'convertible_loan_note', 'safe', 'revenue_share', 'fund_nominee_interest'],
                            'description' => 'Type of instrument held. For private_company or crowdfunding.',
                        ],
                        'funding_round' => [
                            'type' => 'string',
                            'enum' => ['pre_seed', 'seed', 'series_a', 'series_b', 'series_c', 'bridge', 'safe', 'other'],
                            'description' => 'Funding round. For private_company or crowdfunding.',
                        ],
                        'share_class' => [
                            'type' => 'string',
                            'description' => 'Share class (e.g., "A Ordinary", "B Preference"). For private_company or crowdfunding.',
                        ],
                        'tax_relief_type' => [
                            'type' => 'string',
                            'enum' => ['eis', 'seis', 'sitr', 'vct', ''],
                            'description' => 'Tax relief scheme applied. For private_company, crowdfunding, vct, eis.',
                        ],
                        // Employee share scheme fields (saye, csop, emi, unapproved_options, rsu)
                        'employer_name' => [
                            'type' => 'string',
                            'description' => 'Employer company name. For employee share schemes (saye, csop, emi, unapproved_options, rsu).',
                        ],
                        'employer_is_listed' => [
                            'type' => 'boolean',
                            'description' => 'Whether shares are publicly listed/traded. For employee share schemes.',
                        ],
                        'grant_date' => [
                            'type' => 'string',
                            'description' => 'Date options/shares were granted in YYYY-MM-DD format. For employee share schemes.',
                        ],
                        'units_granted' => [
                            'type' => 'number',
                            'description' => 'Number of units/options granted. For employee share schemes.',
                        ],
                        'exercise_price' => [
                            'type' => 'number',
                            'description' => 'Exercise/strike price per share in pounds. For saye, csop, emi, unapproved_options.',
                        ],
                        'market_value_at_grant' => [
                            'type' => 'number',
                            'description' => 'Market value per share at grant date in pounds. For employee share schemes.',
                        ],
                        'current_share_price' => [
                            'type' => 'number',
                            'description' => 'Current share price in pounds. For employee share schemes.',
                        ],
                        'units_vested' => [
                            'type' => 'number',
                            'description' => 'Number of units currently vested. For employee share schemes.',
                        ],
                        'units_unvested' => [
                            'type' => 'number',
                            'description' => 'Number of units not yet vested. For employee share schemes.',
                        ],
                        'vesting_type' => [
                            'type' => 'string',
                            'enum' => ['cliff', 'monthly', 'quarterly', 'annual', 'performance', 'immediate'],
                            'description' => 'Vesting schedule type. For employee share schemes.',
                        ],
                        'full_vest_date' => [
                            'type' => 'string',
                            'description' => 'Date all units fully vest in YYYY-MM-DD format. For employee share schemes.',
                        ],
                        'cliff_date' => [
                            'type' => 'string',
                            'description' => 'Cliff vesting date in YYYY-MM-DD format. For employee share schemes with cliff vesting.',
                        ],
                        'cliff_percentage' => [
                            'type' => 'number',
                            'description' => 'Percentage that vests at cliff (e.g., 25). For employee share schemes with cliff vesting.',
                        ],
                        // SAYE-specific fields
                        'saye_monthly_savings' => [
                            'type' => 'number',
                            'description' => 'Monthly savings amount (max £500). Only for saye type.',
                        ],
                        'saye_current_savings_balance' => [
                            'type' => 'number',
                            'description' => 'Current savings balance in pounds. Only for saye type.',
                        ],
                        'scheme_start_date' => [
                            'type' => 'string',
                            'description' => 'SAYE contract start date in YYYY-MM-DD format. Only for saye type.',
                        ],
                        'scheme_duration_months' => [
                            'type' => 'number',
                            'enum' => [36, 60],
                            'description' => 'SAYE contract duration: 36 (3 years) or 60 (5 years). Only for saye type.',
                        ],
                    ],
                    'required' => ['account_name', 'current_value'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_holding',
                'description' => 'Add a holding to an EXISTING investment account that was already created WITHOUT holdings. Use this ONLY when the user wants to add holdings to an account that already exists. If the user is creating a NEW account AND mentions holdings at the same time, use create_investment_account with the holdings parameter instead. You MAY call this tool multiple times in the same turn when the user mentions multiple holdings.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'account_name' => [
                            'type' => 'string',
                            'description' => 'Name or provider of the existing investment account to add the holding to.',
                        ],
                        'security_name' => [
                            'type' => 'string',
                            'description' => 'Name of the fund, ETF, or share (e.g. "Vanguard FTSE All-World").',
                        ],
                        'ticker' => [
                            'type' => 'string',
                            'description' => 'Ticker symbol (e.g. "VWRL", "SWDA").',
                        ],
                        'asset_type' => [
                            'type' => 'string',
                            'enum' => ['uk_equity', 'us_equity', 'international_equity', 'fund', 'etf', 'bond', 'cash', 'alternative', 'property'],
                            'description' => '"fund" for OEICs/unit trusts, "etf" for ETFs, "uk_equity" / "us_equity" / "international_equity" for shares, "bond" for fixed income, "cash", "alternative" for commodities/crypto, "property" for property funds.',
                        ],
                        'allocation_percent' => [
                            'type' => 'number',
                            'description' => 'Percentage of the account this holding represents (0-100).',
                        ],
                        'current_price' => [
                            'type' => 'number',
                            'description' => 'Current price per unit in pounds.',
                        ],
                        'ocf_percent' => [
                            'type' => 'number',
                            'description' => 'Ongoing Charge Figure as percentage (e.g. 0.22 for 0.22%).',
                        ],
                    ],
                    'required' => ['account_name', 'security_name', 'asset_type'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_pension',
                'description' => 'Create a pension for the user. Handles both Defined Contribution (workplace, Self-Invested Personal Pension, personal) and Defined Benefit (final salary, career average) pensions. You MAY call this tool multiple times in the same turn when the user mentions multiple pensions.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'pension_category' => [
                            'type' => 'string',
                            'enum' => ['dc', 'db'],
                            'description' => 'Whether this is a Defined Contribution (dc) or Defined Benefit (db) pension. Default "dc" for workplace/SIPP/personal pensions. Use "db" for final salary or career average schemes.',
                        ],
                        'scheme_name' => [
                            'type' => 'string',
                            'description' => 'Name of the pension scheme (e.g., "Aviva Workplace Pension", "NHS Pension Scheme")',
                        ],
                        'scheme_type' => [
                            'type' => 'string',
                            'description' => 'For DC: "workplace", "sipp", or "personal_pension". For DB: "final_salary", "career_average", or "public_sector".',
                        ],
                        'provider' => [
                            'type' => 'string',
                            'description' => 'Pension provider (e.g., "Aviva", "Scottish Widows"). DC pensions only.',
                        ],
                        'current_fund_value' => [
                            'type' => 'number',
                            'description' => 'Current fund value in pounds. DC pensions only.',
                        ],
                        'employee_contribution_percent' => [
                            'type' => 'number',
                            'description' => 'Employee contribution as percentage of salary (e.g., 5 for 5%). DC pensions only.',
                        ],
                        'employer_contribution_percent' => [
                            'type' => 'number',
                            'description' => 'Employer contribution as percentage of salary (e.g., 3 for 3%). DC pensions only.',
                        ],
                        'accrued_annual_pension' => [
                            'type' => 'number',
                            'description' => 'Accrued annual pension in pounds. DB pensions only.',
                        ],
                        'normal_retirement_age' => [
                            'type' => 'integer',
                            'description' => 'Normal retirement age for the scheme. DB pensions only.',
                        ],
                        'pensionable_service_years' => [
                            'type' => 'number',
                            'description' => 'Years of pensionable service. DB pensions only.',
                        ],
                    ],
                    'required' => ['pension_category', 'scheme_name'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function propertyCreationTools(): array
    {
        return [
            [
                'name' => 'create_property',
                'description' => 'Create a property for the user. If they also mention a mortgage, include the outstanding mortgage amount and it will be created automatically. You MAY call this tool multiple times in the same turn when the user mentions multiple properties — the frontend queue saves them in order. Do NOT call navigate_to_page or get_module_analysis in the same turn as create_property — those interrupt the form fill.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'property_type' => [
                            'type' => 'string',
                            'enum' => ['main_residence', 'secondary_residence', 'buy_to_let'],
                            'description' => 'Type of property. Default "main_residence" if this is their home.',
                        ],
                        'current_value' => [
                            'type' => 'number',
                            'description' => 'Current estimated value in pounds',
                        ],
                        'purchase_price' => [
                            'type' => 'number',
                            'description' => 'Original purchase price in pounds',
                        ],
                        'purchase_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Purchase date in YYYY-MM-DD format (approximate year is fine, e.g., "2018-01-01")',
                        ],
                        'address_line_1' => [
                            'type' => 'string',
                            'description' => 'Street address or description',
                        ],
                        'postcode' => [
                            'type' => 'string',
                            'description' => 'UK postcode',
                        ],
                        'outstanding_mortgage' => [
                            'type' => 'number',
                            'description' => 'Outstanding mortgage balance in pounds. If provided, a linked mortgage will be created automatically.',
                        ],
                        'mortgage_rate' => [
                            'type' => 'number',
                            'description' => 'Mortgage interest rate as a percentage (e.g., 4.2 for 4.2%). Only used if outstanding_mortgage is provided.',
                        ],
                        'mortgage_lender' => [
                            'type' => 'string',
                            'description' => 'Mortgage lender name. Only used if outstanding_mortgage is provided.',
                        ],
                        'monthly_rental_income' => [
                            'type' => 'number',
                            'description' => 'Monthly rental income in pounds. For buy-to-let properties.',
                        ],
                    ],
                    'required' => ['property_type', 'current_value'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_mortgage',
                'description' => 'Create a standalone mortgage linked to an existing property. Use this when the user mentions a mortgage separately from a property, or wants to add a mortgage to an existing property. You MAY call this tool multiple times in the same turn when the user mentions multiple mortgages.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'property_address_hint' => [
                            'type' => 'string',
                            'description' => 'A hint to match the property — can be address, postcode, or description like "my main home". The system will fuzzy-match against existing properties.',
                        ],
                        'lender_name' => [
                            'type' => 'string',
                            'description' => 'Mortgage lender (e.g., "Halifax", "Nationwide")',
                        ],
                        'outstanding_balance' => [
                            'type' => 'number',
                            'description' => 'Outstanding mortgage balance in pounds',
                        ],
                        'interest_rate' => [
                            'type' => 'number',
                            'description' => 'Current interest rate as a percentage (e.g., 4.2 for 4.2%)',
                        ],
                        'mortgage_type' => [
                            'type' => 'string',
                            'enum' => ['repayment', 'interest_only', 'mixed'],
                            'description' => 'Mortgage repayment type. Default "repayment".',
                        ],
                        'rate_type' => [
                            'type' => 'string',
                            'enum' => ['fixed', 'variable', 'tracker'],
                            'description' => 'Interest rate type. Default "fixed".',
                        ],
                        'monthly_payment' => [
                            'type' => 'number',
                            'description' => 'Monthly payment amount in pounds',
                        ],
                        'remaining_term_months' => [
                            'type' => 'integer',
                            'description' => 'Remaining mortgage term in months',
                        ],
                    ],
                    'required' => ['outstanding_balance'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function protectionCreationTools(): array
    {
        return [
            [
                'name' => 'create_protection_policy',
                'description' => 'Create a protection insurance policy for the user. Handles life insurance, critical illness cover, and income protection policies. You MAY call this tool multiple times in the same turn when the user mentions multiple policies (e.g. life insurance AND critical illness).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'policy_type' => [
                            'type' => 'string',
                            'enum' => ['level_term', 'term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'standalone_ci', 'accelerated_ci', 'income_protection'],
                            'description' => 'Type of policy. "level_term" for level term life insurance, "term" for generic term life, "whole_of_life" for whole of life, "decreasing_term" for decreasing term (e.g., mortgage protection), "family_income_benefit" for family income benefit, "standalone_ci" for standalone critical illness, "accelerated_ci" for accelerated critical illness (combined with life cover), "income_protection" for income protection.',
                        ],
                        'provider' => [
                            'type' => 'string',
                            'description' => 'Insurance provider (e.g., "Aviva", "Legal & General")',
                        ],
                        'sum_assured' => [
                            'type' => 'number',
                            'description' => 'Sum assured / cover amount in pounds. For life and critical illness policies.',
                        ],
                        'benefit_amount' => [
                            'type' => 'number',
                            'description' => 'Monthly benefit amount in pounds. For income protection policies only.',
                        ],
                        'premium_amount' => [
                            'type' => 'number',
                            'description' => 'Premium amount in pounds',
                        ],
                        'premium_frequency' => [
                            'type' => 'string',
                            'enum' => ['monthly', 'annually'],
                            'description' => 'How often premiums are paid. Default "monthly".',
                        ],
                        'policy_term_years' => [
                            'type' => 'integer',
                            'description' => 'Policy term in years (not applicable for whole of life)',
                        ],
                        'policy_start_date' => [
                            'type' => 'string',
                            'description' => 'Policy start date. Pass the user-supplied phrase verbatim (e.g. "today", "26 April 2026", "last Monday") — the server parses it deterministically.',
                        ],
                        'in_trust' => [
                            'type' => 'boolean',
                            'description' => 'Whether the policy is written in trust for Inheritance Tax planning. Default false.',
                        ],
                    ],
                    'required' => ['policy_type'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function estateCreationTools(): array
    {
        return [
            [
                'name' => 'create_asset',
                'description' => 'Create an asset. Use this for assets not covered by other tools — such as collectibles, artwork, or other valuable items the user wants to track. You MAY call this tool multiple times in the same turn when the user mentions multiple assets.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'asset_name' => [
                            'type' => 'string',
                            'description' => 'Name or description of the asset',
                        ],
                        'asset_type' => [
                            'type' => 'string',
                            'enum' => ['property', 'pension', 'investment', 'business', 'other'],
                            'description' => 'Type of estate asset. Use "other" for cash, collectibles, and similar.',
                        ],
                        'current_value' => [
                            'type' => 'number',
                            'description' => 'Current estimated value in pounds',
                        ],
                        'is_iht_exempt' => [
                            'type' => 'boolean',
                            'description' => 'Whether the asset is exempt from Inheritance Tax (e.g., business property relief). Default false.',
                        ],
                        'exemption_reason' => [
                            'type' => 'string',
                            'description' => 'Reason for Inheritance Tax exemption, if applicable',
                        ],
                    ],
                    'required' => ['asset_name', 'asset_type', 'current_value'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_liability',
                'description' => 'Create a liability. Use this when the user mentions any debt: credit cards, personal loans, student loans, car finance, or any other outstanding balance owed. You MAY call this tool multiple times in the same turn when the user mentions multiple liabilities.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'liability_name' => [
                            'type' => 'string',
                            'description' => 'Name or description of the liability',
                        ],
                        'liability_type' => [
                            'type' => 'string',
                            'enum' => ['loan', 'personal_loan', 'credit_card', 'mortgage', 'student_loan', 'other'],
                            'description' => 'Type of liability',
                        ],
                        'current_balance' => [
                            'type' => 'number',
                            'description' => 'Outstanding balance in pounds',
                        ],
                        'monthly_payment' => [
                            'type' => 'number',
                            'description' => 'Monthly payment amount in pounds',
                        ],
                        'interest_rate' => [
                            'type' => 'number',
                            'description' => 'Interest rate as a percentage',
                        ],
                    ],
                    'required' => ['liability_name', 'liability_type', 'current_balance'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_estate_gift',
                'description' => 'Record a gift for Inheritance Tax planning. Use this when the user mentions gifts they have made or plan to make, as these affect their Inheritance Tax position under the 7-year rule. You MAY call this tool multiple times in the same turn when the user mentions multiple gifts.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'gift_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Date the gift was or will be made, in YYYY-MM-DD format',
                        ],
                        'recipient' => [
                            'type' => 'string',
                            'description' => 'Name of the recipient',
                        ],
                        'gift_type' => [
                            'type' => 'string',
                            'enum' => ['pet', 'clt', 'exempt', 'small_gift', 'annual_exemption'],
                            'description' => 'Inheritance Tax classification. "pet" for Potentially Exempt Transfer (most common — gifts to individuals), "clt" for Chargeable Lifetime Transfer (gifts to trusts), "exempt" for exempt gifts (e.g., to spouse or charity), "small_gift" for small gifts up to £250 per recipient, "annual_exemption" for annual exemption gifts up to £3,000 per year. Default to "pet" for most gifts between individuals.',
                        ],
                        'gift_value' => [
                            'type' => 'number',
                            'description' => 'Value of the gift in pounds',
                        ],
                        'notes' => [
                            'type' => 'string',
                            'description' => 'Additional notes about the gift',
                        ],
                    ],
                    'required' => ['gift_date', 'recipient', 'gift_type', 'gift_value'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_will',
                'description' => 'Record the user\'s will details. Use when the user tells you they have a will and shares executor, beneficiaries, guardians, or specific gifts information. For existing wills only — the Will Builder UI remains the tool for drafting a new will from scratch.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'executor_name' => [
                            'type' => 'string',
                            'description' => 'Full name of the primary executor.',
                        ],
                        'residuary_beneficiary' => [
                            'type' => 'string',
                            'description' => 'Named primary residuary beneficiary — who receives the bulk of the estate after specific gifts and debts.',
                        ],
                        'guardian_for_minors' => [
                            'type' => 'string',
                            'description' => 'Named guardian for any minor children, if the user has minor dependants.',
                        ],
                        'specific_gifts' => [
                            'type' => 'string',
                            'description' => 'Free-text description of specific gifts (item, recipient). Leave blank if the user mentions no specific gifts.',
                        ],
                        'spouse_primary_beneficiary' => [
                            'type' => 'boolean',
                            'description' => 'Whether the user\'s spouse is the primary beneficiary. Defaults true if the user is married and did not specify otherwise.',
                        ],
                    ],
                    'required' => ['executor_name'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'update_will',
                'description' => 'Update an existing will record. Use when the user amends their will details (new executor, new beneficiary, updated specific gifts).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'executor_name' => ['type' => 'string', 'description' => 'New executor name.'],
                        'residuary_beneficiary' => ['type' => 'string', 'description' => 'New residuary beneficiary.'],
                        'guardian_for_minors' => ['type' => 'string', 'description' => 'New guardian for minors.'],
                        'specific_gifts' => ['type' => 'string', 'description' => 'New specific gifts description.'],
                        'spouse_primary_beneficiary' => ['type' => 'boolean', 'description' => 'Spouse as primary beneficiary flag.'],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_power_of_attorney',
                'description' => <<<'DESC'
                Record a Lasting Power of Attorney (LPA) the user already has in place. UK has two types: Property & Financial Affairs (lpa_type=property_financial) and Health & Welfare (lpa_type=health_welfare). For each, capture the primary attorney name. Replacement attorneys are optional. You MAY call this tool multiple times in the same turn — for example if the user has BOTH a property_financial AND a health_welfare LPA, call create_power_of_attorney TWICE in your first response.

                STATUS IS MANDATORY — extract it from the user's wording:
                  • "registered", "in force", "active with OPG", "already registered with the Office of the Public Guardian" → status = "registered"
                  • "draft", "signed but not registered", "not yet registered", "in the pipeline", "sent off for registration", "being registered", "pending registration" → status = "draft"
                  • If the user gives no signal at all, default to "draft".

                NEVER drop status=registered when the user said so. Worked example:
                  User: "I have a registered property and financial LPA with my brother Tom"
                  Required: create_power_of_attorney(lpa_type='property_financial', primary_attorney_name='Tom', status='registered').
                DESC,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'lpa_type' => [
                            'type' => 'string',
                            'enum' => ['property_financial', 'health_welfare'],
                            'description' => 'Which LPA type. property_financial covers money/property decisions. health_welfare covers medical/care decisions.',
                        ],
                        'primary_attorney_name' => [
                            'type' => 'string',
                            'description' => 'Full name of the primary attorney (the person empowered to act for the donor).',
                        ],
                        'replacement_attorney_name' => [
                            'type' => 'string',
                            'description' => 'Optional. Full name of a replacement attorney who steps in if the primary is unable or unwilling to act.',
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['draft', 'registered'],
                            'description' => 'LPA status. If user says "registered" / "in force" / "active with OPG" → "registered". If user says "draft" / "not registered" / "pending" / "being registered" → "draft". Default "draft" if not stated.',
                        ],
                        'opg_reference' => [
                            'type' => 'string',
                            'description' => 'Office of the Public Guardian registration reference, if the LPA is registered.',
                        ],
                    ],
                    'required' => ['lpa_type', 'primary_attorney_name'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'update_power_of_attorney',
                'description' => 'Update an existing LPA record (e.g. status change from draft to registered, OPG reference added, replacement attorney added).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'lpa_id' => ['type' => 'integer', 'description' => 'ID of the LPA to update.'],
                        'status' => ['type' => 'string', 'enum' => ['draft', 'registered']],
                        'opg_reference' => ['type' => 'string'],
                        'primary_attorney_name' => ['type' => 'string'],
                        'replacement_attorney_name' => ['type' => 'string'],
                    ],
                    'required' => ['lpa_id'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function additionalCreationTools(): array
    {
        return [
            [
                'name' => 'create_family_member',
                'description' => 'Add a family member (spouse, child, dependent). Use when the user mentions family members who affect their financial planning. You MAY call this tool multiple times in the same turn when the user mentions multiple family members — for two children, call create_family_member TWICE in your first response.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => ['type' => 'string', 'description' => 'First name'],
                        'surname' => ['type' => 'string', 'description' => 'Surname'],
                        'relationship' => ['type' => 'string', 'enum' => ['spouse', 'child', 'parent', 'sibling', 'other'], 'description' => 'Relationship to the user'],
                        'date_of_birth' => ['type' => 'string', 'description' => 'Date of birth (YYYY-MM-DD)'],
                        'gender' => ['type' => 'string', 'enum' => ['male', 'female', 'other']],
                        'is_dependent' => ['type' => 'boolean', 'description' => 'Whether this person is financially dependent on the user'],
                    ],
                    'required' => ['first_name', 'relationship'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_trust',
                'description' => 'Record a trust for estate planning. Use when the user mentions trusts they have set up or want to document. You MAY call this tool multiple times in the same turn when the user mentions multiple trusts.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'trust_name' => ['type' => 'string', 'description' => 'Name of the trust'],
                        'trust_type' => ['type' => 'string', 'enum' => ['discretionary', 'bare', 'interest_in_possession', 'life_insurance', 'loan', 'discounted_gift', 'accumulation_maintenance'], 'description' => 'Type of trust'],
                        'current_value' => ['type' => 'number', 'description' => 'Current value of assets in trust (£)'],
                        'date_established' => ['type' => 'string', 'description' => 'Date trust was established (YYYY-MM-DD)'],
                        'settlor' => ['type' => 'string', 'description' => 'Who settled the trust'],
                    ],
                    'required' => ['trust_name', 'trust_type'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_business_interest',
                'description' => 'Record a business interest or ownership. Use when the user mentions business ownership, partnerships, or self-employment assets. You MAY call this tool multiple times in the same turn when the user mentions multiple businesses.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'business_name' => ['type' => 'string', 'description' => 'Name of the business'],
                        'business_type' => ['type' => 'string', 'enum' => ['sole_trader', 'partnership', 'limited_company', 'llp'], 'description' => 'Type of business entity'],
                        'ownership_percentage' => ['type' => 'number', 'description' => 'Percentage owned (0-100)'],
                        'estimated_value' => ['type' => 'number', 'description' => 'Estimated value of the interest (£)'],
                        'annual_profit' => ['type' => 'number', 'description' => 'Annual profit/drawings (£)'],
                    ],
                    'required' => ['business_name', 'business_type'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'create_chattel',
                'description' => 'Record a personal valuable item (jewellery, art, collectibles, vehicles). Use when the user mentions valuable personal possessions. You MAY call this tool multiple times in the same turn when the user mentions multiple items.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'description' => ['type' => 'string', 'description' => 'Description of the item'],
                        'category' => ['type' => 'string', 'enum' => ['jewellery', 'art', 'antiques', 'collectibles', 'vehicles', 'other'], 'description' => 'Category of item'],
                        'estimated_value' => ['type' => 'number', 'description' => 'Estimated current value (£)'],
                        'purchase_value' => ['type' => 'number', 'description' => 'Original purchase value (£)'],
                        'is_insured' => ['type' => 'boolean', 'description' => 'Whether the item is insured'],
                    ],
                    'required' => ['description', 'estimated_value'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function dataModificationTools(): array
    {
        return [
            [
                'name' => 'update_record',
                'description' => 'Update an existing record. Use when the user wants to change details of an existing goal, account, property, pension, policy, or other financial record. Ask the user to confirm the changes before calling this tool. You MAY call this tool multiple times in the same turn when the user retracts or amends multiple records in one message. The schema restricts which fields are editable per entity_type — invented field names will be rejected.',
                'parameters' => $this->updateRecordSchema(),
            ],
            [
                'name' => 'delete_record',
                'description' => 'Delete an existing record. Two-phase confirmation: the first call returns a confirmation_token and a preview_message — DO NOT delete on that turn; show the user the preview_message and ask them to confirm. Only on a second call, with the exact same confirmation_token echoed back, does the deletion proceed. Tokens are bound to (user, entity_type, entity_id, today\'s date) and cannot be replayed across days.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'entity_type' => [
                            'type' => 'string',
                            'enum' => ['goal', 'life_event', 'savings_account', 'investment_account', 'dc_pension', 'db_pension', 'property', 'mortgage', 'life_insurance', 'critical_illness', 'income_protection', 'estate_asset', 'estate_liability', 'estate_gift', 'family_member', 'trust', 'business_interest', 'chattel'],
                            'description' => 'The type of record to delete',
                        ],
                        'entity_id' => ['type' => 'integer', 'description' => 'The ID of the record to delete'],
                        'confirmation_token' => [
                            'type' => 'string',
                            'description' => 'Optional. Omit on the first call (you will receive a token). On the second call, pass the exact 64-character token from the first response. Without a matching token the call returns requires_confirmation again and does NOT delete.',
                        ],
                    ],
                    'required' => ['entity_type', 'entity_id'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Build the `update_record` parameter schema as a oneOf of per-entity
     * branches, sourcing allowed fields from {@see UpdateRecordAllowlist}.
     *
     * Each branch pins `entity_type` to a const and restricts `fields` to
     * the per-entity allowlist with `additionalProperties: false`. The
     * runtime handler still re-checks the allowlist (defence-in-depth)
     * because the LLM occasionally ignores schema constraints.
     */
    private function updateRecordSchema(): array
    {
        $oneOf = [];
        foreach (\App\Constants\UpdateRecordAllowlist::MAP as $entityType => $allowedFields) {
            $properties = [];
            foreach ($allowedFields as $field) {
                $properties[$field] = ['type' => ['string', 'number', 'boolean', 'null']];
            }

            $oneOf[] = [
                'type' => 'object',
                'properties' => [
                    'entity_type' => ['const' => $entityType],
                    'entity_id' => ['type' => 'integer', 'description' => 'The ID of the record to update.'],
                    'fields' => [
                        'type' => 'object',
                        'description' => 'Key-value pairs to update. Only the fields listed for this entity_type are accepted.',
                        'properties' => $properties,
                        'additionalProperties' => false,
                    ],
                ],
                'required' => ['entity_type', 'entity_id', 'fields'],
                'additionalProperties' => false,
            ];
        }

        return ['oneOf' => $oneOf];
    }

    /**
     * Handoff tools — surfaced to the LLM only during the onboarding
     * inline-capture turn. Not included in getTools() so they never leak
     * into AdviceFyn's chat turn. OnboardingChatDirector::handleInlineCapture
     * opts into them via toolsListOverride.
     *
     * Tool names are defined as constants on HandoffContract so a typo
     * fails at parse time rather than silently mis-routing at runtime.
     *
     * @param  string  $provider  'anthropic' or 'xai' — picks output format.
     * @return list<array<string, mixed>>
     */
    public function handoffTools(string $provider = 'anthropic'): array
    {
        $tools = [
            [
                'name' => \App\Services\AI\HandoffContract::DELEGATE_TO_CAPTURE,
                'description' => 'Internal. Emit this when you (advice Fyn) cannot answer without data the user has not supplied, or when the user asks for an inline capture mid-conversation. Never shown to the user. The orchestrator will hand off to data-capture Fyn and re-invoke you once capture is complete.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Why capture is needed (e.g. "retirement advice blocked on missing pension data").',
                        ],
                        'entity_types' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Record types to capture (dc_pension, savings_account, property, etc.). Drawn from data_capture persona allowed_tools.',
                        ],
                        'fields_needed' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Optional. Specific fields required to unblock the advice answer.',
                        ],
                    ],
                    'required' => ['reason', 'entity_types'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => \App\Services\AI\HandoffContract::CAPTURE_COMPLETE,
                'description' => 'Internal. Emit this when you (data-capture Fyn) have finished capturing the records the user described. The orchestrator will return control to advice Fyn.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'summary' => [
                            'type' => 'string',
                            'description' => 'Short user-facing recap (e.g. "Added Scottish Widows SIPP £50k").',
                        ],
                        'records_created' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['type' => 'string'],
                                    'id' => ['type' => ['integer', 'string']],
                                ],
                                'required' => ['type', 'id'],
                                'additionalProperties' => false,
                            ],
                            'description' => 'Structured list of records created or updated this sub-conversation.',
                        ],
                    ],
                    'required' => ['summary', 'records_created'],
                    'additionalProperties' => false,
                ],
            ],
        ];

        if ($provider === 'xai') {
            return array_map(fn (array $tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                ],
            ], $tools);
        }

        return array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'input_schema' => $tool['parameters'],
        ], $tools);
    }

    /**
     * Onboarding extraction tools — used ONLY by OnboardingChatDirector
     * during grouped_extract turns. Not included in getTools() so they
     * never leak into the normal Fyn chat token budget. The director
     * passes this list as a `toolsListOverride` when delegating the
     * grouped turn to the coordinating agent.
     *
     * Each tool extracts a named group of onboarding fields from a
     * free-text reply and returns a structured payload. Handlers in
     * CoordinatingAgent write the fields to the database and return
     * a capture receipt that HasAiChat yields as an SSE
     * `onboarding_field_captured` event.
     *
     * @param  string  $provider  'anthropic' or 'xai' — picks the output
     *                            format so the director can route the
     *                            same tools to either API.
     * @return list<array<string, mixed>>
     */
    public function onboardingExtractionTools(string $provider = 'anthropic'): array
    {
        $tools = [
            [
                'name' => 'capture_personal_details',
                'description' => 'Capture the user\'s date of birth and/or marital status from a free-text reply during onboarding. Call this once per turn. Do not call any other tool. CRITICAL: only include a field in the arguments when the user has EXPLICITLY stated it in their reply. Do not guess, infer, or default any field. If the user only gave their date of birth, include only date_of_birth. If they only gave their marital status, include only marital_status. Omit a field entirely rather than inventing a value — the onboarding flow will re-ask for anything missing.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_of_birth' => [
                            'type' => 'string',
                            'description' => 'Date of birth in YYYY-MM-DD format, parsed from natural language like "12 January 1985". Only include this field if the user explicitly stated a date of birth.',
                        ],
                        'marital_status' => [
                            'type' => 'string',
                            'enum' => ['single', 'married', 'civil_partnership', 'divorced', 'widowed'],
                            'description' => 'The user\'s marital status. Only include this field if the user explicitly stated their marital status. Map phrases: "married" → married, "civil partnership" or "civil partner" → civil_partnership, "single" or "unmarried" → single, "divorced" or "separated" → divorced, "widowed" or "widow" → widowed.',
                        ],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'capture_spouse_details',
                'description' => 'Capture the user\'s spouse or civil partner details from a free-text reply during onboarding. This creates a linked spouse user account. Call this once per turn. Do not call any other tool.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => [
                            'type' => 'string',
                            'description' => 'The spouse or partner\'s first name. Extract from phrases like "my partner Jamie" or "called Sarah".',
                        ],
                        'last_name' => [
                            'type' => 'string',
                            'description' => 'The spouse or partner\'s last name, if provided. Optional.',
                        ],
                        'date_of_birth' => [
                            'type' => 'string',
                            'description' => 'Spouse/partner date of birth in YYYY-MM-DD format. Parse natural language dates into ISO format.',
                        ],
                        'email' => [
                            'type' => 'string',
                            'description' => 'The spouse or partner\'s email address. Required — this is used to create their linked account.',
                        ],
                        'annual_income' => [
                            'type' => 'number',
                            'description' => 'The spouse or partner\'s rough annual income in GBP, if mentioned. Optional. Strip currency symbols and commas; "75k" = 75000.',
                        ],
                    ],
                    'required' => ['first_name', 'date_of_birth', 'email'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'capture_dependants',
                'description' => 'Capture a list of the user\'s dependants (children or other dependants) from a free-text reply during onboarding. Call this once per turn with an array of all dependants mentioned. Do not call any other tool.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'dependants' => [
                            'type' => 'array',
                            'description' => 'One entry per dependant mentioned. Names may be omitted if the user did not provide them (use null).',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'first_name' => [
                                        'type' => 'string',
                                        'description' => 'The dependant\'s first name if mentioned, otherwise null.',
                                    ],
                                    'age' => [
                                        'type' => 'integer',
                                        'description' => 'The dependant\'s age in whole years. Required.',
                                    ],
                                    'relationship' => [
                                        'type' => 'string',
                                        'enum' => ['child', 'parent', 'other_dependent'],
                                        'description' => 'Child (son, daughter, step-child, etc.), parent (mother, father, in-law), or other_dependent (sibling, nephew, elderly relative, friend).',
                                    ],
                                ],
                                'required' => ['age', 'relationship'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['dependants'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'capture_work_details',
                'description' => 'Capture the user\'s employer, position, and annual income from a free-text reply during onboarding. Only used when the user is employed, self-employed, or part-time. Call this once per turn. Do not call any other tool.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'employer' => [
                            'type' => 'string',
                            'description' => 'The company or employer name. For self-employed users this may be their trading name or "self-employed".',
                        ],
                        'occupation' => [
                            'type' => 'string',
                            'description' => 'The user\'s job title or role. e.g. "Software engineer", "Sole trader", "Consultant".',
                        ],
                        'annual_income' => [
                            'type' => 'number',
                            'description' => 'Gross annual income in GBP. Strip currency symbols and commas; "75k" = 75000. For self-employed users this is self-employment income.',
                        ],
                    ],
                    'required' => ['employer', 'occupation', 'annual_income'],
                    'additionalProperties' => false,
                ],
            ],
        ];

        if ($provider === 'xai') {
            // xAI / OpenAI function-calling format: wrap in function object
            // with strict mode. Matches the shape XaiToolDefinitions emits.
            return array_map(function (array $tool): array {
                $params = $tool['parameters'];
                // strict mode requires all properties in `required` — for
                // extraction tools we already mark required fields, so we
                // just copy the flag through.
                $params['additionalProperties'] = false;

                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'],
                        'parameters' => $params,
                        'strict' => false, // strict=false to allow optional fields like last_name
                    ],
                ];
            }, $tools);
        }

        // Anthropic format: parameters → input_schema
        return array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'input_schema' => $tool['parameters'],
        ], $tools);
    }

    /**
     * SaveTax campaign — sections 4-6 capture tools.
     *
     * Used in the campaign-only state-machine branch after expenditure capture
     * (path=campaign users only). All 4 tools write to existing tables (dc_pensions,
     * users, tax_strategy_household_inputs).
     */
    private function campaignSaveTaxTools(): array
    {
        return [
            [
                'name' => 'capture_salary_sacrifice',
                'description' => 'Set salary_sacrifice flag on a specific DC pension owned by the user. Use during the SaveTax campaign occupational-scheme capture state.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'pension_id' => ['type' => 'integer', 'description' => 'ID of the dc_pension row to update.'],
                        'salary_sacrifice' => ['type' => 'boolean', 'description' => 'true if pension contributions are made via salary sacrifice.'],
                    ],
                    'required' => ['pension_id', 'salary_sacrifice'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'capture_spouse_work_status',
                'description' => 'Set whether the user\'s spouse currently works. Updates household_calculation_mode (dual_earner | single_earner_couple) and marriage_allowance_eligible accordingly. The state machine routes the next state based on the result.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'spouse_works' => ['type' => 'boolean', 'description' => 'true if spouse has earned income, false otherwise.'],
                    ],
                    'required' => ['spouse_works'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'capture_spouse_household_data',
                'description' => 'Capture working-spouse data for dual_earner households (spouse_works=yes path). Writes to tax_strategy_household_inputs.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'spouse_annual_income' => ['type' => 'number', 'description' => 'Spouse gross annual income in pounds.'],
                        'spouse_employment_status' => ['type' => 'string', 'enum' => ['full_time', 'part_time', 'self_employed', 'retired'], 'description' => 'Spouse employment status.'],
                        'spouse_isa_balance' => ['type' => 'number', 'description' => 'Spouse current ISA balance in pounds.'],
                        'spouse_psa_band' => ['type' => 'string', 'enum' => ['basic', 'higher', 'additional'], 'description' => 'Spouse Personal Savings Allowance band.'],
                        'spouse_unrealised_gains' => ['type' => 'number', 'description' => 'Spouse unrealised capital gains in pounds.'],
                        'spouse_annual_dividends' => ['type' => 'number', 'description' => 'Spouse annual dividend income in pounds.'],
                        'spouse_pension_input_annual' => ['type' => 'number', 'description' => 'Spouse gross annual pension contribution in pounds.'],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'capture_spouse_non_working_assets',
                'description' => 'Capture standalone assets owned by a non-working spouse (single_earner_couple path). Used to compute available capacity for asset-shifting strategies (Personal Allowance, Starting Rate for Savings, Personal Savings Allowance, ISA, CGT, Dividend allowance).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'spouse_existing_isa_balance' => ['type' => 'number', 'description' => 'Spouse\'s existing standalone ISA balance.'],
                        'spouse_existing_savings_balance' => ['type' => 'number', 'description' => 'Spouse\'s existing standalone bank/savings balance.'],
                        'spouse_existing_investment_balance' => ['type' => 'number', 'description' => 'Spouse\'s existing standalone investment account (GIA) balance.'],
                        'spouse_existing_dividend_holdings_value' => ['type' => 'number', 'description' => 'Value of spouse\'s dividend-paying holdings.'],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function expenditureTools(): array
    {
        return [
            [
                'name' => 'set_expenditure',
                'description' => 'Set the user\'s monthly expenditure by category. Call this IMMEDIATELY when the user mentions their spending, bills, or monthly outgoings. Fill in every category the user mentions and omit anything not mentioned. The form will be opened, filled, and saved. This tool captures all categories in a SINGLE call — do NOT call it multiple times per turn.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'rent' => ['type' => 'number', 'description' => 'Monthly rent in pounds.'],
                        'utilities' => ['type' => 'number', 'description' => 'Monthly utilities (gas, electricity, water) in pounds.'],
                        'food_groceries' => ['type' => 'number', 'description' => 'Monthly food and groceries in pounds.'],
                        'transport_fuel' => ['type' => 'number', 'description' => 'Monthly transport/fuel costs in pounds.'],
                        'healthcare_medical' => ['type' => 'number', 'description' => 'Monthly healthcare costs in pounds.'],
                        'insurance' => ['type' => 'number', 'description' => 'Monthly non-property insurance in pounds.'],
                        'mobile_phones' => ['type' => 'number', 'description' => 'Monthly mobile phone costs in pounds.'],
                        'internet_tv' => ['type' => 'number', 'description' => 'Monthly broadband/TV costs in pounds.'],
                        'subscriptions' => ['type' => 'number', 'description' => 'Monthly subscriptions in pounds.'],
                        'clothing_personal_care' => ['type' => 'number', 'description' => 'Monthly clothing and personal care in pounds.'],
                        'entertainment_dining' => ['type' => 'number', 'description' => 'Monthly entertainment and dining in pounds.'],
                        'holidays_travel' => ['type' => 'number', 'description' => 'Monthly holidays/travel in pounds.'],
                        'pets' => ['type' => 'number', 'description' => 'Monthly pet costs in pounds.'],
                        'childcare' => ['type' => 'number', 'description' => 'Monthly childcare costs in pounds.'],
                        'school_fees' => ['type' => 'number', 'description' => 'Monthly school fees in pounds.'],
                        'school_lunches' => ['type' => 'number', 'description' => 'Monthly school lunches in pounds.'],
                        'school_extras' => ['type' => 'number', 'description' => 'Monthly school extras in pounds.'],
                        'university_fees' => ['type' => 'number', 'description' => 'Monthly university costs in pounds.'],
                        'children_activities' => ['type' => 'number', 'description' => 'Monthly children activities in pounds.'],
                        'gifts_charity' => ['type' => 'number', 'description' => 'Monthly gifts in pounds.'],
                        'charitable_donations' => ['type' => 'number', 'description' => 'Monthly charitable donations in pounds.'],
                        'other_expenditure' => ['type' => 'number', 'description' => 'Other monthly expenses in pounds.'],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Billing / subscription tools. All read-only and zero-parameter, so they
     * are exposed in both preview and live mode (preview personas have no
     * subscription rows, so they get the canonical "none" shape back).
     */
    private function billingTools(): array
    {
        $emptyObject = (object) [];

        return [
            [
                'name' => 'get_subscription_status',
                'description' => 'Get the user\'s current subscription status — plan, billing cycle, current period end, trial end, next charge, and whether they have cancelled. Use when the user asks about their subscription, billing, when they will be charged next, whether their trial has ended, or whether their subscription is still active.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $emptyObject,
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'list_invoices',
                'description' => 'List the user\'s invoices in reverse chronological order (most recent first). Each row includes the invoice number, issued date, amount in pounds, currency, status, plan name, billing cycle, and a PDF download URL. Use when the user asks for their billing history, past invoices, or wants to download a receipt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $emptyObject,
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'get_current_plan',
                'description' => 'Get the details of the user\'s current subscription plan — name, tier slug, billing cycle, price in pounds, and the list of features included. Use when the user asks what plan they are on, what features they have, or what they are paying.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $emptyObject,
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function profileTools(): array
    {
        return [
            [
                'name' => 'update_profile',
                'description' => 'Update the user\'s profile information (personal details, income, expenditure, or domicile). Use when the user provides personal information like their age, income, spending, marital status, or address. Ask clarifying questions if needed to gather required fields.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'section' => [
                            'type' => 'string',
                            'enum' => ['personal', 'income_occupation', 'expenditure', 'domicile'],
                            'description' => 'Which profile section to update. personal: name, DOB, gender, marital status, address, phone. income_occupation: employment status, income, employer. expenditure: monthly spending. domicile: country of birth, UK arrival date.',
                        ],
                        'fields' => [
                            'type' => 'object',
                            'description' => 'Key-value pairs of fields to update. For personal: first_name, surname, date_of_birth (YYYY-MM-DD), gender (male/female/other), marital_status (single/married/divorced/widowed), phone, address_line_1, city, postcode. For income_occupation: employment_status (employed/full_time/part_time/self_employed/retired/unemployed/other), occupation, employer, annual_employment_income, annual_self_employment_income. For expenditure: monthly_expenditure, annual_expenditure. For domicile: country_of_birth, uk_arrival_date.',
                            'additionalProperties' => true,
                        ],
                    ],
                    'required' => ['section', 'fields'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }
}
