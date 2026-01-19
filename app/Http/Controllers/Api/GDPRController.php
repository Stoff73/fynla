<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataExport;
use App\Models\ErasureRequest;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\GDPR\DataErasureService;
use App\Services\GDPR\DataExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GDPRController extends Controller
{
    public function __construct(
        private DataExportService $exportService,
        private DataErasureService $erasureService,
        private ConsentService $consentService
    ) {}

    /**
     * Get user's current consent status
     */
    public function getConsents(Request $request): JsonResponse
    {
        $consents = $this->consentService->getUserConsents($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'consents' => $consents,
                'needs_reconsent' => $this->consentService->getConsentTypesNeedingReconsent($request->user()),
            ],
        ]);
    }

    /**
     * Update user consents
     */
    public function updateConsents(Request $request): JsonResponse
    {
        $request->validate([
            'consents' => 'required|array',
            'consents.*' => 'boolean',
        ]);

        $validTypes = array_keys(UserConsent::CURRENT_VERSIONS);
        $consents = array_intersect_key($request->consents, array_flip($validTypes));

        $this->consentService->recordConsents($request->user(), $consents);

        return response()->json([
            'success' => true,
            'message' => 'Consents updated successfully.',
            'data' => [
                'consents' => $this->consentService->getUserConsents($request->user()),
            ],
        ]);
    }

    /**
     * Request a data export
     */
    public function requestExport(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'sometimes|string|in:json,csv',
        ]);

        $format = $request->format ?? DataExport::FORMAT_JSON;
        $export = $this->exportService->requestExport($request->user(), $format);

        // Process immediately for now (could be queued for large datasets)
        if ($export->isPending()) {
            $this->exportService->processExport($export);
            $export->refresh();
        }

        return response()->json([
            'success' => true,
            'message' => $export->isCompleted()
                ? 'Your data export is ready for download.'
                : 'Your data export request has been received.',
            'data' => [
                'export_id' => $export->id,
                'status' => $export->status,
                'format' => $export->format,
                'expires_at' => $export->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get export status
     */
    public function getExportStatus(Request $request): JsonResponse
    {
        $export = DataExport::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->first();

        if (! $export) {
            return response()->json([
                'success' => false,
                'message' => 'No export request found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'export_id' => $export->id,
                'status' => $export->status,
                'format' => $export->format,
                'file_size' => $export->file_size,
                'requested_at' => $export->requested_at?->toIso8601String(),
                'completed_at' => $export->completed_at?->toIso8601String(),
                'expires_at' => $export->expires_at?->toIso8601String(),
                'is_downloadable' => $export->isDownloadable(),
            ],
        ]);
    }

    /**
     * Download the export file
     */
    public function downloadExport(Request $request, int $id): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $export = DataExport::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $export) {
            return response()->json([
                'success' => false,
                'message' => 'Export not found.',
            ], 404);
        }

        if (! $export->isDownloadable()) {
            return response()->json([
                'success' => false,
                'message' => $export->isExpired()
                    ? 'This export has expired. Please request a new one.'
                    : 'Export is not ready for download.',
            ], 400);
        }

        $filePath = $this->exportService->getExportFile($export);

        if (! $filePath || ! file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Export file not found.',
            ], 404);
        }

        $filename = 'fynla_data_export_' . now()->format('Y-m-d') . '.' . $export->format;

        return response()->download($filePath, $filename);
    }

    /**
     * Request account deletion (right to be forgotten)
     */
    public function requestErasure(Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
            'confirm' => 'required|boolean|accepted',
        ]);

        // Preview users cannot request erasure
        if ($request->user()->is_preview_user) {
            return response()->json([
                'success' => false,
                'message' => 'Preview accounts cannot be deleted.',
            ], 403);
        }

        $erasureRequest = $this->erasureService->requestErasure(
            $request->user(),
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Your data erasure request has been submitted. You will receive an email to confirm the deletion.',
            'data' => [
                'request_id' => $erasureRequest->id,
                'status' => $erasureRequest->status,
                'requested_at' => $erasureRequest->requested_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get erasure request status
     */
    public function getErasureStatus(Request $request): JsonResponse
    {
        $erasureRequest = ErasureRequest::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->first();

        if (! $erasureRequest) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_request' => false,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_request' => true,
                'request_id' => $erasureRequest->id,
                'status' => $erasureRequest->status,
                'requested_at' => $erasureRequest->requested_at?->toIso8601String(),
                'confirmed_at' => $erasureRequest->confirmed_at?->toIso8601String(),
                'completed_at' => $erasureRequest->completed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Confirm erasure request
     */
    public function confirmErasure(Request $request, int $id): JsonResponse
    {
        $erasureRequest = ErasureRequest::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $erasureRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Erasure request not found.',
            ], 404);
        }

        if (! $erasureRequest->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'This request cannot be confirmed.',
            ], 400);
        }

        $this->erasureService->confirmErasure($erasureRequest);

        // Process immediately (in production, this might be queued)
        $this->erasureService->processErasure($erasureRequest);

        return response()->json([
            'success' => true,
            'message' => 'Your account and all associated data has been deleted.',
        ]);
    }

    /**
     * Cancel erasure request
     */
    public function cancelErasure(Request $request, int $id): JsonResponse
    {
        $erasureRequest = ErasureRequest::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $erasureRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Erasure request not found.',
            ], 404);
        }

        try {
            $this->erasureService->cancelErasure($erasureRequest);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Erasure request has been cancelled.',
        ]);
    }

    /**
     * Get consent history
     */
    public function getConsentHistory(Request $request): JsonResponse
    {
        $history = $this->consentService->getConsentHistory($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'history' => $history->map(fn ($c) => [
                    'type' => $c->consent_type,
                    'version' => $c->version,
                    'consented' => $c->consented,
                    'consented_at' => $c->consented_at?->toIso8601String(),
                    'withdrawn_at' => $c->withdrawn_at?->toIso8601String(),
                ]),
            ],
        ]);
    }
}
