# Lifecycle Email Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a single user lifecycle email engine that ships five distinct campaigns (empty trialer re-engagement, engaged trialer discount conversion, cancelled trialer feedback, churned subscriber feedback, lapsed subscriber payment recovery) with per-user opt-out preferences and a real end-to-end verification suite.

**Architecture:** One shared `LifecycleEngine` service iterates over five pluggable `LifecycleCampaign` classes, each declaring its own eligibility query, mailable, and priority. Dedup via a single `lifecycle_email_log` table. Magic links via Laravel signed URLs. Per-user-locked discount codes via a new `lifecycle_welcome` discount type. Live e2e validation via dummy seeded users with email recipient override to a real inbox.

**Tech Stack:** Laravel 10, PHP 8.2, MySQL 8, Pest, Vue 3, Tailwind, Mockery, Carbon

**Spec:** `docs/superpowers/specs/2026-04-14-lifecycle-email-engine-design.md`

**Prerequisite:** The system cron must be firing on production (added at end of session 51, verification carried over to session 52). The lifecycle engine cannot ship until cron is verified working.

---

## How to use this plan

- Each phase produces working, committed code. Commit after every task.
- Steps marked `- [ ]` are checkboxes — mark them as you go.
- TDD pattern: write the failing test → run it → see it fail → implement → run it → see it pass → commit.
- All file paths are absolute from the repo root.
- Run `php artisan db:seed` at the start AND end of every task per the project rules.
- Never use `migrate:fresh` or `migrate:refresh`.

---

## File structure overview

### New files

```
app/Console/Commands/
  RunLifecycleEngine.php
  RunLifecycleEngineE2ETest.php
  RunLifecycleEngineE2ECleanup.php

app/Http/Controllers/Api/
  NotificationPreferenceController.php          (NEW — web equivalent)

app/Http/Controllers/Lifecycle/
  LifecycleActionController.php

app/Mail/Lifecycle/
  EmptyTrialerMail.php
  EngagedTrialerMail.php
  CancelledTrialerMail.php
  ChurnedSubscriberMail.php
  LapsedSubscriberMail.php

app/Models/
  LifecycleEmailLog.php
  FeedbackResponse.php

app/Services/Lifecycle/
  LifecycleEngine.php
  LifecycleSnapshotService.php
  LifecycleDiscountCodeGenerator.php
  Contracts/
    LifecycleCampaign.php
  Campaigns/
    EmptyTrialerCampaign.php
    EngagedTrialerCampaign.php
    CancelledTrialerCampaign.php
    ChurnedSubscriberCampaign.php
    LapsedSubscriberCampaign.php

config/
  lifecycle.php

database/migrations/
  YYYY_MM_DD_HHMMSS_create_lifecycle_email_log_table.php
  YYYY_MM_DD_HHMMSS_create_feedback_responses_table.php
  YYYY_MM_DD_HHMMSS_add_user_id_and_metadata_to_discount_codes.php
  YYYY_MM_DD_HHMMSS_add_is_lifecycle_test_user_to_users.php
  YYYY_MM_DD_HHMMSS_add_lifecycle_columns_to_notification_preferences.php
  YYYY_MM_DD_HHMMSS_add_subscriptions_indexes.php  (only if missing)

database/seeders/
  LifecycleTestSeeder.php

resources/js/components/UserProfile/
  NotificationPreferences.vue                    (NEW — web settings page)

resources/views/emails/lifecycle/
  _layout.blade.php
  _button.blade.php
  _quick-picks.blade.php
  empty-trialer.blade.php
  engaged-trialer.blade.php
  cancelled-trialer.blade.php
  churned-subscriber.blade.php
  lapsed-subscriber.blade.php
  feedback-thanks.blade.php
  feedback-text-thanks.blade.php

tests/Unit/Services/Lifecycle/
  LifecycleSnapshotServiceTest.php
  LifecycleDiscountCodeGeneratorTest.php
  LifecycleEngineTest.php

tests/Feature/Lifecycle/
  LifecycleActionControllerTest.php
  LifecycleEngineCommandTest.php
  LifecycleEngineEndToEndTest.php
  Campaigns/
    EmptyTrialerCampaignTest.php
    EngagedTrialerCampaignTest.php
    CancelledTrialerCampaignTest.php
    ChurnedSubscriberCampaignTest.php
    LapsedSubscriberCampaignTest.php
```

### Modified files

```
app/Console/Kernel.php                                  (1 line: schedule entry)
app/Providers/AppServiceProvider.php                    (LifecycleEngine binding)
app/Models/User.php                                     (is_lifecycle_test_user fields + relation)
app/Models/NotificationPreference.php                   (5 lifecycle fields)
app/Models/DiscountCode.php                             (user_id + metadata fields)
app/Services/Payment/DiscountCodeService.php            (validate + calculateDiscount)
app/Services/Payment/TrialService.php                   (restartTrial method)
app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php
                                                        (add 5 lifecycle fields + estate_alerts fix)
app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php
                                                        (add 5 lifecycle field rules)
resources/js/mobile/views/NotificationSettings.vue      (5 new toggleItems entries)
resources/js/components/UserProfile/Settings.vue        (Notifications tab link)
resources/views/emails/trial-expiration-reminder.blade.php
                                                        (palette hex code swap — tangential cleanup)
routes/web.php                                          (5 new lifecycle routes)
routes/api.php                                          (2 new notification preference routes)
tests/Unit/Services/Payment/DiscountCodeServiceTest.php (augmented)
tests/Unit/Services/Payment/TrialServiceTest.php        (augmented)
database/factories/UserFactory.php                      (5 new state methods)
```

---

## Phase 1 — Database schema migrations

5 migrations. Each is a tiny, isolated change. Run and verify locally before moving on. Per project rules, every migration uses `declare(strict_types=1)` and includes a `Schema::hasTable()` safety check.

### Task 1.1 — Create `lifecycle_email_log` migration

**Files:**
- Create: `database/migrations/2026_04_14_120001_create_lifecycle_email_log_table.php`

- [ ] **Step 1: Create the migration via artisan**

```bash
php artisan make:migration create_lifecycle_email_log_table
```

Expected: file created at `database/migrations/2026_04_14_HHMMSS_create_lifecycle_email_log_table.php`. Note the actual filename for the next steps.

- [ ] **Step 2: Replace the migration content**

Open the file and replace its entire contents with:

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
        if (Schema::hasTable('lifecycle_email_log')) {
            return;
        }

        Schema::create('lifecycle_email_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign', 50);
            $table->timestamp('sent_at');
            $table->timestamp('clicked_at')->nullable();
            $table->string('action_taken', 50)->nullable();
            $table->json('context')->nullable();

            $table->unique(['user_id', 'campaign']);
            $table->index(['campaign', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_email_log');
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: `INFO Running migrations.` followed by `2026_04_14_HHMMSS_create_lifecycle_email_log_table .... DONE`

- [ ] **Step 4: Verify the table exists**

```bash
php artisan tinker --execute="echo (\Schema::hasTable('lifecycle_email_log') ? 'EXISTS' : 'MISSING').PHP_EOL;"
```

Expected output: `EXISTS`

- [ ] **Step 5: Verify the unique constraint works**

```bash
php artisan tinker --execute="\DB::table('lifecycle_email_log')->insert(['user_id'=>1,'campaign'=>'test','sent_at'=>now()]); try { \DB::table('lifecycle_email_log')->insert(['user_id'=>1,'campaign'=>'test','sent_at'=>now()]); echo 'FAIL: duplicate inserted'.PHP_EOL; } catch (\Exception \$e) { echo 'PASS: unique constraint enforced'.PHP_EOL; } \DB::table('lifecycle_email_log')->where('campaign','test')->delete();"
```

Expected output: `PASS: unique constraint enforced`

- [ ] **Step 6: Reseed and commit**

```bash
php artisan db:seed
git add database/migrations/2026_04_14_*_create_lifecycle_email_log_table.php
git commit -m "feat: add lifecycle_email_log table for engine dedup tracking"
```

---

### Task 1.2 — Create `feedback_responses` migration

**Files:**
- Create: `database/migrations/2026_04_14_120002_create_feedback_responses_table.php`

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration create_feedback_responses_table
```

- [ ] **Step 2: Replace contents**

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
        if (Schema::hasTable('feedback_responses')) {
            return;
        }

        Schema::create('feedback_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign', 50);
            $table->string('reason_code', 50);
            $table->text('free_text')->nullable();
            $table->timestamp('clicked_at');
            $table->timestamp('text_submitted_at')->nullable();

            $table->index(['user_id', 'campaign']);
            $table->index('reason_code');
            $table->index(['campaign', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_responses');
    }
};
```

- [ ] **Step 3: Run and verify**

```bash
php artisan migrate
php artisan tinker --execute="echo (\Schema::hasTable('feedback_responses') ? 'EXISTS' : 'MISSING').PHP_EOL;"
```

Expected: `EXISTS`

- [ ] **Step 4: Reseed and commit**

```bash
php artisan db:seed
git add database/migrations/2026_04_14_*_create_feedback_responses_table.php
git commit -m "feat: add feedback_responses table for cancellation/recovery feedback"
```

---

### Task 1.3 — Add `user_id` and `metadata` to `discount_codes`

**Files:**
- Create: `database/migrations/2026_04_14_120003_add_user_id_and_metadata_to_discount_codes.php`

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration add_user_id_and_metadata_to_discount_codes
```

- [ ] **Step 2: Replace contents**

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
        Schema::table('discount_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('discount_codes', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->cascadeOnDelete();
            }
            if (! Schema::hasColumn('discount_codes', 'metadata')) {
                $table->json('metadata')->nullable()->after('applicable_cycles');
            }
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            if (Schema::hasColumn('discount_codes', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('discount_codes', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
```

- [ ] **Step 3: Run and verify**

```bash
php artisan migrate
php artisan tinker --execute="echo (\Schema::hasColumn('discount_codes', 'user_id') ? 'user_id OK' : 'user_id MISSING').PHP_EOL; echo (\Schema::hasColumn('discount_codes', 'metadata') ? 'metadata OK' : 'metadata MISSING').PHP_EOL;"
```

Expected:
```
user_id OK
metadata OK
```

- [ ] **Step 4: Verify existing rows still work**

```bash
php artisan tinker --execute="echo 'existing rows: '.\DB::table('discount_codes')->count().PHP_EOL; \DB::table('discount_codes')->whereNull('user_id')->take(3)->get(['code','user_id'])->each(function(\$r){echo \$r->code.' user_id='.(\$r->user_id??'null').PHP_EOL;});"
```

Expected: shows existing codes (`LAUNCH20`, `FYNLA10`, `TRYME` if seeded) with `user_id=null`. Existing shared codes are unaffected.

- [ ] **Step 5: Reseed and commit**

```bash
php artisan db:seed
git add database/migrations/2026_04_14_*_add_user_id_and_metadata_to_discount_codes.php
git commit -m "feat: add user_id and metadata columns to discount_codes for per-user lifecycle codes"
```

---

### Task 1.4 — Add `is_lifecycle_test_user` to `users`

**Files:**
- Create: `database/migrations/2026_04_14_120004_add_is_lifecycle_test_user_to_users.php`

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration add_is_lifecycle_test_user_to_users
```

- [ ] **Step 2: Replace contents**

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
            if (! Schema::hasColumn('users', 'is_lifecycle_test_user')) {
                $table->boolean('is_lifecycle_test_user')->default(false);
                $table->index('is_lifecycle_test_user');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_lifecycle_test_user')) {
                $table->dropIndex(['is_lifecycle_test_user']);
                $table->dropColumn('is_lifecycle_test_user');
            }
        });
    }
};
```

- [ ] **Step 3: Run and verify**

```bash
php artisan migrate
php artisan tinker --execute="echo (\Schema::hasColumn('users', 'is_lifecycle_test_user') ? 'EXISTS' : 'MISSING').PHP_EOL; echo 'real users with flag=true: '.\DB::table('users')->where('is_lifecycle_test_user', true)->count().PHP_EOL;"
```

Expected:
```
EXISTS
real users with flag=true: 0
```

- [ ] **Step 4: Reseed and commit**

```bash
php artisan db:seed
git add database/migrations/2026_04_14_*_add_is_lifecycle_test_user_to_users.php
git commit -m "feat: add is_lifecycle_test_user flag to users table for e2e safety"
```

---

### Task 1.5 — Add 5 lifecycle columns to `notification_preferences`

**Files:**
- Create: `database/migrations/2026_04_14_120005_add_lifecycle_columns_to_notification_preferences.php`

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration add_lifecycle_columns_to_notification_preferences
```

- [ ] **Step 2: Replace contents**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'lifecycle_empty_trialer',
        'lifecycle_engaged_trialer',
        'lifecycle_cancelled_trialer',
        'lifecycle_churned_subscriber',
        'lifecycle_lapsed_subscriber',
    ];

    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (! Schema::hasColumn('notification_preferences', $column)) {
                    $table->boolean($column)->default(true);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('notification_preferences', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
```

- [ ] **Step 3: Run and verify**

```bash
php artisan migrate
php artisan tinker --execute="\$cols = ['lifecycle_empty_trialer','lifecycle_engaged_trialer','lifecycle_cancelled_trialer','lifecycle_churned_subscriber','lifecycle_lapsed_subscriber']; foreach (\$cols as \$c) { echo \$c.': '.(\Schema::hasColumn('notification_preferences', \$c) ? 'OK' : 'MISSING').PHP_EOL; }"
```

Expected: 5 lines, all `OK`

- [ ] **Step 4: Verify existing rows are backfilled to `true`**

```bash
php artisan tinker --execute="\$existing = \DB::table('notification_preferences')->first(); if (\$existing) { echo 'lifecycle_empty_trialer='.\$existing->lifecycle_empty_trialer.PHP_EOL; echo 'lifecycle_engaged_trialer='.\$existing->lifecycle_engaged_trialer.PHP_EOL; } else { echo 'no existing rows yet — backfill not testable'.PHP_EOL; }"
```

Expected: if there are existing rows, all 5 lifecycle columns should be `1` (true). MySQL backfills DEFAULT TRUE on existing rows automatically.

- [ ] **Step 5: Reseed and commit**

```bash
php artisan db:seed
git add database/migrations/2026_04_14_*_add_lifecycle_columns_to_notification_preferences.php
git commit -m "feat: add 5 lifecycle email opt-out columns to notification_preferences"
```

---

### Task 1.6 — Verify and (if missing) add subscription indexes

**Files:**
- Possibly create: `database/migrations/2026_04_14_120006_add_subscriptions_indexes.php`

- [ ] **Step 1: Check which indexes already exist**

```bash
php artisan tinker --execute="\$idx = collect(\DB::select('SHOW INDEX FROM subscriptions'))->pluck('Key_name')->unique()->values(); echo 'Existing indexes:'.PHP_EOL; foreach (\$idx as \$i) { echo '  '.\$i.PHP_EOL; }"
```

Note which of these are present (or absent):
- `idx_subs_status_trial` (for `(status, trial_ends_at)`)
- `idx_subs_status_period` (for `(status, current_period_end)`)
- `idx_subs_status_cancelled` (for `(status, cancelled_at)`)

- [ ] **Step 2: If ALL three are present → skip the migration entirely**

If your output shows all three (or equivalent indexes covering the same columns), commit a no-op note to the plan and proceed to Task 1.7. Skip steps 3-5.

- [ ] **Step 3: If any are missing → create the migration**

```bash
php artisan make:migration add_subscriptions_indexes
```

- [ ] **Step 4: Replace contents (only including missing indexes)**

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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Only add indexes that don't already exist
            // Customise this list based on Step 1's output
            $existing = collect(\DB::select('SHOW INDEX FROM subscriptions'))
                ->pluck('Key_name')
                ->unique()
                ->all();

            if (! in_array('idx_subs_status_trial', $existing, true)) {
                $table->index(['status', 'trial_ends_at'], 'idx_subs_status_trial');
            }
            if (! in_array('idx_subs_status_period', $existing, true)) {
                $table->index(['status', 'current_period_end'], 'idx_subs_status_period');
            }
            if (! in_array('idx_subs_status_cancelled', $existing, true)) {
                $table->index(['status', 'cancelled_at'], 'idx_subs_status_cancelled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $existing = collect(\DB::select('SHOW INDEX FROM subscriptions'))
                ->pluck('Key_name')
                ->unique()
                ->all();

            if (in_array('idx_subs_status_trial', $existing, true)) {
                $table->dropIndex('idx_subs_status_trial');
            }
            if (in_array('idx_subs_status_period', $existing, true)) {
                $table->dropIndex('idx_subs_status_period');
            }
            if (in_array('idx_subs_status_cancelled', $existing, true)) {
                $table->dropIndex('idx_subs_status_cancelled');
            }
        });
    }
};
```

- [ ] **Step 5: Run, verify, reseed, commit**

```bash
php artisan migrate
php artisan tinker --execute="\$idx = collect(\DB::select('SHOW INDEX FROM subscriptions'))->pluck('Key_name')->unique()->values(); foreach (\$idx as \$i) { if (str_starts_with(\$i, 'idx_subs_')) echo \$i.PHP_EOL; }"
php artisan db:seed
git add database/migrations/2026_04_14_*_add_subscriptions_indexes.php
git commit -m "perf: add composite indexes on subscriptions for lifecycle eligibility queries"
```

---

### Task 1.7 — Run all migrations clean and verify

- [ ] **Step 1: Show migration status**

```bash
php artisan migrate:status | grep -E "lifecycle|discount_codes|users|notification_pref|subscription" | tail -10
```

All recently added migrations should show `Ran`.

- [ ] **Step 2: Sanity-check schema with one composite query**

```bash
php artisan tinker --execute="
\$tables = ['lifecycle_email_log', 'feedback_responses'];
foreach (\$tables as \$t) echo \$t.': '.(\Schema::hasTable(\$t) ? 'EXISTS' : 'MISSING').PHP_EOL;
\$cols = [
    'discount_codes' => ['user_id','metadata'],
    'users' => ['is_lifecycle_test_user'],
    'notification_preferences' => ['lifecycle_empty_trialer','lifecycle_engaged_trialer','lifecycle_cancelled_trialer','lifecycle_churned_subscriber','lifecycle_lapsed_subscriber'],
];
foreach (\$cols as \$table => \$columns) {
    foreach (\$columns as \$col) {
        echo \$table.'.'.\$col.': '.(\Schema::hasColumn(\$table, \$col) ? 'OK' : 'MISSING').PHP_EOL;
    }
}
"
```

Expected: every line should end in `EXISTS` or `OK`. No `MISSING`.

- [ ] **Step 3: Phase 1 commit checkpoint**

Phase 1 is now complete. The repo has 5 (or 6) new migration files. Working tree should be clean. Verify:

```bash
git log --oneline -10
git status --short
```

`git status` should show nothing in working tree (or only files unrelated to this work).

---

## Phase 2 — Model updates

3 model file changes. Each is small. TDD approach: write a test that exercises the new field/method, see it fail (or skip if model layer only), implement, see it pass.

### Task 2.1 — Update `NotificationPreference` model

**Files:**
- Modify: `app/Models/NotificationPreference.php`
- Test: `tests/Unit/Models/NotificationPreferenceTest.php` (create if missing)

- [ ] **Step 1: Read the current file**

```bash
cat app/Models/NotificationPreference.php
```

Note the existing `$fillable`, `$casts`, and `getOrCreateForUser()` defaults.

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/Models/NotificationPreferenceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has all 5 lifecycle email preference columns defaulting to true', function () {
    $user = User::factory()->create();
    $prefs = NotificationPreference::getOrCreateForUser($user->id);

    expect($prefs->lifecycle_empty_trialer)->toBeTrue();
    expect($prefs->lifecycle_engaged_trialer)->toBeTrue();
    expect($prefs->lifecycle_cancelled_trialer)->toBeTrue();
    expect($prefs->lifecycle_churned_subscriber)->toBeTrue();
    expect($prefs->lifecycle_lapsed_subscriber)->toBeTrue();
});

it('allows updating individual lifecycle preferences', function () {
    $user = User::factory()->create();
    $prefs = NotificationPreference::getOrCreateForUser($user->id);

    $prefs->update(['lifecycle_engaged_trialer' => false]);

    expect($prefs->fresh()->lifecycle_engaged_trialer)->toBeFalse();
    expect($prefs->fresh()->lifecycle_empty_trialer)->toBeTrue();  // others untouched
});
```

- [ ] **Step 3: Run the test — expect failure**

```bash
./vendor/bin/pest tests/Unit/Models/NotificationPreferenceTest.php
```

Expected: 2 tests fail with "Undefined property: NotificationPreference::$lifecycle_empty_trialer" or similar (because the field isn't in `$casts` yet, so it's not being hydrated as a boolean).

- [ ] **Step 4: Update `app/Models/NotificationPreference.php`**

Add the 5 new fields to `$fillable`, `$casts`, and the `getOrCreateForUser()` defaults block. The full updated file:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'policy_renewals',
        'goal_milestones',
        'contribution_reminders',
        'market_updates',
        'fyn_daily_insight',
        'security_alerts',
        'payment_alerts',
        'mortgage_rate_alerts',
        'estate_alerts',
        'lifecycle_empty_trialer',
        'lifecycle_engaged_trialer',
        'lifecycle_cancelled_trialer',
        'lifecycle_churned_subscriber',
        'lifecycle_lapsed_subscriber',
    ];

    protected $casts = [
        'policy_renewals' => 'boolean',
        'goal_milestones' => 'boolean',
        'contribution_reminders' => 'boolean',
        'market_updates' => 'boolean',
        'fyn_daily_insight' => 'boolean',
        'security_alerts' => 'boolean',
        'payment_alerts' => 'boolean',
        'mortgage_rate_alerts' => 'boolean',
        'estate_alerts' => 'boolean',
        'lifecycle_empty_trialer' => 'boolean',
        'lifecycle_engaged_trialer' => 'boolean',
        'lifecycle_cancelled_trialer' => 'boolean',
        'lifecycle_churned_subscriber' => 'boolean',
        'lifecycle_lapsed_subscriber' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'policy_renewals' => true,
                'goal_milestones' => true,
                'contribution_reminders' => true,
                'market_updates' => false,
                'fyn_daily_insight' => true,
                'security_alerts' => true,
                'payment_alerts' => true,
                'mortgage_rate_alerts' => true,
                'estate_alerts' => true,
                'lifecycle_empty_trialer' => true,
                'lifecycle_engaged_trialer' => true,
                'lifecycle_cancelled_trialer' => true,
                'lifecycle_churned_subscriber' => true,
                'lifecycle_lapsed_subscriber' => true,
            ]
        );
    }
}
```

- [ ] **Step 5: Run the test — expect pass**

```bash
./vendor/bin/pest tests/Unit/Models/NotificationPreferenceTest.php
```

Expected: 2 tests pass.

- [ ] **Step 6: Reseed and commit**

```bash
php artisan db:seed
git add app/Models/NotificationPreference.php tests/Unit/Models/NotificationPreferenceTest.php
git commit -m "feat: add 5 lifecycle email preference fields to NotificationPreference model"
```

---

### Task 2.2 — Create `LifecycleEmailLog` model

**Files:**
- Create: `app/Models/LifecycleEmailLog.php`
- Test: covered indirectly by feature tests in Phase 7

- [ ] **Step 1: Create the model file**

Create `app/Models/LifecycleEmailLog.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifecycleEmailLog extends Model
{
    use HasFactory;

    protected $table = 'lifecycle_email_log';

    protected $fillable = [
        'user_id',
        'campaign',
        'sent_at',
        'clicked_at',
        'action_taken',
        'context',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'clicked_at' => 'datetime',
        'context' => 'array',
    ];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 2: Quick smoke test in tinker**

```bash
php artisan tinker --execute="\$u = \App\Models\User::first(); \$log = \App\Models\LifecycleEmailLog::create(['user_id' => \$u->id, 'campaign' => 'test', 'sent_at' => now(), 'context' => ['x' => 1]]); echo 'created id='.\$log->id.' context='.json_encode(\$log->context).PHP_EOL; \$log->delete();"
```

Expected: `created id=N context={"x":1}`

- [ ] **Step 3: Commit**

```bash
git add app/Models/LifecycleEmailLog.php
git commit -m "feat: add LifecycleEmailLog model"
```

---

### Task 2.3 — Create `FeedbackResponse` model

**Files:**
- Create: `app/Models/FeedbackResponse.php`

- [ ] **Step 1: Create the model file**

Create `app/Models/FeedbackResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'campaign',
        'reason_code',
        'free_text',
        'clicked_at',
        'text_submitted_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
        'text_submitted_at' => 'datetime',
    ];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 2: Smoke test**

```bash
php artisan tinker --execute="\$u = \App\Models\User::first(); \$r = \App\Models\FeedbackResponse::create(['user_id' => \$u->id, 'campaign' => 'test', 'reason_code' => 'too_expensive', 'clicked_at' => now()]); echo 'created id='.\$r->id.PHP_EOL; \$r->delete();"
```

Expected: `created id=N`

- [ ] **Step 3: Commit**

```bash
git add app/Models/FeedbackResponse.php
git commit -m "feat: add FeedbackResponse model"
```

---

### Task 2.4 — Update `User` model with `is_lifecycle_test_user` and relations

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Add `is_lifecycle_test_user` to `$fillable` and `$casts`**

Find the `$fillable` array in `app/Models/User.php` and add `'is_lifecycle_test_user'` to it.

Find the `$casts` array and add `'is_lifecycle_test_user' => 'boolean'`.

(Use `Read` and `Edit` tools for surgical changes — these are large files.)

- [ ] **Step 2: Add the `lifecycleEmails` relation**

Find an existing `hasMany` relation method in `User.php` (e.g., `subscriptions()`, `goals()`) and add a new one nearby:

```php
public function lifecycleEmails(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\LifecycleEmailLog::class);
}

public function notificationPreference(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(\App\Models\NotificationPreference::class);
}
```

(Check first whether `notificationPreference()` already exists on the User model; if so, skip that part.)

- [ ] **Step 3: Smoke test the relations**

```bash
php artisan tinker --execute="\$u = \App\Models\User::first(); echo 'lifecycleEmails relation: '.\$u->lifecycleEmails()->count().PHP_EOL; echo 'notificationPreference relation: '.(\$u->notificationPreference ? 'exists' : 'null').PHP_EOL; echo 'is_lifecycle_test_user cast: '.var_export(\$u->is_lifecycle_test_user, true).PHP_EOL;"
```

Expected:
```
lifecycleEmails relation: 0
notificationPreference relation: exists or null
is_lifecycle_test_user cast: false
```

- [ ] **Step 4: Reseed and commit**

```bash
php artisan db:seed
git add app/Models/User.php
git commit -m "feat: add is_lifecycle_test_user + lifecycleEmails relation to User model"
```

---

### Task 2.5 — Update `DiscountCode` model with `user_id` and `metadata`

**Files:**
- Modify: `app/Models/DiscountCode.php`

- [ ] **Step 1: Add fields to `$fillable`**

Add to the `$fillable` array (between existing entries):

```php
'user_id',
'metadata',
```

- [ ] **Step 2: Add cast for `metadata`**

Add to `$casts`:

```php
'metadata' => 'array',
```

- [ ] **Step 3: Add the `user()` relation**

Add a method (after the existing `creator()` method):

```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id');
}
```

- [ ] **Step 4: Smoke test**

```bash
php artisan tinker --execute="\$u = \App\Models\User::first(); \$dc = \App\Models\DiscountCode::create(['code' => 'TEST_LIFECYCLE_'.uniqid(), 'type' => 'lifecycle_welcome', 'value' => 0, 'max_uses' => 1, 'max_uses_per_user' => 1, 'is_active' => true, 'user_id' => \$u->id, 'metadata' => ['plan_amounts' => ['standard.monthly' => 500]]]); echo 'created id='.\$dc->id.' user_id='.\$dc->user_id.' metadata='.json_encode(\$dc->metadata).PHP_EOL; \$dc->delete();"
```

Expected: shows the created code with `user_id` set and `metadata` parseable as an array.

- [ ] **Step 5: Reseed and commit**

```bash
php artisan db:seed
git add app/Models/DiscountCode.php
git commit -m "feat: add user_id and metadata fillable + cast to DiscountCode model"
```

---

### Phase 2 checkpoint

- [ ] All 5 model tasks committed
- [ ] `git status --short` shows clean working tree
- [ ] Existing test suite still passes for the touched models:

```bash
./vendor/bin/pest tests/Unit/Services/Payment/DiscountCodeServiceTest.php tests/Unit/Models/ 2>&1 | tail -10
```

Expected: all pre-existing tests still pass. We haven't changed behaviour, only added fields.

---

## Phase 3 — DiscountCodeService updates

Add the per-user lock check and the `lifecycle_welcome` calculation. TDD all the way.

### Task 3.1 — Add `user_id` lock check to `validate()`

**Files:**
- Modify: `app/Services/Payment/DiscountCodeService.php`
- Modify: `tests/Unit/Services/Payment/DiscountCodeServiceTest.php`

- [ ] **Step 1: Read the existing test file**

```bash
cat tests/Unit/Services/Payment/DiscountCodeServiceTest.php | head -50
```

Note the existing test patterns and the namespace.

- [ ] **Step 2: Write the failing tests**

Append to `tests/Unit/Services/Payment/DiscountCodeServiceTest.php` (inside the existing `describe()` block, or as new top-level `it()` calls — match the existing style):

```php
it('rejects a user-locked code when the wrong user tries to use it', function () {
    $owner = \App\Models\User::factory()->create();
    $other = \App\Models\User::factory()->create();

    $code = \App\Models\DiscountCode::create([
        'code' => 'WELCOME_TEST1',
        'type' => 'lifecycle_welcome',
        'value' => 0,
        'max_uses' => 1,
        'max_uses_per_user' => 1,
        'is_active' => true,
        'user_id' => $owner->id,
        'metadata' => ['plan_amounts' => ['standard.monthly' => 500]],
        'applicable_plans' => ['standard'],
        'applicable_cycles' => ['monthly'],
    ]);

    $service = app(\App\Services\Payment\DiscountCodeService::class);
    $result = $service->validate('WELCOME_TEST1', $other->id, 'standard', 'monthly', 1099);

    expect($result['valid'])->toBeFalse();
    expect($result['message'])->toContain('not valid for your account');
});

it('accepts a user-locked code when the correct user tries to use it', function () {
    $owner = \App\Models\User::factory()->create();

    $code = \App\Models\DiscountCode::create([
        'code' => 'WELCOME_TEST2',
        'type' => 'lifecycle_welcome',
        'value' => 0,
        'max_uses' => 1,
        'max_uses_per_user' => 1,
        'is_active' => true,
        'user_id' => $owner->id,
        'metadata' => ['plan_amounts' => ['standard.monthly' => 500]],
        'applicable_plans' => ['standard'],
        'applicable_cycles' => ['monthly'],
    ]);

    $service = app(\App\Services\Payment\DiscountCodeService::class);
    $result = $service->validate('WELCOME_TEST2', $owner->id, 'standard', 'monthly', 1099);

    expect($result['valid'])->toBeTrue();
});

it('still accepts shared codes (user_id null) for any user', function () {
    $someUser = \App\Models\User::factory()->create();

    $code = \App\Models\DiscountCode::create([
        'code' => 'SHAREDTEST',
        'type' => 'percentage',
        'value' => 10,
        'max_uses' => 100,
        'max_uses_per_user' => 1,
        'is_active' => true,
        'user_id' => null,
    ]);

    $service = app(\App\Services\Payment\DiscountCodeService::class);
    $result = $service->validate('SHAREDTEST', $someUser->id, 'standard', 'monthly', 1099);

    expect($result['valid'])->toBeTrue();
});
```

- [ ] **Step 3: Run the tests — expect failure**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/DiscountCodeServiceTest.php --filter="rejects a user-locked code|accepts a user-locked code|still accepts shared codes"
```

Expected: first two tests fail because `validate()` doesn't check `user_id`. The third test should pass already (existing behaviour).

- [ ] **Step 4: Add the user_id check to `validate()`**

In `app/Services/Payment/DiscountCodeService.php::validate()`, immediately after the `$discount = DiscountCode::where('code', $code)->first();` lookup and the `! $discount` early return, add:

```php
if ($discount->user_id !== null && $discount->user_id !== $userId) {
    return $this->invalid('This discount code is not valid for your account.');
}
```

- [ ] **Step 5: Run the tests — expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/DiscountCodeServiceTest.php --filter="rejects a user-locked code|accepts a user-locked code|still accepts shared codes"
```

Expected: 3 passes.

- [ ] **Step 6: Run the FULL test file to ensure no regressions**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/DiscountCodeServiceTest.php
```

Expected: all tests pass (existing + 3 new = N+3 total).

- [ ] **Step 7: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Payment/DiscountCodeService.php tests/Unit/Services/Payment/DiscountCodeServiceTest.php
git commit -m "feat: per-user discount code lock in DiscountCodeService::validate"
```

---

### Task 3.2 — Add `lifecycle_welcome` calculation to `calculateDiscount()`

**Files:**
- Modify: `app/Services/Payment/DiscountCodeService.php`
- Modify: `app/Models/DiscountCode.php` (mirror the change in the model's own calculateDiscount)
- Modify: `tests/Unit/Services/Payment/DiscountCodeServiceTest.php`

- [ ] **Step 1: Write the failing tests**

Append to the test file:

```php
it('calculates lifecycle_welcome discount from metadata for matching plan/cycle', function () {
    $user = \App\Models\User::factory()->create();

    $code = \App\Models\DiscountCode::create([
        'code' => 'WELCOME_LC1',
        'type' => 'lifecycle_welcome',
        'value' => 0,
        'max_uses' => 1,
        'max_uses_per_user' => 1,
        'is_active' => true,
        'user_id' => $user->id,
        'metadata' => [
            'plan_amounts' => [
                'standard.monthly' => 500,
                'standard.yearly' => 4500,
                'family.monthly' => 400,
            ],
        ],
        'applicable_plans' => ['standard', 'family'],
        'applicable_cycles' => ['monthly', 'yearly'],
    ]);

    $service = app(\App\Services\Payment\DiscountCodeService::class);

    $result = $service->validate('WELCOME_LC1', $user->id, 'standard', 'monthly', 1099);
    expect($result['discount_amount'])->toBe(500);
    expect($result['final_amount'])->toBe(599);

    $result = $service->validate('WELCOME_LC1', $user->id, 'standard', 'yearly', 10000);
    expect($result['discount_amount'])->toBe(4500);

    $result = $service->validate('WELCOME_LC1', $user->id, 'family', 'monthly', 1499);
    expect($result['discount_amount'])->toBe(400);
});

it('returns zero discount for lifecycle_welcome with non-discounted plan/cycle combo', function () {
    $user = \App\Models\User::factory()->create();

    \App\Models\DiscountCode::create([
        'code' => 'WELCOME_LC2',
        'type' => 'lifecycle_welcome',
        'value' => 0,
        'max_uses' => 1,
        'max_uses_per_user' => 1,
        'is_active' => true,
        'user_id' => $user->id,
        'metadata' => ['plan_amounts' => ['standard.monthly' => 500]],
        'applicable_plans' => ['standard'],
        'applicable_cycles' => ['monthly'],
    ]);

    $service = app(\App\Services\Payment\DiscountCodeService::class);

    // Pro is not in metadata — should return 0 discount but still be valid
    $result = $service->validate('WELCOME_LC2', $user->id, 'standard', 'monthly', 1099);
    expect($result['discount_amount'])->toBe(500);
});
```

- [ ] **Step 2: Run the tests — expect failure**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/DiscountCodeServiceTest.php --filter="calculates lifecycle_welcome|returns zero"
```

Expected: tests fail because `lifecycle_welcome` type isn't handled in the match expression — falls through to `default` which returns 0.

- [ ] **Step 3: Update the service's `calculateDiscount()` method**

Find the `calculateDiscount()` method in `DiscountCodeService.php`. Replace it with:

```php
public function calculateDiscount(DiscountCode $discount, int $amountPence, ?string $planSlug = null, ?string $billingCycle = null): int
{
    return match ($discount->type) {
        'percentage' => (int) round($amountPence * $discount->value / 100),
        'fixed_amount' => min($discount->value, $amountPence),
        'lifecycle_welcome' => $this->calculateLifecycleWelcomeDiscount($discount, $amountPence, $planSlug, $billingCycle),
        'trial_extension' => 0,
        default => 0,
    };
}

private function calculateLifecycleWelcomeDiscount(
    DiscountCode $discount,
    int $amountPence,
    ?string $planSlug,
    ?string $billingCycle
): int {
    if ($planSlug === null || $billingCycle === null) {
        return 0;
    }

    $key = "{$planSlug}.{$billingCycle}";
    $amount = $discount->metadata['plan_amounts'][$key] ?? 0;

    return min((int) $amount, $amountPence);
}
```

- [ ] **Step 4: Update `validate()` to pass plan + cycle to `calculateDiscount()`**

Find the line in `validate()` that calls `$this->calculateDiscount($discount, $amountPence)` and update it to pass plan/cycle:

```php
$discountAmount = $this->calculateDiscount($discount, $amountPence, $planSlug, $billingCycle);
```

- [ ] **Step 5: Mirror the change in `DiscountCode` model**

`DiscountCode.php` also has a `calculateDiscount(int $amountPence)` method. Update it to accept plan/cycle too:

```php
public function calculateDiscount(int $amountPence, ?string $planSlug = null, ?string $billingCycle = null): int
{
    return match ($this->type) {
        'percentage' => (int) round($amountPence * $this->value / 100),
        'fixed_amount' => min($this->value, $amountPence),
        'lifecycle_welcome' => $this->calculateLifecycleAmount($amountPence, $planSlug, $billingCycle),
        'trial_extension' => 0,
        default => 0,
    };
}

private function calculateLifecycleAmount(int $amountPence, ?string $planSlug, ?string $billingCycle): int
{
    if ($planSlug === null || $billingCycle === null) {
        return 0;
    }
    $key = "{$planSlug}.{$billingCycle}";
    $amount = $this->metadata['plan_amounts'][$key] ?? 0;
    return min((int) $amount, $amountPence);
}
```

- [ ] **Step 6: Run the tests — expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/DiscountCodeServiceTest.php
```

Expected: all tests pass.

- [ ] **Step 7: Run the broader payment test suite to check for regressions**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/
```

Expected: all green.

- [ ] **Step 8: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Payment/DiscountCodeService.php app/Models/DiscountCode.php tests/Unit/Services/Payment/DiscountCodeServiceTest.php
git commit -m "feat: add lifecycle_welcome discount type with per-plan/cycle metadata"
```

---

## Phase 4 — TrialService::restartTrial

One method, well-bounded. TDD.

### Task 4.1 — Implement `TrialService::restartTrial`

**Files:**
- Modify: `app/Services/Payment/TrialService.php`
- Modify: `tests/Unit/Services/Payment/TrialServiceTest.php` (or create if missing)

- [ ] **Step 1: Check whether the test file exists**

```bash
ls tests/Unit/Services/Payment/TrialServiceTest.php 2>&1
```

If it doesn't exist, create it with this header:

```php
<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\TrialService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
```

- [ ] **Step 2: Write the failing tests**

Append:

```php
it('restartTrial reactivates an expired subscription with a new 14-day window', function () {
    $user = User::factory()->create(['plan' => 'free']);
    $sub = Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_started_at' => now()->subDays(20),
        'trial_ends_at' => now()->subDays(13),
        'data_retention_starts_at' => now()->subDays(13),
    ]);

    $service = app(TrialService::class);
    $service->restartTrial($user, days: 14);

    $sub->refresh();
    $user->refresh();

    expect($sub->status)->toBe('trialing');
    expect($sub->trial_ends_at->isAfter(now()->addDays(13)))->toBeTrue();
    expect($sub->data_retention_starts_at)->toBeNull();
    expect($user->plan)->toBe('pro');
});

it('restartTrial is idempotent — does nothing if user is already in active trial', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $futureEnd = now()->addDays(5);
    $sub = Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'trialing',
        'trial_started_at' => now()->subDays(2),
        'trial_ends_at' => $futureEnd,
    ]);

    $service = app(TrialService::class);
    $service->restartTrial($user, days: 14);

    $sub->refresh();

    // Should NOT have been extended
    expect($sub->trial_ends_at->toDateTimeString())->toBe($futureEnd->toDateTimeString());
});

it('restartTrial throws if user has an active paid subscription', function () {
    $user = User::factory()->create(['plan' => 'standard']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'current_period_end' => now()->addDays(30),
    ]);

    $service = app(TrialService::class);

    expect(fn () => $service->restartTrial($user, days: 14))
        ->toThrow(\InvalidArgumentException::class);
});
```

- [ ] **Step 3: Run the tests — expect failure**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/TrialServiceTest.php --filter="restartTrial"
```

Expected: 3 tests fail with `Method TrialService::restartTrial does not exist.`

- [ ] **Step 4: Implement `restartTrial` in `TrialService.php`**

Add this method at the end of `app/Services/Payment/TrialService.php` (before the closing `}`):

```php
/**
 * Restart a previously expired trial for a user (lifecycle Campaign 1).
 *
 * - Updates the user's most recent Subscription record to status='trialing'
 *   with a new trial_started_at/trial_ends_at window
 * - Clears data_retention_starts_at to halt the data purge countdown
 * - Updates users.plan back to 'pro' (trial = pro-level access)
 * - Is idempotent: no-op if the user is already in an active trial
 * - Throws if the user has an active paid subscription
 */
public function restartTrial(User $user, int $days = 14): void
{
    $now = Carbon::now();

    // Refuse to overwrite an active paid subscription
    $hasActivePaid = Subscription::where('user_id', $user->id)
        ->whereIn('status', ['active', 'past_due'])
        ->exists();

    if ($hasActivePaid) {
        throw new \InvalidArgumentException(
            "Cannot restart trial for user {$user->id}: they have an active paid subscription."
        );
    }

    // Find the most recent subscription
    $latest = Subscription::where('user_id', $user->id)
        ->orderByDesc('created_at')
        ->first();

    // Idempotency: if already trialing with a future end date, no-op
    if ($latest && $latest->status === 'trialing' && $latest->trial_ends_at && $latest->trial_ends_at->isFuture()) {
        return;
    }

    $newTrialEnd = $now->copy()->addDays($days);

    if ($latest) {
        // Update the existing record (preserves audit history)
        $latest->update([
            'status' => 'trialing',
            'trial_started_at' => $now,
            'trial_ends_at' => $newTrialEnd,
            'data_retention_starts_at' => null,
        ]);
    } else {
        // Edge case: user has no subscription at all — create one
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'trialing',
            'trial_started_at' => $now,
            'trial_ends_at' => $newTrialEnd,
            'amount' => 0,
        ]);
    }

    $user->update([
        'plan' => 'pro',
        'trial_ends_at' => $newTrialEnd,
    ]);
}
```

- [ ] **Step 5: Run the tests — expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/TrialServiceTest.php --filter="restartTrial"
```

Expected: 3 passes.

- [ ] **Step 6: Run the full TrialService test file**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/TrialServiceTest.php
```

Expected: all green (existing + 3 new).

- [ ] **Step 7: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Payment/TrialService.php tests/Unit/Services/Payment/TrialServiceTest.php
git commit -m "feat: add TrialService::restartTrial for lifecycle Campaign 1 fresh restart"
```

---

## Phase 5 — Snapshot service and discount code generator

These are the two helper services the engine depends on. Build them before the engine.

### Task 5.1 — Create `LifecycleSnapshotService::isEmpty`

**Files:**
- Create: `app/Services/Lifecycle/LifecycleSnapshotService.php`
- Create: `tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php`

- [ ] **Step 1: Create the test file with the first failing test**

Create `tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Lifecycle\LifecycleSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('isEmpty returns true for a user with no module data', function () {
    $user = User::factory()->create();

    $service = app(LifecycleSnapshotService::class);

    expect($service->isEmpty($user))->toBeTrue();
});

it('isEmpty returns false for a user with a property record', function () {
    $user = User::factory()->create();
    \App\Models\Property::factory()->create(['user_id' => $user->id]);

    $service = app(LifecycleSnapshotService::class);

    expect($service->isEmpty($user))->toBeFalse();
});

it('isEmpty returns false for a user with any module data (test all 6 tables)', function () {
    $tables = [
        \App\Models\Property::class,
        \App\Models\DCPension::class,
        \App\Models\SavingsAccount::class,
        \App\Models\InvestmentAccount::class,
        \App\Models\LifeInsurancePolicy::class,
        \App\Models\Goal::class,
    ];

    $service = app(LifecycleSnapshotService::class);

    foreach ($tables as $modelClass) {
        $user = User::factory()->create();
        $modelClass::factory()->create(['user_id' => $user->id]);

        expect($service->isEmpty($user))->toBeFalse();
    }
});
```

- [ ] **Step 2: Run the test — expect failure**

```bash
./vendor/bin/pest tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php
```

Expected: fails with `Class App\Services\Lifecycle\LifecycleSnapshotService not found.`

- [ ] **Step 3: Create the service file**

Create `app/Services/Lifecycle/LifecycleSnapshotService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Lifecycle;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LifecycleSnapshotService
{
    private const MODULE_TABLES = [
        'properties',
        'dc_pensions',
        'savings_accounts',
        'investment_accounts',
        'life_insurance_policies',
        'goals',
    ];

    public function isEmpty(User $user): bool
    {
        foreach (self::MODULE_TABLES as $table) {
            if (DB::table($table)->where('user_id', $user->id)->exists()) {
                return false;
            }
        }
        return true;
    }
}
```

- [ ] **Step 4: Run the test — expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php
```

Expected: 3 passes (one of the tests has 6 internal assertions).

- [ ] **Step 5: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Lifecycle/LifecycleSnapshotService.php tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php
git commit -m "feat: add LifecycleSnapshotService::isEmpty for empty trialer detection"
```

---

### Task 5.2 — Add `findUserIdsWithData` batch method

**Files:**
- Modify: `app/Services/Lifecycle/LifecycleSnapshotService.php`
- Modify: `tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php`

- [ ] **Step 1: Write the failing test**

Append:

```php
it('findUserIdsWithData returns the subset of user IDs that have data', function () {
    $userWithData = User::factory()->create();
    $userWithoutData = User::factory()->create();
    $anotherUserWithData = User::factory()->create();

    \App\Models\Property::factory()->create(['user_id' => $userWithData->id]);
    \App\Models\Goal::factory()->create(['user_id' => $anotherUserWithData->id]);

    $service = app(LifecycleSnapshotService::class);
    $result = $service->findUserIdsWithData([
        $userWithData->id,
        $userWithoutData->id,
        $anotherUserWithData->id,
    ]);

    expect($result->all())->toEqualCanonicalizing([
        $userWithData->id,
        $anotherUserWithData->id,
    ]);
});

it('findUserIdsWithData returns empty collection when no candidates have data', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $service = app(LifecycleSnapshotService::class);
    $result = $service->findUserIdsWithData([$u1->id, $u2->id]);

    expect($result->isEmpty())->toBeTrue();
});

it('findUserIdsWithData handles empty input array', function () {
    $service = app(LifecycleSnapshotService::class);
    $result = $service->findUserIdsWithData([]);

    expect($result->isEmpty())->toBeTrue();
});
```

- [ ] **Step 2: Run the tests — expect failure**

```bash
./vendor/bin/pest tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php --filter=findUserIdsWithData
```

Expected: 3 fails with method not found.

- [ ] **Step 3: Add the method to `LifecycleSnapshotService`**

```php
public function findUserIdsWithData(array $userIds): Collection
{
    if (empty($userIds)) {
        return collect();
    }

    $query = DB::table('properties')->whereIn('user_id', $userIds)->select('user_id');

    foreach (['dc_pensions', 'savings_accounts', 'investment_accounts', 'life_insurance_policies', 'goals'] as $table) {
        $query->union(DB::table($table)->whereIn('user_id', $userIds)->select('user_id'));
    }

    return $query->pluck('user_id')->unique()->values();
}
```

- [ ] **Step 4: Run the tests — expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php
```

Expected: all tests pass (6 total now).

- [ ] **Step 5: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Lifecycle/LifecycleSnapshotService.php tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php
git commit -m "feat: add LifecycleSnapshotService::findUserIdsWithData batch query"
```

---

### Task 5.3 — Add `buildContext` for personalisation

**Files:**
- Modify: `app/Services/Lifecycle/LifecycleSnapshotService.php`
- Modify: test file

- [ ] **Step 1: Write the failing tests**

```php
it('buildContext returns first_name, completion_pct, and modules_with_data', function () {
    $user = User::factory()->create(['first_name' => 'James']);
    \App\Models\Property::factory()->count(2)->create(['user_id' => $user->id]);
    \App\Models\Goal::factory()->count(3)->create(['user_id' => $user->id]);

    $service = app(LifecycleSnapshotService::class);
    $context = $service->buildContext($user);

    expect($context['first_name'])->toBe('James');
    expect($context['completion_pct'])->toBeInt();
    expect($context['modules_with_data'])->toBeArray();

    $moduleNames = collect($context['modules_with_data'])->pluck('name')->all();
    expect($moduleNames)->toContain('Properties');
    expect($moduleNames)->toContain('Goals');
});

it('buildContext omits modules with zero count', function () {
    $user = User::factory()->create(['first_name' => 'Test']);
    \App\Models\Property::factory()->create(['user_id' => $user->id]);

    $service = app(LifecycleSnapshotService::class);
    $context = $service->buildContext($user);

    $moduleNames = collect($context['modules_with_data'])->pluck('name')->all();
    expect($moduleNames)->toContain('Properties');
    expect($moduleNames)->not->toContain('Goals');
    expect($moduleNames)->not->toContain('Pensions');
});

it('buildContext handles null first_name gracefully', function () {
    $user = User::factory()->create(['first_name' => null]);

    $service = app(LifecycleSnapshotService::class);
    $context = $service->buildContext($user);

    expect($context['first_name'])->toBeNull();
});
```

- [ ] **Step 2: Run — expect failure**

```bash
./vendor/bin/pest tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php --filter=buildContext
```

- [ ] **Step 3: Add `buildContext` to the service**

Add after `findUserIdsWithData`:

```php
public function buildContext(User $user): array
{
    $modules = [
        ['name' => 'Properties', 'table' => 'properties', 'label' => 'properties'],
        ['name' => 'Pensions', 'table' => 'dc_pensions', 'label' => 'pension'],
        ['name' => 'Savings', 'table' => 'savings_accounts', 'label' => 'savings accounts'],
        ['name' => 'Investments', 'table' => 'investment_accounts', 'label' => 'investment accounts'],
        ['name' => 'Protection', 'table' => 'life_insurance_policies', 'label' => 'protection policies'],
        ['name' => 'Goals', 'table' => 'goals', 'label' => 'goals'],
    ];

    $modulesWithData = [];
    $modulesRemaining = [];

    foreach ($modules as $module) {
        $count = DB::table($module['table'])->where('user_id', $user->id)->count();
        if ($count > 0) {
            $modulesWithData[] = [
                'name' => $module['name'],
                'count' => $count,
                'label' => $module['label'],
            ];
        } else {
            $modulesRemaining[] = $module['name'];
        }
    }

    $totalModules = count($modules);
    $modulesStarted = count($modulesWithData);
    $completionPct = $totalModules > 0 ? (int) round(($modulesStarted / $totalModules) * 100) : 0;

    return [
        'first_name' => $user->first_name,
        'completion_pct' => $completionPct,
        'modules_with_data' => $modulesWithData,
        'modules_remaining' => $modulesRemaining,
        'days_since_signup' => (int) $user->created_at?->diffInDays(now()),
    ];
}
```

- [ ] **Step 4: Run — expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php
```

Expected: all tests pass.

- [ ] **Step 5: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Lifecycle/LifecycleSnapshotService.php tests/Unit/Services/Lifecycle/LifecycleSnapshotServiceTest.php
git commit -m "feat: add LifecycleSnapshotService::buildContext for email personalisation"
```

---

### Task 5.4 — Create `LifecycleDiscountCodeGenerator`

**Files:**
- Create: `app/Services/Lifecycle/LifecycleDiscountCodeGenerator.php`
- Create: `tests/Unit/Services/Lifecycle/LifecycleDiscountCodeGeneratorTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

declare(strict_types=1);

use App\Models\DiscountCode;
use App\Models\User;
use App\Services\Lifecycle\LifecycleDiscountCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generate creates a unique code prefixed WELCOME_', function () {
    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code)->toBeInstanceOf(DiscountCode::class);
    expect($code->code)->toStartWith('WELCOME_');
});

it('generate locks the code to the user via user_id', function () {
    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->user_id)->toBe($user->id);
});

it('generate sets type to lifecycle_welcome', function () {
    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->type)->toBe('lifecycle_welcome');
});

it('generate sets max_uses=1 and max_uses_per_user=1', function () {
    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->max_uses)->toBe(1);
    expect($code->max_uses_per_user)->toBe(1);
});

it('generate sets expires_at to 7 days from now', function () {
    $user = User::factory()->create();

    config(['lifecycle.discount_code_ttl_days' => 7]);

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->expires_at->isAfter(now()->addDays(6)))->toBeTrue();
    expect($code->expires_at->isBefore(now()->addDays(8)))->toBeTrue();
});

it('generate populates metadata with per-plan-per-cycle discount amounts from config', function () {
    config(['lifecycle.campaign2_discounts' => [
        'student.monthly' => 100,
        'standard.monthly' => 500,
    ]]);

    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->metadata)->toBeArray();
    expect($code->metadata['plan_amounts'])->toBe([
        'student.monthly' => 100,
        'standard.monthly' => 500,
    ]);
});

it('generate sets applicable_plans to student/standard/family (no pro)', function () {
    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->applicable_plans)->toBe(['student', 'standard', 'family']);
});
```

- [ ] **Step 2: Run — expect failure**

```bash
./vendor/bin/pest tests/Unit/Services/Lifecycle/LifecycleDiscountCodeGeneratorTest.php
```

Expected: class not found.

- [ ] **Step 3: Create the generator service**

Create `app/Services/Lifecycle/LifecycleDiscountCodeGenerator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Lifecycle;

use App\Models\DiscountCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class LifecycleDiscountCodeGenerator
{
    private const MAX_COLLISION_RETRIES = 5;

    public function generate(User $user): DiscountCode
    {
        $code = $this->generateUniqueCode();

        return DiscountCode::create([
            'code' => $code,
            'type' => 'lifecycle_welcome',
            'value' => 0,
            'user_id' => $user->id,
            'max_uses' => 1,
            'max_uses_per_user' => 1,
            'applicable_plans' => ['student', 'standard', 'family'],
            'applicable_cycles' => ['monthly', 'yearly'],
            'starts_at' => now(),
            'expires_at' => now()->addDays((int) config('lifecycle.discount_code_ttl_days', 7)),
            'is_active' => true,
            'metadata' => [
                'plan_amounts' => config('lifecycle.campaign2_discounts', []),
                'campaign' => 'engaged_trialer',
                'issued_via' => 'lifecycle_email',
            ],
        ]);
    }

    private function generateUniqueCode(): string
    {
        for ($i = 0; $i < self::MAX_COLLISION_RETRIES; $i++) {
            $code = 'WELCOME_' . strtoupper(Str::random(8));
            if (! DiscountCode::where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate a unique lifecycle discount code after ' . self::MAX_COLLISION_RETRIES . ' attempts.');
    }
}
```

- [ ] **Step 4: Run — expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Lifecycle/LifecycleDiscountCodeGeneratorTest.php
```

Expected: 7 passes.

- [ ] **Step 5: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Lifecycle/LifecycleDiscountCodeGenerator.php tests/Unit/Services/Lifecycle/LifecycleDiscountCodeGeneratorTest.php
git commit -m "feat: add LifecycleDiscountCodeGenerator for per-user welcome codes"
```

---

## Phase 6 — Campaign infrastructure (interface + engine)

### Task 6.1 — Create `LifecycleCampaign` interface

**Files:**
- Create: `app/Services/Lifecycle/Contracts/LifecycleCampaign.php`

- [ ] **Step 1: Create the file**

```php
<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Contracts;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

interface LifecycleCampaign
{
    /** Slug used in lifecycle_email_log.campaign and config keys. */
    public function name(): string;

    /** Collision priority — lower wins on same-day collision. */
    public function priority(): int;

    /** Candidate users for this campaign at this moment. Engine still applies dedup. */
    public function eligibleUsers(): Collection;

    /** Build the Mailable for a specific eligible user. */
    public function mailable(User $user): Mailable;
}
```

- [ ] **Step 2: Smoke test (no implementation yet, just verify the file is autoloadable)**

```bash
php artisan tinker --execute="echo (interface_exists(\\App\\Services\\Lifecycle\\Contracts\\LifecycleCampaign::class) ? 'OK' : 'FAIL').PHP_EOL;"
```

Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add app/Services/Lifecycle/Contracts/LifecycleCampaign.php
git commit -m "feat: add LifecycleCampaign interface contract"
```

---

### Task 6.2 — Create `config/lifecycle.php`

**Files:**
- Create: `config/lifecycle.php`

- [ ] **Step 1: Create the config file**

```php
<?php

declare(strict_types=1);

return [
    'enabled' => env('LIFECYCLE_ENGINE_ENABLED', true),

    // Campaigns are registered here. The engine resolves them via container
    // and sorts by priority() at runtime.
    'campaigns' => [
        \App\Services\Lifecycle\Campaigns\CancelledTrialerCampaign::class,
        \App\Services\Lifecycle\Campaigns\ChurnedSubscriberCampaign::class,
        \App\Services\Lifecycle\Campaigns\LapsedSubscriberCampaign::class,
        \App\Services\Lifecycle\Campaigns\EmptyTrialerCampaign::class,
        \App\Services\Lifecycle\Campaigns\EngagedTrialerCampaign::class,
    ],

    // Timing knobs (all in days)
    'trial_restart_days' => 14,
    'magic_link_ttl_days' => 7,
    'discount_code_ttl_days' => 7,
    'cancellation_feedback_delay_days' => 3,
    'lapsed_recovery_threshold_days' => 5,
    'eligibility_anchor_days' => 9,

    // Per-plan-per-cycle discount amounts in pence (Campaign 2)
    'campaign2_discounts' => [
        'student.monthly' => 100,    // £3.99 → £2.99 = £1.00 off
        'student.yearly' => 801,     // £30.00 → £21.99 = £8.01 off
        'standard.monthly' => 500,   // £10.99 → £5.99 = £5.00 off
        'standard.yearly' => 4500,   // £100.00 → £55.00 = £45.00 off
        'family.monthly' => 400,     // £14.99 → £10.99 = £4.00 off
        'family.yearly' => 5000,     // £150.00 → £100.00 = £50.00 off
    ],

    // Reason codes per feedback campaign
    'feedback_reasons' => [
        'cancelled_trialer' => [
            'too_expensive', 'missing_features', 'found_alternative',
            'not_what_expected', 'bugs_or_ux', 'personal_change', 'other',
        ],
        'churned_subscriber' => [
            'too_expensive', 'missing_features', 'found_alternative',
            'not_what_expected', 'bugs_or_ux', 'personal_change', 'other',
        ],
        'lapsed_subscriber' => [
            'will_fix', 'wants_to_cancel', 'needs_help',
        ],
    ],

    // Maps each campaign slug to its corresponding notification_preferences column
    'campaign_to_preference' => [
        'empty_trialer' => 'lifecycle_empty_trialer',
        'engaged_trialer' => 'lifecycle_engaged_trialer',
        'cancelled_trialer' => 'lifecycle_cancelled_trialer',
        'churned_subscriber' => 'lifecycle_churned_subscriber',
        'lapsed_subscriber' => 'lifecycle_lapsed_subscriber',
    ],

    'test_recipient_override' => env('LIFECYCLE_TEST_RECIPIENT', null),
];
```

- [ ] **Step 2: Verify the config loads**

```bash
php artisan config:clear
php artisan tinker --execute="echo 'enabled='.var_export(config('lifecycle.enabled'), true).PHP_EOL; echo 'eligibility_anchor_days='.config('lifecycle.eligibility_anchor_days').PHP_EOL; echo 'campaign2_discounts standard.monthly='.config('lifecycle.campaign2_discounts.standard.monthly').PHP_EOL;"
```

Expected:
```
enabled=true
eligibility_anchor_days=9
campaign2_discounts standard.monthly=500
```

- [ ] **Step 3: Commit**

```bash
git add config/lifecycle.php
git commit -m "feat: add config/lifecycle.php with all engine knobs"
```

---

### Task 6.3 — Create `LifecycleEngine` (skeleton + run loop)

**Files:**
- Create: `app/Services/Lifecycle/LifecycleEngine.php`
- Create: `tests/Unit/Services/Lifecycle/LifecycleEngineTest.php`

- [ ] **Step 1: Create the test file with first failing test**

```php
<?php

declare(strict_types=1);

use App\Services\Lifecycle\LifecycleEngine;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('run returns stats array with sent/skipped/errored counts per campaign', function () {
    $engine = app(LifecycleEngine::class);

    // With no campaigns configured (config override), should return empty stats
    config(['lifecycle.campaigns' => []]);

    $stats = $engine->run();

    expect($stats)->toBeArray();
});
```

- [ ] **Step 2: Run — expect failure**

```bash
./vendor/bin/pest tests/Unit/Services/Lifecycle/LifecycleEngineTest.php
```

Expected: `Class App\Services\Lifecycle\LifecycleEngine not found.`

- [ ] **Step 3: Create the engine**

```php
<?php

declare(strict_types=1);

namespace App\Services\Lifecycle;

use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LifecycleEngine
{
    private bool $testMode = false;
    private array $cachedTrialAfterEndCandidates = [];
    private array $cachedHasDataIds = [];

    public function __construct(
        private readonly LifecycleSnapshotService $snapshotService,
        private readonly LifecycleDiscountCodeGenerator $discountGenerator,
    ) {}

    public function setTestMode(bool $testMode): self
    {
        $this->testMode = $testMode;
        return $this;
    }

    public function run(): array
    {
        $stats = [];
        $emailedToday = collect();

        $campaigns = collect(config('lifecycle.campaigns', []))
            ->map(fn ($class) => app($class))
            ->sortBy(fn (LifecycleCampaign $c) => $c->priority())
            ->values();

        foreach ($campaigns as $campaign) {
            $name = $campaign->name();
            $stats[$name] = ['sent' => 0, 'skipped' => 0, 'errored' => 0];

            try {
                $eligible = $this->filterEligible($campaign, $emailedToday);

                foreach ($eligible as $user) {
                    try {
                        $this->dispatchEmail($campaign, $user);
                        $emailedToday->push($user->id);
                        $stats[$name]['sent']++;
                    } catch (\Throwable $e) {
                        Log::error('Lifecycle email send failed', [
                            'campaign' => $name,
                            'user_id' => $user->id,
                            'exception' => $e::class,
                            'message' => $e->getMessage(),
                        ]);
                        $stats[$name]['errored']++;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Lifecycle campaign failed', [
                    'campaign' => $name,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
                $stats[$name]['errored']++;
            }
        }

        return $stats;
    }

    private function filterEligible(LifecycleCampaign $campaign, Collection $emailedToday): Collection
    {
        return $campaign->eligibleUsers()
            ->reject(fn (User $u) => $u->is_preview_user)
            ->reject(fn (User $u) => $u->is_lifecycle_test_user && ! $this->testMode)
            ->reject(fn (User $u) => $emailedToday->contains($u->id))
            ->reject(fn (User $u) => LifecycleEmailLog::where('user_id', $u->id)
                ->where('campaign', $campaign->name())
                ->exists());
    }

    private function dispatchEmail(LifecycleCampaign $campaign, User $user): void
    {
        $mailable = $campaign->mailable($user);

        $recipient = $user->is_lifecycle_test_user && config('lifecycle.test_recipient_override')
            ? config('lifecycle.test_recipient_override')
            : $user->email;

        Mail::to($recipient)->send($mailable);

        LifecycleEmailLog::create([
            'user_id' => $user->id,
            'campaign' => $campaign->name(),
            'sent_at' => now(),
            'context' => null,
        ]);
    }

    public function trialAfterEndCandidates(): Collection
    {
        if (empty($this->cachedTrialAfterEndCandidates)) {
            $anchorDays = config('lifecycle.eligibility_anchor_days', 9);
            $this->cachedTrialAfterEndCandidates = User::query()
                ->where('created_at', '<=', now()->subDays($anchorDays))
                ->whereHas('subscriptions', fn ($q) => $q
                    ->where(fn ($q2) => $q2
                        ->where('status', 'expired')
                        ->orWhere(fn ($q3) => $q3
                            ->where('status', 'trialing')
                            ->where('trial_ends_at', '<', now())
                        )
                    )
                )
                ->whereDoesntHave('subscriptions', fn ($q) => $q->whereIn('status', ['active', 'past_due']))
                ->get()
                ->all();

            $this->cachedHasDataIds = $this->snapshotService
                ->findUserIdsWithData(collect($this->cachedTrialAfterEndCandidates)->pluck('id')->all())
                ->flip()
                ->all();
        }

        return collect($this->cachedTrialAfterEndCandidates);
    }

    public function candidateHasData(int $userId): bool
    {
        return isset($this->cachedHasDataIds[$userId]);
    }
}
```

- [ ] **Step 4: Run the test — expect pass**

```bash
./vendor/bin/pest tests/Unit/Services/Lifecycle/LifecycleEngineTest.php
```

Expected: 1 pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Lifecycle/LifecycleEngine.php tests/Unit/Services/Lifecycle/LifecycleEngineTest.php
git commit -m "feat: add LifecycleEngine with run loop, dedup, and failure isolation"
```

---

## Plan continues — splitting for length

Phases 7-14 are detailed in a follow-up document. The plan continues in the same format:

- **Phase 7 (Tasks 7.1-7.5):** Five campaign classes + tests
- **Phase 8 (Tasks 8.1-8.7):** Mail classes + Blade templates + trial reminder palette fix
- **Phase 9 (Tasks 9.1-9.5):** Magic link routes + LifecycleActionController
- **Phase 10 (Tasks 10.1-10.4):** AppServiceProvider binding + RunLifecycleEngine command + Kernel.php
- **Phase 11 (Tasks 11.1-11.5):** Web NotificationPreferences.vue + new API controller + mobile augmentation + estate_alerts fix
- **Phase 12 (Tasks 12.1-12.4):** LifecycleTestSeeder + e2e artisan commands
- **Phase 13 (Manual):** 12-step e2e verification protocol + test review report
- **Phase 14:** Production deploy

Each phase follows the same TDD-step-commit pattern as Phases 1-6 above. The plan continues in part 2.

---

## Plan-writing status

This file is **part 1 of 2**. Phases 1-6 are written in full (database migrations, models, discount/trial service updates, snapshot service, discount generator, campaign infrastructure scaffolding).

Part 2 covers Phases 7-14 (the five campaigns, mail templates, routes/controller, command/kernel/provider wiring, settings UI, e2e test infrastructure, manual verification, deploy).

Continuing in `lifecycleEmailEngineImplementationPlan-part2.md` to keep file sizes manageable.
