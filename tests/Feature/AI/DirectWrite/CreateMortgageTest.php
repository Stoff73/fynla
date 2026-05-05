<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('create_mortgage links to the matching property by address hint', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'address_line_1' => '12 High Street',
        'postcode' => 'BS1 1AA',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_mortgage', [
        'property_address_hint' => 'High Street',
        'lender_name' => 'Halifax',
        'outstanding_balance' => 200000,
        'interest_rate' => 4.5,
        'mortgage_type' => 'repayment',
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('mortgage');

    $mortgage = Mortgage::find($result['entity_id']);
    expect($mortgage)->not->toBeNull();
    expect($mortgage->property_id)->toBe($property->id);
    expect($mortgage->lender_name)->toBe('Halifax');
    expect((float) $mortgage->outstanding_balance)->toBe(200000.0);
});

it('create_mortgage falls back to user single property when no hint', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $property = Property::factory()->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('create_mortgage', [
        'lender_name' => 'Nationwide',
        'outstanding_balance' => 100000,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect(Mortgage::find($result['entity_id'])->property_id)->toBe($property->id);
});

it('create_mortgage errors when no property exists', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_mortgage', [
        'outstanding_balance' => 100000,
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect(Mortgage::count())->toBe(0);
});

it('create_mortgage returns validation_failed without outstanding_balance', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    Property::factory()->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('create_mortgage', [
        'lender_name' => 'X',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
});

it('create_mortgage blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = app(CoordinatingAgent::class)->executeTool('create_mortgage', [
        'outstanding_balance' => 100000,
    ], $user);

    expect($result['blocked'])->toBeTrue();
});

it('create_mortgage return shape has no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    Property::factory()->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('create_mortgage', [
        'outstanding_balance' => 100000,
    ], $user);

    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('route');
});
