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
// ended → plan reset to free" transitions (TrialService::expireTrials,
// expireCancelledSubscriptions, RetentionPurgeService::purgeUser) must
// also clear users.tier or a paying customer who cancels keeps tier access
// forever after their access period expires — an entitlement/revenue leak.
// tier => null means "resolve via TierResolver" → resolves to 'free'.

it('revokes tier access when a cancelled tier2 subscription expires past its period', function () {
    $store = app(TierConfigurationStore::class);

    // tier2 paying customer who has cancelled; their access period has now passed.
    $user = User::factory()->create(['plan' => 'tier2', 'tier' => 'tier2']);
    Subscription::factory()->plan('tier2')->billingCycle('monthly')->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'current_period_end' => now()->subDay(), // period already over
        'data_retention_starts_at' => null,      // not yet swept
        'amount' => 1499,
    ]);

    // Sanity: BEFORE the sweep the customer still resolves as tier2.
    expect(app(TierResolver::class)->resolve($user->fresh()))->toBe('tier2');

    $count = app(TrialService::class)->expireCancelledSubscriptions();

    expect($count)->toBe(1);

    $fresh = $user->fresh();
    // Legacy billing-compat column reset AND canonical gating column cleared.
    expect($fresh->plan)->toBe('free')
        ->and($fresh->tier)->toBeNull()
        // Access correctly REVOKED to Free — not still tier2.
        ->and(app(TierResolver::class)->resolve($fresh))->toBe('free')
        // The concrete proof: the gate now gives the Free cap of 3 for
        // savings_account, NOT tier2's unlimited (null).
        ->and(app(TierGate::class)->hardLimit($fresh, 'savings_account'))->toBe(3)
        ->and($store->capFor('tier2', 'savings_account'))->toBeNull()
        // A revoked user is now blocked beyond the Free cap.
        ->and(app(TierGate::class)->canCreate($fresh, 'savings_account', 3))->toBeFalse();
});

it('revokes tier access when a tier3 trial expires', function () {
    $user = User::factory()->create(['plan' => 'tier3', 'tier' => 'tier3']);
    Subscription::factory()->plan('tier3')->billingCycle('monthly')->create([
        'user_id' => $user->id,
        'status' => 'trialing',
        'trial_ends_at' => now()->subHour(), // trial already over
        'amount' => 2999,
    ]);

    expect(app(TierResolver::class)->resolve($user->fresh()))->toBe('tier3');

    $count = app(TrialService::class)->expireTrials();

    expect($count)->toBe(1);

    $fresh = $user->fresh();
    expect($fresh->plan)->toBe('free')
        ->and($fresh->tier)->toBeNull()
        ->and(app(TierResolver::class)->resolve($fresh))->toBe('free')
        ->and(app(TierGate::class)->hardLimit($fresh, 'savings_account'))->toBe(3);
});

it('does NOT revoke an active tier2 subscriber during the cancelled-expiry sweep', function () {
    // Active tier2 subscriber — must be untouched by the terminal sweep.
    $active = User::factory()->create(['plan' => 'tier2', 'tier' => 'tier2']);
    Subscription::factory()->plan('tier2')->billingCycle('monthly')->create([
        'user_id' => $active->id,
        'status' => 'active',
        'current_period_end' => now()->addMonth(),
        'amount' => 1499,
    ]);

    // A cancelled-but-NOT-yet-expired tier2 subscriber — access preserved
    // until current_period_end; the sweep must NOT touch them either.
    $cancelledNotExpired = User::factory()->create(['plan' => 'tier2', 'tier' => 'tier2']);
    Subscription::factory()->plan('tier2')->billingCycle('monthly')->create([
        'user_id' => $cancelledNotExpired->id,
        'status' => 'cancelled',
        'current_period_end' => now()->addWeek(), // still in access window
        'data_retention_starts_at' => null,
        'amount' => 1499,
    ]);

    app(TrialService::class)->expireCancelledSubscriptions();

    // Both keep tier2 — the reset only hits genuinely-ended subscriptions.
    expect($active->fresh()->tier)->toBe('tier2')
        ->and(app(TierResolver::class)->resolve($active->fresh()))->toBe('tier2')
        ->and($cancelledNotExpired->fresh()->tier)->toBe('tier2')
        ->and(app(TierResolver::class)->resolve($cancelledNotExpired->fresh()))->toBe('tier2');
});

it('does NOT revoke an active tier3 trialing subscriber during the trial-expiry sweep', function () {
    $activeTrial = User::factory()->create(['plan' => 'tier3', 'tier' => 'tier3']);
    Subscription::factory()->plan('tier3')->billingCycle('monthly')->create([
        'user_id' => $activeTrial->id,
        'status' => 'trialing',
        'trial_ends_at' => now()->addDays(5), // trial still running
        'amount' => 2999,
    ]);

    app(TrialService::class)->expireTrials();

    expect($activeTrial->fresh()->tier)->toBe('tier3')
        ->and(app(TierResolver::class)->resolve($activeTrial->fresh()))->toBe('tier3');
});

// ── Upgrade-path coverage the reviewer noted (tier1 → tier2) ───────────────
//
// Upgrade goes upgradeSubscription → confirmPayment (shared code path with a
// fresh purchase). This exercises the upgrade scenario explicitly: a tier1
// subscriber upgrades to tier2 and must end up resolving as tier2.

it('sets users.tier to tier2 when a tier1 subscriber upgrades via upgradeSubscription then confirms', function () {
    $store = app(TierConfigurationStore::class);
    $user = User::factory()->create([
        'plan' => 'tier1',
        'tier' => 'tier1',
        'revolut_customer_id' => 'cust_upgrade',
    ]);

    // Active tier1 subscription (precondition for upgradeSubscription).
    Subscription::factory()->plan('tier1')->billingCycle('monthly')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
        'amount' => $store->forTier('tier1')->price_monthly_pence,
    ]);

    $orderId = '55555555-5555-5555-5555-555555555555';
    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('createOrder')->once()->andReturn([
        'id' => $orderId, 'token' => 'tok_upgrade', 'state' => 'pending',
        'created_at' => now()->toIso8601String(),
    ]);
    $revolut->shouldReceive('getOrder')->with($orderId)->andReturn([
        'id' => $orderId, 'state' => 'completed', 'capture_mode' => 'automatic',
        'amount' => $store->forTier('tier2')->price_monthly_pence, 'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $revolut);

    // Step 1: upgrade tier1 → tier2.
    $this->actingAs($user)->postJson('/api/payment/upgrade', [
        'plan' => 'tier2',
    ])->assertOk();

    // Step 2: confirm the upgrade payment.
    $this->actingAs($user)->postJson('/api/payment/confirm', [
        'order_id' => $orderId,
    ])->assertOk();

    $fresh = $user->fresh();
    expect($fresh->tier)->toBe('tier2')
        ->and($fresh->plan)->toBe('tier2')
        ->and(app(TierResolver::class)->resolve($fresh))->toBe('tier2')
        // tier2 gives unlimited savings_account (null), not the Free cap of 3.
        ->and(app(TierGate::class)->hardLimit($fresh, 'savings_account'))->toBeNull()
        ->and(app(TierGate::class)->canCreate($fresh, 'savings_account', 99))->toBeTrue();
});

// ── RetentionPurgeService clears tier on full account wipe ─────────────────

it('clears users.tier when an account is purged after retention', function () {
    $this->seed(SubscriptionPlanSeeder::class);

    $user = User::factory()->create(['plan' => 'tier2', 'tier' => 'tier2']);

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

it('revokes tier access when a tier2 fixed-term subscription FINISHES via the Revolut webhook', function () {
    $store = app(TierConfigurationStore::class);

    $user = User::factory()->create(['plan' => 'tier2', 'tier' => 'tier2']);
    $subscription = Subscription::factory()->plan('tier2')->billingCycle('monthly')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'current_period_end' => now()->subDay(), // final cycle elapsed
        'data_retention_starts_at' => null,
        'amount' => 1499,
    ]);
    // Revolut subscription id the FINISHED payload resolves against.
    $subscription->forceFill(['revolut_subscription_id' => 'sub_finished_t2'])->save();

    // Sanity: BEFORE the webhook the customer still resolves as tier2.
    expect(app(TierResolver::class)->resolve($user->fresh()))->toBe('tier2');

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
        // savings_account, NOT tier2's unlimited (null).
        ->and(app(TierGate::class)->hardLimit($fresh, 'savings_account'))->toBe(3)
        ->and($store->capFor('tier2', 'savings_account'))->toBeNull()
        ->and(app(TierGate::class)->canCreate($fresh, 'savings_account', 3))->toBeFalse();
});

// SUBSCRIPTION_FINISHED is always terminal in Revolut's model (emitted only
// once every billing cycle of a fixed-term subscription has completed —
// there is no "finished but still in a paid window" case), so there is no
// negative/should-not-revoke variant for this event. A legacy-slug user
// hitting this path already has users.tier === null, so `tier => null` is a
// harmless no-op — no special-casing and no separate test needed.
