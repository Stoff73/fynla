<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

it('dry-run erases nothing', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $msg = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant', 'blob_md_path' => 'episodic/2026/06/01/1/1.md']);
    Storage::disk('local')->put('episodic/2026/06/01/1/1.md', 'A');

    $this->artisan('fyn:user:erase', ['user' => $user->id])->assertExitCode(0);

    expect(AiMessage::find($msg->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists('episodic/2026/06/01/1/1.md'))->toBeTrue();
});

it('erases a user\'s ai_messages rows and their hot + cold blobs with --force', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $hot = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant', 'blob_md_path' => 'episodic/2026/06/01/1/1.md']);
    $cold = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant', 'blob_md_path' => 'episodic/2025/01/01/1/2.md']);
    Storage::disk('local')->put('episodic/2026/06/01/1/1.md', 'A');
    Storage::disk('local')->put('episodic-cold/2025/01/01/1/2.md', 'B'); // cold variant

    // a different user's data must survive
    $other = User::factory()->create();
    $otherConv = AiConversation::factory()->create(['user_id' => $other->id]);
    $otherMsg = AiMessage::factory()->create(['conversation_id' => $otherConv->id, 'role' => 'assistant', 'blob_md_path' => 'episodic/2026/06/01/9/9.md']);
    Storage::disk('local')->put('episodic/2026/06/01/9/9.md', 'C');

    $this->artisan('fyn:user:erase', ['user' => $user->id, '--force' => true])->assertExitCode(0);

    expect(AiMessage::whereIn('id', [$hot->id, $cold->id])->count())->toBe(0)
        ->and(Storage::disk('local')->exists('episodic/2026/06/01/1/1.md'))->toBeFalse()
        ->and(Storage::disk('local')->exists('episodic-cold/2025/01/01/1/2.md'))->toBeFalse()
        // other user's data intact
        ->and(AiMessage::find($otherMsg->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists('episodic/2026/06/01/9/9.md'))->toBeTrue();
});
