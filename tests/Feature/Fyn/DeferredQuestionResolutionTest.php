<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use App\Services\Onboarding\OnboardingChatDirector;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * CSJ raise shape (2026-07-23): the completion raise's Yes/No bubbles land on
 * the ADVICE state as plain text. Live, the bare "Yes" reached the planner
 * context-free (the stored questions had been deleted when the raise emitted)
 * and punted with the canonical no-action defer instead of answering. The
 * questions now move to `pending_deferred_answer`, and AdviceFyn::handle
 * resolves the reply deterministically.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

function deferredConversation(User $user, array $entries): AiConversation
{
    return AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'advice',
        'title' => 'Advice',
        'metadata' => ['pending_deferred_answer' => $entries],
    ]);
}

it('closes out warmly on No and clears the pending flag without a model call', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    $conversation = deferredConversation($user, [
        ['question' => 'How is my financial health?', 'topic' => 'your overall finances'],
    ]);

    $events = iterator_to_array(app(AdviceFyn::class)->handle(
        $user,
        $conversation,
        'No',
    ), false);

    $texts = collect($events)->filter(fn (array $e) => ($e['type'] ?? null) === 'content')->pluck('text')->implode(' ');
    expect($texts)->toContain('No problem')
        ->and($texts)->not->toContain('little more time');

    $conversation->refresh();
    expect($conversation->metadata['pending_deferred_answer'] ?? null)->toBeNull();
    expect($conversation->messages()->where('role', 'assistant')->count())->toBe(1);
});

it('the raise stashes the deferred questions as pending_deferred_answer instead of deleting them', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Nia',
        'onboarding_completed' => false,
        'onboarding_fyn_selection' => 'savetax',
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
        'metadata' => ['deferred_questions' => [
            ['question' => 'How is my financial health?', 'state_id' => 'base_work', 'topic' => 'your overall finances'],
        ]],
    ]);

    $method = new ReflectionMethod(OnboardingChatDirector::class, 'emitDeferredQuestions');
    $events = iterator_to_array($method->invoke(app(OnboardingChatDirector::class), $user, $conversation), false);

    $raise = collect($events)->firstWhere('type', 'quick_replies');
    expect($raise['prompt_text'] ?? '')->toContain('your Save Tax onboarding is now complete');

    $conversation->refresh();
    expect($conversation->metadata['deferred_questions'] ?? null)->toBeNull();
    expect($conversation->metadata['pending_deferred_answer'][0]['question'] ?? null)
        ->toBe('How is my financial health?');
});
