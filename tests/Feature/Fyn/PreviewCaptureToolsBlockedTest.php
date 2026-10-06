<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\RecordEditForms;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * A demo persona's /m chat opens the same forms as a real user's, and their
 * save runs the setup walk's capture tools. Six of those wrote for a demo
 * persona (only create_*, update_* and the newer capture_* tools refused), so
 * "Save changes" on the personal form overwrote the shared persona every
 * visitor sees (found 2026-10-05, 7a step 2).
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

dataset('setup capture tools', [
    'personal details' => ['capture_personal_details', ['date_of_birth' => '1990-01-01', 'gender' => 'male', 'marital_status' => 'single']],
    'spouse details' => ['capture_spouse_details', ['first_name' => 'Sam']],
    'dependants' => ['capture_dependants', ['dependants' => [['relationship' => 'child', 'first_name' => 'Ava', 'date_of_birth' => '2015-05-05']]]],
    'work details' => ['capture_work_details', ['employer' => 'Acme', 'annual_income' => 50000]],
    'monthly expenditure' => ['capture_monthly_expenditure', ['monthly_expenditure' => 2000]],
    'employer benefits' => ['capture_employer_benefits', ['has_death_in_service' => false]],
]);

it('refuses a setup capture tool for a demo persona and writes nothing', function (string $tool, array $input): void {
    $persona = User::factory()->create(['is_preview_user' => true, 'date_of_birth' => '1980-02-02', 'first_name' => 'Demo']);
    $before = $persona->fresh()->toArray();

    $result = app(CoordinatingAgent::class)->executeTool($tool, $input, $persona, null);

    expect($result['blocked'] ?? false)->toBeTrue()
        ->and($persona->fresh()->toArray())->toEqual($before)
        ->and($persona->employments()->count())->toBe(0);
})->with('setup capture tools');

it('never saves a demo persona\'s edit form, including the job it writes directly', function (): void {
    $persona = User::factory()->create(['is_preview_user' => true, 'date_of_birth' => '1980-02-02']);
    $job = $persona->employments()->create(['employer' => 'Demo Ltd', 'annual_income' => 40000]);
    $forms = app(RecordEditForms::class);

    $personal = $forms->formFor($persona, 'personal', $persona->id);
    $personal['answers'][CaptureForms::LEAD]['date_of_birth'] = '1990-01-01';
    $work = $forms->formFor($persona, 'employment', $job->id);
    $work['answers'][CaptureForms::LEAD]['annual_income'] = 90000;

    expect($forms->update($persona, $personal, 1)['success'])->toBeFalse()
        ->and($forms->update($persona, $work, 1)['message'])->toBe(\App\Services\Onboarding\RecordEditForms::DEMO_MESSAGE)
        ->and($persona->fresh()->date_of_birth->format('Y-m-d'))->toBe('1980-02-02')
        ->and((float) $job->fresh()->annual_income)->toBe(40000.0);
});
