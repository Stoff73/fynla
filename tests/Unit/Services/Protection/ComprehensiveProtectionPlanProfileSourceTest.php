<?php

declare(strict_types=1);

use App\Constants\ProfileEnums;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\ComprehensiveProtectionPlanService;
use Illuminate\Support\Facades\Schema;

/**
 * Item 8a (CSJ 2026-10-06): smoking and health have ONE home, the user's own
 * answers (`users.smoking_status` / `health_status`), which the web and /m Health
 * forms and Fyn's personal details form save. The protection profile's copies had
 * no input on any surface, every production row held their defaults (0 / 'good'),
 * and they were dropped. This replaces W-0033's tests, which pinned the profile as
 * the source because the code already read it.
 *
 * `buildUserProfile` is private and the public entry point needs a full protection
 * analysis, so it is invoked by reflection — the pattern used by the HasAiChat trait
 * tests.
 */
function protectionUserProfile(User $user, ProtectionProfile $profile): array
{
    $reflection = new ReflectionMethod(ComprehensiveProtectionPlanService::class, 'buildUserProfile');
    $reflection->setAccessible(true);

    return $reflection->invoke(app(ComprehensiveProtectionPlanService::class), $user, $profile);
}

it('shows the user\'s own smoking and health answers, in the Health form\'s words', function (): void {
    $user = User::factory()->create(['smoking_status' => 'quit_recent', 'health_status' => 'no_existing']);
    $profile = ProtectionProfile::factory()->create(['user_id' => $user->id]);

    $rendered = protectionUserProfile($user, $profile);

    expect($rendered['smoker_status'])->toBe('No, gave up 12 months or sooner')
        ->and($rendered['health_status'])->toBe('No, existing health conditions');
});

it('says "Not provided" rather than inventing an answer when the user has not answered', function (): void {
    $user = User::factory()->create(['smoking_status' => null, 'health_status' => null]);
    $profile = ProtectionProfile::factory()->create(['user_id' => $user->id]);

    $rendered = protectionUserProfile($user, $profile);

    expect($rendered['smoker_status'])->toBe('Not provided')
        ->and($rendered['health_status'])->toBe('Not provided');
});

it('has no second home on the protection profile', function (): void {
    expect(Schema::hasColumn('protection_profiles', 'smoker_status'))->toBeFalse()
        ->and(Schema::hasColumn('protection_profiles', 'health_status'))->toBeFalse();
});

it('still reads education level from the one home design-lead gave it', function (): void {
    $user = User::factory()->create(['education_level' => 'postgraduate']);
    $profile = ProtectionProfile::factory()->create(['user_id' => $user->id]);

    expect(protectionUserProfile($user, $profile)['education_level'])
        ->toBe(ProfileEnums::EDUCATION_LEVEL_LABELS['postgraduate']);
});
