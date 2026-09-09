<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
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
 * F3 — every trigger key the prompt tells Fyn to look for must be a key some
 * engine can actually emit. Ten phantom names were retired on 2026-09-09 (CSJ:
 * wire them all in); this keeps the lists honest from here on.
 */
it('names only seeded definition keys in every relevant-triggers list', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    foreach ([
        SavingsActionDefinitionSeeder::class, InvestmentActionDefinitionSeeder::class,
        RetirementActionDefinitionSeeder::class, ProtectionActionDefinitionSeeder::class,
        EstateActionDefinitionSeeder::class, TaxActionDefinitionSeeder::class,
    ] as $seeder) {
        $this->seed($seeder);
    }

    $seeded = [];
    foreach ([
        'savings_action_definitions', 'investment_action_definitions', 'retirement_action_definitions',
        'protection_action_definitions', 'estate_action_definitions', 'tax_action_definitions',
    ] as $table) {
        $seeded = array_merge($seeded, DB::table($table)->where('is_enabled', true)->pluck('key')->all());
    }
    $seeded = array_flip($seeded);

    $missing = [];
    foreach (QuerySchemas::RELEVANT_TRIGGERS as $type => $keys) {
        foreach ($keys as $key) {
            if (! isset($seeded[$key])) {
                $missing[] = "{$type}: {$key}";
            }
        }
    }

    expect($missing)->toBe([]);
});
