<?php

declare(strict_types=1);

use App\Models\Household;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Savings\ISATracker;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * TODO item 7a, audit items 15-20 (CSJ 2026-10-01): every investment figure is
 * computed once, on the server, and GET /api/investment publishes it. Web, /m and
 * native render these fields as sent; none of them adds, shares, prices or
 * subtracts on the client.
 *
 * Fixtures are deliberately asymmetric: a 70/30 joint account read by the 30%
 * owner, so a client that shows the stored percentage, or the full value, or 50%,
 * gives a different number from the one asserted (test-failure-forensics, Collision).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);

    $household = Household::factory()->create();
    $this->primary = User::factory()->create(['household_id' => $household->id]);
    $this->viewer = User::factory()->create(['household_id' => $household->id]);
    $this->taxYear = app(ISATracker::class)->getCurrentTaxYear();
});

function oneFigureAccount(array $attributes): InvestmentAccount
{
    return InvestmentAccount::factory()->create(array_merge([
        'account_type' => 'gia',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'joint_owner_id' => null,
        'contributions_ytd' => 0,
        'monthly_contribution_amount' => null,
        'contribution_frequency' => 'monthly',
        'isa_subscription_current_year' => 0,
        'platform_fee_percent' => 0,
        'platform_fee_type' => 'percentage',
        'platform_fee_amount' => null,
        'platform_fee_frequency' => 'annually',
        'advisor_fee_percent' => 0,
        'tax_year' => null,
    ], $attributes));
}

function oneFigureHolding(InvestmentAccount $account, float $value, float $ocf, array $extra = []): Holding
{
    return Holding::factory()->forAccount($account)->create(array_merge([
        'asset_type' => 'equity',
        'current_value' => $value,
        'quantity' => null,
        'current_price' => null,
        'purchase_price' => null,
        'cost_basis' => null,
        'ocf_percent' => $ocf,
    ], $extra));
}

describe('item 15: total portfolio value', function () {
    it('publishes the viewer share of every account, not the sum of full values', function () {
        oneFigureAccount([
            'user_id' => $this->primary->id,
            'joint_owner_id' => $this->viewer->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 70,
            'current_value' => 100000,
        ]);
        oneFigureAccount(['user_id' => $this->viewer->id, 'current_value' => 25000]);

        $data = $this->actingAs($this->viewer, 'sanctum')->getJson('/api/investment')->assertOk()->json('data');

        // 30% of 100,000 + 25,000. The full values sum to 125,000; a 50% default to 75,000.
        expect((float) $data['total_value'])->toBe(55000.0);
    });
});

describe('item 16: account value and share', function () {
    it('publishes the joint owner its own percentage and share', function () {
        oneFigureAccount([
            'user_id' => $this->primary->id,
            'joint_owner_id' => $this->viewer->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 70,
            'current_value' => 100000,
        ]);

        $viewerAccount = $this->actingAs($this->viewer, 'sanctum')->getJson('/api/investment')->json('data.accounts.0');
        expect((float) $viewerAccount['user_share_percent'])->toBe(30.0)
            ->and((float) $viewerAccount['user_share'])->toBe(30000.0)
            ->and((float) $viewerAccount['full_value'])->toBe(100000.0);

        $primaryAccount = $this->actingAs($this->primary, 'sanctum')->getJson('/api/investment')->json('data.accounts.0');
        expect((float) $primaryAccount['user_share_percent'])->toBe(70.0)
            ->and((float) $primaryAccount['user_share'])->toBe(70000.0);
    });
});

describe('item 18: charges on the portfolio contract', function () {
    it('prices recorded charges once and counts the holding with none', function () {
        $account = oneFigureAccount([
            'user_id' => $this->viewer->id,
            'current_value' => 10000,
            'platform_fee_percent' => 0.5,
            'advisor_fee_percent' => 0.25,
        ]);
        oneFigureHolding($account, 6000, 0.2);
        // holdings.ocf_percent is NOT NULL DEFAULT 0 (migration 2026_08_22_010000),
        // so a holding with no charge entered is stored, and priced, at 0%.
        oneFigureHolding($account, 4000, 0);

        $fees = $this->actingAs($this->viewer, 'sanctum')->getJson('/api/investment')->json('data.accounts.0.portfolio.fees');

        expect($fees['platform'])->toMatchArray(['recorded' => true, 'type' => 'percentage', 'annual_cost' => 50])
            ->and($fees['advisor'])->toMatchArray(['recorded' => true, 'annual_cost' => 25])
            // 6,000 at 0.2% and 4,000 at 0%: weighted 0.12%, costing 12 a year.
            ->and($fees['fund_charges'])->toMatchArray([
                'weighted_ocf_percent' => 0.12,
                'annual_cost' => 12,
                'holdings_count' => 2,
                'holdings_without_recorded_ocf' => 0,
            ])
            ->and((float) $fees['total_annual_cost'])->toBe(87.0)
            ->and((float) $fees['total_percent'])->toBe(0.87)
            ->and($fees['ten_year_impact']['assumed_growth_percent'])->toEqual(5)
            ->and($fees['ten_year_impact']['years'])->toBe(10);
    });

    it('annualises a fixed platform fee and serialises its type for the edit form', function () {
        oneFigureAccount([
            'user_id' => $this->viewer->id,
            'current_value' => 12000,
            'platform_fee_type' => 'fixed',
            'platform_fee_amount' => 10,
            'platform_fee_frequency' => 'monthly',
        ]);

        $account = $this->actingAs($this->viewer, 'sanctum')->getJson('/api/investment')->json('data.accounts.0');

        expect($account['platform_fee_type'])->toBe('fixed')
            ->and($account['platform_fee_frequency'])->toBe('monthly')
            ->and($account['portfolio']['fees']['platform'])->toMatchArray([
                'recorded' => true,
                'type' => 'fixed',
                'annual_cost' => 120,
                'percent' => 1,
            ]);
    });

    it('moves the ten-year impact when the charge moves', function () {
        $cheap = oneFigureAccount(['user_id' => $this->viewer->id, 'current_value' => 10000, 'platform_fee_percent' => 0.1]);
        $dear = oneFigureAccount(['user_id' => $this->viewer->id, 'current_value' => 10000, 'platform_fee_percent' => 1.0]);

        $accounts = collect($this->actingAs($this->viewer, 'sanctum')->getJson('/api/investment')->json('data.accounts'))->keyBy('id');
        $cheapImpact = $accounts[$cheap->id]['portfolio']['fees']['ten_year_impact'];
        $dearImpact = $accounts[$dear->id]['portfolio']['fees']['ten_year_impact'];

        expect($dearImpact['total_fees'])->toBeGreaterThan($cheapImpact['total_fees'])
            ->and($dearImpact['value_with_fees'])->toBeLessThan($cheapImpact['value_with_fees'])
            // Same pot, same growth, no charges: identical.
            ->and($dearImpact['value_without_fees'])->toBe($cheapImpact['value_without_fees'])
            ->and($dearImpact['total_impact'])->toEqualWithDelta($dearImpact['total_fees'] + $dearImpact['lost_growth'], 0.02);
    });

    it('sums the charges summary from the accounts it lists', function () {
        oneFigureAccount(['user_id' => $this->viewer->id, 'current_value' => 10000, 'platform_fee_percent' => 0.5]);
        oneFigureAccount(['user_id' => $this->viewer->id, 'current_value' => 30000, 'platform_fee_percent' => 0.25]);

        $summary = $this->actingAs($this->viewer, 'sanctum')->getJson('/api/investment')->json('data.fees_summary');

        expect((float) $summary['platform_annual_cost'])->toBe(125.0)
            ->and((float) $summary['basis_value'])->toBe(40000.0)
            ->and((float) $summary['total_percent'])->toBe(0.3125);
    });
});

describe('item 17: returns', function () {
    it('publishes the return after the recorded charges', function () {
        $account = oneFigureAccount([
            'user_id' => $this->viewer->id,
            'current_value' => 12000,
            'platform_fee_percent' => 0.5,
        ]);
        oneFigureHolding($account, 12000, 0, [
            'cost_basis' => 10000,
            'purchase_date' => now()->subYears(2)->toDateString(),
        ]);

        $row = $this->actingAs($this->viewer, 'sanctum')->getJson('/api/investment')->json('data.accounts.0');

        expect($row['annualised_return'])->not->toBeNull()
            ->and((float) $row['annualised_return_after_charges'])
            ->toEqualWithDelta((float) $row['annualised_return'] - (float) $row['portfolio']['fees']['total_percent'], 0.01);
    });
});

describe('item 19: monthly contribution', function () {
    it('publishes the estimator figure at its frequency and never invents one', function () {
        $quarterly = oneFigureAccount([
            'user_id' => $this->viewer->id,
            'current_value' => 5000,
            'monthly_contribution_amount' => 300,
            'contribution_frequency' => 'quarterly',
        ]);
        $nothing = oneFigureAccount(['user_id' => $this->viewer->id, 'current_value' => 5000]);

        $accounts = collect($this->actingAs($this->viewer, 'sanctum')->getJson('/api/investment')->json('data.accounts'))->keyBy('id');

        // The raw column says 300; /m and native printed it as a monthly figure.
        expect((float) $accounts[$quarterly->id]['estimated_monthly_contribution'])->toBe(100.0)
            ->and((float) $accounts[$nothing->id]['estimated_monthly_contribution'])->toBe(0.0)
            ->and((float) $accounts[$quarterly->id]['portfolio']['fees']['ten_year_impact']['annual_contribution'])->toBe(1200.0);
    });
});

describe('item 20: ISA used and remaining', function () {
    it('publishes ISATracker status and each account share of it', function () {
        $isa = oneFigureAccount([
            'user_id' => $this->viewer->id,
            'account_type' => 'isa',
            'isa_type' => 'stocks_and_shares',
            'current_value' => 30000,
            'isa_subscription_current_year' => 5000,
            'tax_year' => $this->taxYear,
        ]);
        SavingsAccount::factory()->create([
            'user_id' => $this->viewer->id,
            'account_type' => 'cash_isa',
            'is_isa' => true,
            'isa_subscription_year' => $this->taxYear,
            'isa_subscription_amount' => 3000,
            'current_balance' => 3000,
        ]);
        $gia = oneFigureAccount(['user_id' => $this->viewer->id, 'current_value' => 1000]);

        $data = $this->actingAs($this->viewer, 'sanctum')->getJson('/api/investment')->json('data');
        $accounts = collect($data['accounts'])->keyBy('id');
        $allowance = (float) app(TaxConfigService::class)->getISAAllowances()['annual_allowance'];

        expect((float) $accounts[$isa->id]['isa_contributed_this_year'])->toBe(5000.0)
            ->and($accounts[$gia->id]['isa_contributed_this_year'])->toBeNull()
            // Remaining counts the cash ISA too: allowance - 8,000, not allowance - 5,000.
            ->and((float) $data['isa_allowance']['total_used'])->toBe(8000.0)
            ->and((float) $data['isa_allowance']['remaining'])->toBe($allowance - 8000.0)
            ->and((float) $data['isa_allowance']['total_allowance'])->toBe($allowance);
    });
});
