<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

describe('Country Tracking API', function () {
    describe('Property Country Tracking', function () {
        it('saves property with specified country', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/properties', [
                'property_type' => 'secondary_residence',
                'ownership_type' => 'individual',
                'current_value' => 250000,
                'purchase_price' => 200000,
                'purchase_date' => '2020-01-01',
                'address_line_1' => '123 Main St',
                'city' => 'Paris',
                'postcode' => '75001',
                'country' => 'France',
            ]);

            $response->assertStatus(201);
            expect($response->json('data.property.country'))->toBe('France');

            $this->assertDatabaseHas('properties', [
                'user_id' => $user->id,
                'country' => 'France',
            ]);
        });

        it('defaults to United Kingdom when country not provided', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/properties', [
                'property_type' => 'secondary_residence',
                'ownership_type' => 'individual',
                'current_value' => 250000,
                'purchase_price' => 200000,
                'purchase_date' => '2020-01-01',
                'address_line_1' => '123 Main St',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
            ]);

            $response->assertStatus(201);
            expect($response->json('data.property.country'))->toBe('United Kingdom');

            $this->assertDatabaseHas('properties', [
                'user_id' => $user->id,
                'country' => 'United Kingdom',
            ]);
        });

        it('updates property country', function () {
            $user = User::factory()->create();
            $property = Property::factory()->create([
                'user_id' => $user->id,
                'country' => 'United Kingdom',
                'postcode' => 'SW1A 1AA',
            ]);

            $response = $this->actingAs($user)->putJson("/api/properties/{$property->id}", [
                'property_type' => $property->property_type,
                'ownership_type' => $property->ownership_type,
                'current_value' => $property->current_value,
                'purchase_price' => $property->purchase_price,
                'purchase_date' => $property->purchase_date->format('Y-m-d'),
                'address_line_1' => $property->address_line_1,
                'city' => 'Madrid',
                'postcode' => '28001', // Spanish postcode
                'country' => 'Spain',
            ]);

            $response->assertStatus(200);
            // Property update returns wrapped: data.property.country
            expect($response->json('data.property.country'))->toBe('Spain');

            $this->assertDatabaseHas('properties', [
                'id' => $property->id,
                'country' => 'Spain',
            ]);
        });
    });

    describe('Savings Account Country Tracking', function () {
        it('saves non-ISA account with specified country', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/savings/accounts', [
                'account_type' => 'easy_access',
                'institution' => 'Foreign Bank',
                'current_balance' => 10000,
                'interest_rate' => 2.5,
                'access_type' => 'immediate',
                'is_isa' => false,
                'country' => 'Germany',
            ]);

            $response->assertStatus(201);
            expect($response->json('data.country'))->toBe('Germany');

            $this->assertDatabaseHas('savings_accounts', [
                'user_id' => $user->id,
                'country' => 'Germany',
            ]);
        });

        it('forces ISA accounts to United Kingdom', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/savings/accounts', [
                'account_type' => 'cash_isa',
                'institution' => 'UK Bank',
                'current_balance' => 15000,
                'interest_rate' => 3.0,
                'access_type' => 'immediate',
                'is_isa' => true,
                'isa_type' => 'cash',
                'isa_subscription_year' => '2025/26',
                'isa_subscription_amount' => 15000,
                'country' => 'France', // Should be overridden
            ]);

            $response->assertStatus(201);
            expect($response->json('data.country'))->toBe('United Kingdom');

            $this->assertDatabaseHas('savings_accounts', [
                'user_id' => $user->id,
                'is_isa' => true,
                'country' => 'United Kingdom',
            ]);
        });

        it('defaults non-ISA to United Kingdom when country not provided', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/savings/accounts', [
                'account_type' => 'easy_access',
                'institution' => 'UK Bank',
                'current_balance' => 5000,
                'interest_rate' => 2.0,
                'access_type' => 'immediate',
                'is_isa' => false,
            ]);

            $response->assertStatus(201);
            expect($response->json('data.country'))->toBe('United Kingdom');

            $this->assertDatabaseHas('savings_accounts', [
                'user_id' => $user->id,
                'country' => 'United Kingdom',
            ]);
        });

        it('updates ISA account country to United Kingdom even if different provided', function () {
            $user = User::factory()->create();
            $account = SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'is_isa' => true,
                'isa_type' => 'cash',
                'country' => 'United Kingdom',
                'interest_rate' => 3.0,
                'access_type' => 'immediate',
            ]);

            $response = $this->actingAs($user)->putJson("/api/savings/accounts/{$account->id}", [
                'account_type' => $account->account_type,
                'institution' => $account->institution,
                'current_balance' => 20000,
                'interest_rate' => 3.5,
                'access_type' => 'immediate',
                'is_isa' => true,
                'isa_type' => 'cash',
                'isa_subscription_year' => '2025/26',
                'isa_subscription_amount' => 20000,
                'country' => 'Spain', // Should be overridden
            ]);

            $response->assertStatus(200);
            expect($response->json('data.country'))->toBe('United Kingdom');

            $this->assertDatabaseHas('savings_accounts', [
                'id' => $account->id,
                'country' => 'United Kingdom',
            ]);
        });
    });

    describe('Mortgage Country Tracking', function () {
        it('saves mortgage with specified country', function () {
            $user = User::factory()->create();
            $property = Property::factory()->create([
                'user_id' => $user->id,
                'country' => 'France',
            ]);

            $response = $this->actingAs($user)->postJson("/api/properties/{$property->id}/mortgages", [
                'lender_name' => 'French Bank',
                'mortgage_type' => 'repayment',
                'original_loan_amount' => 200000,
                'outstanding_balance' => 180000,
                'interest_rate' => 3.5,
                'rate_type' => 'fixed',
                'monthly_payment' => 1000,
                'start_date' => '2020-01-01',
                'maturity_date' => '2045-01-01',
                'country' => 'France',
            ]);

            $response->assertStatus(201);
            expect($response->json('data.mortgage.country'))->toBe('France');

            $this->assertDatabaseHas('mortgages', [
                'property_id' => $property->id,
                'country' => 'France',
            ]);
        });

        it('defaults to United Kingdom when country not provided', function () {
            $user = User::factory()->create();
            $property = Property::factory()->create([
                'user_id' => $user->id,
                'country' => 'United Kingdom',
            ]);

            $response = $this->actingAs($user)->postJson("/api/properties/{$property->id}/mortgages", [
                'lender_name' => 'UK Bank',
                'mortgage_type' => 'repayment',
                'original_loan_amount' => 200000,
                'outstanding_balance' => 180000,
                'interest_rate' => 4.0,
                'rate_type' => 'fixed',
                'monthly_payment' => 1100,
                'start_date' => '2020-01-01',
                'maturity_date' => '2045-01-01',
            ]);

            $response->assertStatus(201);
            expect($response->json('data.mortgage.country'))->toBe('United Kingdom');

            $this->assertDatabaseHas('mortgages', [
                'property_id' => $property->id,
                'country' => 'United Kingdom',
            ]);
        });

        it('updates mortgage country', function () {
            $user = User::factory()->create();
            $property = Property::factory()->create([
                'user_id' => $user->id,
            ]);
            $mortgage = Mortgage::factory()->create([
                'property_id' => $property->id,
                'user_id' => $user->id,
                'country' => 'United Kingdom',
            ]);

            $response = $this->actingAs($user)->putJson("/api/mortgages/{$mortgage->id}", [
                'lender_name' => $mortgage->lender_name,
                'mortgage_type' => $mortgage->mortgage_type,
                'outstanding_balance' => $mortgage->outstanding_balance,
                'monthly_payment' => $mortgage->monthly_payment,
                'country' => 'Spain',
            ]);

            $response->assertStatus(200);
            expect($response->json('data.mortgage.country'))->toBe('Spain');

            $this->assertDatabaseHas('mortgages', [
                'id' => $mortgage->id,
                'country' => 'Spain',
            ]);
        });
    });

    describe('Country Field Validation', function () {
        it('accepts valid country names', function () {
            $user = User::factory()->create();

            $validCountries = ['United Kingdom', 'France', 'Germany', 'Spain', 'Italy', 'USA'];

            foreach ($validCountries as $country) {
                $response = $this->actingAs($user)->postJson('/api/properties', [
                    'property_type' => 'secondary_residence',
                    'ownership_type' => 'individual',
                    'current_value' => 100000,
                    'purchase_price' => 90000,
                    'purchase_date' => '2020-01-01',
                    'address_line_1' => '123 Test St',
                    'city' => 'Test City',
                    'postcode' => '12345',
                    'country' => $country,
                ]);

                $response->assertStatus(201);
            }
        });

        it('accepts missing country and applies default', function () {
            $user = User::factory()->create();

            // Don't include country field at all - should default to UK
            $response = $this->actingAs($user)->postJson('/api/properties', [
                'property_type' => 'main_residence',
                'ownership_type' => 'individual',
                'current_value' => 300000,
                'purchase_price' => 280000,
                'purchase_date' => '2020-01-01',
                'address_line_1' => '123 UK St',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
            ]);

            $response->assertStatus(201);
            expect($response->json('data.property.country'))->toBe('United Kingdom');
        });
    });

    describe('Authorization', function () {
        it('prevents users from accessing other users properties with country data', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $property = Property::factory()->create([
                'user_id' => $user2->id,
                'country' => 'France',
            ]);

            $response = $this->actingAs($user1)->putJson("/api/properties/{$property->id}", [
                'country' => 'Germany',
            ]);

            $response->assertStatus(404);

            // Property should remain unchanged
            $this->assertDatabaseHas('properties', [
                'id' => $property->id,
                'country' => 'France',
            ]);
        });
    });
});
