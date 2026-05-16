<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TierConfigurationResource;
use App\Models\TierConfiguration;
use Illuminate\Http\JsonResponse;

class PricingConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $tiers = TierConfiguration::where('is_active', true)
            ->orderByRaw("FIELD(tier,'free','tier1','tier2','tier3')")->get();

        return response()->json(['data' => TierConfigurationResource::collection($tiers)]);
    }
}
