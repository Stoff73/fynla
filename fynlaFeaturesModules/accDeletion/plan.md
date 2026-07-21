# Account Deletion Rework — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace user-facing account deletion (Settings → Privacy, retention overlay CTA, grace-period auto-expiry) with a single retention-first soft-delete service that preserves all user data for the legal retention period (default 7 years), supports proration via scheduled deletion, and supports restoration on return.

**Architecture:** A new `AccountDeletionService` orchestrates four lifecycle transitions (schedule, cancel, delete, restore). All three deletion trigger paths converge on this service. The current `DataPurgeService` is renamed `RetentionPurgeService` and only ever runs from a new monthly cron after `purge_eligible_at` elapses. `DataErasureService` is removed. The login/register flow detects soft-deleted users and offers restoration. A new daily cron fires scheduled deletions and sends reminder emails.

**Tech Stack:** Laravel 10, Pest 2 (PHPUnit-compatible `it()` syntax), Vue 3 (Options API), Vuex (namespaced), Sanctum (token auth), MySQL 8, Tailwind 3, Pint (PSR-12). Migrations follow the anonymous-class pattern documented in `database/CLAUDE.md`.

**Companion docs (in this folder):**

- `design.md` — the spec this plan implements (read it first)
- `accDeletion.md` — the original audit of the broken implementation

---

## File Structure

### New files

| Path | Responsibility |
|---|---|
| `config/retention.php` | Retention-period configuration (years before hard-purge) |
| `database/migrations/2026_05_07_000001_add_deletion_tracking_to_users_table.php` | Adds `deletion_scheduled_for`, `deletion_reason`, `deletion_source`, `restored_at`, `purge_eligible_at` |
| `database/migrations/2026_05_07_000002_fix_life_events_joint_owner_id_fk.php` | Replaces RESTRICT FK with `nullOnDelete` |
| `database/migrations/2026_05_07_000003_backfill_legacy_purged_users.php` | Tags existing soft-deleted rows with `deletion_reason='legacy_purged'` |
| `app/Services/Account/AccountDeletionService.php` | Core service: schedule, cancel, delete, restore |
| `app/Services/Account/RetentionPurgeService.php` | Renamed from `DataPurgeService`; only called by the 7-year hard-purge cron; bug fix removes 2 invalid table entries from `getDeletionOrder()` |
| `app/Console/Commands/ExecuteScheduledDeletions.php` | Daily 00:10 — fires deletions whose `deletion_scheduled_for` has passed |
| `app/Console/Commands/ExecuteGraceDeletions.php` | Daily 00:15 — fires deletions for users whose 30-day grace ended |
| `app/Console/Commands/SendDeletionReminders.php` | Daily 00:20 — sends 7-day and 1-day reminder emails |
| `app/Console/Commands/PurgeAfterRetention.php` | Monthly — calls `RetentionPurgeService` for users past `purge_eligible_at` |
| `app/Mail/Account/AccountDeletionScheduledEmail.php` | Mailable |
| `app/Mail/Account/AccountDeletionReminder7DaysEmail.php` | Mailable |
| `app/Mail/Account/AccountDeletionReminder1DayEmail.php` | Mailable |
| `app/Mail/Account/AccountDeletionConfirmationEmail.php` | Mailable |
| `app/Mail/Account/AccountDeletionCancelledEmail.php` | Mailable |
| `app/Mail/Account/AccountRestorationConfirmationEmail.php` | Mailable |
| `resources/views/emails/account/deletion-scheduled.blade.php` | Email template |
| `resources/views/emails/account/deletion-reminder-7days.blade.php` | Email template |
| `resources/views/emails/account/deletion-reminder-1day.blade.php` | Email template |
| `resources/views/emails/account/deletion-confirmation.blade.php` | Email template |
| `resources/views/emails/account/deletion-cancelled.blade.php` | Email template |
| `resources/views/emails/account/restoration-confirmation.blade.php` | Email template |
| `app/Http/Controllers/Api/Auth/RestoreAccountController.php` | New controller for `/api/auth/restore` and `/api/auth/restore/check` |
| `app/Http/Requests/Account/RestoreAccountRequest.php` | Validates restore payload |
| `resources/js/components/Account/RestoreAccountModal.vue` | Modal shown by login/register on restorable response |
| `resources/js/components/Account/ScheduledDeletionBanner.vue` | Banner across authenticated pages when account is scheduled for deletion |
| `tests/Unit/Services/Account/AccountDeletionServiceTest.php` | Unit tests |
| `tests/Unit/Services/Account/RetentionPurgeServiceTest.php` | Unit tests |
| `tests/Feature/Account/DeletionTriggerPathsTest.php` | All 3 paths converge correctly |
| `tests/Feature/Account/RestorationFlowTest.php` | Login + register restoration paths |
| `tests/Feature/Account/ScheduledDeletionTest.php` | Schedule + cancel + execute lifecycle |
| `tests/Feature/Account/CronJobsTest.php` | All 4 cron commands |

### Modified files

| Path | Change |
|---|---|
| `app/Models/User.php` | `$fillable` adds new columns; `$casts` for timestamps; helper methods `isScheduledForDeletion()`, `canBeRestored()` |
| `app/Models/AuditLog.php` | New action constants + `actionLabel()` switch entries |
| `app/Http/Controllers/Api/AuthController.php` | `register()` and `login()` and `verifyCode()` detect trashed users and return `account_deleted_restorable` |
| `app/Http/Controllers/Api/GDPRController.php` | `executeErasure()` repointed; new `cancelScheduledDeletion()` method; constructor takes `AccountDeletionService` |
| `app/Http/Controllers/Api/PaymentController.php` | `deleteAllData()` repointed; constructor swap |
| `app/Console/Kernel.php` | Schedule entries for the 4 new cron commands |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Add `/api/auth/restore` and `/api/auth/gdpr/erasure/cancel-scheduled` to `EXCLUDED_ROUTES` |
| `routes/api.php` | New endpoints |
| `resources/js/views/Settings/PrivacySettings.vue` | State-aware (active / scheduled / show banner) |
| `resources/js/components/Payment/DataRetentionOverlay.vue` | Copy update + redirect to `/login` |
| `resources/js/views/Auth/Login.vue` | Handle `account_deleted_restorable` response |
| `resources/js/views/Auth/Register.vue` | Same |
| `resources/js/layouts/AppLayout.vue` | Mount `ScheduledDeletionBanner` |
| `resources/js/services/authService.js` | `restoreCheck()`, `restore()` methods |
| `resources/js/services/gdprService.js` | `cancelScheduledDeletion()` method |
| Joint-owner card components (Property/Savings/Investment/etc.) | Append `(Deactivated)` badge when joint owner is soft-deleted |

### Deleted files

| Path | Reason |
|---|---|
| `app/Services/GDPR/DataErasureService.php` | Replaced by `AccountDeletionService` |
| `app/Services/Payment/DataPurgeService.php` | Renamed to `app/Services/Account/RetentionPurgeService.php` |
| `app/Console/Commands/PurgeExpiredUserData.php` | Replaced by `ExecuteGraceDeletions.php` |

---

## Pre-flight: Task 0

### Task 0.1: Verify branch + clean state

- [ ] **Step 1: Verify branch**

```bash
git branch --show-current
```

Expected: `accountDeletionRework`

- [ ] **Step 2: Verify clean working tree (no staged or unstaged changes to source)**

```bash
git status --short | grep -E "^(M|A|D)" | head
```

Expected: empty output (untracked files like `FCA/`, `fyn/`, etc. are OK; only modified/staged source is the concern).

- [ ] **Step 3: Run baseline tests once to confirm green starting point**

```bash
./vendor/bin/pest --testsuite=Unit --stop-on-failure
```

Expected: all green. If anything is red on `dev`, stop and fix the dev-branch regression first.

---

## Phase 1 — Foundation: schema, config, audit codes

### Task 1.1: Retention config

**Files:**

- Create: `config/retention.php`

- [ ] **Step 1: Create config**

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Account retention period (years)
    |--------------------------------------------------------------------------
    | After a user's account is soft-deleted (any reason), their data is
    | retained in the database for this many years before the monthly
    | RetentionPurgeService cron hard-deletes it.
    |
    | Default 7 years matches FCA COBS 11.5 record-keeping requirements.
    */
    'account_years' => (int) env('ACCOUNT_RETENTION_YEARS', 7),

    /*
    | Reminder emails sent before a scheduled deletion fires.
    | Days before `deletion_scheduled_for` to send each reminder.
    */
    'reminder_days_before' => [7, 1],
];
```

- [ ] **Step 2: Verify config loads**

```bash
php artisan tinker --execute="echo config('retention.account_years');"
```

Expected: `7`.

- [ ] **Step 3: Commit**

```bash
git add config/retention.php
git commit -m "feat(retention): add account retention config (default 7 years)"
```

---

### Task 1.2: Add deletion tracking columns to users (TDD-style)

**Files:**

- Create: `database/migrations/2026_05_07_000001_add_deletion_tracking_to_users_table.php`
- Test: `tests/Feature/Account/SchemaTest.php`

- [ ] **Step 1: Write failing schema test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('users table has deletion tracking columns', function () {
    expect(Schema::hasColumn('users', 'deletion_scheduled_for'))->toBeTrue();
    expect(Schema::hasColumn('users', 'deletion_reason'))->toBeTrue();
    expect(Schema::hasColumn('users', 'deletion_source'))->toBeTrue();
    expect(Schema::hasColumn('users', 'restored_at'))->toBeTrue();
    expect(Schema::hasColumn('users', 'purge_eligible_at'))->toBeTrue();
});
```

- [ ] **Step 2: Run test, expect fail**

```bash
./vendor/bin/pest tests/Feature/Account/SchemaTest.php
```

Expected: FAIL — none of the columns exist yet.

- [ ] **Step 3: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deletion_scheduled_for')) {
                $table->timestamp('deletion_scheduled_for')->nullable()->after('trial_ends_at');
            }
            if (! Schema::hasColumn('users', 'deletion_reason')) {
                $table->enum('deletion_reason', [
                    'user_requested',
                    'trial_expired',
                    'subscription_cancelled_grace_ended',
                    'admin_initiated',
                    'legacy_purged',
                ])->nullable()->after('deletion_scheduled_for');
            }
            if (! Schema::hasColumn('users', 'deletion_source')) {
                $table->string('deletion_source', 50)->nullable()->after('deletion_reason');
            }
            if (! Schema::hasColumn('users', 'restored_at')) {
                $table->timestamp('restored_at')->nullable()->after('deletion_source');
            }
            if (! Schema::hasColumn('users', 'purge_eligible_at')) {
                $table->timestamp('purge_eligible_at')->nullable()->after('restored_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('deletion_scheduled_for');
            $table->index('purge_eligible_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deletion_scheduled_for']);
            $table->dropIndex(['purge_eligible_at']);
            $table->dropColumn([
                'deletion_scheduled_for',
                'deletion_reason',
                'deletion_source',
                'restored_at',
                'purge_eligible_at',
            ]);
        });
    }
};
```

- [ ] **Step 4: Run migration**

```bash
php artisan migrate
```

Expected: `Migrated: 2026_05_07_000001_add_deletion_tracking_to_users_table`.

- [ ] **Step 5: Re-run test, expect pass**

```bash
./vendor/bin/pest tests/Feature/Account/SchemaTest.php
```

Expected: PASS.

- [ ] **Step 6: Reseed (per CLAUDE.md "ALWAYS reseed after any operation that modifies local DB data")**

```bash
php artisan db:seed
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_05_07_000001_add_deletion_tracking_to_users_table.php tests/Feature/Account/SchemaTest.php
git commit -m "feat(users): add deletion-tracking columns and indexes"
```

---

### Task 1.3: Fix `life_events.joint_owner_id` foreign key

**Files:**

- Create: `database/migrations/2026_05_07_000002_fix_life_events_joint_owner_id_fk.php`

- [ ] **Step 1: Add to schema test**

Append to `tests/Feature/Account/SchemaTest.php`:

```php
it('life_events.joint_owner_id FK is set null on delete', function () {
    $row = DB::selectOne(
        "SELECT r.DELETE_RULE
         FROM information_schema.KEY_COLUMN_USAGE k
         JOIN information_schema.REFERENTIAL_CONSTRAINTS r
           ON k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
          AND k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA
         WHERE k.TABLE_NAME = 'life_events'
           AND k.COLUMN_NAME = 'joint_owner_id'
           AND k.CONSTRAINT_SCHEMA = DATABASE()"
    );
    expect($row->DELETE_RULE)->toBe('SET NULL');
});
```

- [ ] **Step 2: Run, expect fail**

```bash
./vendor/bin/pest tests/Feature/Account/SchemaTest.php
```

Expected: the FK rule test FAILS (current value is `NO ACTION`).

- [ ] **Step 3: Write migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('life_events', function (Blueprint $table) {
            $table->dropForeign(['joint_owner_id']);
            $table->foreign('joint_owner_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('life_events', function (Blueprint $table) {
            $table->dropForeign(['joint_owner_id']);
            $table->foreign('joint_owner_id')
                ->references('id')->on('users');
        });
    }
};
```

- [ ] **Step 4: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 5: Re-run test, expect pass**

```bash
./vendor/bin/pest tests/Feature/Account/SchemaTest.php
```

- [ ] **Step 6: Reseed**

```bash
php artisan db:seed
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_05_07_000002_fix_life_events_joint_owner_id_fk.php tests/Feature/Account/SchemaTest.php
git commit -m "fix(life_events): set joint_owner_id FK to nullOnDelete"
```

---

### Task 1.4: Backfill legacy soft-deleted users

**Files:**

- Create: `database/migrations/2026_05_07_000003_backfill_legacy_purged_users.php`

- [ ] **Step 1: Write migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->whereNull('deletion_reason')
            ->update([
                'deletion_reason' => 'legacy_purged',
                'deletion_source' => 'auto_expiration_grace',
                'purge_eligible_at' => DB::raw('deleted_at'),
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('deletion_reason', 'legacy_purged')
            ->update([
                'deletion_reason' => null,
                'deletion_source' => null,
                'purge_eligible_at' => null,
            ]);
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 3: Verify backfill (expect 0 rows on a fresh dev DB; on a real DB this would tag pre-existing soft-deleted rows)**

```bash
php artisan tinker --execute="echo \App\Models\User::onlyTrashed()->where('deletion_reason','legacy_purged')->count();"
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_07_000003_backfill_legacy_purged_users.php
git commit -m "feat(users): backfill legacy soft-deleted users with deletion_reason"
```

---

### Task 1.5: Update User model

**Files:**

- Modify: `app/Models/User.php`

- [ ] **Step 1: Add fillable + casts**

Find the `$fillable` array in `app/Models/User.php` and add at the end (preserving alphabetical order if the existing array uses one; otherwise append):

```php
'deletion_scheduled_for',
'deletion_reason',
'deletion_source',
'restored_at',
'purge_eligible_at',
```

Find the `$casts` array (or `casts()` method) and add:

```php
'deletion_scheduled_for' => 'datetime',
'restored_at' => 'datetime',
'purge_eligible_at' => 'datetime',
```

- [ ] **Step 2: Add helper methods**

At the end of the User class, before the closing `}`:

```php
/**
 * Account is scheduled for deletion but not yet executed.
 */
public function isScheduledForDeletion(): bool
{
    return $this->deletion_scheduled_for !== null
        && $this->deleted_at === null;
}

/**
 * Account is currently in the deleted state and within the retention window
 * (i.e. data still on disk and the row is soft-deleted, not legacy-purged).
 */
public function canBeRestored(): bool
{
    return $this->trashed()
        && $this->deletion_reason !== 'legacy_purged'
        && ($this->purge_eligible_at === null || $this->purge_eligible_at->isFuture());
}
```

- [ ] **Step 3: Write unit test**

`tests/Unit/Models/UserDeletionStateTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;

it('isScheduledForDeletion returns true only when scheduled and not deleted', function () {
    $u = User::factory()->create(['deletion_scheduled_for' => Carbon::tomorrow()]);
    expect($u->isScheduledForDeletion())->toBeTrue();

    $u->update(['deleted_at' => now()]);
    $u->refresh();
    expect($u->isScheduledForDeletion())->toBeFalse();
});

it('canBeRestored returns false for legacy_purged users', function () {
    $u = User::factory()->create([
        'deleted_at' => Carbon::yesterday(),
        'deletion_reason' => 'legacy_purged',
        'purge_eligible_at' => Carbon::yesterday(),
    ]);
    $u = User::withTrashed()->find($u->id);
    expect($u->canBeRestored())->toBeFalse();
});

it('canBeRestored returns true for normal soft-deleted user', function () {
    $u = User::factory()->create([
        'deleted_at' => now(),
        'deletion_reason' => 'user_requested',
        'purge_eligible_at' => now()->addYears(7),
    ]);
    $u = User::withTrashed()->find($u->id);
    expect($u->canBeRestored())->toBeTrue();
});
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/pest tests/Unit/Models/UserDeletionStateTest.php
```

Expected: 3 PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Unit/Models/UserDeletionStateTest.php
git commit -m "feat(user): add deletion-state casts and helper methods"
```

---

### Task 1.6: AuditLog action constants

**Files:**

- Modify: `app/Models/AuditLog.php`

- [ ] **Step 1: Add new constants**

In `app/Models/AuditLog.php`, find the existing `ACTION_ERASURE_*` constants (around line 89-91) and add immediately after:

```php
public const ACTION_ACCOUNT_DELETION_SCHEDULED = 'account_deletion_scheduled';

public const ACTION_ACCOUNT_DELETION_CANCELLED = 'account_deletion_cancelled';

public const ACTION_ACCOUNT_DELETED = 'account_deleted';

public const ACTION_ACCOUNT_RESTORED = 'account_restored';

public const ACTION_ACCOUNT_PURGED = 'account_purged';
```

- [ ] **Step 2: Add `actionLabel()` switch entries**

Find the `actionLabel()` method (around line 188) and add inside the match:

```php
self::ACTION_ACCOUNT_DELETION_SCHEDULED => 'Account deletion scheduled',
self::ACTION_ACCOUNT_DELETION_CANCELLED => 'Account deletion cancelled',
self::ACTION_ACCOUNT_DELETED => 'Account deleted',
self::ACTION_ACCOUNT_RESTORED => 'Account restored',
self::ACTION_ACCOUNT_PURGED => 'Account purged',
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/AuditLog.php
git commit -m "feat(audit): add account deletion lifecycle action constants"
```

---

## Phase 2 — Core service: `AccountDeletionService`

### Task 2.1: Service skeleton + `scheduleDeletion`

**Files:**

- Create: `app/Services/Account/AccountDeletionService.php`
- Test: `tests/Unit/Services/Account/AccountDeletionServiceTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use App\Mail\Account\AccountDeletionScheduledEmail;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

it('scheduleDeletion sets columns, audit logs, and queues email', function () {
    $user = User::factory()->create();
    $executesAt = now()->addDays(14);

    app(AccountDeletionService::class)->scheduleDeletion(
        $user,
        'user_requested',
        'settings_privacy',
        $executesAt
    );

    $user->refresh();
    expect($user->deletion_scheduled_for->toDateTimeString())->toBe($executesAt->toDateTimeString());
    expect($user->deletion_reason)->toBe('user_requested');
    expect($user->deletion_source)->toBe('settings_privacy');
    expect($user->deleted_at)->toBeNull();

    Mail::assertQueued(AccountDeletionScheduledEmail::class);

    expect(AuditLog::where('user_id', $user->id)
        ->where('action', AuditLog::ACTION_ACCOUNT_DELETION_SCHEDULED)
        ->count())->toBe(1);
});

it('scheduleDeletion refuses if user is already scheduled', function () {
    $user = User::factory()->create(['deletion_scheduled_for' => now()->addDays(7)]);

    expect(fn () => app(AccountDeletionService::class)->scheduleDeletion(
        $user,
        'user_requested',
        'settings_privacy',
        now()->addDays(14)
    ))->toThrow(\RuntimeException::class, 'already scheduled');
});

it('scheduleDeletion refuses if user is already deleted', function () {
    $user = User::factory()->create();
    $user->delete();

    expect(fn () => app(AccountDeletionService::class)->scheduleDeletion(
        $user,
        'user_requested',
        'settings_privacy',
        now()->addDays(14)
    ))->toThrow(\RuntimeException::class, 'already deleted');
});
```

- [ ] **Step 2: Run, expect fail (class doesn't exist)**

```bash
./vendor/bin/pest tests/Unit/Services/Account/AccountDeletionServiceTest.php
```

- [ ] **Step 3: Write service skeleton + scheduleDeletion**

```php
<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Mail\Account\AccountDeletionScheduledEmail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AccountDeletionService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function scheduleDeletion(
        User $user,
        string $reason,
        string $source,
        Carbon $executesAt
    ): void {
        if ($user->trashed()) {
            throw new \RuntimeException('User is already deleted.');
        }
        if ($user->isScheduledForDeletion()) {
            throw new \RuntimeException('User is already scheduled for deletion.');
        }

        DB::transaction(function () use ($user, $reason, $source, $executesAt) {
            $this->auditService->log(
                AuditLog::ACTION_ACCOUNT_DELETION_SCHEDULED,
                $user->id,
                metadata: [
                    'reason' => $reason,
                    'source' => $source,
                    'executes_at' => $executesAt->toIso8601String(),
                ]
            );

            $user->update([
                'deletion_scheduled_for' => $executesAt,
                'deletion_reason' => $reason,
                'deletion_source' => $source,
            ]);

            Mail::to($user->email)->queue(
                new AccountDeletionScheduledEmail($user, $executesAt)
            );
        });
    }

    public function cancelScheduledDeletion(User $user): void
    {
        // Implemented in Task 2.2
        throw new \LogicException('Not yet implemented');
    }

    public function deleteAccount(User $user, string $reason, string $source): void
    {
        // Implemented in Task 2.3
        throw new \LogicException('Not yet implemented');
    }

    public function restoreAccount(User $user): void
    {
        // Implemented in Task 2.4
        throw new \LogicException('Not yet implemented');
    }
}
```

- [ ] **Step 4: Verify `AuditService::log` signature matches**

```bash
grep -n "public function log\|function log(" /Users/CSJ/Desktop/fynla/app/Services/Audit/AuditService.php | head -5
```

If the existing `AuditService::log` does not accept a `metadata` named arg with the shown shape, adjust the call site to match its actual signature (read the file). Use whatever method writes a row with `user_id` + `action` + arbitrary metadata.

- [ ] **Step 5: Stub the AccountDeletionScheduledEmail mailable so tests can compile**

`app/Mail/Account/AccountDeletionScheduledEmail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletionScheduledEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Carbon $executesAt
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Your account is scheduled for deletion')
            ->markdown('emails.account.deletion-scheduled');
    }
}
```

Stub Blade `resources/views/emails/account/deletion-scheduled.blade.php`:

```blade
@component('mail::message')
# Hi {{ $user->first_name ?? '' }},

Your Fynla account is scheduled for deletion on {{ $executesAt->format('j F Y') }}.

You can cancel this at any time before that date by going to **Settings → Privacy** in your dashboard.

Thanks,
Fynla
@endcomponent
```

(Full template content per the project's `email-template` skill in Task 3 below — this stub gets the tests green.)

- [ ] **Step 6: Run tests, expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Account/AccountDeletionServiceTest.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/Account/AccountDeletionService.php app/Mail/Account/AccountDeletionScheduledEmail.php resources/views/emails/account/deletion-scheduled.blade.php tests/Unit/Services/Account/AccountDeletionServiceTest.php
git commit -m "feat(account): add AccountDeletionService::scheduleDeletion"
```

---

### Task 2.2: `cancelScheduledDeletion`

**Files:**

- Modify: `app/Services/Account/AccountDeletionService.php`
- Modify: `tests/Unit/Services/Account/AccountDeletionServiceTest.php`
- Create: `app/Mail/Account/AccountDeletionCancelledEmail.php`
- Create: `resources/views/emails/account/deletion-cancelled.blade.php`

- [ ] **Step 1: Add failing tests**

Append to the test file:

```php
it('cancelScheduledDeletion clears columns, audit logs, queues email', function () {
    $user = User::factory()->create([
        'deletion_scheduled_for' => now()->addDays(7),
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings_privacy',
    ]);

    app(AccountDeletionService::class)->cancelScheduledDeletion($user);

    $user->refresh();
    expect($user->deletion_scheduled_for)->toBeNull();
    expect($user->deletion_reason)->toBeNull();
    expect($user->deletion_source)->toBeNull();

    Mail::assertQueued(\App\Mail\Account\AccountDeletionCancelledEmail::class);

    expect(AuditLog::where('user_id', $user->id)
        ->where('action', AuditLog::ACTION_ACCOUNT_DELETION_CANCELLED)
        ->count())->toBe(1);
});

it('cancelScheduledDeletion refuses if user is not scheduled', function () {
    $user = User::factory()->create();

    expect(fn () => app(AccountDeletionService::class)->cancelScheduledDeletion($user))
        ->toThrow(\RuntimeException::class, 'not scheduled');
});
```

- [ ] **Step 2: Run, expect fail**

```bash
./vendor/bin/pest tests/Unit/Services/Account/AccountDeletionServiceTest.php
```

- [ ] **Step 3: Implement `cancelScheduledDeletion`**

Replace the stub method body:

```php
public function cancelScheduledDeletion(User $user): void
{
    if (! $user->isScheduledForDeletion()) {
        throw new \RuntimeException('User is not scheduled for deletion.');
    }

    DB::transaction(function () use ($user) {
        $this->auditService->log(
            AuditLog::ACTION_ACCOUNT_DELETION_CANCELLED,
            $user->id,
            metadata: [
                'previous_reason' => $user->deletion_reason,
                'previous_source' => $user->deletion_source,
                'previous_scheduled_for' => $user->deletion_scheduled_for?->toIso8601String(),
            ]
        );

        $user->update([
            'deletion_scheduled_for' => null,
            'deletion_reason' => null,
            'deletion_source' => null,
        ]);

        Mail::to($user->email)->queue(new AccountDeletionCancelledEmail($user));
    });
}
```

- [ ] **Step 4: Stub the cancelled mailable + Blade**

`app/Mail/Account/AccountDeletionCancelledEmail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletionCancelledEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function build(): self
    {
        return $this
            ->subject('Your scheduled account deletion has been cancelled')
            ->markdown('emails.account.deletion-cancelled');
    }
}
```

`resources/views/emails/account/deletion-cancelled.blade.php`:

```blade
@component('mail::message')
# Hi {{ $user->first_name ?? '' }},

Your scheduled account deletion has been cancelled. Your account remains active and you can keep using Fynla as normal.

Thanks,
Fynla
@endcomponent
```

- [ ] **Step 5: Add `use App\Mail\Account\AccountDeletionCancelledEmail;`** to the service.

- [ ] **Step 6: Run tests, expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Account/AccountDeletionServiceTest.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/Account/AccountDeletionService.php app/Mail/Account/AccountDeletionCancelledEmail.php resources/views/emails/account/deletion-cancelled.blade.php tests/Unit/Services/Account/AccountDeletionServiceTest.php
git commit -m "feat(account): cancelScheduledDeletion"
```

---

### Task 2.3: `deleteAccount` (the core retention-first delete)

**Files:**

- Modify: `app/Services/Account/AccountDeletionService.php`
- Create: `app/Mail/Account/AccountDeletionConfirmationEmail.php`
- Create: `resources/views/emails/account/deletion-confirmation.blade.php`

- [ ] **Step 1: Add tests**

Append:

```php
it('deleteAccount soft-deletes user and preserves all financial data', function () {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id, 'status' => 'active']);

    // Seed some financial data
    \App\Models\LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);
    \App\Models\Investment\InvestmentAccount::factory()->create(['user_id' => $user->id]);

    $beforeAuditCount = AuditLog::where('user_id', $user->id)->count();

    app(AccountDeletionService::class)->deleteAccount(
        $user,
        'user_requested',
        'settings_privacy'
    );

    $user = User::withTrashed()->find($user->id);
    expect($user->trashed())->toBeTrue();
    expect($user->deletion_reason)->toBe('user_requested');
    expect($user->deletion_source)->toBe('settings_privacy');
    expect($user->purge_eligible_at)->not->toBeNull();
    expect($user->purge_eligible_at->isAfter(now()->addYears(6)))->toBeTrue();

    // PII intact
    expect($user->first_name)->not->toBeNull();
    expect($user->email)->not->toBeNull();

    // Financial data intact
    expect(\App\Models\LifeInsurancePolicy::where('user_id', $user->id)->count())->toBe(1);
    expect(\App\Models\Investment\InvestmentAccount::where('user_id', $user->id)->count())->toBe(1);

    // Subscription cancelled (status flipped, row preserved)
    expect(Subscription::where('user_id', $user->id)->first()->status)->toBe('cancelled');

    // Audit log appended (not anonymised)
    expect(AuditLog::where('user_id', $user->id)->count())->toBeGreaterThan($beforeAuditCount);
    expect(AuditLog::where('user_id', $user->id)
        ->where('action', AuditLog::ACTION_ACCOUNT_DELETED)->count())->toBe(1);

    // Sessions and tokens revoked
    expect(\DB::table('user_sessions')->where('user_id', $user->id)->count())->toBe(0);
    expect(\DB::table('personal_access_tokens')
        ->where('tokenable_type', User::class)
        ->where('tokenable_id', $user->id)->count())->toBe(0);

    Mail::assertQueued(\App\Mail\Account\AccountDeletionConfirmationEmail::class);
});

it('deleteAccount clears scheduled-deletion fields if previously scheduled', function () {
    $user = User::factory()->create([
        'deletion_scheduled_for' => now()->subMinute(),
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings_privacy',
    ]);

    app(AccountDeletionService::class)->deleteAccount(
        $user,
        $user->deletion_reason,
        $user->deletion_source
    );

    $user = User::withTrashed()->find($user->id);
    expect($user->deletion_scheduled_for)->toBeNull();
});

it('deleteAccount with non-active subscription leaves status alone', function () {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id, 'status' => 'expired']);

    app(AccountDeletionService::class)->deleteAccount(
        $user,
        'trial_expired',
        'auto_expiration_grace'
    );

    expect(Subscription::where('user_id', $user->id)->first()->status)->toBe('expired');
});
```

- [ ] **Step 2: Run, expect fail**

```bash
./vendor/bin/pest tests/Unit/Services/Account/AccountDeletionServiceTest.php
```

- [ ] **Step 3: Implement `deleteAccount`**

Replace the stub:

```php
public function deleteAccount(User $user, string $reason, string $source): void
{
    DB::transaction(function () use ($user, $reason, $source) {
        $this->auditService->log(
            AuditLog::ACTION_ACCOUNT_DELETED,
            $user->id,
            metadata: [
                'reason' => $reason,
                'source' => $source,
            ]
        );

        // Revoke Sanctum tokens
        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->delete();

        // Delete sessions
        DB::table('user_sessions')->where('user_id', $user->id)->delete();

        // Cancel an active subscription only — leave others (expired/trialing/cancelled) alone
        DB::table('subscriptions')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        $retentionYears = (int) config('retention.account_years', 7);

        $user->update([
            'deletion_scheduled_for' => null,
            'deletion_reason' => $reason,
            'deletion_source' => $source,
            'purge_eligible_at' => now()->addYears($retentionYears),
        ]);
        $user->delete(); // soft-delete via SoftDeletes trait

        Mail::to($user->email)->queue(new AccountDeletionConfirmationEmail($user));
    });
}
```

- [ ] **Step 4: Stub confirmation mailable + Blade**

`app/Mail/Account/AccountDeletionConfirmationEmail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletionConfirmationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function build(): self
    {
        return $this
            ->subject('Your Fynla account has been deleted')
            ->markdown('emails.account.deletion-confirmation');
    }
}
```

`resources/views/emails/account/deletion-confirmation.blade.php`:

```blade
@component('mail::message')
# Hi {{ $user->first_name ?? '' }},

Your Fynla account has been deleted. Your records are retained for {{ config('retention.account_years', 7) }} years for regulatory compliance, after which they will be permanently deleted.

You can restore your account at any time within that period by signing in with your existing credentials.

Thanks,
Fynla
@endcomponent
```

- [ ] **Step 5: Add `use App\Mail\Account\AccountDeletionConfirmationEmail;`** to the service.

- [ ] **Step 6: Run tests, expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Account/AccountDeletionServiceTest.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/Account/AccountDeletionService.php app/Mail/Account/AccountDeletionConfirmationEmail.php resources/views/emails/account/deletion-confirmation.blade.php tests/Unit/Services/Account/AccountDeletionServiceTest.php
git commit -m "feat(account): deleteAccount preserves all data, only soft-deletes user"
```

---

### Task 2.4: `restoreAccount`

**Files:**

- Modify: `app/Services/Account/AccountDeletionService.php`
- Create: `app/Mail/Account/AccountRestorationConfirmationEmail.php`
- Create: `resources/views/emails/account/restoration-confirmation.blade.php`

- [ ] **Step 1: Add tests**

```php
it('restoreAccount un-soft-deletes and writes audit + email', function () {
    $user = User::factory()->create();
    app(AccountDeletionService::class)->deleteAccount($user, 'user_requested', 'settings_privacy');

    $user = User::withTrashed()->find($user->id);
    expect($user->trashed())->toBeTrue();

    app(AccountDeletionService::class)->restoreAccount($user);

    $user->refresh();
    expect($user->trashed())->toBeFalse();
    expect($user->restored_at)->not->toBeNull();
    expect($user->purge_eligible_at)->toBeNull();
    // deletion_reason and deletion_source persist for audit
    expect($user->deletion_reason)->toBe('user_requested');

    expect(AuditLog::where('user_id', $user->id)
        ->where('action', AuditLog::ACTION_ACCOUNT_RESTORED)->count())->toBe(1);

    Mail::assertQueued(\App\Mail\Account\AccountRestorationConfirmationEmail::class);
});

it('restoreAccount refuses for legacy_purged users', function () {
    $user = User::factory()->create([
        'deleted_at' => now()->subYears(2),
        'deletion_reason' => 'legacy_purged',
    ]);

    $user = User::withTrashed()->find($user->id);

    expect(fn () => app(AccountDeletionService::class)->restoreAccount($user))
        ->toThrow(\RuntimeException::class, 'cannot be restored');
});
```

- [ ] **Step 2: Run, expect fail**

- [ ] **Step 3: Implement**

```php
public function restoreAccount(User $user): void
{
    if (! $user->canBeRestored()) {
        throw new \RuntimeException('Account cannot be restored.');
    }

    DB::transaction(function () use ($user) {
        $this->auditService->log(
            AuditLog::ACTION_ACCOUNT_RESTORED,
            $user->id,
            metadata: [
                'previous_reason' => $user->deletion_reason,
                'previous_source' => $user->deletion_source,
            ]
        );

        $user->update([
            'restored_at' => now(),
            'purge_eligible_at' => null,
            // deletion_reason and deletion_source intentionally NOT cleared (audit history)
        ]);
        $user->restore();

        Mail::to($user->email)->queue(new AccountRestorationConfirmationEmail($user));
    });
}
```

- [ ] **Step 4: Stub mailable + Blade**

`app/Mail/Account/AccountRestorationConfirmationEmail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountRestorationConfirmationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function build(): self
    {
        return $this
            ->subject('Welcome back to Fynla')
            ->markdown('emails.account.restoration-confirmation');
    }
}
```

`resources/views/emails/account/restoration-confirmation.blade.php`:

```blade
@component('mail::message')
# Welcome back, {{ $user->first_name ?? '' }}.

Your account has been restored. All your previous data is back exactly as you left it.

To regain full access to your plans and recommendations, choose a subscription on your dashboard.

Thanks,
Fynla
@endcomponent
```

- [ ] **Step 5: Add `use App\Mail\Account\AccountRestorationConfirmationEmail;`** to the service.

- [ ] **Step 6: Run tests, expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Account/AccountDeletionServiceTest.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/Account/AccountDeletionService.php app/Mail/Account/AccountRestorationConfirmationEmail.php resources/views/emails/account/restoration-confirmation.blade.php tests/Unit/Services/Account/AccountDeletionServiceTest.php
git commit -m "feat(account): restoreAccount with retention-respecting checks"
```

---

## Phase 3 — Rename `DataPurgeService` → `RetentionPurgeService` (with bug fix)

### Task 3.1: Move and rename, fix the schema-mismatch bug

**Files:**

- Create: `app/Services/Account/RetentionPurgeService.php`
- Delete: `app/Services/Payment/DataPurgeService.php`
- Test: `tests/Unit/Services/Account/RetentionPurgeServiceTest.php`

- [ ] **Step 1: Copy file to new namespace**

```bash
mkdir -p app/Services/Account
cp app/Services/Payment/DataPurgeService.php app/Services/Account/RetentionPurgeService.php
```

- [ ] **Step 2: Update namespace and class name in the new file**

Edit `app/Services/Account/RetentionPurgeService.php`:

- Change `namespace App\Services\Payment;` to `namespace App\Services\Account;`
- Change `class DataPurgeService` to `class RetentionPurgeService`
- Rename method `purgeUserData` to `purgeUser` (single param: `User $user`)

- [ ] **Step 3: Fix the bug in `getDeletionOrder()`**

Find the method and remove these two lines (the schema-mismatch bug from the audit):

```php
'data_retention_email_log',
'renewal_reminder_log',
```

Both tables only have `subscription_id`, not `user_id`. They cascade from `subscriptions` automatically when subscriptions is deleted later in the order.

- [ ] **Step 4: Write a sanity-check test**

`tests/Unit/Services/Account/RetentionPurgeServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Services\Account\RetentionPurgeService;
use Illuminate\Support\Facades\Schema;

it('every table in deletion order has a user_id column', function () {
    $svc = app(RetentionPurgeService::class);
    $reflection = new \ReflectionClass($svc);
    $method = $reflection->getMethod('getDeletionOrder');
    $method->setAccessible(true);
    $order = $method->invoke($svc);

    foreach ($order as $table) {
        expect(Schema::hasTable($table))
            ->toBeTrue("table {$table} from getDeletionOrder must exist");
        expect(Schema::hasColumn($table, 'user_id'))
            ->toBeTrue("table {$table} from getDeletionOrder must have user_id column");
    }
});
```

- [ ] **Step 5: Delete the old file**

```bash
git rm app/Services/Payment/DataPurgeService.php
```

- [ ] **Step 6: Run tests**

```bash
./vendor/bin/pest tests/Unit/Services/Account/RetentionPurgeServiceTest.php
```

Expected: PASS.

- [ ] **Step 7: Update remaining callers (PaymentController + PurgeExpiredUserData are repointed in later phases). At this point only the dependency-resolution from the constructor binding will fail. Run unit tests to spot which:**

```bash
./vendor/bin/pest --testsuite=Unit 2>&1 | grep -E "DataPurgeService|RetentionPurgeService" | head
```

The two callers (`PaymentController` constructor `private DataPurgeService $purgeService`, `PurgeExpiredUserData` constructor) will be dealt with in Phase 5 + Phase 6.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Account/RetentionPurgeService.php tests/Unit/Services/Account/RetentionPurgeServiceTest.php
git commit -m "refactor(retention): rename DataPurgeService -> RetentionPurgeService, fix data_retention_email_log/renewal_reminder_log schema mismatch"
```

---

## Phase 4 — Email mailables and templates

### Task 4.1: Reminder mailables (7-day + 1-day)

**Files:**

- Create: `app/Mail/Account/AccountDeletionReminder7DaysEmail.php`
- Create: `app/Mail/Account/AccountDeletionReminder1DayEmail.php`
- Create: `resources/views/emails/account/deletion-reminder-7days.blade.php`
- Create: `resources/views/emails/account/deletion-reminder-1day.blade.php`

- [ ] **Step 1: Write 7-day mailable**

`app/Mail/Account/AccountDeletionReminder7DaysEmail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletionReminder7DaysEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Carbon $executesAt
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Your Fynla account will be deleted in 7 days')
            ->markdown('emails.account.deletion-reminder-7days');
    }
}
```

- [ ] **Step 2: Write 7-day Blade**

`resources/views/emails/account/deletion-reminder-7days.blade.php`:

```blade
@component('mail::message')
# Hi {{ $user->first_name ?? '' }},

Your Fynla account is scheduled for deletion on **{{ $executesAt->format('j F Y') }}** — 7 days from now.

If you'd like to keep your account, sign in and cancel the deletion in **Settings → Privacy**.

Thanks,
Fynla
@endcomponent
```

- [ ] **Step 3: Write 1-day mailable**

`app/Mail/Account/AccountDeletionReminder1DayEmail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletionReminder1DayEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Carbon $executesAt
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Final reminder: your Fynla account will be deleted tomorrow')
            ->markdown('emails.account.deletion-reminder-1day');
    }
}
```

- [ ] **Step 4: Write 1-day Blade**

`resources/views/emails/account/deletion-reminder-1day.blade.php`:

```blade
@component('mail::message')
# Hi {{ $user->first_name ?? '' }},

This is your final reminder. Your Fynla account will be deleted on **{{ $executesAt->format('j F Y') }}** — tomorrow.

If you'd like to keep your account, sign in and cancel the deletion in **Settings → Privacy**.

Thanks,
Fynla
@endcomponent
```

- [ ] **Step 5: Render-test all six templates with a fake mail send**

```bash
php artisan tinker --execute="
\$u = \App\Models\User::factory()->make();
\$e = now()->addDays(7);
foreach ([
    new \App\Mail\Account\AccountDeletionScheduledEmail(\$u, \$e),
    new \App\Mail\Account\AccountDeletionReminder7DaysEmail(\$u, \$e),
    new \App\Mail\Account\AccountDeletionReminder1DayEmail(\$u, \$e),
    new \App\Mail\Account\AccountDeletionConfirmationEmail(\$u),
    new \App\Mail\Account\AccountDeletionCancelledEmail(\$u),
    new \App\Mail\Account\AccountRestorationConfirmationEmail(\$u),
] as \$m) {
    echo get_class(\$m) . ': OK ' . PHP_EOL . substr(\$m->render(), 0, 80) . PHP_EOL . PHP_EOL;
}"
```

Expected: all six render without error.

- [ ] **Step 6: Apply project email-template polish**

Per the project's `email-template` skill conventions, the `@component('mail::message')` markdown shorthand can stay or be migrated to the project's `master.blade.php` layout. **Migrate when the implementer reaches this step using the `email-template` skill** — it knows the exact module conventions, header colours, signoff, etc. Each Blade above must use `master.blade.php` with appropriate modules (logo-bar, hero-header, body, cta where applicable, signoff, footer) and pass the email-template skill's audit. This is not a placeholder; it is a deferred call-out to a project skill that owns the cosmetic layer.

- [ ] **Step 7: Commit**

```bash
git add app/Mail/Account/ resources/views/emails/account/
git commit -m "feat(account): all six lifecycle email mailables and templates"
```

---

## Phase 5 — Cron commands

### Task 5.1: `ExecuteScheduledDeletions`

**Files:**

- Create: `app/Console/Commands/ExecuteScheduledDeletions.php`
- Test: `tests/Feature/Account/CronJobsTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

it('accounts:execute-scheduled-deletions deletes only users whose schedule has passed', function () {
    $past = User::factory()->create([
        'deletion_scheduled_for' => now()->subHour(),
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings_privacy',
    ]);
    $future = User::factory()->create([
        'deletion_scheduled_for' => now()->addDay(),
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings_privacy',
    ]);

    \Artisan::call('accounts:execute-scheduled-deletions');

    expect(User::withTrashed()->find($past->id)->trashed())->toBeTrue();
    expect(User::find($future->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run, expect fail (command doesn't exist)**

```bash
./vendor/bin/pest tests/Feature/Account/CronJobsTest.php
```

- [ ] **Step 3: Write command**

`app/Console/Commands/ExecuteScheduledDeletions.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExecuteScheduledDeletions extends Command
{
    protected $signature = 'accounts:execute-scheduled-deletions';

    protected $description = 'Execute account deletions whose deletion_scheduled_for has passed.';

    public function handle(AccountDeletionService $service): int
    {
        $users = User::whereNull('deleted_at')
            ->whereNotNull('deletion_scheduled_for')
            ->where('deletion_scheduled_for', '<=', now())
            ->get();

        $this->info("Processing {$users->count()} scheduled deletion(s).");

        foreach ($users as $user) {
            try {
                $service->deleteAccount(
                    $user,
                    $user->deletion_reason,
                    $user->deletion_source
                );
                $this->info("Deleted user #{$user->id}.");
            } catch (\Throwable $e) {
                Log::error('Scheduled deletion failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed user #{$user->id}: {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test, expect pass**

```bash
./vendor/bin/pest tests/Feature/Account/CronJobsTest.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ExecuteScheduledDeletions.php tests/Feature/Account/CronJobsTest.php
git commit -m "feat(cron): accounts:execute-scheduled-deletions"
```

---

### Task 5.2: `ExecuteGraceDeletions` (replaces `PurgeExpiredUserData`)

**Files:**

- Create: `app/Console/Commands/ExecuteGraceDeletions.php`
- Delete: `app/Console/Commands/PurgeExpiredUserData.php`

- [ ] **Step 1: Add test**

```php
it('accounts:execute-grace-deletions soft-deletes users whose 30-day grace ended', function () {
    $user = User::factory()->create();
    \DB::table('subscriptions')->insert([
        'user_id' => $user->id,
        'plan' => 'pro',
        'billing_cycle' => 'monthly',
        'status' => 'expired',
        'data_retention_starts_at' => now()->subDays(31),
        'amount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Artisan::call('accounts:execute-grace-deletions');

    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
    expect(User::withTrashed()->find($user->id)->deletion_reason)
        ->toBeIn(['trial_expired','subscription_cancelled_grace_ended']);
});

it('accounts:execute-grace-deletions skips preview users', function () {
    $user = User::factory()->create(['is_preview_user' => true]);
    \DB::table('subscriptions')->insert([
        'user_id' => $user->id,
        'plan' => 'pro',
        'billing_cycle' => 'monthly',
        'status' => 'expired',
        'data_retention_starts_at' => now()->subDays(31),
        'amount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Artisan::call('accounts:execute-grace-deletions');

    expect(User::find($user->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run, expect fail**

- [ ] **Step 3: Write command**

`app/Console/Commands/ExecuteGraceDeletions.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Account\AccountDeletionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExecuteGraceDeletions extends Command
{
    protected $signature = 'accounts:execute-grace-deletions';

    protected $description = 'Soft-delete users whose 30-day post-expiry grace period has ended.';

    public function handle(AccountDeletionService $service): int
    {
        $cutoff = Carbon::now()->startOfDay()->subDays(30);

        $subscriptions = Subscription::where('status', 'expired')
            ->whereNotNull('data_retention_starts_at')
            ->where('data_retention_starts_at', '<=', $cutoff)
            ->with('user')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No users past the 30-day grace period.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($subscriptions as $sub) {
            $user = $sub->user;
            if (! $user || $user->trashed() || $user->is_preview_user) {
                continue;
            }

            // Pick the right reason based on how the subscription got to expired
            $reason = $sub->trial_started_at
                ? 'trial_expired'
                : 'subscription_cancelled_grace_ended';

            try {
                $service->deleteAccount($user, $reason, 'auto_expiration_grace');
                $this->info("Deleted user #{$user->id} (reason: {$reason}).");
                $count++;
            } catch (\Throwable $e) {
                Log::error('Grace deletion failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed user #{$user->id}: {$e->getMessage()}");
            }
        }

        $this->info("Processed {$count} grace-period deletion(s).");
        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Delete obsolete command**

```bash
git rm app/Console/Commands/PurgeExpiredUserData.php
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/pest tests/Feature/Account/CronJobsTest.php
```

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ExecuteGraceDeletions.php tests/Feature/Account/CronJobsTest.php
git commit -m "feat(cron): accounts:execute-grace-deletions, replace PurgeExpiredUserData"
```

---

### Task 5.3: `SendDeletionReminders`

**Files:**

- Create: `app/Console/Commands/SendDeletionReminders.php`

The existing `data_retention_email_log` table can be reused for idempotency (it already has `id`, `subscription_id`, `day_number`, `sent_at`). For account-deletion reminders we need a per-user log keyed differently. Add a small migration:

- Create: `database/migrations/2026_05_07_000004_create_account_deletion_reminder_log_table.php`

- [ ] **Step 1: Migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_deletion_reminder_log')) {
            return;
        }

        Schema::create('account_deletion_reminder_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('days_remaining'); // 7 or 1
            $table->timestamp('sent_at');
            $table->index(['user_id', 'days_remaining']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_reminder_log');
    }
};
```

- [ ] **Step 2: Run migration + reseed**

```bash
php artisan migrate && php artisan db:seed
```

- [ ] **Step 3: Add test**

```php
it('accounts:send-deletion-reminders sends 7-day and 1-day reminders idempotently', function () {
    $u7 = User::factory()->create([
        'deletion_scheduled_for' => now()->addDays(7)->addHour(),
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings_privacy',
    ]);
    $u1 = User::factory()->create([
        'deletion_scheduled_for' => now()->addHours(20),
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings_privacy',
    ]);

    \Artisan::call('accounts:send-deletion-reminders');
    \Artisan::call('accounts:send-deletion-reminders'); // idempotent

    Mail::assertQueuedCount(\App\Mail\Account\AccountDeletionReminder7DaysEmail::class, 1);
    Mail::assertQueuedCount(\App\Mail\Account\AccountDeletionReminder1DayEmail::class, 1);
});
```

- [ ] **Step 4: Write the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Account\AccountDeletionReminder1DayEmail;
use App\Mail\Account\AccountDeletionReminder7DaysEmail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendDeletionReminders extends Command
{
    protected $signature = 'accounts:send-deletion-reminders';

    protected $description = 'Send 7-day and 1-day reminders before scheduled account deletion.';

    public function handle(): int
    {
        $this->sendForWindow(7, AccountDeletionReminder7DaysEmail::class);
        $this->sendForWindow(1, AccountDeletionReminder1DayEmail::class);
        return Command::SUCCESS;
    }

    private function sendForWindow(int $daysRemaining, string $mailable): void
    {
        $start = now()->addDays($daysRemaining)->subHours(12);
        $end = now()->addDays($daysRemaining)->addHours(12);

        $users = User::whereNull('deleted_at')
            ->whereNotNull('deletion_scheduled_for')
            ->whereBetween('deletion_scheduled_for', [$start, $end])
            ->whereDoesntHave('deletionReminderLog', function ($q) use ($daysRemaining) {
                $q->where('days_remaining', $daysRemaining);
            })
            ->get();

        foreach ($users as $user) {
            Mail::to($user->email)->queue(new $mailable($user, $user->deletion_scheduled_for));
            DB::table('account_deletion_reminder_log')->insert([
                'user_id' => $user->id,
                'days_remaining' => $daysRemaining,
                'sent_at' => now(),
            ]);
        }
    }
}
```

- [ ] **Step 5: Add `deletionReminderLog` relation to User model**

```php
public function deletionReminderLog()
{
    return $this->hasMany(\App\Models\AccountDeletionReminderLog::class);
}
```

And create the model:

`app/Models/AccountDeletionReminderLog.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountDeletionReminderLog extends Model
{
    public $timestamps = false;
    protected $table = 'account_deletion_reminder_log';
    protected $fillable = ['user_id', 'days_remaining', 'sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
}
```

- [ ] **Step 6: Run tests**

```bash
./vendor/bin/pest tests/Feature/Account/CronJobsTest.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Console/Commands/SendDeletionReminders.php app/Models/AccountDeletionReminderLog.php app/Models/User.php database/migrations/2026_05_07_000004_create_account_deletion_reminder_log_table.php tests/Feature/Account/CronJobsTest.php
git commit -m "feat(cron): accounts:send-deletion-reminders with idempotency log"
```

---

### Task 5.4: `PurgeAfterRetention`

**Files:**

- Create: `app/Console/Commands/PurgeAfterRetention.php`

- [ ] **Step 1: Test**

```php
it('accounts:purge-after-retention hard-purges users past purge_eligible_at', function () {
    $user = User::factory()->create([
        'deleted_at' => now()->subYears(8),
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings_privacy',
        'purge_eligible_at' => now()->subDays(1),
    ]);

    \Artisan::call('accounts:purge-after-retention');

    // After purge: row may still exist with PII scrubbed, OR may be hard-deleted
    // RetentionPurgeService::purgeUser does soft-delete with PII scrub today.
    $u = User::withTrashed()->find($user->id);
    expect($u->first_name)->toBeNull();
});
```

- [ ] **Step 2: Run, expect fail**

- [ ] **Step 3: Write command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Account\RetentionPurgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeAfterRetention extends Command
{
    protected $signature = 'accounts:purge-after-retention';

    protected $description = 'Hard-purge users whose retention period has elapsed.';

    public function handle(RetentionPurgeService $service): int
    {
        $users = User::onlyTrashed()
            ->whereNotNull('purge_eligible_at')
            ->where('purge_eligible_at', '<=', now())
            ->where(function ($q) {
                $q->where('deletion_reason', '!=', 'legacy_purged')
                  ->orWhereNull('deletion_reason');
            })
            ->get();

        $this->info("Purging {$users->count()} retention-expired user(s).");

        foreach ($users as $user) {
            try {
                $service->purgeUser($user);
                $this->info("Purged user #{$user->id}.");
            } catch (\Throwable $e) {
                Log::error('Retention purge failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed user #{$user->id}: {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Run tests**

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/PurgeAfterRetention.php tests/Feature/Account/CronJobsTest.php
git commit -m "feat(cron): accounts:purge-after-retention"
```

---

### Task 5.5: Update Kernel schedule

**Files:**

- Modify: `app/Console/Kernel.php`

- [ ] **Step 1: Edit schedule()**

In `app/Console/Kernel.php`, find the existing `$schedule->command('trials:expire')->dailyAt('00:05');` line and add immediately after:

```php
$schedule->command('accounts:execute-scheduled-deletions')->dailyAt('00:10');
$schedule->command('accounts:execute-grace-deletions')->dailyAt('00:15');
$schedule->command('accounts:send-deletion-reminders')->dailyAt('00:20');
$schedule->command('accounts:purge-after-retention')->monthlyOn(1, '02:00');
```

Find any existing reference to `data-retention:purge-expired` and remove it (the command was deleted).

- [ ] **Step 2: Verify**

```bash
php artisan schedule:list | grep -E "accounts:|trials:"
```

Expected: shows the 4 new entries + `trials:expire`.

- [ ] **Step 3: Commit**

```bash
git add app/Console/Kernel.php
git commit -m "chore(schedule): wire up account deletion lifecycle crons"
```

---

## Phase 6 — Auth flow modifications

### Task 6.1: Login detects trashed users (TDD)

**Files:**

- Modify: `app/Http/Controllers/Api/AuthController.php`
- Test: `tests/Feature/Account/RestorationFlowTest.php`

- [ ] **Step 1: Read existing AuthController::login (around line 122)**

```bash
sed -n '110,260p' app/Http/Controllers/Api/AuthController.php
```

Identify the path that runs `Auth::attempt()` / `Hash::check()` — this is where you splice in the trashed-user branch.

- [ ] **Step 2: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Account\AccountDeletionService;

it('login of soft-deleted user with correct password returns restorable response', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-pass')]);
    app(AccountDeletionService::class)->deleteAccount($user, 'user_requested', 'settings_privacy');

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'correct-pass',
    ]);

    $response->assertOk()
        ->assertJsonPath('account_deleted_restorable', true)
        ->assertJsonStructure(['restoration_token','deleted_at','deletion_reason','first_name']);
});

it('login of soft-deleted user with WRONG password returns generic 401', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-pass')]);
    app(AccountDeletionService::class)->deleteAccount($user, 'user_requested', 'settings_privacy');

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-pass',
    ]);

    $response->assertStatus(401);
    expect($response->json('account_deleted_restorable'))->toBeNull(); // no enumeration leak
});

it('login of legacy_purged user returns generic 401', function () {
    $user = User::factory()->create([
        'password' => bcrypt('correct-pass'),
        'deleted_at' => now()->subYear(),
        'deletion_reason' => 'legacy_purged',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'correct-pass',
    ]);

    $response->assertStatus(401);
});
```

- [ ] **Step 3: Run, expect fail**

- [ ] **Step 4: Splice the trashed-user branch into AuthController::login**

At the very top of the `login()` method body (right after `$request->validated()` or whatever pulls credentials), add:

```php
// Trashed-user detection: only reveal restorability after correct password
$candidate = \App\Models\User::withTrashed()->where('email', $request->input('email'))->first();
if ($candidate && $candidate->trashed()) {
    if (! \Illuminate\Support\Facades\Hash::check($request->input('password'), $candidate->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }
    if ($candidate->deletion_reason === 'legacy_purged') {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }
    return response()->json([
        'account_deleted_restorable' => true,
        'deleted_at' => $candidate->deleted_at->toIso8601String(),
        'deletion_reason' => $candidate->deletion_reason,
        'deletion_source' => $candidate->deletion_source,
        'first_name' => $candidate->first_name,
        'restoration_token' => $this->issueRestorationToken($candidate),
    ]);
}
```

Add a private helper at the bottom of the class:

```php
private function issueRestorationToken(\App\Models\User $user): string
{
    $token = \Illuminate\Support\Str::random(64);
    \Illuminate\Support\Facades\Cache::put(
        "restoration_token:{$token}",
        ['user_id' => $user->id, 'issued_at' => now()->toIso8601String()],
        now()->addMinutes(5)
    );
    return $token;
}
```

- [ ] **Step 5: Run tests, expect pass**

```bash
./vendor/bin/pest tests/Feature/Account/RestorationFlowTest.php
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php tests/Feature/Account/RestorationFlowTest.php
git commit -m "feat(auth): login returns restorable response for trashed users with correct password"
```

---

### Task 6.2: Register detects trashed emails

**Files:**

- Modify: `app/Http/Controllers/Api/AuthController.php` (`register()` method, line 58)

- [ ] **Step 1: Add tests**

Append to `RestorationFlowTest.php`:

```php
it('register with email of soft-deleted user returns restorable response', function () {
    $existing = User::factory()->create(['password' => bcrypt('old-pass')]);
    app(AccountDeletionService::class)->deleteAccount($existing, 'user_requested', 'settings_privacy');

    $response = $this->postJson('/api/auth/register', [
        'email' => $existing->email,
        'password' => 'new-attempted-pass',
        'first_name' => 'Different',
        'surname' => 'Person',
        // ...other registration fields per RegisterRequest
    ]);

    $response->assertOk()
        ->assertJsonPath('account_deleted_restorable', true)
        ->assertJsonPath('requires_password_verification', true);
});
```

(If the test errors on missing registration fields, copy the field list from `app/Http/Requests/RegisterRequest.php` and supply minimal valid values for everything except email.)

- [ ] **Step 2: Run, expect fail**

- [ ] **Step 3: Add trashed-user branch in `register()`**

At the top of `register()`:

```php
$existing = \App\Models\User::withTrashed()->where('email', $request->input('email'))->first();
if ($existing && $existing->trashed() && $existing->deletion_reason !== 'legacy_purged') {
    return response()->json([
        'account_deleted_restorable' => true,
        'requires_password_verification' => true,
        'deleted_at' => $existing->deleted_at->toIso8601String(),
        'deletion_reason' => $existing->deletion_reason,
        'first_name' => $existing->first_name,
    ]);
}
```

(Existing live-user "email already taken" validation handles the non-trashed case unchanged.)

- [ ] **Step 4: Run tests, expect pass**

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php tests/Feature/Account/RestorationFlowTest.php
git commit -m "feat(auth): register returns restorable response for trashed-email"
```

---

### Task 6.3: `RestoreAccountController` — check + restore endpoints

**Files:**

- Create: `app/Http/Controllers/Api/Auth/RestoreAccountController.php`
- Create: `app/Http/Requests/Account/RestoreAccountRequest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Add tests**

Append:

```php
it('restore endpoint with valid token un-soft-deletes the user and returns Sanctum token', function () {
    $user = User::factory()->create(['password' => bcrypt('p')]);
    app(AccountDeletionService::class)->deleteAccount($user, 'user_requested', 'settings_privacy');

    // Get the restoration token via login flow
    $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'p']);
    $token = $login->json('restoration_token');

    $response = $this->postJson('/api/auth/restore', ['restoration_token' => $token]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'email'], 'redirect_to']);

    expect(User::find($user->id))->not->toBeNull();
    expect(User::find($user->id)->trashed())->toBeFalse();
});

it('restore endpoint with invalid token returns 401', function () {
    $response = $this->postJson('/api/auth/restore', ['restoration_token' => 'definitely-not-real']);
    $response->assertStatus(401);
});

it('restore/check endpoint with email + password returns a fresh restoration_token', function () {
    $user = User::factory()->create(['password' => bcrypt('p')]);
    app(AccountDeletionService::class)->deleteAccount($user, 'user_requested', 'settings_privacy');

    $response = $this->postJson('/api/auth/restore/check', [
        'email' => $user->email, 'password' => 'p',
    ]);

    $response->assertOk()->assertJsonStructure(['restoration_token']);
});
```

- [ ] **Step 2: Run, expect fail**

- [ ] **Step 3: Write controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RestoreAccountController extends Controller
{
    public function __construct(
        private readonly AccountDeletionService $service
    ) {}

    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::withTrashed()->where('email', $request->input('email'))->first();
        if (! $user || ! $user->trashed()) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if ($user->deletion_reason === 'legacy_purged') {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json(['restoration_token' => $this->issueToken($user)]);
    }

    public function restore(Request $request): JsonResponse
    {
        $request->validate(['restoration_token' => 'required|string']);

        $cached = Cache::pull('restoration_token:' . $request->input('restoration_token'));
        if (! $cached) {
            return response()->json(['message' => 'Invalid or expired restoration token'], 401);
        }

        $user = User::withTrashed()->find($cached['user_id']);
        if (! $user || ! $user->canBeRestored()) {
            return response()->json(['message' => 'Account cannot be restored'], 401);
        }

        $this->service->restoreAccount($user);
        $user->refresh();

        $token = $user->createToken('restored-session')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->only(['id', 'email', 'first_name', 'surname']),
            'redirect_to' => '/subscription/select',
        ]);
    }

    private function issueToken(User $user): string
    {
        $token = Str::random(64);
        Cache::put(
            "restoration_token:{$token}",
            ['user_id' => $user->id, 'issued_at' => now()->toIso8601String()],
            now()->addMinutes(5)
        );
        return $token;
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/api.php`, find the `/auth/` route group (it has `/login`, `/register` etc.) and add:

```php
Route::post('/restore/check', [\App\Http\Controllers\Api\Auth\RestoreAccountController::class, 'check'])
    ->middleware('throttle:5,1');
Route::post('/restore', [\App\Http\Controllers\Api\Auth\RestoreAccountController::class, 'restore'])
    ->middleware('throttle:5,1');
```

- [ ] **Step 5: Add to `PreviewWriteInterceptor::EXCLUDED_ROUTES`**

In `app/Http/Middleware/PreviewWriteInterceptor.php`, add to the `EXCLUDED_ROUTES` array:

```php
'/api/auth/restore',
'/api/auth/restore/check',
'/api/auth/gdpr/erasure/cancel-scheduled',
```

(Per CLAUDE.md rule #8.)

- [ ] **Step 6: Run tests**

```bash
./vendor/bin/pest tests/Feature/Account/RestorationFlowTest.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/Auth/RestoreAccountController.php app/Http/Middleware/PreviewWriteInterceptor.php routes/api.php tests/Feature/Account/RestorationFlowTest.php
git commit -m "feat(auth): /api/auth/restore + /api/auth/restore/check endpoints"
```

---

## Phase 7 — Repoint existing controllers

### Task 7.1: GDPRController.executeErasure → AccountDeletionService

**Files:**

- Modify: `app/Http/Controllers/Api/GDPRController.php`

- [ ] **Step 1: Add a feature test for the schedule branch**

`tests/Feature/Account/DeletionTriggerPathsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;

it('Settings → Privacy on user with active paid sub schedules deletion at current_period_end', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'current_period_end' => now()->addDays(20),
    ]);
    \Sanctum::actingAs($user);

    // Bypass verification wizard: assume the session has been verified
    $sessionToken = $this->initiateAndVerifyErasure($user, 'account');

    $response = $this->postJson('/api/auth/gdpr/erasure/execute', [
        'session_token' => $sessionToken,
        'confirmation_text' => 'Delete my Account',
    ]);

    $response->assertOk();
    expect(User::find($user->id)->deletion_scheduled_for)->not->toBeNull();
    expect(User::find($user->id)->trashed())->toBeFalse();
});

it('Settings → Privacy on user with no paid sub deletes immediately', function () {
    $user = User::factory()->create();
    \Sanctum::actingAs($user);

    $sessionToken = $this->initiateAndVerifyErasure($user, 'account');

    $response = $this->postJson('/api/auth/gdpr/erasure/execute', [
        'session_token' => $sessionToken,
        'confirmation_text' => 'Delete my Account',
    ]);

    $response->assertOk();
    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});
```

(Stub `initiateAndVerifyErasure()` as a Pest helper that calls `/initiate` + `/verify` so tests don't have to repeat the wizard each time. Define it in `tests/Pest.php` or a trait `InteractsWithErasureWizard`.)

- [ ] **Step 2: Run, expect fail**

- [ ] **Step 3: Modify GDPRController**

In `app/Http/Controllers/Api/GDPRController.php`:

- Replace `use App\Services\GDPR\DataErasureService;` with `use App\Services\Account\AccountDeletionService;`.
- Replace `private readonly DataErasureService $erasureService` with `private readonly AccountDeletionService $deletionService` in the constructor.
- In `executeErasure` (line 475), the section that today calls `$this->erasureService->processErasure(...)`. Replace with:

```php
if ($type === 'account') {
    $sub = $user->subscription;
    $hasActivePaid = $sub
        && $sub->status === 'active'
        && $sub->current_period_end
        && $sub->current_period_end->isFuture();

    if ($hasActivePaid) {
        $this->deletionService->scheduleDeletion(
            $user,
            'user_requested',
            'settings_privacy',
            $sub->current_period_end
        );
        return response()->json([
            'success' => true,
            'message' => 'Your account is scheduled for deletion on ' . $sub->current_period_end->format('j F Y') . '.',
            'logout_required' => false,
            'scheduled_deletion_at' => $sub->current_period_end->toIso8601String(),
        ]);
    }

    $this->deletionService->deleteAccount($user, 'user_requested', 'settings_privacy');
    return response()->json([
        'success' => true,
        'message' => 'Your account has been deleted.',
        'logout_required' => true,
    ]);
}

if ($type === 'data') {
    // Existing data-only flow remains; if it currently uses DataErasureService::deleteDataOnly,
    // that method does NOT need to be in the new service — the only data-only operation is
    // resetting profile fields. Inline it here:
    $user->update([
        'employment_status' => null,
        'salary' => null,
        'national_insurance_number' => null,
    ]);
    $this->auditService->logGDPR(\App\Models\AuditLog::ACTION_ERASURE_COMPLETED, $user->id, ['type' => 'data_only']);

    return response()->json([
        'success' => true,
        'message' => 'Your data has been deleted.',
        'logout_required' => false,
    ]);
}
```

- Replace any remaining `$this->erasureService->...` references in `requestErasure()` / `confirmErasure()` / `verifyErasure()`. The `ErasureRequest` row + audit logging can be inlined; the service was a thin wrapper. Read the current method bodies and replace `$this->erasureService->requestErasure($user, ...)` with `\App\Models\ErasureRequest::create([...])` directly, etc.

- [ ] **Step 4: Add new method `cancelScheduledDeletion`**

```php
public function cancelScheduledDeletion(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
{
    $user = $request->user();
    if (! $user->isScheduledForDeletion()) {
        return response()->json(['success' => false, 'message' => 'No scheduled deletion to cancel.'], 422);
    }

    $this->deletionService->cancelScheduledDeletion($user);

    return response()->json(['success' => true, 'message' => 'Scheduled deletion cancelled.']);
}
```

- [ ] **Step 5: Add the route in `routes/api.php`**

```php
Route::post('/auth/gdpr/erasure/cancel-scheduled', [
    \App\Http\Controllers\Api\GDPRController::class,
    'cancelScheduledDeletion'
])->middleware(['auth:sanctum', 'throttle:5,1']);
```

- [ ] **Step 6: Run tests**

```bash
./vendor/bin/pest tests/Feature/Account/DeletionTriggerPathsTest.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/GDPRController.php routes/api.php tests/Feature/Account/DeletionTriggerPathsTest.php
git commit -m "refactor(gdpr): repoint executeErasure to AccountDeletionService; add cancel-scheduled"
```

---

### Task 7.2: PaymentController.deleteAllData → AccountDeletionService

**Files:**

- Modify: `app/Http/Controllers/Api/PaymentController.php`

- [ ] **Step 1: Add test**

```php
it('payment.deleteAllData calls AccountDeletionService and clears tokens/sessions', function () {
    $user = User::factory()->create(['password' => bcrypt('p')]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'data_retention_starts_at' => now()->subDays(5),
    ]);
    \Sanctum::actingAs($user);

    $response = $this->postJson('/api/payment/delete-all-data', [
        'confirmation_text' => 'DELETE',
        'current_password' => 'p',
    ]);

    $response->assertOk();
    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});
```

- [ ] **Step 2: Run, expect fail**

- [ ] **Step 3: Modify PaymentController**

- Replace `use App\Services\Payment\DataPurgeService;` with `use App\Services\Account\AccountDeletionService;`.
- Replace `private readonly DataPurgeService $purgeService` with `private readonly AccountDeletionService $deletionService` in the constructor.
- Inside `deleteAllData()` (line 775), replace the body of the `try` block that today calls `$this->purgeService->purgeUserData($user);` with:

```php
$this->deletionService->deleteAccount($user, 'user_requested', 'expiration_modal');

return response()->json([
    'success' => true,
    'message' => 'Your account has been deleted.',
]);
```

The existing pre-deletion validation (preview check, password check, "DELETE" confirmation, grace-period check, email send) stays — but the `Mail::to(...)->send(new DataDeletionConfirmation(...))` call is now redundant because the service queues `AccountDeletionConfirmationEmail`. Remove the duplicate.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/pest tests/Feature/Account/DeletionTriggerPathsTest.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/PaymentController.php tests/Feature/Account/DeletionTriggerPathsTest.php
git commit -m "refactor(payment): repoint deleteAllData to AccountDeletionService"
```

---

### Task 7.3: Delete obsolete `DataErasureService`

**Files:**

- Delete: `app/Services/GDPR/DataErasureService.php`

- [ ] **Step 1: Verify no callers remain**

```bash
grep -rn "DataErasureService" app/ tests/ 2>/dev/null
```

Expected: no results outside the file itself.

- [ ] **Step 2: Delete**

```bash
git rm app/Services/GDPR/DataErasureService.php
```

- [ ] **Step 3: Verify backend tests still pass**

```bash
./vendor/bin/pest --testsuite=Unit && ./vendor/bin/pest --testsuite=Feature
```

- [ ] **Step 4: Commit**

```bash
git commit -m "chore: remove obsolete DataErasureService"
```

---

## Phase 8 — Frontend: services + modal + banner

### Task 8.1: `authService.js` restore methods

**Files:**

- Modify: `resources/js/services/authService.js`

- [ ] **Step 1: Add methods**

Append (before the final closing `}`/export):

```javascript
async restoreCheck(email, password) {
  const response = await api.post('/auth/restore/check', { email, password });
  return response.data;
},

async restore(restorationToken) {
  const response = await api.post('/auth/restore', { restoration_token: restorationToken });
  return response.data;
},
```

- [ ] **Step 2: Smoke-test by browser-fetching after the modal lands. No commit yet — bundled with the modal in next task.**

---

### Task 8.2: `gdprService.js` cancel-scheduled

**Files:**

- Modify: `resources/js/services/gdprService.js`

- [ ] **Step 1: Add method**

```javascript
async cancelScheduledDeletion() {
  const response = await api.post('/auth/gdpr/erasure/cancel-scheduled');
  return response.data;
},
```

(File location: read with `grep -l "erasure/initiate" resources/js/services/` if uncertain.)

---

### Task 8.3: `RestoreAccountModal.vue`

**Files:**

- Create: `resources/js/components/Account/RestoreAccountModal.vue`

- [ ] **Step 1: Component**

```vue
<template>
  <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-horizon-600/60" @click="onCancel"></div>

    <div class="relative bg-white rounded-lg shadow-2xl max-w-lg w-full p-8">
      <h2 class="text-h3 text-horizon-500 mb-4">Welcome back, {{ firstName || 'there' }}</h2>

      <p class="text-body text-neutral-500 mb-4">
        We have a record of your previous Fynla account, deleted on
        <strong>{{ deletedDate }}</strong>.
      </p>

      <p class="text-body text-neutral-500 mb-6">
        Your data has been retained for regulatory compliance, and we can restore your account now.
        You'll need to choose a subscription plan after restoration.
      </p>

      <div v-if="requiresPasswordVerification" class="mb-4">
        <label class="block text-body-sm text-neutral-500 mb-1.5">
          Please confirm your password to restore:
        </label>
        <input
          v-model="passwordInput"
          type="password"
          autocomplete="current-password"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md text-sm
                 focus:outline-none focus:ring-2 focus:ring-raspberry-500 focus:border-raspberry-500"
        />
      </div>

      <div v-if="error" class="bg-raspberry-100 border border-raspberry-600/20 rounded-lg p-3 mb-4">
        <p class="text-body-sm text-raspberry-600">{{ error }}</p>
      </div>

      <div class="flex gap-3">
        <button class="btn-secondary flex-1" :disabled="loading" @click="onCancel">Cancel</button>
        <button class="btn-primary flex-1" :disabled="loading || !canRestore" @click="onRestore">
          <span v-if="loading">Restoring...</span>
          <span v-else>Restore my account</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import authService from '@/services/authService';
import logger from '@/utils/logger';

export default {
  name: 'RestoreAccountModal',
  props: {
    visible: { type: Boolean, default: false },
    firstName: { type: String, default: '' },
    deletedAt: { type: String, default: '' },
    requiresPasswordVerification: { type: Boolean, default: false },
    email: { type: String, default: '' },
    restorationToken: { type: String, default: '' },
  },
  emits: ['cancel', 'restored'],
  data() {
    return {
      passwordInput: '',
      loading: false,
      error: null,
    };
  },
  computed: {
    deletedDate() {
      if (!this.deletedAt) return '';
      return new Date(this.deletedAt).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'long', year: 'numeric',
      });
    },
    canRestore() {
      if (this.requiresPasswordVerification) return this.passwordInput.length > 0;
      return !!this.restorationToken;
    },
  },
  methods: {
    onCancel() {
      this.passwordInput = '';
      this.error = null;
      this.$emit('cancel');
    },
    async onRestore() {
      this.loading = true;
      this.error = null;
      try {
        let token = this.restorationToken;
        if (this.requiresPasswordVerification) {
          const res = await authService.restoreCheck(this.email, this.passwordInput);
          token = res.restoration_token;
        }
        const result = await authService.restore(token);
        this.$emit('restored', result);
      } catch (e) {
        logger.error('RestoreAccountModal restore failed', e);
        this.error = e.response?.data?.message || 'Could not restore your account. Please try again.';
        this.loading = false;
      }
    },
  },
};
</script>
```

- [ ] **Step 2: Browser test plan (after Login.vue wiring in next phase)**

---

### Task 8.4: `ScheduledDeletionBanner.vue`

**Files:**

- Create: `resources/js/components/Account/ScheduledDeletionBanner.vue`
- Modify: `resources/js/layouts/AppLayout.vue`

- [ ] **Step 1: Component**

```vue
<template>
  <div v-if="scheduled" class="bg-violet-100 border-b border-violet-300 px-4 py-3">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
      <p class="text-body-sm text-violet-700">
        Your account is scheduled for deletion on <strong>{{ formattedDate }}</strong>
        ({{ daysRemaining }} {{ daysRemaining === 1 ? 'day' : 'days' }}).
      </p>
      <button class="btn-secondary text-body-sm" @click="cancelDeletion" :disabled="cancelling">
        <span v-if="cancelling">Cancelling…</span>
        <span v-else>Cancel scheduled deletion</span>
      </button>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import gdprService from '@/services/gdprService';
import logger from '@/utils/logger';

export default {
  name: 'ScheduledDeletionBanner',
  data() { return { cancelling: false }; },
  computed: {
    ...mapState({
      scheduledFor: state => state.auth.user?.deletion_scheduled_for,
    }),
    scheduled() { return !!this.scheduledFor; },
    formattedDate() {
      return this.scheduledFor
        ? new Date(this.scheduledFor).toLocaleDateString('en-GB',
          { day: 'numeric', month: 'long', year: 'numeric' })
        : '';
    },
    daysRemaining() {
      if (!this.scheduledFor) return 0;
      const diff = new Date(this.scheduledFor) - new Date();
      return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
    },
  },
  methods: {
    async cancelDeletion() {
      this.cancelling = true;
      try {
        await gdprService.cancelScheduledDeletion();
        await this.$store.dispatch('auth/fetchUser');
      } catch (e) {
        logger.error('Cancel scheduled deletion failed', e);
        alert('Could not cancel. Please try again.');
      } finally {
        this.cancelling = false;
      }
    },
  },
};
</script>
```

- [ ] **Step 2: Mount in `AppLayout.vue`**

In `resources/js/layouts/AppLayout.vue`, find the existing `<TrialCountdownBanner />` mount and add immediately above it:

```vue
<ScheduledDeletionBanner />
```

Add to imports/components in the script block.

- [ ] **Step 3: Verify auth user payload includes `deletion_scheduled_for`**

Check `app/Http/Controllers/Api/AuthController.php::user()` returns this field. If the response is built via `UserResource` or similar, ensure it's included. Add if missing.

- [ ] **Step 4: Browser-test the banner appears for a scheduled user**

```bash
./dev.sh
```

Then in the browser: log in as a test user (`john@example.com` / `password`), use tinker to set `deletion_scheduled_for`:

```bash
php artisan tinker --execute="\App\Models\User::where('email','john@example.com')->update(['deletion_scheduled_for' => now()->addDays(10)]);"
```

Refresh dashboard → verify banner shows. Click Cancel → verify it disappears (and DB column clears).

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/Account/ resources/js/layouts/AppLayout.vue resources/js/services/authService.js resources/js/services/gdprService.js
git commit -m "feat(ui): RestoreAccountModal + ScheduledDeletionBanner + service methods"
```

---

## Phase 9 — Frontend: existing UI surfaces

### Task 9.1: `Login.vue` and `Register.vue` handle restorable response

**Files:**

- Modify: `resources/js/views/Auth/Login.vue`
- Modify: `resources/js/views/Auth/Register.vue`

(Locate the actual file paths first — the project may have these at `views/Auth/` or `views/Login.vue`. Use `grep -rln "function login\|authService.login" resources/js/views/`.)

- [ ] **Step 1: In Login.vue's submit handler, handle the new response shape**

After receiving the response from `authService.login(...)`:

```javascript
if (response.account_deleted_restorable) {
  this.restoreModal = {
    visible: true,
    firstName: response.first_name,
    deletedAt: response.deleted_at,
    restorationToken: response.restoration_token,
    requiresPasswordVerification: false,
    email: this.email,
  };
  this.loading = false;
  return;
}
```

Mount `<RestoreAccountModal v-bind="restoreModal" @cancel="restoreModal.visible = false" @restored="onRestored" />` in template.

`onRestored(result)`: store the new `result.token`, dispatch `auth/setUser` with `result.user`, then `this.$router.push(result.redirect_to)`.

- [ ] **Step 2: Same pattern in Register.vue, but `requiresPasswordVerification: true` and pass `email`.**

- [ ] **Step 3: Browser test both flows on a soft-deleted seeded user**

```bash
php artisan tinker --execute="
\$u = \App\Models\User::where('email','sarah@example.com')->first();
app(\App\Services\Account\AccountDeletionService::class)->deleteAccount(\$u, 'user_requested', 'settings_privacy');
"
```

Then in browser:
- Try to log in as `sarah@example.com` / `password` → modal should appear.
- Click "Restore my account" → land on `/subscription/select`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Auth/
git commit -m "feat(ui): login/register show RestoreAccountModal for trashed users"
```

---

### Task 9.2: `PrivacySettings.vue` state-aware

**Files:**

- Modify: `resources/js/views/Settings/PrivacySettings.vue`

- [ ] **Step 1: Add a computed for scheduled state**

```javascript
computed: {
  isScheduledForDeletion() {
    return !!this.$store.state.auth.user?.deletion_scheduled_for;
  },
  scheduledDate() {
    return this.$store.state.auth.user?.deletion_scheduled_for;
  },
},
```

- [ ] **Step 2: Wrap the existing wizard button in `v-if="!isScheduledForDeletion"`**

- [ ] **Step 3: Add an alternate panel `v-else`**

```vue
<div v-else class="bg-violet-100 border border-violet-300 rounded-lg p-6">
  <h3 class="text-h4 text-violet-700 mb-2">Account scheduled for deletion</h3>
  <p class="text-body text-neutral-700 mb-4">
    Your account will be deleted on
    <strong>{{ formatScheduledDate(scheduledDate) }}</strong>.
    Your records will be retained for {{ retentionYears }} years
    after that for regulatory compliance.
  </p>
  <button class="btn-primary" :disabled="cancelling" @click="onCancelScheduled">
    {{ cancelling ? 'Cancelling…' : 'Cancel scheduled deletion' }}
  </button>
</div>
```

- [ ] **Step 4: Update wizard step-3 confirmation copy**

When the user is confirming a deletion in the wizard, the copy should reflect whether scheduling will happen. Add a computed `willSchedule` that returns true when the user has an active subscription with `current_period_end` in the future. Then conditionally render two confirmation strings.

(Reference §10.1 of `design.md` for the exact wording.)

- [ ] **Step 5: Add `onCancelScheduled` method**

```javascript
async onCancelScheduled() {
  this.cancelling = true;
  try {
    await gdprService.cancelScheduledDeletion();
    await this.$store.dispatch('auth/fetchUser');
  } catch (e) {
    this.error = e.response?.data?.message || 'Could not cancel. Please try again.';
  } finally {
    this.cancelling = false;
  }
},
```

- [ ] **Step 6: Browser-test all three states**

A → wizard → schedule for paid user → State B → cancel → back to A.
A → wizard → immediate delete for free user → user logged out, lands on /login.

- [ ] **Step 7: Commit**

```bash
git add resources/js/views/Settings/PrivacySettings.vue
git commit -m "feat(ui): PrivacySettings.vue state-aware (active / scheduled)"
```

---

### Task 9.3: `DataRetentionOverlay.vue` copy + redirect

**Files:**

- Modify: `resources/js/components/Payment/DataRetentionOverlay.vue`

- [ ] **Step 1: Update the warning copy in step-3 confirmation**

Replace:

```
This action is permanent and cannot be undone. All your financial plans, policies, pensions, investments, savings, goals, and documents will be deleted.
```

With:

```
Your account will be deleted. Your records are retained for {{ retentionYears }} years for regulatory compliance, after which they are permanently removed. You can restore your account at any time within that period by signing in.
```

Add `retentionYears: 7` to data (or import from a constants module).

- [ ] **Step 2: Update success redirect**

In `confirmDeleteAll`, change:

```javascript
window.location.href = '/';
```

to:

```javascript
window.location.href = '/login';
```

- [ ] **Step 3: Browser-test the full flow on an expired-grace test user**

(May require seeding such a state in tinker.)

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Payment/DataRetentionOverlay.vue
git commit -m "feat(ui): retention overlay copy + redirect to /login post-delete"
```

---

### Task 9.4: Joint-owner `(Deactivated)` badges

**Files:**

- Modify: card components that display joint-owner names. Find them with:

```bash
grep -rln "joint_owner\|jointOwner" resources/js/components/ | head -20
```

Likely candidates:
- `resources/js/components/Property/PropertyCard.vue`
- `resources/js/components/Savings/SavingsAccountCard.vue`
- `resources/js/components/Investment/InvestmentAccountCard.vue`

- [ ] **Step 1: Add a small inline badge wherever joint owner is rendered**

Pattern (apply to each component):

```vue
<span>
  {{ jointOwnerName }}
  <span v-if="jointOwner?.deleted_at" class="ml-1 text-caption text-neutral-400">(Deactivated)</span>
</span>
```

This requires the API to surface `deleted_at` on the joint-owner mini-payload. Update the relevant Resource (e.g. `PropertyResource`) to include it via `withTrashed`-aware loading. Backend changes per resource:

```php
// In a Resource where joint_owner is included:
'joint_owner' => $this->whenLoaded('jointOwner', fn () => [
    'id' => $this->jointOwner->id,
    'first_name' => $this->jointOwner->first_name,
    'deleted_at' => $this->jointOwner->deleted_at,
]),
```

The model relation must use `withTrashed`:

```php
public function jointOwner()
{
    return $this->belongsTo(User::class, 'joint_owner_id')->withTrashed();
}
```

Apply this to each model with a `joint_owner_id` (Property, SavingsAccount, InvestmentAccount, Mortgage, BusinessInterest, Chattel, Goal, LifeEvent, Liability).

- [ ] **Step 2: Browser-test on a seeded user with a joint owner who is then soft-deleted**

```bash
php artisan tinker --execute="
\$j = \App\Models\User::factory()->create();
\App\Models\Property::factory()->create(['user_id' => 1, 'joint_owner_id' => \$j->id, 'ownership_type' => 'joint', 'ownership_percentage' => 50]);
app(\App\Services\Account\AccountDeletionService::class)->deleteAccount(\$j, 'user_requested', 'settings_privacy');
"
```

Log in as user 1 → verify Property card shows the joint owner with `(Deactivated)`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/ app/Models/ app/Http/Resources/
git commit -m "feat(ui): show (Deactivated) badge for soft-deleted joint owners"
```

---

## Phase 10 — End-to-end Playwright verification

Per CLAUDE.md "CRITICAL — Browser Testing Rules": every interactive flow below must be CLICKED, FILLED, SUBMITTED in Playwright. No "verified" without actual interaction.

### Task 10.1: Settings → Privacy → Delete on paid user (schedules)

- [ ] **Step 1: Seed a user with active subscription with future period_end**

```bash
php artisan db:seed
php artisan tinker --execute="
\$u = \App\Models\User::where('email','john@example.com')->first();
\App\Models\Subscription::updateOrCreate(['user_id' => \$u->id], [
    'plan' => 'pro', 'billing_cycle' => 'monthly', 'status' => 'active',
    'current_period_end' => now()->addDays(15), 'amount' => 0,
]);
"
```

- [ ] **Step 2: Drive the journey in Playwright**

Login → Settings → Privacy → click Delete → wizard step 1 (Account) → step 2 (verification code, fetched from DB per CLAUDE.md auth note) → step 3 confirmation typed phrase → submit → assert success toast → reload dashboard → assert banner appears with the future date and Cancel button.

- [ ] **Step 3: Cancel and verify**

Click Cancel on banner → assert banner disappears → DB check: `deletion_scheduled_for IS NULL`.

- [ ] **Step 4: Commit any test fixtures or screenshots**

---

### Task 10.2: Settings → Privacy → Delete on free user (immediate)

- [ ] **Step 1: Seed a free user (no subscription, or expired)**

- [ ] **Step 2: Drive the wizard end-to-end**

After confirmation, expect: logout, redirect to `/login`, login attempt with same credentials returns RestoreAccountModal.

- [ ] **Step 3: Don't restore in this test — just verify the soft-deleted state in DB**

```bash
php artisan tinker --execute="echo \App\Models\User::onlyTrashed()->where('email','...')->first()?->deletion_reason;"
```

Expected: `user_requested`.

---

### Task 10.3: Trial expiry → grace → auto-delete cron

- [ ] **Step 1: Seed expired trial subscription with `data_retention_starts_at = now()->subDays(31)`**

- [ ] **Step 2: Run the cron**

```bash
php artisan accounts:execute-grace-deletions
```

- [ ] **Step 3: Verify in DB and via login attempt (Playwright)**

User soft-deleted, login returns RestoreAccountModal.

---

### Task 10.4: Retention overlay CTA path

- [ ] **Step 1: Seed user in grace period (subscription expired, within 30 days)**

- [ ] **Step 2: Login as user → DataRetentionOverlay should appear**

- [ ] **Step 3: Click "Delete All Data & Start Again" → enter password + DELETE → submit**

- [ ] **Step 4: Verify redirect to `/login` and DB state**

---

### Task 10.5: Restore via login

- [ ] **Step 1: Take any soft-deleted user (from any prior test)**

- [ ] **Step 2: Playwright: login → modal → restore**

- [ ] **Step 3: Land on `/subscription/select` → verify financial data still present after picking a plan**

---

### Task 10.6: Restore via register

Same but register with the soft-deleted email.

---

### Task 10.7: Joint-owner deactivated badge

Per Task 9.4 step 2. Verify visual.

---

## Phase 11 — Cleanup pass

### Task 11.1: Remove dead `DataDeletionConfirmation` mailable if unused

```bash
grep -rn "DataDeletionConfirmation" app/ resources/ | grep -v "//\|/\*"
```

If only referenced from removed/refactored code, delete the mailable + Blade.

### Task 11.2: Lint + format

```bash
./vendor/bin/pint
```

Verify no errors. Commit any formatting changes.

### Task 11.3: Run full test suite

```bash
./vendor/bin/pest
```

Expected: all green.

### Task 11.4: Final commit

```bash
git status
# Ensure clean
```

---

## Self-Review Checklist (run by the implementer at the end)

**Spec coverage** — point each spec section at a task:

| Spec § | Tasks |
|---|---|
| §4 Lifecycle states | 1.5, 2.1–2.4 |
| §5 Three trigger paths | 7.1, 7.2, 5.2 |
| §6.1 Schema columns | 1.2 |
| §6.2 life_events FK fix | 1.3 |
| §6.3 deletion-order bug fix | 3.1 |
| §6.4 No new tables (audit codes) | 1.6 |
| §7 Services | 2.1–2.4, 3.1, 7.3 |
| §8 Routes | 6.3, 7.1 |
| §9 Auth flow | 6.1, 6.2, 6.3 |
| §10 UI changes | 8.3, 8.4, 9.1, 9.2, 9.3, 9.4 |
| §11 Email notifications | 2.1–2.4, 4.1 |
| §12 Cron jobs | 5.1, 5.2, 5.3, 5.4, 5.5 |
| §13 Restoration semantics | 2.4, 6.3 |
| §14 Joint-owner edge case | 9.4 |
| §15 Migration of existing soft-deleted | 1.4 |
| §16 Cron sequencing | 5.5 |
| §17 Testing strategy | tests added throughout |
| §19 Decisions | implemented per spec |

**Type consistency** — function/property names match across tasks:

- `AccountDeletionService::scheduleDeletion(User, string, string, Carbon)` — used in tasks 2.1, 5.1, 7.1
- `AccountDeletionService::cancelScheduledDeletion(User)` — tasks 2.2, 7.1, 8.4, 9.2
- `AccountDeletionService::deleteAccount(User, string, string)` — tasks 2.3, 5.1, 5.2, 7.1, 7.2
- `AccountDeletionService::restoreAccount(User)` — tasks 2.4, 6.3
- `RetentionPurgeService::purgeUser(User)` — tasks 3.1, 5.4
- Audit constants `ACTION_ACCOUNT_DELETION_SCHEDULED` etc. — task 1.6, used in 2.1–2.4
- User columns `deletion_scheduled_for`, `deletion_reason`, `deletion_source`, `restored_at`, `purge_eligible_at` — task 1.2, used throughout

**Placeholder scan** — no "TBD" / "implement later" / vague hand-waving. Each step has runnable code or commands. Email-template polish in Task 4.1 step 6 is an explicit deferred call to the project's `email-template` skill, not a placeholder.

**Open questions for the implementer to flag**:

- The exact location of `Login.vue` / `Register.vue` and whether the project uses Auth.vue with router branches; resolve by `grep -rln "function login\|authService.login" resources/js/`.
- The signature of `AuditService::log()` may differ from `metadata: [...]` named-argument shape. Read the existing service before committing 2.1.
- `AppLayout.vue` may already mount banners differently; honour the existing pattern.
- `MfaService` / verification-code flow inside `executeErasure` is unchanged and assumed to work — if `7.1` reveals a bug there, route through a dedicated bug-fix sub-task (per CLAUDE.md rule #15 "LOOP UNTIL CORRECT").

---

End of plan.
