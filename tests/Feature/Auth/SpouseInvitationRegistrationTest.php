<?php

declare(strict_types=1);

use App\Http\Middleware\RedirectPhoneToMobile;
use App\Mail\SpouseInvitation as SpouseInvitationMail;
use App\Models\PendingRegistration;
use App\Models\SpouseInvitation;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

/**
 * Batch 3 (September/September19Updates/azTest-plan.md, CSJ 2026-09-19).
 * Laura invited Azlan by email; nothing recorded it, so he registered as a
 * stranger and none of her figures reached him. The invitation is now a row
 * with a token: the link fills the registration page, and registering from
 * it links the accounts and hands over the household facts.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Mail::fake();
});

function inviteAzlan(string $inviterEmail = 'laura@example.com'): array
{
    $laura = User::factory()->create(['first_name' => 'Laura', 'email' => $inviterEmail, 'marital_status' => 'married', 'funnel_answers' => ['campaign' => 'savetax', 'spouse' => 'yes']]);
    Sanctum::actingAs($laura);
    test()->postJson('/api/user/family-members', [
        'first_name' => 'Azlan', 'last_name' => 'Raj', 'relationship' => 'spouse', 'email' => 'azlan@example.com', 'annual_income' => 230000,
    ])->assertSuccessful();
    $invitation = SpouseInvitation::where('inviter_id', $laura->id)->first();

    return [$laura, $invitation];
}

function registerFromInvitation(string $token, string $email = 'azlan@example.com'): User
{
    auth()->forgetGuards();
    test()->postJson('/api/auth/register', [
        'first_name' => 'Azlan', 'surname' => 'Raj', 'email' => $email,
        'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'invite_token' => $token,
    ])->assertStatus(201);
    $pending = PendingRegistration::where('email', $email)->first();
    expect($pending->spouse_invitation_token)->toBe($token);
    test()->postJson('/api/auth/verify-code', ['type' => 'registration', 'pending_id' => $pending->id, 'code' => $pending->verification_code, 'email' => $email])->assertOk();

    return User::where('email', $email)->firstOrFail();
}

it('remembers the invitation and puts its token on the email link', function (): void {
    [$laura, $invitation] = inviteAzlan();

    expect($invitation)->not->toBeNull()
        ->and($invitation->email)->toBe('azlan@example.com')
        ->and($invitation->first_name)->toBe('Azlan')
        ->and($invitation->isOpen())->toBeTrue();
    Mail::assertSent(SpouseInvitationMail::class, fn (SpouseInvitationMail $mail): bool => $mail->token === $invitation->token
        && str_contains($mail->content()->with['registerUrl'], '/register?invite='.$invitation->token));
});

it('fills the registration page from the link, and reads the same for a bad token', function (): void {
    [, $invitation] = inviteAzlan();
    auth()->forgetGuards();

    $this->getJson('/api/auth/spouse-invitation/'.$invitation->token)->assertOk()
        ->assertJson(['first_name' => 'Azlan', 'email' => 'azlan@example.com', 'inviter_first_name' => 'Laura']);
    $this->getJson('/api/auth/spouse-invitation/not-a-token')->assertStatus(404);

    $invitation->forceFill(['expires_at' => now()->subDay()])->save();
    $this->getJson('/api/auth/spouse-invitation/'.$invitation->token)->assertStatus(404);
});

it("registering from the link links the accounts, hands over Laura's figures and enters her campaign", function (): void {
    [$laura, $invitation] = inviteAzlan();

    $azlan = registerFromInvitation($invitation->token);

    expect($azlan->fresh()->spouse_id)->toBe($laura->id)
        ->and($laura->fresh()->spouse_id)->toBe($azlan->id)
        ->and($azlan->fresh()->household_id)->toBe($laura->fresh()->household_id)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull()
        ->and($invitation->fresh()->accepted_user_id)->toBe($azlan->id)
        ->and($azlan->fresh()->funnel_answers['campaign'] ?? null)->toBe('savetax')
        ->and($azlan->fresh()->onboarding_fyn_context['invited_by'] ?? null)->toBe($laura->id)
        ->and((float) $azlan->fresh()->annual_employment_income)->toBe(230000.0);
});

it('a used or mismatched invitation still registers the account, unlinked', function (): void {
    [$laura, $invitation] = inviteAzlan();
    $invitation->forceFill(['accepted_at' => now()])->save();

    $azlan = registerFromInvitation($invitation->token);

    expect($azlan->fresh()->spouse_id)->toBeNull()
        ->and($laura->fresh()->spouse_id)->toBeNull();

    // A different address registering from someone else's link is not linked.
    $laura2 = User::factory()->create(['first_name' => 'Laura', 'email' => 'laura2@example.com', 'marital_status' => 'married']);
    Sanctum::actingAs($laura2);
    $this->postJson('/api/user/family-members', ['first_name' => 'Sam', 'last_name' => 'Raj', 'relationship' => 'spouse', 'email' => 'sam@example.com'])->assertSuccessful();
    $invitation2 = SpouseInvitation::where('inviter_id', $laura2->id)->firstOrFail();
    $other = registerFromInvitation($invitation2->token, 'someone-else@example.com');
    expect($other->fresh()->spouse_id)->toBeNull()
        ->and($laura2->fresh()->spouse_id)->toBeNull()
        ->and($invitation2->fresh()->accepted_at)->toBeNull();
});

it('a phone opening the invitation link keeps it through the /m host', function (): void {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'Accept' => 'text/html'])
        ->get('/register?invite=abc123')
        ->assertRedirect('/m?to='.urlencode('/register?invite=abc123'));
    expect(RedirectPhoneToMobile::isFramableTo('/register?invite=abc123'))->toBeTrue()
        ->and(RedirectPhoneToMobile::isFramableTo('/admin'))->toBeFalse();
});
