<?php

declare(strict_types=1);

use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('seeds the death in service reliance threshold the analyser reads', function () {
    expect(app(TaxConfigService::class)->get('protection.dis_reliance_percent'))->toEqual(0.5);
});

it('reads the protection thresholds with no literal fallback in code', function () {
    foreach (['app/Services/Protection/CoverageGapAnalyzer.php', 'app/Services/Protection/ProtectionActionDefinitionService.php', 'app/Services/Protection/ProtectionGapPresentationService.php'] as $file) {
        $source = file_get_contents(base_path($file));
        expect($source)->not->toMatch("/get\\('protection\\.dis_reliance_percent', [0-9.]+\\)/")
            ->and($source)->not->toMatch("/income_protection_max_benefit', [0-9.]+\\)/");
    }
});
