<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Account\RetentionPurgeService;
use App\Services\AI\Memory\FynMemoryStore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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
