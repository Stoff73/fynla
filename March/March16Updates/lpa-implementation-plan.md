# LPA Implementation Plan -- Fynla Estate Planning Module

**Date:** 16 March 2026
**Companion document:** `lpa-research.md`
**Status:** Planning

---

## 1. Architecture Decisions

### 1.1 New 5th Tab in EstateDashboard

Add a new "Lasting Powers of Attorney" tab to the existing `EstateDashboard.vue`, which currently has 4 tabs:

| # | Current Tab | Tab ID |
|---|-------------|--------|
| 1 | Inheritance Tax Planning | `iht` |
| 2 | Gifting Strategy | `gifting` |
| 3 | Life Policy Strategy | `life-policy` |
| 4 | Trust Strategy | `trusts` |
| **5** | **Lasting Powers of Attorney** | **`lpa`** |

This follows the existing tab-switching pattern in `EstateDashboard.vue` using `activeTab` and `switchTab()`.

### 1.2 Full-Page Wizard for Adding/Editing LPAs

Rather than a single form modal, LPA data entry will use a multi-step wizard component. This mirrors the complexity of the OPG form structure and guides users through the process logically:

| Step | Content |
|------|---------|
| 1 | LPA Type selection (Property & Financial Affairs or Health & Welfare) |
| 2 | Donor details confirmation (pre-populated from user profile) |
| 3 | Attorney details (name, relationship, contact) |
| 4 | How attorneys act (jointly / jointly and severally / mixed) |
| 5 | Preferences and restrictions (free text) |
| 6 | Status and registration details (registered/unregistered, date, OPG reference) |
| 7 | Review and save |

For Health & Welfare LPAs, Step 5 also includes the life-sustaining treatment authority question.

The wizard opens as a full-page overlay (not a small modal), consistent with complex data entry patterns elsewhere in the application.

### 1.3 Three New Database Tables

| Table | Purpose |
|-------|---------|
| `lpas` | Core LPA record (type, status, registration details, preferences) |
| `lpa_attorneys` | Attorney details for each LPA (supports multiple attorneys per LPA) |
| `lpa_documents` | Uploaded document references (scanned LPA copies, registration confirmations) |

### 1.4 Lightweight Compliance Approach

Fynla records and tracks LPAs -- it does not generate legal documents. Every LPA-related screen includes the standard legal disclaimer (matching the `WillPlanning.vue` pattern). The wizard collects planning information, not legally binding data.

### 1.5 Browser Print for Summary

Users can print/save their LPA summary as a reference document using `window.print()` with a print-optimised layout. No PDF generation library required.

---

## 2. Database Schema

### 2.1 Table: `lpas`

Migration file: `database/migrations/2026_03_16_100001_create_lpas_table.php`

```
| Column                          | Type                | Nullable | Default | Notes                                                    |
|---------------------------------|---------------------|----------|---------|----------------------------------------------------------|
| id                              | bigIncrements       | NO       | -       | Primary key                                              |
| user_id                         | unsignedBigInteger  | NO       | -       | FK to users.id                                           |
| lpa_type                        | enum                | NO       | -       | 'property_financial', 'health_welfare'                   |
| status                          | enum                | NO       | 'draft' | 'draft', 'signed', 'registered', 'revoked'              |
| when_attorneys_can_act          | enum                | YES      | NULL    | 'while_has_capacity', 'only_lost_capacity'               |
|                                 |                     |          |         | (Property & Financial only; Health & Welfare always       |
|                                 |                     |          |         | only when lacking capacity)                               |
| how_attorneys_act               | enum                | YES      | NULL    | 'jointly', 'jointly_and_severally', 'mixed'              |
| mixed_decisions_detail          | text                | YES      | NULL    | Free text if how_attorneys_act = 'mixed'                 |
| life_sustaining_treatment_auth  | boolean             | YES      | NULL    | Health & Welfare only: true = Option A (granted),        |
|                                 |                     |          |         | false = Option B (not granted), null = not set           |
| preferences                     | text                | YES      | NULL    | Non-binding wishes / guidance for attorneys               |
| restrictions                    | text                | YES      | NULL    | Binding restrictions/conditions on attorneys              |
| certificate_provider_name       | string(255)         | YES      | NULL    | Name of the certificate provider                         |
| certificate_provider_type       | enum                | YES      | NULL    | 'personal_knowledge', 'professional'                     |
| certificate_provider_profession | string(255)         | YES      | NULL    | If professional, their role (solicitor, doctor, etc.)    |
| opg_reference_number            | string(100)         | YES      | NULL    | OPG registration reference (e.g. 7000-0000-0000)        |
| signed_date                     | date                | YES      | NULL    | Date the LPA was signed by the donor                     |
| registered_date                 | date                | YES      | NULL    | Date the OPG registered the LPA                          |
| registration_fee_paid           | decimal(8,2)        | YES      | NULL    | Fee paid (GBP 82 standard, GBP 41 reduced, GBP 0 exempt)|
| revoked_date                    | date                | YES      | NULL    | Date the LPA was revoked (if applicable)                 |
| revocation_reason               | text                | YES      | NULL    | Reason for revocation                                    |
| last_reviewed_date              | date                | YES      | NULL    | When the user last reviewed this LPA                     |
| review_notes                    | text                | YES      | NULL    | Notes from the last review                               |
| notes                           | text                | YES      | NULL    | General notes                                            |
| created_at                      | timestamp           | NO       | -       | Laravel timestamp                                        |
| updated_at                      | timestamp           | NO       | -       | Laravel timestamp                                        |
| deleted_at                      | timestamp           | YES      | NULL    | Soft delete                                              |
```

**Indexes:**
- `lpas_user_id_foreign` (FK to users)
- `lpas_user_id_lpa_type_index` (composite for querying both LPA types per user)

**Constraints:**
- Each user can have at most one active (non-revoked, non-deleted) LPA of each type. Enforced at the application level, not via unique constraint (to allow soft-deleted historical records).

### 2.2 Table: `lpa_attorneys`

Migration file: `database/migrations/2026_03_16_100002_create_lpa_attorneys_table.php`

```
| Column            | Type               | Nullable | Default | Notes                                               |
|-------------------|--------------------|----------|---------|-----------------------------------------------------|
| id                | bigIncrements      | NO       | -       | Primary key                                         |
| lpa_id            | unsignedBigInteger | NO       | -       | FK to lpas.id (cascade delete)                      |
| attorney_type     | enum               | NO       | -       | 'primary', 'replacement'                            |
| full_name         | string(255)        | NO       | -       | Attorney's full legal name                          |
| date_of_birth     | date               | YES      | NULL    | Attorney's date of birth                            |
| relationship      | string(100)        | YES      | NULL    | Relationship to donor (spouse, child, solicitor...) |
| address           | text               | YES      | NULL    | Attorney's address                                  |
| phone             | string(50)         | YES      | NULL    | Contact phone number                                |
| email             | string(255)        | YES      | NULL    | Contact email                                       |
| sort_order        | tinyInteger        | NO       | 0       | Display ordering                                    |
| created_at        | timestamp          | NO       | -       | Laravel timestamp                                   |
| updated_at        | timestamp          | NO       | -       | Laravel timestamp                                   |
```

**Indexes:**
- `lpa_attorneys_lpa_id_foreign` (FK to lpas, cascade on delete)

### 2.3 Table: `lpa_documents`

Migration file: `database/migrations/2026_03_16_100003_create_lpa_documents_table.php`

```
| Column            | Type               | Nullable | Default   | Notes                                          |
|-------------------|--------------------|----------|-----------|-------------------------------------------------|
| id                | bigIncrements      | NO       | -         | Primary key                                    |
| lpa_id            | unsignedBigInteger | NO       | -         | FK to lpas.id (cascade delete)                 |
| user_id           | unsignedBigInteger | NO       | -         | FK to users.id (for ownership validation)      |
| original_filename | string(255)        | NO       | -         | Original uploaded filename                     |
| stored_filename   | string(255)        | NO       | -         | Server-side stored filename (UUID-based)       |
| disk              | string(50)         | NO       | 'private' | Storage disk                                   |
| path              | string(500)        | NO       | -         | Storage path                                   |
| mime_type         | string(100)        | NO       | -         | File MIME type (application/pdf, image/jpeg...) |
| file_size         | unsignedInteger    | NO       | -         | File size in bytes                              |
| document_label    | string(255)        | YES      | NULL      | User-provided label (e.g. "Registered copy")   |
| created_at        | timestamp          | NO       | -         | Laravel timestamp                               |
| updated_at        | timestamp          | NO       | -         | Laravel timestamp                               |
```

**Indexes:**
- `lpa_documents_lpa_id_foreign` (FK to lpas, cascade on delete)
- `lpa_documents_user_id_foreign` (FK to users)

**File storage:** Documents are stored in the `private` disk (not publicly accessible). Downloaded via a signed URL or controller endpoint that validates ownership.

---

## 3. Backend Files

### 3.1 Models

| File | Purpose |
|------|---------|
| `app/Models/Estate/Lpa.php` | Core LPA model with relationships, casts, scopes |
| `app/Models/Estate/LpaAttorney.php` | Attorney model, belongs to Lpa |
| `app/Models/Estate/LpaDocument.php` | Document model, belongs to Lpa and User |

**Lpa.php key details:**
- `use HasFactory, SoftDeletes;`
- Relationships: `user()`, `attorneys()`, `replacementAttorneys()`, `primaryAttorneys()`, `documents()`
- Scopes: `scopeActive($query)` (not revoked, not deleted), `scopePropertyFinancial($query)`, `scopeHealthWelfare($query)`
- Casts: enums as strings, dates as `date`, `life_sustaining_treatment_auth` as `boolean`, `registration_fee_paid` as `decimal:2`
- Constants: `TYPE_PROPERTY_FINANCIAL`, `TYPE_HEALTH_WELFARE`, `STATUS_DRAFT`, `STATUS_SIGNED`, `STATUS_REGISTERED`, `STATUS_REVOKED`

**LpaAttorney.php key details:**
- Relationships: `lpa()`
- Constants: `TYPE_PRIMARY`, `TYPE_REPLACEMENT`
- Scope: `scopePrimary($query)`, `scopeReplacement($query)`

### 3.2 Services

| File | Purpose |
|------|---------|
| `app/Services/Estate/LpaService.php` | Core CRUD and business logic for LPAs |
| `app/Services/Estate/LpaComplianceService.php` | Validation rules, gap analysis, review reminders |

**LpaService.php responsibilities:**
- `getLpasForUser(User $user): Collection` -- get both LPA types for a user
- `createLpa(User $user, array $data): Lpa` -- create LPA with attorneys in a transaction
- `updateLpa(Lpa $lpa, array $data): Lpa` -- update LPA and sync attorneys
- `revokeLpa(Lpa $lpa, string $reason): Lpa` -- soft-revoke (set status, date, reason)
- `deleteLpa(Lpa $lpa): void` -- soft delete
- `uploadDocument(Lpa $lpa, UploadedFile $file, ?string $label): LpaDocument`
- `deleteDocument(LpaDocument $document): void`
- `getLpaSummary(User $user): array` -- summary data for the tab overview

**LpaComplianceService.php responsibilities:**
- `getGapAnalysis(User $user): array` -- which LPA types are missing
- `getReviewReminders(User $user): array` -- LPAs not reviewed in 12+ months
- `isFullyProtected(User $user): bool` -- has both LPA types registered

### 3.3 Controller

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/Estate/LpaController.php` | API endpoints for LPA CRUD and documents |

**Endpoints:**

| Method | URI | Action | Notes |
|--------|-----|--------|-------|
| GET | `/api/estate/lpas` | `index` | Get all LPAs for authenticated user |
| POST | `/api/estate/lpas` | `store` | Create new LPA with attorneys |
| GET | `/api/estate/lpas/{id}` | `show` | Get single LPA with attorneys and documents |
| PUT | `/api/estate/lpas/{id}` | `update` | Update LPA and attorneys |
| DELETE | `/api/estate/lpas/{id}` | `destroy` | Soft delete LPA |
| POST | `/api/estate/lpas/{id}/revoke` | `revoke` | Revoke an LPA |
| POST | `/api/estate/lpas/{id}/documents` | `uploadDocument` | Upload scanned LPA document |
| DELETE | `/api/estate/lpas/{id}/documents/{docId}` | `deleteDocument` | Delete uploaded document |
| GET | `/api/estate/lpas/{id}/documents/{docId}/download` | `downloadDocument` | Download stored document |
| GET | `/api/estate/lpas/summary` | `summary` | Gap analysis and review status |

### 3.4 Form Requests

| File | Purpose |
|------|---------|
| `app/Http/Requests/Estate/StoreLpaRequest.php` | Validation for creating an LPA |
| `app/Http/Requests/Estate/UpdateLpaRequest.php` | Validation for updating an LPA |
| `app/Http/Requests/Estate/RevokeLpaRequest.php` | Validation for revoking an LPA |
| `app/Http/Requests/Estate/UploadLpaDocumentRequest.php` | Validation for document upload |

**StoreLpaRequest rules (key fields):**

```php
'lpa_type' => ['required', Rule::in(['property_financial', 'health_welfare'])],
'status' => ['required', Rule::in(['draft', 'signed', 'registered'])],
'when_attorneys_can_act' => ['nullable', 'required_if:lpa_type,property_financial', Rule::in(['while_has_capacity', 'only_lost_capacity'])],
'how_attorneys_act' => ['nullable', Rule::in(['jointly', 'jointly_and_severally', 'mixed'])],
'mixed_decisions_detail' => ['nullable', 'required_if:how_attorneys_act,mixed', 'string', 'max:2000'],
'life_sustaining_treatment_auth' => ['nullable', 'boolean'],
'preferences' => ['nullable', 'string', 'max:5000'],
'restrictions' => ['nullable', 'string', 'max:5000'],
'certificate_provider_name' => ['nullable', 'string', 'max:255'],
'certificate_provider_type' => ['nullable', Rule::in(['personal_knowledge', 'professional'])],
'opg_reference_number' => ['nullable', 'string', 'max:100'],
'signed_date' => ['nullable', 'date', 'before_or_equal:today'],
'registered_date' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:signed_date'],
'registration_fee_paid' => ['nullable', 'numeric', 'min:0', 'max:999.99'],

// Attorneys array
'attorneys' => ['required', 'array', 'min:1', 'max:10'],
'attorneys.*.attorney_type' => ['required', Rule::in(['primary', 'replacement'])],
'attorneys.*.full_name' => ['required', 'string', 'max:255'],
'attorneys.*.date_of_birth' => ['nullable', 'date', 'before:today'],
'attorneys.*.relationship' => ['nullable', 'string', 'max:100'],
'attorneys.*.address' => ['nullable', 'string', 'max:1000'],
'attorneys.*.phone' => ['nullable', 'string', 'max:50'],
'attorneys.*.email' => ['nullable', 'email', 'max:255'],
```

**UploadLpaDocumentRequest rules:**

```php
'document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
'document_label' => ['nullable', 'string', 'max:255'],
```

### 3.5 Routes

Add to `routes/api.php` inside the existing `estate` middleware group:

```php
// LPA routes
Route::prefix('lpas')->group(function () {
    Route::get('/summary', [LpaController::class, 'summary']);
    Route::get('/', [LpaController::class, 'index']);
    Route::post('/', [LpaController::class, 'store']);
    Route::get('/{id}', [LpaController::class, 'show']);
    Route::put('/{id}', [LpaController::class, 'update']);
    Route::delete('/{id}', [LpaController::class, 'destroy']);
    Route::post('/{id}/revoke', [LpaController::class, 'revoke']);
    Route::post('/{id}/documents', [LpaController::class, 'uploadDocument']);
    Route::delete('/{id}/documents/{docId}', [LpaController::class, 'deleteDocument']);
    Route::get('/{id}/documents/{docId}/download', [LpaController::class, 'downloadDocument']);
});
```

### 3.6 API Resource

| File | Purpose |
|------|---------|
| `app/Http/Resources/Estate/LpaResource.php` | Transform Lpa model for API response |
| `app/Http/Resources/Estate/LpaAttorneyResource.php` | Transform LpaAttorney for API response |

---

## 4. Frontend Files

### 4.1 Tab Component (Main View)

| File | Purpose |
|------|---------|
| `resources/js/components/Estate/LpaPlanningTab.vue` | Main LPA tab content, displayed when `activeTab === 'lpa'` in EstateDashboard |

**LpaPlanningTab.vue responsibilities:**
- Legal disclaimer banner (matching WillPlanning.vue pattern)
- Preview mode notice (matching WillPlanning.vue pattern)
- Gap analysis display (which LPA types are missing)
- List of existing LPAs with status badges
- "Add Lasting Power of Attorney" button (opens wizard)
- Review reminder alerts for LPAs not reviewed in 12+ months
- Summary statistics (registered count, last review dates)

### 4.2 Display Components

| File | Purpose |
|------|---------|
| `resources/js/components/Estate/LpaCard.vue` | Individual LPA display card (type, status, attorneys, key dates) |
| `resources/js/components/Estate/LpaDetailView.vue` | Full detail view of a single LPA (all fields, attorneys list, documents) |
| `resources/js/components/Estate/LpaGapAnalysis.vue` | Visual display of which LPA types are in place vs missing |
| `resources/js/components/Estate/LpaAttorneyList.vue` | List of attorneys for an LPA (primary and replacement, with contact details) |

### 4.3 Wizard Steps

| File | Purpose |
|------|---------|
| `resources/js/components/Estate/LpaWizard.vue` | Wizard container (step navigation, progress bar, save/cancel) |
| `resources/js/components/Estate/LpaWizardStepType.vue` | Step 1: Select LPA type |
| `resources/js/components/Estate/LpaWizardStepDonor.vue` | Step 2: Confirm donor details (pre-populated from profile) |
| `resources/js/components/Estate/LpaWizardStepAttorneys.vue` | Step 3: Add/edit attorneys (dynamic list, primary + replacement) |
| `resources/js/components/Estate/LpaWizardStepDecisions.vue` | Step 4: How attorneys act + when they can act |
| `resources/js/components/Estate/LpaWizardStepPreferences.vue` | Step 5: Preferences, restrictions, life-sustaining treatment |
| `resources/js/components/Estate/LpaWizardStepRegistration.vue` | Step 6: Registration status, OPG reference, dates, fees |
| `resources/js/components/Estate/LpaWizardStepReview.vue` | Step 7: Review all entered data, confirm and save |

### 4.4 Document Upload

| File | Purpose |
|------|---------|
| `resources/js/components/Estate/LpaDocumentUpload.vue` | Upload scanned LPA documents (drag-and-drop, file picker) |
| `resources/js/components/Estate/LpaDocumentList.vue` | List uploaded documents with download/delete actions |

### 4.5 Shared / Utility Components

No new shared components needed. The wizard will reuse existing patterns:
- Form inputs from the existing design system
- Status badges using existing badge classes (`.badge-active`, `.badge-pending`, etc.)
- `currencyMixin` for fee display
- `previewModeMixin` for preview mode blocking
- `v-preview-disabled` directive on action buttons

### 4.6 Vuex Store Updates

**File:** `resources/js/store/modules/estate.js`

Add to existing estate module (do NOT create a separate store module):

**New state fields:**
```javascript
lpas: [],
lpaSummary: null,
lpaLoading: false,
lpaError: null,
```

**New mutations:**
```javascript
setLpas(state, lpas)
addLpa(state, lpa)
updateLpa(state, lpa)
removeLpa(state, id)
setLpaSummary(state, summary)
setLpaLoading(state, loading)
setLpaError(state, error)
```

**New actions:**
```javascript
async fetchLpas({ commit })
async fetchLpaSummary({ commit })
async createLpa({ commit }, data)
async updateLpa({ commit }, { id, data })
async deleteLpa({ commit }, id)
async revokeLpa({ commit }, { id, reason })
async uploadLpaDocument({ commit }, { lpaId, formData })
async deleteLpaDocument({ commit }, { lpaId, docId })
```

### 4.7 API Service Updates

**File:** `resources/js/services/estateService.js`

Add new methods to the existing estate service:

```javascript
// LPA endpoints
async getLpas()
async getLpaSummary()
async createLpa(data)
async getLpa(id)
async updateLpa(id, data)
async deleteLpa(id)
async revokeLpa(id, reason)
async uploadLpaDocument(lpaId, formData)
async deleteLpaDocument(lpaId, docId)
async downloadLpaDocument(lpaId, docId)
```

### 4.8 EstateDashboard.vue Changes

Minimal changes to existing file:

1. Import `LpaPlanningTab` component
2. Register in `components`
3. Add tab entry: `{ id: 'lpa', label: 'Lasting Powers of Attorney' }`
4. Add conditional render: `<LpaPlanningTab v-else-if="activeTab === 'lpa'" @switch-tab="switchTab" />`

---

## 5. Testing Plan

### 5.1 Factories

| File | Purpose |
|------|---------|
| `database/factories/Estate/LpaFactory.php` | Generate test LPA records |
| `database/factories/Estate/LpaAttorneyFactory.php` | Generate test attorney records |
| `database/factories/Estate/LpaDocumentFactory.php` | Generate test document records |

**LpaFactory states:**
- `propertyFinancial()` -- Property & Financial Affairs type
- `healthWelfare()` -- Health & Welfare type
- `draft()` -- Draft status
- `signed()` -- Signed status with signed_date
- `registered()` -- Registered status with signed_date, registered_date, opg_reference
- `revoked()` -- Revoked status with revocation details
- `withLifeSustainingAuth()` -- Health & Welfare with Option A
- `withoutLifeSustainingAuth()` -- Health & Welfare with Option B
- `needsReview()` -- last_reviewed_date > 12 months ago

**LpaAttorneyFactory states:**
- `primary()` -- Primary attorney
- `replacement()` -- Replacement attorney
- `spouse()` -- Relationship set to 'Spouse'
- `solicitor()` -- Relationship set to 'Solicitor', professional contact details

### 5.2 Unit Tests

| File | Tests |
|------|-------|
| `tests/Unit/Services/Estate/LpaServiceTest.php` | CRUD operations, attorney sync, document upload/delete, summary generation |
| `tests/Unit/Services/Estate/LpaComplianceServiceTest.php` | Gap analysis, review reminders, fully protected check |
| `tests/Unit/Models/Estate/LpaTest.php` | Model relationships, scopes, casts, constants |
| `tests/Unit/Models/Estate/LpaAttorneyTest.php` | Model relationships, scopes |

**Key test cases for LpaServiceTest:**
- Creates LPA with attorneys in a single transaction
- Updates LPA and syncs attorneys (add, remove, update)
- Prevents duplicate active LPAs of the same type for a user
- Revokes LPA correctly (sets status, date, reason)
- Soft deletes LPA and cascades to attorneys
- Uploads document with correct storage path
- Deletes document and removes from storage
- Returns correct summary data

**Key test cases for LpaComplianceServiceTest:**
- Identifies missing Property & Financial Affairs LPA
- Identifies missing Health & Welfare LPA
- Identifies LPAs needing review (12+ months)
- Returns fully protected = true when both types registered
- Returns fully protected = false when only one type exists
- Returns fully protected = false when LPA is draft/signed but not registered

### 5.3 Feature Tests

| File | Tests |
|------|-------|
| `tests/Feature/Api/Estate/LpaControllerTest.php` | API endpoint tests with authentication |

**Key test cases:**
- `it('returns all LPAs for authenticated user')`
- `it('creates a Property & Financial Affairs LPA with attorneys')`
- `it('creates a Health & Welfare LPA with life-sustaining treatment choice')`
- `it('validates required fields when creating LPA')`
- `it('prevents creating duplicate active LPA of same type')`
- `it('updates LPA and syncs attorneys')`
- `it('revokes an LPA with reason')`
- `it('soft deletes an LPA')`
- `it('uploads a document to an LPA')`
- `it('rejects oversized document uploads')`
- `it('rejects invalid file types')`
- `it('downloads a document for the owning user')`
- `it('prevents downloading another user document')`
- `it('returns gap analysis summary')`
- `it('returns 401 for unauthenticated requests')`
- `it('blocks preview user writes via PreviewWriteInterceptor')`
- `it('validates when_attorneys_can_act is required for property_financial type')`
- `it('ignores when_attorneys_can_act for health_welfare type')`

### 5.4 Preview Persona Data

Add LPA seed data to the `PreviewUserSeeder` for relevant personas:

| Persona | LPA Data |
|---------|----------|
| **peak_earners** (David & Sarah Mitchell) | Both LPA types registered, 2 primary attorneys each, 1 replacement. Sarah has Health & Welfare with life-sustaining treatment authority granted. Last reviewed 6 months ago. |
| **widow** (Margaret Thompson) | Property & Financial Affairs LPA registered (daughter as attorney). Health & Welfare LPA in draft status (not yet completed). Triggers gap analysis alert. |
| **retired_couple** (Robert & Patricia Williams) | Both LPA types registered for both spouses. Each spouse is the other's primary attorney with adult children as replacements. Reviewed 14 months ago (triggers review reminder). |
| **young_family** (James & Emily Carter) | No LPAs. Gap analysis shows both types missing. |
| **entrepreneur** (Alex Chen) | Property & Financial Affairs LPA registered (business partner as co-attorney with spouse). No Health & Welfare LPA. |
| **young_saver** (John Morgan) | No LPAs (typical for younger single users). |

---

## 6. Implementation Order

### Step 1: Database (Migration + Models)
1. Create migration for `lpas` table
2. Create migration for `lpa_attorneys` table
3. Create migration for `lpa_documents` table
4. Create `Lpa` model with relationships, casts, scopes
5. Create `LpaAttorney` model
6. Create `LpaDocument` model
7. Add `lpas()` relationship to `User` model
8. Run migrations, then reseed: `php artisan migrate && php artisan db:seed`

### Step 2: Factories
1. Create `LpaFactory` with all states
2. Create `LpaAttorneyFactory` with all states
3. Create `LpaDocumentFactory`
4. Verify factories work: `Lpa::factory()->propertyFinancial()->registered()->create()`

### Step 3: Backend Services
1. Create `LpaService` with full CRUD, document handling, summary
2. Create `LpaComplianceService` with gap analysis, review reminders
3. Write unit tests for both services

### Step 4: Backend API Layer
1. Create `StoreLpaRequest`, `UpdateLpaRequest`, `RevokeLpaRequest`, `UploadLpaDocumentRequest`
2. Create `LpaResource` and `LpaAttorneyResource`
3. Create `LpaController` with all endpoints
4. Add routes to `routes/api.php` inside estate group
5. Write feature tests for all endpoints
6. Run full test suite: `./vendor/bin/pest`

### Step 5: Frontend Store + Service
1. Add LPA state, mutations, and actions to `resources/js/store/modules/estate.js`
2. Add LPA API methods to `resources/js/services/estateService.js`

### Step 6: Frontend Components -- Tab + Display
1. Create `LpaPlanningTab.vue` (main tab view)
2. Create `LpaCard.vue` (LPA summary card)
3. Create `LpaDetailView.vue` (full detail view)
4. Create `LpaGapAnalysis.vue` (missing LPA alert)
5. Create `LpaAttorneyList.vue` (attorney display)
6. Update `EstateDashboard.vue` (add 5th tab)

### Step 7: Frontend Components -- Wizard + Documents
1. Create `LpaWizard.vue` (wizard container)
2. Create all 7 wizard step components
3. Create `LpaDocumentUpload.vue`
4. Create `LpaDocumentList.vue`

### Step 8: Preview Persona Seeding + Final Verification
1. Add LPA seed data to `PreviewUserSeeder`
2. Run `php artisan db:seed`
3. Test all 6 preview personas via landing page
4. Run full test suite: `./vendor/bin/pest`
5. Verify mobile dashboard still works (LPA data should not affect mobile aggregator)

---

## 7. Verification Checklist

### Database
- [ ] All 3 migrations run cleanly
- [ ] Models have correct relationships (Lpa hasMany attorneys, hasMany documents)
- [ ] Soft deletes work on Lpa model
- [ ] Cascade deletes work on attorneys and documents when LPA is hard-deleted
- [ ] Factories generate valid records for all states

### Backend
- [ ] All CRUD endpoints return correct status codes and response format
- [ ] Ownership validation: users can only access their own LPAs
- [ ] Duplicate prevention: cannot create two active LPAs of the same type
- [ ] Document upload: validates file type and size, stores in private disk
- [ ] Document download: validates ownership before serving file
- [ ] Gap analysis: correctly identifies missing LPA types
- [ ] Review reminders: correctly identifies LPAs needing review
- [ ] PreviewWriteInterceptor: blocks preview user writes (except calculation routes)
- [ ] All unit tests pass
- [ ] All feature tests pass

### Frontend
- [ ] 5th tab appears in EstateDashboard and switches correctly
- [ ] Legal disclaimer appears on all LPA screens
- [ ] Preview mode notice appears for preview users
- [ ] Gap analysis displays correctly for users with missing LPAs
- [ ] LPA cards display correct status badges
- [ ] Wizard navigates through all 7 steps
- [ ] Wizard pre-populates donor details from user profile
- [ ] Attorney add/remove works in wizard step 3
- [ ] Life-sustaining treatment question only appears for Health & Welfare type
- [ ] When attorneys can act question only appears for Property & Financial type
- [ ] Document upload works (drag-and-drop and file picker)
- [ ] Document download works
- [ ] `v-preview-disabled` blocks add/edit/delete actions in preview mode
- [ ] `currencyMixin` used for fee display (not local formatCurrency)
- [ ] No hardcoded hex colours -- all from Tailwind palette
- [ ] No amber/orange colours used
- [ ] All user-facing text spells out acronyms (except ISA)
- [ ] British English in user-facing text
- [ ] Browser print produces clean output

### Preview Personas
- [ ] peak_earners: both LPA types visible, registered status
- [ ] widow: one registered, one draft, gap analysis alert shown
- [ ] retired_couple: both types for both spouses, review reminder shown
- [ ] young_family: no LPAs, full gap analysis shown
- [ ] entrepreneur: one type only, partial gap analysis
- [ ] young_saver: no LPAs, gap analysis shown

### Integration
- [ ] Estate dashboard loads without errors when LPA tab is selected
- [ ] Switching between all 5 tabs works smoothly
- [ ] Estate module data loading includes LPA summary
- [ ] Mobile dashboard unaffected (no LPA data in mobile aggregator)
- [ ] Full test suite passes: `./vendor/bin/pest`
- [ ] `php artisan db:seed` completes successfully after all changes

---

## 8. Files Summary

### New Files (28 total)

**Database (3 migrations):**
- `database/migrations/2026_03_16_100001_create_lpas_table.php`
- `database/migrations/2026_03_16_100002_create_lpa_attorneys_table.php`
- `database/migrations/2026_03_16_100003_create_lpa_documents_table.php`

**Models (3):**
- `app/Models/Estate/Lpa.php`
- `app/Models/Estate/LpaAttorney.php`
- `app/Models/Estate/LpaDocument.php`

**Services (2):**
- `app/Services/Estate/LpaService.php`
- `app/Services/Estate/LpaComplianceService.php`

**Controller (1):**
- `app/Http/Controllers/Api/Estate/LpaController.php`

**Form Requests (4):**
- `app/Http/Requests/Estate/StoreLpaRequest.php`
- `app/Http/Requests/Estate/UpdateLpaRequest.php`
- `app/Http/Requests/Estate/RevokeLpaRequest.php`
- `app/Http/Requests/Estate/UploadLpaDocumentRequest.php`

**API Resources (2):**
- `app/Http/Resources/Estate/LpaResource.php`
- `app/Http/Resources/Estate/LpaAttorneyResource.php`

**Factories (3):**
- `database/factories/Estate/LpaFactory.php`
- `database/factories/Estate/LpaAttorneyFactory.php`
- `database/factories/Estate/LpaDocumentFactory.php`

**Frontend Components (14):**
- `resources/js/components/Estate/LpaPlanningTab.vue`
- `resources/js/components/Estate/LpaCard.vue`
- `resources/js/components/Estate/LpaDetailView.vue`
- `resources/js/components/Estate/LpaGapAnalysis.vue`
- `resources/js/components/Estate/LpaAttorneyList.vue`
- `resources/js/components/Estate/LpaWizard.vue`
- `resources/js/components/Estate/LpaWizardStepType.vue`
- `resources/js/components/Estate/LpaWizardStepDonor.vue`
- `resources/js/components/Estate/LpaWizardStepAttorneys.vue`
- `resources/js/components/Estate/LpaWizardStepDecisions.vue`
- `resources/js/components/Estate/LpaWizardStepPreferences.vue`
- `resources/js/components/Estate/LpaWizardStepRegistration.vue`
- `resources/js/components/Estate/LpaWizardStepReview.vue`
- `resources/js/components/Estate/LpaDocumentUpload.vue`
- `resources/js/components/Estate/LpaDocumentList.vue` (shares list with detail view)

Wait -- that is 15 components. Corrected count: 15 frontend components.

**Tests (4):**
- `tests/Unit/Services/Estate/LpaServiceTest.php`
- `tests/Unit/Services/Estate/LpaComplianceServiceTest.php`
- `tests/Unit/Models/Estate/LpaTest.php`
- `tests/Feature/Api/Estate/LpaControllerTest.php`

### Modified Files (4)

- `resources/js/views/Estate/EstateDashboard.vue` -- add 5th tab
- `resources/js/store/modules/estate.js` -- add LPA state/mutations/actions
- `resources/js/services/estateService.js` -- add LPA API methods
- `routes/api.php` -- add LPA routes inside estate group
- `database/seeders/PreviewUserSeeder.php` -- add LPA seed data for preview personas

That is 5 modified files.

**Total: 32 new files + 5 modified files = 37 file operations.**
