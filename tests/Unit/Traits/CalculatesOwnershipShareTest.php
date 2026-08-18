<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

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
}

class OwnershipShareCalculator
{
    use CalculatesOwnershipShare;

    public function mortgageBalanceFor(Mortgage $mortgage, int $userId): float
    {
        return $this->calculateUserMortgageShare($mortgage, $userId);
    }

    public function mortgagePaymentFor(Mortgage $mortgage, int $userId): float
    {
        return $this->calculateUserMortgageMonthlyPaymentShare($mortgage, $userId);
    }
}
