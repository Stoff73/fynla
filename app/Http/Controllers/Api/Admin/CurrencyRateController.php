<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCurrencyRateRequest;
use App\Http\Requests\Admin\UpdateCurrencyRateRequest;
use App\Http\Resources\CurrencyRateResource;
use App\Services\Stores\CurrencyRateStore;
use App\Services\Stores\IngestSource;
use Illuminate\Http\JsonResponse;

class CurrencyRateController extends Controller
{
    public function __construct(
        private readonly CurrencyRateStore $store,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CurrencyRateResource::collection($this->store->all()),
        ]);
    }

    public function store(StoreCurrencyRateRequest $request): JsonResponse
    {
        $id = $this->store->create(
            $request->validated(),
            IngestSource::ADMIN,
            actorUserId: $request->user()->id,
        );

        return response()->json([
            'data' => new CurrencyRateResource($this->store->findEloquent($id)),
        ], 201);
    }

    public function update(UpdateCurrencyRateRequest $request, int $id): JsonResponse
    {
        $this->store->update(
            $id,
            $request->validated(),
            IngestSource::ADMIN,
            actorUserId: $request->user()->id,
        );

        return response()->json([
            'data' => new CurrencyRateResource($this->store->findEloquent($id)),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->store->delete($id, IngestSource::ADMIN, actorUserId: request()->user()->id);

        return response()->json(['data' => null]);
    }
}
