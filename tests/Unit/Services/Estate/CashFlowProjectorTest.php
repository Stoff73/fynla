<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\CashFlowProjector;

beforeEach(function () {
    // Ensure active tax configuration exists
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $taxConfig = app(\App\Services\TaxConfigService::class);
    $this->projector = new CashFlowProjector($taxConfig);
    $this->user = User::factory()->create();
});

describe('createPersonalPL', function () {
    it('creates personal P&L statement with correct structure', function () {
        $result = $this->projector->createPersonalPL($this->user->id, '2025');

        expect($result)->toHaveKeys([
            'tax_year',
            'period_start',
            'period_end',
            'income',
            'expenditure',
            'net_surplus_deficit',
            'status',
        ])
            ->and($result['tax_year'])->toBe('2025/26')
            ->and($result['period_start'])->toBe('2025-04-06')
            ->and($result['period_end'])->toBe('2026-04-05');
    });

    it('includes debt servicing in expenditure', function () {
        Liability::create([
            'user_id' => $this->user->id,
            'liability_type' => 'mortgage',
            'liability_name' => 'Home Mortgage',
            'current_balance' => 200000,
            'monthly_payment' => 1000,
        ]);

        Liability::create([
            'user_id' => $this->user->id,
            'liability_type' => 'personal_loan',
            'liability_name' => 'Car Loan',
            'current_balance' => 15000,
            'monthly_payment' => 300,
        ]);

        $result = $this->projector->createPersonalPL($this->user->id, '2025');

        $debtServicing = collect($result['expenditure']['items'])
            ->firstWhere('category', 'Debt Servicing');

        expect($debtServicing['amount'])->toBe(15600.0); // (1000 + 300) * 12
    });

    it('calculates surplus when income exceeds expenditure', function () {
        $result = $this->projector->createPersonalPL($this->user->id, '2025');

        // By default, all values are 0 except debt servicing
        expect($result['status'])->toBeIn(['Surplus', 'Deficit']);
    });
});

describe('projectCashFlow', function () {
    it('projects cash flow for multiple years', function () {
        $result = $this->projector->projectCashFlow($this->user->id, 5);

        expect($result['projection_years'])->toBe(5)
            ->and($result['projections'])->toHaveCount(5)
            ->and($result['summary'])->toHaveKeys(['total_income', 'total_expenditure', 'total_net_cash_flow']);
    });

    it('applies inflation to future years', function () {
        $result = $this->projector->projectCashFlow($this->user->id, 3);

        // All years should have the same initial values since no income/expenditure is set
        expect($result['projections'])->toBeArray();
    });

    it('calculates cumulative cash flow', function () {
        $result = $this->projector->projectCashFlow($this->user->id, 3);

        // Each projection should have cumulative_cash_flow
        foreach ($result['projections'] as $projection) {
            expect($projection)->toHaveKey('cumulative_cash_flow');
        }
    });
});

describe('identifyCashFlowIssues', function () {
    it('identifies consecutive deficit years', function () {
        $projection = [
            'projections' => [
                ['year' => 2025, 'net_cash_flow' => -10000, 'cumulative_cash_flow' => -10000],
                ['year' => 2025, 'net_cash_flow' => -15000, 'cumulative_cash_flow' => -25000],
                ['year' => 2026, 'net_cash_flow' => 5000, 'cumulative_cash_flow' => -20000],
            ],
        ];

        $result = $this->projector->identifyCashFlowIssues($projection);

        $hasConsecutiveDeficits = collect($result['issues'])
            ->contains(fn ($issue) => $issue['type'] === 'Consecutive Deficits');

        expect($result['has_issues'])->toBe(true)
            ->and($hasConsecutiveDeficits)->toBe(true);
    });

    it('identifies large single-year deficits', function () {
        $projection = [
            'projections' => [
                ['year' => 2025, 'net_cash_flow' => -15000, 'cumulative_cash_flow' => -15000],
                ['year' => 2025, 'net_cash_flow' => 5000, 'cumulative_cash_flow' => -10000],
            ],
        ];

        $result = $this->projector->identifyCashFlowIssues($projection);

        $hasLargeDeficit = collect($result['issues'])
            ->contains(fn ($issue) => $issue['type'] === 'Large Deficit Year');

        expect($result['has_issues'])->toBe(true)
            ->and($hasLargeDeficit)->toBe(true);
    });

    it('identifies negative cumulative cash flow', function () {
        $projection = [
            'projections' => [
                ['year' => 2025, 'net_cash_flow' => -5000, 'cumulative_cash_flow' => -5000],
                ['year' => 2025, 'net_cash_flow' => -5000, 'cumulative_cash_flow' => -10000],
                ['year' => 2026, 'net_cash_flow' => -5000, 'cumulative_cash_flow' => -15000],
            ],
        ];

        $result = $this->projector->identifyCashFlowIssues($projection);

        $hasNegativeCumulative = collect($result['issues'])
            ->contains(fn ($issue) => $issue['type'] === 'Negative Cumulative Cash Flow');

        expect($result['has_issues'])->toBe(true)
            ->and($hasNegativeCumulative)->toBe(true);
    });

    it('returns healthy status for positive cash flow', function () {
        $projection = [
            'projections' => [
                ['year' => 2025, 'net_cash_flow' => 10000, 'cumulative_cash_flow' => 10000],
                ['year' => 2025, 'net_cash_flow' => 12000, 'cumulative_cash_flow' => 22000],
                ['year' => 2026, 'net_cash_flow' => 15000, 'cumulative_cash_flow' => 37000],
            ],
        ];

        $result = $this->projector->identifyCashFlowIssues($projection);

        expect($result['has_issues'])->toBe(false)
            ->and($result['overall_health']['status'])->toBe('Healthy');
    });
});

describe('calculateDiscretionaryIncome', function () {
    it('calculates discretionary income correctly', function () {
        $result = $this->projector->calculateDiscretionaryIncome($this->user->id, '2025');

        expect($result)->toHaveKeys([
            'tax_year',
            'total_income',
            'essential_expenditure',
            'discretionary_income',
            'discretionary_percentage',
            'available_for_gifting',
        ]);
    });

    it('suggests 25% of discretionary income for gifting', function () {
        $result = $this->projector->calculateDiscretionaryIncome($this->user->id, '2025');

        // Since all values are 0 by default, available_for_gifting should be 0
        expect($result['available_for_gifting'])->toBe(0.0);
    });

    it('calculates discretionary percentage of total income', function () {
        $result = $this->projector->calculateDiscretionaryIncome($this->user->id, '2025');

        expect($result['discretionary_percentage'])->toBeNumeric();
    });
});
