<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('adds a nullable strategy_type column to every non-tax action_definitions table', function (): void {
    foreach ([
        'retirement_action_definitions',
        'savings_action_definitions',
        'investment_action_definitions',
        'protection_action_definitions',
        'estate_action_definitions',
    ] as $table) {
        expect(Schema::hasColumn($table, 'strategy_type'))->toBeTrue("$table missing strategy_type");
    }
});
