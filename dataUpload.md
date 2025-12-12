# Document Upload & AI Extraction Feature

## Overview

This feature allows users to upload financial documents (pension statements, insurance policies, investment statements, savings statements) and have them automatically analysed using Claude Vision API. The extracted data is mapped to the appropriate database models and presented to users for verification before saving.

**Implementation Date**: December 2025
**Branch**: FynlaMVP

---

## Architecture

```
User uploads PDF/Image
       ↓
DocumentUploadService (validates & stores file)
       ↓
AIExtractionService (Claude Vision API)
       ↓
DocumentTypeDetector (auto-detect document type)
       ↓
FieldMapper (map to database model fields)
       ↓
Vue Review Modal (user verifies extracted data)
       ↓
Existing Form APIs (save to database)
```

---

## Database Schema

### 1. `documents` Table

Stores uploaded document metadata and processing status.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Foreign key to users |
| original_filename | string | Original uploaded filename |
| stored_filename | string | UUID-based stored filename |
| disk | string | Storage disk (default: 'local') |
| path | string | Storage path |
| mime_type | string | File MIME type |
| file_size | bigint | File size in bytes |
| document_type | enum | pension_statement, insurance_policy, investment_statement, mortgage_statement, savings_statement |
| detected_document_subtype | string | e.g., 'dc_pension', 'life_insurance' |
| detection_confidence | decimal(5,4) | 0-1 confidence score |
| status | enum | uploaded, processing, extracted, review_pending, confirmed, failed |
| error_message | text | Error details if failed |
| processed_at | timestamp | When processing completed |
| confirmed_at | timestamp | When user confirmed extraction |
| timestamps | - | created_at, updated_at |
| soft_deletes | - | deleted_at |

### 2. `document_extractions` Table

Stores AI extraction results.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| document_id | bigint | Foreign key to documents |
| extraction_version | string | Version identifier |
| model_used | string | AI model used (e.g., claude-3-5-sonnet) |
| input_tokens | integer | Tokens sent to API |
| output_tokens | integer | Tokens received from API |
| raw_response | longText | Full API response |
| extracted_fields | json | Parsed field values |
| field_confidence | json | Per-field confidence scores |
| warnings | json | Extraction warnings |
| target_model | string | Target Eloquent model class |
| target_model_id | bigint | ID if updating existing record |
| is_valid | boolean | Validation status |
| validation_errors | json | Validation error details |
| timestamps | - | created_at, updated_at |

### 3. `document_extraction_logs` Table

Audit trail for document processing.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| document_id | bigint | Foreign key to documents |
| user_id | bigint | Foreign key to users |
| action | enum | uploaded, extraction_started, extraction_completed, extraction_failed, fields_edited, confirmed, rejected, reprocessed |
| metadata | json | Additional context |
| ip_address | string | User's IP address |
| user_agent | string | User's browser/client |
| timestamps | - | created_at, updated_at |

---

## Backend Services

### Location: `app/Services/Documents/`

### 1. DocumentUploadService

**Purpose**: Handles file upload, validation, and storage.

**Key Methods**:
- `upload(UploadedFile $file, int $userId, ?string $documentType): Document`
- `validateFile(UploadedFile $file): void`
- `generateStoredFilename(UploadedFile $file): string`
- `delete(Document $document): bool`

**File Constraints**:
- Max size: 10MB
- Allowed types: PDF, PNG, JPG, JPEG, WebP
- Storage location: `storage/app/documents/{user_id}/`

### 2. AIExtractionService

**Purpose**: Integrates with Claude Vision API for document analysis.

**Key Methods**:
- `extract(Document $document): array`
- `buildPrompt(string $documentType): string`
- `parseResponse(string $response): array`
- `calculateOverallConfidence(array $fieldConfidence): float`

**Document Type Prompts**:
Each document type has a specialized extraction prompt that includes:
- UK-specific context (tax years, date formats, currency)
- Expected field names and formats
- Provider-specific guidance
- Confidence scoring instructions

### 3. DocumentTypeDetector

**Purpose**: Auto-detects document types and maps to target models.

**Key Methods**:
- `detect(string $documentType, array $extractedFields): array`
- `getTargetModel(string $documentType, ?string $subtype): ?string`

**Known UK Providers**:
- Pensions: Aviva, Scottish Widows, Standard Life, Legal & General, Nest, etc.
- Insurance: Aviva, Legal & General, Vitality, Royal London, etc.
- Investments: Hargreaves Lansdown, AJ Bell, Vanguard, Fidelity, etc.

### 4. DocumentProcessor

**Purpose**: Main orchestrator for the document processing workflow.

**Key Methods**:
- `process(Document $document): array` - Full extraction pipeline
- `confirm(Document $document, array $editedFields): array` - Save to target model
- `reextract(Document $document): array` - Re-run extraction
- `delete(Document $document): bool` - Delete document and related data

**Registered Mappers**:
```php
'dc_pension' => DCPensionMapper::class,
'db_pension' => DBPensionMapper::class,
'life_insurance' => LifeInsuranceMapper::class,
'critical_illness' => CriticalIllnessMapper::class,
'income_protection' => IncomeProtectionMapper::class,
'investment_account' => InvestmentAccountMapper::class,
'savings_account' => SavingsAccountMapper::class,
```

### 5. Field Mappers

**Location**: `app/Services/Documents/FieldMappers/`

**Base Class**: `AbstractFieldMapper`

**Common Helper Methods**:
- `parseDate($value): ?string` - Converts various date formats to Y-m-d
- `parseDecimal($value): ?float` - Extracts numeric values from strings
- `parsePercentage($value): ?float` - Converts percentages to decimals
- `parseInt($value): ?int` - Extracts integers
- `parseBool($value): bool` - Converts to boolean

**Implemented Mappers**:

| Mapper | Target Model | Key Fields |
|--------|--------------|------------|
| DCPensionMapper | DCPension | provider, scheme_name, current_fund_value, pension_type, contributions |
| DBPensionMapper | DBPension | scheme_name, scheme_type, annual_income, payment_start_age, lump_sum |
| LifeInsuranceMapper | LifeInsurancePolicy | provider, policy_number, sum_assured, premium_amount, policy_type |
| InvestmentAccountMapper | InvestmentAccount | provider, account_type, current_value, platform_fee, holdings |

---

## API Endpoints

### Base URL: `/api/documents`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | List user's documents |
| POST | `/upload` | Upload and process document |
| POST | `/upload-only` | Upload without processing |
| GET | `/{id}` | Get document details |
| GET | `/{id}/extraction` | Get extraction results |
| POST | `/{id}/confirm` | Confirm and save extracted data |
| POST | `/{id}/reprocess` | Re-run AI extraction |
| DELETE | `/{id}` | Delete document |
| GET | `/types` | Get supported document types |

### Request/Response Examples

**Upload Document**:
```http
POST /api/documents/upload
Content-Type: multipart/form-data

file: [binary]
document_type: pension_statement (optional)
```

**Response**:
```json
{
  "success": true,
  "data": {
    "document_id": 123,
    "document_type": "pension_statement",
    "detected_subtype": "dc_pension",
    "extracted_fields": {
      "provider": "Aviva",
      "scheme_name": "Workplace Pension",
      "current_fund_value": 45000
    },
    "field_confidence": {
      "provider": 0.95,
      "scheme_name": 0.88,
      "current_fund_value": 0.92
    },
    "warnings": [],
    "target_model": "App\\Models\\DCPension"
  }
}
```

**Confirm Extraction**:
```http
POST /api/documents/{id}/confirm
Content-Type: application/json

{
  "fields": {
    "provider": "Aviva",
    "scheme_name": "My Workplace Pension",
    "current_fund_value": 45500
  }
}
```

---

## Frontend Components

### Location: `resources/js/components/Shared/`

### 1. DocumentUploadModal.vue

**Purpose**: Main orchestrator modal with 3-step workflow.

**Props**:
- `documentType` (String, optional): Expected document type

**Events**:
- `@close`: Modal closed
- `@extracted`: Data extracted (returns fields and confidence)
- `@saved`: Data saved to database
- `@manual-entry`: User chose to enter manually

**Steps**:
1. **Upload**: Drag-and-drop file selection
2. **Processing**: Animated progress indicator
3. **Review**: Side-by-side data verification with confidence badges

### 2. UploadDropZone.vue

**Purpose**: Drag-and-drop file upload with validation.

**Props**:
- `acceptedTypes` (Array): Allowed file extensions
- `maxSizeMB` (Number): Maximum file size

**Events**:
- `@file-selected`: File chosen
- `@file-removed`: File removed
- `@error`: Validation error

### 3. ProcessingState.vue

**Purpose**: Animated processing indicator.

**Props**:
- `step` (String): Current step (uploading, analysing, extracting, mapping)
- `uploadProgress` (Number): Upload progress percentage

### 4. ConfidenceBadge.vue

**Purpose**: Visual confidence indicator.

**Props**:
- `confidence` (String|Number): Confidence level or 0-1 value

**Visual Indicators**:
- **Green (checkmark)**: High/Very High confidence (≥0.80)
- **Amber (?)**: Medium confidence (0.60-0.79)
- **Red (!)**: Low/Very Low confidence (<0.60)

---

## Integration Points

### Onboarding Steps

| Component | Document Type | Location |
|-----------|---------------|----------|
| ProtectionPoliciesStep.vue | insurance_policy | Protection tab |
| AssetsStep.vue | pension_statement | Retirement tab |
| AssetsStep.vue | investment_statement | Investments tab |
| AssetsStep.vue | savings_statement | Cash tab |

### Module Dashboards

| Component | Document Type |
|-----------|---------------|
| Protection/CurrentSituation.vue | insurance_policy |
| Retirement/RetirementReadiness.vue | pension_statement |
| Investment/PortfolioOverview.vue | investment_statement |
| Savings/CurrentSituation.vue | savings_statement |

---

## Confidence Scoring

| Score Range | Level | UI Treatment |
|-------------|-------|--------------|
| 0.95 - 1.00 | Very High | Green checkmark, auto-populate |
| 0.80 - 0.94 | High | Green checkmark, allow edit |
| 0.60 - 0.79 | Medium | Amber ?, require confirmation |
| 0.40 - 0.59 | Low | Red !, highlight for review |
| < 0.40 | Very Low | Red !, suggest manual entry |

**Important Fields** (weighted higher):
- Financial values (fund value, sum assured, premium)
- Policy/account numbers
- Provider names

---

## Configuration

### Environment Variables

Add to `.env`:
```
ANTHROPIC_API_KEY=your_api_key_here
```

### Config File

`config/services.php`:
```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
],
```

---

## Activation Steps

1. **Add API Key**:
   ```bash
   # Add to .env
   ANTHROPIC_API_KEY=sk-ant-api03-xxxxx
   ```

2. **Run Migrations**:
   ```bash
   php artisan migrate
   ```

3. **Clear Caches**:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

4. **Build Frontend** (if needed):
   ```bash
   npm run build
   ```

---

## Error Handling

### Upload Errors
- File too large → "Try Again" button
- Invalid file type → "Try Again" button

### Processing Errors
- Document unreadable → "Enter Manually Instead" button
- API timeout → Automatic retry (2 attempts)

### Partial Extraction
- Some fields missing → Warning banner with field list
- Low confidence fields → Highlighted for review

---

## Security Considerations

1. **File Storage**: Files stored with UUID names, not original filenames
2. **User Isolation**: Documents filtered by `user_id`
3. **File Validation**: MIME type and extension validation
4. **Soft Deletes**: Documents are soft-deleted, not permanently removed
5. **Audit Trail**: All actions logged with IP and user agent

---

## Future Enhancements

1. **Additional Document Types**:
   - Mortgage statements
   - Tax documents (P60, P45)
   - Bank statements

2. **Batch Upload**: Upload multiple documents at once

3. **Document Preview**: In-modal PDF/image preview

4. **Historical Extraction**: Compare extractions over time

5. **Confidence Improvements**: Machine learning on user corrections

---

## File Manifest

### Migrations
- `database/migrations/2025_12_05_000001_create_documents_table.php`
- `database/migrations/2025_12_05_000002_create_document_extractions_table.php`
- `database/migrations/2025_12_05_000003_create_document_extraction_logs_table.php`

### Models
- `app/Models/Document.php`
- `app/Models/DocumentExtraction.php`
- `app/Models/DocumentExtractionLog.php`

### Services
- `app/Services/Documents/DocumentUploadService.php`
- `app/Services/Documents/AIExtractionService.php`
- `app/Services/Documents/DocumentTypeDetector.php`
- `app/Services/Documents/DocumentProcessor.php`

### Field Mappers
- `app/Services/Documents/FieldMappers/FieldMapperInterface.php`
- `app/Services/Documents/FieldMappers/AbstractFieldMapper.php`
- `app/Services/Documents/FieldMappers/DCPensionMapper.php`
- `app/Services/Documents/FieldMappers/DBPensionMapper.php`
- `app/Services/Documents/FieldMappers/LifeInsuranceMapper.php`
- `app/Services/Documents/FieldMappers/InvestmentAccountMapper.php`

### Controllers & Requests
- `app/Http/Controllers/Api/DocumentController.php`
- `app/Http/Requests/Documents/UploadDocumentRequest.php`
- `app/Http/Requests/Documents/ConfirmExtractionRequest.php`

### Frontend Components
- `resources/js/components/Shared/DocumentUploadModal.vue`
- `resources/js/components/Shared/UploadDropZone.vue`
- `resources/js/components/Shared/ProcessingState.vue`
- `resources/js/components/Shared/ConfidenceBadge.vue`

### Frontend Services
- `resources/js/services/documentService.js`

### Modified Files (Integration)
- `resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue`
- `resources/js/components/Onboarding/steps/AssetsStep.vue`
- `resources/js/components/Protection/CurrentSituation.vue`
- `resources/js/views/Retirement/RetirementReadiness.vue`
- `resources/js/components/Investment/PortfolioOverview.vue`
- `resources/js/components/Savings/CurrentSituation.vue`
- `routes/api.php`
- `config/services.php`

---

*Documentation created: December 2025*
*Built with [Claude Code](https://claude.com/claude-code)*
