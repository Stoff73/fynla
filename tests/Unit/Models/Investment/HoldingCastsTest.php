<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Regression test for audit S-02 (May/May12Updates/review-database.md).
 * Holding columns are stored as decimal(15,N) but were previously cast
 * to 'float' — losing precision on every read. Casts are now decimal:N
 * which returns full-precision strings.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = InvestmentAccount::factory()->create(['user_id' => $this->user->id]);
});

describe('Holding precision casts', function () {
    it('preserves quantity precision to 6 decimal places', function () {
        $holding = Holding::create([
            'holdable_id' => $this->account->id,
            'holdable_type' => InvestmentAccount::class,
            'security_name' => 'Test Fund',
            'quantity' => 123.456789,
            'current_value' => 0,
            'cost_basis' => 0,
        ]);

        $reloaded = Holding::find($holding->id);

        expect($reloaded->quantity)->toBeString();
        // decimal(15,6) rounds to 6 places: 123.456789 stored as 123.456789
        expect((string) $reloaded->quantity)->toBe('123.456789');
    });

    it('preserves prices to 4 decimal places', function () {
        $holding = Holding::create([
            'holdable_id' => $this->account->id,
            'holdable_type' => InvestmentAccount::class,
            'security_name' => 'Test Fund',
            'purchase_price' => 99.9999,
            'current_price' => 100.1234,
            'current_value' => 0,
            'cost_basis' => 0,
        ]);

        $reloaded = Holding::find($holding->id);

        expect($reloaded->purchase_price)->toBeString()
            ->and((string) $reloaded->purchase_price)->toBe('99.9999')
            ->and((string) $reloaded->current_price)->toBe('100.1234');
    });

    it('preserves currency values to 2 decimal places', function () {
        $holding = Holding::create([
            'holdable_id' => $this->account->id,
            'holdable_type' => InvestmentAccount::class,
            'security_name' => 'Test Fund',
            'current_value' => 1234567.89,
            'cost_basis' => 1000000.00,
        ]);

        $reloaded = Holding::find($holding->id);

        expect($reloaded->current_value)->toBeString()
            ->and((string) $reloaded->current_value)->toBe('1234567.89')
            ->and((string) $reloaded->cost_basis)->toBe('1000000.00');
    });

    it('returns strings for cast columns (decimal cast contract)', function () {
        $holding = Holding::create([
            'holdable_id' => $this->account->id,
            'holdable_type' => InvestmentAccount::class,
            'security_name' => 'Test Fund',
            'allocation_percent' => 50.5,
            'quantity' => 10,
            'purchase_price' => 100,
            'current_price' => 110,
            'current_value' => 1100,
            'cost_basis' => 1000,
            'dividend_yield' => 3.5,
            'ocf_percent' => 0.25,
        ]);

        $reloaded = Holding::find($holding->id);

        foreach (['allocation_percent', 'quantity', 'purchase_price', 'current_price',
                  'current_value', 'cost_basis', 'dividend_yield', 'ocf_percent'] as $col) {
            expect($reloaded->{$col})->toBeString("Column {$col} should cast to string under decimal:N");
        }
    });
});
