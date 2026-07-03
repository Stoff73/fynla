<?php

declare(strict_types=1);

namespace App\Services\Gamification;

/**
 * WP-5c-iii — request-scoped collector of milestones minted during the
 * current request (mirrors LevelUpCollector). Lets the surface that caused
 * a mint acknowledge it in the same turn — e.g. Fyn appending one plain-text
 * sentence after a capture that crossed a threshold.
 */
class MilestoneCollector
{
    /** @var list<array{type: string, label: string}> */
    private array $minted = [];

    public function record(string $type, string $label): void
    {
        $this->minted[] = ['type' => $type, 'label' => $label];
    }

    public function hasMints(): bool
    {
        return $this->minted !== [];
    }

    /** @return array{type: string, label: string}|null */
    public function first(): ?array
    {
        return $this->minted[0] ?? null;
    }

    /** @return list<array{type: string, label: string}> */
    public function all(): array
    {
        return $this->minted;
    }
}
