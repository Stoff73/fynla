<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProposedSemanticFact;
use App\Services\AI\Learning\SemanticFactPromoter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CoALA Phase 6 — admin review surface for proposed per-user semantic facts.
 *
 * Pending proposals land in proposed_semantic_facts (status='pending') via the
 * learning pipeline. An admin either approves (SemanticFactPromoter writes the
 * per-user UserSemanticStore and marks the row approved) or rejects (marks rejected).
 * All writes go through SemanticFactPromoter — this controller never touches the
 * store directly. Read-only Fyn surfaces (AdviceFyn / SemanticCorpusLoader) are
 * untouched by this controller.
 */
final class SemanticFactReviewController extends Controller
{
    /**
     * GET /api/admin/semantic-facts
     * All pending proposed semantic facts, newest first.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ProposedSemanticFact::where('status', 'pending')->latest()->get(),
        ]);
    }

    /**
     * PATCH /api/admin/semantic-facts/{fact}
     * Approve or reject a pending proposed fact.
     */
    public function update(Request $request, ProposedSemanticFact $fact, SemanticFactPromoter $promoter): JsonResponse
    {
        $action = (string) $request->input('action');

        if ($fact->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Already reviewed.'], 422);
        }

        match ($action) {
            'approve' => $promoter->approve($fact, (int) $request->user()->id),
            'reject' => $promoter->reject($fact, (int) $request->user()->id),
            default => abort(422, 'action must be approve or reject'),
        };

        return response()->json(['success' => true, 'data' => $fact->fresh()]);
    }
}
