<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ConfirmExtractionRequest;
use App\Http\Requests\Documents\UploadDocumentRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Document;
use App\Services\Documents\DocumentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private DocumentProcessor $processor
    ) {}

    /**
     * List user's documents.
     * GET /api/documents
     */
    public function index(Request $request): JsonResponse
    {
        $documents = Document::where('user_id', $request->user()->id)
            ->with('latestExtraction')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * Upload and process a document.
     * POST /api/documents/upload
     */
    public function upload(UploadDocumentRequest $request): JsonResponse
    {
        \Log::info('[DocumentController] upload called', [
            'has_file' => $request->hasFile('document'),
            'document_type' => $request->input('document_type'),
            'user_id' => $request->user()->id,
        ]);

        try {
            $result = $this->processor->process(
                $request->file('document'),
                $request->user(),
                $request->input('document_type')
            );

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded and processed successfully',
                'data' => [
                    'document_id' => $result['document']->id,
                    'document_type' => $result['document']->document_type,
                    'detected_subtype' => $result['document']->detected_document_subtype,
                    'status' => $result['document']->status,
                    'extracted_fields' => $result['mapped_data'],
                    'field_confidence' => $result['extraction']->field_confidence,
                    'warnings' => $result['extraction']->warnings,
                    'validation_errors' => $result['validation_errors'],
                    'is_valid' => $result['is_valid'],
                    'target_model' => $result['target_model']
                        ? class_basename($result['target_model'])
                        : null,
                ],
            ], 201);
        } catch (\Exception $e) {
            \Log::error('[DocumentController] upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->safeErrorResponse('Document processing failed', $e);
        }
    }

    /**
     * Upload a document without processing.
     * POST /api/documents/upload-only
     */
    public function uploadOnly(UploadDocumentRequest $request): JsonResponse
    {
        try {
            $document = $this->processor->uploadOnly(
                $request->file('document'),
                $request->user(),
                $request->input('document_type')
            );

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'document_id' => $document->id,
                    'status' => $document->status,
                ],
            ], 201);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Upload failed', $e);
        }
    }

    /**
     * Get document details with extraction.
     * GET /api/documents/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $document = Document::where('user_id', $request->user()->id)
            ->with(['latestExtraction', 'logs'])
            ->findOrFail($id);

        $mappedData = $this->processor->getMappedData($document);

        return response()->json([
            'success' => true,
            'data' => [
                'document' => $document,
                'mapped_data' => $mappedData['fields'],
                'confidence' => $mappedData['confidence'],
                'warnings' => $mappedData['warnings'],
                'is_valid' => $mappedData['is_valid'],
                'validation_errors' => $mappedData['validation_errors'],
            ],
        ]);
    }

    /**
     * Get extraction results for pre-filling form.
     * GET /api/documents/{id}/extraction
     */
    public function getExtraction(Request $request, int $id): JsonResponse
    {
        $document = Document::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $extraction = $document->latestExtraction;

        if (! $extraction) {
            return response()->json([
                'success' => false,
                'message' => 'No extraction found for this document',
            ], 404);
        }

        $mappedData = $this->processor->getMappedData($document);

        return response()->json([
            'success' => true,
            'data' => [
                'document_type' => $document->document_type,
                'detected_subtype' => $document->detected_document_subtype,
                'extracted_fields' => $mappedData['fields'],
                'field_confidence' => $mappedData['confidence'],
                'warnings' => $mappedData['warnings'],
                'is_valid' => $mappedData['is_valid'],
                'validation_errors' => $mappedData['validation_errors'],
                'target_model' => $extraction->target_model
                    ? class_basename($extraction->target_model)
                    : null,
            ],
        ]);
    }

    /**
     * Confirm extraction and save to model.
     * POST /api/documents/{id}/confirm
     */
    public function confirm(ConfirmExtractionRequest $request, int $id): JsonResponse
    {
        $document = Document::where('user_id', $request->user()->id)
            ->findOrFail($id);

        try {
            $result = $this->processor->confirm(
                $document,
                $request->validated()['data'],
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Data saved successfully',
                'data' => [
                    'document_id' => $result['document']->id,
                    'model_type' => $result['model_type'],
                    'model_id' => $result['model']->id,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to save data', $e);
        }
    }

    /**
     * Re-extract data from document.
     * POST /api/documents/{id}/reprocess
     */
    public function reprocess(Request $request, int $id): JsonResponse
    {
        $document = Document::where('user_id', $request->user()->id)
            ->findOrFail($id);

        try {
            $result = $this->processor->reextract($document);

            return response()->json([
                'success' => true,
                'message' => 'Document reprocessed successfully',
                'data' => [
                    'document_id' => $result['document']->id,
                    'extracted_fields' => $result['mapped_data'],
                    'field_confidence' => $result['extraction']->field_confidence,
                    'warnings' => $result['extraction']->warnings,
                    'is_valid' => $result['is_valid'],
                    'validation_errors' => $result['validation_errors'],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Reprocessing failed', $e);
        }
    }

    /**
     * Delete a document.
     * DELETE /api/documents/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $document = Document::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $this->processor->delete($document, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully',
        ]);
    }

    /**
     * Get available document types.
     * GET /api/documents/types
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->processor->getAvailableTypes(),
        ]);
    }
}
