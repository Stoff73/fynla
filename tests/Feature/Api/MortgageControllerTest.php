<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MortgageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->property = Property::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_list_property_mortgages(): void
    {
        Mortgage::factory()->count(2)->create([
            'property_id' => $this->property->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/properties/{$this->property->id}/mortgages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'mortgages' => [
                        '*' => [
                            'id',
                            'lender_name',
                            'mortgage_type',
                            'outstanding_balance',
                            'monthly_payment',
                        ],
                    ],
                ],
            ]);

        expect($response->json('data.mortgages'))->toHaveCount(2);
    }

    public function test_can_create_mortgage_for_property(): void
    {
        $mortgageData = [
            'lender_name' => 'Test Bank',
            'mortgage_type' => 'repayment',
            'original_loan_amount' => 200000,
            'outstanding_balance' => 200000,
            'interest_rate' => 4.0,
            'rate_type' => 'fixed',
            'monthly_payment' => 1056,
            'start_date' => now()->format('Y-m-d'),
            'maturity_date' => now()->addYears(25)->format('Y-m-d'),
        ];

        $response = $this->withToken($this->token)
            ->postJson("/api/properties/{$this->property->id}/mortgages", $mortgageData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mortgage' => [
                        'lender_name' => 'Test Bank',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('mortgages', [
            'property_id' => $this->property->id,
            'user_id' => $this->user->id,
            'lender_name' => 'Test Bank',
        ]);
    }

    public function test_can_show_mortgage(): void
    {
        $mortgage = Mortgage::factory()->create([
            'property_id' => $this->property->id,
            'user_id' => $this->user->id,
            'lender_name' => 'Test Lender',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/mortgages/{$mortgage->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mortgage' => [
                        'id' => $mortgage->id,
                        'lender_name' => 'Test Lender',
                    ],
                ],
            ]);
    }

    public function test_cannot_view_other_user_mortgage(): void
    {
        $otherUser = User::factory()->create();
        $otherProperty = Property::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        $mortgage = Mortgage::factory()->create([
            'property_id' => $otherProperty->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/mortgages/{$mortgage->id}");

        $response->assertStatus(404);
    }

    public function test_can_update_mortgage(): void
    {
        $mortgage = Mortgage::factory()->create([
            'property_id' => $this->property->id,
            'user_id' => $this->user->id,
            'outstanding_balance' => 200000,
        ]);

        $updateData = [
            'outstanding_balance' => 195000,
            'monthly_payment' => 1050,
        ];

        $response = $this->withToken($this->token)
            ->putJson("/api/mortgages/{$mortgage->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mortgage' => [
                        'outstanding_balance' => '195000.00',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('mortgages', [
            'id' => $mortgage->id,
            'outstanding_balance' => 195000,
        ]);
    }

    public function test_cannot_update_other_user_mortgage(): void
    {
        $otherUser = User::factory()->create();
        $otherProperty = Property::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        $mortgage = Mortgage::factory()->create([
            'property_id' => $otherProperty->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/mortgages/{$mortgage->id}", [
                'outstanding_balance' => 1,
            ]);

        $response->assertStatus(404);
    }

    public function test_can_delete_mortgage(): void
    {
        $mortgage = Mortgage::factory()->create([
            'property_id' => $this->property->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/mortgages/{$mortgage->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSoftDeleted('mortgages', [
            'id' => $mortgage->id,
        ]);
    }

    public function test_cannot_delete_other_user_mortgage(): void
    {
        $otherUser = User::factory()->create();
        $otherProperty = Property::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        $mortgage = Mortgage::factory()->create([
            'property_id' => $otherProperty->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/mortgages/{$mortgage->id}");

        $response->assertStatus(404);

        // Ensure mortgage was not deleted
        $this->assertDatabaseHas('mortgages', [
            'id' => $mortgage->id,
        ]);
    }

    public function test_can_get_amortization_schedule(): void
    {
        $mortgage = Mortgage::factory()->create([
            'property_id' => $this->property->id,
            'user_id' => $this->user->id,
            'original_loan_amount' => 200000,
            'outstanding_balance' => 200000,
            'interest_rate' => 4.0,
            'monthly_payment' => 1056,
            'start_date' => now(),
            'maturity_date' => now()->addYears(25),
            'mortgage_type' => 'repayment',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/mortgages/{$mortgage->id}/amortization-schedule");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'schedule' => [
                        '*' => [
                            'month',
                            'opening_balance',
                            'payment',
                            'interest',
                            'principal',
                            'closing_balance',
                        ],
                    ],
                ],
            ]);

        // Should have a schedule with months - exact count depends on remaining term calculation
        // The service calculates remaining term from current date to maturity
        $schedule = $response->json('data.schedule');
        expect($schedule)->toBeArray();
        expect(count($schedule))->toBeGreaterThan(0);
        expect(count($schedule))->toBeLessThanOrEqual(300);
    }

    public function test_can_calculate_mortgage_payment(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/mortgages/calculate-payment', [
                'loan_amount' => 200000,
                'annual_interest_rate' => 4.0,
                'term_months' => 300,
                'mortgage_type' => 'repayment',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'monthly_payment',
                ],
            ]);

        $monthlyPayment = $response->json('data.monthly_payment');
        expect($monthlyPayment)->toBeGreaterThan(1000);
        expect($monthlyPayment)->toBeLessThan(1100);
    }

    public function test_validation_fails_for_invalid_mortgage_data(): void
    {
        $invalidData = [
            'lender_name' => '',
            'mortgage_type' => 'invalid_type',
            'outstanding_balance' => -1000,
            'interest_rate' => 150,
        ];

        $response = $this->withToken($this->token)
            ->postJson("/api/properties/{$this->property->id}/mortgages", $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'mortgage_type',
                'outstanding_balance',
                'interest_rate',
            ]);
    }

    public function test_maturity_date_must_be_after_start_date(): void
    {
        $mortgageData = [
            'lender_name' => 'Test Bank',
            'mortgage_type' => 'repayment',
            'original_loan_amount' => 200000,
            'outstanding_balance' => 200000,
            'interest_rate' => 4.0,
            'rate_type' => 'fixed',
            'monthly_payment' => 1056,
            'start_date' => '2025-01-01',
            'maturity_date' => '2024-01-01', // Before start date
        ];

        $response = $this->withToken($this->token)
            ->postJson("/api/properties/{$this->property->id}/mortgages", $mortgageData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['maturity_date']);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson("/api/properties/{$this->property->id}/mortgages");

        $response->assertStatus(401);
    }

    public function test_interest_only_mortgage_creation(): void
    {
        $mortgageData = [
            'lender_name' => 'Interest Only Bank',
            'mortgage_type' => 'interest_only',
            'original_loan_amount' => 150000,
            'outstanding_balance' => 150000,
            'interest_rate' => 3.5,
            'rate_type' => 'variable',
            'monthly_payment' => 437.50,
            'start_date' => now()->format('Y-m-d'),
            'maturity_date' => now()->addYears(20)->format('Y-m-d'),
        ];

        $response = $this->withToken($this->token)
            ->postJson("/api/properties/{$this->property->id}/mortgages", $mortgageData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mortgage' => [
                        'mortgage_type' => 'interest_only',
                    ],
                ],
            ]);
    }
}
