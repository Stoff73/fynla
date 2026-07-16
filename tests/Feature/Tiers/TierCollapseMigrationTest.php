<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TierConfiguration;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

function tierCollapseMigration(): Migration
{
    return require database_path('migrations/2026_07_15_000000_collapse_tier_identity_to_free_premium.php');
}

function restoreCanonicalTierSchema(Migration $migration): void
{
    Invoice::query()->delete();
    Payment::query()->delete();
    Subscription::query()->delete();
    User::query()->where('email', 'like', 'tier-collapse-%')->forceDelete();

    $migration->down();
    TierConfiguration::query()->delete();
    TierConfiguration::create(array_replace(tierConfigFixture('free'), ['display_name' => 'Free']));
    TierConfiguration::create(array_replace(tierConfigFixture('tier2'), [
        'display_name' => 'Tier 2',
        'price_monthly_pence' => 1499,
        'price_annual_pence' => 14990,
    ]));
    $migration->up();
    TierConfiguration::query()->delete();
}

it('blocks active entitlement then maps historical identity without rewriting financial history', function () {
    $migration = tierCollapseMigration();
    $migration->down();

    try {
        TierConfiguration::query()->delete();
        foreach (['free', 'tier1', 'tier2', 'tier3'] as $tier) {
            TierConfiguration::create(array_replace(tierConfigFixture($tier), [
                'display_name' => $tier === 'free' ? 'Free' : 'Tier '.substr($tier, -1),
                'price_monthly_pence' => $tier === 'tier2' ? 1499 : 0,
                'price_annual_pence' => $tier === 'tier2' ? 14990 : 0,
            ]));
        }

        $activeUser = User::factory()->create([
            'email' => 'tier-collapse-active@example.com',
            'plan' => 'tier1',
            'tier' => 'tier1',
        ]);
        $activeSubscription = Subscription::factory()->plan('tier1')->create([
            'user_id' => $activeUser->id,
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
        ]);

        expect(fn () => $migration->up())
            ->toThrow(RuntimeException::class, 'Tier collapse blocked');

        $activeSubscription->delete();
        $activeUser->forceDelete();

        $historicalUser = User::factory()->create([
            'email' => 'tier-collapse-history@example.com',
            'plan' => 'tier3',
            'tier' => 'tier2',
        ]);
        $historicalSubscription = Subscription::factory()->expired()->plan('tier1')->create([
            'user_id' => $historicalUser->id,
            'amount' => 1234,
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $historicalUser->id,
            'subscription_id' => $historicalSubscription->id,
            'plan_slug' => 'tier3',
            'amount' => 1234,
            'status' => 'completed',
        ]);
        $invoice = Invoice::factory()->create([
            'user_id' => $historicalUser->id,
            'subscription_id' => $historicalSubscription->id,
            'payment_id' => $payment->id,
            'subtotal_amount' => 1234,
            'total_amount' => 1234,
        ]);

        $migration->up();

        expect($historicalUser->fresh()->tier)->toBe('premium')
            ->and($historicalUser->fresh()->plan)->toBe('premium')
            ->and($historicalSubscription->fresh()->plan)->toBe('premium')
            ->and((int) $historicalSubscription->fresh()->amount)->toBe(1234)
            ->and($payment->fresh()->plan_slug)->toBe('tier3')
            ->and((int) $payment->fresh()->amount)->toBe(1234)
            ->and((int) $invoice->fresh()->total_amount)->toBe(1234)
            ->and(TierConfiguration::pluck('tier')->sort()->values()->all())->toBe(['free', 'premium'])
            ->and(TierConfiguration::where('tier', 'premium')->value('price_monthly_pence'))->toBe(699)
            ->and(TierConfiguration::where('tier', 'premium')->value('price_annual_pence'))->toBe(5999);
    } finally {
        restoreCanonicalTierSchema($migration);
    }
});

it('writes the approved Free and Premium contract without relying on a retired bridge row', function () {
    $migration = tierCollapseMigration();
    $migration->down();

    try {
        TierConfiguration::query()->delete();
        TierConfiguration::create(array_replace(tierConfigFixture('free'), ['display_name' => 'Free']));
        TierConfiguration::create(array_replace(tierConfigFixture('tier1'), ['display_name' => 'Tier 1']));

        $migration->up();

        expect(TierConfiguration::pluck('tier')->sort()->values()->all())->toBe(['free', 'premium'])
            ->and(TierConfiguration::where('tier', 'premium')->value('price_monthly_pence'))->toBe(699)
            ->and(TierConfiguration::where('tier', 'premium')->value('price_annual_pence'))->toBe(5999);
    } finally {
        restoreCanonicalTierSchema($migration);
    }
});
