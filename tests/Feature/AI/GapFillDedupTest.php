<?php

declare(strict_types=1);

use App\Models\CriticalIllnessPolicy;
use App\Models\DCPension;
use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\AssetCaptureEntityExtractor;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Sprint 0.11.5 — Gap-fill DB dedup (INV-2.9.5).
 *
 * Without DB dedup, a user retrying an identical multi-entity message
 * (e.g. after a network glitch) would have the deterministic
 * AssetCaptureEntityExtractor re-emit the same gap-fill tool calls,
 * which then re-persist duplicate rows in the target module. The
 * existing in-message dedup only protects against the LLM and
 * extractor agreeing within a single turn — it cannot see persisted
 * rows from earlier turns.
 *
 * Fix: findMissing now optionally accepts a User and queries the target
 * module table for rows created within the last 24h with a matching
 * identity key. Any extracted entity that already has such a row is
 * filtered out before the gap-fill emission.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->extractor = new AssetCaptureEntityExtractor;
    $this->user = User::factory()->create();
});

describe('Gap-fill DB dedup — protection (S0.11.5 / INV-2.9.5)', function () {
    it('suppresses a gap-fill when a matching life policy exists from the last 24h', function () {
        LifeInsurancePolicy::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Aviva',
            'policy_type' => 'level_term',
            'created_at' => now()->subHours(2),
        ]);

        $extracted = [
            ['policy_type' => 'term', 'provider' => 'Aviva', 'sum_assured' => 300000.0],
        ];

        $missing = $this->extractor->findMissing('protection', $extracted, [], $this->user);

        expect($missing)->toBe([]);
    });

    it('still emits the gap-fill when the matching policy is older than 24h', function () {
        LifeInsurancePolicy::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Aviva',
            'policy_type' => 'level_term',
            'created_at' => now()->subHours(25),
        ]);

        $extracted = [
            ['policy_type' => 'term', 'provider' => 'Aviva', 'sum_assured' => 300000.0],
        ];

        $missing = $this->extractor->findMissing('protection', $extracted, [], $this->user);

        expect($missing)->toHaveCount(1);
    });

    it('does not cross-pollinate between users', function () {
        $otherUser = User::factory()->create();
        LifeInsurancePolicy::factory()->create([
            'user_id' => $otherUser->id,
            'provider' => 'Aviva',
            'policy_type' => 'level_term',
            'created_at' => now()->subHours(2),
        ]);

        $extracted = [
            ['policy_type' => 'term', 'provider' => 'Aviva', 'sum_assured' => 300000.0],
        ];

        // User A has no matching row → gap-fill should still emit.
        $missing = $this->extractor->findMissing('protection', $extracted, [], $this->user);

        expect($missing)->toHaveCount(1);
    });

    it('suppresses critical illness gap-fill when a matching CI policy exists', function () {
        // CriticalIllnessPolicy.policy_type uses the DB enum 'standalone'
        // (no `_ci` suffix). The dedup pass synthesises the group-side
        // identity key from the table itself, not the column value.
        CriticalIllnessPolicy::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Vitality',
            'policy_type' => 'standalone',
            'created_at' => now()->subMinutes(30),
        ]);

        $extracted = [
            ['policy_type' => 'standalone_ci', 'provider' => 'Vitality', 'sum_assured' => 100000.0],
        ];

        $missing = $this->extractor->findMissing('protection', $extracted, [], $this->user);

        expect($missing)->toBe([]);
    });

    it('partial dedup: keeps unmatched entities, drops matched ones', function () {
        LifeInsurancePolicy::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Aviva',
            'policy_type' => 'level_term',
            'created_at' => now()->subHours(1),
        ]);

        $extracted = [
            ['policy_type' => 'term', 'provider' => 'Aviva', 'sum_assured' => 300000.0],
            ['policy_type' => 'standalone_ci', 'provider' => 'Vitality', 'sum_assured' => 100000.0],
        ];

        $missing = $this->extractor->findMissing('protection', $extracted, [], $this->user);

        expect($missing)->toHaveCount(1)
            ->and($missing[0]['policy_type'])->toBe('standalone_ci')
            ->and($missing[0]['provider'])->toBe('Vitality');
    });
});

describe('Gap-fill DB dedup — savings (S0.11.5)', function () {
    it('suppresses a savings gap-fill when a matching account exists', function () {
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'institution' => 'Monzo',
            'account_name' => 'Monzo Easy Access Saver',
            'is_isa' => false,
            'created_at' => now()->subHours(3),
        ]);

        $extracted = [
            [
                'account_name' => 'Monzo Easy Access Saver',
                'institution' => 'Monzo',
                'account_type' => 'easy_access',
                'current_balance' => 5000.0,
            ],
        ];

        $missing = $this->extractor->findMissing('savings', $extracted, [], $this->user);

        expect($missing)->toBe([]);
    });

    it('does not suppress when the existing row is an ISA but the extracted is not', function () {
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'institution' => 'Monzo',
            'account_name' => 'Monzo Cash ISA',
            'is_isa' => true,
            'created_at' => now()->subHours(3),
        ]);

        $extracted = [
            [
                'account_name' => 'Monzo Easy Access Saver',
                'institution' => 'Monzo',
                'account_type' => 'easy_access',
                'current_balance' => 5000.0,
                // no is_isa flag → standard saver
            ],
        ];

        $missing = $this->extractor->findMissing('savings', $extracted, [], $this->user);

        expect($missing)->toHaveCount(1);
    });
});

describe('Gap-fill DB dedup — retirement (S0.11.5)', function () {
    it('suppresses a DC pension gap-fill when a matching pension exists', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Aviva',
            'scheme_name' => 'Aviva Workplace Pension',
            'created_at' => now()->subHours(4),
        ]);

        $extracted = [
            [
                'pension_category' => 'dc',
                'scheme_name' => 'Aviva Workplace Pension',
                'scheme_type' => 'workplace',
                'provider' => 'Aviva',
                'current_fund_value' => 50000.0,
            ],
        ];

        $missing = $this->extractor->findMissing('retirement', $extracted, [], $this->user);

        expect($missing)->toBe([]);
    });
});

describe('Gap-fill DB dedup — investment (S0.11.5)', function () {
    it('suppresses an investment gap-fill when a matching account exists', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Hargreaves Lansdown',
            'account_name' => 'Hargreaves Lansdown Stocks & Shares Individual Savings Account',
            'account_type' => 'stocks_shares_isa',
            'created_at' => now()->subHours(2),
        ]);

        $extracted = [
            [
                'account_name' => 'Hargreaves Lansdown Stocks & Shares Individual Savings Account',
                'account_type' => 'stocks_shares_isa',
                'provider' => 'Hargreaves Lansdown',
                'current_value' => 30000.0,
            ],
        ];

        $missing = $this->extractor->findMissing('investment', $extracted, [], $this->user);

        expect($missing)->toBe([]);
    });
});

describe('Gap-fill DB dedup — property (S0.16b BS-17 prep)', function () {
    it('suppresses a property gap-fill when a matching buy-to-let exists', function () {
        Property::factory()->create([
            'user_id' => $this->user->id,
            'property_type' => 'buy_to_let',
            'postcode' => 'SW1A 1AA',
            'created_at' => now()->subHours(2),
        ]);

        $extracted = [
            ['property_type' => 'buy_to_let', 'postcode' => 'SW1A1AA', 'current_value' => 350000.0],
        ];

        $missing = $this->extractor->findMissing('property', $extracted, [], $this->user);

        expect($missing)->toBe([]);
    });

    it('does not suppress when postcodes differ', function () {
        Property::factory()->create([
            'user_id' => $this->user->id,
            'property_type' => 'buy_to_let',
            'postcode' => 'SW1A 1AA',
            'created_at' => now()->subHours(2),
        ]);

        $extracted = [
            ['property_type' => 'buy_to_let', 'postcode' => 'M1 1AA', 'current_value' => 250000.0],
        ];

        $missing = $this->extractor->findMissing('property', $extracted, [], $this->user);

        expect($missing)->toHaveCount(1);
    });

    it('still emits the gap-fill when the matching property is older than 24h', function () {
        Property::factory()->create([
            'user_id' => $this->user->id,
            'property_type' => 'main_residence',
            'postcode' => 'SW1A 1AA',
            'created_at' => now()->subHours(25),
        ]);

        $extracted = [
            ['property_type' => 'main_residence', 'postcode' => 'SW1A1AA', 'current_value' => 600000.0],
        ];

        $missing = $this->extractor->findMissing('property', $extracted, [], $this->user);

        expect($missing)->toHaveCount(1);
    });
});

describe('Gap-fill DB dedup — goal (S0.16b BS-17 prep)', function () {
    it('suppresses a goal gap-fill when a matching goal exists', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Emergency Fund',
            'created_at' => now()->subHours(2),
        ]);

        $extracted = [
            ['goal_name' => 'Emergency Fund', 'target_amount' => 10000.0],
        ];

        $missing = $this->extractor->findMissing('goal', $extracted, [], $this->user);

        expect($missing)->toBe([]);
    });

    it('matches goal names case- and punctuation-insensitively', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Emergency Fund',
            'created_at' => now()->subHours(2),
        ]);

        $extracted = [
            ['goal_name' => 'emergency-fund', 'target_amount' => 10000.0],
        ];

        $missing = $this->extractor->findMissing('goal', $extracted, [], $this->user);

        expect($missing)->toBe([]);
    });

    it('does not suppress when goal names differ', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Emergency Fund',
            'created_at' => now()->subHours(2),
        ]);

        $extracted = [
            ['goal_name' => 'House Deposit', 'target_amount' => 50000.0],
        ];

        $missing = $this->extractor->findMissing('goal', $extracted, [], $this->user);

        expect($missing)->toHaveCount(1);
    });
});

describe('Gap-fill DB dedup — backward compatibility (S0.11.5)', function () {
    it('preserves existing behaviour when no User is passed', function () {
        // Existing call sites used to omit the User arg. The unit-test
        // suite still does. With no User, dedup against the DB is
        // skipped and only in-message dedup applies.
        LifeInsurancePolicy::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Aviva',
            'policy_type' => 'level_term',
            'created_at' => now()->subHours(2),
        ]);

        $extracted = [
            ['policy_type' => 'term', 'provider' => 'Aviva', 'sum_assured' => 300000.0],
        ];

        // No User → DB dedup not consulted → existing rows do not suppress.
        $missing = $this->extractor->findMissing('protection', $extracted, []);

        expect($missing)->toHaveCount(1);
    });
});
