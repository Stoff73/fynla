<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('POST /api/properties persists a Property via PropertyStore', function () {
    $user = User::factory()->create(['tier' => 'premium']);

    $response = $this->actingAs($user)->postJson('/api/properties', [
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'current_value' => 350000,
        'country' => 'United Kingdom',
    ]);

    $response->assertCreated();
    expect(Property::where('user_id', $user->id)->count())->toBe(1);

    $property = Property::where('user_id', $user->id)->first();
    expect($property->property_type)->toBe('main_residence');
    expect($property->ownership_type)->toBe('individual');
    expect((string) $property->current_value)->toBe('350000.00');
});

it('PUT /api/properties/{id} updates a Property via PropertyStore', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 350000]);

    $response = $this->actingAs($user)->putJson("/api/properties/{$property->id}", [
        'current_value' => 425000,
    ]);

    $response->assertOk();
    expect((string) $property->fresh()->current_value)->toBe('425000.00');
});

it('DELETE /api/properties/{id} soft-deletes via PropertyStore', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/api/properties/{$property->id}");

    $response->assertOk();
    expect(Property::find($property->id))->toBeNull();
    expect(Property::withTrashed()->find($property->id))->not->toBeNull();
});

it('rejects updates from a non-owner', function () {
    $owner = User::factory()->create(['tier' => 'premium']);
    $stranger = User::factory()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($stranger)->putJson("/api/properties/{$property->id}", [
        'current_value' => 999999,
    ]);

    $response->assertStatus(404);
});
