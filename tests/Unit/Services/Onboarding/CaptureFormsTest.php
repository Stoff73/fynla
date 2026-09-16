<?php

declare(strict_types=1);

use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingStateMachine;

it('lists the property form and returns null for an unknown form', function (): void {
    expect(CaptureForms::names())->toBe(['property', 'isa', 'savings', 'investment', 'pension', 'spouse_household', 'spouse_assets', 'personal', 'spouse_details', 'dependants', 'work', 'dob'])
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

it('offers a workplace pension and a personal pension or SIPP, both through create_pension', function (): void {
    $schema = CaptureForms::schema('pension');
    expect(array_column($schema['kinds'], 'key'))->toBe(['workplace', 'personal'])
        ->and(array_column($schema['kinds'], 'label'))->toBe(['Workplace pension', 'Personal pension or SIPP'])
        ->and(array_unique(array_column($schema['kinds'], 'tool')))->toBe(['create_pension'])
        ->and($schema['kinds'][0]['fields'])->toBe(['provider', 'current_value', 'employee_contribution_percent', 'employer_contribution_percent', 'salary_sacrifice'])
        ->and($schema['kinds'][1]['fields'])->toBe(['provider', 'current_value', 'annual_contribution'])
        ->and($schema['fields']['current_value']['required'])->toBeFalse()
        ->and($schema['fields']['employee_contribution_percent'])->toMatchArray(['required' => true, 'min' => 0, 'max' => 100])
        ->and(array_column($schema['fields']['salary_sacrifice']['options'], 'value'))->toBe(['yes', 'no']);

    $form = ['name' => 'pension', 'answers' => [
        'workplace' => ['provider' => 'Aviva', 'current_value' => 42000, 'employee_contribution_percent' => 5, 'employer_contribution_percent' => 3, 'salary_sacrifice' => 'yes'],
        'personal' => ['provider' => 'Vanguard', 'annual_contribution' => 6000],
    ]];
    $inputs = CaptureForms::toolInputs($form);
    expect($inputs['workplace'])->toBe([
        'pension_category' => 'dc', 'scheme_name' => 'Aviva workplace pension', 'scheme_type' => 'occupational', 'provider' => 'Aviva',
        'current_fund_value' => 42000.0, 'employee_contribution_percent' => 5.0, 'employer_contribution_percent' => 3.0, 'salary_sacrifice' => true,
    ])
        ->and($inputs['personal'])->toBe([
            'pension_category' => 'dc', 'scheme_name' => 'Vanguard personal pension or SIPP', 'scheme_type' => 'personal', 'provider' => 'Vanguard',
            'monthly_contribution_amount' => 500.0,
        ])
        ->and(CaptureForms::summarise($form))->toBe('Workplace pension with Aviva, worth £42,000, I pay 5% and my employer 3%, salary sacrifice. Personal pension or SIPP with Vanguard, I pay in £6,000 a year.');
});

it('the pension step is a form turn with the loop question after it', function (): void {
    OnboardingStateMachine::flushTransitionTableCache();
    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);
    expect([$state['turn_type'], $state['form'], $state['form_prompt_text'], $state['next']])->toBe(['form', 'pension', 'Now your pensions.', 'campaign_pension_more'])
        ->and($state['capture_focus'])->toBe('occupational')
        ->and($state['prompt_text'])->toContain('workplace pension');
});

it('the spouse forms fold every section into one household write', function (): void {
    $schema = CaptureForms::schema('spouse_household');
    expect($schema['tool'])->toBe('capture_spouse_household_data')
        ->and($schema['lead_fields'])->toBe(['spouse_annual_income'])
        ->and($schema['kinds_prompt'])->toBe('Do they have any of the following? You can choose more than one.')
        ->and(array_column($schema['kinds'], 'label'))->toBe(['ISAs', 'A pension', 'Investments'])
        ->and(CaptureForms::rules('spouse_household'))->toHaveKey('_lead.spouse_annual_income')
        ->and(CaptureForms::rules('spouse_household')['_lead.spouse_annual_income'][0])->toBe('required_with:_lead');

    $form = ['name' => 'spouse_household', 'answers' => [
        '_lead' => ['spouse_annual_income' => 45000],
        'isa' => ['spouse_isa_balance' => 12000, 'spouse_isa_provider' => ' Nationwide '],
        'pension' => ['spouse_pension_input_annual' => 3000],
    ]];
    expect(CaptureForms::toolInputs($form))->toBe(['_lead' => [
        'spouse_annual_income' => 45000.0, 'spouse_isa_balance' => 12000.0, 'spouse_isa_provider' => 'Nationwide', 'spouse_pension_input_annual' => 3000.0,
    ]])
        ->and(CaptureForms::summarise($form))->toBe('My spouse earns £45,000 a year, £12,000 in ISAs with Nationwide, pays £3,000 a year into their pension.');

    $assets = CaptureForms::schema('spouse_assets');
    expect($assets['tool'])->toBe('capture_spouse_non_working_assets')
        ->and($assets['allow_empty'])->toBeTrue()
        ->and(array_column($assets['kinds'], 'label'))->toBe(['Savings', 'ISAs', 'Investments', 'A pension'])
        ->and(CaptureForms::toolInputs(['name' => 'spouse_assets', 'answers' => []]))->toBe(['_lead' => [
            'spouse_existing_savings_balance' => 0.0, 'spouse_existing_isa_balance' => 0.0, 'spouse_existing_investment_balance' => 0.0,
            'spouse_existing_dividend_holdings_value' => 0.0, 'spouse_existing_pension_balance' => 0.0,
        ]])
        ->and(CaptureForms::summarise(['name' => 'spouse_assets', 'answers' => []]))->toBe('My spouse has nothing in their own name.')
        ->and(CaptureForms::summarise(['name' => 'spouse_assets', 'answers' => ['savings' => ['spouse_existing_savings_balance' => 8000]]]))->toBe('£8,000 in savings.');
});

it('the personal form asks date of birth and marital status as lead fields and writes once through capture_personal_details', function (): void {
    $schema = CaptureForms::schema('personal');
    expect($schema['tool'])->toBe('capture_personal_details')
        ->and($schema['lead_fields'])->toBe(['date_of_birth', 'marital_status'])
        ->and($schema['kinds'])->toBe([])
        ->and(array_column($schema['fields']['marital_status']['options'], 'value'))->toBe(['single', 'married', 'civil_partnership', 'divorced', 'widowed'])
        ->and(CaptureForms::rules('personal'))->toBe([
            '_lead.date_of_birth' => ['required_with:_lead', 'date_format:Y-m-d'],
            '_lead.marital_status' => ['required_with:_lead', 'in:single,married,civil_partnership,divorced,widowed'],
        ])
        ->and(CaptureForms::names())->toContain('personal');

    $form = ['name' => 'personal', 'answers' => ['_lead' => ['date_of_birth' => '1985-01-12', 'marital_status' => 'civil_partnership']]];
    expect(CaptureForms::toolInputs($form))->toBe(['_lead' => ['date_of_birth' => '1985-01-12', 'marital_status' => 'civil_partnership']])
        ->and(CaptureForms::summarise($form))->toBe("I was born on 12 January 1985 and I'm in a civil partnership.");

    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL);
    expect($state['form'])->toBe('personal')
        ->and($state['form_prompt_text'])->toBe('Let me grab a few basics first, {first_name}.');
});

it('the spouse details form asks name, date of birth, email and income as lead fields and writes once through capture_spouse_details', function (): void {
    $schema = CaptureForms::schema('spouse_details');
    expect($schema['tool'])->toBe('capture_spouse_details')
        ->and($schema['lead_fields'])->toBe(['first_name', 'last_name', 'date_of_birth', 'email', 'annual_income'])
        ->and($schema['kinds'])->toBe([])
        ->and(CaptureForms::rules('spouse_details')['_lead.email'])->toBe(['required_with:_lead', 'email', 'max:255'])
        ->and(CaptureForms::rules('spouse_details')['_lead.last_name'])->toBe(['nullable', 'string', 'max:255']);

    $form = ['name' => 'spouse_details', 'answers' => ['_lead' => ['first_name' => ' Jamie ', 'date_of_birth' => '1986-03-03', 'email' => 'jamie@example.com', 'annual_income' => 40000]]];
    expect(CaptureForms::toolInputs($form))->toBe(['_lead' => ['first_name' => 'Jamie', 'date_of_birth' => '1986-03-03', 'email' => 'jamie@example.com', 'annual_income' => 40000.0]])
        ->and(CaptureForms::summarise($form))->toBe('My spouse is Jamie, born on 3 March 1986, email jamie@example.com, earning £40,000 a year.');

    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_SPOUSE);
    expect($state['form'])->toBe('spouse_details')
        ->and($state['form_prompt_text'])->toBe("Now your spouse or partner's details.")
        ->and($state['skip_link']['label'])->toBe('Skip this for now');
});

it('the dependants form saves one dependant per turn as a one-item list through capture_dependants, with a loop question after it', function (): void {
    $schema = CaptureForms::schema('dependants');
    expect($schema['tool'])->toBe('capture_dependants')
        ->and($schema['lead_fields'])->toBe(['relationship', 'first_name', 'date_of_birth'])
        ->and(array_column($schema['fields']['relationship']['options'], 'value'))->toBe(['child', 'parent', 'other_dependent']);

    $form = ['name' => 'dependants', 'answers' => ['_lead' => ['relationship' => 'child', 'first_name' => 'Alice', 'date_of_birth' => '2017-09-14']]];
    expect(CaptureForms::toolInputs($form))->toBe(['_lead' => ['dependants' => [['relationship' => 'child', 'first_name' => 'Alice', 'date_of_birth' => '2017-09-14']]]])
        ->and(CaptureForms::summarise($form))->toBe('My child Alice was born on 14 September 2017.')
        ->and(CaptureForms::summarise(['name' => 'dependants', 'answers' => ['_lead' => ['relationship' => 'other_dependent', 'date_of_birth' => '1950-02-01']]]))->toBe('My dependant was born on 1 February 1950.');

    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL);
    $more = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_DEPENDANTS_MORE);
    expect($state['form'])->toBe('dependants')
        ->and($state['next'])->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS_MORE)
        ->and($more['turn_type'])->toBe('bubbles')
        ->and(array_column($more['bubbles'], 'id'))->toBe(['yes', 'no']);
});

it('the work form writes employer, role and income once through capture_work_details', function (): void {
    $schema = CaptureForms::schema('work');
    expect($schema['tool'])->toBe('capture_work_details')
        ->and($schema['lead_fields'])->toBe(['employer', 'occupation', 'annual_income'])
        ->and(CaptureForms::rules('work')['_lead.annual_income'][0])->toBe('required_with:_lead');

    $form = ['name' => 'work', 'answers' => ['_lead' => ['employer' => 'Acme Ltd', 'occupation' => 'Software engineer', 'annual_income' => 75000]]];
    expect(CaptureForms::toolInputs($form))->toBe(['_lead' => ['employer' => 'Acme Ltd', 'occupation' => 'Software engineer', 'annual_income' => 75000.0]])
        ->and(CaptureForms::summarise($form))->toBe('My job is Software engineer at Acme Ltd and I earn £75,000 a year.');

    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_WORK);
    expect($state['form'])->toBe('work')
        ->and($state['form_prompt_text'])->toBe(OnboardingStateMachine::class.'::buildWorkFormPrompt');
});

it('the campaign date-of-birth step is a one-field form through capture_personal_details', function (): void {
    $schema = CaptureForms::schema('dob');
    expect($schema['tool'])->toBe('capture_personal_details')
        ->and($schema['lead_fields'])->toBe(['date_of_birth'])
        ->and(CaptureForms::toolInputs(['name' => 'dob', 'answers' => ['_lead' => ['date_of_birth' => '1981-03-14']]]))->toBe(['_lead' => ['date_of_birth' => '1981-03-14']])
        ->and(CaptureForms::summarise(['name' => 'dob', 'answers' => ['_lead' => ['date_of_birth' => '1981-03-14']]]))->toBe('I was born on 14 March 1981.')
        ->and(OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_DOB)['form'])->toBe('dob');
});
