<?php

declare(strict_types=1);

namespace App\Listeners\Eval;

use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;
use App\Services\Eval\EvalTraceCollector;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Subscribes to the 3 Eval events. Only captures when the active
 * Sanctum token EXPLICITLY lists `bypass-preview-mode` — same security
 * semantics as PreviewWriteInterceptor, so non-eval requests pay zero
 * overhead beyond an early-return.
 */
final class EvalTraceListener
{
    public function __construct(private readonly EvalTraceCollector $collector) {}

    public function handle(GateChecked|EngineCalled|AgentDecision $event): void
    {
        if (! $this->shouldCapture()) {
            return;
        }

        $this->collector->record($event);
    }

    private function shouldCapture(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        if (! $token instanceof PersonalAccessToken) {
            return false;
        }

        return in_array('bypass-preview-mode', $token->abilities ?? [], true);
    }
}
