<?php

declare(strict_types=1);

use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;

it('GateChecked carries gate, module, passed, context, microtime', function () {
    $event = new GateChecked(
        gate: 'kyc',
        module: 'protection',
        passed: true,
        context: ['field' => 'dob'],
        atMicrotime: 1234567890.123,
    );

    expect($event->gate)->toBe('kyc')
        ->and($event->module)->toBe('protection')
        ->and($event->passed)->toBeTrue()
        ->and($event->context)->toBe(['field' => 'dob'])
        ->and($event->atMicrotime)->toBe(1234567890.123);
});

it('EngineCalled carries engine, params, result, duration, microtime', function () {
    $event = new EngineCalled(
        engine: 'protection_analysis',
        params: ['user_id' => 1],
        resultSummary: ['result_path' => 'happy', 'keys_returned' => ['summary']],
        durationMs: 340,
        atMicrotime: 1234567890.456,
    );

    expect($event->engine)->toBe('protection_analysis')
        ->and($event->params)->toBe(['user_id' => 1])
        ->and($event->resultSummary['result_path'])->toBe('happy')
        ->and($event->durationMs)->toBe(340);
});

it('AgentDecision carries agent, decisionPoint, outcome, context, microtime', function () {
    $event = new AgentDecision(
        agent: 'AdviceFyn',
        decisionPoint: 'response_mode',
        outcome: 'recommendation',
        context: ['primary' => 'protection_cover'],
        atMicrotime: 1234567890.789,
    );

    expect($event->agent)->toBe('AdviceFyn')
        ->and($event->decisionPoint)->toBe('response_mode')
        ->and($event->outcome)->toBe('recommendation')
        ->and($event->context['primary'])->toBe('protection_cover');
});
