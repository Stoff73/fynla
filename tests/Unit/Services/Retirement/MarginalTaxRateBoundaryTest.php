<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Retirement\RetirementStrategyService;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    app()->forgetScopedInstances();
    $this->service = app(RetirementStrategyService::class);
    $this->marginalRate = function (float $income): float {
        $method = new ReflectionMethod(RetirementStrategyService::class, 'getMarginalTaxRate');
        $method->setAccessible(true);
        $user = User::factory()->make([
            'annual_employment_income' => $income,
            'annual_self_employment_income' => 0,
        ]);

        return $method->invoke($this->service, $user);
    };
});

describe('getMarginalTaxRate additional-rate boundary', function () {
    it('applies 45% for income between the additional-rate threshold and the old buggy boundary', function () {
        // £125,140–£137,710 was wrongly taxed at 40% because the boundary was
        // computed as personalAllowance + bands[1].max (12,570 + 125,140 = 137,710)
        // instead of the absolute additional_rate_threshold (£125,140).
        expect(($this->marginalRate)(130000.00))->toBe(0.45)
            ->and(($this->marginalRate)(137000.00))->toBe(0.45)
            ->and(($this->marginalRate)(137710.00))->toBe(0.45);
    });

    it('still applies 40% just below the additional-rate threshold', function () {
        expect(($this->marginalRate)(125140.00))->toBe(0.40)
            ->and(($this->marginalRate)(120000.00))->toBe(0.40);
    });

    it('applies 45% just above the additional-rate threshold', function () {
        expect(($this->marginalRate)(125141.00))->toBe(0.45);
    });

    it('leaves the lower band boundaries unchanged', function () {
        expect(($this->marginalRate)(12570.00))->toBe(0.0)
            ->and(($this->marginalRate)(40000.00))->toBe(0.20)
            ->and(($this->marginalRate)(50270.00))->toBe(0.20)
            ->and(($this->marginalRate)(60000.00))->toBe(0.40);
    });
});
