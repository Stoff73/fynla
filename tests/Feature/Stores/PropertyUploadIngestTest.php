<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\Normalisers\PropertyNormaliser;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('persists a Property extraction via PropertyStore with IngestSource::UPLOAD', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

    // Mirror what DocumentProcessor::confirmExcel does for a property extraction.
    $canonical = (new PropertyNormaliser)->fromUpload([
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'property_type' => 'main_residence',
        'current_value' => 350000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $property = app(PropertyStore::class)->create($canonical, $user, IngestSource::UPLOAD);

    expect(Property::where('user_id', $user->id)->count())->toBe(1);
    expect($property->address_line_1)->toBe('5 Acacia Avenue');
    expect((string) $property->current_value)->toBe('350000.00');
});
