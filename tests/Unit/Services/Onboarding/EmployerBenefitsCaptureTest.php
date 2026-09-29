<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\RecordEditForms;

/*
 * Fyn's employer benefits form (CSJ 2026-09-29) writes through the same
 * EmployerBenefitsWriter and bounds as the web form, and "No, none of these"
 * is saved as an answer.
 */
function employerForm(array $lead): array
{
    return ['name' => CaptureForms::EMPLOYER_BENEFITS, 'answers' => [CaptureForms::LEAD => $lead]];
}

it('is one write through capture_employer_benefits that asks the none question outright', function () {
    $schema = CaptureForms::schema(CaptureForms::EMPLOYER_BENEFITS);

    expect($schema['tool'])->toBe('capture_employer_benefits')
        ->and($schema['fields']['provides']['required'])->toBeTrue()
        ->and(array_column($schema['fields']['provides']['options'], 'value'))->toBe(['yes', 'no'])
        ->and(CaptureForms::rules(CaptureForms::EMPLOYER_BENEFITS)['_lead.death_in_service_multiple'])->toContain('max:20');
});

it('saves the figures the form gives, and says so in plain words', function () {
    $user = User::factory()->create();
    $form = employerForm(['provides' => 'yes', 'employer_name' => 'Acme Ltd', 'death_in_service_multiple' => '4',
        'group_ip_benefit_percent' => '50', 'group_ip_benefit_months' => '24', 'group_ip_definition' => 'any', 'has_employer_pmi' => 'yes']);

    $result = app(CoordinatingAgent::class)->handleCaptureEmployerBenefits(CaptureForms::toolInputs($form)[CaptureForms::LEAD], $user);
    $profile = ProtectionProfile::where('user_id', $user->id)->first();

    expect($result['updated'])->toBeTrue()
        ->and((float) $profile->death_in_service_multiple)->toBe(4.0)
        ->and($profile->group_ip_benefit_months)->toBe(24)
        ->and($profile->group_ip_definition)->toBe('any')
        ->and($profile->has_employer_pmi)->toBeTrue()
        ->and($profile->employer_benefits_recorded_at)->not->toBeNull()
        ->and(CaptureForms::summarise($form))->toBe('My job at Acme Ltd gives me death in service of 4 times my salary, income protection of 50% of my salary for 24 months, private medical insurance.');
});

it('records "No, none of these" as an answer with every benefit blank', function () {
    $user = User::factory()->create();
    ProtectionProfile::factory()->create(['user_id' => $user->id, 'death_in_service_multiple' => 3, 'employer_benefits_recorded_at' => null]);

    app(CoordinatingAgent::class)->handleCaptureEmployerBenefits(
        CaptureForms::toolInputs(employerForm(['provides' => 'no', 'death_in_service_multiple' => '4']))[CaptureForms::LEAD],
        $user,
    );
    $profile = ProtectionProfile::where('user_id', $user->id)->first();

    expect($profile->death_in_service_multiple)->toBeNull()
        ->and($profile->employer_benefits_recorded_at)->not->toBeNull()
        ->and(CaptureForms::summarise(employerForm(['provides' => 'no'])))->toBe('My job gives me none of these benefits.');
});

it('refuses a figure outside the writer\'s bounds without saving', function () {
    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->handleCaptureEmployerBenefits(['provides' => 'yes', 'group_ip_benefit_percent' => 150], $user);

    expect($result['error'])->toBeTrue()
        ->and(ProtectionProfile::where('user_id', $user->id)->value('employer_benefits_recorded_at'))->toBeNull();
});

it('offers employer benefits under protection for Fyn to edit, filled from the profile', function () {
    $user = User::factory()->create();
    ProtectionProfile::factory()->create(['user_id' => $user->id, 'death_in_service_multiple' => null, 'group_ip_benefit_percent' => null,
        'group_ci_amount' => null, 'has_employer_pmi' => false, 'employer_name' => 'Acme Ltd', 'employer_benefits_recorded_at' => now()]);
    $edit = app(RecordEditForms::class);

    $rows = collect($edit->candidates($user, 'protection'));
    $form = $edit->formFor($user, 'employer_benefits', $user->id);

    expect($rows->pluck('type'))->toContain('employer_benefits')
        ->and($form['name'])->toBe(CaptureForms::EMPLOYER_BENEFITS)
        ->and($form['answers'][CaptureForms::LEAD])->toMatchArray(['employer_name' => 'Acme Ltd', 'provides' => 'no']);
});
