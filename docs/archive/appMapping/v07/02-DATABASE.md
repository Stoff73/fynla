# 02 - Database Schema

Fynla v0.7.0 uses MySQL 8 with 55 migrations, 49 models, and approximately 50 tables. This document covers every table, its columns, relationships, and indexing strategy.

Laravel conventions apply throughout: every table has `id` (unsigned bigint, auto-increment primary key), `created_at` (timestamp, nullable), and `updated_at` (timestamp, nullable) unless noted otherwise. Foreign keys use `unsignedBigInteger` with cascade or restrict deletes as appropriate.

---

## 1. Tables by Category

### 1.1 Authentication and Security

#### `users`

The central table. Over 80 columns covering personal details, income, expenditure, MFA, account lockout, preview mode, onboarding, and UI preferences.

**Personal Information**

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| name | varchar | yes | null | Legacy full name column |
| first_name | varchar | yes | null | New name structure |
| middle_name | varchar | yes | null | |
| surname | varchar | yes | null | |
| email | varchar | no | | Unique |
| password | varchar | no | | Hashed via Laravel cast |
| email_verified_at | timestamp | yes | null | |
| remember_token | varchar(100) | yes | null | |
| date_of_birth | date | yes | null | |
| gender | varchar | yes | null | |
| marital_status | varchar | yes | null | married, single, etc. |
| nationality | varchar | yes | null | |
| national_insurance_number | varchar | yes | null | |
| occupation | varchar | yes | null | |
| soc_code | varchar | yes | null | SOC 2020 code |
| phone | varchar | yes | null | |
| address_line_1 | varchar | yes | null | |
| address_line_2 | varchar | yes | null | |
| city | varchar | yes | null | |
| county | varchar | yes | null | |
| postcode | varchar | yes | null | |
| country_of_birth | varchar | yes | null | |
| domicile_status | varchar | yes | null | uk_domiciled, non_uk_domiciled |
| uk_arrival_date | date | yes | null | |
| deemed_domicile_date | date | yes | null | |
| retirement_date | date | yes | null | |

**Income Sources**

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| annual_employment_income | decimal(12,2) | yes | null | Cast to float |
| annual_self_employment_income | decimal(12,2) | yes | null | |
| annual_rental_income | decimal(12,2) | yes | null | |
| annual_dividend_income | decimal(12,2) | yes | null | |
| annual_interest_income | decimal(12,2) | yes | null | |
| annual_other_income | decimal(12,2) | yes | null | |
| annual_trust_income | decimal(12,2) | yes | null | |
| payday_day_of_month | integer | yes | null | 1-31 |

**Expenditure Categories**

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| monthly_expenditure | decimal(12,2) | yes | null | |
| annual_expenditure | decimal(12,2) | yes | null | |
| food_groceries | decimal(12,2) | yes | null | |
| transport_fuel | decimal(12,2) | yes | null | |
| healthcare_medical | decimal(12,2) | yes | null | |
| insurance | decimal(12,2) | yes | null | |
| mobile_phones | decimal(12,2) | yes | null | |
| internet_tv | decimal(12,2) | yes | null | |
| subscriptions | decimal(12,2) | yes | null | |
| clothing_personal_care | decimal(12,2) | yes | null | |
| entertainment_dining | decimal(12,2) | yes | null | |
| holidays_travel | decimal(12,2) | yes | null | |
| pets | decimal(12,2) | yes | null | |
| childcare | decimal(12,2) | yes | null | |
| school_fees | decimal(12,2) | yes | null | |
| school_lunches | decimal(12,2) | yes | null | |
| school_extras | decimal(12,2) | yes | null | |
| university_fees | decimal(12,2) | yes | null | |
| children_activities | decimal(12,2) | yes | null | |
| gifts_charity | decimal(12,2) | yes | null | |
| regular_savings | decimal(12,2) | yes | null | |
| other_expenditure | decimal(12,2) | yes | null | |
| rent | decimal(12,2) | yes | null | |
| utilities | decimal(12,2) | yes | null | |

**MFA Fields**

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| mfa_enabled | boolean | no | false | |
| mfa_secret | text | yes | null | TOTP secret, hidden from serialisation |
| mfa_recovery_codes | json | yes | null | Array of backup codes, hidden |
| mfa_confirmed_at | timestamp | yes | null | |

**Lockout Fields**

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| failed_login_count | integer | no | 0 | |
| locked_until | timestamp | yes | null | |
| last_failed_login_at | timestamp | yes | null | |
| must_change_password | boolean | no | false | |

**Preview and Admin**

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| is_preview_user | boolean | no | false | Guarded |
| preview_persona_id | varchar | yes | null | Guarded |
| is_admin | boolean | no | false | Guarded |
| is_primary_account | boolean | yes | null | |

**Relationships and Household**

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| spouse_id | unsignedBigInteger | yes | null | FK to users.id |
| household_id | unsignedBigInteger | yes | null | FK to households.id |
| role_id | unsignedBigInteger | yes | null | FK to roles.id |

**Onboarding and UI**

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| onboarding_completed | boolean | no | false | |
| onboarding_skipped_steps | json | yes | null | |
| onboarding_started_at | timestamp | yes | null | |
| onboarding_completed_at | timestamp | yes | null | |
| charitable_bequest | boolean | yes | null | |
| liabilities_reviewed | boolean | yes | null | |
| info_guide_enabled | boolean | no | true | |
| guidance_active | boolean | yes | null | |
| guidance_completed | boolean | yes | null | |
| guidance_current_step | integer | yes | null | |
| dashboard_widget_order | json | yes | null | Array of widget positions |

**Model**: `App\Models\User` -- Uses `$guarded` instead of `$fillable`. Extends Authenticatable with HasApiTokens (Sanctum), HasFactory, Notifiable.

---

#### `pending_registrations`

Stores registration data until email verification completes. No expiry timer. Re-registering the same email overwrites the previous pending record.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| email | varchar | no | | Unique |
| first_name | varchar | no | | |
| middle_name | varchar | yes | null | |
| surname | varchar | no | | |
| password | varchar | no | | Pre-hashed, hidden |
| verification_code | varchar | no | | 6-digit, hidden |
| registration_source | varchar | yes | null | "preview" or null |
| preview_persona_id | varchar | yes | null | |

**Model**: `App\Models\PendingRegistration`

---

#### `login_attempts`

Tracks every login attempt for rate limiting and security monitoring. No `updated_at` column -- only `created_at`.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| email | varchar | no | | |
| ip_address | varchar | no | | |
| user_agent | text | yes | null | |
| successful | boolean | no | | |
| failure_reason | varchar | yes | null | invalid_credentials, account_locked, mfa_required, mfa_failed, email_not_verified |
| created_at | timestamp | yes | null | No updated_at |

**Indexes**: `[email, created_at]`, `[ip_address, created_at]`

**Model**: `App\Models\LoginAttempt` -- `$timestamps = false`, only `created_at` is cast.

---

#### `password_reset_sessions`

Multi-step password reset flow with email verification, optional MFA verification, and session token.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| token | varchar(64) | no | | Random string |
| email_code | varchar(6) | no | | 6-digit code |
| email_code_resend_count | integer | no | 0 | Max 2 resends |
| email_verified_at | timestamp | yes | null | |
| mfa_verified_at | timestamp | yes | null | |
| ip_address | varchar | yes | null | |
| expires_at | timestamp | no | | 15 minutes from creation |
| used_at | timestamp | yes | null | |
| created_at | timestamp | yes | null | No updated_at |

**Model**: `App\Models\PasswordResetSession` -- `$timestamps = false`.

---

#### `user_sessions`

Active authentication sessions linked to Sanctum personal access tokens.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| token_id | unsignedBigInteger | no | | FK to personal_access_tokens.id |
| ip_address | varchar | yes | null | |
| user_agent | text | yes | null | |
| device_name | varchar | yes | null | Parsed from user_agent |
| last_activity_at | timestamp | yes | null | |
| created_at | timestamp | yes | null | No updated_at |

**Index**: `[user_id, created_at]`

**Model**: `App\Models\UserSession` -- `$timestamps = false`.

---

#### `audit_logs`

Immutable audit trail for authentication events, data changes, admin actions, and GDPR events. No `updated_at` column.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | yes | null | FK to users.id, nullable for pre-auth events |
| event_type | varchar | no | | auth, data_access, data_change, admin, gdpr |
| action | varchar | no | | e.g. login_success, created, updated, deleted |
| model_type | varchar | yes | null | Fully qualified class name |
| model_id | unsignedBigInteger | yes | null | |
| old_values | json | yes | null | Previous state |
| new_values | json | yes | null | New state |
| metadata | json | yes | null | Additional context |
| ip_address | varchar | yes | null | |
| user_agent | text | yes | null | |
| created_at | timestamp | yes | null | No updated_at |

**Model**: `App\Models\AuditLog` -- `$timestamps = false`. Provides static helper methods: `log()`, `logAuth()`, `logDataChange()`, `logGDPR()`, `logAdmin()`.

---

#### `roles`

Role-based access control with hierarchical levels.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| name | varchar | no | | Unique: user, support, admin |
| display_name | varchar | yes | null | |
| description | varchar | yes | null | |
| level | integer | no | 0 | Hierarchy: 0=user, 50=support, 100=admin |

**Model**: `App\Models\Role`

---

#### `permissions`

Named permissions organised by category.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| name | varchar | no | | Unique. Format: category.action (e.g. users.view) |
| display_name | varchar | yes | null | |
| description | varchar | yes | null | |
| category | varchar | yes | null | users, content, settings, admin |

**Model**: `App\Models\Permission`

---

#### `role_permission`

Pivot table linking roles to permissions. No timestamps.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| role_id | unsignedBigInteger | no | | FK to roles.id |
| permission_id | unsignedBigInteger | no | | FK to permissions.id |

---

#### `user_consents`

GDPR consent tracking with versioning. Each consent type can have multiple versions; only the current version matters.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| consent_type | varchar | no | | terms, privacy, marketing, data_processing |
| version | varchar | no | | e.g. v1.0 |
| consented | boolean | no | | |
| consented_at | timestamp | yes | null | |
| withdrawn_at | timestamp | yes | null | |
| ip_address | varchar | yes | null | |
| user_agent | text | yes | null | |

**Unique constraint**: `[user_id, consent_type, version]`

**Model**: `App\Models\UserConsent`

---

#### `email_verification_codes`

6-digit codes for login verification, registration, and password reset. 15-minute expiry with a maximum of 2 resends.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| code | varchar(6) | no | | 6-digit zero-padded |
| type | varchar | no | | login, registration, password_reset |
| resend_count | integer | no | 0 | Max 2 |
| expires_at | timestamp | no | | 15 minutes from creation |
| verified_at | timestamp | yes | null | |

**Model**: `App\Models\EmailVerificationCode`

---

### 1.2 GDPR

#### `erasure_requests`

Right-to-erasure (Article 17) request tracking.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| status | varchar | no | pending | pending, processing, completed, cancelled |
| reason | text | yes | null | User-provided reason |
| requested_at | timestamp | yes | null | Auto-set on creation |
| confirmed_at | timestamp | yes | null | |
| completed_at | timestamp | yes | null | |
| cancelled_at | timestamp | yes | null | |
| data_categories_deleted | json | yes | null | Array of deleted category names |
| processed_by | varchar | yes | null | Admin identifier |

**Model**: `App\Models\ErasureRequest`

---

#### `data_exports`

Right-of-access (Article 15) data export requests. Files expire after 7 days.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| status | varchar | no | pending | pending, processing, completed, failed, expired |
| format | varchar | no | json | json, csv |
| file_path | varchar | yes | null | Storage path |
| file_size | integer | yes | null | Bytes |
| requested_at | timestamp | yes | null | |
| completed_at | timestamp | yes | null | |
| expires_at | timestamp | yes | null | 7 days after completion |
| downloaded_at | timestamp | yes | null | |
| ip_address | varchar | yes | null | |

**Model**: `App\Models\DataExport`

---

#### `document_extractions`

Stores extraction data from uploaded documents (payslips, statements, etc.).

**Model**: `App\Models\DocumentExtraction`

---

### 1.3 Properties

#### `properties`

Real property records with full address, costs, rental details, and ownership.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| household_id | unsignedBigInteger | yes | null | FK to households.id |
| trust_id | unsignedBigInteger | yes | null | FK to trusts.id |
| property_type | varchar | no | | main_residence, secondary_residence, buy_to_let |
| ownership_type | varchar | no | individual | individual, joint, tenants_in_common, trust |
| joint_ownership_type | varchar | yes | null | joint_tenancy, tenants_in_common |
| joint_owner_id | unsignedBigInteger | yes | null | FK to users.id |
| joint_owner_name | varchar | yes | null | Free text if not a system user |
| trust_name | varchar | yes | null | Free text if trust not linked |
| tenure_type | varchar | yes | null | freehold, leasehold |
| lease_remaining_years | integer | yes | null | |
| lease_expiry_date | date | yes | null | |
| country | varchar | yes | null | |
| ownership_percentage | decimal(5,2) | yes | null | Primary owner's share |
| address_line_1 | varchar | yes | null | |
| address_line_2 | varchar | yes | null | |
| city | varchar | yes | null | |
| county | varchar | yes | null | |
| postcode | varchar | yes | null | |
| purchase_date | date | yes | null | |
| purchase_price | decimal(12,2) | yes | null | |
| current_value | decimal(12,2) | yes | null | |
| valuation_date | date | yes | null | |
| sdlt_paid | decimal(12,2) | yes | null | Stamp Duty Land Tax |
| monthly_rental_income | decimal(10,2) | yes | null | Buy-to-let only |
| outstanding_mortgage | decimal(12,2) | yes | null | Legacy field; mortgages table preferred |
| tenant_name | varchar | yes | null | |
| tenant_email | varchar | yes | null | |
| managing_agent_name | varchar | yes | null | |
| managing_agent_company | varchar | yes | null | |
| managing_agent_email | varchar | yes | null | |
| managing_agent_phone | varchar | yes | null | |
| managing_agent_fee | decimal(10,2) | yes | null | |
| lease_start_date | date | yes | null | Rental lease |
| lease_end_date | date | yes | null | |
| monthly_council_tax | decimal(10,2) | yes | null | |
| monthly_gas | decimal(10,2) | yes | null | |
| monthly_electricity | decimal(10,2) | yes | null | |
| monthly_water | decimal(10,2) | yes | null | |
| monthly_building_insurance | decimal(10,2) | yes | null | |
| monthly_contents_insurance | decimal(10,2) | yes | null | |
| monthly_service_charge | decimal(10,2) | yes | null | |
| monthly_maintenance_reserve | decimal(10,2) | yes | null | |
| other_monthly_costs | decimal(10,2) | yes | null | |
| notes | text | yes | null | |

**Computed attributes**: `equity` (current_value minus sum of mortgage outstanding balances)

**Model**: `App\Models\Property` -- Uses Auditable, HasJointOwnership traits.

---

#### `mortgages`

Mortgage records linked to a property. Supports mixed rate types (part fixed, part variable) and mixed repayment types (part repayment, part interest-only).

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| property_id | unsignedBigInteger | no | | FK to properties.id |
| user_id | unsignedBigInteger | no | | FK to users.id |
| country | varchar | yes | null | |
| lender_name | varchar | yes | null | |
| mortgage_account_number | varchar | yes | null | |
| mortgage_type | varchar | no | | repayment, interest_only, mixed |
| repayment_percentage | decimal(5,2) | yes | null | For mixed type |
| interest_only_percentage | decimal(5,2) | yes | null | For mixed type |
| original_loan_amount | decimal(12,2) | yes | null | |
| outstanding_balance | decimal(12,2) | yes | null | |
| interest_rate | decimal(6,4) | yes | null | |
| rate_type | varchar | yes | null | fixed, variable, tracker, discount, mixed |
| fixed_rate_percentage | decimal(5,2) | yes | null | For mixed rate |
| variable_rate_percentage | decimal(5,2) | yes | null | For mixed rate |
| fixed_interest_rate | decimal(6,4) | yes | null | |
| variable_interest_rate | decimal(6,4) | yes | null | |
| rate_fix_end_date | date | yes | null | |
| monthly_payment | decimal(10,2) | yes | null | |
| monthly_interest_portion | decimal(10,2) | yes | null | |
| start_date | date | yes | null | |
| maturity_date | date | yes | null | |
| remaining_term_months | integer | yes | null | |
| ownership_type | varchar | yes | null | individual, joint, tenants_in_common |
| ownership_percentage | decimal(5,2) | yes | null | |
| joint_owner_id | unsignedBigInteger | yes | null | FK to users.id |
| joint_owner_name | varchar | yes | null | |
| notes | text | yes | null | |

**Model**: `App\Models\Mortgage` -- Uses Auditable, HasJointOwnership.

---

#### `chattels`

Non-property tangible assets: vehicles, art, antiques, jewellery, collectibles.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| joint_owner_id | unsignedBigInteger | yes | null | FK to users.id |
| joint_owner_name | varchar | yes | null | |
| household_id | unsignedBigInteger | yes | null | FK to households.id |
| trust_id | unsignedBigInteger | yes | null | FK to trusts.id |
| chattel_type | varchar | no | | vehicle, art, antique, jewelry, collectible, other |
| name | varchar | no | | |
| description | text | yes | null | |
| ownership_type | varchar | no | individual | individual, joint, tenants_in_common, trust |
| country | varchar | yes | null | |
| ownership_percentage | decimal(5,2) | yes | null | |
| purchase_price | decimal(12,2) | yes | null | |
| purchase_date | date | yes | null | |
| current_value | decimal(12,2) | yes | null | |
| valuation_date | date | yes | null | |
| make | varchar | yes | null | Vehicle specific |
| model | varchar | yes | null | |
| year | integer | yes | null | |
| registration_number | varchar | yes | null | |
| notes | text | yes | null | |

**Model**: `App\Models\Chattel` -- Uses Auditable, HasJointOwnership.

---

#### `business_interests`

Business ownership records with tax compliance tracking and Business Property Relief (BPR) eligibility.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| joint_owner_id | unsignedBigInteger | yes | null | FK to users.id |
| household_id | unsignedBigInteger | yes | null | FK to households.id |
| trust_id | unsignedBigInteger | yes | null | FK to trusts.id |
| business_name | varchar | no | | |
| company_number | varchar | yes | null | Companies House number |
| business_type | varchar | no | | sole_trader, partnership, limited_company, llp, other |
| ownership_type | varchar | no | individual | individual, joint, tenants_in_common, trust |
| ownership_percentage | decimal(5,2) | yes | null | |
| country | varchar | yes | null | |
| current_valuation | decimal(12,2) | yes | null | |
| valuation_date | date | yes | null | |
| valuation_method | varchar | yes | null | |
| annual_revenue | decimal(12,2) | yes | null | |
| annual_profit | decimal(12,2) | yes | null | |
| annual_dividend_income | decimal(12,2) | yes | null | |
| description | text | yes | null | |
| notes | text | yes | null | |
| vat_registered | boolean | yes | null | |
| vat_number | varchar | yes | null | |
| utr_number | varchar | yes | null | Unique Taxpayer Reference |
| tax_year_end | date | yes | null | |
| employee_count | integer | yes | null | |
| paye_reference | varchar | yes | null | |
| trading_status | varchar | yes | null | trading, dormant, pre_trading |
| acquisition_date | date | yes | null | |
| acquisition_cost | decimal(12,2) | yes | null | |
| bpr_eligible | boolean | yes | null | Business Property Relief |
| industry_sector | varchar | yes | null | |

**Model**: `App\Models\BusinessInterest` -- Uses Auditable, HasJointOwnership.

---

### 1.4 Savings and Investment

#### `savings_accounts`

Cash savings, ISAs, and fixed-term deposits. Account numbers are encrypted at rest using Laravel's Crypt facade.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| account_type | varchar | yes | null | |
| institution | varchar | yes | null | Provider/bank name |
| account_number | text | yes | null | Encrypted via Crypt accessor |
| current_balance | decimal(12,2) | yes | null | |
| interest_rate | decimal(6,4) | yes | null | |
| access_type | varchar | yes | null | easy_access, notice, fixed_term |
| notice_period_days | integer | yes | null | |
| maturity_date | date | yes | null | |
| is_emergency_fund | boolean | yes | false | |
| is_isa | boolean | yes | false | |
| country | varchar | yes | null | |
| isa_type | varchar | yes | null | cash, stocks_and_shares, innovative_finance, lifetime, junior |
| isa_subscription_year | varchar | yes | null | Tax year, e.g. 2025/26 |
| isa_subscription_amount | decimal(12,2) | yes | null | |
| regular_contribution_amount | decimal(10,2) | yes | null | |
| contribution_frequency | varchar | yes | null | monthly, weekly, quarterly |
| planned_lump_sum_amount | decimal(12,2) | yes | null | |
| planned_lump_sum_date | date | yes | null | |
| ownership_type | varchar | yes | null | individual, joint, trust |
| ownership_percentage | decimal(5,2) | yes | null | |
| joint_owner_id | unsignedBigInteger | yes | null | FK to users.id |
| trust_id | unsignedBigInteger | yes | null | FK to trusts.id |
| beneficiary_id | unsignedBigInteger | yes | null | FK to family_members.id (Junior ISA) |
| beneficiary_name | varchar | yes | null | |
| beneficiary_dob | date | yes | null | |
| include_in_retirement | boolean | yes | false | |

**Model**: `App\Models\SavingsAccount` -- Uses Auditable, HasJointOwnership. The `accountNumber` accessor encrypts/decrypts the value transparently.

---

#### `investment_accounts`

The largest table in the schema. A single table handles general investment accounts, ISAs, bonds, private company investments, crowdfunding, and employee share schemes using nullable column groups.

**Core Fields**

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| account_name | varchar | yes | null | |
| joint_owner_id | unsignedBigInteger | yes | null | FK to users.id |
| household_id | unsignedBigInteger | yes | null | FK to households.id |
| trust_id | unsignedBigInteger | yes | null | FK to trusts.id |
| ownership_type | varchar | yes | null | individual, joint, tenants_in_common, trust |
| ownership_percentage | decimal(5,2) | yes | null | |
| account_type | varchar | no | | gia, isa, sipp, bond, private_company, crowdfunding, saye, csop, emi, unapproved_options, rsu |
| account_type_other | varchar | yes | null | |
| country | varchar | yes | null | |
| provider | varchar | yes | null | |
| account_number | varchar | yes | null | |
| platform | varchar | yes | null | |
| current_value | decimal(12,2) | yes | null | |
| contributions_ytd | decimal(12,2) | yes | 0 | |
| monthly_contribution_amount | decimal(10,2) | yes | null | |
| contribution_frequency | varchar | yes | null | |
| planned_lump_sum_amount | decimal(12,2) | yes | null | |
| planned_lump_sum_date | date | yes | null | |
| tax_year | varchar | yes | null | |
| platform_fee_percent | decimal(6,4) | yes | 0 | |
| platform_fee_amount | decimal(10,2) | yes | null | |
| platform_fee_type | varchar | yes | null | percentage, fixed |
| platform_fee_frequency | varchar | yes | null | annual, monthly, quarterly |
| advisor_fee_percent | decimal(6,4) | yes | 0 | |
| isa_type | varchar | yes | null | |
| isa_subscription_current_year | decimal(12,2) | yes | 0 | |
| risk_preference | varchar | yes | null | |
| has_custom_risk | boolean | yes | false | |
| rebalance_threshold_percent | decimal(5,2) | yes | 10.00 | |
| include_in_retirement | boolean | yes | false | |

**Bond Fields** (onshore/offshore bonds)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| bond_purchase_date | date | yes | null | |
| bond_withdrawal_taken | decimal(12,2) | yes | null | |

**BADR Fields** (Business Asset Disposal Relief)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| badr_eligible | boolean | yes | null | |
| badr_is_employee | boolean | yes | null | |
| badr_trading_company | boolean | yes | null | |
| badr_5_percent_holding | boolean | yes | null | |
| badr_held_2_years | boolean | yes | null | |
| badr_emi_shares | boolean | yes | null | |
| badr_lifetime_used | decimal(12,2) | yes | null | |

**Private Investment Fields** (75 columns for private company/crowdfunding)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| company_legal_name | varchar | yes | null | |
| company_registration_number | varchar | yes | null | |
| company_country | varchar | yes | null | |
| company_website | varchar | yes | null | |
| company_trading_name | varchar | yes | null | |
| company_sector | varchar | yes | null | |
| crowdfunding_platform | varchar | yes | null | |
| investment_date | date | yes | null | |
| investment_amount | decimal(12,2) | yes | null | |
| investment_currency | varchar | yes | null | |
| funding_round | varchar | yes | null | |
| pre_money_valuation | decimal(14,2) | yes | null | |
| post_money_valuation | decimal(14,2) | yes | null | |
| price_per_share | decimal(10,4) | yes | null | |
| number_of_shares | integer | yes | null | |
| instrument_type | varchar | yes | null | ordinary_shares, preference_shares, convertible_loan, etc. |
| share_class | varchar | yes | null | |
| has_voting_rights | boolean | yes | null | |
| has_dividend_rights | boolean | yes | null | |
| liquidation_preference | varchar | yes | null | |
| has_anti_dilution | boolean | yes | null | |
| holding_structure | varchar | yes | null | direct, nominee |
| nominee_name | varchar | yes | null | |
| conversion_terms | text | yes | null | |
| interest_rate | decimal(6,4) | yes | null | For convertible loans |
| maturity_date | date | yes | null | |
| tax_relief_type | varchar | yes | null | eis, seis, none |
| eis3_certificate_number | varchar | yes | null | |
| hmrc_reference | varchar | yes | null | |
| relief_claimed_date | date | yes | null | |
| relief_amount_claimed | decimal(12,2) | yes | null | |
| disposal_restriction_date | date | yes | null | 3-year holding period end |
| clawback_risk | boolean | yes | null | |
| clawback_notes | text | yes | null | |
| latest_valuation | decimal(14,2) | yes | null | |
| latest_valuation_date | date | yes | null | |
| current_ownership_percent | decimal(5,2) | yes | null | May change due to dilution |
| company_status | varchar | yes | null | active, dissolved, in_administration |
| status_notes | text | yes | null | |
| exit_type | varchar | yes | null | trade_sale, ipo, buyback, write_off |
| exit_date | date | yes | null | |
| exit_gross_proceeds | decimal(14,2) | yes | null | |
| exit_fees | decimal(12,2) | yes | null | |
| exit_net_proceeds | decimal(14,2) | yes | null | |
| exit_moic | decimal(6,2) | yes | null | Multiple on invested capital |
| loss_relief_eligible | boolean | yes | null | |
| capital_loss_amount | decimal(12,2) | yes | null | |
| negligible_value_claim | boolean | yes | null | |

**Employee Share Scheme Fields** (53 columns across 8 groups)

Group 1 -- Employer Details:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| employer_name | varchar | yes | null | |
| employer_registration | varchar | yes | null | |
| employer_ticker | varchar | yes | null | |
| employer_is_listed | boolean | yes | null | |
| parent_company_name | varchar | yes | null | |
| parent_company_country | varchar | yes | null | |
| ers_scheme_reference | varchar | yes | null | |
| ers_registered | boolean | yes | null | |

Group 2 -- Grant Details:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| grant_date | date | yes | null | |
| grant_reference | varchar | yes | null | |
| units_granted | integer | yes | null | |
| exercise_price | decimal(10,4) | yes | null | |
| market_value_at_grant | decimal(10,4) | yes | null | |
| share_class_scheme | varchar | yes | null | |
| grant_currency | varchar | yes | null | |
| option_price_paid | decimal(10,2) | yes | null | |
| scheme_start_date | date | yes | null | |
| scheme_duration_months | integer | yes | null | |

Group 3 -- Vesting Schedule:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| vesting_type | varchar | yes | null | time, performance, hybrid |
| cliff_date | date | yes | null | |
| cliff_percentage | integer | yes | null | |
| vesting_period_months | integer | yes | null | |
| vesting_frequency_months | integer | yes | null | |
| has_performance_conditions | boolean | yes | null | |
| performance_conditions_description | text | yes | null | |
| performance_period_end | date | yes | null | |
| performance_vesting_min_percent | integer | yes | null | |
| performance_vesting_max_percent | integer | yes | null | |
| full_vest_date | date | yes | null | |
| accelerated_vesting_allowed | boolean | yes | null | |

Group 4 -- Current Status:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| units_vested | integer | yes | null | |
| units_unvested | integer | yes | null | |
| units_exercised | integer | yes | null | |
| units_forfeited | integer | yes | null | |
| units_expired | integer | yes | null | |
| scheme_status | varchar | yes | null | |
| current_share_price | decimal(10,4) | yes | null | |
| share_price_date | date | yes | null | |

Group 5 -- Exercise and Expiry:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| exercise_window_start | date | yes | null | |
| exercise_window_end | date | yes | null | |
| last_exercise_date | date | yes | null | |
| total_exercise_proceeds | decimal(12,2) | yes | null | |
| total_exercise_cost | decimal(12,2) | yes | null | |
| exercise_history_json | json | yes | null | |

Group 6 -- Tax Treatment:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| tax_treatment | varchar | yes | null | |
| is_readily_convertible_asset | boolean | yes | null | |
| paye_via_payroll | boolean | yes | null | |
| income_tax_at_vest_exercise | decimal(12,2) | yes | null | |
| ni_at_vest_exercise | decimal(12,2) | yes | null | |
| csop_disqualifying_event | boolean | yes | null | |
| csop_three_year_date | date | yes | null | |
| cost_basis_for_cgt | decimal(12,2) | yes | null | |

Group 7 -- SAYE Specific:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| saye_monthly_savings | decimal(10,2) | yes | null | |
| saye_current_savings_balance | decimal(12,2) | yes | null | |
| saye_maturity_date | date | yes | null | |
| saye_option_discount_percent | decimal(5,2) | yes | null | |
| saye_bonus_amount | decimal(12,2) | yes | null | |

Group 8 -- Leaver Terms:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| leaver_category | varchar | yes | null | good_leaver, bad_leaver |
| post_termination_exercise_days | integer | yes | null | |
| termination_date | date | yes | null | |
| leaver_notes | text | yes | null | |

**Model**: `App\Models\Investment\InvestmentAccount` -- Uses Auditable, HasJointOwnership. MorphMany relationship to `holdings`.

---

#### `holdings`

Polymorphic table for individual fund/stock holdings within investment accounts or DC pensions.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| holdable_id | unsignedBigInteger | no | | Polymorphic FK |
| holdable_type | varchar | no | | App\Models\Investment\InvestmentAccount or App\Models\DCPension |
| asset_type | varchar | yes | null | equities, bonds, cash, property, alternatives |
| allocation_percent | decimal(5,2) | yes | null | |
| security_name | varchar | yes | null | |
| ticker | varchar | yes | null | |
| isin | varchar | yes | null | International Securities ID |
| quantity | decimal(12,4) | yes | null | |
| purchase_price | decimal(10,4) | yes | null | |
| purchase_date | date | yes | null | |
| current_price | decimal(10,4) | yes | null | |
| current_value | decimal(14,2) | yes | null | |
| cost_basis | decimal(14,2) | yes | null | |
| dividend_yield | decimal(6,4) | yes | null | |
| ocf_percent | decimal(6,4) | yes | null | Ongoing Charges Figure |

**Model**: `App\Models\Investment\Holding` -- Uses Auditable. MorphTo `holdable` relationship.

---

#### `goals`

Financial goals with progress tracking, contribution streaks, and projection data. Supports soft deletes.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| goal_name | varchar | no | | |
| goal_type | varchar | no | | emergency_fund, property_purchase, home_deposit, education, retirement, wealth_accumulation, wedding, holiday, car_purchase, debt_repayment, custom |
| custom_goal_type_name | varchar | yes | null | When goal_type = custom |
| description | text | yes | null | |
| target_amount | decimal(12,2) | no | | |
| current_amount | decimal(12,2) | no | 0 | |
| target_date | date | yes | null | |
| start_date | date | yes | null | |
| assigned_module | varchar | yes | null | savings, investment, property, retirement |
| module_override | boolean | yes | false | |
| priority | varchar | yes | medium | critical, high, medium, low |
| is_essential | boolean | yes | false | |
| status | varchar | no | active | active, paused, completed, abandoned |
| monthly_contribution | decimal(10,2) | yes | null | |
| contribution_frequency | varchar | yes | null | |
| contribution_streak | integer | yes | 0 | Current consecutive contributions |
| longest_streak | integer | yes | 0 | |
| last_contribution_date | date | yes | null | |
| linked_account_ids | json | yes | null | Array of account IDs |
| linked_savings_account_id | unsignedBigInteger | yes | null | FK to savings_accounts.id |
| risk_preference | integer | yes | null | |
| use_global_risk_profile | boolean | yes | true | |
| ownership_type | varchar | yes | individual | individual, joint |
| joint_owner_id | unsignedBigInteger | yes | null | FK to users.id |
| ownership_percentage | decimal(5,2) | yes | null | |
| show_in_projection | boolean | yes | true | |
| show_in_household_view | boolean | yes | true | |
| property_location | varchar | yes | null | Property purchase goals |
| property_type | varchar | yes | null | |
| is_first_time_buyer | boolean | yes | null | |
| estimated_property_price | decimal(12,2) | yes | null | |
| deposit_percentage | decimal(5,2) | yes | null | |
| stamp_duty_estimate | decimal(12,2) | yes | null | |
| additional_costs_estimate | decimal(12,2) | yes | null | |
| milestones | json | yes | null | |
| projection_data | json | yes | null | |
| completed_at | timestamp | yes | null | |
| completion_notes | text | yes | null | |
| deleted_at | timestamp | yes | null | Soft delete |

**Computed attributes**: `progress_percentage`, `days_remaining`, `months_remaining`, `is_on_track`, `display_goal_type`

**Model**: `App\Models\Goal` -- Uses Auditable, HasJointOwnership, SoftDeletes.

---

#### `goal_contributions`

Individual contributions against goals with streak tracking.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| goal_id | unsignedBigInteger | no | | FK to goals.id |
| user_id | unsignedBigInteger | no | | FK to users.id |
| amount | decimal(12,2) | no | | |
| contribution_date | date | no | | |
| contribution_type | varchar | no | | manual, automatic, lump_sum, interest, adjustment |
| notes | text | yes | null | |
| goal_balance_after | decimal(12,2) | yes | null | Running balance |
| streak_qualifying | boolean | yes | true | Counts toward streak |

**Model**: `App\Models\GoalContribution`

---

#### `life_events`

Future events that will impact a user's financial position. Unlike goals (which you save towards), life events are things that happen to you. Supports soft deletes.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| event_name | varchar | no | | |
| event_type | varchar | no | | Income: inheritance, gift_received, bonus, redundancy_payment, property_sale, business_sale, pension_lump_sum, lottery_windfall, custom_income. Expense: large_purchase, home_improvement, wedding, education_fees, gift_given, medical_expense, custom_expense |
| description | text | yes | null | |
| amount | decimal(12,2) | no | | Always positive; impact_type determines sign |
| impact_type | varchar | no | | income, expense |
| expected_date | date | yes | null | |
| certainty | varchar | yes | null | confirmed, likely, possible, speculative |
| icon | varchar | yes | null | |
| show_in_projection | boolean | yes | true | |
| show_in_household_view | boolean | yes | true | |
| ownership_type | varchar | yes | individual | individual, joint |
| joint_owner_id | unsignedBigInteger | yes | null | FK to users.id |
| ownership_percentage | decimal(5,2) | yes | null | |
| status | varchar | yes | expected | expected, confirmed, completed, cancelled |
| occurred_at | timestamp | yes | null | |
| deleted_at | timestamp | yes | null | Soft delete |

**Computed attributes**: `signed_amount`, `display_event_type`, `years_until_event`

**Model**: `App\Models\LifeEvent` -- Uses Auditable, HasJointOwnership, SoftDeletes.

---

### 1.5 Pensions

#### `dc_pensions`

Defined Contribution pensions: workplace, SIPP, and personal pensions.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| scheme_name | varchar | no | | |
| scheme_type | varchar | no | | workplace, sipp, personal |
| provider | varchar | yes | null | |
| pension_type | varchar | yes | null | |
| member_number | varchar | yes | null | |
| current_fund_value | decimal(12,2) | yes | null | |
| annual_salary | decimal(12,2) | yes | null | |
| employee_contribution_percent | decimal(5,2) | yes | null | |
| employer_contribution_percent | decimal(5,2) | yes | null | |
| employer_matching_limit | decimal(5,2) | yes | null | |
| monthly_contribution_amount | decimal(10,2) | yes | null | |
| lump_sum_contribution | decimal(12,2) | yes | null | |
| investment_strategy | varchar | yes | null | |
| platform_fee_percent | decimal(6,4) | yes | null | |
| retirement_age | integer | yes | null | |
| expected_return_percent | decimal(5,2) | yes | null | |
| projected_value_at_retirement | decimal(14,2) | yes | null | |
| risk_preference | varchar | yes | null | |
| has_custom_risk | boolean | yes | false | |
| beneficiary_id | unsignedBigInteger | yes | null | FK to users.id |
| beneficiary_name | varchar | yes | null | |

**Model**: `App\Models\DCPension` -- Uses Auditable. MorphMany relationship to `holdings`.

---

#### `db_pensions`

Defined Benefit (final salary / career average) pensions. Captured for projection only; no DB-to-DC transfer advice is provided.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| scheme_name | varchar | no | | |
| scheme_type | varchar | yes | null | final_salary, career_average, public_sector |
| accrued_annual_pension | decimal(12,2) | yes | null | Current annual entitlement |
| pensionable_service_years | decimal(5,2) | yes | null | |
| pensionable_salary | decimal(12,2) | yes | null | |
| normal_retirement_age | integer | yes | null | |
| revaluation_method | varchar | yes | null | cpi, rpi, fixed, none |
| spouse_pension_percent | decimal(5,2) | yes | null | Percentage paid to surviving spouse |
| lump_sum_entitlement | decimal(12,2) | yes | null | |
| inflation_protection | varchar | yes | null | |

**Model**: `App\Models\DBPension` -- Uses Auditable.

---

#### `state_pensions`

UK State Pension forecast and National Insurance contribution tracking. One record per user.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id, unique |
| ni_years_completed | integer | yes | null | |
| ni_years_required | integer | yes | null | Usually 35 |
| state_pension_forecast_annual | decimal(10,2) | yes | null | |
| state_pension_age | integer | yes | null | |
| already_receiving | boolean | yes | false | |
| ni_gaps | json | yes | null | Array of gap years |
| gap_fill_cost | decimal(10,2) | yes | null | Cost to fill NI gaps |

**Model**: `App\Models\StatePension` -- Uses Auditable. HasOne from User.

---

#### `retirement_profiles`

Retirement income planning. One record per user.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id, unique |
| current_age | integer | yes | null | |
| target_retirement_age | integer | yes | null | |
| current_annual_salary | decimal(12,2) | yes | null | |
| target_retirement_income | decimal(12,2) | yes | null | |
| essential_expenditure | decimal(12,2) | yes | null | |
| lifestyle_expenditure | decimal(12,2) | yes | null | |
| life_expectancy | integer | yes | null | |
| spouse_life_expectancy | integer | yes | null | |
| risk_tolerance | varchar | yes | null | DEPRECATED: use RiskProfile model instead |

**Model**: `App\Models\RetirementProfile`

---

### 1.6 Profiles and Configuration

#### `risk_profiles`

Investment risk assessment results. Supports both assessed and self-selected profiles.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| risk_tolerance | varchar | yes | null | |
| risk_level | varchar | yes | null | cautious, balanced, adventurous (nullable for self-select) |
| capacity_for_loss_percent | decimal(5,2) | yes | null | |
| time_horizon_years | integer | yes | null | |
| knowledge_level | varchar | yes | null | |
| attitude_to_volatility | varchar | yes | null | |
| esg_preference | boolean | yes | null | |
| risk_assessed_at | timestamp | yes | null | |
| is_self_assessed | boolean | yes | null | |
| factor_breakdown | json | yes | null | Detailed factor scores |

**Model**: `App\Models\Investment\RiskProfile`

---

#### `protection_profiles`

Insurance needs assessment data. One record per user.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| annual_income | decimal(12,2) | yes | null | |
| monthly_expenditure | decimal(12,2) | yes | null | |
| mortgage_balance | decimal(12,2) | yes | null | |
| other_debts | decimal(12,2) | yes | null | |
| number_of_dependents | integer | yes | null | |
| dependents_ages | json | yes | null | Array of ages |
| retirement_age | integer | yes | null | |
| occupation | varchar | yes | null | |
| smoker_status | boolean | yes | null | |
| health_status | varchar | yes | null | |
| has_no_policies | boolean | yes | null | Explicit "I have no protection" |

**Model**: `App\Models\ProtectionProfile`

---

#### `expenditure_profiles`

Categorised monthly expenditure summary. One record per user.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| monthly_housing | decimal(10,2) | yes | null | |
| monthly_utilities | decimal(10,2) | yes | null | |
| monthly_food | decimal(10,2) | yes | null | |
| monthly_transport | decimal(10,2) | yes | null | |
| monthly_insurance | decimal(10,2) | yes | null | |
| monthly_loans | decimal(10,2) | yes | null | |
| monthly_discretionary | decimal(10,2) | yes | null | |
| total_monthly_expenditure | decimal(10,2) | yes | null | |

**Model**: `App\Models\ExpenditureProfile`

---

#### `iht_profiles`

Inheritance Tax planning profile. Tracks NRB transfers from deceased spouse.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| marital_status | varchar | yes | null | |
| has_spouse | boolean | yes | null | |
| own_home | boolean | yes | null | |
| home_value | decimal(12,2) | yes | null | |
| nrb_transferred_from_spouse | decimal(12,2) | yes | null | Nil Rate Band transferred |
| rnrb_transferred_from_spouse | decimal(12,2) | yes | null | Residence Nil Rate Band transferred |
| charitable_giving_percent | decimal(5,2) | yes | null | For 36% rate reduction |

**Model**: `App\Models\Estate\IHTProfile`

---

#### `user_assumptions`

User overrides for planning assumptions used in pension and investment projections.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| assumption_type | varchar | no | | pensions, investments, estate_planning |
| inflation_rate | decimal(5,2) | yes | null | |
| return_rate | decimal(5,2) | yes | null | |
| compound_periods | integer | yes | null | |

**Unique constraint**: `[user_id, assumption_type]`

**Model**: `App\Models\UserAssumption`

---

#### `monte_carlo_cache`

Cached results from Monte Carlo retirement simulations.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| cache_key | varchar | no | | Unique |
| results | longText | no | | Serialised simulation results |
| calculated_at | timestamp | no | | |
| expires_at | timestamp | no | | |

---

#### `occupation_codes`

ONS Standard Occupational Classification (SOC) 2020 lookup table. Seeded with national occupation data.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| soc_code | varchar(4) | no | | 4-digit SOC code |
| title | varchar | no | | Job title. Full-text indexed |
| unit_group | varchar | yes | null | SOC unit group description |
| minor_group | varchar | yes | null | 3-digit group |
| sub_major_group | varchar | yes | null | 2-digit group |
| major_group | varchar | yes | null | 1-digit group |
| is_primary | boolean | no | false | Primary title for this SOC code |

**Full-text index**: on `title` column for autocomplete search.

**Model**: `App\Models\OccupationCode`

---

#### `family_members`

Dependents and family members associated with a user. Used for Junior ISA beneficiaries, child benefit tracking, and estate planning.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| household_id | unsignedBigInteger | yes | null | FK to households.id |
| relationship | varchar | yes | null | child, stepchild, parent, sibling, other |
| name | varchar | yes | null | Legacy full name |
| first_name | varchar | yes | null | |
| middle_name | varchar | yes | null | |
| last_name | varchar | yes | null | |
| date_of_birth | date | yes | null | |
| gender | varchar | yes | null | |
| national_insurance_number | varchar | yes | null | |
| annual_income | decimal(12,2) | yes | null | |
| is_dependent | boolean | yes | null | |
| education_status | varchar | yes | null | |
| receives_child_benefit | boolean | yes | false | |
| notes | text | yes | null | |

**Model**: `App\Models\FamilyMember` -- Uses Auditable.

---

#### `households`

Groups users and their dependents into a household for joint planning views.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| household_name | varchar | yes | null | |
| notes | text | yes | null | |

**Model**: `App\Models\Household` -- HasMany users, familyMembers, properties, businessInterests, chattels, cashAccounts, investmentAccounts, trusts.

---

### 1.7 Estate Planning

#### `trusts`

Trust structures for IHT planning. Supports 10 trust types with specific fields for discounted gift trusts and loan trusts.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| household_id | unsignedBigInteger | yes | null | FK to households.id |
| trust_name | varchar | no | | |
| trust_type | varchar | no | | bare, interest_in_possession, discretionary, accumulation_maintenance, life_insurance, discounted_gift, loan, mixed, settlor_interested, other |
| other_type_description | varchar | yes | null | When trust_type = other |
| country | varchar | yes | null | |
| trust_creation_date | date | yes | null | |
| initial_value | decimal(12,2) | yes | null | |
| current_value | decimal(12,2) | yes | null | |
| discount_amount | decimal(12,2) | yes | null | Discounted gift trusts |
| retained_income_annual | decimal(12,2) | yes | null | |
| loan_amount | decimal(12,2) | yes | null | Loan trusts |
| loan_interest_bearing | boolean | yes | null | |
| loan_interest_rate | decimal(6,4) | yes | null | |
| sum_assured | decimal(12,2) | yes | null | Life insurance trusts |
| annual_premium | decimal(12,2) | yes | null | |
| is_relevant_property_trust | boolean | yes | false | Subject to 10-year charges |
| last_periodic_charge_date | date | yes | null | |
| last_periodic_charge_amount | decimal(12,2) | yes | null | |
| last_valuation_date | date | yes | null | |
| next_tax_return_due | date | yes | null | |
| total_asset_value | decimal(14,2) | yes | null | |
| beneficiaries | text | yes | null | |
| trustees | text | yes | null | |
| settlor | text | yes | null | |
| purpose | text | yes | null | |
| notes | text | yes | null | |
| is_active | boolean | yes | true | |

**Model**: `App\Models\Estate\Trust` -- Includes `getIHTValue()` method that returns the IHT-relevant value based on trust type.

---

#### `wills`

Will status and executor details. One record per user.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| has_will | boolean | yes | null | |
| spouse_primary_beneficiary | boolean | yes | null | |
| spouse_bequest_percentage | decimal(5,2) | yes | null | |
| executor_name | varchar | yes | null | |
| executor_notes | text | yes | null | |
| will_last_updated | date | yes | null | |

**Model**: `App\Models\Estate\Will` -- HasMany bequests.

---

#### `bequests`

Individual bequests within a will. Supports percentage-of-estate, specific amounts, and specific assets.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| will_id | unsignedBigInteger | no | | FK to wills.id |
| user_id | unsignedBigInteger | no | | FK to users.id |
| beneficiary_name | varchar | no | | |
| beneficiary_user_id | unsignedBigInteger | yes | null | FK to users.id if system user |
| beneficiary_type | varchar | no | | individual, charity, trust, organization |
| charity_registration_number | varchar | yes | null | |
| bequest_type | varchar | no | | percentage, specific_amount, specific_asset, residuary |
| percentage_of_estate | decimal(5,2) | yes | null | |
| specific_amount | decimal(12,2) | yes | null | |
| specific_asset_description | text | yes | null | |
| asset_id | unsignedBigInteger | yes | null | |
| priority_order | integer | yes | null | |
| conditions | text | yes | null | |
| notes | text | yes | null | |

**Model**: `App\Models\Estate\Bequest` -- Includes `isCharitable()` method that checks beneficiary_type, registration number, and name keywords.

---

#### `letters_to_spouse`

Structured letter covering immediate actions, account access, long-term plans, and funeral wishes.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| immediate_actions | text | yes | null | |
| executor_name | varchar | yes | null | |
| executor_contact | varchar | yes | null | |
| attorney_name | varchar | yes | null | |
| attorney_contact | varchar | yes | null | |
| financial_advisor_name | varchar | yes | null | |
| financial_advisor_contact | varchar | yes | null | |
| accountant_name | varchar | yes | null | |
| accountant_contact | varchar | yes | null | |
| immediate_funds_access | text | yes | null | |
| employer_hr_contact | varchar | yes | null | |
| employer_benefits_info | text | yes | null | |
| password_manager_info | text | yes | null | |
| phone_plan_info | text | yes | null | |
| bank_accounts_info | text | yes | null | |
| investment_accounts_info | text | yes | null | |
| insurance_policies_info | text | yes | null | |
| real_estate_info | text | yes | null | |
| vehicles_info | text | yes | null | |
| valuable_items_info | text | yes | null | |
| cryptocurrency_info | text | yes | null | |
| liabilities_info | text | yes | null | |
| recurring_bills_info | text | yes | null | |
| estate_documents_location | text | yes | null | |
| beneficiary_info | text | yes | null | |
| children_education_plans | text | yes | null | |
| financial_guidance | text | yes | null | |
| social_security_info | text | yes | null | |
| funeral_preference | varchar | yes | null | |
| funeral_service_details | text | yes | null | |
| obituary_wishes | text | yes | null | |
| additional_wishes | text | yes | null | |
| additional_boxes | json | yes | null | User-created sections |

**Model**: `App\Models\LetterToSpouse`

---

#### `gifts`

Gifts made during lifetime, tracked for IHT Potentially Exempt Transfer (PET) and Chargeable Lifetime Transfer (CLT) calculations.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| gift_date | date | yes | null | |
| recipient | varchar | yes | null | |
| gift_type | varchar | yes | null | pet, clt, exempt, small_gift, annual_exemption |
| gift_value | decimal(12,2) | yes | null | |
| status | varchar | yes | null | |
| taper_relief_applicable | boolean | yes | null | Gifts 3-7 years before death |
| notes | text | yes | null | |

**Model**: `App\Models\Estate\Gift`

---

#### `liabilities`

Non-mortgage debts: personal loans, credit cards, student loans, and other obligations.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| ownership_type | varchar | yes | null | individual, joint |
| joint_owner_id | unsignedBigInteger | yes | null | FK to users.id |
| trust_id | unsignedBigInteger | yes | null | FK to trusts.id |
| liability_type | varchar | no | | mortgage, personal_loan, credit_card, student_loan, other |
| country | varchar | yes | null | |
| liability_name | varchar | yes | null | |
| current_balance | decimal(12,2) | yes | null | |
| monthly_payment | decimal(10,2) | yes | null | |
| interest_rate | decimal(6,4) | yes | null | |
| maturity_date | date | yes | null | |
| secured_against | varchar | yes | null | |
| is_priority_debt | boolean | yes | false | |
| mortgage_type | varchar | yes | null | For mortgage-type liabilities |
| fixed_until | date | yes | null | |
| notes | text | yes | null | |

**Model**: `App\Models\Estate\Liability` -- Uses HasJointOwnership.

---

### 1.8 Insurance

#### `life_insurance_policies`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| policy_type | varchar | yes | null | term, whole_of_life, decreasing_term, family_income_benefit, level_term |
| provider | varchar | yes | null | |
| policy_number | varchar | yes | null | |
| sum_assured | decimal(12,2) | yes | null | |
| start_value | decimal(12,2) | yes | null | Initial cover amount |
| decreasing_rate | decimal(6,4) | yes | null | Annual decrease for decreasing term |
| premium_amount | decimal(10,2) | yes | null | |
| premium_frequency | varchar | yes | null | monthly, annual |
| policy_start_date | date | yes | null | |
| policy_end_date | date | yes | null | |
| policy_term_years | integer | yes | null | |
| indexation_rate | decimal(6,4) | yes | null | |
| in_trust | boolean | yes | false | If true, outside estate for IHT |
| is_mortgage_protection | boolean | yes | false | |
| beneficiaries | text | yes | null | |

**Model**: `App\Models\LifeInsurancePolicy` -- Uses Auditable.

---

#### `critical_illness_policies`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| policy_type | varchar | yes | null | |
| provider | varchar | yes | null | |
| policy_number | varchar | yes | null | |
| sum_assured | decimal(12,2) | yes | null | |
| premium_amount | decimal(10,2) | yes | null | |
| premium_frequency | varchar | yes | null | |
| policy_start_date | date | yes | null | |
| policy_term_years | integer | yes | null | |
| conditions_covered | json | yes | null | Array of covered conditions |

**Model**: `App\Models\CriticalIllnessPolicy` -- Uses Auditable.

---

#### `income_protection_policies`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| provider | varchar | yes | null | |
| policy_number | varchar | yes | null | |
| benefit_amount | decimal(10,2) | yes | null | Monthly benefit |
| benefit_frequency | varchar | yes | null | |
| deferred_period_weeks | integer | yes | null | Waiting period before claim |
| benefit_period_months | integer | yes | null | |
| premium_amount | decimal(10,2) | yes | null | |
| occupation_class | varchar | yes | null | |
| policy_start_date | date | yes | null | |

**Model**: `App\Models\IncomeProtectionPolicy` -- Uses Auditable.

---

#### `disability_policies`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| provider | varchar | yes | null | |
| policy_number | varchar | yes | null | |
| benefit_amount | decimal(10,2) | yes | null | |
| benefit_frequency | varchar | yes | null | |
| deferred_period_weeks | integer | yes | null | |
| benefit_period_months | integer | yes | null | |
| premium_amount | decimal(10,2) | yes | null | |
| premium_frequency | varchar | yes | null | |
| occupation_class | varchar | yes | null | |
| policy_start_date | date | yes | null | |
| policy_term_years | integer | yes | null | |
| coverage_type | varchar | yes | null | |

**Model**: `App\Models\DisabilityPolicy`

---

#### `sickness_illness_policies`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | unsignedBigInteger | no | | FK to users.id |
| provider | varchar | yes | null | |
| policy_number | varchar | yes | null | |
| benefit_amount | decimal(10,2) | yes | null | |
| benefit_frequency | varchar | yes | null | |
| deferred_period_weeks | integer | yes | null | |
| benefit_period_months | integer | yes | null | |
| premium_amount | decimal(10,2) | yes | null | |
| premium_frequency | varchar | yes | null | |
| policy_start_date | date | yes | null | |
| policy_term_years | integer | yes | null | |
| conditions_covered | json | yes | null | |
| exclusions | text | yes | null | |

**Model**: `App\Models\SicknessIllnessPolicy`

---

## 2. Entity Relationship Map

```
                                    +-------------+
                                    |  households  |
                                    +------+------+
                                           |
                         +-----------------+------------------+
                         |                 |                  |
                    HasMany           HasMany            HasMany
                         |                 |                  |
                  +------+------+   +------+------+    +-----+------+
                  |    users    |   |family_members|    | properties |
                  +------+------+   +--------------+    +-----+------+
                         |                                    |
         +-------+-------+-------+-------+              HasMany
         |       |       |       |       |                    |
      HasOne  HasOne  HasOne  HasOne  HasMany          +------+------+
         |       |       |       |       |             |  mortgages  |
         v       v       v       v       v             +-------------+
    +--------+ +----+ +-----+ +-----+ +--------+
    |state_  | |ret-| |prot-| |exp- | |  life  |
    |pensions| |ire-| |ecti-| |endi-| |insurance|
    +--------+ |ment| |on_  | |ture_| |policies|
               |pro-| |prof-| |prof-| +--------+
               |file| |iles | |iles |
               +----+ +-----+ +-----+
                         |
                    User HasMany
         +-------+--+------+-------+-------+-------+
         |       |         |       |       |       |
         v       v         v       v       v       v
    +--------+ +--------+ +----+ +-----+ +-----+ +--------+
    |savings_| |invest- | |dc_ | |db_  | |goals| |life_   |
    |accounts| |ment_   | |pen-| |pen- | |     | |events  |
    |        | |accounts| |sion| |sions| |     | |        |
    +--------+ +---+----+ +-+--+ +-----+ +--+--+ +--------+
                   |         |               |
              MorphMany  MorphMany       HasMany
                   |         |               |
                   v         v               v
              +----------+            +-----------+
              | holdings |            |   goal_   |
              +----------+            |contribut- |
                                      |   ions    |
                                      +-----------+

    User HasMany (continued)
    +-------+-------+-------+-------+-------+
    |       |       |       |       |       |
    v       v       v       v       v       v
  +------+ +------+ +------+ +------+ +------+
  |chatt-| |busi- | |liabi-| |trusts| |gifts |
  |els   | |ness_ | |lities|        |      |
  |      | |inter-| |      |        |      |
  +------+ |ests  | +------+        +------+
           +------+

    User HasMany (security)
    +--------+--------+--------+--------+--------+
    |        |        |        |        |        |
    v        v        v        v        v        v
  +------+ +------+ +------+ +------+ +------+ +------+
  |audit | |user_ | |user_ | |data_ | |eras- | |email_|
  |logs  | |sess- | |cons- | |expor-| |ure_  | |verif-|
  |      | |ions  | |ents  | |ts    | |reque-| |icati-|
  +------+ +------+ +------+ +------+ |sts   | |on_   |
                                       +------+ |codes |
                                                 +------+

    Roles and Permissions
    +-------+     +---------------+     +-------------+
    | roles |<--->| role_permission|<--->| permissions |
    +---+---+     +---------------+     +-------------+
        |
     HasMany
        |
        v
    +-------+
    | users |
    +-------+

    Wills and Bequests
    +-------+     +-----------+
    | wills |---->| bequests  |
    +-------+  HM +-----------+

    Holdings (Polymorphic)
    +--------------------+     +----------+
    | investment_accounts|---->| holdings |
    +--------------------+  MM +-----+----+
    +--------------------+       ^
    |    dc_pensions     |-------+
    +--------------------+  MM
```

Key:
- HM = HasMany
- MM = MorphMany
- Arrows point from parent to child
- `users` self-references via `spouse_id`

---

## 3. Joint Ownership Pattern

Fynla uses a **single-record pattern** for jointly owned assets. One database row represents the entire asset. The `user_id` identifies the primary owner, `joint_owner_id` identifies the co-owner, and `ownership_percentage` stores the primary owner's share. The co-owner's share is always `100 - ownership_percentage`.

### Tables That Support Joint Ownership

All of these tables include `joint_owner_id`, `ownership_type`, and `ownership_percentage` columns, and their models use the `HasJointOwnership` trait:

- `properties`
- `mortgages`
- `savings_accounts`
- `investment_accounts`
- `chattels`
- `business_interests`
- `liabilities`
- `goals`
- `life_events`

### Creating a Joint Record

```php
Property::create([
    'user_id'              => $user->id,
    'joint_owner_id'       => $spouse->id,
    'ownership_type'       => 'joint',          // or 'tenants_in_common'
    'ownership_percentage' => 70,               // Primary owner gets 70%
    'current_value'        => 500000,
]);
// The spouse's share is implicitly 30% (100 - 70).
```

### Calculating Each Owner's Share

```php
$userIsOwner = $property->user_id === $currentUser->id;

$ownershipMultiplier = $userIsOwner
    ? ($property->ownership_percentage ?? 50) / 100
    : (100 - ($property->ownership_percentage ?? 50)) / 100;

$usersShareValue = $property->current_value * $ownershipMultiplier;
```

When `ownership_percentage` is null, the system defaults to 50/50.

### Querying for a User's Assets (Including Joint)

The `HasJointOwnership` trait provides three scopes:

```php
// All assets where user is primary OR joint owner
Property::forUserOrJoint($userId)->get();

// Only assets where user is the primary owner
Property::forUser($userId)->get();

// Only assets where user is the joint owner
Property::forJointOwner($userId)->get();
```

The trait also provides helper methods:

```php
$property->isOwnedBy($userId);   // true if user_id or joint_owner_id matches
$property->hasJointOwner();       // true if joint_owner_id is not null
```

### Important: No Duplicate Records

A jointly owned property with a value of 500,000 exists as a single row with `current_value = 500000`. The ownership percentage determines each person's share. The system never creates two rows for the same asset.

---

## 4. Key Indexes

### Joint Owner Indexes

A dedicated migration (`2026_01_26_150000_add_joint_owner_indexes`) adds `joint_owner_id` indexes to all major asset tables. Without these, the `OR joint_owner_id = ?` clause in joint ownership queries would cause full table scans.

| Table | Index Name | Columns |
|-------|-----------|---------|
| properties | properties_joint_owner_id_index | joint_owner_id |
| savings_accounts | savings_accounts_joint_owner_id_index | joint_owner_id |
| investment_accounts | investment_accounts_joint_owner_id_index | joint_owner_id |
| mortgages | mortgages_joint_owner_id_index | joint_owner_id |
| chattels | chattels_joint_owner_id_index | joint_owner_id |
| business_interests | business_interests_joint_owner_id_index | joint_owner_id |
| liabilities | liabilities_joint_owner_id_index | joint_owner_id |

### Composite Indexes

| Table | Index Name | Columns | Purpose |
|-------|-----------|---------|---------|
| goals | goals_joint_owner_id_status_index | [joint_owner_id, status] | Filtering active goals for joint owners |
| user_consents | user_consents_unique | [user_id, consent_type, version] | Unique constraint, prevents duplicate consent records |
| user_assumptions | user_assumptions_unique | [user_id, assumption_type] | One assumption set per type per user |

### Security and Audit Indexes

| Table | Index Name | Columns | Purpose |
|-------|-----------|---------|---------|
| login_attempts | login_attempts_email_created_at_index | [email, created_at] | Rate limiting by email |
| login_attempts | login_attempts_ip_address_created_at_index | [ip_address, created_at] | Rate limiting by IP |
| user_sessions | user_sessions_user_id_created_at_index | [user_id, created_at] | Session listing per user |

### Full-Text Indexes

| Table | Index Name | Columns | Purpose |
|-------|-----------|---------|---------|
| occupation_codes | occupation_codes_title_fulltext | title | Autocomplete occupation search |

### Standard Foreign Key Indexes

Every `user_id` column on every table is indexed via the foreign key constraint. Similarly, `property_id` on mortgages, `goal_id` on goal_contributions, `will_id` on bequests, and all other FK columns carry implicit indexes from the foreign key definition.

### Polymorphic Indexes

The `holdings` table uses `holdable_id` and `holdable_type` for its polymorphic relationship to `investment_accounts` and `dc_pensions`. Laravel creates a composite index on `[holdable_type, holdable_id]` for these columns.
