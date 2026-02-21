<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\NetWorth\NetWorthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetWorthController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly NetWorthService $netWorthService
    ) {}

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

            // Add spouse net worth data if spouse exists and data sharing is enabled
            $spouseData = null;
            if ($user->spouse_id) {
                $spouse = $user->spouse;
                if ($spouse) {
                    // Check if data sharing is enabled (you can add permission checks here)
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
