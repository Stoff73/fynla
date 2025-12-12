<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\ProtectionAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Protection\ScenarioRequest;
use App\Http\Requests\Protection\StoreDisabilityPolicyRequest;
use App\Http\Requests\Protection\StoreLifePolicyRequest;
use App\Http\Requests\Protection\StoreProtectionProfileRequest;
use App\Http\Requests\Protection\StoreSicknessIllnessPolicyRequest;
use App\Http\Requests\Protection\UpdateDisabilityPolicyRequest;
use App\Http\Requests\Protection\UpdateLifePolicyRequest;
use App\Http\Requests\Protection\UpdateSicknessIllnessPolicyRequest;
use App\Models\CriticalIllnessPolicy;
use App\Models\DisabilityPolicy;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionProfile;
use App\Models\SicknessIllnessPolicy;
use App\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProtectionController extends Controller
{
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
        $user = $request->user();
        $validated = $request->validated();
        $validated['user_id'] = $user->id;

        try {
            $policy = LifeInsurancePolicy::create($validated);

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Life insurance policy created successfully.',
                'data' => $policy,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create life insurance policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a life insurance policy.
     */
    public function updateLifePolicy(UpdateLifePolicyRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $policy = LifeInsurancePolicy::where('user_id', $user->id)
                ->findOrFail($id);

            $policy->update($request->validated());

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Life insurance policy updated successfully.',
                'data' => $policy,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to update it.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update life insurance policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a life insurance policy.
     */
    public function destroyLifePolicy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $policy = LifeInsurancePolicy::where('user_id', $user->id)
                ->findOrFail($id);

            $policy->delete();

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Life insurance policy deleted successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to delete it.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete life insurance policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new critical illness policy.
     */
    public function storeCriticalIllnessPolicy(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'policy_type' => 'required|in:standalone,accelerated,additional',
            'provider' => 'required|string|max:255',
            'policy_number' => 'nullable|string|max:255',
            'sum_assured' => 'required|numeric|min:1000',
            'premium_amount' => 'required|numeric|min:0',
            'premium_frequency' => 'required|in:monthly,quarterly,annually',
            'policy_start_date' => 'nullable|date|before_or_equal:today',
            'policy_end_date' => 'nullable|date|after:policy_start_date',
            'policy_term_years' => 'nullable|integer|min:1|max:50',
            'conditions_covered' => 'nullable|array',
        ]);

        $validated['user_id'] = $user->id;

        try {
            $policy = CriticalIllnessPolicy::create($validated);

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Critical illness policy created successfully.',
                'data' => $policy,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create critical illness policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a critical illness policy.
     */
    public function updateCriticalIllnessPolicy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'policy_type' => 'sometimes|in:standalone,accelerated,additional',
            'provider' => 'sometimes|string|max:255',
            'policy_number' => 'sometimes|nullable|string|max:255',
            'sum_assured' => 'sometimes|numeric|min:1000',
            'premium_amount' => 'sometimes|numeric|min:0',
            'premium_frequency' => 'sometimes|in:monthly,quarterly,annually',
            'policy_start_date' => 'sometimes|date|before_or_equal:today',
            'policy_end_date' => 'sometimes|nullable|date|after:policy_start_date',
            'policy_term_years' => 'sometimes|integer|min:1|max:50',
            'conditions_covered' => 'sometimes|nullable|array',
        ]);

        try {
            $policy = CriticalIllnessPolicy::where('user_id', $user->id)
                ->findOrFail($id);

            $policy->update($validated);

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Critical illness policy updated successfully.',
                'data' => $policy,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to update it.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update critical illness policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a critical illness policy.
     */
    public function destroyCriticalIllnessPolicy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $policy = CriticalIllnessPolicy::where('user_id', $user->id)
                ->findOrFail($id);

            $policy->delete();

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Critical illness policy deleted successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to delete it.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete critical illness policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new income protection policy.
     */
    public function storeIncomeProtectionPolicy(Request $request): JsonResponse
    {
        $user = $request->user();

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

        $validated['user_id'] = $user->id;

        try {
            $policy = IncomeProtectionPolicy::create($validated);

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Income protection policy created successfully.',
                'data' => $policy,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create income protection policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an income protection policy.
     */
    public function updateIncomeProtectionPolicy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

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

        try {
            $policy = IncomeProtectionPolicy::where('user_id', $user->id)
                ->findOrFail($id);

            $policy->update($validated);

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Income protection policy updated successfully.',
                'data' => $policy,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to update it.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update income protection policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an income protection policy.
     */
    public function destroyIncomeProtectionPolicy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $policy = IncomeProtectionPolicy::where('user_id', $user->id)
                ->findOrFail($id);

            $policy->delete();

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Income protection policy deleted successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to delete it.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete income protection policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new disability policy.
     */
    public function storeDisabilityPolicy(StoreDisabilityPolicyRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $validated['user_id'] = $user->id;

        try {
            $policy = DisabilityPolicy::create($validated);

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Disability policy created successfully.',
                'data' => $policy,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create disability policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a disability policy.
     */
    public function updateDisabilityPolicy(UpdateDisabilityPolicyRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $policy = DisabilityPolicy::where('user_id', $user->id)
                ->findOrFail($id);

            $policy->update($request->validated());

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Disability policy updated successfully.',
                'data' => $policy,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to update it.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update disability policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a disability policy.
     */
    public function destroyDisabilityPolicy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $policy = DisabilityPolicy::where('user_id', $user->id)
                ->findOrFail($id);

            $policy->delete();

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Disability policy deleted successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to delete it.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete disability policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new sickness/illness policy.
     */
    public function storeSicknessIllnessPolicy(StoreSicknessIllnessPolicyRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $validated['user_id'] = $user->id;

        try {
            $policy = SicknessIllnessPolicy::create($validated);

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sickness/Illness policy created successfully.',
                'data' => $policy,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sickness/illness policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a sickness/illness policy.
     */
    public function updateSicknessIllnessPolicy(UpdateSicknessIllnessPolicyRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $policy = SicknessIllnessPolicy::where('user_id', $user->id)
                ->findOrFail($id);

            $policy->update($request->validated());

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sickness/Illness policy updated successfully.',
                'data' => $policy,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to update it.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sickness/illness policy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a sickness/illness policy.
     */
    public function destroySicknessIllnessPolicy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $policy = SicknessIllnessPolicy::where('user_id', $user->id)
                ->findOrFail($id);

            $policy->delete();

            // Invalidate cache
            $this->protectionAgent->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sickness/Illness policy deleted successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to delete it.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete sickness/illness policy: '.$e->getMessage(),
            ], 500);
        }
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
