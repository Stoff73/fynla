<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Memory\Episodic\EpisodeRetriever;

it('returns a user\'s assistant episodes newest-first, limited, SQL only', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    foreach (range(1, 3) as $i) {
        AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant', 'blob_md_path' => "episodic/p{$i}.md"]);
    }
    $other = AiConversation::factory()->create();
    AiMessage::factory()->create(['conversation_id' => $other->id, 'role' => 'assistant']);

    $episodes = app(EpisodeRetriever::class)->findEpisodes($user->id, 2, null);

    expect($episodes)->toHaveCount(2)
        ->and($episodes->first()->conversation->user_id)->toBe($user->id);
});

it('excludes non-assistant messages from results', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'user']);
    AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant']);

    $episodes = app(EpisodeRetriever::class)->findEpisodes($user->id, 20, null);

    expect($episodes)->toHaveCount(1)
        ->and($episodes->first()->role)->toBe('assistant');
});

it('filters by since timestamp when provided', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant', 'created_at' => now()->subDays(10)]);
    AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant', 'created_at' => now()->subDay()]);

    $episodes = app(EpisodeRetriever::class)->findEpisodes($user->id, 20, now()->subDays(3));

    expect($episodes)->toHaveCount(1);
});

it('returns newest first by id descending', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $first = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant']);
    $second = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant']);

    $episodes = app(EpisodeRetriever::class)->findEpisodes($user->id, 20, null);

    expect($episodes->first()->id)->toBe($second->id);
});
