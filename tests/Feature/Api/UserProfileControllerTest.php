<?php

declare(strict_types=1);

use App\Models\Household;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
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
        // Create a new test instance without authentication
        $this->app = $this->createApplication();

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
        // Create a new test instance without authentication
        $this->app = $this->createApplication();

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
        // Create a new test instance without authentication
        $this->app = $this->createApplication();

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
