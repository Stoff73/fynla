<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

it('returns the composed tax plan alongside ranked recommendations', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_recommendations', [], $user->fresh(), null);

    expect($result)->toHaveKeys(['recommendations', 'total', 'surplus', 'composed_tax_plan'])
        ->and($result['composed_tax_plan'])->toHaveKeys(['items', 'combined_annual_saving', 'locked']);
});
