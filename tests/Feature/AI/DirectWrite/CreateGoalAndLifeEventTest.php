<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Goal;
use App\Models\LifeEvent;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

// 0.5.o create_goal

it('create_goal persists a Goal with custom_goal_type_name when type is custom', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_goal', [
        'name' => 'Sabbatical fund',
        'goal_type' => 'custom',
        'target_amount' => 30000,
        'target_date' => now()->addYears(3)->toDateString(),
        'priority' => 'medium',
        'monthly_contribution' => 500,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('goal');

    $goal = Goal::find($result['entity_id']);
    expect($goal)->not->toBeNull();
    expect($goal->goal_name)->toBe('Sabbatical fund');
    expect($goal->goal_type)->toBe('custom');
    expect($goal->custom_goal_type_name)->toBe('Sabbatical fund');
    expect((float) $goal->target_amount)->toBe(30000.0);
});

it('create_goal persists standard goal types without custom_goal_type_name', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_goal', [
        'name' => 'House deposit',
        'goal_type' => 'home_deposit',
        'target_amount' => 50000,
        'target_date' => now()->addYears(2)->toDateString(),
        'priority' => 'high',
    ], $user);

    expect(Goal::find($result['entity_id'])->custom_goal_type_name)->toBeNull();
});

it('create_goal returns validation_failed without required fields', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $result = app(CoordinatingAgent::class)->executeTool('create_goal', [
        'name' => 'X',
    ], $user);
    expect($result['error_type'])->toBe('validation_failed');
});

it('create_goal blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $result = app(CoordinatingAgent::class)->executeTool('create_goal', [
        'name' => 'X', 'goal_type' => 'holiday',
        'target_amount' => 1, 'target_date' => now()->addYear()->toDateString(),
        'priority' => 'low',
    ], $user);
    expect($result['blocked'])->toBeTrue();
});

// 0.5.p create_life_event

it('create_life_event persists a LifeEvent', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_life_event', [
        'event_name' => 'Inheritance from grandfather',
        'event_type' => 'inheritance',
        'event_date' => now()->addMonths(6)->toDateString(),
        'estimated_amount' => 50000,
        'certainty' => 'likely',
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('life_event');

    $event = LifeEvent::find($result['entity_id']);
    expect($event)->not->toBeNull();
    expect($event->event_name)->toBe('Inheritance from grandfather');
    expect($event->event_type)->toBe('inheritance');
    expect((float) $event->amount)->toBe(50000.0);
    expect($event->certainty)->toBe('likely');
});

it('create_life_event defaults certainty to likely', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_life_event', [
        'event_name' => 'Bonus',
        'event_type' => 'bonus',
        'event_date' => now()->addMonths(3)->toDateString(),
        'estimated_amount' => 5000,
    ], $user);

    expect(LifeEvent::find($result['entity_id'])->certainty)->toBe('likely');
});

it('create_life_event blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $result = app(CoordinatingAgent::class)->executeTool('create_life_event', [
        'event_name' => 'X', 'event_type' => 'bonus',
        'event_date' => now()->addMonths(3)->toDateString(),
        'estimated_amount' => 1,
    ], $user);
    expect($result['blocked'])->toBeTrue();
});

it('goal + life_event return shapes have no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $g = app(CoordinatingAgent::class)->executeTool('create_goal', [
        'name' => 'X', 'goal_type' => 'holiday',
        'target_amount' => 1, 'target_date' => now()->addYear()->toDateString(),
        'priority' => 'low',
    ], $user);
    $e = app(CoordinatingAgent::class)->executeTool('create_life_event', [
        'event_name' => 'X', 'event_type' => 'bonus',
        'event_date' => now()->addMonths(3)->toDateString(),
        'estimated_amount' => 1,
    ], $user);

    foreach ([$g, $e] as $r) {
        expect($r)->not->toHaveKey('action');
        expect($r)->not->toHaveKey('fields');
    }
});
