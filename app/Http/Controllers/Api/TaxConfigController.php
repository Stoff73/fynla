<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaxConfigSnapshotService;
use Illuminate\Http\JsonResponse;

/**
 * Returns the active UK tax-year configuration snapshot the frontend needs to
 * render allowances, thresholds, and rates without falling back to the
 * hardcoded constants in `resources/js/constants/taxConfig.js`.
 *
 * This is the authenticated entry point used by App.vue / auth.js / admin
 * tax-year switching. The matching unauthenticated entry point for public
 * pages (CalculatorsPage etc.) lives at `Api\Public\TaxConfigController`.
 *
 * Both controllers share `TaxConfigSnapshotService` so the projection logic
 * (frontend shape, lower-bound → upper-bound band conversion) has one home.
 */
class TaxConfigController extends Controller
{
    public function __construct(private readonly TaxConfigSnapshotService $snapshot) {}

    public function show(): JsonResponse
    {
        // No cross-request cache: TaxConfigService is request-scoped so each
        // call already loads the active config from the DB exactly once, and a
        // longer-lived cache would go stale when admin edits the active year.
        return response()->json([
            'success' => true,
            'data' => $this->snapshot->build(),
        ]);
    }
}
