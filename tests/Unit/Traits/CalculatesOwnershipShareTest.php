<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Traits\CalculatesOwnershipShare;
use Tests\TestCase;

class CalculatesOwnershipShareTest extends TestCase
{
    public function test_individual_mortgage_is_not_split_by_the_property_ownership_percentage(): void
    {
        $mortgage = new Mortgage([
            'user_id' => 101,
            'joint_owner_id' => 202,
            'ownership_type' => 'individual',
            'ownership_percentage' => 30.00,
            'outstanding_balance' => 210000.00,
            'monthly_payment' => 1300.00,
        ]);

        $calculator = new OwnershipShareCalculator;

        expect($calculator->mortgageBalanceFor($mortgage, 101))->toBe(210000.00)
            ->and($calculator->mortgagePaymentFor($mortgage, 101))->toBe(1300.00)
            ->and($calculator->mortgageBalanceFor($mortgage, 202))->toBe(0.00)
            ->and($calculator->mortgagePaymentFor($mortgage, 202))->toBe(0.00);
    }

    public function test_joint_mortgage_uses_its_own_borrower_split(): void
    {
        $mortgage = new Mortgage([
            'user_id' => 101,
            'joint_owner_id' => 202,
            'ownership_type' => 'joint',
            'ownership_percentage' => 30.00,
            'outstanding_balance' => 210000.00,
            'monthly_payment' => 1300.00,
        ]);

        $calculator = new OwnershipShareCalculator;

        expect($calculator->mortgageBalanceFor($mortgage, 101))->toBe(63000.00)
            ->and($calculator->mortgagePaymentFor($mortgage, 101))->toBe(390.00)
            ->and($calculator->mortgageBalanceFor($mortgage, 202))->toBe(147000.00)
            ->and(round($calculator->mortgagePaymentFor($mortgage, 202), 2))->toBe(910.00);
    }

    public function test_a_joint_asset_splits_the_full_value_between_the_two_owners(): void
    {
        $account = new InvestmentAccount([
            'ownership_type' => 'joint',
            'ownership_percentage' => 50.00,
            'current_value' => 95000.00,
        ]);
        $account->user_id = 16;
        $account->joint_owner_id = 17;

        $calculator = new OwnershipShareCalculator;

        expect($calculator->shareFor($account, 16))->toBe(47500.00)
            ->and($calculator->shareFor($account, 17))->toBe(47500.00)
            ->and($calculator->shareFor($account, 99))->toBe(0.00);
    }

    public function test_a_joint_asset_stored_at_100_is_taken_at_its_word_not_rewritten_to_50(): void
    {
        // The trait used to silently rewrite a stored 100 to 50, which masked
        // the write-side bug that stored joint assets 100/0 and left every
        // surface doing its own arithmetic disagreeing with every surface using
        // the trait (W-0014 / W-0015). The share is now normalised on the way IN.
        $account = new InvestmentAccount([
            'ownership_type' => 'joint',
            'ownership_percentage' => 100.00,
            'current_value' => 95000.00,
        ]);
        $account->user_id = 16;
        $account->joint_owner_id = 17;

        $calculator = new OwnershipShareCalculator;

        expect($calculator->shareFor($account, 16))->toBe(95000.00)
            ->and($calculator->shareFor($account, 17))->toBe(0.00);
    }

    public function test_an_uneven_tenants_in_common_split_gives_the_complement_to_the_other_party(): void
    {
        $account = new InvestmentAccount([
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 60.00,
            'current_value' => 100000.00,
        ]);
        $account->user_id = 16;
        $account->joint_owner_id = 17;

        $calculator = new OwnershipShareCalculator;

        expect($calculator->shareFor($account, 16))->toBe(60000.00)
            ->and($calculator->shareFor($account, 17))->toBe(40000.00);
    }
}

class OwnershipShareCalculator
{
    use CalculatesOwnershipShare;

    public function shareFor(object $asset, int $userId): float
    {
        return $this->calculateUserShare($asset, $userId);
    }

    public function mortgageBalanceFor(Mortgage $mortgage, int $userId): float
    {
        return $this->calculateUserMortgageShare($mortgage, $userId);
    }

    public function mortgagePaymentFor(Mortgage $mortgage, int $userId): float
    {
        return $this->calculateUserMortgageMonthlyPaymentShare($mortgage, $userId);
    }
}
