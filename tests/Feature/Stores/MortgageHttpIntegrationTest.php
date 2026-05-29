<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    config(['audit.in_tests' => true]);
    $this->user = User::factory()->create(['tier' => 'tier1']);
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
    Sanctum::actingAs($this->user);
});

it('creates a mortgage via POST and records FORM audit context', function () {
    $payload = [
        'lender_name' => 'Nationwide',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000.00,
        'interest_rate' => 4.5,
        'rate_type' => 'fixed',
        'monthly_payment' => 1500.00,
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 240,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    $response = $this->postJson("/api/properties/{$this->property->id}/mortgages", $payload);

    $response->assertCreated();
    $this->assertDatabaseHas('mortgages', [
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'lender_name' => 'Nationwide',
    ]);

    $mortgage = Mortgage::where('lender_name', 'Nationwide')->first();
    $auditRow = AuditLog::where('model_type', Mortgage::class)
        ->where('model_id', $mortgage->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();
    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('form');
});

it('updates a mortgage via PUT', function () {
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'outstanding_balance' => 200000,
    ]);

    $response = $this->putJson("/api/mortgages/{$mortgage->id}", [
        'lender_name' => $mortgage->lender_name,
        'mortgage_type' => $mortgage->mortgage_type,
        'outstanding_balance' => 195000,
        'monthly_payment' => (float) $mortgage->monthly_payment,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response->assertOk();
    expect((float) $mortgage->fresh()->outstanding_balance)->toEqual(195000.00);
});

it('soft-deletes a mortgage via DELETE', function () {
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
    ]);

    $response = $this->deleteJson("/api/mortgages/{$mortgage->id}");

    $response->assertOk();
    $this->assertSoftDeleted('mortgages', ['id' => $mortgage->id]);
});

it('rejects update from non-owner (joint owner is read-only)', function () {
    $spouse = User::factory()->create(['tier' => 'tier1']);
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'joint_owner_id' => $spouse->id,
        'property_id' => $this->property->id,
        'ownership_type' => 'joint',
    ]);

    Sanctum::actingAs($spouse);
    $response = $this->putJson("/api/mortgages/{$mortgage->id}", [
        'lender_name' => $mortgage->lender_name,
        'mortgage_type' => $mortgage->mortgage_type,
        'outstanding_balance' => 999999,
        'monthly_payment' => 1500,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
    ]);

    $response->assertStatus(404);
});

it('returns 422 on invalid mortgage_type', function () {
    $response = $this->postJson("/api/properties/{$this->property->id}/mortgages", [
        'lender_name' => 'Test',
        'mortgage_type' => 'not_a_real_type',
        'outstanding_balance' => 100000,
        'monthly_payment' => 600,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    // Form-request validation will catch this BEFORE the store sees it.
    $response->assertStatus(422);
});

it('returns 403 with structured payload when free-tier mortgage cap is exceeded', function () {
    // Default user factory creates tier1 (unlimited); recreate as free-tier for this cap test.
    $freeUser = User::factory()->create(['tier' => 'free']);
    $freeProperty = Property::factory()->create(['user_id' => $freeUser->id]);
    Sanctum::actingAs($freeUser);

    // Free-tier cap is 10 per TierConfigurationSeeder; create 10 then attempt 11th.
    for ($i = 1; $i <= 10; $i++) {
        Mortgage::factory()->create([
            'user_id' => $freeUser->id,
            'property_id' => $freeProperty->id,
        ]);
    }

    $response = $this->postJson("/api/properties/{$freeProperty->id}/mortgages", [
        'lender_name' => 'Eleventh',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 100000,
        'monthly_payment' => 600,
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 240,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'error' => [
                'entity_key' => 'mortgage',
                'hard_limit' => 10,
            ],
        ]);
});
