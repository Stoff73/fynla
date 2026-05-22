<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSavingsMarketRateRequest;
use App\Http\Requests\Admin\UpdateSavingsMarketRateRequest;
use App\Http\Resources\SavingsMarketRateResource;
use App\Models\SavingsMarketRate;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsMarketRateStore;
use Illuminate\Http\JsonResponse;

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
            'data' => new SavingsMarketRateResource(SavingsMarketRate::find($id)),
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
            'data' => new SavingsMarketRateResource(SavingsMarketRate::find($id)),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->store->delete($id, IngestSource::ADMIN, actorUserId: request()->user()->id);

        return response()->json(['data' => null]);
    }
}
