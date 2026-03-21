<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\Household;
use App\Models\LifeEvent;
use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \App\Models\TaxConfiguration::factory()->create(['is_active' => true]);
    $this->household = Household::factory()->create();
    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'monthly_expenditure' => 2000,
        'annual_employment_income' => 40000,
        'date_of_birth' => now()->subYears(35),
    ]);
});

describe('SavingsAgent goal recommendations', function () {
    it('recommends increasing contributions for behind-schedule savings goal', function () {
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 5000,
        ]);

        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Holiday Fund',
            'target_amount' => 20000,
            'current_amount' => 5000,
            'target_date' => now()->addMonths(6),
            'assigned_module' => 'savings',
            'status' => 'active',
            'monthly_contribution' => 200,
        ]);

        $agent = app(\App\Agents\SavingsAgent::class);
        $analysis = $agent->analyze($this->user->id);

        // generateRecommendations returns a flat array
        $recommendations = $agent->generateRecommendations(
            array_merge($analysis, ['user_id' => $this->user->id])
        );

        $goalRecs = collect($recommendations)
            ->filter(fn ($r) => str_contains($r['description'] ?? '', 'Holiday Fund'));

        expect($goalRecs)->not->toBeEmpty();
    });

    it('suggests emergency fund goal when no goal exists and runway is insufficient', function () {
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 1000,
        ]);

        $agent = app(\App\Agents\SavingsAgent::class);
        $analysis = $agent->analyze($this->user->id);
        $recommendations = $agent->generateRecommendations(
            array_merge($analysis, ['user_id' => $this->user->id])
        );

        $efRecs = collect($recommendations)
            ->filter(fn ($r) => str_contains($r['description'] ?? '', 'emergency fund goal'));

        expect($efRecs)->not->toBeEmpty();
    });

    it('recommends cash buffer for upcoming expense life events', function () {
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 5000,
        ]);

        LifeEvent::factory()->create([
            'user_id' => $this->user->id,
            'event_name' => 'Kitchen Renovation',
            'event_type' => 'home_improvement',
            'impact_type' => 'expense',
            'amount' => 25000,
            'expected_date' => now()->addMonths(8),
            'certainty' => 'confirmed',
            'status' => 'confirmed',
        ]);

        $agent = app(\App\Agents\SavingsAgent::class);
        $analysis = $agent->analyze($this->user->id);
        $recommendations = $agent->generateRecommendations(
            array_merge($analysis, ['user_id' => $this->user->id])
        );

        $eventRecs = collect($recommendations)
            ->filter(fn ($r) => str_contains($r['description'] ?? '', 'Kitchen Renovation'));

        expect($eventRecs)->not->toBeEmpty();
    });
});
