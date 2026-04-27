<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;

it('PROTECTION_COVER requires list_records for all three protection types', function () {
    $tools = QuerySchemas::REQUIRED_TOOLS[QuerySchemas::PROTECTION_COVER] ?? [];

    expect($tools)
        ->toContain('list_records(life_insurance)')
        ->toContain('list_records(critical_illness)')
        ->toContain('list_records(income_protection)');
});

it('PROTECTION_COVER still requires get_module_analysis(protection)', function () {
    $tools = QuerySchemas::REQUIRED_TOOLS[QuerySchemas::PROTECTION_COVER] ?? [];

    expect($tools)->toContain('get_module_analysis(protection)');
});

it('PROTECTION_COVER tool list has no duplicate entries', function () {
    $tools = QuerySchemas::REQUIRED_TOOLS[QuerySchemas::PROTECTION_COVER] ?? [];

    expect($tools)->toBe(array_unique($tools));
});
