<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Estate;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Estate\LetterEstateValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LetterValidationController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly LetterEstateValidationService $validationService
    ) {}

    /**
     * Validate letter to spouse against estate planning data.
     */
    public function checkConsistency(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $warnings = $this->validationService->validateLetterAgainstEstate($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'warnings' => $warnings,
                    'warning_count' => count($warnings),
                    'has_warnings' => count($warnings) > 0,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Letter estate validation');
        }
    }
}
