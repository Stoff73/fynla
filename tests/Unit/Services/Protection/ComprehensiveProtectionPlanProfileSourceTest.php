<?php

declare(strict_types=1);

use App\Constants\ProfileEnums;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\ComprehensiveProtectionPlanService;
use Illuminate\Support\Facades\DB;

/**
 * W-0033. `buildUserProfile()` used to read `$user->smoker` and `$user->good_health`
 * "first", falling back to the protection profile. Neither property has ever existed
 * on User, so both branches were permanently false and the profile always won.
 *
 * The item asked for a decision rather than a tidy-up: which source is authoritative
 * for health and smoking status in protection advice. The answer is the protection
 * profile, because that is what the enforcing layer reads — RecommendationEngine
 * generates the advice from `$profile->smoker_status`, ProtectionDataReadinessService
 * gates on it, and Retirement reads the same field for the same fact. These tests
 * pin that decision so the dead branches are not "restored" by someone who reads the
 * old comment and assumes the user record was meant to win.
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

it('takes smoking status from the protection profile, not the user record', function (): void {
    $user = User::factory()->create(['smoking_status' => 'yes']);
    $profile = ProtectionProfile::factory()->create([
        'user_id' => $user->id,
        'smoker_status' => false,
    ]);

    // The user record says they smoke; the profile says they do not. The profile is
    // authoritative, and it is what RecommendationEngine reads to write the advice
    // that sits beside this line in the plan.
    expect(protectionUserProfile($user, $profile)['smoker_status'])->toBe('Non-smoker');
});

it('takes health status from the protection profile, not the user record', function (): void {
    $user = User::factory()->create(['health_status' => 'no_existing']);
    $profile = ProtectionProfile::factory()->create([
        'user_id' => $user->id,
        'health_status' => 'excellent',
    ]);

    expect(protectionUserProfile($user, $profile)['health_status'])->toBe('Excellent');
});

it('reports a smoker as a smoker', function (): void {
    $user = User::factory()->create();
    $profile = ProtectionProfile::factory()->create([
        'user_id' => $user->id,
        'smoker_status' => true,
    ]);

    expect(protectionUserProfile($user, $profile)['smoker_status'])->toBe('Smoker');
});

it('says "Not provided" rather than inventing an answer when the profile holds none', function (): void {
    $user = User::factory()->create();
    $profile = ProtectionProfile::factory()->make([
        'user_id' => $user->id,
        'smoker_status' => null,
        'health_status' => null,
    ]);

    $rendered = protectionUserProfile($user, $profile);

    expect($rendered['smoker_status'])->toBe('Not provided')
        ->and($rendered['health_status'])->toBe('Not provided');
});

/**
 * CHARACTERISATION TEST — this pins a defect, not a desired behaviour.
 *
 * `->make()` above is not a stylistic choice: `protection_profiles.smoker_status` is
 * `tinyint(1) NOT NULL DEFAULT 0` and `health_status` is `varchar(255) NOT NULL
 * DEFAULT 'good'`, so an unanswered question cannot be stored. The moment a profile
 * row is created the database supplies "not a smoker, in good health" and the plan
 * states it as fact — on the document that decides how much life cover to recommend.
 * `StoreProtectionProfileRequest:37-38` validates both as `nullable`, so the request
 * layer permits "no answer" and the column silently converts it to a definite one.
 *
 * Raised as W-0141. When that is fixed this test will fail — that is the signal, not
 * a regression. Update it then; do not "fix" it by widening the assertion.
 */
it('currently cannot store an unanswered smoking or health question at all', function (): void {
    $columns = collect(DB::select('SHOW COLUMNS FROM protection_profiles'))->keyBy('Field');

    expect($columns['smoker_status']->Null)->toBe('NO')
        ->and($columns['smoker_status']->Default)->toBe('0')
        ->and($columns['health_status']->Null)->toBe('NO')
        ->and($columns['health_status']->Default)->toBe('good');
});

it('still reads education level from the one home design-lead gave it', function (): void {
    $user = User::factory()->create(['education_level' => 'postgraduate']);
    $profile = ProtectionProfile::factory()->create(['user_id' => $user->id]);

    // F-0005 moved this off a private `match` in this service and onto
    // ProfileEnums::EDUCATION_LEVEL_LABELS. W-0033 edits the lines directly above it;
    // this asserts the edit did not disturb that.
    expect(protectionUserProfile($user, $profile)['education_level'])
        ->toBe(ProfileEnums::EDUCATION_LEVEL_LABELS['postgraduate']);
});
