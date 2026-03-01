<?php

declare(strict_types=1);

use App\Services\Plans\DistributionAccount;

describe('DistributionAccount', function () {
    it('initialises with correct balance', function () {
        $account = new DistributionAccount(1000.00);

        expect($account->remaining())->toBe(1000.00)
            ->and($account->initialBalance())->toBe(1000.00)
            ->and($account->totalAllocated())->toBe(0.0)
            ->and($account->getAllocations())->toBe([])
            ->and($account->isExhausted())->toBeFalse();
    });

    it('allocates amount and reduces remaining correctly', function () {
        $account = new DistributionAccount(1000.00);

        $allocated = $account->allocate('emergency_fund', 300.00);

        expect($allocated)->toBe(300.00)
            ->and($account->remaining())->toBe(700.00)
            ->and($account->totalAllocated())->toBe(300.00);
    });

    it('returns capped amount when requesting more than remaining', function () {
        $account = new DistributionAccount(200.00);

        $allocated = $account->allocate('pension_contribution', 500.00);

        expect($allocated)->toBe(200.00)
            ->and($account->remaining())->toBe(0.0)
            ->and($account->isExhausted())->toBeTrue();
    });

    it('returns zero when account is exhausted', function () {
        $account = new DistributionAccount(100.00);
        $account->allocate('first', 100.00);

        $allocated = $account->allocate('second', 50.00);

        expect($allocated)->toBe(0.0)
            ->and($account->remaining())->toBe(0.0);
    });

    it('returns zero for zero or negative allocation requests', function () {
        $account = new DistributionAccount(1000.00);

        expect($account->allocate('zero', 0.0))->toBe(0.0)
            ->and($account->allocate('negative', -50.00))->toBe(0.0)
            ->and($account->remaining())->toBe(1000.00);
    });

    it('tracks multiple allocations correctly', function () {
        $account = new DistributionAccount(1000.00);

        $account->allocate('emergency_fund', 200.00);
        $account->allocate('pension', 350.00);
        $account->allocate('investment', 150.00);

        expect($account->getAllocations())->toBe([
            'emergency_fund' => 200.00,
            'pension' => 350.00,
            'investment' => 150.00,
        ])
            ->and($account->remaining())->toBe(300.00)
            ->and($account->totalAllocated())->toBe(700.00);
    });

    it('accumulates allocations for the same label', function () {
        $account = new DistributionAccount(1000.00);

        $account->allocate('pension', 200.00);
        $account->allocate('pension', 150.00);

        expect($account->getAllocations())->toBe(['pension' => 350.00])
            ->and($account->remaining())->toBe(650.00);
    });

    it('resets to initial balance and clears allocations', function () {
        $account = new DistributionAccount(1000.00);

        $account->allocate('emergency_fund', 300.00);
        $account->allocate('pension', 400.00);

        expect($account->remaining())->toBe(300.00);

        $account->reset();

        expect($account->remaining())->toBe(1000.00)
            ->and($account->totalAllocated())->toBe(0.0)
            ->and($account->getAllocations())->toBe([])
            ->and($account->isExhausted())->toBeFalse();
    });

    it('reports exhausted correctly at zero', function () {
        $account = new DistributionAccount(500.00);

        expect($account->isExhausted())->toBeFalse();

        $account->allocate('full_allocation', 500.00);

        expect($account->isExhausted())->toBeTrue();
    });

    it('handles zero initial balance', function () {
        $account = new DistributionAccount(0.0);

        expect($account->remaining())->toBe(0.0)
            ->and($account->isExhausted())->toBeTrue()
            ->and($account->allocate('anything', 100.00))->toBe(0.0);
    });

    it('handles negative initial balance by treating as zero', function () {
        $account = new DistributionAccount(-500.00);

        expect($account->remaining())->toBe(0.0)
            ->and($account->isExhausted())->toBeTrue();
    });

    it('handles penny-precision allocations correctly', function () {
        $account = new DistributionAccount(100.00);

        $account->allocate('item_a', 33.33);
        $account->allocate('item_b', 33.33);
        $account->allocate('item_c', 33.34);

        expect($account->remaining())->toBe(0.0)
            ->and($account->totalAllocated())->toBe(100.00)
            ->and($account->isExhausted())->toBeTrue();
    });
});
