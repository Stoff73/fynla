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
        ];

        if (! $isPreviewMode) {
            $tools = array_merge(
                $tools,
                $this->whatIfTools(),
                $this->dataCreationTools(),
                $this->additionalCreationTools(),
                $this->dataModificationTools(),
                $this->profileTools(),
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
                'description' => 'Create a new financial goal for the user. Use this when the user says they want to save for something specific.',
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
                'description' => 'Create a future life event that may impact the user\'s financial plan.',
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
                'description' => 'Create a savings account for the user. Use this when the user mentions a savings account, Cash Individual Savings Account, or cash deposit.',
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
                'description' => 'Create an investment account for the user. Use this when the user mentions any investment: ISA, GIA, bond, VCT, EIS, private company shares, crowdfunding, employee share schemes (SAYE, CSOP, EMI, share options, RSUs), or other investments.',
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
                'name' => 'create_pension',
                'description' => 'Create a pension for the user. Handles both Defined Contribution (workplace, Self-Invested Personal Pension, personal) and Defined Benefit (final salary, career average) pensions.',
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
                'description' => 'Create a property for the user. If they also mention a mortgage, include the outstanding mortgage amount and it will be created automatically.',
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
                'description' => 'Create a standalone mortgage linked to an existing property. Use this when the user mentions a mortgage separately from a property, or wants to add a mortgage to an existing property.',
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
                'description' => 'Create a protection insurance policy for the user. Handles life insurance, critical illness cover, and income protection policies.',
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
                'description' => 'Create an asset. Use this for assets not covered by other tools — such as collectibles, artwork, or other valuable items the user wants to track.',
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
                'description' => 'Create a liability. Use this when the user mentions any debt: credit cards, personal loans, student loans, car finance, or any other outstanding balance owed.',
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
                'description' => 'Record a gift for Inheritance Tax planning. Use this when the user mentions gifts they have made or plan to make, as these affect their Inheritance Tax position under the 7-year rule.',
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
        ];
    }

    private function additionalCreationTools(): array
    {
        return [
            [
                'name' => 'create_family_member',
                'description' => 'Add a family member (spouse, child, dependent). Use when the user mentions family members who affect their financial planning.',
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
                'description' => 'Record a trust for estate planning. Use when the user mentions trusts they have set up or want to document.',
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
                'description' => 'Record a business interest or ownership. Use when the user mentions business ownership, partnerships, or self-employment assets.',
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
                'description' => 'Record a personal valuable item (jewellery, art, collectibles, vehicles). Use when the user mentions valuable personal possessions.',
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
                'description' => 'Update an existing record. Use when the user wants to change details of an existing goal, account, property, pension, policy, or other financial record. Ask the user to confirm the changes before calling this tool.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'entity_type' => [
                            'type' => 'string',
                            'enum' => ['goal', 'life_event', 'savings_account', 'investment_account', 'dc_pension', 'db_pension', 'property', 'mortgage', 'life_insurance', 'critical_illness', 'income_protection', 'estate_asset', 'estate_liability', 'estate_gift', 'family_member', 'trust', 'business_interest', 'chattel'],
                            'description' => 'The type of record to update',
                        ],
                        'entity_id' => ['type' => 'integer', 'description' => 'The ID of the record to update'],
                        'fields' => [
                            'type' => 'object',
                            'description' => 'Key-value pairs of fields to update. Only include fields that are changing.',
                            'additionalProperties' => true,
                        ],
                    ],
                    'required' => ['entity_type', 'entity_id', 'fields'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'delete_record',
                'description' => 'Delete an existing record. ALWAYS confirm with the user before deleting. Use when the user explicitly asks to remove a goal, account, property, pension, policy, or other financial record.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'entity_type' => [
                            'type' => 'string',
                            'enum' => ['goal', 'life_event', 'savings_account', 'investment_account', 'dc_pension', 'db_pension', 'property', 'mortgage', 'life_insurance', 'critical_illness', 'income_protection', 'estate_asset', 'estate_liability', 'estate_gift', 'family_member', 'trust', 'business_interest', 'chattel'],
                            'description' => 'The type of record to delete',
                        ],
                        'entity_id' => ['type' => 'integer', 'description' => 'The ID of the record to delete'],
                    ],
                    'required' => ['entity_type', 'entity_id'],
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
