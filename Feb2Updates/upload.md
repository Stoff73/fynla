# Document Upload Feature - Complete Technical Map

**Generated:** February 2, 2026
**Based on:** Current codebase analysis (not markdown documentation)

---

## Table of Contents

1. [Overview](#overview)
2. [User Flow](#user-flow)
3. [Architecture Diagram](#architecture-diagram)
4. [File Inventory](#file-inventory)
5. [Frontend Components](#frontend-components)
6. [Frontend Service](#frontend-service)
7. [API Routes](#api-routes)
8. [Backend Controller](#backend-controller)
9. [Backend Services](#backend-services)
10. [Field Mappers](#field-mappers)
11. [Database Models](#database-models)
12. [LLM Prompts](#llm-prompts)
13. [Data Flow](#data-flow)
14. [Configuration](#configuration)

---

## Overview

The Document Upload feature allows users to upload financial documents (pension statements, insurance policies, investment statements, etc.) which are then processed by Claude AI to extract structured data. The extracted data is mapped to database models and presented to the user for review and confirmation.

**Key Technologies:**
- Frontend: Vue.js 3
- Backend: Laravel 10 (PHP 8.2)
- AI: Claude API (Anthropic) - Model: `claude-sonnet-4-5`
- Storage: Local disk (dev) / S3 (production)

---

## User Flow

```
1. USER: Opens upload modal from component (e.g., PensionList.vue)
   └─► DocumentUploadModal.vue displayed

2. USER: Drags/drops or selects file
   └─► UploadDropZone.vue validates file type and size
   └─► Images > 2MB auto-compressed to JPEG (max 2000px, 85% quality)
   └─► PDFs allowed up to 100MB

3. USER: Clicks "Upload & Analyse"
   └─► Frontend shows "Processing" state
   └─► POST /api/documents/upload

4. BACKEND: DocumentController::upload()
   └─► UploadDocumentRequest validates (20MB limit, allowed MIME types)
   └─► DocumentProcessor::process() called

5. BACKEND: DocumentProcessor orchestrates:
   a. DocumentUploadService::upload() - stores file, creates Document record
   b. AIExtractionService::extract() - sends to Claude API
   c. FieldMapper::map() - transforms AI response to model fields
   d. FieldMapper::validate() - checks required fields

6. CLAUDE API: Receives document + prompt
   └─► Returns JSON with extracted fields, confidence scores, warnings

7. BACKEND: Returns response with extracted_fields, confidence, warnings

8. FRONTEND: Shows "Review" step
   └─► User edits fields if needed
   └─► Confidence badges shown per field

9. USER: Clicks "Save [Document Type]"
   └─► POST /api/documents/{id}/confirm

10. BACKEND: DocumentProcessor::confirm()
    └─► Creates model record (DCPension, DBPension, etc.)
    └─► Updates Document status to "confirmed"

11. FRONTEND: Emits "saved" event, closes modal
    └─► Parent component refreshes data
```

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              FRONTEND (Vue.js)                               │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐     ┌──────────────────┐     ┌───────────────────┐    │
│  │ Parent Component │────►│DocumentUploadModal│────►│  UploadDropZone   │    │
│  │ (PensionList,   │     │                  │     │  - File validation │    │
│  │  PortfolioView) │     │  - Step wizard   │     │  - Image compress  │    │
│  └─────────────────┘     │  - Review fields │     └───────────────────┘    │
│                          │  - Save button   │                               │
│                          └────────┬─────────┘                               │
│                                   │                                          │
│                          ┌────────▼─────────┐                               │
│                          │ documentService.js│                               │
│                          │  - upload()       │                               │
│                          │  - confirm()      │                               │
│                          └────────┬─────────┘                               │
└───────────────────────────────────┼─────────────────────────────────────────┘
                                    │ HTTP
┌───────────────────────────────────▼─────────────────────────────────────────┐
│                              BACKEND (Laravel)                               │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────────┐                                                    │
│  │  DocumentController │                                                    │
│  │  - upload()         │                                                    │
│  │  - confirm()        │                                                    │
│  └──────────┬──────────┘                                                    │
│             │                                                               │
│  ┌──────────▼──────────┐                                                    │
│  │  DocumentProcessor  │◄──── Orchestrator                                  │
│  └──────────┬──────────┘                                                    │
│             │                                                               │
│  ┌──────────┴──────────────────────────────────────────┐                   │
│  │                                                      │                   │
│  ▼                                                      ▼                   │
│  ┌───────────────────┐  ┌───────────────────┐  ┌──────────────────┐       │
│  │DocumentUploadService│  │AIExtractionService│  │DocumentTypeDetector│       │
│  │ - Store file       │  │ - Build prompts   │  │ - Detect type     │       │
│  │ - Create record    │  │ - Call Claude API │  │ - Get target model│       │
│  └───────────────────┘  │ - Parse response  │  └──────────────────┘       │
│                         └─────────┬─────────┘                              │
│                                   │                                         │
│  ┌────────────────────────────────┴────────────────────────────────────┐   │
│  │                        FIELD MAPPERS                                 │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌────────────────┐            │   │
│  │  │DCPensionMapper│  │DBPensionMapper│  │LifeInsurance  │  ...       │   │
│  │  │              │  │              │  │    Mapper      │            │   │
│  │  └──────────────┘  └──────────────┘  └────────────────┘            │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                          SUPPORT SERVICES                             │  │
│  │  ┌─────────────────┐  ┌──────────────────┐  ┌──────────────────┐    │  │
│  │  │ImageResizeService│  │ExcelParserService│  │ (PDF Parser -    │    │  │
│  │  │ - Resize > 5MB  │  │ - XLSX/XLS/CSV   │  │  not implemented)│    │  │
│  │  └─────────────────┘  └──────────────────┘  └──────────────────┘    │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ HTTPS
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          CLAUDE API (Anthropic)                              │
│  Endpoint: https://api.anthropic.com/v1/messages                            │
│  Model: claude-sonnet-4-5                                                   │
│  Max Tokens: 4096                                                           │
│  Timeout: 120 seconds                                                       │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## File Inventory

### Frontend Components (Vue.js)

| File | Purpose |
|------|---------|
| `resources/js/components/Shared/DocumentUploadModal.vue` | Main modal with 3-step wizard |
| `resources/js/components/Shared/UploadDropZone.vue` | Drag-drop file selection, compression |
| `resources/js/components/Shared/ProcessingState.vue` | Loading/processing indicators |
| `resources/js/components/Shared/ConfidenceBadge.vue` | Confidence score display |
| `resources/js/services/documentService.js` | API wrapper |

### Backend Services (PHP)

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/DocumentController.php` | API endpoints |
| `app/Http/Requests/Documents/UploadDocumentRequest.php` | Request validation |
| `app/Services/Documents/DocumentProcessor.php` | Main orchestrator |
| `app/Services/Documents/AIExtractionService.php` | Claude API integration |
| `app/Services/Documents/DocumentUploadService.php` | File storage |
| `app/Services/Documents/DocumentTypeDetector.php` | Type/subtype detection |
| `app/Services/Documents/ImageResizeService.php` | Image compression for API |
| `app/Services/Documents/ExcelParserService.php` | Spreadsheet parsing |

### Field Mappers

| File | Target Model |
|------|--------------|
| `app/Services/Documents/FieldMappers/AbstractFieldMapper.php` | Base class |
| `app/Services/Documents/FieldMappers/FieldMapperInterface.php` | Interface |
| `app/Services/Documents/FieldMappers/DCPensionMapper.php` | `App\Models\DCPension` |
| `app/Services/Documents/FieldMappers/DBPensionMapper.php` | `App\Models\DBPension` |
| `app/Services/Documents/FieldMappers/LifeInsuranceMapper.php` | `App\Models\LifeInsurancePolicy` |
| `app/Services/Documents/FieldMappers/InvestmentAccountMapper.php` | `App\Models\Investment\InvestmentAccount` |

### Database Models

| File | Table |
|------|-------|
| `app/Models/Document.php` | `documents` |
| `app/Models/DocumentExtraction.php` | `document_extractions` |
| `app/Models/DocumentExtractionLog.php` | `document_extraction_logs` |

---

## Frontend Components

### DocumentUploadModal.vue

**Location:** `resources/js/components/Shared/DocumentUploadModal.vue`

**Props:**
```javascript
documentType: {
  type: String,
  default: null,  // 'pension_statement', 'insurance_policy', etc.
}
```

**Emits:**
- `close` - Modal closed
- `extracted` - Data extracted (before confirmation)
- `saved` - Data saved to database
- `manual-entry` - User chose manual entry

**Steps:**
1. `upload` - File selection
2. `processing` - Uploading and AI analysis
3. `review` - Edit extracted fields

**Key Data:**
```javascript
{
  selectedFile: null,
  uploadProgress: 0,
  processingStep: 'uploading', // 'uploading' | 'analysing' | 'extracting' | 'mapping'
  documentId: null,
  extractedFields: {},
  editedFields: {},
  fieldConfidence: {},
  extractionWarnings: [],
  detectedType: null,
  detectedSubtype: null,
}
```

### UploadDropZone.vue

**Location:** `resources/js/components/Shared/UploadDropZone.vue`

**Props:**
```javascript
acceptedTypes: {
  type: Array,
  default: () => ['.pdf', '.png', '.jpg', '.jpeg', '.webp', '.xlsx', '.xls', '.csv'],
},
maxSizeMB: {
  type: Number,
  default: 20,
}
```

**File Validation:**
- Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`, `image/webp`, `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, `application/vnd.ms-excel`, `text/csv`, `application/csv`
- PDFs: Up to 100MB (text extraction handles large files)
- Images: Up to 20MB (auto-compressed if > 2MB)

**Image Compression Logic:**
```javascript
compressImage(file) {
  // Resize to max 2000px on longest side
  // Convert to JPEG at 85% quality
  // Returns compressed File object
}
```

---

## Frontend Service

### documentService.js

**Location:** `resources/js/services/documentService.js`

```javascript
const documentService = {
  // List all user documents
  async getDocuments(page = 1)

  // Get available document types
  async getTypes()

  // Upload and process document
  async upload(file, documentType = null, onProgress = null)

  // Upload without processing
  async uploadOnly(file, documentType = null)

  // Get document details
  async getDocument(id)

  // Get extraction results
  async getExtraction(id)

  // Confirm and save to model
  async confirm(id, data)

  // Re-process document
  async reprocess(id)

  // Delete document
  async deleteDocument(id)
}
```

---

## API Routes

**File:** `routes/api.php` (lines 967-978)

```php
// Document Upload & AI Extraction routes (rate limited for security)
Route::middleware(['auth:sanctum', 'throttle:30,1'])->prefix('documents')->group(function () {
    Route::get('/', [DocumentController::class, 'index']);
    Route::get('/types', [DocumentController::class, 'types']);
    Route::post('/upload', [DocumentController::class, 'upload'])->middleware('throttle:10,1');
    Route::post('/upload-only', [DocumentController::class, 'uploadOnly'])->middleware('throttle:10,1');
    Route::get('/{id}', [DocumentController::class, 'show']);
    Route::get('/{id}/extraction', [DocumentController::class, 'getExtraction']);
    Route::post('/{id}/confirm', [DocumentController::class, 'confirm']);
    Route::post('/{id}/reprocess', [DocumentController::class, 'reprocess'])->middleware('throttle:5,1');
    Route::delete('/{id}', [DocumentController::class, 'destroy']);
});
```

**Rate Limits:**
- General document routes: 30 requests/minute
- Upload routes: 10 requests/minute
- Reprocess route: 5 requests/minute

---

## Backend Controller

### DocumentController.php

**Location:** `app/Http/Controllers/Api/DocumentController.php`

**Key Methods:**

#### `upload(UploadDocumentRequest $request)`
```php
// POST /api/documents/upload
// Uploads and processes document in one call
// Returns: document_id, document_type, detected_subtype, extracted_fields, field_confidence, warnings
```

#### `confirm(ConfirmExtractionRequest $request, int $id)`
```php
// POST /api/documents/{id}/confirm
// Saves confirmed data to target model (DCPension, DBPension, etc.)
// Returns: document_id, model_type, model_id
```

### UploadDocumentRequest.php

**Location:** `app/Http/Requests/Documents/UploadDocumentRequest.php`

**Validation Rules:**
```php
return [
    'document' => [
        'required',
        'file',
        'mimes:pdf,jpeg,jpg,png,webp,xlsx,xls,csv',
        'max:20480', // 20MB
    ],
    'document_type' => [
        'nullable',
        'in:pension_statement,insurance_policy,investment_statement,mortgage_statement,savings_statement,property_document',
    ],
];
```

---

## Backend Services

### DocumentProcessor.php

**Location:** `app/Services/Documents/DocumentProcessor.php`

The main orchestrator that coordinates the entire process.

```php
public function process(UploadedFile $file, User $user, ?string $expectedType = null): array
{
    return DB::transaction(function () use ($file, $user, $expectedType) {
        // 1. Upload document
        $document = $this->uploadService->upload($file, $user, $expectedType);

        // 2. Extract data via AI
        $extraction = $this->extractionService->extract($document);

        // 3. Map to model fields
        $mapper = $this->getMapper($document);
        $mappedData = $mapper ? $mapper->map($extraction->extracted_fields) : $extraction->extracted_fields;

        // 4. Validate
        $validationErrors = $mapper ? $mapper->validate($mappedData) : [];

        return [
            'document' => $document,
            'extraction' => $extraction,
            'mapped_data' => $mappedData,
            'validation_errors' => $validationErrors,
            'is_valid' => empty($validationErrors),
            'target_model' => $extraction->target_model,
        ];
    });
}
```

**Registered Mappers:**
```php
$this->mappers = [
    \App\Models\DCPension::class => new DCPensionMapper,
    \App\Models\DBPension::class => new DBPensionMapper,
    \App\Models\LifeInsurancePolicy::class => new LifeInsuranceMapper,
    \App\Models\Investment\InvestmentAccount::class => new InvestmentAccountMapper,
];
```

### AIExtractionService.php

**Location:** `app/Services/Documents/AIExtractionService.php`

**Configuration:**
```php
private const API_URL = 'https://api.anthropic.com/v1/messages';
private const MODEL = 'claude-sonnet-4-5';
private const MAX_TOKENS = 4096;
private const TIMEOUT_SECONDS = 120;
```

**Key Methods:**

#### `extract(Document $document): DocumentExtraction`
Main extraction method - determines document type and calls appropriate API method.

#### `callClaudeAPI(string $base64, string $mediaType, string $prompt): array`
Calls Claude Vision API with image/PDF content.

```php
// Request structure for images
$response = Http::withHeaders($headers)->timeout(self::TIMEOUT_SECONDS)->post(self::API_URL, [
    'model' => self::MODEL,
    'max_tokens' => self::MAX_TOKENS,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'image',  // or 'document' for PDFs
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mediaType,
                        'data' => $base64,
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => $prompt,
                ],
            ],
        ],
    ],
]);
```

#### `callClaudeAPIWithText(string $textContent, string $prompt): array`
Calls Claude API with text content (for spreadsheets).

### DocumentUploadService.php

**Location:** `app/Services/Documents/DocumentUploadService.php`

**Configuration:**
```php
private const ALLOWED_MIME_TYPES = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'text/csv',
    'application/csv',
];

private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
```

**Storage:**
- Development: `local` disk
- Production: `s3` disk
- Path: `documents/{user_id}/{uuid}.{extension}`

### ImageResizeService.php

**Location:** `app/Services/Documents/ImageResizeService.php`

Resizes images to meet Claude API limits (5MB max).

**Configuration:**
```php
private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;    // 5MB
private const MAX_DIMENSION = 1568;                 // Recommended max dimension
private const TARGET_SIZE = 4.5 * 1024 * 1024;     // Target with buffer
private const MIN_QUALITY = 40;                     // Minimum JPEG quality
```

### ExcelParserService.php

**Location:** `app/Services/Documents/ExcelParserService.php`

Converts spreadsheets to text for AI processing.

**Configuration:**
```php
private const MAX_ROWS = 500;
private const MAX_COLS = 26; // A-Z
```

### DocumentTypeDetector.php

**Location:** `app/Services/Documents/DocumentTypeDetector.php`

Maps document types to target models and subtypes.

**Type-to-Model Mapping:**
```php
private const TYPE_MODEL_MAP = [
    'pension_statement' => [
        'dc_pension' => \App\Models\DCPension::class,
        'db_pension' => \App\Models\DBPension::class,
        'state_pension' => \App\Models\StatePension::class,
    ],
    'insurance_policy' => [
        'life_insurance' => \App\Models\LifeInsurancePolicy::class,
        'critical_illness' => \App\Models\CriticalIllnessPolicy::class,
        'income_protection' => \App\Models\IncomeProtectionPolicy::class,
        'disability' => \App\Models\DisabilityPolicy::class,
        'sickness_illness' => \App\Models\SicknessIllnessPolicy::class,
    ],
    'investment_statement' => [
        'investment_account' => \App\Models\Investment\InvestmentAccount::class,
    ],
    'mortgage_statement' => [
        'mortgage' => \App\Models\Mortgage::class,
    ],
    'savings_statement' => [
        'savings_account' => \App\Models\SavingsAccount::class,
        'cash_account' => \App\Models\CashAccount::class,
    ],
    'property_document' => [
        'property' => \App\Models\Property::class,
    ],
];
```

---

## Field Mappers

### AbstractFieldMapper.php

Base class providing common transformation methods:

```php
protected function parseDate(?string $date): ?string          // Y-m-d format
protected function parseDecimal(mixed $value): ?float         // Currency/numbers
protected function parsePercentage(mixed $value): ?float      // Returns decimal (5% = 0.05)
protected function parseInt(mixed $value): ?int               // Integer values
protected function parseBool(mixed $value): ?bool             // Boolean values
protected function normalizeString(?string $value): ?string   // Trim strings
protected function parseEnum(mixed $value, array $allowed): ?string // Enum validation
```

### DCPensionMapper.php

**Required Fields:** `pension_type`, `current_fund_value`

**Extracted Fields:**
- provider, scheme_name, member_number
- pension_type (occupational, sipp, personal, stakeholder)
- current_fund_value, annual_salary
- employee_contribution_percent, employer_contribution_percent
- monthly_contribution_amount, platform_fee_percent
- retirement_age, projected_value_at_retirement, investment_strategy

### DBPensionMapper.php

**Required Fields:** `scheme_name`, `scheme_type`, `accrued_annual_pension`

**Extracted Fields:**
- scheme_name, scheme_type (final_salary, career_average, public_sector)
- accrued_annual_pension, pensionable_service_years, pensionable_salary
- normal_retirement_age, spouse_pension_percent, lump_sum_entitlement
- inflation_protection (cpi, rpi, fixed, none), revaluation_method

### LifeInsuranceMapper.php

**Required Fields:** `provider`, `life_policy_type`, `sum_assured`, `premium_amount`, `premium_frequency`

**Extracted Fields:**
- provider, policy_number
- policy_type (term, decreasing_term, level_term, whole_of_life, family_income_benefit)
- sum_assured, premium_amount, premium_frequency
- policy_start_date, policy_end_date, policy_term_years
- indexation_rate, in_trust, beneficiaries

### InvestmentAccountMapper.php

**Required Fields:** `provider`, `account_type`, `current_value`

**Extracted Fields:**
- provider, account_number, platform
- account_type (isa, gia, nsi, onshore_bond, offshore_bond, vct, eis, other)
- current_value, contributions_ytd
- isa_subscription_current_year, platform_fee_percent

**Holdings (nested array):**
- security_name, ticker, isin
- asset_type (uk_equity, us_equity, international_equity, fund, etf, bond, cash, alternative, property)
- quantity, current_price, current_value

---

## Database Models

### Document Model

**Table:** `documents`

**Status Constants:**
```php
STATUS_UPLOADED = 'uploaded';
STATUS_PROCESSING = 'processing';
STATUS_EXTRACTED = 'extracted';
STATUS_REVIEW_PENDING = 'review_pending';
STATUS_CONFIRMED = 'confirmed';
STATUS_FAILED = 'failed';
STATUS_ARCHIVED = 'archived';
```

**Type Constants:**
```php
TYPE_PENSION_STATEMENT = 'pension_statement';
TYPE_INSURANCE_POLICY = 'insurance_policy';
TYPE_INVESTMENT_STATEMENT = 'investment_statement';
TYPE_MORTGAGE_STATEMENT = 'mortgage_statement';
TYPE_SAVINGS_STATEMENT = 'savings_statement';
TYPE_PROPERTY_DOCUMENT = 'property_document';
TYPE_UNKNOWN = 'unknown';
```

**Fields:**
- `id`, `user_id`
- `original_filename`, `stored_filename`
- `disk`, `path`, `mime_type`, `file_size`
- `document_type`, `detected_document_subtype`, `detection_confidence`
- `status`, `error_message`
- `processed_at`, `confirmed_at`
- `created_at`, `updated_at`, `deleted_at`

### DocumentExtraction Model

**Table:** `document_extractions`

**Fields:**
- `id`, `document_id`
- `extraction_version`
- `model_used` (e.g., "claude-sonnet-4-5")
- `input_tokens`, `output_tokens`
- `raw_response` (full API response, hidden from JSON)
- `extracted_fields` (JSON - the extracted data)
- `field_confidence` (JSON - confidence per field)
- `warnings` (JSON - extraction warnings)
- `target_model` (e.g., "App\Models\DCPension")
- `target_model_id` (ID of created model record)
- `is_valid`, `validation_errors` (JSON)

### DocumentExtractionLog Model

**Table:** `document_extraction_logs`

**Action Constants:**
```php
ACTION_UPLOADED = 'uploaded';
ACTION_EXTRACTION_STARTED = 'extraction_started';
ACTION_EXTRACTION_COMPLETED = 'extraction_completed';
ACTION_EXTRACTION_FAILED = 'extraction_failed';
ACTION_FIELDS_MODIFIED = 'fields_modified';
ACTION_CONFIRMED = 'confirmed';
ACTION_SAVED_TO_MODEL = 'saved_to_model';
ACTION_DELETED = 'deleted';
```

**Fields:**
- `id`, `document_id`, `user_id`
- `action`
- `metadata` (JSON - action-specific data)
- `ip_address`, `user_agent`
- `created_at`, `updated_at`

---

## LLM Prompts

### Base Prompt (All Document Types)

**File:** `app/Services/Documents/AIExtractionService.php` - `getBasePrompt()`

```
You are a financial document extraction specialist for a UK financial planning application.

IMPORTANT RULES:
1. Extract all visible data from this document
2. Return data as valid JSON with the exact field names specified
3. For each field, provide a confidence score (0.0 to 1.0)
4. If a field is not found, use null
5. Use ISO 8601 format for dates (YYYY-MM-DD)
6. Use numeric values without currency symbols or commas
7. Percentages as decimals (5% = 0.05, not 5)
8. Preserve exact provider/scheme names as written
9. Note any warnings or ambiguities

UK CONTEXT:
- Tax year runs April 6 to April 5 (e.g., 2024/25 = 6 April 2024 to 5 April 2025)
- Currency is GBP (£)
- Date formats on documents are typically DD/MM/YYYY

Response format:
{
  "document_type": "detected type",
  "document_subtype": "specific subtype",
  "fields": { ... extracted field values ... },
  "confidence": { ... confidence per field (0.0-1.0) ... },
  "warnings": [ ... any extraction warnings ... ]
}
```

### Pension Statement Prompt

**File:** `app/Services/Documents/AIExtractionService.php` - `getPensionPrompt()`

```
PENSION DOCUMENT - Identify if DC Pension, DB Pension, or State Pension and extract:

For DC Pensions (workplace, SIPP, personal, stakeholder):
- provider: Company administering the pension
- scheme_name: Name of the pension scheme
- member_number: Member/policy reference number
- pension_type: One of [occupational, sipp, personal, stakeholder]
- current_fund_value: Current pot value (number only)
- annual_salary: Pensionable salary if shown
- employee_contribution_percent: Employee % as decimal (5% = 0.05)
- employer_contribution_percent: Employer % as decimal
- monthly_contribution_amount: Fixed monthly amount (for personal pensions)
- platform_fee_percent: Annual fee as decimal (0.45% = 0.0045)
- retirement_age: Target retirement age
- projected_value_at_retirement: Projected value if shown
- investment_strategy: Fund/strategy description

For DB Pensions (final salary, career average, public sector):
- scheme_name: Name of the DB scheme
- scheme_type: One of [final_salary, career_average, public_sector]
- accrued_annual_pension: Annual pension entitlement
- pensionable_service_years: Years of service (can be decimal)
- pensionable_salary: Pensionable salary
- normal_retirement_age: Scheme NRA
- spouse_pension_percent: Spouse pension as decimal (50% = 0.50)
- lump_sum_entitlement: Tax-free lump sum
- inflation_protection: One of [cpi, rpi, fixed, none]
- revaluation_method: How benefits revalue

For State Pension (DWP forecast):
- ni_years_completed: Qualifying years on record
- ni_years_required: Years needed for full pension (typically 35)
- state_pension_forecast_annual: Annual forecast amount
- state_pension_age: Age when eligible

Set document_subtype to: dc_pension, db_pension, or state_pension
```

### Insurance Policy Prompt

**File:** `app/Services/Documents/AIExtractionService.php` - `getInsurancePrompt()`

```
INSURANCE POLICY - Identify policy type and extract:

For Life Insurance:
- provider: Insurance company name
- policy_number: Policy reference
- policy_type: One of [term, decreasing_term, level_term, whole_of_life, family_income_benefit]
- sum_assured: Death benefit amount
- premium_amount: Premium payment
- premium_frequency: One of [monthly, quarterly, annually]
- policy_start_date: Inception date (YYYY-MM-DD)
- policy_end_date: Expiry date (YYYY-MM-DD) - null for whole of life
- policy_term_years: Term in years
- indexation_rate: Annual increase as decimal
- in_trust: true/false if written in trust
- beneficiaries: Named beneficiaries if shown

For Critical Illness:
- provider: Insurance company
- policy_number: Policy reference
- policy_type: One of [standalone, accelerated, additional]
- sum_assured: CI benefit amount
- premium_amount: Premium payment
- premium_frequency: One of [monthly, quarterly, annually]
- policy_start_date: Start date (YYYY-MM-DD)
- policy_term_years: Term in years

For Income Protection:
- provider: Insurance company
- policy_number: Policy reference
- benefit_amount: Monthly/weekly benefit
- benefit_frequency: One of [monthly, weekly]
- deferred_period_weeks: Waiting period in weeks
- benefit_period_months: Benefit duration (null if to retirement)
- premium_amount: Premium payment
- policy_start_date: Start date (YYYY-MM-DD)

Set document_subtype to: life_insurance, critical_illness, or income_protection
```

### Investment Statement Prompt

**File:** `app/Services/Documents/AIExtractionService.php` - `getInvestmentPrompt()`

```
INVESTMENT STATEMENT - Extract account and holdings:

Account Details:
- provider: Platform/provider name (e.g., Hargreaves Lansdown, Vanguard)
- account_number: Account reference
- account_type: One of [isa, gia, nsi, onshore_bond, offshore_bond, vct, eis, other]
- platform: Platform name if different from provider
- current_value: Total account value
- contributions_ytd: Contributions this tax year
- isa_subscription_current_year: ISA contributions this year (for ISAs, max £20,000)
- platform_fee_percent: Annual platform fee as decimal
- tax_year: Tax year of statement (YYYY/YY format)

Holdings (array of investments):
Each holding should have:
- security_name: Name of the investment
- ticker: Stock ticker if shown
- isin: ISIN code if shown
- asset_type: One of [uk_equity, us_equity, international_equity, fund, etf, bond, cash, alternative, property]
- quantity: Number of units/shares
- current_price: Price per unit
- current_value: Total value

Set document_subtype to: investment_account
```

### Mortgage Statement Prompt

**File:** `app/Services/Documents/AIExtractionService.php` - `getMortgagePrompt()`

```
MORTGAGE STATEMENT - Extract:

- lender_name: Mortgage lender
- mortgage_account_number: Account reference
- mortgage_type: One of [repayment, interest_only, mixed]
- original_loan_amount: Original loan amount
- outstanding_balance: Current balance
- interest_rate: Current rate as decimal (3.5% = 0.035)
- rate_type: One of [fixed, variable, tracker, discount, mixed]
- rate_fix_end_date: Fixed rate end date (YYYY-MM-DD) if applicable
- monthly_payment: Monthly payment amount
- start_date: Mortgage start date (YYYY-MM-DD)
- maturity_date: Mortgage end date (YYYY-MM-DD)
- remaining_term_months: Months remaining

Set document_subtype to: mortgage
```

### Savings Statement Prompt

**File:** `app/Services/Documents/AIExtractionService.php` - `getSavingsPrompt()`

```
SAVINGS/BANK STATEMENT - Extract:

- institution: Bank/building society name
- account_number: Account number (last 4 digits for security)
- account_type: Type of savings account
- current_balance: Current balance
- interest_rate: Interest rate as decimal (AER)
- access_type: One of [immediate, notice, fixed]
- notice_period_days: Notice period if applicable
- maturity_date: Maturity date if fixed term (YYYY-MM-DD)
- is_isa: true/false if this is a Cash ISA
- isa_subscription_year: Tax year of subscription (YYYY/YY)
- isa_subscription_amount: Amount subscribed this year

Set document_subtype to: savings_account or cash_account
```

### Unknown Type Prompt

**File:** `app/Services/Documents/AIExtractionService.php` - `getUnknownTypePrompt()`

```
UNKNOWN DOCUMENT TYPE - Please analyze and:

1. Identify the document type from:
   - pension_statement (DC pension, DB pension, or State Pension)
   - insurance_policy (life, critical illness, income protection)
   - investment_statement (ISA, GIA, bonds, etc.)
   - mortgage_statement
   - savings_statement

2. Set document_subtype to the specific type:
   - dc_pension, db_pension, state_pension
   - life_insurance, critical_illness, income_protection
   - investment_account
   - mortgage
   - savings_account, cash_account

3. Extract all relevant fields for that document type following the patterns above.

If you cannot determine the document type with confidence, set document_type to "unknown".
```

---

## Data Flow

### Upload Flow

```
1. Frontend: FormData with file + document_type
   └─► POST /api/documents/upload

2. UploadDocumentRequest: Validates file (mimes, size)

3. DocumentController::upload()
   └─► DocumentProcessor::process()

4. DocumentProcessor::process() (in DB transaction):
   a. DocumentUploadService::upload()
      - Validates file
      - Generates UUID filename
      - Stores to disk (local/s3)
      - Creates Document record (status: 'uploaded')
      - Logs: 'uploaded'

   b. AIExtractionService::extract()
      - Updates Document status to 'processing'
      - Logs: 'extraction_started'
      - Determines file type (spreadsheet vs image/PDF)

      For spreadsheets:
        - ExcelParserService::parseFromContent()
        - callClaudeAPIWithText()

      For images/PDFs:
        - DocumentUploadService::getBase64()
        - ImageResizeService::processForClaudeAPI() (if > 5MB)
        - callClaudeAPI()

      - Parses JSON response
      - DocumentTypeDetector::detect() (if type unknown)
      - Creates DocumentExtraction record
      - Updates Document status to 'extracted'
      - Logs: 'extraction_completed'

   c. Get appropriate FieldMapper
      - Maps extracted fields to model fields
      - Validates required fields

   d. Update DocumentExtraction (is_valid, validation_errors)
   e. Update Document status to 'review_pending'

5. Return response:
   {
     success: true,
     data: {
       document_id, document_type, detected_subtype,
       extracted_fields, field_confidence, warnings,
       validation_errors, is_valid, target_model
     }
   }
```

### Confirmation Flow

```
1. Frontend: { data: editedFields }
   └─► POST /api/documents/{id}/confirm

2. DocumentController::confirm()
   └─► DocumentProcessor::confirm()

3. DocumentProcessor::confirm() (in DB transaction):
   a. Get Document and latest DocumentExtraction
   b. Get target model class from extraction
   c. Merge confirmed data with user_id
   d. Create model instance (e.g., DCPension::create())
   e. Update DocumentExtraction (target_model_id)
   f. Update Document status to 'confirmed'
   g. Logs: 'confirmed', 'saved_to_model'

4. Return response:
   {
     success: true,
     data: { document_id, model_type, model_id }
   }
```

---

## Configuration

### Environment Variables

```env
# Required
ANTHROPIC_API_KEY=sk-ant-...

# Storage (production)
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
```

### config/services.php

```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
],
```

### Rate Limiting

- Document routes: 30 requests/minute
- Upload routes: 10 requests/minute
- Reprocess route: 5 requests/minute

---

## Current Limitations

1. **No PDF Text Extraction:** PDFs are sent as base64 images to Claude, which is expensive for large documents. The `smalot/pdfparser` integration mentioned in deploy2.md is not yet implemented in the codebase.

2. **No Noise Filtering:** All document content is sent to Claude. No pre-processing to filter out T&Cs, disclaimers, or marketing content.

3. **Limited Mappers:** Only 4 mappers implemented (DCPension, DBPension, LifeInsurance, InvestmentAccount). Missing: CriticalIllness, IncomeProtection, Savings, Mortgage.

4. **No Image Compression on Backend:** Backend relies on frontend compression. If frontend compression fails, large images may exceed API limits.

5. **Single Model per Document:** System assumes one model per document. Investment statements with multiple holdings only extract account-level data, not individual holdings.

---

## Components Using DocumentUploadModal

The modal is imported and used in these components:

- `resources/js/components/Savings/CurrentSituation.vue`
- `resources/js/components/Investment/PortfolioOverview.vue`
- `resources/js/components/Protection/CurrentSituation.vue`
- `resources/js/components/NetWorth/InvestmentList.vue`
- `resources/js/components/NetWorth/PensionList.vue`
- `resources/js/components/Onboarding/steps/AssetsStep.vue`
- `resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue`
- `resources/js/views/Trusts/TrustsDashboard.vue`
- `resources/js/views/Retirement/RetirementReadiness.vue`
