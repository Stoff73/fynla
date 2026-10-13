<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\FamilyMember;
use App\Models\SpouseInvitation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * T9 (walk fix plan B2; Q17). "Please invite my partner to link accounts.
 * Their email is …" was handed to capture as a spouse record and opened the
 * spouse form, which asks for a date of birth, and no invitation was sent.
 * An invite request reaches the invite step's own code: the details are read
 * with its parser and sent with its linking call (sendSpouseInvitation), and
 * the reply is its outcome sentence.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Mail::fake();
    Notification::fake();
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'marital_status' => 'married']);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
    $this->conversation = AiConversation::create(['user_id' => $this->user->id, 'title' => 'Chat', 'status' => 'active', 'model_used' => '']);
    FynStreamHarness::fake()->bind(); // the invitation never needs the model
});

function t9Send(AiConversation $conversation, string $message): string
{
    test()->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => $message])->assertOk()->streamedContent();

    return (string) $conversation->messages()->where('role', 'assistant')->latest('id')->value('content');
}

it('sends the invitation for a request that gives the email, using the spouse already on file', function (): void {
    FamilyMember::factory()->create(['user_id' => $this->user->id, 'relationship' => 'spouse', 'first_name' => 'Dev']);

    $reply = t9Send($this->conversation, 'Please invite my partner to link accounts. Their email is dev.walk@example.com.');

    expect($reply)->toContain('dev.walk@example.com')
        ->and($reply)->toStartWith('Done.')
        // An address with no account gets a registration invitation
        // (SpouseLinkingService::inviteUnregisteredSpouse, :452-462).
        ->and(SpouseInvitation::where('inviter_id', $this->user->id)->where('email', 'dev.walk@example.com')->value('first_name'))->toBe('Dev');
});

it('asks for the first name and email when the request has none, then sends on the reply', function (): void {
    $ask = t9Send($this->conversation, 'Please invite my partner to link accounts');

    expect($ask)->toBe("What's their first name and email address?");

    $done = t9Send($this->conversation, 'Angela, angela.walk@example.com');

    expect($done)->toContain('angela.walk@example.com')
        ->and($done)->toStartWith('Done.')
        ->and(SpouseInvitation::where('inviter_id', $this->user->id)->where('email', 'angela.walk@example.com')->value('first_name'))->toBe('Angela');
});

// Security review of #1232: Family settings allows 5 spouse invitations an
// hour per user (FamilyMembersController, W-0349); the chat path had no
// limit, so it could email any address on request. Every invite entry point
// now passes the one limit (SpouseLinkingService::guardInviteRate).

it('stops a chat invitation over the hourly limit Family settings uses', function (): void {
    foreach (range(1, 5) as $i) {
        RateLimiter::hit('spouse-invite:'.$this->user->id, 3600);
    }

    $reply = t9Send($this->conversation, 'Invite my wife Angela, angela.limit@example.com');

    expect($reply)->toBe('Too many household invitations. Please try again in an hour.')
        ->and(SpouseInvitation::where('inviter_id', $this->user->id)->exists())->toBeFalse();
});

it('never sends an invitation for a demo persona', function (): void {
    $this->user->forceFill(['is_preview_user' => true])->save();

    $reply = t9Send($this->conversation, 'Invite my wife Angela, angela.preview@example.com');

    expect($reply)->toContain('preview')
        ->and(SpouseInvitation::where('inviter_id', $this->user->id)->exists())->toBeFalse();
});

it('limits the spouse tools the same way', function (): void {
    foreach (range(1, 5) as $i) {
        RateLimiter::hit('spouse-invite:'.$this->user->id, 3600);
    }

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'relationship' => 'spouse',
        'first_name' => 'Angela',
        'email' => 'angela.tool@example.com',
    ], $this->user);

    expect($result['message'] ?? '')->toBe('Too many household invitations. Please try again in an hour.')
        ->and(SpouseInvitation::where('inviter_id', $this->user->id)->exists())->toBeFalse();
});
