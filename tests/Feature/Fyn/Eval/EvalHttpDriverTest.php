<?php

declare(strict_types=1);

use App\Services\Eval\EvalHttpDriver;
use App\Services\Eval\EvalSseConsumer;

beforeEach(function () {
    // Skip — this is a true end-to-end integration test that requires
    // ./dev.sh running on localhost:8000 with a live Anthropic key. The
    // unit-test database is laravel_testing (RefreshDatabase) but the
    // server hits the local dev DB. Wired up here as documentation;
    // run manually via `php artisan tinker` per implementation plan §10.4.
    $this->markTestSkipped(
        'EvalHttpDriverTest is a live integration test — drive manually '.
        'with `./dev.sh` running and a real provider key configured. '.
        'See implementation plan Task 10.4 for the tinker invocation.'
    );
});

it('drives a full login → create conversation → send message → consume SSE → logout cycle', function () {
    $driver = new EvalHttpDriver(new EvalSseConsumer);

    $result = $driver->run(
        scenario: [
            'persona' => 'peak_earners',
            'is_mutating' => false,
            'input' => [
                'turns' => [
                    ['user' => 'What is our combined net worth?', 'current_route' => null],
                ],
            ],
        ],
        provider: 'anthropic',
        model: 'claude-haiku-4-5-20251001',
        baseUrl: 'http://localhost:8000',
    );

    expect($result['events'])->not->toBeEmpty()
        ->and($result['http_log'])->toHaveCount(4) // login, create conv, send msg, logout (trace via cache, no HTTP)
        ->and($result['http_log'][0]['url'])->toContain('/api/eval/login/peak_earners')
        ->and($result['http_log'][0]['status'])->toBe(200)
        ->and($result['engine_trace'])->not->toBeEmpty();

    $eventTypes = collect($result['events'])->pluck('type')->unique()->all();
    expect($eventTypes)->toContain('done');
});
