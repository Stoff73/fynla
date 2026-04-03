<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\OnboardingProgress;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    public function __construct(
        private EstateOnboardingFlow $estateFlow,
        private \App\Services\TaxConfigService $taxConfig,
        private readonly \App\Services\Cache\CacheInvalidationService $cacheInvalidation
    ) {}

    /**
     * Get the onboarding status for a user
     */
    public function getOnboardingStatus(int $userId): array
    {
        $user = User::findOrFail($userId);

        $skippedSteps = $user->onboarding_skipped_steps ?? [];

        $status = [
            'onboarding_completed' => $user->onboarding_completed,
            'focus_area' => $user->life_stage,
            'current_step' => $user->onboarding_current_step,
            'skipped_steps' => $skippedSteps,
            'has_skipped_steps' => ! empty($skippedSteps),
            'skipped_steps_count' => count($skippedSteps),
            'fully_completed' => $user->onboarding_completed && empty($skippedSteps),
            'started_at' => $user->onboarding_started_at?->toISOString(),
            'completed_at' => $user->onboarding_completed_at?->toISOString(),
            'progress_percentage' => 0,
            'total_steps' => 0,
            'completed_steps' => 0,
            'onboarding_mode' => $user->onboarding_mode,
            'asset_flags' => $user->onboarding_asset_flags,
        ];

        if ($user->life_stage) {
            $progress = $this->calculateProgress($userId);
            $status['progress_percentage'] = $progress['percentage'];
            $status['total_steps'] = $progress['total'];
            $status['completed_steps'] = $progress['completed'];
        }

        return $status;
    }

    /**
     * Set the focus area for user's onboarding
     */
    public function setFocusArea(int $userId, string $focusArea): User
    {
        $user = User::findOrFail($userId);

        $user->update([
            'life_stage' => $focusArea,
            'onboarding_focus_area' => $focusArea,
            'onboarding_started_at' => $user->onboarding_started_at ?? Carbon::now(),
            'onboarding_current_step' => $this->getFirstStep($focusArea),
        ]);

        return $user->fresh();
    }

    /**
     * Get the first step for a focus area
     */
    private function getFirstStep(string $focusArea): string
    {
        if ($focusArea === 'estate') {
            $steps = $this->estateFlow->getSteps();
            $firstStep = array_key_first($steps);

            return $firstStep;
        }

        // Future: Add other focus areas
        return 'personal_info';
    }

    /**
     * Save progress for a specific step
     */
    public function saveStepProgress(int $userId, string $stepName, array $data): OnboardingProgress
    {
        $user = User::findOrFail($userId);

        if (! $user->life_stage) {
            throw new \Exception('Life stage not set');
        }

        // Process step-specific data to save to actual database tables
        $this->processStepData($userId, $stepName, $data);

        // Find or create progress record for this step
        $progress = OnboardingProgress::updateOrCreate(
            [
                'user_id' => $userId,
                'focus_area' => $user->life_stage,
                'step_name' => $stepName,
            ],
            [
                'step_data' => $data,
                'completed' => true,
                'completed_at' => Carbon::now(),
            ]
        );

        // Update current step on user
        $nextStep = $this->getNextStep($userId, $stepName);
        $user->update([
            'onboarding_current_step' => $nextStep ?? $stepName,
        ]);

        return $progress;
    }

    /**
     * Process step-specific data and save to proper database tables
     */
    protected function processStepData(int $userId, string $stepName, array $data): void
    {
        switch ($stepName) {
            case 'personal_info':
                $this->processPersonalInfo($userId, $data);
                break;

            case 'income':
                $this->processIncomeInfo($userId, $data);
                break;

            case 'expenditure':
                $this->processExpenditureInfo($userId, $data);
                break;

            case 'domicile_info':
                $this->processDomicileInfo($userId, $data);
                break;

            case 'assets':
                $this->processAssets($userId, $data);
                break;

            case 'liabilities':
                $this->processLiabilities($userId, $data);
                break;

            case 'protection_policies':
                $this->processProtectionPolicies($userId, $data);
                break;

            case 'family_info':
                $this->processFamilyInfo($userId, $data);
                break;

            case 'will_info':
                $this->processWillInfo($userId, $data);
                break;

            case 'quick_assets':
                $this->processQuickAssets($userId, $data);
                break;
        }
    }

    /**
     * Process personal information and update user record
     */
    protected function processPersonalInfo(int $userId, array $data): void
    {
        $user = User::findOrFail($userId);

        // Update user personal information fields
        $updateData = [
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'marital_status' => $data['marital_status'] ?? null,
            'national_insurance_number' => $data['national_insurance_number'] ?? null,
            'address_line_1' => $data['address_line_1'] ?? null,
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'] ?? null,
            'county' => $data['county'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'phone' => $data['phone'] ?? null,
        ];

        // Only include health/lifestyle fields if a valid value was selected
        if (! empty($data['health_status'])) {
            $updateData['health_status'] = $data['health_status'];
        }
        if (! empty($data['smoking_status'])) {
            $updateData['smoking_status'] = $data['smoking_status'];
        }
        if (! empty($data['education_level'])) {
            $updateData['education_level'] = $data['education_level'];
        }

        $user->update($updateData);
    }

    /**
     * Process domicile information and update user record
     */
    protected function processDomicileInfo(int $userId, array $data): void
    {
        $user = User::findOrFail($userId);

        // Update user domicile fields
        $updateData = [
            'domicile_status' => $data['domicile_status'] ?? null,
            'country_of_birth' => $data['country_of_birth'] ?? null,
        ];

        // Only update these fields if non-UK domiciled
        if (isset($data['domicile_status']) && $data['domicile_status'] === 'non_uk_domiciled') {
            $updateData['uk_arrival_date'] = $data['uk_arrival_date'] ?? null;
            $updateData['years_uk_resident'] = $data['years_uk_resident'] ?? null;
            $updateData['deemed_domicile_date'] = $data['deemed_domicile_date'] ?? null;
        }

        $user->update($updateData);
    }

    /**
     * Process family information and save to family_members table
     */
    protected function processFamilyInfo(int $userId, array $data): void
    {
        $user = User::findOrFail($userId);

        // Save charitable bequest preference
        if (isset($data['charitable_bequest'])) {
            $user->update([
                'charitable_bequest' => $data['charitable_bequest'],
            ]);
        }

        if (! isset($data['family_members']) || ! is_array($data['family_members'])) {
            return;
        }

        // Get existing family members added during onboarding
        $existingMembers = \App\Models\FamilyMember::where('user_id', $userId)
            ->whereNotNull('date_of_birth')
            ->get()
            ->keyBy('name');

        foreach ($data['family_members'] as $memberData) {
            // Special handling for spouse with email - attempt account linking
            if ($memberData['relationship'] === 'spouse' && ! empty($memberData['email'])) {
                $this->handleSpouseLinking($user, $memberData);

                continue; // Skip normal family member creation as it's handled by spouse linking
            }

            // Check if this member already exists (by name)
            $existingMember = $existingMembers->get($memberData['name']);

            if ($existingMember) {
                // Update existing member
                $existingMember->update([
                    'relationship' => $memberData['relationship'],
                    'date_of_birth' => $memberData['date_of_birth'],
                    'is_dependent' => $memberData['is_dependent'] ?? false,
                ]);
            } else {
                // Create new family member
                \App\Models\FamilyMember::create([
                    'user_id' => $userId,
                    'name' => $memberData['name'],
                    'relationship' => $memberData['relationship'],
                    'date_of_birth' => $memberData['date_of_birth'],
                    'is_dependent' => $memberData['is_dependent'] ?? false,
                ]);
            }
        }
    }

    /**
     * Handle spouse account linking during onboarding
     */
    protected function handleSpouseLinking(User $user, array $spouseData): void
    {
        $spouseEmail = strtolower(trim($spouseData['email']));

        // Check if spouse account exists
        $spouseAccount = User::where('email', $spouseEmail)->first();

        if ($spouseAccount) {
            // Account exists - link them
            if ($spouseAccount->id === $user->id) {
                // Can't link to self
                return;
            }

            if ($user->spouse_id === $spouseAccount->id) {
                // Already linked
                return;
            }

            if ($spouseAccount->spouse_id && $spouseAccount->spouse_id !== $user->id) {
                // Spouse already linked to someone else
                return;
            }

            DB::transaction(function () use ($user, $spouseAccount, $spouseData) {
                // Lock spouse row to prevent concurrent linking by another user
                $spouseAccount = User::lockForUpdate()->find($spouseAccount->id);
                if ($spouseAccount->spouse_id && $spouseAccount->spouse_id !== $user->id) {
                    return;
                }

                // Link the accounts bidirectionally
                $user->update([
                    'spouse_id' => $spouseAccount->id,
                    'marital_status' => 'married',
                ]);

                $spouseAccount->update([
                    'spouse_id' => $user->id,
                    'marital_status' => 'married',
                ]);

                $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $spouseAccount->id);

                // Create bidirectional spouse data sharing permissions
                \App\Models\SpousePermission::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'spouse_id' => $spouseAccount->id,
                    ],
                    [
                        'status' => 'accepted',
                        'responded_at' => now(),
                    ]
                );

                \App\Models\SpousePermission::updateOrCreate(
                    [
                        'user_id' => $spouseAccount->id,
                        'spouse_id' => $user->id,
                    ],
                    [
                        'status' => 'accepted',
                        'responded_at' => now(),
                    ]
                );

                // Create family member record for the current user
                \App\Models\FamilyMember::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'relationship' => 'spouse',
                    ],
                    [
                        'name' => $spouseAccount->name,
                        'linked_user_id' => $spouseAccount->id,
                        'date_of_birth' => $spouseData['date_of_birth'] ?? $spouseAccount->date_of_birth,
                        'is_dependent' => false,
                    ]
                );

                // Create reciprocal family member record for spouse
                \App\Models\FamilyMember::updateOrCreate(
                    [
                        'user_id' => $spouseAccount->id,
                        'relationship' => 'spouse',
                    ],
                    [
                        'name' => $user->name,
                        'linked_user_id' => $user->id,
                        'date_of_birth' => $user->date_of_birth,
                        'is_dependent' => false,
                    ]
                );
            });
        } else {
            // Account doesn't exist yet - just create family member record
            \App\Models\FamilyMember::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'relationship' => 'spouse',
                ],
                [
                    'name' => $spouseData['name'],
                    'date_of_birth' => $spouseData['date_of_birth'],
                    'is_dependent' => false,
                ]
            );
        }
    }

    /**
     * Process will information and save to wills table
     */
    protected function processWillInfo(int $userId, array $data): void
    {
        $user = User::findOrFail($userId);

        // Determine has_will value: treat null as false (no will)
        $hasWill = isset($data['has_will']) && $data['has_will'] === true;

        // Create or update will record
        $willData = [
            'user_id' => $userId,
            'has_will' => $hasWill,
        ];

        // Add last reviewed date if will exists and date is provided
        if ($hasWill && ! empty($data['will_last_updated'])) {
            $willData['will_last_updated'] = $data['will_last_updated'];
        }

        // Add executor name if provided
        if ($hasWill && ! empty($data['executor_name'])) {
            $willData['executor_name'] = $data['executor_name'];
        }

        // Use updateOrCreate to handle both new and existing records
        \App\Models\Estate\Will::updateOrCreate(
            ['user_id' => $userId],
            $willData
        );
    }

    /**
     * Process income information and update user record
     */
    protected function processIncomeInfo(int $userId, array $data): void
    {
        $user = User::findOrFail($userId);

        // Update user income and employment fields
        $user->update([
            'occupation' => $data['occupation'] ?? null,
            'employer' => $data['employer'] ?? null,
            'industry' => $data['industry'] ?? null,
            'employment_status' => $data['employment_status'] ?? null,
            'target_retirement_age' => $data['target_retirement_age'] ?? null,
            'retirement_date' => $data['retirement_date'] ?? null,
            'annual_employment_income' => $data['annual_employment_income'] ?? 0,
            'annual_self_employment_income' => $data['annual_self_employment_income'] ?? 0,
            'annual_dividend_income' => $data['annual_dividend_income'] ?? 0,
            'annual_interest_income' => $data['annual_interest_income'] ?? 0,
            'annual_other_income' => $data['annual_other_income'] ?? 0,
            'is_registered_blind' => $data['is_registered_blind'] ?? false,
        ]);

        // Update or create retirement profile if retirement age is provided
        if (isset($data['target_retirement_age'])) {
            $currentAge = $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->age : 30;

            \App\Models\RetirementProfile::updateOrCreate(
                ['user_id' => $userId],
                [
                    'current_age' => $currentAge,
                    'target_retirement_age' => $data['target_retirement_age'],
                ]
            );
        }

        // If user is retired, calculate their retirement age from retirement date
        if ($data['employment_status'] === 'retired' && isset($data['retirement_date']) && $user->date_of_birth) {
            $birthDate = \Carbon\Carbon::parse($user->date_of_birth);
            $retirementDate = \Carbon\Carbon::parse($data['retirement_date']);
            $retirementAge = $retirementDate->diffInYears($birthDate);
            $currentAge = \Carbon\Carbon::now()->diffInYears($birthDate);

            \App\Models\RetirementProfile::updateOrCreate(
                ['user_id' => $userId],
                [
                    'current_age' => $currentAge,
                    'target_retirement_age' => $retirementAge,
                ]
            );
        }
    }

    /**
     * Process expenditure information and update user record
     */
    protected function processExpenditureInfo(int $userId, array $data): void
    {
        $user = User::findOrFail($userId);

        // Check if this is separate mode data (has userData and spouseData keys)
        if (isset($data['userData']) && isset($data['spouseData'])) {
            // Separate mode: Update current user with userData
            $userData = $data['userData'];
            $user->update([
                'food_groceries' => $userData['food_groceries'] ?? 0,
                'transport_fuel' => $userData['transport_fuel'] ?? 0,
                'healthcare_medical' => $userData['healthcare_medical'] ?? 0,
                'insurance' => $userData['insurance'] ?? 0,
                'mobile_phones' => $userData['mobile_phones'] ?? 0,
                'internet_tv' => $userData['internet_tv'] ?? 0,
                'subscriptions' => $userData['subscriptions'] ?? 0,
                'clothing_personal_care' => $userData['clothing_personal_care'] ?? 0,
                'entertainment_dining' => $userData['entertainment_dining'] ?? 0,
                'holidays_travel' => $userData['holidays_travel'] ?? 0,
                'pets' => $userData['pets'] ?? 0,
                'childcare' => $userData['childcare'] ?? 0,
                'school_fees' => $userData['school_fees'] ?? 0,
                'children_activities' => $userData['children_activities'] ?? 0,
                'other_expenditure' => $userData['other_expenditure'] ?? 0,
                'monthly_expenditure' => $userData['monthly_expenditure'] ?? 0,
                'annual_expenditure' => $userData['annual_expenditure'] ?? 0,
                'expenditure_entry_mode' => $userData['expenditure_entry_mode'] ?? 'category',
            ]);

            // Update spouse with spouseData
            if ($user->spouse_id && $user->spouse) {
                $spouse = $user->spouse;
                if ($spouse) {
                    $spouseData = $data['spouseData'];
                    $spouse->update([
                        'food_groceries' => $spouseData['food_groceries'] ?? 0,
                        'transport_fuel' => $spouseData['transport_fuel'] ?? 0,
                        'healthcare_medical' => $spouseData['healthcare_medical'] ?? 0,
                        'insurance' => $spouseData['insurance'] ?? 0,
                        'mobile_phones' => $spouseData['mobile_phones'] ?? 0,
                        'internet_tv' => $spouseData['internet_tv'] ?? 0,
                        'subscriptions' => $spouseData['subscriptions'] ?? 0,
                        'clothing_personal_care' => $spouseData['clothing_personal_care'] ?? 0,
                        'entertainment_dining' => $spouseData['entertainment_dining'] ?? 0,
                        'holidays_travel' => $spouseData['holidays_travel'] ?? 0,
                        'pets' => $spouseData['pets'] ?? 0,
                        'childcare' => $spouseData['childcare'] ?? 0,
                        'school_fees' => $spouseData['school_fees'] ?? 0,
                        'children_activities' => $spouseData['children_activities'] ?? 0,
                        'other_expenditure' => $spouseData['other_expenditure'] ?? 0,
                        'monthly_expenditure' => $spouseData['monthly_expenditure'] ?? 0,
                        'annual_expenditure' => $spouseData['annual_expenditure'] ?? 0,
                        'expenditure_entry_mode' => $spouseData['expenditure_entry_mode'] ?? 'category',
                    ]);
                }
            }
        } else {
            // Joint mode or single user
            // Check if user has a spouse - if so, apply 50/50 split
            $isJointMode = $user->spouse_id !== null;
            $divisor = $isJointMode ? 2 : 1;

            // Calculate halved values for joint mode, full values for single user
            $expenditureData = [
                'food_groceries' => ($data['food_groceries'] ?? 0) / $divisor,
                'transport_fuel' => ($data['transport_fuel'] ?? 0) / $divisor,
                'healthcare_medical' => ($data['healthcare_medical'] ?? 0) / $divisor,
                'insurance' => ($data['insurance'] ?? 0) / $divisor,
                'mobile_phones' => ($data['mobile_phones'] ?? 0) / $divisor,
                'internet_tv' => ($data['internet_tv'] ?? 0) / $divisor,
                'subscriptions' => ($data['subscriptions'] ?? 0) / $divisor,
                'clothing_personal_care' => ($data['clothing_personal_care'] ?? 0) / $divisor,
                'entertainment_dining' => ($data['entertainment_dining'] ?? 0) / $divisor,
                'holidays_travel' => ($data['holidays_travel'] ?? 0) / $divisor,
                'pets' => ($data['pets'] ?? 0) / $divisor,
                'childcare' => ($data['childcare'] ?? 0) / $divisor,
                'school_fees' => ($data['school_fees'] ?? 0) / $divisor,
                'school_lunches' => ($data['school_lunches'] ?? 0) / $divisor,
                'school_extras' => ($data['school_extras'] ?? 0) / $divisor,
                'university_fees' => ($data['university_fees'] ?? 0) / $divisor,
                'children_activities' => ($data['children_activities'] ?? 0) / $divisor,
                'gifts_charity' => ($data['gifts_charity'] ?? 0) / $divisor,
                'regular_savings' => ($data['regular_savings'] ?? 0) / $divisor,
                'other_expenditure' => ($data['other_expenditure'] ?? 0) / $divisor,
                'monthly_expenditure' => ($data['monthly_expenditure'] ?? 0) / $divisor,
                'annual_expenditure' => ($data['annual_expenditure'] ?? 0) / $divisor,
                'expenditure_entry_mode' => $data['expenditure_entry_mode'] ?? 'category',
                'expenditure_sharing_mode' => 'joint',
            ];

            $user->update($expenditureData);

            // For joint/50/50 mode, also update spouse with the same halved expenses
            // Each account now stores their 50% share of the household total
            if ($isJointMode && $user->spouse) {
                $user->spouse->update($expenditureData);
            }
        }
    }

    /**
     * Process assets information and save to properties table
     */
    protected function processAssets(int $userId, array $data): void
    {
        // Process Properties
        if (isset($data['properties']) && is_array($data['properties'])) {
            $totalMonthlyRentalIncome = 0;

            foreach ($data['properties'] as $propertyData) {
                $monthlyRental = $propertyData['monthly_rental_income'] ?? 0;

                // Create property record
                $property = \App\Models\Property::create([
                    'user_id' => $userId,
                    'property_type' => $propertyData['property_type'],
                    'ownership_type' => $propertyData['ownership_type'] ?? 'individual',
                    'address_line_1' => $propertyData['address_line_1'],
                    'address_line_2' => $propertyData['address_line_2'] ?? null,
                    'city' => $propertyData['city'] ?? null,
                    'postcode' => $propertyData['postcode'] ?? null,
                    'country' => $propertyData['country'] ?? 'United Kingdom',
                    'current_value' => $propertyData['current_value'],
                    'outstanding_mortgage' => $propertyData['outstanding_mortgage'] ?? 0,
                    'monthly_rental_income' => $monthlyRental,
                    'annual_rental_income' => $monthlyRental * 12,
                ]);

                // Accumulate rental income for updating user's total rental income
                if ($monthlyRental > 0) {
                    $totalMonthlyRentalIncome += $monthlyRental;
                }

                // If property has a mortgage, create a mortgage record linked to this property
                if (isset($propertyData['outstanding_mortgage']) && $propertyData['outstanding_mortgage'] > 0) {
                    \App\Models\Mortgage::create([
                        'property_id' => $property->id,
                        'user_id' => $userId,
                        'lender_name' => 'Mortgage Provider', // Default name from onboarding
                        'mortgage_type' => 'repayment', // Default to repayment
                        'original_loan_amount' => $propertyData['outstanding_mortgage'], // Use current balance as original
                        'outstanding_balance' => $propertyData['outstanding_mortgage'],
                        'interest_rate' => 0.0350, // Default 3.5% if not provided
                        'rate_type' => 'fixed',
                        'monthly_payment' => $this->calculateMortgagePayment(
                            $propertyData['outstanding_mortgage'],
                            0.0350,
                            25
                        ),
                        'start_date' => now()->subYears(5), // Default 5 years ago
                        'maturity_date' => now()->addYears(20), // Default 20 years remaining
                        'remaining_term_months' => 240, // 20 years * 12 months
                    ]);
                }
            }

            // Update user's rental income if any buy-to-let properties were added
            if ($totalMonthlyRentalIncome > 0) {
                $user = User::find($userId);
                $user->update([
                    'annual_rental_income' => $totalMonthlyRentalIncome * 12,
                ]);
            }
        }

        // Process Investment Accounts
        if (isset($data['investments']) && is_array($data['investments'])) {
            foreach ($data['investments'] as $investmentData) {
                // Map frontend account types to database enum values
                $accountTypeMap = [
                    'stocks_shares_isa' => 'isa',
                    'gia' => 'gia',
                    'offshore_bond' => 'offshore_bond',
                    'other' => 'gia', // Default 'other' to GIA
                ];

                $accountType = $accountTypeMap[$investmentData['account_type']] ?? 'gia';
                $ownershipType = $investmentData['ownership_type'] ?? 'individual';

                // IMPORTANT: For joint ownership, divide values by 2 to store each user's share
                // This ensures consistency with properties and all other joint assets
                $currentValue = $investmentData['current_value'];
                $isaAllowanceUsed = $investmentData['isa_allowance_used'] ?? 0;
                $ownershipPercentage = 100.00;

                // Per CLAUDE.md Rule #7: joint assets use a SINGLE record at full value
                // with joint_owner_id and ownership_percentage. Never divide values.
                $jointOwnerId = null;
                if ($ownershipType === 'joint') {
                    $ownershipPercentage = 50.00;
                    $jointOwnerId = $investmentData['joint_owner_id']
                        ?? \App\Models\User::find($userId)?->familyMembers()->where('relationship', 'spouse')->first()?->linked_user_id;
                }

                \App\Models\Investment\InvestmentAccount::create([
                    'user_id' => $userId,
                    'provider' => $investmentData['institution'],
                    'account_type' => $accountType,
                    'country' => $investmentData['country'] ?? 'United Kingdom',
                    'current_value' => $currentValue,
                    'ownership_type' => $ownershipType,
                    'ownership_percentage' => $ownershipPercentage,
                    'joint_owner_id' => $jointOwnerId,
                    'isa_subscription_current_year' => $isaAllowanceUsed,
                    'tax_year' => $this->taxConfig->getTaxYear(),
                    'isa_type' => $accountType === 'isa' ? 'stocks_and_shares' : null,
                ]);
            }
        }

        // Process Cash/Savings Accounts
        if (isset($data['cash']) && is_array($data['cash'])) {
            foreach ($data['cash'] as $cashData) {
                $isCashISA = $cashData['account_type'] === 'cash_isa';
                $ownershipType = $cashData['ownership_type'] ?? 'individual';

                // IMPORTANT: For joint ownership, divide values by 2 to store each user's share
                $currentBalance = $cashData['current_balance'];
                $isaAllowanceUsed = $cashData['isa_allowance_used'] ?? 0;

                // Per CLAUDE.md Rule #7: joint assets use a SINGLE record at full value
                $jointOwnerId = null;
                if ($ownershipType === 'joint') {
                    $jointOwnerId = $cashData['joint_owner_id']
                        ?? \App\Models\User::find($userId)?->familyMembers()->where('relationship', 'spouse')->first()?->linked_user_id;
                }

                \App\Models\SavingsAccount::create([
                    'user_id' => $userId,
                    'institution' => $cashData['institution'],
                    'account_type' => $cashData['account_type'],
                    'country' => $cashData['country'] ?? 'United Kingdom',
                    'current_balance' => $currentBalance,
                    'interest_rate' => isset($cashData['interest_rate']) ? $cashData['interest_rate'] / 100 : 0,
                    'ownership_type' => $ownershipType,
                    'ownership_percentage' => $ownershipType === 'joint' ? 50.00 : 100.00,
                    'joint_owner_id' => $jointOwnerId,
                    'is_isa' => $isCashISA,
                    'isa_type' => $isCashISA ? 'cash' : null,
                    'isa_subscription_year' => $isCashISA ? $this->taxConfig->getTaxYear() : null,
                    'isa_subscription_amount' => $isCashISA ? $isaAllowanceUsed : null,
                    'access_type' => $this->mapAccessType($cashData['account_type']),
                ]);
            }
        }
    }

    /**
     * Map account type to access type for savings accounts
     */
    private function mapAccessType(string $accountType): string
    {
        return match ($accountType) {
            'current_account', 'cash_isa', 'easy_access' => 'immediate',
            'notice_account' => 'notice',
            'fixed_term' => 'fixed',
            default => 'immediate',
        };
    }

    /**
     * Calculate approximate monthly mortgage payment
     */
    private function calculateMortgagePayment(float $principal, float $annualRate, int $years): float
    {
        // Prevent division by zero if years is 0
        if ($years <= 0) {
            return 0.0;
        }

        if ($annualRate == 0) {
            return $principal / ($years * 12);
        }

        $monthlyRate = $annualRate / 12;
        $numPayments = $years * 12;

        $denominator = pow(1 + $monthlyRate, $numPayments) - 1;
        if ($denominator == 0) {
            return 0.0;
        }

        $payment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $numPayments)) / $denominator;

        return round($payment, 2);
    }

    /**
     * Process liabilities information and save to liabilities table
     */
    protected function processLiabilities(int $userId, array $data): void
    {
        if (! isset($data['liabilities']) || ! is_array($data['liabilities'])) {
            return;
        }

        foreach ($data['liabilities'] as $liabilityData) {
            // Skip mortgages - they should be linked to properties and created in processAssets
            if ($liabilityData['type'] === 'mortgage') {
                continue;
            }

            // Convert interest rate from percentage to decimal (e.g., 27 -> 0.27)
            $interestRate = isset($liabilityData['interest_rate'])
                ? $liabilityData['interest_rate'] / 100
                : null;

            // Create liability record
            \App\Models\Estate\Liability::create([
                'user_id' => $userId,
                'liability_type' => $liabilityData['type'],
                'liability_name' => $liabilityData['lender'],
                'country' => $liabilityData['country'] ?? 'United Kingdom',
                'current_balance' => $liabilityData['outstanding_balance'],
                'monthly_payment' => $liabilityData['monthly_payment'] ?? null,
                'interest_rate' => $interestRate,
                'notes' => $liabilityData['purpose'] ?? null,
            ]);
        }
    }

    /**
     * Process protection policies and save to appropriate policy tables
     */
    protected function processProtectionPolicies(int $userId, array $data): void
    {
        if (! isset($data['policies']) || ! is_array($data['policies'])) {
            return;
        }

        foreach ($data['policies'] as $policyData) {
            $policyType = $policyData['policyType'];

            switch ($policyType) {
                case 'life':
                    $this->createLifeInsurancePolicy($userId, $policyData);
                    break;

                case 'criticalIllness':
                    $this->createCriticalIllnessPolicy($userId, $policyData);
                    break;

                case 'incomeProtection':
                    $this->createIncomeProtectionPolicy($userId, $policyData);
                    break;

                    // Note: disability and sicknessIllness might need their own tables
                    // For now, we'll skip them or you can add tables for these
            }
        }
    }

    /**
     * Create life insurance policy record
     */
    protected function createLifeInsurancePolicy(int $userId, array $data): void
    {
        $startDate = ! empty($data['start_date']) ? $data['start_date'] : now()->toDateString();
        $endDate = ! empty($data['end_date']) ? $data['end_date'] : null;

        // Calculate term years if end date provided or use provided term_years
        $termYears = $data['term_years'] ?? 25; // Default
        if ($endDate) {
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);
            $termYears = $start->diffInYears($end);
        }

        // Get policy type from data (use policy_type if provided, otherwise default to term)
        $policyType = $data['policy_type'] ?? 'term';

        // Build the policy data
        $policyData = [
            'user_id' => $userId,
            'policy_type' => $policyType,
            'provider' => $data['provider'],
            'policy_number' => $data['policy_number'] ?? null,
            'sum_assured' => $data['sum_assured'] ?? $data['coverage_amount'],
            'premium_amount' => $data['premium_amount'],
            'premium_frequency' => $data['premium_frequency'] === 'annual' ? 'annually' : 'monthly',
            'policy_start_date' => $startDate,
            'policy_term_years' => $termYears,
            'in_trust' => $data['in_trust'] ?? false,
            'beneficiaries' => $data['beneficiaries'] ?? null,
        ];

        // Add decreasing policy specific fields if policy type is decreasing_term
        if ($policyType === 'decreasing_term') {
            $policyData['start_value'] = $data['start_value'] ?? null;
            $policyData['decreasing_rate'] = $data['decreasing_rate'] ?? null;
        }

        \App\Models\LifeInsurancePolicy::create($policyData);
    }

    /**
     * Create critical illness policy record
     */
    protected function createCriticalIllnessPolicy(int $userId, array $data): void
    {
        $startDate = ! empty($data['start_date']) ? $data['start_date'] : now()->toDateString();
        $endDate = ! empty($data['end_date']) ? $data['end_date'] : null;

        // Calculate term years if end date provided
        $termYears = 25; // Default
        if ($endDate) {
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);
            $termYears = $start->diffInYears($end);
        }

        \App\Models\CriticalIllnessPolicy::create([
            'user_id' => $userId,
            'policy_type' => 'standalone', // Default
            'provider' => $data['provider'],
            'policy_number' => $data['policy_number'] ?? null,
            'sum_assured' => $data['coverage_amount'],
            'premium_amount' => $data['premium_amount'],
            'premium_frequency' => $data['premium_frequency'] === 'annual' ? 'annually' : 'monthly',
            'policy_start_date' => $startDate,
            'policy_term_years' => $termYears,
        ]);
    }

    /**
     * Create income protection policy record
     */
    protected function createIncomeProtectionPolicy(int $userId, array $data): void
    {
        $startDate = ! empty($data['start_date']) ? $data['start_date'] : now()->toDateString();

        \App\Models\IncomeProtectionPolicy::create([
            'user_id' => $userId,
            'provider' => $data['provider'],
            'policy_number' => $data['policy_number'] ?? null,
            'benefit_amount' => $data['coverage_amount'],
            'benefit_frequency' => 'monthly',
            'deferred_period_weeks' => $data['waiting_period_weeks'] ?? 13, // Default 13 weeks
            'benefit_period_months' => $data['benefit_period_months'] ?? null,
            'premium_amount' => $data['premium_amount'],
            'policy_start_date' => $startDate,
        ]);
    }

    /**
     * Mark a step as skipped
     */
    public function skipStep(int $userId, string $stepName): OnboardingProgress
    {
        $user = User::findOrFail($userId);

        if (! $user->life_stage) {
            throw new \Exception('Life stage not set');
        }

        // Create or update progress record
        $progress = OnboardingProgress::updateOrCreate(
            [
                'user_id' => $userId,
                'focus_area' => $user->life_stage,
                'step_name' => $stepName,
            ],
            [
                'skipped' => true,
                'skip_reason_shown' => true,
            ]
        );

        // Add to skipped steps array in user record
        $skippedSteps = $user->onboarding_skipped_steps ?? [];
        if (! in_array($stepName, $skippedSteps)) {
            $skippedSteps[] = $stepName;
        }

        // Update current step to next step
        $nextStep = $this->getNextStep($userId, $stepName);
        $user->update([
            'onboarding_skipped_steps' => $skippedSteps,
            'onboarding_current_step' => $nextStep ?? $stepName,
        ]);

        return $progress;
    }

    /**
     * Skip all remaining steps and complete onboarding
     */
    public function skipToDashboard(int $userId): User
    {
        $user = User::findOrFail($userId);

        if (! $user->life_stage) {
            throw new \Exception('Life stage not set');
        }

        $steps = $this->getOnboardingSteps($user->life_stage, $userId);
        $skippedSteps = $user->onboarding_skipped_steps ?? [];

        // Get completed step names from progress records
        $completedStepNames = OnboardingProgress::where('user_id', $userId)
            ->where('focus_area', $user->life_stage)
            ->where('completed', true)
            ->pluck('step_name')
            ->toArray();

        // Mark all uncompleted/unskipped steps as skipped (except completion step)
        foreach ($steps as $step) {
            $stepName = $step['name'] ?? $step;
            if ($stepName === 'completion') {
                continue;
            }

            $isCompleted = in_array($stepName, $completedStepNames);
            $isSkipped = in_array($stepName, $skippedSteps);

            if (! $isCompleted && ! $isSkipped) {
                $skippedSteps[] = $stepName;

                OnboardingProgress::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'focus_area' => $user->life_stage,
                        'step_name' => $stepName,
                    ],
                    [
                        'skipped' => true,
                        'skip_reason_shown' => true,
                    ]
                );
            }
        }

        $user->update([
            'onboarding_skipped_steps' => $skippedSteps,
            'onboarding_completed' => true,
            'onboarding_completed_at' => Carbon::now(),
        ]);

        return $user->fresh();
    }

    /**
     * Complete the onboarding process
     */
    public function completeOnboarding(int $userId): User
    {
        $user = User::findOrFail($userId);

        $user->update([
            'onboarding_completed' => true,
            'onboarding_completed_at' => Carbon::now(),
        ]);

        return $user->fresh();
    }

    /**
     * Complete the quick onboarding process (3-step progressive flow)
     */
    public function completeQuickOnboarding(int $userId): User
    {
        $user = User::findOrFail($userId);

        $user->update([
            'onboarding_completed' => true,
            'onboarding_completed_at' => Carbon::now(),
            'onboarding_mode' => 'quick',
        ]);

        return $user->fresh();
    }

    /**
     * Process quick assets flags from progressive onboarding step 3
     */
    protected function processQuickAssets(int $userId, array $data): void
    {
        $user = User::findOrFail($userId);

        $assetFlags = $data['asset_flags'] ?? [];

        $user->update([
            'onboarding_asset_flags' => $assetFlags,
        ]);
    }

    /**
     * Restart the onboarding process
     */
    public function restartOnboarding(int $userId): User
    {
        $user = User::findOrFail($userId);

        DB::transaction(function () use ($user) {
            // Delete all progress records
            OnboardingProgress::where('user_id', $user->id)->delete();

            // Reset user onboarding fields
            $user->update([
                'onboarding_completed' => false,
                'life_stage' => null,
                'onboarding_focus_area' => null,
                'onboarding_current_step' => null,
                'onboarding_skipped_steps' => null,
                'onboarding_started_at' => null,
                'onboarding_completed_at' => null,
            ]);
        });

        return $user->fresh();
    }

    /**
     * Calculate progress percentage for a user
     */
    public function calculateProgress(int $userId): array
    {
        $user = User::findOrFail($userId);

        if (! $user->life_stage) {
            return [
                'percentage' => 0,
                'total' => 0,
                'completed' => 0,
            ];
        }

        $steps = $this->getOnboardingSteps($user->life_stage, $userId);
        $totalSteps = count($steps);

        $completedSteps = OnboardingProgress::where('user_id', $userId)
            ->where('focus_area', $user->life_stage)
            ->where(function ($query) {
                $query->where('completed', true)
                    ->orWhere('skipped', true);
            })
            ->count();

        $percentage = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0;

        return [
            'percentage' => $percentage,
            'total' => $totalSteps,
            'completed' => $completedSteps,
        ];
    }

    /**
     * Get onboarding steps for a focus area
     */
    public function getOnboardingSteps(string $focusArea, ?int $userId = null): array
    {
        if ($focusArea === 'estate') {
            if ($userId) {
                $user = User::find($userId);
                $userData = $this->getUserDataArray($user);

                return $this->estateFlow->getFilteredSteps($userData);
            }

            return $this->estateFlow->getSteps();
        }

        // Future: Add other focus areas
        return [];
    }

    /**
     * Get next step name
     */
    public function getNextStep(int $userId, string $currentStep): ?string
    {
        $user = User::findOrFail($userId);

        if (! $user->life_stage) {
            return null;
        }

        if ($user->life_stage === 'estate') {
            $userData = $this->getUserDataArray($user);

            return $this->estateFlow->getNextStep($currentStep, $userData);
        }

        return null;
    }

    /**
     * Get previous step name
     */
    public function getPreviousStep(int $userId, string $currentStep): ?string
    {
        $user = User::findOrFail($userId);

        if (! $user->life_stage) {
            return null;
        }

        if ($user->life_stage === 'estate') {
            $userData = $this->getUserDataArray($user);

            return $this->estateFlow->getPreviousStep($currentStep, $userData);
        }

        return null;
    }

    /**
     * Check if a step should be shown based on progressive disclosure
     */
    public function shouldShowStep(int $userId, string $stepName): bool
    {
        $user = User::findOrFail($userId);

        if (! $user->life_stage) {
            return false;
        }

        if ($user->life_stage === 'estate') {
            $userData = $this->getUserDataArray($user);

            return $this->estateFlow->shouldShowStep($stepName, $userData);
        }

        return false;
    }

    /**
     * Get skip reason text for a step
     */
    public function getSkipReasonText(string $focusArea, string $stepName): ?string
    {
        if ($focusArea === 'estate') {
            return $this->estateFlow->getSkipReason($stepName);
        }

        return null;
    }

    /**
     * Get step data for a user
     */
    public function getStepData(int $userId, string $stepName): ?array
    {
        $user = User::findOrFail($userId);

        if (! $user->life_stage) {
            return null;
        }

        $progress = OnboardingProgress::where('user_id', $userId)
            ->where('focus_area', $user->life_stage)
            ->where('step_name', $stepName)
            ->first();

        // If we have saved progress, return it
        if ($progress && $progress->step_data) {
            return $progress->step_data;
        }

        // No saved progress - fall back to user's existing data for this step
        return $this->getStepDataFromUser($user, $stepName);
    }

    /**
     * Get step data from user's existing fields (fallback when no onboarding_progress record exists)
     */
    private function getStepDataFromUser(User $user, string $stepName): ?array
    {
        switch ($stepName) {
            case 'expenditure':
                // Return user's expenditure fields if any exist
                $hasExpenditureData = $user->monthly_expenditure > 0 ||
                                     $user->annual_expenditure > 0 ||
                                     $user->food_groceries > 0 ||
                                     $user->transport_fuel > 0;

                if (! $hasExpenditureData) {
                    return null;
                }

                $userData = [
                    'food_groceries' => $user->food_groceries ?? 0,
                    'transport_fuel' => $user->transport_fuel ?? 0,
                    'healthcare_medical' => $user->healthcare_medical ?? 0,
                    'insurance' => $user->insurance ?? 0,
                    'mobile_phones' => $user->mobile_phones ?? 0,
                    'internet_tv' => $user->internet_tv ?? 0,
                    'subscriptions' => $user->subscriptions ?? 0,
                    'clothing_personal_care' => $user->clothing_personal_care ?? 0,
                    'entertainment_dining' => $user->entertainment_dining ?? 0,
                    'holidays_travel' => $user->holidays_travel ?? 0,
                    'pets' => $user->pets ?? 0,
                    'childcare' => $user->childcare ?? 0,
                    'school_fees' => $user->school_fees ?? 0,
                    'school_lunches' => $user->school_lunches ?? 0,
                    'school_extras' => $user->school_extras ?? 0,
                    'university_fees' => $user->university_fees ?? 0,
                    'children_activities' => $user->children_activities ?? 0,
                    'gifts_charity' => $user->gifts_charity ?? 0,
                    'regular_savings' => $user->regular_savings ?? 0,
                    'other_expenditure' => $user->other_expenditure ?? 0,
                    'monthly_expenditure' => $user->monthly_expenditure ?? 0,
                    'annual_expenditure' => $user->annual_expenditure ?? 0,
                    'expenditure_entry_mode' => $user->expenditure_entry_mode ?? 'category',
                    'expenditure_sharing_mode' => $user->expenditure_sharing_mode ?? 'joint',
                ];

                // If user is married and has spouse, check if spouse also has expenditure data
                if ($user->spouse_id && $user->spouse) {
                    $spouse = $user->spouse;
                    if ($spouse !== null) {
                        $hasSpouseExpenditureData = ($spouse->monthly_expenditure ?? 0) > 0 ||
                                                   ($spouse->annual_expenditure ?? 0) > 0 ||
                                                   ($spouse->food_groceries ?? 0) > 0 ||
                                                   ($spouse->transport_fuel ?? 0) > 0;

                        if ($hasSpouseExpenditureData) {
                            // Both user and spouse have separate expenditure - return in separate mode format
                            // Override sharing mode to 'separate' since both have data
                            $userData['expenditure_sharing_mode'] = 'separate';

                            $spouseData = [
                                'food_groceries' => $spouse->food_groceries ?? 0,
                                'transport_fuel' => $spouse->transport_fuel ?? 0,
                                'healthcare_medical' => $spouse->healthcare_medical ?? 0,
                                'insurance' => $spouse->insurance ?? 0,
                                'mobile_phones' => $spouse->mobile_phones ?? 0,
                                'internet_tv' => $spouse->internet_tv ?? 0,
                                'subscriptions' => $spouse->subscriptions ?? 0,
                                'clothing_personal_care' => $spouse->clothing_personal_care ?? 0,
                                'entertainment_dining' => $spouse->entertainment_dining ?? 0,
                                'holidays_travel' => $spouse->holidays_travel ?? 0,
                                'pets' => $spouse->pets ?? 0,
                                'childcare' => $spouse->childcare ?? 0,
                                'school_fees' => $spouse->school_fees ?? 0,
                                'school_lunches' => $spouse->school_lunches ?? 0,
                                'school_extras' => $spouse->school_extras ?? 0,
                                'university_fees' => $spouse->university_fees ?? 0,
                                'children_activities' => $spouse->children_activities ?? 0,
                                'gifts_charity' => $spouse->gifts_charity ?? 0,
                                'regular_savings' => $spouse->regular_savings ?? 0,
                                'other_expenditure' => $spouse->other_expenditure ?? 0,
                                'monthly_expenditure' => $spouse->monthly_expenditure ?? 0,
                                'annual_expenditure' => $spouse->annual_expenditure ?? 0,
                                'expenditure_entry_mode' => $spouse->expenditure_entry_mode ?? 'category',
                                'expenditure_sharing_mode' => 'separate',
                                'name' => $spouse->name,
                            ];

                            return [
                                'userData' => $userData,
                                'spouseData' => $spouseData,
                            ];
                        }
                    }
                }

                // No spouse data or spouse has no expenditure - return just user data
                return $userData;

                // Add other step fallbacks as needed
            default:
                return null;
        }
    }

    /**
     * Convert User model to array for step logic
     */
    private function getUserDataArray(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $userData = [
            'marital_status' => $user->marital_status,
            'annual_employment_income' => $user->annual_employment_income,
            'annual_self_employment_income' => $user->annual_self_employment_income,
            'annual_rental_income' => $user->annual_rental_income,
            'annual_dividend_income' => $user->annual_dividend_income,
            'annual_interest_income' => $user->annual_interest_income,
            'annual_other_income' => $user->annual_other_income,
        ];

        // Add step data from onboarding progress
        $progressRecords = OnboardingProgress::where('user_id', $user->id)
            ->where('focus_area', $user->life_stage)
            ->get();

        foreach ($progressRecords as $progress) {
            if ($progress->step_data) {
                $userData = array_merge($userData, $progress->step_data);
            }
        }

        return $userData;
    }
}
