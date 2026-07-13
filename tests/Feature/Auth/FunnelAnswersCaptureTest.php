<?php

declare(strict_types=1);

use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Auth\FunnelAnswersMapper;
use Database\Seeders\SubscriptionPlanSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * /savetax acquisition-funnel answers. The public funnel captures 5 answers
 * (employment, income band, spouse, spouse income, assets) and the compact
 * "Register for free" button submits them with registration. Backend persists
 * them on PendingRegistration through the verify step, then copies them onto
 * the new User row so onboarding/Fyn and the dashboard start from the user's
 * real responses. Mirrors the signup_source flow.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(SubscriptionPlanSeeder::class);
});

function funnelPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Funnel',
        'surname' => 'Tester',
        'email' => 'funnel-'.uniqid().'@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ], $overrides);
}

$answers = [
    'employment' => 'full-time',
    'income' => '50271_100000',
    'spouse' => 'yes',
    'spouseIncome' => 'upto_50270',
    'assets' => ['isa', 'pension', 'savings'],
];

it('persists funnel_answers on pending_registrations at registration', function () use ($answers) {
    $email = 'funnel-pending@example.com';
    $this->postJson('/api/auth/register', funnelPayload([
        'email' => $email,
        'funnel_answers' => $answers,
    ]))->assertStatus(201);

    $pending = PendingRegistration::where('email', $email)->first();
    expect($pending)->not->toBeNull();
    expect($pending->funnel_answers)->toEqual($answers);
});

it('copies funnel_answers from pending_registrations onto the user when verified', function () use ($answers) {
    $email = 'funnel-verify@example.com';
    $register = $this->postJson('/api/auth/register', funnelPayload([
        'email' => $email,
        'funnel_answers' => $answers,
    ]));
    $register->assertStatus(201);
    $pendingId = $register->json('data.pending_id');

    $code = PendingRegistration::find($pendingId)->verification_code;
    $this->postJson('/api/auth/verify-code', [
        'type' => 'registration',
        'pending_id' => $pendingId,
        'code' => $code,
    ])->assertOk();

    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull();
    expect($user->funnel_answers)->toEqual($answers);

    // Pre-fill: the clean maps are seeded onto the profile (income band is a
    // range, confirmed conversationally, so it is intentionally NOT pre-filled).
    expect($user->employment_status)->toBe('full_time'); // funnel 'full-time'
    expect($user->marital_status)->toBe('married');       // funnel spouse 'yes'

    $this->assertDatabaseMissing('pending_registrations', ['email' => $email]);
});

it('maps funnel employment + spouse variants onto the profile', function () {
    $svc = new FunnelAnswersMapper;

    $selfEmployed = User::factory()->create([
        'employment_status' => null, 'marital_status' => null,
        'funnel_answers' => ['employment' => 'self-employed', 'spouse' => 'no'],
    ]);
    $svc->mapToProfile($selfEmployed);
    expect($selfEmployed->fresh()->employment_status)->toBe('self_employed');
    expect($selfEmployed->fresh()->marital_status)->toBe('single');

    // Never overwrites a value already set.
    $existing = User::factory()->create([
        'employment_status' => 'retired', 'marital_status' => 'widowed',
        'funnel_answers' => ['employment' => 'full-time', 'spouse' => 'yes'],
    ]);
    $svc->mapToProfile($existing);
    expect($existing->fresh()->employment_status)->toBe('retired');
    expect($existing->fresh()->marital_status)->toBe('widowed');
});

it('maps funnel spouseIncome onto household_calculation_mode so Fyn never re-asks', function () {
    $svc = new FunnelAnswersMapper;

    // 'zero' answers the spouse-work question outright: single-earner couple.
    $singleEarner = User::factory()->create([
        'employment_status' => null, 'marital_status' => null,
        'household_calculation_mode' => null,
        'funnel_answers' => ['spouse' => 'yes', 'spouseIncome' => 'zero'],
    ]);
    $svc->mapToProfile($singleEarner);
    expect($singleEarner->fresh()->household_calculation_mode)->toBe('single_earner_couple');
    expect($singleEarner->fresh()->marriage_allowance_eligible)->toBeTrue();

    // Any earning band → dual-earner household (exact figure still confirmed
    // conversationally in STATE_CAMPAIGN_SPOUSE_HOUSEHOLD).
    $dualEarner = User::factory()->create([
        'employment_status' => null, 'marital_status' => null,
        'household_calculation_mode' => null,
        'funnel_answers' => ['spouse' => 'yes', 'spouseIncome' => 'upto_50270'],
    ]);
    $svc->mapToProfile($dualEarner);
    expect($dualEarner->fresh()->household_calculation_mode)->toBe('dual_earner');
    expect($dualEarner->fresh()->marriage_allowance_eligible)->toBeFalse();

    // No spouse → never set; spouseIncome absent → never set.
    $noSpouse = User::factory()->create([
        'employment_status' => null, 'marital_status' => null,
        'household_calculation_mode' => null,
        'funnel_answers' => ['spouse' => 'no'],
    ]);
    $svc->mapToProfile($noSpouse);
    expect($noSpouse->fresh()->household_calculation_mode)->toBeNull();

    // Never overwrites a value already set.
    $existing = User::factory()->create([
        'employment_status' => null, 'marital_status' => null,
        'household_calculation_mode' => 'dual_earner',
        'funnel_answers' => ['spouse' => 'yes', 'spouseIncome' => 'zero'],
    ]);
    $svc->mapToProfile($existing);
    expect($existing->fresh()->household_calculation_mode)->toBe('dual_earner');
});

it('preserves funnel_answers on re-registration when the second attempt omits them (bug 6.1)', function () use ($answers) {
    $email = 'funnel-reregister@example.com';

    // First attempt comes via the /savetax funnel, carrying funnel_answers.
    $this->postJson('/api/auth/register', funnelPayload([
        'email' => $email,
        'funnel_answers' => $answers,
    ]))->assertStatus(201);

    // A second attempt on the same email (e.g. SPA re-register after an
    // abandoned verification) does NOT resend funnel_answers — it must not
    // null them, or the user loses the SaveTax onboarding entry point.
    $this->postJson('/api/auth/register', funnelPayload([
        'email' => $email,
    ]))->assertStatus(201);

    $pending = PendingRegistration::where('email', $email)->first();
    expect($pending->funnel_answers)->toEqual($answers);
});

it('accepts registration when funnel_answers is omitted (direct signup)', function () {
    $this->postJson('/api/auth/register', funnelPayload([
        'email' => 'funnel-omitted@example.com',
    ]))->assertStatus(201);

    $pending = PendingRegistration::where('email', 'funnel-omitted@example.com')->first();
    expect($pending->funnel_answers)->toBeNull();
});

it('rejects funnel_answers when it is not an array', function () {
    $this->postJson('/api/auth/register', funnelPayload([
        'email' => 'funnel-bad@example.com',
        'funnel_answers' => 'not-an-array',
    ]))->assertStatus(422)->assertJsonValidationErrors(['funnel_answers']);
});
