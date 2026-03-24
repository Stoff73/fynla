<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('storeAccount with inline holdings', function () {
    it('creates account with holdings in single transaction', function () {
        $response = $this->postJson('/api/investment/accounts', [
            'account_type' => 'isa',
            'provider' => 'Vanguard',
            'current_value' => 50000,
            'holdings' => [
                [
                    'security_name' => 'Vanguard FTSE All-World',
                    'asset_type' => 'etf',
                    'allocation_percent' => 60,
                    'cost_basis' => 25000,
                ],
                [
                    'security_name' => 'iShares UK Gilts',
                    'asset_type' => 'bond',
                    'allocation_percent' => 25,
                    'cost_basis' => 12000,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $account = InvestmentAccount::where('user_id', $this->user->id)->first();
        expect($account)->not->toBeNull();
        expect((float) $account->current_value)->toBe(50000.0);

        $holdings = $account->holdings;
        // 2 user holdings + 1 auto-created cash (15% remainder)
        expect($holdings)->toHaveCount(3);

        $etf = $holdings->where('asset_type', 'etf')->first();
        expect($etf->security_name)->toBe('Vanguard FTSE All-World');
        expect($etf->allocation_percent)->toBe(60.0);
        expect($etf->current_value)->toBe(30000.0);
        expect($etf->cost_basis)->toBe(25000.0);

        $cash = $holdings->where('asset_type', 'cash')->first();
        expect($cash->security_name)->toBe('Cash');
        expect($cash->allocation_percent)->toBe(15.0);
        expect($cash->current_value)->toBe(7500.0);
    });

    it('creates account without holdings when none provided', function () {
        $response = $this->postJson('/api/investment/accounts', [
            'account_type' => 'gia',
            'provider' => 'Hargreaves Lansdown',
            'current_value' => 10000,
        ]);

        $response->assertStatus(201);

        $account = InvestmentAccount::where('user_id', $this->user->id)->first();
        expect($account->holdings)->toHaveCount(0);
    });

    it('skips auto-cash when user explicitly adds a cash holding', function () {
        $response = $this->postJson('/api/investment/accounts', [
            'account_type' => 'isa',
            'provider' => 'AJ Bell',
            'current_value' => 20000,
            'holdings' => [
                [
                    'security_name' => 'Vanguard LifeStrategy 80',
                    'asset_type' => 'fund',
                    'allocation_percent' => 70,
                ],
                [
                    'security_name' => 'Cash Reserve',
                    'asset_type' => 'cash',
                    'allocation_percent' => 10,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $account = InvestmentAccount::where('user_id', $this->user->id)->first();
        $holdings = $account->holdings;

        // 2 user holdings only — no auto-created cash despite 20% remaining
        expect($holdings)->toHaveCount(2);
        expect($holdings->where('asset_type', 'cash')->count())->toBe(1);
        expect($holdings->where('asset_type', 'cash')->first()->security_name)->toBe('Cash Reserve');
    });

    it('rejects holdings exceeding 100% allocation', function () {
        $response = $this->postJson('/api/investment/accounts', [
            'account_type' => 'gia',
            'provider' => 'Interactive Investor',
            'current_value' => 30000,
            'holdings' => [
                [
                    'security_name' => 'Fund A',
                    'asset_type' => 'fund',
                    'allocation_percent' => 60,
                ],
                [
                    'security_name' => 'Fund B',
                    'asset_type' => 'fund',
                    'allocation_percent' => 50,
                ],
            ],
        ]);

        $response->assertStatus(422);
    });

    it('creates holdings with 100% allocation and no auto-cash', function () {
        $response = $this->postJson('/api/investment/accounts', [
            'account_type' => 'isa',
            'provider' => 'Fidelity',
            'current_value' => 40000,
            'holdings' => [
                [
                    'security_name' => 'Global Equity Fund',
                    'asset_type' => 'fund',
                    'allocation_percent' => 100,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $account = InvestmentAccount::where('user_id', $this->user->id)->first();
        expect($account->holdings)->toHaveCount(1);
        expect($account->holdings->first()->allocation_percent)->toBe(100.0);
    });
});
