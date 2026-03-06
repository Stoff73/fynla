<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\JointAccountLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JointAccountLogController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * Get joint account logs for the authenticated user.
     * Returns logs where user is either the editor or the joint owner.
     *
     * GET /api/joint-account-logs
     * Query params:
     *   - type: filter by loggable_type (property, mortgage, investment, savings)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type');

        $query = JointAccountLog::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })
            ->with(['user:id,name,email', 'jointOwner:id,name,email', 'loggable'])
            ->orderBy('created_at', 'desc');

        // Filter by type if specified
        if ($type) {
            $typeMap = [
                'property' => 'App\\Models\\Property',
                'mortgage' => 'App\\Models\\Mortgage',
                'investment' => 'App\\Models\\InvestmentAccount',
                'savings' => 'App\\Models\\SavingsAccount',
            ];

            if (isset($typeMap[$type])) {
                $query->where('loggable_type', $typeMap[$type]);
            }
        }

        $logs = $query->get();

        // Format logs for frontend
        $formattedLogs = $logs->map(function ($log) use ($user) {
            // Safe null checks for user and jointOwner relationships
            $userName = $log->user?->name ?? 'Unknown User';
            $jointOwnerName = $log->jointOwner?->name ?? 'Unknown User';

            $editedBy = $log->user_id === $user->id ? 'You' : $userName;
            $affectedUser = $log->joint_owner_id === $user->id ? 'your' : ($jointOwnerName."'s");

            // Determine asset type for display
            $assetType = match ($log->loggable_type) {
                'App\\Models\\Property' => 'property',
                'App\\Models\\Mortgage' => 'mortgage',
                'App\\Models\\InvestmentAccount' => 'investment',
                'App\\Models\\SavingsAccount' => 'savings',
                default => 'account',
            };

            // Get asset name from the loggable relationship
            $assetName = match ($log->loggable_type) {
                'App\\Models\\Property' => $log->loggable?->address_line_1 ?? 'Unknown Property',
                'App\\Models\\Mortgage' => $log->loggable?->lender_name ?? 'Unknown Mortgage',
                'App\\Models\\InvestmentAccount' => $log->loggable?->account_name ?? 'Unknown Investment',
                'App\\Models\\SavingsAccount' => $log->loggable?->account_name ?? 'Unknown Savings',
                default => 'Unknown Asset',
            };

            return [
                'id' => $log->id,
                'edited_by' => $editedBy,
                'edited_by_name' => $userName,
                'affected_user' => $affectedUser,
                'affected_user_name' => $jointOwnerName,
                'asset_type' => $assetType,
                'asset_name' => $assetName,
                'action' => $log->action,
                'changes' => $log->changes,
                'created_at' => $log->created_at->toIso8601String(),
                'formatted_date' => $log->created_at->format('d/m/Y'),
                'formatted_time' => $log->created_at->format('H:i'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $formattedLogs,
                'count' => $formattedLogs->count(),
            ],
        ]);
    }
}
