<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a household
    $this->household = Household::factory()->create();

    // Create a test user
    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
    ]);

    // Create some family members
    $this->child1 = FamilyMember::factory()->create([
        'user_id' => $this->user->id,
        'relationship' => 'child',
        'name' => 'Child One',
        'date_of_birth' => '2015-03-10',
        'is_dependent' => true,
    ]);

    $this->child2 = FamilyMember::factory()->create([
        'user_id' => $this->user->id,
        'relationship' => 'child',
        'name' => 'Child Two',
        'date_of_birth' => '2018-07-22',
        'is_dependent' => true,
    ]);

    // Authenticate as this user
    $this->actingAs($this->user, 'sanctum');
});

describe('GET /api/user/family-members', function () {
    it('returns all family members for authenticated user', function () {
        $response = $this->getJson('/api/user/family-members');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'family_members',
                    'count',
                ],
            ]);

        expect($response->json('data.family_members'))->toHaveCount(2);
        expect($response->json('data.count'))->toBe(2);
        expect($response->json('data.family_members.0.name'))->toBe('Child One');
        expect($response->json('data.family_members.1.name'))->toBe('Child Two');
    });

    it('returns empty array when user has no family members', function () {
        // Delete existing family members
        FamilyMember::where('user_id', $this->user->id)->delete();

        $response = $this->getJson('/api/user/family-members');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'family_members' => [],
                    'count' => 0,
                ],
            ]);
    });

    it('requires authentication', function () {
        // Create a new test instance without authentication
        $this->app = $this->createApplication();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/user/family-members');

        $response->assertStatus(401);
    });
});

describe('GET /api/user/family-members/{id}', function () {
    it('returns a specific family member', function () {
        $response = $this->getJson("/api/user/family-members/{$this->child1->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'family_member' => [
                        'id' => $this->child1->id,
                        'name' => 'Child One',
                        'relationship' => 'child',
                    ],
                ],
            ]);
    });

    it('returns 404 for non-existent family member', function () {
        $response = $this->getJson('/api/user/family-members/99999');

        $response->assertStatus(404);
    });

    it('prevents viewing another user family member', function () {
        // Create another user with family member
        $otherUser = User::factory()->create([
            'household_id' => Household::factory()->create()->id,
        ]);
        $otherFamilyMember = FamilyMember::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Child',
        ]);

        $response = $this->getJson("/api/user/family-members/{$otherFamilyMember->id}");

        $response->assertStatus(404);
    });

    it('requires authentication', function () {
        // Create a new test instance without authentication
        $this->app = $this->createApplication();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson("/api/user/family-members/{$this->child1->id}");

        $response->assertStatus(401);
    });
});

describe('POST /api/user/family-members', function () {
    it('creates a new family member successfully', function () {
        // API uses first_name and last_name instead of name
        $newMemberData = [
            'relationship' => 'child',
            'first_name' => 'New',
            'last_name' => 'Child',
            'date_of_birth' => '2020-05-15',
            'gender' => 'female',
            'is_dependent' => true,
            'education_status' => 'primary',
            'annual_income' => 0,
            'notes' => 'Third child',
        ];

        $response = $this->postJson('/api/user/family-members', $newMemberData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Family member added successfully',
            ]);

        // Verify database - name is constructed from first_name + last_name
        $this->assertDatabaseHas('family_members', [
            'user_id' => $this->user->id,
            'first_name' => 'New',
            'last_name' => 'Child',
            'relationship' => 'child',
        ]);

        // Verify response data
        expect($response->json('data.family_member.first_name'))->toBe('New');
        expect($response->json('data.family_member.relationship'))->toBe('child');
    });

    it('validates required fields', function () {
        $invalidData = [
            'relationship' => '', // Required
            'first_name' => '', // Required
            'last_name' => '', // Required
        ];

        $response = $this->postJson('/api/user/family-members', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['relationship', 'first_name', 'last_name']);
    });

    it('validates relationship enum values', function () {
        $invalidData = [
            'relationship' => 'invalid_relationship',
            'first_name' => 'Test',
            'last_name' => 'Name',
        ];

        $response = $this->postJson('/api/user/family-members', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['relationship']);
    });

    it('validates date format', function () {
        $invalidData = [
            'relationship' => 'child',
            'first_name' => 'Test',
            'last_name' => 'Child',
            'date_of_birth' => 'not-a-date',
        ];

        $response = $this->postJson('/api/user/family-members', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth']);
    });

    it('requires authentication', function () {
        // Create a new test instance without authentication
        $this->app = $this->createApplication();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/user/family-members', [
            'relationship' => 'child',
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    });
});

describe('PUT /api/user/family-members/{id}', function () {
    it('updates a family member successfully', function () {
        $updatedData = [
            'name' => 'Updated Child Name',
            'gender' => 'male',
            'is_dependent' => false,
            'annual_income' => 5000.00,
            'notes' => 'Now earning part-time income',
        ];

        $response = $this->putJson("/api/user/family-members/{$this->child1->id}", $updatedData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Family member updated successfully',
            ]);

        // Verify database
        $this->assertDatabaseHas('family_members', [
            'id' => $this->child1->id,
            'name' => 'Updated Child Name',
            'is_dependent' => false,
        ]);
    });

    it('prevents updating another user family member', function () {
        // Create another user with family member
        $otherUser = User::factory()->create([
            'household_id' => Household::factory()->create()->id,
        ]);
        $otherFamilyMember = FamilyMember::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Original Name',
        ]);

        $response = $this->putJson("/api/user/family-members/{$otherFamilyMember->id}", [
            'name' => 'Unauthorized Update',
        ]);

        $response->assertStatus(404);

        // Verify database was not updated
        $this->assertDatabaseHas('family_members', [
            'id' => $otherFamilyMember->id,
            'name' => 'Original Name',
        ]);
    });

    it('returns 404 for non-existent family member', function () {
        $response = $this->putJson('/api/user/family-members/99999', [
            'name' => 'Test',
        ]);

        $response->assertStatus(404);
    });

    it('requires authentication', function () {
        // Create a new test instance without authentication
        $this->app = $this->createApplication();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->putJson("/api/user/family-members/{$this->child1->id}", [
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    });
});

describe('DELETE /api/user/family-members/{id}', function () {
    it('deletes a family member successfully', function () {
        $response = $this->deleteJson("/api/user/family-members/{$this->child1->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Family member deleted successfully',
            ]);

        // Verify soft delete
        $this->assertSoftDeleted('family_members', [
            'id' => $this->child1->id,
        ]);

        // Verify model is not findable (SoftDeletes excludes it)
        expect(FamilyMember::find($this->child1->id))->toBeNull();
    });

    it('prevents deleting another user family member', function () {
        // Create another user with family member
        $otherUser = User::factory()->create([
            'household_id' => Household::factory()->create()->id,
        ]);
        $otherFamilyMember = FamilyMember::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Child',
        ]);

        $response = $this->deleteJson("/api/user/family-members/{$otherFamilyMember->id}");

        $response->assertStatus(404);

        // Verify database still has the record
        $this->assertDatabaseHas('family_members', [
            'id' => $otherFamilyMember->id,
        ]);
    });

    it('returns 404 for non-existent family member', function () {
        $response = $this->deleteJson('/api/user/family-members/99999');

        $response->assertStatus(404);
    });

    it('requires authentication', function () {
        // Create a new test instance without authentication
        $this->app = $this->createApplication();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->deleteJson("/api/user/family-members/{$this->child1->id}");

        $response->assertStatus(401);
    });
});
