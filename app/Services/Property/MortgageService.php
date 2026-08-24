<?php

declare(strict_types=1);

namespace App\Services\Property;

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\Normalisers\MortgageNormaliser;
use App\Support\SharedOwnership;
use Carbon\Carbon;

class MortgageService
{
    public function __construct(
        private readonly MortgageStore $mortgageStore,
    ) {}

    /**
     * Create a mortgage record from property form data
     *
     * Extracts mortgage-related fields from validated property data and creates
     * a new Mortgage record linked to the property. Mortgage borrower liability
     * is configured independently from property ownership.
     *
     * @param  Property  $property  The property to attach the mortgage to
     * @param  array  $validated  Validated form data containing mortgage fields
     * @param  User  $user  The user creating the mortgage
     * @return Mortgage|null The created mortgage, or null if no mortgage data provided
     */
    public function createFromPropertyData(Property $property, array $validated, User $user): ?Mortgage
    {
        // Only create if outstanding_mortgage is provided and > 0
        if (! isset($validated['outstanding_mortgage']) || $validated['outstanding_mortgage'] <= 0) {
            return null;
        }

        $mortgageOwnershipType = $this->normalizeMortgageOwnershipType(
            $validated['mortgage_ownership_type'] ?? 'individual'
        );

        $mortgageData = [
            'property_id' => $property->id,
            'lender_name' => $validated['mortgage_lender_name'] ?? 'To be completed',
            // W-0012 — accepted by the request as of 2026-08-24; without this line
            // it would validate and then still not be written.
            'mortgage_account_number' => $validated['mortgage_account_number'] ?? null,
            'mortgage_type' => $validated['mortgage_type'] ?? 'repayment',
            'repayment_percentage' => $validated['mortgage_repayment_percentage'] ?? null,
            'interest_only_percentage' => $validated['mortgage_interest_only_percentage'] ?? null,
            'original_loan_amount' => $validated['mortgage_original_loan_amount'] ?? null,
            'outstanding_balance' => $validated['outstanding_mortgage'],  // FULL balance
            'interest_rate' => $validated['mortgage_interest_rate'] ?? 0.0000,
            'rate_type' => $validated['mortgage_rate_type'] ?? 'fixed',
            'fixed_rate_percentage' => $validated['mortgage_fixed_rate_percentage'] ?? null,
            'variable_rate_percentage' => $validated['mortgage_variable_rate_percentage'] ?? null,
            'fixed_interest_rate' => $validated['mortgage_fixed_interest_rate'] ?? null,
            'variable_interest_rate' => $validated['mortgage_variable_interest_rate'] ?? null,
            'monthly_payment' => $validated['mortgage_monthly_payment'] ?? 0.00,  // FULL payment
            'monthly_interest_portion' => $validated['mortgage_monthly_interest_portion'] ?? null,
            'start_date' => $validated['mortgage_start_date'] ?? now(),
            'maturity_date' => $validated['mortgage_maturity_date'] ?? now()->addYears(config('mortgage.default_term_years', 25)),
            // rate_fix_end_date used to have no key here at all, so the wizard's
            // Rate Fix End Date input was silently discarded and the remortgage
            // and rate-shock alerts had nothing to key off (W-0012).
            'rate_fix_end_date' => $validated['mortgage_rate_fix_end_date'] ?? null,
            // remaining_term_months is deliberately NOT set: MortgageNormaliser
            // derives it from maturity_date so the two cannot disagree. It used
            // to be the literal 300 regardless of what the user entered (W-0012).
            'ownership_type' => $mortgageOwnershipType,
        ];

        // The share (W-0172). The wizard's mortgage step has no share input, so a
        // shared mortgage arrives stating none — and defaulting it to 50 invented
        // a split the property flatly contradicts. A tenants-in-common property
        // at 40% got a mortgage at 50%, so its owner was charged 50% of a debt
        // they hold 40% of and the other 50% belonged to nobody: not the owner,
        // not a spouse, not the named third party.
        //
        // ONE source for the share. Where the caller states one it stands, exactly
        // as everywhere else; where they state none, the parent property is what
        // the arrangement actually is, so the mortgage takes its share rather
        // than a default invented next to it (Rule 20, and the same
        // supplied-beats-inherited rule as W-0040).
        $mortgageData = SharedOwnership::applyTo(
            $mortgageData + ['ownership_percentage' => $validated['mortgage_ownership_percentage'] ?? null],
            $mortgageOwnershipType,
            $property,
        );

        // Add joint borrower fields if applicable.
        $mortgageJointOwnerId = $validated['mortgage_joint_owner_id'] ?? null;
        $mortgageJointOwnerName = $validated['mortgage_joint_owner_name'] ?? null;

        if ($mortgageData['ownership_type'] === 'joint') {
            if ($mortgageJointOwnerId) {
                $jointOwner = User::find($mortgageJointOwnerId);
                $mortgageData['joint_owner_id'] = $mortgageJointOwnerId;
                $mortgageData['joint_owner_name'] = $jointOwner?->name;
            } elseif ($mortgageJointOwnerName) {
                $mortgageData['joint_owner_name'] = $mortgageJointOwnerName;
            }
        }

        $canonical = MortgageNormaliser::fromForm($mortgageData, $user);

        return $this->mortgageStore->create($canonical, $user, IngestSource::FORM);
    }

    /**
     * Normalize ownership type for mortgages.
     *
     * A shared mortgage is stored as `joint`, `tenants_in_common` included.
     *
     * The DATABASE would accept `tenants_in_common` — the enum was widened on
     * 2026-01-17 by `add_tenants_in_common_to_mortgages_ownership_type`. The
     * APPLICATION would not. `MortgageStore::validateCanonical:304` rejects
     * anything but `individual|joint`, and at least seven consumers decide
     * whether a mortgage is shared by testing `ownership_type === 'joint'`
     * exactly (`UserProfileService:931`, `PropertyCard:153`,
     * `PropertyDetailInline:382,388,814`, `PropertyFinancials:443`,
     * `LetterToSpouse:482`). Storing TIC would make every one of them read the
     * mortgage as individual and charge the user 100% of the debt — a worse
     * defect than the one W-0172 fixes, on more surfaces.
     *
     * So the coercion stays and only its justification changes: it is not that
     * the column cannot hold TIC, it is that nothing downstream understands it.
     * Making mortgages genuinely TIC-capable is a change across those consumers
     * and the store's validator — raised as W-0162, deliberately not done here.
     *
     * The share is NOT flattened with the type: a tenants-in-common property's
     * 40% is carried onto its mortgage by the caller above.
     */
    private function normalizeMortgageOwnershipType(?string $ownershipType): string
    {
        if (SharedOwnership::isShared($ownershipType)) {
            return 'joint';
        }

        return 'individual';
    }

    /**
     * Calculate monthly mortgage payment
     *
     * @param  float  $annualInterestRate  (e.g., 5.5 for 5.5%)
     * @param  string  $mortgageType  ('repayment' or 'interest_only')
     */
    public function calculateMonthlyPayment(
        float $loanAmount,
        float $annualInterestRate,
        int $termMonths,
        string $mortgageType = 'repayment'
    ): float {
        if ($loanAmount <= 0 || $termMonths <= 0) {
            return 0;
        }

        $monthlyRate = ($annualInterestRate / 100) / 12;

        if ($mortgageType === 'interest_only') {
            // Interest-only: monthly payment = loan amount × monthly interest rate
            return $loanAmount * $monthlyRate;
        }

        // Repayment mortgage formula: M = P[r(1+r)^n]/[(1+r)^n-1]
        // Where: M = monthly payment, P = principal, r = monthly rate, n = number of payments

        if ($monthlyRate == 0) {
            // No interest
            return $loanAmount / $termMonths;
        }

        $monthlyPayment = $loanAmount * (
            ($monthlyRate * pow(1 + $monthlyRate, $termMonths)) /
            (pow(1 + $monthlyRate, $termMonths) - 1)
        );

        return round($monthlyPayment, 2);
    }

    /**
     * Generate amortization schedule for a mortgage
     */
    public function generateAmortizationSchedule(Mortgage $mortgage): array
    {
        $schedule = [];

        $balance = (float) ($mortgage->outstanding_balance ?? 0);
        $monthlyPayment = (float) ($mortgage->monthly_payment ?? 0);
        $annualRate = (float) ($mortgage->interest_rate ?? 0);
        $monthlyRate = ($annualRate / 100) / 12;
        $remainingMonths = $mortgage->remaining_term_months ?? 0;
        $startDate = $mortgage->start_date ? Carbon::parse($mortgage->start_date) : Carbon::now();
        $mortgageType = $mortgage->mortgage_type ?? 'repayment';

        $currentDate = Carbon::now();
        $monthsPassed = $startDate->diffInMonths($currentDate);
        $currentMonth = $monthsPassed;

        // Generate schedule for remaining term
        for ($month = $currentMonth; $month < $currentMonth + $remainingMonths; $month++) {
            $paymentDate = $startDate->copy()->addMonths($month);
            $openingBalance = $balance;
            $interestPayment = $balance * $monthlyRate;

            // For interest-only mortgages, principal payment is 0
            if ($mortgageType === 'interest_only') {
                $principalPayment = 0;
            } else {
                $principalPayment = $monthlyPayment - $interestPayment;

                // Ensure we don't overpay on the last payment
                if ($principalPayment > $balance) {
                    $principalPayment = $balance;
                    $monthlyPayment = $principalPayment + $interestPayment;
                }
            }

            $balance -= $principalPayment;
            $closingBalance = max(0, $balance);

            $schedule[] = [
                'month' => $month + 1,
                'payment_date' => $paymentDate->format('Y-m-d'),
                'opening_balance' => round($openingBalance, 2),
                'payment' => round($monthlyPayment, 2),
                'principal' => round($principalPayment, 2),
                'interest' => round($interestPayment, 2),
                'closing_balance' => round($closingBalance, 2),
            ];

            if ($balance <= 0) {
                break;
            }
        }

        return [
            'mortgage_id' => $mortgage->id,
            'lender' => $mortgage->lender_name,
            'original_loan' => $mortgage->original_loan_amount,
            'outstanding_balance' => $mortgage->outstanding_balance,
            'interest_rate' => $mortgage->interest_rate,
            'monthly_payment' => $mortgage->monthly_payment,
            'remaining_months' => $remainingMonths,
            'schedule' => $schedule,
            'total_payments' => count($schedule),
            'total_interest' => array_sum(array_column($schedule, 'interest')),
            'total_principal' => array_sum(array_column($schedule, 'principal')),
        ];
    }

    /**
     * Calculate remaining term in months
     */
    public function calculateRemainingTerm(Mortgage $mortgage): int
    {
        if (! $mortgage->maturity_date) {
            return $mortgage->remaining_term_months ?? 0;
        }

        $today = Carbon::now();
        $maturityDate = Carbon::parse($mortgage->maturity_date);

        if ($maturityDate->isPast()) {
            return 0;
        }

        return $today->diffInMonths($maturityDate);
    }

    /**
     * Calculate total interest to be paid over remaining term
     */
    public function calculateTotalInterest(Mortgage $mortgage): float
    {
        $schedule = $this->generateAmortizationSchedule($mortgage);

        return $schedule['total_interest'] ?? 0;
    }

    /**
     * Calculate equity being built per year
     */
    public function calculateAnnualEquityBuild(Mortgage $mortgage): array
    {
        $schedule = $this->generateAmortizationSchedule($mortgage);
        $annualData = [];

        $currentYear = null;
        $yearlyPrincipal = 0;
        $yearlyInterest = 0;

        foreach ($schedule['schedule'] as $payment) {
            $year = Carbon::parse($payment['payment_date'])->year;

            if ($currentYear !== null && $year !== $currentYear) {
                $annualData[] = [
                    'year' => $currentYear,
                    'principal_paid' => round($yearlyPrincipal, 2),
                    'interest_paid' => round($yearlyInterest, 2),
                    'total_paid' => round($yearlyPrincipal + $yearlyInterest, 2),
                ];
                $yearlyPrincipal = 0;
                $yearlyInterest = 0;
            }

            $currentYear = $year;
            $yearlyPrincipal += $payment['principal'];
            $yearlyInterest += $payment['interest'];
        }

        // Add the last year
        if ($currentYear !== null) {
            $annualData[] = [
                'year' => $currentYear,
                'principal_paid' => round($yearlyPrincipal, 2),
                'interest_paid' => round($yearlyInterest, 2),
                'total_paid' => round($yearlyPrincipal + $yearlyInterest, 2),
            ];
        }

        return [
            'mortgage_id' => $mortgage->id,
            'annual_breakdown' => $annualData,
        ];
    }
}
