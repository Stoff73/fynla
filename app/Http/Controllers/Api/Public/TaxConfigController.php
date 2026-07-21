<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\TaxConfigSnapshotService;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated entry point for the same tax-year snapshot served
 * from the authenticated `Api\TaxConfigController`. Used by `PublicLayout`
 * to hydrate the shared `taxConfig` Vuex store before unauthenticated pages
 * (CalculatorsPage etc.) render — so they read live UK rates from the
 * backend instead of falling back to the hardcoded constants in
 * `resources/js/constants/taxConfig.js`.
 *
 * UK tax allowances/thresholds/rates are public-by-nature data (HMRC
 * publishes them), so exposing the snapshot publicly carries no leakage
 * risk. The route is rate-limited to defend against scraping/abuse.
 */
class TaxConfigController extends Controller
{
    public function __construct(private readonly TaxConfigSnapshotService $snapshot) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->snapshot->build(),
        ]);
    }
}
