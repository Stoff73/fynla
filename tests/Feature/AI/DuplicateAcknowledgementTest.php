<?php

declare(strict_types=1);

use App\Models\CriticalIllnessPolicy;
use App\Models\DCPension;
use App\Models\Estate\Liability;
use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\DuplicateAcknowledgement;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Sprint 0.16b — DuplicateAcknowledgement (BS-17 honest-response loop).
 *
 * When RecordDuplicateChecker confirms the user has reasserted a record
 * already on file, AdviceFyn::handle short-circuits — does not invoke
 * the LLM at all — and emits a deterministic factual ack built by this
 * service. The tests pin the descriptor wording per entity_type so a
 * future LLM/prompt change cannot regress the no-gaslight contract.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->builder = app(DuplicateAcknowledgement::class);
    $this->user = User::factory()->create();
});

function ackIntent(string $entityType): array
{
    return [
        'entity_type' => $entityType,
        'matched_verb' => 'i have',
        'matched_entity_keyword' => $entityType,
        'fields_needed' => [],
        'reason' => 'test',
    ];
}

describe('build — protection_policy', function () {
    it('describes a single life policy with provider, type, and sum assured', function () {
        LifeInsurancePolicy::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Aviva',
            'policy_type' => 'level_term',
            'sum_assured' => 300000,
            'created_at' => now()->subHours(1),
        ]);

        $ack = $this->builder->build($this->user, ackIntent('protection_policy'), 'I have Aviva life insurance £300k');

        expect($ack['descriptors'])->toBe(['Aviva level term life insurance for **£300,000**'])
            ->and($ack['text'])->toBe("Aviva level term life insurance for **£300,000** is already on record. Anything else you'd like to add?");
    });

    it('describes life + critical illness as a bullet list', function () {
        LifeInsurancePolicy::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Aviva',
            'policy_type' => 'level_term',
            'sum_assured' => 300000,
            'created_at' => now()->subHours(1),
        ]);
        CriticalIllnessPolicy::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Vitality',
            'policy_type' => 'standalone',
            'sum_assured' => 100000,
            'created_at' => now()->subHours(1),
        ]);

        $ack = $this->builder->build($this->user, ackIntent('protection_policy'), 'I have Aviva life insurance £300k and Vitality critical illness £100k');

        expect($ack['descriptors'])->toBe([
            'Aviva level term life insurance for **£300,000**',
            'Vitality standalone critical illness cover for **£100,000**',
        ])
            ->and($ack['text'])->toContain('These are already on record:')
            ->and($ack['text'])->toContain('- Aviva level term life insurance for **£300,000**')
            ->and($ack['text'])->toContain('- Vitality standalone critical illness cover for **£100,000**')
            ->and($ack['text'])->toContain("Anything else you'd like to add?");
    });
});

describe('build — savings_account', function () {
    it('describes a cash ISA with institution and balance', function () {
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'institution' => 'Nationwide',
            'account_name' => 'Nationwide Cash Individual Savings Account',
            'is_isa' => true,
            'current_balance' => 5000,
            'created_at' => now()->subHours(1),
        ]);

        $ack = $this->builder->build($this->user, ackIntent('savings_account'), 'I have a Nationwide cash ISA worth £5000');

        expect($ack['descriptors'])->toBe(['Nationwide cash ISA with a balance of **£5,000**']);
    });
});

describe('build — investment_account', function () {
    it('describes a GIA with provider and current value', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Hargreaves Lansdown',
            'account_name' => 'Hargreaves Lansdown General Investment Account',
            'account_type' => 'personal_investment_account',
            'current_value' => 30000,
            'created_at' => now()->subHours(1),
        ]);

        $ack = $this->builder->build($this->user, ackIntent('investment_account'), 'I have a Hargreaves Lansdown GIA worth £30000');

        expect($ack['descriptors'])->toBe(['Hargreaves Lansdown General Investment Account worth **£30,000**']);
    });
});

describe('build — pension', function () {
    it('describes a DC pension with provider and value', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Aviva',
            'scheme_name' => 'Workplace Pension',
            'current_fund_value' => 50000,
            'created_at' => now()->subHours(1),
        ]);

        $ack = $this->builder->build($this->user, ackIntent('pension'), 'I have an Aviva workplace pension worth £50000');

        expect($ack['descriptors'])->toBe(['Aviva Workplace Pension worth **£50,000**']);
    });
});

describe('build — property', function () {
    it('describes a buy-to-let with postcode and value', function () {
        Property::factory()->create([
            'user_id' => $this->user->id,
            'property_type' => 'buy_to_let',
            'postcode' => 'M1 1AA',
            'current_value' => 350000,
            'created_at' => now()->subHours(1),
        ]);

        $ack = $this->builder->build($this->user, ackIntent('property'), 'I have a buy-to-let at M1 1AA worth £350k');

        expect($ack['descriptors'])->toHaveCount(1)
            ->and($ack['descriptors'][0])->toContain('buy-to-let')
            ->and($ack['descriptors'][0])->toContain('M1 1AA')
            ->and($ack['descriptors'][0])->toContain('**£350,000**');
    });
});

describe('build — goal', function () {
    it('describes a goal with name and target', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Emergency Fund',
            'target_amount' => 10000,
            'created_at' => now()->subHours(1),
        ]);

        $ack = $this->builder->build($this->user, ackIntent('goal'), 'Add a goal called Emergency Fund target £10000');

        expect($ack['descriptors'])->toHaveCount(1)
            ->and($ack['descriptors'][0])->toContain('"Emergency Fund"')
            ->and($ack['descriptors'][0])->toContain('**£10,000**');
    });
});

describe('build — mortgage', function () {
    it('describes a mortgage with lender and outstanding balance', function () {
        $property = Property::factory()->create(['user_id' => $this->user->id]);
        Mortgage::factory()->create([
            'user_id' => $this->user->id,
            'property_id' => $property->id,
            'lender_name' => 'Halifax',
            'outstanding_balance' => 200000,
            'created_at' => now()->subHours(1),
        ]);

        $ack = $this->builder->build($this->user, ackIntent('mortgage'), 'I have a Halifax mortgage of £200,000');

        expect($ack['descriptors'])->toHaveCount(1)
            ->and($ack['descriptors'][0])->toContain('Halifax mortgage')
            ->and($ack['descriptors'][0])->toContain('**£200,000**');
    });
});

describe('build — liability', function () {
    it('describes a credit card with balance', function () {
        Liability::factory()->create([
            'user_id' => $this->user->id,
            'liability_type' => 'credit_card',
            'liability_name' => 'Barclays credit card',
            'current_balance' => 2500,
            'created_at' => now()->subHours(1),
        ]);

        $ack = $this->builder->build($this->user, ackIntent('liability'), 'I have a Barclays credit card with £2500 balance');

        expect($ack['descriptors'])->toHaveCount(1)
            ->and($ack['descriptors'][0])->toContain('Barclays credit card')
            ->and($ack['descriptors'][0])->toContain('**£2,500**');
    });
});

describe('build — fall-through', function () {
    it('returns the safe-fallback text when no DB row matches', function () {
        $ack = $this->builder->build($this->user, ackIntent('protection_policy'), 'I have Aviva life insurance £300k');

        // No persisted policy → no descriptor → fallback string.
        expect($ack['descriptors'])->toBe([])
            ->and($ack['text'])->toBe("These records are already on file. Anything else you'd like to add or check?");
    });
});
