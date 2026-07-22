<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('adds catalogue metadata columns to every action-definition table', function () {
    $tables = [
        'tax_action_definitions', 'retirement_action_definitions',
        'protection_action_definitions', 'investment_action_definitions',
        'savings_action_definitions', 'estate_action_definitions',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasColumns($table, ['claim_tier', 'required_data', 'sequencing']))
            ->toBeTrue("missing metadata columns on {$table}");
    }

    expect(Schema::hasColumn('tax_action_definitions', 'strategy_type'))->toBeTrue();
});
