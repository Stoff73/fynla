<?php

declare(strict_types=1);

use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('seeds the death in service reliance threshold the analyser reads', function () {
    expect(app(TaxConfigService::class)->getProtectionNeeds()['employer_cover']['reliance_share'])->toEqual(0.5);
});

// Item 8b (CSJ 2026-10-06): every protection need figure lives under
// protection.needs_calculation, each with its source, and nothing else.
it('keeps every protection need figure, with its source, in one block', function () {
    $needs = app(TaxConfigService::class)->getProtectionNeeds();

    expect($needs['life_cover']['income_replacement']['discount_rate'])->toEqual(0.005)
        ->and($needs['life_cover']['income_replacement']['discount_rate_source'])->toContain('Personal Injury Discount Rate')
        ->and($needs['life_cover']['final_expenses']['amount'])->toEqual(9797)
        ->and($needs['life_cover']['final_expenses']['source'])->toContain('SunLife')
        ->and($needs['critical_illness']['income_multiple'])->toEqual(3)
        ->and($needs['critical_illness']['source'])->toContain('rule of thumb')
        ->and($needs['income_protection']['benefit_tiers'])->toEqual([['up_to' => 60000, 'rate' => 0.60], ['up_to' => null, 'rate' => 0.50]])
        ->and($needs['income_protection']['source'])->toContain('Legal & General')
        ->and(app(TaxConfigService::class)->getProtectionConfig())->not->toHaveKeys(['income_multipliers', 'final_expenses', 'education_cost_per_year', 'withdrawal_rates', 'dis_reliance_percent']);
});

it('reads the protection thresholds with no literal fallback in code', function () {
    foreach (glob(base_path('app/Services/Protection/*.php')) as $path) {
        $file = str_replace(base_path().'/', '', $path);
        $source = file_get_contents(base_path($file));
        expect($source)->not->toMatch("/get\\('protection\\.dis_reliance_percent', [0-9.]+\\)/")
            ->and($source)->not->toMatch("/income_protection_max_benefit', [0-9.]+\\)/")
            ->and($source)->not->toMatch("/get\\('protection\\./");
    }
});
