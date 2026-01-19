# Fynla Security Implementation Task List

**Reference:** SecurityCompliancePlan.md
**Created:** 19 January 2025
**Status:** ✅ ALL TASKS COMPLETE - Merged to main (PR #20)

> **Note:** All tasks below have been implemented and merged. The checkboxes below represent the original planning document. See `SecurityImplementationSummary.md` for implementation details.

---

## Phase 1: Authentication Security (Week 1-2)

### 1.1 Failed Login Tracking & Lockout

- [ ] **1.1.1** Create migration `2025_01_xx_create_login_attempts_table.php`
  - Table: `login_attempts` with email, ip_address, user_agent, successful, failure_reason, created_at
  - Indexes on (email, created_at) and (ip_address, created_at)

- [ ] **1.1.2** Create migration to add lockout fields to users table
  - Add: `failed_login_count INT DEFAULT 0`
  - Add: `locked_until TIMESTAMP NULL`
  - Add: `last_failed_login_at TIMESTAMP NULL`

- [ ] **1.1.3** Create `app/Models/LoginAttempt.php`
  - Fillable fields, relationships, scopes for filtering

- [ ] **1.1.4** Create `app/Services/Auth/LoginLockoutService.php`
  - `checkIfLocked(string $email): bool`
  - `recordFailedAttempt(string $email, string $ip, string $reason): void`
  - `recordSuccessfulLogin(string $email): void`
  - `getLockoutDuration(int $failedCount): int` (progressive lockout)
  - `resetFailedAttempts(User $user): void`

- [ ] **1.1.5** Modify `app/Http/Controllers/Api/AuthController.php`
  - Inject `LoginLockoutService`
  - Check lockout before `Auth::attempt()`
  - Record failed/successful attempts
  - Return lockout remaining time in error response

- [ ] **1.1.6** Write tests
  - `tests/Unit/Services/Auth/LoginLockoutServiceTest.php`
  - `tests/Feature/Auth/LoginLockoutTest.php`

### 1.2 TOTP Multi-Factor Authentication

- [ ] **1.2.1** Install packages
  ```bash
  composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
  ```

- [ ] **1.2.2** Publish Google2FA config
  ```bash
  php artisan vendor:publish --provider="PragmaRX\Google2FALaravel\ServiceProvider"
  ```

- [ ] **1.2.3** Create migration `2025_01_xx_add_mfa_fields_to_users_table.php`
  - Add: `mfa_enabled BOOLEAN DEFAULT FALSE`
  - Add: `mfa_secret VARCHAR(255) NULL`
  - Add: `mfa_recovery_codes JSON NULL`
  - Add: `mfa_confirmed_at TIMESTAMP NULL`

- [ ] **1.2.4** Create `app/Services/Auth/MFAService.php`
  - `generateSecret(): string`
  - `getQRCodeDataUri(User $user, string $secret): string`
  - `verifyCode(User $user, string $code): bool`
  - `enableMFA(User $user, string $secret): void`
  - `disableMFA(User $user): void`
  - `generateRecoveryCodes(): array` (10 codes)
  - `hashRecoveryCodes(array $codes): array`
  - `verifyRecoveryCode(User $user, string $code): bool`
  - `regenerateRecoveryCodes(User $user): array`

- [ ] **1.2.5** Create `app/Http/Controllers/Api/MFAController.php`
  - `setup()` - Generate secret, return QR code data URI
  - `verifySetup(Request $request)` - Verify first code, enable MFA
  - `verify(Request $request)` - Verify during login
  - `useRecoveryCode(Request $request)` - Use recovery code
  - `disable(Request $request)` - Disable MFA (requires password)
  - `regenerateRecoveryCodes()` - Generate new recovery codes

- [ ] **1.2.6** Create `app/Http/Middleware/EnsureMFAVerified.php`
  - Check if user has MFA enabled
  - Check if current session has MFA verified flag
  - Redirect/return 403 if MFA required but not verified

- [ ] **1.2.7** Register MFA routes in `routes/api.php`
  ```php
  Route::middleware('auth:sanctum')->prefix('auth/mfa')->group(function () {
      Route::post('setup', [MFAController::class, 'setup']);
      Route::post('verify-setup', [MFAController::class, 'verifySetup']);
      Route::post('verify', [MFAController::class, 'verify']);
      Route::post('recovery', [MFAController::class, 'useRecoveryCode']);
      Route::post('disable', [MFAController::class, 'disable']);
      Route::get('recovery-codes', [MFAController::class, 'regenerateRecoveryCodes']);
  });
  ```

- [ ] **1.2.8** Modify `AuthController::login()` to check MFA
  - After password verification, check if `mfa_enabled`
  - If MFA enabled, return `requires_mfa: true` instead of token
  - Store temporary auth state for MFA verification step

- [ ] **1.2.9** Create Vue components
  - `resources/js/components/Auth/MFASetupModal.vue`
  - `resources/js/components/Auth/MFAVerifyModal.vue`
  - `resources/js/views/Settings/SecuritySettings.vue` (or add section)

- [ ] **1.2.10** Write tests
  - `tests/Unit/Services/Auth/MFAServiceTest.php`
  - `tests/Feature/Auth/MFAFlowTest.php`

### 1.3 Session Management

- [ ] **1.3.1** Create migration `2025_01_xx_create_user_sessions_table.php`
  - Table with user_id, token_id, ip_address, user_agent, device_name, last_activity_at, created_at

- [ ] **1.3.2** Create `app/Models/UserSession.php`
  - Relationships to User and PersonalAccessToken
  - Computed `device_name` from user_agent parsing

- [ ] **1.3.3** Create `app/Services/Auth/SessionService.php`
  - `createSession(User $user, PersonalAccessToken $token, Request $request): UserSession`
  - `updateLastActivity(UserSession $session): void`
  - `getUserSessions(User $user): Collection`
  - `revokeSession(UserSession $session): void`
  - `revokeAllExceptCurrent(User $user, UserSession $current): void`

- [ ] **1.3.4** Create `app/Observers/PersonalAccessTokenObserver.php`
  - `created()` - Create corresponding UserSession
  - `deleted()` - Delete corresponding UserSession

- [ ] **1.3.5** Register observer in `app/Providers/AppServiceProvider.php`

- [ ] **1.3.6** Create `app/Http/Controllers/Api/SessionController.php`
  - `index()` - List all user sessions
  - `destroy($id)` - Revoke specific session
  - `destroyOthers()` - Revoke all except current

- [ ] **1.3.7** Register session routes in `routes/api.php`

- [ ] **1.3.8** Create `resources/js/components/Settings/ActiveSessions.vue`
  - Display list of sessions with device, IP, last activity
  - Button to revoke individual sessions
  - Button to revoke all other sessions

- [ ] **1.3.9** Write tests
  - `tests/Feature/Auth/SessionManagementTest.php`

### 1.4 Password Breach Checking

- [ ] **1.4.1** Create `app/Services/Auth/PasswordBreachService.php`
  - `checkPassword(string $password): bool` (true if breached)
  - Use HIBP API with k-Anonymity (send only first 5 chars of SHA1)
  - Cache results for 24 hours to reduce API calls

- [ ] **1.4.2** Create `app/Rules/NotBreached.php`
  - Laravel validation rule
  - Returns warning message, doesn't block (configurable)

- [ ] **1.4.3** Integrate in registration and password change
  - Modify `RegisterRequest` validation
  - Modify `AuthController::changePassword()` validation

- [ ] **1.4.4** Write tests
  - `tests/Unit/Services/Auth/PasswordBreachServiceTest.php`

---

## Phase 2: Data Protection (Week 3-4)

### 2.1 Field-Level Encryption

- [ ] **2.1.1** Create `app/Casts/EncryptedString.php`
  - Extends Laravel's `Castable`
  - Uses `Crypt::encryptString()` / `Crypt::decryptString()`
  - Handles null values gracefully

- [ ] **2.1.2** Create `app/Casts/EncryptedDecimal.php`
  - Encrypts decimal values as strings
  - Decrypts and casts back to float
  - Preserves precision

- [ ] **2.1.3** Create migration `2025_01_xx_add_encrypted_columns_to_users.php`
  - Add encrypted columns for income fields (VARCHAR(500) for encrypted data)
  - Keep original columns temporarily for migration

- [ ] **2.1.4** Create migration `2025_01_xx_add_encrypted_columns_to_financial_models.php`
  - SavingsAccount: current_balance_encrypted
  - InvestmentAccount: current_value_encrypted, account_number_encrypted
  - DCPension: current_fund_value_encrypted, monthly_contribution_amount_encrypted
  - DBPension: accrued_annual_pension_encrypted, lump_sum_entitlement_encrypted
  - StatePension: current_annual_amount_encrypted, forecast_full_amount_encrypted
  - Property: current_value_encrypted, purchase_price_encrypted
  - Mortgage: current_balance_encrypted, original_amount_encrypted, monthly_payment_encrypted
  - Liability: current_balance_encrypted, original_amount_encrypted, monthly_payment_encrypted

- [ ] **2.1.5** Create `app/Console/Commands/EncryptExistingData.php`
  - Artisan command: `php artisan data:encrypt {--model=} {--batch=100}`
  - Process records in batches
  - Log progress
  - Verify encryption/decryption works before marking complete

- [ ] **2.1.6** Update model casts to use encrypted casts
  - Modify each model to use new EncryptedDecimal cast
  - Test each model individually

- [ ] **2.1.7** Run encryption migration
  - Full database backup first
  - Run `php artisan data:encrypt` for each model
  - Verify data integrity

- [ ] **2.1.8** Write tests
  - `tests/Unit/Casts/EncryptedDecimalTest.php`
  - `tests/Unit/Casts/EncryptedStringTest.php`
  - `tests/Feature/Security/EncryptionTest.php`

### 2.2 Encrypted Backups

- [ ] **2.2.1** Add backup encryption key to `.env.example`
  ```
  BACKUP_ENCRYPTION_KEY=
  ```

- [ ] **2.2.2** Create `app/Services/Backup/EncryptedBackupService.php`
  - `createBackup(): string` (returns filename)
  - `encryptFile(string $path): string` (returns encrypted path)
  - `decryptFile(string $encryptedPath): string` (returns decrypted path)
  - Use OpenSSL AES-256-CBC

- [ ] **2.2.3** Create `app/Console/Commands/CreateEncryptedBackup.php`
  - Artisan command: `php artisan backup:create`
  - Creates SQL dump, compresses, encrypts
  - Stores in `storage/app/backups/`

- [ ] **2.2.4** Create `app/Console/Commands/DecryptBackup.php`
  - Artisan command: `php artisan backup:decrypt {filename}`
  - Decrypts for restore purposes

- [ ] **2.2.5** Update `AdminController::createBackup()` to use encrypted backups

- [ ] **2.2.6** Write tests for backup encryption/decryption

### 2.3 Fix Email Verification Code Expiry

- [ ] **2.3.1** Modify `app/Models/EmailVerificationCode.php`
  - Change `addYear()` to `addMinutes(15)`
  - Add method `isExpired(): bool`

- [ ] **2.3.2** Update verification flow to check expiry properly

- [ ] **2.3.3** Add resend functionality if code expired

---

## Phase 3: Comprehensive Audit Logging (Week 5-6)

### 3.1 Unified Audit System

- [ ] **3.1.1** Create migration `2025_01_xx_create_audit_logs_table.php`
  - Table with all audit fields as specified in plan
  - Proper indexes for querying

- [ ] **3.1.2** Create `app/Models/AuditLog.php`
  - Event type constants
  - Action constants
  - Static `log()` method
  - Scopes for filtering

- [ ] **3.1.3** Create `app/Services/Audit/AuditService.php`
  - `logAuth(string $action, ?User $user, array $metadata = []): void`
  - `logDataAccess(string $action, User $user, ?Model $model = null): void`
  - `logDataChange(string $action, Model $model, array $oldValues, array $newValues): void`
  - `logAdmin(string $action, User $admin, array $metadata = []): void`
  - `logGDPR(string $action, User $user, array $metadata = []): void`

- [ ] **3.1.4** Create `app/Traits/Auditable.php`
  - Boot method to register model observers
  - Automatic change tracking on create/update/delete
  - Configurable `$auditableFields` property

- [ ] **3.1.5** Create `app/Observers/AuditableObserver.php`
  - `created()` - Log creation with new values
  - `updated()` - Log update with old/new values (only changed fields)
  - `deleted()` - Log deletion with old values

- [ ] **3.1.6** Add Auditable trait to financial models
  - Property, Mortgage, Liability
  - SavingsAccount, InvestmentAccount
  - DCPension, DBPension, StatePension
  - LifePolicy, CriticalIllnessPolicy, IncomeProtectionPolicy
  - Trust, Gift

- [ ] **3.1.7** Integrate auth logging in AuthController
  - Log login attempts (success/failure)
  - Log logout
  - Log password changes
  - Log MFA events

- [ ] **3.1.8** Add middleware for data access logging (optional - may impact performance)
  - Log significant page/endpoint access
  - Consider sampling or async logging

- [ ] **3.1.9** Write tests
  - `tests/Unit/Services/Audit/AuditServiceTest.php`
  - `tests/Feature/Audit/AuditLoggingTest.php`

---

## Phase 4: GDPR Compliance (Week 7-8)

### 4.1 Data Export

- [ ] **4.1.1** Create `app/Services/GDPR/DataExportService.php`
  - `generateExport(User $user): array` (all user data)
  - `exportToJson(User $user): string` (JSON file content)
  - `createExportFile(User $user): string` (file path)
  - Include all related data (protection, savings, investment, retirement, estate)

- [ ] **4.1.2** Create `app/Jobs/GenerateDataExport.php`
  - Queue job for async export generation
  - Notify user when complete
  - Store export file with expiry (7 days)

- [ ] **4.1.3** Create database table for export tracking
  ```sql
  CREATE TABLE data_exports (
      id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
      file_path VARCHAR(255) NULL,
      expires_at TIMESTAMP NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      completed_at TIMESTAMP NULL
  );
  ```

- [ ] **4.1.4** Create `app/Http/Controllers/Api/GDPRController.php`
  - `requestExport()` - Queue export job
  - `exportStatus()` - Check export status
  - `downloadExport()` - Download completed export

- [ ] **4.1.5** Register GDPR routes

- [ ] **4.1.6** Create `resources/js/views/Settings/PrivacySettings.vue`
  - Request export button
  - Export status display
  - Download link when ready

- [ ] **4.1.7** Write tests
  - `tests/Unit/Services/GDPR/DataExportServiceTest.php`
  - `tests/Feature/GDPR/DataExportTest.php`

### 4.2 Right to Erasure

- [ ] **4.2.1** Create migration `2025_01_xx_create_erasure_requests_table.php`

- [ ] **4.2.2** Create `app/Models/ErasureRequest.php`

- [ ] **4.2.3** Create `app/Services/GDPR/DataErasureService.php`
  - `requestErasure(User $user): ErasureRequest`
  - `confirmErasure(ErasureRequest $request, string $token): bool`
  - `cancelErasure(ErasureRequest $request): void`
  - `processErasure(ErasureRequest $request): void`
  - `deleteAllUserData(User $user): void` (comprehensive deletion)
  - `anonymizeAuditLogs(User $user): void`

- [ ] **4.2.4** Create `app/Jobs/ProcessDataErasure.php`
  - Queue job for async deletion
  - Delete in correct order (foreign key constraints)
  - Anonymize audit logs
  - Send completion email

- [ ] **4.2.5** Create email templates
  - `app/Mail/ErasureConfirmation.php`
  - `app/Mail/ErasureCompleted.php`

- [ ] **4.2.6** Add erasure endpoints to GDPRController
  - `requestErasure()`
  - `confirmErasure(Request $request)`
  - `cancelErasure()`

- [ ] **4.2.7** Add erasure UI to PrivacySettings.vue
  - Request deletion button
  - Confirmation flow
  - Cancel option during grace period

- [ ] **4.2.8** Write tests
  - `tests/Unit/Services/GDPR/DataErasureServiceTest.php`
  - `tests/Feature/GDPR/ErasureWorkflowTest.php`

### 4.3 Consent Tracking

- [ ] **4.3.1** Create migration `2025_01_xx_create_user_consents_table.php`

- [ ] **4.3.2** Create `app/Models/UserConsent.php`
  - Consent type constants
  - Version tracking
  - Relationships

- [ ] **4.3.3** Create `app/Services/GDPR/ConsentService.php`
  - `recordConsent(User $user, string $type, string $version, bool $consented): void`
  - `hasConsented(User $user, string $type, ?string $version = null): bool`
  - `getConsents(User $user): Collection`
  - `withdrawConsent(User $user, string $type): void`

- [ ] **4.3.4** Create `resources/js/components/Auth/ConsentCheckboxes.vue`
  - Terms of service (required)
  - Privacy policy (required)
  - Marketing emails (optional)

- [ ] **4.3.5** Integrate consent collection in registration flow

- [ ] **4.3.6** Add consent management to PrivacySettings.vue

- [ ] **4.3.7** Write tests
  - `tests/Unit/Services/GDPR/ConsentServiceTest.php`

---

## Phase 5: Enhanced Authorization (Week 9-10)

### 5.1 RBAC Implementation

- [ ] **5.1.1** Create migration `2025_01_xx_create_roles_tables.php`
  - roles table
  - permissions table
  - role_permissions pivot table

- [ ] **5.1.2** Create migration `2025_01_xx_add_role_id_to_users_table.php`
  - Add role_id column with default 1 (user role)

- [ ] **5.1.3** Create `app/Models/Role.php`
  - Relationships to permissions and users
  - `hasPermission(string $permission): bool`

- [ ] **5.1.4** Create `app/Models/Permission.php`
  - Relationships to roles

- [ ] **5.1.5** Create `database/seeders/RolesAndPermissionsSeeder.php`
  - Seed: user, support, admin roles
  - Seed permissions for each module

- [ ] **5.1.6** Create `app/Services/Auth/PermissionService.php`
  - `userHasRole(User $user, string $role): bool`
  - `userHasPermission(User $user, string $permission): bool`
  - `assignRole(User $user, string $role): void`

- [ ] **5.1.7** Create `app/Http/Middleware/HasRole.php`
  - Check user has required role

- [ ] **5.1.8** Create `app/Http/Middleware/HasPermission.php`
  - Check user has required permission

- [ ] **5.1.9** Register middleware aliases in Kernel.php

- [ ] **5.1.10** Migrate existing is_admin users to admin role
  - Create Artisan command for migration
  - Run migration
  - Update IsAdmin middleware to use role check

- [ ] **5.1.11** Write tests
  - `tests/Feature/Authorization/RBACTest.php`

---

## Phase 6: API Security (Week 11)

### 6.1 CORS Configuration

- [ ] **6.1.1** Update `config/cors.php`
  - Restrict allowed_origins to specific domains
  - Restrict allowed_methods to required methods
  - Restrict allowed_headers
  - Set proper max_age

### 6.2 Token Scoping

- [ ] **6.2.1** Define token abilities in Sanctum config or documentation

- [ ] **6.2.2** Update token creation in AuthController
  - Standard tokens: `['read', 'write']`
  - Document read-only token creation for future API access

- [ ] **6.2.3** Add ability middleware to write routes (optional)

### 6.3 Rate Limiting

- [ ] **6.3.1** Update `RouteServiceProvider.php`
  - Role-based rate limits
  - Separate limiter for expensive operations (calculations)

- [ ] **6.3.2** Apply calculation limiter to Monte Carlo endpoints

---

## Phase 7: Legal & Trust (Week 12)

### 7.1 Disclaimers

- [ ] **7.1.1** Create `resources/js/components/Legal/StrategyDisclaimer.vue`
  - Standard disclaimer text
  - Configurable variant (short/full)

- [ ] **7.1.2** Create `resources/js/components/Legal/CalculationDisclaimer.vue`
  - Calculation assumptions disclaimer

- [ ] **7.1.3** Add disclaimers to strategy views
  - Protection adequacy results
  - Retirement projections
  - Estate planning recommendations
  - Investment analysis

### 7.2 Security Page

- [ ] **7.2.1** Update `resources/js/views/Public/SecurityPage.vue`
  - Document implemented security features
  - MFA availability
  - Encryption at rest
  - Audit logging
  - GDPR compliance
  - Session management

### 7.3 Legal Pages

- [ ] **7.3.1** Create `resources/js/views/Public/TermsPage.vue`
  - Limitation of liability
  - Data accuracy assumptions
  - User responsibilities
  - Strategies vs regulated advice
  - Account termination
  - Governing law

- [ ] **7.3.2** Create `resources/js/views/Public/PrivacyPolicyPage.vue`
  - Data collection
  - Data usage
  - Data sharing
  - User rights (GDPR)
  - Contact information

- [ ] **7.3.3** Add routes for legal pages

- [ ] **7.3.4** Link legal pages in footer

---

## Final Verification

- [ ] Run full test suite: `./vendor/bin/pest`
- [ ] Manual testing checklist (see SecurityCompliancePlan.md)
- [ ] Security review of all new endpoints
- [ ] Database backup before production deployment
- [ ] Staged rollout (deploy to staging first)
- [ ] Monitor logs for errors after deployment
- [ ] Reseed required data after deployment

---

## Post-Implementation

- [ ] Update CLAUDE.md with new security features
- [ ] Create admin documentation for new features
- [ ] Create user documentation for MFA, sessions, GDPR
- [ ] Schedule quarterly security review
- [ ] Plan penetration testing (Phase 2 - future)

---

## Phase 8: Code Quality Audit (Complete)

> **Added:** 19 January 2025 - All tasks completed post-merge

### 8.1 Critical Bug Fixes

- [x] **TASK-001** Fix `lifePolicies` → `lifeInsurancePolicies` in DataErasureService
- [x] **TASK-002** Fix MFA user enumeration vulnerability (use secure challenge tokens)
- [x] **TASK-014** Add `device_name` to UserSession fillable attributes

### 8.2 Test Coverage

- [x] **TASK-003** Add SessionService unit tests (10 tests)
- [x] **TASK-004** Add PermissionService unit tests (8 tests)
- [x] **TASK-005** Add DataErasureService unit tests (8 tests)
- [x] **TASK-010** Add feature tests for MFA, Sessions, GDPR APIs (32 tests)

### 8.3 Code Quality

- [x] **TASK-006** Extract shared modal CSS to `_modals.css`
- [x] **TASK-007** Fix toast notification pattern in MFASetupModal
- [x] **TASK-008** Add default `requested_at` to ErasureRequest model
- [x] **TASK-011** Add JSDoc comments to MFA Vue components
- [x] **TASK-012** Extract audit retention config to `config/audit.php`
- [x] **TASK-013** Create `PurgeAuditLogs` artisan command
