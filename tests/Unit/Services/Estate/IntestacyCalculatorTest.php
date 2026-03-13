<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Estate\IntestacyCalculator;

describe('IntestacyCalculator', function () {
    beforeEach(function () {
        $this->calculator = new IntestacyCalculator;
    });

    it('calculates distribution for married person with no children - spouse gets all', function () {
        $spouse = User::factory()->create();
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
        ]);

        $result = $this->calculator->calculateDistribution($user->id, 500000);

        expect($result['scenario'])->toBe('Married without Children');
        expect($result['beneficiaries'])->toHaveCount(1);
        expect($result['beneficiaries'][0]['relationship'])->toBe('Spouse/Civil Partner');
        expect($result['beneficiaries'][0]['amount'])->toBe(500000.0);
        expect($result['beneficiaries'][0]['percentage'])->toBe(100);
        expect($result['goes_to_crown'])->toBe(false);
    });

    it('calculates distribution for married person with children - estate under £250k', function () {
        $spouse = User::factory()->create();
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
        ]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'child',
            'name' => 'Child 1',
            'first_name' => 'Child',
            'last_name' => 'One',
        ]);
        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'child',
            'name' => 'Child 2',
            'first_name' => 'Child',
            'last_name' => 'Two',
        ]);

        $result = $this->calculator->calculateDistribution($user->id, 200000);

        expect($result['scenario'])->toBe('Married with Children - Estate under £322,000');
        expect($result['beneficiaries'])->toHaveCount(1);
        expect($result['beneficiaries'][0]['relationship'])->toBe('Spouse/Civil Partner');
        expect($result['beneficiaries'][0]['amount'])->toBe(200000.0);
    });

    it('calculates distribution for married person with children - estate over £250k', function () {
        $spouse = User::factory()->create();
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
        ]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'child',
            'name' => 'Child 1',
            'first_name' => 'Child',
            'last_name' => 'One',
        ]);
        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'child',
            'name' => 'Child 2',
            'first_name' => 'Child',
            'last_name' => 'Two',
        ]);

        $result = $this->calculator->calculateDistribution($user->id, 500000);

        expect($result['scenario'])->toBe('Married with Children - Estate over £322,000');
        expect($result['beneficiaries'])->toHaveCount(2);

        // Spouse gets first £322k + half of remaining £178k = £411k
        expect($result['beneficiaries'][0]['relationship'])->toBe('Spouse/Civil Partner');
        expect($result['beneficiaries'][0]['amount'])->toBe(411000.0);

        // Children share other half of remaining £178k = £89k
        expect($result['beneficiaries'][1]['relationship'])->toBe('Children');
        expect($result['beneficiaries'][1]['amount'])->toBe(89000.0);
    });

    it('calculates distribution for single person with children', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
        ]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'child',
            'name' => 'Child 1',
            'first_name' => 'Child',
            'last_name' => 'One',
        ]);
        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'child',
            'name' => 'Child 2',
            'first_name' => 'Child',
            'last_name' => 'Two',
        ]);

        $result = $this->calculator->calculateDistribution($user->id, 300000);

        expect($result['scenario'])->toBe('Not Married - Children Inherit');
        expect($result['beneficiaries'])->toHaveCount(1);
        expect($result['beneficiaries'][0]['relationship'])->toBe('Children');
        expect($result['beneficiaries'][0]['amount'])->toBe(300000.0);
        expect($result['beneficiaries'][0]['count'])->toBe(2);
    });

    it('calculates distribution for single person with no children - parents inherit', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
        ]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'parent',
            'name' => 'Parent 1',
            'first_name' => 'Parent',
            'last_name' => 'One',
        ]);
        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'parent',
            'name' => 'Parent 2',
            'first_name' => 'Parent',
            'last_name' => 'Two',
        ]);

        $result = $this->calculator->calculateDistribution($user->id, 300000);

        expect($result['scenario'])->toBe('No Children - Parents Inherit');
        expect($result['beneficiaries'])->toHaveCount(1);
        expect($result['beneficiaries'][0]['relationship'])->toBe('Parents');
        expect($result['beneficiaries'][0]['amount'])->toBe(300000.0);
        expect($result['beneficiaries'][0]['count'])->toBe(2);
    });

    it('calculates distribution for single person with no relatives - crown inherits', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
        ]);

        $result = $this->calculator->calculateDistribution($user->id, 500000);

        expect($result['scenario'])->toBe('No Eligible Relatives - Crown Inherits');
        expect($result['beneficiaries'])->toHaveCount(1);
        expect($result['beneficiaries'][0]['relationship'])->toBe('The Crown (Government)');
        expect($result['beneficiaries'][0]['amount'])->toBe(500000.0);
        expect($result['goes_to_crown'])->toBe(true);
    });

    it('includes decision path in results', function () {
        $spouse = User::factory()->create();
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
        ]);

        $result = $this->calculator->calculateDistribution($user->id, 500000);

        expect($result['decision_path'])->toBeArray();
        expect($result['decision_path'])->not->toBeEmpty();
        expect($result['decision_path'][0]['question'])->toBe('Are you married/civil partnered?');
        expect($result['decision_path'][0]['answer'])->toBe('YES');
    });
});
