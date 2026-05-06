<?php

declare(strict_types=1);

namespace App\Events\Eval;

final class GateChecked
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $gate,
        public readonly string $module,
        public readonly bool $passed,
        public readonly array $context,
        public readonly float $atMicrotime,
    ) {}
}
