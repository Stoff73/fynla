<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

final class ThresholdResult
{
    /**
     * @param  array{from: float, to: ?float}|null  $range
     * @param  array{value: float, distance: float, unit: string, over: bool}  $position
     * @param  array{title: string, amount: float, recovers: float, downside: string, action: array{route: string}}|null  $lever
     * @param  array<string, float>  $incomeMix
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly ?array $range,
        public readonly array $position,
        public readonly string $headline,
        public readonly string $body,
        public readonly string $explanation,
        public readonly ?ThresholdCost $cost,
        public readonly ?array $lever,
        public readonly array $incomeMix = [],
    ) {}

    public function isDateLine(): bool
    {
        return $this->position['unit'] === 'days';
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'range' => $this->range,
            'position' => $this->position,
            'headline' => $this->headline,
            'body' => $this->body,
            'explanation' => $this->explanation,
            'income_mix' => $this->incomeMix,
            'cost' => $this->cost?->toArray(),
            'cost_total' => $this->cost?->total() ?? 0.0,
            'lever' => $this->lever,
        ];
    }
}
