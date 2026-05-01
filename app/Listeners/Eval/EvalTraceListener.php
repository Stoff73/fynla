<?php

declare(strict_types=1);

namespace App\Listeners\Eval;

use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;
use App\Services\Eval\EvalBypassGate;
use App\Services\Eval\EvalTraceCollector;
use Illuminate\Support\Facades\Auth;

/**
 * Subscribes to the 3 Eval events. Only captures when the active request
 * has both the bypass-preview-mode Sanctum ability AND an X-Eval-Run-Id
 * header. Routing via EvalBypassGate gives uniform defence-in-depth with
 * PreviewWriteInterceptor — a stolen token alone cannot trigger trace
 * capture without the harness header (M21 — F-12 closure).
 */
final class EvalTraceListener
{
    public function __construct(private readonly EvalTraceCollector $collector) {}

    public function handle(GateChecked|EngineCalled|AgentDecision $event): void
    {
        if (! EvalBypassGate::isActive(Auth::user())) {
            return;
        }

        $this->collector->record($event);
    }
}
