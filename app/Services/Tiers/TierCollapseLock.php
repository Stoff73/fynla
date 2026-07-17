<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use Closure;
use Illuminate\Support\Facades\DB;

class TierCollapseLock
{
    private const PREFIX = 'fynla:tier-identity-collapse';

    private const SHARD_COUNT = 64;

    public function run(Closure $callback, string $scope, int $timeoutSeconds = 15): mixed
    {
        $shardName = $this->shardName($scope);
        $shard = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$shardName, $timeoutSeconds]);

        if ((int) ($shard->acquired ?? 0) !== 1) {
            throw new \RuntimeException('Payment processing is unavailable during the tier identity cutover.');
        }

        $lockName = self::PREFIX.':scope:'.substr(hash('sha256', $scope), 0, 24);
        $result = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, $timeoutSeconds]);

        if ((int) ($result->acquired ?? 0) !== 1) {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$shardName]);

            throw new \RuntimeException('Payment processing is temporarily unavailable for this account.');
        }

        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$shardName]);
        }
    }

    public function runExclusive(Closure $callback, int $timeoutSeconds = 15): mixed
    {
        $acquired = [];

        foreach (range(0, self::SHARD_COUNT - 1) as $shard) {
            $lockName = sprintf('%s:%02d', self::PREFIX, $shard);
            $result = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, $timeoutSeconds]);

            if ((int) ($result->acquired ?? 0) !== 1) {
                foreach (array_reverse($acquired) as $heldLock) {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$heldLock]);
                }

                throw new \RuntimeException('Could not acquire the exclusive tier identity cutover lock.');
            }

            $acquired[] = $lockName;
        }

        try {
            return $callback();
        } finally {
            foreach (array_reverse($acquired) as $lockName) {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            }
        }
    }

    private function shardName(string $scope): string
    {
        $shard = ((int) sprintf('%u', crc32($scope))) % self::SHARD_COUNT;

        return sprintf('%s:%02d', self::PREFIX, $shard);
    }
}
