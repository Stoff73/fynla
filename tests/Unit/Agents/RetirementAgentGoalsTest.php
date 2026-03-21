<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\Household;
use App\Models\RetirementProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \App\Models\TaxConfiguration::factory()->create(['is_active' => true]);
    $this->household = Household::factory()->create();
    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'date_of_birth' => now()->subYears(55),
        'marital_status' => 'single',
        'annual_employment_income' => 50000,
    ]);
    RetirementProfile::factory()->create([
        'user_id' => $this->user->id,
        'current_age' => 55,
        'target_retirement_age' => 60,
        'target_retirement_income' => 30000,
    ]);
});

describe('RetirementAgent goal integration', function () {
    it('identifies goals that extend beyond retirement age', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Grandchildren Fund',
            'target_amount' => 100000,
            'current_amount' => 20000,
            'target_date' => now()->addYears(10),
            'status' => 'active',
            'monthly_contribution' => 500,
        ]);

        $agent = app(\App\Agents\RetirementAgent::class);
        $result = $agent->analyze($this->user->id);

        expect($result['data'])->toHaveKey('post_retirement_goals');
        expect($result['data']['post_retirement_goals'])->not->toBeEmpty();
        expect($result['data']['post_retirement_goals'][0]['name'])->toBe('Grandchildren Fund');
    });
});
