<?php

declare(strict_types=1);

use App\Models\Estate\Will;
use App\Models\Estate\WillDocument;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

describe('Will Builder API', function () {
    describe('GET /estate/will-builder/pre-populate', function () {
        it('returns pre-populated data for authenticated user', function () {
            $user = User::factory()->create([
                'first_name' => 'James',
                'surname' => 'Carter',
                'occupation' => 'Engineer',
            ]);

            $response = $this->actingAs($user)->getJson('/api/estate/will-builder/pre-populate');

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
            $user = User::factory()->create();

            $response = $this->actingAs($user)->getJson('/api/estate/will-builder');

            $response->assertOk()
                ->assertJson(['success' => true, 'data' => null]);
        });

        it('returns existing draft', function () {
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->getJson('/api/estate/will-builder');

            $response->assertOk()
                ->assertJson(['success' => true])
                ->assertJsonPath('data.id', $doc->id);
        });
    });

    describe('POST /estate/will-builder', function () {
        it('creates a new will document draft', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/estate/will-builder', [
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
            $user = User::factory()->create();

            $this->actingAs($user)->postJson('/api/estate/will-builder', [])
                ->assertStatus(422);
        });
    });

    describe('PUT /estate/will-builder/{id}', function () {
        it('saves step data incrementally', function () {
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->putJson("/api/estate/will-builder/{$doc->id}", [
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
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $doc = WillDocument::factory()->create(['user_id' => $otherUser->id]);

            $this->actingAs($user)->putJson("/api/estate/will-builder/{$doc->id}", [
                'step' => 'personal',
                'testator_full_name' => 'Hacker',
            ])->assertNotFound();
        });
    });

    describe('POST /estate/will-builder/{id}/complete', function () {
        it('marks a valid document as complete', function () {
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'executors' => [['name' => 'John Smith', 'address' => '10 High St']],
                'residuary_estate' => [['beneficiary_name' => 'Emily', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(40),
                'domicile_confirmed' => 'england_wales',
            ]);

            $response = $this->actingAs($user)->postJson("/api/estate/will-builder/{$doc->id}/complete");

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
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'executors' => [],
                'residuary_estate' => [],
            ]);

            $this->actingAs($user)->postJson("/api/estate/will-builder/{$doc->id}/complete")
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
            $user = User::factory()->create([
                'first_name' => 'James',
                'surname' => 'Carter',
                'spouse_id' => $spouse->id,
            ]);

            $doc = WillDocument::factory()->mirror()->create([
                'user_id' => $user->id,
                'testator_full_name' => 'James Carter',
                'residuary_estate' => [
                    ['beneficiary_name' => 'Emily Carter', 'percentage' => 100, 'substitution_beneficiary' => ''],
                ],
            ]);

            $response = $this->actingAs($user)->postJson("/api/estate/will-builder/{$doc->id}/mirror");

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
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)->deleteJson("/api/estate/will-builder/{$doc->id}")
                ->assertOk()
                ->assertJson(['success' => true]);

            expect(WillDocument::find($doc->id))->toBeNull();
            expect(WillDocument::withTrashed()->find($doc->id))->not->toBeNull();
        });

        it('prevents deleting another users document', function () {
            $user = User::factory()->create();
            $other = User::factory()->create();
            $doc = WillDocument::factory()->create(['user_id' => $other->id]);

            $this->actingAs($user)->deleteJson("/api/estate/will-builder/{$doc->id}")
                ->assertNotFound();
        });
    });

    describe('GET /estate/will-builder/{id}/validate', function () {
        it('returns validation warnings', function () {
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'executors' => [],
                'residuary_estate' => [],
            ]);

            $response = $this->actingAs($user)->getJson("/api/estate/will-builder/{$doc->id}/validate");

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => ['has_errors' => true],
                ]);
        });
    });
});
