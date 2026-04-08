# Refer a Friend — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Paid subscribers can refer friends; when a friend purchases a subscription, both get bonus subscription time (+1 week for monthly, +1 month for annual).

**Architecture:** New `referrals` table tracks invitations. Referral code stored on `users.referral_code`, passed through registration via `?ref=` query param and stored on `users.referred_by_code`. Bonus applied in `confirmPayment()` by extending `current_period_end`.

**Tech Stack:** Laravel (migration, model, service, controller, mailable), Vue.js (modal component, Navbar modification, Register modification)

**Spec:** `April/April8Updates/referFriend-spec.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `database/migrations/2026_04_08_150001_add_referral_columns_to_users_table.php` | Create | Add `referral_code` + `referred_by_code` to users |
| `database/migrations/2026_04_08_150002_create_referrals_table.php` | Create | Referral tracking table |
| `database/migrations/2026_04_08_150003_add_referral_code_to_pending_registrations_table.php` | Create | Store referral code during registration |
| `app/Models/Referral.php` | Create | Referral model |
| `app/Services/Payment/ReferralService.php` | Create | Code generation, invitation, bonus logic |
| `app/Http/Controllers/Api/ReferralController.php` | Create | 3 API endpoints |
| `app/Mail/ReferralInvitationEmail.php` | Create | Invitation email mailable |
| `resources/views/emails/referral-invitation.blade.php` | Create | Invitation email template |
| `resources/js/components/Payment/ReferralModal.vue` | Create | Refer a Friend modal |
| `app/Models/User.php` | Modify | Add `referrals()` relationship |
| `app/Models/PendingRegistration.php` | Modify | Add `referral_code` to fillable |
| `app/Http/Controllers/Api/AuthController.php` | Modify | Store referral code on registration + user creation |
| `app/Http/Controllers/Api/PaymentController.php` | Modify | Apply referral bonus after payment |
| `resources/js/components/Navbar.vue` | Modify | Add "Refer a Friend" button |
| `resources/js/views/Register.vue` | Modify | Read `ref` query param |
| `routes/api.php` | Modify | Add referral routes |
| `tests/Unit/Services/Payment/ReferralServiceTest.php` | Create | Unit tests |

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_04_08_150001_add_referral_columns_to_users_table.php`
- Create: `database/migrations/2026_04_08_150002_create_referrals_table.php`
- Create: `database/migrations/2026_04_08_150003_add_referral_code_to_pending_registrations_table.php`

- [ ] **Step 1: Create users referral columns migration**

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
            if (Schema::hasColumn('users', 'referral_code')) {
                return;
            }
            $table->string('referral_code', 20)->nullable()->unique()->after('revolut_customer_id');
            $table->string('referred_by_code', 20)->nullable()->after('referral_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['referral_code', 'referred_by_code']);
        });
    }
};
```

- [ ] **Step 2: Create referrals table migration**

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
        if (Schema::hasTable('referrals')) {
            return;
        }

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referral_code', 20)->index();
            $table->string('referee_email', 255);
            $table->enum('status', ['pending', 'registered', 'converted', 'expired'])->default('pending');
            $table->boolean('bonus_applied')->default(false);
            $table->timestamp('referred_at');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'referee_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
```

- [ ] **Step 3: Create pending_registrations referral_code migration**

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
        Schema::table('pending_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('pending_registrations', 'referral_code')) {
                return;
            }
            $table->string('referral_code', 20)->nullable()->after('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropColumn('referral_code');
        });
    }
};
```

- [ ] **Step 4: Run migrations**

```bash
php artisan migrate
```

Expected: All 3 migrations run successfully.

- [ ] **Step 5: Verify**

```bash
php artisan tinker --execute="echo Schema::hasColumn('users', 'referral_code') ? 'OK' : 'FAIL';"
php artisan tinker --execute="echo Schema::hasTable('referrals') ? 'OK' : 'FAIL';"
php artisan tinker --execute="echo Schema::hasColumn('pending_registrations', 'referral_code') ? 'OK' : 'FAIL';"
```

Expected: All OK.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_04_08_15000*.php
git commit -m "feat: add referral database migrations"
```

---

## Task 2: Referral Model

**Files:**
- Create: `app/Models/Referral.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/PendingRegistration.php`

- [ ] **Step 1: Create Referral model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referee_id',
        'referral_code',
        'referee_email',
        'status',
        'bonus_applied',
        'referred_at',
        'registered_at',
        'converted_at',
    ];

    protected $casts = [
        'bonus_applied' => 'boolean',
        'referred_at' => 'datetime',
        'registered_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }
}
```

- [ ] **Step 2: Add referrals relationship to User model**

In `app/Models/User.php`, add after the last existing relationship method:

```php
public function referralsSent(): HasMany
{
    return $this->hasMany(Referral::class, 'referrer_id');
}
```

Add `use Illuminate\Database\Eloquent\Relations\HasMany;` if not already imported.

- [ ] **Step 3: Add referral_code to PendingRegistration fillable**

In `app/Models/PendingRegistration.php`, add `'referral_code'` to the `$fillable` array after `'billing_cycle'`.

- [ ] **Step 4: Verify model resolves**

```bash
php artisan tinker --execute="new \App\Models\Referral(); echo 'OK';"
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/Referral.php app/Models/User.php app/Models/PendingRegistration.php
git commit -m "feat: add Referral model and User relationship"
```

---

## Task 3: ReferralService

**Files:**
- Create: `app/Services/Payment/ReferralService.php`
- Test: `tests/Unit/Services/Payment/ReferralServiceTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\Referral;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed tax config for any dependent services
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

describe('generateCode', function () {
    it('generates a code in FYN-XXXXX format', function () {
        $user = User::factory()->create();
        $service = app(ReferralService::class);

        $code = $service->generateCode($user);

        expect($code)->toMatch('/^FYN-[A-Z0-9]{5}$/');
        expect($user->fresh()->referral_code)->toBe($code);
    });

    it('returns existing code if user already has one', function () {
        $user = User::factory()->create(['referral_code' => 'FYN-EXIST']);
        $service = app(ReferralService::class);

        $code = $service->generateCode($user);

        expect($code)->toBe('FYN-EXIST');
    });
});

describe('sendInvitation', function () {
    it('creates a referral record and sends email', function () {
        Mail::fake();
        $user = User::factory()->create(['referral_code' => 'FYN-ABC12']);
        $user->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        $service = app(ReferralService::class);
        $referral = $service->sendInvitation($user, 'friend@example.com');

        expect($referral->status)->toBe('pending');
        expect($referral->referee_email)->toBe('friend@example.com');
        expect($referral->referral_code)->toBe('FYN-ABC12');
        Mail::assertSent(\App\Mail\ReferralInvitationEmail::class);
    });

    it('rejects if user has no active subscription', function () {
        $user = User::factory()->create();
        $user->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'trialing',
            'amount' => 0, 'current_period_start' => now(), 'current_period_end' => now()->addDays(7),
        ]);

        $service = app(ReferralService::class);

        expect(fn () => $service->sendInvitation($user, 'friend@example.com'))
            ->toThrow(\InvalidArgumentException::class, 'active paid subscription');
    });

    it('rejects self-referral', function () {
        $user = User::factory()->create(['email' => 'me@example.com', 'referral_code' => 'FYN-SELF1']);
        $user->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        $service = app(ReferralService::class);

        expect(fn () => $service->sendInvitation($user, 'me@example.com'))
            ->toThrow(\InvalidArgumentException::class, 'cannot refer yourself');
    });

    it('rejects duplicate invitation to same email', function () {
        $user = User::factory()->create(['referral_code' => 'FYN-DUP12']);
        $user->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        Referral::create([
            'referrer_id' => $user->id, 'referral_code' => 'FYN-DUP12',
            'referee_email' => 'already@example.com', 'status' => 'pending', 'referred_at' => now(),
        ]);

        $service = app(ReferralService::class);

        expect(fn () => $service->sendInvitation($user, 'already@example.com'))
            ->toThrow(\InvalidArgumentException::class, 'already invited');
    });
});

describe('applyReferralBonus', function () {
    it('extends both subscriptions by 1 week for monthly purchase', function () {
        $referrer = User::factory()->create(['referral_code' => 'FYN-REF01']);
        $referrerSub = $referrer->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'monthly', 'status' => 'active',
            'amount' => 1099, 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $referrerOriginalEnd = $referrerSub->current_period_end->copy();

        $referee = User::factory()->create(['referred_by_code' => 'FYN-REF01']);
        $refereeSub = $referee->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'monthly', 'status' => 'active',
            'amount' => 1099, 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $refereeOriginalEnd = $refereeSub->current_period_end->copy();

        Referral::create([
            'referrer_id' => $referrer->id, 'referee_id' => $referee->id,
            'referral_code' => 'FYN-REF01', 'referee_email' => $referee->email,
            'status' => 'registered', 'referred_at' => now(), 'registered_at' => now(),
        ]);

        $service = app(ReferralService::class);
        $service->applyReferralBonus($referee, 'monthly');

        $referrerSub->refresh();
        $refereeSub->refresh();

        expect($referrerSub->current_period_end->diffInDays($referrerOriginalEnd))->toBe(7);
        expect($refereeSub->current_period_end->diffInDays($refereeOriginalEnd))->toBe(7);

        $referral = Referral::where('referee_id', $referee->id)->first();
        expect($referral->bonus_applied)->toBeTrue();
        expect($referral->status)->toBe('converted');
    });

    it('extends both subscriptions by 1 month for annual purchase', function () {
        $referrer = User::factory()->create(['referral_code' => 'FYN-REF02']);
        $referrerSub = $referrer->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);
        $referrerOriginalEnd = $referrerSub->current_period_end->copy();

        $referee = User::factory()->create(['referred_by_code' => 'FYN-REF02']);
        $refereeSub = $referee->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);
        $refereeOriginalEnd = $refereeSub->current_period_end->copy();

        Referral::create([
            'referrer_id' => $referrer->id, 'referee_id' => $referee->id,
            'referral_code' => 'FYN-REF02', 'referee_email' => $referee->email,
            'status' => 'registered', 'referred_at' => now(), 'registered_at' => now(),
        ]);

        $service = app(ReferralService::class);
        $service->applyReferralBonus($referee, 'yearly');

        $referrerSub->refresh();
        $refereeSub->refresh();

        expect($referrerSub->current_period_end->diffInMonths($referrerOriginalEnd))->toBe(1);
        expect($refereeSub->current_period_end->diffInMonths($refereeOriginalEnd))->toBe(1);
    });

    it('does not apply bonus twice', function () {
        $referrer = User::factory()->create(['referral_code' => 'FYN-ONCE1']);
        $referrer->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        $referee = User::factory()->create(['referred_by_code' => 'FYN-ONCE1']);
        $referee->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        Referral::create([
            'referrer_id' => $referrer->id, 'referee_id' => $referee->id,
            'referral_code' => 'FYN-ONCE1', 'referee_email' => $referee->email,
            'status' => 'converted', 'bonus_applied' => true,
            'referred_at' => now(), 'registered_at' => now(), 'converted_at' => now(),
        ]);

        $service = app(ReferralService::class);
        $service->applyReferralBonus($referee, 'yearly');

        // Should not throw, just silently skip
        expect(Referral::where('referee_id', $referee->id)->first()->bonus_applied)->toBeTrue();
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/ReferralServiceTest.php
```

Expected: FAIL — ReferralService class not found.

- [ ] **Step 3: Implement ReferralService**

```php
<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Mail\ReferralInvitationEmail;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ReferralService
{
    /**
     * Generate or return existing referral code for a user.
     */
    public function generateCode(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        do {
            $code = 'FYN-' . strtoupper(Str::random(5));
        } while (User::where('referral_code', $code)->exists());

        $user->update(['referral_code' => $code]);

        return $code;
    }

    /**
     * Send a referral invitation email.
     */
    public function sendInvitation(User $referrer, string $email): Referral
    {
        $email = strtolower(trim($email));

        // Validate referrer has active paid subscription
        $subscription = $referrer->subscription;
        if (! $subscription || $subscription->status !== 'active') {
            throw new \InvalidArgumentException('You must have an active paid subscription to refer a friend.');
        }

        // Prevent self-referral
        if (strtolower($referrer->email) === $email) {
            throw new \InvalidArgumentException('You cannot refer yourself.');
        }

        // Prevent duplicate referrals to the same email
        $existing = Referral::where('referrer_id', $referrer->id)
            ->where('referee_email', $email)
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('You have already invited this person.');
        }

        // Ensure referrer has a code
        $code = $this->generateCode($referrer);

        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'referral_code' => $code,
            'referee_email' => $email,
            'status' => 'pending',
            'referred_at' => now(),
        ]);

        // Send invitation email
        try {
            Mail::to($email)->send(new ReferralInvitationEmail($referrer, $code));
        } catch (\Exception $e) {
            Log::error('Failed to send referral invitation email', [
                'referrer_id' => $referrer->id,
                'referee_email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Referral invitation sent', [
            'referrer_id' => $referrer->id,
            'referee_email' => $email,
            'referral_code' => $code,
        ]);

        return $referral;
    }

    /**
     * Link a newly registered user to their referral.
     */
    public function applyReferralOnRegistration(User $newUser, string $referralCode): void
    {
        // Find referral by code + email match, or just by code if email doesn't match
        $referral = Referral::where('referral_code', $referralCode)
            ->where('referee_email', strtolower($newUser->email))
            ->where('status', 'pending')
            ->first();

        // If no email match, try matching by code alone (user registered with different email)
        if (! $referral) {
            $referral = Referral::where('referral_code', $referralCode)
                ->whereNull('referee_id')
                ->where('status', 'pending')
                ->orderBy('referred_at', 'asc')
                ->first();
        }

        if (! $referral) {
            // No matching referral found — store the code on the user anyway
            // so we can match later if needed
            $newUser->update(['referred_by_code' => $referralCode]);
            return;
        }

        $referral->update([
            'referee_id' => $newUser->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $newUser->update(['referred_by_code' => $referralCode]);

        Log::info('Referral registration linked', [
            'referral_id' => $referral->id,
            'referee_id' => $newUser->id,
            'referral_code' => $referralCode,
        ]);
    }

    /**
     * Apply referral bonus after a successful payment.
     */
    public function applyReferralBonus(User $referee, string $billingCycle): void
    {
        if (! $referee->referred_by_code) {
            return;
        }

        // Find the referral that hasn't had bonus applied yet
        $referral = Referral::where('referee_id', $referee->id)
            ->where('bonus_applied', false)
            ->first();

        if (! $referral) {
            return;
        }

        $referrer = $referral->referrer;
        if (! $referrer) {
            return;
        }

        // Calculate bonus
        $isMonthly = $billingCycle === 'monthly';

        // Extend referee's subscription
        $refereeSub = $referee->subscription;
        if ($refereeSub) {
            $refereeSub->update([
                'current_period_end' => $isMonthly
                    ? $refereeSub->current_period_end->addWeek()
                    : $refereeSub->current_period_end->addMonth(),
            ]);
        }

        // Extend referrer's subscription
        $referrerSub = $referrer->subscription;
        if ($referrerSub) {
            $referrerSub->update([
                'current_period_end' => $isMonthly
                    ? $referrerSub->current_period_end->addWeek()
                    : $referrerSub->current_period_end->addMonth(),
            ]);
        }

        // Mark referral as converted
        $referral->update([
            'bonus_applied' => true,
            'status' => 'converted',
            'converted_at' => now(),
        ]);

        $bonusText = $isMonthly ? '1 week' : '1 month';
        Log::info('Referral bonus applied', [
            'referral_id' => $referral->id,
            'referrer_id' => $referrer->id,
            'referee_id' => $referee->id,
            'bonus' => $bonusText,
            'billing_cycle' => $billingCycle,
        ]);
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/ReferralServiceTest.php
```

Expected: Tests fail because ReferralInvitationEmail doesn't exist yet. We'll create it in Task 4. For now, verify the non-email tests pass and the email test fails only on the missing mailable.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Payment/ReferralService.php tests/Unit/Services/Payment/ReferralServiceTest.php
git commit -m "feat: add ReferralService with unit tests"
```

---

## Task 4: Email — ReferralInvitationEmail

**Files:**
- Create: `app/Mail/ReferralInvitationEmail.php`
- Create: `resources/views/emails/referral-invitation.blade.php`

- [ ] **Step 1: Create mailable**

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferralInvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $referrer,
        public string $referralCode
    ) {}

    public function envelope(): Envelope
    {
        $name = trim(($this->referrer->first_name ?? '') . ' ' . ($this->referrer->surname ?? ''));

        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "{$name} thinks you'd like Fynla",
        );
    }

    public function content(): Content
    {
        $registerUrl = config('app.url') . '/register?ref=' . $this->referralCode;

        return new Content(
            view: 'emails.referral-invitation',
            with: [
                'referrerName' => trim(($this->referrer->first_name ?? '') . ' ' . ($this->referrer->surname ?? '')),
                'referralCode' => $this->referralCode,
                'registerUrl' => $registerUrl,
            ],
        );
    }
}
```

- [ ] **Step 2: Create email template**

Create `resources/views/emails/referral-invitation.blade.php`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You've Been Invited to Fynla</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
        .header { background: linear-gradient(135deg, #1F2A44 0%, #2d3a5c 100%); padding: 24px 30px; text-align: center; }
        .header h1 { color: #ffffff; font-size: 22px; margin: 0; font-weight: 700; }
        .header p { color: rgba(255,255,255,0.7); font-size: 13px; margin: 4px 0 0; }
        .content { padding: 30px; }
        .content p { margin: 0 0 15px 0; }
        .highlight-box { background: #f0fdf7; border-left: 4px solid #20B486; padding: 14px 18px; margin: 20px 0; font-size: 14px; border-radius: 0 6px 6px 0; }
        .btn { display: inline-block; background: #E83E6D; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-weight: 600; font-size: 16px; margin: 20px 0; }
        .btn-container { text-align: center; margin: 25px 0; }
        .footer { padding: 20px 30px; background: #fafafa; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
        .footer a { color: #E83E6D; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>You've Been Invited</h1>
            <p>Your friend thinks you'd enjoy Fynla</p>
        </div>

        <div class="content">
            <p>Hello,</p>

            <p><strong>{{ $referrerName }}</strong> thinks you'd like Fynla — your personal financial planning companion.</p>

            <p>Fynla helps you plan your savings, investments, retirement, and estate with confidence, all within UK regulations. Whether you're just starting out or planning ahead, Fynla gives you the tools to take control of your financial future.</p>

            <div class="highlight-box">
                <strong>Bonus:</strong> Sign up and you'll both get extra time on your subscriptions — an extra week with a monthly plan, or an extra month with an annual plan.
            </div>

            <div class="btn-container">
                <a href="{{ $registerUrl }}" class="btn">Create Your Free Account</a>
            </div>

            <p style="font-size: 13px; color: #717171;">Your referral code <strong>{{ $referralCode }}</strong> will be applied automatically when you register using the link above.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Fynla. All rights reserved.</p>
            <p>Questions? Contact us at <a href="mailto:support@fynla.org">support@fynla.org</a></p>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 3: Run ReferralService tests again**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/ReferralServiceTest.php
```

Expected: ALL PASS.

- [ ] **Step 4: Verify template compiles**

```bash
php artisan view:clear && php artisan view:cache
```

Expected: No errors.

- [ ] **Step 5: Commit**

```bash
git add app/Mail/ReferralInvitationEmail.php resources/views/emails/referral-invitation.blade.php
git commit -m "feat: add referral invitation email"
```

---

## Task 5: ReferralController + Routes

**Files:**
- Create: `app/Http/Controllers/Api/ReferralController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\ReferralService;
use App\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly ReferralService $referralService
    ) {}

    /**
     * Get the authenticated user's referral code.
     * GET /api/referral/code
     */
    public function getMyCode(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only paid subscribers can refer
        $subscription = $user->subscription;
        if (! $subscription || $subscription->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'You must have an active subscription to refer a friend.',
            ], 403);
        }

        $code = $this->referralService->generateCode($user);

        return response()->json([
            'success' => true,
            'data' => ['code' => $code],
        ]);
    }

    /**
     * Send a referral invitation.
     * POST /api/referral/invite
     */
    public function sendInvitation(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $user = $request->user();

        try {
            $referral = $this->referralService->sendInvitation($user, $request->input('email'));

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent successfully.',
                'data' => [
                    'referral_id' => $referral->id,
                    'email' => $referral->referee_email,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Sending referral invitation');
        }
    }

    /**
     * Get the user's referral history.
     * GET /api/referral/list
     */
    public function myReferrals(Request $request): JsonResponse
    {
        $user = $request->user();

        $referrals = $user->referralsSent()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'email' => $r->referee_email,
                'status' => $r->status,
                'bonus_applied' => $r->bonus_applied,
                'referred_at' => $r->referred_at?->toIso8601String(),
                'registered_at' => $r->registered_at?->toIso8601String(),
                'converted_at' => $r->converted_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => ['referrals' => $referrals],
        ]);
    }
}
```

- [ ] **Step 2: Add routes to `routes/api.php`**

Add after the existing `payment` group (around line 998):

```php
// Referral
Route::middleware('auth:sanctum')->prefix('referral')->group(function () {
    Route::get('/code', [ReferralController::class, 'getMyCode']);
    Route::post('/invite', [ReferralController::class, 'sendInvitation'])->middleware('throttle:10,1');
    Route::get('/list', [ReferralController::class, 'myReferrals']);
});
```

Add the import at the top of the file:

```php
use App\Http\Controllers\Api\ReferralController;
```

- [ ] **Step 3: Verify routes compile**

```bash
php artisan route:list --path=referral
```

Expected: Shows 3 referral routes.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/ReferralController.php routes/api.php
git commit -m "feat: add referral API endpoints"
```

---

## Task 6: Hook into Registration Flow

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php`
- Modify: `resources/js/views/Register.vue`

- [ ] **Step 1: Modify Register.vue to capture `ref` query param**

In `resources/js/views/Register.vue`, around line 265 where `selectedPlan` and `selectedBilling` are read from query params, add:

```javascript
const referralCode = route.query.ref || null;
```

Then around line 289 where the payload is built, add after the billing_cycle block:

```javascript
if (referralCode) {
  payload.referral_code = referralCode;
}
```

- [ ] **Step 2: Modify AuthController::register() to accept referral_code**

In `app/Http/Controllers/Api/AuthController.php`, in the `register()` method around line 68, add `'referral_code'` to the PendingRegistration::createOrUpdate array:

```php
$pending = PendingRegistration::createOrUpdate([
    'email' => $request->email,
    'first_name' => $request->first_name,
    'middle_name' => $request->middle_name,
    'surname' => $request->surname,
    'password' => Hash::make($request->password),
    'registration_source' => $request->registration_source ?? null,
    'preview_persona_id' => $request->preview_persona_id ?? null,
    'plan' => $request->plan ?? null,
    'billing_cycle' => $request->billing_cycle ?? null,
    'referral_code' => $request->referral_code ?? null,
]);
```

- [ ] **Step 3: Modify AuthController verification to store referral on user creation**

In the `verifyCode()` method around line 466 where the User is created, add `referred_by_code`:

```php
$user = User::create([
    'first_name' => $pending->first_name,
    'middle_name' => $pending->middle_name,
    'surname' => $pending->surname,
    'email' => $pending->email,
    'password' => $pending->password,
    'role_id' => $role?->id,
    'referred_by_code' => $pending->referral_code,
]);
```

Then after the trial is started (around line 489), add the referral linking:

```php
// Link referral if user registered with a referral code
if ($pending->referral_code) {
    try {
        app(\App\Services\Payment\ReferralService::class)
            ->applyReferralOnRegistration($user, $pending->referral_code);
    } catch (\Exception $e) {
        Log::error('Failed to link referral on registration', [
            'user_id' => $user->id,
            'referral_code' => $pending->referral_code,
            'error' => $e->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Verify syntax**

```bash
php -l app/Http/Controllers/Api/AuthController.php
```

Expected: No syntax errors.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php resources/js/views/Register.vue
git commit -m "feat: capture referral code during registration"
```

---

## Task 7: Hook into Payment Confirmation

**Files:**
- Modify: `app/Http/Controllers/Api/PaymentController.php`

- [ ] **Step 1: Add ReferralService to constructor**

In `PaymentController.php`, add to the constructor:

```php
public function __construct(
    private readonly RevolutService $revolutService,
    private readonly RevolutSubscriptionService $subscriptionService,
    private readonly DiscountCodeService $discountCodeService,
    private readonly InvoiceService $invoiceService,
    private readonly ReferralService $referralService,
) {}
```

Add the import:

```php
use App\Services\Payment\ReferralService;
```

- [ ] **Step 2: Apply referral bonus after payment confirmation**

In `confirmPayment()`, after the payment confirmation email block (around line 395), add:

```php
// Apply referral bonus if user was referred
if ($user->referred_by_code) {
    try {
        $this->referralService->applyReferralBonus($user, $payment->billing_cycle);
    } catch (\Exception $e) {
        Log::error('Failed to apply referral bonus', [
            'user_id' => $user->id,
            'referred_by_code' => $user->referred_by_code,
            'error' => $e->getMessage(),
        ]);
    }
}
```

- [ ] **Step 3: Verify syntax**

```bash
php -l app/Http/Controllers/Api/PaymentController.php
```

Expected: No syntax errors.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/PaymentController.php
git commit -m "feat: apply referral bonus after payment confirmation"
```

---

## Task 8: Navbar — Refer a Friend Button

**Files:**
- Modify: `resources/js/components/Navbar.vue`

- [ ] **Step 1: Add Refer a Friend button**

In `Navbar.vue`, after the "Upgrade Now" button block (around line 91), add:

```html
          <!-- Refer a Friend (active paid subscribers only) -->
          <button
            v-else-if="isPaidSubscriber"
            @click="showReferralModal = true"
            class="inline-flex items-center text-sm font-semibold text-horizon-500 hover:text-horizon-600 hover:bg-white/40 px-3 py-1.5 rounded-md transition-all"
          >
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            Refer a Friend
          </button>
```

- [ ] **Step 2: Add computed property**

In the script `setup()` section, add after `showUpgradeButton`:

```javascript
const isPaidSubscriber = computed(() => {
  if (!trialData.value) return false;
  if (isPreviewMode.value) return false;
  return trialData.value.status === 'active';
});
```

Add `showReferralModal` ref:

```javascript
const showReferralModal = ref(false);
```

Return both from setup:

```javascript
isPaidSubscriber,
showReferralModal,
```

- [ ] **Step 3: Add ReferralModal component**

At the top of the template (after LogoutSuccessModal), add:

```html
<ReferralModal
  :show="showReferralModal"
  @close="showReferralModal = false"
/>
```

Import and register it in the script:

```javascript
import ReferralModal from '@/components/Payment/ReferralModal.vue';
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Navbar.vue
git commit -m "feat: add Refer a Friend button to Navbar"
```

---

## Task 9: ReferralModal Component

**Files:**
- Create: `resources/js/components/Payment/ReferralModal.vue`

- [ ] **Step 1: Create the modal component**

```vue
<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
  >
    <div class="flex items-center justify-center min-h-screen px-4">
      <div class="fixed inset-0 bg-savannah-1000/75 transition-opacity" @click="$emit('close')"></div>

      <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-h4 font-semibold text-horizon-500">Refer a Friend</h2>
          <button @click="$emit('close')" class="text-neutral-500 hover:text-horizon-500 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <p class="text-body-sm text-neutral-500 mb-4">
          Invite a friend to Fynla. When they subscribe, you'll both get bonus time — an extra week with a monthly plan, or an extra month with an annual plan.
        </p>

        <!-- Referral Code -->
        <div v-if="code" class="bg-savannah-100 rounded-lg p-4 mb-4 text-center">
          <p class="text-caption text-neutral-500 mb-1">Your referral code</p>
          <p class="text-h4 font-bold text-horizon-500 font-mono tracking-wider">{{ code }}</p>
        </div>

        <!-- Email Input -->
        <div class="mb-4">
          <label class="text-body-sm font-medium text-horizon-500 mb-1 block">Friend's email address</label>
          <div class="flex gap-2">
            <input
              v-model="email"
              type="email"
              placeholder="friend@example.com"
              class="flex-1 px-3 py-2 border border-light-gray rounded-lg text-body-sm focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500"
              @keyup.enter="sendInvitation"
              :disabled="sending"
            />
            <button
              @click="sendInvitation"
              :disabled="!email.trim() || sending"
              class="px-4 py-2 bg-raspberry-500 text-white text-body-sm font-medium rounded-lg hover:bg-raspberry-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {{ sending ? 'Sending...' : 'Send' }}
            </button>
          </div>
          <p v-if="error" class="text-caption text-raspberry-600 mt-1">{{ error }}</p>
          <p v-if="success" class="text-caption text-spring-600 mt-1">{{ success }}</p>
        </div>

        <!-- Referral History -->
        <div v-if="referrals.length > 0" class="border-t border-light-gray pt-4">
          <p class="text-body-sm font-medium text-horizon-500 mb-2">Your referrals</p>
          <div class="space-y-2 max-h-48 overflow-y-auto scrollbar-thin">
            <div
              v-for="r in referrals"
              :key="r.id"
              class="flex items-center justify-between text-body-sm"
            >
              <span class="text-neutral-500 truncate mr-2">{{ r.email }}</span>
              <span
                class="text-caption font-medium px-2 py-0.5 rounded-full"
                :class="statusClass(r.status)"
              >
                {{ statusLabel(r.status) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';

export default {
  name: 'ReferralModal',

  props: {
    show: { type: Boolean, default: false },
  },

  emits: ['close'],

  data() {
    return {
      code: null,
      email: '',
      sending: false,
      error: null,
      success: null,
      referrals: [],
    };
  },

  watch: {
    show(val) {
      if (val) {
        this.fetchCode();
        this.fetchReferrals();
        this.error = null;
        this.success = null;
        this.email = '';
      }
    },
  },

  methods: {
    async fetchCode() {
      try {
        const response = await api.get('/referral/code');
        if (response.data.success) {
          this.code = response.data.data.code;
        }
      } catch {
        // Non-critical
      }
    },

    async fetchReferrals() {
      try {
        const response = await api.get('/referral/list');
        if (response.data.success) {
          this.referrals = response.data.data.referrals;
        }
      } catch {
        // Non-critical
      }
    },

    async sendInvitation() {
      const emailVal = this.email.trim();
      if (!emailVal) return;

      this.sending = true;
      this.error = null;
      this.success = null;

      try {
        const response = await api.post('/referral/invite', { email: emailVal });
        if (response.data.success) {
          this.success = 'Invitation sent successfully.';
          this.email = '';
          this.fetchReferrals();
        } else {
          this.error = response.data.message;
        }
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to send invitation.';
      } finally {
        this.sending = false;
      }
    },

    statusClass(status) {
      return {
        pending: 'bg-savannah-100 text-neutral-500',
        registered: 'bg-violet-100 text-violet-700',
        converted: 'bg-spring-100 text-spring-700',
        expired: 'bg-neutral-100 text-neutral-500',
      }[status] || 'bg-neutral-100 text-neutral-500';
    },

    statusLabel(status) {
      return {
        pending: 'Pending',
        registered: 'Registered',
        converted: 'Subscribed',
        expired: 'Expired',
      }[status] || status;
    },
  },
};
</script>
```

- [ ] **Step 2: Verify compilation**

```bash
# dev.sh should be running — check terminal for Vue compilation errors
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Payment/ReferralModal.vue
git commit -m "feat: add ReferralModal component"
```

---

## Task 10: Run Full Test Suite + Seed

- [ ] **Step 1: Run all tests**

```bash
./vendor/bin/pest
```

Expected: ALL PASS, no regressions.

- [ ] **Step 2: Run referral-specific tests**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/ReferralServiceTest.php -v
```

Expected: All referral tests pass.

- [ ] **Step 3: Reseed**

```bash
php artisan db:seed
```

- [ ] **Step 4: Verify routes**

```bash
php artisan route:list --path=referral
```

Expected: 3 referral routes listed.

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "feat: Refer a Friend — complete implementation"
```
