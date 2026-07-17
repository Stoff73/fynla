<?php

declare(strict_types=1);

use App\Models\AuditLog;
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
    config(['audit.in_tests' => true]);
});

it('persists a Property via the Fyn capture path with IngestSource::FYN_AI', function () {
    $user = User::factory()->create(['tier' => 'premium']);

    // Mirror what CoordinatingAgent::handleCreateProperty does after the
    // Fyn tool-call validator passes:
    $canonical = (new PropertyNormaliser)->fromFyn([
        'address' => '5 Acacia Avenue',
        'property_type' => 'main_residence',
        'value' => 350000,
        'ownership_type' => 'individual',
    ]);

    $property = app(PropertyStore::class)->create($canonical, $user, IngestSource::FYN_AI);

    expect(Property::where('user_id', $user->id)->count())->toBe(1);
    expect($property->address_line_1)->toBe('5 Acacia Avenue');
    expect((string) $property->current_value)->toBe('350000.00');

    $auditRow = AuditLog::where('model_type', Property::class)
        ->where('model_id', $property->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('persists Fyn-supplied monthly costs and tenant fields via fromFyn → PropertyStore', function () {
    // Regression coverage for PR 3 review finding — XaiToolDefinitions exposes
    // 9 monthly_* fields + tenant + managing-agent strings to Grok. fromFyn
    // must whitelist them or they're silently stripped before reaching the
    // store (PropertyStore::validateCanonical accepts them — the gap was
    // purely in the normaliser).
    $user = User::factory()->create(['tier' => 'premium']);

    $canonical = (new PropertyNormaliser)->fromFyn([
        'address_line_1' => '12 Tenant Road',
        'city' => 'Manchester',
        'postcode' => 'M1 1AA',
        'property_type' => 'buy_to_let',
        'current_value' => 250000,
        'monthly_rental_income' => 1500,
        'monthly_council_tax' => 150,
        'monthly_gas' => 80,
        'monthly_electricity' => 60,
        'monthly_water' => 30,
        'monthly_building_insurance' => 25,
        'monthly_contents_insurance' => 15,
        'monthly_service_charge' => 100,
        'monthly_maintenance_reserve' => 50,
        'other_monthly_costs' => 20,
        'managing_agent_fee' => 10,
        'tenant_name' => 'Alice Renter',
        'tenant_email' => 'alice@example.com',
        'managing_agent_name' => 'Jane Smith',
        'managing_agent_company' => 'Smith Lettings Ltd',
        'managing_agent_email' => 'jane@smithlettings.com',
        'managing_agent_phone' => '0161 555 0100',
    ]);

    $property = app(PropertyStore::class)->create($canonical, $user, IngestSource::FYN_AI);
    $fresh = $property->fresh();

    expect((string) $fresh->monthly_council_tax)->toBe('150.00');
    expect((string) $fresh->monthly_gas)->toBe('80.00');
    expect((string) $fresh->monthly_electricity)->toBe('60.00');
    expect((string) $fresh->monthly_water)->toBe('30.00');
    expect((string) $fresh->monthly_building_insurance)->toBe('25.00');
    expect((string) $fresh->monthly_contents_insurance)->toBe('15.00');
    expect((string) $fresh->monthly_service_charge)->toBe('100.00');
    expect((string) $fresh->monthly_maintenance_reserve)->toBe('50.00');
    expect((string) $fresh->other_monthly_costs)->toBe('20.00');
    expect((string) $fresh->managing_agent_fee)->toBe('10.00');
    expect($fresh->tenant_name)->toBe('Alice Renter');
    expect($fresh->tenant_email)->toBe('alice@example.com');
    expect($fresh->managing_agent_name)->toBe('Jane Smith');
    expect($fresh->managing_agent_company)->toBe('Smith Lettings Ltd');
    expect($fresh->managing_agent_email)->toBe('jane@smithlettings.com');
    expect($fresh->managing_agent_phone)->toBe('0161 555 0100');
});
