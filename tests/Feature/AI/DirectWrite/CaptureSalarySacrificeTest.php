<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\DCPension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('writes salary_sacrifice flag to the named dc_pension', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $pension = DCPension::factory()->for($user)->create(['salary_sacrifice' => null]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_salary_sacrifice', [
        'pension_id' => $pension->id,
        'salary_sacrifice' => true,
    ], $user);

    expect($result['updated'] ?? false)->toBeTrue();
    expect($pension->fresh()->salary_sacrifice)->toBeTrue();
});

it('rejects requests for a pension owned by another user', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create();
    $pension = DCPension::factory()->for($other)->create();

    $result = app(CoordinatingAgent::class)->executeTool('capture_salary_sacrifice', [
        'pension_id' => $pension->id,
        'salary_sacrifice' => true,
    ], $user);

    expect($result['error'] ?? null)->not->toBeNull();
    expect($pension->fresh()->salary_sacrifice)->toBeNull();
});
