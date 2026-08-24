<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Constants\ValidationLimits;
use App\Models\RetirementProfile;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * W-0035. `retirement_profiles.target_retirement_income` is the figure every
 * retirement projection is built on, and until this endpoint existed the only thing
 * that could write it was Fyn's `capture_retirement_goals` tool. A user who never
 * chatted to Fyn had required capital, the income projection, decumulation, capital
 * adequacy and Monte Carlo all built on `RequiredCapitalCalculator`'s fallback —
 * (gross income − pension contributions) × 75% — presented as their own target.
 *
 * For the persona household that meant Sarah being told she needed £116,250 a year
 * when she had said £55,000.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

function goalsUser(array $attributes = []): User
{
    return User::factory()->create([
        'date_of_birth' => now()->subYears(48)->subMonths(2)->format('Y-m-d'),
        'annual_employment_income' => 120000,
        ...$attributes,
    ]);
}

it('persists a target retirement income to retirement_profiles', function () {
    $user = goalsUser();
    Sanctum::actingAs($user);

    $this->putJson('/api/retirement/goals', [
        'target_retirement_age' => 60,
        'target_retirement_income' => 55000,
    ])->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('retirement_profiles', [
        'user_id' => $user->id,
        'target_retirement_age' => 60,
        'target_retirement_income' => 55000.00,
    ]);
});

it('makes required-capital report the stated target rather than the derived one', function () {
    $user = goalsUser();
    Sanctum::actingAs($user);

    // Before: nothing stated, so the calculator falls back and says so.
    $before = $this->getJson('/api/retirement/required-capital')->assertOk();
    expect($before->json('data.income_source'))->toBe('calculated')
        ->and((float) $before->json('data.required_income'))->toBe(90000.0); // 120,000 x 0.75

    $this->putJson('/api/retirement/goals', [
        'target_retirement_age' => 60,
        'target_retirement_income' => 55000,
    ])->assertOk();

    $after = $this->getJson('/api/retirement/required-capital')->assertOk();
    expect($after->json('data.income_source'))->toBe('profile')
        ->and((float) $after->json('data.required_income'))->toBe(55000.0);
});

it('keeps the derived figure as a fallback for a user who has stated nothing', function () {
    Sanctum::actingAs(goalsUser());

    $response = $this->getJson('/api/retirement/required-capital')->assertOk();

    expect($response->json('data.income_source'))->toBe('calculated')
        ->and((float) $response->json('data.required_income'))->toBeGreaterThan(0.0);
});

it('updates an existing profile without disturbing the value not supplied', function () {
    $user = goalsUser();
    RetirementProfile::create([
        'user_id' => $user->id,
        'current_age' => 48,
        'target_retirement_age' => 60,
        'target_retirement_income' => 55000,
    ]);
    Sanctum::actingAs($user);

    $this->putJson('/api/retirement/goals', ['target_retirement_income' => 62000])->assertOk();

    $this->assertDatabaseHas('retirement_profiles', [
        'user_id' => $user->id,
        'target_retirement_age' => 60, // untouched
        'target_retirement_income' => 62000.00,
    ]);
});

it('creates a profile from an income alone when the user already has a retirement age', function () {
    $user = goalsUser(['target_retirement_age' => 62]);
    Sanctum::actingAs($user);

    $this->putJson('/api/retirement/goals', ['target_retirement_income' => 55000])->assertOk();

    $this->assertDatabaseHas('retirement_profiles', [
        'user_id' => $user->id,
        'target_retirement_age' => 62,
        'target_retirement_income' => 55000.00,
    ]);
});

it('refuses to invent a retirement age when nothing has one', function () {
    $user = goalsUser(['target_retirement_age' => null]);
    Sanctum::actingAs($user);

    $this->putJson('/api/retirement/goals', ['target_retirement_income' => 55000])
        ->assertStatus(422)
        ->assertJsonPath('errors.target_retirement_age.0', 'Set your target retirement age before recording a target retirement income.');

    $this->assertDatabaseMissing('retirement_profiles', ['user_id' => $user->id]);
});

it('refreshes the cached current_age instead of leaving it stale', function () {
    // PensionProjector::getCurrentAge() prefers retirement_profiles.current_age over
    // the date of birth, so a value left stale silently shifts every projection.
    $user = goalsUser();
    RetirementProfile::create([
        'user_id' => $user->id,
        'current_age' => 31, // stale by years
        'target_retirement_age' => 60,
    ]);
    Sanctum::actingAs($user);

    $this->putJson('/api/retirement/goals', ['target_retirement_income' => 55000])->assertOk();

    expect(RetirementProfile::where('user_id', $user->id)->first()->current_age)->toBe(48);
});

it('rejects a request that states neither an age nor an income', function () {
    Sanctum::actingAs(goalsUser());

    $this->putJson('/api/retirement/goals', [])->assertStatus(422);
});

it('bounds the target retirement age by ValidationLimits, not by a number typed into the request', function () {
    Sanctum::actingAs(goalsUser());

    $this->putJson('/api/retirement/goals', [
        'target_retirement_age' => ValidationLimits::MIN_RETIREMENT_AGE - 1,
    ])->assertStatus(422)->assertJsonValidationErrors('target_retirement_age');

    $this->putJson('/api/retirement/goals', [
        'target_retirement_age' => ValidationLimits::MAX_RETIREMENT_AGE + 1,
    ])->assertStatus(422)->assertJsonValidationErrors('target_retirement_age');
});

it('rejects a negative target retirement income', function () {
    Sanctum::actingAs(goalsUser());

    $this->putJson('/api/retirement/goals', ['target_retirement_income' => -1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('target_retirement_income');
});

it('requires authentication', function () {
    $this->putJson('/api/retirement/goals', ['target_retirement_income' => 55000])
        ->assertStatus(401);
});

/**
 * `retirement_profiles` is the store of record, but RetirementProjectionService,
 * the "When you want to retire" data requirement and ModuleAvailabilityProvider all
 * read `users.target_retirement_age`. Fyn's capture handler carried this mirror
 * alone, so this endpoint shipped without it and a user who set 60 on the module
 * form still saw the default 67 on /retirement with the checklist item outstanding.
 * It lives in RetirementProfileStore now — one store, every surface (Rule 20).
 */
it('mirrors the retirement age onto the users column the projection reads', function () {
    $user = goalsUser(['target_retirement_age' => null]);
    Sanctum::actingAs($user);

    $this->putJson('/api/retirement/goals', [
        'target_retirement_age' => 60,
        'target_retirement_income' => 55000,
    ])->assertOk();

    expect($user->fresh()->target_retirement_age)->toBe(60);
});

it('mirrors the age on an update too, not only when creating the profile', function () {
    $user = goalsUser(['target_retirement_age' => 60]);
    RetirementProfile::create([
        'user_id' => $user->id,
        'current_age' => 48,
        'target_retirement_age' => 60,
    ]);
    Sanctum::actingAs($user);

    $this->putJson('/api/retirement/goals', ['target_retirement_age' => 65])->assertOk();

    expect($user->fresh()->target_retirement_age)->toBe(65);
});

it('writes the same row whether the module form or Fyn asked', function () {
    // Rule 20. Fyn's capture_retirement_goals used to create the row itself; both
    // paths now go through RetirementProfileStore, so a figure set on one surface
    // cannot differ in shape from the same figure set on another.
    $viaForm = goalsUser(['target_retirement_age' => null, 'is_preview_user' => false]);
    Sanctum::actingAs($viaForm);
    $this->putJson('/api/retirement/goals', [
        'target_retirement_age' => 60,
        'target_retirement_income' => 55000,
    ])->assertOk();

    $viaFyn = goalsUser(['target_retirement_age' => null, 'is_preview_user' => false]);
    app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_age' => 60,
        'target_retirement_income' => 55000,
    ], $viaFyn);

    $shape = fn (User $u): array => [
        'target_retirement_age' => $u->retirementProfile->target_retirement_age,
        'target_retirement_income' => (float) $u->retirementProfile->target_retirement_income,
        'current_age' => $u->retirementProfile->current_age,
        'users_target_retirement_age' => $u->fresh()->target_retirement_age,
    ];

    expect($shape($viaForm->fresh()))->toBe($shape($viaFyn->fresh()));
});

it('asks Fyn for a date of birth rather than inventing a current age of 30', function () {
    // The handler used to default current_age to 30 when the date of birth was
    // unknown. PensionProjector::getCurrentAge() prefers current_age over the date
    // of birth, so that fabrication silently shifted every projection the user saw.
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => null]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', [
        'target_retirement_age' => 60,
        'target_retirement_income' => 55000,
    ], $user);

    expect($result['error'] ?? false)->toBeTrue()
        ->and($result['error_type'] ?? null)->toBe('validation_failed');

    $this->assertDatabaseMissing('retirement_profiles', ['user_id' => $user->id]);
});
