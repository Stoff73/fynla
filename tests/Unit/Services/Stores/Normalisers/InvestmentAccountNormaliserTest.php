<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\Normalisers\InvestmentAccountNormaliser;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('injects user_id from the user object', function () {
    $form = [
        'account_name' => 'Test ISA',
        'account_type' => 'isa',
        'current_value' => 10000,
        'ownership_type' => 'individual',
    ];

    $canonical = InvestmentAccountNormaliser::fromForm($form, $this->user);

    expect($canonical['user_id'])->toBe($this->user->id);
});

it('coerces tenants_in_common to joint', function () {
    $form = [
        'account_name' => 'Shared GIA',
        'account_type' => 'gia',
        'ownership_type' => 'tenants_in_common',
        'current_value' => 5000,
    ];

    $canonical = InvestmentAccountNormaliser::fromForm($form, $this->user);

    expect($canonical['ownership_type'])->toBe('joint');
});

it('coerces unknown ownership_type to individual', function () {
    $form = [
        'account_name' => 'Bond',
        'account_type' => 'onshore_bond',
        'ownership_type' => 'corporate',
        'current_value' => 20000,
    ];

    $canonical = InvestmentAccountNormaliser::fromForm($form, $this->user);

    expect($canonical['ownership_type'])->toBe('individual');
});

it('defaults ownership_percentage to 100 for individual when unset', function () {
    $form = [
        'account_name' => 'ISA',
        'account_type' => 'isa',
        'ownership_type' => 'individual',
        'current_value' => 15000,
    ];

    $canonical = InvestmentAccountNormaliser::fromForm($form, $this->user);

    expect($canonical['ownership_percentage'])->toBe(100.00);
});

it('defaults ownership_percentage to 50 for joint when unset', function () {
    $form = [
        'account_name' => 'Joint GIA',
        'account_type' => 'gia',
        'ownership_type' => 'joint',
        'current_value' => 30000,
    ];

    $canonical = InvestmentAccountNormaliser::fromForm($form, $this->user);

    expect($canonical['ownership_percentage'])->toBe(50.00);
});

it('casts core numeric fields to float', function () {
    $form = [
        'account_name' => 'ISA',
        'account_type' => 'isa',
        'ownership_type' => 'individual',
        'current_value' => '12500.50',
        'contributions_ytd' => '3000',
        'monthly_contribution_amount' => '500',
    ];

    $canonical = InvestmentAccountNormaliser::fromForm($form, $this->user);

    expect($canonical['current_value'])->toBe(12500.50);
    expect($canonical['contributions_ytd'])->toBe(3000.0);
    expect($canonical['monthly_contribution_amount'])->toBe(500.0);
});

it('maps Fyn alias fields to canonical names', function () {
    $fyn = [
        'name' => 'My ISA',
        'type' => 'isa',
        'value' => 20000,
        'ownership' => 'individual',
    ];

    $canonical = InvestmentAccountNormaliser::fromFyn($fyn, $this->user);

    expect($canonical['account_name'])->toBe('My ISA');
    expect($canonical['account_type'])->toBe('isa');
    expect($canonical['current_value'])->toBe(20000.0);
    expect($canonical['ownership_type'])->toBe('individual');
    expect($canonical['user_id'])->toBe($this->user->id);
});

it('normalises upload data as canonical (field mapper already mapped)', function () {
    $upload = [
        'account_name' => 'GIA Upload',
        'account_type' => 'gia',
        'ownership_type' => 'individual',
        'current_value' => 45000.00,
        'provider' => 'Vanguard',
    ];

    $canonical = InvestmentAccountNormaliser::fromUpload($upload, $this->user);

    expect($canonical['user_id'])->toBe($this->user->id);
    expect($canonical['current_value'])->toBe(45000.0);
    expect($canonical['provider'])->toBe('Vanguard');
});
