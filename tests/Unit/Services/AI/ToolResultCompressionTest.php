<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;

it('keeps composed tax-plan item fields visible to the model', function (): void {
    $agent = app(CoordinatingAgent::class);
    $compress = new ReflectionMethod($agent, 'compressToolResultForModel');
    $compress->setAccessible(true);

    $result = $compress->invoke($agent, 'get_recommendations', [
        'recommendations' => [],
        'total' => 0,
        'surplus' => 1643.12,
        'composed_tax_plan' => [
            'items' => [[
                'type' => 'isa_topup_vs_psa',
                'title' => 'Wrap £2,612 of cash savings inside an ISA before April 5',
                'description' => 'You hold £16,750 of non-ISA cash producing £622.75 of annual interest, of which £122.75 is above your £500 Savings Allowance.',
                'suggested_transfer_amount' => 2611.70,
                'annual_interest' => 622.75,
                'non_isa_balance' => 16750.00,
                'taxable_interest_sheltered' => 122.75,
                'personal_savings_allowance' => 500.00,
                'estimated_annual_tax_saved' => 49.10,
            ]],
            'combined_annual_saving' => 49.10,
            'locked' => [],
        ],
    ]);

    expect($result['composed_tax_plan']['items'][0])->toBeArray()
        ->and($result['composed_tax_plan']['items'][0]['type'])->toBe('isa_topup_vs_psa')
        ->and($result['composed_tax_plan']['items'][0]['non_isa_balance'])->toBe(16750.00)
        ->and($result['composed_tax_plan']['items'][0]['annual_interest'])->toBe(622.75)
        ->and($result['composed_tax_plan']['items'][0]['taxable_interest_sheltered'])->toBe(122.75)
        ->and($result['composed_tax_plan']['items'][0]['estimated_annual_tax_saved'])->toBe(49.10);
});
