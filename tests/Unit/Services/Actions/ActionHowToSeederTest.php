<?php

declare(strict_types=1);

use App\Models\TaxActionDefinition;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;

/*
 * database/seeders/data/action-how-to/<module>.md is the one source of how-to steps. The seeder
 * writes every entry's steps and status, so an entry CSJ has not approved stays
 * draft and never reaches a card.
 */
beforeEach(fn () => $this->seed(TaxActionDefinitionSeeder::class));

it('parses steps and status per strategy from the markdown source', function () {
    $entries = ActionHowToSeeder::parse(<<<'MD'
# How-to steps: tax actions

## pension_tax_relief
status: approved
source: https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief
1. Decide how much.
2. Pay it in.

## salary_sacrifice_ni
status: draft
unverified: step 2
source: https://example.gov.uk
1. Ask your employer.
MD);

    expect($entries['pension_tax_relief'])->toBe(['status' => 'approved', 'steps' => ['Decide how much.', 'Pay it in.']])
        ->and($entries['salary_sacrifice_ni'])->toBe(['status' => 'draft', 'steps' => ['Ask your employer.']]);
});

it('writes the repository file to the tax definitions, leaving every draft as draft', function () {
    $this->seed(ActionHowToSeeder::class);

    $row = TaxActionDefinition::where('strategy_type', 'pension_tax_relief')->first();

    expect($row->how_to_status)->toBe('draft')
        ->and(json_decode((string) $row->getRawOriginal('how_to_steps'), true))->toBeArray()->not->toBeEmpty();
});

it('reads its source from the database folder every deploy ships, and fails loudly without it', function () {
    // Review I3: docs/ is not deployed (deploy/DEPLOY.md rsync list), so a
    // source there would leave production with no steps and no error.
    expect(ActionHowToSeeder::sourcePath('tax'))->toStartWith(database_path())
        ->and(is_file(ActionHowToSeeder::sourcePath('tax')))->toBeTrue();

    expect(fn () => (new ActionHowToSeeder)->loadModule('missing_module_for_test'))->toThrow(RuntimeException::class);
});

it('runs with the main database seeder', function () {
    expect(file_get_contents(database_path('seeders/DatabaseSeeder.php')))->toContain('ActionHowToSeeder::class');
});
