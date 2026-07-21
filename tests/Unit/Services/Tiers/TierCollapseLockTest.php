<?php

declare(strict_types=1);

use App\Services\Tiers\TierCollapseLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('rejects a scoped payment operation while the exclusive cutover lock is held', function () {
    $connectionName = 'tier_cutover_test';
    config(["database.connections.{$connectionName}" => config('database.connections.'.config('database.default'))]);
    $cutoverConnection = DB::connection($connectionName);
    foreach (range(0, 63) as $shard) {
        $cutoverConnection->selectOne(
            'SELECT GET_LOCK(?, 1) AS acquired',
            [sprintf('fynla:tier-identity-collapse:%02d', $shard)],
        );
    }

    try {
        expect(fn () => app(TierCollapseLock::class)->run(fn () => true, 'user:123', 0))
            ->toThrow(RuntimeException::class, 'tier identity cutover');
    } finally {
        foreach (array_reverse(range(0, 63)) as $shard) {
            $cutoverConnection->selectOne(
                'SELECT RELEASE_LOCK(?) AS released',
                [sprintf('fynla:tier-identity-collapse:%02d', $shard)],
            );
        }
        DB::purge($connectionName);
    }
});

it('cannot acquire the exclusive cutover while a scoped mutation owns its shard', function () {
    $scope = 'user:456';
    $shard = sprintf('fynla:tier-identity-collapse:%02d', abs(crc32($scope)) % 64);
    $connectionName = 'tier_runtime_test';
    config(["database.connections.{$connectionName}" => config('database.connections.'.config('database.default'))]);
    $runtimeConnection = DB::connection($connectionName);
    $runtimeConnection->selectOne('SELECT GET_LOCK(?, 1) AS acquired', [$shard]);

    try {
        expect(fn () => app(TierCollapseLock::class)->runExclusive(fn () => true, 0))
            ->toThrow(RuntimeException::class, 'exclusive tier identity cutover');
    } finally {
        $runtimeConnection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$shard]);
        DB::purge($connectionName);
    }
});
