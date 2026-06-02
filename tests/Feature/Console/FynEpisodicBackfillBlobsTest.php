<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Memory\Episodic\EpisodeProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

it('backfills a blob for a legacy row and is idempotent + retrievable', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $msg = AiMessage::factory()->create([
        'conversation_id' => $conv->id, 'role' => 'assistant',
        'system_prompt' => 'SYS', 'assembled_context' => 'CTX', 'blob_md_path' => null,
    ]);

    $this->artisan('fyn:episodic:backfill-blobs')->assertExitCode(0);

    $msg->refresh();
    expect($msg->blob_md_path)->not->toBeNull()
        ->and($msg->blob_md_sha256)->toHaveLength(64)
        ->and(Storage::disk('local')->exists($msg->blob_md_path))->toBeTrue();
    $firstPath = $msg->blob_md_path;

    // idempotent: second run skips
    $this->artisan('fyn:episodic:backfill-blobs')->assertExitCode(0);
    expect($msg->fresh()->blob_md_path)->toBe($firstPath);

    // retrievable as a first-class episode (CSJ accessibility guarantee)
    expect(app(EpisodeProjection::class)->detail($msg->id)['blob_body'])->toContain('SYS');
});

it('skips rows that have neither system_prompt nor assembled_context', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $msg = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'user', 'system_prompt' => null, 'assembled_context' => null, 'blob_md_path' => null]);

    $this->artisan('fyn:episodic:backfill-blobs')->assertExitCode(0);
    expect($msg->fresh()->blob_md_path)->toBeNull();
});
