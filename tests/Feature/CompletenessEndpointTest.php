<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;

describe('GET /api/life-stage/completeness', function () {

    it('returns completeness structure for authenticated user', function () {
        $user = User::factory()->create(['life_stage' => 'university']);

        $this->actingAs($user)
            ->getJson('/api/life-stage/completeness')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'modules' => [
                        'protection' => ['has_data', 'can_advise', 'missing', 'guidance', 'required_actions'],
                        'savings' => ['has_data', 'can_advise', 'missing', 'guidance', 'required_actions'],
                        'retirement' => ['has_data', 'can_advise', 'missing', 'guidance', 'required_actions'],
                        'investment' => ['has_data', 'can_advise', 'missing', 'guidance', 'required_actions'],
                        'estate' => ['has_data', 'can_advise', 'missing', 'guidance', 'required_actions'],
                        'goals' => ['has_data', 'can_advise', 'missing', 'guidance', 'required_actions'],
                        'tax_optimisation' => ['has_data', 'can_advise', 'missing', 'guidance', 'required_actions'],
                    ],
                    'life_stage',
                ],
            ]);
    });

    it('returns all modules blocked for user with no data', function () {
        $user = User::factory()->create([
            'life_stage' => 'university',
            'date_of_birth' => null,
            'annual_employment_income' => 0,
            'marital_status' => null,
            'employment_status' => null,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/life-stage/completeness')
            ->assertOk();

        $modules = $response->json('data.modules');

        // All modules should have has_data = false and can_advise = false
        foreach ($modules as $module => $status) {
            expect($status['has_data'])->toBeFalse("Module {$module} should have no data");
            expect($status['can_advise'])->toBeFalse("Module {$module} should not be advisable");
            expect($status['missing'])->not->toBeEmpty("Module {$module} should have missing fields");
        }
    });

    it('returns has_data true for module with partial data', function () {
        $user = User::factory()->create([
            'life_stage' => 'early_career',
            'date_of_birth' => '1995-06-15',
            'annual_employment_income' => 35000,
            'marital_status' => null, // Missing — savings can't advise
        ]);

        // Add a savings account — savings has_data should be true
        SavingsAccount::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/life-stage/completeness')
            ->assertOk();

        $savings = $response->json('data.modules.savings');
        expect($savings['has_data'])->toBeTrue();
        // Savings blocks on: date_of_birth, income, expenditure
        // User has DOB + income but no expenditure → can_advise = false
        expect($savings['can_advise'])->toBeFalse();
        expect($savings['missing'])->toContain('Monthly expenditure is required to calculate savings capacity.');
    });

    it('returns can_advise true when all blocking fields present', function () {
        $user = User::factory()->create([
            'life_stage' => 'mid_career',
            'date_of_birth' => '1985-03-20',
            'annual_employment_income' => 55000,
            'marital_status' => 'married',
            'monthly_expenditure' => 2500,
            'employment_status' => 'employed',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/life-stage/completeness')
            ->assertOk();

        $protection = $response->json('data.modules.protection');
        // Protection needs: date_of_birth, income, marital_status — all present
        expect($protection['can_advise'])->toBeTrue();
        expect($protection['missing'])->toBeEmpty();
    });

    it('returns correct life_stage in response', function () {
        $user = User::factory()->create(['life_stage' => 'peak']);

        $response = $this->actingAs($user)
            ->getJson('/api/life-stage/completeness')
            ->assertOk();

        expect($response->json('data.life_stage'))->toBe('peak');
    });

    it('requires authentication', function () {
        $this->getJson('/api/life-stage/completeness')
            ->assertUnauthorized();
    });

    it('returns has_data true for estate when user has savings accounts', function () {
        $user = User::factory()->create([
            'life_stage' => 'mid_career',
            'date_of_birth' => '1985-03-20',
            'marital_status' => 'married',
        ]);

        SavingsAccount::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/life-stage/completeness')
            ->assertOk();

        $estate = $response->json('data.modules.estate');
        expect($estate['has_data'])->toBeTrue();
    });
});
