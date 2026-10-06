<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\RecordEditForms;

/*
 * /m Personal Information "Edit details" opens the personal form (date of
 * birth, gender, marital status), not a typed question: Fyn capture is forms
 * only (CSJ 2026-10-01), and gender is asked with the date of birth (CSJ
 * 2026-10-04). Walking release #1071 on fynla.org, it asked "what is your
 * date of birth?" and never asked gender.
 */
it('opens /m\'s Personal Information "Edit details" on the personal form, filled with what is recorded', function () {
    $user = User::factory()->create(['date_of_birth' => null, 'gender' => null, 'marital_status' => 'single', 'smoking_status' => null, 'health_status' => null]);

    $form = app(RecordEditForms::class)->formForResource($user, 'personal_information');

    expect($form['name'])->toBe(CaptureForms::PERSONAL)
        ->and($form['record'])->toBe(['type' => 'personal', 'id' => $user->id])
        ->and($form['schema']['lead_fields'])->toBe(['date_of_birth', 'gender', 'marital_status', 'smoking_status', 'health_status'])
        ->and($form['answers'][CaptureForms::LEAD])->toBe(['marital_status' => 'single']);
});

it('saves the date of birth and gender from that form', function () {
    $user = User::factory()->create(['date_of_birth' => null, 'gender' => null, 'marital_status' => 'single']);

    $result = app(RecordEditForms::class)->update($user, [
        'name' => CaptureForms::PERSONAL,
        'answers' => [CaptureForms::LEAD => ['date_of_birth' => '1981-03-14', 'gender' => 'female', 'marital_status' => 'single']],
        'record' => ['type' => 'personal', 'id' => $user->id],
    ], 0);
    $fresh = $user->fresh();

    expect($result['success'])->toBeTrue()
        ->and($fresh->date_of_birth->format('Y-m-d'))->toBe('1981-03-14')
        ->and($fresh->gender)->toBe('female');
});

/*
 * Item 8a (CSJ 2026-10-06): smoking and health are asked on the same form and
 * saved to the user's own answers, the columns the web and /m Health forms write.
 */
it('saves smoking and health from that form, and shows them when it opens again', function () {
    $user = User::factory()->create(['date_of_birth' => '1960-05-01', 'gender' => 'male', 'marital_status' => 'married', 'smoking_status' => null, 'health_status' => null]);

    $result = app(RecordEditForms::class)->update($user, [
        'name' => CaptureForms::PERSONAL,
        'answers' => [CaptureForms::LEAD => ['date_of_birth' => '1960-05-01', 'gender' => 'male', 'marital_status' => 'married', 'smoking_status' => 'yes', 'health_status' => 'no_both']],
        'record' => ['type' => 'personal', 'id' => $user->id],
    ], 0);
    $fresh = $user->fresh();

    expect($result['success'])->toBeTrue()
        ->and($fresh->smoking_status)->toBe('yes')
        ->and($fresh->health_status)->toBe('no_both')
        ->and(app(RecordEditForms::class)->formForResource($fresh, 'personal_information')['answers'][CaptureForms::LEAD])
        ->toMatchArray(['smoking_status' => 'yes', 'health_status' => 'no_both']);
});

it('refuses a smoking answer that is not on the list', function () {
    $user = User::factory()->create(['date_of_birth' => '1960-05-01', 'gender' => 'male', 'marital_status' => 'married', 'smoking_status' => null]);

    $result = app(RecordEditForms::class)->update($user, [
        'name' => CaptureForms::PERSONAL,
        'answers' => [CaptureForms::LEAD => ['date_of_birth' => '1960-05-01', 'gender' => 'male', 'marital_status' => 'married', 'smoking_status' => 'sometimes', 'health_status' => 'yes']],
        'record' => ['type' => 'personal', 'id' => $user->id],
    ], 0);

    expect($result['success'] ?? false)->toBeFalse()
        ->and($user->fresh()->smoking_status)->toBeNull();
});
