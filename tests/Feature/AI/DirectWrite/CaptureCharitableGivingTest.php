<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('writes annual_charitable_donations to the user', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_charitable_donations' => null,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_charitable_giving', [
        'annual_donations' => 500,
    ], $user);

    expect($result['updated'] ?? false)->toBeTrue();
    expect((float) $user->fresh()->annual_charitable_donations)->toBe(500.0);
});

it('rejects negative amounts', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_charitable_donations' => null,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_charitable_giving', [
        'annual_donations' => -100,
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect($user->fresh()->annual_charitable_donations)->toBeNull();
});

it('overwrites previous value', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_charitable_donations' => null,
    ]);

    app(CoordinatingAgent::class)->executeTool('capture_charitable_giving', [
        'annual_donations' => 500,
    ], $user);

    app(CoordinatingAgent::class)->executeTool('capture_charitable_giving', [
        'annual_donations' => 200,
    ], $user);

    expect((float) $user->fresh()->annual_charitable_donations)->toBe(200.0);
});

it('blocks preview users', function () {
    $user = User::factory()->create([
        'is_preview_user' => true,
        'annual_charitable_donations' => null,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_charitable_giving', [
        'annual_donations' => 500,
    ], $user);

    expect($result['blocked'] ?? false)->toBeTrue();
    expect($user->fresh()->annual_charitable_donations)->toBeNull();
});
