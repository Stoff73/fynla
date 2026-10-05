<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
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
/** @param  array<string, array<string, array<string, mixed>>>  $forms  form index => section => field => value */
function typedFillModelReturns(array $forms): void
{
    config(['services.xai.api_key' => 'test']);
    Http::fake(['api.x.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['forms' => $forms])]]]])]);
}

function personalFormFor(User $user): array
{
    return app(RecordEditForms::class)->formFor($user, 'personal', $user->id);
}

it('fills in the new date of birth over the recorded one', function () {
    $user = User::factory()->create(['date_of_birth' => '1960-08-01', 'gender' => 'female', 'marital_status' => 'single']);
    typedFillModelReturns(['0' => ['_lead' => ['date_of_birth' => '1960-08-02']]]);

    $filled = app(TypedFormFill::class)->fill(personalFormFor($user), 'Actually my date of birth is 2 August 1960');

    expect($filled['filled'])->toBe(['date_of_birth'])
        ->and($filled['answers'][CaptureForms::LEAD])->toBe(['date_of_birth' => '1960-08-02', 'gender' => 'female', 'marital_status' => 'single'])
        ->and($user->fresh()->date_of_birth->format('Y-m-d'))->toBe('1960-08-01');
});

it('drops a value its field would refuse, and one that is no change', function () {
    $user = User::factory()->create(['date_of_birth' => '1960-08-01', 'gender' => 'female', 'marital_status' => 'single']);
    typedFillModelReturns(['0' => ['_lead' => ['gender' => 'unknown', 'marital_status' => 'single', 'date_of_birth' => '2 Aug 1960']]]);

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
    typedFillModelReturns(['0' => ['essential' => ['food_groceries' => 450]]]);

    $form = app(RecordEditForms::class)->formFor($user, 'expenditure', $user->id);
    $filled = app(TypedFormFill::class)->fill($form, 'Groceries are more like £450 now');

    expect($filled['answers']['essential'])->toBe(['rent' => 900.0, 'food_groceries' => 450.0]);
});

it('opens the kind a blank setup form is told about, and only that kind', function () {
    typedFillModelReturns(['0' => [
        'easy_access' => ['provider' => 'Nationwide', 'current_value' => '5000', 'interest_rate' => 4.1],
        'fixed' => ['shoe_size' => 9],
    ]]);

    $filled = app(TypedFormFill::class)->fill(['schema' => CaptureForms::schema(CaptureForms::SAVINGS), 'answers' => []], 'I have a Nationwide easy access account with £5,000 at 4.1%');

    expect($filled['answers'])->toBe(['easy_access' => ['provider' => 'Nationwide', 'current_value' => 5000.0, 'interest_rate' => 4.1]])
        ->and($filled['filled'])->toBe(['provider', 'current_value', 'interest_rate']);
});

it('reads several records in one call and returns only the one the message fills', function () {
    $user = User::factory()->create();
    $halifax = SavingsAccount::factory()->create(['user_id' => $user->id, 'institution' => 'Halifax', 'account_type' => 'easy_access', 'current_balance' => 4000, 'ownership_type' => 'individual']);
    $monzo = SavingsAccount::factory()->create(['user_id' => $user->id, 'institution' => 'Monzo', 'account_type' => 'easy_access', 'current_balance' => 900, 'ownership_type' => 'individual']);
    $forms = [
        app(RecordEditForms::class)->formFor($user, 'savings_account', $halifax->id),
        app(RecordEditForms::class)->formFor($user, 'savings_account', $monzo->id),
    ];
    $section = (string) array_key_first($forms[1]['answers']);
    typedFillModelReturns(['1' => [$section => ['current_value' => 1200]]]);

    $filled = app(TypedFormFill::class)->fillAny($forms, 'My Monzo balance is £1,200 now');

    expect(array_keys($filled))->toBe([1])
        ->and($filled[1]['answers'][$section]['current_value'])->toBe(1200.0)
        ->and(Http::recorded())->toHaveCount(1);
});
