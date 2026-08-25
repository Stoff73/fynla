<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\ContributionEstimatorService;

beforeEach(function () {
    $this->service = new ContributionEstimatorService;
});

describe('ContributionEstimatorService', function () {
    it('returns user override when provided', function () {
        $account = new InvestmentAccount(['account_type' => 'isa']);

        expect($this->service->estimateMonthlyContribution($account, 500.0))->toBe(500.0);
    });

    it('ignores a negative override and falls through to the recorded chain', function () {
        $account = new InvestmentAccount(['account_type' => 'sipp']);

        expect($this->service->estimateMonthlyContribution($account, -100.0))->toBe(0.0);
    });

    // The defect this suite exists to prevent: a projection is only auditable if every
    // pound compounding in it is a pound the user told us about.
    it('contributes nothing when the user has recorded no contribution', function ($type) {
        $account = new InvestmentAccount([
            'account_type' => $type,
            'current_value' => 100000,
            'monthly_contribution_amount' => null,
            'contributions_ytd' => null,
            'isa_subscription_current_year' => null,
        ]);

        expect($this->service->estimateMonthlyContribution($account))->toBe(0.0);
    })->with(['isa', 'gia', 'sipp', 'vct']);

    it('never invents a contribution from the account value alone', function () {
        $small = new InvestmentAccount(['account_type' => 'gia', 'current_value' => 1000]);
        $large = new InvestmentAccount(['account_type' => 'gia', 'current_value' => 5000000]);

        // A value-derived estimate would move with the balance. A recorded one does not.
        expect($this->service->estimateMonthlyContribution($large))
            ->toBe($this->service->estimateMonthlyContribution($small));
    });

    it('reads the recorded contribution, and moves with it', function () {
        $make = fn (float $amount) => new InvestmentAccount([
            'account_type' => 'isa',
            'monthly_contribution_amount' => $amount,
            'contribution_frequency' => 'monthly',
        ]);

        $low = $this->service->estimateMonthlyContribution($make(100.0));
        $high = $this->service->estimateMonthlyContribution($make(400.0));

        expect($low)->toBe(100.0)
            ->and($high)->toBe(400.0)
            ->and($high)->toBeGreaterThan($low);
    });

    it('converts the recorded contribution at its stated frequency', function () {
        $make = fn (string $frequency) => new InvestmentAccount([
            'account_type' => 'isa',
            'monthly_contribution_amount' => 1200.0,
            'contribution_frequency' => $frequency,
        ]);

        expect($this->service->estimateMonthlyContribution($make('monthly')))->toBe(1200.0)
            ->and($this->service->estimateMonthlyContribution($make('quarterly')))->toBe(400.0)
            ->and($this->service->estimateMonthlyContribution($make('annually')))->toBe(100.0);
    });

    it('prefers the recorded regular contribution over this year subscriptions', function () {
        $account = new InvestmentAccount([
            'account_type' => 'isa',
            'monthly_contribution_amount' => 250.0,
            'contribution_frequency' => 'monthly',
            'contributions_ytd' => 9000,
            'isa_subscription_current_year' => 9000,
        ]);

        expect($this->service->estimateMonthlyContribution($account))->toBe(250.0);
    });

    it('annualises contributions already made this tax year, and moves with them', function () {
        $make = fn (float $ytd) => new InvestmentAccount([
            'account_type' => 'gia',
            'contributions_ytd' => $ytd,
        ]);

        $low = $this->service->estimateMonthlyContribution($make(1200.0));
        $high = $this->service->estimateMonthlyContribution($make(6000.0));

        expect($low)->toBeGreaterThan(0.0)
            ->and($high)->toBe($low * 5.0);
    });

    it('reads the ISA subscription only when the generic column is empty', function () {
        $isa = new InvestmentAccount([
            'account_type' => 'isa',
            'contributions_ytd' => 0,
            'isa_subscription_current_year' => 6000,
        ]);
        $gia = new InvestmentAccount([
            'account_type' => 'gia',
            'contributions_ytd' => 0,
            'isa_subscription_current_year' => 6000,
        ]);

        expect($this->service->estimateMonthlyContribution($isa))->toBeGreaterThan(0.0)
            ->and($this->service->estimateMonthlyContribution($gia))->toBe(0.0);
    });

    it('sums the accounts it is given', function () {
        $one = new InvestmentAccount([
            'account_type' => 'isa',
            'monthly_contribution_amount' => 300.0,
            'contribution_frequency' => 'monthly',
        ]);
        $one->id = 1;
        $two = new InvestmentAccount([
            'account_type' => 'gia',
            'monthly_contribution_amount' => 1200.0,
            'contribution_frequency' => 'annually',
        ]);
        $two->id = 2;

        expect($this->service->estimatePortfolioContribution(collect([$one, $two])))->toBe(400.0);
    });

    it('applies per-account overrides in the portfolio total', function () {
        $one = new InvestmentAccount([
            'account_type' => 'isa',
            'monthly_contribution_amount' => 300.0,
            'contribution_frequency' => 'monthly',
        ]);
        $one->id = 1;
        $two = new InvestmentAccount(['account_type' => 'gia', 'current_value' => 100000]);
        $two->id = 2;

        expect($this->service->estimatePortfolioContribution(collect([$one, $two]), [1 => 50.0]))
            ->toBe(50.0);
    });
});
