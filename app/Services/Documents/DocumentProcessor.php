<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentExtractionLog;
use App\Models\User;
use App\Services\Documents\FieldMappers\DBPensionMapper;
use App\Services\Documents\FieldMappers\DCPensionMapper;
use App\Services\Documents\FieldMappers\FieldMapperInterface;
use App\Services\Documents\FieldMappers\InvestmentAccountMapper;
use App\Services\Documents\FieldMappers\LifeInsuranceMapper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DocumentProcessor
{
    /**
     * Registered field mappers.
     */
    private array $mappers = [];

    public function __construct(
        private readonly DocumentUploadService $uploadService,
        private readonly AIExtractionService $extractionService,
        private readonly DocumentTypeDetector $typeDetector,
    ) {
        $this->registerMappers();
    }

    /**
     * Process a document: upload, extract, validate.
     */
    public function process(
        UploadedFile $file,
        User $user,
        ?string $expectedType = null
    ): array {
        return DB::transaction(function () use ($file, $user, $expectedType) {
            // 1. Upload document
            $document = $this->uploadService->upload($file, $user, $expectedType);

            // 2. Extract data via AI
            $extraction = $this->extractionService->extract($document);

            // 3. Map to model fields
            $mapper = $this->getMapper($document);
            $mappedData = $mapper
                ? $mapper->map($extraction->extracted_fields)
                : $extraction->extracted_fields;

            // 4. Validate
            $validationErrors = $mapper
                ? $mapper->validate($mappedData)
                : [];

            $isValid = empty($validationErrors);

            $extraction->update([
                'is_valid' => $isValid,
                'validation_errors' => $validationErrors ?: null,
            ]);

            $document->update(['status' => Document::STATUS_REVIEW_PENDING]);

            return [
                'document' => $document->fresh(),
                'extraction' => $extraction,
                'mapped_data' => $mappedData,
                'validation_errors' => $validationErrors,
                'is_valid' => $isValid,
                'target_model' => $extraction->target_model,
            ];
        });
    }

    /**
     * Upload a document without extraction (for deferred processing).
     */
    public function uploadOnly(
        UploadedFile $file,
        User $user,
        ?string $expectedType = null
    ): Document {
        return $this->uploadService->upload($file, $user, $expectedType);
    }

    /**
     * Extract data from an already uploaded document.
     */
    public function extractOnly(Document $document): array
    {
        $extraction = $this->extractionService->extract($document);

        $mapper = $this->getMapper($document);
        $mappedData = $mapper
            ? $mapper->map($extraction->extracted_fields)
            : $extraction->extracted_fields;

        $validationErrors = $mapper
            ? $mapper->validate($mappedData)
            : [];

        $isValid = empty($validationErrors);

        $extraction->update([
            'is_valid' => $isValid,
            'validation_errors' => $validationErrors ?: null,
        ]);

        $document->update(['status' => Document::STATUS_REVIEW_PENDING]);

        return [
            'document' => $document->fresh(),
            'extraction' => $extraction,
            'mapped_data' => $mappedData,
            'validation_errors' => $validationErrors,
            'is_valid' => $isValid,
            'target_model' => $extraction->target_model,
        ];
    }

    /**
     * Confirm extraction and save to target model.
     */
    public function confirm(
        Document $document,
        array $confirmedData,
        User $user
    ): array {
        return DB::transaction(function () use ($document, $confirmedData, $user) {
            $extraction = $document->latestExtraction;

            if (! $extraction) {
                throw new RuntimeException('No extraction found for document');
            }

            $modelClass = $extraction->target_model;

            if (! $modelClass || ! class_exists($modelClass)) {
                throw new RuntimeException('Invalid target model: '.($modelClass ?? 'null'));
            }

            // Merge confirmed data with user_id
            $confirmedData['user_id'] = $user->id;

            // Create the model
            $model = $modelClass::create($confirmedData);

            // Update extraction with model reference
            $extraction->update([
                'target_model_id' => $model->id,
            ]);

            // Update document status
            $document->update([
                'status' => Document::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);

            // Log confirmation
            DocumentExtractionLog::log(
                $document,
                $user,
                DocumentExtractionLog::ACTION_CONFIRMED
            );

            DocumentExtractionLog::log(
                $document,
                $user,
                DocumentExtractionLog::ACTION_SAVED_TO_MODEL,
                [
                    'model_class' => $modelClass,
                    'model_id' => $model->id,
                ]
            );

            return [
                'document' => $document->fresh(),
                'model' => $model,
                'model_type' => class_basename($modelClass),
            ];
        });
    }

    /**
     * Re-extract data from a document.
     */
    public function reextract(Document $document): array
    {
        return $this->extractOnly($document);
    }

    /**
     * Delete a document.
     */
    public function delete(Document $document, User $user): bool
    {
        return $this->uploadService->delete($document, $user);
    }

    /**
     * Get mapped data for review.
     */
    public function getMappedData(Document $document): array
    {
        $extraction = $document->latestExtraction;

        if (! $extraction) {
            return [
                'fields' => [],
                'confidence' => [],
                'warnings' => [],
            ];
        }

        $mapper = $this->getMapper($document);
        $mappedData = $mapper
            ? $mapper->map($extraction->extracted_fields)
            : $extraction->extracted_fields;

        return [
            'fields' => $mappedData,
            'confidence' => $extraction->field_confidence ?? [],
            'warnings' => $extraction->warnings ?? [],
            'is_valid' => $extraction->is_valid,
            'validation_errors' => $extraction->validation_errors ?? [],
        ];
    }

    /**
     * Get available document types for upload.
     */
    public function getAvailableTypes(): array
    {
        return [
            Document::TYPE_PENSION_STATEMENT => 'Pension Statement',
            Document::TYPE_INSURANCE_POLICY => 'Insurance Policy',
            Document::TYPE_INVESTMENT_STATEMENT => 'Investment Statement',
            Document::TYPE_MORTGAGE_STATEMENT => 'Mortgage Statement',
            Document::TYPE_SAVINGS_STATEMENT => 'Savings Statement',
        ];
    }

    /**
     * Get the appropriate mapper for a document.
     */
    private function getMapper(Document $document): ?FieldMapperInterface
    {
        $targetModel = $this->typeDetector->getTargetModel($document);

        return $this->mappers[$targetModel] ?? null;
    }

    /**
     * Register all field mappers.
     */
    private function registerMappers(): void
    {
        $this->mappers = [
            \App\Models\DCPension::class => new DCPensionMapper,
            \App\Models\DBPension::class => new DBPensionMapper,
            \App\Models\LifeInsurancePolicy::class => new LifeInsuranceMapper,
            \App\Models\Investment\InvestmentAccount::class => new InvestmentAccountMapper,
            \App\Models\Property::class => new FieldMappers\PropertyMapper,
            \App\Models\SavingsAccount::class => new FieldMappers\SavingsAccountMapper,
            \App\Models\Mortgage::class => new FieldMappers\MortgageMapper,
        ];
    }
}
