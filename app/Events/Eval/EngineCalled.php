<?php

declare(strict_types=1);

namespace App\Events\Eval;

final class EngineCalled
{
    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $resultSummary
     */
    public function __construct(
        public readonly string $engine,
        public readonly array $params,
        public readonly array $resultSummary,
        public readonly int $durationMs,
        public readonly float $atMicrotime,
    ) {}
}
