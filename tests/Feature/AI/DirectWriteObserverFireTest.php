<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\DCPension;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\Liability;
use App\Models\Estate\Trust;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * S0.5.q — observer wiring assertion.
 *
 * Every direct-write create handler must persist via Model::create inside
 * a DB::transaction so the model's `created` lifecycle event fires (this
 * is what risk / goal / net-worth observers listen to). We assert this
 * by spying on each model's `created` Eloquent event using a fresh
 * listener registered before the handler runs.
 */
it('every direct-write handler fires the model `created` event', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $cases = [
        [SavingsAccount::class, 'create_savings_account', ['account_name' => 'Obs Test Sav', 'current_balance' => 100]],
        [InvestmentAccount::class, 'create_investment_account', ['account_name' => 'Obs Test Inv', 'current_value' => 100]],
        [DCPension::class, 'create_pension', ['pension_category' => 'dc', 'scheme_name' => 'Obs Test Pen']],
        [LifeInsurancePolicy::class, 'create_protection_policy', ['policy_type' => 'level_term', 'sum_assured' => 100]],
        [Asset::class, 'create_asset', ['asset_name' => 'Obs Test Ast', 'asset_type' => 'other', 'current_value' => 100]],
        [Liability::class, 'create_liability', ['liability_name' => 'Obs Test Lia', 'liability_type' => 'loan', 'current_balance' => 100]],
        [Gift::class, 'create_estate_gift', ['gift_date' => '2024-01-01', 'recipient' => 'X', 'gift_type' => 'pet', 'gift_value' => 100]],
        [FamilyMember::class, 'create_family_member', ['first_name' => 'Obs', 'relationship' => 'child']],
        [Trust::class, 'create_trust', ['trust_name' => 'Obs Test Trust', 'trust_type' => 'bare']],
        [BusinessInterest::class, 'create_business_interest', ['business_name' => 'Obs Test Biz', 'business_type' => 'sole_trader']],
        [Chattel::class, 'create_chattel', ['description' => 'Obs Test Chat', 'estimated_value' => 100]],
        [Goal::class, 'create_goal', ['name' => 'Obs', 'goal_type' => 'holiday', 'target_amount' => 1, 'target_date' => now()->addYear()->toDateString(), 'priority' => 'low']],
        [LifeEvent::class, 'create_life_event', ['event_name' => 'Obs', 'event_type' => 'bonus', 'event_date' => now()->addMonths(3)->toDateString(), 'estimated_amount' => 100]],
    ];

    foreach ($cases as [$model, $tool, $input]) {
        $fired = false;
        $model::created(function () use (&$fired) {
            $fired = true;
        });
        $result = app(CoordinatingAgent::class)->executeTool($tool, $input, $user);
        expect($result['success'] ?? false)->toBeTrue();
        expect($fired)->toBeTrue("{$tool} did not fire {$model}::created event");
    }
});
