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
});
