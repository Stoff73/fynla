<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/**
 * W-0399 — the endpoint half.
 *
 * `IHTCalculationService::determineIHTRate()` separated the pooled section 23(1)
 * exemption from the survivor-only Schedule 1A rate-test amount, and then the
 * rate-test figure reached NO result array, NO controller and NO screen. It was
 * computed and discarded, so `IHTPlanning.vue` had one charitable figure to
 * render and two to explain.
 *
 * This asserts the distinction survives all the way to the payload the card
 * reads — the journey home, which is the half no unit test on the service can
 * see (`app/Http/CLAUDE.md`, axis 7).
 *
 * ASYMMETRIC: £30,000 and £5,000, so the pooled £35,000 and the tested £30,000
 * are not multiples of one another.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

function coupleWithDifferentLegacies(): array
{
    $firstToDie = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'marital_status' => 'married',
        'date_of_birth' => '1950-02-11',
        'gender' => 'male',
    ]);
    $survivor = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'marital_status' => 'married',
        'date_of_birth' => '1964-09-30',
        'gender' => 'female',
        'spouse_id' => $firstToDie->id,
    ]);
    $firstToDie->update(['spouse_id' => $survivor->id]);

    foreach ([[$firstToDie, 900_000], [$survivor, 400_000]] as [$user, $balance]) {
        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => $balance,
        ]);
    }

    foreach ([[$survivor, 'British Heart Foundation', 30000], [$firstToDie, 'Cancer Research UK', 5000]] as [$user, $charity, $amount]) {
        $will = Will::create(['user_id' => $user->id, 'has_will' => true]);
        Bequest::create([
            'will_id' => $will->id,
            'user_id' => $user->id,
            'beneficiary_name' => $charity,
            'beneficiary_type' => 'charity',
            'bequest_type' => 'specific_amount',
            'specific_amount' => $amount,
            'priority_order' => 1,
        ]);
    }

    return [$survivor, $firstToDie];
}

describe('POST /api/estate/calculate-iht publishes both charitable figures', function () {
    it('carries the rate-test amount onto the summary the card reads', function () {
        [$survivor] = coupleWithDifferentLegacies();

        $summary = $this->actingAs($survivor)
            ->postJson('/api/estate/calculate-iht')
            ->assertOk()
            ->json('iht_summary.current');

        expect((float) $summary['charitable_deduction'])->toBe(35000.0)
            ->and((float) $summary['charitable_rate_test_amount'])->toBe(30000.0)
            ->and($summary['charitable_deduction'])->not->toBe($summary['charitable_rate_test_amount']);
    });

    it('publishes the same pair to both partners', function () {
        [$survivor, $firstToDie] = coupleWithDifferentLegacies();

        foreach ([$survivor, $firstToDie] as $user) {
            $summary = $this->actingAs($user)
                ->postJson('/api/estate/calculate-iht')
                ->assertOk()
                ->json('iht_summary.current');

            expect((float) $summary['charitable_deduction'])->toBe(35000.0)
                ->and((float) $summary['charitable_rate_test_amount'])->toBe(30000.0);
        }
    });

    it('spells out Inheritance Tax in the rate message it serves', function () {
        [$survivor] = coupleWithDifferentLegacies();

        $message = $this->actingAs($survivor)
            ->postJson('/api/estate/calculate-iht')
            ->assertOk()
            ->json('iht_summary.current.iht_rate_message');

        expect($message)->toContain('Inheritance Tax rate')
            ->and($message)->not->toContain('IHT');
    });
});
