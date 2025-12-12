<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Property;
use App\Services\Property\PropertyService;
use App\Services\Property\PropertyTaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function __construct(
        private PropertyService $propertyService,
        private PropertyTaxService $propertyTaxService
    ) {}

    /**
     * Get all properties for the authenticated user
     *
     * GET /api/properties
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $properties = Property::where('user_id', $user->id)
            ->with('mortgages')  // Load mortgages relationship for edit functionality
            ->orderBy('property_type')
            ->orderBy('created_at', 'desc')
            ->get();

        // For each joint property, fetch the joint owner's share and add it to the property data
        $properties = $properties->map(function ($property) use ($user) {
            $propertyData = $property->toArray();

            // For joint properties, include the joint owner's share so frontend can calculate full values
            if (in_array($property->ownership_type, ['joint', 'tenants_in_common']) && $property->joint_owner_id) {
                $reciprocalProperty = Property::where('user_id', $property->joint_owner_id)
                    ->where('joint_owner_id', $user->id)
                    ->where('address_line_1', $property->address_line_1)
                    ->where('postcode', $property->postcode)
                    ->with('mortgages')
                    ->first();

                if ($reciprocalProperty) {
                    $propertyData['joint_owner_share'] = [
                        'current_value' => $reciprocalProperty->current_value,
                        'purchase_price' => $reciprocalProperty->purchase_price,
                        'ownership_percentage' => $reciprocalProperty->ownership_percentage,
                    ];

                    // Include mortgage share if exists
                    if ($reciprocalProperty->mortgages && $reciprocalProperty->mortgages->count() > 0) {
                        $reciprocalMortgage = $reciprocalProperty->mortgages->first();
                        $propertyData['joint_owner_share']['mortgage'] = [
                            'outstanding_balance' => $reciprocalMortgage->outstanding_balance,
                            'original_loan_amount' => $reciprocalMortgage->original_loan_amount,
                        ];
                    }
                }
            }

            return $propertyData;
        });

        return response()->json($properties);
    }

    /**
     * Store a new property
     *
     * POST /api/properties
     */
    public function store(StorePropertyRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Set defaults for optional fields
        $validated['user_id'] = $user->id;
        $validated['household_id'] = $user->household_id;
        $validated['ownership_type'] = $validated['ownership_type'] ?? 'individual';
        $validated['ownership_percentage'] = $validated['ownership_percentage'] ?? 100.00;
        $validated['valuation_date'] = $validated['valuation_date'] ?? now();

        // Handle address field - populate address_line_1 from address if needed
        if (isset($validated['address']) && ! isset($validated['address_line_1'])) {
            $validated['address_line_1'] = $validated['address'];
        }

        // Ensure postcode is never null (database requires NOT NULL)
        if (! isset($validated['postcode']) || $validated['postcode'] === null) {
            $validated['postcode'] = '';
        }

        // Convert rental_income to monthly if provided
        if (isset($validated['rental_income']) && ! isset($validated['monthly_rental_income'])) {
            $validated['monthly_rental_income'] = $validated['rental_income'];
        }

        // For joint or tenants_in_common ownership, default to 50/50 split if not specified
        if (in_array($validated['ownership_type'], ['joint', 'tenants_in_common']) && $validated['ownership_percentage'] == 100.00) {
            $validated['ownership_percentage'] = 50.00;
        }

        // Store the USER'S SHARE in current_value (not full value)
        // For joint properties, this creates TWO records with each user's share
        $totalValue = $validated['current_value'];
        $userOwnershipPercentage = $validated['ownership_percentage'];
        $validated['current_value'] = $totalValue * ($userOwnershipPercentage / 100);

        // For joint properties, also split costs and rental income by ownership percentage
        if (in_array($validated['ownership_type'], ['joint', 'tenants_in_common'])) {
            $ownershipMultiplier = $userOwnershipPercentage / 100;

            if (isset($validated['monthly_rental_income'])) {
                $validated['monthly_rental_income'] = $validated['monthly_rental_income'] * $ownershipMultiplier;
            }
            if (isset($validated['monthly_council_tax'])) {
                $validated['monthly_council_tax'] = $validated['monthly_council_tax'] * $ownershipMultiplier;
            }
            if (isset($validated['monthly_gas'])) {
                $validated['monthly_gas'] = $validated['monthly_gas'] * $ownershipMultiplier;
            }
            if (isset($validated['monthly_electricity'])) {
                $validated['monthly_electricity'] = $validated['monthly_electricity'] * $ownershipMultiplier;
            }
            if (isset($validated['monthly_water'])) {
                $validated['monthly_water'] = $validated['monthly_water'] * $ownershipMultiplier;
            }
            if (isset($validated['monthly_building_insurance'])) {
                $validated['monthly_building_insurance'] = $validated['monthly_building_insurance'] * $ownershipMultiplier;
            }
            if (isset($validated['monthly_contents_insurance'])) {
                $validated['monthly_contents_insurance'] = $validated['monthly_contents_insurance'] * $ownershipMultiplier;
            }
            if (isset($validated['monthly_service_charge'])) {
                $validated['monthly_service_charge'] = $validated['monthly_service_charge'] * $ownershipMultiplier;
            }
            if (isset($validated['monthly_maintenance_reserve'])) {
                $validated['monthly_maintenance_reserve'] = $validated['monthly_maintenance_reserve'] * $ownershipMultiplier;
            }
            if (isset($validated['other_monthly_costs'])) {
                $validated['other_monthly_costs'] = $validated['other_monthly_costs'] * $ownershipMultiplier;
            }
        }

        $property = Property::create($validated);

        // If outstanding_mortgage provided, auto-create a basic mortgage record
        if (isset($validated['outstanding_mortgage']) && $validated['outstanding_mortgage'] > 0) {
            // Split outstanding balance based on ownership percentage
            $userMortgageBalance = $validated['outstanding_mortgage'] * ($userOwnershipPercentage / 100);

            // Split monthly payment based on ownership percentage if provided
            $userMonthlyPayment = isset($validated['mortgage_monthly_payment'])
                ? $validated['mortgage_monthly_payment'] * ($userOwnershipPercentage / 100)
                : 0.00;

            $mortgageData = [
                'property_id' => $property->id,
                'user_id' => $user->id,
                'lender_name' => $validated['mortgage_lender_name'] ?? 'To be completed',
                'mortgage_type' => $validated['mortgage_type'] ?? 'repayment',
                'repayment_percentage' => $validated['mortgage_repayment_percentage'] ?? null,
                'interest_only_percentage' => $validated['mortgage_interest_only_percentage'] ?? null,
                'original_loan_amount' => 0.00,  // Not provided in property form
                'outstanding_balance' => $userMortgageBalance,  // User's share
                'interest_rate' => $validated['mortgage_interest_rate'] ?? 0.0000,
                'rate_type' => $validated['mortgage_rate_type'] ?? 'fixed',
                'fixed_rate_percentage' => $validated['mortgage_fixed_rate_percentage'] ?? null,
                'variable_rate_percentage' => $validated['mortgage_variable_rate_percentage'] ?? null,
                'fixed_interest_rate' => $validated['mortgage_fixed_interest_rate'] ?? null,
                'variable_interest_rate' => $validated['mortgage_variable_interest_rate'] ?? null,
                'monthly_payment' => $userMonthlyPayment,
                'start_date' => $validated['mortgage_start_date'] ?? now(),
                'maturity_date' => $validated['mortgage_maturity_date'] ?? now()->addYears(25),
                'remaining_term_months' => 300,
                'ownership_type' => $validated['ownership_type'],
            ];

            // Add joint ownership fields if applicable (joint or tenants_in_common)
            if (in_array($validated['ownership_type'], ['joint', 'tenants_in_common']) && isset($validated['joint_owner_id'])) {
                $jointOwner = \App\Models\User::find($validated['joint_owner_id']);
                $mortgageData['joint_owner_id'] = $validated['joint_owner_id'];
                $mortgageData['joint_owner_name'] = $jointOwner ? $jointOwner->name : null;
            }

            \App\Models\Mortgage::create($mortgageData);
        }

        // If joint or tenants_in_common ownership, create reciprocal property for joint owner
        if (in_array($validated['ownership_type'], ['joint', 'tenants_in_common']) && isset($validated['joint_owner_id'])) {
            $totalMortgage = $validated['outstanding_mortgage'] ?? 0;
            $this->createJointProperty($property, $validated['joint_owner_id'], $validated['ownership_percentage'], $totalValue, $totalMortgage, $validated);
        }

        // Sync rental income to user table
        $this->syncUserRentalIncome($user);

        // Load mortgages relationship before returning
        $property->load('mortgages');

        return response()->json($property, 201);
    }

    /**
     * Get a single property
     *
     * GET /api/properties/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $property = Property::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['mortgages', 'household', 'trust'])
            ->firstOrFail();

        $summary = $this->propertyService->getPropertySummary($property);

        // For joint properties, include the joint owner's share INSIDE the property object
        // so it gets carried through Vuex store correctly
        if (in_array($property->ownership_type, ['joint', 'tenants_in_common']) && $property->joint_owner_id) {
            $reciprocalProperty = Property::where('user_id', $property->joint_owner_id)
                ->where('joint_owner_id', $user->id)
                ->where('address_line_1', $property->address_line_1)
                ->where('postcode', $property->postcode)
                ->with('mortgages')
                ->first();

            if ($reciprocalProperty) {
                $summary['joint_owner_share'] = [
                    'current_value' => $reciprocalProperty->current_value,
                    'purchase_price' => $reciprocalProperty->purchase_price,
                    'ownership_percentage' => $reciprocalProperty->ownership_percentage,
                ];

                // Include mortgage share if exists
                if ($reciprocalProperty->mortgages && $reciprocalProperty->mortgages->count() > 0) {
                    $reciprocalMortgage = $reciprocalProperty->mortgages->first();
                    $summary['joint_owner_share']['mortgage'] = [
                        'outstanding_balance' => $reciprocalMortgage->outstanding_balance,
                        'original_loan_amount' => $reciprocalMortgage->original_loan_amount,
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'property' => $summary,
            ],
        ]);
    }

    /**
     * Update a property
     *
     * PUT /api/properties/{id}
     */
    public function update(UpdatePropertyRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $property = Property::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validated();

        // Handle joint property updates with value splitting
        $isJointProperty = in_array($property->ownership_type, ['joint', 'tenants_in_common']) && $property->joint_owner_id;

        if ($isJointProperty) {
            $validated = $this->handleJointPropertyUpdate($property, $validated, $user);
        }

        // Update the user's property
        $property->update($validated);
        $property->load(['mortgages', 'household', 'trust']);

        // Sync rental income to user table
        $this->syncUserRentalIncome($user);

        $summary = $this->propertyService->getPropertySummary($property);

        return response()->json([
            'success' => true,
            'message' => 'Property updated successfully',
            'data' => [
                'property' => $summary,
            ],
        ]);
    }

    /**
     * Handle joint property update by splitting values between owners
     */
    private function handleJointPropertyUpdate(Property $property, array $validated, \App\Models\User $user): array
    {
        $reciprocalProperty = Property::where('user_id', $property->joint_owner_id)
            ->where('joint_owner_id', $user->id)
            ->where('address_line_1', $property->address_line_1)
            ->where('postcode', $property->postcode)
            ->first();

        if (! $reciprocalProperty) {
            return $validated;
        }

        // Capture before values for logging
        $beforeValues = $this->capturePropertyValues($property, $reciprocalProperty);

        // Get ownership percentages (allow change for tenants_in_common)
        [$userPercentage, $jointOwnerPercentage] = $this->calculateOwnershipPercentages(
            $property,
            $reciprocalProperty,
            $validated
        );

        // Split values if current_value is provided
        if (isset($validated['current_value'])) {
            $fullValue = $validated['current_value'];
            $validated = $this->splitPropertyValues($validated, $userPercentage);

            // Prepare and update reciprocal property
            $reciprocalData = $this->prepareReciprocalData($validated, $fullValue, $userPercentage, $jointOwnerPercentage);
            $reciprocalProperty->update($reciprocalData);

            // Log the joint account edit
            $this->logJointPropertyUpdate($user, $property, $beforeValues, $validated, $reciprocalData, $fullValue);
        } else {
            // No value change, just update other fields on both properties
            $reciprocalProperty->update($validated);
        }

        // Update user's percentage if tenants_in_common
        if ($property->ownership_type === 'tenants_in_common' && isset($validated['ownership_percentage'])) {
            $validated['ownership_percentage'] = $userPercentage;
        }

        // Sync rental income for joint owner
        $jointOwner = \App\Models\User::find($property->joint_owner_id);
        if ($jointOwner) {
            $this->syncUserRentalIncome($jointOwner);
        }

        return $validated;
    }

    /**
     * Capture property values before update for logging
     */
    private function capturePropertyValues(Property $property, Property $reciprocalProperty): array
    {
        return [
            'current_value' => [
                'user_share' => $property->current_value,
                'joint_owner_share' => $reciprocalProperty->current_value,
                'full_value' => $property->current_value + $reciprocalProperty->current_value,
            ],
        ];
    }

    /**
     * Calculate ownership percentages for joint property update
     */
    private function calculateOwnershipPercentages(Property $property, Property $reciprocalProperty, array $validated): array
    {
        $userPercentage = $property->ownership_percentage;
        $jointOwnerPercentage = $reciprocalProperty->ownership_percentage;

        // For tenants_in_common, allow percentage change from form
        if ($property->ownership_type === 'tenants_in_common' && isset($validated['ownership_percentage'])) {
            $userPercentage = $validated['ownership_percentage'];
            $jointOwnerPercentage = 100.00 - $userPercentage;
        }

        return [$userPercentage, $jointOwnerPercentage];
    }

    /**
     * Split property values by ownership percentage
     */
    private function splitPropertyValues(array $validated, float $userPercentage): array
    {
        $costFields = $this->getPropertyCostFields();
        $fullValue = $validated['current_value'];
        $validated['current_value'] = $fullValue * ($userPercentage / 100);

        foreach ($costFields as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = $validated[$field] * ($userPercentage / 100);
            }
        }

        return $validated;
    }

    /**
     * Get list of property cost/income fields that need splitting
     */
    private function getPropertyCostFields(): array
    {
        return [
            'monthly_rental_income',
            'monthly_council_tax',
            'monthly_gas',
            'monthly_electricity',
            'monthly_water',
            'monthly_building_insurance',
            'monthly_contents_insurance',
            'monthly_service_charge',
            'monthly_maintenance_reserve',
            'other_monthly_costs',
        ];
    }

    /**
     * Prepare reciprocal property data for joint owner
     */
    private function prepareReciprocalData(array $validated, float $fullValue, float $userPercentage, float $jointOwnerPercentage): array
    {
        $reciprocalData = $validated;
        $reciprocalData['current_value'] = $fullValue * ($jointOwnerPercentage / 100);
        $reciprocalData['ownership_percentage'] = $jointOwnerPercentage;

        // Recalculate cost fields for joint owner's percentage
        $costFields = $this->getPropertyCostFields();
        foreach ($costFields as $field) {
            if (isset($validated[$field])) {
                // Get back full value from user's split, then apply joint owner's percentage
                $fullFieldValue = $validated[$field] / ($userPercentage / 100);
                $reciprocalData[$field] = $fullFieldValue * ($jointOwnerPercentage / 100);
            }
        }

        return $reciprocalData;
    }

    /**
     * Log joint property update for audit trail
     */
    private function logJointPropertyUpdate(\App\Models\User $user, Property $property, array $beforeValues, array $validated, array $reciprocalData, float $fullValue): void
    {
        $afterValues = [
            'current_value' => [
                'user_share' => $validated['current_value'],
                'joint_owner_share' => $reciprocalData['current_value'],
                'full_value' => $fullValue,
            ],
        ];

        \App\Models\JointAccountLog::logEdit(
            $user->id,
            $property->joint_owner_id,
            $property,
            [
                'before' => $beforeValues,
                'after' => $afterValues,
                'fields_changed' => ['current_value'],
            ],
            'update'
        );
    }

    /**
     * Delete a property
     *
     * DELETE /api/properties/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $property = Property::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // If this is a joint property, also delete the reciprocal property
        if ($property->joint_owner_id) {
            $reciprocalProperty = Property::where('user_id', $property->joint_owner_id)
                ->where('joint_owner_id', $user->id)
                ->where('address_line_1', $property->address_line_1)
                ->where('postcode', $property->postcode)
                ->first();

            if ($reciprocalProperty) {
                $reciprocalProperty->delete();
            }
        }

        $property->delete();

        return response()->json([
            'success' => true,
            'message' => 'Property deleted successfully',
        ]);
    }

    /**
     * Calculate SDLT for a property purchase
     *
     * POST /api/properties/calculate-sdlt
     */
    public function calculateSDLT(Request $request): JsonResponse
    {
        $request->validate([
            'purchase_price' => 'required|numeric|min:0',
            'property_type' => 'required|in:main_residence,secondary_residence,buy_to_let',
            'is_first_home' => 'sometimes|boolean',
        ]);

        $sdlt = $this->propertyTaxService->calculateSDLT(
            $request->input('purchase_price'),
            $request->input('property_type'),
            $request->input('is_first_home', false)
        );

        return response()->json([
            'success' => true,
            'data' => $sdlt,
        ]);
    }

    /**
     * Calculate CGT for a property disposal
     *
     * POST /api/properties/{id}/calculate-cgt
     */
    public function calculateCGT(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'disposal_price' => 'required|numeric|min:0',
            'disposal_costs' => 'sometimes|numeric|min:0',
        ]);

        $property = Property::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $cgt = $this->propertyTaxService->calculateCGT(
            $property,
            $request->input('disposal_price'),
            $request->input('disposal_costs', 0),
            $user
        );

        return response()->json([
            'success' => true,
            'data' => $cgt,
        ]);
    }

    /**
     * Calculate rental income tax for a property
     *
     * POST /api/properties/{id}/rental-income-tax
     */
    public function calculateRentalIncomeTax(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $property = Property::where('id', $id)
            ->where('user_id', $user->id)
            ->with('mortgages')
            ->firstOrFail();

        $rentalTax = $this->propertyTaxService->calculateRentalIncomeTax($property, $user);

        return response()->json([
            'success' => true,
            'data' => $rentalTax,
        ]);
    }

    /**
     * Create a reciprocal property record for joint owner
     */
    private function createJointProperty(Property $originalProperty, int $jointOwnerId, float $ownershipPercentage, float $totalValue, float $totalMortgage = 0, array $validated = []): void
    {
        // Validate ownership percentage to prevent division by zero
        if ($ownershipPercentage <= 0 || $ownershipPercentage >= 100) {
            throw new \InvalidArgumentException('Ownership percentage must be between 0 and 100 (exclusive) for joint properties');
        }

        // Calculate the reciprocal ownership percentage
        $reciprocalPercentage = 100.00 - $ownershipPercentage;

        // Get joint owner's household_id
        $jointOwner = \App\Models\User::findOrFail($jointOwnerId);

        // Create the reciprocal property
        $jointPropertyData = $originalProperty->toArray();

        // Remove auto-generated fields
        unset($jointPropertyData['id'], $jointPropertyData['created_at'], $jointPropertyData['updated_at']);

        // Update fields for joint owner
        $jointPropertyData['user_id'] = $jointOwnerId;
        $jointPropertyData['household_id'] = $jointOwner->household_id;
        $jointPropertyData['ownership_percentage'] = $reciprocalPercentage;
        $jointPropertyData['joint_owner_id'] = $originalProperty->user_id;

        // Calculate joint owner's share of the total value
        $jointPropertyData['current_value'] = $totalValue * ($reciprocalPercentage / 100);

        // Calculate joint owner's share of costs and rental income
        // Original property has user's share, calculate joint owner's share from the ratio
        // Formula: joint_share = user_share * (reciprocal% / user%)
        $shareRatio = $reciprocalPercentage / $ownershipPercentage;
        $jointPropertyData['monthly_rental_income'] = ($originalProperty->monthly_rental_income ?? 0) * $shareRatio;
        $jointPropertyData['monthly_council_tax'] = ($originalProperty->monthly_council_tax ?? 0) * $shareRatio;
        $jointPropertyData['monthly_gas'] = ($originalProperty->monthly_gas ?? 0) * $shareRatio;
        $jointPropertyData['monthly_electricity'] = ($originalProperty->monthly_electricity ?? 0) * $shareRatio;
        $jointPropertyData['monthly_water'] = ($originalProperty->monthly_water ?? 0) * $shareRatio;
        $jointPropertyData['monthly_building_insurance'] = ($originalProperty->monthly_building_insurance ?? 0) * $shareRatio;
        $jointPropertyData['monthly_contents_insurance'] = ($originalProperty->monthly_contents_insurance ?? 0) * $shareRatio;
        $jointPropertyData['monthly_service_charge'] = ($originalProperty->monthly_service_charge ?? 0) * $shareRatio;
        $jointPropertyData['monthly_maintenance_reserve'] = ($originalProperty->monthly_maintenance_reserve ?? 0) * $shareRatio;
        $jointPropertyData['other_monthly_costs'] = ($originalProperty->other_monthly_costs ?? 0) * $shareRatio;

        $jointProperty = Property::create($jointPropertyData);

        // If there's a mortgage, create joint owner's share
        if ($totalMortgage > 0) {
            $jointMortgageBalance = $totalMortgage * ($reciprocalPercentage / 100);

            // Split monthly payment based on reciprocal ownership percentage if provided
            $jointMonthlyPayment = isset($validated['mortgage_monthly_payment'])
                ? $validated['mortgage_monthly_payment'] * ($reciprocalPercentage / 100)
                : 0.00;

            \App\Models\Mortgage::create([
                'property_id' => $jointProperty->id,
                'user_id' => $jointOwnerId,
                'lender_name' => $validated['mortgage_lender_name'] ?? 'To be completed',
                'mortgage_type' => $validated['mortgage_type'] ?? 'repayment',
                'repayment_percentage' => $validated['mortgage_repayment_percentage'] ?? null,
                'interest_only_percentage' => $validated['mortgage_interest_only_percentage'] ?? null,
                'original_loan_amount' => 0.00,  // Not provided in property form
                'outstanding_balance' => $jointMortgageBalance,  // Joint owner's share
                'interest_rate' => $validated['mortgage_interest_rate'] ?? 0.0000,
                'rate_type' => $validated['mortgage_rate_type'] ?? 'fixed',
                'fixed_rate_percentage' => $validated['mortgage_fixed_rate_percentage'] ?? null,
                'variable_rate_percentage' => $validated['mortgage_variable_rate_percentage'] ?? null,
                'fixed_interest_rate' => $validated['mortgage_fixed_interest_rate'] ?? null,
                'variable_interest_rate' => $validated['mortgage_variable_interest_rate'] ?? null,
                'monthly_payment' => $jointMonthlyPayment,
                'start_date' => $validated['mortgage_start_date'] ?? now(),
                'maturity_date' => $validated['mortgage_maturity_date'] ?? now()->addYears(25),
                'remaining_term_months' => 300,
                'ownership_type' => $originalProperty->ownership_type,
                'joint_owner_id' => $originalProperty->user_id,
                'joint_owner_name' => \App\Models\User::find($originalProperty->user_id)->name ?? null,
            ]);
        }

        // Update original property with joint_owner_id pointing to the reciprocal record
        $originalProperty->update(['joint_owner_id' => $jointOwnerId]);

        // Sync rental income for joint owner as well
        $this->syncUserRentalIncome($jointOwner);
    }

    /**
     * Sync rental income from properties to user table
     * Note: monthly_rental_income is ALREADY stored as user's share in database
     */
    private function syncUserRentalIncome(\App\Models\User $user): void
    {
        $annualRentalIncome = $user->properties->sum(function ($property) {
            $monthlyRental = $property->monthly_rental_income ?? 0;

            // Values are already user's share - just annualize
            return $monthlyRental * 12;
        });

        $user->update(['annual_rental_income' => $annualRentalIncome]);
    }
}
