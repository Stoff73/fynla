<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Tax\Thresholds\ThresholdPositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where the user stands against the tax and benefit lines that apply to them,
 * nearest first. One payload for web, /m and native (Rule 20); the surfaces
 * differ only in how much of it they show.
 */
class ThresholdController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(private readonly ThresholdPositionService $thresholds) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->thresholds->evaluate($request->user())]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Evaluating threshold position');
        }
    }
}
