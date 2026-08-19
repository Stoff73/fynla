<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
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
