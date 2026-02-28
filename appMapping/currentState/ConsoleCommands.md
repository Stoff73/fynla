# Console Commands & Scheduled Tasks - Complete System Map

## 1. System Overview

Fynla has **8 custom Artisan commands** and **2 scheduled tasks** that handle trial lifecycle management, data migration between legacy and current table structures, security encryption, audit log maintenance, and preview user data resets.

All custom commands are located in `app/Console/Commands/` and auto-discovered via `$this->load(__DIR__.'/Commands')` in the Kernel. The `routes/console.php` file contains only the default Laravel `inspire` command.

| Category             | Commands | Scheduled |
|----------------------|----------|-----------|
| Trial Management     | 2        | 2         |
| Data Migration       | 3        | 0         |
| Security/Maintenance | 3        | 0         |

---

## 2. Scheduled Tasks

**File:** `app/Console/Kernel.php`

The scheduler registers two daily commands. Both relate to trial subscription management.

| Command                  | Frequency       | Time  | Purpose                                           |
|--------------------------|-----------------|-------|---------------------------------------------------|
| `trials:send-reminders`  | Daily           | 09:00 | Send reminder emails at 3, 2, and 1 days before trial expiry |
| `trials:expire`          | Daily           | 00:05 | Expire subscriptions past their `trial_ends_at` date |

**Timezone:** Server default (no explicit timezone override in Kernel).

**Important:** For the scheduler to work, the server must have a cron entry running `php artisan schedule:run` every minute:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

No other conditions, guards, or environment checks are applied to either scheduled task.

---

## 3. Trial Management Commands

### 3.1 ExpireTrials

**File:** `app/Console/Commands/ExpireTrials.php`

| Property    | Value |
|-------------|-------|
| Signature   | `trials:expire` |
| Description | Expire trials that have passed their trial_ends_at date |
| Options     | None |
| Scheduled   | Daily at 00:05 |

**What it does, step by step:**

1. Resolves `App\Services\Payment\TrialService` via dependency injection.
2. Calls `TrialService::expireTrials()`.
3. Inside `expireTrials()` (in `app/Services/Payment/TrialService.php`):
   - Queries `subscriptions` table for records where `status = 'trialing'` AND `trial_ends_at < now()`.
   - For each matching subscription:
     - Updates `subscription.status` to `'expired'`.
     - Updates the associated `user.plan` to `'free'`.
4. Returns the count of expired subscriptions.
5. Outputs: `"Expired {count} trial(s)."`.

**Database changes:**
- `subscriptions.status`: `'trialing'` -> `'expired'`
- `users.plan`: current value -> `'free'`

**Logging:** No explicit logging beyond console output.

---

### 3.2 SendTrialReminderEmails

**File:** `app/Console/Commands/SendTrialReminderEmails.php`

| Property    | Value |
|-------------|-------|
| Signature   | `trials:send-reminders` |
| Description | Send trial expiration reminder emails at 3, 2, and 1 days before expiry |
| Options     | None |
| Scheduled   | Daily at 09:00 |

**What it does, step by step:**

1. Iterates over reminder intervals: **3 days**, **2 days**, and **1 day** before expiry.
2. For each interval, calculates the target date (`now + N days`, start of day).
3. Queries `subscriptions` where `status = 'trialing'` AND `trial_ends_at` matches the target date (date only, not time). Eager loads the `user` relationship.
4. For each matching subscription:
   - Skips if the user relationship is null.
   - Checks the `trial_reminder_log` table to see if a reminder for this user and day-count was already sent. Skips if already sent (idempotency guard).
   - Sends a `TrialExpirationReminder` mailable (`app/Mail/TrialExpirationReminder.php`) to the user's email, passing the user and days remaining.
   - On success, inserts a record into `trial_reminder_log` with `user_id`, `days_remaining`, and `sent_at`.
   - On failure, logs an error via `Log::error()` with user ID, days remaining, and error message. Does not re-throw.
5. Outputs: `"Sent {count} trial reminder email(s)."`.

**Database reads:** `subscriptions`, `users`, `trial_reminder_log`
**Database writes:** `trial_reminder_log` (insert on successful send)
**External:** Sends email via configured mail driver.

---

## 4. Data Migration Commands

These are one-time migration commands for moving data from legacy table structures to the current schema. They are **not scheduled** and are intended to be run manually.

### 4.1 MigrateEstateToNetWorth

**File:** `app/Console/Commands/MigrateEstateToNetWorth.php`

| Property    | Value |
|-------------|-------|
| Signature   | `migrate:estate-to-networth {--dry-run}` |
| Description | Migrate legacy estate assets to new NetWorth module tables (properties, business_interests, chattels) |
| Options     | `--dry-run` - Run migration without committing changes |

**What it does, step by step:**

1. Checks if the legacy `assets` table exists. Fails immediately if not found.
2. Counts total records in `assets` table. Exits with success if zero.
3. In non-dry-run mode, prompts for user confirmation before proceeding.
4. Wraps the entire migration in a database transaction.
5. Iterates over all records in `assets` with a progress bar, routing by `asset_type`:

| `asset_type`  | Target Table         | Target Model          | Action         |
|---------------|----------------------|-----------------------|----------------|
| `property`    | `properties`         | `App\Models\Property` | Migrated       |
| `business`    | `business_interests` | `App\Models\BusinessInterest` | Migrated |
| `other` / default | `chattels`       | `App\Models\Chattel`  | Migrated       |
| `pension`     | (none)               | (none)                | Skipped        |
| `investment`  | (none)               | (none)                | Skipped        |

6. **Property mapping:** Sets `property_type` to `'residential'` (default), uses `asset_name` as `address_line_1`, sets `purchase_price` equal to `current_valuation`, maps `ownership_type` (`individual` stays, everything else becomes `joint`), sets `ownership_percentage` to 100 for individual or 50 for joint. Carries over `is_iht_exempt`, `exemption_reason`, and timestamps.

7. **Business mapping:** Sets `business_type` to `'other'` (default), `is_trading` to `true`, same ownership mapping as properties.

8. **Chattel mapping:** Sets `category` to `'other'` (default), `is_set` to `false`, `acquisition_cost` equals `current_valuation`.

9. In dry-run mode, rolls back the transaction. Otherwise commits.
10. Displays a statistics table showing counts for each category and errors.

**Safety checks:**
- Table existence check before starting
- Interactive confirmation prompt (non-dry-run)
- Full database transaction with rollback on error
- Dry-run mode rolls back all changes
- Per-record error handling (continues on individual failures)

---

### 4.2 MigrateSavingsToCash

**File:** `app/Console/Commands/MigrateSavingsToCash.php`

| Property    | Value |
|-------------|-------|
| Signature   | `migrate:savings-to-cash {--dry-run}` |
| Description | Migrate legacy savings_accounts to new cash_accounts table |
| Options     | `--dry-run` - Run migration without committing changes |

**What it does, step by step:**

1. Checks if the legacy `savings_accounts` table exists. Fails immediately if not found.
2. Counts total records. Exits with success if zero.
3. In non-dry-run mode, prompts for user confirmation.
4. Wraps migration in a database transaction.
5. Iterates over all `savings_accounts` records with a progress bar.
6. For each record, creates a new `CashAccount` (`cash_accounts` table) with mapped fields:

**Account type mapping** (based on `is_isa` and `account_type`):

| Source Condition           | Target `account_type` |
|----------------------------|-----------------------|
| `is_isa = true`            | `cash_isa`            |
| `savings` / `savings_account` | `savings_account`  |
| `current` / `current_account` | `current_account`  |
| `fixed` / `fixed_deposit`  | `fixed_deposit`       |
| `notice`                   | `notice_account`      |
| default                    | `savings_account`     |

**Purpose mapping** (based on `is_isa` and `access_type`):

| Source Condition        | Target `purpose`  |
|-------------------------|-------------------|
| `is_isa = true`         | `savings`         |
| `access_type = immediate` | `emergency_fund` |
| `access_type = notice/fixed` | `savings`    |
| default                 | `general`         |

**Account name generation:** `"{institution} Cash ISA"` for ISA accounts, `"{institution} {account_type}"` for others.

7. Sets `ownership_type` to `'individual'`, `ownership_percentage` to 100, `is_joint` to `false` as defaults.
8. Carries over: `institution`, `account_number`, `current_balance`, `interest_rate`, `access_type`, `notice_period_days`, `maturity_date`, `isa_subscription_year`, `isa_subscription_amount`, and timestamps.
9. Displays statistics table with totals, ISA count, regular count, and errors.

**Safety checks:** Same pattern as MigrateEstateToNetWorth (table check, confirmation, transaction, dry-run, per-record error handling).

---

### 4.3 VerifyDataMigration

**File:** `app/Console/Commands/VerifyDataMigration.php`

| Property    | Value |
|-------------|-------|
| Signature   | `migrate:verify {--detailed}` |
| Description | Verify data migration integrity and completeness |
| Options     | `--detailed` - Show detailed verification results |

**What it does, step by step:**

Runs five verification checks and reports pass/fail for each:

1. **Assets Migration Verification** (`verifyAssetsMigration`):
   - If `assets` table does not exist, reports warning and skips.
   - Counts old `assets` records, new `properties` + `business_interests` + `chattels` records.
   - Subtracts pensions and investments from expected total (these are intentionally skipped).
   - Compares expected vs actual totals.

2. **Savings Migration Verification** (`verifySavingsMigration`):
   - If `savings_accounts` table does not exist, reports warning and skips.
   - Compares count of old `savings_accounts` to new `cash_accounts`.
   - Direct 1:1 count comparison.

3. **Data Integrity** (`verifyDataIntegrity`):
   - Checks for null `user_id` or null value fields in: `properties`, `business_interests`, `chattels`, `cash_accounts`.
   - Reports count of invalid records per table.

4. **User Associations** (`verifyUserAssociations`):
   - LEFT JOINs each target table (`properties`, `business_interests`, `chattels`, `cash_accounts`) to `users`.
   - Counts orphaned records (where user no longer exists).

5. **Duplicate Detection** (`verifyNoDuplicates`):
   - Checks for duplicate properties (same `user_id` + `address_line_1`).
   - Checks for duplicate cash accounts (same `user_id` + `account_number`, excluding nulls).

**Output format:**
- Each check gets a status icon (success/warning/error) and description.
- With `--detailed`, shows counts, breakdowns, and specific issue descriptions.
- Exit code: `SUCCESS` (0) if zero issues, `FAILURE` (1) if any issues found.

---

## 5. Security & Maintenance Commands

### 5.1 EncryptExistingData

**File:** `app/Console/Commands/EncryptExistingData.php`

| Property    | Value |
|-------------|-------|
| Signature   | `data:encrypt {--model=} {--batch=100} {--dry-run}` |
| Description | Encrypt existing unencrypted data in financial models |
| Options     | `--model` - Specific model name to encrypt (e.g., `User`, `SavingsAccount`); `--batch` - Records per batch (default: 100); `--dry-run` - Show what would be encrypted |

**Models and fields encrypted:**

| Model              | Fields |
|--------------------|--------|
| `User`             | `annual_employment_income`, `annual_self_employment_income`, `annual_rental_income`, `annual_dividend_income`, `annual_interest_income`, `annual_other_income`, `annual_trust_income` |
| `SavingsAccount`   | `current_balance` |
| `InvestmentAccount`| `current_value` |
| `DCPension`        | `current_fund_value`, `monthly_contribution_amount`, `employer_contribution_amount` |
| `DBPension`        | `accrued_annual_pension`, `lump_sum_entitlement` |
| `StatePension`     | `current_annual_amount`, `forecast_full_amount` |
| `Property`         | `current_value`, `purchase_price` |
| `Mortgage`         | `current_balance`, `original_amount`, `monthly_payment` |
| `Liability`        | `current_balance`, `original_amount`, `monthly_payment` |

**What it does, step by step:**

1. If `--model` is specified, validates it against the known model list. Fails with an error listing valid models if not found.
2. For each model to process, calls `encryptModel()`.
3. `encryptModel()` uses `chunkById` with the configured batch size for memory-efficient processing.
4. For each record, reads raw (un-cast) field values via `getRawOriginal()`.
5. Checks if each field value is already encrypted using `isLikelyEncrypted()` - this checks if the string starts with `eyJ` (base64-encoded JSON, which is how Laravel's encrypted cast stores values).
6. If any field needs encryption and not in dry-run mode:
   - Sets each unencrypted field's value back on the model (triggering the model's `encrypted` cast).
   - Calls `$record->save()` to persist the encrypted values.
7. Reports per-model and total encrypted record counts.

**Safety features:**
- Dry-run mode for preview
- Single-model targeting with `--model`
- Batch processing to avoid memory exhaustion
- Skip-already-encrypted detection prevents double-encryption
- Uses `getRawOriginal()` to read raw database values, not cast values

---

### 5.2 PurgeAuditLogs

**File:** `app/Console/Commands/PurgeAuditLogs.php`

| Property    | Value |
|-------------|-------|
| Signature   | `audit:purge {--dry-run} {--days=}` |
| Description | Purge old audit logs based on retention policy |
| Options     | `--dry-run` - Show what would be deleted; `--days` - Override default retention days |

**Retention periods** (from `config/auth.php`):

| Log Type | Default Retention | Config Key |
|----------|-------------------|------------|
| Standard (non-GDPR) | 90 days | `auth.audit.retention_days` |
| GDPR events | 2,555 days (~7 years) | `auth.audit.gdpr_retention_days` |

**What it does, step by step:**

1. Reads retention configuration. `--days` flag overrides the standard retention only (not GDPR).
2. Calculates cutoff dates for standard and GDPR logs.
3. Queries `audit_logs` (via `AuditLog` model):
   - Standard: `created_at < cutoff` AND `event_type != 'gdpr'`
   - GDPR: `created_at < GDPR cutoff` AND `event_type = 'gdpr'`
4. Displays a table showing log type, cutoff date, and record count.
5. If not dry-run and records exist, deletes both query sets.
6. Logs the purge action via `Log::info()` with standard/GDPR/total counts.

**Database changes:** Deletes rows from `audit_logs` table.
**Logging:** Writes to application log (`Log::info`) on successful purge.

---

### 5.3 ResetPreviewData

**File:** `app/Console/Commands/ResetPreviewData.php`

| Property    | Value |
|-------------|-------|
| Signature   | `preview:reset {persona?}` |
| Description | Reset preview user data to original state from persona JSON files |
| Argument    | `persona` (optional) - The persona ID to reset. If omitted, resets all. |

**Valid persona IDs:**
- `young_family`
- `peak_earners`
- `widow`
- `entrepreneur`

**Note:** The constant only lists 4 of the 6 total personas defined in the system. The `young_saver` and `retired_couple` personas are NOT included in this command.

**What it does, step by step:**

1. Validates the persona argument against the allowed list. Fails if invalid.
2. If no persona specified, processes all four valid personas.
3. For each persona:
   - Finds the primary preview user by `is_preview_user = true` AND `preview_persona_id = {personaId}`.
   - Finds the spouse user (if any) by `preview_persona_id = "{personaId}_spouse"`.
   - Warns and skips if the user is not found (suggests running `PreviewUserSeeder`).
   - Inside a DB transaction:
     - Deletes all financial data for the user (and spouse) via `deleteUserData()`.
     - Deletes the spouse's tokens and user record (if exists).
     - Resets the primary user's `spouse_id` to null.
     - Deletes the primary user's tokens and user record.
   - Runs `db:seed --class=PreviewUserSeeder` which recreates only the deleted persona (skipping already-existing ones).
4. Outputs progress messages per persona.

**Data deleted per user** (in `deleteUserData()`):

| Model                     | Deletion Method |
|---------------------------|-----------------|
| `Investment\Holding`      | Via polymorphic `holdable` on `InvestmentAccount` and `DCPension` |
| `Investment\InvestmentAccount` | Direct by `user_id` |
| `SavingsAccount`          | Direct by `user_id` |
| `DCPension`               | Direct by `user_id` |
| `DBPension`               | Direct by `user_id` |
| `LifeInsurancePolicy`     | Direct by `user_id` |
| `CriticalIllnessPolicy`   | Direct by `user_id` |
| `IncomeProtectionPolicy`  | Direct by `user_id` |
| `Estate\Liability`        | Direct by `user_id` |
| `FamilyMember`            | Direct by `user_id` |
| `Mortgage`                | Direct by `user_id` |
| `Property`                | Direct by `user_id` |

**Relationship to PreviewUserSeeder:** This command deletes the persona entirely, then calls the `PreviewUserSeeder` to recreate it. The seeder is designed to skip personas that already exist, so only the just-deleted persona gets re-created.

---

## 6. Service Providers

### AppServiceProvider (`app/Providers/AppServiceProvider.php`)
- Registers `Model::preventLazyLoading()` in non-production environments
- Registers model observers (see below)

### EventServiceProvider (`app/Providers/EventServiceProvider.php`)
Registers 6 risk-related model observers and the `Registered` event listener. See **risk.md Section 5** for the complete observer architecture, trigger conditions, and recalculation flow.

| Observer | Model | Trigger |
|----------|-------|---------|
| `UserRiskObserver` | `User` | Age, income, dependants changes |
| `PropertyRiskObserver` | `Property` | Property CRUD |
| `SavingsAccountRiskObserver` | `SavingsAccount` | Savings CRUD |
| `InvestmentAccountRiskObserver` | `InvestmentAccount` | Investment CRUD |
| `DCPensionRiskObserver` | `DCPension` | Pension CRUD |
| `FamilyMemberRiskObserver` | `FamilyMember` | Dependant CRUD |

---

## 7. Cross-Module Integration

### Commands -> Services

| Command | Service/Dependency |
|---------|-------------------|
| `trials:expire` | `App\Services\Payment\TrialService` (injected via handle method) |
| `trials:send-reminders` | `App\Mail\TrialExpirationReminder` mailable, `Subscription` model, `trial_reminder_log` table |
| `data:encrypt` | Model encryption casts (built into each model's `$casts` array) |
| `preview:reset` | `Database\Seeders\PreviewUserSeeder` (called via `db:seed`) |

### Commands -> Database Tables

| Command | Tables Read | Tables Written |
|---------|-------------|----------------|
| `trials:expire` | `subscriptions`, `users` | `subscriptions`, `users` |
| `trials:send-reminders` | `subscriptions`, `users`, `trial_reminder_log` | `trial_reminder_log` |
| `migrate:estate-to-networth` | `assets` | `properties`, `business_interests`, `chattels` |
| `migrate:savings-to-cash` | `savings_accounts` | `cash_accounts` |
| `migrate:verify` | `assets`, `savings_accounts`, `properties`, `business_interests`, `chattels`, `cash_accounts`, `users` | (none - read only) |
| `data:encrypt` | All financial model tables (9 tables) | Same tables (re-saves with encryption) |
| `audit:purge` | `audit_logs` | `audit_logs` (deletes) |
| `preview:reset` | `users` | Deletes from 12+ financial tables, then re-seeds |

### Observers -> Jobs

All six risk observers dispatch `App\Jobs\RecalculateRiskProfileJob`, which recalculates the user's risk profile asynchronously via the queue.

---

## 8. Running Commands

### Trial Management

```bash
# Expire all overdue trials (runs automatically at 00:05 daily)
php artisan trials:expire

# Send trial reminder emails (runs automatically at 09:00 daily)
php artisan trials:send-reminders
```

### Data Migration (one-time, manual)

```bash
# Preview estate migration without changes
php artisan migrate:estate-to-networth --dry-run

# Execute estate migration (will prompt for confirmation)
php artisan migrate:estate-to-networth

# Preview savings migration without changes
php artisan migrate:savings-to-cash --dry-run

# Execute savings migration (will prompt for confirmation)
php artisan migrate:savings-to-cash

# Verify all migrations passed integrity checks
php artisan migrate:verify

# Verify with detailed output
php artisan migrate:verify --detailed
```

### Security & Maintenance

```bash
# Encrypt all unencrypted financial data across all models
php artisan data:encrypt

# Encrypt a specific model only
php artisan data:encrypt --model=User

# Encrypt with custom batch size
php artisan data:encrypt --batch=50

# Preview what would be encrypted
php artisan data:encrypt --dry-run

# Combine options
php artisan data:encrypt --model=Property --batch=200 --dry-run

# Purge audit logs using default retention (90 days standard, ~7 years GDPR)
php artisan audit:purge

# Preview what would be purged
php artisan audit:purge --dry-run

# Override standard retention to 30 days
php artisan audit:purge --days=30

# Reset all preview personas to original state
php artisan preview:reset

# Reset a specific persona
php artisan preview:reset young_family
php artisan preview:reset peak_earners
php artisan preview:reset widow
php artisan preview:reset entrepreneur
```

### Scheduler

```bash
# Run the scheduler manually (executes any due commands)
php artisan schedule:run

# List all scheduled commands and their next run times
php artisan schedule:list
```

---

## 9. Known Issues and Considerations

### Production vs Development Differences

- **Lazy loading prevention** is only active in non-production. Production allows lazy loading silently, which could mask N+1 query issues.
- The **scheduler** requires a cron job to be configured on the server. If the cron entry is missing, `trials:expire` and `trials:send-reminders` will never run automatically.

### Data Safety Concerns

- **`preview:reset`** permanently deletes all financial data for the target persona(s) before re-seeding. There is no confirmation prompt. This is safe because preview users are test data only, but care must be taken to never run it against real user data.
- **`preview:reset`** only covers 4 of 6 personas (`young_family`, `peak_earners`, `widow`, `entrepreneur`). The `young_saver` and `retired_couple` personas are not supported by this command and must be reset manually via the seeder.
- **`data:encrypt`** re-saves records to trigger encryption casts. If encryption is misconfigured (wrong `APP_KEY`), data could become unreadable. Always test with `--dry-run` first.
- **`data:encrypt`** detection of already-encrypted values relies on checking for the `eyJ` prefix (base64 JSON). If a raw numeric value happens to start with `eyJ` (extremely unlikely for financial data), it would be incorrectly skipped.
- **`audit:purge`** permanently deletes audit log records. The `--days` flag only overrides the standard retention period, not the GDPR retention (which is always loaded from config). Use `--dry-run` to preview before purging.

### Migration Commands Are One-Time Use

- `migrate:estate-to-networth` and `migrate:savings-to-cash` require the legacy source tables (`assets` and `savings_accounts` respectively) to exist. Once those tables are dropped, these commands will fail gracefully.
- Running migration commands a second time without clearing the target tables first will create duplicate records. Use `migrate:verify` to check for duplicates after running.

### Observer Debounce Behaviour

- Risk recalculation uses a 5-second debounce. If multiple models for the same user change within 5 seconds, only one recalculation job is dispatched. This is intentional to batch rapid form saves.
- `PropertyRiskObserver` has its own independent debounce implementation (does not extend `RiskRecalculationObserver`). Both use identical cache-based debounce logic with the same key format and TTL.

### Email Dependencies

- `trials:send-reminders` depends on a working mail configuration. If mail is misconfigured, errors are logged but the command still returns `SUCCESS` (it catches exceptions per-user and continues).
- The `trial_reminder_log` table is the idempotency guard. If this table is cleared, users may receive duplicate reminder emails.
