<?php

declare(strict_types=1);

use App\Console\Commands\EvalRecordCommand;
use Illuminate\Support\Facades\Artisan;

/**
 * Unit test for canonical 0.1 reset orchestration in EvalRecordCommand.
 *
 * Per maxAuditEval.md §5.6 + canonical 0.1: the recording session must
 * invoke `preview:reset {persona}` if and only if the captured db_writes
 * diff is non-empty, AFTER the EvalProviderRun row is persisted. Empty
 * diff -> no reset (non-mutating scenarios must never reset).
 */

beforeEach(function () {
    $this->command = new EvalRecordCommand();
    // Wire a buffered output so $this->info() does not throw.
    $output = new \Symfony\Component\Console\Output\BufferedOutput();
    $this->command->setOutput(new \Illuminate\Console\OutputStyle(
        new \Symfony\Component\Console\Input\ArrayInput([]),
        $output,
    ));
});

afterEach(function () {
    Mockery::close();
});

it('does NOT call preview:reset when db_writes is empty (non-mutating scenario)', function () {
    $spy = Mockery::spy(\Illuminate\Contracts\Console\Kernel::class);
    Artisan::swap($spy);

    $this->command->resetPersonaIfMutating([], 'peak_earners');

    $spy->shouldNotHaveReceived('call');
    expect(true)->toBeTrue();
});

it('does NOT call preview:reset when persona is empty', function () {
    $spy = Mockery::spy(\Illuminate\Contracts\Console\Kernel::class);
    Artisan::swap($spy);

    $this->command->resetPersonaIfMutating(['savings_count' => ['from' => 1, 'to' => 2]], '');

    $spy->shouldNotHaveReceived('call');
    expect(true)->toBeTrue();
});

it('calls preview:reset with the persona when db_writes is non-empty', function () {
    $spy = Mockery::spy(\Illuminate\Contracts\Console\Kernel::class);
    Artisan::swap($spy);

    $this->command->resetPersonaIfMutating(
        ['savings_count' => ['from' => 1, 'to' => 2]],
        'peak_earners',
    );

    $spy->shouldHaveReceived('call')
        ->once()
        ->with('preview:reset', ['persona' => 'peak_earners']);
    expect(true)->toBeTrue();
});
