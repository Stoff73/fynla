<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0413 — `rent` and `utilities` were collected and thrown away.
 *
 * Everything was in place except one line. The columns exist on `users`, the
 * model casts them, `ExpenditureForm.vue` collects them and `CoordinatingAgent`
 * lists them among the expenditure fields — but `updateExpenditure()`'s
 * `$request->validate()` list started at `food_groceries`, and `validate()`
 * returns ONLY what it validated. Both fields were dropped before the write,
 * with no error.
 *
 * **The people losing the data were the ones who could least afford to.** Both
 * fields are shown only to a user with NO main residence, because a homeowner
 * enters housing costs against the property. So a renter typed their rent into a
 * box that discarded it, and had no property record to hold it either.
 *
 * A round-trip test, not a write test: the read-back was broken too — neither
 * field was on `UserResource` — so a write-only assertion would have passed
 * while the form still showed the boxes empty.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // Without these the tier middleware rejects the route before the controller
    // is reached, and the failure presents as a 404 rather than a 403.
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
});

it('persists rent and utilities and returns them again', function () {
    $user = User::factory()->create(['tier' => 'premium']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/user/profile/expenditure', [
            'rent' => 950,
            'utilities' => 180,
            'monthly_expenditure' => 2500,
        ])->assertOk();

    expect((float) $user->fresh()->rent)->toBe(950.0)
        ->and((float) $user->fresh()->utilities)->toBe(180.0);

    // The read-back half. Without this the form shows an empty box over a
    // populated column, which is indistinguishable from the original defect.
    $body = $this->actingAs($user, 'sanctum')->getJson('/api/user/profile')
        ->assertOk()
        ->json();

    expect($body['data']['expenditure']['rent'])->toBe('950.00')
        ->and($body['data']['expenditure']['utilities'])->toBe('180.00');
});

it('leaves a zero alone rather than treating it as nothing entered', function () {
    // A renter with no utility bill of their own is a real answer.
    $user = User::factory()->create(['tier' => 'premium']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/user/profile/expenditure', ['utilities' => 250])
        ->assertOk();
    $this->actingAs($user, 'sanctum')
        ->putJson('/api/user/profile/expenditure', ['utilities' => 0])
        ->assertOk();

    expect((float) $user->fresh()->utilities)->toBe(0.0);
});
