<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\RetirementProfile;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('creates a retirement profile with both age and income', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1985-06-15']);

    $result = app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_age' => 60,
        'target_retirement_income' => 40000,
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect($result['field_group'] ?? null)->toBe('campaign_retirement_goals');

    $profile = RetirementProfile::where('user_id', $user->id)->first();
    expect($profile)->not->toBeNull();
    expect($profile->target_retirement_age)->toBe(60);
    expect((float) $profile->target_retirement_income)->toBe(40000.0);
});

it('creates a retirement profile with age only', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1980-01-01']);

    $result = app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_age' => 65,
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect(RetirementProfile::where('user_id', $user->id)->value('target_retirement_age'))->toBe(65);
});

it('updates an existing profile on a second call — does not duplicate', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1980-01-01']);

    app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_age' => 60,
        'target_retirement_income' => 30000,
    ], $user);

    app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_age' => 63,
        'target_retirement_income' => 45000,
    ], $user);

    expect(RetirementProfile::where('user_id', $user->id)->count())->toBe(1);
    $profile = RetirementProfile::where('user_id', $user->id)->first();
    expect($profile->target_retirement_age)->toBe(63);
    expect((float) $profile->target_retirement_income)->toBe(45000.0);
});

it('updates income only when an existing profile exists', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1980-01-01']);

    // Create existing profile first
    RetirementProfile::create([
        'user_id' => $user->id,
        'current_age' => 45,
        'target_retirement_age' => 65,
        'target_retirement_income' => null,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_income' => 35000,
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    $profile = RetirementProfile::where('user_id', $user->id)->first();
    expect($profile->target_retirement_age)->toBe(65); // unchanged
    expect((float) $profile->target_retirement_income)->toBe(35000.0);
});

it('returns partial when income only and no existing profile', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1980-01-01']);

    $result = app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_income' => 35000,
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect($result['partial'] ?? false)->toBeTrue();
    expect(RetirementProfile::where('user_id', $user->id)->count())->toBe(0);
});

it('rejects a retirement age below 55', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1980-01-01']);

    $result = app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_age' => 50,
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect(RetirementProfile::count())->toBe(0);
});

it('rejects a retirement age above 75', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1980-01-01']);

    $result = app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_age' => 80,
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect(RetirementProfile::count())->toBe(0);
});

it('rejects negative income', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1980-01-01']);

    $result = app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_age' => 65,
        'target_retirement_income' => -500,
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect(RetirementProfile::count())->toBe(0);
});

it('rejects when neither field is provided', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect(RetirementProfile::count())->toBe(0);
});

it('blocks preview users', function () {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_age' => 65,
    ], $user);

    expect($result['blocked'] ?? false)->toBeTrue();
    expect(RetirementProfile::count())->toBe(0);
});
