<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\StatePension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('creates a state pension record with all fields', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_state_pension', [
        'forecast_annual' => 11500,
        'ni_years_completed' => 28,
        'state_pension_age' => 67,
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect($result['field_group'] ?? null)->toBe('campaign_state_pension');

    $record = StatePension::where('user_id', $user->id)->first();
    expect($record)->not->toBeNull();
    expect((float) $record->state_pension_forecast_annual)->toBe(11500.0);
    expect($record->ni_years_completed)->toBe(28);
    expect($record->state_pension_age)->toBe(67);
});

it('creates a record with forecast only', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_state_pension', [
        'forecast_annual' => 9600,
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    $record = StatePension::where('user_id', $user->id)->first();
    expect((float) $record->state_pension_forecast_annual)->toBe(9600.0);
});

it('creates a record with National Insurance years only', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_state_pension', [
        'ni_years_completed' => 15,
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    $record = StatePension::where('user_id', $user->id)->first();
    expect($record->ni_years_completed)->toBe(15);
    expect($record->state_pension_forecast_annual)->toBeNull();
});

it('updates an existing record on a second call — does not duplicate', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    app(CoordinatingAgent::class)->executeTool('capture_state_pension', [
        'forecast_annual' => 9000,
        'ni_years_completed' => 20,
    ], $user);

    app(CoordinatingAgent::class)->executeTool('capture_state_pension', [
        'forecast_annual' => 11000,
        'ni_years_completed' => 25,
    ], $user);

    expect(StatePension::where('user_id', $user->id)->count())->toBe(1);
    $record = StatePension::where('user_id', $user->id)->first();
    expect((float) $record->state_pension_forecast_annual)->toBe(11000.0);
    expect($record->ni_years_completed)->toBe(25);
});

it('leaves untouched fields unchanged on partial update', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    app(CoordinatingAgent::class)->executeTool('capture_state_pension', [
        'forecast_annual' => 11500,
        'state_pension_age' => 67,
    ], $user);

    // Second call updates only ni_years_completed
    app(CoordinatingAgent::class)->executeTool('capture_state_pension', [
        'ni_years_completed' => 30,
    ], $user);

    $record = StatePension::where('user_id', $user->id)->first();
    expect((float) $record->state_pension_forecast_annual)->toBe(11500.0); // unchanged
    expect($record->state_pension_age)->toBe(67); // unchanged
    expect($record->ni_years_completed)->toBe(30); // updated
});

it('rejects negative forecast', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_state_pension', [
        'forecast_annual' => -100,
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect(StatePension::count())->toBe(0);
});

it('rejects National Insurance years below 0', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_state_pension', [
        'ni_years_completed' => -1,
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect(StatePension::count())->toBe(0);
});

it('rejects National Insurance years above 60', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_state_pension', [
        'ni_years_completed' => 61,
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect(StatePension::count())->toBe(0);
});

it('rejects when no fields are provided', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_state_pension', [], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect(StatePension::count())->toBe(0);
});

it('blocks preview users', function () {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_state_pension', [
        'forecast_annual' => 11500,
    ], $user);

    expect($result['blocked'] ?? false)->toBeTrue();
    expect(StatePension::count())->toBe(0);
});
