<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Insights\UploadInsightImageRequest;
use App\Services\Insights\InsightImageService;
use Illuminate\Http\JsonResponse;

class InsightImageController extends Controller
{
    public function __construct(private readonly InsightImageService $images)
    {
        $this->middleware('permission:admin.access');
    }

    public function store(UploadInsightImageRequest $request): JsonResponse
    {
        $paths = $this->images->upload(
            $request->file('image'),
            (string) $request->string('slug'),
        );

        return response()->json(['data' => $paths], 201);
    }
}
