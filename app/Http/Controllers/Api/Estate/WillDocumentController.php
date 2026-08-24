<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Estate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estate\SaveWillDocumentRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Estate\WillDocument;
use App\Services\Estate\WillDocumentService;
use App\Services\Estate\WillTypePolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WillDocumentController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly WillDocumentService $service,
        private readonly WillTypePolicy $willTypePolicy
    ) {}

    /**
     * W-0019 — a married user builds mirror wills only, on every surface.
     *
     * Server-side because the web will builder is not the only way in: /m hands
     * off to it, and Fyn can steer a user here. Hiding the option in one client
     * is not the rule being enforced.
     *
     * @return JsonResponse|null Null when the request may proceed.
     */
    private function refuseUnsupportedWillType(Request $request, ?string $willType): ?JsonResponse
    {
        $refusal = $this->willTypePolicy->refusalFor($request->user(), $willType);

        if ($refusal === null) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => WillTypePolicy::asText($refusal),
            'data' => [
                'refusal_heading' => WillTypePolicy::REFUSAL_HEADING,
                'refusal' => $refusal,
            ],
        ], 422);
    }

    /**
     * Get pre-populated data from user profile.
     */
    public function prePopulate(Request $request): JsonResponse
    {
        try {
            $data = $this->service->prePopulateData($request->user());

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to load pre-populated data.', $e);
        }
    }

    /**
     * Get the user's current will document draft or null.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $doc = $this->service->getForUser($request->user());

            return response()->json([
                'success' => true,
                'data' => $doc,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to load will document.', $e);
        }
    }

    /**
     * Create a new will document draft.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'will_type' => 'required|in:simple,mirror',
                'testator_full_name' => 'required|string|max:255',
                'testator_address' => 'nullable|string|max:1000',
                'testator_date_of_birth' => 'nullable|date',
                'testator_occupation' => 'nullable|string|max:255',
                'domicile_confirmed' => 'nullable|in:england_wales,scotland,northern_ireland,other',
            ]);

            if ($refused = $this->refuseUnsupportedWillType($request, $validated['will_type'])) {
                return $refused;
            }

            $doc = $this->service->createDraft($request->user(), $validated);

            return response()->json([
                'success' => true,
                'message' => 'Will document draft created.',
                'data' => $doc,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create will document.', $e);
        }
    }

    /**
     * Get a specific will document.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $doc = WillDocument::where('user_id', $request->user()->id)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $doc,
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to load will document.', $e);
        }
    }

    /**
     * Save wizard step data.
     */
    public function update(SaveWillDocumentRequest $request, int $id): JsonResponse
    {
        try {
            $doc = WillDocument::where('user_id', $request->user()->id)
                ->findOrFail($id);

            $step = $request->input('step');

            // The intro step is where a will's type can change after creation —
            // the one other way into a one-sided will for a married user.
            if ($step === 'intro' && $request->filled('will_type')) {
                if ($refused = $this->refuseUnsupportedWillType($request, $request->input('will_type'))) {
                    return $refused;
                }
            }

            $updated = $this->service->updateStep($doc, $step, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Step saved.',
                'data' => $updated,
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save step data.', $e);
        }
    }

    /**
     * Mark a will document as complete.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        try {
            $doc = WillDocument::where('user_id', $request->user()->id)
                ->findOrFail($id);

            $completed = $this->service->markComplete($doc);

            return response()->json([
                'success' => true,
                'message' => 'Will document completed and saved.',
                'data' => $completed,
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to complete will document.', $e);
        }
    }

    /**
     * Generate a mirror will for the spouse.
     */
    public function generateMirror(Request $request, int $id): JsonResponse
    {
        try {
            $doc = WillDocument::where('user_id', $request->user()->id)
                ->findOrFail($id);

            $mirror = $this->service->generateMirrorWill($doc);

            return response()->json([
                'success' => true,
                'message' => 'Mirror will generated for spouse.',
                'data' => [
                    'primary' => $doc->fresh(),
                    'mirror' => $mirror,
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to generate mirror will.', $e);
        }
    }

    /**
     * Validate a will document and return warnings.
     */
    public function validateDocument(Request $request, int $id): JsonResponse
    {
        try {
            $doc = WillDocument::where('user_id', $request->user()->id)
                ->findOrFail($id);

            $warnings = $this->service->validateDocument($doc);

            return response()->json([
                'success' => true,
                'data' => [
                    'warnings' => $warnings,
                    'has_errors' => collect($warnings)->contains('severity', 'error'),
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to validate will document.', $e);
        }
    }

    /**
     * Soft-delete a will document draft.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $doc = WillDocument::where('user_id', $request->user()->id)
                ->findOrFail($id);

            $doc->delete();

            return response()->json([
                'success' => true,
                'message' => 'Will document deleted.',
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete will document.', $e);
        }
    }
}
