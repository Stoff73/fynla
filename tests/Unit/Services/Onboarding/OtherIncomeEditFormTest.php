<?php

declare(strict_types=1);

use App\Models\Employment;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\RecordEditForms;

/*
 * Dividends, interest, trust and other income are edited through a Fyn form
 * (TODO item 7a; CSJ 2026-10-01: all Fyn capture through forms), written by
 * update_profile, the one Fyn writer of the profile's income figures.
 */
function otherIncomeForm(array $lead, int $userId): array
{
    return [
        'name' => CaptureForms::OTHER_INCOME,
        'answers' => [CaptureForms::LEAD => $lead],
        'record' => ['type' => 'other_income', 'id' => $userId],
    ];
}

it('is one write through update_profile with the four figures the web Income form edits outside jobs', function () {
    $schema = CaptureForms::schema(CaptureForms::OTHER_INCOME);

    expect(CaptureForms::names())->toContain(CaptureForms::OTHER_INCOME)
        ->and($schema['tool'])->toBe('update_profile')
        ->and($schema['lead_fields'])->toBe(['annual_dividend_income', 'annual_interest_income', 'annual_trust_income', 'annual_other_income'])
        ->and(CaptureForms::rules(CaptureForms::OTHER_INCOME)['_lead.annual_trust_income'])->toContain('min:0');
});

it('is listed with the jobs whenever Fyn offers income to change', function () {
    $user = User::factory()->create();
    Employment::create(['user_id' => $user->id, 'income_type' => 'employment', 'employer' => 'Acme Ltd', 'annual_income' => 40000, 'is_estimate' => false]);

    $types = collect(app(RecordEditForms::class)->candidates($user, 'income'))->pluck('type')->all();

    expect($types)->toBe(['employment', 'other_income']);
});

it('opens filled with what is recorded, leaving interest blank when the accounts\' figure is used', function () {
    $user = User::factory()->create(['annual_dividend_income' => 1200, 'annual_interest_income' => 0, 'annual_trust_income' => null, 'annual_other_income' => 300]);

    $form = app(RecordEditForms::class)->formFor($user, 'other_income', $user->id);

    expect($form['name'])->toBe(CaptureForms::OTHER_INCOME)
        ->and($form['schema']['edit'])->toBeTrue()
        ->and($form['answers'][CaptureForms::LEAD])->toBe(['annual_dividend_income' => 1200.0, 'annual_other_income' => 300.0]);
});

it('saves every figure, including trust income and a typed interest figure, and reads it back', function () {
    $user = User::factory()->create(['annual_dividend_income' => 1200, 'annual_interest_income' => null, 'annual_trust_income' => null, 'annual_other_income' => null]);
    $form = otherIncomeForm(['annual_dividend_income' => '900', 'annual_interest_income' => '450', 'annual_trust_income' => '2000', 'annual_other_income' => '0'], $user->id);

    $result = app(RecordEditForms::class)->update($user, $form, 0);
    $fresh = $user->fresh();

    expect($result['success'])->toBeTrue()
        ->and((float) $fresh->annual_dividend_income)->toBe(900.0)
        ->and((float) $fresh->annual_interest_income)->toBe(450.0)
        ->and((float) $fresh->annual_trust_income)->toBe(2000.0)
        ->and((float) $fresh->annual_other_income)->toBe(0.0)
        ->and(CaptureForms::summarise($form))->toBe('I receive £900 a year in dividends, £450 a year in interest, £2,000 a year in trust income, £0 a year in other income.');
});

it('opens /m\'s "Edit details" on the form that edits that income source', function () {
    $user = User::factory()->create();
    $job = Employment::create(['user_id' => $user->id, 'income_type' => 'employment', 'employer' => 'Acme Ltd', 'annual_income' => 40000, 'is_estimate' => false]);
    $edit = app(RecordEditForms::class);

    expect($edit->formForResource($user, 'income', ['income_owner' => 'user', 'income_source' => 'dividend'])['name'])->toBe(CaptureForms::OTHER_INCOME)
        ->and($edit->formForResource($user, 'income', ['income_owner' => 'user', 'income_source' => 'employment'])['record'])->toBe(['type' => 'employment', 'id' => $job->id])
        ->and($edit->formForResource($user, 'income', ['income_owner' => 'spouse', 'income_source' => 'dividend']))->toBeNull()
        ->and($edit->formForResource($user, 'income', ['income_owner' => 'user', 'income_source' => 'rental']))->toBeNull();
});

it('reads back 0 interest as the savings accounts\' figure, never as none', function () {
    $form = ['name' => CaptureForms::OTHER_INCOME, 'answers' => [CaptureForms::LEAD => ['annual_dividend_income' => '900', 'annual_interest_income' => '0']]];

    expect(CaptureForms::summarise($form))->toBe('I receive £900 a year in dividends, interest worked out from my savings accounts.');
});
