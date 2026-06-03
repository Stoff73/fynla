<?php

declare(strict_types=1);

use App\Models\PendingRegistration;
use App\Models\User;
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
    'income' => 'higher',
    'spouse' => 'yes',
    'spouseIncome' => 'personal-allowance',
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

    $this->assertDatabaseMissing('pending_registrations', ['email' => $email]);
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
