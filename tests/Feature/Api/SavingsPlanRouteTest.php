<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * F26 — the dashboard requested a savings plan from a route constrained to four
 * modules, a silent 404 on every load. The plan service always existed.
 */
it('serves the savings plan the dashboard asks for', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $user = User::factory()->create(['onboarding_completed' => true, 'monthly_expenditure' => 2000, 'annual_employment_income' => 45000]);
    SavingsAccount::factory()->for($user)->create(['current_balance' => 6000, 'interest_rate' => 4.0]);
    Sanctum::actingAs($user);

    $this->getJson('/api/plans/savings')->assertOk()->assertJsonPath('success', true);
});
