<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Property\MortgageService;
use App\Services\Stores\MortgageStore;
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
}
