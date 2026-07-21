# Pure Freemium Signup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** New users start on the Free tier immediately (no trial), the Free tier becomes a usable first-class state, existing trial-origin users are migrated to Free without data loss, and all trial machinery is removed.

**Architecture:** Registration stops creating a trial `Subscription` and sets `users.tier='free'`. `CheckSubscription` is reworked so a user with no subscription (Free) or an active subscription can write — per-tier creation caps are enforced downstream by the existing `DbTierGate` at store boundaries — while only genuinely churned *paid* users (a terminal subscription row) keep the read-only/grace/`subscription_required` lockout. A data-safe one-off command converts existing trial-origin users (no completed Payment) to Free. Trial code (service methods, cron, emails, lifecycle campaigns, UI) is then fully removed.

**Tech Stack:** Laravel 10 (PHP 8, Pest), Vue 3, MySQL 8. Tests: `./vendor/bin/pest`. Format: `./vendor/bin/pint`.

**Spec:** `docs/superpowers/specs/2026-05-29-pure-freemium-signup-design.md`

**Branch:** `pureFreemium` (off `dev`). Normal `feature → dev → main`. `PAYMENT_ENABLED=true` on dev/prod, so gating changes are live — test under that flag.

**Conventions:**
- Tests auto-seed `TaxConfiguration` in `beforeEach`; use `RefreshDatabase`.
- Never run `php artisan --env=testing` for DB ops.
- Factories: `User::factory()`, `Subscription::factory()->trialing()/->expired()/->active()/->cancelled()`.
- Commit after every green step. Run `./vendor/bin/pint <files>` before each commit.

---

## PR 1 — Signup lands on Free (no trial)

### Task 1.1: Registration creates a Free user, no trial

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php:551-556`
- Test: `tests/Feature/Auth/RegistrationTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Auth/RegistrationTest.php`:

```php
it('creates a free-tier user with no trial on verified registration', function () {
    $pending = \App\Models\PendingRegistration::create([
        'first_name' => 'Free',
        'surname' => 'Signup',
        'email' => 'free.signup@example.com',
        'password' => \Illuminate\Support\Facades\Hash::make('Password1!'),
        'verification_code' => '123456',
        'verification_attempts' => 0,
        'signup_source' => 'web',
    ]);

    $response = $this->postJson('/api/auth/verify-registration', [
        'email' => 'free.signup@example.com',
        'code' => '123456',
    ]);

    $response->assertStatus(201);

    $user = \App\Models\User::where('email', 'free.signup@example.com')->firstOrFail();
    expect($user->tier)->toBe('free');
    expect($user->trial_ends_at)->toBeNull();
    expect(\App\Models\Subscription::where('user_id', $user->id)->exists())->toBeFalse();
});
```

NOTE: confirm the exact verify-registration route + `PendingRegistration` fillable fields against the existing passing tests in this file before running (mirror their setup). Adjust field names to match.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Auth/RegistrationTest.php --filter="creates a free-tier user"`
Expected: FAIL — `tier` is null/`trial_ends_at` set/a Subscription exists (trial still created).

- [ ] **Step 3: Replace the trial block with a Free assignment**

In `AuthController::verifyRegistration` (the user-creation path, currently `:551-556`), replace:

```php
            // Start trial — use selected plan or default to 'standard'
            $plan = ($pending->plan && in_array($pending->plan, ['student', 'standard', 'pro']))
                ? $pending->plan
                : 'standard';
            $billingCycle = in_array($pending->billing_cycle, ['monthly', 'yearly']) ? $pending->billing_cycle : 'yearly';
            $this->trialService->startTrial($user, $plan, $billingCycle);
```

with:

```php
            // Pure freemium: new users start on the Free tier immediately.
            // No trial, no Subscription row — TierResolver resolves tier='free'
            // to the Free tier and DbTierGate enforces free-tier caps. A
            // Subscription is created only on first payment (upgrade).
            $user->update(['tier' => 'free']);
```

Leave the `TrialService` constructor injection in place for now (removed in PR 5). Do not change the consent block below it.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Auth/RegistrationTest.php --filter="creates a free-tier user"`
Expected: PASS

- [ ] **Step 5: Run the full registration suite (catch regressions)**

Run: `./vendor/bin/pest tests/Feature/Auth/RegistrationTest.php`
Expected: PASS. If a pre-existing test asserts a trial/subscription is created on registration, update it to assert `tier='free'` + no subscription (that old expectation is now wrong by design).

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/Api/AuthController.php
git add app/Http/Controllers/Api/AuthController.php tests/Feature/Auth/RegistrationTest.php
git commit -m "feat(freemium): registration lands users on Free tier, no trial"
```

---

## PR 2 — Make Free usable: rework CheckSubscription

### Task 2.1: Free users (no subscription) can write; only churned paid users are locked out

**Files:**
- Modify: `app/Http/Middleware/CheckSubscription.php:98-119`
- Test: `tests/Feature/Middleware/CheckSubscriptionTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Middleware/CheckSubscriptionTest.php`:

```php
<?php

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.payment_enabled' => true]);
});

it('allows a free user (no subscription) to perform writes', function () {
    $user = User::factory()->create(['tier' => 'free']);
    \Laravel\Sanctum\Sanctum::actingAs($user);

    // A write route guarded by CheckSubscription. Use an always-present
    // non-excluded write endpoint; bank-account create is a good candidate.
    $response = $this->postJson('/api/net-worth/cash', [
        'account_name' => 'Free Tier Current',
        'account_type' => 'current',
        'balance' => 100,
        'ownership_type' => 'individual',
    ]);

    // Must NOT be the subscription lockout (403 subscription_required).
    expect($response->json('error') ?? '')->not->toBe('subscription_required');
});

it('blocks writes for a churned paid user with a terminal subscription past grace', function () {
    $user = User::factory()->create(['tier' => null, 'plan' => 'pro']);
    Subscription::factory()->expired()->create([
        'user_id' => $user->id,
        'plan' => 'pro',
        'data_retention_starts_at' => now()->subDays(40), // past 30-day grace
    ]);
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $response = $this->postJson('/api/net-worth/cash', [
        'account_name' => 'Blocked', 'account_type' => 'current',
        'balance' => 1, 'ownership_type' => 'individual',
    ]);

    $response->assertStatus(403);
    expect($response->json('error'))->toBe('subscription_required');
});

it('allows read (GET) for a churned paid user', function () {
    $user = User::factory()->create(['tier' => null, 'plan' => 'pro']);
    Subscription::factory()->expired()->create([
        'user_id' => $user->id, 'plan' => 'pro',
        'data_retention_starts_at' => now()->subDays(40),
    ]);
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $response = $this->getJson('/api/net-worth/cash');
    expect($response->status())->not->toBe(403);
});
```

NOTE: confirm `/api/net-worth/cash` is guarded by `CheckSubscription`, accepts these fields, and is not in `READ_ONLY_EXCLUDED_PATHS`/`ALWAYS_EXCLUDED_PATHS`. If not ideal, pick another non-excluded write route and matching payload (check `routes/api.php`). The assertions only care about the `subscription_required` lockout, not the create succeeding.

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/Middleware/CheckSubscriptionTest.php`
Expected: the free-user write test FAILS — current middleware returns `subscription_required` because the free user has neither `hasActivePlan()` nor `onTrial()`.

- [ ] **Step 3: Rework the decision logic**

In `app/Http/Middleware/CheckSubscription.php`, replace the block at `:98-119`:

```php
        // User has active subscription or is trialing — allow through
        if ($user->hasActivePlan() || $user->onTrial()) {
            return $next($request);
        }

        // Expired trial or grace period — allow read-only access so users can see
        // their data behind the plan selection modal. Writes are blocked.
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        if ($user->isInGracePeriod()) {
            return response()->json([
                'error' => 'grace_period',
                'message' => 'Your subscription has expired. You have read-only access during the grace period.',
            ], 403);
        }

        return response()->json([
            'error' => 'subscription_required',
            'message' => 'Your trial has expired. Please subscribe to continue.',
        ], 403);
```

with:

```php
        $subscription = $user->relationLoaded('subscription')
            ? $user->subscription
            : $user->subscription()->first();

        // Pure freemium: a Free user has NO subscription row and may write —
        // per-tier creation caps are enforced downstream by DbTierGate at the
        // store boundary. An active (paid) subscription may also write.
        if ($subscription === null || $subscription->isActive()) {
            return $next($request);
        }

        // Remaining case: a churned PAID user whose subscription is terminal
        // (expired/cancelled past period). Read-only + grace, then hard block.
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        if ($user->isInGracePeriod()) {
            return response()->json([
                'error' => 'grace_period',
                'message' => 'Your subscription has expired. You have read-only access during the grace period.',
            ], 403);
        }

        return response()->json([
            'error' => 'subscription_required',
            'message' => 'Your subscription has expired. Please subscribe to continue.',
        ], 403);
```

NOTE: `User::isInGracePeriod()` (`app/Models/User.php:282`) delegates to the subscription; leave it. Do not remove `hasActivePlan()` here yet — `onTrial()` is removed in PR 5; this block no longer calls either.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/Middleware/CheckSubscriptionTest.php`
Expected: PASS (free user writes; churned paid blocked on write, allowed on read).

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Http/Middleware/CheckSubscription.php
git add app/Http/Middleware/CheckSubscription.php tests/Feature/Middleware/CheckSubscriptionTest.php
git commit -m "feat(freemium): Free tier is writable; lockout only for churned paid users"
```

### Task 2.2: Confirm DbTierGate still caps Free creates (regression guard)

**Files:**
- Test: `tests/Unit/Services/Tiers/DbTierGateFreeCapTest.php` (create)

- [ ] **Step 1: Write the test**

Create `tests/Unit/Services/Tiers/DbTierGateFreeCapTest.php`:

```php
<?php

use App\Models\User;
use App\Services\Tiers\DbTierGate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('caps a free user at the free-tier limit for an entity', function () {
    $this->seed(\Database\Seeders\TierConfigurationSeeder::class);
    $gate = app(DbTierGate::class);
    $user = User::factory()->create(['tier' => 'free']);

    // Pick an entity key with a known free cap from TierConfigurationSeeder.
    // Replace 'savings_accounts' + cap below with a real seeded key/value.
    $cap = $gate->hardLimit($user, 'savings_accounts');
    expect($cap)->not->toBeNull();

    expect($gate->canCreate($user, 'savings_accounts', $cap - 1))->toBeTrue();
    expect($gate->canCreate($user, 'savings_accounts', $cap))->toBeFalse();
});
```

NOTE: open `database/seeders/TierConfigurationSeeder.php` and use a real entity key + its free cap. If no entity has a finite free cap, this test documents that and can assert `hardLimit` is null (uncapped) instead — but verify with CSJ, since "usable but limited" implies at least one finite free cap.

- [ ] **Step 2: Run it**

Run: `./vendor/bin/pest tests/Unit/Services/Tiers/DbTierGateFreeCapTest.php`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Services/Tiers/DbTierGateFreeCapTest.php
git commit -m "test(freemium): DbTierGate caps Free-tier creates"
```

---

## PR 3 — Repurpose trial-status endpoint + remove the banner

### Task 3.1: `/payment/trial-status` returns tier/subscription state, no trial fields

**Files:**
- Modify: `app/Http/Controllers/Api/PaymentController.php:808-843` (`trialStatus`)
- Test: `tests/Feature/Payment/SubscriptionStatusTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Payment/SubscriptionStatusTest.php`:

```php
<?php

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns free state with no trial fields for a free user', function () {
    $user = User::factory()->create(['tier' => 'free']);
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $response = $this->getJson('/api/payment/trial-status');

    $response->assertStatus(200)
        ->assertJson(['has_subscription' => false, 'tier' => 'free']);
    expect($response->json())->not->toHaveKey('days_remaining');
    expect($response->json())->not->toHaveKey('trial_ends_at');
});

it('returns active paid state for a subscriber', function () {
    $user = User::factory()->create(['tier' => 'tier3']);
    Subscription::factory()->active()->create([
        'user_id' => $user->id, 'plan' => 'tier3',
        'current_period_end' => now()->addYear(),
    ]);
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $response = $this->getJson('/api/payment/trial-status');
    $response->assertStatus(200)
        ->assertJson(['has_subscription' => true, 'status' => 'active']);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Feature/Payment/SubscriptionStatusTest.php`
Expected: FAIL — current payload still returns trial fields / different shape.

- [ ] **Step 3: Replace the `trialStatus` method body**

Replace `PaymentController::trialStatus` (`:808-843`) with:

```php
    public function trialStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;
        $tier = app(\App\Services\Tiers\TierResolver::class)->resolve($user);
        $paymentEnabled = config('app.payment_enabled', false);

        if (! $subscription) {
            return response()->json([
                'has_subscription' => false,
                'tier' => $tier,
                'payment_enabled' => $paymentEnabled,
            ]);
        }

        return response()->json([
            'has_subscription' => true,
            'tier' => $tier,
            'plan' => $subscription->plan,
            'status' => $subscription->status,
            'billing_cycle' => $subscription->billing_cycle,
            'amount' => $subscription->amount,
            'current_period_start' => $subscription->current_period_start?->toISOString(),
            'current_period_end' => $subscription->current_period_end?->toISOString(),
            'cancelled_at' => $subscription->cancelled_at?->toISOString(),
            'data_retention_starts_at' => $paymentEnabled ? $subscription->data_retention_starts_at?->toISOString() : null,
            'grace_period_ends_at' => $paymentEnabled ? $subscription->gracePeriodEndsAt()?->toISOString() : null,
            'is_in_grace_period' => $paymentEnabled && $subscription->isInGracePeriod(),
            'payment_enabled' => $paymentEnabled,
            'auto_renew' => $subscription->auto_renew ?? false,
            'next_renewal_date' => ($subscription->status === 'active' && $subscription->auto_renew)
                ? $subscription->current_period_end?->toISOString()
                : null,
        ]);
    }
```

This drops `trial_ends_at`, `days_remaining`, and `progress`. Keep grace/retention fields (used by `DataRetentionOverlay`).

- [ ] **Step 4: Run to verify pass**

Run: `./vendor/bin/pest tests/Feature/Payment/SubscriptionStatusTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/Api/PaymentController.php
git add app/Http/Controllers/Api/PaymentController.php tests/Feature/Payment/SubscriptionStatusTest.php
git commit -m "feat(freemium): trial-status endpoint returns tier/subscription state, no trial fields"
```

### Task 3.2: Remove the TrialCountdownBanner from the UI

**Files:**
- Delete: `resources/js/components/Trial/TrialCountdownBanner.vue`
- Modify: `resources/js/layouts/AppLayout.vue` (remove import `:158`, component registration `:185`, and template usage)

- [ ] **Step 1: Find every reference**

Run: `grep -rn "TrialCountdownBanner" resources/js`
Expected: import + registration in `AppLayout.vue`, plus a template tag (`<TrialCountdownBanner ...>` — confirm exact line/casing).

- [ ] **Step 2: Remove the template usage, import, and registration in `AppLayout.vue`**

Delete the `<TrialCountdownBanner ... />` element from the template, the `import TrialCountdownBanner ...` line (`:158`), and `TrialCountdownBanner,` from the `components: { ... }` object (`:185`).

- [ ] **Step 3: Delete the component file**

```bash
git rm resources/js/components/Trial/TrialCountdownBanner.vue
```

- [ ] **Step 4: Verify no dangling references + SFC compiles**

Run: `grep -rn "TrialCountdownBanner" resources/js` → expect no matches.
Run: `node -e "const fs=require('fs');const{parse,compileScript}=require('@vue/compiler-sfc');const s=fs.readFileSync('resources/js/layouts/AppLayout.vue','utf8');const{descriptor,errors}=parse(s,{filename:'AppLayout.vue'});if(errors.length){console.error(errors);process.exit(1);}compileScript(descriptor,{id:'x'});console.log('OK')"`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add resources/js/layouts/AppLayout.vue
git commit -m "feat(freemium): remove trial countdown banner from app layout"
```

---

## PR 4 — Data-safe migration of existing trial-origin users

### Task 4.1: One-off command to convert trial-origin users to Free

**Files:**
- Create: `app/Console/Commands/ConvertTrialUsersToFree.php`
- Test: `tests/Feature/Console/ConvertTrialUsersToFreeTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Console/ConvertTrialUsersToFreeTest.php`:

```php
<?php

use App\Models\User;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('converts trial-origin users to Free and halts any deletion countdown', function () {
    $trialing = User::factory()->create(['tier' => null, 'plan' => 'standard']);
    Subscription::factory()->trialing()->create(['user_id' => $trialing->id, 'plan' => 'standard']);

    $expiredTrial = User::factory()->create(['tier' => null, 'plan' => 'standard']);
    Subscription::factory()->expired()->create([
        'user_id' => $expiredTrial->id, 'plan' => 'standard',
        'data_retention_starts_at' => now()->subDays(5),
    ]);

    $this->artisan('freemium:convert-trial-users')->assertExitCode(0);

    foreach ([$trialing, $expiredTrial] as $u) {
        $u->refresh();
        expect($u->tier)->toBe('free');
        expect($u->plan)->toBe('free');
        expect($u->trial_ends_at)->toBeNull();
        expect(Subscription::where('user_id', $u->id)->exists())->toBeFalse();
    }
});

it('leaves genuinely paid users untouched', function () {
    $paidActive = User::factory()->create(['tier' => 'tier3', 'plan' => 'pro']);
    $sub = Subscription::factory()->active()->create(['user_id' => $paidActive->id, 'plan' => 'pro']);
    Payment::factory()->create(['user_id' => $paidActive->id, 'subscription_id' => $sub->id, 'status' => 'completed']);

    $paidChurned = User::factory()->create(['tier' => null, 'plan' => 'pro']);
    $sub2 = Subscription::factory()->expired()->create([
        'user_id' => $paidChurned->id, 'plan' => 'pro',
        'data_retention_starts_at' => now()->subDays(5),
    ]);
    Payment::factory()->create(['user_id' => $paidChurned->id, 'subscription_id' => $sub2->id, 'status' => 'completed']);

    $this->artisan('freemium:convert-trial-users')->assertExitCode(0);

    $paidActive->refresh();
    expect($paidActive->tier)->toBe('tier3');
    expect(Subscription::where('user_id', $paidActive->id)->exists())->toBeTrue();

    $paidChurned->refresh();
    expect(Subscription::where('user_id', $paidChurned->id)->where('status', 'expired')->exists())->toBeTrue();
});

it('dry-run reports counts and changes nothing', function () {
    $trialing = User::factory()->create(['tier' => null, 'plan' => 'standard']);
    Subscription::factory()->trialing()->create(['user_id' => $trialing->id, 'plan' => 'standard']);

    $this->artisan('freemium:convert-trial-users', ['--dry-run' => true])->assertExitCode(0);

    $trialing->refresh();
    expect($trialing->tier)->toBeNull();
    expect(Subscription::where('user_id', $trialing->id)->exists())->toBeTrue();
});
```

NOTE: confirm `Payment::factory()` exists with a `status` field; if not, create the row via `Payment::create([...])` with the minimal required columns (see `database/factories/` or the `payments` migration).

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Feature/Console/ConvertTrialUsersToFreeTest.php`
Expected: FAIL — command does not exist.

- [ ] **Step 3: Implement the command**

Create `app/Console/Commands/ConvertTrialUsersToFree.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertTrialUsersToFree extends Command
{
    protected $signature = 'freemium:convert-trial-users {--dry-run : Report what would change without writing}';

    protected $description = 'Convert trial-origin users (never paid) to the Free tier; leave paid users untouched.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Trial-origin = users with a trialing OR expired subscription who have
        // NO completed payment. Paid-then-churned users (a completed payment)
        // are left on the existing churn/grace path.
        $candidates = User::query()
            ->where('is_preview_user', false)
            ->whereHas('subscription', function ($q) {
                $q->whereIn('status', ['trialing', 'expired']);
            })
            ->whereDoesntHave('subscription.payments', function ($q) {
                $q->where('status', 'completed');
            })
            ->get();

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Trial-origin users to convert: '.$candidates->count());

        if ($dryRun) {
            foreach ($candidates as $u) {
                $this->line("  would convert user {$u->id} ({$u->email}) → free");
            }

            return self::SUCCESS;
        }

        $converted = 0;
        foreach ($candidates as $user) {
            DB::transaction(function () use ($user, &$converted) {
                // Halt any deletion countdown BEFORE removing the subscription.
                Subscription::where('user_id', $user->id)
                    ->update(['data_retention_starts_at' => null]);
                Subscription::where('user_id', $user->id)->delete(); // soft-delete (Subscription uses SoftDeletes)

                $user->update([
                    'tier' => 'free',
                    'plan' => 'free',
                    'trial_ends_at' => null,
                ]);
                $converted++;
            });
        }

        // Data-safety assertion: no converted user is left on a deletion path.
        $stranded = User::whereIn('id', $candidates->pluck('id'))
            ->whereHas('subscription', fn ($q) => $q->whereNotNull('data_retention_starts_at'))
            ->count();
        if ($stranded > 0) {
            $this->error("ABORTED CHECK: {$stranded} converted users still have a deletion countdown. Investigate.");

            return self::FAILURE;
        }

        $this->info("Converted {$converted} users to Free.");

        return self::SUCCESS;
    }
}
```

NOTE: `Subscription` uses `SoftDeletes`, so `delete()` soft-deletes (audit-preserving) and the `whereHas('subscription', ...)` checks exclude soft-deleted rows by default — so post-delete the stranded check sees none, and `CheckSubscription`'s `$user->subscription` (default scope excludes trashed) returns null → Free. Confirm `User::subscription()` is a plain `hasOne`/`belongsTo` honoring soft-deletes.

- [ ] **Step 4: Run to verify pass**

Run: `./vendor/bin/pest tests/Feature/Console/ConvertTrialUsersToFreeTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Console/Commands/ConvertTrialUsersToFree.php
git add app/Console/Commands/ConvertTrialUsersToFree.php tests/Feature/Console/ConvertTrialUsersToFreeTest.php
git commit -m "feat(freemium): data-safe command to convert trial-origin users to Free"
```

---

## PR 5 — Full removal of trial machinery

### Task 5.1: Remove trial-start from registration path + TrialService trial methods

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php` (remove `TrialService` import + constructor param if now unused)
- Modify: `app/Services/Payment/TrialService.php` (remove `startTrial`, `restartTrial`)
- Modify: `app/Http/Controllers/Lifecycle/LifecycleActionController.php:39` (remove `restartTrial` action)
- Delete: `tests/Unit/Services/Payment/TrialServiceTest.php` trial-start/restart cases (keep expiry tests if `expireTrials` survives)

- [ ] **Step 1: Confirm callers are gone**

Run: `grep -rn "startTrial\|restartTrial" app/ routes/`
Expected: only `TrialService` definitions + `LifecycleActionController:39`. (AuthController call was removed in PR 1.)

- [ ] **Step 2: Remove `startTrial` and `restartTrial` from `TrialService`**

Delete both methods. Keep `expireTrials` and `expireCancelledSubscriptions` for now (Task 5.2 decides their fate).

- [ ] **Step 3: Remove the restart-trial action in `LifecycleActionController`**

Remove the branch at `:39` that calls `restartTrial`. If that controller method becomes empty/dead, remove the route + method (check `routes/`). Update/delete its test.

- [ ] **Step 4: Drop unused `TrialService` injection from `AuthController`**

If `TrialService` is no longer referenced in `AuthController`, remove the `use` import and the constructor parameter. Run `grep -n "trialService\|TrialService" app/Http/Controllers/Api/AuthController.php` to confirm none remain.

- [ ] **Step 5: Run affected suites**

Run: `./vendor/bin/pest tests/Unit/Services/Payment/ tests/Feature/Auth/`
Expected: PASS after deleting/adjusting trial-start/restart tests.

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/Services/Payment/TrialService.php app/Http/Controllers/Api/AuthController.php app/Http/Controllers/Lifecycle/LifecycleActionController.php
git add -A
git commit -m "refactor(freemium): remove startTrial/restartTrial and their callers"
```

### Task 5.2: Remove trial reminder cron + emails; repurpose trials:expire

**Files:**
- Modify: `app/Console/Kernel.php:20` (remove `trials:send-reminders`)
- Delete: `app/Console/Commands/SendTrialReminderEmails.php`, `app/Mail/Lifecycle/TrialReminderMail.php` (confirm path), `app/Mail/EndOfTrialMail.php` (confirm path), and their tests (`tests/Unit/Console/Commands/SendTrialReminderEmailsTest.php`)
- Modify: `app/Console/Commands/ExpireTrials.php` — keep only `expireCancelledSubscriptions()`; remove the `expireTrials()` call (no trials exist). Rename command/description optional.

- [ ] **Step 1: Remove the cron line**

In `app/Console/Kernel.php`, delete line 20 (`$schedule->command('trials:send-reminders')->dailyAt('09:00');`). Leave `trials:expire` (`:23`).

- [ ] **Step 2: Delete the reminder command + mailables + test**

```bash
grep -rln "SendTrialReminderEmails\|TrialReminderMail\|EndOfTrialMail" app/ tests/
git rm app/Console/Commands/SendTrialReminderEmails.php tests/Unit/Console/Commands/SendTrialReminderEmailsTest.php
# git rm the two mailables at their confirmed paths
```

- [ ] **Step 3: Repurpose `ExpireTrials`**

In `app/Console/Commands/ExpireTrials.php::handle`, remove the `$trialService->expireTrials()` call; keep `$trialService->expireCancelledSubscriptions()`. Then remove `TrialService::expireTrials()` itself (now unused) and its test cases.

- [ ] **Step 4: Run**

Run: `./vendor/bin/pest tests/Unit/Console/ tests/Unit/Services/Payment/`
Expected: PASS after removing the deleted-command tests.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Console/Kernel.php app/Console/Commands/ExpireTrials.php app/Services/Payment/TrialService.php
git add -A
git commit -m "refactor(freemium): remove trial reminder cron/emails; expire-cron handles cancelled paid only"
```

### Task 5.3: Remove the three trialer lifecycle campaigns

**Files:**
- Modify: `config/lifecycle.php:16,19,20` (remove `CancelledTrialerCampaign`, `EmptyTrialerCampaign`, `EngagedTrialerCampaign` from `campaigns`; remove now-unused `use` imports `:4,6,7`)
- Delete: `app/Services/Lifecycle/Campaigns/{CancelledTrialer,EmptyTrialer,EngagedTrialer}Campaign.php` + their mailables (`app/Mail/Lifecycle/{CancelledTrialer,EmptyTrialer,EngagedTrialer}Mail.php`)
- Delete: `tests/Feature/Lifecycle/Campaigns/{CancelledTrialer,EmptyTrialer,EngagedTrialer}CampaignTest.php`
- Keep: `LapsedSubscriberCampaign`, `ChurnedSubscriberCampaign` (paid churn)

- [ ] **Step 1: Remove from the registry**

Edit `config/lifecycle.php`: remove the three classes from the `'campaigns'` array (`:16,19,20`) and their `use` lines (`:4,6,7`). Leave `LapsedSubscriberCampaign` + `ChurnedSubscriberCampaign`.

- [ ] **Step 2: Delete the campaign classes, mailables, and tests**

```bash
git rm app/Services/Lifecycle/Campaigns/CancelledTrialerCampaign.php \
       app/Services/Lifecycle/Campaigns/EmptyTrialerCampaign.php \
       app/Services/Lifecycle/Campaigns/EngagedTrialerCampaign.php \
       tests/Feature/Lifecycle/Campaigns/CancelledTrialerCampaignTest.php \
       tests/Feature/Lifecycle/Campaigns/EmptyTrialerCampaignTest.php \
       tests/Feature/Lifecycle/Campaigns/EngagedTrialerCampaignTest.php
# git rm the three Mail/Lifecycle mailables at confirmed paths
```

- [ ] **Step 3: Verify no dangling references**

Run: `grep -rn "Trialer" app/ config/ tests/ resources/`
Expected: no matches (or only in unrelated comments — resolve each).

- [ ] **Step 4: Run the lifecycle suite**

Run: `./vendor/bin/pest tests/Feature/Lifecycle/ tests/Unit/Services/Lifecycle/ 2>/dev/null; ./vendor/bin/pest --filter=Lifecycle`
Expected: PASS (only Lapsed/Churned remain).

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint config/lifecycle.php
git add -A
git commit -m "refactor(freemium): remove trialer lifecycle campaigns (keep paid-churn campaigns)"
```

### Task 5.4: Remove trial-only Subscription/User members + admin metrics

**Files:**
- Modify: `app/Models/Subscription.php` — remove `scopeTrialing` (`:71-74`), `isTrialing` (`:81-84`), `daysLeftInTrial` (`:124-131`), `trialProgress` (`:133-147`)
- Modify: `app/Models/User.php` — remove `onTrial()` (`:252-...`)
- Modify: `app/Http/Middleware/CheckFeatureAccess.php` — remove the `if ($user->onTrial()) return $next(...)` branch
- Modify: admin metrics that count `trialing` — `app/Services/.../UserMetricsService` + `AdminController` (the `Subscription::where('status','trialing')` / `whereIn(['active','trialing'])` usages) and `resources/js/components/Admin/TrialBreakdown.vue`

- [ ] **Step 1: Find all callers before removing**

Run: `grep -rn "isTrialing\|onTrial\|daysLeftInTrial\|trialProgress\|scopeTrialing\|->trialing()\|'trialing'" app/ resources/js/`
List every hit; each must be removed or rewritten in this task.

- [ ] **Step 2: Remove the methods/scopes**

Delete the four `Subscription` members and `User::onTrial()`. In `CheckFeatureAccess`, delete the trial branch (free users already resolve via the plan-order default; the real gate is `DbTierGate`).

- [ ] **Step 3: Update admin metrics**

In the metrics service + `AdminController`, drop `trialing` from status breakdowns (e.g. `whereIn('status', ['active'])` where it was `['active','trialing']`). Update `TrialBreakdown.vue` to remove the trial dimension (or delete the component if it becomes empty — confirm where it's used first).

- [ ] **Step 4: Verify no dangling references + run full suite**

Run: `grep -rn "isTrialing\|onTrial\|daysLeftInTrial\|trialProgress\|scopeTrialing" app/ resources/js/` → expect none.
Run: `./vendor/bin/pest`
Expected: PASS (entire suite). Fix any remaining trial-coupled test by updating it to the freemium expectation or deleting it if it tested removed behaviour.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Models/Subscription.php app/Models/User.php app/Http/Middleware/CheckFeatureAccess.php
git add -A
git commit -m "refactor(freemium): remove trial-only model methods, onTrial gate branch, and admin trial metrics"
```

---

## PR 6 — Verification & deploy

### Task 6.1: Full-suite + architecture green

- [ ] **Step 1:** Run `./vendor/bin/pest` — expect all green. Fix fallout (deleted trial behaviour) by updating/removing the specific tests, never by weakening freemium assertions.
- [ ] **Step 2:** Run `./vendor/bin/pest --testsuite=Architecture` — expect green.
- [ ] **Step 3:** Run `./vendor/bin/pint --test` — expect clean.

### Task 6.2: csjones browser test (per CLAUDE.md browser-testing law)

- [ ] **Step 1:** Merge PR → `dev`; build `./deploy/csjones-fynla/build.sh`; deploy to csjones (git pull + upload bundle + `php artisan migrate --force && php artisan optimize`).
- [ ] **Step 2:** Run the migration **dry-run** on csjones: `php artisan freemium:convert-trial-users --dry-run` — review counts. Then live: `php artisan freemium:convert-trial-users`. Assert no converted user has a pending deletion.
- [ ] **Step 3:** Register a brand-new user on `https://csjones.co/fynla` (fetch the verification code from the csjones DB). Verify: lands on dashboard as **Free**, **no trial banner**, can create data within free caps, hits the upgrade prompt at the free cap, and a sandbox upgrade raises the tier. Interact (click/fill/submit) — reading the page is not a test.
- [ ] **Step 4:** Confirm an existing converted Free user (e.g. john) can write and has no banner; confirm a paid user is unaffected.

### Task 6.3: Production release

- [ ] **Step 1:** Open `dev → main` PR, admin-merge.
- [ ] **Step 2:** Build `./deploy/fynla-org/build.sh`; upload bundle + changed PHP; `php artisan migrate --force && php artisan optimize`.
- [ ] **Step 3:** Run `php artisan freemium:convert-trial-users --dry-run` on prod, review, then run live. Verify the data-safety assertion passed (exit 0, no stranded users).
- [ ] **Step 4:** Smoke test a fresh prod signup → Free, no banner. Monitor `storage/logs/laravel.log` for 10-15 min.

---

## Self-Review Notes (author)

- **Spec coverage:** §1 signup→free = PR1; §2 CheckSubscription rework = PR2; §3 free representation + trial-status repurpose + banner = PR3; §4 migration = PR4; §5 full removal = PR5; testing/rollout = PR6. All spec sections mapped.
- **Open confirmations the executor MUST resolve before running a step (flagged inline):** exact verify-registration route + `PendingRegistration` fields (1.1); a non-excluded write route for the middleware test (2.1); a real free-capped entity key (2.2); `Payment` factory shape (4.1); exact mailable paths for the three trialer emails + reminder mails (5.2/5.3); `TrialBreakdown.vue` usage before deletion (5.4). These are lookups, not design gaps.
- **Type consistency:** command signature `freemium:convert-trial-users` used identically in 4.1 + 6.2 + 6.3; `trialStatus` payload keys (`has_subscription`, `tier`, `status`) consistent across 3.1 and the SubscriptionStatus test.
