<?php

declare(strict_types=1);

use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;
use App\Models\User;
use App\Services\Eval\EvalTraceCollector;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'PreviewUserSeeder']);
});

/**
 * Helper: hydrate $user with a real PersonalAccessToken carrying the given
 * abilities, then bind to the auth guard so Auth::user() + currentAccessToken()
 * resolve during event dispatch (matching what happens during a real HTTP
 * request hitting auth:sanctum).
 */
function evalTraceActingAs(\App\Models\User $user, array $abilities, bool $withEvalRunIdHeader = true): void
{
    $token = $user->createToken('eval-trace-test', $abilities);
    $user->withAccessToken($token->accessToken);
    test()->actingAs($user);
    // M21 — EvalTraceListener now routes through EvalBypassGate, which
    // requires X-Eval-Run-Id alongside the bypass-preview-mode ability.
    // Set the header on the test request so event dispatch picks it up.
    if ($withEvalRunIdHeader) {
        request()->headers->set('X-Eval-Run-Id', 'test-run-id');
    }
}

it('captures all 3 event types when token has bypass-preview-mode ability', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    evalTraceActingAs($user, ['bypass-preview-mode']);

    event(new GateChecked('kyc', 'protection', true, ['field' => 'dob'], microtime(true)));
    event(new EngineCalled('protection_analysis', [], ['result_path' => 'happy'], 100, microtime(true)));
    event(new AgentDecision('AdviceFyn', 'response_mode', 'recommendation', [], microtime(true)));

    $events = app(EvalTraceCollector::class)->all();

    expect($events)->toHaveCount(3)
        ->and($events[0]['event'])->toBe('GateChecked')
        ->and($events[0]['gate'])->toBe('kyc')
        ->and($events[1]['event'])->toBe('EngineCalled')
        ->and($events[1]['engine'])->toBe('protection_analysis')
        ->and($events[2]['event'])->toBe('AgentDecision')
        ->and($events[2]['outcome'])->toBe('recommendation');
});

it('does NOT capture when token has only the default wildcard ability', function () {
    $user = User::factory()->create();
    evalTraceActingAs($user, ['*']);

    event(new GateChecked('kyc', 'protection', true, [], microtime(true)));

    expect(app(EvalTraceCollector::class)->all())->toBeEmpty();
});

it('does NOT capture for unauthenticated requests', function () {
    // No actingAs — Auth::user() returns null. Listener short-circuits.
    event(new GateChecked('kyc', 'protection', true, [], microtime(true)));

    expect(app(EvalTraceCollector::class)->all())->toBeEmpty();
});

it('does NOT capture when bypass-preview-mode token is present without X-Eval-Run-Id (M21)', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    // Mint the right ability but skip the header — EvalBypassGate must deny.
    evalTraceActingAs($user, ['bypass-preview-mode'], withEvalRunIdHeader: false);

    event(new GateChecked('kyc', 'protection', true, [], microtime(true)));

    expect(app(EvalTraceCollector::class)->all())->toBeEmpty();
});

it('captures relative t_ms timestamps starting at 0 for the first event', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    evalTraceActingAs($user, ['bypass-preview-mode']);

    $t0 = microtime(true);
    event(new GateChecked('kyc', 'global', true, [], $t0));
    event(new GateChecked('data_readiness', 'protection', true, [], $t0 + 0.05));
    event(new EngineCalled('protection_analysis', [], ['result_path' => 'happy'], 50, $t0 + 0.1));

    $events = app(EvalTraceCollector::class)->all();

    expect($events[0]['t_ms'])->toBe(0)
        ->and($events[1]['t_ms'])->toBe(50)
        ->and($events[2]['t_ms'])->toBe(100);
});
