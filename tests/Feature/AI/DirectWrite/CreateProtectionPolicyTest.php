<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\CriticalIllnessPolicy;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('create_protection_policy persists a LifeInsurancePolicy for level_term', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_protection_policy', [
        'policy_type' => 'level_term',
        'provider' => 'Aviva',
        'sum_assured' => 250000,
        'premium_amount' => 25,
        'policy_term_years' => 25,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('life_insurance_policy');

    $policy = LifeInsurancePolicy::find($result['entity_id']);
    expect($policy)->not->toBeNull();
    expect($policy->provider)->toBe('Aviva');
    expect($policy->policy_type)->toBe('level_term');
    expect((float) $policy->sum_assured)->toBe(250000.0);
    expect($policy->policy_term_years)->toBe(25);
});

it('create_protection_policy persists a CriticalIllnessPolicy for standalone_ci', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_protection_policy', [
        'policy_type' => 'standalone_ci',
        'provider' => 'Vitality',
        'sum_assured' => 150000,
        'premium_amount' => 60,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('critical_illness_policy');

    $policy = CriticalIllnessPolicy::find($result['entity_id']);
    expect($policy)->not->toBeNull();
    expect($policy->provider)->toBe('Vitality');
    expect((float) $policy->sum_assured)->toBe(150000.0);
});

it('create_protection_policy persists an IncomeProtectionPolicy', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_protection_policy', [
        'policy_type' => 'income_protection',
        'provider' => 'LV=',
        'benefit_amount' => 30000,
        'premium_amount' => 45,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('income_protection_policy');

    $policy = IncomeProtectionPolicy::find($result['entity_id']);
    expect($policy)->not->toBeNull();
    expect($policy->provider)->toBe('LV=');
    expect((float) $policy->benefit_amount)->toBe(30000.0);
});

it('create_protection_policy maps generic term to level_term', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_protection_policy', [
        'policy_type' => 'term',
        'sum_assured' => 100000,
    ], $user);

    expect(LifeInsurancePolicy::find($result['entity_id'])->policy_type)->toBe('level_term');
});

it('create_protection_policy returns validation_failed without policy_type', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_protection_policy', [
        'sum_assured' => 100000,
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
});

it('create_protection_policy blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = app(CoordinatingAgent::class)->executeTool('create_protection_policy', [
        'policy_type' => 'level_term',
        'sum_assured' => 100000,
    ], $user);

    expect($result['blocked'])->toBeTrue();
});

it('create_protection_policy return shape has no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_protection_policy', [
        'policy_type' => 'level_term',
        'sum_assured' => 100000,
    ], $user);

    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('route');
});
