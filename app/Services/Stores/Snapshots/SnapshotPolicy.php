<?php

declare(strict_types=1);

namespace App\Services\Stores\Snapshots;

use Closure;

class SnapshotPolicy
{
    public function __construct(
        public readonly Closure $triggerPredicate,
        public readonly int $retentionDays,
        /** @var array{free:int, tier1:int, tier2:int, tier3:int} */
        public readonly array $surfacingWindowDays,
        public readonly int $maxRowsHardCap,
        public readonly string $recalcCadence,
    ) {}

    public function shouldSnapshot(float|int|null $old, float|int|null $new): bool
    {
        if ($old === null) {
            return true;
        }

        return ($this->triggerPredicate)($old, $new);
    }

    public function surfacingWindow(string $tier): int
    {
        return $this->surfacingWindowDays[$tier] ?? $this->surfacingWindowDays['free'];
    }
}
