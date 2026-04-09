<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * xAI-optimised tool definitions with strict function calling.
 *
 * Returns tools pre-wrapped in OpenAI function-calling format with strict: true.
 * No further wrapping is needed in HasAiChat — pass directly to $params['tools'].
 *
 * Key differences from AiToolDefinitions:
 * - All tools use strict mode (guaranteed schema compliance)
 * - All fields in required array; optional fields use nullable types
 * - Property tools have enriched schemas covering all form fields
 * - Contextual gathering instructions in tool descriptions
 * - Nullable enums use anyOf pattern (strict mode requirement)
 */
class XaiToolDefinitions
{
    /**
     * Get all tool definitions in OpenAI function-calling format with strict mode.
     * Tools are pre-wrapped — no further wrapping needed in HasAiChat.
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

        return $tools;
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Wrap a tool definition in OpenAI function-calling format with strict mode.
     * Set $strict = false for tools with dynamic key-value objects (additionalProperties: true).
     */
    private function wrapTool(string $name, string $description, array $properties, array $required, bool $strict = true): array
    {
        $function = [
            'name' => $name,
            'description' => $description,
            'parameters' => [
                'type' => 'object',
                // Empty properties must be JSON object {}, not array [].
                'properties' => empty($properties) ? (object) [] : $properties,
                'required' => $required,
                'additionalProperties' => false,
            ],
        ];

        if ($strict) {
            $function['strict'] = true;
        }

        return ['type' => 'function', 'function' => $function];
    }

    /**
     * Nullable enum for strict mode.
     * OpenAI strict mode does NOT allow 'type' => ['string', 'null'] with 'enum'.
     * Must use 'anyOf' pattern instead.
     */
    private function nullableEnum(array $values, string $description): array
    {
        return [
            'anyOf' => [
                ['type' => 'string', 'enum' => $values],
                ['type' => 'null'],
            ],
            'description' => $description,
        ];
    }

    // ─── Navigation ──────────────────────────────────────────────────

    private function navigationTools(): array
    {
        return [
            $this->wrapTool(
                'navigate_to_page',
                'Navigate the user to a specific page in the application. Use this when the user asks to go somewhere or when showing them relevant information would be helpful.',
                [
                    'route_path' => [
                        'type' => 'string',
                        'description' => 'The application route path. Valid routes: '
                            .'MAIN: /dashboard, /profile, /settings, /settings/security, /settings/assumptions, /help. '
                            .'INCOME & EXPENDITURE: /valuable-info?section=income (Income tab — view and edit income sources), /valuable-info?section=expenditure (Expenditure tab — view and edit monthly spending), /valuable-info?section=letter (Expression of Wishes / Letter to Spouse), /valuable-info?section=risk (Risk Profile summary). '
                            .'NET WORTH: /net-worth/wealth-summary (overall wealth), /net-worth/property (properties and mortgages), /net-worth/investments (investment accounts list), /net-worth/retirement (pensions), /net-worth/cash (Bank Accounts & Savings), /net-worth/business (business interests), /net-worth/chattels (personal valuables), /net-worth/liabilities (debts). '
                            .'INVESTMENT DETAILS: To show a specific account\'s details (Monte Carlo, tax treatment, rebalancing, diversification, fees, holdings), navigate to /net-worth/investments — the user clicks into any account card to see its full detail view with per-account projections. Do NOT use /net-worth/investment-detail (that is a legacy portfolio-wide view). Other detail pages: /net-worth/fees-detail (fees breakdown), /net-worth/holdings-detail (portfolio holdings), /net-worth/tax-efficiency (tax efficiency analysis), /net-worth/strategy-detail (investment strategy). '
                            .'SAVINGS DETAIL: /savings (savings dashboard with analysis), /savings/account/{id} (individual savings account detail). '
                            .'PROTECTION: /protection (protection dashboard with coverage analysis and policies). '
                            .'ESTATE: /estate (Estate Planning dashboard — Inheritance Tax, gifting, will status), /estate/will-builder (Will Builder), /estate/power-of-attorney (Power of Attorney). '
                            .'TRUSTS: /trusts (trust list and management). '
                            .'GOALS & EVENTS: /goals (Goals dashboard — all goals with progress tracking), /goals?tab=events (Life Events tab — upcoming life events). '
                            .'RISK: /risk-profile (risk questionnaire and profile). '
                            .'PLANS: /plans (all plans overview), /plans/investment (investment plan), /plans/retirement (retirement plan), /plans/protection (protection plan), /plans/estate (estate plan), /holistic-plan (Holistic Financial Plan combining all modules). '
                            .'ACTIONS: /actions (recommended actions across all modules). '
                            .'PLANNING: /planning/journeys (guided planning journeys), /planning/what-if (What-If Scenarios). '
                            .'NEVER use /savings or /investment as standalone paths for net worth — use /net-worth/cash and /net-worth/investments instead. /savings is valid for the savings dashboard.',
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Brief explanation of why navigating here is helpful',
                    ],
                ],
                ['route_path', 'description']
            ),
        ];
    }

    // ─── Analysis ────────────────────────────────────────────────────

    private function analysisTools(): array
    {
        return [
            $this->wrapTool(
                'list_records',
                'List existing records of a given type with IDs and key details. Use this BEFORE calling update_record to find the correct entity_id. '
                .'Also use when the user asks "what accounts do I have?" or "show me my pensions". '
                .'The <existing_records> section in the system prompt already has a snapshot — use this tool for a fresh, detailed lookup.',
                [
                    'entity_type' => [
                        'type' => 'string',
                        'enum' => ['savings_account', 'investment_account', 'dc_pension', 'db_pension', 'property', 'mortgage', 'life_insurance', 'critical_illness', 'income_protection', 'trust', 'business_interest', 'chattel', 'estate_liability', 'estate_gift', 'family_member'],
                        'description' => 'The type of record to list.',
                    ],
                ],
                ['entity_type']
            ),
            $this->wrapTool(
                'list_goals',
                'List all of the user\'s financial goals with their current progress, status, and IDs. Use this when the user asks about their goals, wants to see progress, or before updating/deleting a specific goal.',
                [],
                []
            ),
            $this->wrapTool(
                'list_life_events',
                'List all of the user\'s life events with dates, amounts, and IDs. Use this when the user asks about their life events, upcoming events, or before updating/deleting a specific event.',
                [],
                []
            ),
            $this->wrapTool(
                'get_module_analysis',
                'Get detailed financial analysis for a specific module. Returns personalised analysis based on the user\'s actual financial data.',
                [
                    'module' => [
                        'type' => 'string',
                        'enum' => ['protection', 'savings', 'investment', 'retirement', 'estate', 'goals', 'holistic'],
                        'description' => 'The financial planning module to analyse',
                    ],
                ],
                ['module']
            ),
            $this->wrapTool(
                'get_recommendations',
                'Get the user\'s personalised financial recommendations ranked by priority across all modules.',
                [],
                []
            ),
        ];
    }

    // ─── Tax ─────────────────────────────────────────────────────────

    private function taxTools(): array
    {
        return [
            $this->wrapTool(
                'get_tax_information',
                'Get current UK tax year information for a specific topic. ALWAYS use this tool when the user asks about tax thresholds, allowances, rates, or any financial product tax treatment. Never state tax values from memory — always retrieve them. Use income_definitions to get the user\'s detailed income breakdown including adjusted net income, threshold income, and tapered pension allowances.',
                [
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
                ['topic']
            ),
        ];
    }

    // ─── Plan Generation ─────────────────────────────────────────────

    private function planGenerationTools(): array
    {
        return [
            $this->wrapTool(
                'generate_financial_plan',
                'Generate a comprehensive holistic financial plan for the user. Analyses all modules and returns an executive summary, top recommendations, and action plan.',
                [],
                []
            ),
        ];
    }

    // ─── What-If ─────────────────────────────────────────────────────

    private function whatIfTools(): array
    {
        return [
            $this->wrapTool(
                'create_what_if_scenario',
                'Create a persistent what-if scenario showing how changes would affect the user\'s financial plan. The scenario is saved and the user is navigated to the What If dashboard to see the comparison.',
                [
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
                        'additionalProperties' => true,
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Your explanation of what this scenario models and the key assumptions',
                    ],
                ],
                ['name', 'scenario_type', 'parameters', 'description'],
                false // Cannot use strict mode — parameters object has dynamic keys
            ),
        ];
    }

    // ─── Data Creation ───────────────────────────────────────────────

    private function dataCreationTools(): array
    {
        return [
            ...$this->goalAndEventTools(),
            ...$this->accountCreationTools(),
            ...$this->propertyCreationTools(),
            ...$this->protectionCreationTools(),
            ...$this->estateCreationTools(),
            ...$this->expenditureTools(),
        ];
    }

    private function goalAndEventTools(): array
    {
        return [
            $this->wrapTool(
                'create_goal',
                'Create a new financial goal. Use when the user wants to save for something specific. '
                .'Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn.',
                [
                    'name' => ['type' => 'string', 'description' => 'Name of the goal (e.g. "Holiday Fund", "House Deposit", "Emergency Fund")'],
                    'target_amount' => ['type' => 'number', 'description' => 'Target amount in pounds (£)'],
                    'target_date' => ['type' => 'string', 'description' => 'Target date in YYYY-MM-DD format. Must be in the future.'],
                    'priority' => ['type' => 'string', 'enum' => ['critical', 'high', 'medium', 'low'], 'description' => '"critical" for must-have goals. "high" for important. "medium" for nice-to-have. "low" for aspirational.'],
                    'goal_type' => ['type' => 'string', 'enum' => ['emergency_fund', 'home_deposit', 'property_purchase', 'holiday', 'education', 'wedding', 'car_purchase', 'retirement', 'wealth_accumulation', 'debt_repayment', 'custom'], 'description' => '"emergency_fund" for emergency savings. "home_deposit" for house deposit saving. "property_purchase" for buying property. "holiday" for holidays. "education" for education costs. "wedding" for wedding. "car_purchase" for buying a car. "retirement" for retirement. "wealth_accumulation" for general wealth building. "debt_repayment" for paying off debt. "custom" for anything else.'],
                    'monthly_contribution' => ['type' => ['number', 'null'], 'description' => 'Monthly contribution amount (£). How much the user plans to save each month towards this goal.'],
                ],
                ['name', 'target_amount', 'target_date', 'priority', 'goal_type', 'monthly_contribution']
            ),
            $this->wrapTool(
                'create_life_event',
                'Create a future life event that impacts the user\'s financial plan. Use for expected income (inheritance, bonus, property sale) or expenses (large purchase, wedding, home improvement). '
                .'Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn.',
                [
                    'event_name' => ['type' => 'string', 'description' => 'Short name for the event (e.g. "Parents\' Estate", "Kitchen Renovation", "Work Bonus")'],
                    'event_type' => [
                        'type' => 'string',
                        'enum' => ['inheritance', 'gift_received', 'bonus', 'redundancy_payment', 'property_sale', 'business_sale', 'pension_lump_sum', 'lottery_windfall', 'custom_income', 'large_purchase', 'home_improvement', 'wedding', 'education_fees', 'gift_given', 'medical_expense', 'custom_expense'],
                        'description' => 'Income events: "inheritance", "gift_received", "bonus", "redundancy_payment", "property_sale", "business_sale", "pension_lump_sum", "lottery_windfall", "custom_income". Expense events: "large_purchase" for car/boat/etc, "home_improvement" for renovation/extension, "wedding", "education_fees" for school/uni, "gift_given", "medical_expense", "custom_expense".',
                    ],
                    'event_date' => ['type' => 'string', 'description' => 'Expected date in YYYY-MM-DD format. Must be in the future.'],
                    'estimated_amount' => ['type' => 'number', 'description' => 'Estimated amount (£). How much money is expected to come in or go out.'],
                    'certainty' => $this->nullableEnum(['confirmed', 'likely', 'possible', 'speculative'], '"confirmed" if definitely happening. "likely" if probably. "possible" if might. "speculative" if uncertain. Default "likely".'),
                    'description' => ['type' => ['string', 'null'], 'description' => 'Optional description with more details.'],
                ],
                ['event_name', 'event_type', 'event_date', 'estimated_amount', 'certainty', 'description']
            ),
        ];
    }

    private function accountCreationTools(): array
    {
        return [
            $this->wrapTool(
                'create_savings_account',
                'Create a bank account or savings product. Use for current accounts, savings accounts, Cash ISAs, premium bonds, or NS&I products. '
                .'Call this tool IMMEDIATELY when the user mentions any bank account or cash savings. '
                .'IMPORTANT: Do NOT call any other creation tools in the same turn.',
                [
                    'account_name' => ['type' => 'string', 'description' => 'Name of the account (e.g. "Nationwide Cash ISA", "HSBC Current Account", "Marcus Savings")'],
                    'account_type' => $this->nullableEnum(
                        ['savings_account', 'current_account', 'easy_access', 'instant_access', 'notice', 'fixed', 'cash_isa', 'junior_isa', 'premium_bonds', 'nsi'],
                        'Product type. "current_account" for current/checking accounts. "savings_account" or "easy_access" for savings. "notice" for notice accounts. "fixed" for fixed term. "cash_isa" for Cash ISA. "premium_bonds" for NS&I Premium Bonds. "nsi" for other NS&I products.'
                    ),
                    'institution' => ['type' => ['string', 'null'], 'description' => 'Bank or building society name (e.g. "HSBC", "Nationwide", "Marcus")'],
                    'current_balance' => ['type' => 'number', 'description' => 'Current balance in pounds'],
                    'interest_rate' => ['type' => ['number', 'null'], 'description' => 'Annual interest rate as a percentage (e.g. 4.5). Use 0 for premium bonds.'],
                    'is_isa' => ['type' => ['boolean', 'null'], 'description' => 'Whether this is a Cash ISA. Set true if user says "ISA" or "tax-free". Default false.'],
                    'is_emergency_fund' => ['type' => ['boolean', 'null'], 'description' => 'Whether this forms part of the emergency fund. Set true if user says "emergency fund" or "rainy day". Default false.'],
                    'regular_contribution_amount' => ['type' => ['number', 'null'], 'description' => 'Monthly contribution amount in pounds, if any'],
                ],
                ['account_name', 'account_type', 'institution', 'current_balance', 'interest_rate', 'is_isa', 'is_emergency_fund', 'regular_contribution_amount']
            ),
            $this->wrapTool(
                'create_investment_account',
                'Create an investment account for the user. Use this when the user mentions any investment: ISA, GIA, bond, VCT, EIS, private company shares, crowdfunding, employee share schemes (SAYE, CSOP, EMI, share options, RSUs), or other financial investments. '
                .'Use account_type "other" for gold, silver, cryptocurrency, bitcoin, or other alternative financial assets. '
                .'Do NOT use this tool for wine, art, jewellery, antiques, collectibles, or vehicles — use create_chattel instead.',
                [
                    'account_name' => ['type' => 'string', 'description' => 'Name of the account (e.g. "Vanguard Stocks & Shares ISA")'],
                    'account_type' => [
                        'type' => 'string',
                        'enum' => [
                            'stocks_shares_isa', 'lifetime_isa', 'personal_investment_account',
                            'onshore_bond', 'offshore_bond', 'vct', 'eis',
                            'private_company', 'crowdfunding', 'saye', 'csop',
                            'emi', 'unapproved_options', 'rsu', 'other',
                        ],
                        'description' => 'Type of investment account.',
                    ],
                    'provider' => ['type' => ['string', 'null'], 'description' => 'Platform, provider, or company name'],
                    'current_value' => ['type' => 'number', 'description' => 'Current value in pounds'],
                    'monthly_contribution_amount' => ['type' => ['number', 'null'], 'description' => 'Monthly contribution amount in pounds'],
                    'platform_fee_percent' => ['type' => ['number', 'null'], 'description' => 'Annual platform fee as a percentage (e.g. 0.15)'],
                    'bond_purchase_date' => ['type' => ['string', 'null'], 'description' => 'Bond purchase date (YYYY-MM-DD). Only for onshore_bond or offshore_bond.'],
                    'bond_withdrawal_taken' => ['type' => ['number', 'null'], 'description' => 'Total 5% tax-deferred withdrawals taken (£). Only for bonds.'],
                    'company_legal_name' => ['type' => ['string', 'null'], 'description' => 'Legal name of the company. For private_company or crowdfunding.'],
                    'company_registration_number' => ['type' => ['string', 'null'], 'description' => 'Companies House registration number.'],
                    'crowdfunding_platform' => $this->nullableEnum(['Seedrs', 'Crowdcube', 'Republic', 'Wefunder', 'other'], 'Crowdfunding platform. Only for crowdfunding type.'),
                    'investment_date' => ['type' => ['string', 'null'], 'description' => 'Date of investment (YYYY-MM-DD).'],
                    'investment_amount' => ['type' => ['number', 'null'], 'description' => 'Original investment amount (£).'],
                    'number_of_shares' => ['type' => ['number', 'null'], 'description' => 'Number of shares held.'],
                    'price_per_share' => ['type' => ['number', 'null'], 'description' => 'Price per share (£).'],
                    'instrument_type' => $this->nullableEnum(['ordinary_shares', 'preference_shares', 'convertible_loan_note', 'safe', 'revenue_share', 'fund_nominee_interest'], 'Type of instrument held.'),
                    'funding_round' => $this->nullableEnum(['pre_seed', 'seed', 'series_a', 'series_b', 'series_c', 'bridge', 'safe', 'other'], 'Funding round.'),
                    'share_class' => ['type' => ['string', 'null'], 'description' => 'Share class (e.g. "A Ordinary").'],
                    'tax_relief_type' => $this->nullableEnum(['eis', 'seis', 'sitr', 'vct', ''], 'Tax relief scheme applied.'),
                    'employer_name' => ['type' => ['string', 'null'], 'description' => 'Employer company name. For employee share schemes.'],
                    'employer_is_listed' => ['type' => ['boolean', 'null'], 'description' => 'Whether shares are publicly listed. For employee share schemes.'],
                    'grant_date' => ['type' => ['string', 'null'], 'description' => 'Date options/shares were granted (YYYY-MM-DD).'],
                    'units_granted' => ['type' => ['number', 'null'], 'description' => 'Number of units/options granted.'],
                    'exercise_price' => ['type' => ['number', 'null'], 'description' => 'Exercise/strike price per share (£).'],
                    'market_value_at_grant' => ['type' => ['number', 'null'], 'description' => 'Market value per share at grant date (£).'],
                    'current_share_price' => ['type' => ['number', 'null'], 'description' => 'Current share price (£).'],
                    'units_vested' => ['type' => ['number', 'null'], 'description' => 'Number of units currently vested.'],
                    'units_unvested' => ['type' => ['number', 'null'], 'description' => 'Number of units not yet vested.'],
                    'vesting_type' => $this->nullableEnum(['cliff', 'monthly', 'quarterly', 'annual', 'performance', 'immediate'], 'Vesting schedule type.'),
                    'full_vest_date' => ['type' => ['string', 'null'], 'description' => 'Date all units fully vest (YYYY-MM-DD).'],
                    'cliff_date' => ['type' => ['string', 'null'], 'description' => 'Cliff vesting date (YYYY-MM-DD).'],
                    'cliff_percentage' => ['type' => ['number', 'null'], 'description' => 'Percentage that vests at cliff (e.g. 25).'],
                    'saye_monthly_savings' => ['type' => ['number', 'null'], 'description' => 'Monthly savings amount (max £500). SAYE only.'],
                    'saye_current_savings_balance' => ['type' => ['number', 'null'], 'description' => 'Current savings balance (£). SAYE only.'],
                    'scheme_start_date' => ['type' => ['string', 'null'], 'description' => 'SAYE contract start date (YYYY-MM-DD).'],
                    'scheme_duration_months' => [
                        'anyOf' => [
                            ['type' => 'integer', 'enum' => [36, 60]],
                            ['type' => 'null'],
                        ],
                        'description' => 'SAYE contract duration: 36 (3 years) or 60 (5 years).',
                    ],
                    'holdings' => [
                        'anyOf' => [
                            [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'security_name' => ['type' => 'string', 'description' => 'Name of the fund, ETF, or share (e.g. "Vanguard FTSE All-World", "iShares Core UK Gilts")'],
                                        'asset_type' => ['type' => 'string', 'enum' => ['equity', 'uk_equity', 'us_equity', 'international_equity', 'fund', 'etf', 'bond', 'cash', 'alternative', 'property'], 'description' => 'Type of holding: "fund" for OEICs/unit trusts, "etf" for ETFs, "uk_equity"/"us_equity"/"international_equity" for shares, "bond" for fixed income, "cash" for cash.'],
                                        'allocation_percent' => ['type' => 'number', 'description' => 'Percentage of the account this holding represents (0-100). All holdings must total 100% or less.'],
                                        'cost_basis' => ['type' => ['number', 'null'], 'description' => 'Total amount originally invested in this holding (£). Optional.'],
                                    ],
                                    'required' => ['security_name', 'asset_type', 'allocation_percent', 'cost_basis'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            ['type' => 'null'],
                        ],
                        'description' => 'Array of holdings to add inline when creating the account. Only for ISA, GIA, onshore/offshore bonds, VCT, EIS. Each holding has security_name, asset_type, allocation_percent (% of account), and optional cost_basis. Any unallocated remainder auto-defaults to cash. If the user mentions specific funds/ETFs/shares they hold, include them here instead of using create_holding separately.',
                    ],
                ],
                [
                    'account_name', 'account_type', 'provider', 'current_value',
                    'monthly_contribution_amount', 'platform_fee_percent',
                    'bond_purchase_date', 'bond_withdrawal_taken',
                    'company_legal_name', 'company_registration_number', 'crowdfunding_platform',
                    'investment_date', 'investment_amount', 'number_of_shares', 'price_per_share',
                    'instrument_type', 'funding_round', 'share_class', 'tax_relief_type',
                    'employer_name', 'employer_is_listed', 'grant_date', 'units_granted',
                    'exercise_price', 'market_value_at_grant', 'current_share_price',
                    'units_vested', 'units_unvested', 'vesting_type', 'full_vest_date',
                    'cliff_date', 'cliff_percentage',
                    'saye_monthly_savings', 'saye_current_savings_balance',
                    'scheme_start_date', 'scheme_duration_months',
                    'holdings',
                ]
            ),
            $this->wrapTool(
                'create_holding',
                'Add a holding to an EXISTING investment account that was already created WITHOUT holdings. Use this ONLY when the user wants to add holdings to an account that already exists and has no holdings. '
                .'If the user is creating a NEW account AND mentions holdings at the same time, use create_investment_account with the holdings parameter instead. '
                .'Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn.',
                [
                    'account_name' => ['type' => 'string', 'description' => 'Name or provider of the investment account to add the holding to (e.g. "Vanguard ISA", "Hargreaves Lansdown"). Must match an existing account.'],
                    'security_name' => ['type' => 'string', 'description' => 'Name of the fund, ETF, or share (e.g. "Vanguard FTSE All-World", "iShares Core MSCI World")'],
                    'ticker' => ['type' => ['string', 'null'], 'description' => 'Ticker symbol (e.g. "VWRL", "SWDA", "VUSA")'],
                    'asset_type' => [
                        'type' => 'string',
                        'enum' => ['uk_equity', 'us_equity', 'international_equity', 'fund', 'etf', 'bond', 'cash', 'alternative', 'property'],
                        'description' => '"fund" for OEICs/unit trusts. "etf" for ETFs. "uk_equity" for UK shares. "us_equity" for US shares. "international_equity" for other shares. "bond" for fixed income. "cash" for cash holdings. "alternative" for commodities/crypto/etc. "property" for property funds.',
                    ],
                    'allocation_percent' => ['type' => ['number', 'null'], 'description' => 'Percentage of the account this holding represents (0-100).'],
                    'purchase_price' => ['type' => ['number', 'null'], 'description' => 'Purchase price per unit (£).'],
                    'current_price' => ['type' => ['number', 'null'], 'description' => 'Current price per unit (£).'],
                    'ocf_percent' => ['type' => ['number', 'null'], 'description' => 'Ongoing Charge Figure as percentage (e.g. 0.22 for 0.22%).'],
                ],
                ['account_name', 'security_name', 'ticker', 'asset_type', 'allocation_percent', 'purchase_price', 'current_price', 'ocf_percent']
            ),
            $this->wrapTool(
                'create_pension',
                'Create a pension for the user. Handles both Defined Contribution (DC: workplace, SIPP, personal) and Defined Benefit (DB: final salary, career average). '
                .'Call this tool IMMEDIATELY when the user mentions a pension. Fill in every field you can. '
                .'IMPORTANT: Do NOT call any other creation tools in the same turn as create_pension. '
                .'If the user mentions a pension without specifying DC or DB, ask: "Is this a workplace pension where your employer contributes, or a final salary/career average scheme?"',
                [
                    'pension_category' => ['type' => 'string', 'enum' => ['dc', 'db'], 'description' => '"dc" for Defined Contribution (workplace, SIPP, personal). "db" for Defined Benefit (final salary, career average).'],
                    'scheme_name' => ['type' => 'string', 'description' => 'Name of the pension scheme (e.g. "Aviva Workplace Pension", "NHS Pension Scheme").'],
                    'scheme_type' => ['type' => ['string', 'null'], 'description' => 'DC: "workplace" (employer pension), "sipp" (Self-Invested Personal Pension), "personal_pension", "stakeholder". DB: "final_salary", "career_average".'],
                    'provider' => ['type' => ['string', 'null'], 'description' => 'Pension provider (e.g. "Aviva", "Scottish Widows"). DC only.'],
                    // DC fields
                    'current_fund_value' => ['type' => ['number', 'null'], 'description' => 'Current fund value in pounds. DC only.'],
                    'employee_contribution_percent' => ['type' => ['number', 'null'], 'description' => 'Employee contribution as % of salary (e.g. 5 for 5%). DC workplace only.'],
                    'employer_contribution_percent' => ['type' => ['number', 'null'], 'description' => 'Employer contribution as % of salary (e.g. 3 for 3%). DC workplace only.'],
                    'annual_salary' => ['type' => ['number', 'null'], 'description' => 'Annual salary in pounds. DC workplace only — needed to calculate contribution amounts.'],
                    'monthly_contribution_amount' => ['type' => ['number', 'null'], 'description' => 'Fixed monthly contribution in pounds. DC personal/SIPP only.'],
                    'retirement_age' => ['type' => ['integer', 'null'], 'description' => 'Planned access age (min 55). DC personal/SIPP only.'],
                    // DB fields
                    'accrued_annual_pension' => ['type' => ['number', 'null'], 'description' => 'Projected annual pension at retirement in pounds. DB only.'],
                    'pensionable_service_years' => ['type' => ['number', 'null'], 'description' => 'Years of pensionable service. DB only.'],
                    'normal_retirement_age' => ['type' => ['integer', 'null'], 'description' => 'Normal retirement age for the scheme. DB only.'],
                    'scheme_status' => $this->nullableEnum(['Active', 'Deferred', 'In Payment'], 'DB pension status. "Active" if still contributing, "Deferred" if left employer, "In Payment" if retired. Default "Active".'),
                    'final_salary' => ['type' => ['number', 'null'], 'description' => 'Pensionable salary in pounds. DB only.'],
                    'accrual_rate' => ['type' => ['integer', 'null'], 'description' => 'Accrual rate denominator (e.g. 60 for 1/60th). DB only. Common: 60 (public sector), 80 (older schemes).'],
                ],
                [
                    'pension_category', 'scheme_name', 'scheme_type', 'provider',
                    'current_fund_value', 'employee_contribution_percent', 'employer_contribution_percent',
                    'annual_salary', 'monthly_contribution_amount', 'retirement_age',
                    'accrued_annual_pension', 'pensionable_service_years', 'normal_retirement_age',
                    'scheme_status', 'final_salary', 'accrual_rate',
                ]
            ),
        ];
    }

    // ─── Property (enriched for xAI) ─────────────────────────────────

    private function propertyCreationTools(): array
    {
        return [
            $this->wrapTool(
                'create_property',
                'Create a property record and optionally a linked mortgage. '
                .'Call this tool IMMEDIATELY when the user mentions a property — do not ask questions first. '
                .'Fill in every field you can from what the user said and set null for anything not mentioned. '
                .'The form will be opened, filled, and saved automatically. After saving, confirm what was added '
                .'and ask if they want to update any details (postcode, monthly costs, etc.) or add another property. '
                .'Infer sensible values: if they say "my house" assume main_residence, if they say "our house" assume joint ownership. '
                .'IMPORTANT: Do NOT call any other creation tools (create_family_member, navigate_to_page, etc.) in the same turn as create_property. '
                .'The property form fill needs the page to stay on /net-worth/property until saved. Add family members in a follow-up message.',
                [
                    // ── Basic (truly required) ──
                    'property_type' => [
                        'type' => 'string',
                        'enum' => ['main_residence', 'secondary_residence', 'buy_to_let'],
                        'description' => 'Type of property. "main_residence" for their primary home, "secondary_residence" for holiday homes, "buy_to_let" for rental properties.',
                    ],
                    'current_value' => [
                        'type' => 'number',
                        'description' => 'Current estimated market value of the full property in pounds (e.g. 450000). Always the FULL value, not the user\'s share.',
                    ],
                    // ── Address ──
                    'address_line_1' => ['type' => ['string', 'null'], 'description' => 'Street address (e.g. "42 Oak Lane"). Try to extract from what user said.'],
                    'address_line_2' => ['type' => ['string', 'null'], 'description' => 'Second address line if needed.'],
                    'city' => ['type' => ['string', 'null'], 'description' => 'City or town. Infer from address if user mentions a place name (e.g. "house in Guildford" → city is "Guildford").'],
                    'county' => ['type' => ['string', 'null'], 'description' => 'County.'],
                    'postcode' => ['type' => ['string', 'null'], 'description' => 'UK postcode (e.g. "SW1A 1AA"). Include if the user mentions it.'],
                    // ── Purchase ──
                    'purchase_price' => ['type' => ['number', 'null'], 'description' => 'Original purchase price in pounds.'],
                    'purchase_date' => ['type' => ['string', 'null'], 'description' => 'Purchase date (YYYY-MM-DD). If only year known, use Jan 1st (e.g. "2015-01-01").'],
                    'valuation_date' => ['type' => ['string', 'null'], 'description' => 'Date of most recent valuation (YYYY-MM-DD). Null if current_value is an estimate.'],
                    // ── Ownership ──
                    'ownership_type' => $this->nullableEnum(
                        ['individual', 'joint', 'tenants_in_common', 'trust'],
                        'How the property is owned. "individual" = sole owner. "joint" = joint tenancy (equal shares, passes to survivor). "tenants_in_common" = can have unequal shares, passes via will. "trust" = held in a trust. Default to "individual" if not specified.'
                    ),
                    'ownership_percentage' => ['type' => ['number', 'null'], 'description' => 'Primary owner\'s percentage share (0-100). Individual=100, joint=50 typically, tenants_in_common=whatever they specify.'],
                    'joint_owner_name' => ['type' => ['string', 'null'], 'description' => 'Name of joint owner. Only if joint or tenants_in_common. Use spouse name if mentioned.'],
                    // ── Tenure ──
                    'tenure_type' => $this->nullableEnum(
                        ['freehold', 'leasehold'],
                        'Freehold (owns the land) or leasehold (owns for a fixed term, common for flats). Null defaults to freehold.'
                    ),
                    'lease_remaining_years' => ['type' => ['integer', 'null'], 'description' => 'Years remaining on the lease. Only if leasehold.'],
                    'lease_expiry_date' => ['type' => ['string', 'null'], 'description' => 'Lease expiry date (YYYY-MM-DD). Only if leasehold.'],
                    // ── Mortgage ──
                    'has_mortgage' => ['type' => 'boolean', 'description' => 'Whether the property has a mortgage. True if the user mentions any mortgage, balance, or lender.'],
                    'mortgage_lender' => ['type' => ['string', 'null'], 'description' => 'Mortgage lender name (e.g. "Halifax", "Nationwide").'],
                    'mortgage_outstanding_balance' => ['type' => ['number', 'null'], 'description' => 'Outstanding mortgage balance in pounds. The full balance, not the user\'s share.'],
                    'mortgage_type' => $this->nullableEnum(
                        ['repayment', 'interest_only', 'mixed'],
                        '"repayment" = capital + interest (most common). "interest_only" = only pay interest. "mixed" = part repayment, part interest-only.'
                    ),
                    'mortgage_rate_type' => $this->nullableEnum(
                        ['fixed', 'variable', 'tracker', 'discount', 'mixed'],
                        'Interest rate type. "fixed" = locked rate. "variable" = lender SVR. "tracker" = follows base rate. "discount" = discount off SVR. "mixed" = split.'
                    ),
                    'mortgage_interest_rate' => ['type' => ['number', 'null'], 'description' => 'Current interest rate as percentage (e.g. 4.2).'],
                    'mortgage_monthly_payment' => ['type' => ['number', 'null'], 'description' => 'Monthly mortgage payment in pounds.'],
                    'mortgage_start_date' => ['type' => ['string', 'null'], 'description' => 'Mortgage start date (YYYY-MM-DD).'],
                    'mortgage_maturity_date' => ['type' => ['string', 'null'], 'description' => 'Mortgage end/maturity date (YYYY-MM-DD).'],
                    // ── Monthly costs ──
                    'monthly_council_tax' => ['type' => ['number', 'null'], 'description' => 'Monthly council tax (£).'],
                    'monthly_gas' => ['type' => ['number', 'null'], 'description' => 'Monthly gas bill (£).'],
                    'monthly_electricity' => ['type' => ['number', 'null'], 'description' => 'Monthly electricity bill (£).'],
                    'monthly_water' => ['type' => ['number', 'null'], 'description' => 'Monthly water bill (£).'],
                    'monthly_building_insurance' => ['type' => ['number', 'null'], 'description' => 'Monthly building insurance (£).'],
                    'monthly_contents_insurance' => ['type' => ['number', 'null'], 'description' => 'Monthly contents insurance (£).'],
                    'monthly_service_charge' => ['type' => ['number', 'null'], 'description' => 'Monthly service charge (£). Common for leasehold.'],
                    'monthly_maintenance_reserve' => ['type' => ['number', 'null'], 'description' => 'Monthly maintenance reserve (£).'],
                    'other_monthly_costs' => ['type' => ['number', 'null'], 'description' => 'Any other monthly property costs (£).'],
                    // ── Buy-to-let rental ──
                    'monthly_rental_income' => ['type' => ['number', 'null'], 'description' => 'Monthly rental income (£). Only for buy_to_let.'],
                    'tenant_name' => ['type' => ['string', 'null'], 'description' => 'Current tenant name. Only for buy_to_let.'],
                    'managing_agent_name' => ['type' => ['string', 'null'], 'description' => 'Letting/managing agent name. Only for buy_to_let.'],
                ],
                [
                    'property_type', 'current_value',
                    'address_line_1', 'address_line_2', 'city', 'county', 'postcode',
                    'purchase_price', 'purchase_date', 'valuation_date',
                    'ownership_type', 'ownership_percentage', 'joint_owner_name',
                    'tenure_type', 'lease_remaining_years', 'lease_expiry_date',
                    'has_mortgage', 'mortgage_lender', 'mortgage_outstanding_balance',
                    'mortgage_type', 'mortgage_rate_type', 'mortgage_interest_rate',
                    'mortgage_monthly_payment', 'mortgage_start_date', 'mortgage_maturity_date',
                    'monthly_council_tax', 'monthly_gas', 'monthly_electricity', 'monthly_water',
                    'monthly_building_insurance', 'monthly_contents_insurance',
                    'monthly_service_charge', 'monthly_maintenance_reserve', 'other_monthly_costs',
                    'monthly_rental_income', 'tenant_name', 'managing_agent_name',
                ]
            ),
            $this->wrapTool(
                'create_mortgage',
                'Add a mortgage to an existing property. Use when the user mentions a mortgage separately from a property. '
                .'Call this tool IMMEDIATELY with whatever details the user provided. Set null for anything not mentioned. '
                .'The form will be filled in front of the user. After filling, ask if they want to add more details before saving.',
                [
                    'property_address_hint' => ['type' => ['string', 'null'], 'description' => 'A hint to match the property — address, postcode, or "my main home". System fuzzy-matches.'],
                    'lender_name' => ['type' => ['string', 'null'], 'description' => 'Mortgage lender name (e.g. "Halifax").'],
                    'outstanding_balance' => ['type' => 'number', 'description' => 'Outstanding mortgage balance in pounds.'],
                    'interest_rate' => ['type' => ['number', 'null'], 'description' => 'Current interest rate as percentage (e.g. 4.2).'],
                    'mortgage_type' => $this->nullableEnum(
                        ['repayment', 'interest_only', 'mixed'],
                        'Repayment type. Default "repayment".'
                    ),
                    'rate_type' => $this->nullableEnum(
                        ['fixed', 'variable', 'tracker', 'discount', 'mixed'],
                        'Interest rate type. Default "fixed".'
                    ),
                    'monthly_payment' => ['type' => ['number', 'null'], 'description' => 'Monthly payment amount in pounds.'],
                    'remaining_term_months' => ['type' => ['integer', 'null'], 'description' => 'Remaining mortgage term in months.'],
                    'start_date' => ['type' => ['string', 'null'], 'description' => 'Mortgage start date (YYYY-MM-DD).'],
                    'maturity_date' => ['type' => ['string', 'null'], 'description' => 'Mortgage end/maturity date (YYYY-MM-DD).'],
                ],
                ['property_address_hint', 'lender_name', 'outstanding_balance', 'interest_rate', 'mortgage_type', 'rate_type', 'monthly_payment', 'remaining_term_months', 'start_date', 'maturity_date']
            ),
        ];
    }

    // ─── Protection ──────────────────────────────────────────────────

    private function protectionCreationTools(): array
    {
        return [
            $this->wrapTool(
                'create_protection_policy',
                'Create a protection insurance policy. Handles life insurance, critical illness, and income protection. '
                .'Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn.',
                [
                    'policy_type' => [
                        'type' => 'string',
                        'enum' => ['level_term', 'term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'standalone_ci', 'accelerated_ci', 'income_protection'],
                        'description' => 'Type of policy. "level_term" for level term life. "term" for generic term life. "whole_of_life" for whole of life. "decreasing_term" for decreasing/mortgage protection. "family_income_benefit" for family income benefit. "standalone_ci" for standalone critical illness. "accelerated_ci" for accelerated critical illness. "income_protection" for income protection.',
                    ],
                    'provider' => ['type' => ['string', 'null'], 'description' => 'Insurance provider (e.g. "Aviva", "Legal & General").'],
                    'sum_assured' => ['type' => ['number', 'null'], 'description' => 'Lump sum cover amount (£). For life insurance and critical illness policies. NOT for income protection or family income benefit.'],
                    'benefit_amount' => ['type' => ['number', 'null'], 'description' => 'Monthly benefit amount (£). For income_protection AND family_income_benefit only.'],
                    'premium_amount' => ['type' => ['number', 'null'], 'description' => 'Premium amount (£).'],
                    'premium_frequency' => $this->nullableEnum(['monthly', 'annually'], 'How often premiums are paid. Default "monthly".'),
                    'policy_term_years' => ['type' => ['integer', 'null'], 'description' => 'Policy term in years (not for whole of life).'],
                    'in_trust' => ['type' => ['boolean', 'null'], 'description' => 'Whether written in trust for IHT. Default false.'],
                ],
                ['policy_type', 'provider', 'sum_assured', 'benefit_amount', 'premium_amount', 'premium_frequency', 'policy_term_years', 'in_trust']
            ),
        ];
    }

    // ─── Estate ──────────────────────────────────────────────────────

    private function estateCreationTools(): array
    {
        return [
            $this->wrapTool(
                'create_asset',
                'Create an asset not covered by other tools — collectibles, artwork, or other valuable items.',
                [
                    'asset_name' => ['type' => 'string', 'description' => 'Name or description of the asset'],
                    'asset_type' => ['type' => 'string', 'enum' => ['property', 'pension', 'investment', 'business', 'other'], 'description' => 'Type of estate asset.'],
                    'current_value' => ['type' => 'number', 'description' => 'Current estimated value (£)'],
                    'is_iht_exempt' => ['type' => ['boolean', 'null'], 'description' => 'Whether exempt from IHT. Default false.'],
                    'exemption_reason' => ['type' => ['string', 'null'], 'description' => 'Reason for IHT exemption, if applicable.'],
                ],
                ['asset_name', 'asset_type', 'current_value', 'is_iht_exempt', 'exemption_reason']
            ),
            $this->wrapTool(
                'create_liability',
                'Create a liability. Use for any debt: credit cards, loans, student loans, car finance, overdrafts. '
                .'Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn.',
                [
                    'liability_name' => ['type' => 'string', 'description' => 'Name of the liability (e.g. "Barclays Visa", "Halifax Personal Loan", "BMW Car Finance")'],
                    'liability_type' => ['type' => 'string', 'enum' => ['personal_loan', 'credit_card', 'student_loan', 'hire_purchase', 'secured_loan', 'overdraft', 'business_loan', 'other'], 'description' => 'Type. "hire_purchase" for car finance/HP. "personal_loan" for bank loans. "credit_card" for credit cards. "student_loan" for student loans. "overdraft" for bank overdrafts.'],
                    'current_balance' => ['type' => 'number', 'description' => 'Outstanding balance (£)'],
                    'monthly_payment' => ['type' => ['number', 'null'], 'description' => 'Monthly payment (£)'],
                    'interest_rate' => ['type' => ['number', 'null'], 'description' => 'Interest rate as percentage'],
                ],
                ['liability_name', 'liability_type', 'current_balance', 'monthly_payment', 'interest_rate']
            ),
            $this->wrapTool(
                'create_estate_gift',
                'Record a gift for Inheritance Tax planning (7-year rule). Use when the user mentions gifts they have made to family, friends, trusts, or charities. '
                .'Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn.',
                [
                    'gift_date' => ['type' => 'string', 'description' => 'Date the gift was made (YYYY-MM-DD). Must be in the past. If user says "last Christmas" calculate the date. If user says "3 years ago" calculate from today.'],
                    'recipient' => ['type' => 'string', 'description' => 'Full name of the recipient (e.g. "Emma Smith", "Oxfam", "Smith Family Trust"). Use the person\'s actual name, not "my daughter" or "my son".'],
                    'gift_type' => [
                        'type' => 'string',
                        'enum' => ['pet', 'clt', 'exempt', 'small_gift', 'annual_exemption'],
                        'description' => '"pet" for Potentially Exempt Transfer — most common, gifts to individuals (becomes tax-free after 7 years). '
                            .'"clt" for Chargeable Lifetime Transfer — gifts to trusts or companies (immediately taxable at 20%). '
                            .'"exempt" for exempt gifts — to spouse, charities, political parties, or for marriage. '
                            .'"small_gift" for Small Gift Exemption — up to £250 per person per year. '
                            .'"annual_exemption" for Annual Exemption — first £3,000 of gifts each tax year.',
                    ],
                    'gift_value' => ['type' => 'number', 'description' => 'Value of the gift in pounds (£)'],
                    'notes' => ['type' => ['string', 'null'], 'description' => 'Additional context about the gift (e.g. "Cash for house deposit", "Wedding gift", "Birthday present")'],
                ],
                ['gift_date', 'recipient', 'gift_type', 'gift_value', 'notes']
            ),
        ];
    }

    // ─── Expenditure ────────────────────────────────────────────────

    private function expenditureTools(): array
    {
        return [
            $this->wrapTool(
                'set_expenditure',
                'Set the user\'s monthly expenditure by category. Call this IMMEDIATELY when the user mentions their spending, bills, or monthly outgoings. '
                .'Fill in every category the user mentions and set null for anything not mentioned. '
                .'The form will be opened, filled, and saved automatically. '
                .'IMPORTANT: Do NOT call any other creation tools in the same turn.',
                [
                    // Essential Living
                    'rent' => ['type' => ['number', 'null'], 'description' => 'Monthly rent in pounds. Null if homeowner.'],
                    'utilities' => ['type' => ['number', 'null'], 'description' => 'Monthly utilities (gas, electricity, water). Null if entered in property costs.'],
                    'food_groceries' => ['type' => ['number', 'null'], 'description' => 'Monthly food and groceries in pounds.'],
                    'transport_fuel' => ['type' => ['number', 'null'], 'description' => 'Monthly transport/fuel costs in pounds.'],
                    'healthcare_medical' => ['type' => ['number', 'null'], 'description' => 'Monthly healthcare costs in pounds.'],
                    'insurance' => ['type' => ['number', 'null'], 'description' => 'Monthly non-property insurance (car, medical, phone) in pounds.'],
                    // Communication
                    'mobile_phones' => ['type' => ['number', 'null'], 'description' => 'Monthly mobile phone costs in pounds.'],
                    'internet_tv' => ['type' => ['number', 'null'], 'description' => 'Monthly broadband/TV costs in pounds.'],
                    'subscriptions' => ['type' => ['number', 'null'], 'description' => 'Monthly subscriptions (Netflix, gym etc.) in pounds.'],
                    // Personal
                    'clothing_personal_care' => ['type' => ['number', 'null'], 'description' => 'Monthly clothing and personal care in pounds.'],
                    'entertainment_dining' => ['type' => ['number', 'null'], 'description' => 'Monthly entertainment and dining out in pounds.'],
                    'holidays_travel' => ['type' => ['number', 'null'], 'description' => 'Monthly average for holidays/travel in pounds.'],
                    'pets' => ['type' => ['number', 'null'], 'description' => 'Monthly pet costs in pounds.'],
                    // Children
                    'childcare' => ['type' => ['number', 'null'], 'description' => 'Monthly childcare costs in pounds.'],
                    'school_fees' => ['type' => ['number', 'null'], 'description' => 'Monthly school fees in pounds.'],
                    'school_lunches' => ['type' => ['number', 'null'], 'description' => 'Monthly school lunches in pounds.'],
                    'school_extras' => ['type' => ['number', 'null'], 'description' => 'Monthly school extras (uniforms, trips) in pounds.'],
                    'university_fees' => ['type' => ['number', 'null'], 'description' => 'Monthly university costs in pounds.'],
                    'children_activities' => ['type' => ['number', 'null'], 'description' => 'Monthly children activities (sports, music) in pounds.'],
                    // Other
                    'gifts_charity' => ['type' => ['number', 'null'], 'description' => 'Monthly gifts and presents in pounds.'],
                    'charitable_donations' => ['type' => ['number', 'null'], 'description' => 'Monthly charitable donations in pounds.'],
                    'other_expenditure' => ['type' => ['number', 'null'], 'description' => 'Any other monthly expenses in pounds.'],
                ],
                [
                    'rent', 'utilities', 'food_groceries', 'transport_fuel', 'healthcare_medical', 'insurance',
                    'mobile_phones', 'internet_tv', 'subscriptions',
                    'clothing_personal_care', 'entertainment_dining', 'holidays_travel', 'pets',
                    'childcare', 'school_fees', 'school_lunches', 'school_extras', 'university_fees', 'children_activities',
                    'gifts_charity', 'charitable_donations', 'other_expenditure',
                ]
            ),
        ];
    }

    // ─── Additional Creation ─────────────────────────────────────────

    private function additionalCreationTools(): array
    {
        return [
            $this->wrapTool(
                'create_family_member',
                'Add a family member. Use when the user mentions children, parents, step-children, dependents, or partners. '
                .'For spouse: only use if the user explicitly asks to add their spouse — the system may already have a linked spouse account. '
                .'Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn. '
                .'For multiple children, call this tool ONCE per child in separate turns.',
                [
                    'first_name' => ['type' => 'string', 'description' => 'First name of the family member'],
                    'surname' => ['type' => ['string', 'null'], 'description' => 'Surname/last name. If not mentioned, assume same as user.'],
                    'relationship' => [
                        'type' => 'string',
                        'enum' => ['spouse', 'partner', 'child', 'step_child', 'parent', 'other_dependent'],
                        'description' => '"spouse" for married/civil partner. "partner" for unmarried partner. "child" for biological child. "step_child" for step children. "parent" for mother/father. "other_dependent" for other financially dependent relatives (aunt, grandparent, sibling etc).',
                    ],
                    'date_of_birth' => ['type' => ['string', 'null'], 'description' => 'Date of birth (YYYY-MM-DD). If user gives age, calculate from today. Spouse must be 16+, child max 18 (or 22 if in education).'],
                    'gender' => $this->nullableEnum(['male', 'female', 'other', 'prefer_not_to_say'], 'Gender. Infer from name/context if obvious (e.g. "daughter" = female, "son" = male).'),
                    'is_dependent' => ['type' => ['boolean', 'null'], 'description' => 'Whether financially dependent on the user. Default true for children, step_children, and other_dependents.'],
                    'education_status' => $this->nullableEnum(
                        ['pre_school', 'primary', 'secondary', 'further_education', 'higher_education', 'graduated', 'not_applicable'],
                        'Education status. Only for child/step_child. "pre_school" for nursery/pre-school. "primary" for primary school. "secondary" for secondary school. "further_education" for sixth form/college. "higher_education" for university. "graduated" if finished university. "not_applicable" if not in education.'
                    ),
                    'receives_child_benefit' => ['type' => ['boolean', 'null'], 'description' => 'Whether child benefit is claimed for this child. Only for child/step_child.'],
                    'notes' => ['type' => ['string', 'null'], 'description' => 'Any additional notes about this family member'],
                ],
                ['first_name', 'surname', 'relationship', 'date_of_birth', 'gender', 'is_dependent', 'education_status', 'receives_child_benefit', 'notes']
            ),
            $this->wrapTool(
                'create_trust',
                'Record a trust for estate planning. Use for discretionary trusts, bare trusts, life insurance trusts, loan trusts, discounted gift trusts, interest in possession trusts, and other UK trust types. '
                .'Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn.',
                [
                    'trust_name' => ['type' => 'string', 'description' => 'Name of the trust (e.g. "Smith Family Discretionary Trust")'],
                    'trust_type' => ['type' => 'string', 'enum' => ['discretionary', 'bare', 'interest_in_possession', 'life_insurance', 'loan', 'discounted_gift', 'accumulation_maintenance', 'mixed', 'settlor_interested'], 'description' => 'Type of trust. "discretionary" for family discretionary trusts. "bare" for bare/absolute trusts. "interest_in_possession" for life interest trusts. "life_insurance" for trusts holding life policies. "loan" for loan trusts. "discounted_gift" for DGTs. "accumulation_maintenance" for A&M trusts. "mixed" for combined trust types. "settlor_interested" when settlor/spouse can benefit.'],
                    'initial_value' => ['type' => ['number', 'null'], 'description' => 'Amount originally settled into the trust (£)'],
                    'current_value' => ['type' => ['number', 'null'], 'description' => 'Current value of assets in trust (£)'],
                    'trust_creation_date' => ['type' => ['string', 'null'], 'description' => 'Date trust was established (YYYY-MM-DD)'],
                    'beneficiaries' => ['type' => ['string', 'null'], 'description' => 'Comma-separated list of beneficiaries (e.g. "James Smith, Emily Smith")'],
                    'trustees' => ['type' => ['string', 'null'], 'description' => 'Comma-separated list of trustees (e.g. "John Smith, ABC Trustee Services Ltd")'],
                    'purpose' => ['type' => ['string', 'null'], 'description' => 'Purpose of the trust (e.g. "Estate planning and IHT mitigation")'],
                ],
                ['trust_name', 'trust_type', 'initial_value', 'current_value', 'trust_creation_date', 'beneficiaries', 'trustees', 'purpose']
            ),
            $this->wrapTool(
                'create_business_interest',
                'Record a business interest or ownership. Handles sole trader, partnership, limited company, LLP. '
                .'Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn.',
                [
                    'business_name' => ['type' => 'string', 'description' => 'Name of the business (e.g. "Acme Technologies Ltd", "Smith Consulting")'],
                    'business_type' => ['type' => 'string', 'enum' => ['sole_trader', 'partnership', 'limited_company', 'llp', 'other'], 'description' => '"sole_trader" for self-employed. "partnership" for partnerships. "limited_company" for Ltd companies. "llp" for Limited Liability Partnerships. "other" for anything else.'],
                    'industry_sector' => ['type' => ['string', 'null'], 'description' => 'Industry sector (e.g. "Technology", "Consulting", "Construction", "Retail")'],
                    'ownership_percentage' => ['type' => ['number', 'null'], 'description' => 'Percentage owned (0-100). Default 100 for sole owner.'],
                    'estimated_value' => ['type' => ['number', 'null'], 'description' => 'Estimated current value of the business (£).'],
                    'annual_revenue' => ['type' => ['number', 'null'], 'description' => 'Annual turnover/revenue (£).'],
                    'annual_profit' => ['type' => ['number', 'null'], 'description' => 'Annual net profit (£). Can be negative for losses.'],
                    'annual_dividend_income' => ['type' => ['number', 'null'], 'description' => 'Annual dividends taken from this business (£). For limited companies only.'],
                    'employee_count' => ['type' => ['integer', 'null'], 'description' => 'Number of employees including the owner.'],
                ],
                ['business_name', 'business_type', 'industry_sector', 'ownership_percentage', 'estimated_value', 'annual_revenue', 'annual_profit', 'annual_dividend_income', 'employee_count']
            ),
            $this->wrapTool(
                'create_chattel',
                'Record a personal valuable item. Use this for jewellery, art, fine art, wine, fine wine, antiques, collectibles, vehicles, watches, handbags, and other physical valuables. Do NOT use this for gold, silver, cryptocurrency, or financial investments — use create_investment_account with type "other" instead.',
                [
                    'description' => ['type' => 'string', 'description' => 'Description of the item'],
                    'category' => ['type' => 'string', 'enum' => ['jewellery', 'art', 'antiques', 'collectibles', 'vehicles', 'other'], 'description' => 'Category of item'],
                    'estimated_value' => ['type' => 'number', 'description' => 'Estimated current value (£)'],
                    'purchase_value' => ['type' => ['number', 'null'], 'description' => 'Original purchase value (£)'],
                    'is_insured' => ['type' => ['boolean', 'null'], 'description' => 'Whether the item is insured'],
                ],
                ['description', 'category', 'estimated_value', 'purchase_value', 'is_insured']
            ),
        ];
    }

    // ─── Data Modification ───────────────────────────────────────────

    private function dataModificationTools(): array
    {
        return [
            $this->wrapTool(
                'update_record',
                'Update an existing record. Use when the user wants to change details of an existing financial record. Ask the user to confirm changes before calling.',
                [
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
                ['entity_type', 'entity_id', 'fields'],
                false // Cannot use strict mode — fields object has dynamic keys
            ),
            $this->wrapTool(
                'delete_record',
                'Delete an existing record. ALWAYS confirm with the user before deleting.',
                [
                    'entity_type' => [
                        'type' => 'string',
                        'enum' => ['goal', 'life_event', 'savings_account', 'investment_account', 'dc_pension', 'db_pension', 'property', 'mortgage', 'life_insurance', 'critical_illness', 'income_protection', 'estate_asset', 'estate_liability', 'estate_gift', 'family_member', 'trust', 'business_interest', 'chattel'],
                        'description' => 'The type of record to delete',
                    ],
                    'entity_id' => ['type' => 'integer', 'description' => 'The ID of the record to delete'],
                ],
                ['entity_type', 'entity_id']
            ),
        ];
    }

    // ─── Profile ─────────────────────────────────────────────────────

    private function profileTools(): array
    {
        return [
            $this->wrapTool(
                'update_profile',
                'Update the user\'s profile information (personal details, income, or domicile). '
                .'NEVER use this for any expenditure or spending data — use set_expenditure instead. '
                .'Expenditure fields (food_groceries, transport_fuel, rent, utilities, childcare, entertainment_dining, etc.) '
                .'are ALL handled exclusively by set_expenditure.',
                [
                    'section' => [
                        'type' => 'string',
                        'enum' => ['personal', 'income_occupation', 'domicile'],
                        'description' => 'Which profile section to update. Must be one of: personal, income_occupation, domicile. NEVER pass expenditure — use set_expenditure for all spending fields.',
                    ],
                    'fields' => [
                        'type' => 'object',
                        'description' => 'Key-value pairs of fields to update. For personal: first_name, surname, date_of_birth, gender, marital_status, phone, address_line_1, city, postcode. For income_occupation: employment_status (MUST be one of: employed, full_time, part_time, self_employed, retired, unemployed, other), occupation, employer, annual_employment_income. For domicile: country_of_birth, uk_arrival_date. Do NOT include any spending or expenditure keys here.',
                        'additionalProperties' => true,
                    ],
                ],
                ['section', 'fields'],
                false // Cannot use strict mode — fields object has dynamic keys
            ),
        ];
    }
}
