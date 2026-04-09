<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Services\Savings\LiquidityAnalyzer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->analyzer = new LiquidityAnalyzer;
    Carbon::setTestNow(Carbon::create(2025, 1, 1));
});

afterEach(function () {
    Carbon::setTestNow(null);
    Mockery::close();
});

function createAccountMock(string $accessType, float $balance, ?int $noticeDays = null, ?string $maturityDate = null): SavingsAccount
{
    $mock = Mockery::mock(SavingsAccount::class)->makePartial();
    $mock->shouldReceive('getAttribute')->with('access_type')->andReturn($accessType);
    $mock->shouldReceive('getAttribute')->with('current_balance')->andReturn($balance);
    $mock->shouldReceive('getAttribute')->with('notice_period_days')->andReturn($noticeDays);
    $mock->shouldReceive('getAttribute')->with('maturity_date')->andReturn($maturityDate);
    $mock->shouldReceive('getAttribute')->with('institution')->andReturn('Test Bank');
    $mock->shouldReceive('getAttribute')->with('account_type')->andReturn('Savings');

    return $mock;
}

describe('LiquidityAnalyzer', function () {
    describe('categorizeLiquidity', function () {
        it('categorizes accounts by access type', function () {
            $accounts = new Collection([
                createAccountMock('immediate', 5000),
                createAccountMock('notice', 3000, 30),
                createAccountMock('fixed', 10000, null, '2025-06-01'),
                createAccountMock('immediate', 2000),
            ]);

            $result = $this->analyzer->categorizeLiquidity($accounts);

            expect($result['immediate'])->toHaveCount(2);
            expect($result['short_notice'])->toHaveCount(1);
            expect($result['fixed_term'])->toHaveCount(1);
        });
    });

    describe('assessLiquidityRisk', function () {
        it('returns Low risk for high immediate access ratio', function () {
            $profile = [
                'immediate' => new Collection([createAccountMock('immediate', 4000)]),
                'short_notice' => new Collection([createAccountMock('notice', 4000)]),
                'fixed_term' => new Collection([createAccountMock('fixed', 2000)]),
            ];

            $risk = $this->analyzer->assessLiquidityRisk($profile);

            expect($risk)->toBe('Low');
        });

        it('returns Medium risk for moderate immediate access', function () {
            $profile = [
                'immediate' => new Collection([createAccountMock('immediate', 2500)]),
                'short_notice' => new Collection([createAccountMock('notice', 2500)]),
                'fixed_term' => new Collection([createAccountMock('fixed', 5000)]),
            ];

            $risk = $this->analyzer->assessLiquidityRisk($profile);

            expect($risk)->toBe('Medium');
        });

        it('returns High risk for low liquidity', function () {
            $profile = [
                'immediate' => new Collection([createAccountMock('immediate', 1000)]),
                'short_notice' => new Collection([createAccountMock('notice', 1000)]),
                'fixed_term' => new Collection([createAccountMock('fixed', 8000)]),
            ];

            $risk = $this->analyzer->assessLiquidityRisk($profile);

            expect($risk)->toBe('High');
        });

        it('returns High risk when no savings', function () {
            $profile = [
                'immediate' => new Collection([]),
                'short_notice' => new Collection([]),
                'fixed_term' => new Collection([]),
            ];

            $risk = $this->analyzer->assessLiquidityRisk($profile);

            expect($risk)->toBe('High');
        });
    });

    describe('getLiquiditySummary', function () {
        it('calculates summary statistics correctly', function () {
            $accounts = new Collection([
                createAccountMock('immediate', 5000),
                createAccountMock('notice', 3000, 30),
                createAccountMock('fixed', 2000, null, '2025-06-01'),
            ]);

            $summary = $this->analyzer->getLiquiditySummary($accounts);

            expect($summary['total_liquid'])->toBe(5000.0);
            expect($summary['total_short_notice'])->toBe(3000.0);
            expect($summary['total_fixed'])->toBe(2000.0);
            expect($summary['liquid_percent'])->toBe(80.0);
            expect($summary['risk_level'])->toBe('Low');
        });

        it('handles empty accounts', function () {
            $accounts = new Collection([]);

            $summary = $this->analyzer->getLiquiditySummary($accounts);

            expect($summary['total_liquid'])->toBe(0.0);
            expect($summary['liquid_percent'])->toBe(0.0);
            expect($summary['risk_level'])->toBe('High');
        });
    });
});
