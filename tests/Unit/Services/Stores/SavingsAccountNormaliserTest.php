<?php

declare(strict_types=1);

use App\Services\Stores\Normalisers\SavingsAccountNormaliser;

describe('SavingsAccountNormaliser::fromForm', function () {
    it('produces canonical-array shape from HTTP form payload', function () {
        $normaliser = new SavingsAccountNormaliser;

        $canonical = $normaliser->fromForm([
            'account_name' => 'Nationwide Cash ISA',
            'account_type' => 'cash_isa',
            'institution' => 'Nationwide',
            'current_balance' => 5000,
            'interest_rate' => 4.5,
            'is_isa' => true,
            'ownership_type' => 'joint',
            'joint_owner_id' => 99,
            // ownership_percentage NOT set — store should infer 50/50 for joint
        ]);

        expect($canonical['account_name'])->toBe('Nationwide Cash ISA');
        expect($canonical['account_type'])->toBe('cash_isa');
        expect($canonical['ownership_type'])->toBe('joint');
        expect($canonical['ownership_percentage'])->toBe(50.00);
        expect($canonical['country'])->toBe('United Kingdom'); // ISA → UK enforced
        expect($canonical['joint_owner_id'])->toBe(99);
    });

    it('defaults ownership to individual at 100% when not set', function () {
        $canonical = (new SavingsAccountNormaliser)->fromForm([
            'account_name' => 'Vanguard cash',
            'current_balance' => 1000,
        ]);

        expect($canonical['ownership_type'])->toBe('individual');
        expect($canonical['ownership_percentage'])->toBe(100.00);
        expect($canonical['country'])->toBe('United Kingdom'); // default for non-ISA
    });

    it('resets ownership_percentage and clears joint_owner_id when switching to individual ownership', function () {
        // Re-submit pattern: edit form flips joint → individual but still carries the
        // joint_owner_id and a 50% ownership_percentage from the previous state.
        // Both must be forced to sole-owner defaults. Matches SavingsController:387-391.
        $canonical = (new SavingsAccountNormaliser)->fromForm([
            'account_name' => 'Solo savings',
            'current_balance' => 1000,
            'ownership_type' => 'individual',
            'joint_owner_id' => 42,
            'ownership_percentage' => 50,
        ]);

        expect($canonical['ownership_type'])->toBe('individual');
        expect($canonical['joint_owner_id'])->toBeNull();
        expect($canonical['ownership_percentage'])->toBe(100.00);
    });
});

describe('SavingsAccountNormaliser::fromFyn', function () {
    it('maps AI-facing account_type to DB-canonical value', function () {
        $normaliser = new SavingsAccountNormaliser;

        expect($normaliser->fromFyn(['account_name' => 'X', 'account_type' => 'fixed_term', 'current_balance' => 1000])['account_type'])
            ->toBe('fixed');

        expect($normaliser->fromFyn(['account_name' => 'X', 'account_type' => 'regular_saver', 'current_balance' => 100])['account_type'])
            ->toBe('easy_access');
    });

    it('infers cash_isa when is_isa is true and account_type is not an ISA variant', function () {
        $canonical = (new SavingsAccountNormaliser)->fromFyn([
            'account_name' => 'X',
            'account_type' => 'easy_access',
            'current_balance' => 1000,
            'is_isa' => true,
        ]);

        expect($canonical['account_type'])->toBe('cash_isa');
    });

    it('defaults institution to account_name when missing', function () {
        $canonical = (new SavingsAccountNormaliser)->fromFyn([
            'account_name' => 'Halifax',
            'current_balance' => 1000,
        ]);

        expect($canonical['institution'])->toBe('Halifax');
    });

    it('derives access_type from account_type', function () {
        $normaliser = new SavingsAccountNormaliser;

        expect($normaliser->fromFyn(['account_name' => 'X', 'account_type' => 'notice', 'current_balance' => 1])['access_type'])->toBe('notice');
        expect($normaliser->fromFyn(['account_name' => 'X', 'account_type' => 'fixed_term', 'current_balance' => 1])['access_type'])->toBe('fixed');
        expect($normaliser->fromFyn(['account_name' => 'X', 'current_balance' => 1])['access_type'])->toBe('immediate');
    });
});
