<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\SpousePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpousePermissionController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * Get the current permission status with spouse
     *
     * GET /api/spouse-permission/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has spouse_id set (linked spouse account)
        $hasLinkedSpouse = (bool) $user->spouse_id;

        // Also check if user has a spouse in family_members table (may not have linked account)
        $spouseFamilyMember = \App\Models\FamilyMember::where('user_id', $user->id)
            ->where('relationship', 'spouse')
            ->first();

        if (! $hasLinkedSpouse && ! $spouseFamilyMember) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_spouse' => false,
                    'spouse' => null,
                    'permission' => null,
                ],
            ]);
        }

        // If spouse_id is set, check for permissions
        if ($user->spouse_id) {
            // Check if permission exists (either direction)
            $permission = SpousePermission::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('spouse_id', $user->spouse_id);
            })->orWhere(function ($query) use ($user) {
                $query->where('user_id', $user->spouse_id)
                    ->where('spouse_id', $user->id);
            })->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'has_spouse' => true,
                    'spouse' => $user->spouse,
                    'permission' => $permission,
                    'can_view_spouse_data' => $permission && $permission->status === 'accepted',
                ],
            ]);
        }

        // If only family member spouse exists (no linked account)
        return response()->json([
            'success' => true,
            'data' => [
                'has_spouse' => true,
                'spouse' => [
                    'id' => null,
                    'name' => $spouseFamilyMember->name,
                    'email' => null,
                ],
                'permission' => null,
                'can_view_spouse_data' => false,
                'requires_account_link' => true,
                'message' => 'Your spouse needs an account to enable data sharing. Add their email in the Family Members section.',
            ],
        ]);
    }

    /**
     * Request permission to view spouse's data
     *
     * POST /api/spouse-permission/request
     */
    public function request(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->spouse_id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a linked spouse',
            ], 422);
        }

        // Check if permission already exists
        $existingPermission = SpousePermission::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('spouse_id', $user->spouse_id);
        })->orWhere(function ($query) use ($user) {
            $query->where('user_id', $user->spouse_id)
                ->where('spouse_id', $user->id);
        })->first();

        if ($existingPermission) {
            return response()->json([
                'success' => false,
                'message' => 'A permission request already exists',
                'data' => ['permission' => $existingPermission],
            ], 422);
        }

        // Create new permission request
        $permission = SpousePermission::create([
            'user_id' => $user->id,
            'spouse_id' => $user->spouse_id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        // Send notification/email to spouse
        $spouse = \App\Models\User::find($user->spouse_id);
        if ($spouse) {
            $spouse->notify(new \App\Notifications\SpousePermissionRequest($user->name));
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission request sent to your spouse',
            'data' => ['permission' => $permission],
        ], 201);
    }

    /**
     * Accept a permission request
     *
     * POST /api/spouse-permission/accept
     */
    public function accept(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->spouse_id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a linked spouse',
            ], 422);
        }

        // Find the permission request (where user is the spouse being asked)
        $permission = SpousePermission::where('spouse_id', $user->id)
            ->where('user_id', $user->spouse_id)
            ->where('status', 'pending')
            ->first();

        if (! $permission) {
            return response()->json([
                'success' => false,
                'message' => 'No pending permission request found',
            ], 404);
        }

        $permission->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission granted successfully',
            'data' => ['permission' => $permission->fresh()],
        ]);
    }

    /**
     * Reject a permission request
     *
     * POST /api/spouse-permission/reject
     */
    public function reject(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->spouse_id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a linked spouse',
            ], 422);
        }

        // Find the permission request (where user is the spouse being asked)
        $permission = SpousePermission::where('spouse_id', $user->id)
            ->where('user_id', $user->spouse_id)
            ->where('status', 'pending')
            ->first();

        if (! $permission) {
            return response()->json([
                'success' => false,
                'message' => 'No pending permission request found',
            ], 404);
        }

        $permission->update([
            'status' => 'rejected',
            'responded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission request rejected',
            'data' => ['permission' => $permission->fresh()],
        ]);
    }

    /**
     * Revoke permission (can be done by either spouse)
     *
     * DELETE /api/spouse-permission/revoke
     */
    public function revoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->spouse_id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a linked spouse',
            ], 422);
        }

        // Find the permission (either direction)
        $permission = SpousePermission::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('spouse_id', $user->spouse_id);
        })->orWhere(function ($query) use ($user) {
            $query->where('user_id', $user->spouse_id)
                ->where('spouse_id', $user->id);
        })->first();

        if (! $permission) {
            return response()->json([
                'success' => false,
                'message' => 'No permission found to revoke',
            ], 404);
        }

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission revoked successfully',
        ]);
    }
}
