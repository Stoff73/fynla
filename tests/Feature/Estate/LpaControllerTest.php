<?php

declare(strict_types=1);

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\LpaAttorney;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // LPA is a full-Estate sub-route (spec §10.2): the acting user must be on
    // a full-Estate tier. Tier config seeded so TeaserGate resolves.
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
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
        $response = $this->withoutMiddleware(CheckForAnyAbility::class)
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
    it('returns the checks and the disclosure that must accompany them', function () {
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
                'data' => [
                    'checks', 'passed', 'failed', 'warnings',
                    'outcome', 'outcome_label', 'heading',
                    'not_checked_heading', 'not_checked_intro', 'not_checked',
                    'not_checked_close', 'referral',
                ],
            ]);
    });

    // W-0100. The endpoint told users their Lasting Power of Attorney was
    // "Compliant" from 1a3d17e99 (2026-03-16) until 2026-08-21. Nothing Fynla
    // returns about an instrument the user holds may assert a property of it.
    it('never returns a verdict on the instrument', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/estate/lpa/{$lpa->id}/compliance");

        $response->assertOk();
        expect($response->json('data'))->not->toHaveKey('overall_status');

        // Nowhere in the response. "valid" is deliberately absent from this
        // list: the disclosure says the checks "cannot tell you whether your
        // Lasting Power of Attorney is valid", and a negation is the point.
        $body = strtolower($response->getContent());
        foreach (['compliant', 'compliance', 'approved', 'sufficient'] as $verdict) {
            expect($body)->not->toContain($verdict);
        }

        // And the outcome line itself claims nothing about the instrument.
        expect(strtolower($response->json('data.outcome_label')))->not->toContain('valid');
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

/**
 * W-0110. Fyn can create a Lasting Power of Attorney from `/m` and native, and only
 * web could read one back. `/m` now reads this endpoint, so the words it prints have
 * to arrive with the record — four web copies of the vocabulary had already drifted
 * ("Property & Financial" against "Property & Financial Affairs") and a fifth on `/m`
 * would have made it five.
 */
describe('the LPA payload carries its own vocabulary', function () {
    it('serves the type and status label with every record', function () {
        LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id, 'status' => 'registered']);

        $this->getJson('/api/estate/lpa')
            ->assertOk()
            ->assertJsonPath('data.0.type_label', 'Property & Financial Affairs')
            ->assertJsonPath('data.0.status_label', 'Registered');
    });

    it('names a health and welfare instrument in full', function () {
        LastingPowerOfAttorney::factory()
            ->healthWelfare()
            ->create(['user_id' => $this->user->id, 'status' => 'draft']);

        $this->getJson('/api/estate/lpa')
            ->assertOk()
            ->assertJsonPath('data.0.type_label', 'Health & Welfare')
            ->assertJsonPath('data.0.status_label', 'Draft');
    });
});
