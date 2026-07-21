<?php

declare(strict_types=1);

namespace Tests\Feature\Fyn\Eval;

final class EvalResult
{
    /**
     * @param  array<string, bool>  $metricResults  metric name => passed
     * @param  array<string, string>  $metricMessages  metric name => failure detail (only set when failed)
     */
    public function __construct(
        public readonly string $scenarioId,
        public readonly string $mode,
        public readonly array $metricResults,
        public readonly array $metricMessages = [],
        public readonly ?float $durationMs = null,
    ) {}

    public function passed(): bool
    {
        foreach ($this->metricResults as $passed) {
            if (! $passed) {
                return false;
            }
        }

        return true;
    }

    public function failureSummary(): string
    {
        $failed = [];
        foreach ($this->metricResults as $metric => $passed) {
            if (! $passed) {
                $detail = $this->metricMessages[$metric] ?? '(no detail)';
                $failed[] = "{$metric}: {$detail}";
            }
        }

        if ($failed === []) {
            return '';
        }

        return "Scenario {$this->scenarioId} failed: ".implode('; ', $failed);
    }
}
