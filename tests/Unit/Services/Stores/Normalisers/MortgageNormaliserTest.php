<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\Normalisers\MortgageNormaliser;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('normalises form data into canonical shape', function () {
    $form = [
        'property_id' => 1,
        'lender_name' => 'Nationwide',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => '250000.00',
        'interest_rate' => '4.5',
        'rate_type' => 'fixed',
        'monthly_payment' => '1500',
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => '240',
        'ownership_type' => 'individual',
    ];

    $canonical = MortgageNormaliser::fromForm($form, $this->user);

    expect($canonical['user_id'])->toBe($this->user->id);
    expect($canonical['outstanding_balance'])->toBe(250000.0);
    expect($canonical['interest_rate'])->toBe(4.5);
    // The term is DERIVED from maturity_date, not taken from input: a row where
    // the two disagree drives the amortisation schedule off a term that
    // contradicts the user's own end date (W-0012). The submitted 240 contradicts
    // a 2045-01-01 maturity, so the date wins.
    expect($canonical['remaining_term_months'])
        ->toBe(monthsUntil('2045-01-01'));
    expect($canonical['ownership_type'])->toBe('individual');
    expect($canonical['ownership_percentage'])->toBe(100.00);
});

it('normalises Fyn AI data with alternate field names', function () {
    $fyn = [
        'property_id' => 1,
        'lender' => 'Halifax',
        'balance' => 180000,
        'rate' => 5.25,
        'ownership_type' => 'individual',
    ];

    $canonical = MortgageNormaliser::fromFyn($fyn, $this->user);

    expect($canonical['lender_name'])->toBe('Halifax');
    expect($canonical['outstanding_balance'])->toBe(180000.0);
    expect($canonical['interest_rate'])->toBe(5.25);
});

it('coerces tenants_in_common to joint for mortgages', function () {
    $form = [
        'property_id' => 1,
        'lender_name' => 'Santander',
        'outstanding_balance' => 200000,
        'monthly_payment' => 1200,
        'ownership_type' => 'tenants_in_common',
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 300,
    ];

    $canonical = MortgageNormaliser::fromForm($form, $this->user);

    expect($canonical['ownership_type'])->toBe('joint');
});

it('defaults joint ownership_percentage to 50.00 when joint and unset', function () {
    $form = [
        'property_id' => 1,
        'lender_name' => 'Barclays',
        'outstanding_balance' => 300000,
        'monthly_payment' => 1800,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 300,
    ];

    $canonical = MortgageNormaliser::fromForm($form, $this->user);

    expect($canonical['ownership_percentage'])->toBe(50.00);
    expect($canonical['joint_owner_id'])->toBe(2);
});

it('normalises upload data as canonical (field mapper already mapped)', function () {
    $upload = [
        'property_id' => 1,
        'lender_name' => 'Lloyds',
        'mortgage_type' => 'interest_only',
        'outstanding_balance' => 400000.0,
        'interest_rate' => 3.75,
        'rate_type' => 'variable',
        'monthly_payment' => 1250.0,
        'start_date' => '2018-06-01',
        'maturity_date' => '2043-06-01',
        'remaining_term_months' => 200,
        'ownership_type' => 'individual',
    ];

    $canonical = MortgageNormaliser::fromUpload($upload, $this->user);

    expect($canonical['user_id'])->toBe($this->user->id);
    expect($canonical['outstanding_balance'])->toBe(400000.0);
});

it('derives remaining_term_months from maturity_date rather than trusting the input', function () {
    $canonical = MortgageNormaliser::fromForm([
        'property_id' => 1,
        'lender_name' => 'HSBC',
        'outstanding_balance' => 65000,
        'monthly_payment' => 550,
        'ownership_type' => 'individual',
        'maturity_date' => Carbon::now()->startOfDay()->addMonths(156)->toDateString(),
        // What the property wizard used to hardcode, whatever the user entered.
        'remaining_term_months' => 300,
    ], $this->user);

    expect($canonical['remaining_term_months'])->toBe(156);
});

it('derives maturity_date from remaining_term_months when no date is given', function () {
    $canonical = MortgageNormaliser::fromForm([
        'property_id' => 1,
        'lender_name' => 'HSBC',
        'outstanding_balance' => 65000,
        'monthly_payment' => 550,
        'ownership_type' => 'individual',
        'remaining_term_months' => 156,
    ], $this->user);

    expect($canonical['maturity_date'])
        ->toBe(Carbon::now()->startOfDay()->addMonths(156)->toDateString())
        ->and($canonical['remaining_term_months'])->toBe(156);
});

it('leaves a partial update that mentions neither term nor maturity date alone', function () {
    $canonical = MortgageNormaliser::fromForm([
        'outstanding_balance' => 60000,
    ], $this->user);

    expect($canonical)->not->toHaveKey('remaining_term_months')
        ->and($canonical)->not->toHaveKey('maturity_date');
});

it('gives a joint mortgage a 50/50 split when the form states no share', function () {
    $canonical = MortgageNormaliser::fromForm([
        'property_id' => 1,
        'lender_name' => 'HSBC',
        'outstanding_balance' => 65000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        // No mortgage form exposes a share input, so none is stated. The form
        // used to send the individual default of 100 and rely on the boundary
        // halving it, which made a stated 100 unexpressible (W-0040).
    ], $this->user);

    expect($canonical['ownership_percentage'])->toBe(50.0);
});

it('keeps a stated 100 on a joint mortgage rather than halving it', function () {
    // Never silently altered here; the refusal lives in the form requests.
    $canonical = MortgageNormaliser::fromForm([
        'property_id' => 1,
        'lender_name' => 'HSBC',
        'outstanding_balance' => 65000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 100,
    ], $this->user);

    expect($canonical['ownership_percentage'])->toBe(100.0);
});

/** Whole months from today to the given date — mirrors MortgageNormaliser. */
function monthsUntil(string $date): int
{
    return max(0, (int) Carbon::now()->startOfDay()->diffInMonths(Carbon::parse($date)->startOfDay(), false));
}
