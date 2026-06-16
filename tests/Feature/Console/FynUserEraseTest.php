<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Memory\UserSemanticStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');

    // UserSemanticStore writes via the File facade to an absolute path, which
    // Storage::fake does NOT intercept — point it at a per-test temp dir so the
    // semantic-store cases never touch real storage/app/memory/semantic-user.
    $this->semanticDir = storage_path('app/test-user-semantic-'.uniqid());
    config(['fyn.memory.user_semantic_path' => $this->semanticDir]);
});

afterEach(fn () => File::deleteDirectory($this->semanticDir));

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

it('dry-run leaves per-user semantic facts intact', function (): void {
    $user = User::factory()->create();
    $store = app(UserSemanticStore::class);
    $store->put($user->id, 'test-fact', 'Test', 'Body');

    $this->artisan('fyn:user:erase', ['user' => $user->id])->assertExitCode(0);

    expect($store->forUser($user->id))->toHaveCount(1);
});

it('force erases per-user semantic facts', function (): void {
    $user = User::factory()->create();
    $store = app(UserSemanticStore::class);
    $store->put($user->id, 'test-fact', 'Test', 'Body');

    $this->artisan('fyn:user:erase', ['user' => $user->id, '--force' => true])->assertExitCode(0);

    expect($store->forUser($user->id))->toBe([]);
});
