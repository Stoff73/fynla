<?php

declare(strict_types=1);

use App\DataTransferObjects\TaxStrategyOutputDTO;
use App\Models\User;
use App\Services\Tax\TaxStrategyService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The desktop Tax Strategy page never said which Income Tax bands it prices;
 * only /m did, from a string of its own (ice-cube, PR 984). The server now
 * sends the sentence once for every client.
 */
uses(RefreshDatabase::class);

it('sends the income-tax jurisdiction with every tax strategy payload', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $user = User::factory()->create(['annual_employment_income' => 40000]);

    $payload = app(TaxStrategyService::class)->getDashboardPayload($user);

    expect($payload['tax_basis_note'])->toBe(TaxStrategyOutputDTO::TAX_BASIS_NOTE)
        ->and($payload['tax_basis_note'])->toContain('England, Wales and Northern Ireland')
        ->and($payload['tax_basis_note'])->toContain('Scottish Income Tax bands are not modelled');
});
