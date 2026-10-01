<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\NetWorth\NetWorthService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
 * One figure, every surface (CSJ 2026-10-01): what a person owns and owes is
 * worked out on the server; the debts list, the /m Net Worth screens and the
 * property pages show it as sent. Every split here is 70/30, never 50/50, so a
 * figure taken from the wrong side of the record cannot pass.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->primary = User::factory()->create(['is_preview_user' => false]);
    // Premium, so the Estate page is the full module, not its teaser.
    $this->spouse = User::factory()->withActivePremiumSubscription()->create(['is_preview_user' => false, 'tier' => 'premium']);

    $this->property = Property::factory()->create([
        'user_id' => $this->primary->id,
        'joint_owner_id' => $this->spouse->id,
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'ownership_percentage' => 70,
        'current_value' => 400000,
        'purchase_price' => 300000,
        'monthly_rental_income' => 0,
    ]);
    $this->mortgage = Mortgage::factory()->create([
        'user_id' => $this->primary->id,
        'joint_owner_id' => $this->spouse->id,
        'property_id' => $this->property->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 70,
        'outstanding_balance' => 100000,
        'monthly_payment' => 1000,
    ]);
    $this->loan = Liability::create([
        'user_id' => $this->primary->id,
        'joint_owner_id' => $this->spouse->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 70,
        'liability_type' => 'personal_loan',
        'liability_name' => 'Car loan',
        'current_balance' => 10000,
        'monthly_payment' => 300,
    ]);
});

it('gives the co-owner their own share, equity and mortgage on the property page', function () {
    Sanctum::actingAs($this->spouse);

    $property = $this->getJson("/api/properties/{$this->property->id}")->assertOk()->json('data.property');

    expect((float) $property['user_share'])->toBe(120000.0)
        ->and((float) $property['user_share_percent'])->toBe(30.0)
        ->and((float) $property['other_owner_share_percent'])->toBe(70.0)
        ->and((float) $property['mortgage_user_share'])->toBe(30000.0)
        ->and((float) $property['user_equity'])->toBe(90000.0)
        ->and((float) $property['full_equity'])->toBe(300000.0)
        ->and((float) $property['value_change'])->toBe(100000.0)
        ->and((float) $property['mortgage_shares'][$this->mortgage->id]['user_monthly_payment'])->toBe(300.0);
});

it('totals the co-owner\'s debts at their share, including debts their spouse recorded', function () {
    Sanctum::actingAs($this->spouse);

    $totals = $this->getJson('/api/estate')->assertOk()->json('data.liability_totals');

    // £30,000 of the mortgage and £3,000 of the loan.
    expect((float) $totals['all']['balance'])->toBe(33000.0)
        ->and((float) $totals['all']['monthly_payments'])->toBe(390.0)
        ->and((float) $totals['personal_loan']['balance'])->toBe(3000.0);
});

it('lists the debts on /m at the same total net worth uses', function () {
    $nw = app(NetWorthService::class);
    $detailed = $nw->getAssetsSummaryWithDetails($this->spouse);
    $overview = $nw->calculateNetWorth($this->spouse);

    expect((float) $detailed['liabilities']['total_value'])->toBe((float) $overview['total_liabilities'])
        ->and((float) $detailed['liabilities']['total_value'])->toBe(33000.0)
        ->and((float) $detailed['property']['items'][0]['user_equity'])->toBe(90000.0)
        ->and((float) $detailed['property']['items'][0]['user_share_percent'])->toBe(30.0)
        ->and($detailed['property']['percent_of_assets'])->toBe(100);
});

it('sends the estate pages the own-estate figures and the gifts of the last seven years', function () {
    Sanctum::actingAs($this->spouse);
    \App\Models\Estate\Gift::create([
        'user_id' => $this->spouse->id, 'gift_date' => now()->subYears(2)->toDateString(),
        'recipient' => 'Child', 'gift_type' => 'pet', 'gift_value' => 20000,
    ]);
    \App\Models\Estate\Gift::create([
        'user_id' => $this->spouse->id, 'gift_date' => now()->subYears(9)->toDateString(),
        'recipient' => 'Child', 'gift_type' => 'pet', 'gift_value' => 50000,
    ]);

    $summary = $this->getJson('/api/estate')->assertOk()->json('data.summary');
    $estate = app(\App\Services\Estate\NetWorthAnalyzer::class)->calculateNetWorth($this->spouse->id);

    expect((float) $summary['net_worth'])->toBe((float) $estate['net_worth'])
        ->and((float) $summary['total_liabilities'])->toBe(33000.0)
        ->and($summary['gifts_within_7_years']['count'])->toBe(1)
        ->and((float) $summary['gifts_within_7_years']['value'])->toBe(20000.0);
});
