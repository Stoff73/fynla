# Document Management Module - Current State Documentation

**Last Updated:** 2026-02-19
**Module Version:** Part of Fynla v0.7.0
**Status:** Functional with AI-powered extraction for pension, insurance, and investment documents. Upload pipeline complete; confirm-to-model flow active for 4 mapper types.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Database Schema](#2-database-schema)
3. [Models](#3-models)
4. [Controller](#4-controller)
5. [Agent](#5-agent)
6. [Services](#6-services)
7. [Validation Requests](#7-validation-requests)
8. [Vuex Store](#8-vuex-store)
9. [API Service](#9-api-service)
10. [Frontend Components](#10-frontend-components)
11. [Frontend Routing](#11-frontend-routing)
12. [Cross-Module Integration](#12-cross-module-integration)
13. [Profile Completeness Integration](#13-profile-completeness-integration)
14. [Seeder Data](#14-seeder-data)
15. [API Routing](#15-api-routing)
16. [Key Constants and Business Logic](#16-key-constants-and-business-logic)
17. [Known Issues and Limitations](#17-known-issues-and-limitations)
18. [Deep Dive: AI Extraction Pipeline](#18-deep-dive-ai-extraction-pipeline)

---

## 1. System Overview

The Document Management module provides AI-powered document upload and data extraction for UK financial documents. Users upload PDFs or images of pension statements, insurance policies, investment statements, mortgage statements, and savings documents. The system uses the Anthropic Claude API to extract structured financial data from the documents, maps it to Fynla's internal models, and presents the user with a review step before committing the data.

### Architecture Flow

```
DocumentUploadModal.vue (3-step modal: Upload -> Processing -> Review)
  -> UploadDropZone.vue (drag & drop, client-side image compression)
  -> ProcessingState.vue (animated progress through 4 stages)
  -> ConfidenceBadge.vue (per-field confidence indicators)
  -> documentService.js (8 API methods)
  -> DocumentController.php (9 endpoints)
  -> DocumentProcessor.php (orchestration layer with DB transactions)
    -> DocumentUploadService.php (file validation, UUID storage, S3 support)
    -> AIExtractionService.php (Claude API integration, PDF text extraction, vision)
    -> DocumentTypeDetector.php (type normalisation, weighted confidence scoring)
    -> FieldMappers/ (4 active mappers: DC Pension, DB Pension, Life Insurance, Investment Account)
  -> Models: Document, DocumentExtraction, DocumentExtractionLog
```

### File Count Summary

| Category | Count |
|---|---|
| Models | 3 |
| Controllers | 1 |
| Services | 6 (+ 4 active field mappers, 1 interface, 1 abstract) |
| Validation Requests | 2 |
| Vue Components | 4 (shared) |
| API Service Files | 1 |

### Integration Points

The DocumentUploadModal is used across 9 parent components:

| Parent Component | Document Type Passed |
|---|---|
| `PensionList.vue` (Net Worth) | `pension_statement` |
| `InvestmentList.vue` (Net Worth) | `investment_statement` |
| `PortfolioOverview.vue` (Investment) | `investment_statement` |
| `CurrentSituation.vue` (Protection) | `insurance_policy` |
| `CurrentSituation.vue` (Savings) | `savings_statement` |
| `RetirementReadiness.vue` | `pension_statement` |
| `TrustsDashboard.vue` | Varies |
| `ProtectionPoliciesStep.vue` (Onboarding) | `insurance_policy` |
| `AssetsStep.vue` (Onboarding) | Varies by asset type |

---

## 2. Database Schema

All three tables exist in `database/schema/mysql-schema.sql`. No standalone migration files were found in `database/migrations/` -- the tables were created via the schema dump mechanism.

### 2.1 `documents` Table

Primary table storing uploaded document metadata and processing status.

```sql
CREATE TABLE `documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `disk` varchar(255) NOT NULL DEFAULT 'local',
  `path` varchar(255) NOT NULL,
  `mime_type` varchar(255) NOT NULL,
  `file_size` bigint unsigned NOT NULL,
  `document_type` enum(
    'pension_statement','insurance_policy','investment_statement',
    'mortgage_statement','savings_statement','property_document','unknown'
  ) NOT NULL DEFAULT 'unknown',
  `detected_document_subtype` varchar(255) DEFAULT NULL,
  `detection_confidence` decimal(5,4) DEFAULT NULL,
  `status` enum(
    'uploaded','processing','extracted','review_pending',
    'confirmed','failed','archived'
  ) NOT NULL DEFAULT 'uploaded',
  `error_message` text DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documents_user_id_status_index` (`user_id`,`status`),
  KEY `documents_user_id_document_type_index` (`user_id`,`document_type`),
  KEY `documents_user_created_idx` (`user_id`,`created_at`),
  CONSTRAINT `documents_user_id_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

**Key design notes:**
- Uses `SoftDeletes` -- `deleted_at` column enables soft deletion when files are removed.
- `stored_filename` uses UUIDs to prevent file enumeration attacks.
- `detection_confidence` is `decimal(5,4)` allowing values like `0.9523`.
- `document_type` is an enum restricting values to 7 canonical types.
- `status` is an enum with 7 possible lifecycle states.
- Storage path pattern: `documents/{user_id}/{uuid}.{ext}`

### 2.2 `document_extractions` Table

Stores the AI extraction results for each document. Supports multiple extraction versions (for reprocessing).

```sql
CREATE TABLE `document_extractions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint unsigned NOT NULL,
  `extraction_version` int NOT NULL DEFAULT '1',
  `model_used` varchar(255) NOT NULL DEFAULT 'claude-3-5-sonnet',
  `input_tokens` int DEFAULT NULL,
  `output_tokens` int DEFAULT NULL,
  `raw_response` longtext NOT NULL,
  `extracted_fields` json NOT NULL,
  `field_confidence` json NOT NULL,
  `warnings` json DEFAULT NULL,
  `target_model` varchar(255) DEFAULT NULL,
  `target_model_id` bigint unsigned DEFAULT NULL,
  `is_valid` tinyint(1) NOT NULL DEFAULT '0',
  `validation_errors` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_extractions_document_id_extraction_version_index`
    (`document_id`,`extraction_version`),
  CONSTRAINT `document_extractions_document_id_foreign`
    FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
);
```

**Key design notes:**
- `extraction_version` increments on each reprocess (version 1, 2, 3...).
- `raw_response` is `longtext` -- stores the full Claude API response JSON. Hidden from API output via the model's `$hidden` property.
- `extracted_fields` and `field_confidence` are parallel JSON objects where keys are field names.
- `target_model` stores a fully-qualified PHP class name (e.g., `App\Models\DCPension`).
- `target_model_id` is populated only after confirmation, linking to the created model instance.
- `model_used` default in schema says `claude-3-5-sonnet` but the service currently uses `claude-3-5-haiku-20241022`.

### 2.3 `document_extraction_logs` Table

Audit trail for all document operations.

```sql
CREATE TABLE `document_extraction_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action` enum(
    'uploaded','extraction_started','extraction_completed',
    'extraction_failed','fields_modified','confirmed',
    'saved_to_model','deleted'
  ) NOT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_extraction_logs_document_id_action_index` (`document_id`,`action`),
  KEY `document_extraction_logs_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `document_extraction_logs_document_id_foreign`
    FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_extraction_logs_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

**Key design notes:**
- `ip_address` is `varchar(45)` to accommodate IPv6.
- `metadata` stores contextual information per action (e.g., token counts on extraction completion, error messages on failure).
- The `action` enum matches the `DocumentExtractionLog::ACTION_*` constants exactly.

### Entity Relationship

```
users (1) ---> (N) documents (1) ---> (N) document_extractions
                  |                        |
                  +----> (N) document_extraction_logs
                  |
                  +----> (soft delete via deleted_at)
```

---

## 3. Models

### 3.1 Document (`app/Models/Document.php`)

The core model representing an uploaded financial document.

**Fillable fields:** `user_id`, `original_filename`, `stored_filename`, `disk`, `path`, `mime_type`, `file_size`, `document_type`, `detected_document_subtype`, `detection_confidence`, `status`, `error_message`, `processed_at`, `confirmed_at`

**Casts:**
| Field | Cast |
|---|---|
| `file_size` | `integer` |
| `detection_confidence` | `decimal:4` |
| `processed_at` | `datetime` |
| `confirmed_at` | `datetime` |

**Type Constants:**
| Constant | Value |
|---|---|
| `TYPE_PENSION_STATEMENT` | `pension_statement` |
| `TYPE_INSURANCE_POLICY` | `insurance_policy` |
| `TYPE_INVESTMENT_STATEMENT` | `investment_statement` |
| `TYPE_MORTGAGE_STATEMENT` | `mortgage_statement` |
| `TYPE_SAVINGS_STATEMENT` | `savings_statement` |
| `TYPE_PROPERTY_DOCUMENT` | `property_document` |
| `TYPE_UNKNOWN` | `unknown` |

**Status Constants (Lifecycle):**
| Constant | Value | Description |
|---|---|---|
| `STATUS_UPLOADED` | `uploaded` | File stored, no processing yet |
| `STATUS_PROCESSING` | `processing` | AI extraction in progress |
| `STATUS_EXTRACTED` | `extracted` | AI extraction complete |
| `STATUS_REVIEW_PENDING` | `review_pending` | Mapped data ready for user review |
| `STATUS_CONFIRMED` | `confirmed` | User confirmed, model created |
| `STATUS_FAILED` | `failed` | Extraction or processing error |
| `STATUS_ARCHIVED` | `archived` | Document archived (not currently used in flow) |

**Status Lifecycle Diagram:**
```
uploaded --> processing --> extracted --> review_pending --> confirmed
    |            |              |
    +-- failed <-+-- failed <--+-- failed
```

**Relationships:**
| Method | Type | Related Model |
|---|---|---|
| `user()` | `BelongsTo` | `User` |
| `extractions()` | `HasMany` | `DocumentExtraction` |
| `latestExtraction()` | `HasOne` (latest) | `DocumentExtraction` |
| `logs()` | `HasMany` | `DocumentExtractionLog` |

**Key Methods:**
| Method | Returns | Description |
|---|---|---|
| `getFileUrlAttribute()` | `?string` | URL for public disk only, null otherwise |
| `getContents()` | `string` | Raw file contents from storage |
| `getBase64Contents()` | `string` | Base64-encoded file contents |
| `isImage()` | `bool` | Checks if MIME type starts with `image/` |
| `isPdf()` | `bool` | Checks if MIME type is `application/pdf` |
| `isProcessing()` | `bool` | Status check |
| `hasFailed()` | `bool` | Status check |
| `isReadyForReview()` | `bool` | True if status is `extracted` or `review_pending` |
| `isConfirmed()` | `bool` | Status check |
| `getFormattedFileSizeAttribute()` | `string` | Human-readable size (e.g., "2.5 MB") |

**Query Scopes:**
| Scope | Parameter | Purpose |
|---|---|---|
| `scopeForUser()` | `int $userId` | Filter by user |
| `scopeWithStatus()` | `string $status` | Filter by status |
| `scopeOfType()` | `string $type` | Filter by document type |

**Traits:** `HasFactory`, `SoftDeletes`

### 3.2 DocumentExtraction (`app/Models/DocumentExtraction.php`)

Stores the result of each AI extraction attempt against a document.

**Fillable fields:** `document_id`, `extraction_version`, `model_used`, `input_tokens`, `output_tokens`, `raw_response`, `extracted_fields`, `field_confidence`, `warnings`, `target_model`, `target_model_id`, `is_valid`, `validation_errors`

**Casts:**
| Field | Cast |
|---|---|
| `extracted_fields` | `array` |
| `field_confidence` | `array` |
| `warnings` | `array` |
| `validation_errors` | `array` |
| `is_valid` | `boolean` |
| `input_tokens` | `integer` |
| `output_tokens` | `integer` |
| `extraction_version` | `integer` |

**Hidden fields:** `raw_response` (excluded from JSON serialisation to avoid exposing large payloads).

**Confidence Level Thresholds:**
| Level | Range | Meaning |
|---|---|---|
| `very_high` | >= 0.95 | Automatically extracted with near-certainty |
| `high` | >= 0.80 | High confidence, verify recommended |
| `medium` | >= 0.60 | Manual verification recommended |
| `low` | >= 0.40 | Check carefully |
| `very_low` | < 0.40 | Manual entry may be needed |

**Key Methods:**
| Method | Returns | Description |
|---|---|---|
| `getTotalTokensAttribute()` | `int` | Sum of input + output tokens |
| `getAverageConfidenceAttribute()` | `float` | Mean confidence across all fields |
| `getLowConfidenceFields($threshold)` | `array` | Fields below threshold (default 0.7) |
| `getHighConfidenceFields($threshold)` | `array` | Fields at or above threshold (default 0.9) |
| `hasAllRequiredFields($fields)` | `bool` | Check if all required fields are present and non-null |
| `getFieldValue($field, $default)` | `mixed` | Get single extracted field value |
| `getFieldConfidence($field)` | `float` | Get confidence for a single field |
| `getFieldConfidenceLevel($field)` | `string` | Get the label (very_high/high/medium/low/very_low) |
| `hasWarnings()` | `bool` | Check if warnings array is non-empty |
| `hasValidationErrors()` | `bool` | Check if validation_errors is non-empty |
| `getTargetModelInstance()` | `?Model` | Load the created model instance (after confirmation) |
| `getTargetModelNameAttribute()` | `?string` | Short class name (e.g., "DCPension") |

**Relationships:**
| Method | Type | Related Model |
|---|---|---|
| `document()` | `BelongsTo` | `Document` |

### 3.3 DocumentExtractionLog (`app/Models/DocumentExtractionLog.php`)

Immutable audit trail recording every action taken on a document.

**Fillable fields:** `document_id`, `user_id`, `action`, `metadata`, `ip_address`, `user_agent`

**Casts:**
| Field | Cast |
|---|---|
| `metadata` | `array` |

**Action Constants:**
| Constant | Value | When Logged |
|---|---|---|
| `ACTION_UPLOADED` | `uploaded` | File stored to disk |
| `ACTION_EXTRACTION_STARTED` | `extraction_started` | AI extraction begins |
| `ACTION_EXTRACTION_COMPLETED` | `extraction_completed` | AI extraction succeeds |
| `ACTION_EXTRACTION_FAILED` | `extraction_failed` | AI extraction throws |
| `ACTION_FIELDS_MODIFIED` | `fields_modified` | User edits extracted fields (not currently triggered) |
| `ACTION_CONFIRMED` | `confirmed` | User clicks "Save" |
| `ACTION_SAVED_TO_MODEL` | `saved_to_model` | Target model created in DB |
| `ACTION_DELETED` | `deleted` | User deletes document |

**Key Methods:**
| Method | Signature | Description |
|---|---|---|
| `log()` | `static log(Document, User, string $action, array $metadata)` | Factory method to create a log entry with auto-captured IP and User-Agent |
| `getActionLabelAttribute()` | accessor | Human-readable label for the action |

**Query Scopes:**
| Scope | Parameter | Purpose |
|---|---|---|
| `scopeForDocument()` | `int $documentId` | Filter by document |
| `scopeByUser()` | `int $userId` | Filter by user |
| `scopeWithAction()` | `string $action` | Filter by action type |

**Relationships:**
| Method | Type | Related Model |
|---|---|---|
| `document()` | `BelongsTo` | `Document` |
| `user()` | `BelongsTo` | `User` |

---

## 4. Controller

### DocumentController (`app/Http/Controllers/Api/DocumentController.php`)

Single controller handling all document CRUD and processing operations. Uses the `SanitizedErrorResponse` trait for secure error handling that strips stack traces in production.

**Dependencies:** `DocumentProcessor` (injected via constructor)

| Method | HTTP | Route | Request | Description |
|---|---|---|---|---|
| `index()` | GET | `/api/documents` | `Request` | Paginated list (20 per page) of user's documents with `latestExtraction`, ordered by `created_at` desc |
| `upload()` | POST | `/api/documents/upload` | `UploadDocumentRequest` | Full pipeline: upload file, AI extraction, field mapping, validation. Returns 201 with extracted fields, confidence, warnings, validation errors |
| `uploadOnly()` | POST | `/api/documents/upload-only` | `UploadDocumentRequest` | Upload without extraction. Returns 201 with document ID and status `uploaded` |
| `show()` | GET | `/api/documents/{id}` | `Request` | Single document with `latestExtraction` and `logs` eager loaded. Returns document + mapped data + confidence + warnings + validation state |
| `getExtraction()` | GET | `/api/documents/{id}/extraction` | `Request` | Extraction data only for pre-filling forms. Returns extracted fields, confidence, warnings, target model name. 404 if no extraction exists |
| `confirm()` | POST | `/api/documents/{id}/confirm` | `ConfirmExtractionRequest` | Confirm reviewed data and create target model. Injects `user_id`, calls `$modelClass::create()`. Returns model type and ID |
| `reprocess()` | POST | `/api/documents/{id}/reprocess` | `Request` | Re-extract data from existing document (creates new extraction version). Returns fresh extraction results |
| `destroy()` | DELETE | `/api/documents/{id}` | `Request` | Soft deletes document record and physically removes file from storage |
| `types()` | GET | `/api/documents/types` | none | Returns map of available document types for upload UI |

**Security:** All endpoints (except `types()`) scope queries with `where('user_id', $request->user()->id)` to ensure user isolation. The `findOrFail()` pattern returns 404 if the document belongs to a different user.

**Error handling:** All mutable operations wrap in try/catch and call `$this->safeErrorResponse()` which sanitises error messages for production by stripping file paths and stack traces.

---

## 5. Agent

**No dedicated agent.** The Document module does not have a module-level agent (like `ProtectionAgent` or `SavingsAgent`). The orchestration is handled by `DocumentProcessor` which coordinates the upload, extraction, mapping, and confirmation flow. The AI interaction is isolated to `AIExtractionService` rather than being channelled through an agent layer.

This is by design -- document processing is a utility/infrastructure concern rather than a domain analysis module. The extracted data feeds into the domain-specific agents (Retirement, Protection, Investment, etc.) once confirmed.

---

## 6. Services

All services are located in `app/Services/Documents/`.

### 6.1 AIExtractionService (`AIExtractionService.php`)

The core AI integration service that sends documents to the Anthropic Claude API and parses the structured response.

**Dependencies:** `DocumentUploadService`, `DocumentTypeDetector`, `ImageResizeService`

**Constants:**
| Constant | Value | Purpose |
|---|---|---|
| `API_URL` | `https://api.anthropic.com/v1/messages` | Anthropic Messages API endpoint |
| `MODEL` | `claude-3-5-haiku-20241022` | Model used for extraction |
| `MAX_TOKENS` | `4096` | Maximum output tokens per request |
| `TIMEOUT_SECONDS` | `120` | HTTP request timeout |
| `MAX_SCANNED_PDF_SIZE` | `15MB` | Size limit for scanned (image-based) PDFs |

**Public Methods:**

`extract(Document $document): DocumentExtraction`
- Main entry point for extraction
- Sets document status to `processing`
- Logs `extraction_started` event
- Builds type-specific prompt
- Routes to PDF or image processing path
- Parses JSON response
- Detects/updates document type if unknown
- Creates `DocumentExtraction` record with versioning
- Sets status to `extracted` on success, `failed` on error
- Logs `extraction_completed` or `extraction_failed`

**Private Methods:**

`callClaudeAPI(string $base64, string $mediaType, string $prompt): array`
- Sends to Anthropic Messages API via `Http::withHeaders()`
- Sets headers: `x-api-key`, `anthropic-version: 2023-06-01`, `content-type: application/json`
- For images: calls `ImageResizeService::processForClaudeAPI()` first
- Builds content block with either `document` type (for PDFs) or `image` type (for images)
- Returns full API response as array

`callClaudeAPIWithText(string $textContent, string $prompt): array`
- Text-only variant for extracted PDF text and spreadsheets
- Prepends text content before the extraction prompt
- No vision/document block, just a text message

`buildContentBlock(string $base64, string $mediaType): array`
- PDFs: returns `{"type": "document", "source": {"type": "base64", "media_type": "application/pdf", "data": ...}}`
- Images: returns `{"type": "image", "source": {"type": "base64", "media_type": ..., "data": ...}}`

`buildExtractionPrompt(Document $document): string`
- Combines base prompt + type-specific guidance
- Type routing via `match` on `document_type`

`processPdfDocument(Document $document, string $prompt): array`
- Tries text extraction via `smalot/pdfparser` first
- If text > 100 chars: filters noise and sends text to Claude (cheaper, faster)
- If text too short or extraction fails: falls back to vision API
- Enforces 15MB limit for scanned PDFs

`extractPdfText(string $fileContents): ?string`
- Uses `Smalot\PdfParser\Parser` with temporary 256MB memory limit
- Returns null on failure (triggers vision fallback)

`filterPdfNoise(string $text): string`
- Removes non-financial content from extracted PDF text
- **skipPatterns** (35+ patterns): Legal disclaimers (T&Cs, privacy policy, FCA, FSCS), marketing content (newsletter, social media), page furniture (page numbers, confidential notices), introductory fluff, website/contact info, company registration details
- **keepPatterns** (17 patterns): Financial keywords that always survive filtering: fund value, balance, contribution, pension, retirement, investment, premium, sum assured, benefit, salary, employer/employee, currency amounts (`/£\d/`), decimal numbers, dates
- **skipSectionKeywords**: Entire sections starting with "terms and conditions", "regulatory information", etc. are skipped until a financial keyword is encountered

`parseResponse(array $response): array`
- Extracts text from `response.content[0].text`
- Strips markdown code blocks (`\`\`\`json ... \`\`\``)
- Parses JSON, throws `RuntimeException` on parse failure

**Prompt Types:**

| Prompt Method | Document Type | Fields Extracted |
|---|---|---|
| `getPensionPrompt()` | `pension_statement` | DC: provider, scheme_name, member_number, pension_type, current_fund_value, salary, contributions, fees, retirement_age, projections, strategy. DB: scheme_name, scheme_type, accrued_annual_pension, service_years, salary, NRA, spouse_pension, lump_sum, inflation, revaluation. State: NI years, forecast, SPA |
| `getInsurancePrompt()` | `insurance_policy` | Life: provider, policy_number, policy_type, sum_assured, premium, frequency, dates, term, indexation, trust, beneficiaries. CI: provider, policy_number, type, sum_assured, premium, dates. IP: provider, benefit_amount/frequency, deferred_period, benefit_period, premium |
| `getInvestmentPrompt()` | `investment_statement` | Account: provider, account_number, account_type, platform, current_value, contributions_ytd, ISA subscription, platform_fee. Holdings: security_name, ticker, ISIN, asset_type, quantity, price, value |
| `getMortgagePrompt()` | `mortgage_statement` | lender, account_number, mortgage_type, original_loan, balance, rate, rate_type, fix_end_date, monthly_payment, start/maturity dates, remaining_term |
| `getSavingsPrompt()` | `savings_statement` | institution, account_number, account_type, balance, interest_rate, access_type, notice_period, maturity_date, ISA flag/subscription |
| `getUnknownTypePrompt()` | `unknown` | Asks AI to identify type and subtype first, then extract all relevant fields |

**Extraction Rules (in base prompt):**
- ISO 8601 dates (YYYY-MM-DD)
- Numeric values without currency symbols or commas
- Percentages as decimals (5% = 0.05)
- Preserve exact provider/scheme names
- UK tax year context (6 April to 5 April)
- GBP currency context

### 6.2 DocumentProcessor (`DocumentProcessor.php`)

Orchestration layer that coordinates the full document lifecycle within DB transactions.

**Dependencies:** `DocumentUploadService`, `AIExtractionService`, `DocumentTypeDetector`

**Registered Mappers:**
| Target Model | Mapper Class | Status |
|---|---|---|
| `App\Models\DCPension` | `DCPensionMapper` | Active |
| `App\Models\DBPension` | `DBPensionMapper` | Active |
| `App\Models\LifeInsurancePolicy` | `LifeInsuranceMapper` | Active |
| `App\Models\Investment\InvestmentAccount` | `InvestmentAccountMapper` | Active |
| `App\Models\CriticalIllnessPolicy` | (commented out) | Planned |
| `App\Models\IncomeProtectionPolicy` | (commented out) | Planned |
| `App\Models\SavingsAccount` | (commented out) | Planned |
| `App\Models\Mortgage` | (commented out) | Planned |

**Public Methods:**

`process(UploadedFile $file, User $user, ?string $expectedType): array`
- Wrapped in `DB::transaction()`
- Step 1: Upload file via `DocumentUploadService`
- Step 2: Extract via `AIExtractionService`
- Step 3: Map fields via appropriate `FieldMapper` (or pass through raw if no mapper)
- Step 4: Validate mapped data
- Updates extraction with `is_valid` and `validation_errors`
- Sets document status to `review_pending`
- Returns: `document`, `extraction`, `mapped_data`, `validation_errors`, `is_valid`, `target_model`

`uploadOnly(UploadedFile $file, User $user, ?string $expectedType): Document`
- Delegates to `DocumentUploadService::upload()`
- No extraction triggered

`extractOnly(Document $document): array`
- Extracts and maps without upload step
- Same post-extraction flow as `process()`

`confirm(Document $document, array $confirmedData, User $user): array`
- Wrapped in `DB::transaction()`
- Gets `latestExtraction` from document
- Resolves `target_model` (fully-qualified class name)
- Injects `user_id` into confirmed data
- Calls `$modelClass::create($confirmedData)` to create the domain model
- Updates extraction with `target_model_id`
- Sets document status to `confirmed`, sets `confirmed_at`
- Logs both `ACTION_CONFIRMED` and `ACTION_SAVED_TO_MODEL`
- Returns: `document`, `model`, `model_type`

`reextract(Document $document): array`
- Delegates to `extractOnly()` -- creates a new extraction version

`delete(Document $document, User $user): bool`
- Delegates to `DocumentUploadService::delete()`

`getMappedData(Document $document): array`
- Gets latest extraction and applies mapper
- Returns: `fields`, `confidence`, `warnings`, `is_valid`, `validation_errors`
- Returns empty arrays if no extraction exists

`getAvailableTypes(): array`
- Returns display names for the 5 main document types (excludes property_document and unknown)

### 6.3 DocumentTypeDetector (`DocumentTypeDetector.php`)

Handles document type detection, normalisation, and model class resolution.

**TYPE_MODEL_MAP:**

| Document Type | Subtype | Target Model |
|---|---|---|
| `pension_statement` | `dc_pension` | `App\Models\DCPension` |
| `pension_statement` | `db_pension` | `App\Models\DBPension` |
| `pension_statement` | `state_pension` | `App\Models\StatePension` |
| `insurance_policy` | `life_insurance` | `App\Models\LifeInsurancePolicy` |
| `insurance_policy` | `critical_illness` | `App\Models\CriticalIllnessPolicy` |
| `insurance_policy` | `income_protection` | `App\Models\IncomeProtectionPolicy` |
| `insurance_policy` | `disability` | `App\Models\DisabilityPolicy` |
| `insurance_policy` | `sickness_illness` | `App\Models\SicknessIllnessPolicy` |
| `investment_statement` | `investment_account` | `App\Models\Investment\InvestmentAccount` |
| `mortgage_statement` | `mortgage` | `App\Models\Mortgage` |
| `savings_statement` | `savings_account` | `App\Models\SavingsAccount` |
| `savings_statement` | `cash_account` | `App\Models\CashAccount` |
| `property_document` | `property` | `App\Models\Property` |

**KNOWN_PROVIDERS (approximately 50 UK financial providers):**

| Category | Providers |
|---|---|
| Pension | Scottish Widows, Aviva, Standard Life, Legal & General, Royal London, Aegon, Nest, NOW: Pensions, The People's Pension, Fidelity, AJ Bell, Hargreaves Lansdown, Interactive Investor |
| Insurance | Aviva, Legal & General, Royal London, Vitality, LV=, Zurich, Scottish Widows, Liverpool Victoria, Guardian, British Friendly, Cirencester Friendly, Holloway Friendly |
| Investment | Hargreaves Lansdown, Vanguard, AJ Bell, Fidelity, Interactive Investor, Charles Stanley, Bestinvest, Nutmeg, Wealthify, Moneyfarm |
| Mortgage | Nationwide, Halifax, Santander, Barclays, HSBC, NatWest, Lloyds, TSB, Virgin Money, Yorkshire Building Society |
| Savings | NS&I, Marcus, Aldermore, Atom Bank, Paragon, Shawbrook, OakNorth, Sainsbury's Bank, Tesco Bank |

**Key Methods:**

`detect(array $extractedData): array`
- Takes raw AI output, normalises type, calculates confidence
- Returns `['type' => ..., 'subtype' => ..., 'confidence' => ...]`

`getTargetModel(Document $document): ?string`
- Resolves document type + subtype to a fully-qualified model class
- Falls back to first model in type map if subtype not matched

`normalizeType(string $type): string`
- Maps 20+ AI response type strings to canonical `Document::TYPE_*` constants
- Examples: `dc_pension` -> `pension_statement`, `isa` -> `investment_statement`, `bank_statement` -> `savings_statement`

`calculateConfidence(array $extractedData): float`
- Weighted average of field confidences
- **Important fields (2x weight):** scheme_name, provider, current_value, sum_assured, current_balance, current_fund_value, policy_number, account_number, member_number
- All other fields: 1x weight
- Default: 0.5 if no confidence data

`identifyProviderCategory(string $providerName): ?string`
- Searches KNOWN_PROVIDERS for a case-insensitive match
- Returns category string or null

### 6.4 DocumentUploadService (`DocumentUploadService.php`)

Handles file validation, storage, and deletion.

**Constants:**
| Constant | Value |
|---|---|
| `ALLOWED_MIME_TYPES` | `application/pdf`, `image/jpeg`, `image/png`, `image/webp` |
| `MAX_FILE_SIZE` | `20 * 1024 * 1024` (20MB) |

**Public Methods:**

`upload(UploadedFile $file, User $user, ?string $expectedType): Document`
- Validates file (MIME type, size, readability)
- Generates UUID-based filename: `{uuid}.{ext}`
- Stores on `local` disk at path `documents/{user_id}/{uuid}.{ext}`
- Creates `Document` record with status `uploaded`
- Logs `ACTION_UPLOADED` with metadata (original filename, size, MIME)

`getFileContents(Document $document): string`
- Reads raw file contents from configured disk

`getBase64(Document $document): string`
- Returns base64-encoded file contents

`delete(Document $document, User $user): bool`
- Physically deletes file from storage
- Logs `ACTION_DELETED`
- Soft deletes the Document record

`fileExists(Document $document): bool`
- Checks if the physical file exists on disk

`getTemporaryUrl(Document $document, int $minutes): ?string`
- S3-only: generates a signed temporary URL
- Returns null for non-S3 disks
- Currently not active (disk is `local`)

`getAllowedMimeTypes(): array` / `getMaxFileSize(): int` / `getMaxFileSizeMB(): int`
- Getter methods for upload constraints

**MIME-to-extension mapping:**
| MIME Type | Extension |
|---|---|
| `application/pdf` | `pdf` |
| `image/jpeg` | `jpg` |
| `image/png` | `png` |
| `image/webp` | `webp` |
| default | `bin` |

### 6.5 ImageResizeService (`ImageResizeService.php`)

Resizes and compresses images to fit within the Claude API's 5MB image size limit.

**Constants:**
| Constant | Value | Purpose |
|---|---|---|
| `MAX_IMAGE_SIZE` | 5MB | Claude API absolute limit |
| `MAX_DIMENSION` | 1568px | Max dimension for optimal performance |
| `TARGET_SIZE` | 4.5MB | Target with buffer below limit |
| `MIN_QUALITY` | 40 | Floor for JPEG compression quality |

**Public Methods:**

`processForClaudeAPI(string $base64Data, string $mediaType): array`
- Checks if image exceeds 5MB limit; returns unchanged if under
- Temporarily increases PHP memory limit to 512MB
- Decodes base64, creates GD image resource
- Resizes to fit within 1568px max dimension (maintains aspect ratio)
- Compresses to JPEG with iterative quality reduction
- Returns `['data' => base64, 'media_type' => 'image/jpeg', 'was_resized' => bool]`

**Private Methods:**

`resizeImage(GdImage $source, int $srcW, int $srcH, int $dstW, int $dstH): GdImage`
- Creates true colour destination image
- Fills with white background (for transparency flattening)
- Uses `imagecopyresampled()` for high-quality downscaling

`calculateNewDimensions(int $width, int $height): array`
- Maintains aspect ratio within `MAX_DIMENSION` (1568px)
- Returns original dimensions if already within bounds
- Ensures minimum dimension of 1px

`compressToJpeg(GdImage $image): string`
- Iterative compression: starts at quality 85
- Steps down by 10 until under `TARGET_SIZE` (4.5MB) or hits `MIN_QUALITY` (40)
- Returns base64-encoded JPEG data
- At minimum quality, returns whatever size results

### 6.6 ExcelParserService (`ExcelParserService.php`)

Parses Excel/CSV spreadsheets into structured text for AI extraction. Uses `PhpOffice\PhpSpreadsheet`.

**Status:** Implemented but NOT wired into the upload pipeline. The `DocumentUploadService` does not accept Excel MIME types, and the `AIExtractionService` does not call this service. Available for future integration.

**Constants:**
| Constant | Value | Purpose |
|---|---|---|
| `MAX_ROWS` | 500 | Maximum rows processed per sheet |
| `MAX_COLS` | 26 | Maximum columns (A-Z) |

**Public Methods:**

`parseToText(string $filePath): string`
- Loads spreadsheet via `IOFactory::load()`
- Converts to structured text format

`parseFromContent(string $content, string $mimeType): string`
- Writes binary content to temp file with correct extension
- Calls `parseToText()` and cleans up temp file

`isSpreadsheet(string $mimeType): bool`
- Checks against: `.xlsx`, `.xls`, `.csv` MIME types

**Text Output Format:**
```
=== Sheet: SheetName ===
Headers: Col1 | Col2 | Col3
Row 2: Col1: value1, Col2: value2, Col3: value3
Row 3: Col1: value4, Col2: value5, Col3: value6
```

**Supported MIME types:**
| MIME Type | Extension |
|---|---|
| `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` | `xlsx` |
| `application/vnd.ms-excel` | `xls` |
| `text/csv` / `application/csv` | `csv` |

### 6.7 Field Mappers

Located in `app/Services/Documents/FieldMappers/`.

#### FieldMapperInterface

```php
interface FieldMapperInterface {
    public function map(array $extractedFields): array;
    public function getModelClass(): string;
    public function getRequiredFields(): array;
    public function getOptionalFields(): array;
    public function validate(array $mappedData): array;
    public function getSubtype(): string;
}
```

#### AbstractFieldMapper

Base class providing common field parsing utilities used by all mappers.

| Method | Input | Output | Behaviour |
|---|---|---|---|
| `map()` | `array $extractedFields` | `array` | Iterates `$fieldMappings`, applies `$transformations`, skips nulls |
| `validate()` | `array $mappedData` | `array` | Checks required fields are present and non-null/non-empty |
| `parseDate()` | `?string` | `?string` | Supports 8 UK date formats: `Y-m-d`, `d/m/Y`, `d-m-Y`, `d/m/y`, `d M Y`, `d F Y`, `j M Y`, `j F Y`. Falls back to Carbon's flexible parsing |
| `parseDecimal()` | `mixed` | `?float` | Strips `£`, `$`, `$`, spaces, commas. Handles pence notation (`100p` -> `1.00`) |
| `parsePercentage()` | `mixed` | `?float` | Strips `%` symbol. If value > 1, divides by 100 (treats as whole number percentage). Values <= 1 passed through as-is |
| `parseInt()` | `mixed` | `?int` | Strips non-numeric characters |
| `parseBool()` | `mixed` | `?bool` | Recognises: `true/yes/1/y` -> true, `false/no/0/n` -> false |
| `normalizeString()` | `?string` | `?string` | Trims whitespace, returns null for empty strings |
| `parseEnum()` | `mixed, array $allowed, ?string $default` | `?string` | Lowercases, tries direct match, then partial `str_contains` match, falls back to default |

#### DCPensionMapper

**Target Model:** `App\Models\DCPension`
**Subtype:** `dc_pension`

**Field Mappings (extraction key -> model key):**
| Extraction Field | Model Field | Transformation |
|---|---|---|
| `provider` | `provider` | `normalizeString` |
| `scheme_name` | `scheme_name` | `normalizeString` |
| `member_number` | `member_number` | `normalizeString` |
| `pension_type` | `pension_type` | `normalizePensionType` |
| `current_fund_value` | `current_fund_value` | `parseDecimal` |
| `annual_salary` | `annual_salary` | `parseDecimal` |
| `employee_contribution_percent` | `employee_contribution_percent` | `parseContributionPercent` |
| `employer_contribution_percent` | `employer_contribution_percent` | `parseContributionPercent` |
| `monthly_contribution_amount` | `monthly_contribution_amount` | `parseDecimal` |
| `platform_fee_percent` | `platform_fee_percent` | `parsePercentage` |
| `retirement_age` | `retirement_age` | `parseInt` |
| `projected_value_at_retirement` | `projected_value_at_retirement` | `parseDecimal` |
| `investment_strategy` | `investment_strategy` | `normalizeString` |

**Required fields:** `pension_type`, `current_fund_value`

**normalizePensionType():** Maps AI output to canonical values:
- Contains "occupational" or "workplace" -> `occupational`
- Contains "sipp" -> `sipp`
- Contains "stakeholder" -> `stakeholder`
- Contains "personal" -> `personal`
- Default: `personal`

**parseContributionPercent():** Special handling for the ambiguity between AI returning `0.05` (5% as decimal) vs `5` (5% as whole number). The DB stores whole numbers, so values < 1 are multiplied by 100.

#### DBPensionMapper

**Target Model:** `App\Models\DBPension`
**Subtype:** `db_pension`

**Field Mappings:**
| Extraction Field | Model Field | Transformation |
|---|---|---|
| `scheme_name` | `scheme_name` | `normalizeString` |
| `scheme_type` | `scheme_type` | `normalizeSchemeType` |
| `accrued_annual_pension` | `accrued_annual_pension` | `parseDecimal` |
| `pensionable_service_years` | `pensionable_service_years` | `parseDecimal` |
| `pensionable_salary` | `pensionable_salary` | `parseDecimal` |
| `normal_retirement_age` | `normal_retirement_age` | `parseInt` |
| `spouse_pension_percent` | `spouse_pension_percent` | `parseSpousePercent` |
| `lump_sum_entitlement` | `lump_sum_entitlement` | `parseDecimal` |
| `inflation_protection` | `inflation_protection` | `normalizeInflationProtection` |
| `revaluation_method` | `revaluation_method` | `normalizeString` |

**Required fields:** `scheme_name`, `scheme_type`, `accrued_annual_pension`

**normalizeSchemeType():** Maps to canonical values with public sector detection:
- Contains "final" or "salary" -> `final_salary`
- Contains "career" or "average" or "care" -> `career_average`
- Contains "public", "nhs", "teacher", "civil", "local gov", "lgps" -> `public_sector`
- Default: `final_salary`

**parseSpousePercent():** DB stores as decimal (0.50 for 50%). Values > 1 are divided by 100.

**normalizeInflationProtection():** Maps to: `cpi`, `rpi`, `fixed`, or `none`.

#### LifeInsuranceMapper

**Target Model:** `App\Models\LifeInsurancePolicy`
**Subtype:** `life_insurance`

**Field Mappings (note the key rename):**
| Extraction Field | Model Field | Transformation |
|---|---|---|
| `provider` | `provider` | `normalizeString` |
| `policy_number` | `policy_number` | `normalizeString` |
| `policy_type` | **`life_policy_type`** | `normalizePolicyType` |
| `sum_assured` | `sum_assured` | `parseDecimal` |
| `premium_amount` | `premium_amount` | `parseDecimal` |
| `premium_frequency` | `premium_frequency` | `normalizeFrequency` |
| `policy_start_date` | `policy_start_date` | `parseDate` |
| `policy_end_date` | `policy_end_date` | `parseDate` |
| `policy_term_years` | `policy_term_years` | `parseInt` |
| `indexation_rate` | `indexation_rate` | `parsePercentage` |
| `in_trust` | `in_trust` | `parseBool` |
| `beneficiaries` | `beneficiaries` | `normalizeString` |

**Important:** The extraction key `policy_type` is remapped to `life_policy_type` in the model. This is because the `LifeInsurancePolicy` model uses `life_policy_type` to avoid column name conflicts.

**Required fields:** `provider`, `life_policy_type`, `sum_assured`, `premium_amount`, `premium_frequency`

**normalizePolicyType():** Maps to:
- Contains "decreasing" -> `decreasing_term`
- Contains "level" -> `level_term`
- Contains "whole" or "life" -> `whole_of_life`
- Contains "family", "income", or "fib" -> `family_income_benefit`
- Contains "term" -> `term`
- Default: `term`

**normalizeFrequency():** Maps to: `monthly`, `quarterly`, or `annually`. Default: `monthly`.

#### InvestmentAccountMapper

**Target Model:** `App\Models\Investment\InvestmentAccount`
**Subtype:** `investment_account`

**Field Mappings:**
| Extraction Field | Model Field | Transformation |
|---|---|---|
| `provider` | `provider` | `normalizeString` |
| `account_number` | `account_number` | `normalizeString` |
| `account_type` | `account_type` | `normalizeAccountType` |
| `platform` | `platform` | `normalizeString` |
| `current_value` | `current_value` | `parseDecimal` |
| `contributions_ytd` | `contributions_ytd` | `parseDecimal` |
| `isa_subscription_current_year` | `isa_subscription_current_year` | `parseDecimal` |
| `platform_fee_percent` | `platform_fee_percent` | `parsePercentage` |

**Required fields:** `provider`, `account_type`, `current_value`

**Additional method -- `mapWithHoldings(array $extractedFields): array`:**
- Maps account-level fields via `map()`
- Extracts `holdings` array and maps each holding individually
- Holdings fields: `security_name`, `ticker`, `isin`, `asset_type`, `quantity`, `current_price`, `current_value`
- Returns `['account' => [...], 'holdings' => [...]]`
- Note: This method is not currently called by `DocumentProcessor` -- the standard `map()` is used instead.

**normalizeAccountType():** Maps to canonical values:
- Contains "isa" (not "cash") -> `isa`
- Contains "gia" or "general" -> `gia`
- Contains "nsi", "ns&i", or "national savings" -> `nsi`
- Contains "onshore" -> `onshore_bond`
- Contains "offshore" -> `offshore_bond`
- Contains "vct" or "venture" -> `vct`
- Contains "eis" or "enterprise" -> `eis`
- Default: `other`

**normalizeAssetType()** (for holdings):
- Maps to: `uk_equity`, `us_equity`, `international_equity`, `etf`, `bond`, `cash`, `property`, `alternative`, `fund`
- Default: `fund`

---

## 7. Validation Requests

### 7.1 UploadDocumentRequest (`app/Http/Requests/Documents/UploadDocumentRequest.php`)

Used by `upload()` and `uploadOnly()` endpoints.

**Authorization:** Always `true` (auth is handled by Sanctum middleware on the route).

**Rules:**
| Field | Rules | Notes |
|---|---|---|
| `document` | `required`, `file`, `mimes:pdf,jpeg,jpg,png,webp`, `max:20480` | Max 20MB |
| `document_type` | `nullable`, `in:pension_statement,insurance_policy,investment_statement,mortgage_statement,savings_statement,property_document` | Optional hint |

**Custom error messages:**
- `document.required`: "Please select a document to upload."
- `document.mimes`: "Document must be a PDF or image (JPEG, PNG, WebP)."
- `document.max`: "Document must be less than 20MB. For large PDFs, try compressing the file or using a PDF with selectable text."
- `document_type.in`: "Invalid document type specified."

### 7.2 ConfirmExtractionRequest (`app/Http/Requests/Documents/ConfirmExtractionRequest.php`)

Used by the `confirm()` endpoint.

**Authorization:** Always `true`.

**Rules:**
| Field | Rules | Notes |
|---|---|---|
| `data` | `required`, `array` | The confirmed field values |
| `data.*` | `nullable` | Any fields allowed; model validation handles specifics |

**Custom error messages:**
- `data.required`: "Please provide the confirmed data."
- `data.array`: "Data must be provided as an object."

**Design note:** The validation is intentionally permissive here because different document types have different field schemas. The actual field validation is handled by the `FieldMapper::validate()` method during the extraction phase.

---

## 8. Vuex Store

**No Vuex store module.** The Document Management module uses component-level state exclusively. Document state (selected file, extracted fields, edited fields, confidence data, processing step) is managed within `DocumentUploadModal.vue`'s `data()` and computed properties.

This is by design -- document upload is a transient modal workflow rather than persistent application state. The data flows:
1. User opens upload modal from a parent component
2. Modal manages its own state through the 3-step flow
3. On confirmation, the `saved` event emits to the parent component
4. Parent component refreshes its own data (e.g., pension list, investment list) via its Vuex store

---

## 9. API Service

### documentService.js (`resources/js/services/documentService.js`)

Frontend API wrapper built on the shared `api` Axios instance.

| Method | HTTP | Endpoint | Parameters | Returns |
|---|---|---|---|---|
| `getDocuments(page)` | GET | `/documents` | `page` (default 1) | Paginated documents list |
| `getTypes()` | GET | `/documents/types` | none | Available document types map |
| `upload(file, documentType, onProgress)` | POST | `/documents/upload` | `FormData` with `document` file + optional `document_type`; `onProgress` callback for upload percentage | Extraction results |
| `uploadOnly(file, documentType)` | POST | `/documents/upload-only` | `FormData` with `document` file + optional `document_type` | Document ID and status |
| `getDocument(id)` | GET | `/documents/{id}` | Document ID | Document with mapped data |
| `getExtraction(id)` | GET | `/documents/{id}/extraction` | Document ID | Extraction data for form pre-fill |
| `confirm(id, data)` | POST | `/documents/{id}/confirm` | Document ID, confirmed data object | Model type and ID |
| `reprocess(id)` | POST | `/documents/{id}/reprocess` | Document ID | Fresh extraction results |
| `deleteDocument(id)` | DELETE | `/documents/{id}` | Document ID | Success message |

**Upload progress tracking:** The `upload()` method accepts an `onProgress` callback that receives the upload percentage (0-100) via Axios's `onUploadProgress` event. This drives the progress bar in `ProcessingState.vue`.

**Content type:** Upload methods set `Content-Type: multipart/form-data` header explicitly.

---

## 10. Frontend Components

All document components are in `resources/js/components/Shared/`.

### 10.1 DocumentUploadModal (`DocumentUploadModal.vue`)

The primary component -- a 3-step modal orchestrating the entire upload-to-confirm flow.

**Props:**
| Prop | Type | Default | Description |
|---|---|---|---|
| `documentType` | `String` | `null` | Optional expected document type, passed to API |

**Emits:**
| Event | Payload | When |
|---|---|---|
| `close` | none | Modal closed (cancel or after save) |
| `extracted` | `{ documentId, fields, confidence, targetModel }` | AI extraction completed |
| `saved` | `{ documentId, modelType, modelId, data }` | Confirmed and saved to DB |
| `manual-entry` | none | User clicks "Enter Manually" from error state |

**Data / State:**
| Property | Type | Purpose |
|---|---|---|
| `steps` | `Array` | `['Upload', 'Processing', 'Review']` |
| `currentStep` | `String` | One of: `upload`, `processing`, `review`, `error` |
| `selectedFile` | `File\|null` | The file selected/dropped by user |
| `uploadProgress` | `Number` | 0-100 upload percentage |
| `processingStep` | `String` | Sub-step: `uploading`, `analysing`, `extracting`, `mapping` |
| `documentId` | `Number\|null` | Server-assigned document ID |
| `extractedFields` | `Object` | Raw mapped fields from API |
| `editedFields` | `Object` | User-editable copy of extracted fields |
| `fieldConfidence` | `Object` | Per-field confidence scores |
| `extractionWarnings` | `Array` | AI extraction warnings |
| `detectedType` | `String\|null` | Detected document type |
| `detectedSubtype` | `String\|null` | Detected document subtype |
| `targetModel` | `String\|null` | Target model short name |
| `isSaving` | `Boolean` | Saving spinner state |

**Step Flow:**
1. **Upload step** -- Shows privacy notice (data goes to Anthropic Haiku 3.5, not anonymised) + `UploadDropZone`. "Upload & Analyse" button appears when file is selected.
2. **Processing step** -- Shows `ProcessingState` with animated spinner. Sub-steps progress: uploading -> analysing -> extracting -> mapping (with 500ms delay between extracting and mapping for UX).
3. **Review step** -- Success banner with detected type. Optional warnings in blue alert. Grid of editable text inputs for each extracted field, with `ConfidenceBadge` alongside each. "Save" button confirms to DB.
4. **Error step** -- Error icon, title, message. "Try Again" resets to upload. "Enter Manually" emits to parent.

**Low confidence field styling:** Fields with confidence < 0.6 get `border-blue-300 bg-blue-50` to draw attention.

**Subtype label mapping:**
| Subtype | Display Label |
|---|---|
| `dc_pension` | Defined Contribution Pension |
| `db_pension` | Defined Benefit Pension |
| `state_pension` | State Pension |
| `life_insurance` | Life Insurance |
| `critical_illness` | Critical Illness |
| `income_protection` | Income Protection |
| `investment_account` | Investment Account |
| `mortgage` | Mortgage |
| `savings_account` | Savings Account |

### 10.2 UploadDropZone (`UploadDropZone.vue`)

Drag-and-drop file selection component with client-side image compression.

**Props:**
| Prop | Type | Default | Description |
|---|---|---|---|
| `acceptedTypes` | `Array` | `['.pdf', '.png', '.jpg', '.jpeg', '.webp']` | Accepted file extensions |
| `maxSizeMB` | `Number` | `20` | Maximum file size in MB |

**Emits:**
| Event | Payload | When |
|---|---|---|
| `file-selected` | `File` | Valid file selected (possibly compressed) |
| `file-removed` | none | User clicks "Remove" |
| `error` | `String` | Validation error message |

**Validation (client-side):**
- MIME type check: `application/pdf`, `image/jpeg`, `image/png`, `image/webp`
- Size check: `maxSizeMB * 1024 * 1024` bytes

**Client-side image compression:**
- Triggers for images > 2MB
- Max dimension: 2000px (longest side)
- Compression: JPEG at 85% quality via `canvas.toBlob()`
- Output filename: original name with `.jpg` extension
- Graceful fallback: if compression fails, uses original file

**Visual states:**
| State | Border | Background |
|---|---|---|
| Dragging | `border-blue-500` | `bg-blue-50` |
| File selected | `border-green-500` | `bg-green-50` |
| Error | `border-red-300` | `bg-red-50` |
| Default | `border-gray-300` | `bg-gray-50` |

**File display:** Shows PDF icon (red) or image icon (blue) depending on file type. Displays filename (truncated) and formatted file size.

### 10.3 ProcessingState (`ProcessingState.vue`)

Animated progress indicator shown during the upload and AI extraction process.

**Props:**
| Prop | Type | Default | Description |
|---|---|---|---|
| `step` | `String` | `uploading` | Current processing step |
| `uploadProgress` | `Number` | `0` | Upload percentage (0-100) |

**Steps and messages:**
| Step | Message | Description |
|---|---|---|
| `uploading` | "Uploading document..." | "Uploading document" (shows progress bar) |
| `analysing` | "Analysing your document..." | "AI analysis in progress" |
| `extracting` | "Extracting data fields..." | "Extracting financial data" |
| `mapping` | "Preparing form data..." | "Mapping to form fields" |

**Progress bar:** Only visible during `uploading` step. Uses primary-600 colour. Width transitions with 500ms CSS animation.

**Step indicator:** Shows "Step X of 4: [description]" at bottom.

### 10.4 ConfidenceBadge (`ConfidenceBadge.vue`)

Small circular indicator showing AI confidence for an extracted field value.

**Props:**
| Prop | Type | Required | Description |
|---|---|---|---|
| `confidence` | `String\|Number` | Yes | Either a level string or numeric 0-1 value |

**Level thresholds (numeric to level):**
| Range | Level |
|---|---|
| >= 0.95 | `very_high` |
| >= 0.80 | `high` |
| >= 0.60 | `medium` |
| >= 0.40 | `low` |
| < 0.40 | `very_low` |

**Visual display:**
| Level | Badge Colour | Icon |
|---|---|---|
| `very_high` / `high` | `bg-green-100 text-green-800` | Checkmark SVG |
| `medium` | `bg-blue-100 text-blue-800` | `?` text |
| `low` / `very_low` | `bg-red-100 text-red-800` | `!` text |

**Tooltip:** Descriptive text with percentage, e.g., "High confidence - please verify (87%)".

---

## 11. Frontend Routing

**No dedicated routes.** The Document Management module does not have its own Vue Router routes. The `DocumentUploadModal` is embedded as a child component within other module views (Pension, Investment, Protection, Savings, Onboarding, etc.) and opened/closed via component-level boolean flags (e.g., `showUploadModal`).

There is no standalone documents list page, document detail page, or document management view in the router. All document interactions happen through the modal within existing module pages.

---

## 12. Cross-Module Integration

The Document Management module is a utility layer that feeds extracted data into domain-specific modules. The integration happens at the model creation level during the `confirm()` flow.

### Target Model Creation by Module

| Module | Document Type | Subtype | Model Created |
|---|---|---|---|
| **Retirement** | `pension_statement` | `dc_pension` | `DCPension` |
| **Retirement** | `pension_statement` | `db_pension` | `DBPension` |
| **Retirement** | `pension_statement` | `state_pension` | `StatePension` (detector only; no mapper) |
| **Protection** | `insurance_policy` | `life_insurance` | `LifeInsurancePolicy` |
| **Protection** | `insurance_policy` | `critical_illness` | `CriticalIllnessPolicy` (detector only; no mapper) |
| **Protection** | `insurance_policy` | `income_protection` | `IncomeProtectionPolicy` (detector only; no mapper) |
| **Protection** | `insurance_policy` | `disability` | `DisabilityPolicy` (detector only; no mapper) |
| **Protection** | `insurance_policy` | `sickness_illness` | `SicknessIllnessPolicy` (detector only; no mapper) |
| **Investment** | `investment_statement` | `investment_account` | `InvestmentAccount` |
| **Property** | `mortgage_statement` | `mortgage` | `Mortgage` (detector only; no mapper) |
| **Savings** | `savings_statement` | `savings_account` | `SavingsAccount` (detector only; no mapper) |
| **Savings** | `savings_statement` | `cash_account` | `CashAccount` (detector only; no mapper) |
| **Property** | `property_document` | `property` | `Property` (detector only; no mapper) |

### Integration Pattern

After `confirm()` creates a model:
1. The `saved` event fires with `modelType` and `modelId`
2. The parent component (e.g., `PensionList.vue`) receives this event
3. The parent refreshes its data from the relevant Vuex store (e.g., `this.$store.dispatch('retirement/fetchPensions')`)
4. The new pension/policy/account appears in the module's listing

### Modules Using DocumentUploadModal

| Module | Component | Document Type |
|---|---|---|
| Net Worth | `PensionList.vue` | `pension_statement` |
| Net Worth | `InvestmentList.vue` | `investment_statement` |
| Investment | `PortfolioOverview.vue` | `investment_statement` |
| Protection | `CurrentSituation.vue` | `insurance_policy` |
| Savings | `CurrentSituation.vue` | `savings_statement` |
| Retirement | `RetirementReadiness.vue` | `pension_statement` |
| Trusts (Estate) | `TrustsDashboard.vue` | Varies |
| Onboarding | `ProtectionPoliciesStep.vue` | `insurance_policy` |
| Onboarding | `AssetsStep.vue` | Varies by asset type |

---

## 13. Profile Completeness Integration

**Not applicable.** The Document Management module does not contribute to the profile completeness score. It is a utility for data entry acceleration rather than a data requirement. The models it creates (DCPension, LifeInsurancePolicy, etc.) contribute to their respective module's completeness scoring, but the document itself does not.

---

## 14. Seeder Data

**No seeder data.** There are no seeders for the `documents`, `document_extractions`, or `document_extraction_logs` tables. Preview users do not have pre-populated documents. The feature is exclusively for real-time user interaction.

---

## 15. API Routing

All routes are defined in `routes/api.php` under the document group.

```php
Route::middleware(['auth:sanctum', 'throttle:30,1'])
    ->prefix('documents')
    ->group(function () {
        Route::get('/',              [DocumentController::class, 'index']);
        Route::get('/types',         [DocumentController::class, 'types']);
        Route::post('/upload',       [DocumentController::class, 'upload'])
            ->middleware('throttle:10,1');
        Route::post('/upload-only',  [DocumentController::class, 'uploadOnly'])
            ->middleware('throttle:10,1');
        Route::get('/{id}',         [DocumentController::class, 'show']);
        Route::get('/{id}/extraction', [DocumentController::class, 'getExtraction']);
        Route::post('/{id}/confirm', [DocumentController::class, 'confirm']);
        Route::post('/{id}/reprocess', [DocumentController::class, 'reprocess'])
            ->middleware('throttle:5,1');
        Route::delete('/{id}',      [DocumentController::class, 'destroy']);
    });
```

### Route Summary

| Method | Path | Controller Method | Rate Limit | Purpose |
|---|---|---|---|---|
| GET | `/api/documents` | `index` | 30/min | List user's documents (paginated) |
| GET | `/api/documents/types` | `types` | 30/min | Available document types |
| POST | `/api/documents/upload` | `upload` | **10/min** | Upload + full extraction pipeline |
| POST | `/api/documents/upload-only` | `uploadOnly` | **10/min** | Upload without extraction |
| GET | `/api/documents/{id}` | `show` | 30/min | Document details with extraction |
| GET | `/api/documents/{id}/extraction` | `getExtraction` | 30/min | Extraction data only |
| POST | `/api/documents/{id}/confirm` | `confirm` | 30/min | Confirm and create model |
| POST | `/api/documents/{id}/reprocess` | `reprocess` | **5/min** | Re-extract (most expensive) |
| DELETE | `/api/documents/{id}` | `destroy` | 30/min | Soft delete + file removal |

### Rate Limiting

Three tiers of rate limiting:
- **Standard (30/min):** Read operations and confirm
- **Upload (10/min):** File upload endpoints (to prevent abuse)
- **Reprocess (5/min):** Re-extraction (each call invokes the Claude API, most expensive operation)

### Authentication

All routes require `auth:sanctum` middleware. User scoping is enforced at the controller level via `where('user_id', $request->user()->id)`.

---

## 16. Key Constants and Business Logic

### File Upload Constraints

| Constraint | Value | Enforced By |
|---|---|---|
| Max file size | 20MB (20,480 KB) | `UploadDocumentRequest` + `DocumentUploadService` |
| Allowed MIME types | `application/pdf`, `image/jpeg`, `image/png`, `image/webp` | `UploadDocumentRequest` + `DocumentUploadService` |
| Max scanned PDF size | 15MB | `AIExtractionService` |
| Storage disk | `local` | `DocumentUploadService` |
| Storage path | `documents/{user_id}/{uuid}.{ext}` | `DocumentUploadService` |
| Filename format | UUID v4 | `DocumentUploadService` |

### AI API Constraints

| Constraint | Value | Enforced By |
|---|---|---|
| Claude model | `claude-3-5-haiku-20241022` | `AIExtractionService` |
| Max output tokens | 4096 | `AIExtractionService` |
| API timeout | 120 seconds | `AIExtractionService` |
| Max image size for API | 5MB | `ImageResizeService` |
| Target image size | 4.5MB (with buffer) | `ImageResizeService` |
| Max image dimension | 1568px | `ImageResizeService` |
| Min JPEG quality | 40 | `ImageResizeService` |
| Initial JPEG quality | 85 | `ImageResizeService` |
| Quality step-down | 10 per iteration | `ImageResizeService` |

### Client-Side Image Compression

| Constraint | Value | Enforced By |
|---|---|---|
| Compression threshold | > 2MB | `UploadDropZone.vue` |
| Max dimension | 2000px | `UploadDropZone.vue` |
| JPEG quality | 85% | `UploadDropZone.vue` |

### Confidence Thresholds

| Threshold | Value | Used For |
|---|---|---|
| Very high | >= 0.95 | Green badge, auto-confident |
| High | >= 0.80 | Green badge |
| Medium | >= 0.60 | Blue badge, review recommended |
| Low | >= 0.40 | Red badge, manual check needed |
| Very low | < 0.40 | Red badge, may need manual entry |
| Low confidence threshold (model) | < 0.70 | `getLowConfidenceFields()` default |
| High confidence threshold (model) | >= 0.90 | `getHighConfidenceFields()` default |
| Low confidence field styling | < 0.60 | Blue highlight in review form |

### Document Type Canonical Values

```
pension_statement, insurance_policy, investment_statement,
mortgage_statement, savings_statement, property_document, unknown
```

### Document Status Canonical Values

```
uploaded, processing, extracted, review_pending,
confirmed, failed, archived
```

### Excel Parser Limits

| Constraint | Value |
|---|---|
| Max rows per sheet | 500 |
| Max columns | 26 (A-Z) |

---

## 17. Known Issues and Limitations

### Active Issues

1. **Schema default mismatch:** The `document_extractions.model_used` column defaults to `claude-3-5-sonnet` in the schema, but the `AIExtractionService` constant is `claude-3-5-haiku-20241022`. The service always sets this field explicitly, so the schema default is not used in practice, but it is misleading.

2. **ExcelParserService not wired:** The service is fully implemented but not connected to the upload pipeline. `DocumentUploadService::ALLOWED_MIME_TYPES` does not include Excel/CSV MIME types. Integration would require updating both the upload service and the extraction service.

3. **Holdings not persisted separately:** `InvestmentAccountMapper::mapWithHoldings()` exists but is not called by `DocumentProcessor`. The standard `map()` method is used, which means individual holdings data from investment statements is lost during mapping. The account-level fields are preserved.

4. **Missing mappers for 8 subtypes:** The `DocumentTypeDetector` maps 13 subtypes to models, but only 4 have active mappers (DCPension, DBPension, LifeInsurance, InvestmentAccount). The remaining 9 (StatePension, CriticalIllness, IncomeProtection, Disability, SicknessIllness, Mortgage, SavingsAccount, CashAccount, Property) will pass through raw AI fields to the confirm step without normalisation or validation.

5. **No migration files:** The three document tables exist in the schema dump but have no standalone migration files in `database/migrations/`. This means `php artisan migrate:status` will not track them individually.

6. **`archived` status unused:** The `STATUS_ARCHIVED` constant and enum value exist but are never set by any code path. There is no archive endpoint.

7. **`ACTION_FIELDS_MODIFIED` never triggered:** The `fields_modified` log action constant exists but is never written. User edits in the review step go directly to `confirm()` without logging intermediate field modifications.

8. **Privacy notice wording:** The DocumentUploadModal shows a static notice that "This feature is still being developed" -- this should be reviewed and updated as the feature matures.

9. **No document list UI:** Users can upload and process documents but there is no standalone page to view/manage previously uploaded documents. The `getDocuments()` API exists but is not surfaced in the frontend.

10. **S3 support inactive:** `DocumentUploadService` hardcodes `disk = 'local'`. The `getTemporaryUrl()` method for S3 exists but is unreachable.

### Limitations

- **Single file per upload:** The modal handles one file at a time. Batch upload is not supported.
- **No OCR fallback:** For scanned PDFs, the service relies on Claude's built-in vision capabilities. There is no dedicated OCR preprocessing step.
- **No document preview:** The review step shows extracted fields but does not display the original document alongside for comparison.
- **Token costs not tracked per user:** Token usage is recorded in `document_extractions` but there is no aggregation, billing, or quota mechanism.
- **Reprocessing creates new version but does not diff:** When reprocessing, a new extraction version is created but there is no comparison with previous versions shown to the user.

---

## 18. Deep Dive: AI Extraction Pipeline

The complete flow from user file selection to domain model creation.

### Phase 1: Client-Side Preparation

```
User selects/drops file
  -> UploadDropZone.vue validates:
     - MIME type in [pdf, jpeg, png, webp]
     - Size <= 20MB
  -> If image > 2MB:
     - Canvas-based compression
     - Max 2000px dimension
     - JPEG 85% quality
     - Creates new File object with .jpg extension
  -> Emits file-selected to DocumentUploadModal
```

### Phase 2: Upload & Processing Request

```
DocumentUploadModal.startUpload()
  -> currentStep = 'processing'
  -> processingStep = 'uploading'
  -> documentService.upload(file, documentType, onProgress)
     -> POST /api/documents/upload (multipart/form-data)
     -> onProgress callback updates uploadProgress (0-100)
     -> When progress hits 100, processingStep = 'analysing'
```

### Phase 3: Server-Side Upload

```
UploadDocumentRequest validates:
  - document: required, file, mimes:pdf,jpeg,jpg,png,webp, max:20480
  - document_type: nullable, in:[valid types]

DocumentController.upload()
  -> DocumentProcessor.process(file, user, expectedType)
    [DB::transaction begins]

    -> DocumentUploadService.upload(file, user, type)
       1. Validate MIME type against ALLOWED_MIME_TYPES
       2. Validate size against MAX_FILE_SIZE (20MB)
       3. Generate UUID filename: {uuid}.{ext}
       4. Store to local disk: documents/{user_id}/{uuid}.{ext}
       5. Create Document record (status: 'uploaded')
       6. Log ACTION_UPLOADED with metadata
       7. Return Document model
```

### Phase 4: AI Extraction

```
    -> AIExtractionService.extract(document)
       1. Set document status = 'processing'
       2. Log ACTION_EXTRACTION_STARTED
       3. Build type-specific prompt:
          - Base prompt (rules, UK context, JSON format)
          + Type guidance (pension/insurance/investment/mortgage/savings/unknown)

       4a. If PDF:
           -> extractPdfText(fileContents) via smalot/pdfparser
           -> If text > 100 chars:
              -> filterPdfNoise(text)
                 - Remove T&Cs, disclaimers, marketing, page furniture
                 - Keep financial data lines (amounts, dates, keywords)
              -> callClaudeAPIWithText(filteredText, prompt)
           -> If text too short (scanned PDF):
              -> Check < 15MB limit
              -> callClaudeAPI(base64, 'application/pdf', prompt)
                 - Uses document content block type

       4b. If image:
           -> getBase64(document)
           -> callClaudeAPI(base64, mediaType, prompt)
              -> ImageResizeService.processForClaudeAPI()
                 - If > 5MB: resize to 1568px max, JPEG 85->40 quality
              -> Build image content block
              -> POST to Anthropic Messages API
                 Headers: x-api-key, anthropic-version, content-type
                 Body: model, max_tokens, messages

       5. parseResponse(apiResponse)
          - Extract text from response.content[0].text
          - Strip markdown code blocks
          - Parse JSON

       6. If document_type was 'unknown':
          -> DocumentTypeDetector.detect(extractedData)
             - normalizeType() maps AI type to canonical type
             - calculateConfidence() with weighted important fields (2x)
          -> Update document type, subtype, confidence

       7. Create DocumentExtraction record:
          - extraction_version (incremented)
          - model_used: 'claude-3-5-haiku-20241022'
          - input_tokens, output_tokens (from API response)
          - raw_response (full JSON, hidden from API output)
          - extracted_fields, field_confidence, warnings
          - target_model (resolved via DocumentTypeDetector.getTargetModel)

       8. Set document status = 'extracted'
       9. Log ACTION_EXTRACTION_COMPLETED with token count and field count
       10. Return DocumentExtraction
```

### Phase 5: Field Mapping & Validation

```
    -> DocumentProcessor.getMapper(document)
       -> DocumentTypeDetector.getTargetModel(document)
       -> Look up mapper from registered mappers:
          DCPension -> DCPensionMapper
          DBPension -> DBPensionMapper
          LifeInsurancePolicy -> LifeInsuranceMapper
          InvestmentAccount -> InvestmentAccountMapper

    -> mapper.map(extractedFields)
       For each fieldMapping entry:
         1. Get value from extractedFields[extractionKey]
         2. Apply transformation (parseDecimal, parseDate, normalizeType, etc.)
         3. Skip null values
         4. Output as modelKey -> transformedValue

    -> mapper.validate(mappedData)
       - Check each required field is present and non-empty
       - Return array of error messages

    -> Update extraction: is_valid, validation_errors
    -> Set document status = 'review_pending'

    [DB::transaction commits]
    Return: document, extraction, mapped_data, validation_errors, is_valid, target_model
```

### Phase 6: Frontend Review

```
API response received in DocumentUploadModal:
  -> processingStep = 'extracting' (500ms delay) -> 'mapping'
  -> Store: documentId, extractedFields, fieldConfidence, warnings
  -> editedFields = {...extractedFields} (user-editable copy)
  -> Emit 'extracted' event to parent
  -> currentStep = 'review'

Review UI:
  - Success banner with detected type label
  - Warnings in blue alert box
  - Grid of editable text inputs
  - ConfidenceBadge next to each field:
    >= 0.80: green checkmark
    >= 0.60: blue "?"
    < 0.60:  red "!" (field gets blue highlight)
  - User can edit any field values
```

### Phase 7: Confirmation & Model Creation

```
User clicks "Save [Type]"
  -> DocumentUploadModal.handleSave()
  -> documentService.confirm(documentId, editedFields)
     -> POST /api/documents/{id}/confirm

DocumentController.confirm()
  -> ConfirmExtractionRequest validates: data required, array
  -> DocumentProcessor.confirm(document, confirmedData, user)
     [DB::transaction begins]

     1. Get latestExtraction from document
     2. Resolve target_model class (e.g., App\Models\DCPension)
     3. Inject user_id into confirmedData
     4. $modelClass::create($confirmedData)
        -> Creates actual domain model record (e.g., dc_pensions table)
     5. Update extraction: target_model_id = model.id
     6. Set document status = 'confirmed', confirmed_at = now()
     7. Log ACTION_CONFIRMED
     8. Log ACTION_SAVED_TO_MODEL with model_class and model_id

     [DB::transaction commits]
     Return: document, model, model_type

Frontend:
  -> Emit 'saved' event with {documentId, modelType, modelId, data}
  -> Close modal
  -> Parent component handles 'saved' event
     -> Refreshes its data store (e.g., re-fetches pension list)
     -> New record appears in the module's UI
```

### Error Handling Throughout

| Phase | Error | Handling |
|---|---|---|
| Client-side validation | Wrong MIME type or too large | Error shown in UploadDropZone, no API call |
| Server-side validation | Invalid file/type | 422 with validation error messages |
| Upload storage | Disk full or permissions | 500, logged, `safeErrorResponse()` |
| PDF text extraction | Parser failure | Falls back to vision API (graceful degradation) |
| Claude API call | Timeout, auth failure, rate limit | RuntimeException, document status set to `failed`, logged |
| JSON parse failure | AI returns invalid JSON | RuntimeException, document status `failed` |
| Confirmation | Invalid target model | RuntimeException, transaction rolled back |
| Any server error | Unexpected exception | `SanitizedErrorResponse` strips sensitive info, returns generic message |
| Any frontend error | API error response | Error step shown with specific message extraction from `response.data.errors` |

### Token Usage Tracking

Each extraction records:
- `input_tokens`: tokens sent to Claude (document content + prompt)
- `output_tokens`: tokens generated by Claude (JSON response)
- Available via `DocumentExtraction::getTotalTokensAttribute()`
- Logged in `ACTION_EXTRACTION_COMPLETED` metadata
- No aggregation or billing mechanism currently implemented
