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
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Shows your earnings and helps calculate tax, savings rates, and protection needs',
                    'link' => '/profile',
                ],
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Helps track your budget and calculate how much you can save each month',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'properties' => [
                    'label' => 'Your properties',
                    'why' => 'Properties are often your largest asset and contribute to your net worth',
                    'link' => '/net-worth',
                ],
                'savings_accounts' => [
                    'label' => 'Your savings accounts',
                    'why' => 'Tracks your cash savings, emergency fund, and tax-free savings allowances',
                    'link' => '/savings',
                ],
            ],
        ],

        'protection' => [
            'description' => 'Protection analysis calculates how much cover you need to protect your family and income.',
            'fields' => [
                'income_needs_update' => [
                    'label' => 'Income needs updating',
                    'why' => 'Your employment status has changed - update your income for accurate protection calculations',
                    'link' => '/profile',
                ],
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Used to calculate life expectancy and insurance term lengths',
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Determines how much income protection cover you need if you cannot work',
                    'link' => '/profile',
                ],
                'marital_status' => [
                    'label' => 'Your marital status',
                    'why' => 'Married people often need more life cover to protect their spouse',
                    'link' => '/profile',
                ],
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Helps calculate how much your family would need to maintain their lifestyle',
                    'link' => '/profile',
                ],
                'occupation' => [
                    'label' => 'Your occupation',
                    'why' => 'Your job affects insurance premiums and income protection eligibility',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'family_members' => [
                    'label' => 'Your children or dependants',
                    'why' => 'Dependants need financial protection if something happens to you',
                    'link' => '/profile',
                ],
                'mortgages' => [
                    'label' => 'Your mortgage details',
                    'why' => 'Mortgage debt is often the largest protection need - should be covered by life insurance',
                    'link' => '/net-worth',
                ],
                'liabilities' => [
                    'label' => 'Your other debts and loans',
                    'why' => 'All debts should be considered when calculating protection needs',
                    'link' => '/net-worth',
                ],
            ],
        ],

        'savings' => [
            'description' => 'Savings analysis tracks your emergency fund and tax-efficient savings allowances.',
            'fields' => [
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Calculates how many months of expenses your emergency fund covers',
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Helps determine appropriate savings targets and tax-free allowances',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'savings_accounts' => [
                    'label' => 'Your savings accounts',
                    'why' => 'Tracks your cash savings, tax-free Individual Savings Account usage, and emergency fund',
                    'link' => '/savings',
                ],
            ],
        ],

        'investment' => [
            'description' => 'Investment analysis helps you understand your portfolio allocation and tax efficiency.',
            'fields' => [
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Your age affects recommended asset allocation - younger investors can typically take more risk',
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Income affects your tax band and the tax efficiency of different investment wrappers',
                    'link' => '/profile',
                ],
                'target_retirement_age' => [
                    'label' => 'When you want to retire',
                    'why' => 'Your investment timeline affects how much risk you should take',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'investment_accounts' => [
                    'label' => 'Your investment accounts',
                    'why' => 'Analyse your portfolio, track performance, and optimise asset allocation',
                    'link' => '/net-worth',
                ],
            ],
        ],

        'retirement' => [
            'description' => 'Retirement planning projects your future income and helps you prepare for when you stop working.',
            'fields' => [
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Calculates how many years until you can access your pension',
                    'link' => '/profile',
                ],
                'target_retirement_age' => [
                    'label' => 'When you want to retire',
                    'why' => 'Projects how much you need to save and what income you will have',
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your current income',
                    'why' => 'Helps calculate pension contributions and your income replacement ratio',
                    'link' => '/profile',
                ],
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Estimates how much income you will need in retirement',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'dc_pensions' => [
                    'label' => 'Your money purchase pensions',
                    'why' => 'Workplace pensions, SIPPs, and personal pensions with a pot value that you can draw from flexibly in retirement',
                    'link' => '/net-worth',
                ],
                'db_pensions' => [
                    'label' => 'Any final salary or career average pensions',
                    'why' => 'Add these if you have a defined benefit scheme that pays a guaranteed income based on your salary and years of service',
                    'link' => '/net-worth',
                ],
                'state_pension' => [
                    'label' => 'Your State Pension forecast',
                    'why' => 'Most people receive State Pension from age 66-68 - add your forecast for accurate projections',
                    'link' => '/net-worth',
                ],
            ],
        ],

        'estate' => [
            'description' => 'Estate planning helps you understand inheritance tax and pass on wealth efficiently.',
            'fields' => [
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Used for life expectancy and estate planning timelines',
                    'link' => '/profile',
                ],
                'domicile_status' => [
                    'label' => 'Your domicile status',
                    'why' => 'Determines which inheritance tax rules apply to your estate',
                    'link' => '/profile',
                ],
                'marital_status' => [
                    'label' => 'Your marital status',
                    'why' => 'Married couples can pass assets tax-free to each other (spouse exemption)',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'properties' => [
                    'label' => 'Your properties',
                    'why' => 'Properties count towards your estate value for inheritance tax',
                    'link' => '/net-worth',
                ],
                'investment_accounts' => [
                    'label' => 'Your investments',
                    'why' => 'Investment assets form part of your taxable estate',
                    'link' => '/net-worth',
                ],
                'spouse' => [
                    'label' => 'Your spouse details',
                    'why' => 'Spouse exemption and transferable allowances can significantly reduce inheritance tax',
                    'link' => '/profile',
                ],
                'family_members' => [
                    'label' => 'Your beneficiaries',
                    'why' => 'Leaving your home to direct descendants can unlock the residence nil-rate band',
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
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'properties' => [
                    'label' => 'Your properties',
                    'why' => 'Property is often the largest component of net worth',
                    'link' => '/net-worth',
                ],
                'savings_accounts' => [
                    'label' => 'Your savings',
                    'why' => 'Cash savings contribute to your liquid net worth',
                    'link' => '/savings',
                ],
                'investment_accounts' => [
                    'label' => 'Your investments',
                    'why' => 'Investment portfolios are a key wealth-building asset',
                    'link' => '/net-worth',
                ],
                'dc_pensions' => [
                    'label' => 'Your money purchase pensions',
                    'why' => 'Workplace pensions, SIPPs, and personal pensions are part of your total wealth',
                    'link' => '/net-worth',
                ],
                'mortgages' => [
                    'label' => 'Your mortgages',
                    'why' => 'Mortgage debt reduces your net worth',
                    'link' => '/net-worth',
                ],
                'liabilities' => [
                    'label' => 'Your debts and loans',
                    'why' => 'All debts are subtracted from your assets to calculate net worth',
                    'link' => '/net-worth',
                ],
            ],
        ],

        'trusts' => [
            'description' => 'Trusts help you manage and protect assets for beneficiaries while potentially reducing inheritance tax.',
            'fields' => [
                'marital_status' => [
                    'label' => 'Your marital status',
                    'why' => 'Married couples often use trusts together for estate planning',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'trusts' => [
                    'label' => 'Your trusts',
                    'why' => 'Track trust details including type, trustees, beneficiaries, and assets held',
                    'link' => '/trusts',
                ],
                'family_members' => [
                    'label' => 'Your beneficiaries',
                    'why' => 'Family members are often trust beneficiaries - add them to track distributions',
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
                    'link' => '/net-worth',
                ],
                'mortgages' => [
                    'label' => 'Your mortgages',
                    'why' => 'Link mortgages to properties to calculate equity and monthly costs',
                    'link' => '/net-worth',
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
                    'link' => '/net-worth',
                ],
                'liabilities' => [
                    'label' => 'Your other debts',
                    'why' => 'Include credit cards, loans, overdrafts, and hire purchase agreements',
                    'link' => '/net-worth',
                ],
            ],
        ],

        'business_interests' => [
            'description' => 'Track your business ownership including shares, partnerships, and sole trader businesses.',
            'fields' => [
                'occupation' => [
                    'label' => 'Your occupation',
                    'why' => 'Your role in the business affects tax treatment and exit planning',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'business_interests' => [
                    'label' => 'Your business interests',
                    'why' => 'Add business details including type, ownership percentage, valuation, and exit plans',
                    'link' => '/net-worth/business-interests',
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
                    'link' => '/profile',
                ],
                'date_of_birth' => [
                    'label' => 'Your date of birth',
                    'why' => 'Essential for retirement planning, life expectancy, and insurance calculations',
                    'link' => '/profile',
                ],
                'annual_employment_income' => [
                    'label' => 'Your annual income',
                    'why' => 'Used for tax calculations, protection needs, and savings targets',
                    'link' => '/profile',
                ],
                'monthly_expenditure' => [
                    'label' => 'Your monthly spending',
                    'why' => 'Helps calculate emergency fund needs and retirement income requirements',
                    'link' => '/profile',
                ],
                'marital_status' => [
                    'label' => 'Your marital status',
                    'why' => 'Affects tax allowances, estate planning, and protection needs',
                    'link' => '/profile',
                ],
                'occupation' => [
                    'label' => 'Your occupation',
                    'why' => 'Affects insurance premiums and income protection eligibility',
                    'link' => '/profile',
                ],
                'target_retirement_age' => [
                    'label' => 'Your target retirement age',
                    'why' => 'Determines your investment timeline and pension access strategy',
                    'link' => '/profile',
                ],
                'domicile_status' => [
                    'label' => 'Your domicile status',
                    'why' => 'Determines which tax rules apply to your worldwide assets',
                    'link' => '/profile',
                ],
            ],
            'relationships' => [
                'family_members' => [
                    'label' => 'Your family members',
                    'why' => 'Add spouse, children, and dependants for protection and estate planning',
                    'link' => '/profile',
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
