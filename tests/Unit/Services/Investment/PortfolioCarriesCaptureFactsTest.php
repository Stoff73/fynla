<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Investment\PortfolioPresentationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0442 acceptance 3. Units, purchase price, current price and purchase date are
 * captured, validated and stored (W-0039) and were displayed on web only. `/m` renders
 * holdings from the `financial_portfolio_v1` contract, and **the contract never carried
 * these four fields** — so no `/m` template change alone could have shown them, which is
 * why the parity half of this item stayed open after the web half was fixed.
 *
 * Nullable rather than defaulted, matching `HoldingResource:30-35`: a holding recorded
 * without a purchase price has not been bought for nothing, and a zero would collapse
 * "not recorded" into a figure the reader cannot tell apart from a real one.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    $this->account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 50000,
        'ownership_type' => 'individual',
    ]);
    $this->service = app(PortfolioPresentationService::class);
});

it('carries units, both prices and the purchase date', function () {
    Holding::factory()->create([
        // Holdings are polymorphic — the factory sets holdable_id/holdable_type from
        // the related model rather than a foreign key column.
        'holdable_id' => $this->account->id,
        'holdable_type' => InvestmentAccount::class,
        'security_name' => 'Global Equity Fund',
        'current_value' => 50000,
        'quantity' => 4211.5,
        'purchase_price' => 9.87,
        'current_price' => 11.87,
        'purchase_date' => '2021-06-14',
    ]);

    // No risk profile and the account's own value as the relevant portfolio: neither
    // affects the four fields under test, and passing real ones would make the test
    // depend on the exposure engine rather than on the contract.
    $holding = $this->service->forInvestmentAccount(
        $this->account->fresh(), null, 50000.0
    )['holdings'][0];

    expect($holding['quantity'])->toBe(4211.5)
        ->and($holding['purchase_price'])->toBe(9.87)
        ->and($holding['current_price'])->toBe(11.87)
        ->and($holding['purchase_date'])->toBe('2021-06-14');
});

/**
 * The distinction the shared formatter exists to protect: "no unit count recorded" and
 * "zero units held" are different facts, and a zero-default would make them one.
 */
it('serves null rather than zero for a holding that records none of them', function () {
    Holding::factory()->create([
        // Holdings are polymorphic — the factory sets holdable_id/holdable_type from
        // the related model rather than a foreign key column.
        'holdable_id' => $this->account->id,
        'holdable_type' => InvestmentAccount::class,
        'security_name' => 'Unpriced Fund',
        'current_value' => 50000,
        'quantity' => null,
        'purchase_price' => null,
        'current_price' => null,
        'purchase_date' => null,
    ]);

    // No risk profile and the account's own value as the relevant portfolio: neither
    // affects the four fields under test, and passing real ones would make the test
    // depend on the exposure engine rather than on the contract.
    $holding = $this->service->forInvestmentAccount(
        $this->account->fresh(), null, 50000.0
    )['holdings'][0];

    expect($holding['quantity'])->toBeNull()
        ->and($holding['purchase_price'])->toBeNull()
        ->and($holding['current_price'])->toBeNull()
        ->and($holding['purchase_date'])->toBeNull();
});
