<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\ProtectionAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Protection\ScenarioRequest;
use App\Http\Requests\Protection\StoreCriticalIllnessPolicyRequest;
use App\Http\Requests\Protection\StoreDisabilityPolicyRequest;
use App\Http\Requests\Protection\StoreLifePolicyRequest;
use App\Http\Requests\Protection\StoreProtectionProfileRequest;
use App\Http\Requests\Protection\StoreSicknessIllnessPolicyRequest;
use App\Http\Requests\Protection\UpdateCriticalIllnessPolicyRequest;
use App\Http\Requests\Protection\UpdateDisabilityPolicyRequest;
use App\Http\Requests\Protection\UpdateLifePolicyRequest;
use App\Http\Requests\Protection\UpdateSicknessIllnessPolicyRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\CriticalIllnessPolicy;
use App\Models\DisabilityPolicy;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionProfile;
use App\Models\SicknessIllnessPolicy;
use App\Traits\PolicyCRUDTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProtectionController extends Controller
{
    use PolicyCRUDTrait;
    use SanitizedErrorResponse;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        private ProtectionAgent $protectionAgent,
        private \App\Services\Protection\ComprehensiveProtectionPlanService $comprehensiveProtectionPlan
    ) {}

    /**
     * Get all protection data for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Auto-create protection profile if it doesn't exist
        $profile = $user->protectionProfile;
        if (! $profile) {
            $profile = ProtectionProfile::create([
                'user_id' => $user->id,
                'annual_income' => 0,
                'monthly_expenditure' => 0,
                'mortgage_balance' => 0,
                'other_debts' => 0,
                'number_of_dependents' => 0,
                'retirement_age' => 67,
            ]);
        }

        // Eager load all policy relationships to prevent N+1 queries
        $user->load([
            'lifeInsurancePolicies',
            'criticalIllnessPolicies',
            'incomeProtectionPolicies',
            'disabilityPolicies',
            'sicknessIllnessPolicies',
        ]);

        $lifePolicies = $user->lifeInsurancePolicies;
        $criticalIllnessPolicies = $user->criticalIllnessPolicies;
        $incomeProtectionPolicies = $user->incomeProtectionPolicies;
        $disabilityPolicies = $user->disabilityPolicies;
        $sicknessIllnessPolicies = $user->sicknessIllnessPolicies;

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $profile,
                'policies' => [
                    'life_insurance' => $lifePolicies,
                    'critical_illness' => $criticalIllnessPolicies,
                    'income_protection' => $incomeProtectionPolicies,
                    'disability' => $disabilityPolicies,
                    'sickness_illness' => $sicknessIllnessPolicies,
                ],
            ],
        ]);
    }

    /**
     * Analyze protection coverage.
     */
    public function analyze(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $analysis = $this->protectionAgent->analyze($userId);

            return response()->json($analysis);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Protection analysis', 500, ['user_id' => $userId]);
        }
    }

    /**
     * Get recommendations.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $analysis = $this->protectionAgent->analyze($userId);
            $recommendations = $this->protectionAgent->generateRecommendations($analysis);

            return response()->json($recommendations);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Recommendations generation', 500, ['user_id' => $userId]);
        }
    }

    /**
     * Build scenarios.
     */
    public function scenarios(ScenarioRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $parameters = $request->validated();

        try {
            $scenarios = $this->protectionAgent->buildScenarios($userId, $parameters);

            return response()->json($scenarios);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to build scenarios: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store or update protection profile.
     */
    public function storeProfile(StoreProtectionProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $validated['user_id'] = $user->id;

        try {
            $profile = ProtectionProfile::updateOrCreate(
                ['user_id' => $user->id],
                $validated
            );

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Protection profile saved successfully.',
                'data' => $profile,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save protection profile: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the has_no_policies flag for the protection profile.
     */
    public function updateHasNoPolicies(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'has_no_policies' => ['required', 'boolean'],
        ]);

        try {
            $profile = ProtectionProfile::where('user_id', $user->id)->first();

            if (! $profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Protection profile not found. Please create a profile first.',
                ], 404);
            }

            $profile->has_no_policies = $request->input('has_no_policies');
            $profile->save();

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Protection profile updated successfully.',
                'data' => $profile,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update protection profile: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new life insurance policy.
     */
    public function storeLifePolicy(StoreLifePolicyRequest $request): JsonResponse
    {
        return $this->storePolicy(
            LifeInsurancePolicy::class,
            $request->validated(),
            $request->user()->id,
            'Life insurance'
        );
    }

    /**
     * Update a life insurance policy.
     */
    public function updateLifePolicy(UpdateLifePolicyRequest $request, int $id): JsonResponse
    {
        return $this->updatePolicy(
            LifeInsurancePolicy::class,
            $request->validated(),
            $request->user()->id,
            $id,
            'Life insurance'
        );
    }

    /**
     * Delete a life insurance policy.
     */
    public function destroyLifePolicy(Request $request, int $id): JsonResponse
    {
        return $this->destroyPolicy(
            LifeInsurancePolicy::class,
            $request->user()->id,
            $id,
            'Life insurance'
        );
    }

    /**
     * Store a new critical illness policy.
     */
    public function storeCriticalIllnessPolicy(StoreCriticalIllnessPolicyRequest $request): JsonResponse
    {
        return $this->storePolicy(
            CriticalIllnessPolicy::class,
            $request->validated(),
            $request->user()->id,
            'Critical illness'
        );
    }

    /**
     * Update a critical illness policy.
     */
    public function updateCriticalIllnessPolicy(UpdateCriticalIllnessPolicyRequest $request, int $id): JsonResponse
    {
        return $this->updatePolicy(
            CriticalIllnessPolicy::class,
            $request->validated(),
            $request->user()->id,
            $id,
            'Critical illness'
        );
    }

    /**
     * Delete a critical illness policy.
     */
    public function destroyCriticalIllnessPolicy(Request $request, int $id): JsonResponse
    {
        return $this->destroyPolicy(
            CriticalIllnessPolicy::class,
            $request->user()->id,
            $id,
            'Critical illness'
        );
    }

    /**
     * Store a new income protection policy.
     */
    public function storeIncomeProtectionPolicy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|max:255',
            'policy_number' => 'nullable|string|max:255',
            'benefit_amount' => 'required|numeric|min:1000',
            'benefit_frequency' => 'required|in:monthly,weekly',
            'deferred_period_weeks' => 'nullable|integer|min:0|max:104',
            'benefit_period_months' => 'nullable|integer|min:1|max:720',
            'premium_amount' => 'required|numeric|min:0',
            'premium_frequency' => 'required|in:monthly,quarterly,annually',
            'occupation_class' => 'nullable|string|max:255',
            'policy_start_date' => 'nullable|date|before_or_equal:today',
            'policy_end_date' => 'nullable|date|after:policy_start_date',
            'policy_term_years' => 'nullable|integer|min:1|max:50',
        ]);

        return $this->storePolicy(
            IncomeProtectionPolicy::class,
            $validated,
            $request->user()->id,
            'Income protection'
        );
    }

    /**
     * Update an income protection policy.
     */
    public function updateIncomeProtectionPolicy(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'sometimes|string|max:255',
            'policy_number' => 'sometimes|nullable|string|max:255',
            'benefit_amount' => 'sometimes|numeric|min:1000',
            'benefit_frequency' => 'sometimes|in:monthly,weekly',
            'deferred_period_weeks' => 'sometimes|integer|min:0|max:104',
            'benefit_period_months' => 'sometimes|nullable|integer|min:1|max:720',
            'premium_amount' => 'sometimes|numeric|min:0',
            'premium_frequency' => 'sometimes|in:monthly,quarterly,annually',
            'occupation_class' => 'sometimes|nullable|string|max:255',
            'policy_start_date' => 'sometimes|date|before_or_equal:today',
            'policy_end_date' => 'sometimes|nullable|date|after:policy_start_date',
        ]);

        return $this->updatePolicy(
            IncomeProtectionPolicy::class,
            $validated,
            $request->user()->id,
            $id,
            'Income protection'
        );
    }

    /**
     * Delete an income protection policy.
     */
    public function destroyIncomeProtectionPolicy(Request $request, int $id): JsonResponse
    {
        return $this->destroyPolicy(
            IncomeProtectionPolicy::class,
            $request->user()->id,
            $id,
            'Income protection'
        );
    }

    /**
     * Store a new disability policy.
     */
    public function storeDisabilityPolicy(StoreDisabilityPolicyRequest $request): JsonResponse
    {
        return $this->storePolicy(
            DisabilityPolicy::class,
            $request->validated(),
            $request->user()->id,
            'Disability'
        );
    }

    /**
     * Update a disability policy.
     */
    public function updateDisabilityPolicy(UpdateDisabilityPolicyRequest $request, int $id): JsonResponse
    {
        return $this->updatePolicy(
            DisabilityPolicy::class,
            $request->validated(),
            $request->user()->id,
            $id,
            'Disability'
        );
    }

    /**
     * Delete a disability policy.
     */
    public function destroyDisabilityPolicy(Request $request, int $id): JsonResponse
    {
        return $this->destroyPolicy(
            DisabilityPolicy::class,
            $request->user()->id,
            $id,
            'Disability'
        );
    }

    /**
     * Store a new sickness/illness policy.
     */
    public function storeSicknessIllnessPolicy(StoreSicknessIllnessPolicyRequest $request): JsonResponse
    {
        return $this->storePolicy(
            SicknessIllnessPolicy::class,
            $request->validated(),
            $request->user()->id,
            'Sickness/Illness'
        );
    }

    /**
     * Update a sickness/illness policy.
     */
    public function updateSicknessIllnessPolicy(UpdateSicknessIllnessPolicyRequest $request, int $id): JsonResponse
    {
        return $this->updatePolicy(
            SicknessIllnessPolicy::class,
            $request->validated(),
            $request->user()->id,
            $id,
            'Sickness/Illness'
        );
    }

    /**
     * Delete a sickness/illness policy.
     */
    public function destroySicknessIllnessPolicy(Request $request, int $id): JsonResponse
    {
        return $this->destroyPolicy(
            SicknessIllnessPolicy::class,
            $request->user()->id,
            $id,
            'Sickness/Illness'
        );
    }

    /**
     * Get comprehensive protection plan
     */
    public function getComprehensiveProtectionPlan(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $plan = $this->comprehensiveProtectionPlan->generateComprehensiveProtectionPlan($user);

            return response()->json([
                'success' => true,
                'data' => $plan,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate comprehensive protection plan: '.$e->getMessage(),
            ], 500);
        }
    }
}
