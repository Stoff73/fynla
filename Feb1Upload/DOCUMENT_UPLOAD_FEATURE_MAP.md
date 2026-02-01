# Document Upload Feature - Complete Architecture Map

## Overview

The document upload feature allows users to upload pension/investment statements which are processed by Anthropic's Claude API to extract data and auto-populate fields in the application.

---

## Architecture Flow

```
User → DocumentUploadModal → documentService.js → API Route → DocumentController
                                                                    ↓
                                                            DocumentProcessor
                                                                    ↓
                                            ┌───────────────────────┼───────────────────────┐
                                            ↓                       ↓                       ↓
                                   DocumentUploadService    AIExtractionService    FieldMappers
                                   (File Storage)           (Claude API)           (Data Transform)
                                                                    ↓
                                                            DocumentTypeDetector
                                                            (Type → Model Mapping)
```

---

## 1. FRONTEND COMPONENTS

### 1.1 DocumentUploadModal.vue
**Location:** `resources/js/components/Shared/DocumentUploadModal.vue`

**Purpose:** Main modal component orchestrating the 3-step document upload workflow

**Steps:**
1. **Upload** - File selection via UploadDropZone
2. **Processing** - Shows progress (uploading → analysing → extracting → mapping)
3. **Review** - Display extracted fields with confidence badges, allow editing

**Events Emitted:**
- `close` - Modal closes
- `extracted` - After successful extraction (fields, confidence, target model)
- `saved` - After data saved to database
- `manual-entry` - User chooses manual entry

**Props:**
- `documentType` (String, optional) - Expected document type hint

---

### 1.2 UploadDropZone.vue
**Location:** `resources/js/components/Shared/UploadDropZone.vue`

**Purpose:** Drag-and-drop file input with validation

**Supported Files:**
- PDF (application/pdf)
- Images: JPEG, PNG, WebP
- Spreadsheets: XLSX, XLS, CSV
- Max size: 100MB

**Emits:**
- `file-selected` - New file selected
- `file-removed` - File removed
- `error` - Validation error

---

### 1.3 ProcessingState.vue
**Location:** `resources/js/components/Shared/ProcessingState.vue`

**Purpose:** Processing progress display with animated spinner

**Steps:**
1. Uploading (with progress bar 0-100%)
2. Analysing
3. Extracting
4. Mapping

---

### 1.4 ConfidenceBadge.vue
**Location:** `resources/js/components/Shared/ConfidenceBadge.vue`

**Confidence Levels:**
| Range | Level | Icon |
|-------|-------|------|
| 0.95+ | Very High | Green ✓ |
| 0.8-0.95 | High | Green ✓ |
| 0.6-0.8 | Medium | Blue ? |
| 0.4-0.6 | Low | Red ! |
| <0.4 | Very Low | Red ! |

---

### 1.5 Integration Points

**Retirement Module:**
- `resources/js/views/Retirement/RetirementReadiness.vue`
- Document type: `pension_statement`
- Handler: `handleDocumentSaved()` - Creates pension record

**Trusts Module:**
- `resources/js/views/Trusts/TrustsDashboard.vue`
- Similar pattern

---

## 2. API SERVICE (JavaScript)

### documentService.js
**Location:** `resources/js/services/documentService.js`

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `upload(file, documentType, onProgress)` | POST `/api/documents/upload` | Upload and process |
| `uploadOnly(file, documentType)` | POST `/api/documents/upload-only` | Upload without processing |
| `getDocuments(page)` | GET `/api/documents` | List documents |
| `getTypes()` | GET `/api/documents/types` | Get document types |
| `getDocument(id)` | GET `/api/documents/{id}` | Get document |
| `getExtraction(id)` | GET `/api/documents/{id}/extraction` | Get extraction |
| `confirm(id, data)` | POST `/api/documents/{id}/confirm` | Save to model |
| `reprocess(id)` | POST `/api/documents/{id}/reprocess` | Re-extract |
| `deleteDocument(id)` | DELETE `/api/documents/{id}` | Delete |

---

## 3. BACKEND ROUTES

**Location:** `routes/api.php` (Lines 968-977)

**Route Group:** `middleware(['auth:sanctum', 'throttle:30,1']) → prefix('documents')`

| Method | Route | Handler | Rate |
|--------|-------|---------|------|
| POST | `/upload` | `DocumentController@upload` | 10/min |
| POST | `/upload-only` | `DocumentController@uploadOnly` | 10/min |
| GET | `/` | `DocumentController@index` | 30/min |
| GET | `/types` | `DocumentController@types` | 30/min |
| GET | `/{id}` | `DocumentController@show` | 30/min |
| GET | `/{id}/extraction` | `DocumentController@getExtraction` | 30/min |
| POST | `/{id}/confirm` | `DocumentController@confirm` | 30/min |
| POST | `/{id}/reprocess` | `DocumentController@reprocess` | 5/min |
| DELETE | `/{id}` | `DocumentController@destroy` | 30/min |

---

## 4. CONTROLLER

### DocumentController.php
**Location:** `app/Http/Controllers/Api/DocumentController.php`

**Key Methods:**

**upload()** (Line 45-75)
- Validates via `UploadDocumentRequest`
- Calls `DocumentProcessor::process()`
- Returns: document_id, document_type, detected_subtype, extracted_fields, field_confidence, warnings, validation_errors, is_valid, target_model

**confirm()** (Line 169-193)
- Saves extracted data to target model
- Returns: document_id, model_type, model_id

**getExtraction()** (Line 132-163)
- Returns extraction ready for form pre-filling

---

## 5. BACKEND SERVICES

### 5.1 DocumentProcessor.php
**Location:** `app/Services/Documents/DocumentProcessor.php`

**Core Orchestrator** - Manages entire workflow

**process()** (Lines 37-77)
1. Upload document
2. Extract data via AI
3. Map to model fields
4. Validate
5. Update document status

**confirm()** (Lines 129-186)
- Creates model instance (DCPension, LifeInsurancePolicy, InvestmentAccount, etc.)
- Updates extraction with target_model_id
- Logs confirmation

**Registered Mappers:**
- DCPension
- DBPension
- LifeInsurancePolicy
- InvestmentAccount

---

### 5.2 AIExtractionService.php
**Location:** `app/Services/Documents/AIExtractionService.php`

**Claude API Integration**

**Configuration:**
- API URL: `https://api.anthropic.com/v1/messages`
- Model: `claude-sonnet-4-5`
- Max Tokens: 4096
- Timeout: 120 seconds

**extract()** (Lines 34-141)
- Updates document status: PROCESSING → EXTRACTED or FAILED
- For PDFs/images: Uses Vision API with base64 encoding
- For spreadsheets: Converts to text via ExcelParserService
- Parses JSON response
- Creates DocumentExtraction record

**callClaudeAPI()** (Lines 147-205)
- For PDFs and images
- API Key: `config('services.anthropic.api_key')`
- Image resizing if > 5MB via ImageResizeService
- Content blocks: document type for PDFs, image type for images

**callClaudeAPIWithText()** (Lines 211-245)
- For spreadsheets converted to text

**Prompt Templates:**
- `getBasePrompt()` - Global rules, JSON format, UK context
- `getPensionPrompt()` - DC, DB, State pension fields
- `getInsurancePrompt()` - Life, CI, IP fields
- `getInvestmentPrompt()` - Account and holdings fields
- `getMortgagePrompt()` - Mortgage fields
- `getSavingsPrompt()` - Savings fields

---

### 5.3 DocumentUploadService.php
**Location:** `app/Services/Documents/DocumentUploadService.php`

**Purpose:** File storage and retrieval

**Configuration:**
- Max size: 100MB
- Storage: `documents/{user_id}/{uuid}.ext`
- Disk: local (dev) or S3 (production)

**Key Methods:**
- `upload()` - Store file, create Document record
- `getBase64()` - Return base64 for Claude API
- `delete()` - Delete file and soft-delete record

---

### 5.4 DocumentTypeDetector.php
**Location:** `app/Services/Documents/DocumentTypeDetector.php`

**Type-to-Model Mapping:**

| Document Type | Subtypes | Target Model |
|---------------|----------|--------------|
| pension_statement | dc_pension, db_pension, state_pension | DCPension, DBPension, StatePension |
| insurance_policy | life_insurance, critical_illness, income_protection | LifeInsurancePolicy, CriticalIllnessPolicy, IncomeProtectionPolicy |
| investment_statement | investment_account | InvestmentAccount |
| mortgage_statement | mortgage | Mortgage |
| savings_statement | savings_account, cash_account | SavingsAccount, CashAccount |

---

### 5.5 ExcelParserService.php
**Location:** `app/Services/Documents/ExcelParserService.php`

**Purpose:** Converts Excel/CSV to text for Claude API
- Max rows: 500
- Max columns: 26 (A-Z)

---

### 5.6 ImageResizeService.php
**Location:** `app/Services/Documents/ImageResizeService.php`

**Purpose:** Resize images for Claude API 5MB limit
- Max dimension: 1568px
- Target size: 4.5MB
- Min JPEG quality: 40%

---

## 6. FIELD MAPPERS

### Base: AbstractFieldMapper.php
**Location:** `app/Services/Documents/FieldMappers/AbstractFieldMapper.php`

**Transformation Methods:**
- `parseDate()` - UK formats → YYYY-MM-DD
- `parseDecimal()` - Remove £, commas → float
- `parsePercentage()` - 5% or 0.05 → 0.05
- `parseInt()`, `parseBool()`, `normalizeString()`, `parseEnum()`

### Mappers:

| Mapper | Location | Required Fields |
|--------|----------|-----------------|
| DCPensionMapper | `FieldMappers/DCPensionMapper.php` | pension_type, current_fund_value |
| DBPensionMapper | `FieldMappers/DBPensionMapper.php` | scheme_name, scheme_type, accrued_annual_pension |
| LifeInsuranceMapper | `FieldMappers/LifeInsuranceMapper.php` | provider, life_policy_type, sum_assured, premium_amount, premium_frequency |
| InvestmentAccountMapper | `FieldMappers/InvestmentAccountMapper.php` | provider, account_type, current_value |

---

## 7. DATA MODELS

### Document.php
**Location:** `app/Models/Document.php`

**Status Constants:**
- `STATUS_UPLOADED`
- `STATUS_PROCESSING`
- `STATUS_EXTRACTED`
- `STATUS_REVIEW_PENDING`
- `STATUS_CONFIRMED`
- `STATUS_FAILED`
- `STATUS_ARCHIVED`

**Type Constants:**
- `TYPE_PENSION_STATEMENT`
- `TYPE_INSURANCE_POLICY`
- `TYPE_INVESTMENT_STATEMENT`
- `TYPE_MORTGAGE_STATEMENT`
- `TYPE_SAVINGS_STATEMENT`
- `TYPE_PROPERTY_DOCUMENT`
- `TYPE_UNKNOWN`

### DocumentExtraction.php
**Location:** `app/Models/DocumentExtraction.php`

**Key Fields:**
- `extracted_fields` (JSON)
- `field_confidence` (JSON)
- `warnings` (JSON)
- `target_model` (class name)
- `target_model_id` (FK)
- `is_valid` (boolean)
- `validation_errors` (JSON)

### DocumentExtractionLog.php
**Location:** `app/Models/DocumentExtractionLog.php`

**Actions:** UPLOADED, EXTRACTION_STARTED, EXTRACTION_COMPLETED, EXTRACTION_FAILED, CONFIRMED, etc.

---

## 8. REQUEST VALIDATION

### UploadDocumentRequest.php
**Location:** `app/Http/Requests/Documents/UploadDocumentRequest.php`

```php
'document' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png,webp,xlsx,xls,csv', 'max:10240'],
'document_type' => ['nullable', 'in:pension_statement,insurance_policy,investment_statement,...']
```

---

## 9. CONFIGURATION

### services.php
**Location:** `config/services.php`

```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
],
```

**Environment Variable:** `ANTHROPIC_API_KEY`

---

## 10. ANTHROPIC API INTEGRATION

### Request Format (Images/PDFs)
```json
{
    "model": "claude-sonnet-4-5",
    "max_tokens": 4096,
    "messages": [{
        "role": "user",
        "content": [
            {
                "type": "document" | "image",
                "source": {
                    "type": "base64",
                    "media_type": "application/pdf" | "image/jpeg" | etc,
                    "data": "base64_encoded_content"
                }
            },
            {
                "type": "text",
                "text": "extraction_prompt"
            }
        ]
    }]
}
```

### Headers
```
x-api-key: {ANTHROPIC_API_KEY}
anthropic-version: 2023-06-01
content-type: application/json
```

---

## 11. COMPLETE FLOW

### Upload & Process:
```
1. User clicks "Upload Statement"
2. DocumentUploadModal opens
3. User selects file via UploadDropZone
4. UploadDropZone validates file type/size
5. User clicks "Upload & Analyse"
6. documentService.upload() → POST /api/documents/upload
7. DocumentController validates → DocumentProcessor.process()
8. DocumentProcessor:
   a. DocumentUploadService.upload() → Store file
   b. AIExtractionService.extract() → Call Claude API
   c. FieldMapper.map() → Transform fields
   d. FieldMapper.validate() → Check required fields
   e. Create DocumentExtraction record
9. Return extracted_fields + field_confidence
10. User reviews/edits fields
11. User clicks "Save"
12. documentService.confirm() → POST /api/documents/{id}/confirm
13. DocumentProcessor.confirm() → Create model (DCPension, etc.)
14. Return success with model_id
15. Frontend emits 'saved', refreshes list
```

---

## 12. CONSTRAINTS & LIMITS

| Constraint | Value |
|-----------|-------|
| File max size (frontend validation) | 10MB |
| File max size (backend storage) | 100MB |
| Image max size for Claude | 5MB |
| Image max dimension | 1568px |
| Claude API timeout | 120 seconds |
| Max tokens | 4096 |
| Excel rows | 500 |
| Upload rate limit | 10/min |
| Reprocess rate limit | 5/min |

---

## 13. KEY FILES SUMMARY

| Component | Path |
|-----------|------|
| Modal | `resources/js/components/Shared/DocumentUploadModal.vue` |
| Drop Zone | `resources/js/components/Shared/UploadDropZone.vue` |
| Processing UI | `resources/js/components/Shared/ProcessingState.vue` |
| Confidence Badge | `resources/js/components/Shared/ConfidenceBadge.vue` |
| JS Service | `resources/js/services/documentService.js` |
| Controller | `app/Http/Controllers/Api/DocumentController.php` |
| Processor | `app/Services/Documents/DocumentProcessor.php` |
| AI Extraction | `app/Services/Documents/AIExtractionService.php` |
| Upload Service | `app/Services/Documents/DocumentUploadService.php` |
| Type Detector | `app/Services/Documents/DocumentTypeDetector.php` |
| Excel Parser | `app/Services/Documents/ExcelParserService.php` |
| Image Resize | `app/Services/Documents/ImageResizeService.php` |
| Field Mappers | `app/Services/Documents/FieldMappers/*.php` |
| Document Model | `app/Models/Document.php` |
| Extraction Model | `app/Models/DocumentExtraction.php` |
| Routes | `routes/api.php` (Lines 968-977) |
| Config | `config/services.php` |
