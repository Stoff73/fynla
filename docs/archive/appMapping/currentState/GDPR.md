# GDPR & Privacy Module

## 1. System Overview

The GDPR & Privacy module implements the UK General Data Protection Regulation requirements for Fynla, providing users with self-service tools to exercise their data rights. The module covers four GDPR articles:

- **Article 6/7 - Consent Management**: Granular consent tracking with version-aware re-consent detection.
- **Article 15/20 - Data Export (Subject Access Request / Right to Portability)**: Full data export in JSON or CSV format covering all financial modules.
- **Article 17 - Right to Erasure**: Self-service account and data deletion with a 3-step verification wizard.
- **Article 21 - Right to Object**: Ability to withdraw marketing consent independently.

### Architecture

The module follows a simplified architecture compared to the financial modules. It has no dedicated Agent or Vuex store; the Vue component calls the API service directly:

```
PrivacySettings.vue → api.js (axios) → GDPRController → Services → Models → DB
```

### File Inventory

| Layer       | File                                                    | Purpose                            |
|-------------|---------------------------------------------------------|------------------------------------|
| Controller  | `app/Http/Controllers/Api/GDPRController.php`           | All GDPR endpoints (14 methods)    |
| Model       | `app/Models/UserConsent.php`                            | Consent records with versioning    |
| Model       | `app/Models/ErasureRequest.php`                         | Deletion request lifecycle         |
| Model       | `app/Models/DataExport.php`                             | Export request lifecycle           |
| Model       | `app/Models/AuditLog.php`                               | GDPR event audit constants         |
| Service     | `app/Services/GDPR/ConsentService.php`                  | Consent business logic             |
| Service     | `app/Services/GDPR/DataErasureService.php`              | Erasure orchestration              |
| Service     | `app/Services/GDPR/DataExportService.php`               | Export generation and file mgmt    |
| Service     | `app/Services/Audit/AuditService.php`                   | Audit logging facade               |
| Mailable    | `app/Mail/DeletionVerificationCode.php`                 | 6-digit deletion verification code |
| Vue         | `resources/js/views/Settings/PrivacySettings.vue`       | Full privacy settings page         |
| Migration   | `database/migrations/2026_01_19_140001_...`             | erasure_requests table             |
| Migration   | `database/migrations/2026_01_19_140002_...`             | user_consents table                |
| Migration   | `database/migrations/2026_01_19_140003_...`             | data_exports table                 |
| Test        | `tests/Feature/Auth/GDPRApiTest.php`                    | Feature tests                      |

---

## 2. Database Schema

### 2.1 user_consents

Tracks individual consent grants per user, per type, per version. A unique constraint on `(user_id, consent_type, version)` enables the `updateOrCreate` pattern used by `UserConsent::recordConsent()`.

```sql
CREATE TABLE `user_consents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `consent_type` varchar(100) NOT NULL,       -- terms | privacy | marketing | data_processing
  `version` varchar(50) NOT NULL,             -- v1.0, v2.0 etc.
  `consented` tinyint(1) NOT NULL DEFAULT 0,
  `consented_at` timestamp NULL DEFAULT NULL,
  `withdrawn_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_consents_user_id_consent_type_version_unique` (`user_id`, `consent_type`, `version`),
  KEY `user_consents_user_id_consent_type_index` (`user_id`, `consent_type`),
  CONSTRAINT `user_consents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 erasure_requests

Tracks the lifecycle of a deletion request from initiation through completion or cancellation.

```sql
CREATE TABLE `erasure_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',  -- pending | processing | completed | cancelled
  `reason` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `data_categories_deleted` json DEFAULT NULL,
  `processed_by` varchar(255) DEFAULT NULL,          -- admin ID or 'self-service'
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `erasure_requests_user_id_status_index` (`user_id`, `status`),
  KEY `erasure_requests_status_index` (`status`),
  CONSTRAINT `erasure_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.3 data_exports

Tracks data export requests with file management and 7-day expiry.

```sql
CREATE TABLE `data_exports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',  -- pending | processing | completed | failed | expired
  `format` varchar(20) NOT NULL DEFAULT 'json',     -- json | csv
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `downloaded_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `data_exports_user_id_status_index` (`user_id`, `status`),
  KEY `data_exports_expires_at_index` (`expires_at`),
  CONSTRAINT `data_exports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Entity Relationship

```
users (1) ──→ (N) user_consents     (one per type per version)
users (1) ──→ (N) erasure_requests  (typically 0 or 1 active)
users (1) ──→ (N) data_exports      (one active at a time)
users (1) ──→ (N) audit_logs        (event_type = 'gdpr')
```

All three GDPR tables use `cascadeOnDelete` foreign keys to the `users` table. This means if the user record is hard-deleted (via `forceDelete()`), the database automatically cascades the deletion to dependent GDPR records.

---

## 3. Models

### 3.1 UserConsent

**File:** `app/Models/UserConsent.php`

#### Constants

```php
// Consent types
public const TYPE_TERMS           = 'terms';
public const TYPE_PRIVACY         = 'privacy';
public const TYPE_MARKETING       = 'marketing';
public const TYPE_DATA_PROCESSING = 'data_processing';

// Version map - bump these when policies change to trigger re-consent
public const CURRENT_VERSIONS = [
    self::TYPE_TERMS           => 'v1.0',
    self::TYPE_PRIVACY         => 'v1.0',
    self::TYPE_MARKETING       => 'v1.0',
    self::TYPE_DATA_PROCESSING => 'v1.0',
];
```

#### Required vs Optional Consents

Three consent types are **required** for the application to function: `terms`, `privacy`, `data_processing`. The `marketing` consent is **optional** and can be freely toggled by the user.

#### Fillable Fields

```php
protected $fillable = [
    'user_id', 'consent_type', 'version',
    'consented', 'consented_at', 'withdrawn_at',
    'ip_address', 'user_agent',
];
```

#### Casts

```php
protected $casts = [
    'consented'    => 'boolean',
    'consented_at' => 'datetime',
    'withdrawn_at' => 'datetime',
];
```

#### Relationships

| Method  | Type      | Target |
|---------|-----------|--------|
| `user()` | BelongsTo | `User` |

#### Static Methods

| Method | Signature | Description |
|--------|-----------|-------------|
| `recordConsent` | `(int $userId, string $consentType, bool $consented = true, ?string $version = null): self` | Uses `updateOrCreate` on the `(user_id, consent_type, version)` unique key. Records IP and user agent from the current request. |
| `hasConsent` | `(int $userId, string $consentType, ?string $version = null): bool` | Checks if active consent exists for the given type and current version. |
| `getUserConsents` | `(int $userId): array` | Returns an associative array keyed by consent type, each with `consented`, `version`, and `consented_at` fields. Iterates over `CURRENT_VERSIONS` to ensure all types are represented. |

#### Instance Methods

| Method | Description |
|--------|-------------|
| `withdraw(): void` | Sets `consented = false` and `withdrawn_at = now()`. |

#### Query Scopes

| Scope | Filter |
|-------|--------|
| `scopeForUser(Builder $query, int $userId)` | `WHERE user_id = ?` |
| `scopeOfType(Builder $query, string $type)` | `WHERE consent_type = ?` |

---

### 3.2 ErasureRequest

**File:** `app/Models/ErasureRequest.php`

#### Constants

```php
public const STATUS_PENDING    = 'pending';
public const STATUS_PROCESSING = 'processing';
public const STATUS_COMPLETED  = 'completed';
public const STATUS_CANCELLED  = 'cancelled';
```

#### Fillable Fields

```php
protected $fillable = [
    'user_id', 'status', 'reason',
    'requested_at', 'confirmed_at', 'completed_at', 'cancelled_at',
    'data_categories_deleted', 'processed_by',
];
```

#### Casts

```php
protected $casts = [
    'requested_at'           => 'datetime',
    'confirmed_at'           => 'datetime',
    'completed_at'           => 'datetime',
    'cancelled_at'           => 'datetime',
    'data_categories_deleted' => 'array',
];
```

#### Boot Logic

The `creating` event auto-sets `requested_at` to `now()` if not already provided.

#### Relationships

| Method  | Type      | Target |
|---------|-----------|--------|
| `user()` | BelongsTo | `User` |

#### Status Methods

| Method | Description |
|--------|-------------|
| `isPending(): bool` | Status is `pending`. |
| `isProcessing(): bool` | Status is `processing`. |
| `isCompleted(): bool` | Status is `completed`. |
| `isCancelled(): bool` | Status is `cancelled`. |

#### State Transition Methods

| Method | Transition | Fields Updated |
|--------|-----------|----------------|
| `confirm(): void` | `pending` -> `processing` | `status`, `confirmed_at` |
| `complete(array $deletedCategories, ?string $processedBy): void` | `processing` -> `completed` | `status`, `completed_at`, `data_categories_deleted`, `processed_by` |
| `cancel(): void` | any -> `cancelled` | `status`, `cancelled_at` |

#### Query Scopes

| Scope | Filter |
|-------|--------|
| `scopePending(Builder $query)` | `WHERE status = 'pending'` |
| `scopeProcessing(Builder $query)` | `WHERE status = 'processing'` |

---

### 3.3 DataExport

**File:** `app/Models/DataExport.php`

#### Constants

```php
// Status lifecycle
public const STATUS_PENDING    = 'pending';
public const STATUS_PROCESSING = 'processing';
public const STATUS_COMPLETED  = 'completed';
public const STATUS_FAILED     = 'failed';
public const STATUS_EXPIRED    = 'expired';

// Supported formats
public const FORMAT_JSON = 'json';
public const FORMAT_CSV  = 'csv';

// Export file TTL
public const EXPIRY_DAYS = 7;
```

#### Fillable Fields

```php
protected $fillable = [
    'user_id', 'status', 'format', 'file_path', 'file_size',
    'requested_at', 'completed_at', 'expires_at', 'downloaded_at',
    'ip_address',
];
```

#### Casts

```php
protected $casts = [
    'file_size'     => 'integer',
    'requested_at'  => 'datetime',
    'completed_at'  => 'datetime',
    'expires_at'    => 'datetime',
    'downloaded_at' => 'datetime',
];
```

#### Relationships

| Method  | Type      | Target |
|---------|-----------|--------|
| `user()` | BelongsTo | `User` |

#### Static Methods

| Method | Signature | Description |
|--------|-----------|-------------|
| `createRequest` | `(int $userId, string $format = 'json'): self` | Creates a new pending export with IP address from the current request. |

#### State Transition Methods

| Method | Transition | Fields Updated |
|--------|-----------|----------------|
| `markProcessing(): void` | `pending` -> `processing` | `status` |
| `markCompleted(string $filePath, int $fileSize): void` | `processing` -> `completed` | `status`, `file_path`, `file_size`, `completed_at`, `expires_at` (now + 7 days) |
| `markFailed(): void` | any -> `failed` | `status` |
| `markExpired(): void` | any -> `expired` | `status` |
| `markDownloaded(): void` | n/a | `downloaded_at` |

#### Status Methods

| Method | Description |
|--------|-------------|
| `isPending(): bool` | Status is `pending`. |
| `isProcessing(): bool` | Status is `processing`. |
| `isCompleted(): bool` | Status is `completed`. |
| `isExpired(): bool` | Status is `expired` OR `expires_at` is in the past. |
| `isDownloadable(): bool` | Completed AND not expired AND `file_path` is set. |

#### Query Scopes

| Scope | Filter |
|-------|--------|
| `scopePending(Builder $query)` | `WHERE status = 'pending'` |
| `scopeCompleted(Builder $query)` | `WHERE status = 'completed'` |
| `scopeExpired(Builder $query)` | `WHERE expires_at < NOW() AND status != 'expired'` |

---

## 4. Controllers

### GDPRController

**File:** `app/Http/Controllers/Api/GDPRController.php`

All endpoints are under the `auth` middleware group (Sanctum authenticated). The controller receives five injected dependencies:

```php
public function __construct(
    private DataExportService $exportService,
    private DataErasureService $erasureService,
    private ConsentService $consentService,
    private MFAService $mfaService,
    private AuditService $auditService
) {}
```

### Consent Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `getConsents(Request)` | `GET /auth/gdpr/consents` | Returns all consent statuses for the user plus `needs_reconsent` array. |
| `updateConsents(Request)` | `PUT /auth/gdpr/consents` | Validates `{consents: {type: bool}}` and batch-updates. Filters against `CURRENT_VERSIONS` keys. |
| `getConsentHistory(Request)` | `GET /auth/gdpr/consents/history` | Returns full consent history with timestamps. |

### Export Endpoints

| Method | Endpoint | Middleware | Description |
|--------|----------|-----------|-------------|
| `requestExport(Request)` | `POST /auth/gdpr/export` | `throttle:export` (3/hour) | Accepts optional `format` (json/csv). Creates export, processes synchronously, returns status. |
| `getExportStatus(Request)` | `GET /auth/gdpr/export/status` | none | Returns the most recent export record for the user. |
| `downloadExport(Request, int $id)` | `GET /auth/gdpr/export/{id}/download` | none | Validates ownership and downloadability. Returns a streamed file download. |

### Self-Service Deletion Endpoints (New 3-Step Flow)

| Method | Endpoint | Middleware | Description |
|--------|----------|-----------|-------------|
| `initiateErasure(Request)` | `POST /auth/gdpr/erasure/initiate` | `throttle:sensitive` (3/min) | Step 1: Validates type (account/data), blocks preview users, generates 64-char session token cached for 15 min. If 2FA enabled returns `requires_2fa=true`; otherwise sends verification email. |
| `verifyErasure(Request)` | `POST /auth/gdpr/erasure/verify` | `throttle:sensitive` (3/min) | Step 2: Validates session token and 6-digit code. Max 3 attempts before lockout (cache cleared). Verifies via TOTP or bcrypt email hash. Marks session as verified on success. |
| `executeErasure(Request)` | `POST /auth/gdpr/erasure/execute` | `throttle:sensitive` (3/min) | Step 3: Validates session is verified. Requires case-sensitive confirmation phrase. For `account`: creates ErasureRequest, confirms, processes (full deletion). For `data`: calls `deleteDataOnly()`, logs audit event. |
| `resendDeletionCode(Request)` | `POST /auth/gdpr/erasure/resend-code` | `throttle:sensitive` (3/min) | Resends email verification code. Only for email flow (rejects if 2FA enabled). Generates a new code. |

### Legacy Erasure Endpoints (Deprecated)

| Method | Endpoint | Middleware | Description |
|--------|----------|-----------|-------------|
| `requestErasure(Request)` | `POST /auth/gdpr/erasure` | `throttle:sensitive` | Legacy: requires `confirm=true`, creates pending request. |
| `getErasureStatus(Request)` | `GET /auth/gdpr/erasure/status` | none | Legacy: returns most recent erasure request status. |
| `confirmErasure(Request, int $id)` | `POST /auth/gdpr/erasure/{id}/confirm` | `throttle:sensitive` | Legacy: confirms and processes erasure immediately. |
| `cancelErasure(Request, int $id)` | `POST /auth/gdpr/erasure/{id}/cancel` | none | Legacy: cancels a pending/processing erasure request. |

### Private Helper Methods

| Method | Description |
|--------|-------------|
| `sendDeletionVerificationEmail(User $user): void` | Generates a 6-digit zero-padded code, bcrypt-hashes it, caches under `deletion_code:{user_id}` for 15 min, sends `DeletionVerificationCode` mailable. |
| `verifyDeletionEmailCode(User $user, string $code): bool` | Retrieves cached hash, verifies via `Hash::check()`. |

---

## 5. Agent

**No dedicated agent.** The GDPR module does not have a dedicated Agent class. Unlike the financial planning modules (ProtectionAgent, SavingsAgent, etc.), the GDPR operations are simple CRUD workflows that do not require multi-service orchestration. The `GDPRController` delegates directly to the three GDPR services.

---

## 6. Services

### 6.1 ConsentService

**File:** `app/Services/GDPR/ConsentService.php`

A thin service layer over the `UserConsent` model. Has no injected dependencies.

| Method | Signature | Description |
|--------|-----------|-------------|
| `recordConsent` | `(User $user, string $consentType, bool $consented = true): UserConsent` | Delegates to `UserConsent::recordConsent()`. |
| `recordConsents` | `(User $user, array $consents): array` | Iterates over `{type => bool}` map and calls `recordConsent()` for each. Returns array of `UserConsent` records keyed by type. |
| `withdrawConsent` | `(User $user, string $consentType): void` | Finds the current-version consent record and calls `withdraw()`. No-op if not found. |
| `hasConsent` | `(User $user, string $consentType): bool` | Delegates to `UserConsent::hasConsent()`. |
| `hasRequiredConsents` | `(User $user): bool` | Returns true if user has active consent for `terms`, `privacy`, and `data_processing`. Does NOT check `marketing`. |
| `getUserConsents` | `(User $user): array` | Delegates to `UserConsent::getUserConsents()`. |
| `getConsentHistory` | `(User $user): Collection` | Returns all consent records for the user ordered by `created_at DESC`. |
| `needsReconsent` | `(User $user, string $consentType): bool` | Compares the user's latest consented version against `CURRENT_VERSIONS`. Returns true if no consent exists or version mismatch. |
| `getConsentTypesNeedingReconsent` | `(User $user): array` | Returns array of consent type strings that need re-consent. |

### 6.2 DataErasureService

**File:** `app/Services/GDPR/DataErasureService.php`

Orchestrates data deletion. Injected dependency: `AuditService`.

| Method | Visibility | Signature | Description |
|--------|-----------|-----------|-------------|
| `requestErasure` | public | `(User $user, ?string $reason = null): ErasureRequest` | Checks for existing pending/processing request (returns it if found). Creates new pending request. Logs `erasure_requested` audit event. |
| `confirmErasure` | public | `(ErasureRequest $request): void` | Validates request is pending. Calls `$request->confirm()` (sets status to `processing`). |
| `cancelErasure` | public | `(ErasureRequest $request): void` | Validates request is not completed or cancelled. Calls `$request->cancel()`. |
| `processErasure` | public | `(ErasureRequest $request, ?string $processedBy = null): void` | Validates request is `processing`. Runs full deletion in a DB transaction. See Deletion Order below. |
| `deleteDataOnly` | public | `(User $user): array` | Deletes financial data but preserves account. Nullifies `employment_status`, `salary`, `national_insurance_number`. Logs `erasure_completed` audit event. Returns array of deleted categories. |
| `deleteFinancialData` | private | `(User $user): array` | Core financial data deletion. See Deletion Order below. |
| `deleteDocuments` | private | `(User $user): array` | Deletes document files from storage, then deletes DB records. |
| `deleteExports` | private | `(User $user): array` | Deletes export files from storage, then deletes DB records. |
| `deleteUser` | private | `(User $user): void` | Handles bilateral spouse cleanup, revokes tokens, deletes sessions, then `forceDelete()` the user. |
| `getPendingRequests` | public | `(): Collection` | Admin helper: returns all pending requests with user info. |

#### Full Account Erasure - Deletion Order

The `processErasure()` method runs inside a single `DB::transaction()`:

```
1. deleteFinancialData()
   a. Goals               (forceDelete - bypasses SoftDeletes)
   b. Protection policies  (life, critical illness, income protection)
   c. Pensions            (DC, DB, state)
   d. Investment accounts  (holdings first, then accounts)
   e. Savings accounts
   f. Mortgages, then properties
   g. Business interests
   h. Chattels
   i. Family members
   j. Consents

2. deleteDocuments()
   a. Storage files
   b. DB records

3. deleteExports()
   a. Storage files
   b. DB records

4. Audit logs          (hard DELETE, not anonymize)

5. Erasure request     (forceDelete - must happen before user deletion due to FK)

6. deleteUser()
   a. Spouse cleanup (bilateral - clears spouse_id on both sides, deletes spouse's family_member record)
   b. Revoke API tokens
   c. Delete sessions
   d. forceDelete user record
```

#### Data-Only Deletion

The `deleteDataOnly()` method runs steps 1a-1j above inside a transaction, then additionally nullifies three profile fields: `employment_status`, `salary`, `national_insurance_number`. The user account and audit trail are preserved.

#### Spouse Cleanup Detail

When a user with a linked spouse is deleted:

1. The spouse's `spouse_id` is set to null.
2. The spouse's `family_member` record with `relationship='spouse'` is deleted.
3. Any other user who references this user as their spouse (reverse direction) is also cleaned up.
4. The spouse account itself remains intact and unaffected.

### 6.3 DataExportService

**File:** `app/Services/GDPR/DataExportService.php`

Handles data export generation and file lifecycle. Injected dependency: `AuditService`.

| Method | Visibility | Signature | Description |
|--------|-----------|-----------|-------------|
| `requestExport` | public | `(User $user, string $format = 'json'): DataExport` | Returns existing pending/processing export if one exists. Otherwise creates a new request. Logs `export_requested` audit event. |
| `processExport` | public | `(DataExport $export): void` | Marks as processing, gathers data, writes file, marks completed. On failure marks as failed and re-throws. |
| `getExportFile` | public | `(DataExport $export): ?string` | Returns the absolute file path if downloadable. Marks the export as downloaded. |
| `gatherUserData` | private | `(User $user): array` | Collects all user data. See Export Data Categories below. |
| `convertToCsv` | private | `(array $data): string` | Flattens nested data to `Category,Field,Value` CSV rows. |
| `flattenForCsv` | private | `(array &$lines, string $prefix, array $data): void` | Recursive helper for CSV flattening with dot-notation prefixes. |
| `csvLine` | private | `(string $category, string $field, $value): string` | Produces a single escaped CSV line. |
| `cleanupExpiredExports` | public | `(): int` | Finds expired exports, deletes storage files, marks as expired. Returns count of cleaned exports. |

#### Export Data Categories

The `gatherUserData()` method exports the following:

```php
[
    'export_date'         => ISO 8601 timestamp,
    'user'                => exportUserProfile(),        // id, email, names, dob, gender, country
    'family_members'      => exportFamilyMembers(),
    'properties'          => exportProperties(),
    'mortgages'           => exportMortgages(),
    'savings_accounts'    => exportSavingsAccounts(),
    'investment_accounts' => exportInvestmentAccounts(),  // with('holdings')
    'pensions' => [
        'dc_pensions'     => exportDCPensions(),
        'db_pensions'     => exportDBPensions(),
        'state_pension'   => exportStatePension(),       // nullable
    ],
    'protection_policies' => [
        'life'              => exportLifePolicies(),
        'critical_illness'  => exportCriticalIllnessPolicies(),
        'income_protection' => exportIncomeProtectionPolicies(),
    ],
    'business_interests'  => exportBusinessInterests(),
    'chattels'            => exportChattels(),
    'goals'               => exportGoals(),              // withTrashed()
    'consents'            => exportConsents(),
    'audit_logs'          => exportAuditLogs(),
]
```

Notable details:
- Investment accounts include eager-loaded `holdings`.
- Goals use `withTrashed()` to include soft-deleted goals.
- Audit logs are ordered by `created_at DESC`.
- All export methods use `->toArray()` on Eloquent models.

#### File Storage

Files are stored at: `exports/user_{id}_{timestamp}.{format}`

- Storage driver: Laravel default (typically `local` pointing to `storage/app/`).
- Expiry: 7 days after completion.
- Download filename: `fynla_data_export_{YYYY-MM-DD}.{format}`

---

## 7. Validation Requests

The GDPR module does **not** use dedicated FormRequest classes. All validation is inline in the controller methods:

| Endpoint | Validation Rules |
|----------|------------------|
| `updateConsents` | `consents: required|array`, `consents.*: boolean` |
| `requestExport` | `format: sometimes|string|in:json,csv` |
| `requestErasure` (legacy) | `reason: nullable|string|max:1000`, `confirm: required|boolean|accepted` |
| `initiateErasure` | `type: required|in:account,data` |
| `verifyErasure` | `session_token: required|string|size:64`, `code: required|string|size:6` |
| `executeErasure` | `session_token: required|string|size:64`, `confirmation: required|string` |
| `resendDeletionCode` | `session_token: required|string|size:64` |

---

## 8. Vuex Store

**No dedicated store.** The GDPR module does not use a Vuex store module. All state management is handled locally within the `PrivacySettings.vue` component's `data()` function. API calls are made directly through the shared `api` (axios) service.

This is appropriate given that GDPR data is:
- Only accessed from a single settings page.
- Not shared across multiple components.
- Not needed in global application state.

---

## 9. API Service

**Inline in Vue component.** The module does not have a dedicated API service file. Instead, `PrivacySettings.vue` imports the shared axios instance and makes calls directly:

```javascript
import api from '@/services/api';

// Examples from the component:
await api.get('/auth/gdpr/consents');
await api.put('/auth/gdpr/consents', { consent_type: type, granted: granted });
await api.post('/auth/gdpr/export', { format: this.exportFormat });
await api.get('/auth/gdpr/export/status');
await api.get(`/auth/gdpr/export/${id}/download`, { responseType: 'blob' });
await api.post('/auth/gdpr/erasure/initiate', { type });
await api.post('/auth/gdpr/erasure/verify', { session_token, code });
await api.post('/auth/gdpr/erasure/execute', { session_token, confirmation });
await api.post('/auth/gdpr/erasure/resend-code', { session_token });
```

---

## 10. Frontend Components

### PrivacySettings.vue

**File:** `resources/js/views/Settings/PrivacySettings.vue`

A single-file Vue component (~1350 lines including styles) that serves as the complete privacy management interface. It uses `AppLayout` as its wrapper and is divided into four visible sections plus the deletion wizard modal.

#### Page Sections

1. **Consent Preferences** - Shows "Essential Services" (always on, non-toggleable) and "Marketing Communications" (toggle switch). The marketing toggle calls `updateConsent('marketing', value)` on change.

2. **Export Your Data** - Three states:
   - **Pending**: Shows spinner and "Export in progress" message.
   - **Completed**: Shows success icon with a "Download" button that triggers a blob download.
   - **Default**: Format selector (JSON/CSV) and "Request Data Export" button.

3. **Delete Your Data or Account** - Red-bordered danger section with a single "Manage Account Deletion" button that opens the 3-step wizard modal.

4. **Your Data Rights** - Informational section listing GDPR rights (Access, Rectification, Erasure, Portability, Object) with a contact email (`privacy@fynla.org`).

#### Component Data

```javascript
data() {
  return {
    consents: { marketing: true },
    exportFormat: 'json',
    exportLoading: false,
    pendingExport: null,
    completedExport: null,
    deletionWizard: {
      show: false,
      step: 1,                    // 1 | 2 | 3
      type: null,                 // 'account' | 'data'
      sessionToken: null,
      verificationMethod: null,   // '2fa' | 'email'
      confirmationText: '',
      loading: false,
      error: null,
    },
    codeDigits: ['', '', '', '', '', ''],
    codeInputRefs: [],
    resendCooldown: 0,
  };
}
```

#### Computed Properties

| Property | Description |
|----------|-------------|
| `requiredConfirmationPhrase` | Returns `'Delete my Account'` or `'Delete my Data'` based on deletion type. |
| `confirmationValid` | True when `confirmationText` exactly matches the required phrase (case-sensitive). |
| `isCodeComplete` | True when all 6 digits are filled. |
| `fullCode` | Joins `codeDigits` array into a single 6-character string. |

#### Lifecycle

- `mounted()`: Calls `loadConsents()` and `checkExportStatus()`.

#### Key Methods

| Method | Description |
|--------|-------------|
| `loadConsents()` | Fetches `GET /auth/gdpr/consents`, sets `consents.marketing`. |
| `updateConsent(type, granted)` | Sends `PUT /auth/gdpr/consents` with type and granted value. |
| `checkExportStatus()` | Fetches `GET /auth/gdpr/export/status`, sets `pendingExport`/`completedExport`. |
| `requestExport()` | Sends `POST /auth/gdpr/export`, then refreshes status. |
| `downloadExport()` | Fetches export as blob, creates temporary download link. |
| `openDeletionWizard()` | Resets wizard state and shows modal. |
| `closeDeletionWizard()` | Hides modal and resets state. |
| `selectDeletionType(type)` | Step 1 handler. Calls `POST /auth/gdpr/erasure/initiate`, sets verification method, advances to step 2, auto-focuses first code input. |
| `verifyIdentity()` | Step 2 handler. Calls `POST /auth/gdpr/erasure/verify` with session token and full code. On success advances to step 3. On failure clears code inputs. |
| `resendCode()` | Calls `POST /auth/gdpr/erasure/resend-code`. Starts 60-second cooldown timer (1-second intervals). |
| `executeDelete()` | Step 3 handler. Calls `POST /auth/gdpr/erasure/execute` with session token and confirmation text. On account deletion: dispatches `auth/logout` and redirects to `/login`. On data deletion: shows toast and redirects to `/dashboard`. |
| `goBackToStep1()` | Resets wizard to step 1, clears type and session token. |
| `goBackToStep2()` | Returns to step 2, clears confirmation text. |

#### Code Input Handlers

The 6-digit verification code uses individual `<input>` elements with sophisticated handling:

| Handler | Behaviour |
|---------|-----------|
| `handleCodeInput(event, index)` | Strips non-numeric characters. Writes digit, auto-focuses next input. |
| `handleCodeKeydown(event, index)` | Backspace on empty input focuses previous. Arrow keys navigate between inputs. |
| `handleCodePaste(event)` | Prevents default, strips non-numeric, distributes up to 6 digits across inputs, focuses next empty. |
| `clearCode()` | Resets all digits to empty, focuses first input. |

---

## 11. Frontend Routing

**File:** `resources/js/router/index.js`

```javascript
{
  path: '/settings/privacy',
  name: 'PrivacySettings',
  component: () => import('@/views/Settings/PrivacySettings.vue'),
  meta: {
    requiresAuth: true,
    breadcrumb: [
      { label: 'Home', path: '/dashboard' },
      { label: 'Settings', path: '/settings' },
      { label: 'Privacy & Data', path: '/settings/privacy' },
    ],
  },
}
```

The route requires authentication (`requiresAuth: true`). It is accessible from the Settings page navigation alongside Security and Assumptions settings.

---

## 12. Cross-Module Integration

### 12.1 Audit Logs

All GDPR events are logged to the `audit_logs` table using `AuditService::logGDPR()`, which delegates to `AuditLog::logGDPR()`:

```php
// AuditLog model constants
public const EVENT_GDPR = 'gdpr';

public const ACTION_EXPORT_REQUESTED  = 'export_requested';
public const ACTION_EXPORT_COMPLETED  = 'export_completed';
public const ACTION_ERASURE_REQUESTED = 'erasure_requested';
public const ACTION_ERASURE_COMPLETED = 'erasure_completed';
```

The `logGDPR()` static method on `AuditLog`:

```php
public static function logGDPR(string $action, int $userId, ?array $metadata = null): self
{
    return self::log(self::EVENT_GDPR, $action, $userId, null, null, null, null, $metadata);
}
```

The `AuditService` wraps this as an instance method:

```php
public function logGDPR(string $action, int $userId, array $metadata = []): AuditLog
{
    return AuditLog::logGDPR($action, $userId, $metadata ?: null);
}
```

#### Audit Events Logged

| Event | Logged By | Metadata |
|-------|-----------|----------|
| `erasure_requested` | `DataErasureService::requestErasure()` | `{request_id, reason}` |
| `erasure_requested` | `GDPRController::initiateErasure()` | `{type, step: 'initiated'}` |
| `erasure_completed` | `GDPRController::executeErasure()` (data-only) | `{type: 'data_only', categories_deleted}` |
| `erasure_completed` | `DataErasureService::deleteDataOnly()` | `{type: 'data_only', categories_deleted}` |
| `export_requested` | `DataExportService::requestExport()` | none |
| `export_completed` | `DataExportService::processExport()` | `{export_id, format}` |

**Critical note:** During full account erasure (`processErasure()`), audit logs are HARD DELETED as part of the transaction (step 4 in the deletion order). This means there is **no post-deletion audit trail** for full account erasures. The erasure request record itself is also deleted (step 5) before the user is deleted.

### 12.2 User Model

The User model has relationships used by the GDPR services. The export service accesses:

- `$user->familyMembers()` - FamilyMember records
- `$user->properties()` - Property records
- `$user->mortgages()` - Mortgage records
- `$user->savingsAccounts()` - SavingsAccount records
- `$user->investmentAccounts()` - InvestmentAccount records (with holdings)
- `$user->dcPensions()` - DCPension records
- `$user->dbPensions()` - DBPension records
- `$user->statePension` - StatePension record (singular, nullable)
- `$user->lifeInsurancePolicies()` - LifeInsurancePolicy records
- `$user->criticalIllnessPolicies()` - CriticalIllnessPolicy records
- `$user->incomeProtectionPolicies()` - IncomeProtectionPolicy records
- `$user->businessInterests()` - BusinessInterest records
- `$user->chattels()` - Chattel records
- `$user->goals()` - Goal records (with SoftDeletes)
- `$user->consents()` - UserConsent records
- `$user->documents()` - Document records (conditional, checked via `method_exists`)
- `$user->dataExports()` - DataExport records (conditional, checked via `method_exists`)
- `$user->tokens()` - Sanctum personal access tokens
- `$user->sessions()` - UserSession records

### 12.3 MFA Service

The deletion wizard integrates with `App\Services\Auth\MFAService`:

- `hasMFAEnabled(User $user): bool` - Determines verification method (2FA vs email).
- `verifyCode(User $user, string $code): bool` - Validates TOTP code from authenticator app.

### 12.4 DeletionVerificationCode Mailable

**File:** `app/Mail/DeletionVerificationCode.php`

```php
class DeletionVerificationCode extends Mailable
{
    public function __construct(
        public User $user,
        public string $code    // 6-digit plaintext code
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: 'Account Deletion Verification Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.deletion-verification-code',
            with: ['user' => $this->user, 'code' => $this->code],
        );
    }
}
```

The email template lives at `resources/views/emails/deletion-verification-code.blade.php`.

### 12.5 PreviewWriteInterceptor

The `PreviewWriteInterceptor` middleware blocks all POST/PUT/PATCH/DELETE requests from preview users unless the route is in `EXCLUDED_ROUTES`. The GDPR erasure routes (`/auth/gdpr/erasure/*`) are **not** in `EXCLUDED_ROUTES`, but the controller has its own preview user check:

```php
if ($user->is_preview_user) {
    return response()->json([
        'success' => false,
        'message' => 'Preview accounts cannot be deleted.',
    ], 403);
}
```

This means the middleware intercepts the request first with a generic "Preview mode" response, preventing the user from ever reaching the controller's more specific error message. This is not a functional bug (preview users are still blocked), but the error message the user sees is the generic middleware response rather than the specific GDPR one.

---

## 13. Profile Completeness

**N/A.** The GDPR module does not participate in the profile completeness system. Privacy settings are not considered part of financial planning completeness.

---

## 14. Seeder Data

**N/A.** There are no GDPR-specific seeders. Preview user personas do not have pre-seeded consent records or export history. Consent records are created at runtime when users interact with the consent preferences.

---

## 15. API Routing

**File:** `routes/api.php`

All GDPR routes are nested under `/auth/gdpr` within the authenticated route group:

```php
Route::prefix('gdpr')->group(function () {
    // Consent management
    Route::get('/consents', [GDPRController::class, 'getConsents']);
    Route::put('/consents', [GDPRController::class, 'updateConsents']);
    Route::get('/consents/history', [GDPRController::class, 'getConsentHistory']);

    // Data export (right to portability) - rate limited to 3/hour
    Route::post('/export', [GDPRController::class, 'requestExport'])->middleware('throttle:export');
    Route::get('/export/status', [GDPRController::class, 'getExportStatus']);
    Route::get('/export/{id}/download', [GDPRController::class, 'downloadExport']);

    // Data erasure (right to be forgotten) - self-service immediate deletion
    Route::post('/erasure/initiate', [GDPRController::class, 'initiateErasure'])->middleware('throttle:sensitive');
    Route::post('/erasure/verify', [GDPRController::class, 'verifyErasure'])->middleware('throttle:sensitive');
    Route::post('/erasure/execute', [GDPRController::class, 'executeErasure'])->middleware('throttle:sensitive');
    Route::post('/erasure/resend-code', [GDPRController::class, 'resendDeletionCode'])->middleware('throttle:sensitive');

    // Legacy erasure endpoints (deprecated, kept for backwards compatibility)
    Route::post('/erasure', [GDPRController::class, 'requestErasure'])->middleware('throttle:sensitive');
    Route::get('/erasure/status', [GDPRController::class, 'getErasureStatus']);
    Route::post('/erasure/{id}/confirm', [GDPRController::class, 'confirmErasure'])->middleware('throttle:sensitive');
    Route::post('/erasure/{id}/cancel', [GDPRController::class, 'cancelErasure']);
});
```

### Rate Limiting

Defined in `app/Providers/RouteServiceProvider.php`:

| Limiter Name | Limit | Scope | Purpose |
|-------------|-------|-------|---------|
| `export` | 3 per hour | `user_id` or IP | Prevents abuse of computationally expensive export operations. |
| `sensitive` | 3 per minute | `user_id` or IP | Protects erasure endpoints from brute force and abuse. |

Both limiters return a JSON error response:

```json
{
    "success": false,
    "message": "Too many requests. Please try again later."
}
```

### Full Route Table

| Method | URI | Controller Method | Middleware |
|--------|-----|-------------------|-----------|
| GET | `/api/auth/gdpr/consents` | `getConsents` | auth:sanctum |
| PUT | `/api/auth/gdpr/consents` | `updateConsents` | auth:sanctum |
| GET | `/api/auth/gdpr/consents/history` | `getConsentHistory` | auth:sanctum |
| POST | `/api/auth/gdpr/export` | `requestExport` | auth:sanctum, throttle:export |
| GET | `/api/auth/gdpr/export/status` | `getExportStatus` | auth:sanctum |
| GET | `/api/auth/gdpr/export/{id}/download` | `downloadExport` | auth:sanctum |
| POST | `/api/auth/gdpr/erasure/initiate` | `initiateErasure` | auth:sanctum, throttle:sensitive |
| POST | `/api/auth/gdpr/erasure/verify` | `verifyErasure` | auth:sanctum, throttle:sensitive |
| POST | `/api/auth/gdpr/erasure/execute` | `executeErasure` | auth:sanctum, throttle:sensitive |
| POST | `/api/auth/gdpr/erasure/resend-code` | `resendDeletionCode` | auth:sanctum, throttle:sensitive |
| POST | `/api/auth/gdpr/erasure` | `requestErasure` | auth:sanctum, throttle:sensitive |
| GET | `/api/auth/gdpr/erasure/status` | `getErasureStatus` | auth:sanctum |
| POST | `/api/auth/gdpr/erasure/{id}/confirm` | `confirmErasure` | auth:sanctum, throttle:sensitive |
| POST | `/api/auth/gdpr/erasure/{id}/cancel` | `cancelErasure` | auth:sanctum |

---

## 16. Key Constants

### Consent Types

| Constant | Value | Required |
|----------|-------|----------|
| `UserConsent::TYPE_TERMS` | `'terms'` | Yes |
| `UserConsent::TYPE_PRIVACY` | `'privacy'` | Yes |
| `UserConsent::TYPE_DATA_PROCESSING` | `'data_processing'` | Yes |
| `UserConsent::TYPE_MARKETING` | `'marketing'` | No |

### Consent Versions

All currently at `'v1.0'`. Defined in `UserConsent::CURRENT_VERSIONS`. When a policy version is bumped, users with the old version will have `needsReconsent()` return true.

### Export Constants

| Constant | Value |
|----------|-------|
| `DataExport::STATUS_PENDING` | `'pending'` |
| `DataExport::STATUS_PROCESSING` | `'processing'` |
| `DataExport::STATUS_COMPLETED` | `'completed'` |
| `DataExport::STATUS_FAILED` | `'failed'` |
| `DataExport::STATUS_EXPIRED` | `'expired'` |
| `DataExport::FORMAT_JSON` | `'json'` |
| `DataExport::FORMAT_CSV` | `'csv'` |
| `DataExport::EXPIRY_DAYS` | `7` |

### Erasure Constants

| Constant | Value |
|----------|-------|
| `ErasureRequest::STATUS_PENDING` | `'pending'` |
| `ErasureRequest::STATUS_PROCESSING` | `'processing'` |
| `ErasureRequest::STATUS_COMPLETED` | `'completed'` |
| `ErasureRequest::STATUS_CANCELLED` | `'cancelled'` |

### Audit Constants (GDPR-specific)

| Constant | Value |
|----------|-------|
| `AuditLog::EVENT_GDPR` | `'gdpr'` |
| `AuditLog::ACTION_EXPORT_REQUESTED` | `'export_requested'` |
| `AuditLog::ACTION_EXPORT_COMPLETED` | `'export_completed'` |
| `AuditLog::ACTION_ERASURE_REQUESTED` | `'erasure_requested'` |
| `AuditLog::ACTION_ERASURE_COMPLETED` | `'erasure_completed'` |

### Cache Keys and TTLs

| Key Pattern | TTL | Purpose |
|------------|-----|---------|
| `deletion_session:{user_id}` | 15 min | Deletion wizard session token and state |
| `deletion_code:{user_id}` | 15 min | Bcrypt-hashed email verification code |

### Deletion Wizard Constants (Hardcoded in Controller)

| Value | Purpose |
|-------|---------|
| 64 characters | Session token length (`Str::random(64)`) |
| 15 minutes | Session and code cache TTL |
| 3 attempts | Maximum verification attempts before lockout |
| 6 digits | Verification code length |
| `'Delete my Account'` | Account deletion confirmation phrase (case-sensitive) |
| `'Delete my Data'` | Data deletion confirmation phrase (case-sensitive) |

### Frontend Constants (Hardcoded in Vue)

| Value | Purpose |
|-------|---------|
| 60 seconds | Resend code cooldown timer |

---

## 17. Known Issues

### 17.1 Vue/API Consent Payload Mismatch

**Severity:** Bug (non-functional consent updates)

The `updateConsent()` method in `PrivacySettings.vue` sends a flat object:

```javascript
await api.put('/auth/gdpr/consents', {
    consent_type: type,
    granted: granted,
});
```

But the controller expects a nested `consents` object:

```php
$request->validate([
    'consents' => 'required|array',
    'consents.*' => 'boolean',
]);
$consents = array_intersect_key($request->consents, array_flip($validTypes));
```

The request will fail validation because `consents` is not present. The Vue should send:

```javascript
await api.put('/auth/gdpr/consents', {
    consents: { [type]: granted },
});
```

### 17.2 Vue/API Consent Response Field Mismatch

**Severity:** Bug (consent toggle always shows default)

The `loadConsents()` method reads `consent.granted`:

```javascript
consents.forEach(consent => {
    if (consent.consent_type === 'marketing') {
        this.consents.marketing = consent.granted;
    }
});
```

But the API returns `consent.consented` (the database column name):

```php
$consents[$type] = [
    'consented' => $consent?->consented ?? false,
    'version' => $version,
    'consented_at' => $consent?->consented_at?->toIso8601String(),
];
```

Additionally, the Vue iterates over the response as an array (`.forEach`) but the API returns an object keyed by consent type.

### 17.3 Audit Logs Hard-Deleted During Full Erasure

**Severity:** Design concern

During full account erasure, audit logs are hard-deleted inside the DB transaction. This means there is no record that the erasure ever occurred. For regulatory compliance, it may be desirable to retain an anonymised audit record indicating that an erasure was performed, with the date and categories deleted, without any identifying user information.

### 17.4 Synchronous Export Processing

**Severity:** Performance concern

The `requestExport()` controller method processes the export synchronously:

```php
if ($export->isPending()) {
    $this->exportService->processExport($export);
    $export->refresh();
}
```

For users with large datasets (many accounts, holdings, goals, etc.), this could result in a slow API response. The comment in the code acknowledges this: "Process immediately for now (could be queued for large datasets)." A future improvement would be to dispatch a queued job.

### 17.5 No Cooldown Between Erasure Requests

**Severity:** Minor

Beyond the `throttle:sensitive` rate limiter (3 per minute), there is no cooldown period between erasure request initiations. A user could repeatedly initiate and abandon deletion sessions. The cache keys are per-user so concurrent sessions overwrite each other (which actually prevents abuse), but there is no feedback to the user about why a previous session was invalidated.

### 17.6 Preview User GDPR Route Gap

**Severity:** Cosmetic

Preview users are blocked from erasure by the controller (`is_preview_user` check), but the GDPR erasure routes are not listed in `PreviewWriteInterceptor::EXCLUDED_ROUTES`. This means the middleware intercepts the request first with its generic "Preview mode" response rather than the controller's specific "Preview accounts cannot be deleted" message. The consent update and export routes are also intercepted by the middleware for preview users, preventing consent changes or data exports in preview mode.

### 17.7 Export Status Response Mismatch

**Severity:** Bug (export status never displays correctly)

The Vue `checkExportStatus()` method expects the API to return `response.data.data.exports` (an array), but the controller returns a single export object:

```javascript
// Vue expects:
const exports = response.data.data?.exports || [];
const pending = exports.find(e => e.status === 'pending' || e.status === 'processing');

// API actually returns:
{
    success: true,
    data: {
        export_id: 1,
        status: 'completed',
        // ... single object, not an array
    }
}
```

The Vue will never correctly populate `pendingExport` or `completedExport` because it tries to call `.find()` on an undefined array.

---

## 18. Deep Dive: Self-Service Deletion Wizard

The 3-step deletion wizard is the most complex feature in the GDPR module. It provides a secure, user-friendly flow for immediate account or data deletion without requiring admin intervention.

### Flow Diagram

```
User clicks "Manage Account Deletion"
           |
    +-----------------+
    |   STEP 1:       |
    |   Choose Type   |
    |                 |
    |  [Delete Data]  |
    |  [Delete Acct]  |
    +-----------------+
           |
    POST /erasure/initiate
    (generates session token,
     checks 2FA status)
           |
    +------+------+
    |             |
  2FA?         No 2FA
    |             |
    |      Send email code
    |      (6-digit, bcrypt,
    |       cached 15 min)
    |             |
    +------+------+
           |
    +-----------------+
    |   STEP 2:       |
    |   Verify        |
    |                 |
    |  [_ _ _ _ _ _]  |  <-- 6-digit code input
    |  [Resend Code]  |  <-- 60s cooldown (email only)
    |  [Verify]       |
    +-----------------+
           |
    POST /erasure/verify
    (max 3 attempts,
     lockout on failure)
           |
    +-----------------+
    |   STEP 3:       |
    |   Confirm       |
    |                 |
    |  Warning box    |
    |  Type phrase:   |
    |  "Delete my     |
    |   Account"      |
    |  [Execute]      |
    +-----------------+
           |
    POST /erasure/execute
    (case-sensitive phrase check,
     verified session required)
           |
    +------+------+
    |             |
  Account      Data
    |             |
  Full         Financial data
  erasure      deletion only
    |             |
  Logout +     Toast +
  /login       /dashboard
```

### Step 1: Initiation

**Endpoint:** `POST /auth/gdpr/erasure/initiate`

**Request:**
```json
{
    "type": "account"  // or "data"
}
```

**Server-side logic:**
1. Block preview users (403).
2. Generate a 64-character random session token.
3. Cache session state under `deletion_session:{user_id}` for 15 minutes:
   ```php
   [
       'token'    => $sessionToken,  // 64 chars
       'type'     => 'account',      // or 'data'
       'verified' => false,
       'attempts' => 0,
   ]
   ```
4. Log `erasure_requested` audit event with `{type, step: 'initiated'}`.
5. Check MFA status:
   - If 2FA enabled: Return `requires_2fa: true`. User will enter TOTP code in step 2.
   - If no 2FA: Call `sendDeletionVerificationEmail()` which generates a 6-digit code, bcrypt-hashes it, caches under `deletion_code:{user_id}` for 15 minutes, and sends the `DeletionVerificationCode` email.

**Responses:**

2FA enabled:
```json
{
    "success": true,
    "requires_2fa": true,
    "requires_email_verification": false,
    "session_token": "aB3...64chars..."
}
```

No 2FA (email verification):
```json
{
    "success": true,
    "requires_2fa": false,
    "requires_email_verification": true,
    "session_token": "aB3...64chars..."
}
```

**Vue behaviour:** On receiving the response, the wizard advances to step 2. Sets `verificationMethod` to `'2fa'` or `'email'`. Auto-focuses the first code input via `$nextTick`.

### Step 2: Verification

**Endpoint:** `POST /auth/gdpr/erasure/verify`

**Request:**
```json
{
    "session_token": "aB3...64chars...",
    "code": "482715"
}
```

**Server-side logic:**
1. Retrieve cached session by `deletion_session:{user_id}`.
2. Validate session exists and token matches (400 if not).
3. Check attempt count:
   - If attempts >= 3: Clear both caches (`deletion_session` and `deletion_code`), return 400 "Too many failed attempts".
4. Verify code:
   - 2FA path: `$this->mfaService->verifyCode($user, $code)` (TOTP verification).
   - Email path: `$this->verifyDeletionEmailCode($user, $code)` which does `Hash::check($code, $storedHash)`.
5. On failure:
   - Increment `attempts` counter in cache.
   - Return 401 with remaining attempts message (or lockout message on 3rd failure).
6. On success:
   - Set `session['verified'] = true` and `session['verified_at'] = now()->timestamp`.
   - Clear `deletion_code:{user_id}` cache.
   - Return success with session token and deletion type.

**Responses:**

Success:
```json
{
    "success": true,
    "message": "Identity verified successfully.",
    "session_token": "aB3...64chars...",
    "type": "account"
}
```

Failure (attempts remaining):
```json
{
    "success": false,
    "message": "Invalid verification code. 2 attempt(s) remaining."
}
```

Lockout:
```json
{
    "success": false,
    "message": "Too many failed attempts. Please start again."
}
```

**Vue behaviour:** On success, advances to step 3. On failure, clears the code inputs and displays error. On lockout, the user must start the wizard over.

### Code Resend (Email Only)

**Endpoint:** `POST /auth/gdpr/erasure/resend-code`

**Request:**
```json
{
    "session_token": "aB3...64chars..."
}
```

- Rejects if 2FA is enabled (returns 400 "Use your authenticator app").
- Generates a new 6-digit code (invalidates the previous one in cache).
- 60-second client-side cooldown (server has no cooldown beyond the rate limiter).

### Step 3: Execution

**Endpoint:** `POST /auth/gdpr/erasure/execute`

**Request:**
```json
{
    "session_token": "aB3...64chars...",
    "confirmation": "Delete my Account"
}
```

**Server-side logic:**
1. Retrieve and validate cached session.
2. Verify session is marked as `verified` (400 if not).
3. Validate confirmation phrase (case-sensitive):
   - `'account'` type requires exactly `'Delete my Account'`.
   - `'data'` type requires exactly `'Delete my Data'`.
   - Returns 400 with expected phrase if mismatch.
4. Clear session cache.
5. Execute deletion:

**Account deletion path:**
```php
$erasureRequest = $this->erasureService->requestErasure($user, 'Self-service account deletion');
$this->erasureService->confirmErasure($erasureRequest);
$this->erasureService->processErasure($erasureRequest, 'self-service');
```
Returns `{success: true, type: 'account', logout_required: true}`.

**Data-only deletion path:**
```php
$deletedCategories = $this->erasureService->deleteDataOnly($user);
$this->auditService->logGDPR(AuditLog::ACTION_ERASURE_COMPLETED, $user->id, [...]);
```
Returns `{success: true, type: 'data', logout_required: false, deleted_categories: [...]}`.

**Vue behaviour:**
- Account deletion: Dispatches `auth/logout` Vuex action, then navigates to `/login`.
- Data deletion: Shows toast notification "Your data has been deleted", closes the wizard modal, navigates to `/dashboard`.

### Security Properties

| Property | Implementation |
|----------|---------------|
| Session binding | 64-character random token tied to user ID in cache |
| Session expiry | 15-minute TTL on cache keys |
| Verification | Either TOTP (2FA) or bcrypt-hashed email code |
| Brute force protection | 3-attempt limit with full session invalidation on lockout |
| Rate limiting | `throttle:sensitive` at 3 requests per minute per user |
| Confirmation phrase | Case-sensitive exact match required |
| Preview isolation | Both middleware and controller block preview users |
| Atomic deletion | Full account erasure runs in a single DB transaction |

### Timing Analysis

| Phase | Duration |
|-------|----------|
| Session validity | 15 minutes from initiation |
| Email code validity | 15 minutes from generation |
| Resend cooldown (client) | 60 seconds |
| Rate limit window | 3 per minute (sensitive endpoints) |
| Total maximum time | 15 minutes (session expiry) |

If the user does not complete all 3 steps within 15 minutes of initiation, the session expires and they must start over.
