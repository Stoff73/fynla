<?php

declare(strict_types=1);

use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\RecordEditForms;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/*
 * /m Expenditure "Edit details" opens the spending form the way it was
 * entered (TODO item 7a Found line): the one-box form for a monthly total,
 * the category form for a breakdown, so an edit never writes the one box over
 * a breakdown. Both write through HouseholdExpenditureWriter, the web
 * Expenditure form's writer, so a linked couple's figure is one household
 * figure halved onto both rows whichever surface entered it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
});

function expenditureEditCouple(array $attributes = []): array
{
    $user = User::factory()->withActivePremiumSubscription()->create(['marital_status' => 'married'] + $attributes);
    $spouse = User::factory()->withActivePremiumSubscription()->create(['marital_status' => 'married', 'spouse_id' => $user->id]);
    $user->update(['spouse_id' => $spouse->id]);

    return [$user->fresh(), $spouse->fresh()];
}

it('opens /m\'s "Edit details" on the one-box form, filled with what is recorded', function () {
    $user = User::factory()->create([
        'monthly_expenditure' => 2500, 'expenditure_entry_mode' => 'simple',
        'childcare' => 400, 'charitable_donations' => 50, 'is_gift_aid' => true,
    ]);

    $form = app(RecordEditForms::class)->formForResource($user, 'expenditure');

    expect($form['name'])->toBe(CaptureForms::EXPENDITURE)
        ->and($form['record'])->toBe(['type' => 'expenditure', 'id' => $user->id])
        ->and($form['answers'][CaptureForms::LEAD])->toBe([
            'monthly_total' => 2500.0, 'childcare' => 400.0, 'charitable_donations' => 50.0, 'is_gift_aid' => 'yes',
        ]);
});

it('opens a breakdown on the category form, each figure in its group, and keeps the breakdown on save', function () {
    $user = User::factory()->withActivePremiumSubscription()->create([
        'expenditure_entry_mode' => 'category', 'expenditure_sharing_mode' => 'separate',
        'rent' => 1200, 'food_groceries' => 500, 'subscriptions' => 40, 'monthly_expenditure' => 1740,
    ]);
    $edit = app(RecordEditForms::class);

    $form = $edit->formForResource($user, 'expenditure');

    expect($form['name'])->toBe(CaptureForms::EXPENDITURE_DETAILED)
        ->and($form['schema']['tool'])->toBe('set_expenditure')
        ->and($form['answers'])->toBe([
            'essential' => ['rent' => 1200.0, 'food_groceries' => 500.0],
            'communication' => ['subscriptions' => 40.0],
        ]);

    $form['answers']['essential']['food_groceries'] = '650';
    $result = $edit->update($user, $form, 0);
    $fresh = $user->fresh();

    expect($result['success'])->toBeTrue()
        ->and($fresh->expenditure_entry_mode)->toBe('category')
        ->and((float) $fresh->food_groceries)->toBe(650.0)
        ->and((float) $fresh->rent)->toBe(1200.0)
        ->and((float) $fresh->subscriptions)->toBe(40.0)
        ->and((float) $fresh->monthly_expenditure)->toBe(1890.0);
});

it('offers a free user the one-box form, as the plan\'s own spending form', function () {
    $user = User::factory()->create(['expenditure_entry_mode' => 'category', 'rent' => 1200, 'monthly_expenditure' => 1200]);

    expect(app(RecordEditForms::class)->formForResource($user, 'expenditure')['name'])->toBe(CaptureForms::EXPENDITURE);
});

it('writes a linked couple\'s one-box figure as the household\'s, half on each row, as the web form does', function () {
    [$user, $spouse] = expenditureEditCouple(['expenditure_sharing_mode' => 'joint']);
    $edit = app(RecordEditForms::class);

    $result = $edit->update($user, [
        'name' => CaptureForms::EXPENDITURE,
        'answers' => [CaptureForms::LEAD => ['monthly_total' => '3000']],
        'record' => ['type' => 'expenditure', 'id' => $user->id],
    ], 0);

    expect($result['success'])->toBeTrue()
        ->and((float) $user->fresh()->monthly_expenditure)->toBe(1500.0)
        ->and((float) $spouse->fresh()->monthly_expenditure)->toBe(1500.0)
        ->and($spouse->fresh()->expenditure_entry_mode)->toBe('simple')
        ->and((float) ExpenditureProfile::where('user_id', $user->id)->value('total_monthly_expenditure'))->toBe(1500.0)
        // Opened again, the form shows the household's figure, not one half.
        ->and($edit->formForResource($user->fresh(), 'expenditure')['answers'][CaptureForms::LEAD]['monthly_total'])->toBe(3000.0);
});

it('opens a linked couple\'s breakdown in household figures, so a save does not halve it again', function () {
    [$user] = expenditureEditCouple([
        'expenditure_sharing_mode' => 'joint', 'expenditure_sharing_mode_declared_at' => now(),
        'expenditure_entry_mode' => 'category', 'food_groceries' => 300, 'rent' => 1000,
    ]);

    $form = app(RecordEditForms::class)->formForResource($user, 'expenditure');

    // Groceries divide between the two rows; rent stays whole on one (SharedExpenditure::SHARED_FIELDS).
    expect($form['answers']['essential'])->toBe(['rent' => 1000.0, 'food_groceries' => 600.0]);
});

it('records a zero monthly total on the spending profile, so a stale figure cannot win', function () {
    $user = User::factory()->create(['monthly_expenditure' => 2000, 'expenditure_entry_mode' => 'simple']);
    ExpenditureProfile::create(['user_id' => $user->id, 'total_monthly_expenditure' => 2000]);

    app(RecordEditForms::class)->update($user, [
        'name' => CaptureForms::EXPENDITURE,
        'answers' => [CaptureForms::LEAD => ['monthly_total' => '0']],
        'record' => ['type' => 'expenditure', 'id' => $user->id],
    ], 0);

    expect((float) $user->fresh()->monthly_expenditure)->toBe(0.0)
        ->and((float) ExpenditureProfile::where('user_id', $user->id)->value('total_monthly_expenditure'))->toBe(0.0);
});
