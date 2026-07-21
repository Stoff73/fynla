<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;
use App\Listeners\Eval\EvalTraceListener;
use App\Services\Eval\EvalTraceCollector;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class EvalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Request-scoped singleton — one collector per request lifecycle.
        $this->app->scoped(EvalTraceCollector::class, fn () => new EvalTraceCollector);
    }

    public function boot(): void
    {
        Event::listen(GateChecked::class, [EvalTraceListener::class, 'handle']);
        Event::listen(EngineCalled::class, [EvalTraceListener::class, 'handle']);
        Event::listen(AgentDecision::class, [EvalTraceListener::class, 'handle']);
    }
}
