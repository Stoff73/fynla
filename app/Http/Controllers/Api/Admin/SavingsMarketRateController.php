<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSavingsMarketRateRequest;
use App\Http\Requests\Admin\UpdateSavingsMarketRateRequest;
use App\Http\Resources\SavingsMarketRateResource;
use App\Services\Savings\MarketRates\MarketRateFetchException;
use App\Services\Savings\MarketRates\MarketRateRefreshService;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsMarketRateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavingsMarketRateController extends Controller
{
    public function __construct(
        private readonly SavingsMarketRateStore $store,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SavingsMarketRateResource::collection($this->store->all()),
        ]);
    }

    public function store(StoreSavingsMarketRateRequest $request): JsonResponse
    {
        $id = $this->store->create(
            $request->validated(),
            IngestSource::ADMIN,
            actorUserId: $request->user()->id,
        );

        return response()->json([
            'data' => new SavingsMarketRateResource($this->store->findEloquent($id)),
        ], 201);
    }

    public function update(UpdateSavingsMarketRateRequest $request, int $id): JsonResponse
    {
        $this->store->update(
            $id,
            $request->validated(),
            IngestSource::ADMIN,
            actorUserId: $request->user()->id,
        );

        return response()->json([
            'data' => new SavingsMarketRateResource($this->store->findEloquent($id)),
        ]);
    }

    /**
     * Pull the current best-buy rates from MoneySavingExpert into the active tax
     * year (F20). Returns the refreshed list plus what changed; a challenged or
     * failed fetch leaves the rows untouched and reports why.
     */
    public function refresh(Request $request, MarketRateRefreshService $service): JsonResponse
    {
        try {
            $summary = $service->refresh(actorUserId: $request->user()->id);
        } catch (MarketRateFetchException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'data' => SavingsMarketRateResource::collection($this->store->all()),
            'summary' => $summary,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->store->delete($id, IngestSource::ADMIN, actorUserId: request()->user()->id);

        return response()->json(['data' => null]);
    }
}
