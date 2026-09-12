<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\FamilyMember;
use App\Models\SpousePermission;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * CSJ 2026-09-12: after the SaveTax plan, Fyn offers to invite the spouse whose
 * figures the plan used by proxy. Yes → name + email → the one linking service
 * → "Done. I've sent an invitation…" before the terminal; Not now → "No
 * problem…" before the terminal.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Mail::fake();
    Notification::fake();
});

function inviteUser(): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'marital_status' => 'married',
        'onboarding_completed' => false,
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_INVITE,
    ]);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();

    return [$user, $conversation];
}

function textsOf(array $events): string
{
    return collect($events)->map(fn (array $e) => (string) ($e['prompt_text'] ?? $e['text'] ?? ''))->filter()->implode(' | ');
}

it('sends the invitation on Yes and voices it before the terminal', function (): void {
    [$user, $conversation] = inviteUser();
    $director = app(OnboardingChatDirector::class);

    $askedFor = textsOf(iterator_to_array($director->handleUserMessage($user, $conversation, 'Yes, invite them'), false));
    expect($askedFor)->toContain("What's their first name and email address?")
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_INVITE_DETAILS);

    $final = textsOf(iterator_to_array($director->handleUserMessage($user->fresh(), $conversation, 'Angela, angela.invite@example.com'), false));

    // An address with no account gets the register-and-link invitation (W-0349).
    expect($final)->toContain("Done. I've sent an invitation to angela.invite@example.com. When Angela accepts, your plans link up")
        ->and($final)->toContain("We've created your personal tax strategy")
        ->and(FamilyMember::where('user_id', $user->id)->where('relationship', 'spouse')->where('first_name', 'Angela')->exists())->toBeTrue()
        ->and(User::where('email', 'angela.invite@example.com')->exists())->toBeFalse()
        ->and($user->fresh()->onboarding_completed)->toBeTrue()
        ->and($user->fresh()->onboarding_fyn_context['spouse_invite'] ?? null)->toBeNull();
});

it('re-asks when the reply has no email, then accepts', function (): void {
    [$user, $conversation] = inviteUser();
    $director = app(OnboardingChatDirector::class);
    iterator_to_array($director->handleUserMessage($user, $conversation, 'Yes, invite them'), false);

    $retry = textsOf(iterator_to_array($director->handleUserMessage($user->fresh(), $conversation, 'Angela'), false));
    expect($retry)->toContain('I need their first name and an email address')
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_INVITE_DETAILS);
});

it('invites an existing account and says so', function (): void {
    [$user, $conversation] = inviteUser();
    User::factory()->create(['email' => 'existing.spouse@example.com', 'first_name' => 'Angela']);
    $director = app(OnboardingChatDirector::class);
    iterator_to_array($director->handleUserMessage($user, $conversation, 'Yes, invite them'), false);

    $final = textsOf(iterator_to_array($director->handleUserMessage($user->fresh(), $conversation, 'Angela, existing.spouse@example.com'), false));

    expect($final)->toContain("Done. I've sent an invitation to existing.spouse@example.com. When Angela accepts, your plans link up")
        ->and(SpousePermission::where('user_id', $user->id)->where('status', 'pending')->exists())->toBeTrue()
        ->and($user->fresh()->onboarding_completed)->toBeTrue();
});

it('says no problem on Not now and finishes', function (): void {
    [$user, $conversation] = inviteUser();

    $final = textsOf(iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, 'Not now'), false));

    expect($final)->toContain('No problem. You can invite them any time from Family in Settings.')
        ->and($final)->toContain("We've created your personal tax strategy")
        ->and(SpousePermission::where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->fresh()->onboarding_completed)->toBeTrue();
});

it('explains a collision without pretending an invitation went out', function (): void {
    [$user, $conversation] = inviteUser();
    $thirdParty = User::factory()->create();
    User::factory()->create(['email' => 'taken@example.com', 'spouse_id' => $thirdParty->id]);
    $director = app(OnboardingChatDirector::class);
    iterator_to_array($director->handleUserMessage($user, $conversation, 'Yes, invite them'), false);

    $final = textsOf(iterator_to_array($director->handleUserMessage($user->fresh(), $conversation, 'Angela, taken@example.com'), false));

    expect($final)->toContain("already registered with another Fynla household, so I haven't sent an invitation")
        ->and(SpousePermission::where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->fresh()->onboarding_completed)->toBeTrue();
});
