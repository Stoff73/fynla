<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Trait for common CRUD operations on Protection policies.
 *
 * Reduces duplication in ProtectionController by providing standardized
 * store, update, and destroy operations that handle authorization, cache
 * invalidation, and error responses consistently.
 */
trait PolicyCRUDTrait
{
    /**
     * Store a new policy.
     *
     * @param  string  $modelClass  The fully qualified model class name
     * @param  array  $validated  The validated data
     * @param  int  $userId  The user ID
     * @param  string  $policyTypeName  Human-readable policy type name (e.g., "Life insurance")
     */
    protected function storePolicy(
        string $modelClass,
        array $validated,
        int $userId,
        string $policyTypeName,
        ?string $resourceClass = null
    ): JsonResponse {
        $validated['user_id'] = $userId;

        try {
            $policy = $modelClass::create($validated);

            // Invalidate cache
            $this->protectionAgent->invalidateCache($userId);

            $responseData = $resourceClass ? new $resourceClass($policy) : $policy;

            return response()->json([
                'success' => true,
                'message' => "{$policyTypeName} policy created successfully.",
                'data' => $responseData,
            ], 201);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['message' => "Failed to process {$policyTypeName} policy. Please try again."], 500);
        }
    }

    /**
     * Update an existing policy.
     *
     * @param  string  $modelClass  The fully qualified model class name
     * @param  array  $validated  The validated data
     * @param  int  $userId  The user ID
     * @param  int  $id  The policy ID
     * @param  string  $policyTypeName  Human-readable policy type name
     */
    protected function updatePolicy(
        string $modelClass,
        array $validated,
        int $userId,
        int $id,
        string $policyTypeName,
        ?string $resourceClass = null
    ): JsonResponse {
        try {
            $policy = $modelClass::where('user_id', $userId)
                ->findOrFail($id);

            $policy->update($validated);

            // Invalidate cache
            $this->protectionAgent->invalidateCache($userId);

            $responseData = $resourceClass ? new $resourceClass($policy) : $policy;

            return response()->json([
                'success' => true,
                'message' => "{$policyTypeName} policy updated successfully.",
                'data' => $responseData,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to update it.',
            ], 404);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['message' => "Failed to process {$policyTypeName} policy. Please try again."], 500);
        }
    }

    /**
     * Delete a policy.
     *
     * @param  string  $modelClass  The fully qualified model class name
     * @param  int  $userId  The user ID
     * @param  int  $id  The policy ID
     * @param  string  $policyTypeName  Human-readable policy type name
     */
    protected function destroyPolicy(
        string $modelClass,
        int $userId,
        int $id,
        string $policyTypeName
    ): JsonResponse {
        try {
            $policy = $modelClass::where('user_id', $userId)
                ->findOrFail($id);

            $policy->delete();

            // Invalidate cache
            $this->protectionAgent->invalidateCache($userId);

            return response()->json([
                'success' => true,
                'message' => "{$policyTypeName} policy deleted successfully.",
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found or you do not have permission to delete it.',
            ], 404);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['message' => "Failed to process {$policyTypeName} policy. Please try again."], 500);
        }
    }
}
