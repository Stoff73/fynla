<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('sets marriage_allowance_eligible=true and mode=single_earner_couple when spouse does not work', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married']);

    $result = app(CoordinatingAgent::class)->executeTool('capture_spouse_work_status', [
        'spouse_works' => false,
    ], $user);

    expect($result['updated'] ?? false)->toBeTrue();
    $user->refresh();
    expect($user->marriage_allowance_eligible)->toBeTrue();
    expect($user->household_calculation_mode)->toBe('single_earner_couple');
});

it('sets marriage_allowance_eligible=false and mode=dual_earner when spouse works', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married']);

    $result = app(CoordinatingAgent::class)->executeTool('capture_spouse_work_status', [
        'spouse_works' => true,
    ], $user);

    expect($result['updated'] ?? false)->toBeTrue();
    $user->refresh();
    expect($user->marriage_allowance_eligible)->toBeFalse();
    expect($user->household_calculation_mode)->toBe('dual_earner');
});
