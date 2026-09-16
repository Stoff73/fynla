<?php

declare(strict_types=1);

use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingStateMachine;

it('lists the property form and returns null for an unknown form', function (): void {
    expect(CaptureForms::names())->toBe(['property'])
        ->and(CaptureForms::schema('property')['name'])->toBe('property')
        ->and(CaptureForms::schema('bank'))->toBeNull();
});

it('offers the three property kinds in order: Home, Second home, Buy to let', function (): void {
    $kinds = CaptureForms::schema('property')['kinds'];

    expect(array_column($kinds, 'key'))->toBe(['main_residence', 'secondary_residence', 'buy_to_let'])
        ->and(array_column($kinds, 'label'))->toBe(['Home', 'Second home', 'Buy to let'])
        ->and(CaptureForms::kindLabel('property', 'secondary_residence'))->toBe('Second home');
});

it('marks the required fields and the conditional share', function (): void {
    $fields = CaptureForms::schema('property')['fields'];
    expect($fields['current_value']['required'])->toBeTrue()
        ->and($fields['mortgage_outstanding_balance']['required'])->toBeTrue()
        ->and($fields['monthly_rental_income']['required'])->toBeTrue()
        ->and($fields['ownership_type']['required'])->toBeTrue()
        ->and($fields['ownership_percentage']['required'])->toBeFalse()
        ->and($fields['ownership_percentage']['required_when'])->toBe(['field' => 'ownership_type', 'in' => ['joint', 'tenants_in_common']])
        ->and(array_column($fields['ownership_type']['options'], 'value'))->toBe(['individual', 'joint', 'tenants_in_common']);
});

it('builds one create_property input per filled kind with nothing unstated', function (): void {
    $inputs = CaptureForms::toolInputs(['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
        'buy_to_let' => ['current_value' => 450000, 'mortgage_outstanding_balance' => null, 'monthly_rental_income' => 1000, 'ownership_type' => 'individual'],
    ]]);

    expect(array_keys($inputs))->toBe(['main_residence', 'buy_to_let'])
        ->and($inputs['main_residence'])->toBe([
            'property_type' => 'main_residence', 'current_value' => 750000.0, 'has_mortgage' => true,
            'mortgage_outstanding_balance' => 325000.0, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0,
        ])
        ->and($inputs['buy_to_let'])->toBe([
            'property_type' => 'buy_to_let', 'current_value' => 450000.0, 'has_mortgage' => false,
            'monthly_rental_income' => 1000.0, 'ownership_type' => 'individual',
        ]);
});

it('builds a create_property input for a second home in the same shape as Home', function (): void {
    $inputs = CaptureForms::toolInputs(['name' => 'property', 'answers' => [
        'secondary_residence' => ['current_value' => 300000, 'mortgage_outstanding_balance' => null, 'ownership_type' => 'individual'],
    ]]);

    expect($inputs['secondary_residence'])->toBe([
        'property_type' => 'secondary_residence', 'current_value' => 300000.0, 'has_mortgage' => false,
        'ownership_type' => 'individual',
    ]);
});

it('drops a share sent for an individual owner and defaults a missing shared one to 50', function (): void {
    $inputs = CaptureForms::toolInputs(['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 1, 'mortgage_outstanding_balance' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 40],
        'buy_to_let' => ['current_value' => 1, 'mortgage_outstanding_balance' => null, 'monthly_rental_income' => 1, 'ownership_type' => 'tenants_in_common'],
    ]]);

    expect($inputs['main_residence'])->not->toHaveKey('ownership_percentage')
        ->and($inputs['buy_to_let']['ownership_percentage'])->toBe(50.0);
});

it('composes the transcript line in plain words', function (): void {
    $line = CaptureForms::summarise(['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
        'buy_to_let' => ['current_value' => 450000, 'mortgage_outstanding_balance' => null, 'monthly_rental_income' => 1000, 'ownership_type' => 'individual'],
    ]]);

    expect($line)->toBe('Home worth £750,000, mortgage £325,000, joint, my share 50%. Buy to let worth £450,000, no mortgage, rent £1,000 a month, individual.');
});

it('composes the transcript line for a second home', function (): void {
    $line = CaptureForms::summarise(['name' => 'property', 'answers' => [
        'secondary_residence' => ['current_value' => 300000, 'mortgage_outstanding_balance' => null, 'ownership_type' => 'individual'],
    ]]);

    expect($line)->toBe('Second home worth £300,000, no mortgage, individual.');
});

it('produces validation rules per kind and field', function (): void {
    $rules = CaptureForms::rules('property');
    expect($rules['main_residence.current_value'])->toBe(['required_with:main_residence', 'numeric', 'min:0', 'max:999999999.99'])
        ->and($rules['main_residence.mortgage_outstanding_balance'])->toBe(['present', 'nullable', 'numeric', 'min:0', 'max:999999999.99'])
        ->and($rules['buy_to_let.monthly_rental_income'])->toBe(['required_with:buy_to_let', 'numeric', 'min:0', 'max:999999.99'])
        ->and($rules['buy_to_let.ownership_type'])->toBe(['required_with:buy_to_let', 'in:individual,joint,tenants_in_common'])
        ->and($rules['buy_to_let.ownership_percentage'])->toBe(['nullable', 'numeric', 'min:0.01', 'max:99.99'])
        ->and($rules)->not->toHaveKey('main_residence.monthly_rental_income');
});

it('the property step is a form turn owned by the corpus', function (): void {
    OnboardingStateMachine::flushTransitionTableCache();
    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY);

    // final-review C1 — prompt_text is the typed instruction a client
    // without the forms capability (native) falls back to; CSJ 2026-09-16:
    // form_prompt_text is now just the short lead-in, since the form's own
    // boxes and Save button carry the instructions, read only by a
    // form-capable client's capture_form event
    // (OnboardingChatDirector::emitTurnForState).
    expect($state['turn_type'])->toBe('form')
        ->and($state['form'])->toBe('property')
        ->and($state['capture_focus'])->toBe('property')
        ->and($state['prompt_text'])->toBe("Now your property. **For each one: is it your home, a second home or a buy-to-let; roughly what it's worth; whether there's a mortgage and how much is left on it; and whether you own it individually or jointly? If jointly, who owns it with you and your share.**")
        ->and($state['form_prompt_text'])->toBe('Now your property.');
});
