<?php

declare(strict_types=1);

use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingStateMachine;

it('lists the property form and returns null for an unknown form', function (): void {
    expect(CaptureForms::names())->toBe(['property', 'isa', 'savings', 'investment'])
        ->and(CaptureForms::schema('property')['name'])->toBe('property')
        ->and(CaptureForms::schema('bank'))->toBeNull();
});

it('offers the three property kinds in order: Home, Second home, Buy to let', function (): void {
    $kinds = CaptureForms::schema('property')['kinds'];

    expect(array_column($kinds, 'key'))->toBe(['main_residence', 'secondary_residence', 'buy_to_let'])
        ->and(array_column($kinds, 'label'))->toBe(['Home', 'Second home', 'Buy to let'])
        ->and(CaptureForms::kindLabel('property', 'secondary_residence'))->toBe('Second home');
});

it('names the create tool and entity type on every property kind', function (): void {
    foreach (CaptureForms::schema('property')['kinds'] as $kind) {
        expect($kind['tool'])->toBe('create_property')
            ->and($kind['entity_type'])->toBe('property');
    }
    expect(CaptureForms::kind('property', 'buy_to_let')['label'])->toBe('Buy to let')
        ->and(CaptureForms::kind('property', 'castle'))->toBeNull()
        ->and(CaptureForms::kindLabel('property', 'castle'))->toBe('castle');
});

it('builds field rules for a text, an optional money and a bounded percent', function (): void {
    expect(CaptureForms::fieldRules('cash_isa', 'provider', ['type' => 'text', 'required' => true]))
        ->toBe(['required_with:cash_isa', 'string', 'max:255'])
        ->and(CaptureForms::fieldRules('cash_isa', 'paid_in_this_year', ['type' => 'money', 'required' => false]))
        ->toBe(['nullable', 'numeric', 'min:0', 'max:999999999.99'])
        ->and(CaptureForms::fieldRules('cash_isa', 'interest_rate', ['type' => 'percent', 'min' => 0, 'max' => 20]))
        ->toBe(['nullable', 'numeric', 'min:0', 'max:20'])
        ->and(CaptureForms::fieldRules('main_residence', 'ownership_percentage', ['type' => 'percent']))
        ->toBe(['nullable', 'numeric', 'min:0.01', 'max:99.99'])
        ->and(CaptureForms::fieldRules('easy_access', 'interest_rate', ['type' => 'percent', 'required' => true, 'min' => 0, 'max' => 20]))
        ->toBe(['required_with:easy_access', 'numeric', 'min:0', 'max:20']);
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

// ── Account forms (CSJ 2026-09-16) ─────────────────────────────────────────

it('offers the two ISA kinds with no ownership field, routed to the right tool', function (): void {
    $schema = CaptureForms::schema('isa');
    expect(array_column($schema['kinds'], 'key'))->toBe(['cash_isa', 'stocks_shares_isa'])
        ->and(array_column($schema['kinds'], 'label'))->toBe(['Cash ISA', 'Stocks and Shares ISA'])
        ->and(array_column($schema['kinds'], 'tool'))->toBe(['create_savings_account', 'create_investment_account'])
        ->and($schema['fields'])->not->toHaveKey('ownership_type')
        ->and($schema['kinds'][0]['fields'])->toBe(['provider', 'current_value', 'paid_in_this_year', 'interest_rate'])
        ->and($schema['kinds'][1]['fields'])->toBe(['provider', 'current_value', 'paid_in_this_year'])
        ->and($schema['fields']['provider']['type'])->toBe('text')
        ->and($schema['fields']['paid_in_this_year']['required'])->toBeFalse()
        ->and($schema['fields']['interest_rate'])->toMatchArray(['min' => 0, 'max' => 20]);
});

it('builds the ISA inputs: a cash ISA is a savings row, the others investment rows, all individual', function (): void {
    $inputs = CaptureForms::toolInputs(['name' => 'isa', 'answers' => [
        'cash_isa' => ['provider' => ' Nationwide ', 'current_value' => 12000, 'paid_in_this_year' => 4000, 'interest_rate' => 4.5],
        'stocks_shares_isa' => ['provider' => 'Vanguard', 'current_value' => 30000, 'paid_in_this_year' => 6000],
    ]]);

    expect($inputs['cash_isa'])->toBe([
        'account_name' => 'Nationwide Cash ISA', 'account_type' => 'cash_isa', 'is_isa' => true, 'institution' => 'Nationwide',
        'current_balance' => 12000.0, 'ownership_type' => 'individual', 'interest_rate' => 4.5, 'isa_subscription_amount' => 4000.0,
    ])
        ->and($inputs['stocks_shares_isa'])->toBe([
            'account_name' => 'Vanguard Stocks and Shares ISA', 'account_type' => 'stocks_shares_isa', 'isa_type' => 'stocks_and_shares',
            'provider' => 'Vanguard', 'current_value' => 30000.0, 'ownership_type' => 'individual', 'isa_subscription_current_year' => 6000.0,
        ])
        ->and($inputs['stocks_shares_isa']['isa_subscription_current_year'])->toBe(6000.0)
        ->and($inputs)->not->toHaveKey('lifetime_isa');

    expect(CaptureForms::summarise(['name' => 'isa', 'answers' => [
        'cash_isa' => ['provider' => 'Nationwide', 'current_value' => 12000, 'paid_in_this_year' => 4000],
        'stocks_shares_isa' => ['provider' => 'Vanguard', 'current_value' => 30000],
    ]]))->toBe('Cash ISA with Nationwide, balance £12,000, £4,000 paid in this year. Stocks and Shares ISA with Vanguard, balance £30,000.');
});

it('offers the four bank kinds; the rate is required on savings kinds and optional on the current account', function (): void {
    $schema = CaptureForms::schema('savings');
    expect(array_column($schema['kinds'], 'key'))->toBe(['current_account', 'easy_access', 'fixed', 'notice'])
        ->and(array_column($schema['kinds'], 'label'))->toBe(['Current account', 'Easy access savings', 'Fixed rate savings', 'Notice account'])
        ->and(array_unique(array_column($schema['kinds'], 'tool')))->toBe(['create_savings_account'])
        ->and($schema['kinds'][0]['fields'])->toContain('current_account_interest_rate')->not->toContain('interest_rate')
        ->and($schema['kinds'][1]['fields'])->toContain('interest_rate')
        ->and($schema['fields']['interest_rate']['required'])->toBeTrue()
        ->and($schema['fields']['current_account_interest_rate']['required'])->toBeFalse()
        ->and(array_column($schema['fields']['ownership_type']['options'], 'value'))->toBe(['individual', 'joint'])
        ->and($schema['fields'])->not->toHaveKey('ownership_percentage');
});

it('builds the bank inputs with a composed account name and joint fixed at 50', function (): void {
    $form = ['name' => 'savings', 'answers' => [
        'current_account' => ['provider' => 'Barclays', 'current_value' => 3200, 'ownership_type' => 'joint'],
        'easy_access' => ['provider' => 'Marcus', 'current_value' => 10000, 'interest_rate' => 4.1, 'ownership_type' => 'individual'],
    ]];
    $inputs = CaptureForms::toolInputs($form);

    expect($inputs['current_account'])->toBe([
        'account_name' => 'Barclays current account', 'account_type' => 'current_account', 'institution' => 'Barclays',
        'current_balance' => 3200.0, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0,
    ])
        ->and($inputs['easy_access'])->toBe([
            'account_name' => 'Marcus easy access savings', 'account_type' => 'easy_access', 'institution' => 'Marcus',
            'current_balance' => 10000.0, 'ownership_type' => 'individual', 'interest_rate' => 4.1,
        ])
        ->and(CaptureForms::summarise($form))->toBe('Barclays current account, balance £3,200, joint. Marcus easy access savings, balance £10,000, 4.1% interest, individual.');

    $withRate = CaptureForms::toolInputs(['name' => 'savings', 'answers' => [
        'current_account' => ['provider' => 'Barclays', 'current_value' => 1, 'current_account_interest_rate' => 1.5, 'ownership_type' => 'individual'],
    ]]);
    expect($withRate['current_account']['interest_rate'])->toBe(1.5);
});

it('offers the two investment kinds and builds their inputs with the share only when joint', function (): void {
    $schema = CaptureForms::schema('investment');
    expect(array_column($schema['kinds'], 'key'))->toBe(['gia', 'other'])
        ->and(array_column($schema['kinds'], 'label'))->toBe(['General Investment Account', 'Other investment'])
        ->and($schema['fields']['ownership_percentage']['required_when'])->toBe(['field' => 'ownership_type', 'in' => ['joint']]);

    $form = ['name' => 'investment', 'answers' => [
        'gia' => ['provider' => 'Vanguard', 'current_value' => 45000, 'ownership_type' => 'individual', 'ownership_percentage' => 40],
        'other' => ['provider' => 'Freetrade', 'investment_type' => 'shares', 'current_value' => 5000, 'ownership_type' => 'joint'],
    ]];
    $inputs = CaptureForms::toolInputs($form);
    expect($inputs['gia'])->toBe([
        'account_name' => 'Vanguard General Investment Account', 'account_type' => 'personal_investment_account', 'provider' => 'Vanguard',
        'current_value' => 45000.0, 'ownership_type' => 'individual',
    ])
        ->and($inputs['other'])->toBe([
            'account_name' => 'Freetrade shares', 'account_type' => 'other', 'provider' => 'Freetrade',
            'current_value' => 5000.0, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0,
        ])
        ->and(CaptureForms::summarise($form))->toBe('General Investment Account with Vanguard worth £45,000, individual. Shares with Freetrade worth £5,000, joint, my share 50%.')
        ->and(CaptureForms::schema('investment')['fields']['investment_type']['required'])->toBeFalse()
        ->and(CaptureForms::schema('investment')['kinds'][0]['fields'])->not->toContain('investment_type');

    $untyped = ['name' => 'investment', 'answers' => ['other' => ['provider' => 'Freetrade', 'current_value' => 5000, 'ownership_type' => 'individual']]];
    expect(CaptureForms::toolInputs($untyped)['other']['account_name'])->toBe('Freetrade Other investment')
        ->and(CaptureForms::summarise($untyped))->toBe('Other investment with Freetrade worth £5,000, individual.');
});

it('the three account steps are form turns owned by the corpus with short lead-ins', function (): void {
    OnboardingStateMachine::flushTransitionTableCache();
    $isa = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS);
    $bank = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS);
    $inv = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS);

    expect([$isa['turn_type'], $isa['form'], $isa['form_prompt_text'], $isa['next']])->toBe(['form', 'isa', 'Now your ISAs.', 'campaign_isa_more'])
        ->and([$bank['turn_type'], $bank['form'], $bank['form_prompt_text'], $bank['next']])->toBe(['form', 'savings', 'Now your bank and savings accounts.', 'campaign_bank_accounts_more'])
        ->and([$inv['turn_type'], $inv['form'], $inv['form_prompt_text'], $inv['next']])->toBe(['form', 'investment', 'Now your investments.', 'campaign_investment_accounts_more'])
        ->and($isa['prompt_text'])->toContain('Cash, Stocks & Shares, Lifetime')
        ->and($bank['skip_if'])->toBe([OnboardingStateMachine::class, 'skipIfNoBankOrSavings'])
        ->and($bank['record_context'])->toBe('savings');
});
