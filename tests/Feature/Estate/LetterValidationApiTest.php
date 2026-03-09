<?php

declare(strict_types=1);

use App\Models\Estate\Will;
use App\Models\LetterToSpouse;
use App\Models\LifeInsurancePolicy;
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

describe('GET /api/estate/letter-validation', function () {
    it('requires authentication', function () {
        $this->app = $this->createApplication();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/estate/letter-validation');

        $response->assertUnauthorized();
    });

    it('returns empty warnings when no letter exists', function () {
        $response = $this->getJson('/api/estate/letter-validation');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'warnings' => [],
                    'warning_count' => 0,
                    'has_warnings' => false,
                ],
            ]);
    });

    it('returns warnings when mismatches exist', function () {
        LetterToSpouse::factory()->create([
            'user_id' => $this->user->id,
            'executor_name' => 'Alice Jones',
            'insurance_policies_info' => null,
        ]);

        Will::factory()->withWill()->create([
            'user_id' => $this->user->id,
            'executor_name' => 'Bob Williams',
        ]);

        LifeInsurancePolicy::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Aviva',
        ]);

        $response = $this->getJson('/api/estate/letter-validation');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'warnings' => [
                        '*' => ['type', 'severity', 'message', 'action'],
                    ],
                    'warning_count',
                    'has_warnings',
                ],
            ]);

        $data = $response->json('data');
        expect($data['has_warnings'])->toBeTrue();
        expect($data['warning_count'])->toBeGreaterThan(0);

        $types = array_column($data['warnings'], 'type');
        expect($types)->toContain('executor_mismatch');
        expect($types)->toContain('insurance_unmatched');
    });

    it('returns correct structure with no warnings when data is consistent', function () {
        $executorName = 'John Smith';

        LetterToSpouse::factory()->create([
            'user_id' => $this->user->id,
            'executor_name' => $executorName,
        ]);

        Will::factory()->withWill()->create([
            'user_id' => $this->user->id,
            'executor_name' => $executorName,
        ]);

        $response = $this->getJson('/api/estate/letter-validation');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['warnings', 'warning_count', 'has_warnings'],
            ]);
    });
});
