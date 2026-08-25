<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Services\Tiers\TeaserGate;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
});

/**
 * Seen live on csjones 2026-08-19: a Free user asked Fyn to record spending,
 * Fyn wrote the category columns straight through the model, and their own
 * Expenditure page then said "Category details are not available on your
 * current plan" — the figure was saved and hidden from the person who gave it.
 *
 * The gate was only ever on UserProfileController. Fyn's capture handlers had
 * no check at all, and UserResource had a third copy of the predicate that had
 * drifted (no admin/preview bypass). One predicate now, on TeaserGate.
 */
it('refuses a Fyn category write for a user without the capability', function (): void {
    $user = User::factory()->create();

    $result = (new ReflectionMethod(CoordinatingAgent::class, 'handleSetExpenditure'))
        ->invoke(app(CoordinatingAgent::class), ['food_groceries' => 400], $user, false);

    expect($result['blocked'])->toBeTrue()
        ->and($result['reason'])->toContain('Premium')
        ->and((float) $user->fresh()->food_groceries)->not->toBe(400.0);
});

it('allows a Fyn category write for a user who has it', function (): void {
    $user = User::factory()->withActivePremiumSubscription()->create();

    (new ReflectionMethod(CoordinatingAgent::class, 'handleSetExpenditure'))
        ->invoke(app(CoordinatingAgent::class), ['food_groceries' => 400], $user, false);

    expect((float) $user->fresh()->food_groceries)->toBe(400.0);
});

it('gives the write path and the endpoint the same answer for the same user', function (): void {
    $gate = app(TeaserGate::class);

    $free = User::factory()->create();
    $premium = User::factory()->withActivePremiumSubscription()->create();

    expect($gate->allows($free, 'expenditure_detailed'))->toBeFalse()
        ->and($gate->allows($premium, 'expenditure_detailed'))->toBeTrue();

    $this->actingAs($free, 'sanctum')
        ->putJson('/api/user/profile/expenditure', ['food_groceries' => 400])
        ->assertStatus(403)
        ->assertJsonPath('error', 'capability_denied');

    $this->actingAs($premium, 'sanctum')
        ->putJson('/api/user/profile/expenditure', ['food_groceries' => 400])
        ->assertOk();
});

it('lets an admin through on both paths, as the write path always did', function (): void {
    // UserResource used to tell an admin the capability was unavailable while
    // the controller let their write through.
    $admin = User::factory()->create(['is_admin' => true]);

    expect(app(TeaserGate::class)->allows($admin, 'expenditure_detailed'))->toBeTrue();

    (new ReflectionMethod(CoordinatingAgent::class, 'handleSetExpenditure'))
        ->invoke(app(CoordinatingAgent::class), ['food_groceries' => 250], $admin, false);

    expect((float) $admin->fresh()->food_groceries)->toBe(250.0);
});

/**
 * W-0011. The Expenditure form builds ONE payload for both entry modes, so a
 * Simple View save arrived carrying all 22 category keys as zeros. The gate
 * fired on key presence, so a Free user could not record any expenditure at
 * all by any route — and `use_simple_entry`, the flag that says "no categories
 * here", was itself listed as a detailed field.
 *
 * `expenditure` is `full` on Free and only `expenditure_detailed` is `none`
 * (TierConfigurationSeeder), and Fyn's own update_profile handler already
 * writes a simple monthly total for any tier. This pins the endpoint to that.
 */
it('lets a free user save a simple monthly total even when the payload carries zeroed categories', function (): void {
    $free = User::factory()->create();

    $this->actingAs($free, 'sanctum')
        ->putJson('/api/user/profile/expenditure', [
            'use_simple_entry' => true,
            'expenditure_entry_mode' => 'simple',
            'use_separate_expenditure' => false,
            'monthly_expenditure' => 2500,
            'annual_expenditure' => 30000,
            'food_groceries' => 0,
            'transport_fuel' => 0,
            'healthcare_medical' => 0,
            'insurance' => 0,
            'subscriptions' => 0,
            'other_expenditure' => 0,
        ])
        ->assertOk();

    $free->refresh();

    expect((float) $free->monthly_expenditure)->toBe(2500.0)
        ->and((float) $free->annual_expenditure)->toBe(30000.0)
        ->and($free->expenditure_entry_mode)->toBe('simple');

    expect(ExpenditureProfile::where('user_id', $free->id)->value('total_monthly_expenditure'))
        ->toEqual(2500.0);
});

it('still refuses a free user a genuine detailed save', function (): void {
    $free = User::factory()->create();

    $this->actingAs($free, 'sanctum')
        ->putJson('/api/user/profile/expenditure', [
            'use_simple_entry' => false,
            'expenditure_entry_mode' => 'category',
            'monthly_expenditure' => 2500,
            'food_groceries' => 400,
        ])
        ->assertStatus(403)
        ->assertJsonPath('error', 'capability_denied');

    expect($free->fresh()->monthly_expenditure)->toBeNull();
});

it('does not write the incidental category zeros a simple save carries', function (): void {
    $free = User::factory()->create(['food_groceries' => 123.45]);

    $this->actingAs($free, 'sanctum')
        ->putJson('/api/user/profile/expenditure', [
            'use_simple_entry' => true,
            'monthly_expenditure' => 900,
            'food_groceries' => 0,
        ])
        ->assertOk();

    // Stripped, not applied — a Free user's stored categories are not theirs to
    // clear through a form that never showed them.
    expect((float) $free->fresh()->food_groceries)->toBe(123.45);
});
