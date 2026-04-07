<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Investment\RebalancingAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rebalancing Actions Controller
 * Manages rebalancing action tracking and execution
 */
class RebalancingActionsController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * Save rebalancing actions
     *
     * POST /api/investment/rebalancing/save
     */
    public function saveRebalancingActions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'actions' => 'required|array|min:1',
            'actions.*.holding_id' => 'required|integer|exists:holdings,id',
            'actions.*.investment_account_id' => 'nullable|integer|exists:investment_accounts,id',
            'actions.*.action_type' => 'required|in:buy,sell',
            'actions.*.security_name' => 'required|string',
            'actions.*.ticker' => 'nullable|string',
            'actions.*.isin' => 'nullable|string',
            'actions.*.shares_to_trade' => 'required|numeric|min:0',
            'actions.*.trade_value' => 'required|numeric|min:0',
            'actions.*.current_price' => 'required|numeric|min:0',
            'actions.*.current_holding' => 'nullable|numeric|min:0',
            'actions.*.target_value' => 'required|numeric|min:0',
            'actions.*.target_weight' => 'required|numeric|min:0|max:1',
            'actions.*.priority' => 'nullable|integer|min:1|max:5',
            'actions.*.rationale' => 'nullable|string',
            'actions.*.cgt_cost_basis' => 'nullable|numeric',
            'actions.*.cgt_gain_or_loss' => 'nullable|numeric',
            'actions.*.cgt_liability' => 'nullable|numeric',
        ]);
        $user = $request->user();

        try {
            $savedActions = [];

            foreach ($validated['actions'] as $actionData) {
                $actionData['user_id'] = $user->id;
                $actionData['status'] = 'pending';

                $action = RebalancingAction::create($actionData);
                $savedActions[] = $action;
            }

            return response()->json([
                'success' => true,
                'data' => $savedActions,
                'message' => sprintf('%d rebalancing action(s) saved', count($savedActions)),
            ], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Saving rebalancing actions', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Get user's rebalancing actions
     *
     * GET /api/investment/rebalancing/actions
     */
    public function getRebalancingActions(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'status' => 'nullable|in:pending,executed,cancelled,expired',
            'action_type' => 'nullable|in:buy,sell',
        ]);

        $query = RebalancingAction::where('user_id', $user->id)
            ->with(['holding', 'investmentAccount'])
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'desc');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['action_type'])) {
            $query->where('action_type', $validated['action_type']);
        }

        $actions = $query->get();

        return response()->json([
            'success' => true,
            'data' => $actions,
            'count' => $actions->count(),
        ]);
    }

    /**
     * Update rebalancing action status
     *
     * PUT /api/investment/rebalancing/actions/{id}
     */
    public function updateRebalancingAction(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $action = RebalancingAction::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $action) {
            return response()->json([
                'success' => false,
                'message' => 'Rebalancing action not found',
            ], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,executed,cancelled,expired',
            'executed_at' => 'nullable|date',
            'executed_price' => 'nullable|numeric|min:0',
            'executed_shares' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $action->update($validated);

            return response()->json([
                'success' => true,
                'data' => $action->fresh(),
                'message' => 'Rebalancing action updated',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Updating rebalancing action', 500, ['user_id' => $user->id, 'action_id' => $id]);
        }
    }

    /**
     * Delete rebalancing action
     *
     * DELETE /api/investment/rebalancing/actions/{id}
     */
    public function deleteRebalancingAction(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $action = RebalancingAction::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $action) {
            return response()->json([
                'success' => false,
                'message' => 'Rebalancing action not found',
            ], 404);
        }

        try {
            $action->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rebalancing action deleted',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Deleting rebalancing action', 500, ['user_id' => $user->id, 'action_id' => $id]);
        }
    }
}
