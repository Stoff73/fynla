<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\RecordEditForms;
use App\Services\Onboarding\TypedFormFill;
use Illuminate\Support\Facades\Http;

/*
 * A change typed to Fyn fills in the record's form, nothing saved (CSJ
 * 2026-10-05, option A). Each value is checked against the field's own rule
 * (CaptureForms::fieldRules); anything else is left as recorded.
 */
function typedFillModelReturns(array $fields): void
{
    config(['services.xai.api_key' => 'test']);
    Http::fake(['api.x.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['fields' => $fields])]]]])]);
}

function personalFormFor(User $user): array
{
    return app(RecordEditForms::class)->formFor($user, 'personal', $user->id);
}

it('fills in the new date of birth over the recorded one', function () {
    $user = User::factory()->create(['date_of_birth' => '1960-08-01', 'gender' => 'female', 'marital_status' => 'single']);
    typedFillModelReturns(['date_of_birth' => '1960-08-02']);

    $filled = app(TypedFormFill::class)->fill(personalFormFor($user), 'Actually my date of birth is 2 August 1960');

    expect($filled['filled'])->toBe(['date_of_birth'])
        ->and($filled['answers'][CaptureForms::LEAD])->toBe(['date_of_birth' => '1960-08-02', 'gender' => 'female', 'marital_status' => 'single'])
        ->and($user->fresh()->date_of_birth->format('Y-m-d'))->toBe('1960-08-01');
});

it('drops a value its field would refuse, and one that is no change', function () {
    $user = User::factory()->create(['date_of_birth' => '1960-08-01', 'gender' => 'female', 'marital_status' => 'single']);
    typedFillModelReturns(['gender' => 'unknown', 'marital_status' => 'single', 'date_of_birth' => '2 Aug 1960']);

    expect(app(TypedFormFill::class)->fill(personalFormFor($user), 'I am single'))->toBeNull();
});

it('leaves a question alone', function () {
    $user = User::factory()->create(['date_of_birth' => '1960-08-01']);
    typedFillModelReturns([]);

    expect(app(TypedFormFill::class)->fill(personalFormFor($user), 'Why do you need my gender?'))->toBeNull();
});

it('fills a category on the detailed spending form under its group', function () {
    $user = User::factory()->withActivePremiumSubscription()->create([
        'expenditure_entry_mode' => 'category', 'expenditure_sharing_mode' => 'separate', 'food_groceries' => 400, 'rent' => 900,
    ]);
    typedFillModelReturns(['food_groceries' => 450]);

    $form = app(RecordEditForms::class)->formFor($user, 'expenditure', $user->id);
    $filled = app(TypedFormFill::class)->fill($form, 'Groceries are more like £450 now');

    expect($filled['answers']['essential'])->toBe(['rent' => 900.0, 'food_groceries' => 450.0]);
});
