<?php

declare(strict_types=1);

use App\Models\EstateActionDefinition;
use App\Models\InvestmentActionDefinition;
use App\Models\ProtectionActionDefinition;
use App\Models\RetirementActionDefinition;
use App\Models\SavingsActionDefinition;
use App\Models\User;
use App\Services\Coordination\PlanSources\ModuleAvailabilityProvider;
use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\InvestmentActionDefinitionSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;

/**
 * Guard: every required_data key on a seeded source='strategy' row must be a
 * key the ModuleAvailabilityProvider actually produces for that module — else
 * the strategy can never become available and locks forever.
 */
it('keys every seeded non-tax required_data off the module availability vocabulary', function (): void {
    $this->seed([
        RetirementActionDefinitionSeeder::class,
        SavingsActionDefinitionSeeder::class,
        InvestmentActionDefinitionSeeder::class,
        ProtectionActionDefinitionSeeder::class,
        EstateActionDefinitionSeeder::class,
    ]);

    $user = User::factory()->create();
    $provider = app(ModuleAvailabilityProvider::class);

    $models = [
        'retirement' => RetirementActionDefinition::class,
        'savings' => SavingsActionDefinition::class,
        'investment' => InvestmentActionDefinition::class,
        'protection' => ProtectionActionDefinition::class,
        'estate' => EstateActionDefinition::class,
    ];

    foreach ($models as $module => $model) {
        $vocab = array_keys($provider->forModule($module, $user));
        $rows = $model::where('source', 'strategy')->where('is_enabled', true)->get();

        expect($rows)->not->toBeEmpty("$module has no source=strategy rows seeded");

        foreach ($rows as $row) {
            foreach ((array) ($row->required_data ?? []) as $key) {
                expect(in_array($key, $vocab, true))->toBeTrue(
                    "$module strategy '{$row->strategy_type}' requires '$key' which ModuleAvailabilityProvider::forModule('$module') never produces"
                );
            }
        }
    }
});
