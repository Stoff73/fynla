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
            $tools = array_merge($tools, $this->dataCreationTools());
        }

        // Convert each tool to Anthropic format: parameters → input_schema, with strict mode
        return array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'input_schema' => $tool['parameters'],
            'strict' => true,
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
                            'description' => 'The application route path. Valid routes: /dashboard, /net-worth/wealth-summary, /net-worth/property, /net-worth/investments, /net-worth/retirement, /net-worth/cash, /net-worth/business, /net-worth/chattels, /net-worth/liabilities, /protection, /estate, /goals, /holistic-plan, /trusts, /risk-profile, /profile, /settings',
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
                'name' => 'run_what_if_scenario',
                'description' => 'Run a what-if scenario to show the user how changes would affect their financial plan. For example: "What if I increase pension contributions by £200/month?" or "What if I retire at 60 instead of 67?"',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'module' => [
                            'type' => 'string',
                            'enum' => ['protection', 'savings', 'investment', 'retirement'],
                            'description' => 'The module to run the scenario against',
                        ],
                        'parameters' => [
                            'type' => 'object',
                            'description' => 'Scenario parameters. For retirement: additional_contribution, later_retirement_age, lower_target_income. For savings: additional_savings. For investment: growth_rate_override.',
                            'properties' => [
                                'additional_contribution' => [
                                    'type' => 'number',
                                    'description' => 'Additional monthly contribution in pounds',
                                ],
                                'later_retirement_age' => [
                                    'type' => 'integer',
                                    'description' => 'Alternative retirement age to model',
                                ],
                                'lower_target_income' => [
                                    'type' => 'number',
                                    'description' => 'Alternative target retirement income in pounds per year',
                                ],
                                'additional_savings' => [
                                    'type' => 'number',
                                    'description' => 'Additional monthly savings amount in pounds',
                                ],
                                'growth_rate_override' => [
                                    'type' => 'number',
                                    'description' => 'Alternative annual growth rate as a percentage (e.g. 7 for 7%)',
                                ],
                            ],
                            'additionalProperties' => false,
                        ],
                    ],
                    'required' => ['module', 'parameters'],
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
                'description' => 'Get current UK tax year information for a specific topic. Use this when the user asks about tax thresholds, allowances, or rates.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => [
                            'type' => 'string',
                            'enum' => ['income_tax', 'capital_gains', 'inheritance_tax', 'isa_allowances', 'pension_allowances'],
                            'description' => 'The tax topic to retrieve information for',
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
                'description' => 'Create an investment account for the user. Use this when the user mentions a Stocks and Shares Individual Savings Account, general investment account, investment bond, or other investment.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'account_name' => [
                            'type' => 'string',
                            'description' => 'Name of the account (e.g., "Vanguard Stocks & Shares ISA", "Hargreaves Lansdown GIA")',
                        ],
                        'account_type' => [
                            'type' => 'string',
                            'enum' => ['stocks_shares_isa', 'lifetime_isa', 'personal_investment_account', 'onshore_bond', 'offshore_bond'],
                            'description' => 'Type of investment account. Default to "personal_investment_account" if not specified.',
                        ],
                        'provider' => [
                            'type' => 'string',
                            'description' => 'Platform or provider name (e.g., "Vanguard", "Hargreaves Lansdown", "AJ Bell")',
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
                'name' => 'create_estate_asset',
                'description' => 'Create an estate planning asset. Use this for assets being tracked specifically for Inheritance Tax and estate planning purposes (e.g., collectibles, business interests, or other assets not captured elsewhere).',
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
                'name' => 'create_estate_liability',
                'description' => 'Create an estate planning liability. Use this for debts and liabilities being tracked for estate planning purposes.',
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
}
