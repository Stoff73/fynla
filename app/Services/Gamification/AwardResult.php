<?php

declare(strict_types=1);

namespace App\Services\Gamification;

final readonly class AwardResult
{
    public function __construct(
        public bool $awarded,
        public int $points,
        public bool $leveledUp,
        public int $newLevel,
        public string $newLevelName,
    ) {}

    public static function noop(): self
    {
        return new self(false, 0, false, 1, 'Starter');
    }
}
