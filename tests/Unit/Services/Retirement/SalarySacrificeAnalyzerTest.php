<?php

declare(strict_types=1);

use App\Services\Retirement\SalarySacrificeAnalyzer;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('names the cap year from config, not a literal', function () {
    $analyser = app(SalarySacrificeAnalyzer::class);
    $method = new ReflectionMethod($analyser, 'calculateNISavings');
    $method->setAccessible(true);

    $ni = $method->invoke($analyser, 5000.0);

    expect($ni['exceeds_nic_cap'])->toBeTrue()
        ->and($ni['nic_cap_effective_year'])->toBe(2027)
        ->and($ni)->not->toHaveKey('post_2029_employee');
});
