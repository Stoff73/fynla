<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreActuarialLifeTableRequest;
use App\Http\Requests\Admin\UpdateActuarialLifeTableRequest;
use App\Http\Resources\ActuarialLifeTableResource;
use App\Services\Stores\ActuarialLifeTableStore;
use App\Services\Stores\IngestSource;
use Illuminate\Http\JsonResponse;

class ActuarialLifeTableController extends Controller
{
    public function __construct(
        private readonly ActuarialLifeTableStore $store,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ActuarialLifeTableResource::collection($this->store->all()),
        ]);
    }

    public function store(StoreActuarialLifeTableRequest $request): JsonResponse
    {
        $id = $this->store->create(
            $request->validated(),
            IngestSource::ADMIN,
            actorUserId: $request->user()->id,
        );

        return response()->json([
            'data' => new ActuarialLifeTableResource($this->store->findEloquent($id)),
        ], 201);
    }

    public function update(UpdateActuarialLifeTableRequest $request, int $id): JsonResponse
    {
        $this->store->update(
            $id,
            $request->validated(),
            IngestSource::ADMIN,
            actorUserId: $request->user()->id,
        );

        return response()->json([
            'data' => new ActuarialLifeTableResource($this->store->findEloquent($id)),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->store->delete($id, IngestSource::ADMIN, actorUserId: request()->user()->id);

        return response()->json(['data' => null]);
    }
}
