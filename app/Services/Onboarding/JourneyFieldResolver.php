<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Traits\StructuredLogging;

class JourneyFieldResolver
{
    use StructuredLogging;

    /**
     * Step overrides for journeys that need explicit step definitions
     * instead of the default personal-fields-collapsed behaviour.
     */
    private const JOURNEY_STEP_OVERRIDES = [
        'budgeting' => [
            ['name' => 'Personal Information', 'component' => 'SimplePersonalInfoStep', 'fields' => []],
            ['name' => 'Your Income', 'component' => 'SimpleIncomeStep', 'fields' => ['annual_employment_income']],
            ['name' => 'Your Monthly Outgoings', 'component' => 'SimpleExpenditureStep', 'fields' => ['monthly_expenditure']],
            ['name' => 'Your Savings Accounts', 'component' => 'SimpleSavingsAccountStep', 'fields' => ['savings_accounts']],
        ],
        'protection' => [
            ['name' => 'Personal Information', 'component' => 'SimplePersonalInfoStep', 'fields' => ['date_of_birth', 'marital_status', 'health_status', 'smoking_status']],
            ['name' => 'Your Income', 'component' => 'SimpleIncomeStep', 'fields' => ['annual_employment_income', 'occupation']],
            ['name' => 'Your Monthly Outgoings', 'component' => 'SimpleExpenditureStep', 'fields' => ['monthly_expenditure']],
            ['name' => 'Your Property & Mortgage', 'component' => 'SimplePropertyMortgageStep', 'fields' => ['mortgages']],
            ['name' => 'Your Family & Dependants', 'component' => 'FamilyInfoStep', 'fields' => ['family_members']],
            ['name' => 'Your Debts & Loans', 'component' => 'LiabilitiesStep', 'fields' => ['liabilities']],
            ['name' => 'Your Existing Protection', 'component' => 'ProtectionPoliciesStep', 'fields' => ['protection_policies']],
        ],
    ];

    /**
     * Multi-journey step overrides for specific combinations.
     * Key is a sorted comma-separated list of journey names.
     */
    private const MULTI_JOURNEY_STEP_OVERRIDES = [
        'budgeting,protection' => [
            ['name' => 'Personal Information', 'component' => 'SimplePersonalInfoStep', 'fields' => ['date_of_birth', 'marital_status', 'health_status', 'smoking_status']],
            ['name' => 'Your Income', 'component' => 'SimpleIncomeStep', 'fields' => ['annual_employment_income', 'occupation']],
            ['name' => 'Your Monthly Outgoings', 'component' => 'SimpleExpenditureStep', 'fields' => ['monthly_expenditure']],
            ['name' => 'Your Property & Mortgage', 'component' => 'SimplePropertyMortgageStep', 'fields' => ['mortgages']],
            ['name' => 'Your Family & Dependants', 'component' => 'FamilyInfoStep', 'fields' => ['family_members']],
            ['name' => 'Your Debts & Loans', 'component' => 'LiabilitiesStep', 'fields' => ['liabilities']],
            ['name' => 'Your Savings Accounts', 'component' => 'SimpleSavingsAccountStep', 'fields' => ['savings_accounts']],
            ['name' => 'Your Existing Protection', 'component' => 'ProtectionPoliciesStep', 'fields' => ['protection_policies']],
        ],
    ];

    private const JOURNEY_FIELDS = [
        'budgeting' => [
            'personal' => ['annual_employment_income', 'monthly_expenditure'],
            'financial' => ['savings_accounts'],
        ],
        'savings' => [
            'personal' => ['date_of_birth', 'annual_employment_income', 'monthly_expenditure'],
            'financial' => ['savings_accounts'],
        ],
        'protection' => [
            'personal' => ['date_of_birth', 'annual_employment_income', 'monthly_expenditure', 'marital_status', 'occupation', 'health_status'],
            'financial' => ['family_members', 'mortgages', 'liabilities', 'protection_policies'],
        ],
        'investment' => [
            'personal' => ['date_of_birth', 'annual_employment_income', 'target_retirement_age'],
            'financial' => ['investment_accounts'],
        ],
        'retirement' => [
            'personal' => ['date_of_birth', 'target_retirement_age', 'annual_employment_income', 'monthly_expenditure'],
            'financial' => ['dc_pensions', 'db_pensions', 'state_pension'],
        ],
        'estate' => [
            'personal' => ['date_of_birth', 'marital_status', 'domicile_status'],
            'financial' => ['properties', 'investment_accounts', 'spouse', 'family_members'],
        ],
        'family' => [
            'personal' => ['marital_status'],
            'financial' => ['family_members', 'spouse'],
        ],
        'business' => [
            'personal' => ['occupation', 'employment_status'],
            'financial' => ['business_interests'],
        ],
        'goals' => [
            'personal' => ['date_of_birth', 'annual_employment_income', 'monthly_expenditure'],
            'financial' => ['goals'],
        ],
    ];

    private const FIELD_DEFINITIONS = [
        'date_of_birth' => [
            'label' => 'Your date of birth',
            'fyn_prompt' => "First things first — when were you born? I'll use your age to pitch retirement projections, insurance terms, and tax planning at the right level.",
            'why' => [
                'protection' => 'Used to calculate life expectancy and insurance term lengths',
                'investment' => 'Your age affects recommended asset allocation — younger investors can typically take more risk',
                'retirement' => 'Calculates how many years until you can access your pension',
                'estate' => 'Used for life expectancy and estate planning timelines',
                'goals' => 'Helps project timelines and affordability for your financial goals',
                'savings' => 'Used for life expectancy and age-appropriate savings recommendations',
            ],
            'how_used' => 'Drives age-based projections across protection, investment, retirement, estate, and goal calculations.',
            'required' => true,
        ],
        'annual_employment_income' => [
            'label' => 'Your annual income',
            'fyn_prompt' => 'Roughly what do you earn a year (gross, before tax)? It drives your tax band, your pension contribution headroom, and what I think you can realistically save each month.',
            'why' => [
                'budgeting' => 'Shows your earnings and helps calculate your savings rate',
                'protection' => 'Determines how much income protection cover you need if you cannot work',
                'investment' => 'Income affects your tax band and the tax efficiency of different investment wrappers',
                'retirement' => 'Helps calculate pension contributions and your income replacement ratio',
                'goals' => 'Used to assess affordability of your financial goals',
                'savings' => 'Sets realistic savings targets and checks your annual Individual Savings Account allowance usage',
            ],
            'how_used' => 'Central to tax calculations, protection needs, savings targets, and retirement projections.',
            'required' => true,
        ],
        'monthly_expenditure' => [
            'label' => 'Your monthly spending',
            'fyn_prompt' => "And roughly how much goes out each month — rent or mortgage, bills, food, transport, the lot? A ballpark figure is fine. I'll use it to work out your savings capacity, emergency fund target, and how much income you'll need in retirement.",
            'why' => [
                'budgeting' => 'Helps track your budget and calculate how much you can save each month',
                'protection' => 'Helps calculate how much your family would need to maintain their lifestyle',
                'retirement' => 'Estimates how much income you will need in retirement',
                'goals' => 'Used to determine how much surplus income is available for goals',
                'savings' => 'Calculates your target emergency fund and monthly savings capacity',
            ],
            'how_used' => 'Calculates emergency fund needs, protection cover, and retirement income requirements.',
            'required' => true,
        ],
        'marital_status' => [
            'label' => 'Your marital status',
            'fyn_prompt' => 'Single, married, in a civil partnership, divorced, or widowed? Couples and civil partners get some meaningful tax advantages — I want to make sure we use them.',
            'why' => [
                'protection' => 'Married people often need more life cover to protect their spouse',
                'estate' => 'Married couples can pass assets tax-free to each other (spouse exemption)',
                'family' => 'Determines whether spouse details are needed for household planning',
            ],
            'how_used' => 'Affects tax allowances, estate planning, and protection needs.',
            'required' => true,
        ],
        'occupation' => [
            'label' => 'Your occupation',
            'fyn_prompt' => 'What do you do for a living? Your role affects insurance premiums and whether you qualify for income protection cover — worth capturing.',
            'why' => [
                'protection' => 'Your job affects insurance premiums and income protection eligibility',
                'business' => 'Your role affects tax treatment and business exit planning',
            ],
            'how_used' => 'Used for insurance premium estimates and business interest analysis.',
            'required' => false,
        ],
        'health_status' => [
            'label' => 'Your health status',
            'fyn_prompt' => "How's your general health? Anything an insurer would want to know about — diabetes, heart conditions, a recent surgery — affects what cover you can get and at what price. If you're in good health, just say so.",
            'why' => [
                'protection' => 'Health conditions affect insurance premiums and eligibility for cover',
            ],
            'how_used' => 'Helps estimate insurance costs and identify cover that may require medical underwriting.',
            'required' => false,
        ],
        'target_retirement_age' => [
            'label' => 'When you want to retire',
            'fyn_prompt' => 'When would you ideally like to stop working? Even a rough age is fine — we can refine it as things change.',
            'why' => [
                'investment' => 'Your investment timeline affects how much risk you should take',
                'retirement' => 'Projects how much you need to save and what income you will have',
            ],
            'how_used' => 'Determines investment horizon and pension access strategy.',
            'required' => true,
        ],
        'domicile_status' => [
            'label' => 'Your domicile status',
            'fyn_prompt' => "Where are you domiciled for tax purposes? For most people born and raised in the UK this is just UK — but if you're non-domiciled or have strong ties elsewhere, let me know. It changes how inheritance tax works for you.",
            'why' => [
                'estate' => 'Determines which inheritance tax rules apply to your estate',
            ],
            'how_used' => 'Controls which tax regime applies to your worldwide assets for inheritance tax.',
            'required' => false,
        ],
        'employment_status' => [
            'label' => 'Your employment status',
            'fyn_prompt' => "What's your employment situation? Employed, self-employed, part-time, retired, or not working at the moment? It changes which tax and pension rules apply to you.",
            'why' => [
                'business' => 'Determines whether you are self-employed, a company director, or employed — affects tax and pension options',
            ],
            'how_used' => 'Used to identify relevant business structures and tax planning opportunities.',
            'required' => false,
        ],
        'savings_accounts' => [
            'label' => 'Your savings accounts',
            'fyn_prompt' => "Tell me about your savings and current accounts — just the bank name, the balance, and whether it's a cash ISA if you know. You can list several in one go and I'll add them all at once.",
            'why' => [
                'budgeting' => 'Tracks your cash savings, emergency fund, and tax-free savings allowances',
                'savings' => 'Captures your existing savings balances so I can assess your emergency fund cover, Individual Savings Account allowance usage, and rate competitiveness',
            ],
            'how_used' => 'Monitors emergency fund coverage and Individual Savings Account usage.',
            'required' => false,
        ],
        'family_members' => [
            'label' => 'Your children or dependants',
            'fyn_prompt' => "Any children or other dependants I should know about? If yes, their first names, ages, and how they're related to you is plenty — it shapes your protection cover and estate planning.",
            'why' => [
                'protection' => 'Dependants need financial protection if something happens to you',
                'estate' => 'Leaving your home to direct descendants can unlock the residence nil-rate band',
                'family' => 'Add family members for household planning and coordination',
            ],
            'how_used' => 'Used for protection calculations, estate tax allowances, and household planning.',
            'required' => false,
        ],
        'mortgages' => [
            'label' => 'Your mortgage details',
            'fyn_prompt' => 'Got a mortgage on any property? Share the outstanding balance, the monthly payment, and the interest rate if you have them to hand — rough numbers are fine.',
            'why' => [
                'protection' => 'Mortgage debt is often the largest protection need — should be covered by life insurance',
            ],
            'how_used' => 'Factors into protection gap analysis and net worth calculations.',
            'required' => false,
        ],
        'liabilities' => [
            'label' => 'Your other debts and loans',
            'fyn_prompt' => 'Any other loans or debts on the go — credit cards, car finance, student loans, personal loans? These shape your protection cover and day-to-day budget, so it helps to have them captured.',
            'why' => [
                'protection' => 'All debts should be considered when calculating protection needs',
            ],
            'how_used' => 'Included in total debt analysis for protection cover requirements.',
            'required' => false,
        ],
        'protection_policies' => [
            'label' => 'Your existing protection policies',
            'fyn_prompt' => "Do you already have any life insurance, critical illness cover, or income protection? If yes, the type, the provider, and the cover amount is what I need. If not, just say so and we'll come back to it.",
            'why' => [
                'protection' => 'Existing cover reduces the gap between what you have and what you need',
            ],
            'how_used' => 'Compared against calculated needs to identify shortfalls.',
            'required' => false,
        ],
        'investment_accounts' => [
            'label' => 'Your investment accounts',
            'fyn_prompt' => 'What investment accounts are you running? A Stocks & Shares ISA, a General Investment Account, bonds, company share schemes — provider and current value is enough to start. List as many as you have.',
            'why' => [
                'investment' => 'Analyse your portfolio, track performance, and optimise asset allocation',
                'estate' => 'Investment assets form part of your taxable estate',
            ],
            'how_used' => 'Portfolio analysis, tax efficiency, and estate value calculations.',
            'required' => false,
        ],
        'dc_pensions' => [
            'label' => 'Your money purchase pensions',
            'fyn_prompt' => "Got any pensions with a pot value you can draw from flexibly? That's workplace auto-enrolment pots, personal pensions, or a Self-Invested Personal Pension. Tell me the provider and the current value of each one.",
            'why' => [
                'retirement' => 'Workplace pensions, SIPPs, and personal pensions with a pot value that you can draw from flexibly in retirement',
            ],
            'how_used' => 'Projects retirement income from defined contribution pension pots.',
            'required' => false,
        ],
        'db_pensions' => [
            'label' => 'Your final salary or career average pensions',
            'fyn_prompt' => "Any Defined Benefit pensions — the kind that pay a guaranteed income based on your salary and years of service? If yes, I need the scheme name and the annual income they'll pay you at retirement.",
            'why' => [
                'retirement' => 'Defined benefit schemes pay a guaranteed income based on your salary and years of service',
            ],
            'how_used' => 'Provides guaranteed retirement income in projections.',
            'required' => false,
        ],
        'state_pension' => [
            'label' => 'Your State Pension forecast',
            'fyn_prompt' => "Have you checked your State Pension forecast on GOV.UK? If so, pop the amount in — it's a key baseline for your retirement income projections. If you haven't, I can remind you to check.",
            'why' => [
                'retirement' => 'Most people receive State Pension from age 66-68 — add your forecast for accurate projections',
            ],
            'how_used' => 'Included as baseline retirement income in all projections.',
            'required' => false,
        ],
        'properties' => [
            'label' => 'Your properties',
            'fyn_prompt' => 'Do you own any property — your home, a buy-to-let, a holiday home, or somewhere abroad? I need the current estimated value and the type (main residence, second home, or buy-to-let) for each one.',
            'why' => [
                'estate' => 'Properties count towards your estate value for inheritance tax',
            ],
            'how_used' => 'Included in estate value calculations and residence nil-rate band eligibility.',
            'required' => false,
        ],
        'spouse' => [
            'label' => 'Your spouse details',
            'fyn_prompt' => "Tell me about your spouse or partner — first name, date of birth, and email address. I'll create an account and link the two of you so we can plan together.",
            'why' => [
                'estate' => 'Spouse exemption and transferable allowances can significantly reduce inheritance tax',
                'family' => 'Spouse details enable household-level financial planning',
            ],
            'how_used' => 'Enables spouse exemption calculations and joint financial analysis.',
            'required' => false,
        ],
        'business_interests' => [
            'label' => 'Your business interests',
            'fyn_prompt' => 'Do you own or have a stake in any business? Sole trade, partnership, limited company — either way, it shapes your tax position and your estate. Trade name, entity type, ownership percentage, and rough valuation for each.',
            'why' => [
                'business' => 'Track business ownership, valuation, and exit plans',
            ],
            'how_used' => 'Included in net worth and may qualify for Business Relief from inheritance tax.',
            'required' => false,
        ],
        'goals' => [
            'label' => 'Your financial goals',
            'fyn_prompt' => "What are you working towards? A house deposit, early retirement, children's university fees, a dream trip — tell me what matters to you. For each one, a short name, a rough target amount, and when you'd like to hit it by.",
            'why' => [
                'goals' => 'Track your goals with timelines, costs, and linked savings or investments',
            ],
            'how_used' => 'Provides affordability analysis and progress tracking for each goal.',
            'required' => false,
        ],
    ];

    /**
     * Canonical ordered list of the base KYC data Fyn collects from every user
     * regardless of which journey or focus they picked. The chat state machine
     * walks this list first, skipping any field whose value is already set on
     * the user record. Fields marked conditional are only asked when earlier
     * answers make them relevant (e.g. occupation only if employed).
     */
    public const BASE_DATA_STEPS = [
        'date_of_birth',
        'marital_status',
        'spouse',          // conditional: only if married or in civil partnership
        'family_members',
        'employment_status',
        'occupation',      // conditional: only if employed, self-employed, or part_time
        'annual_employment_income',
        'monthly_expenditure',
    ];

    /**
     * Return a single field definition from FIELD_DEFINITIONS, or null if
     * the key is not known. Used by the OnboardingStateMachine to pull the
     * fyn_prompt copy for each state without duplicating it in state config.
     */
    public static function getFieldDefinition(string $fieldKey): ?array
    {
        return self::FIELD_DEFINITIONS[$fieldKey] ?? null;
    }

    /**
     * Return just the fyn_prompt string for a field, falling back to the
     * label if fyn_prompt is missing. Safe default for the state machine.
     */
    public static function getFynPrompt(string $fieldKey): string
    {
        $definition = self::FIELD_DEFINITIONS[$fieldKey] ?? null;
        if ($definition === null) {
            return '';
        }

        return (string) ($definition['fyn_prompt'] ?? $definition['label'] ?? '');
    }

    public function getFieldsForJourneys(array $journeys): array
    {
        $this->validateJourneyNames($journeys);

        $personalFields = [];
        $financialFields = [];
        $seenKeys = [];

        foreach ($journeys as $journey) {
            $config = self::JOURNEY_FIELDS[$journey] ?? [];

            foreach ($config['personal'] ?? [] as $fieldKey) {
                if (! in_array($fieldKey, $seenKeys, true)) {
                    $seenKeys[] = $fieldKey;
                    $personalFields[] = $this->buildFieldEntry($fieldKey, $journeys);
                }
            }

            foreach ($config['financial'] ?? [] as $fieldKey) {
                if (! in_array($fieldKey, $seenKeys, true)) {
                    $seenKeys[] = $fieldKey;
                    $financialFields[] = $this->buildFieldEntry($fieldKey, $journeys);
                }
            }
        }

        return [
            'personal_fields' => $personalFields,
            'financial_fields' => $financialFields,
        ];
    }

    public function getStepsForJourney(string $journey): array
    {
        $this->validateJourneyNames([$journey]);

        // Use explicit step overrides if defined for this journey
        if (isset(self::JOURNEY_STEP_OVERRIDES[$journey])) {
            return self::JOURNEY_STEP_OVERRIDES[$journey];
        }

        $steps = [];
        $config = self::JOURNEY_FIELDS[$journey];

        if (! empty($config['personal'])) {
            $steps[] = [
                'name' => 'Personal Information',
                'component' => 'JourneyPersonalStep',
                'fields' => $config['personal'],
            ];
        }

        if (! empty($config['financial'])) {
            foreach ($config['financial'] as $fieldKey) {
                $definition = self::FIELD_DEFINITIONS[$fieldKey] ?? null;
                $label = $definition['label'] ?? ucfirst(str_replace('_', ' ', $fieldKey));

                $steps[] = [
                    'name' => $label,
                    'component' => 'Journey'.str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldKey))).'Step',
                    'fields' => [$fieldKey],
                ];
            }
        }

        return $steps;
    }

    public function getStepsForJourneys(array $journeys): array
    {
        $this->validateJourneyNames($journeys);

        // If only one journey and it has step overrides, use those directly
        if (count($journeys) === 1 && isset(self::JOURNEY_STEP_OVERRIDES[$journeys[0]])) {
            return self::JOURNEY_STEP_OVERRIDES[$journeys[0]];
        }

        // Check for explicit multi-journey combination overrides
        $sortedKey = $journeys;
        sort($sortedKey);
        $combinationKey = implode(',', $sortedKey);
        if (isset(self::MULTI_JOURNEY_STEP_OVERRIDES[$combinationKey])) {
            return self::MULTI_JOURNEY_STEP_OVERRIDES[$combinationKey];
        }

        $mergedPersonalFields = [];
        $financialSteps = [];
        $seenFinancialKeys = [];

        foreach ($journeys as $journey) {
            $config = self::JOURNEY_FIELDS[$journey];

            foreach ($config['personal'] ?? [] as $field) {
                if (! in_array($field, $mergedPersonalFields, true)) {
                    $mergedPersonalFields[] = $field;
                }
            }

            foreach ($config['financial'] ?? [] as $fieldKey) {
                if (! in_array($fieldKey, $seenFinancialKeys, true)) {
                    $seenFinancialKeys[] = $fieldKey;
                    $definition = self::FIELD_DEFINITIONS[$fieldKey] ?? null;
                    $label = $definition['label'] ?? ucfirst(str_replace('_', ' ', $fieldKey));

                    $financialSteps[] = [
                        'name' => $label,
                        'component' => 'Journey'.str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldKey))).'Step',
                        'fields' => [$fieldKey],
                    ];
                }
            }
        }

        $steps = [];

        if (! empty($mergedPersonalFields)) {
            $steps[] = [
                'name' => 'Personal Information',
                'component' => 'JourneyPersonalStep',
                'fields' => $mergedPersonalFields,
            ];
        }

        return array_merge($steps, $financialSteps);
    }

    public function getPreviewForJourneys(array $journeys): array
    {
        $this->validateJourneyNames($journeys);

        $fields = $this->getFieldsForJourneys($journeys);
        $personalCount = count($fields['personal_fields']);
        $financialCount = count($fields['financial_fields']);
        $totalFields = $personalCount + $financialCount;

        // Estimate ~30 seconds per field, rounded up to nearest minute
        $estimatedMinutes = max(1, (int) ceil(($totalFields * 30) / 60));

        return [
            'personal_count' => $personalCount,
            'financial_count' => $financialCount,
            'personal_fields' => $fields['personal_fields'],
            'financial_fields' => $fields['financial_fields'],
            'estimated_minutes' => $estimatedMinutes,
        ];
    }

    private function buildFieldEntry(string $fieldKey, array $selectedJourneys): array
    {
        $definition = self::FIELD_DEFINITIONS[$fieldKey] ?? null;

        if ($definition === null) {
            return [
                'key' => $fieldKey,
                'label' => ucfirst(str_replace('_', ' ', $fieldKey)),
                'why' => '',
                'required' => false,
            ];
        }

        // Combine "why" texts from all selected journeys that use this field
        $whyTexts = [];
        if (is_array($definition['why'])) {
            foreach ($selectedJourneys as $journey) {
                if (isset($definition['why'][$journey])) {
                    $whyTexts[] = $definition['why'][$journey];
                }
            }
        }

        $combinedWhy = ! empty($whyTexts) ? implode('. ', array_unique($whyTexts)) : '';

        return [
            'key' => $fieldKey,
            'label' => $definition['label'],
            'why' => $combinedWhy,
            'required' => $definition['required'],
        ];
    }

    private function validateJourneyNames(array $journeys): void
    {
        foreach ($journeys as $journey) {
            if (! in_array($journey, JourneyStateService::JOURNEYS, true)) {
                throw new \InvalidArgumentException(
                    "Invalid journey name: '{$journey}'. Valid journeys: ".implode(', ', JourneyStateService::JOURNEYS)
                );
            }
        }
    }
}
