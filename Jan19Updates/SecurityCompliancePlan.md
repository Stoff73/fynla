# Fynla Security Compliance Implementation Plan

**Created:** 19 January 2025
**Status:** Planning Complete - Ready for Implementation
**Approach:** Comprehensive (all items before next release)

## Executive Summary

This plan addresses security compliance for Fynla based on the security requirements document. It covers 9 key security areas with a prioritized implementation roadmap designed to work within shared hosting constraints using self-hosted solutions only.

### Business Context
- **Platform Type:** Hybrid (B2C direct consumers + B2B adviser-led, adviser features deferred)
- **User Geography:** Global (UK GDPR, EU GDPR, potentially CCPA compliance)
- **Service Type:** Financial strategies (functionally similar to regulated advice)
- **Infrastructure:** Shared hosting (application-level security only)
- **Budget:** Self-hosted solutions only (no paid external services)

---

## Current State vs Requirements

### What's Already Implemented

| Feature | Status | Notes |
|---------|--------|-------|
| Token-based Auth | Good | Laravel Sanctum, 8hr expiry |
| Email OTP | Good | But codes don't expire properly (set to 1 year) |
| Password Policy | Good | 8+ chars, mixed case, special chars |
| Rate Limiting | Good | 300/min prod, endpoint-specific throttling |
| Input Sanitization | Good | XSS protection via `SanitizeInput` middleware |
| CSRF Protection | Good | SameSite cookies, Sanctum |
| Admin Auth | Basic | `is_admin` flag, `IsAdmin` middleware |
| Field Encryption | Partial | Only `SavingsAccount.account_number` encrypted |
| Audit Logging | Partial | Tax config, documents, joint accounts only |

### Critical Gaps (Must Address)

| Gap | Risk | Impact |
|-----|------|--------|
| No MFA/2FA | Critical | Single factor auth vulnerable to credential theft |
| No encryption at rest | Critical | Financial data exposed if DB compromised |
| No comprehensive audit logging | Critical | Cannot track data access or changes |
| No GDPR data export | High | Non-compliance with Article 15 |
| No GDPR erasure workflow | High | Non-compliance with Article 17 |
| No failed login tracking | High | Brute force attacks undetected |
| No session management UI | High | Users cannot manage their security |
| No consent tracking | Medium | GDPR consent requirements unmet |
| CORS too permissive | Medium | Potential cross-origin attacks |

---

## Implementation Phases

### Phase 1: Authentication Security (Week 1-2)
**Priority: CRITICAL**

#### 1.1 Failed Login Tracking & Progressive Lockout

**Database Schema:**
```sql
-- Migration: 2025_01_xx_create_login_attempts_table.php
CREATE TABLE login_attempts (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    successful BOOLEAN DEFAULT FALSE,
    failure_reason VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_created (email, created_at),
    INDEX idx_ip_created (ip_address, created_at)
);

-- Add lockout fields to users
ALTER TABLE users ADD COLUMN failed_login_count INT DEFAULT 0;
ALTER TABLE users ADD COLUMN locked_until TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN last_failed_login_at TIMESTAMP NULL;
```

**Files to Create:**
- `app/Models/LoginAttempt.php`
- `app/Services/Auth/LoginLockoutService.php`

**Files to Modify:**
- `app/Http/Controllers/Api/AuthController.php` - Add lockout checks to `login()` method

**Lockout Policy:**
| Failed Attempts | Lockout Duration |
|-----------------|------------------|
| 3 | 1 minute |
| 5 | 5 minutes |
| 10 | 30 minutes |
| 15+ | 24 hours (notify admin) |

#### 1.2 TOTP Multi-Factor Authentication

**Package:** `pragmarx/google2fa-laravel` + `bacon/bacon-qr-code`

**Database Schema:**
```sql
-- Migration: 2025_01_xx_add_mfa_fields_to_users_table.php
ALTER TABLE users ADD COLUMN mfa_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN mfa_secret VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN mfa_recovery_codes JSON NULL;
ALTER TABLE users ADD COLUMN mfa_confirmed_at TIMESTAMP NULL;
```

**Files to Create:**
- `app/Http/Controllers/Api/MFAController.php`
- `app/Http/Middleware/EnsureMFAVerified.php`
- `app/Services/Auth/MFAService.php`
- `resources/js/views/Settings/SecuritySettings.vue`
- `resources/js/components/Auth/MFASetupModal.vue`
- `resources/js/components/Auth/MFAVerifyModal.vue`

**API Endpoints:**
```
POST /api/auth/mfa/setup          - Generate secret & QR code
POST /api/auth/mfa/verify-setup   - Confirm TOTP, enable MFA
POST /api/auth/mfa/verify         - Verify TOTP during login
POST /api/auth/mfa/recovery       - Use recovery code
POST /api/auth/mfa/disable        - Disable MFA (requires password)
GET  /api/auth/mfa/recovery-codes - Regenerate recovery codes
```

**Recovery Codes:**
- Generate 10 recovery codes on MFA setup
- Hash with bcrypt before storing (same as passwords)
- Each code usable only once
- Can regenerate all codes (invalidates previous)

#### 1.3 Session Management

**Database Schema:**
```sql
-- Migration: 2025_01_xx_create_user_sessions_table.php
CREATE TABLE user_sessions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_id BIGINT UNSIGNED NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_name VARCHAR(255) NULL,
    last_activity_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Files to Create:**
- `app/Models/UserSession.php`
- `app/Http/Controllers/Api/SessionController.php`
- `app/Services/Auth/SessionService.php`
- `app/Observers/PersonalAccessTokenObserver.php`
- `resources/js/components/Settings/ActiveSessions.vue`

**API Endpoints:**
```
GET    /api/auth/sessions         - List all active sessions
DELETE /api/auth/sessions/{id}    - Revoke specific session
DELETE /api/auth/sessions/others  - Revoke all except current
```

#### 1.4 Password Breach Checking

**Implementation:** Have I Been Pwned API (k-Anonymity - only first 5 chars of SHA1 hash sent)

**Files to Create:**
- `app/Services/Auth/PasswordBreachService.php`
- `app/Rules/NotBreached.php`

**Integration Points:**
- Registration password validation (warn, don't block)
- Password change validation (warn, don't block)
- Display warning but allow user to proceed

---

### Phase 2: Data Protection (Week 3-4)
**Priority: CRITICAL**

#### 2.1 Field-Level Encryption at Rest

**Reference Implementation:** `app/Models/SavingsAccount.php` (account_number encryption)

**Files to Create:**
- `app/Casts/EncryptedDecimal.php`
- `app/Casts/EncryptedString.php`

**Models to Update:**

| Model | Encrypted Fields |
|-------|-----------------|
| User | `annual_employment_income`, `annual_self_employment_income`, `annual_rental_income`, `annual_dividend_income`, `annual_pension_income`, `annual_state_benefits_income`, `annual_other_income` |
| SavingsAccount | `current_balance` (account_number already done) |
| InvestmentAccount | `current_value`, `account_number` |
| DCPension | `current_fund_value`, `monthly_contribution_amount`, `employer_contribution_amount` |
| DBPension | `accrued_annual_pension`, `lump_sum_entitlement` |
| StatePension | `current_annual_amount`, `forecast_full_amount` |
| Property | `current_value`, `purchase_price` |
| Mortgage | `current_balance`, `original_amount`, `monthly_payment` |
| Liability | `current_balance`, `original_amount`, `monthly_payment` |

**Migration Strategy:**
1. Create new encrypted columns (nullable)
2. Create Artisan command `php artisan data:encrypt` to migrate existing data
3. Run encryption in batches (100 records at a time) to avoid timeout
4. After verification, drop original columns or keep as backup

#### 2.2 Encrypted Backups

**Files to Create:**
- `app/Console/Commands/CreateEncryptedBackup.php`
- `app/Services/Backup/EncryptedBackupService.php`

**Process:**
1. Export database to SQL using existing `mysqldump` approach
2. Compress with gzip
3. Encrypt with AES-256-CBC using separate backup key (stored in .env)
4. Store encrypted `.sql.gz.enc` file
5. Delete unencrypted intermediate files

**Decryption Command:**
```bash
php artisan backup:decrypt {filename}
```

#### 2.3 Fix Email Verification Code Expiry

**Current Issue:** Codes set to expire in 1 year (effectively never)

**File to Modify:** `app/Models/EmailVerificationCode.php`

**Change:**
```php
// FROM: 'expires_at' => Carbon::now()->addYear()
// TO:   'expires_at' => Carbon::now()->addMinutes(15)
```

---

### Phase 3: Comprehensive Audit Logging (Week 5-6)
**Priority: CRITICAL**

#### 3.1 Unified Audit Log System

**Reference Implementation:** `app/Models/DocumentExtractionLog.php`

**Database Schema:**
```sql
-- Migration: 2025_01_xx_create_audit_logs_table.php
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    model_type VARCHAR(100) NULL,
    model_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    metadata JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_event_action (event_type, action),
    INDEX idx_model (model_type, model_id)
);
```

**Files to Create:**
- `app/Models/AuditLog.php`
- `app/Services/Audit/AuditService.php`
- `app/Traits/Auditable.php`
- `app/Observers/AuditableObserver.php`

**Event Types & Actions:**

| Event Type | Actions |
|------------|---------|
| `auth` | `login_attempt`, `login_success`, `login_failed`, `logout`, `mfa_enabled`, `mfa_disabled`, `mfa_verified`, `password_changed`, `session_revoked` |
| `data_access` | `view_dashboard`, `view_protection`, `view_savings`, `view_investment`, `view_retirement`, `view_estate`, `export_data` |
| `data_change` | `created`, `updated`, `deleted` (for all financial models) |
| `admin` | `user_created`, `user_updated`, `user_deleted`, `backup_created`, `backup_restored`, `tax_config_changed` |
| `gdpr` | `export_requested`, `export_completed`, `erasure_requested`, `erasure_completed` |

**Models to Add Auditable Trait:**
- All financial models (Property, Mortgage, Liability, SavingsAccount, InvestmentAccount, DCPension, DBPension, StatePension, LifePolicy, CriticalIllnessPolicy, IncomeProtectionPolicy)
- User model (for profile changes)

---

### Phase 4: GDPR Compliance (Week 7-8)
**Priority: HIGH**

#### 4.1 Data Export (Article 15 - Right of Access)

**Files to Create:**
- `app/Http/Controllers/Api/GDPRController.php`
- `app/Services/GDPR/DataExportService.php`
- `app/Jobs/GenerateDataExport.php`
- `resources/js/views/Settings/PrivacySettings.vue`

**API Endpoints:**
```
POST /api/gdpr/export/request   - Request data export (queued)
GET  /api/gdpr/export/status    - Check export status
GET  /api/gdpr/export/download  - Download generated export
```

**Export Format:**
```json
{
  "export_date": "2025-01-19T12:00:00Z",
  "user": { "profile data..." },
  "protection": { "policies..." },
  "savings": { "accounts..." },
  "investment": { "accounts..." },
  "retirement": { "pensions..." },
  "estate": { "assets, trusts..." },
  "activity_log": { "recent actions..." }
}
```

#### 4.2 Right to Erasure (Article 17)

**Database Schema:**
```sql
-- Migration: 2025_01_xx_create_erasure_requests_table.php
CREATE TABLE erasure_requests (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    requested_at TIMESTAMP NOT NULL,
    confirmation_token VARCHAR(64) NULL,
    confirmed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    status ENUM('pending', 'confirmed', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

**Files to Create:**
- `app/Services/GDPR/DataErasureService.php`
- `app/Jobs/ProcessDataErasure.php`
- `app/Mail/ErasureConfirmation.php`
- `app/Mail/ErasureCompleted.php`

**Erasure Workflow:**
1. User requests erasure via settings
2. Confirmation email sent with unique token
3. 7-day grace period (can cancel)
4. After confirmation click, queue hard deletion
5. Delete all user data across all tables
6. Anonymize audit logs (keep events, replace user_id with "deleted_user")
7. Send completion email to confirmed address

**API Endpoints:**
```
POST /api/gdpr/erasure/request   - Request account deletion
POST /api/gdpr/erasure/confirm   - Confirm with token
POST /api/gdpr/erasure/cancel    - Cancel during grace period
```

#### 4.3 Consent Tracking

**Database Schema:**
```sql
-- Migration: 2025_01_xx_create_user_consents_table.php
CREATE TABLE user_consents (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    consent_type VARCHAR(50) NOT NULL,
    version VARCHAR(20) NOT NULL,
    consented BOOLEAN DEFAULT FALSE,
    consented_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, consent_type)
);
```

**Consent Types:**
- `terms_of_service` - v1.0 (required)
- `privacy_policy` - v1.0 (required)
- `marketing_emails` - v1.0 (optional)
- `analytics_cookies` - v1.0 (optional)

**Files to Create:**
- `app/Models/UserConsent.php`
- `app/Services/GDPR/ConsentService.php`
- `resources/js/components/Auth/ConsentCheckboxes.vue`

---

### Phase 5: Enhanced Authorization (Week 9-10)
**Priority: HIGH**

#### 5.1 Role-Based Access Control

**Database Schema:**
```sql
-- Migration: 2025_01_xx_create_roles_tables.php
CREATE TABLE roles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Add role_id to users (replaces is_admin)
ALTER TABLE users ADD COLUMN role_id BIGINT UNSIGNED DEFAULT 1;
```

**Initial Roles:**
| Role | Description | Permissions |
|------|-------------|-------------|
| `user` (id=1) | Standard consumer | Own data CRUD |
| `support` (id=2) | Support staff | Read-only access to user data |
| `admin` (id=3) | Full admin | All permissions |

**Files to Create:**
- `app/Models/Role.php`
- `app/Models/Permission.php`
- `app/Services/Auth/PermissionService.php`
- `app/Http/Middleware/HasRole.php`
- `app/Http/Middleware/HasPermission.php`
- `database/seeders/RolesAndPermissionsSeeder.php`

**Migration from is_admin:**
1. Create roles table and seed initial roles
2. Add role_id column to users
3. Migrate: `is_admin = true` → `role_id = 3`
4. Migrate: `is_admin = false` → `role_id = 1`
5. Update `IsAdmin` middleware to check role instead
6. Eventually drop `is_admin` column

---

### Phase 6: API Security (Week 11)
**Priority: MEDIUM**

#### 6.1 Tighten CORS Configuration

**File to Modify:** `config/cors.php`

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_filter([
        env('APP_URL'),
        env('FRONTEND_URL'),
        // Specific production domains only
        'https://fynla.org',
        'https://www.fynla.org',
    ]),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
    'max_age' => 86400,
    'supports_credentials' => true,
];
```

#### 6.2 Token Scoping (Read/Write Permissions)

**Approach:** Use Sanctum's token abilities

**Token Scopes:**
- `read` - Read-only API access
- `write` - Create/update/delete operations
- `admin` - Administrative operations

**Implementation in AuthController:**
```php
// Standard login - full access
$token = $user->createToken('auth_token', ['read', 'write']);

// Read-only API access (future use for integrations)
$token = $user->createToken('readonly_token', ['read']);
```

**Middleware for Routes:**
```php
Route::middleware(['auth:sanctum', 'ability:write'])->group(function () {
    // Write operations only
});
```

#### 6.3 Enhanced Rate Limiting

**File to Modify:** `app/Providers/RouteServiceProvider.php`

```php
// Role-based rate limits
RateLimiter::for('api', function (Request $request) {
    $user = $request->user();

    if ($user?->role?->name === 'admin') {
        return Limit::perMinute(1000)->by($user->id);
    }

    if ($user?->role?->name === 'support') {
        return Limit::perMinute(500)->by($user->id);
    }

    return Limit::perMinute(300)->by($user?->id ?: $request->ip());
});

// Stricter limits for expensive operations
RateLimiter::for('calculations', function (Request $request) {
    return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
});
```

---

### Phase 7: Legal & Trust (Week 12)
**Priority: MEDIUM**

#### 7.1 Strategy Disclaimers

**Files to Create:**
- `resources/js/components/Legal/StrategyDisclaimer.vue`
- `resources/js/components/Legal/CalculationDisclaimer.vue`

**Standard Disclaimer Text:**
> "The strategies and projections provided by Fynla are for informational and planning purposes only. They do not constitute regulated financial advice under the Financial Services and Markets Act 2000. Past performance is not indicative of future results. Tax rules can change and their effects depend on individual circumstances. Please consult with a qualified financial adviser before making significant financial decisions."

**Placement:**
- Footer of all strategy/recommendation views
- Before Monte Carlo projections
- In PDF/data exports
- On calculation result pages

#### 7.2 Security Page Updates

**File to Modify:** `resources/js/views/Public/SecurityPage.vue`

Update to reflect actual implemented security features:
- MFA available (TOTP)
- Data encrypted at rest
- Session management
- Audit logging
- GDPR compliance (export, erasure)
- Regular backups

#### 7.3 Terms of Service Updates

**Files to Create:**
- `resources/js/views/Public/TermsPage.vue`
- `resources/js/views/Public/PrivacyPolicyPage.vue`

**Key Sections for Terms:**
- Limitation of liability
- Data accuracy assumptions
- User responsibilities
- Strategies vs regulated advice distinction
- Account termination
- Dispute resolution

---

## Testing Strategy

### Unit Tests
```
tests/Unit/Services/Auth/MFAServiceTest.php
tests/Unit/Services/Auth/LoginLockoutServiceTest.php
tests/Unit/Services/Auth/PasswordBreachServiceTest.php
tests/Unit/Services/Auth/SessionServiceTest.php
tests/Unit/Services/Audit/AuditServiceTest.php
tests/Unit/Services/GDPR/DataExportServiceTest.php
tests/Unit/Services/GDPR/DataErasureServiceTest.php
tests/Unit/Services/GDPR/ConsentServiceTest.php
tests/Unit/Casts/EncryptedDecimalTest.php
tests/Unit/Casts/EncryptedStringTest.php
```

### Feature Tests
```
tests/Feature/Auth/MFAFlowTest.php
tests/Feature/Auth/LoginLockoutTest.php
tests/Feature/Auth/SessionManagementTest.php
tests/Feature/GDPR/DataExportTest.php
tests/Feature/GDPR/ErasureWorkflowTest.php
tests/Feature/Authorization/RBACTest.php
tests/Feature/Security/EncryptionTest.php
```

### Manual Testing Checklist
- [ ] MFA setup flow with Google Authenticator
- [ ] Recovery code usage
- [ ] Account lockout after failed attempts
- [ ] Session viewing and revocation
- [ ] Data export generates correct JSON
- [ ] Erasure workflow with email confirmation
- [ ] Consent checkboxes on registration
- [ ] Role-based route access
- [ ] Rate limiting behaviour
- [ ] Encrypted data readable after encryption

---

## Dependencies

### Composer Packages
```bash
composer require pragmarx/google2fa-laravel
composer require bacon/bacon-qr-code
```

### No Additional NPM Packages Required
- Use existing Vue.js 3 + Tailwind CSS stack
- QR code displayed as data URI from backend

---

## Shared Hosting Considerations

| Constraint | Solution |
|------------|----------|
| No cron jobs | Use Laravel queue with database driver (already configured) |
| No server firewall | Application-level rate limiting |
| No KMS service | Use APP_KEY with documented rotation |
| No WAF | Input sanitization middleware (already exists) |
| Limited shell access | All commands via Artisan |
| No Redis | Use database cache/queue driver |

---

## Key Files Summary

### Critical Files to Create
1. `app/Services/Auth/MFAService.php` - MFA implementation
2. `app/Services/Auth/LoginLockoutService.php` - Brute force protection
3. `app/Services/Audit/AuditService.php` - Unified audit logging
4. `app/Services/GDPR/DataExportService.php` - GDPR export
5. `app/Services/GDPR/DataErasureService.php` - GDPR erasure
6. `app/Casts/EncryptedDecimal.php` - Field encryption
7. `app/Traits/Auditable.php` - Model change tracking

### Critical Files to Modify
1. `app/Http/Controllers/Api/AuthController.php` - Add MFA, lockout, session tracking
2. `app/Models/User.php` - Add MFA fields, role relationship, encrypted fields
3. `app/Providers/RouteServiceProvider.php` - Enhanced rate limiting
4. `config/cors.php` - Tighten CORS
5. `app/Models/EmailVerificationCode.php` - Fix expiry (15 mins not 1 year)

### Migrations to Create
1. `create_login_attempts_table`
2. `add_mfa_fields_to_users_table`
3. `create_user_sessions_table`
4. `create_audit_logs_table`
5. `create_erasure_requests_table`
6. `create_user_consents_table`
7. `create_roles_tables`
8. `add_encrypted_columns_to_financial_models`

---

## Implementation Timeline

| Week | Phase | Key Deliverables |
|------|-------|------------------|
| 1-2 | Auth Security | Failed login tracking, MFA, session management |
| 3-4 | Data Protection | Field encryption, encrypted backups, fix OTP expiry |
| 5-6 | Audit Logging | Unified audit system, Auditable trait |
| 7-8 | GDPR | Data export, erasure workflow, consent tracking |
| 9-10 | Authorization | RBAC system, role migration |
| 11 | API Security | CORS, token scoping, rate limits |
| 12 | Legal/Trust | Disclaimers, security page, terms updates |

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Data encryption breaks existing queries | Test encryption cast thoroughly, add DB indexes on encrypted fields if needed for lookups |
| MFA lockout (lost phone/codes) | Admin reset flow, 10 recovery codes generated on setup |
| Migration failures | Run in batches, full backup before each phase |
| Performance impact of audit logging | Async logging via queue, database indexes |
| GDPR erasure incomplete | Comprehensive list of all user-related tables, cascade deletes |

---

## Success Criteria

- [ ] MFA adoption > 50% of active users within 3 months
- [ ] Zero untracked login attempts
- [ ] All financial data encrypted at rest
- [ ] Audit logs for all data access and changes
- [ ] GDPR export completes in < 5 minutes
- [ ] GDPR erasure completes in < 24 hours
- [ ] No CORS-related security issues
- [ ] Rate limiting prevents abuse without blocking legitimate use
