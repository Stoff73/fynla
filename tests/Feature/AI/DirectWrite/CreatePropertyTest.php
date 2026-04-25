<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('create_property persists a Property row', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_property', [
        'property_type' => 'main_residence',
        'current_value' => 450000,
        'purchase_price' => 380000,
        'address_line_1' => '12 High Street',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('property');

    $property = Property::find($result['entity_id']);
    expect($property)->not->toBeNull();
    expect($property->property_type)->toBe('main_residence');
    expect((float) $property->current_value)->toBe(450000.0);
    expect($property->address_line_1)->toBe('12 High Street');
    expect($property->ownership_type)->toBe('individual');
    expect((float) $property->ownership_percentage)->toBe(100.0);
});

it('create_property auto-creates a Mortgage when has_mortgage is true', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_property', [
        'property_type' => 'main_residence',
        'current_value' => 450000,
        'has_mortgage' => true,
        'mortgage_outstanding_balance' => 200000,
        'mortgage_interest_rate' => 4.5,
        'mortgage_type' => 'repayment',
    ], $user);

    expect($result['success'])->toBeTrue();
    $mortgage = Mortgage::where('property_id', $result['entity_id'])->first();
    expect($mortgage)->not->toBeNull();
    expect($mortgage->user_id)->toBe($user->id);
    expect((float) $mortgage->outstanding_balance)->toBe(200000.0);
    expect($mortgage->mortgage_type)->toBe('repayment');
});

it('create_property auto-creates a Mortgage on legacy outstanding_mortgage field', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_property', [
        'property_type' => 'main_residence',
        'current_value' => 300000,
        'outstanding_mortgage' => 150000,
        'mortgage_rate' => 5.2,
    ], $user);

    expect($result['success'])->toBeTrue();
    $mortgage = Mortgage::where('property_id', $result['entity_id'])->first();
    expect($mortgage)->not->toBeNull();
    expect((float) $mortgage->outstanding_balance)->toBe(150000.0);
});

it('create_property defaults ownership_percentage by ownership_type', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $joint = app(CoordinatingAgent::class)->executeTool('create_property', [
        'property_type' => 'main_residence',
        'current_value' => 100,
        'ownership_type' => 'joint',
    ], $user);

    expect((float) Property::find($joint['entity_id'])->ownership_percentage)->toBe(50.0);
});

it('create_property returns validation_failed without required fields', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_property', [
        'address_line_1' => 'X',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
});

it('create_property blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = app(CoordinatingAgent::class)->executeTool('create_property', [
        'property_type' => 'main_residence',
        'current_value' => 100,
    ], $user);

    expect($result['blocked'])->toBeTrue();
});

it('create_property return shape has no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_property', [
        'property_type' => 'main_residence',
        'current_value' => 100,
    ], $user);

    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('route');
});
