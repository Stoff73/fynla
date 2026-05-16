<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTierConfigurationRequest;
use App\Http\Resources\TierConfigurationResource;
use App\Models\TierConfiguration;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TierConfigurationStore;
use Illuminate\Http\JsonResponse;

class TierConfigurationController extends Controller
{
    public function __construct(private readonly TierConfigurationStore $store) {}

    public function index(): JsonResponse
    {
        $tiers = TierConfiguration::orderByRaw("FIELD(tier,'free','tier1','tier2','tier3')")->get();

        return response()->json(['data' => TierConfigurationResource::collection($tiers)]);
    }

    public function update(UpdateTierConfigurationRequest $request, string $tier): JsonResponse
    {
        $updated = $this->store->updateTier($tier, $request->validated(), $request->user(), IngestSource::ADMIN);

        return response()->json(['data' => new TierConfigurationResource($updated)]);
    }
}
