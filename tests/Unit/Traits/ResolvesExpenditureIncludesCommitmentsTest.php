<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Traits\ResolvesExpenditure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0531 guard. `users` carries no housing column — the mortgage, council tax,
 * utilities and maintenance are on the property — so a chain that returns the
 * manual column alone answers a different question from the Expenditure tab, and
 * the emergency runway divides cash by the smaller of the two.
 *
 * Two of these tests read the trait file itself rather than its output. The
 * previous sweeps of this family were all satisfied by service-level assertions,
 * which stay green when the delegation is reverted and the figures merely change.
 */
function expenditureHarness(): object
{
    return new class
    {
        use ResolvesExpenditure;

        /** @return array{amount: float, source: string, label: string} */
        public function resolve(User $user): array
        {
            return $this->resolveMonthlyExpenditure($user);
        }
    };
}

function userWithMortgagedHome(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'expenditure_entry_mode' => 'simple',
        'monthly_expenditure' => 1500,
        'annual_expenditure' => null,
    ], $attributes));

    $property = Property::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'monthly_council_tax' => 0,
        'monthly_gas' => 0,
        'monthly_electricity' => 0,
        'monthly_water' => 0,
        'monthly_building_insurance' => 0,
        'monthly_contents_insurance' => 0,
        'monthly_service_charge' => 0,
        'monthly_maintenance_reserve' => 0,
        'other_monthly_costs' => 0,
    ]);

    Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'ownership_type' => 'individual',
        'monthly_payment' => 900,
    ]);

    return $user->fresh();
}

it('adds the mortgage to the resolved monthly expenditure', function () {
    $resolved = expenditureHarness()->resolve(userWithMortgagedHome());

    expect($resolved['amount'])->toBe(2400.0)
        ->and($resolved['source'])->toBe('user_monthly');
});

it('adds the mortgage to the annual-expenditure fallback too', function () {
    $user = userWithMortgagedHome([
        'monthly_expenditure' => 0,
        'annual_expenditure' => 12000,
    ]);

    $resolved = expenditureHarness()->resolve($user);

    expect($resolved['amount'])->toBe(1900.0)
        ->and($resolved['source'])->toBe('user_annual');
});

it('still reports nothing recorded when only the mortgage is known (W-0495)', function () {
    $user = userWithMortgagedHome([
        'monthly_expenditure' => 0,
        'annual_expenditure' => 0,
    ]);

    $resolved = expenditureHarness()->resolve($user);

    // A mortgage is not an answer to "what does this household spend". Resolving
    // 900 here would replace "we cannot work out your runway" with a runway
    // computed from one outgoing, which is the failure W-0495 removed.
    expect($resolved['amount'])->toBe(0.0)
        ->and($resolved['source'])->toBe('none');
});

it('reads the manual half through the breakdown, so entry mode is respected', function () {
    $user = userWithMortgagedHome([
        'expenditure_entry_mode' => 'category',
        'monthly_expenditure' => 1500,
        'food_groceries' => 400,
        'transport_fuel' => 100,
    ]);

    // 500 of categories, not the stale 1,500 on the column, plus the 900 mortgage.
    expect(expenditureHarness()->resolve($user)['amount'])->toBe(1400.0);
});

it('delegates to the one home rather than re-summing commitments', function () {
    $source = file_get_contents(app_path('Traits/ResolvesExpenditure.php'));

    expect($source)->toContain('getExpenditureBreakdown')
        ->and($source)->not->toContain('->getFinancialCommitments(');
});

it('never returns the bare monthly_expenditure column as the amount', function () {
    $source = file_get_contents(app_path('Traits/ResolvesExpenditure.php'));

    expect($source)->not->toMatch("/'amount' => \(float\) \\\$user->monthly_expenditure/")
        ->and($source)->not->toMatch("/'amount' => \(float\) \(\\\$user->annual_expenditure \/ 12\)/");
});
