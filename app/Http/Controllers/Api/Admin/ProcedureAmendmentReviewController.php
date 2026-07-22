<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProposedProcedureAmendment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CoALA Phase 6 — admin review surface for proposed procedural amendments.
 *
 * Pending proposals land in proposed_procedure_amendments (status='pending') when
 * the workflow planner stages an amendment on failure (Tasks 10/11). An admin either
 * approves or rejects the proposal. CRITICAL: approval marks the row 'approved' ONLY —
 * it does NOT write the procedural corpus. An engineer applies the .md change by hand.
 * This is the "no autonomous procedural edits" invariant (CoALA Phase 6).
 */
final class ProcedureAmendmentReviewController extends Controller
{
    /**
     * GET /api/admin/procedure-amendments
     * All pending proposed procedure amendments, newest first.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ProposedProcedureAmendment::where('status', 'pending')->latest()->get(),
        ]);
    }

    /**
     * PATCH /api/admin/procedure-amendments/{amendment}
     * Approve or reject a pending proposed amendment.
     *
     * NOTE: approval marks the proposal accepted ONLY. It does NOT write the
     * procedural corpus — an engineer applies the .md change by hand. This is
     * the "no autonomous procedural edits" invariant (CoALA Phase 6).
     */
    public function update(Request $request, ProposedProcedureAmendment $amendment): JsonResponse
    {
        if ($amendment->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Already reviewed.'], 422);
        }

        $action = (string) $request->input('action');
        $status = match ($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => abort(422, 'action must be approve or reject'),
        };

        $amendment->forceFill([
            'status' => $status,
            'reviewed_by' => (int) $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json(['success' => true, 'data' => $amendment->fresh()]);
    }
}
