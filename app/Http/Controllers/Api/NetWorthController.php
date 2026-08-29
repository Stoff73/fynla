<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\NetWorth\NetWorthForecastAssumptionService;
use App\Services\NetWorth\NetWorthForecastService;
use App\Services\NetWorth\NetWorthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetWorthController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly NetWorthService $netWorthService,
        private readonly NetWorthForecastService $forecastService,
        private readonly NetWorthForecastAssumptionService $forecastAssumptionService,
    ) {}

    public function getForecast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'years' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->forecastService->forecast(
                $request->user(),
                (int) ($validated['years'] ?? 30),
            ),
        ]);
    }

    public function updateForecastAssumptions(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->forecastAssumptionService->update(
                $request->user(),
                $request->all(),
            ),
        ]);
    }

    public function resetForecastAssumptions(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->forecastAssumptionService->reset($request->user()),
        ]);
    }

    /**
     * Get net worth overview
     */
    public function getOverview(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $netWorth = $this->netWorthService->getCachedNetWorth($user);

            // W-0350 — the gate this comment described was never installed.
            //
            // It read "if spouse exists and data sharing is enabled", and the line
            // under it said "you can add permission checks here". Neither check was
            // there. What shipped on the strength of `$user->spouse_id` alone —
            // a column this account writes about itself — is the named account's
            // `total_assets`, `total_liabilities`, `net_worth` and the full breakdown
            // across pensions, property, investments, cash, business and chattels,
            // plus their mortgages, loans and credit cards.
            //
            // Lifted to RECIPROCITY, per the census's ranked acceptance. **Not to
            // `hasAcceptedSpousePermission()`, deliberately**: measured on the
            // development database, 8 of the 12 reciprocally linked accounts have no
            // accepted permission row, so requiring consent here would take the spouse
            // panel away from two-thirds of real couples. The Inheritance Tax path DOES
            // require it for the same class of data, and that inconsistency is a
            // decision to be taken openly rather than smuggled in as part of this fix.
            $spouseData = null;
            if ($spouse = $user->reciprocalLiveSpouse()) {
                $spouseNetWorth = $this->netWorthService->getCachedNetWorth($spouse);
                $spouseData = [
                    'totalAssets' => $spouseNetWorth['total_assets'],
                    'totalLiabilities' => $spouseNetWorth['total_liabilities'],
                    'netWorth' => $spouseNetWorth['net_worth'],
                    'breakdown' => $spouseNetWorth['breakdown'],
                    'liabilitiesBreakdown' => $spouseNetWorth['liabilities_breakdown'],
                    'hasDbPensions' => $spouseNetWorth['has_db_pensions'] ?? false,
                ];
            }

            $response = [
                'success' => true,
                'data' => $netWorth,
            ];

            if ($spouseData) {
                $response['spouse_data'] = $spouseData;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Calculating net worth');
        }
    }

    /**
     * Get asset breakdown with percentages
     */
    public function getBreakdown(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $breakdown = $this->netWorthService->getAssetBreakdown($user);

            return response()->json([
                'success' => true,
                'data' => $breakdown,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching asset breakdown');
        }
    }

    /**
     * Get assets summary
     */
    public function getAssetsSummary(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $summary = $this->netWorthService->getAssetsSummary($user);

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching assets summary');
        }
    }

    /**
     * Get joint assets
     */
    public function getJointAssets(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $jointAssets = $this->netWorthService->getJointAssets($user);

            return response()->json([
                'success' => true,
                'data' => $jointAssets,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching joint assets');
        }
    }

    /**
     * Get assets summary with detailed individual account lists
     * Used for the Net Worth Overview cards
     */
    public function getAssetsSummaryWithDetails(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $summary = $this->netWorthService->getAssetsSummaryWithDetails($user);

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching assets summary with details');
        }
    }

    /**
     * Refresh net worth (bypass cache)
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Invalidate cache
            $this->netWorthService->invalidateCache($user->id);

            // Recalculate
            $netWorth = $this->netWorthService->calculateNetWorth($user);

            return response()->json([
                'success' => true,
                'data' => $netWorth,
                'message' => 'Net worth refreshed successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Refreshing net worth');
        }
    }
}
