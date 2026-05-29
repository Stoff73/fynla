<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Property\MortgageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MortgageServiceTest extends TestCase
{
    use RefreshDatabase;

    private MortgageService $mortgageService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // SP1 Pass 5 PR 4: MortgageService now has constructor DI for MortgageStore.
        // Resolve via the container so the dependency is injected.
        $this->mortgageService = app(MortgageService::class);
        $this->user = User::factory()->create();
    }

    public function test_calculate_monthly_payment_repayment_mortgage(): void
    {
        $monthlyPayment = $this->mortgageService->calculateMonthlyPayment(
            loanAmount: 200000,
            annualInterestRate: 4.0,
            termMonths: 300, // 25 years
            mortgageType: 'repayment'
        );

        // For a £200k loan at 4% over 25 years
        // Expected monthly payment: approximately £1,056
        expect($monthlyPayment)->toBeGreaterThan(1000.0);
        expect($monthlyPayment)->toBeLessThan(1100.0);
    }

    public function test_calculate_monthly_payment_interest_only(): void
    {
        $monthlyPayment = $this->mortgageService->calculateMonthlyPayment(
            loanAmount: 200000,
            annualInterestRate: 4.0,
            termMonths: 300,
            mortgageType: 'interest_only'
        );

        // For interest-only: £200,000 * 0.04 / 12 = £666.666...
        expect($monthlyPayment)->toBeGreaterThan(666.0);
        expect($monthlyPayment)->toBeLessThan(667.0);
    }

    public function test_calculate_monthly_payment_with_different_rates(): void
    {
        $payment3Percent = $this->mortgageService->calculateMonthlyPayment(
            loanAmount: 200000,
            annualInterestRate: 3.0,
            termMonths: 300,
            mortgageType: 'repayment'
        );

        $payment5Percent = $this->mortgageService->calculateMonthlyPayment(
            loanAmount: 200000,
            annualInterestRate: 5.0,
            termMonths: 300,
            mortgageType: 'repayment'
        );

        // Higher interest rate should result in higher monthly payment
        expect($payment5Percent)->toBeGreaterThan($payment3Percent);
    }

    public function test_calculate_monthly_payment_shorter_term_higher_payment(): void
    {
        $payment25Years = $this->mortgageService->calculateMonthlyPayment(
            loanAmount: 200000,
            annualInterestRate: 4.0,
            termMonths: 300,
            mortgageType: 'repayment'
        );

        $payment10Years = $this->mortgageService->calculateMonthlyPayment(
            loanAmount: 200000,
            annualInterestRate: 4.0,
            termMonths: 120,
            mortgageType: 'repayment'
        );

        // Shorter term should have higher monthly payments
        expect($payment10Years)->toBeGreaterThan($payment25Years);
    }

    public function test_generate_amortization_schedule(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $mortgage = Mortgage::factory()->create([
            'property_id' => $property->id,
            'user_id' => $this->user->id,
            'lender_name' => 'Test Bank',
            'original_loan_amount' => 200000,
            'outstanding_balance' => 200000,
            'interest_rate' => 4.0,
            'monthly_payment' => 1056,
            'start_date' => now()->subYears(0),
            'maturity_date' => now()->addYears(25),
            'remaining_term_months' => 300,
            'mortgage_type' => 'repayment',
        ]);

        $schedule = $this->mortgageService->generateAmortizationSchedule($mortgage);

        // Should have 300 months (25 years)
        expect($schedule['schedule'])->toHaveCount(300);

        // First month
        $firstMonth = $schedule['schedule'][0];
        expect($firstMonth)->toHaveKeys([
            'month',
            'opening_balance',
            'payment',
            'interest',
            'principal',
            'closing_balance',
        ]);

        // Opening balance should be the loan amount
        expect($firstMonth['opening_balance'])->toBe(200000.0);

        // Payment should match monthly payment
        expect($firstMonth['payment'])->toBe(1056.0);

        // Interest in first month should be approximately £666.67 (£200k * 0.04 / 12)
        expect($firstMonth['interest'])->toBeGreaterThan(650.0);
        expect($firstMonth['interest'])->toBeLessThan(700.0);

        // Principal should be approximately payment - interest (allow for rounding)
        $expectedPrincipal = $firstMonth['payment'] - $firstMonth['interest'];
        expect($firstMonth['principal'])->toBeGreaterThan($expectedPrincipal - 0.01);
        expect($firstMonth['principal'])->toBeLessThan($expectedPrincipal + 0.01);

        // Closing balance should be approximately opening - principal (allow for rounding)
        $expectedClosing = $firstMonth['opening_balance'] - $firstMonth['principal'];
        expect($firstMonth['closing_balance'])->toBeGreaterThan($expectedClosing - 0.01);
        expect($firstMonth['closing_balance'])->toBeLessThan($expectedClosing + 0.01);
    }

    public function test_amortization_schedule_balance_reduces_over_time(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $mortgage = Mortgage::factory()->create([
            'property_id' => $property->id,
            'user_id' => $this->user->id,
            'lender_name' => 'Test Bank',
            'original_loan_amount' => 200000,
            'outstanding_balance' => 200000,
            'interest_rate' => 4.0,
            'monthly_payment' => 1056,
            'start_date' => now(),
            'maturity_date' => now()->addYears(25),
            'remaining_term_months' => 300,
            'mortgage_type' => 'repayment',
        ]);

        $schedule = $this->mortgageService->generateAmortizationSchedule($mortgage);

        $firstMonth = $schedule['schedule'][0];
        $midMonth = $schedule['schedule'][150]; // Mid-term
        $lastMonth = $schedule['schedule'][299]; // Final month

        // Balance should decrease
        expect($midMonth['opening_balance'])->toBeLessThan($firstMonth['opening_balance']);
        expect($lastMonth['opening_balance'])->toBeLessThan($midMonth['opening_balance']);

        // Final closing balance should be close to zero
        expect($lastMonth['closing_balance'])->toBeLessThan(1.0);
    }

    public function test_amortization_schedule_interest_decreases_over_time(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $mortgage = Mortgage::factory()->create([
            'property_id' => $property->id,
            'user_id' => $this->user->id,
            'lender_name' => 'Test Bank',
            'original_loan_amount' => 200000,
            'outstanding_balance' => 200000,
            'interest_rate' => 4.0,
            'monthly_payment' => 1056,
            'start_date' => now(),
            'maturity_date' => now()->addYears(25),
            'remaining_term_months' => 300,
            'mortgage_type' => 'repayment',
        ]);

        $schedule = $this->mortgageService->generateAmortizationSchedule($mortgage);

        $firstMonth = $schedule['schedule'][0];
        $lastMonth = $schedule['schedule'][299];

        // Interest payment should decrease over time
        expect($lastMonth['interest'])->toBeLessThan($firstMonth['interest']);

        // Principal payment should increase over time
        expect($lastMonth['principal'])->toBeGreaterThan($firstMonth['principal']);
    }

    public function test_calculate_remaining_term(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $mortgage = Mortgage::factory()->create([
            'property_id' => $property->id,
            'user_id' => $this->user->id,
            'lender_name' => 'Test Bank',
            'start_date' => now()->subYears(5),
            'maturity_date' => now()->addYears(20),
            'remaining_term_months' => 240,
        ]);

        $remainingTerm = $this->mortgageService->calculateRemainingTerm($mortgage);

        // Should be approximately 240 months (20 years)
        expect($remainingTerm)->toBeGreaterThan(230);
        expect($remainingTerm)->toBeLessThan(250);
    }

    public function test_calculate_total_interest(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $mortgage = Mortgage::factory()->create([
            'property_id' => $property->id,
            'user_id' => $this->user->id,
            'lender_name' => 'Test Bank',
            'original_loan_amount' => 200000,
            'outstanding_balance' => 200000,
            'interest_rate' => 4.0,
            'monthly_payment' => 1056,
            'start_date' => now(),
            'maturity_date' => now()->addYears(25),
            'remaining_term_months' => 300,
            'mortgage_type' => 'repayment',
        ]);

        $totalInterest = $this->mortgageService->calculateTotalInterest($mortgage);

        // Total payments: £1,056 * 300 = £316,800
        // Total interest: £316,800 - £200,000 = £116,800
        expect($totalInterest)->toBeGreaterThan(100000.0);
        expect($totalInterest)->toBeLessThan(120000.0);
    }

    public function test_interest_only_mortgage_no_principal_reduction(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $mortgage = Mortgage::factory()->create([
            'property_id' => $property->id,
            'user_id' => $this->user->id,
            'lender_name' => 'Test Bank',
            'original_loan_amount' => 200000,
            'outstanding_balance' => 200000,
            'interest_rate' => 4.0,
            'monthly_payment' => 666.67,
            'start_date' => now(),
            'maturity_date' => now()->addYears(25),
            'remaining_term_months' => 300,
            'mortgage_type' => 'interest_only',
        ]);

        $schedule = $this->mortgageService->generateAmortizationSchedule($mortgage);

        $firstMonth = $schedule['schedule'][0];
        $lastMonth = $schedule['schedule'][299];

        // For interest-only, principal should be 0 and balance unchanged
        expect($firstMonth['principal'])->toBe(0.0);
        expect($lastMonth['opening_balance'])->toBe(200000.0);
        expect($lastMonth['closing_balance'])->toBe(200000.0);
    }

    public function test_zero_interest_rate_edge_case(): void
    {
        $monthlyPayment = $this->mortgageService->calculateMonthlyPayment(
            loanAmount: 120000,
            annualInterestRate: 0.0,
            termMonths: 120,
            mortgageType: 'repayment'
        );

        // With 0% interest, payment should be loan / months
        // £120,000 / 120 = £1,000
        expect($monthlyPayment)->toBe(1000.0);
    }
}
