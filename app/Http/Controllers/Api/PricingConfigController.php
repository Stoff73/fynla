<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TierConfigurationResource;
use App\Services\Stores\TierConfigurationStore;
use Illuminate\Http\JsonResponse;

class PricingConfigController extends Controller
{
    public function __construct(private readonly TierConfigurationStore $store) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => TierConfigurationResource::collection($this->store->allActiveOrdered()),
        ]);
    }
}
