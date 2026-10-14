<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * T28 (F12): the Liabilities page read the Estate store, and the Estate index
 * gives Free users only a teaser (EstateController::index), so a Free user's
 * mortgages and debts never showed, though Liabilities is a Free module
 * (capability_matrix liabilities=full). The page reads its own net-worth route.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create(['tier' => 'free', 'is_preview_user' => false]);
    $property = Property::factory()->create(['user_id' => $this->user->id, 'ownership_type' => 'individual', 'ownership_percentage' => 100]);
    Mortgage::factory()->create([
        'property_id' => $property->id, 'user_id' => $this->user->id, 'lender_name' => 'BM Solutions',
        'outstanding_balance' => 180000, 'monthly_payment' => null, 'interest_rate' => null, 'mortgage_type' => null,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    Liability::factory()->for($this->user)->create([
        'liability_type' => 'credit_card', 'liability_name' => 'Amex', 'current_balance' => 2400,
        'monthly_payment' => 120, 'ownership_type' => 'individual',
    ]);
});

it('lists a Free user\'s mortgages and debts at their share, with the totals', function () {
    Sanctum::actingAs($this->user);

    $data = $this->getJson('/api/net-worth/liabilities')->assertOk()->json('data');

    $mortgage = collect($data['liabilities'])->firstWhere('liability_type', 'mortgage');
    expect($mortgage['user_share'])->toEqual(180000)
        ->and(collect($data['liabilities'])->firstWhere('liability_type', 'credit_card')['current_balance'])->toEqual(2400)
        ->and($data['liability_totals']['all']['balance'])->toEqual(182400);
});

it('sends a mortgage payment, rate or type never given as none, not £0, 0% or "Repayment"', function () {
    Sanctum::actingAs($this->user);

    $mortgage = collect($this->getJson('/api/net-worth/liabilities')->json('data.liabilities'))->firstWhere('liability_type', 'mortgage');
    expect($mortgage['monthly_payment'])->toBeNull()
        ->and($mortgage['interest_rate'])->toBeNull()
        ->and($mortgage['notes'])->toBeNull();
});
