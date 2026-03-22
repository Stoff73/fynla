<?php

declare(strict_types=1);

namespace App\Services\UserProfile;

use App\Models\User;

/**
 * Service for determining what data each module needs.
 *
 * Provides plain-language explanations of why each piece of data
 * is collected and how it powers the views/calculations.
 */
class ModuleDataRequirementsService
{
    /**
     * Module requirement definitions.
     * Plain English labels and explanations - no acronyms.
     */
    private const MODULE_REQUIREMENTS = [
        'dashboard' => [
            'description' => 'Your dashboard gives you an overview of your complete financial picture.',
            'fields' => [
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Used to calculate your age for retirement planning and life expectancy estimates',
                    'how_used' => 'Calculates your age for dashboard widgets, retirement countdown, and life expectancy estimates',
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Shows your earnings and helps calculate tax, savings rates, and protection needs',
                    'how_used' => 'Powers income vs spending analysis, savings rate, and tax position summary',
                    'link' => '/valuable-info?section=income',
                ],
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Helps track your budget and calculate how much you can save each month',
                    'how_used' => 'Calculates your savings rate and identifies opportunities to save more',
                    'link' => '/valuable-info?section=expenditure',
                ],
            ],
            'relationships' => [
                'properties' => [
                    'label' => 'Your properties',
                    'why' => 'Properties are often your largest asset and contribute to your net worth',
                    'how_used' => 'Included in your total net worth and estate value calculations',
                    'link' => '/net-worth/property',
                ],
                'savings_accounts' => [
                    'label' => 'Your savings accounts',
                    'why' => 'Tracks your cash savings, emergency fund, and tax-free savings allowances',
                    'how_used' => 'Shows your emergency fund status and Individual Savings Account allowance usage',
                    'link' => '/net-worth/cash',
                ],
            ],
        ],

        'protection' => [
            'description' => 'Protection analysis calculates how much cover you need to protect your family and income.',
            'fields' => [
                'income_needs_update' => [
                    'label' => 'Income needs updating',
                    'why' => 'Your employment status has changed - update your income for accurate protection calculations',
                    'how_used' => 'Ensures your protection cover recommendations are based on current earnings',
                    'link' => '/valuable-info?section=income',
                ],
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Used to calculate life expectancy and insurance term lengths',
                    'how_used' => 'Calculates insurance term lengths and premium estimates based on your age',
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Determines how much income protection cover you need if you cannot work',
                    'how_used' => 'Determines recommended income protection and life cover amounts',
                    'link' => '/valuable-info?section=income',
                ],
                'marital_status' => [
                    'label' => 'Your marital status',
                    'why' => 'Married people often need more life cover to protect their spouse',
                    'how_used' => 'Adjusts cover recommendations based on whether you have a financially dependent spouse',
                    'link' => '/profile',
                ],
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Helps calculate how much your family would need to maintain their lifestyle',
                    'how_used' => 'Calculates family protection needs — the monthly income your dependants would need',
                    'link' => '/valuable-info?section=expenditure',
                ],
                'occupation' => [
                    'label' => 'Your occupation',
                    'why' => 'Your job affects insurance premiums and income protection eligibility',
                    'how_used' => 'Affects income protection eligibility and premium calculations for your occupation class',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'family_members' => [
                    'label' => 'Your children or dependants',
                    'why' => 'Dependants need financial protection if something happens to you',
                    'how_used' => 'Calculates total dependent protection needs and child-specific cover recommendations',
                    'link' => '/profile',
                ],
                'mortgages' => [
                    'label' => 'Your mortgage details',
                    'why' => 'Mortgage debt is often the largest protection need - should be covered by life insurance',
                    'how_used' => 'Adds mortgage balance to your recommended life cover amount',
                    'link' => '/net-worth/property',
                ],
                'liabilities' => [
                    'label' => 'Your other debts and loans',
                    'why' => 'All debts should be considered when calculating protection needs',
                    'how_used' => 'Includes outstanding debts in total protection cover needed',
                    'link' => '/net-worth/liabilities',
                ],
            ],
        ],

        'savings' => [
            'description' => 'Savings analysis tracks your emergency fund and tax-efficient savings allowances.',
            'fields' => [
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Calculates how many months of expenses your emergency fund covers',
                    'how_used' => 'Calculates how many months of expenses your emergency fund covers',
                    'link' => '/valuable-info?section=expenditure',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Helps determine appropriate savings targets and tax-free allowances',
                    'how_used' => 'Determines your Individual Savings Account contribution capacity and savings targets',
                    'link' => '/valuable-info?section=income',
                ],
            ],
            'relationships' => [
                'savings_accounts' => [
                    'label' => 'Your savings accounts',
                    'why' => 'Tracks your cash savings, tax-free Individual Savings Account usage, and emergency fund',
                    'how_used' => 'Tracks individual account performance, interest rates, and tax-free allowance usage',
                    'link' => '/net-worth/cash',
                ],
            ],
        ],

        'investment' => [
            'description' => 'Investment analysis helps you understand your portfolio allocation and tax efficiency.',
            'fields' => [
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Your age affects recommended asset allocation - younger investors can typically take more risk',
                    'how_used' => 'Determines age-appropriate asset allocation and investment time horizon',
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Income affects your tax band and the tax efficiency of different investment wrappers',
                    'how_used' => 'Identifies your tax band for optimising investment wrapper selection',
                    'link' => '/valuable-info?section=income',
                ],
                'target_retirement_age' => [
                    'label' => 'When you want to retire',
                    'why' => 'Your investment timeline affects how much risk you should take',
                    'how_used' => 'Sets your investment time horizon for risk-appropriate portfolio recommendations',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'investment_accounts' => [
                    'label' => 'Your investment accounts',
                    'why' => 'Analyse your portfolio, track performance, and optimise asset allocation',
                    'how_used' => 'Analyses portfolio allocation, fee impact, and rebalancing opportunities',
                    'link' => '/net-worth/investments',
                ],
            ],
        ],

        'retirement' => [
            'description' => 'Retirement planning projects your future income and helps you prepare for when you stop working.',
            'fields' => [
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Calculates how many years until you can access your pension',
                    'how_used' => 'Calculates years to pension access age and retirement projection timeline',
                    'link' => '/profile',
                ],
                'target_retirement_age' => [
                    'label' => 'When you want to retire',
                    'why' => 'Projects how much you need to save and what income you will have',
                    'how_used' => 'Projects required savings and sustainable withdrawal rate for your chosen retirement date',
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your current income',
                    'why' => 'Helps calculate pension contributions and your income replacement ratio',
                    'how_used' => 'Calculates pension contribution headroom and income replacement ratio',
                    'link' => '/valuable-info?section=income',
                ],
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Estimates how much income you will need in retirement',
                    'how_used' => 'Estimates required retirement income and tests pension sustainability',
                    'link' => '/valuable-info?section=expenditure',
                ],
            ],
            'relationships' => [
                'dc_pensions' => [
                    'label' => 'Your money purchase pensions',
                    'why' => 'Workplace pensions, SIPPs, and personal pensions with a pot value that you can draw from flexibly in retirement',
                    'how_used' => 'Projects pot growth, tests contribution levels, and models drawdown strategies',
                    'link' => '/net-worth/retirement',
                ],
                'db_pensions' => [
                    'label' => 'Any final salary or career average pensions',
                    'why' => 'Add these if you have a defined benefit scheme that pays a guaranteed income based on your salary and years of service',
                    'how_used' => 'Adds guaranteed income to your retirement projection',
                    'link' => '/net-worth/retirement',
                ],
                'state_pension' => [
                    'label' => 'Your State Pension forecast',
                    'why' => 'Most people receive State Pension from age 66-68 - add your forecast for accurate projections',
                    'how_used' => 'Includes State Pension forecast in total projected retirement income',
                    'link' => '/net-worth/retirement',
                ],
            ],
        ],

        'estate' => [
            'description' => 'Estate planning helps you understand inheritance tax and pass on wealth efficiently.',
            'fields' => [
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Used for life expectancy and estate planning timelines',
                    'how_used' => 'Estimates life expectancy for estate planning timelines',
                    'link' => '/profile',
                ],
                'domicile_status' => [
                    'label' => 'Your domicile status',
                    'why' => 'Determines which inheritance tax rules apply to your estate',
                    'how_used' => 'Determines whether UK or international inheritance tax rules apply',
                    'link' => '/profile',
                ],
                'marital_status' => [
                    'label' => 'Your marital status',
                    'why' => 'Married couples can pass assets tax-free to each other (spouse exemption)',
                    'how_used' => 'Enables spouse exemption and transferable nil-rate band calculations',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'properties' => [
                    'label' => 'Your properties',
                    'why' => 'Properties count towards your estate value for inheritance tax',
                    'how_used' => 'Includes property values in estate total and tests residence nil-rate band eligibility',
                    'link' => '/net-worth/property',
                ],
                'investment_accounts' => [
                    'label' => 'Your investments',
                    'why' => 'Investment assets form part of your taxable estate',
                    'how_used' => 'Adds investment values to your taxable estate',
                    'link' => '/net-worth/investments',
                ],
                'spouse' => [
                    'label' => 'Your spouse details',
                    'why' => 'Spouse exemption and transferable allowances can significantly reduce inheritance tax',
                    'how_used' => 'Enables transferable nil-rate band and spouse exemption modelling',
                    'link' => '/profile',
                ],
                'family_members' => [
                    'label' => 'Your beneficiaries',
                    'why' => 'Leaving your home to direct descendants can unlock the residence nil-rate band',
                    'how_used' => 'Maps beneficiaries for residence nil-rate band eligibility',
                    'link' => '/profile',
                ],
            ],
        ],

        'net_worth' => [
            'description' => 'Net worth shows your total assets minus liabilities - your overall financial position.',
            'fields' => [
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Your age provides context for your net worth journey',
                    'how_used' => 'Provides age context for your net worth trajectory',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'properties' => [
                    'label' => 'Your properties',
                    'why' => 'Property is often the largest component of net worth',
                    'how_used' => 'Adds property equity to your total assets',
                    'link' => '/net-worth/property',
                ],
                'savings_accounts' => [
                    'label' => 'Your savings',
                    'why' => 'Cash savings contribute to your liquid net worth',
                    'how_used' => 'Includes cash savings in your liquid assets total',
                    'link' => '/net-worth/cash',
                ],
                'investment_accounts' => [
                    'label' => 'Your investments',
                    'why' => 'Investment portfolios are a key wealth-building asset',
                    'how_used' => 'Adds investment portfolio values to your total wealth',
                    'link' => '/net-worth/investments',
                ],
                'dc_pensions' => [
                    'label' => 'Your money purchase pensions',
                    'why' => 'Workplace pensions, SIPPs, and personal pensions are part of your total wealth',
                    'how_used' => 'Includes pension pot values in your total wealth picture',
                    'link' => '/net-worth/retirement',
                ],
                'mortgages' => [
                    'label' => 'Your mortgages',
                    'why' => 'Mortgage debt reduces your net worth',
                    'how_used' => 'Subtracts outstanding mortgage balances from your total assets',
                    'link' => '/net-worth/property',
                ],
                'liabilities' => [
                    'label' => 'Your debts and loans',
                    'why' => 'All debts are subtracted from your assets to calculate net worth',
                    'how_used' => 'Subtracts debts and loans from your total assets to show true net worth',
                    'link' => '/net-worth/liabilities',
                ],
            ],
        ],

        'trusts' => [
            'description' => 'Trusts help you manage and protect assets for beneficiaries while potentially reducing inheritance tax.',
            'fields' => [
                'marital_status' => [
                    'label' => 'Your marital status',
                    'why' => 'Married couples often use trusts together for estate planning',
                    'how_used' => 'Determines available trust planning strategies for couples',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'trusts' => [
                    'label' => 'Your trusts',
                    'why' => 'Track trust details including type, trustees, beneficiaries, and assets held',
                    'how_used' => 'Tracks trust structures, values, and beneficiary distributions',
                    'link' => '/trusts',
                ],
                'family_members' => [
                    'label' => 'Your beneficiaries',
                    'why' => 'Family members are often trust beneficiaries - add them to track distributions',
                    'how_used' => 'Maps trust beneficiaries for distribution planning',
                    'link' => '/profile',
                ],
            ],
        ],

        'properties' => [
            'description' => 'Track your property portfolio including main residence, buy-to-lets, and holiday homes.',
            'fields' => [],
            'relationships' => [
                'properties' => [
                    'label' => 'Your properties',
                    'why' => 'Add property details including value, ownership type, and rental income',
                    'how_used' => 'Tracks property values, rental yields, and Capital Gains Tax positions',
                    'link' => '/net-worth/property',
                ],
                'mortgages' => [
                    'label' => 'Your mortgages',
                    'why' => 'Link mortgages to properties to calculate equity and monthly costs',
                    'how_used' => 'Calculates equity per property and total monthly mortgage costs',
                    'link' => '/net-worth/property',
                ],
            ],
        ],

        'liabilities' => [
            'description' => 'Track all your debts and loans to understand your total financial obligations.',
            'fields' => [],
            'relationships' => [
                'mortgages' => [
                    'label' => 'Your mortgages',
                    'why' => 'Mortgages are typically your largest debt - track balances and repayments',
                    'how_used' => 'Tracks mortgage repayment progress and interest costs',
                    'link' => '/net-worth/property',
                ],
                'liabilities' => [
                    'label' => 'Your other debts',
                    'why' => 'Include credit cards, loans, overdrafts, and hire purchase agreements',
                    'how_used' => 'Monitors debt reduction progress and total interest costs',
                    'link' => '/net-worth/liabilities',
                ],
            ],
        ],

        'business_interests' => [
            'description' => 'Track your business ownership including shares, partnerships, and sole trader businesses.',
            'fields' => [
                'occupation' => [
                    'label' => 'Your occupation',
                    'why' => 'Your role in the business affects tax treatment and exit planning',
                    'how_used' => 'Links your role to Business Property Relief eligibility',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'business_interests' => [
                    'label' => 'Your business interests',
                    'why' => 'Add business details including type, ownership percentage, valuation, and exit plans',
                    'how_used' => 'Calculates business values for net worth and estate planning',
                    'link' => '/net-worth/business',
                ],
            ],
        ],

        'chattels' => [
            'description' => 'Track valuable personal possessions including vehicles, art, antiques, and collectibles.',
            'fields' => [],
            'relationships' => [
                'chattels' => [
                    'label' => 'Your valuable items',
                    'why' => 'Add personal valuables to track values, calculate Capital Gains Tax on disposal, and include in estate planning',
                    'how_used' => 'Tracks chattel values for Capital Gains Tax calculations and estate inclusion',
                    'link' => '/net-worth/chattels',
                ],
            ],
        ],

        'profile' => [
            'description' => 'Your personal and financial profile provides the foundation for all planning calculations.',
            'fields' => [
                'income_needs_update' => [
                    'label' => 'Income needs updating',
                    'why' => 'Your employment status has changed - please update your income to reflect your current earnings',
                    'how_used' => 'Flags that protection and savings calculations may use outdated income data',
                    'link' => '/valuable-info?section=income',
                ],
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Essential for retirement planning, life expectancy, and insurance calculations',
                    'how_used' => 'Foundation for all age-based calculations across every module',
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Used for tax calculations, protection needs, and savings targets',
                    'how_used' => 'Core input for tax calculations, protection needs, and savings targets',
                    'link' => '/valuable-info?section=income',
                ],
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Helps calculate emergency fund needs and retirement income requirements',
                    'how_used' => 'Used across budgeting, protection, retirement, and goal planning',
                    'link' => '/valuable-info?section=expenditure',
                ],
                'marital_status' => [
                    'label' => 'Your marital status',
                    'why' => 'Affects tax allowances, estate planning, and protection needs',
                    'how_used' => 'Determines tax allowances, estate planning strategies, and household features',
                    'link' => '/profile',
                ],
                'occupation' => [
                    'label' => 'Your occupation',
                    'why' => 'Affects insurance premiums and income protection eligibility',
                    'how_used' => 'Affects insurance premiums and Business Property Relief calculations',
                    'link' => '/profile',
                ],
                'target_retirement_age' => [
                    'label' => 'Your target retirement age',
                    'why' => 'Determines your investment timeline and pension access strategy',
                    'how_used' => 'Sets investment timeline and pension projection end date',
                    'link' => '/profile',
                ],
                'domicile_status' => [
                    'label' => 'Your domicile status',
                    'why' => 'Determines which tax rules apply to your worldwide assets',
                    'how_used' => 'Controls which international tax rules apply to your estate',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'family_members' => [
                    'label' => 'Your family members',
                    'why' => 'Add spouse, children, and dependants for protection and estate planning',
                    'how_used' => 'Used across protection, estate, and household planning modules',
                    'link' => '/profile',
                ],
            ],
        ],

        'budgeting' => [
            'description' => 'Track your income, spending, and savings to understand your monthly cash flow.',
            'fields' => [
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Calculates your savings rate and how much you have available to save each month',
                    'how_used' => 'Powers your budget dashboard, emergency fund tracker, and savings recommendations',
                    'link' => '/valuable-info?section=expenditure',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Shows your earnings so we can calculate your savings rate and tax-free allowance usage',
                    'how_used' => 'Used to calculate your savings rate percentage and Individual Savings Account contribution headroom',
                    'link' => '/valuable-info?section=income',
                ],
            ],
            'relationships' => [
                'savings_accounts' => [
                    'label' => 'Your savings accounts',
                    'why' => 'Tracks your cash savings, emergency fund progress, and tax-free savings usage',
                    'how_used' => 'Powers your emergency fund tracker and Individual Savings Account allowance monitoring',
                    'link' => '/net-worth/cash',
                ],
            ],
        ],

        'family' => [
            'description' => 'Your family details improve protection recommendations, estate planning, and household coordination.',
            'fields' => [
                'marital_status' => [
                    'label' => 'Your marital status',
                    'why' => 'Affects tax allowances, estate planning, and whether household coordination features are available',
                    'how_used' => 'Enables spouse exemption in estate planning and unlocks household financial coordination',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'family_members' => [
                    'label' => 'Your children and dependants',
                    'why' => 'Dependants affect your protection needs and are beneficiaries in your estate plan',
                    'how_used' => 'Calculates protection cover amounts and maps estate beneficiaries',
                    'link' => '/profile',
                ],
                'spouse' => [
                    'label' => 'Your spouse or partner details',
                    'why' => 'Enables household financial planning and spousal tax optimisations',
                    'how_used' => 'Powers household net worth, spousal transfer recommendations, and death-of-spouse scenario modelling',
                    'link' => '/profile',
                ],
            ],
        ],

        'business' => [
            'description' => 'Record your business interests to include them in your net worth and estate planning.',
            'fields' => [
                'occupation' => [
                    'label' => 'Your occupation',
                    'why' => 'Your role in the business affects tax treatment and succession planning',
                    'how_used' => 'Used in Business Property Relief calculations and exit planning scenarios',
                    'link' => '/valuable-info?section=income',
                ],
                'employment_status' => [
                    'label' => 'Your employment status',
                    'why' => 'Whether you are employed, self-employed, or a company director affects your tax position',
                    'how_used' => 'Determines applicable tax reliefs and pension contribution limits',
                    'link' => '/valuable-info?section=income',
                ],
            ],
            'relationships' => [
                'business_interests' => [
                    'label' => 'Your business interests',
                    'why' => 'Add your businesses to include them in your net worth and estate planning',
                    'how_used' => 'Included in net worth calculations, estate value, and Business Property Relief eligibility',
                    'link' => '/net-worth/business',
                ],
            ],
        ],

        'goals' => [
            'description' => 'Set financial goals and track your progress towards achieving them.',
            'fields' => [
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Your age helps us estimate realistic timelines for your financial goals',
                    'how_used' => 'Calculates time horizons and age-appropriate goal recommendations',
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Helps assess how much you can realistically save towards your goals each month',
                    'how_used' => 'Powers affordability analysis and contribution recommendations for each goal',
                    'link' => '/valuable-info?section=income',
                ],
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Shows how much disposable income you have available for goal contributions',
                    'how_used' => 'Calculates your maximum monthly saving capacity and goal feasibility',
                    'link' => '/valuable-info?section=expenditure',
                ],
            ],
            'relationships' => [
                'goals' => [
                    'label' => 'Your financial goals',
                    'why' => 'Set targets like saving for a deposit, building an emergency fund, or planning a holiday',
                    'how_used' => 'Tracks progress, calculates affordability, and shows milestone achievements',
                    'link' => '/goals',
                ],
            ],
        ],
    ];

    /**
     * Route to module mapping.
     */
    private const ROUTE_MODULE_MAP = [
        '/dashboard' => 'dashboard',
        '/protection' => 'protection',
        '/savings' => 'savings',
        '/investment' => 'investment',
        '/retirement' => 'retirement',
        '/estate' => 'estate',
        '/net-worth' => 'net_worth',
        '/goals' => 'goals',
        '/profile' => 'profile',
    ];

    /**
     * Get requirements for a specific module, personalised for the user.
     */
    public function getRequirementsForModule(User $user, string $module): array
    {
        $requirements = self::MODULE_REQUIREMENTS[$module] ?? self::MODULE_REQUIREMENTS['dashboard'];

        $allRequirements = [];
        $filled = [];
        $missing = [];

        // Check field requirements
        foreach ($requirements['fields'] ?? [] as $fieldKey => $fieldConfig) {
            $isFilled = $this->isFieldFilled($user, $fieldKey);

            $requirement = [
                'key' => $fieldKey,
                'type' => 'field',
                'label' => $fieldConfig['label'],
                'why' => $fieldConfig['why'],
                'how_used' => $fieldConfig['how_used'] ?? '',
                'link' => $fieldConfig['link'],
                'status' => $isFilled ? 'filled' : 'missing',
            ];

            $allRequirements[] = $requirement;

            if ($isFilled) {
                $filled[] = $requirement;
            } else {
                $missing[] = $requirement;
            }
        }

        // Check relationship requirements
        foreach ($requirements['relationships'] ?? [] as $relationKey => $relationConfig) {
            $isFilled = $this->isRelationshipFilled($user, $relationKey);

            $requirement = [
                'key' => $relationKey,
                'type' => 'relationship',
                'label' => $relationConfig['label'],
                'why' => $relationConfig['why'],
                'how_used' => $relationConfig['how_used'] ?? '',
                'link' => $relationConfig['link'],
                'status' => $isFilled ? 'filled' : 'missing',
            ];

            $allRequirements[] = $requirement;

            if ($isFilled) {
                $filled[] = $requirement;
            } else {
                $missing[] = $requirement;
            }
        }

        $total = count($allRequirements);
        $filledCount = count($filled);

        return [
            'module' => $module,
            'description' => $requirements['description'] ?? '',
            'all_requirements' => $allRequirements,
            'filled' => $filled,
            'missing' => $missing,
            'completion_percentage' => $total > 0 ? round(($filledCount / $total) * 100) : 100,
            'filled_count' => $filledCount,
            'total_count' => $total,
        ];
    }

    /**
     * Get module name from a route path.
     */
    public function getModuleFromRoute(string $routePath): string
    {
        foreach (self::ROUTE_MODULE_MAP as $prefix => $module) {
            if (str_starts_with($routePath, $prefix)) {
                return $module;
            }
        }

        return 'dashboard';
    }

    /**
     * Check if a user field is filled.
     */
    private function isFieldFilled(User $user, string $fieldKey): bool
    {
        $value = $user->getAttribute($fieldKey);

        // Special handling for income_needs_update - returns false (missing) if true
        if ($fieldKey === 'income_needs_update') {
            return ! $user->income_needs_update;
        }

        // Special handling for annual_employment_income - also check pension income
        // Users with pension income don't need employment income
        if ($fieldKey === 'annual_employment_income') {
            // Has employment income
            if ($value !== null) {
                return true;
            }
            // Check for pension income sources
            $hasStatePensionIncome = $user->statePension()->where('state_pension_forecast_annual', '>', 0)->exists();
            $hasDBPensionIncome = $user->dbPensions()->where('accrued_annual_pension', '>', 0)->exists();
            $hasDCPensionIncome = $user->employment_status === 'retired' && $user->dcPensions()->exists();

            return $hasStatePensionIncome || $hasDBPensionIncome || $hasDCPensionIncome;
        }

        // Special handling for domicile_status - check if it's a valid value
        // (not null, not empty string, and not the placeholder 'not_set')
        if ($fieldKey === 'domicile_status') {
            return $value !== null && $value !== '' && $value !== 'not_set';
        }

        // For numeric fields, 0 is valid
        if (in_array($fieldKey, ['monthly_expenditure', 'target_retirement_age'])) {
            return $value !== null;
        }

        return ! empty($value);
    }

    /**
     * Check if a user relationship has data.
     */
    private function isRelationshipFilled(User $user, string $relationKey): bool
    {
        return match ($relationKey) {
            // Properties is "filled" if user has properties OR is paying rent (renter, not owner)
            'properties' => $user->properties()->exists() || ($user->rent > 0),
            'mortgages' => $user->mortgages()->exists(),
            'liabilities' => $user->liabilities()->exists(),
            'savings_accounts' => $user->savingsAccounts()->exists(),
            'investment_accounts' => $user->investmentAccounts()->exists(),
            // DC pensions: filled if user has them, OR if user has DB pensions (may legitimately not have DC), OR if retired
            'dc_pensions' => $user->dcPensions()->exists() || $user->dbPensions()->exists() || $user->employment_status === 'retired',
            'db_pensions' => $user->dbPensions()->exists(),
            'state_pension' => $user->statePension()->exists(),
            'family_members' => $user->familyMembers()->exists(),
            'spouse' => $this->isSpouseRequirementFilled($user),
            'trusts' => $user->trusts()->exists(),
            'business_interests' => $user->businessInterests()->exists(),
            'chattels' => $user->chattels()->exists(),
            'goals' => $user->goals()->exists(),
            'protection_policies' => $user->lifeInsurancePolicies()->exists()
                || $user->criticalIllnessPolicies()->exists()
                || $user->incomeProtectionPolicies()->exists(),
            default => false,
        };
    }

    /**
     * Check if spouse requirement is filled.
     *
     * For single, divorced, or widowed users, this is always "filled"
     * since they don't have a spouse to add.
     */
    private function isSpouseRequirementFilled(User $user): bool
    {
        // Single, divorced, or widowed users don't need spouse info
        $nonMarriedStatuses = ['single', 'divorced', 'widowed'];

        if (in_array($user->marital_status, $nonMarriedStatuses, true)) {
            return true;
        }

        // Married users need spouse_id to be set
        return $user->spouse_id !== null;
    }
}
