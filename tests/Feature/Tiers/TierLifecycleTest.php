<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Account\RetentionPurgeService;
use App\Services\Payment\RevolutService;
use App\Services\Payment\TrialService;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Stores\TierGate;
use App\Services\Tiers\TierResolver;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

// ── PR5 review CRITICAL (symmetric REVOKE): subscription end clears tier ───
//
// Grant (confirmPayment/webhook) sets users.tier. The terminal "subscription
// ended → plan reset to free" transitions (TrialService::expireCancelledSubscriptions,
// RetentionPurgeService::purgeUser) must
// also clear users.tier or a paying customer who cancels keeps tier access
// forever after their access period expires — an entitlement/revenue leak.
// tier => null means "resolve via TierResolver" → resolves to 'free'.

it('revokes tier access when a cancelled premium subscription expires past its period', function () {
    $store = app(TierConfigurationStore::class);

    // premium paying customer who has cancelled; their access period has now passed.
    $user = User::factory()->create(['plan' => 'premium', 'tier' => 'premium']);
    Subscription::factory()->plan('premium')->billingCycle('monthly')->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'current_period_end' => now()->subDay(), // period already over
        'data_retention_starts_at' => null,      // not yet swept
        'amount' => 1499,
    ]);

    // Sanity: BEFORE the sweep the customer still resolves as premium.
    expect(app(TierResolver::class)->resolve($user->fresh()))->toBe('premium');

    $count = app(TrialService::class)->expireCancelledSubscriptions();

    expect($count)->toBe(1);

    $fresh = $user->fresh();
    // Legacy billing-compat column reset AND canonical gating column cleared.
    expect($fresh->plan)->toBe('free')
        ->and($fresh->tier)->toBeNull()
        // Access correctly REVOKED to Free — not still premium.
        ->and(app(TierResolver::class)->resolve($fresh))->toBe('free')
        // The concrete proof: the gate now gives the Free cap of 3 for
        // savings_account, NOT premium's unlimited (null).
        ->and(app(TierGate::class)->hardLimit($fresh, 'savings_account'))->toBe(2)
        ->and($store->capFor('premium', 'savings_account'))->toBeNull()
        // A revoked user is now blocked beyond the Free cap.
        ->and(app(TierGate::class)->canCreate($fresh, 'savings_account', 3))->toBeFalse();
});

it('does NOT revoke an active premium subscriber during the cancelled-expiry sweep', function () {
    // Active premium subscriber — must be untouched by the terminal sweep.
    $active = User::factory()->create(['plan' => 'premium', 'tier' => 'premium']);
    Subscription::factory()->plan('premium')->billingCycle('monthly')->create([
        'user_id' => $active->id,
        'status' => 'active',
        'current_period_end' => now()->addMonth(),
        'amount' => 1499,
    ]);

    // A cancelled-but-NOT-yet-expired premium subscriber — access preserved
    // until current_period_end; the sweep must NOT touch them either.
    $cancelledNotExpired = User::factory()->create(['plan' => 'premium', 'tier' => 'premium']);
    Subscription::factory()->plan('premium')->billingCycle('monthly')->create([
        'user_id' => $cancelledNotExpired->id,
        'status' => 'cancelled',
        'current_period_end' => now()->addWeek(), // still in access window
        'data_retention_starts_at' => null,
        'amount' => 1499,
    ]);

    app(TrialService::class)->expireCancelledSubscriptions();

    // Both keep premium — the reset only hits genuinely-ended subscriptions.
    expect($active->fresh()->tier)->toBe('premium')
        ->and(app(TierResolver::class)->resolve($active->fresh()))->toBe('premium')
        ->and($cancelledNotExpired->fresh()->tier)->toBe('premium')
        ->and(app(TierResolver::class)->resolve($cancelledNotExpired->fresh()))->toBe('premium');
});

it('expires ended one-time Premium access and revokes its entitlement', function () {
    $user = User::factory()->create(['plan' => 'premium', 'tier' => 'premium']);
    $subscription = Subscription::factory()->plan('premium')->billingCycle('monthly')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => false,
        'current_period_end' => now()->subMinute(),
        'data_retention_starts_at' => null,
    ]);

    expect($subscription->isActive())->toBeFalse()
        ->and(app(TrialService::class)->expireCancelledSubscriptions())->toBe(1)
        ->and($subscription->fresh()->status)->toBe('expired')
        ->and($subscription->fresh()->data_retention_starts_at)->not->toBeNull()
        ->and($user->fresh()->plan)->toBe('free')
        ->and($user->fresh()->tier)->toBeNull()
        ->and(app(TierResolver::class)->resolve($user->fresh()))->toBe('free');
});

it('does not revoke a user when another paid period is still active', function () {
    $user = User::factory()->create(['plan' => 'premium', 'tier' => 'premium']);
    $ended = Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => false,
        'current_period_end' => now()->subMinute(),
        'data_retention_starts_at' => null,
    ]);
    $active = Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => false,
        'current_period_end' => now()->addMonth(),
        'data_retention_starts_at' => null,
    ]);

    expect(app(TrialService::class)->expireCancelledSubscriptions())->toBe(1)
        ->and($ended->fresh()->status)->toBe('expired')
        ->and($active->fresh()->status)->toBe('active')
        ->and($user->fresh()->subscription->id)->toBe($active->id)
        ->and($user->fresh()->tier)->toBe('premium');
});

// ── RetentionPurgeService clears tier on full account wipe ─────────────────

it('clears users.tier when an account is purged after retention', function () {
    $this->seed(SubscriptionPlanSeeder::class);

    $user = User::factory()->create(['plan' => 'premium', 'tier' => 'premium']);

    app(RetentionPurgeService::class)->purgeUser($user);

    $fresh = $user->fresh();
    expect($fresh->plan)->toBe('free')
        ->and($fresh->tier)->toBeNull()
        ->and(app(TierResolver::class)->resolve($fresh))->toBe('free');
});

// ── PR5 review CRITICAL (third terminal path): SUBSCRIPTION_FINISHED ───────
//
// SUBSCRIPTION_FINISHED is the Revolut webhook for a fixed-term subscription
// that has completed ALL billing cycles — paid access genuinely ends now
// (Revolut never emits it mid-window; the handler sets
// data_retention_starts_at to the already-elapsed current_period_end).
// Before the fix this path set subscription.status='expired' and
// data_retention_starts_at but never touched the user — AND because it sets
// data_retention_starts_at + status='expired', the cancelled-expiry sweep
// (status='cancelled' AND whereNull('data_retention_starts_at')) and the
// trial sweep (status='trialing') BOTH permanently exclude it. So no sweep
// could ever reclaim the user → tier access leaked forever. The handler now
// revokes the user itself in the same transaction.

it('revokes tier access when a premium fixed-term subscription FINISHES via the Revolut webhook', function () {
    $store = app(TierConfigurationStore::class);

    $user = User::factory()->create(['plan' => 'premium', 'tier' => 'premium']);
    $subscription = Subscription::factory()->plan('premium')->billingCycle('monthly')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => true,
        'current_period_end' => now()->subDay(), // final cycle elapsed
        'data_retention_starts_at' => null,
        'amount' => 1499,
    ]);
    // Revolut subscription id the FINISHED payload resolves against.
    $subscription->forceFill(['revolut_subscription_id' => 'sub_finished_t2'])->save();

    // Sanity: BEFORE the webhook the customer still resolves as premium.
    expect(app(TierResolver::class)->resolve($user->fresh()))->toBe('premium');

    // Drive the REAL webhook HTTP endpoint. Only the Revolut HTTP boundary
    // (signature verification) is mocked — handleSubscriptionFinished takes
    // no Revolut API call, so nothing else needs stubbing.
    $webhookMock = Mockery::mock(RevolutService::class);
    $webhookMock->shouldReceive('verifyWebhookSignature')->andReturn(true);
    app()->instance(RevolutService::class, $webhookMock);

    $this->postJson('/api/webhooks/revolut', [
        'event' => 'SUBSCRIPTION_FINISHED',
        'id' => 'sub_finished_t2',
    ], [
        'Revolut-Signature' => 'v1=stub',
        'Revolut-Request-Timestamp' => (string) (int) (microtime(true) * 1000),
    ])->assertOk();

    $subscription->refresh();
    $fresh = $user->fresh();

    // Subscription terminated.
    expect($subscription->status)->toBe('expired')
        ->and($subscription->data_retention_starts_at)->not->toBeNull()
        // User revoked: legacy column reset AND canonical column cleared.
        ->and($fresh->plan)->toBe('free')
        ->and($fresh->tier)->toBeNull()
        ->and(app(TierResolver::class)->resolve($fresh))->toBe('free')
        // Concrete revoke proof: gate gives the Free cap of 3 for
        // savings_account, NOT premium's unlimited (null).
        ->and(app(TierGate::class)->hardLimit($fresh, 'savings_account'))->toBe(2)
        ->and($store->capFor('premium', 'savings_account'))->toBeNull()
        ->and(app(TierGate::class)->canCreate($fresh, 'savings_account', 3))->toBeFalse();
});

// SUBSCRIPTION_FINISHED is always terminal in Revolut's model (emitted only
// once every billing cycle of a fixed-term subscription has completed —
// there is no "finished but still in a paid window" case), so there is no
// negative/should-not-revoke variant for this event. A legacy-slug user
// hitting this path already has users.tier === null, so `tier => null` is a
// harmless no-op — no special-casing and no separate test needed.
