<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\Normalisers\MortgageNormaliser;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('normalises form data into canonical shape', function () {
    $form = [
        'property_id' => 1,
        'lender_name' => 'Nationwide',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => '250000.00',
        'interest_rate' => '4.5',
        'rate_type' => 'fixed',
        'monthly_payment' => '1500',
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => '240',
        'ownership_type' => 'individual',
    ];

    $canonical = MortgageNormaliser::fromForm($form, $this->user);

    expect($canonical['user_id'])->toBe($this->user->id);
    expect($canonical['outstanding_balance'])->toBe(250000.0);
    expect($canonical['interest_rate'])->toBe(4.5);
    expect($canonical['remaining_term_months'])->toBe(240);
    expect($canonical['ownership_type'])->toBe('individual');
    expect($canonical['ownership_percentage'])->toBe(100.00);
});

it('normalises Fyn AI data with alternate field names', function () {
    $fyn = [
        'property_id' => 1,
        'lender' => 'Halifax',
        'balance' => 180000,
        'rate' => 5.25,
        'ownership_type' => 'individual',
    ];

    $canonical = MortgageNormaliser::fromFyn($fyn, $this->user);

    expect($canonical['lender_name'])->toBe('Halifax');
    expect($canonical['outstanding_balance'])->toBe(180000.0);
    expect($canonical['interest_rate'])->toBe(5.25);
});

it('coerces tenants_in_common to joint for mortgages', function () {
    $form = [
        'property_id' => 1,
        'lender_name' => 'Santander',
        'outstanding_balance' => 200000,
        'monthly_payment' => 1200,
        'ownership_type' => 'tenants_in_common',
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 300,
    ];

    $canonical = MortgageNormaliser::fromForm($form, $this->user);

    expect($canonical['ownership_type'])->toBe('joint');
});

it('defaults joint ownership_percentage to 50.00 when joint and unset', function () {
    $form = [
        'property_id' => 1,
        'lender_name' => 'Barclays',
        'outstanding_balance' => 300000,
        'monthly_payment' => 1800,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 300,
    ];

    $canonical = MortgageNormaliser::fromForm($form, $this->user);

    expect($canonical['ownership_percentage'])->toBe(50.00);
    expect($canonical['joint_owner_id'])->toBe(2);
});

it('normalises upload data as canonical (field mapper already mapped)', function () {
    $upload = [
        'property_id' => 1,
        'lender_name' => 'Lloyds',
        'mortgage_type' => 'interest_only',
        'outstanding_balance' => 400000.0,
        'interest_rate' => 3.75,
        'rate_type' => 'variable',
        'monthly_payment' => 1250.0,
        'start_date' => '2018-06-01',
        'maturity_date' => '2043-06-01',
        'remaining_term_months' => 200,
        'ownership_type' => 'individual',
    ];

    $canonical = MortgageNormaliser::fromUpload($upload, $this->user);

    expect($canonical['user_id'])->toBe($this->user->id);
    expect($canonical['outstanding_balance'])->toBe(400000.0);
});
