<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Memory\Episodic\EpisodeBlobData;
use App\Services\AI\Memory\Episodic\EpisodeBlobWriter;
use App\Services\AI\Memory\Episodic\EpisodeProjection;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('lists episodes with SQL-only summary fields', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant', 'model_used' => 'grok-4', 'persona' => 'advice', 'tool_calls' => [['name' => 'x']]]);

    $list = app(EpisodeProjection::class)->list($user->id, 10, null);

    expect($list)->toHaveCount(1)
        ->and($list[0]['model_used'])->toBe('grok-4')
        ->and($list[0]['tool_count'])->toBe(1)
        ->and($list[0])->toHaveKeys(['id', 'created_at', 'persona', 'has_blob']);
});

it('detail lazy-loads the blob body', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $msg = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant']);
    $data = new EpisodeBlobData((string) $msg->id, $conv->id, $user->id, '2026-06-01T10:00:00Z', 'advice', null, null, null, 'grok-4', 'SYSPROMPT', 'CTX', null, null, null);
    $ref = app(EpisodeBlobWriter::class)->write($msg, $data);
    $msg->update(['blob_md_path' => $ref->path, 'blob_md_sha256' => $ref->sha256]);

    $detail = app(EpisodeProjection::class)->detail($msg->id);

    expect($detail['id'])->toBe($msg->id)
        ->and($detail['blob_body'])->toContain('SYSPROMPT');
});

it('detail returns null blob_body when no blob exists', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $msg = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant', 'blob_md_path' => null]);
    expect(app(EpisodeProjection::class)->detail($msg->id)['blob_body'])->toBeNull();
});
