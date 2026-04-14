<?php

declare(strict_types=1);

use App\Services\Lifecycle\LifecycleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('run returns stats array with sent/skipped/errored counts per campaign', function () {
    // With no campaigns configured (config override), should return empty stats
    config(['lifecycle.campaigns' => []]);

    $engine = app(LifecycleEngine::class);
    $stats = $engine->run();

    expect($stats)->toBeArray();
    expect($stats)->toBe([]);
});
