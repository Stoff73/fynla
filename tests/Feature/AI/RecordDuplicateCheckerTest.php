<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\RecordDuplicateChecker;
use App\Services\Onboarding\AssetCaptureEntityExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Sprint 0.16b — RecordDuplicateChecker BS-17 unblocker (session 93).
 *
 * Mirrors the session-91 BS-19 fix pattern: alreadyExists delegates to
 * AssetCaptureEntityExtractor::findMissing(user) so the routing gate
 * suppresses the inline-capture handoff when EVERY entity in the user's
 * message is already persisted within the 24h dedup window.
 *
 * Originally the checker only covered protection_policy. This test pins
 * the extension to savings_account, investment_account, pension,
 * property, and goal — the five remaining entity_types the
 * WriteIntentClassifier emits, which together unblock BS-17 (multi-entity
 * persist) once the full duplicate-suppression contract holds.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->checker = new RecordDuplicateChecker(new AssetCaptureEntityExtractor);
    $this->user = User::factory()->create();
});

function intentFor(string $entityType): array
{
    return [
        'entity_type' => $entityType,
        'matched_verb' => 'i have',
        'matched_entity_keyword' => $entityType,
        'fields_needed' => [],
        'reason' => 'test',
    ];
}

describe('alreadyExists — protection_policy', function () {
    it('returns true when the matching policy exists in the 24h window', function () {
        LifeInsurancePolicy::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Aviva',
            'policy_type' => 'level_term',
            'created_at' => now()->subHours(2),
        ]);

        $message = 'I have Aviva life insurance £300k';

        expect($this->checker->alreadyExists($this->user, intentFor('protection_policy'), $message))
            ->toBeTrue();
    });

    it('returns false when no matching policy exists', function () {
        $message = 'I have Aviva life insurance £300k';

        expect($this->checker->alreadyExists($this->user, intentFor('protection_policy'), $message))
            ->toBeFalse();
    });
});

describe('alreadyExists — savings_account', function () {
    it('returns true when a matching savings account exists', function () {
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'institution' => 'Nationwide',
            'account_name' => 'Nationwide Cash Individual Savings Account',
            'is_isa' => true,
            'created_at' => now()->subHours(1),
        ]);

        $message = 'I have a Nationwide cash ISA worth £5000';

        expect($this->checker->alreadyExists($this->user, intentFor('savings_account'), $message))
            ->toBeTrue();
    });

    it('returns false when no matching savings account exists', function () {
        $message = 'I have a Nationwide cash ISA worth £5000';

        expect($this->checker->alreadyExists($this->user, intentFor('savings_account'), $message))
            ->toBeFalse();
    });
});

describe('alreadyExists — investment_account', function () {
    it('returns true when a matching investment account exists', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Hargreaves Lansdown',
            'account_name' => 'Hargreaves Lansdown General Investment Account',
            'account_type' => 'personal_investment_account',
            'created_at' => now()->subHours(2),
        ]);

        $message = 'I have a Hargreaves Lansdown GIA worth £30000';

        expect($this->checker->alreadyExists($this->user, intentFor('investment_account'), $message))
            ->toBeTrue();
    });
});

describe('alreadyExists — pension', function () {
    it('returns true when a matching DC pension exists', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Aviva',
            'scheme_name' => 'Aviva Workplace Pension',
            'created_at' => now()->subHours(3),
        ]);

        $message = 'I have an Aviva workplace pension worth £50000';

        expect($this->checker->alreadyExists($this->user, intentFor('pension'), $message))
            ->toBeTrue();
    });
});

describe('alreadyExists — property', function () {
    it('returns true when a matching buy-to-let exists with the same postcode', function () {
        Property::factory()->create([
            'user_id' => $this->user->id,
            'property_type' => 'buy_to_let',
            'postcode' => 'M1 1AA',
            'created_at' => now()->subHours(2),
        ]);

        $message = 'I have a buy-to-let at M1 1AA worth £350k';

        expect($this->checker->alreadyExists($this->user, intentFor('property'), $message))
            ->toBeTrue();
    });

    it('returns false when postcodes differ', function () {
        Property::factory()->create([
            'user_id' => $this->user->id,
            'property_type' => 'buy_to_let',
            'postcode' => 'SW1A 1AA',
            'created_at' => now()->subHours(2),
        ]);

        $message = 'I have a buy-to-let at M1 1AA worth £350k';

        expect($this->checker->alreadyExists($this->user, intentFor('property'), $message))
            ->toBeFalse();
    });
});

describe('alreadyExists — goal', function () {
    it('returns true when a matching goal name exists', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Emergency Fund',
            'created_at' => now()->subHours(1),
        ]);

        $message = 'Add a goal called Emergency Fund, target £10000 by 2028';

        expect($this->checker->alreadyExists($this->user, intentFor('goal'), $message))
            ->toBeTrue();
    });

    it('returns false when goal names differ', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'House Deposit',
            'created_at' => now()->subHours(1),
        ]);

        $message = 'Add a goal called Emergency Fund, target £10000 by 2028';

        expect($this->checker->alreadyExists($this->user, intentFor('goal'), $message))
            ->toBeFalse();
    });
});

describe('alreadyExists — fall-through', function () {
    it('returns false for entity_types not yet in scope (mortgage, liability)', function () {
        $message = 'I have a Halifax mortgage of £200,000';

        expect($this->checker->alreadyExists($this->user, intentFor('mortgage'), $message))->toBeFalse()
            ->and($this->checker->alreadyExists($this->user, intentFor('liability'), $message))->toBeFalse();
    });

    it('returns false when the user message produces no extractable entity', function () {
        // No "goal" keyword at all → extractor returns []; allEntitiesExist
        // explicitly returns false on empty extraction (we cannot suppress
        // what we cannot identify).
        $message = 'I am thinking about something for the future';

        expect($this->checker->alreadyExists($this->user, intentFor('goal'), $message))->toBeFalse();
    });
});
