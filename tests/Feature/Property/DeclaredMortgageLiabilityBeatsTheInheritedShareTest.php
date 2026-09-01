<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0483. CSJ amended W-0228 on 2026-08-30: *"W-0228 can allow mortgage share that is
 * not the same as ownership share."*
 *
 * W-0228 made the property authoritative after one household was shown two figures for
 * one debt, four inches apart. That ruling still holds — it now yields only to someone
 * SAYING otherwise, through a nullable column that no existing row carries.
 *
 * The load-bearing case is the last one: `mortgages.ownership_percentage` must stay
 * unread. It is populated everywhere, was never reviewed, and the persona carries
 * `joint 50%` on a mortgage secured on a `tenants_in_common 40%` property. Believing it
 * is the defect W-0228 closed, and a declared-share feature is exactly where it would
 * come back.
 */
function shareHarness(): object
{
    return new class
    {
        use CalculatesOwnershipShare;

        public function mortgageShare(object $mortgage, int $userId): float
        {
            return $this->calculateUserMortgageShare($mortgage, $userId);
        }
    };
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->coOwner = User::factory()->create();

    $this->property = Property::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => $this->coOwner->id,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 40.00,
    ]);
});

function mortgageOn(Property $property, array $attributes = []): Mortgage
{
    return Mortgage::factory()->create(array_merge([
        'user_id' => $property->user_id,
        'property_id' => $property->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
        'outstanding_balance' => 120_000,
    ], $attributes));
}

it('still follows the property when nobody has declared a borrowing split', function () {
    $mortgage = mortgageOn($this->property);

    // 40/60 from the property, NOT 50/50 from the mortgage row. This is W-0228.
    expect(shareHarness()->mortgageShare($mortgage, $this->owner->id))->toBe(48_000.0)
        ->and(shareHarness()->mortgageShare($mortgage, $this->coOwner->id))->toBe(72_000.0);
});

it('gives the whole debt to a co-owner who borrowed alone', function () {
    // The capability the item asked for and CSJ granted.
    $mortgage = mortgageOn($this->property, ['declared_liability_percentage' => 100.00]);

    expect(shareHarness()->mortgageShare($mortgage, $this->owner->id))->toBe(120_000.0)
        ->and(shareHarness()->mortgageShare($mortgage, $this->coOwner->id))->toBe(0.0);
});

it('splits a declared share the same way ownership_percentage reads elsewhere', function () {
    $mortgage = mortgageOn($this->property, ['declared_liability_percentage' => 75.00]);

    expect(shareHarness()->mortgageShare($mortgage, $this->owner->id))->toBe(90_000.0)
        ->and(shareHarness()->mortgageShare($mortgage, $this->coOwner->id))->toBe(30_000.0);
});

it('charges nothing to somebody who is neither borrower nor co-owner', function () {
    $mortgage = mortgageOn($this->property, ['declared_liability_percentage' => 100.00]);
    $stranger = User::factory()->create();

    expect(shareHarness()->mortgageShare($mortgage, $stranger->id))->toBe(0.0);
});

it('treats a declared zero as a statement, not as nothing said', function () {
    // The distinction a NOT NULL DEFAULT would have destroyed: "I borrowed none of it"
    // and "nobody has said" are different answers, and only one of them is 40%.
    $mortgage = mortgageOn($this->property, ['declared_liability_percentage' => 0.00]);

    expect(shareHarness()->mortgageShare($mortgage, $this->owner->id))->toBe(0.0)
        ->and(shareHarness()->mortgageShare($mortgage, $this->coOwner->id))->toBe(120_000.0);
});

it('never reads ownership_percentage off the mortgage row', function () {
    // The mortgage says joint 50%, the property says tenants-in-common 40%, and nobody
    // has declared anything. 50% here would be W-0228's defect returning through the
    // door this item opened.
    $mortgage = mortgageOn($this->property, ['ownership_percentage' => 50.00]);

    expect(shareHarness()->mortgageShare($mortgage, $this->owner->id))->not->toBe(60_000.0);
});
