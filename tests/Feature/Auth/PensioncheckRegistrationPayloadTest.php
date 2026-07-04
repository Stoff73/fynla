<?php

declare(strict_types=1);

use App\Models\PendingRegistration;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * C3 RED-FIRST — RegisterRequest strips funnel_answers.age / .pensions / .pot
 * for pensioncheck registrations because those keys are absent from the
 * validation rules. The fix adds them; these tests prove it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(SubscriptionPlanSeeder::class);
});

function pensioncheckRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Pension',
        'surname' => 'Check',
        'email' => 'pension-check-'.uniqid().'@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'funnel_answers' => [
            'campaign' => 'pensioncheck',
            'employment' => 'full-time',
            'income' => 'upto_50270',
            'age' => '40s',
            'pensions' => ['workplace', 'personal_sipp'],
            'pot' => '50k_100k',
            'spouse' => 'no',
        ],
    ], $overrides);
}

it('persists all six pensioncheck funnel keys on pending_registrations after registration', function (): void {
    $payload = pensioncheckRegistrationPayload();
    $email = $payload['email'];

    $this->postJson('/api/auth/register', $payload)->assertStatus(201);

    $pending = PendingRegistration::where('email', $email)->first();
    expect($pending)->not->toBeNull();

    $fa = $pending->funnel_answers;
    expect($fa)->not->toBeNull();
    expect($fa['campaign'] ?? null)->toBe('pensioncheck');
    expect($fa['employment'] ?? null)->toBe('full-time');
    expect($fa['income'] ?? null)->toBe('upto_50270');
    // C3: these three keys were stripped before the fix
    expect($fa['age'] ?? null)->toBe('40s');
    expect($fa['pensions'] ?? null)->toBe(['workplace', 'personal_sipp']);
    expect($fa['pot'] ?? null)->toBe('50k_100k');
});

it('copies all six pensioncheck funnel keys onto the user row when MFA verifies', function (): void {
    $payload = pensioncheckRegistrationPayload();
    $email = $payload['email'];

    $registerResponse = $this->postJson('/api/auth/register', $payload)
        ->assertStatus(201);
    $pendingId = $registerResponse->json('data.pending_id');

    $code = PendingRegistration::find($pendingId)->verification_code;
    $this->postJson('/api/auth/verify-code', [
        'type' => 'registration',
        'pending_id' => $pendingId,
        'code' => $code,
    ])->assertOk();

    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull();

    $fa = $user->funnel_answers;
    expect($fa['age'] ?? null)->toBe('40s');
    expect($fa['pensions'] ?? null)->toBe(['workplace', 'personal_sipp']);
    expect($fa['pot'] ?? null)->toBe('50k_100k');
});

it('accepts registration without the new pensioncheck keys (savetax regression: omitting them is valid)', function (): void {
    $savetaxPayload = array_merge(pensioncheckRegistrationPayload(), [
        'funnel_answers' => [
            'campaign' => 'savetax',
            'employment' => 'full-time',
            'income' => 'upto_50270',
            'assets' => ['pension', 'savings'],
            'spouse' => 'no',
        ],
    ]);

    $this->postJson('/api/auth/register', $savetaxPayload)->assertStatus(201);
});
