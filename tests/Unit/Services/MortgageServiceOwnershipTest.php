<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Property\MortgageService;
use App\Services\Stores\MortgageStore;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class MortgageServiceOwnershipTest extends TestCase
{
    public function test_shared_property_defaults_its_mortgage_to_individual_liability(): void
    {
        $user = new User(['id' => 101]);
        $user->id = 101;
        $property = new Property([
            'id' => 201,
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 30.00,
            'joint_owner_name' => 'External co-owner',
        ]);
        $property->id = 201;

        $store = Mockery::mock(MortgageStore::class);
        $store->shouldReceive('create')
            ->once()
            ->andReturnUsing(fn (array $canonical) => new Mortgage($canonical));

        $mortgage = (new MortgageService($store))->createFromPropertyData($property, [
            'outstanding_mortgage' => 210000.00,
            'mortgage_lender_name' => 'Nationwide',
            'mortgage_monthly_payment' => 1300.00,
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 30.00,
            'joint_owner_name' => 'External co-owner',
        ], $user);

        expect($mortgage)->not->toBeNull()
            ->and($mortgage->ownership_type)->toBe('individual')
            ->and((float) $mortgage->ownership_percentage)->toBe(100.00)
            ->and($mortgage->joint_owner_id)->toBeNull()
            ->and($mortgage->joint_owner_name)->toBeNull();
    }

    public function test_joint_borrowers_are_saved_separately_from_the_property_ownership_split(): void
    {
        $user = new User(['id' => 101]);
        $user->id = 101;
        $property = new Property([
            'id' => 201,
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 30.00,
        ]);
        $property->id = 201;

        $store = Mockery::mock(MortgageStore::class);
        $store->shouldReceive('create')->once()
            ->andReturnUsing(fn (array $canonical) => new Mortgage($canonical));

        $mortgage = (new MortgageService($store))->createFromPropertyData($property, [
            'outstanding_mortgage' => 210000.00,
            'mortgage_lender_name' => 'Nationwide',
            'mortgage_monthly_payment' => 1300.00,
            'mortgage_ownership_type' => 'joint',
            'mortgage_joint_owner_name' => 'Alex Smith',
            'mortgage_ownership_percentage' => 50.00,
        ], $user);

        expect($mortgage)->not->toBeNull()
            ->and($mortgage->ownership_type)->toBe('joint')
            ->and((float) $mortgage->ownership_percentage)->toBe(50.00)
            ->and($mortgage->joint_owner_id)->toBeNull()
            ->and($mortgage->joint_owner_name)->toBe('Alex Smith');
    }

    public function test_the_wizard_derives_the_term_from_the_entered_maturity_date_instead_of_hardcoding_300(): void
    {
        $user = new User(['id' => 101]);
        $user->id = 101;
        $property = new Property(['id' => 201, 'ownership_type' => 'joint', 'ownership_percentage' => 50.00]);
        $property->id = 201;

        $store = Mockery::mock(MortgageStore::class);
        $store->shouldReceive('create')->once()
            ->andReturnUsing(fn (array $canonical) => new Mortgage($canonical));

        $maturity = Carbon::now()->startOfDay()->addMonths(156);

        $mortgage = (new MortgageService($store))->createFromPropertyData($property, [
            'outstanding_mortgage' => 65000.00,
            'mortgage_lender_name' => 'HSBC',
            'mortgage_monthly_payment' => 550.00,
            'mortgage_maturity_date' => $maturity->toDateString(),
            'mortgage_rate_type' => 'fixed',
            'mortgage_rate_fix_end_date' => '2027-04-01',
            'mortgage_monthly_interest_portion' => 232.38,
        ], $user);

        expect($mortgage)->not->toBeNull()
            // 300 was the literal every wizard-created mortgage got, whatever the
            // user entered — a 25-year term against a 13-year loan (W-0012).
            ->and($mortgage->remaining_term_months)->toBe(156)
            // The wizard renders a Rate Fix End Date input; the array simply had
            // no key for it, so the value was silently discarded (W-0012).
            ->and($mortgage->rate_fix_end_date?->toDateString())->toBe('2027-04-01')
            ->and((float) $mortgage->monthly_interest_portion)->toBe(232.38);
    }

    public function test_a_shared_mortgage_takes_the_parent_propertys_share_when_none_is_stated(): void
    {
        // W-0172. The wizard's mortgage step has no share input, so a shared
        // mortgage states none. Defaulting it to 50 invented a split the
        // property contradicts: a tenants-in-common owner at 40% was charged
        // 50% of the debt, and the other 50% belonged to nobody.
        $user = new User(['id' => 101]);
        $user->id = 101;
        $property = new Property([
            'id' => 201,
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 40.00,
            'joint_owner_name' => 'Mike Barrett',
        ]);
        $property->id = 201;

        $store = Mockery::mock(MortgageStore::class);
        $store->shouldReceive('create')->once()
            ->andReturnUsing(fn (array $canonical) => new Mortgage($canonical));

        $mortgage = (new MortgageService($store))->createFromPropertyData($property, [
            'outstanding_mortgage' => 120000.00,
            'mortgage_lender_name' => 'NatWest',
            'mortgage_ownership_type' => 'joint',
            'mortgage_joint_owner_name' => 'Mike Barrett',
            // No share stated — the Borrower(s) control has no share input.
        ], $user);

        expect((float) $mortgage->ownership_percentage)->toBe(40.00)
            ->and($mortgage->joint_owner_name)->toBe('Mike Barrett')
            ->and($mortgage->joint_owner_id)->toBeNull();
    }

    public function test_a_stated_mortgage_share_still_beats_the_propertys(): void
    {
        // Supplied beats inherited, here as everywhere (W-0040). Two people can
        // own 40/60 and split the borrowing differently.
        $user = new User(['id' => 101]);
        $user->id = 101;
        $property = new Property([
            'id' => 201,
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 40.00,
        ]);
        $property->id = 201;

        $store = Mockery::mock(MortgageStore::class);
        $store->shouldReceive('create')->once()
            ->andReturnUsing(fn (array $canonical) => new Mortgage($canonical));

        $mortgage = (new MortgageService($store))->createFromPropertyData($property, [
            'outstanding_mortgage' => 120000.00,
            'mortgage_lender_name' => 'NatWest',
            'mortgage_ownership_type' => 'joint',
            'mortgage_ownership_percentage' => 70.00,
        ], $user);

        expect((float) $mortgage->ownership_percentage)->toBe(70.00);
    }

    public function test_a_tenants_in_common_mortgage_is_stored_as_joint_but_keeps_the_propertys_share(): void
    {
        // The type is flattened to joint on purpose: the store's validator and
        // at least seven consumers decide shared-ness by testing
        // ownership_type === 'joint' exactly, so a TIC mortgage would read as
        // individual and charge 100% of the debt. The SHARE is not flattened
        // with it — that was the W-0172 defect.
        $user = new User(['id' => 101]);
        $user->id = 101;
        $property = new Property([
            'id' => 201,
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 40.00,
        ]);
        $property->id = 201;

        $store = Mockery::mock(MortgageStore::class);
        $store->shouldReceive('create')->once()
            ->andReturnUsing(fn (array $canonical) => new Mortgage($canonical));

        $mortgage = (new MortgageService($store))->createFromPropertyData($property, [
            'outstanding_mortgage' => 120000.00,
            'mortgage_lender_name' => 'NatWest',
            'mortgage_ownership_type' => 'tenants_in_common',
            'mortgage_joint_owner_name' => 'Mike Barrett',
        ], $user);

        expect($mortgage->ownership_type)->toBe('joint')
            ->and((float) $mortgage->ownership_percentage)->toBe(40.00);
    }

    public function test_a_joint_mortgage_with_no_stated_share_becomes_a_50_50_split(): void
    {
        $user = new User(['id' => 101]);
        $user->id = 101;
        $property = new Property(['id' => 201]);
        $property->id = 201;

        $store = Mockery::mock(MortgageStore::class);
        $store->shouldReceive('create')->once()
            ->andReturnUsing(fn (array $canonical) => new Mortgage($canonical));

        $mortgage = (new MortgageService($store))->createFromPropertyData($property, [
            'outstanding_mortgage' => 65000.00,
            'mortgage_lender_name' => 'HSBC',
            'mortgage_ownership_type' => 'joint',
            'mortgage_joint_owner_name' => 'Alex Smith',
            // The property form has no mortgage share input, so it states none.
            // It used to send the individual default of 100 and rely on the
            // boundary halving it, which made a stated 100 unexpressible (W-0040).
        ], $user);

        expect((float) $mortgage->ownership_percentage)->toBe(50.00);
    }
}
