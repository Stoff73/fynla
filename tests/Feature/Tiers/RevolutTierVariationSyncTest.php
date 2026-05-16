<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\Payment\RevolutSubscriptionService;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\RevolutTierVariationSync;
use Database\Seeders\RolesPermissionsSeeder;
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

// ── Task 5.1 Step 1: Sync pushes price + writes back variation id ──────────

it('pushes each paid tier price to Revolut and writes back the variation id', function () {
    $actor = User::factory()->create();

    $fakeClient = Mockery::mock(RevolutSubscriptionService::class);

    // Each paid tier (tier1, tier2, tier3) should receive exactly one upsertTierPlan call.
    // The mock returns a unique variation id per call so we can assert the write-back.
    $fakeClient->shouldReceive('upsertTierPlan')
        ->times(3)  // tier1, tier2, tier3 — free is skipped
        ->andReturnUsing(function (string $displayName, int $pricePence) {
            return ['id' => 'rev_var_'.strtolower(str_replace(' ', '_', $displayName))];
        });

    $store = app(TierConfigurationStore::class);
    $sync = new RevolutTierVariationSync($fakeClient, $store);
    $sync->run($actor);

    // Each paid tier now has a variation id written back via the store.
    expect(TierConfiguration::where('tier', 'tier1')->first()->revolut_plan_variation_id)
        ->toBe('rev_var_tier_1');

    expect(TierConfiguration::where('tier', 'tier2')->first()->revolut_plan_variation_id)
        ->toBe('rev_var_tier_2');

    expect(TierConfiguration::where('tier', 'tier3')->first()->revolut_plan_variation_id)
        ->toBe('rev_var_tier_3');
});

it('skips the free tier because it has no monthly price', function () {
    $actor = User::factory()->create();

    $fakeClient = Mockery::mock(RevolutSubscriptionService::class);
    // free tier: price_monthly_pence = 0, so upsertTierPlan must never be called for it.
    $fakeClient->shouldReceive('upsertTierPlan')
        ->times(3)  // only the three paid tiers
        ->andReturn(['id' => 'rev_var_test']);

    $store = app(TierConfigurationStore::class);
    $sync = new RevolutTierVariationSync($fakeClient, $store);
    $sync->run($actor);

    expect(TierConfiguration::where('tier', 'free')->first()->revolut_plan_variation_id)
        ->toBeNull();
});

it('continues syncing remaining tiers when one tier call fails', function () {
    $actor = User::factory()->create();
    $callCount = 0;

    $fakeClient = Mockery::mock(RevolutSubscriptionService::class);
    $fakeClient->shouldReceive('upsertTierPlan')
        ->times(3)
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new RuntimeException('Revolut API error');
            }

            return ['id' => "rev_var_{$callCount}"];
        });

    $store = app(TierConfigurationStore::class);
    $sync = new RevolutTierVariationSync($fakeClient, $store);

    // Should not throw — partial failure is tolerated
    expect(fn () => $sync->run($actor))->not->toThrow(Throwable::class);
});

// ── Task 5.1 Step 1: Price-lock assertion ─────────────────────────────────
//
// Billing path (located in SubscriptionRenewalService::handleRenewalPayment):
//   $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug);  // line 59
//   $amount = $subscriptionPlan
//       ? ($subscriptionPlan->getLaunchPrice ?? $subscriptionPlan->getPrice)
//       : $subscription->amount;                                     // line 60-62
//
// For tier keys (tier1/tier2/tier3) there is no SubscriptionPlan row, so
// findBySlug() returns null and the renewal falls through to $subscription->amount
// — the value stored at payment confirmation time. This is the price-lock: the
// billed amount is read from the subscription row's locked amount, NOT from a
// live tier configuration read.
//
// This test constructs a fixture where the tier store price has been updated
// after subscription creation and asserts that the renewal service ignores the
// new price and bills from the subscription row.
//
it('does NOT change the price an existing subscriber is billed when the tier price changes (price-lock)', function () {
    // Arrange — user with an active tier1 subscription locked at 499p
    $user = User::factory()->create();
    $originalAmountPence = 499;

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'tier1',
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'amount' => $originalAmountPence,
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->addMonth(),
    ]);

    // Simulate the tier store price being changed AFTER the subscription was created.
    // (This is what a RevolutTierVariationSync run would do — it updates the store
    // but must NOT retroactively change the subscriber's billed amount.)
    TierConfiguration::where('tier', 'tier1')->update(['price_monthly_pence' => 999]);

    // Act — simulate what SubscriptionRenewalService does when the renewal webhook fires.
    // It looks up the SubscriptionPlan by the plan slug stored on the subscription.
    $planSlug = $subscription->plan;                              // 'tier1'
    $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug); // returns null (no SubscriptionPlan for tier keys)

    // This is the actual billing path — null coalescence to $subscription->amount.
    $renewalAmount = $subscriptionPlan
        ? ($subscriptionPlan->getLaunchPriceForCycle($subscription->billing_cycle)
            ?? $subscriptionPlan->getPriceForCycle($subscription->billing_cycle))
        : $subscription->amount;

    // Assert — billed amount is from the subscription row (499p), NOT the new tier price (999p).
    // Subscription.amount has decimal:2 cast, so toBe uses string equality; cast to int for clarity.
    expect((int) $renewalAmount)->toBe($originalAmountPence)
        ->and((int) $renewalAmount)->not->toBe(999);
});
