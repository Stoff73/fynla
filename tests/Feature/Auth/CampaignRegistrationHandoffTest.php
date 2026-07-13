<?php

declare(strict_types=1);

use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Auth\MFAService;
use App\Services\Auth\RegistrationHandoffService;
use App\Services\Onboarding\FunnelIncomeBand;
use App\Services\TaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Carbon::setTestNow();
});

function campaignRegistrationPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'first_name' => 'Campaign',
        'surname' => 'Tester',
        'email' => 'campaign-'.uniqid().'@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'funnel_answers' => [
            'campaign' => 'savetax',
            'employment' => 'full-time',
        ],
    ], $overrides);
}

it('exchanges a campaign handoff without browser storage or repeated fields', function (): void {
    $response = $this->postJson('/api/auth/register', campaignRegistrationPayload());

    $response->assertCreated()
        ->assertJsonPath('requires_verification', true)
        ->assertJsonPath('data.email', fn (string $email): bool => str_contains($email, '*'))
        ->assertJsonStructure(['data' => ['pending_id', 'email', 'handoff_token']]);

    $token = $response->json('data.handoff_token');
    expect($token)->toBeString()
        ->and($token)->not->toContain('@example.com');

    $this->postJson('/api/auth/registration-handoff/resolve', ['handoff' => $token])
        ->assertOk()
        ->assertJson([
            'kind' => 'verification',
            'source' => 'savetax',
            'pending_id' => $response->json('data.pending_id'),
            'first_name' => 'Campaign',
        ])
        ->assertJsonMissingPath('email')
        ->assertJsonMissingPath('verification_code')
        ->assertJsonMissingPath('password');
});

it('strips unrecognised campaign facts before they can seed a user profile', function (): void {
    $response = $this->postJson('/api/auth/register', campaignRegistrationPayload([
        'funnel_answers' => [
            'campaign' => 'savetax',
            'employment' => 'chief-wizard',
            'income' => 'millions',
            'spouse' => 'yes',
            'spouseIncome' => 'attacker-controlled',
            'assets' => ['isa', 'offshore-secret', 'isa'],
        ],
    ]))->assertCreated();

    $pending = PendingRegistration::findOrFail($response->json('data.pending_id'));
    expect($pending->funnel_answers)->toMatchArray([
        'campaign' => 'savetax',
        'spouse' => 'yes',
        'assets' => ['isa'],
    ]);
});

it('stamps Save Tax income bands with the issued tax-year interpretation', function (): void {
    $response = $this->postJson('/api/auth/register', campaignRegistrationPayload([
        'funnel_answers' => [
            'campaign' => 'savetax',
            'employment' => 'full-time',
            'income' => '50271_100000',
            'spouse' => 'yes',
            'spouseIncome' => 'upto_50270',
        ],
    ]))->assertCreated();

    $answers = PendingRegistration::findOrFail($response->json('data.pending_id'))->funnel_answers;
    expect($answers['income_context'])->toMatchArray([
        'key' => '50271_100000',
        'tax_year' => app(TaxConfigService::class)->getTaxYear(),
        'label' => FunnelIncomeBand::label('50271_100000'),
        'recap_label' => FunnelIncomeBand::recapLabel('50271_100000'),
    ])->and($answers['spouse_income_context'])->toMatchArray([
        'key' => 'upto_50270',
        'tax_year' => app(TaxConfigService::class)->getTaxYear(),
        'label' => FunnelIncomeBand::label('upto_50270'),
        'recap_label' => FunnelIncomeBand::recapLabel('upto_50270'),
    ]);
});

it('rejects an expired or subject-mismatched handoff token', function (): void {
    Carbon::setTestNow('2026-07-13 10:00:00');
    $pending = PendingRegistration::createOrUpdate(campaignRegistrationPayload());
    $token = app(RegistrationHandoffService::class)->issue($pending, 'verification', 'savetax');

    Carbon::setTestNow('2026-07-13 10:16:00');
    $this->postJson('/api/auth/registration-handoff/resolve', ['handoff' => $token])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This registration link has expired. Please start again.');

    Carbon::setTestNow('2026-07-13 10:00:00');
    $freshToken = app(RegistrationHandoffService::class)->issue($pending, 'verification', 'savetax');
    $pending->delete();

    $this->postJson('/api/auth/registration-handoff/resolve', ['handoff' => $freshToken])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This registration link is no longer valid. Please start again.');
});

it('opens restoration with the original campaign source and no exposed account data', function (): void {
    $user = User::factory()->create([
        'email' => 'restorable-campaign@example.com',
        'first_name' => 'Returner',
        'password' => 'Password123!',
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings',
    ]);
    $user->delete();

    $response = $this->postJson('/api/auth/register', campaignRegistrationPayload([
        'email' => $user->email,
    ]));

    $response->assertOk()
        ->assertJson([
            'account_deleted_restorable' => true,
            'requires_password_verification' => true,
        ])
        ->assertJsonStructure(['handoff_token']);

    $this->postJson('/api/auth/registration-handoff/resolve', [
        'handoff' => $response->json('handoff_token'),
    ])->assertOk()
        ->assertJson([
            'kind' => 'restoration',
            'source' => 'savetax',
        ])
        ->assertJsonMissingPath('email')
        ->assertJsonMissingPath('user_id')
        ->assertJsonMissingPath('first_name')
        ->assertJsonMissingPath('deleted_at');
});

it('invalidates an older verification handoff when the registration is replaced', function (): void {
    $payload = campaignRegistrationPayload(['email' => 'replaced@example.com']);
    $first = $this->postJson('/api/auth/register', $payload)->assertCreated();

    $this->postJson('/api/auth/register', array_replace($payload, [
        'first_name' => 'Replacement',
    ]))->assertCreated();

    $this->postJson('/api/auth/registration-handoff/resolve', [
        'handoff' => $first->json('data.handoff_token'),
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'This registration link is no longer valid. Please start again.');
});

it('does not offer restoration outside the retained restoration window', function (): void {
    $user = User::factory()->create([
        'email' => 'retention-ended@example.com',
        'password' => 'Password123!',
        'deletion_reason' => 'user_requested',
        'purge_eligible_at' => now()->subMinute(),
    ]);
    $user->delete();

    $this->postJson('/api/auth/register', campaignRegistrationPayload([
        'email' => $user->email,
    ]))->assertUnprocessable()
        ->assertJsonPath('email_exists', true)
        ->assertJsonMissingPath('handoff_token');
});

it('invalidates a restoration handoff after a restore and new deletion lifecycle', function (): void {
    $user = User::factory()->create([
        'email' => 'deleted-twice@example.com',
        'password' => 'Password123!',
        'deletion_reason' => 'user_requested',
        'purge_eligible_at' => now()->addYear(),
    ]);
    $user->delete();
    $token = app(RegistrationHandoffService::class)->issue($user, 'restoration', 'savetax');

    $user->restore();
    $user->forceFill(['purge_eligible_at' => now()->addYear()])->save();
    Carbon::setTestNow(now()->addSecond());
    $user->delete();

    $this->postJson('/api/auth/registration-handoff/resolve', ['handoff' => $token])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This registration link is no longer valid. Please start again.');
});

it('restores through the handoff and returns to the savetax journey instead of pricing', function (): void {
    $user = User::factory()->create([
        'email' => 'restore-through-handoff@example.com',
        'password' => 'Password123!',
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings',
        'purge_eligible_at' => now()->addYear(),
    ]);
    $user->delete();

    $register = $this->postJson('/api/auth/register', campaignRegistrationPayload([
        'email' => $user->email,
    ]))->assertOk();

    $check = $this->postJson('/api/auth/restore/check', [
        'registration_handoff' => $register->json('handoff_token'),
        'password' => 'Password123!',
    ])->assertOk();

    $restore = $this->postJson('/api/auth/restore', [
        'restoration_token' => $check->json('restoration_token'),
    ])->assertOk()
        ->assertJsonPath('redirect_to', '/dashboard?openFyn=journey&from=savetax')
        ->assertJsonMissingPath('redirect_to.openPricing');

    $accessToken = $user->fresh()->tokens()->latest()->first();
    expect($accessToken?->abilities)->toBe(['mfa_verified'])
        ->and(UserSession::where('token_id', $accessToken?->id)->exists())->toBeTrue()
        ->and($restore->json('token'))->toBeString();
});

it('requires multi-factor authentication before restoring a protected account', function (): void {
    $user = User::factory()->create([
        'email' => 'mfa-restore@example.com',
        'password' => 'Password123!',
        'deletion_reason' => 'user_requested',
        'purge_eligible_at' => now()->addYear(),
    ]);
    $recoveryCodes = app(MFAService::class)->enableMFA(
        $user,
        app(MFAService::class)->generateSecret()
    );
    $user->delete();
    $handoff = app(RegistrationHandoffService::class)->issue($user, 'restoration', 'savetax');

    $check = $this->postJson('/api/auth/restore/check', [
        'registration_handoff' => $handoff,
        'password' => 'Password123!',
    ])->assertOk()
        ->assertJsonPath('requires_mfa', true);

    $this->postJson('/api/auth/restore', [
        'restoration_token' => $check->json('restoration_token'),
    ])->assertUnprocessable()
        ->assertJsonPath('requires_mfa', true);
    expect(User::withTrashed()->findOrFail($user->id)->trashed())->toBeTrue();

    $this->postJson('/api/auth/restore', [
        'restoration_token' => $check->json('restoration_token'),
        'mfa_code' => 'wrong-code',
    ])->assertUnauthorized();
    expect(User::withTrashed()->findOrFail($user->id)->trashed())->toBeTrue();

    $this->postJson('/api/auth/restore', [
        'restoration_token' => $check->json('restoration_token'),
        'mfa_code' => $recoveryCodes[0],
    ])->assertOk();
    expect(User::find($user->id))->not->toBeNull();
});

it('locks the restoration password boundary after repeated failures', function (): void {
    config()->set('auth.lockout.thresholds', [3 => 1]);
    $user = User::factory()->create([
        'email' => 'locked-restore@example.com',
        'password' => 'Password123!',
        'deletion_reason' => 'user_requested',
        'purge_eligible_at' => now()->addYear(),
    ]);
    $user->delete();
    $handoff = app(RegistrationHandoffService::class)->issue($user, 'restoration', 'savetax');

    foreach (range(1, 3) as $_attempt) {
        $this->postJson('/api/auth/restore/check', [
            'registration_handoff' => $handoff,
            'password' => 'WrongPassword!',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/auth/restore/check', [
        'registration_handoff' => $handoff,
        'password' => 'Password123!',
    ])->assertStatus(423)
        ->assertJsonPath('locked', true);
});

it('applies the same account lockout to deleted-account login', function (): void {
    config()->set('auth.lockout.thresholds', [3 => 1]);
    $user = User::factory()->create([
        'email' => 'locked-deleted-login@example.com',
        'password' => 'Password123!',
        'deletion_reason' => 'user_requested',
        'purge_eligible_at' => now()->addYear(),
    ]);
    $user->delete();

    foreach (range(1, 3) as $_attempt) {
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Password123!',
    ])->assertStatus(423)
        ->assertJsonPath('locked', true);
});

it('consumes a restoration token exactly once', function (): void {
    $user = User::factory()->create([
        'email' => 'single-use-restore@example.com',
        'password' => 'Password123!',
        'deletion_reason' => 'user_requested',
        'purge_eligible_at' => now()->addYear(),
    ]);
    $user->delete();
    $handoff = app(RegistrationHandoffService::class)->issue($user, 'restoration', 'savetax');
    $check = $this->postJson('/api/auth/restore/check', [
        'registration_handoff' => $handoff,
        'password' => 'Password123!',
    ])->assertOk();
    $payload = ['restoration_token' => $check->json('restoration_token')];

    $this->postJson('/api/auth/restore', $payload)->assertOk();
    $this->postJson('/api/auth/restore', $payload)->assertUnauthorized();
});

it('rejects a verification handoff at the restoration password boundary', function (): void {
    $pending = PendingRegistration::createOrUpdate(campaignRegistrationPayload());
    $token = app(RegistrationHandoffService::class)->issue($pending, 'verification', 'savetax');

    $this->postJson('/api/auth/restore/check', [
        'registration_handoff' => $token,
        'password' => 'Password123!',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'This registration link cannot be used for that action.');
});

it('does not issue a campaign handoff for an ordinary registration', function (): void {
    $payload = campaignRegistrationPayload();
    unset($payload['funnel_answers']);

    $this->postJson('/api/auth/register', $payload)
        ->assertCreated()
        ->assertJsonMissingPath('data.handoff_token');
});

it('strips the handoff token from browser history before resolving it', function (): void {
    $source = file_get_contents(resource_path('js/views/Register.vue'));

    expect($source)->toContain("cleanUrl.searchParams.delete('handoff')")
        ->and($source)->toContain('window.history.replaceState')
        ->and(strpos($source, 'window.history.replaceState'))
        ->toBeLessThan(strpos($source, "api.post('/auth/registration-handoff/resolve'"));
});

it('injects a base-path-aware bearer guard before the campaign page can paint', function (): void {
    config()->set('app.url', 'https://csjones.co/fynla');

    $response = $this->get('/savetax');

    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toMatch('/<head[^>]*><script>\(function\(\)/i')
        ->and($html)->toContain("window.location.replace('/fynla/dashboard')")
        ->and(strpos($html, "window.location.replace('/fynla/dashboard')"))
        ->toBeLessThan(strpos($html, '<body'));
});

it('server-redirects an authenticated campaign visitor to the environment dashboard', function (): void {
    config()->set('app.url', 'https://csjones.co/fynla');
    $user = User::factory()->create(['is_preview_user' => false]);

    $this->actingAs($user)
        ->get('/savetax')
        ->assertRedirect('https://csjones.co/fynla/dashboard');
});
