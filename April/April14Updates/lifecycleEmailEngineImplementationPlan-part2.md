# Lifecycle Email Engine Implementation Plan — Part 2

> **Continued from** `lifecycleEmailEngineImplementationPlan.md` (Phases 1-6).
> **Pre-requisite:** All Phase 1-6 tasks completed and committed.

This part covers Phases 7-14: campaign classes, mail templates, magic link routes, command/kernel wiring, the web settings UI, e2e test infrastructure, manual verification, and deploy.

---

## Phase 7 — Five campaign classes

Each campaign is a small class implementing `LifecycleCampaign`. They share patterns; we build one fully (Empty Trialer) then the others follow the same shape.

### Task 7.1 — `EmptyTrialerCampaign`

**Files:**
- Create: `app/Services/Lifecycle/Campaigns/EmptyTrialerCampaign.php`
- Create: `tests/Feature/Lifecycle/Campaigns/EmptyTrialerCampaignTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Lifecycle\Campaigns\EmptyTrialerCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes a user with no data, expired trial, registered 9+ days ago', function () {
    $user = User::factory()->create([
        'created_at' => now()->subDays(9),
        'is_preview_user' => false,
    ]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_started_at' => now()->subDays(9),
        'trial_ends_at' => now()->subDays(2),
    ]);

    $campaign = app(EmptyTrialerCampaign::class);
    $eligible = $campaign->eligibleUsers();

    expect($eligible->pluck('id'))->toContain($user->id);
});

it('excludes a user with module data', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);
    \App\Models\Property::factory()->create(['user_id' => $user->id]);

    $campaign = app(EmptyTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('excludes a user with an active subscription', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'current_period_end' => now()->addDays(30),
    ]);

    $campaign = app(EmptyTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('excludes a user registered <9 days ago', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(5)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);

    $campaign = app(EmptyTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('has correct name and priority', function () {
    $campaign = app(EmptyTrialerCampaign::class);
    expect($campaign->name())->toBe('empty_trialer');
    expect($campaign->priority())->toBe(4);
});
```

- [ ] **Step 2: Run — expect failure**

```bash
./vendor/bin/pest tests/Feature/Lifecycle/Campaigns/EmptyTrialerCampaignTest.php
```

- [ ] **Step 3: Create the campaign class**

```php
<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Campaigns;

use App\Mail\Lifecycle\EmptyTrialerMail;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use App\Services\Lifecycle\LifecycleEngine;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

class EmptyTrialerCampaign implements LifecycleCampaign
{
    public function __construct(
        private readonly LifecycleEngine $engine,
    ) {}

    public function name(): string
    {
        return 'empty_trialer';
    }

    public function priority(): int
    {
        return 4;
    }

    public function eligibleUsers(): Collection
    {
        return $this->engine->trialAfterEndCandidates()
            ->reject(fn (User $u) => $this->engine->candidateHasData($u->id))
            ->values();
    }

    public function mailable(User $user): Mailable
    {
        return new EmptyTrialerMail($user);
    }
}
```

- [ ] **Step 4: Run — note that `EmptyTrialerMail` doesn't exist yet**

The test will fail at the `mailable()` call but pass for `eligibleUsers()`, `name()`, and `priority()`. We're testing eligibility logic here; the mail class is built in Phase 8.

To make the test pass without the mail class, **temporarily** comment out any test that calls `mailable()`. Or create a stub mail class:

```bash
mkdir -p app/Mail/Lifecycle
cat > app/Mail/Lifecycle/EmptyTrialerMail.php <<'PHP'
<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmptyTrialerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Stub — replaced in Phase 8');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>stub</p>');
    }
}
PHP
```

This stub gets replaced fully in Task 8.2. For now it just makes the eligibility tests runnable.

- [ ] **Step 5: Run — expect pass**

```bash
./vendor/bin/pest tests/Feature/Lifecycle/Campaigns/EmptyTrialerCampaignTest.php
```

Expected: 5 passes.

- [ ] **Step 6: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Lifecycle/Campaigns/EmptyTrialerCampaign.php app/Mail/Lifecycle/EmptyTrialerMail.php tests/Feature/Lifecycle/Campaigns/EmptyTrialerCampaignTest.php
git commit -m "feat: add EmptyTrialerCampaign for lifecycle Campaign 1"
```

---

### Task 7.2 — `EngagedTrialerCampaign`

**Files:**
- Create: `app/Services/Lifecycle/Campaigns/EngagedTrialerCampaign.php`
- Create: `app/Mail/Lifecycle/EngagedTrialerMail.php` (stub)
- Create: `tests/Feature/Lifecycle/Campaigns/EngagedTrialerCampaignTest.php`

- [ ] **Step 1: Write failing test (mirror EmptyTrialer with inverted data check)**

```php
<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Lifecycle\Campaigns\EngagedTrialerCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes a user with module data, expired trial, registered 9+ days ago', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);
    \App\Models\Property::factory()->create(['user_id' => $user->id]);

    $campaign = app(EngagedTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->toContain($user->id);
});

it('excludes a user with NO module data (would be Campaign 1)', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);

    $campaign = app(EngagedTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('has correct name and priority', function () {
    $campaign = app(EngagedTrialerCampaign::class);
    expect($campaign->name())->toBe('engaged_trialer');
    expect($campaign->priority())->toBe(5);
});
```

- [ ] **Step 2: Run — expect failure**

- [ ] **Step 3: Create stub mail class**

```bash
cat > app/Mail/Lifecycle/EngagedTrialerMail.php <<'PHP'
<?php
declare(strict_types=1);
namespace App\Mail\Lifecycle;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
class EngagedTrialerMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public User $user, public array $context = [], public ?string $magicUrl = null, public ?string $discountCode = null) {}
    public function envelope(): Envelope { return new Envelope(subject: 'Stub'); }
    public function content(): Content { return new Content(htmlString: '<p>stub</p>'); }
}
PHP
```

- [ ] **Step 4: Create the campaign class**

```php
<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Campaigns;

use App\Mail\Lifecycle\EngagedTrialerMail;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use App\Services\Lifecycle\LifecycleDiscountCodeGenerator;
use App\Services\Lifecycle\LifecycleEngine;
use App\Services\Lifecycle\LifecycleSnapshotService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class EngagedTrialerCampaign implements LifecycleCampaign
{
    public function __construct(
        private readonly LifecycleEngine $engine,
        private readonly LifecycleSnapshotService $snapshotService,
        private readonly LifecycleDiscountCodeGenerator $discountGenerator,
    ) {}

    public function name(): string
    {
        return 'engaged_trialer';
    }

    public function priority(): int
    {
        return 5;
    }

    public function eligibleUsers(): Collection
    {
        return $this->engine->trialAfterEndCandidates()
            ->filter(fn (User $u) => $this->engine->candidateHasData($u->id))
            ->values();
    }

    public function mailable(User $user): Mailable
    {
        $context = $this->snapshotService->buildContext($user);
        $code = $this->discountGenerator->generate($user);

        $magicUrl = URL::temporarySignedRoute(
            'lifecycle.apply-discount',
            now()->addDays((int) config('lifecycle.magic_link_ttl_days', 7)),
            [
                'user_id' => $user->id,
                'campaign' => 'engaged_trialer',
                'code' => $code->code,
            ]
        );

        return new EngagedTrialerMail($user, $context, $magicUrl, $code->code);
    }
}
```

- [ ] **Step 5: Run — expect pass for eligibility tests; mailable() will fail because the route isn't defined yet**

For now, only the first 3 tests should pass. The `mailable()` call would fail because `lifecycle.apply-discount` isn't a defined route yet. Skip testing `mailable()` until Phase 9 creates the route.

```bash
./vendor/bin/pest tests/Feature/Lifecycle/Campaigns/EngagedTrialerCampaignTest.php
```

- [ ] **Step 6: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Lifecycle/Campaigns/EngagedTrialerCampaign.php app/Mail/Lifecycle/EngagedTrialerMail.php tests/Feature/Lifecycle/Campaigns/EngagedTrialerCampaignTest.php
git commit -m "feat: add EngagedTrialerCampaign for lifecycle Campaign 2"
```

---

### Task 7.3 — `CancelledTrialerCampaign`

**Files:**
- Create: `app/Services/Lifecycle/Campaigns/CancelledTrialerCampaign.php`
- Create: `app/Mail/Lifecycle/CancelledTrialerMail.php` (stub)
- Create: `tests/Feature/Lifecycle/Campaigns/CancelledTrialerCampaignTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Lifecycle\Campaigns\CancelledTrialerCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes a user who cancelled mid-trial 3 days ago', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'trial_started_at' => now()->subDays(5),
        'trial_ends_at' => now()->addDays(2),  // future — they cancelled BEFORE end
        'cancelled_at' => now()->subDays(3)->setTime(12, 0),
    ]);

    $campaign = app(CancelledTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->toContain($user->id);
});

it('excludes a user who cancelled 2 days ago (not yet 3 days)', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'trial_started_at' => now()->subDays(4),
        'trial_ends_at' => now()->addDays(3),
        'cancelled_at' => now()->subDays(2)->setTime(12, 0),
    ]);

    $campaign = app(CancelledTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('excludes a user who cancelled AFTER trial ended (would be Campaign 4)', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'trial_started_at' => now()->subDays(20),
        'trial_ends_at' => now()->subDays(13),
        'cancelled_at' => now()->subDays(3)->setTime(12, 0),
    ]);

    $campaign = app(CancelledTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('has correct name and priority', function () {
    $campaign = app(CancelledTrialerCampaign::class);
    expect($campaign->name())->toBe('cancelled_trialer');
    expect($campaign->priority())->toBe(1);
});
```

- [ ] **Step 2: Create the stub mail and the campaign class**

Create stub:

```bash
cat > app/Mail/Lifecycle/CancelledTrialerMail.php <<'PHP'
<?php
declare(strict_types=1);
namespace App\Mail\Lifecycle;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
class CancelledTrialerMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public User $user, public array $feedbackUrls = []) {}
    public function envelope(): Envelope { return new Envelope(subject: 'Stub'); }
    public function content(): Content { return new Content(htmlString: '<p>stub</p>'); }
}
PHP
```

Create campaign class:

```php
<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Campaigns;

use App\Mail\Lifecycle\CancelledTrialerMail;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class CancelledTrialerCampaign implements LifecycleCampaign
{
    public function name(): string
    {
        return 'cancelled_trialer';
    }

    public function priority(): int
    {
        return 1;
    }

    public function eligibleUsers(): Collection
    {
        $delay = (int) config('lifecycle.cancellation_feedback_delay_days', 3);

        return User::query()
            ->whereHas('subscriptions', fn ($q) => $q
                ->where('status', 'cancelled')
                ->whereNotNull('cancelled_at')
                ->whereNotNull('trial_started_at')
                ->whereColumn('cancelled_at', '<', 'trial_ends_at')
                ->whereDate('cancelled_at', now()->subDays($delay)->toDateString())
            )
            ->whereDoesntHave('subscriptions', fn ($q) => $q->whereIn('status', ['active', 'trialing']))
            ->get();
    }

    public function mailable(User $user): Mailable
    {
        $feedbackUrls = $this->buildFeedbackUrls($user);
        return new CancelledTrialerMail($user, $feedbackUrls);
    }

    private function buildFeedbackUrls(User $user): array
    {
        $reasons = config('lifecycle.feedback_reasons.cancelled_trialer', []);
        $urls = [];
        $expiry = now()->addDays((int) config('lifecycle.magic_link_ttl_days', 7));

        foreach ($reasons as $reason) {
            $urls[$reason] = URL::temporarySignedRoute(
                'lifecycle.feedback',
                $expiry,
                [
                    'user_id' => $user->id,
                    'campaign' => 'cancelled_trialer',
                    'reason' => $reason,
                ]
            );
        }

        return $urls;
    }
}
```

- [ ] **Step 3: Run — expect pass for the eligibility/name/priority tests**

```bash
./vendor/bin/pest tests/Feature/Lifecycle/Campaigns/CancelledTrialerCampaignTest.php
```

- [ ] **Step 4: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Lifecycle/Campaigns/CancelledTrialerCampaign.php app/Mail/Lifecycle/CancelledTrialerMail.php tests/Feature/Lifecycle/Campaigns/CancelledTrialerCampaignTest.php
git commit -m "feat: add CancelledTrialerCampaign for lifecycle Campaign 3"
```

---

### Task 7.4 — `ChurnedSubscriberCampaign`

**Files:**
- Create: `app/Services/Lifecycle/Campaigns/ChurnedSubscriberCampaign.php`
- Create: `app/Mail/Lifecycle/ChurnedSubscriberMail.php` (stub)
- Create: `tests/Feature/Lifecycle/Campaigns/ChurnedSubscriberCampaignTest.php`

- [ ] **Step 1: Write failing test (mirror CancelledTrialer with inverted column comparison)**

```php
<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Lifecycle\Campaigns\ChurnedSubscriberCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes a user who cancelled paid sub 3 days ago (cancelled_at >= trial_ends_at)', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'trial_started_at' => now()->subDays(60),
        'trial_ends_at' => now()->subDays(53),
        'cancelled_at' => now()->subDays(3)->setTime(12, 0),
    ]);

    $campaign = app(ChurnedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->toContain($user->id);
});

it('excludes a user who cancelled mid-trial (would be Campaign 3)', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'trial_started_at' => now()->subDays(5),
        'trial_ends_at' => now()->addDays(2),
        'cancelled_at' => now()->subDays(3)->setTime(12, 0),
    ]);

    $campaign = app(ChurnedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('has correct name and priority', function () {
    $campaign = app(ChurnedSubscriberCampaign::class);
    expect($campaign->name())->toBe('churned_subscriber');
    expect($campaign->priority())->toBe(2);
});
```

- [ ] **Step 2: Create stub mail and campaign**

Stub:

```bash
cat > app/Mail/Lifecycle/ChurnedSubscriberMail.php <<'PHP'
<?php
declare(strict_types=1);
namespace App\Mail\Lifecycle;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
class ChurnedSubscriberMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public User $user, public array $feedbackUrls = [], public ?string $subscriptionDuration = null) {}
    public function envelope(): Envelope { return new Envelope(subject: 'Stub'); }
    public function content(): Content { return new Content(htmlString: '<p>stub</p>'); }
}
PHP
```

Campaign class — almost identical to CancelledTrialer except `whereColumn('cancelled_at', '>=', 'trial_ends_at')`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Campaigns;

use App\Mail\Lifecycle\ChurnedSubscriberMail;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class ChurnedSubscriberCampaign implements LifecycleCampaign
{
    public function name(): string
    {
        return 'churned_subscriber';
    }

    public function priority(): int
    {
        return 2;
    }

    public function eligibleUsers(): Collection
    {
        $delay = (int) config('lifecycle.cancellation_feedback_delay_days', 3);

        return User::query()
            ->whereHas('subscriptions', fn ($q) => $q
                ->where('status', 'cancelled')
                ->whereNotNull('cancelled_at')
                ->whereColumn('cancelled_at', '>=', 'trial_ends_at')
                ->whereDate('cancelled_at', now()->subDays($delay)->toDateString())
            )
            ->whereDoesntHave('subscriptions', fn ($q) => $q->whereIn('status', ['active', 'trialing']))
            ->get();
    }

    public function mailable(User $user): Mailable
    {
        $reasons = config('lifecycle.feedback_reasons.churned_subscriber', []);
        $urls = [];
        $expiry = now()->addDays((int) config('lifecycle.magic_link_ttl_days', 7));

        foreach ($reasons as $reason) {
            $urls[$reason] = URL::temporarySignedRoute(
                'lifecycle.feedback',
                $expiry,
                [
                    'user_id' => $user->id,
                    'campaign' => 'churned_subscriber',
                    'reason' => $reason,
                ]
            );
        }

        // Compute subscription duration
        $sub = $user->subscriptions()->where('status', 'cancelled')->latest('cancelled_at')->first();
        $duration = null;
        if ($sub && $sub->current_period_start && $sub->cancelled_at) {
            $duration = $sub->current_period_start->diffForHumans($sub->cancelled_at, ['parts' => 1]);
        }

        return new ChurnedSubscriberMail($user, $urls, $duration);
    }
}
```

- [ ] **Step 3: Run — expect pass**

```bash
./vendor/bin/pest tests/Feature/Lifecycle/Campaigns/ChurnedSubscriberCampaignTest.php
```

- [ ] **Step 4: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Lifecycle/Campaigns/ChurnedSubscriberCampaign.php app/Mail/Lifecycle/ChurnedSubscriberMail.php tests/Feature/Lifecycle/Campaigns/ChurnedSubscriberCampaignTest.php
git commit -m "feat: add ChurnedSubscriberCampaign for lifecycle Campaign 4"
```

---

### Task 7.5 — `LapsedSubscriberCampaign`

**Files:**
- Create: `app/Services/Lifecycle/Campaigns/LapsedSubscriberCampaign.php`
- Create: `app/Mail/Lifecycle/LapsedSubscriberMail.php` (stub)
- Create: `tests/Feature/Lifecycle/Campaigns/LapsedSubscriberCampaignTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Lifecycle\Campaigns\LapsedSubscriberCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes a user with status=past_due for at least 5 days', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'past_due',
        'current_period_end' => now()->subDays(6),
    ]);

    $campaign = app(LapsedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->toContain($user->id);
});

it('excludes a user past_due for only 4 days', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'past_due',
        'current_period_end' => now()->subDays(4),
    ]);

    $campaign = app(LapsedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('excludes a user with status=active', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'current_period_end' => now()->addDays(20),
    ]);

    $campaign = app(LapsedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('has correct name and priority', function () {
    $campaign = app(LapsedSubscriberCampaign::class);
    expect($campaign->name())->toBe('lapsed_subscriber');
    expect($campaign->priority())->toBe(3);
});
```

- [ ] **Step 2: Create stub mail and campaign**

Stub:

```bash
cat > app/Mail/Lifecycle/LapsedSubscriberMail.php <<'PHP'
<?php
declare(strict_types=1);
namespace App\Mail\Lifecycle;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
class LapsedSubscriberMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(
        public User $user,
        public ?string $updatePaymentUrl = null,
        public array $feedbackUrls = [],
        public ?string $gracePeriodEnd = null
    ) {}
    public function envelope(): Envelope { return new Envelope(subject: 'Stub'); }
    public function content(): Content { return new Content(htmlString: '<p>stub</p>'); }
}
PHP
```

Campaign class:

```php
<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Campaigns;

use App\Mail\Lifecycle\LapsedSubscriberMail;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class LapsedSubscriberCampaign implements LifecycleCampaign
{
    public function name(): string
    {
        return 'lapsed_subscriber';
    }

    public function priority(): int
    {
        return 3;
    }

    public function eligibleUsers(): Collection
    {
        $threshold = (int) config('lifecycle.lapsed_recovery_threshold_days', 5);

        return User::query()
            ->whereHas('subscriptions', fn ($q) => $q
                ->where('status', 'past_due')
                ->where('current_period_end', '<', now()->subDays($threshold))
            )
            ->get();
    }

    public function mailable(User $user): Mailable
    {
        $expiry = now()->addDays((int) config('lifecycle.magic_link_ttl_days', 7));

        $updatePaymentUrl = URL::temporarySignedRoute(
            'lifecycle.update-payment',
            $expiry,
            ['user_id' => $user->id]
        );

        $feedbackUrls = [];
        foreach (config('lifecycle.feedback_reasons.lapsed_subscriber', []) as $reason) {
            $feedbackUrls[$reason] = URL::temporarySignedRoute(
                'lifecycle.feedback',
                $expiry,
                [
                    'user_id' => $user->id,
                    'campaign' => 'lapsed_subscriber',
                    'reason' => $reason,
                ]
            );
        }

        // Compute grace period end (current_period_end + 7 days from Revolut retry window)
        $sub = $user->subscriptions()->where('status', 'past_due')->latest('current_period_end')->first();
        $gracePeriodEnd = $sub && $sub->current_period_end
            ? $sub->current_period_end->copy()->addDays(7)->format('j F Y')
            : null;

        return new LapsedSubscriberMail($user, $updatePaymentUrl, $feedbackUrls, $gracePeriodEnd);
    }
}
```

- [ ] **Step 3: Run — expect pass**

```bash
./vendor/bin/pest tests/Feature/Lifecycle/Campaigns/LapsedSubscriberCampaignTest.php
```

- [ ] **Step 4: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Lifecycle/Campaigns/LapsedSubscriberCampaign.php app/Mail/Lifecycle/LapsedSubscriberMail.php tests/Feature/Lifecycle/Campaigns/LapsedSubscriberCampaignTest.php
git commit -m "feat: add LapsedSubscriberCampaign for lifecycle Campaign 5"
```

---

### Task 7.6 — Add notification preference filter to engine

The campaigns are now created but the engine doesn't yet honour `notification_preferences`. Add the filter.

**Files:**
- Modify: `app/Services/Lifecycle/LifecycleEngine.php`

- [ ] **Step 1: Write a failing test for the preference filter**

Create or append to `tests/Feature/Lifecycle/Campaigns/EngagedTrialerCampaignTest.php`:

```php
it('engine excludes users who have opted out via notification_preferences', function () {
    \Illuminate\Support\Facades\Mail::fake();

    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);
    \App\Models\Property::factory()->create(['user_id' => $user->id]);

    \App\Models\NotificationPreference::create([
        'user_id' => $user->id,
        'lifecycle_engaged_trialer' => false,
        'lifecycle_empty_trialer' => true,
        'lifecycle_cancelled_trialer' => true,
        'lifecycle_churned_subscriber' => true,
        'lifecycle_lapsed_subscriber' => true,
    ]);

    config(['lifecycle.campaigns' => [\App\Services\Lifecycle\Campaigns\EngagedTrialerCampaign::class]]);

    $engine = app(\App\Services\Lifecycle\LifecycleEngine::class);
    $stats = $engine->run();

    expect($stats['engaged_trialer']['sent'])->toBe(0);
    \Illuminate\Support\Facades\Mail::assertNothingSent();
});

it('engine includes users with no notification_preferences row at all', function () {
    \Illuminate\Support\Facades\Mail::fake();

    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);
    \App\Models\Property::factory()->create(['user_id' => $user->id]);

    // Explicitly DO NOT create a notification_preferences row
    expect(\App\Models\NotificationPreference::where('user_id', $user->id)->exists())->toBeFalse();

    config(['lifecycle.campaigns' => [\App\Services\Lifecycle\Campaigns\EngagedTrialerCampaign::class]]);

    $engine = app(\App\Services\Lifecycle\LifecycleEngine::class);
    $stats = $engine->run();

    expect($stats['engaged_trialer']['sent'])->toBe(1);
});
```

- [ ] **Step 2: Run — expect failure of the first test**

The first test will fail because the engine doesn't check preferences yet. The second test should pass already (no preference = opted in is the natural behaviour because there's no row to filter on).

- [ ] **Step 3: Update `LifecycleEngine::filterEligible` to add the preference filter**

Replace `filterEligible()` in `app/Services/Lifecycle/LifecycleEngine.php`:

```php
private function filterEligible(LifecycleCampaign $campaign, Collection $emailedToday): Collection
{
    $preferenceColumn = config("lifecycle.campaign_to_preference.{$campaign->name()}");

    return $campaign->eligibleUsers()
        ->reject(fn (User $u) => $u->is_preview_user)
        ->reject(fn (User $u) => $u->is_lifecycle_test_user && ! $this->testMode)
        ->reject(fn (User $u) => $emailedToday->contains($u->id))
        ->reject(fn (User $u) => LifecycleEmailLog::where('user_id', $u->id)
            ->where('campaign', $campaign->name())
            ->exists())
        ->reject(function (User $u) use ($preferenceColumn) {
            if (! $preferenceColumn) {
                return false;  // No mapping config → don't filter
            }
            $pref = $u->notificationPreference;
            if (! $pref) {
                return false;  // No row → opted in
            }
            return $pref->{$preferenceColumn} === false;
        });
}
```

- [ ] **Step 4: Run — expect both tests to pass**

```bash
./vendor/bin/pest tests/Feature/Lifecycle/Campaigns/EngagedTrialerCampaignTest.php
```

- [ ] **Step 5: Reseed and commit**

```bash
php artisan db:seed
git add app/Services/Lifecycle/LifecycleEngine.php tests/Feature/Lifecycle/Campaigns/EngagedTrialerCampaignTest.php
git commit -m "feat: lifecycle engine honours notification_preferences per campaign"
```

---

## Phase 8 — Mail classes and Blade templates

Each campaign needs a real (non-stub) mail class plus a Blade template. Plus shared partials. Plus the trial reminder palette fix.

### Task 8.1 — Create shared Blade partials

**Files:**
- Create: `resources/views/emails/lifecycle/_layout.blade.php`
- Create: `resources/views/emails/lifecycle/_button.blade.php`
- Create: `resources/views/emails/lifecycle/_quick-picks.blade.php`

- [ ] **Step 1: Create the layout partial**

`resources/views/emails/lifecycle/_layout.blade.php`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Fynla')</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1F2A44; background-color: #F7F6F4; margin: 0; padding: 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #F7F6F4;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; background-color: #FFFFFF; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
                    <tr>
                        <td style="padding: 30px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #F7F6F4; padding: 20px 30px; text-align: center; font-size: 13px; color: #717171; border-top: 1px solid #EEEEEE;">
                            <p style="margin: 5px 0;">&copy; {{ date('Y') }} Fynla. All rights reserved.</p>
                            <p style="margin: 5px 0;">You're receiving this because you signed up for Fynla.</p>
                            <p style="margin: 5px 0;">You can manage which emails you receive in your <a href="{{ config('app.url') }}/profile/notifications" style="color: #E83E6D; text-decoration: none;">account settings</a>.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

- [ ] **Step 2: Create the button partial**

`resources/views/emails/lifecycle/_button.blade.php`:

```html
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 25px auto;">
    <tr>
        <td style="background-color: #E83E6D; border-radius: 8px;">
            <a href="{{ $url }}" style="display: inline-block; padding: 14px 32px; color: #FFFFFF; text-decoration: none; font-weight: 600; font-size: 16px;">{{ $label }}</a>
        </td>
    </tr>
</table>
```

- [ ] **Step 3: Create the quick-picks partial**

`resources/views/emails/lifecycle/_quick-picks.blade.php`:

```html
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 20px auto;">
    @foreach ($buttons as $reason => $url)
        <tr>
            <td style="padding: 4px 0;">
                <a href="{{ $url }}" style="display: inline-block; padding: 10px 20px; background-color: #FFFFFF; color: #1F2A44; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid #1F2A44; border-radius: 6px; min-width: 200px; text-align: center;">{{ $labels[$reason] ?? $reason }}</a>
            </td>
        </tr>
    @endforeach
</table>
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/emails/lifecycle/_layout.blade.php resources/views/emails/lifecycle/_button.blade.php resources/views/emails/lifecycle/_quick-picks.blade.php
git commit -m "feat: add shared Blade partials for lifecycle email templates"
```

---

### Task 8.2 — Empty Trialer mail + template

**Files:**
- Modify: `app/Mail/Lifecycle/EmptyTrialerMail.php` (replace stub)
- Create: `resources/views/emails/lifecycle/empty-trialer.blade.php`

- [ ] **Step 1: Replace the EmptyTrialerMail stub**

`app/Mail/Lifecycle/EmptyTrialerMail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmptyTrialerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $magicUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "It's been a while — come back and try Fynla again",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.empty-trialer',
            with: [
                'user' => $this->user,
                'magicUrl' => $this->magicUrl,
                'firstName' => $this->user->first_name ?? 'there',
            ],
        );
    }
}
```

- [ ] **Step 2: Create the template**

`resources/views/emails/lifecycle/empty-trialer.blade.php`:

```html
@extends('emails.lifecycle._layout')
@section('title', 'Come back to Fynla')
@section('content')
    <p>Hi {{ $firstName }},</p>

    <p>We noticed you signed up for Fynla a couple of weeks ago but didn't get a chance to look around. No worries — life gets busy.</p>

    <p>Your account is still here. We'd love for you to come back and see what Fynla can do for your financial planning.</p>

    <p>To make it easy, we're giving you a fresh 14-day trial — no payment details required, full access to everything Fynla offers:</p>

    <ul>
        <li>Track properties, pensions, savings, investments</li>
        <li>Plan for retirement and inheritance tax</li>
        <li>Get personalised recommendations from Fyn, our AI assistant</li>
        <li>See your complete financial picture in one place</li>
    </ul>

    @include('emails.lifecycle._button', ['url' => $magicUrl, 'label' => 'START MY 14-DAY TRIAL'])

    <p style="text-align: center; color: #717171; font-size: 13px;">This invitation expires in 7 days.</p>

    <p>— The Fynla team</p>
@endsection
```

- [ ] **Step 3: Update the EmptyTrialerCampaign to pass the magicUrl**

In `app/Services/Lifecycle/Campaigns/EmptyTrialerCampaign.php`, replace `mailable()`:

```php
public function mailable(User $user): Mailable
{
    $magicUrl = URL::temporarySignedRoute(
        'lifecycle.restart-trial',
        now()->addDays((int) config('lifecycle.magic_link_ttl_days', 7)),
        ['user_id' => $user->id]
    );

    return new EmptyTrialerMail($user, $magicUrl);
}
```

Add the `use` statements at the top:

```php
use Illuminate\Support\Facades\URL;
```

- [ ] **Step 4: Commit**

```bash
git add app/Mail/Lifecycle/EmptyTrialerMail.php resources/views/emails/lifecycle/empty-trialer.blade.php app/Services/Lifecycle/Campaigns/EmptyTrialerCampaign.php
git commit -m "feat: implement EmptyTrialerMail with Blade template + magic link"
```

---

### Task 8.3 — Engaged Trialer mail + template (most complex)

**Files:**
- Modify: `app/Mail/Lifecycle/EngagedTrialerMail.php`
- Create: `resources/views/emails/lifecycle/engaged-trialer.blade.php`

- [ ] **Step 1: Replace the stub**

```php
<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EngagedTrialerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public array $context = [],
        public ?string $magicUrl = null,
        public ?string $discountCode = null,
    ) {}

    public function envelope(): Envelope
    {
        $firstName = $this->user->first_name;
        $subject = $firstName
            ? "Your Fynla picture so far, {$firstName} — and 25-45% off to finish it"
            : "Your Fynla picture so far — and 25-45% off to finish it";

        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.engaged-trialer',
            with: [
                'user' => $this->user,
                'firstName' => $this->user->first_name ?? 'there',
                'completionPct' => $this->context['completion_pct'] ?? 0,
                'modulesWithData' => $this->context['modules_with_data'] ?? [],
                'modulesRemaining' => $this->context['modules_remaining'] ?? [],
                'magicUrl' => $this->magicUrl,
                'discountCode' => $this->discountCode,
            ],
        );
    }
}
```

- [ ] **Step 2: Create the template (with HTML completion bar and discount table)**

`resources/views/emails/lifecycle/engaged-trialer.blade.php`:

```html
@extends('emails.lifecycle._layout')
@section('title', 'Your Fynla picture so far')
@section('content')
    <p>Hi {{ $firstName }},</p>

    <p>Your free Fynla trial has wrapped up — but the picture you started building is still there, and it's looking strong:</p>

    {{-- HTML table-based progress bar --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0;">
        <tr>
            <td>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #FDFAF7; border-radius: 12px; overflow: hidden; padding: 20px;">
                    <tr>
                        <td style="text-align: center; font-size: 14px; color: #717171; padding-bottom: 8px;">YOU'RE</td>
                    </tr>
                    <tr>
                        <td style="text-align: center; font-size: 36px; font-weight: 900; color: #1F2A44; padding-bottom: 4px;">{{ $completionPct }}%</td>
                    </tr>
                    <tr>
                        <td style="text-align: center; font-size: 14px; color: #717171; padding-bottom: 12px;">THERE</td>
                    </tr>
                    <tr>
                        <td>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #EEEEEE; border-radius: 6px; height: 8px;">
                                <tr>
                                    <td style="background-color: #20B486; width: {{ $completionPct }}%; border-radius: 6px;">&nbsp;</td>
                                    <td>&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p>You've started tracking:</p>
    <ul>
        @foreach ($modulesWithData as $module)
            <li>{{ $module['count'] }} {{ $module['label'] }}</li>
        @endforeach
    </ul>

    @if (count($modulesRemaining) > 0)
        <p>{{ count($modulesRemaining) }} more {{ count($modulesRemaining) === 1 ? 'area' : 'areas' }} to set up — {{ implode(', ', $modulesRemaining) }} — and your full Fynla plan is complete.</p>
    @endif

    <p>To help you finish, we're offering a one-time welcome discount on any Fynla plan. Pick what works for you:</p>

    {{-- Discount table --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0; border-collapse: collapse;">
        <tr style="background-color: #F7F6F4;">
            <th style="padding: 10px; border: 1px solid #EEEEEE; text-align: left; font-size: 14px;">Plan</th>
            <th style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;">Monthly</th>
            <th style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;">Yearly</th>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #EEEEEE; font-size: 14px;">Student</td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£2.99</strong></td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£21.99</strong></td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #EEEEEE; font-size: 14px;">Standard</td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£5.99</strong></td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£55.00</strong></td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #EEEEEE; font-size: 14px;">Family</td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£10.99</strong></td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£100.00</strong></td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #EEEEEE; font-size: 14px;">Pro</td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;">£19.99</td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;">£200.00</td>
        </tr>
    </table>

    @include('emails.lifecycle._button', ['url' => $magicUrl, 'label' => 'CLAIM YOUR DISCOUNT'])

    <p style="text-align: center; color: #717171; font-size: 13px;">If the button doesn't work, your discount code is:</p>
    <p style="text-align: center; font-family: monospace; font-size: 18px; font-weight: 700; color: #1F2A44; letter-spacing: 1px;">{{ $discountCode }}</p>
    <p style="text-align: center; color: #717171; font-size: 13px;">This offer expires in 7 days. Pro is at standard pricing.</p>

    <p>— The Fynla team</p>
@endsection
```

- [ ] **Step 3: Commit**

```bash
git add app/Mail/Lifecycle/EngagedTrialerMail.php resources/views/emails/lifecycle/engaged-trialer.blade.php
git commit -m "feat: implement EngagedTrialerMail with completion bar + discount table"
```

---

### Task 8.4 — Cancelled and Churned mail + templates

These two are nearly identical so we batch them.

**Files:**
- Modify: `app/Mail/Lifecycle/CancelledTrialerMail.php` and `ChurnedSubscriberMail.php`
- Create: `resources/views/emails/lifecycle/cancelled-trialer.blade.php` and `churned-subscriber.blade.php`

- [ ] **Step 1: Replace CancelledTrialerMail**

```php
<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CancelledTrialerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public array $feedbackUrls = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: 'Sorry to see you go — what could we have done better?',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.cancelled-trialer',
            with: [
                'firstName' => $this->user->first_name ?? 'there',
                'feedbackUrls' => $this->feedbackUrls,
            ],
        );
    }
}
```

- [ ] **Step 2: Create the cancelled-trialer template**

`resources/views/emails/lifecycle/cancelled-trialer.blade.php`:

```html
@extends('emails.lifecycle._layout')
@section('title', 'Sorry to see you go')
@section('content')
    <p>Hi {{ $firstName }},</p>

    <p>You cancelled your Fynla trial a few days ago — we're sorry it didn't work out. We're a small team trying to build the best UK financial planning tool we can, and the only way we get better is by hearing from people who decided it wasn't for them.</p>

    <p>If you have a moment, what was the main reason?</p>

    @include('emails.lifecycle._quick-picks', [
        'buttons' => $feedbackUrls,
        'labels' => [
            'too_expensive' => 'Too expensive',
            'missing_features' => 'Missing features',
            'found_alternative' => 'Found alternative',
            'not_what_expected' => 'Not what I expected',
            'bugs_or_ux' => 'Bugs or poor UX',
            'personal_change' => 'Personal change',
            'other' => 'Other',
        ],
    ])

    <p>Whichever you pick, you'll go to a one-question page where you can add anything else you'd like us to know. Or just close this email and we'll leave you alone — we won't ask again.</p>

    <p>— The Fynla team</p>
@endsection
```

- [ ] **Step 3: Replace ChurnedSubscriberMail (similar shape, different opening)**

```php
<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChurnedSubscriberMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public array $feedbackUrls = [],
        public ?string $subscriptionDuration = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Thank you for being a Fynla subscriber — we'd love your feedback",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.churned-subscriber',
            with: [
                'firstName' => $this->user->first_name ?? 'there',
                'feedbackUrls' => $this->feedbackUrls,
                'subscriptionDuration' => $this->subscriptionDuration,
            ],
        );
    }
}
```

- [ ] **Step 4: Create the churned-subscriber template**

`resources/views/emails/lifecycle/churned-subscriber.blade.php`:

```html
@extends('emails.lifecycle._layout')
@section('title', 'Thank you for being a Fynla subscriber')
@section('content')
    <p>Hi {{ $firstName }},</p>

    <p>You were a Fynla subscriber@if ($subscriptionDuration) for {{ $subscriptionDuration }}@endif and we're really sorry to see you go. Thank you for trusting us with your financial planning during that time.</p>

    <p>We're a small team trying to build the best UK financial planning tool we can, and the only way we get better is by hearing from people who chose to leave. If you have a moment, what was the main reason?</p>

    @include('emails.lifecycle._quick-picks', [
        'buttons' => $feedbackUrls,
        'labels' => [
            'too_expensive' => 'Too expensive',
            'missing_features' => 'Missing features',
            'found_alternative' => 'Found alternative',
            'not_what_expected' => 'Not what I expected',
            'bugs_or_ux' => 'Bugs or poor UX',
            'personal_change' => 'Personal change',
            'other' => 'Other',
        ],
    ])

    <p>— The Fynla team</p>
@endsection
```

- [ ] **Step 5: Commit**

```bash
git add app/Mail/Lifecycle/CancelledTrialerMail.php app/Mail/Lifecycle/ChurnedSubscriberMail.php resources/views/emails/lifecycle/cancelled-trialer.blade.php resources/views/emails/lifecycle/churned-subscriber.blade.php
git commit -m "feat: implement Cancelled and Churned mail classes + templates"
```

---

### Task 8.5 — Lapsed Subscriber mail + template

**Files:**
- Modify: `app/Mail/Lifecycle/LapsedSubscriberMail.php`
- Create: `resources/views/emails/lifecycle/lapsed-subscriber.blade.php`

- [ ] **Step 1: Replace the stub**

```php
<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LapsedSubscriberMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $updatePaymentUrl = null,
        public array $feedbackUrls = [],
        public ?string $gracePeriodEnd = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Your Fynla payment didn't go through — let's get you back on track",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.lapsed-subscriber',
            with: [
                'firstName' => $this->user->first_name ?? 'there',
                'updatePaymentUrl' => $this->updatePaymentUrl,
                'feedbackUrls' => $this->feedbackUrls,
                'gracePeriodEnd' => $this->gracePeriodEnd,
            ],
        );
    }
}
```

- [ ] **Step 2: Create the template**

`resources/views/emails/lifecycle/lapsed-subscriber.blade.php`:

```html
@extends('emails.lifecycle._layout')
@section('title', 'Your Fynla payment didn\'t go through')
@section('content')
    <p>Hi {{ $firstName }},</p>

    <p>We weren't able to process your latest Fynla subscription payment. This usually happens for one of a few reasons:</p>

    <ul>
        <li>The card on file expired</li>
        <li>The card has insufficient funds</li>
        <li>The bank flagged the transaction as suspicious</li>
        <li>The bank requires extra authentication</li>
    </ul>

    <p>The good news: your account is still active and your data is safe. We'll keep trying for a few more days, but if your payment isn't sorted@if ($gracePeriodEnd) by {{ $gracePeriodEnd }}@endif, your subscription will lapse and you'll lose access.</p>

    @include('emails.lifecycle._button', ['url' => $updatePaymentUrl, 'label' => 'UPDATE PAYMENT METHOD'])

    <p>If something has changed and you'd like to talk to us, just click one of the buttons below — we won't hassle you either way:</p>

    @include('emails.lifecycle._quick-picks', [
        'buttons' => $feedbackUrls,
        'labels' => [
            'will_fix' => "I'll fix it shortly",
            'wants_to_cancel' => 'I want to cancel',
            'needs_help' => 'I need help',
        ],
    ])

    <p>— The Fynla team</p>
@endsection
```

- [ ] **Step 3: Commit**

```bash
git add app/Mail/Lifecycle/LapsedSubscriberMail.php resources/views/emails/lifecycle/lapsed-subscriber.blade.php
git commit -m "feat: implement LapsedSubscriberMail + template"
```

---

### Task 8.6 — Feedback thank-you views

**Files:**
- Create: `resources/views/lifecycle/feedback-thanks.blade.php`
- Create: `resources/views/lifecycle/feedback-text-thanks.blade.php`

- [ ] **Step 1: Create the main thank-you page**

`resources/views/lifecycle/feedback-thanks.blade.php`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanks for your feedback — Fynla</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F7F6F4; color: #1F2A44; margin: 0; padding: 40px 20px; }
        .card { max-width: 600px; margin: 0 auto; background: #FFFFFF; border-radius: 12px; padding: 40px; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
        h1 { color: #1F2A44; font-weight: 900; }
        textarea { width: 100%; min-height: 120px; padding: 12px; border: 1px solid #EEEEEE; border-radius: 6px; font-family: inherit; font-size: 14px; box-sizing: border-box; }
        .btn { display: inline-block; padding: 12px 24px; background: #E83E6D; color: #FFFFFF; text-decoration: none; font-weight: 600; border-radius: 8px; border: none; font-size: 16px; cursor: pointer; }
        .btn-secondary { background: transparent; color: #1F2A44; border: 1px solid #1F2A44; margin-left: 8px; }
        .reason { background: #FDFAF7; padding: 12px; border-radius: 6px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Thanks — that helps us understand</h1>

        <div class="reason">
            <strong>You said:</strong> {{ ucwords(str_replace('_', ' ', $reason)) }}
        </div>

        <p>If there's anything else you'd like to share, we'd love to hear it. Optional — but every word helps us improve Fynla.</p>

        <form method="POST" action="{{ route('lifecycle.feedback-text') }}{{ '?' . parse_url($signed_token, PHP_URL_QUERY) }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $user_id }}">
            <input type="hidden" name="campaign" value="{{ $campaign }}">
            <textarea name="free_text" maxlength="2000" placeholder="Tell us more (optional)..."></textarea>
            <p style="margin: 20px 0;">
                <button type="submit" class="btn">Send feedback</button>
                <a href="{{ config('app.url') }}" class="btn btn-secondary">No thanks, I'm done</a>
            </p>
        </form>
    </div>
</body>
</html>
```

- [ ] **Step 2: Create the post-text-submit page**

`resources/views/lifecycle/feedback-text-thanks.blade.php`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank you — Fynla</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F7F6F4; color: #1F2A44; margin: 0; padding: 40px 20px; }
        .card { max-width: 600px; margin: 0 auto; background: #FFFFFF; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
        h1 { color: #1F2A44; font-weight: 900; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Thank you</h1>
        <p>We've recorded your feedback. We won't ask again.</p>
        <p><a href="{{ config('app.url') }}" style="color: #E83E6D; text-decoration: none;">Return to Fynla</a></p>
    </div>
</body>
</html>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/lifecycle/feedback-thanks.blade.php resources/views/lifecycle/feedback-text-thanks.blade.php
git commit -m "feat: add feedback thank-you Blade views for lifecycle quick-picks"
```

---

### Task 8.7 — Tangential cleanup: fix existing trial reminder palette

**Files:**
- Modify: `resources/views/emails/trial-expiration-reminder.blade.php`

- [ ] **Step 1: Read the current file to find the wrong colours**

```bash
grep -n '#3b82f6\|#f0f9ff\|#1e40af\|#64748b\|#fef2f2\|#fecaca\|#991b1b\|#7f1d1d\|#f9fafb\|#6b7280' resources/views/emails/trial-expiration-reminder.blade.php
```

This gives you all the lines with non-Fynla colours.

- [ ] **Step 2: Swap each wrong colour to the Fynla palette**

Make these specific replacements in `resources/views/emails/trial-expiration-reminder.blade.php`:

| Old | New | Reason |
|---|---|---|
| `#3b82f6` | `#E83E6D` | Generic blue → raspberry-500 (CTAs) |
| `#f0f9ff` | `#FDFAF7` | Light blue bg → savannah-100 (subtle highlight) |
| `#1e40af` | `#1F2A44` | Dark blue → horizon-500 |
| `#64748b` | `#717171` | Gray → neutral-500 |
| `#fef2f2` | `#FDFAF7` | Light red bg → savannah-100 |
| `#fecaca` | `#EEEEEE` | Light red border → light-gray |
| `#991b1b` | `#1F2A44` | Dark red → horizon-500 (just text) |
| `#7f1d1d` | `#1F2A44` | Dark red → horizon-500 |
| `#f9fafb` | `#F7F6F4` | Footer bg → eggshell-500 |
| `#6b7280` | `#717171` | Footer text gray → neutral-500 |

Use `Edit` with `replace_all: true` for each colour.

- [ ] **Step 3: Verify no banned colours remain**

```bash
grep -nE '#3b82f6|#f0f9ff|#1e40af|#fef2f2|#fecaca|#991b1b|#7f1d1d|#f9fafb|#6b7280|#64748b' resources/views/emails/trial-expiration-reminder.blade.php
```

Expected: no output (zero matches).

- [ ] **Step 4: Commit**

```bash
git add resources/views/emails/trial-expiration-reminder.blade.php
git commit -m "fix: trial reminder template uses Fynla palette colours instead of generic blue"
```

---

## Phase 9 — Magic link routes and action handler

### Task 9.1 — Add the 5 routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Find where to add them in `routes/web.php`**

```bash
tail -30 routes/web.php
```

- [ ] **Step 2: Append the route group**

```php
// Lifecycle email magic link actions — all signed URLs, no auth required
Route::middleware('signed')->prefix('lifecycle')->group(function () {
    Route::get('/restart-trial', [\App\Http\Controllers\Lifecycle\LifecycleActionController::class, 'restartTrial'])
         ->name('lifecycle.restart-trial');
    Route::get('/apply-discount', [\App\Http\Controllers\Lifecycle\LifecycleActionController::class, 'applyDiscount'])
         ->name('lifecycle.apply-discount');
    Route::get('/feedback', [\App\Http\Controllers\Lifecycle\LifecycleActionController::class, 'feedback'])
         ->name('lifecycle.feedback');
    Route::get('/update-payment', [\App\Http\Controllers\Lifecycle\LifecycleActionController::class, 'updatePayment'])
         ->name('lifecycle.update-payment');
});

Route::post('/lifecycle/feedback-text', [\App\Http\Controllers\Lifecycle\LifecycleActionController::class, 'submitFeedbackText'])
     ->name('lifecycle.feedback-text')
     ->middleware('signed');
```

- [ ] **Step 3: Verify the routes load**

```bash
php artisan route:clear
php artisan route:list | grep lifecycle
```

Expected: 5 routes listed.

- [ ] **Step 4: Commit**

```bash
git add routes/web.php
git commit -m "feat: add 5 lifecycle magic link routes (signed URLs)"
```

---

### Task 9.2 — Create `LifecycleActionController` skeleton

**Files:**
- Create: `app/Http/Controllers/Lifecycle/LifecycleActionController.php`

- [ ] **Step 1: Create the controller with all 5 methods**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lifecycle;

use App\Http\Controllers\Controller;
use App\Models\FeedbackResponse;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Services\Payment\TrialService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LifecycleActionController extends Controller
{
    public function __construct(
        private readonly TrialService $trialService,
    ) {}

    public function restartTrial(Request $request): RedirectResponse
    {
        $userId = (int) $request->query('user_id');
        $user = User::findOrFail($userId);

        $this->markClicked($userId, 'empty_trialer', 'restarted_trial');

        $this->trialService->restartTrial($user, days: (int) config('lifecycle.trial_restart_days', 14));

        if (auth()->check() && auth()->id() === $userId) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Welcome back! Your Fynla trial is active for another ' . config('lifecycle.trial_restart_days', 14) . ' days.');
        }

        return redirect()
            ->route('login')
            ->with('lifecycle_message', 'Sign in to access your reactivated Fynla trial.');
    }

    public function applyDiscount(Request $request): RedirectResponse
    {
        $userId = (int) $request->query('user_id');
        $campaign = (string) $request->query('campaign');
        $code = (string) $request->query('code');

        $this->markClicked($userId, $campaign, 'applied_discount');

        session([
            'lifecycle.pending_discount' => [
                'code' => $code,
                'user_id' => $userId,
                'expires' => now()->addHour(),
            ],
        ]);

        if (auth()->check() && auth()->id() === $userId) {
            return redirect()->route('checkout.index', ['discount_code' => $code]);
        }

        return redirect()
            ->route('login')
            ->with('intended_after_login', route('checkout.index', ['discount_code' => $code]))
            ->with('lifecycle_message', 'Sign in to claim your welcome discount.');
    }

    public function feedback(Request $request): View
    {
        $userId = (int) $request->query('user_id');
        $campaign = (string) $request->query('campaign');
        $reason = (string) $request->query('reason');

        $allowedReasons = config("lifecycle.feedback_reasons.{$campaign}", []);
        abort_unless(in_array($reason, $allowedReasons, true), 400);

        FeedbackResponse::updateOrCreate(
            ['user_id' => $userId, 'campaign' => $campaign],
            ['reason_code' => $reason, 'clicked_at' => now()]
        );

        $this->markClicked($userId, $campaign, "feedback:{$reason}");

        return view('lifecycle.feedback-thanks', [
            'campaign' => $campaign,
            'reason' => $reason,
            'user_id' => $userId,
            'signed_token' => $request->fullUrl(),
        ]);
    }

    public function submitFeedbackText(Request $request): View
    {
        $request->validate(['free_text' => 'required|string|max:2000']);

        FeedbackResponse::where('user_id', (int) $request->input('user_id'))
            ->where('campaign', (string) $request->input('campaign'))
            ->update([
                'free_text' => $request->input('free_text'),
                'text_submitted_at' => now(),
            ]);

        return view('lifecycle.feedback-text-thanks');
    }

    public function updatePayment(Request $request): RedirectResponse
    {
        $userId = (int) $request->query('user_id');

        $this->markClicked($userId, 'lapsed_subscriber', 'clicked_update_payment');

        if (auth()->check() && auth()->id() === $userId) {
            return redirect()->route('account.billing');
        }

        return redirect()
            ->route('login')
            ->with('intended_after_login', route('account.billing'))
            ->with('lifecycle_message', 'Sign in to update your payment method.');
    }

    private function markClicked(int $userId, string $campaign, string $action): void
    {
        LifecycleEmailLog::where('user_id', $userId)
            ->where('campaign', $campaign)
            ->whereNull('clicked_at')
            ->update([
                'clicked_at' => now(),
                'action_taken' => $action,
            ]);
    }
}
```

- [ ] **Step 2: Verify the routes resolve**

```bash
php artisan route:clear
php artisan route:list | grep lifecycle
```

Expected: 5 routes listed with controller method bindings (no errors).

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Lifecycle/LifecycleActionController.php
git commit -m "feat: add LifecycleActionController with 5 magic link action handlers"
```

---

### Task 9.3 — Feature tests for the controller

**Files:**
- Create: `tests/Feature/Lifecycle/LifecycleActionControllerTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

declare(strict_types=1);

use App\Models\LifecycleEmailLog;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('restartTrial via valid signed URL reactivates the trial', function () {
    $user = User::factory()->create(['plan' => 'free']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_started_at' => now()->subDays(15),
        'trial_ends_at' => now()->subDays(8),
        'data_retention_starts_at' => now()->subDays(8),
    ]);

    LifecycleEmailLog::create([
        'user_id' => $user->id,
        'campaign' => 'empty_trialer',
        'sent_at' => now()->subDays(2),
    ]);

    $url = URL::temporarySignedRoute(
        'lifecycle.restart-trial',
        now()->addDays(7),
        ['user_id' => $user->id]
    );

    $response = $this->get($url);

    $response->assertRedirect(route('login'));
    $user->refresh();
    expect($user->plan)->toBe('pro');

    $log = LifecycleEmailLog::where('user_id', $user->id)->first();
    expect($log->clicked_at)->not->toBeNull();
    expect($log->action_taken)->toBe('restarted_trial');
});

it('rejects tampered signed URL', function () {
    $url = URL::temporarySignedRoute(
        'lifecycle.restart-trial',
        now()->addDays(7),
        ['user_id' => 1]
    );

    // Change the user_id without re-signing
    $tampered = str_replace('user_id=1', 'user_id=2', $url);

    $this->get($tampered)->assertForbidden();
});

it('feedback creates a row with reason_code', function () {
    $user = User::factory()->create();
    LifecycleEmailLog::create([
        'user_id' => $user->id,
        'campaign' => 'cancelled_trialer',
        'sent_at' => now()->subDays(1),
    ]);

    $url = URL::temporarySignedRoute(
        'lifecycle.feedback',
        now()->addDays(7),
        [
            'user_id' => $user->id,
            'campaign' => 'cancelled_trialer',
            'reason' => 'too_expensive',
        ]
    );

    $this->get($url)->assertOk();

    $row = \App\Models\FeedbackResponse::where('user_id', $user->id)->first();
    expect($row->reason_code)->toBe('too_expensive');
    expect($row->free_text)->toBeNull();
    expect($row->clicked_at)->not->toBeNull();
});

it('feedback rejects an unknown reason code', function () {
    $user = User::factory()->create();

    $url = URL::temporarySignedRoute(
        'lifecycle.feedback',
        now()->addDays(7),
        [
            'user_id' => $user->id,
            'campaign' => 'cancelled_trialer',
            'reason' => 'invalid_reason',
        ]
    );

    $this->get($url)->assertStatus(400);
});

it('clicking a different reason on the same email replaces (not duplicates)', function () {
    $user = User::factory()->create();

    $url1 = URL::temporarySignedRoute(
        'lifecycle.feedback',
        now()->addDays(7),
        ['user_id' => $user->id, 'campaign' => 'cancelled_trialer', 'reason' => 'too_expensive']
    );
    $url2 = URL::temporarySignedRoute(
        'lifecycle.feedback',
        now()->addDays(7),
        ['user_id' => $user->id, 'campaign' => 'cancelled_trialer', 'reason' => 'missing_features']
    );

    $this->get($url1);
    $this->get($url2);

    expect(\App\Models\FeedbackResponse::where('user_id', $user->id)->count())->toBe(1);
    expect(\App\Models\FeedbackResponse::where('user_id', $user->id)->first()->reason_code)->toBe('missing_features');
});

it('updatePayment redirects to billing for logged-in user', function () {
    $user = User::factory()->create();

    LifecycleEmailLog::create([
        'user_id' => $user->id,
        'campaign' => 'lapsed_subscriber',
        'sent_at' => now()->subDays(1),
    ]);

    $url = URL::temporarySignedRoute(
        'lifecycle.update-payment',
        now()->addDays(7),
        ['user_id' => $user->id]
    );

    $this->actingAs($user)->get($url)->assertRedirect(route('account.billing'));

    $log = LifecycleEmailLog::where('user_id', $user->id)->first();
    expect($log->action_taken)->toBe('clicked_update_payment');
});
```

- [ ] **Step 2: Run — expect failures only on routes that don't exist (e.g. `account.billing` may not exist)**

```bash
./vendor/bin/pest tests/Feature/Lifecycle/LifecycleActionControllerTest.php
```

Note any test failures. If `route('account.billing')` doesn't resolve, find the actual route name in `routes/web.php` and update the controller. If `route('checkout.index')` doesn't exist either, find the real one.

- [ ] **Step 3: Fix any route name mismatches**

```bash
php artisan route:list | grep -iE "billing|checkout|dashboard|login"
```

Update `LifecycleActionController.php` to use the real route names. Re-run tests until all pass.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Lifecycle/LifecycleActionController.php tests/Feature/Lifecycle/LifecycleActionControllerTest.php
git commit -m "test: feature tests for LifecycleActionController action handlers"
```

---

## Phase 10 — Command, Kernel, AppServiceProvider

### Task 10.1 — Bind LifecycleEngine in AppServiceProvider

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add the singleton binding**

In `AppServiceProvider::register()`, add:

```php
$this->app->singleton(\App\Services\Lifecycle\LifecycleEngine::class, function ($app) {
    return new \App\Services\Lifecycle\LifecycleEngine(
        snapshotService: $app->make(\App\Services\Lifecycle\LifecycleSnapshotService::class),
        discountGenerator: $app->make(\App\Services\Lifecycle\LifecycleDiscountCodeGenerator::class),
    );
});
```

- [ ] **Step 2: Verify**

```bash
php artisan tinker --execute="\$e = app(\\App\\Services\\Lifecycle\\LifecycleEngine::class); echo get_class(\$e).PHP_EOL;"
```

Expected: `App\Services\Lifecycle\LifecycleEngine`

- [ ] **Step 3: Commit**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "feat: bind LifecycleEngine as singleton in AppServiceProvider"
```

---

### Task 10.2 — Create `RunLifecycleEngine` artisan command

**Files:**
- Create: `app/Console/Commands/RunLifecycleEngine.php`

- [ ] **Step 1: Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Lifecycle\LifecycleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunLifecycleEngine extends Command
{
    protected $signature = 'lifecycle:run-daily';

    protected $description = 'Send lifecycle emails (re-engagement, conversion, feedback, recovery)';

    public function handle(LifecycleEngine $engine): int
    {
        if (! config('lifecycle.enabled')) {
            $this->warn('Lifecycle engine is disabled via config.');
            return Command::SUCCESS;
        }

        // Defence-in-depth: refuse to run if test users haven't been cleaned up
        $staleTestUsers = User::where('is_lifecycle_test_user', true)->count();
        if ($staleTestUsers > 0) {
            Log::error('Lifecycle engine refusing to run: stale test users present', [
                'count' => $staleTestUsers,
            ]);
            $this->error("Refusing to run — {$staleTestUsers} test users still exist. Run 'php artisan lifecycle:e2e-cleanup' first.");
            return Command::FAILURE;
        }

        Log::info('Lifecycle engine starting');
        $stats = $engine->run();

        foreach ($stats as $campaign => $counts) {
            $this->info(sprintf(
                '%s: %d sent, %d skipped, %d errored',
                $campaign,
                $counts['sent'] ?? 0,
                $counts['skipped'] ?? 0,
                $counts['errored'] ?? 0
            ));
        }

        Log::info('Lifecycle engine completed', ['stats' => $stats]);

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 2: Verify the command exists**

```bash
php artisan list | grep lifecycle
```

Expected: shows `lifecycle:run-daily`.

- [ ] **Step 3: Run the command (should be a no-op locally)**

```bash
php artisan lifecycle:run-daily
```

Expected: prints stats per campaign (all 0 sent locally because there are no eligible users).

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/RunLifecycleEngine.php
git commit -m "feat: add lifecycle:run-daily artisan command with kill switch and stale test user guard"
```

---

### Task 10.3 — Add to Kernel.php at 08:30

**Files:**
- Modify: `app/Console/Kernel.php`

- [ ] **Step 1: Add the schedule line**

In `Kernel::schedule()`, add a new line near the other email commands:

```php
$schedule->command('lifecycle:run-daily')->dailyAt('08:30');
```

- [ ] **Step 2: Verify the schedule**

```bash
php artisan schedule:list | grep lifecycle
```

Expected: `30 8 * * *  php artisan lifecycle:run-daily  Next Due: ...`

- [ ] **Step 3: Commit**

```bash
git add app/Console/Kernel.php
git commit -m "feat: schedule lifecycle:run-daily at 08:30 UTC"
```

---

## Phase 11 — Web notification preferences page + mobile augmentation + API

### Task 11.1 — Update `UpdateNotificationPreferencesRequest` form request

**Files:**
- Modify: `app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php`

- [ ] **Step 1: Read the existing rules**

```bash
cat app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php
```

- [ ] **Step 2: Add the 5 lifecycle field rules**

In the `rules()` method, add:

```php
'lifecycle_empty_trialer' => 'nullable|boolean',
'lifecycle_engaged_trialer' => 'nullable|boolean',
'lifecycle_cancelled_trialer' => 'nullable|boolean',
'lifecycle_churned_subscriber' => 'nullable|boolean',
'lifecycle_lapsed_subscriber' => 'nullable|boolean',
'estate_alerts' => 'nullable|boolean',  // also missing from existing rules
```

(Check first whether `estate_alerts` is already in the rules — if so, only add the 5 lifecycle fields.)

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php
git commit -m "feat: add 5 lifecycle fields + estate_alerts to UpdateNotificationPreferencesRequest"
```

---

### Task 11.2 — Fix `estate_alerts` gap + add lifecycle fields to existing mobile controller

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php`

- [ ] **Step 1: Update the `show()` method to return all 14 fields**

Replace the `show()` method:

```php
public function show(Request $request): JsonResponse
{
    try {
        $prefs = NotificationPreference::getOrCreateForUser($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'policy_renewals' => $prefs->policy_renewals,
                'goal_milestones' => $prefs->goal_milestones,
                'contribution_reminders' => $prefs->contribution_reminders,
                'market_updates' => $prefs->market_updates,
                'fyn_daily_insight' => $prefs->fyn_daily_insight,
                'security_alerts' => $prefs->security_alerts,
                'payment_alerts' => $prefs->payment_alerts,
                'mortgage_rate_alerts' => $prefs->mortgage_rate_alerts,
                'estate_alerts' => $prefs->estate_alerts,
                'lifecycle_empty_trialer' => $prefs->lifecycle_empty_trialer,
                'lifecycle_engaged_trialer' => $prefs->lifecycle_engaged_trialer,
                'lifecycle_cancelled_trialer' => $prefs->lifecycle_cancelled_trialer,
                'lifecycle_churned_subscriber' => $prefs->lifecycle_churned_subscriber,
                'lifecycle_lapsed_subscriber' => $prefs->lifecycle_lapsed_subscriber,
            ],
        ]);
    } catch (\Exception $e) {
        return $this->errorResponse($e, 'Fetching notification preferences');
    }
}
```

- [ ] **Step 2: Smoke test**

```bash
php artisan tinker --execute="\$user = \App\Models\User::first(); \App\Models\NotificationPreference::getOrCreateForUser(\$user->id); echo 'OK'.PHP_EOL;"
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php
git commit -m "fix: include estate_alerts + 5 lifecycle fields in mobile notification preferences response"
```

---

### Task 11.3 — Create new web `NotificationPreferenceController`

**Files:**
- Create: `app/Http/Controllers/Api/NotificationPreferenceController.php`

- [ ] **Step 1: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateNotificationPreferencesRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    use SanitizedErrorResponse;

    public function show(Request $request): JsonResponse
    {
        try {
            $prefs = NotificationPreference::getOrCreateForUser($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => $prefs->only([
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
                ]),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching notification preferences');
        }
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        try {
            $prefs = NotificationPreference::getOrCreateForUser($request->user()->id);
            $prefs->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Notification preferences updated.',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Updating notification preferences');
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/Api/NotificationPreferenceController.php
git commit -m "feat: add web NotificationPreferenceController for non-mobile users"
```

---

### Task 11.4 — Add the API routes

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: Find an existing authenticated route group in `routes/api.php`**

```bash
grep -n "auth:sanctum" routes/api.php | head -5
```

- [ ] **Step 2: Add the 2 new routes inside the existing `auth:sanctum` group**

```php
Route::get('/notifications/preferences', [\App\Http\Controllers\Api\NotificationPreferenceController::class, 'show']);
Route::put('/notifications/preferences', [\App\Http\Controllers\Api\NotificationPreferenceController::class, 'update']);
```

- [ ] **Step 3: Verify**

```bash
php artisan route:clear
php artisan route:list | grep "notifications/preferences"
```

Expected: 2 routes (GET and PUT) under `/api/notifications/preferences`.

- [ ] **Step 4: Commit**

```bash
git add routes/api.php
git commit -m "feat: add /api/notifications/preferences routes for web users"
```

---

### Task 11.5 — Update mobile Vue settings to add 5 lifecycle toggles

**Files:**
- Modify: `resources/js/mobile/views/NotificationSettings.vue`

- [ ] **Step 1: Read the current toggleItems array**

```bash
grep -A 12 "toggleItems:" resources/js/mobile/views/NotificationSettings.vue
```

- [ ] **Step 2: Append 5 new entries to the `toggleItems` array**

In `data()`, add to `toggleItems` (after the existing 8 entries):

```js
{ key: 'lifecycle_empty_trialer', label: 'Trial Re-engagement', description: 'Invitations to come back if your trial expires unused' },
{ key: 'lifecycle_engaged_trialer', label: 'Trial Discount Offers', description: 'Discount offers if your trial expires after using the app' },
{ key: 'lifecycle_cancelled_trialer', label: 'Trial Cancellation Feedback', description: 'Brief feedback request after cancelling a trial' },
{ key: 'lifecycle_churned_subscriber', label: 'Subscription Cancellation Feedback', description: 'Brief feedback request after cancelling a subscription' },
{ key: 'lifecycle_lapsed_subscriber', label: 'Payment Recovery', description: 'Help with renewing your subscription if a payment fails' },
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/mobile/views/NotificationSettings.vue
git commit -m "feat: add 5 lifecycle email toggles to mobile NotificationSettings"
```

---

### Task 11.6 — Create web `NotificationPreferences.vue` component

**Files:**
- Create: `resources/js/components/UserProfile/NotificationPreferences.vue`

- [ ] **Step 1: Create the component**

```vue
<template>
  <div class="p-6 max-w-3xl">
    <h2 class="text-2xl font-black text-horizon-500 mb-2">Notification Preferences</h2>
    <p class="text-sm text-neutral-500 mb-6">Choose which Fynla emails you'd like to receive. You can change these at any time.</p>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 14" :key="i" class="bg-savannah-100 animate-pulse rounded-xl h-14"></div>
    </div>

    <div v-else>
      <div v-for="section in sections" :key="section.title" class="mb-8">
        <h3 class="text-lg font-bold text-horizon-500 mb-3">{{ section.title }}</h3>
        <div class="border border-light-gray rounded-xl divide-y divide-light-gray">
          <div
            v-for="item in section.items"
            :key="item.key"
            class="flex items-center justify-between p-4"
          >
            <div class="flex-1 min-w-0 mr-4">
              <p class="text-sm font-semibold text-horizon-500">{{ item.label }}</p>
              <p class="text-xs text-neutral-500 mt-0.5">{{ item.description }}</p>
            </div>
            <button
              type="button"
              class="relative w-11 h-6 rounded-full transition-colors flex-shrink-0"
              :class="preferences[item.key] ? 'bg-spring-500' : 'bg-neutral-300'"
              @click="togglePreference(item.key)"
            >
              <span
                class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                :class="preferences[item.key] ? 'translate-x-5' : 'translate-x-0'"
              ></span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';

export default {
  name: 'NotificationPreferences',
  data() {
    return {
      loading: true,
      preferences: {},
      sections: [
        {
          title: 'Account',
          items: [
            { key: 'security_alerts', label: 'Security Alerts', description: 'Login attempts and security changes' },
            { key: 'payment_alerts', label: 'Payment Alerts', description: 'Subscription payment confirmations' },
          ],
        },
        {
          title: 'Feature Alerts',
          items: [
            { key: 'policy_renewals', label: 'Policy Renewals', description: 'Reminders when policies are due for renewal' },
            { key: 'goal_milestones', label: 'Goal Milestones', description: 'Celebrations when you hit savings milestones' },
            { key: 'contribution_reminders', label: 'Contribution Reminders', description: 'Reminders to make regular contributions' },
            { key: 'market_updates', label: 'Market Updates', description: 'Notable changes in your investments' },
            { key: 'fyn_daily_insight', label: 'Fyn Daily Insight', description: 'A daily financial tip from Fyn' },
            { key: 'mortgage_rate_alerts', label: 'Mortgage Rate Alerts', description: 'Warnings when fixed rates are expiring' },
            { key: 'estate_alerts', label: 'Estate Alerts', description: 'Gift exemption and trust anniversary reminders' },
          ],
        },
        {
          title: 'Lifecycle Emails',
          items: [
            { key: 'lifecycle_empty_trialer', label: 'Trial Re-engagement', description: 'Invitations to come back if your trial expires unused' },
            { key: 'lifecycle_engaged_trialer', label: 'Trial Discount Offers', description: 'Discount offers if your trial expires after using the app' },
            { key: 'lifecycle_cancelled_trialer', label: 'Trial Cancellation Feedback', description: 'Brief feedback request after cancelling a trial' },
            { key: 'lifecycle_churned_subscriber', label: 'Subscription Cancellation Feedback', description: 'Brief feedback request after cancelling a subscription' },
            { key: 'lifecycle_lapsed_subscriber', label: 'Payment Recovery', description: 'Help with renewing your subscription if a payment fails' },
          ],
        },
      ],
    };
  },

  async mounted() {
    await this.fetchPreferences();
  },

  methods: {
    async fetchPreferences() {
      this.loading = true;
      try {
        const response = await api.get('/notifications/preferences');
        this.preferences = response.data.data || response.data;
      } catch (error) {
        console.error('Failed to load notification preferences', error);
        this.preferences = {};
      } finally {
        this.loading = false;
      }
    },

    async togglePreference(key) {
      const newValue = !this.preferences[key];
      const previousValue = this.preferences[key];
      this.preferences = { ...this.preferences, [key]: newValue };

      try {
        await api.put('/notifications/preferences', { [key]: newValue });
      } catch (error) {
        console.error('Failed to update preference', error);
        // Revert on failure
        this.preferences = { ...this.preferences, [key]: previousValue };
      }
    },
  },
};
</script>
```

- [ ] **Step 2: Add a route to the new component in the router**

Find `resources/js/router/index.js` and add a route under the authenticated routes:

```js
{
  path: '/profile/notifications',
  name: 'NotificationPreferences',
  component: () => import('@/components/UserProfile/NotificationPreferences.vue'),
  meta: { requiresAuth: true },
},
```

- [ ] **Step 3: Add a tab/link in `Settings.vue`**

Find `resources/js/components/UserProfile/Settings.vue` and add a navigation entry to the Notifications page. The exact change depends on the existing structure — add a link like:

```vue
<router-link to="/profile/notifications" class="...">
  Notifications
</router-link>
```

- [ ] **Step 4: Test in browser**

```bash
./dev.sh   # if not already running
```

Then in a browser, log in and navigate to `http://localhost:8000/profile/notifications`. Verify all 14 toggles render and persist when toggled.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/UserProfile/NotificationPreferences.vue resources/js/router/index.js resources/js/components/UserProfile/Settings.vue
git commit -m "feat: add web NotificationPreferences page with all 14 toggles"
```

---

## Phase 12 — E2E test infrastructure

### Task 12.1 — Create `LifecycleTestSeeder`

**Files:**
- Create: `database/seeders/LifecycleTestSeeder.php`

- [ ] **Step 1: Create the seeder**

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LifecycleTestSeeder extends Seeder
{
    private const PASSWORD = 'Password1!';
    private const EMAIL_DOMAIN = 'fynla.test';

    public function run(): void
    {
        // Clear any pre-existing test users (idempotent)
        User::where('is_lifecycle_test_user', true)->each(function ($u) {
            $u->subscriptions()->delete();
            $u->properties()->delete();
            $u->dcPensions()->delete();
            $u->savingsAccounts()->delete();
            $u->investmentAccounts()->delete();
            $u->lifeInsurancePolicies()->delete();
            $u->goals()->delete();
            $u->delete();
        });

        $this->createEmptyTrialer();
        $this->createEngagedTrialer();
        $this->createCancelledTrialer();
        $this->createChurnedSubscriber();
        $this->createLapsedSubscriber();
    }

    private function createEmptyTrialer(): void
    {
        $user = User::create([
            'first_name' => 'TestEmpty',
            'last_name' => 'User',
            'email' => 'lifecycle-e2e-1@' . self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'free',
            'is_lifecycle_test_user' => true,
            'created_at' => now()->subDays(9),
            'updated_at' => now()->subDays(9),
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'expired',
            'trial_started_at' => now()->subDays(9),
            'trial_ends_at' => now()->subDays(2),
            'data_retention_starts_at' => now()->subDays(2),
            'amount' => 0,
        ]);
    }

    private function createEngagedTrialer(): void
    {
        $user = User::create([
            'first_name' => 'TestEngaged',
            'last_name' => 'User',
            'email' => 'lifecycle-e2e-2@' . self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'free',
            'is_lifecycle_test_user' => true,
            'created_at' => now()->subDays(9),
            'updated_at' => now()->subDays(9),
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'expired',
            'trial_started_at' => now()->subDays(9),
            'trial_ends_at' => now()->subDays(2),
            'data_retention_starts_at' => now()->subDays(2),
            'amount' => 0,
        ]);

        // Add module data so they qualify as engaged
        \App\Models\Property::factory()->count(2)->create(['user_id' => $user->id]);
        \App\Models\DCPension::factory()->create(['user_id' => $user->id]);
        \App\Models\SavingsAccount::factory()->count(5)->create(['user_id' => $user->id]);
        \App\Models\Goal::factory()->count(2)->create(['user_id' => $user->id]);
    }

    private function createCancelledTrialer(): void
    {
        $user = User::create([
            'first_name' => 'TestCancelled',
            'last_name' => 'User',
            'email' => 'lifecycle-e2e-3@' . self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'free',
            'is_lifecycle_test_user' => true,
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(3),
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'cancelled',
            'trial_started_at' => now()->subDays(8),
            'trial_ends_at' => now()->addDays(1),  // future — cancelled BEFORE end
            'cancelled_at' => now()->subDays(3)->setTime(12, 0),
            'cancellation_reason' => 'test',
            'amount' => 0,
        ]);
    }

    private function createChurnedSubscriber(): void
    {
        $user = User::create([
            'first_name' => 'TestChurned',
            'last_name' => 'User',
            'email' => 'lifecycle-e2e-4@' . self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'free',
            'is_lifecycle_test_user' => true,
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(3),
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'monthly',
            'status' => 'cancelled',
            'trial_started_at' => now()->subDays(60),
            'trial_ends_at' => now()->subDays(53),
            'current_period_start' => now()->subDays(53),
            'current_period_end' => now()->addDays(20),
            'cancelled_at' => now()->subDays(3)->setTime(12, 0),
            'cancellation_reason' => 'test',
            'amount' => 1099,
        ]);
    }

    private function createLapsedSubscriber(): void
    {
        $user = User::create([
            'first_name' => 'TestLapsed',
            'last_name' => 'User',
            'email' => 'lifecycle-e2e-5@' . self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'standard',
            'is_lifecycle_test_user' => true,
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(6),
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'monthly',
            'status' => 'past_due',
            'trial_started_at' => now()->subDays(60),
            'trial_ends_at' => now()->subDays(53),
            'current_period_start' => now()->subDays(36),
            'current_period_end' => now()->subDays(6),
            'amount' => 1099,
        ]);
    }
}
```

- [ ] **Step 2: Smoke test the seeder**

```bash
php artisan tinker --execute="(new \Database\Seeders\LifecycleTestSeeder())->run(); echo 'test users: '.\App\Models\User::where('is_lifecycle_test_user', true)->count().PHP_EOL; \App\Models\User::where('is_lifecycle_test_user', true)->each(function(\$u){echo '  '.\$u->first_name.' / '.\$u->email.PHP_EOL;});"
```

Expected: 5 test users listed (TestEmpty, TestEngaged, TestCancelled, TestChurned, TestLapsed).

- [ ] **Step 3: Clean up the test users (manual sanity check that delete works)**

```bash
php artisan tinker --execute="\App\Models\User::where('is_lifecycle_test_user', true)->each(function(\$u){\$u->subscriptions()->delete(); \$u->delete();}); echo 'cleaned up'.PHP_EOL;"
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/LifecycleTestSeeder.php
git commit -m "feat: add LifecycleTestSeeder for end-to-end verification"
```

---

### Task 12.2 — Create `lifecycle:e2e-test` artisan command

**Files:**
- Create: `app/Console/Commands/RunLifecycleEngineE2ETest.php`

- [ ] **Step 1: Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiscountCode;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Services\Lifecycle\LifecycleEngine;
use Database\Seeders\LifecycleTestSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class RunLifecycleEngineE2ETest extends Command
{
    protected $signature = 'lifecycle:e2e-test {--recipient= : Real email to send all test emails to}';

    protected $description = 'Run the lifecycle engine against dummy seeded users with email recipient override';

    public function handle(LifecycleEngine $engine): int
    {
        $recipient = $this->option('recipient');
        if (! $recipient) {
            $this->error('--recipient is required (e.g., --recipient=chris@fynla.org)');
            return Command::FAILURE;
        }

        $this->info("Running lifecycle e2e test, sending all emails to: {$recipient}");

        // Override config for this run
        config(['lifecycle.test_recipient_override' => $recipient]);

        // Seed test users
        $this->info('Seeding 5 test users...');
        (new LifecycleTestSeeder())->run();

        $testUserCount = User::where('is_lifecycle_test_user', true)->count();
        $this->info("Created {$testUserCount} test users.");

        // Run the engine in test mode
        $this->info('Running lifecycle engine in test mode...');
        $stats = $engine->setTestMode(true)->run();

        // Print stats
        $this->info('--- Stats ---');
        foreach ($stats as $campaign => $counts) {
            $this->info(sprintf('%s: %d sent, %d errored', $campaign, $counts['sent'] ?? 0, $counts['errored'] ?? 0));
        }

        // Print magic links for manual click verification
        $this->info('--- Magic links generated ---');
        $logs = LifecycleEmailLog::with('user')
            ->whereIn('user_id', User::where('is_lifecycle_test_user', true)->pluck('id'))
            ->get();

        foreach ($logs as $log) {
            $this->info("Test user: {$log->user->first_name} (ID {$log->user_id}), campaign: {$log->campaign}, log row id: {$log->id}");
        }

        // Print discount codes (Campaign 2)
        $codes = DiscountCode::whereIn('user_id', User::where('is_lifecycle_test_user', true)->pluck('id'))->get();
        if ($codes->isNotEmpty()) {
            $this->info('--- Per-user discount codes generated ---');
            foreach ($codes as $code) {
                $this->info("  {$code->code} (user_id={$code->user_id}, expires={$code->expires_at})");
            }
        }

        $this->newLine();
        $this->info("Done. Check {$recipient} inbox for 5 emails.");
        $this->warn("REMEMBER to run 'php artisan lifecycle:e2e-cleanup' when finished testing.");

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 2: Verify the command is registered**

```bash
php artisan list | grep e2e-test
```

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/RunLifecycleEngineE2ETest.php
git commit -m "feat: add lifecycle:e2e-test command for end-to-end live testing"
```

---

### Task 12.3 — Create `lifecycle:e2e-cleanup` artisan command

**Files:**
- Create: `app/Console/Commands/RunLifecycleEngineE2ECleanup.php`

- [ ] **Step 1: Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiscountCode;
use App\Models\FeedbackResponse;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunLifecycleEngineE2ECleanup extends Command
{
    protected $signature = 'lifecycle:e2e-cleanup';

    protected $description = 'Remove all lifecycle test users and their associated data';

    public function handle(): int
    {
        $testUsers = User::where('is_lifecycle_test_user', true)->get();

        if ($testUsers->isEmpty()) {
            $this->info('No lifecycle test users to clean up.');
            return Command::SUCCESS;
        }

        $userIds = $testUsers->pluck('id')->all();

        $stats = [
            'lifecycle_email_log' => LifecycleEmailLog::whereIn('user_id', $userIds)->delete(),
            'feedback_responses' => FeedbackResponse::whereIn('user_id', $userIds)->delete(),
            'discount_codes' => DiscountCode::whereIn('user_id', $userIds)->delete(),
        ];

        // Delete dependent data (the User model has many cascade-delete relations,
        // but be explicit to avoid surprises)
        foreach ($testUsers as $user) {
            $user->subscriptions()->delete();
            $user->properties()->delete();
            $user->dcPensions()->delete();
            $user->savingsAccounts()->delete();
            $user->investmentAccounts()->delete();
            $user->lifeInsurancePolicies()->delete();
            $user->goals()->delete();
        }

        $deletedUsers = User::whereIn('id', $userIds)->delete();
        $stats['users'] = $deletedUsers;

        $this->info('Cleanup complete:');
        foreach ($stats as $table => $count) {
            $this->info("  {$table}: {$count} rows deleted");
        }

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 2: Smoke test (with the seeder, then cleanup)**

```bash
php artisan tinker --execute="(new \Database\Seeders\LifecycleTestSeeder())->run();"
php artisan lifecycle:e2e-cleanup
php artisan tinker --execute="echo 'remaining test users: '.\App\Models\User::where('is_lifecycle_test_user', true)->count().PHP_EOL;"
```

Expected: `remaining test users: 0`

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/RunLifecycleEngineE2ECleanup.php
git commit -m "feat: add lifecycle:e2e-cleanup command for tearing down test users"
```

---

## Phase 13 — Manual verification + test review report

This is **manual** — no code, no tests, just running through the protocol and writing the report.

### Task 13.1 — Run the 12-step verification protocol

- [ ] **Step 1: Locally first**

Make sure the local dev environment is running and you're set up to receive emails (locally, mail goes to mailpit by default).

```bash
./dev.sh
php artisan db:seed
php artisan lifecycle:e2e-test --recipient=chris@fynla.org
```

Inspect the local CLI output. Verify magic links are generated. The local mail will go to mailpit if MAIL_HOST=mailpit, or to chris@fynla.org if MAIL_HOST=mail.fynla.org. Use whichever matches your local `.env`.

- [ ] **Step 2: Walk through all 12 steps from the spec**

Open the spec at `docs/superpowers/specs/2026-04-14-lifecycle-email-engine-design.md` § 8 and follow each numbered step.

For each step, mark the corresponding box in the test review report (next task).

- [ ] **Step 3: Run cleanup**

```bash
php artisan lifecycle:e2e-cleanup
```

---

### Task 13.2 — Write the test review report

**Files:**
- Create: `April/AprilNUpdates/lifecycleEngineE2EReport.md` (where N is the day you run the test — e.g., `April15Updates`)
- Copy to: `fynlaBrain/April/AprilNUpdates/lifecycleEngineE2EReport.md`

- [ ] **Step 1: Create the report**

Use this template:

```markdown
# Lifecycle Email Engine — E2E Verification Report

**Date:** [YYYY-MM-DD]
**Operator:** [your name]
**Environment:** [local / production]
**Recipient inbox used:** chris@fynla.org

## Result Summary

| Step | Description | Result |
|---|---|---|
| 1 | SSH to environment | PASS / FAIL |
| 2 | Run `lifecycle:e2e-test --recipient=chris@fynla.org` | PASS / FAIL |
| 3 | Open chris@fynla.org inbox | PASS / FAIL |
| 4 | Confirm 5 emails received within 60 seconds | PASS / FAIL |
| 5 | Email content verification (subjects, bodies, personalisation) | PASS / FAIL |
| 6 | Campaign 1 (TestEmpty): full restart-trial flow | PASS / FAIL |
| 7 | Campaign 2 (TestEngaged): discount apply at checkout | PASS / FAIL |
| 8 | Campaign 3 (TestCancelled): feedback quick-pick + text | PASS / FAIL |
| 9 | Campaign 4 (TestChurned): feedback quick-pick + text | PASS / FAIL |
| 10 | Campaign 5 (TestLapsed): update-payment + quick-picks | PASS / FAIL |
| 11 | Edge cases (tampered URL, expired URL, opt-out) | PASS / FAIL |
| 12 | Cleanup verified | PASS / FAIL |

## Issues Found

[List any issues encountered, with reproduction steps and severity. Empty if no issues.]

## Sign-off

- [ ] Ready to launch
- [ ] Blocked — see issues above

[Operator signature/date]
```

- [ ] **Step 2: Fill it in honestly**

Mark each step PASS only if you actually performed and verified it. If anything was skipped or failed, write it down with details.

- [ ] **Step 3: Copy to the vault**

```bash
cp April/April15Updates/lifecycleEngineE2EReport.md /Users/CSJ/Desktop/fynlaBrain/April/April15Updates/lifecycleEngineE2EReport.md
```

(Adjust the folder name to whatever date you ran it.)

- [ ] **Step 4: Commit (note: April/ is gitignored, so this is for local + vault only)**

The report file lives in the `April/` directory which is gitignored. That's intentional — it's a local artefact. The canonical copy lives in the vault.

---

## Phase 14 — Production deploy

Only proceed once Phase 13 is complete and the test review report shows all PASS.

### Task 14.1 — Build production assets locally

- [ ] **Step 1: Build**

```bash
./deploy/fynla-org/build.sh
```

Expected: `public/build/` directory generated with hashed asset filenames.

---

### Task 14.2 — Generate deploy file list

- [ ] **Step 1: Get the diff against main**

```bash
git diff main --name-only > /tmp/lifecycle-deploy-files.txt
cat /tmp/lifecycle-deploy-files.txt
```

This is the exact list of files that need uploading to production. Per `feedback_deploy_guide_completeness.md`, never write deploy guides from memory.

- [ ] **Step 2: Write a deploy guide**

Create `April/AprilNUpdates/deployLifecycleEngine.md` (and copy to vault) listing:
- Every PHP file changed (from the diff)
- Every Vue file changed
- Every Blade file changed
- The 5+ migration files
- The new config file
- The route files
- `public/build/` (entire directory)

Add post-upload SSH commands:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan optimize
```

---

### Task 14.3 — Upload via SiteGround File Manager

- [ ] **Step 1: User uploads files manually per `feedback_never_raw_vite_build.md` and CLAUDE.md "Manual File Upload Only" rule**

(Operator instruction — Claude does not upload these.)

---

### Task 14.4 — Run migrations on production

- [ ] **Step 1: SSH and migrate**

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate --force
```

Expected: 5 lifecycle migrations run (or 6 if the index migration was needed).

- [ ] **Step 2: Verify schema on production**

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

Expected: every line `EXISTS` or `OK`.

---

### Task 14.5 — Verify schedule entry on production

- [ ] **Step 1: Confirm cron sees the new command**

```bash
php artisan schedule:list | grep lifecycle
```

Expected: `30 8 * * *  php artisan lifecycle:run-daily  Next Due: ...`

- [ ] **Step 2: Manually verify the engine works (no-op, no real users eligible yet)**

```bash
php artisan lifecycle:run-daily
```

Expected: prints stats, all zeros (no real users qualify on day 1).

---

### Task 14.6 — Wait for first scheduled run

- [ ] **Step 1: Tomorrow at 08:31 UTC, check for activity**

```bash
ssh ... 'cd ~/www/fynla.org/public_html && tail storage/logs/laravel.log | grep -i lifecycle'
```

- [ ] **Step 2: Check `lifecycle_email_log` for new rows**

```bash
php artisan tinker --execute="echo 'rows: '.\DB::table('lifecycle_email_log')->count().PHP_EOL;"
```

If there are eligible users in the database (e.g., the 11 ghost trialers we cleaned up earlier had real candidates among them — but they were already expired and won't be Day 9 candidates), you'll see them populated.

---

### Task 14.7 — Update CSJTODO

- [ ] **Step 1: Update root `CSJTODO.md`**

Mark the lifecycle email engine as deployed. Add a "Day 1 monitoring" item for the next session.

- [ ] **Step 2: Commit**

```bash
git add CSJTODO.md
git commit -m "docs: lifecycle email engine deployed"
git push origin main
```

---

## Plan complete

Total estimated time: ~12-14 hours of focused engineering work, spread across however many sessions you choose.

The 14 phases produce:
- 5 (or 6) database migrations
- 3 new models + 5 model updates
- 6 new services (engine, snapshot, generator, contracts, 5 campaigns)
- 5 new mail classes + 10 Blade templates
- 1 new HTTP controller (lifecycle actions)
- 1 new HTTP controller (web notification preferences)
- 1 new web Vue page + mobile augmentation
- 1 new config file
- 3 new artisan commands
- 1 new seeder
- ~50 new automated tests
- 1 manual e2e verification protocol
- 1 written test review report
- 1 production deploy

**After running this plan, the lifecycle email engine is live and ready to serve five distinct user lifecycle moments with per-user opt-out preferences and end-to-end verified delivery.**
