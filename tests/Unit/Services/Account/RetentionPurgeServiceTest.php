<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Account\RetentionPurgeService;
use App\Services\AI\Memory\FynMemoryStore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

it('every table in deletion order has a user_id column', function () {
    $svc = app(RetentionPurgeService::class);
    $reflection = new ReflectionClass($svc);
    $method = $reflection->getMethod('getDeletionOrder');
    $method->setAccessible(true);
    $order = $method->invoke($svc);

    foreach ($order as $table) {
        expect(Schema::hasTable($table))
            ->toBeTrue("table {$table} from getDeletionOrder must exist");
        expect(Schema::hasColumn($table, 'user_id'))
            ->toBeTrue("table {$table} from getDeletionOrder must have user_id column");
    }
});

it('forgets the user\'s episodic memory on purge (GDPR right to erasure, FR-M2)', function () {
    $base = sys_get_temp_dir().'/fyn-purge-mem-'.uniqid();
    config(['fyn.memory.episodic_path' => $base]);

    $user = User::factory()->create();
    $store = app(FynMemoryStore::class);
    $store->writeEpisode($user->id, 1, ['summary' => 'User wants to retire at 60.']);

    // Sanity: the episode is recallable before the purge.
    expect($store->recall($user->id))->toHaveCount(1);

    app(RetentionPurgeService::class)->purgeUser($user);

    // The user's entire episode tree is gone — recall returns nothing.
    expect($store->recall($user->id))->toBe([]);

    File::deleteDirectory($base);
});

it('erases the user\'s Phase 2 episodic blobs (hot + cold) on scheduled purge (GDPR right to erasure)', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $hotPath = 'episodic/2026/06/01/'.$conversation->id.'/hotmsg.md';
    $coldPath = 'episodic/2026/05/01/'.$conversation->id.'/coldmsg.md';
    $coldActual = str_replace('episodic/', 'episodic-cold/', $coldPath);

    // Hot blob lives at the addressed path; cold blob lives under episodic-cold/.
    Storage::disk('local')->put($hotPath, '# system prompt + assembled context (PII)');
    Storage::disk('local')->put($coldActual, '# system prompt + assembled context (PII)');

    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'blob_md_path' => $hotPath,
    ]);
    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'blob_md_path' => $coldPath,
    ]);

    // Sanity: both blobs exist before the purge.
    expect(Storage::disk('local')->exists($hotPath))->toBeTrue()
        ->and(Storage::disk('local')->exists($coldActual))->toBeTrue();

    app(RetentionPurgeService::class)->purgeUser($user);

    // Both hot and cold forensic blobs are gone after the scheduled purge.
    expect(Storage::disk('local')->exists($hotPath))->toBeFalse()
        ->and(Storage::disk('local')->exists($coldActual))->toBeFalse();
});
