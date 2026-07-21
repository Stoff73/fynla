<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Set a fixed "now" time for consistent testing
    Carbon::setTestNow(Carbon::create(2025, 10, 27));
});

afterEach(function () {
    Carbon::setTestNow(); // Reset
});

describe('Domicile Info API', function () {
    it('allows authenticated user to update domicile info as uk_domiciled', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'uk_domiciled',
                'country_of_birth' => 'United Kingdom',
                'uk_arrival_date' => null,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Domicile information updated successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
        ]);
    });

    it('allows authenticated user to update domicile info as non_uk_domiciled with arrival date', function () {
        $user = User::factory()->create();
        $arrivalDate = Carbon::now()->subYears(10)->toDateString();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'non_uk_domiciled',
                'country_of_birth' => 'France',
                'uk_arrival_date' => $arrivalDate,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'domicile_status' => 'non_uk_domiciled',
            'country_of_birth' => 'France',
            'uk_arrival_date' => $arrivalDate,
            'years_uk_resident' => 10,
        ]);
    });

    it('automatically calculates years_uk_resident when updating', function () {
        $user = User::factory()->create();
        $arrivalDate = Carbon::now()->subYears(18)->toDateString();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'non_uk_domiciled',
                'country_of_birth' => 'India',
                'uk_arrival_date' => $arrivalDate,
            ]);

        $response->assertStatus(200);

        $user->refresh();
        expect($user->years_uk_resident)->toBe(18);
    });

    it('automatically sets deemed_domicile_date when user has 15+ years residence', function () {
        $user = User::factory()->create();
        $arrivalDate = Carbon::now()->subYears(18)->toDateString();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'non_uk_domiciled',
                'country_of_birth' => 'Australia',
                'uk_arrival_date' => $arrivalDate,
            ]);

        $response->assertStatus(200);

        $user->refresh();
        expect($user->deemed_domicile_date)->not->toBeNull();

        // Should be 15 years after arrival
        $expectedDeemedDate = Carbon::parse($arrivalDate)->addYears(15);
        expect($user->deemed_domicile_date->format('Y-m-d'))
            ->toBe($expectedDeemedDate->format('Y-m-d'));
    });

    it('does NOT set deemed_domicile_date when user has less than 15 years residence', function () {
        $user = User::factory()->create();
        $arrivalDate = Carbon::now()->subYears(10)->toDateString();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'non_uk_domiciled',
                'country_of_birth' => 'Canada',
                'uk_arrival_date' => $arrivalDate,
            ]);

        $response->assertStatus(200);

        $user->refresh();
        expect($user->deemed_domicile_date)->toBeNull();
    });

    it('returns domicile_info in response', function () {
        $user = User::factory()->create();
        $arrivalDate = Carbon::now()->subYears(12)->toDateString();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'non_uk_domiciled',
                'country_of_birth' => 'Germany',
                'uk_arrival_date' => $arrivalDate,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'domicile_info' => [
                        'domicile_status',
                        'country_of_birth',
                        'uk_arrival_date',
                        'years_uk_resident',
                        'is_deemed_domiciled',
                        'explanation',
                    ],
                ],
            ]);

        $domicileInfo = $response->json('data.domicile_info');
        expect($domicileInfo['years_uk_resident'])->toBe(12)
            ->and($domicileInfo['is_deemed_domiciled'])->toBeFalse();
    });
});

describe('Domicile Info Validation', function () {
    it('requires domicile_status field', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'country_of_birth' => 'Spain',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['domicile_status']);
    });

    it('requires country_of_birth field', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'uk_domiciled',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country_of_birth']);
    });

    it('requires uk_arrival_date for non_uk_domiciled status', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'non_uk_domiciled',
                'country_of_birth' => 'Japan',
                'uk_arrival_date' => null,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['uk_arrival_date']);
    });

    it('does NOT require uk_arrival_date for uk_domiciled status', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'uk_domiciled',
                'country_of_birth' => 'United Kingdom',
                'uk_arrival_date' => null,
            ]);

        $response->assertStatus(200);
    });

    it('rejects invalid domicile_status values', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'invalid_status',
                'country_of_birth' => 'Italy',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['domicile_status']);
    });

    it('rejects uk_arrival_date in the future', function () {
        $user = User::factory()->create();
        $futureDate = Carbon::now()->addYears(1)->toDateString();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'non_uk_domiciled',
                'country_of_birth' => 'Portugal',
                'uk_arrival_date' => $futureDate,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['uk_arrival_date']);
    });

    it('accepts uk_arrival_date as today', function () {
        $user = User::factory()->create();
        $today = Carbon::now()->toDateString();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'non_uk_domiciled',
                'country_of_birth' => 'Netherlands',
                'uk_arrival_date' => $today,
            ]);

        $response->assertStatus(200);
    });
});

describe('Cache Invalidation', function () {
    it('successfully updates domicile without errors when cache invalidation runs', function () {
        $user = User::factory()->create();

        // Update domicile - this should trigger cache invalidation code without errors
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'uk_domiciled',
                'country_of_birth' => 'United Kingdom',
            ]);

        // Verify request succeeded (cache invalidation didn't cause errors)
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    });

    it('successfully updates domicile for user with spouse without cache errors', function () {
        $spouse = User::factory()->create();
        $user = User::factory()->create(['spouse_id' => $spouse->id]);

        // Update domicile - this should trigger spouse cache invalidation without errors
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'non_uk_domiciled',
                'country_of_birth' => 'France',
                'uk_arrival_date' => '2010-01-15',
            ]);

        // Verify request succeeded (spouse cache invalidation didn't cause errors)
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    });
});

describe('Authentication', function () {
    it('requires authentication to update domicile info', function () {
        $response = $this->putJson('/api/user/profile/domicile', [
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
        ]);

        $response->assertStatus(401);
    });

    it('only allows users to update their own domicile info', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 updates their domicile
        $this->actingAs($user1, 'sanctum')
            ->putJson('/api/user/profile/domicile', [
                'domicile_status' => 'uk_domiciled',
                'country_of_birth' => 'United Kingdom',
            ]);

        // User 2's domicile should not be affected
        $user2->refresh();
        expect($user2->domicile_status)->toBeNull();

        // Only user 1's domicile should be updated
        $user1->refresh();
        expect($user1->domicile_status)->toBe('uk_domiciled');
    });
});
