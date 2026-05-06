<?php

declare(strict_types=1);

namespace Tests\Feature\Fyn\Eval;

use Illuminate\Support\Facades\Storage;

/**
 * Aggregates a list of EvalResult objects and renders a per-tool scorecard
 * to storage/eval-scorecards/YYYY-MM-DD-{mode}.md per fyn-rubrics.md §B
 * "Escalation path".
 */
final class EvalReport
{
    /** @var list<EvalResult> */
    private array $results = [];

    public function __construct(public readonly string $mode) {}

    public function add(EvalResult $result): void
    {
        $this->results[] = $result;
    }

    /**
     * @return list<EvalResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function scenarioCount(): int
    {
        return count($this->results);
    }

    public function passCount(): int
    {
        return count(array_filter($this->results, fn (EvalResult $r): bool => $r->passed()));
    }

    public function failCount(): int
    {
        return $this->scenarioCount() - $this->passCount();
    }

    /**
     * Render the markdown scorecard. Returns the rendered string; pass
     * persist=true to also write it to storage/eval-scorecards/.
     */
    public function render(bool $persist = false): string
    {
        $date = date('Y-m-d');
        $lines = [];
        $lines[] = "# Fyn Eval Scorecard — {$date}";
        $lines[] = '';
        $lines[] = "**Mode:** {$this->mode}";
        $lines[] = "**Scenarios:** {$this->passCount()} / {$this->scenarioCount()} passed";
        $lines[] = '';

        if ($this->results === []) {
            $lines[] = '_No scenarios executed._';
        } else {
            $lines[] = '| Scenario | Result | Failed metrics |';
            $lines[] = '|---|---|---|';
            foreach ($this->results as $result) {
                $status = $result->passed() ? 'PASS' : 'FAIL';
                $failed = [];
                foreach ($result->metricResults as $metric => $passed) {
                    if (! $passed) {
                        $failed[] = $metric;
                    }
                }
                $failedCol = $failed === [] ? '—' : implode(', ', $failed);
                $lines[] = "| {$result->scenarioId} | {$status} | {$failedCol} |";
            }
        }

        $lines[] = '';
        $output = implode("\n", $lines)."\n";

        if ($persist) {
            $relative = "eval-scorecards/{$date}-{$this->mode}.md";
            Storage::disk('local')->put($relative, $output);
        }

        return $output;
    }
}
