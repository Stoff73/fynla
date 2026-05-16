<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTierConfigurationRequest;
use App\Http\Resources\TierConfigurationResource;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TierConfigurationStore;
use Illuminate\Http\JsonResponse;

class TierConfigurationController extends Controller
{
    public function __construct(private readonly TierConfigurationStore $store) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => TierConfigurationResource::collection($this->store->allOrdered()),
        ]);
    }

    public function update(UpdateTierConfigurationRequest $request, string $tier): JsonResponse
    {
        $updated = $this->store->updateTier($tier, $request->validated(), $request->user(), IngestSource::ADMIN);

        return response()->json(['data' => new TierConfigurationResource($updated)]);
    }
}
