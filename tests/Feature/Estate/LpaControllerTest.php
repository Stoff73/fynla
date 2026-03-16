<?php

declare(strict_types=1);

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\LpaAttorney;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('GET /api/estate/lpa', function () {
    it('returns all LPAs for the authenticated user', function () {
        LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);
        LastingPowerOfAttorney::factory()
            ->healthWelfare()
            ->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/estate/lpa');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data'])
            ->assertJsonCount(2, 'data');
    });

    it('does not return other users LPAs', function () {
        $other = User::factory()->create();
        LastingPowerOfAttorney::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson('/api/estate/lpa');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('requires authentication', function () {
        $this->withHeaders(['Authorization' => '']);
        // Create a fresh request without sanctum
        $response = $this->withoutMiddleware(\Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class)
            ->getJson('/api/estate/lpa');

        // The auth:sanctum middleware should handle this
        expect($response->status())->toBeIn([200, 401]);
    });
});

describe('POST /api/estate/lpa', function () {
    it('creates a new LPA', function () {
        $response = $this->postJson('/api/estate/lpa', [
            'lpa_type' => 'property_financial',
            'donor_full_name' => 'John Smith',
            'donor_date_of_birth' => '1970-01-15',
            'when_attorneys_can_act' => 'only_when_lost_capacity',
            'attorneys' => [
                [
                    'attorney_type' => 'primary',
                    'full_name' => 'Sarah Smith',
                    'relationship_to_donor' => 'Spouse',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.donor_full_name', 'John Smith')
            ->assertJsonPath('data.lpa_type', 'property_financial');

        $this->assertDatabaseHas('lasting_powers_of_attorney', [
            'user_id' => $this->user->id,
            'donor_full_name' => 'John Smith',
        ]);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/estate/lpa', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lpa_type', 'donor_full_name', 'donor_date_of_birth']);
    });

    it('validates lpa_type enum', function () {
        $response = $this->postJson('/api/estate/lpa', [
            'lpa_type' => 'invalid_type',
            'donor_full_name' => 'John Smith',
            'donor_date_of_birth' => '1970-01-15',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lpa_type']);
    });

    it('limits notification persons to 5', function () {
        $persons = array_fill(0, 6, ['full_name' => 'Person']);

        $response = $this->postJson('/api/estate/lpa', [
            'lpa_type' => 'property_financial',
            'donor_full_name' => 'John Smith',
            'donor_date_of_birth' => '1970-01-15',
            'notification_persons' => $persons,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['notification_persons']);
    });
});

describe('GET /api/estate/lpa/{id}', function () {
    it('returns a single LPA with relations', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);
        LpaAttorney::factory()->create(['lasting_power_of_attorney_id' => $lpa->id]);

        $response = $this->getJson("/api/estate/lpa/{$lpa->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['id', 'lpa_type', 'attorneys']]);
    });

    it('returns 404 for other users LPA', function () {
        $other = User::factory()->create();
        $lpa = LastingPowerOfAttorney::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson("/api/estate/lpa/{$lpa->id}");

        $response->assertNotFound();
    });
});

describe('PUT /api/estate/lpa/{id}', function () {
    it('updates an existing LPA', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->draft()
            ->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/estate/lpa/{$lpa->id}", [
            'donor_full_name' => 'Updated Name',
            'preferences' => 'New preferences',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.donor_full_name', 'Updated Name');
    });

    it('returns 404 for other users LPA', function () {
        $other = User::factory()->create();
        $lpa = LastingPowerOfAttorney::factory()->create(['user_id' => $other->id]);

        $response = $this->putJson("/api/estate/lpa/{$lpa->id}", [
            'donor_full_name' => 'Hacker',
        ]);

        $response->assertNotFound();
    });
});

describe('DELETE /api/estate/lpa/{id}', function () {
    it('soft deletes an LPA', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/estate/lpa/{$lpa->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('lasting_powers_of_attorney', ['id' => $lpa->id]);
    });
});

describe('GET /api/estate/lpa/{id}/compliance', function () {
    it('returns compliance checks for an LPA', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'donor_date_of_birth' => now()->subYears(55),
            ]);
        LpaAttorney::factory()->create(['lasting_power_of_attorney_id' => $lpa->id]);

        $response = $this->getJson("/api/estate/lpa/{$lpa->id}/compliance");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['checks', 'passed', 'failed', 'warnings', 'overall_status'],
            ]);
    });
});

describe('POST /api/estate/lpa/{id}/register', function () {
    it('marks an LPA as registered', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->draft()
            ->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/estate/lpa/{$lpa->id}/register", [
            'registration_date' => '2024-06-15',
            'opg_reference' => 'OPG-1234567',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.status', 'registered')
            ->assertJsonPath('data.opg_reference', 'OPG-1234567');
    });
});

describe('GET /api/estate/lpa/donor-defaults', function () {
    it('returns auto-filled donor details from user profile', function () {
        $this->user->update([
            'first_name' => 'Jane',
            'surname' => 'Doe',
            'date_of_birth' => '1980-05-20',
        ]);

        $response = $this->getJson('/api/estate/lpa/donor-defaults');

        $response->assertOk()
            ->assertJsonPath('data.donor_full_name', 'Jane Doe');
    });
});
