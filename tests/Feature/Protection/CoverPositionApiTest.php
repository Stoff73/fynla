<?php

declare(strict_types=1);

use App\Models\ProtectionProfile;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

it('returns the cover position with the protection data', function () {
    $this->seed(TaxConfigurationSeeder::class);
    $user = User::factory()->create(['annual_employment_income' => 60000, 'date_of_birth' => now()->subYears(40)]);
    ProtectionProfile::factory()->create(['user_id' => $user->id, 'annual_income' => 60000, 'mortgage_balance' => 0, 'other_debts' => 0,
        'number_of_dependents' => 0, 'dependents_ages' => [], 'death_in_service_multiple' => 4]);
    Sanctum::actingAs($user);

    $this->getJson('/api/protection')->assertOk()
        ->assertJsonPath('data.cover_position.life.employer_cover', 240000)
        ->assertJsonPath('data.cover_position.income_protection.unit', 'monthly')
        ->assertJsonStructure(['data' => ['cover_position' => ['life' => ['need', 'own_cover', 'employer_cover', 'short_by', 'over_by', 'depends_on_job', 'status']]]]);
});
