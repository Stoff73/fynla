<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * csjones user 405, 2026-09-16: a cap-refused "A workplace pension with Nest
 * worth about £45,000" was merged into the next reply "No, they don't have any
 * pensions of their own" as its original capture details, and the model
 * recorded the Nest pension. A completion declaration is never a clarification.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

function mergeFor(AiConversation $conversation, string $message): string
{
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'mergeUnresolvedCaptureMessage');
    $method->setAccessible(true);

    return $method->invoke($director, $conversation, $message);
}

it('does not merge a refused attempt into a reply that says no', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
    $conversation->messages()->create(['role' => 'user', 'content' => 'A workplace pension with Nest worth about £45,000']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Want to upgrade or replace one?', 'metadata' => ['turn_intent' => 'capture_clarification', 'capture_write_failed' => true]]);
    $conversation->messages()->create(['role' => 'user', 'content' => "No, they don't have any pensions of their own"]);

    expect(mergeFor($conversation, "No, they don't have any pensions of their own"))->toBe("No, they don't have any pensions of their own");
});

it('still merges a genuine clarification into the refused attempt', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
    $conversation->messages()->create(['role' => 'user', 'content' => 'A Halifax savings account with £4,000']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Is this in your name only, or do you share it with someone else?', 'metadata' => ['turn_intent' => 'capture_clarification', 'capture_write_failed' => true]]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Just mine']);

    expect(mergeFor($conversation, 'Just mine'))->not->toBe('Just mine');
});
