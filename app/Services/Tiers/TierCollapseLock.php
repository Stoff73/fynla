<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use Closure;
use Illuminate\Support\Facades\DB;

class TierCollapseLock
{
    private const NAME = 'fynla:tier-identity-collapse';

    public function run(Closure $callback, int $timeoutSeconds = 15): mixed
    {
        $result = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [self::NAME, $timeoutSeconds]);

        if ((int) ($result->acquired ?? 0) !== 1) {
            throw new \RuntimeException('Payment processing is temporarily unavailable during the tier identity cutover.');
        }

        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [self::NAME]);
        }
    }
}
