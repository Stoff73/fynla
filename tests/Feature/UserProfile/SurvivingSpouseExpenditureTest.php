<?php

declare(strict_types=1);

use App\Models\ExpenditureProfile;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Expenditure\HouseholdExpenditureWriter;
use App\Support\SharedExpenditure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * W-0477 — a household that stops being two accounts must not leave its spending
 * stored as halves.
 *
 * Under the shared mode the row IS the half: every writer divides on the way in and
 * every reader trusts what is stored. That holds only while there are two accounts.
 * The stored halves do not change when one goes, so the survivor keeps £600 of
 * groceries that means £1,200 of household spending — and every reader downstream
 * (the affordability statements, the cash-flow projection, `/m`'s expenditure screen)
 * takes the half for the whole. **Spending understated, disposable income
 * OVERSTATED**, and disposable income is what every affordability statement rests on.
 *
 * The same shape as W-0278: a value that was correct while two accounts existed and
 * silently changes meaning when one goes.
 *
 * Three severance paths are pinned below because they do not share a signature — an
 * unlink nulls `spouse_id` on both rows, a deletion leaves it set while `liveSpouse()`
 * goes null, and the purge writes through the query builder and fires no model events.
 */
function sharedHousehold(): array
{
    $primary = User::factory()->create([
        'marital_status' => 'married',
        'expenditure_sharing_mode' => SharedExpenditure::MODE_JOINT,
        'food_groceries' => 600,
        'monthly_expenditure' => 1_500,
    ]);
    $partner = User::factory()->create([
        'marital_status' => 'married',
        'spouse_id' => $primary->id,
        'expenditure_sharing_mode' => SharedExpenditure::MODE_JOINT,
        'food_groceries' => 600,
        'monthly_expenditure' => 1_500,
    ]);
    $primary->update(['spouse_id' => $partner->id]);

    ExpenditureProfile::factory()->create([
        'user_id' => $primary->id,
        'total_monthly_expenditure' => 1_500,
    ]);

    return [$primary->fresh(), $partner->fresh()];
}

it('puts the survivor back into household terms when the partner account is deleted', function () {
    [$primary, $partner] = sharedHousehold();

    $partner->delete();

    $survivor = $primary->fresh();
    // £600 was half of £1,200. With nobody left holding the other half, the row has
    // to mean the whole or every reader is wrong about it.
    expect((float) $survivor->food_groceries)->toEqualWithDelta(1_200.0, 0.01);
    expect((float) $survivor->monthly_expenditure)->toEqualWithDelta(3_000.0, 0.01);
    // And it stops declaring a sharing mode it can no longer honour, so a second
    // severance cannot double it again.
    expect($survivor->expenditure_sharing_mode)->toBe(SharedExpenditure::MODE_SEPARATE);
});

it('does not double a second time if the promotion runs again', function () {
    [$primary, $partner] = sharedHousehold();

    $partner->delete();
    app(HouseholdExpenditureWriter::class)
        ->promoteSharesToHousehold($primary->fresh());

    expect((float) $primary->fresh()->food_groceries)->toEqualWithDelta(1_200.0, 0.01);
});

it('leaves a household that never shared alone', function () {
    $single = User::factory()->create([
        'marital_status' => 'single',
        'expenditure_sharing_mode' => SharedExpenditure::MODE_JOINT,
        'food_groceries' => 600,
    ]);
    $other = User::factory()->create();

    // `DEFAULT_MODE` is joint, so a user who has never had a spouse carries it too —
    // and their figures were never divided. Doubling those would invent spending.
    // Nothing about deleting an unrelated account may touch them.
    $other->delete();

    expect((float) $single->fresh()->food_groceries)->toEqualWithDelta(600.0, 0.01);
});

it('puts both accounts back into household terms when the household is unlinked', function () {
    [$primary, $partner] = sharedHousehold();

    $card = FamilyMember::factory()->create([
        'user_id' => $primary->id,
        'relationship' => 'spouse',
        'linked_user_id' => $partner->id,
    ]);

    Sanctum::actingAs($primary);
    $this->deleteJson("/api/user/family-members/{$card->id}")->assertOk();

    // Both accounts survive the unlink and each now records its own household.
    expect((float) $primary->fresh()->food_groceries)->toEqualWithDelta(1_200.0, 0.01);
    expect((float) $partner->fresh()->food_groceries)->toEqualWithDelta(1_200.0, 0.01);
});
