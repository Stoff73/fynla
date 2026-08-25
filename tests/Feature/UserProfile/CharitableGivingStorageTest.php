<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use App\Services\UserProfile\UserProfileService;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The endpoint tests below cross the Premium capability boundary, which
    // needs the tier table and the role/permission rows to resolve.
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
});

/**
 * Charitable giving is a monthly expenditure category like every other, and the
 * annual figure IHT planning reads is derived from it.
 *
 * Before 2026-08-19 it had no monthly column at all. The Expenditure form has
 * always shown a "Charitable Donations" line, but `charitable_donations` was
 * neither a column nor in the endpoint's validation list, so the figure was
 * dropped on every save. Its only persistence was a side-channel write of
 * `annual_charitable_donations = monthly * 12` that nothing read back — so the
 * field reloaded as 0 and, with Gift Aid on, the next save committed that 0
 * over whatever the user or Fyn had recorded.
 */
it('derives the annual figure from the monthly one', function (): void {
    $user = User::factory()->create();

    $user->update(['charitable_donations' => 200]);

    expect((float) $user->fresh()->charitable_donations)->toBe(200.0)
        ->and((float) $user->fresh()->annual_charitable_donations)->toBe(2400.0);
});

it('clears the annual figure when the monthly one is cleared', function (): void {
    $user = User::factory()->create(['charitable_donations' => 200]);

    $user->update(['charitable_donations' => null]);

    expect($user->fresh()->charitable_donations)->toBeNull()
        ->and($user->fresh()->annual_charitable_donations)->toBeNull();
});

it('persists the monthly figure through the expenditure endpoint', function (): void {
    // A detailed category, so Premium — charitable_donations sits in
    // DETAILED_EXPENDITURE_FIELDS beside gifts_charity.
    $user = User::factory()->withActivePremiumSubscription()->create();

    // The form has always posted this key. It was silently discarded.
    $this->actingAs($user, 'sanctum')->putJson('/api/user/profile/expenditure', [
        'monthly_expenditure' => 1000,
        'charitable_donations' => 75,
    ])->assertOk();

    expect((float) $user->fresh()->charitable_donations)->toBe(75.0)
        ->and((float) $user->fresh()->annual_charitable_donations)->toBe(900.0);
});

it('does not zero a recorded donation when expenditure is saved without it', function (): void {
    // The live data-loss path: Fyn records the donation, the user later saves an
    // unrelated expenditure change, and the donation must survive it.
    $user = User::factory()->withActivePremiumSubscription()->create([
        'charitable_donations' => 200,
        'is_gift_aid' => true,
    ]);

    $this->actingAs($user, 'sanctum')->putJson('/api/user/profile/expenditure', [
        'monthly_expenditure' => 1500,
        'food_groceries' => 400,
    ])->assertOk();

    expect((float) $user->fresh()->charitable_donations)->toBe(200.0)
        ->and((float) $user->fresh()->annual_charitable_donations)->toBe(2400.0);
});

it('records a capture in the monthly column and reports what was stored', function (): void {
    $user = User::factory()->create();

    $result = (new ReflectionMethod(CoordinatingAgent::class, 'handleCaptureCharitableGiving'))
        ->invoke(app(CoordinatingAgent::class), ['annual_donations' => 2400], $user, false);

    expect((float) $user->fresh()->charitable_donations)->toBe(200.0)
        ->and((float) $user->fresh()->annual_charitable_donations)->toBe(2400.0)
        ->and($result['summary'])->toContain('2,400');
});

it('shows the donation on the profile payload both surfaces read', function (): void {
    // /m builds its Expenditure page from expenditure.categories on this payload.
    // The key was absent, so a donation captured on /m could never be seen there.
    $user = User::factory()->create(['charitable_donations' => 200]);

    $profile = app(UserProfileService::class)->getCompleteProfile($user);

    expect($profile['expenditure']['categories'])->toHaveKey('charitable_donations')
        ->and((float) $profile['expenditure']['categories']['charitable_donations'])->toBe(200.0);
});

it('leaves the annual figure derived, never written by hand', function (): void {
    // The wipe was a frontend side-channel write of annual_charitable_donations
    // firing on the Gift Aid flag alone while the monthly field sat at 0. The
    // annual column is derived by User::charitableDonations() and nothing else
    // may set it, or the derivation can be fought again from another surface.
    $offenders = [];

    foreach (['resources/js', 'resources/mobile'] as $dir) {
        $path = base_path($dir);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($files as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['js', 'vue'], true)) {
                continue;
            }
            if (str_contains((string) file_get_contents($file->getPathname()), 'annual_charitable_donations')) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBe([], 'These write or read the derived annual figure directly: '.implode(', ', $offenders));
});
