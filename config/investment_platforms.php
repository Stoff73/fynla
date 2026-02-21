<?php

declare(strict_types=1);

return [
    'platforms' => [
        'vanguard' => [
            'fund_dealing_cost' => 0.00,
            'equity_dealing_cost' => 7.50,
        ],
        'hargreaves_lansdown' => [
            'fund_dealing_cost' => 0.00,
            'equity_dealing_cost' => 11.95,
        ],
        'aj_bell' => [
            'fund_dealing_cost' => 1.50,
            'equity_dealing_cost' => 5.00,
        ],
        'interactive_investor' => [
            'fund_dealing_cost' => 3.99,
            'equity_dealing_cost' => 3.99,
        ],
        'fidelity' => [
            'fund_dealing_cost' => 0.00,
            'equity_dealing_cost' => 7.50,
        ],
        'charles_stanley_direct' => [
            'fund_dealing_cost' => 0.00,
            'equity_dealing_cost' => 11.50,
        ],
    ],

    'default_cost_percent' => 0.001, // 0.1% fallback for unknown platforms
];
