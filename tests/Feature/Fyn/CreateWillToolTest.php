<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Estate\Will;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FR-M9 — create_will / update_will. Pins the handler's upsert-by-user
 * semantics, spouse_primary_beneficiary defaulting, and the update-as-
 * create fallback when no prior will exists.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('creates a Will row with the provided fields and returns its id', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_will', [
        'executor_name' => 'Alice Smith',
        'residuary_beneficiary' => 'Bob Smith',
        'guardian_for_minors' => 'Carol Smith',
        'specific_gifts' => 'My watch to Alice, car to Bob.',
    ], $user);

    expect($result['action'])->toBe('record_saved');
    expect($result['entity_type'])->toBe('will');

    $will = Will::find($result['id']);
    expect($will)->not->toBeNull();
    expect($will->has_will)->toBeTrue();
    expect($will->executor_name)->toBe('Alice Smith');
    expect($will->residuary_beneficiary)->toBe('Bob Smith');
    expect($will->guardian_for_minors)->toBe('Carol Smith');
    expect((string) $will->specific_gifts)->toContain('watch');
});

it('defaults spouse_primary_beneficiary to true for married users', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'marital_status' => 'married',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_will', [
        'executor_name' => 'Exec',
    ], $user);

    $will = Will::find($result['id']);
    expect((bool) $will->spouse_primary_beneficiary)->toBeTrue();
});

it('defaults spouse_primary_beneficiary to false for single users', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'marital_status' => 'single',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_will', [
        'executor_name' => 'Exec',
    ], $user);

    $will = Will::find($result['id']);
    expect((bool) $will->spouse_primary_beneficiary)->toBeFalse();
});

it('upserts on repeated create_will calls for the same user', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $agent = app(CoordinatingAgent::class);

    $r1 = $agent->executeTool('create_will', ['executor_name' => 'First Exec'], $user);
    $r2 = $agent->executeTool('create_will', ['executor_name' => 'Second Exec'], $user);

    expect(Will::where('user_id', $user->id)->count())->toBe(1);
    expect($r1['id'])->toBe($r2['id']);

    $will = Will::find($r2['id']);
    expect($will->executor_name)->toBe('Second Exec');
});

it('update_will falls back to create when no prior will exists', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('update_will', [
        'executor_name' => 'Brand New Exec',
    ], $user);

    expect($result['action'])->toBe('record_saved');
    $will = Will::find($result['id']);
    expect($will->executor_name)->toBe('Brand New Exec');
});
