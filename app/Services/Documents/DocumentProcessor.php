<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\DCPension;
use App\Models\Document;
use App\Models\DocumentExtractionLog;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Documents\FieldMappers\DBPensionMapper;
use App\Services\Documents\FieldMappers\DCPensionMapper;
use App\Services\Documents\FieldMappers\FieldMapperInterface;
use App\Services\Documents\FieldMappers\InvestmentAccountMapper;
use App\Services\Documents\FieldMappers\LifeInsuranceMapper;
use App\Services\Documents\FieldMappers\ProtectionMapper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * Process an Excel workbook: parse sheets, extract per-sheet, return for review.
     */
    public function processExcel(UploadedFile $file, User $user): array
    {
        return DB::transaction(function () use ($file, $user) {
            $document = $this->uploadService->upload($file, $user, Document::TYPE_UNKNOWN);
            $document->update(['status' => Document::STATUS_PROCESSING]);

            $excelParser = app(ExcelParserService::class);
            $filePath = storage_path('app/'.$document->path);
            $sheets = $excelParser->parseToSheets($filePath);

            if (empty($sheets)) {
                $document->update(['status' => Document::STATUS_FAILED, 'error_message' => 'No data sheets found']);
                throw new RuntimeException('No financial data found in this workbook.');
            }

            $sheetResults = [];
            foreach ($sheets as $sheet) {
                try {
                    $extracted = $this->extractionService->extractSheet(
                        $sheet['name'],
                        $sheet['content'],
                    );

                    $extraction = \App\Models\DocumentExtraction::create([
                        'document_id' => $document->id,
                        'extraction_version' => 1,
                        'model_used' => config('services.xai.vision_model', 'grok-4-1-fast-non-reasoning'),
                        'raw_response' => json_encode($extracted),
                        'extracted_fields' => $extracted,
                        'field_confidence' => $extracted['confidence'] ?? [],
                        'warnings' => $extracted['warnings'] ?? [],
                        'is_valid' => true,
                    ]);

                    $sheetResults[] = [
                        'extraction_id' => $extraction->id,
                        'sheet_name' => $sheet['name'],
                        'row_count' => $sheet['row_count'],
                        'category' => $extracted['category'] ?? 'ignore',
                        'category_confidence' => $extracted['category_confidence'] ?? 0.0,
                        'account' => $extracted['account'] ?? [],
                        'holdings' => $extracted['holdings'] ?? [],
                        'properties' => $extracted['properties'] ?? [],
                        'policies' => $extracted['policies'] ?? [],
                        'confidence' => $extracted['confidence'] ?? [],
                        'warnings' => $extracted['warnings'] ?? [],
                    ];
                } catch (\Exception $e) {
                    Log::warning("[DocumentProcessor] Sheet extraction failed: {$sheet['name']}", [
                        'error' => $e->getMessage(),
                    ]);
                    $sheetResults[] = [
                        'extraction_id' => null,
                        'sheet_name' => $sheet['name'],
                        'row_count' => $sheet['row_count'],
                        'category' => 'error',
                        'error' => 'Failed to process this sheet',
                    ];
                }
            }

            $document->update(['status' => Document::STATUS_REVIEW_PENDING]);

            // Auto-match accounts for investment/pension sheets
            $importService = app(HoldingsImportService::class);
            foreach ($sheetResults as &$result) {
                if (in_array($result['category'], ['investment_holdings', 'pension_holdings'])) {
                    $match = $importService->findMatchingAccount($user, $result['category'], $result['account'] ?? []);
                    $result['matched_account'] = $match ? [
                        'id' => $match->id,
                        'name' => $match->provider ?? $match->scheme_name ?? 'Unknown',
                        'type' => class_basename($match),
                    ] : null;

                    if ($match && ! empty($result['holdings'])) {
                        $result['holdings'] = $importService->diffHoldings($match, $result['holdings']);
                    }
                } else {
                    $result['matched_account'] = null;
                }
            }
            unset($result);

            return [
                'document' => $document->fresh(),
                'sheets' => $sheetResults,
            ];
        });
    }

    /**
     * Confirm Excel import — create/update accounts and holdings from confirmed sheet data.
     */
    public function confirmExcel(Document $document, array $confirmedSheets, User $user): array
    {
        return DB::transaction(function () use ($document, $confirmedSheets, $user) {
            $importService = app(HoldingsImportService::class);
            $results = [];

            foreach ($confirmedSheets as $sheet) {
                $category = $sheet['category'] ?? 'ignore';
                if ($category === 'ignore' || $category === 'error') {
                    continue;
                }

                $accountId = $sheet['matched_account_id'] ?? null;
                $createNew = $sheet['create_new'] ?? false;

                if ($category === 'investment_holdings' || $category === 'pension_holdings') {
                    $account = null;

                    if ($accountId) {
                        $modelClass = $category === 'investment_holdings'
                            ? InvestmentAccount::class
                            : DCPension::class;
                        $account = $modelClass::where('user_id', $user->id)->find($accountId);
                    }

                    if (! $account && ($createNew || ! $accountId)) {
                        $accountData = $sheet['account'] ?? [];
                        $accountData['user_id'] = $user->id;

                        if ($category === 'investment_holdings') {
                            $account = InvestmentAccount::create($accountData);
                        } else {
                            $account = DCPension::create($accountData);
                        }
                    }

                    if ($account && ! empty($sheet['holdings'])) {
                        $holdingResults = $importService->applyHoldings($account, $sheet['holdings']);
                        $results[] = [
                            'sheet_name' => $sheet['sheet_name'],
                            'category' => $category,
                            'account_id' => $account->id,
                            'account_type' => class_basename($account),
                            'holdings' => $holdingResults,
                        ];
                    }
                } elseif ($category === 'cash_savings') {
                    $mapper = new FieldMappers\SavingsAccountMapper;
                    $mapped = $mapper->map($sheet['account'] ?? []);
                    $mapped['user_id'] = $user->id;
                    $model = \App\Models\SavingsAccount::create($mapped);
                    $results[] = [
                        'sheet_name' => $sheet['sheet_name'],
                        'category' => $category,
                        'model_type' => 'SavingsAccount',
                        'model_id' => $model->id,
                    ];
                } elseif ($category === 'property') {
                    $mapper = new FieldMappers\PropertyMapper;
                    foreach ($sheet['properties'] ?? [$sheet['account'] ?? []] as $propertyData) {
                        $mapped = $mapper->map($propertyData);
                        $mapped['user_id'] = $user->id;
                        $model = \App\Models\Property::create($mapped);
                        $results[] = [
                            'sheet_name' => $sheet['sheet_name'],
                            'category' => $category,
                            'model_type' => 'Property',
                            'model_id' => $model->id,
                        ];
                    }
                } elseif ($category === 'protection') {
                    $mapper = new ProtectionMapper;
                    foreach ($sheet['policies'] ?? [$sheet['account'] ?? []] as $policyData) {
                        $mapped = $mapper->map($policyData);
                        $mapped['user_id'] = $user->id;
                        $policyType = $mapped['policy_type'] ?? 'term';
                        $modelClass = $mapper->getModelClassForType($policyType);
                        $model = $modelClass::create($mapped);
                        $results[] = [
                            'sheet_name' => $sheet['sheet_name'],
                            'category' => $category,
                            'model_type' => class_basename($modelClass),
                            'model_id' => $model->id,
                        ];
                    }
                }
            }

            $document->update([
                'status' => Document::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);

            DocumentExtractionLog::log($document, $user, DocumentExtractionLog::ACTION_CONFIRMED, [
                'import_type' => 'excel',
                'sheets_processed' => count($results),
            ]);

            return [
                'document' => $document->fresh(),
                'results' => $results,
            ];
        });
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
