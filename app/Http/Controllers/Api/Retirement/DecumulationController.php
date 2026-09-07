<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Retirement;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Services\Estate\FutureValueCalculator;
use App\Services\Retirement\DecumulationPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Decumulation Controller
 *
 * Provides retirement drawdown strategy analysis including sustainable
 * withdrawal rates, annuity vs drawdown comparison, and income phasing.
 */
class DecumulationController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly DecumulationPlanner $planner,
        // W-0198 — the one home for how long this person expects to live.
        private readonly FutureValueCalculator $futureValue
    ) {}

    /**
     * Get full decumulation analysis for the authenticated user.
     *
     * GET /api/retirement/decumulation-analysis
     */
    public function analysis(Request $request): JsonResponse
    {
        $user = $request->user();

        $profile = RetirementProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'No retirement profile found. Please set up your retirement profile first.',
                'data' => [],
            ]);
        }

        $dcPensions = DCPension::where('user_id', $user->id)->get();
        $totalDcValue = (float) $dcPensions->sum('current_fund_value');

        if ($totalDcValue <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No Defined Contribution pension value found. Add pension details to see drawdown strategies.',
                'data' => [],
            ]);
        }

        $retirementAge = $profile->target_retirement_age;
        $currentAge = $profile->current_age;
        $yearsToRetirement = max(0, $retirementAge - $currentAge);
        // W-0198. Was `override ?? profile ?? 85`, one of four different combinations
        // of the same two columns. The precedence lives in one place now.
        $lifeExpectancy = $this->futureValue->getLifeExpectancy($user)['death_age'];
        $yearsInRetirement = max(1, $lifeExpectancy - $retirementAge);
        // W-0198. Was `spouse_life_expectancy !== null` — a married user who had not
        // filled that optional field was compared against a single-life annuity.
        $hasSpouse = $this->futureValue->hasSpouse($user);

        // Care cost parameters
        $careCostAnnual = (float) ($profile->care_cost_annual ?? 0);
        $careStartAge = $profile->care_start_age ?? 0;
        $careStartsAfterYear = $careStartAge > $retirementAge ? $careStartAge - $retirementAge : 0;

        $withdrawalRates = $this->planner->calculateSustainableWithdrawalRate(
            $totalDcValue,
            $yearsInRetirement,
            0.05,
            0.025,
            $careCostAnnual,
            $careStartsAfterYear
        );

        $annuityVsDrawdown = $this->planner->compareAnnuityVsDrawdown(
            $totalDcValue,
            $currentAge,
            $hasSpouse
        );

        $pclsStrategy = $this->planner->calculatePCLSStrategy($totalDcValue);

        $incomePhasing = $this->planner->modelIncomePhasing(
            $dcPensions,
            $retirementAge
        );

        return response()->json([
            'success' => true,
            'message' => 'Decumulation analysis completed',
            'data' => [
                'withdrawal_rates' => $withdrawalRates,
                'annuity_vs_drawdown' => $annuityVsDrawdown,
                'pcls_strategy' => $pclsStrategy,
                'income_phasing' => $incomePhasing,
                'context' => [
                    'current_age' => $currentAge,
                    'retirement_age' => $retirementAge,
                    'years_to_retirement' => $yearsToRetirement,
                    'life_expectancy' => $lifeExpectancy,
                    'years_in_retirement' => $yearsInRetirement,
                    'total_dc_value' => $totalDcValue,
                    'has_spouse' => $hasSpouse,
                ],
            ],
        ]);
    }
}
