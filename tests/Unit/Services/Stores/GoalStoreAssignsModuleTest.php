<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\GoalStore;
use App\Services\Stores\IngestSource;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * Both write paths land here: the form assigns the module in its controller,
 * Fyn's create_goal did not, so an emergency fund goal Fyn created sat outside
 * every module bucket (live 2026-09-09). The store now assigns it once for all.
 */
it('assigns the module from the goal type when the caller gives none', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true]);

    $goal = app(GoalStore::class)->create([
        'goal_name' => 'Emergency Fund',
        'goal_type' => 'emergency_fund',
        'target_amount' => 7500,
        'current_amount' => 0,
        'target_date' => now()->addMonths(15)->toDateString(),
        'status' => 'active',
        'priority' => 'high',
        'ownership_type' => 'individual',
    ], $user, IngestSource::FYN_AI);

    expect($goal->assigned_module)->toBe('savings');
});

it('keeps a module the caller chose', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true]);

    $goal = app(GoalStore::class)->create([
        'goal_name' => 'House',
        'goal_type' => 'home_deposit',
        'target_amount' => 40000,
        'current_amount' => 0,
        'target_date' => now()->addYears(4)->toDateString(),
        'status' => 'active',
        'priority' => 'high',
        'ownership_type' => 'individual',
        'assigned_module' => 'investment',
    ], $user, IngestSource::FYN_AI);

    expect($goal->assigned_module)->toBe('investment');
});
