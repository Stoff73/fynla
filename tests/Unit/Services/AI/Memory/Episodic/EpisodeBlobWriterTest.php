<?php

declare(strict_types=1);

use App\Models\AiMessage;
use App\Services\AI\Memory\Episodic\EpisodeBlobData;
use App\Services\AI\Memory\Episodic\EpisodeBlobLocator;
use App\Services\AI\Memory\Episodic\EpisodeBlobWriter;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function blobData(AiMessage $m): EpisodeBlobData
{
    return new EpisodeBlobData(
        episodeId: (string) $m->id, conversationId: $m->conversation_id, clientId: 7,
        timestamp: '2026-06-01T10:00:00Z', persona: 'advice', module: 'retirement',
        proceduralVersion: null, semanticSnapshotId: null, modelUsed: 'grok-4',
        systemPrompt: 'SYS', assembledContext: 'CTX', reasoningTrace: null, toolCalls: null, toolResults: null,
    );
}

it('writes the blob atomically and returns a ref with the correct sha + sharded path', function (): void {
    $msg = AiMessage::factory()->create();
    $ref = app(EpisodeBlobWriter::class)->write($msg, blobData($msg));

    expect($ref->path)->toContain('episodic/2026/06/01/')
        ->and($ref->path)->toEndWith("/{$msg->id}.md")
        ->and(Storage::disk('local')->exists($ref->path))->toBeTrue();

    $body = Storage::disk('local')->get($ref->path);
    expect($ref->sha256)->toBe(hash('sha256', $body))
        ->and($body)->toContain('## system_prompt');

    expect(Storage::disk('local')->exists($ref->path.'.tmp'))->toBeFalse();
});

it('locator resolves a written blob and returns null for a missing one', function (): void {
    $msg = AiMessage::factory()->create();
    $ref = app(EpisodeBlobWriter::class)->write($msg, blobData($msg));
    $locator = app(EpisodeBlobLocator::class);

    expect($locator->resolve($ref->path))->not->toBeNull()
        ->and($locator->resolve('episodic/2026/06/01/999/999.md'))->toBeNull();
});
