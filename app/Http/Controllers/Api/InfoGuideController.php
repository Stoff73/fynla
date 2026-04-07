<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\UserProfile\ModuleDataRequirementsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for the Information Guide feature.
 *
 * Provides endpoints for fetching module data requirements
 * and managing the user's guide visibility preference.
 */
class InfoGuideController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly ModuleDataRequirementsService $requirementsService
    ) {}

    /**
     * Get data requirements for a specific module.
     *
     * GET /api/info-guide/requirements?module=protection
     */
    public function getRequirements(Request $request): JsonResponse
    {
        $module = $request->query('module', 'dashboard');
        $user = $request->user();

        $requirements = $this->requirementsService->getRequirementsForModule($user, $module);

        return response()->json([
            'success' => true,
            'data' => $requirements,
        ]);
    }

    /**
     * Get the user's info guide preference.
     *
     * GET /api/info-guide/preference
     */
    public function getPreference(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $user->info_guide_enabled ?? true,
            ],
        ]);
    }

    /**
     * Update the user's info guide preference.
     *
     * PUT /api/info-guide/preference
     */
    public function updatePreference(Request $request): JsonResponse
    {
        $user = $request->user();

        // Don't persist for preview users
        if ($user->is_preview_user) {
            return response()->json([
                'success' => true,
                'message' => 'Preference noted (not persisted for preview users)',
                'data' => ['enabled' => $request->boolean('enabled')],
            ]);
        }

        $user->update([
            'info_guide_enabled' => $request->boolean('enabled'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preference updated',
            'data' => ['enabled' => $user->info_guide_enabled],
        ]);
    }
}
