<?php

declare(strict_types=1);

namespace App\Services\Gamification;

class LevelUpCollector
{
    private ?int $level = null;

    private ?string $levelName = null;

    public function record(int $level, string $levelName): void
    {
        if ($this->level === null || $level > $this->level) {
            $this->level = $level;
            $this->levelName = $levelName;
        }
    }

    public function hasLevelUp(): bool
    {
        return $this->level !== null;
    }

    /** @return array{level:int,level_name:string}|null */
    public function highest(): ?array
    {
        if ($this->level === null) {
            return null;
        }

        return ['level' => $this->level, 'level_name' => $this->levelName];
    }
}
