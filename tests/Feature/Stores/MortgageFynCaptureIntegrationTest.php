<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AuditLog;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    config(['audit.in_tests' => true]);
    $this->user = User::factory()->create(['tier' => 'tier1']);
    $this->property = Property::factory()->create([
        'user_id' => $this->user->id,
        'address_line_1' => '123 Test Street',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);
});

it('creates a mortgage via Fyn AI handleCreateMortgage with FYN_AI audit context', function () {
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleCreateMortgage');
    $method->setAccessible(true);

    $input = [
        'outstanding_balance' => 180000,
        'interest_rate' => 5.25,
        'mortgage_type' => 'repayment',
        'monthly_payment' => 1100,
        'remaining_term_months' => 300,
        'lender_name' => 'Halifax',
        // No property_address_hint — falls back to user's only property.
    ];

    $result = $method->invoke($agent, $input, $this->user, false);

    expect($result['success'] ?? false)->toBeTrue();
    expect($result['entity_type'] ?? null)->toBe('mortgage');

    $this->assertDatabaseHas('mortgages', [
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'lender_name' => 'Halifax',
    ]);

    $mortgage = Mortgage::where('lender_name', 'Halifax')->first();
    $auditRow = AuditLog::where('model_type', Mortgage::class)
        ->where('model_id', $mortgage->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();
    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('rejects preview-user mortgage creates via Fyn', function () {
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleCreateMortgage');
    $method->setAccessible(true);

    $result = $method->invoke($agent, ['outstanding_balance' => 100000], $this->user, true);

    expect($result['blocked'] ?? false)->toBeTrue();
});

it('returns property_match_failed when hint matches no property', function () {
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleCreateMortgage');
    $method->setAccessible(true);

    // Create a second property so count > 1 (triggers property_match_failed not missing_property)
    Property::factory()->create(['user_id' => $this->user->id, 'address_line_1' => '99 Other Road']);

    $input = [
        'outstanding_balance' => 100000,
        'property_address_hint' => 'NoSuchAddress',
    ];

    $result = $method->invoke($agent, $input, $this->user, false);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('property_match_failed');
});

it('matches property by fuzzy address_line_1 hint', function () {
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleCreateMortgage');
    $method->setAccessible(true);

    $otherProperty = Property::factory()->create([
        'user_id' => $this->user->id,
        'address_line_1' => '99 Other Road',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $input = [
        'outstanding_balance' => 250000,
        'lender_name' => 'Santander',
        'property_address_hint' => 'Other',
    ];

    $result = $method->invoke($agent, $input, $this->user, false);

    expect($result['success'] ?? false)->toBeTrue();
    $this->assertDatabaseHas('mortgages', [
        'user_id' => $this->user->id,
        'property_id' => $otherProperty->id,
    ]);
});
