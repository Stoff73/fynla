<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * W-0324. `dividend_yield` had a rule in the standalone holding requests and in **none**
 * of the three nested `holdings.*` sets. `validated()` passes exactly the keys with
 * rules and drops the rest, so a yield supplied through the account form or the pension
 * form was discarded before it reached the write — and the save reported success.
 *
 * Adding the rule alone would not have fixed it: `InvestmentController` read
 * `$holdingData['ocf_percent']` and never mentioned this column at all. **Both halves
 * are the fix**, which is why this test asserts the value in the database rather than
 * a 201.
 *
 * This is a W-0262-class defect on the PRESENCE axis, found while a sweep was looking
 * at the range axis — a sweep for one kind of rule-versus-schema disagreement says
 * nothing about the others.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    Sanctum::actingAs($this->user);
});

it('persists a dividend yield sent in a nested holdings array', function () {
    $response = $this->postJson('/api/investment/accounts', [
        'account_name' => 'Dealing Account',
        'account_type' => 'gia',
        'provider' => 'Example Broker',
        'current_value' => 50000,
        'ownership_type' => 'individual',
        'holdings' => [[
            'security_name' => 'Global Equity Fund',
            'asset_type' => 'fund',
            'allocation_percent' => 100,
            'ocf_percent' => 0.22,
            'dividend_yield' => 3.4,
        ]],
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('holdings', [
        'security_name' => 'Global Equity Fund',
        'dividend_yield' => 3.4,
    ]);
});

it('refuses a yield outside the range the column can hold', function () {
    $this->postJson('/api/investment/accounts', [
        'account_name' => 'Dealing Account',
        'account_type' => 'gia',
        'provider' => 'Example Broker',
        'current_value' => 50000,
        'ownership_type' => 'individual',
        'holdings' => [[
            'security_name' => 'Global Equity Fund',
            'asset_type' => 'fund',
            'allocation_percent' => 100,
            'dividend_yield' => 250,
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('holdings.0.dividend_yield');
});

it('carries the rule on every nested holdings set, not only the one that was tested', function () {
    $requests = [
        \App\Http\Requests\StoreInvestmentAccountRequest::class,
        \App\Http\Requests\UpdateInvestmentAccountRequest::class,
        \App\Http\Requests\Retirement\StoreDCPensionRequest::class,
    ];

    foreach ($requests as $class) {
        $rules = (new $class)->rules();

        // `toHaveKey($key, $value)` asserts the VALUE, so the key check is done on
        // the key list — a nested set missing this rule discards the field silently.
        expect(array_keys($rules))->toContain('holdings.*.dividend_yield');
    }
});
