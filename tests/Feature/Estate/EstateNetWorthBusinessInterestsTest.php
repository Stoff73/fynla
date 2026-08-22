<?php

declare(strict_types=1);

use App\Models\BusinessInterest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * W-0138, second class. Business interests were absent from the same aggregation
 * as chattels, so they were missing from the estate `/m`, web and native iOS all
 * read — invisible to the `peak_earners` persona, which holds none, and squarely
 * visible to `entrepreneur`, which does.
 *
 * The share rule differs from every other asset class and that is the part worth
 * pinning: `ownership_percentage` is a SHAREHOLDING, so it applies even to an
 * individually owned record, where for property or cash "individual" means all
 * of it.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2025, 6, 15));
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);

    $this->owner = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $this->partner = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $this->stranger = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

    // Individually held, but only 60% of the company is theirs.
    BusinessInterest::factory()->create([
        'user_id' => $this->owner->id,
        'business_name' => 'Chen Consulting',
        'business_type' => 'limited_company',
        'ownership_type' => 'individual',
        'ownership_percentage' => 60,
        'current_valuation' => 500000,
    ]);

    // Shared 70/30 with the partner account.
    BusinessInterest::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => $this->partner->id,
        'business_name' => 'Riverside Lettings',
        'business_type' => 'partnership',
        'ownership_type' => 'joint',
        'ownership_percentage' => 70,
        'current_valuation' => 200000,
    ]);

    // A third account's company. Must reach neither of the two above.
    BusinessInterest::factory()->create([
        'user_id' => $this->stranger->id,
        'business_name' => 'Unrelated Holdings',
        'business_type' => 'limited_company',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_valuation' => 777000,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return array{composition: array<int, array<string, mixed>>, totals: array<string, mixed>}
 */
function estateNetWorthBusinessFor(User $user): array
{
    Sanctum::actingAs($user);

    $response = test()->getJson('/api/estate/net-worth');
    $response->assertOk();

    $netWorth = $response->json('data.net_worth');

    return [
        'composition' => $netWorth['asset_composition'],
        'totals' => $netWorth,
    ];
}

describe('GET /api/estate/net-worth business interests', function () {
    it('values a shareholding at the shareholding, not at the whole company', function () {
        $result = estateNetWorthBusinessFor($this->owner);

        $business = collect($result['composition'])->firstWhere('type', 'business');

        // 60% of £500,000 individually held, plus 70% of the £200,000 shared record.
        expect($business)->not->toBeNull()
            ->and((float) $business['value'])->toBe(440000.0)
            ->and($business['count'])->toBe(2)
            ->and((float) $result['totals']['total_assets'])->toBe(440000.0);
    });

    it('gives the joint owner the complement, and none of the other holding', function () {
        $result = estateNetWorthBusinessFor($this->partner);

        $business = collect($result['composition'])->firstWhere('type', 'business');

        // 30% of the shared record only.
        expect($business)->not->toBeNull()
            ->and((float) $business['value'])->toBe(60000.0)
            ->and($business['count'])->toBe(1)
            ->and((float) $result['totals']['total_assets'])->toBe(60000.0);
    });

    it('never leaks a third account\'s company onto either surface', function () {
        foreach ([$this->owner, $this->partner] as $user) {
            $result = estateNetWorthBusinessFor($user);

            expect((float) $result['totals']['total_assets'])->toBeLessThan(777000.0)
                ->and(json_encode($result['composition']))->not->toContain('777000');
        }
    });
});
