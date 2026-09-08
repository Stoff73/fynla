<?php

declare(strict_types=1);

use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\InvestmentActionDefinitionSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Every enabled seeded `trigger_config.condition` must have a dispatcher arm
 * in its engine, and every arm must have at least one seeded row. This is the
 * test that would have caught the savings engine being dead since 2026-03-14:
 * its arms were named after row keys while the rows carry condition names
 * (fyn-wiring finding F0).
 */
$engines = [
    'Savings' => ['table' => 'savings_action_definitions', 'service' => 'app/Services/Savings/SavingsActionDefinitionService.php', 'seeder' => SavingsActionDefinitionSeeder::class],
    'Investment' => ['table' => 'investment_action_definitions', 'service' => 'app/Services/Investment/InvestmentActionDefinitionService.php', 'seeder' => InvestmentActionDefinitionSeeder::class],
    'Retirement' => ['table' => 'retirement_action_definitions', 'service' => 'app/Services/Retirement/RetirementActionDefinitionService.php', 'seeder' => RetirementActionDefinitionSeeder::class],
    'Protection' => ['table' => 'protection_action_definitions', 'service' => 'app/Services/Protection/ProtectionActionDefinitionService.php', 'seeder' => ProtectionActionDefinitionSeeder::class],
    'Estate' => ['table' => 'estate_action_definitions', 'service' => 'app/Services/Estate/EstateActionDefinitionService.php', 'seeder' => EstateActionDefinitionSeeder::class],
    'Tax' => ['table' => 'tax_action_definitions', 'service' => 'app/Services/Tax/TaxActionDefinitionService.php', 'seeder' => TaxActionDefinitionSeeder::class],
];

function dispatcherArms(string $relativePath): array
{
    $source = file_get_contents(base_path($relativePath));
    preg_match_all("/^\\s*'([a-z0-9_]+)'\\s*=>\\s*\\\$this->evaluate/m", $source, $matches);

    return array_values(array_unique($matches[1]));
}

function seededConditions(string $table, bool $enabledOnly): array
{
    $query = DB::table($table)->whereNotNull('trigger_config');
    if ($enabledOnly) {
        $query->where('is_enabled', true);
    }

    return $query->get()
        ->map(fn ($row) => json_decode((string) $row->trigger_config, true)['condition'] ?? null)
        ->filter()
        ->unique()
        ->values()
        ->all();
}

foreach ($engines as $name => $engine) {
    it("{$name}: every enabled seeded condition has a dispatcher arm", function () use ($engine) {
        $this->seed(TaxConfigurationSeeder::class);
        $this->seed($engine['seeder']);

        $arms = dispatcherArms($engine['service']);
        $missing = array_values(array_diff(seededConditions($engine['table'], true), $arms));

        expect($missing)->toBe([], 'Enabled conditions with no arm: '.implode(', ', $missing));
    });

    it("{$name}: every dispatcher arm has a seeded row", function () use ($engine) {
        $this->seed(TaxConfigurationSeeder::class);
        $this->seed($engine['seeder']);

        $orphans = array_values(array_diff(dispatcherArms($engine['service']), seededConditions($engine['table'], false)));

        expect($orphans)->toBe([], 'Arms with no seeded row: '.implode(', ', $orphans));
    });
}
