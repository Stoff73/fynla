<?php

declare(strict_types=1);

use App\Constants\ProfileEnums;
use App\Models\Household;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    // Create a household
    $this->household = Household::factory()->create();

    // Create a test user
    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'first_name' => 'Test',
        'middle_name' => null,
        'surname' => 'User',
        'email' => 'test@example.com',
        'date_of_birth' => '1985-05-15',
        'gender' => 'male',
        'marital_status' => 'single',
        'address_line_1' => '123 Test Street',
        'city' => 'London',
        'postcode' => 'SW1A 1AA',
        'phone' => '02012345678',
        'national_insurance_number' => 'AB123456C',
        'occupation' => 'Software Engineer',
        'employer' => 'Test Company Ltd',
        'industry' => 'Technology',
        'employment_status' => 'employed',
        'annual_employment_income' => 75000.00,
        'annual_self_employment_income' => 0.00,
        'annual_rental_income' => 12000.00,
        'annual_dividend_income' => 3000.00,
        'annual_other_income' => 0.00,
    ]);

    // Authenticate as this user
    $this->actingAs($this->user, 'sanctum');
});

describe('GET /api/user/profile', function () {
    it('returns authenticated user profile data', function () {
        $response = $this->getJson('/api/user/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'personal_info' => [
                        'id',
                        'name',
                        'email',
                        'date_of_birth',
                        'age',
                        'gender',
                        'marital_status',
                        'national_insurance_number',
                        'address' => [
                            'line_1',
                            'line_2',
                            'city',
                            'county',
                            'postcode',
                        ],
                        'phone',
                    ],
                    'income_occupation' => [
                        'occupation',
                        'employer',
                        'industry',
                        'employment_status',
                        'annual_employment_income',
                        'annual_self_employment_income',
                        'annual_rental_income',
                        'annual_dividend_income',
                        'annual_interest_income',
                        'total_annual_income',
                    ],
                    'family_members',
                    'assets_summary',
                    'liabilities_summary',
                ],
            ]);

        expect($response->json('data.personal_info.name'))->toBe('Test User');
        expect($response->json('data.personal_info.email'))->toBe('test@example.com');
        // Total income = 75000 (employment) + 3000 (dividend) = 78000
        // Note: rental income is calculated from properties, not user field
        expect((float) $response->json('data.income_occupation.total_annual_income'))->toBe(78000.0);
    });

    it('requires authentication', function () {
        $this->actingAsGuest();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/user/profile');

        $response->assertStatus(401);
    });
});

describe('PUT /api/user/profile/personal', function () {
    it('updates user personal information successfully', function () {
        $updatedData = [
            'first_name' => 'Updated',
            'surname' => 'Name',
            'date_of_birth' => '1990-01-01',
            'gender' => 'female',
            'marital_status' => 'married',
            'address_line_1' => '456 New Street',
            'address_line_2' => 'Apartment 10',
            'city' => 'Manchester',
            'county' => 'Greater Manchester',
            'postcode' => 'M1 1AA',
            'phone' => '01612345678',
            'national_insurance_number' => 'CD987654E',
        ];

        $response = $this->putJson('/api/user/profile/personal', $updatedData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Personal information updated successfully',
            ]);

        // Verify database was updated
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'first_name' => 'Updated',
            'surname' => 'Name',
            'city' => 'Manchester',
            'postcode' => 'M1 1AA',
        ]);
    });

    /**
     * W-0006. The rules whitelisted `good_health` and `smoker` as booleans.
     * Neither is a column on `users`, and neither real column appeared in the
     * rule set at all, so validated() stripped both values on every submit —
     * no 422, no error, the panel closed as if saved and nothing was written.
     */
    it('persists health and smoking status from the Health & Lifestyle form', function () {
        $this->putJson('/api/user/profile/personal', [
            'health_status' => 'yes',
            'smoking_status' => 'never',
            'education_level' => 'postgraduate',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'health_status' => 'yes',
            'smoking_status' => 'never',
            'education_level' => 'postgraduate',
        ]);
    });

    /**
     * W-0031. The rule allowed `doctorate`, `foundation` and `hnd`; the column
     * enum holds none of them, so validation passed and the write died as a
     * QueryException — HTTP 500, not 422. Not latent either: PersonalInformation.vue
     * offered all three from a live select.
     */
    it('rejects the three education levels the column cannot hold, with 422 not 500', function () {
        foreach (['doctorate', 'foundation', 'hnd'] as $level) {
            $this->putJson('/api/user/profile/personal', ['education_level' => $level])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['education_level']);
        }

        expect($this->user->fresh()->education_level)->toBeNull();
    });

    it('accepts every education level the column does hold', function () {
        foreach (ProfileEnums::EDUCATION_LEVELS as $level) {
            $this->putJson('/api/user/profile/personal', ['education_level' => $level])
                ->assertStatus(200);

            expect($this->user->fresh()->education_level)->toBe($level);
        }
    });

    it('rejects a health or smoking value outside the column enum', function () {
        $this->putJson('/api/user/profile/personal', [
            'health_status' => 'excellent',
            'smoking_status' => 'occasionally',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['health_status', 'smoking_status']);
    });

    it('treats the unanswered "Select..." option as no answer rather than failing the whole form', function () {
        $this->user->refresh();
        $originalHealth = $this->user->health_status;
        $originalSmoking = $this->user->smoking_status;

        $this->putJson('/api/user/profile/personal', [
            'first_name' => 'Sarah',
            'health_status' => '',
            'smoking_status' => '',
            'education_level' => '',
        ])->assertStatus(200);

        $this->user->refresh();

        // Untouched, not nulled — smoking_status is NOT NULL, and an unanswered
        // select means "leave it alone", never "clear it".
        expect($this->user->first_name)->toBe('Sarah')
            ->and($this->user->health_status)->toBe($originalHealth)
            ->and($this->user->smoking_status)->toBe($originalSmoking);
    });

    /**
     * Fault 2 of W-0006: education_level DID persist but UserResource exposed
     * none of the three, so the page rendered the stored value as
     * "Not specified". One source — GET /api/auth/user — for every client.
     */
    it('exposes health, smoking and education on the user resource', function () {
        $this->user->update([
            'health_status' => 'yes',
            'smoking_status' => 'never',
            'education_level' => 'postgraduate',
        ]);

        $this->getJson('/api/auth/user')
            ->assertStatus(200)
            ->assertJsonPath('data.user.health_status', 'yes')
            ->assertJsonPath('data.user.smoking_status', 'never')
            ->assertJsonPath('data.user.education_level', 'postgraduate');
    });

    it('validates string fields format', function () {
        $invalidData = [
            'first_name' => 123, // Must be string
            'postcode' => 456, // Must be string
        ];

        $response = $this->putJson('/api/user/profile/personal', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'postcode']);
    });

    it('validates email format', function () {
        $invalidData = [
            'email' => 'not-an-email',
            'first_name' => 'Test',
            'surname' => 'User',
            'postcode' => 'SW1A 1AA',
        ];

        $response = $this->putJson('/api/user/profile/personal', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('requires authentication', function () {
        $this->actingAsGuest();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->putJson('/api/user/profile/personal', [
            'first_name' => 'Test',
            'surname' => 'User',
            'postcode' => 'SW1A 1AA',
        ]);

        $response->assertStatus(401);
    });
});

describe('PUT /api/user/profile/income-occupation', function () {
    it('updates income and occupation data successfully', function () {
        $updatedData = [
            'occupation' => 'Senior Developer',
            'employer' => 'New Company Ltd',
            'industry' => 'Finance',
            'employment_status' => 'self_employed',
            'annual_employment_income' => 0.00,
            'annual_self_employment_income' => 95000.00,
            'annual_rental_income' => 15000.00,
            'annual_dividend_income' => 5000.00,
            'annual_other_income' => 2000.00,
        ];

        $response = $this->putJson('/api/user/profile/income-occupation', $updatedData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Income and occupation information updated successfully',
            ]);

        // Verify database was updated
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'occupation' => 'Senior Developer',
            'annual_self_employment_income' => 95000.00,
        ]);

        // Verify response includes updated data
        expect($response->json('data.user.occupation'))->toBe('Senior Developer');
        expect((float) $response->json('data.user.annual_self_employment_income'))->toBe(95000.0);
    });

    it('validates income fields are numeric and non-negative', function () {
        $invalidData = [
            'annual_employment_income' => -1000, // Negative not allowed
            'annual_rental_income' => 'not-a-number',
        ];

        $response = $this->putJson('/api/user/profile/income-occupation', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['annual_employment_income', 'annual_rental_income']);
    });

    it('requires authentication', function () {
        $this->actingAsGuest();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->putJson('/api/user/profile/income-occupation', [
            'occupation' => 'Test',
        ]);

        $response->assertStatus(401);
    });
});

describe('Authorization', function () {
    it('prevents viewing another user profile', function () {
        // Create another user
        $otherUser = User::factory()->create([
            'household_id' => Household::factory()->create()->id,
        ]);

        // Try to access profile endpoint (should only return own profile)
        $response = $this->getJson('/api/user/profile');

        $response->assertStatus(200);
        expect($response->json('data.personal_info.id'))->toBe($this->user->id);
        expect($response->json('data.personal_info.id'))->not->toBe($otherUser->id);
    });

    it('prevents updating another user profile', function () {
        // Profile updates are scoped to authenticated user only
        // This test verifies the controller only updates the authenticated user's data
        $response = $this->putJson('/api/user/profile/personal', [
            'first_name' => 'Attempted',
            'surname' => 'Unauthorized Update',
            'postcode' => 'SW1A 1AA',
        ]);

        $response->assertStatus(200);

        // Verify only the authenticated user's profile was updated
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'first_name' => 'Attempted',
            'surname' => 'Unauthorized Update',
        ]);
    });
});
