<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Estate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estate\StoreLpaRequest;
use App\Http\Requests\Estate\UpdateLpaRequest;
use App\Http\Requests\Estate\UploadLpaRequest;
use App\Services\Estate\LpaComplianceService;
use App\Services\Estate\LpaDocumentService;
use App\Services\Estate\LpaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LpaController extends Controller
{
    public function __construct(
        private readonly LpaService $lpaService,
        private readonly LpaComplianceService $complianceService,
        private readonly LpaDocumentService $documentService
    ) {}

    /**
     * List all LPAs for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $lpas = $this->lpaService->getLpasForUser($request->user());

        return response()->json([
            'success' => true,
            'data' => $lpas,
        ]);
    }

    /**
     * Get a single LPA with full details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $lpa = $this->lpaService->getLpaForUser($request->user(), $id);

        if (! $lpa) {
            return response()->json([
                'success' => false,
                'message' => 'Lasting Power of Attorney not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $lpa,
        ]);
    }

    /**
     * Create a new LPA.
     */
    public function store(StoreLpaRequest $request): JsonResponse
    {
        $lpa = $this->lpaService->createLpa(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Lasting Power of Attorney created successfully.',
            'data' => $lpa,
        ], 201);
    }

    /**
     * Update an existing LPA.
     */
    public function update(UpdateLpaRequest $request, int $id): JsonResponse
    {
        $lpa = $this->lpaService->getLpaForUser($request->user(), $id);

        if (! $lpa) {
            return response()->json([
                'success' => false,
                'message' => 'Lasting Power of Attorney not found.',
            ], 404);
        }

        $updated = $this->lpaService->updateLpa($lpa, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lasting Power of Attorney updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Delete an LPA.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $lpa = $this->lpaService->getLpaForUser($request->user(), $id);

        if (! $lpa) {
            return response()->json([
                'success' => false,
                'message' => 'Lasting Power of Attorney not found.',
            ], 404);
        }

        $this->lpaService->deleteLpa($lpa);

        return response()->json([
            'success' => true,
            'message' => 'Lasting Power of Attorney deleted successfully.',
        ]);
    }

    /**
     * Upload an existing LPA document.
     */
    public function upload(UploadLpaRequest $request): JsonResponse
    {
        $lpa = $this->documentService->uploadLpa(
            $request->user(),
            $request->file('file'),
            $request->validated('lpa_type')
        );

        return response()->json([
            'success' => true,
            'message' => 'Lasting Power of Attorney document uploaded successfully.',
            'data' => $lpa,
        ], 201);
    }

    /**
     * Run compliance checks for an LPA.
     */
    public function compliance(Request $request, int $id): JsonResponse
    {
        $lpa = $this->lpaService->getLpaForUser($request->user(), $id);

        if (! $lpa) {
            return response()->json([
                'success' => false,
                'message' => 'Lasting Power of Attorney not found.',
            ], 404);
        }

        $result = $this->complianceService->checkCompliance($lpa);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Mark an LPA as registered with the Office of the Public Guardian.
     */
    public function markRegistered(Request $request, int $id): JsonResponse
    {
        $lpa = $this->lpaService->getLpaForUser($request->user(), $id);

        if (! $lpa) {
            return response()->json([
                'success' => false,
                'message' => 'Lasting Power of Attorney not found.',
            ], 404);
        }

        $validated = $request->validate([
            'registration_date' => 'nullable|date',
            'opg_reference' => 'nullable|string|max:50',
        ]);

        $updated = $this->lpaService->markAsRegistered($lpa, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Lasting Power of Attorney marked as registered with the Office of the Public Guardian.',
            'data' => $updated,
        ]);
    }

    /**
     * Get auto-filled donor details from user profile.
     */
    public function donorDefaults(Request $request): JsonResponse
    {
        $defaults = $this->lpaService->autoFillDonorDetails($request->user());

        return response()->json([
            'success' => true,
            'data' => $defaults,
        ]);
    }
}
