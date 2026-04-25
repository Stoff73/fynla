<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Estate\Trust;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('create_trust persists a Trust row with relevant_property_trust derived', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'John',
        'surname' => 'Doe',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_trust', [
        'trust_name' => 'Doe Family Discretionary Trust',
        'trust_type' => 'discretionary',
        'initial_value' => 100000,
        'current_value' => 110000,
        'beneficiaries' => 'my children',
        'trustees' => 'my brother',
        'purpose' => 'tax planning',
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('trust');

    $trust = Trust::find($result['entity_id']);
    expect($trust)->not->toBeNull();
    expect($trust->trust_name)->toBe('Doe Family Discretionary Trust');
    expect($trust->trust_type)->toBe('discretionary');
    expect((float) $trust->initial_value)->toBe(100000.0);
    expect((float) $trust->current_value)->toBe(110000.0);
    expect($trust->is_relevant_property_trust)->toBeTrue();
    expect($trust->settlor)->toBe('John Doe'); // defaulted from user
});

it('create_trust defaults current_value to initial_value when only one supplied', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_trust', [
        'trust_name' => 'Bare Trust',
        'trust_type' => 'bare',
        'initial_value' => 25000,
    ], $user);

    expect((float) Trust::find($result['entity_id'])->current_value)->toBe(25000.0);
});

it('create_trust marks discretionary and accumulation_maintenance as relevant_property', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    foreach (['discretionary', 'accumulation_maintenance'] as $type) {
        $result = app(CoordinatingAgent::class)->executeTool('create_trust', [
            'trust_name' => "Trust {$type}",
            'trust_type' => $type,
        ], $user);
        expect(Trust::find($result['entity_id'])->is_relevant_property_trust)->toBeTrue();
    }

    foreach (['bare', 'interest_in_possession', 'life_insurance'] as $type) {
        $result = app(CoordinatingAgent::class)->executeTool('create_trust', [
            'trust_name' => "Trust {$type}",
            'trust_type' => $type,
        ], $user);
        expect(Trust::find($result['entity_id'])->is_relevant_property_trust)->toBeFalse();
    }
});

it('create_trust returns validation_failed without trust_name', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_trust', [
        'trust_type' => 'bare',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
});

it('create_trust blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $result = app(CoordinatingAgent::class)->executeTool('create_trust', [
        'trust_name' => 'X', 'trust_type' => 'bare',
    ], $user);
    expect($result['blocked'])->toBeTrue();
});

it('create_trust return shape has no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $result = app(CoordinatingAgent::class)->executeTool('create_trust', [
        'trust_name' => 'X', 'trust_type' => 'bare',
    ], $user);
    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
});
