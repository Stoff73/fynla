<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\RecommendationTracking;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\TierConfigurationSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * A dashboard recommendation that asks for information opens Fyn in a
 * recommendation-origin contextual conversation (CSJ 2026-09-09). Once the
 * capture writes, Fyn asks whether there is anything else; "No thanks" ticks
 * the recommendation off through the one completion path and sends the client
 * back to the dashboard. One home (AdviceFyn::withRecommendationFollowUp) for
 * native, /m and web.
 */
beforeEach(function (): void {
    $this->seed(TierConfigurationSeeder::class);
    config()->set('app.ai_audit_hmac_key', 'recommendation-follow-up-test-key');
});

function recommendationConversation(User $user, string $recommendationId = 'savings_child_no_savings'): int
{
    $nextActions = Mockery::mock(NextActionsService::class);
    $nextActions->shouldReceive('buildAll')->with($user->id)->andReturn([[
        'id' => $recommendationId,
        'type' => 'recommendation',
        'module' => 'savings',
        'title' => 'No Savings Recorded for Oliver',
        'detail' => 'Oliver has no savings account recorded.',
        'meta' => 'Lifecycle',
        'value' => 60.0,
        'done' => false,
        'action' => ['kind' => 'fyn_capture', 'payload' => 'savings'],
    ]]);
    app()->instance(NextActionsService::class, $nextActions);

    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($user);

    return (int) test()->postJson('/api/ai-chat/contextual-conversations', [
        'action' => 'add',
        'resource_type' => 'savings',
        'resource_id' => null,
        'current_destination' => ['screen' => 'savings', 'params' => [], 'fallback' => 'dashboard'],
        'origin' => ['kind' => 'recommendation', 'recommendation_id' => $recommendationId],
    ])->assertCreated()->json('data.conversation.id');
}

/** @return list<array<string, mixed>> */
function streamedFrames(string $content): array
{
    $frames = [];
    foreach (explode("\n\n", $content) as $chunk) {
        $chunk = trim($chunk);
        if (str_starts_with($chunk, 'data: ')) {
            $decoded = json_decode(substr($chunk, 6), true);
            if (is_array($decoded)) {
                $frames[] = $decoded;
            }
        }
    }

    return $frames;
}

it('asks whether there is anything else once a recommendation-driven capture writes', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    $conversationId = recommendationConversation($user);

    FynStreamHarness::fake()
        ->toolTurn('create_savings_account', [
            'account_name' => "Oliver's Savings",
            'account_type' => 'easy_access',
            'institution' => 'Halifax',
            'current_balance' => 500,
            'interest_rate' => 3.0,
            'ownership_type' => 'individual',
        ], 'toolu_rec_capture')
        ->textTurn("Saved Oliver's Savings.")
        ->bind();

    $content = $this->postJson("/api/ai-chat/conversations/{$conversationId}/messages", [
        'message' => "Add an individually owned Halifax easy access account named Oliver's Savings with £500 at 3%.",
    ])->assertOk()->streamedContent();

    expect(SavingsAccount::where('user_id', $user->id)->where('account_name', "Oliver's Savings")->exists())->toBeTrue();

    $frames = streamedFrames($content);
    $types = array_column($frames, 'type');
    $askedAt = array_search('quick_replies', $types, true);
    $doneAt = array_search('done', array_reverse($types, true), true);

    expect($askedAt)->not->toBeFalse()
        ->and($frames[$askedAt]['bubbles'])->toBe([['id' => 'yes', 'label' => 'Yes'], ['id' => 'no_thanks', 'label' => 'No thanks']])
        ->and($askedAt)->toBeLessThan((int) $doneAt);

    $conversation = AiConversation::findOrFail($conversationId);
    expect($conversation->metadata['recommendation_follow_up'])->toBe('asked')
        ->and($conversation->messages()->where('role', 'assistant')->latest('id')->first()->metadata['bubbles'])
        ->toBe([['id' => 'yes', 'label' => 'Yes'], ['id' => 'no_thanks', 'label' => 'No thanks']]);
});

it('does not ask when the capture turn wrote nothing', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    $conversationId = recommendationConversation($user);

    FynStreamHarness::fake()
        ->textTurn('Which bank is the account with, and what is the balance?')
        ->bind();

    $content = $this->postJson("/api/ai-chat/conversations/{$conversationId}/messages", [
        'message' => 'I want to add a savings account for Oliver.',
    ])->assertOk()->streamedContent();

    expect(array_column(streamedFrames($content), 'type'))->not->toContain('quick_replies')
        ->and(AiConversation::findOrFail($conversationId)->metadata)->not->toHaveKey('recommendation_follow_up');
});

it('ticks the recommendation off and returns to the dashboard on "No thanks"', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    $conversationId = recommendationConversation($user);
    $conversation = AiConversation::findOrFail($conversationId);
    $conversation->update(['metadata' => array_merge($conversation->metadata, ['recommendation_follow_up' => 'asked'])]);

    $content = $this->postJson("/api/ai-chat/conversations/{$conversationId}/messages", [
        'message' => 'No thanks',
    ])->assertOk()->streamedContent();

    $frames = streamedFrames($content);
    $navigation = collect($frames)->firstWhere('type', 'navigation');

    expect($navigation)->not->toBeNull()
        ->and($navigation['route_path'])->toBe('/dashboard')
        ->and(collect($frames)->firstWhere('type', 'content')['text'])->toContain('ticked "No Savings Recorded for Oliver" off');

    $tracking = RecommendationTracking::where('user_id', $user->id)->where('recommendation_id', 'savings_child_no_savings')->sole();
    expect($tracking->status)->toBe('completed')
        ->and($tracking->module)->toBe('savings')
        ->and($tracking->completed_at)->not->toBeNull()
        ->and(AiConversation::findOrFail($conversationId)->metadata)->not->toHaveKey('recommendation_follow_up');
});

it('carries on capturing on "Yes" without ticking anything off', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    $conversationId = recommendationConversation($user);
    $conversation = AiConversation::findOrFail($conversationId);
    $conversation->update(['metadata' => array_merge($conversation->metadata, ['recommendation_follow_up' => 'asked'])]);

    $content = $this->postJson("/api/ai-chat/conversations/{$conversationId}/messages", [
        'message' => 'Yes',
    ])->assertOk()->streamedContent();

    $frames = streamedFrames($content);

    expect(collect($frames)->firstWhere('type', 'content')['text'])->toBe('What would you like to add?')
        ->and(array_column($frames, 'type'))->not->toContain('navigation')
        ->and(RecommendationTracking::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(AiConversation::findOrFail($conversationId)->metadata)->not->toHaveKey('recommendation_follow_up');
});

it('leaves an ordinary conversation untouched', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($user);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test', 'title' => 'Test', 'metadata' => ['current_route' => '/dashboard']]);

    FynStreamHarness::fake()
        ->toolTurn('create_savings_account', [
            'account_name' => 'Rainy Day',
            'account_type' => 'easy_access',
            'institution' => 'Halifax',
            'current_balance' => 5000,
            'interest_rate' => 4.5,
            'ownership_type' => 'individual',
        ], 'toolu_plain')
        ->textTurn('Saved your Rainy Day account.')
        ->bind();

    $content = $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", [
        'message' => 'Add my individually owned Halifax easy access savings account named Rainy Day, with a £5,000 balance and 4.5% interest.',
    ])->assertOk()->streamedContent();

    expect(array_column(streamedFrames($content), 'type'))->not->toContain('quick_replies');
});
