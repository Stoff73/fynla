<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePersonalAccountLineItemRequest;
use App\Http\Requests\UpdatePersonalAccountLineItemRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\PersonalAccount;
use App\Services\UserProfile\PersonalAccountsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalAccountsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly PersonalAccountsService $personalAccountsService
    ) {}

    /**
     * Get all personal accounts for the authenticated user
     *
     * GET /api/user/personal-accounts
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $accounts = PersonalAccount::where('user_id', $user->id)
            ->orderBy('account_type')
            ->orderBy('period_start', 'desc')
            ->get()
            ->groupBy('account_type');

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' => $accounts,
            ],
        ]);
    }

    /**
     * Calculate personal accounts (P&L, Cashflow, Balance Sheet)
     *
     * POST /api/user/personal-accounts/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $user = $request->user();

        $startDate = $request->has('start_date')
            ? Carbon::parse($request->input('start_date'))->setTimezone(config('app.timezone'))
            : Carbon::now()->setTimezone(config('app.timezone'))->startOfYear();

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->input('end_date'))->setTimezone(config('app.timezone'))
            : Carbon::now()->setTimezone(config('app.timezone'))->endOfYear();

        $asOfDate = $request->has('as_of_date')
            ? Carbon::parse($request->input('as_of_date'))->setTimezone(config('app.timezone'))
            : Carbon::now()->setTimezone(config('app.timezone'));

        // Calculate all three statements for the user
        $profitAndLoss = $this->personalAccountsService->calculateProfitAndLoss(
            $user,
            $startDate,
            $endDate
        );

        $cashflow = $this->personalAccountsService->calculateCashflow(
            $user,
            $startDate,
            $endDate
        );

        $balanceSheet = $this->personalAccountsService->calculateBalanceSheet(
            $user,
            $asOfDate
        );

        // Check if user is married and has permission to view spouse data
        $spouseData = null;
        if ($user->spouse_id && $user->hasAcceptedSpousePermission()) {
            $spouse = \App\Models\User::find($user->spouse_id);
            if ($spouse) {
                $spouseData = [
                    'profit_and_loss' => $this->personalAccountsService->calculateProfitAndLoss(
                        $spouse,
                        $startDate,
                        $endDate
                    ),
                    'cashflow' => $this->personalAccountsService->calculateCashflow(
                        $spouse,
                        $startDate,
                        $endDate
                    ),
                    'balance_sheet' => $this->personalAccountsService->calculateBalanceSheet(
                        $spouse,
                        $asOfDate
                    ),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'profit_and_loss' => $profitAndLoss,
                'cashflow' => $cashflow,
                'balance_sheet' => $balanceSheet,
                'spouse_data' => $spouseData,
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'as_of_date' => $asOfDate->format('Y-m-d'),
                ],
            ],
        ]);
    }

    /**
     * Store a manual line item
     *
     * POST /api/user/personal-accounts/line-item
     */
    public function storeLineItem(StorePersonalAccountLineItemRequest $request): JsonResponse
    {
        $user = $request->user();

        $lineItem = PersonalAccount::create([
            'user_id' => $user->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Line item added successfully',
            'data' => [
                'line_item' => $lineItem,
            ],
        ], 201);
    }

    /**
     * Update a manual line item
     *
     * PUT /api/user/personal-accounts/line-item/{id}
     */
    public function updateLineItem(UpdatePersonalAccountLineItemRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $lineItem = PersonalAccount::where('user_id', $user->id)
            ->findOrFail($id);

        $lineItem->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Line item updated successfully',
            'data' => [
                'line_item' => $lineItem->fresh(),
            ],
        ]);
    }

    /**
     * Delete a manual line item
     *
     * DELETE /api/user/personal-accounts/line-item/{id}
     */
    public function deleteLineItem(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $lineItem = PersonalAccount::where('user_id', $user->id)
            ->findOrFail($id);

        $lineItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Line item deleted successfully',
        ]);
    }
}
