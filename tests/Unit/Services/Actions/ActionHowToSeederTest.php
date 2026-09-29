<?php

declare(strict_types=1);

use App\Models\TaxActionDefinition;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;

/*
 * database/seeders/data/action-how-to/<module>.md is the one source of how-to steps. The seeder
 * writes every entry's steps and status, so an entry CSJ has not approved stays
 * draft and never reaches a card.
 */
beforeEach(function () {
    $this->seed(TaxActionDefinitionSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
});

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

    expect($entries['pension_tax_relief'])->toBe(['status' => 'approved', 'steps' => [
        ['when' => null, 'text' => 'Decide how much.'],
        ['when' => null, 'text' => 'Pay it in.'],
    ]])
        ->and($entries['salary_sacrifice_ni'])->toBe(['status' => 'draft', 'steps' => [['when' => null, 'text' => 'Ask your employer.']]]);
});

it('keeps each step with the branch it sits under', function () {
    $entries = ActionHowToSeeder::parse(<<<'MD'
## pension_tax_relief
status: draft
when has_personal_pension and above_basic:
1. Pay {net_payment} into {personal_pension}.
always:
2. Pay it in by {tax_year_end}.
MD);

    expect($entries['pension_tax_relief']['steps'])->toBe([
        ['when' => 'has_personal_pension and above_basic', 'text' => 'Pay {net_payment} into {personal_pension}.'],
        ['when' => null, 'text' => 'Pay it in by {tax_year_end}.'],
    ]);
});

it('keeps outcome lines apart from the steps', function () {
    $entries = ActionHowToSeeder::parse(<<<'MD'
## pension_tax_relief
status: edited
1. Pay it in.
outcome:
1. Your Income Tax falls from {tax_now} to {tax_after}.
outcome when has_salary_sacrifice:
2. National Insurance falls by {ni_saved}.
always:
2. Pay it in by {tax_year_end}.
MD);

    // "edited" is CSJ's review mark, not an approval: it stays draft.
    expect($entries['pension_tax_relief'])->toBe(['status' => 'draft', 'steps' => [
        ['when' => null, 'text' => 'Pay it in.'],
        ['when' => null, 'text' => 'Your Income Tax falls from {tax_now} to {tax_after}.', 'part' => 'outcome'],
        ['when' => 'has_salary_sacrifice', 'text' => 'National Insurance falls by {ni_saved}.', 'part' => 'outcome'],
        ['when' => null, 'text' => 'Pay it in by {tax_year_end}.'],
    ]]);
});

it('refuses a heading that names no strategy, rather than skipping it without a word', function () {
    $path = ActionHowToSeeder::sourcePath('tax');
    $original = file_get_contents($path);
    file_put_contents($path, $original."\n## dividend_allowance_check\nstatus: draft\n1. A step.\n");

    try {
        expect(fn () => (new ActionHowToSeeder)->loadModule('tax'))
            ->toThrow(RuntimeException::class, 'dividend_allowance_check');
    } finally {
        file_put_contents($path, $original);
    }
});

it('names a real strategy in every heading of the repository file', function () {
    $keys = array_keys(ActionHowToSeeder::parse((string) file_get_contents(ActionHowToSeeder::sourcePath('tax'))));

    expect(array_diff($keys, TaxActionDefinition::pluck('strategy_type')->all()))->toBe([])
        ->and($keys)->toHaveCount(21);
});

it('writes the repository file to the tax definitions, each with the status CSJ gave it', function () {
    $this->seed(ActionHowToSeeder::class);
    $entries = ActionHowToSeeder::parse((string) file_get_contents(ActionHowToSeeder::sourcePath('tax')));

    foreach ($entries as $type => $entry) {
        $row = TaxActionDefinition::where('strategy_type', $type)->first();
        expect($row->how_to_status)->toBe($entry['status'], $type)
            // A MySQL JSON column reorders object keys, so compare by value.
            ->and(json_decode((string) $row->getRawOriginal('how_to_steps'), true))->toEqual($entry['steps']);
    }
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
