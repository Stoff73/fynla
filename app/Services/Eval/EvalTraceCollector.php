<?php

declare(strict_types=1);

namespace App\Services\Eval;

use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;

/**
 * Request-scoped collector that captures GateChecked / EngineCalled /
 * AgentDecision events fired during a chat send.
 *
 * One instance per request lifecycle (registered via $app->scoped() in
 * EvalServiceProvider). The /api/eval/trace/{conversationId} endpoint
 * reads ::all() and returns the timeline to the EvalHttpDriver.
 */
final class EvalTraceCollector
{
    /** @var list<array<string, mixed>> */
    private array $events = [];

    private ?float $startMicrotime = null;

    public function record(GateChecked|EngineCalled|AgentDecision $event): void
    {
        $this->startMicrotime ??= $event->atMicrotime;
        // round() (not floor cast) so 0.05s renders as 50ms, not 49 — float
        // multiplication on small offsets routinely lands one ULP below the
        // integer.
        $tMs = (int) round(($event->atMicrotime - $this->startMicrotime) * 1000);

        $this->events[] = match (true) {
            $event instanceof GateChecked => [
                'event' => 'GateChecked',
                't_ms' => $tMs,
                'gate' => $event->gate,
                'module' => $event->module,
                'passed' => $event->passed,
                'context' => $event->context,
            ],
            $event instanceof EngineCalled => [
                'event' => 'EngineCalled',
                't_ms' => $tMs,
                'engine' => $event->engine,
                'params' => $event->params,
                'resultSummary' => $event->resultSummary,
                'durationMs' => $event->durationMs,
            ],
            $event instanceof AgentDecision => [
                'event' => 'AgentDecision',
                't_ms' => $tMs,
                'agent' => $event->agent,
                'decisionPoint' => $event->decisionPoint,
                'outcome' => $event->outcome,
                'context' => $event->context,
            ],
        };
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->events;
    }

    public function reset(): void
    {
        $this->events = [];
        $this->startMicrotime = null;
    }
}
