<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\FamilyMember;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('create_family_member persists a child FamilyMember', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'surname' => 'Carter',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Lily',
        'relationship' => 'child',
        'date_of_birth' => '2018-05-12',
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('family_member');

    $fm = FamilyMember::find($result['entity_id']);
    expect($fm)->not->toBeNull();
    expect($fm->first_name)->toBe('Lily');
    expect($fm->last_name)->toBe('Carter'); // surname defaulted from user
    expect($fm->relationship)->toBe('child');
    expect($fm->is_dependent)->toBeTrue();
});

it('create_family_member infers education_status from age for child', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $sevenYearsAgo = now()->subYears(7)->toDateString();

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Tom',
        'relationship' => 'child',
        'date_of_birth' => $sevenYearsAgo,
    ], $user);

    expect(FamilyMember::find($result['entity_id'])->education_status)->toBe('primary');
});

it('create_family_member maps step_child to child with mapping note', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Sam',
        'relationship' => 'step_child',
    ], $user);

    $fm = FamilyMember::find($result['entity_id']);
    expect($fm->relationship)->toBe('child');
    expect($fm->notes)->toContain('Step child');
});

it('create_family_member persists a spouse without email', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Emily',
        'surname' => 'Carter',
        'relationship' => 'spouse',
    ], $user);

    expect($result['success'])->toBeTrue();
    $fm = FamilyMember::find($result['entity_id']);
    expect($fm->relationship)->toBe('spouse');
    expect($fm->first_name)->toBe('Emily');
});

it('create_family_member returns validation_failed without first_name', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'relationship' => 'child',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
});

it('create_family_member blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'X', 'relationship' => 'child',
    ], $user);
    expect($result['blocked'])->toBeTrue();
});

it('create_family_member return shape has no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'X', 'relationship' => 'child',
    ], $user);
    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
});
