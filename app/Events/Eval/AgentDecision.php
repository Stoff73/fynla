<?php

declare(strict_types=1);

namespace App\Events\Eval;

final class AgentDecision
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $agent,
        public readonly string $decisionPoint,
        public readonly string $outcome,
        public readonly array $context,
        public readonly float $atMicrotime,
    ) {}
}
