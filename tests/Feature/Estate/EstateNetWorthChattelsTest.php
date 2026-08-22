<?php

declare(strict_types=1);

use App\Models\Chattel;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * W-0138 — the /m estate screen reads GET /api/estate/net-worth and renders its
 * `asset_composition` rows. Chattels were absent from that composition, so a user
 * with £132,250 of recorded valuables was shown an estate that excluded them.
 *
 * These tests pin the harm, not the mechanism: the recorded chattels reach the
 * endpoint, at the owner's share, and a chattel belonging to someone else never
 * reaches a surface it does not belong on.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2025, 6, 15));
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);

    $this->owner = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $this->spouse = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $this->stranger = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

    // Individually owned by the primary account — wholly theirs.
    Chattel::factory()->create([
        'user_id' => $this->owner->id,
        'chattel_type' => 'vehicle',
        'name' => 'Jaguar',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 85000,
    ]);

    // Shared 60/40 — one record, the primary owner's share is the stored percentage.
    Chattel::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => $this->spouse->id,
        'chattel_type' => 'art',
        'name' => 'Landscape painting',
        'ownership_type' => 'joint',
        'ownership_percentage' => 60,
        'current_value' => 50000,
    ]);

    // Belongs to a third account entirely. Must reach neither of the two above.
    Chattel::factory()->create([
        'user_id' => $this->stranger->id,
        'chattel_type' => 'collectible',
        'name' => 'Stamp collection',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 999000,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return array{composition: array<int, array<string, mixed>>, totals: array<string, mixed>}
 */
function estateNetWorthFor(User $user): array
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

describe('GET /api/estate/net-worth chattels', function () {
    it('includes the primary owner\'s chattels at their share', function () {
        $result = estateNetWorthFor($this->owner);

        $chattels = collect($result['composition'])->firstWhere('type', 'chattel');

        // £85,000 individually owned + 60% of the £50,000 shared record.
        expect($chattels)->not->toBeNull()
            ->and((float) $chattels['value'])->toBe(115000.0)
            ->and($chattels['count'])->toBe(2)
            ->and((float) $result['totals']['total_assets'])->toBe(115000.0);
    });

    it('gives the joint owner the complement, and nothing that is not theirs', function () {
        $result = estateNetWorthFor($this->spouse);

        $chattels = collect($result['composition'])->firstWhere('type', 'chattel');

        // 40% of the shared record only — the £85,000 belongs to the other account.
        expect($chattels)->not->toBeNull()
            ->and((float) $chattels['value'])->toBe(20000.0)
            ->and($chattels['count'])->toBe(1)
            ->and((float) $result['totals']['total_assets'])->toBe(20000.0);
    });

    it('never leaks a third account\'s chattel onto either surface', function () {
        foreach ([$this->owner, $this->spouse] as $user) {
            $result = estateNetWorthFor($user);

            expect((float) $result['totals']['total_assets'])->toBeLessThan(999000.0)
                ->and(json_encode($result['composition']))->not->toContain('999000');
        }
    });
});
