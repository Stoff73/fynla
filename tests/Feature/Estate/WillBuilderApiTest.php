<?php

declare(strict_types=1);

use App\Models\Estate\Will;
use App\Models\Estate\WillDocument;
use App\Models\User;
use App\Services\Estate\WillTypePolicy;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // Will Builder is a full-Estate sub-route (spec §10.2): the acting user
    // must be on a full-Estate tier. Tier config seeded so TeaserGate resolves.
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * A premium, reciprocally-linked married pair — the shape W-0019 governs.
 *
 * @return array{0: User, 1: User}
 */
function premiumMarriedCouple(): array
{
    $spouse = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'first_name' => 'Sarah',
        'middle_name' => null,
        'surname' => 'Jones',
        'marital_status' => 'married',
    ]);

    $user = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'first_name' => 'David',
        'middle_name' => null,
        'surname' => 'Jones',
        'marital_status' => 'married',
        'spouse_id' => $spouse->id,
    ]);

    $spouse->update(['spouse_id' => $user->id]);

    return [$user->fresh(), $spouse->fresh()];
}

describe('Will Builder API', function () {
    describe('GET /estate/will-builder/pre-populate', function () {
        it('returns pre-populated data for authenticated user', function () {
            // middle_name=null override prevents the 30% factory flake where
            // fake()->optional(0.3)->firstName() inserts a middle name and
            // breaks the full_name='James Carter' assertion.
            $user = User::factory()->withActivePremiumSubscription()->create([
                'first_name' => 'James',
                'middle_name' => null,
                'surname' => 'Carter',
                'occupation' => 'Engineer',
                'tier' => 'premium',
            ]);

            $response = $this->actingAs($user, 'sanctum')->getJson('/api/estate/will-builder/pre-populate');

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'testator' => [
                            'full_name' => 'James Carter',
                            'occupation' => 'Engineer',
                        ],
                    ],
                ]);
        });

        it('returns 401 for unauthenticated users', function () {
            $this->getJson('/api/estate/will-builder/pre-populate')
                ->assertUnauthorized();
        });
    });

    describe('GET /estate/will-builder', function () {
        it('returns null when no draft exists', function () {
            $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

            $response = $this->actingAs($user, 'sanctum')->getJson('/api/estate/will-builder');

            $response->assertOk()
                ->assertJson(['success' => true, 'data' => null]);
        });

        it('returns existing draft', function () {
            $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
            $doc = WillDocument::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user, 'sanctum')->getJson('/api/estate/will-builder');

            $response->assertOk()
                ->assertJson(['success' => true])
                ->assertJsonPath('data.id', $doc->id);
        });
    });

    describe('POST /estate/will-builder', function () {
        it('creates a new will document draft', function () {
            $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

            $response = $this->actingAs($user, 'sanctum')->postJson('/api/estate/will-builder', [
                'will_type' => 'simple',
                'testator_full_name' => 'James Carter',
                'domicile_confirmed' => 'england_wales',
            ]);

            $response->assertCreated()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'will_type' => 'simple',
                        'status' => 'draft',
                        'testator_full_name' => 'James Carter',
                    ],
                ]);

            $this->assertDatabaseHas('will_documents', [
                'user_id' => $user->id,
                'will_type' => 'simple',
                'testator_full_name' => 'James Carter',
            ]);
        });

        it('validates required fields', function () {
            $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

            $this->actingAs($user, 'sanctum')->postJson('/api/estate/will-builder', [])
                ->assertStatus(422);
        });
    });

    describe('PUT /estate/will-builder/{id}', function () {
        it('saves step data incrementally', function () {
            $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
            $doc = WillDocument::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user, 'sanctum')->putJson("/api/estate/will-builder/{$doc->id}", [
                'step' => 'executors',
                'executors' => [
                    ['name' => 'John Smith', 'address' => '10 High St', 'relationship' => 'Brother', 'phone' => '07700900000'],
                ],
            ]);

            $response->assertOk()
                ->assertJson(['success' => true]);

            $doc->refresh();
            expect($doc->executors)->toHaveCount(1);
            expect($doc->executors[0]['name'])->toBe('John Smith');
        });

        it('prevents access to another users document', function () {
            $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
            $otherUser = User::factory()->create();
            $doc = WillDocument::factory()->create(['user_id' => $otherUser->id]);

            $this->actingAs($user, 'sanctum')->putJson("/api/estate/will-builder/{$doc->id}", [
                'step' => 'personal',
                'testator_full_name' => 'Hacker',
            ])->assertNotFound();
        });
    });

    describe('POST /estate/will-builder/{id}/complete', function () {
        it('marks a valid document as complete', function () {
            $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'executors' => [['name' => 'John Smith', 'address' => '10 High St']],
                'residuary_estate' => [['beneficiary_name' => 'Emily', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(40),
                'domicile_confirmed' => 'england_wales',
            ]);

            $response = $this->actingAs($user, 'sanctum')->postJson("/api/estate/will-builder/{$doc->id}/complete");

            $response->assertOk()
                ->assertJson(['success' => true]);

            $doc->refresh();
            expect($doc->status)->toBe('complete');

            // Will table should be synced
            $will = Will::where('user_id', $user->id)->first();
            expect($will->has_will)->toBeTrue();
            expect($will->will_document_id)->toBe($doc->id);
        });

        it('rejects completion with validation errors', function () {
            $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'executors' => [],
                'residuary_estate' => [],
            ]);

            $this->actingAs($user, 'sanctum')->postJson("/api/estate/will-builder/{$doc->id}/complete")
                ->assertStatus(422);
        });
    });

    describe('POST /estate/will-builder/{id}/mirror', function () {
        it('generates a mirror will for spouse', function () {
            $spouse = User::factory()->create([
                'first_name' => 'Emily',
                'middle_name' => null,
                'surname' => 'Carter',
            ]);
            $user = User::factory()->withActivePremiumSubscription()->create([
                'first_name' => 'James',
                'surname' => 'Carter',
                'spouse_id' => $spouse->id,
                'tier' => 'premium',
            ]);
            // W-0350 — reciprocal, as the mirror will is written into that account.
            $spouse->update(['spouse_id' => $user->id]);

            $doc = WillDocument::factory()->mirror()->create([
                'user_id' => $user->id,
                'testator_full_name' => 'James Carter',
                'residuary_estate' => [
                    ['beneficiary_name' => 'Emily Carter', 'percentage' => 100, 'substitution_beneficiary' => ''],
                ],
            ]);

            $response = $this->actingAs($user, 'sanctum')->postJson("/api/estate/will-builder/{$doc->id}/mirror");

            $response->assertOk()
                ->assertJson(['success' => true]);

            // Mirror document should exist
            $mirror = WillDocument::where('user_id', $spouse->id)->first();
            expect($mirror)->not->toBeNull();
            expect($mirror->testator_full_name)->toBe('Emily Carter');
            expect($mirror->residuary_estate[0]['beneficiary_name'])->toBe('James Carter');
        });
    });

    describe('DELETE /estate/will-builder/{id}', function () {
        it('soft-deletes a draft', function () {
            $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
            $doc = WillDocument::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user, 'sanctum')->deleteJson("/api/estate/will-builder/{$doc->id}")
                ->assertOk()
                ->assertJson(['success' => true]);

            expect(WillDocument::find($doc->id))->toBeNull();
            expect(WillDocument::withTrashed()->find($doc->id))->not->toBeNull();
        });

        it('prevents deleting another users document', function () {
            $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
            $other = User::factory()->create();
            $doc = WillDocument::factory()->create(['user_id' => $other->id]);

            $this->actingAs($user, 'sanctum')->deleteJson("/api/estate/will-builder/{$doc->id}")
                ->assertNotFound();
        });
    });

    describe('GET /estate/will-builder/{id}/validate', function () {
        it('returns validation warnings', function () {
            $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'executors' => [],
                'residuary_estate' => [],
            ]);

            $response = $this->actingAs($user, 'sanctum')->getJson("/api/estate/will-builder/{$doc->id}/validate");

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => ['has_errors' => true],
                ]);
        });
    });
    describe('married users are offered mirror wills only (W-0019)', function () {
        it('refuses to create a simple will, and names a solicitor', function () {
            [$user] = premiumMarriedCouple();

            $response = $this->actingAs($user, 'sanctum')->postJson('/api/estate/will-builder', [
                'will_type' => 'simple',
                'testator_full_name' => 'David Jones',
            ]);

            $response->assertStatus(422)
                ->assertJson(['success' => false])
                ->assertJsonPath('data.refusal_heading', WillTypePolicy::REFUSAL_HEADING);

            expect($response->json('message'))->toContain('solicitor');
            expect(WillDocument::where('user_id', $user->id)->count())->toBe(0);
        });

        it('creates the mirror will it does offer', function () {
            [$user] = premiumMarriedCouple();

            $this->actingAs($user, 'sanctum')->postJson('/api/estate/will-builder', [
                'will_type' => 'mirror',
                'testator_full_name' => 'David Jones',
            ])->assertCreated();
        });

        it('refuses a switch to a simple will after the draft exists', function () {
            [$user] = premiumMarriedCouple();

            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'mirror',
            ]);

            $this->actingAs($user, 'sanctum')
                ->putJson("/api/estate/will-builder/{$doc->id}", [
                    'step' => 'intro',
                    'will_type' => 'simple',
                ])
                ->assertStatus(422);

            expect($doc->fresh()->will_type)->toBe('mirror');
        });

        it('leaves an unmarried user\'s simple will alone', function () {
            $user = User::factory()->withActivePremiumSubscription()->create([
                'tier' => 'premium',
                'marital_status' => 'single',
                'spouse_id' => null,
            ]);

            $this->actingAs($user, 'sanctum')->postJson('/api/estate/will-builder', [
                'will_type' => 'simple',
                'testator_full_name' => 'John Morgan',
            ])->assertCreated();
        });

        it('hands every client the same policy through pre-populate', function () {
            [$user] = premiumMarriedCouple();

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/estate/will-builder/pre-populate')
                ->assertOk()
                ->assertJsonPath('data.will_type_policy.married', true)
                ->assertJsonPath('data.will_type_policy.allowed_will_types', ['mirror'])
                ->assertJsonPath('data.will_type_policy.refusal', WillTypePolicy::REFUSAL_MARRIED);
        });

        it('tells a married user with no partner account they cannot build here', function () {
            $user = User::factory()->withActivePremiumSubscription()->create([
                'tier' => 'premium',
                'marital_status' => 'married',
                'spouse_id' => null,
            ]);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/estate/will-builder/pre-populate')
                ->assertOk()
                ->assertJsonPath('data.will_type_policy.can_build', false)
                ->assertJsonPath('data.will_type_policy.refusal', WillTypePolicy::REFUSAL_NO_MIRROR_PARTNER);

            $this->actingAs($user, 'sanctum')->postJson('/api/estate/will-builder', [
                'will_type' => 'mirror',
                'testator_full_name' => 'David Jones',
            ])->assertStatus(422);
        });
    });
});
