<?php

declare(strict_types=1);

use App\Models\SavingsAccount;

describe('SavingsAccount display_name', function () {
    it('uses the name the user gave the account', function () {
        $account = new SavingsAccount(['account_name' => 'Holiday pot', 'institution' => 'HSBC', 'account_type' => 'easy_access']);

        expect($account->display_name)->toBe('Holiday pot');
    });

    it('falls back to institution and product when the form captured no name', function () {
        $account = new SavingsAccount(['account_name' => null, 'institution' => 'HSBC', 'account_type' => 'current_account']);

        expect($account->display_name)->toBe('HSBC Current Account');
    });

    it('names the product alone when there is no institution either', function () {
        $account = new SavingsAccount(['account_name' => '', 'institution' => null, 'account_type' => 'premium_bonds']);

        expect($account->display_name)->toBe('Premium Bonds');
    });

    it('humanises an unmapped type and never returns an empty label', function () {
        expect((new SavingsAccount(['institution' => null, 'account_type' => 'regular_saver']))->display_name)->toBe('Regular Saver')
            ->and((new SavingsAccount(['institution' => null, 'account_type' => null]))->display_name)->toBe('Unnamed account');
    });
});
