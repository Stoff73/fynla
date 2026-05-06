<?php

declare(strict_types=1);

use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;
use App\Models\User;
use App\Services\Eval\EvalTraceCollector;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'PreviewUserSeeder']);
});

/**
 * Helper — mirrors the live request shape: real Sanctum PAT bound to the
 * auth guard so currentAccessToken() resolves during event dispatch, plus
 * the X-Eval-Run-Id header EvalBypassGate requires (F-12).
 */
function tracePersistActingAs(User $user, array $abilities): void
{
    $token = $user->createToken('eval-trace-persist-test', $abilities);
    $user->withAccessToken($token->accessToken);
    test()->actingAs($user);
    request()->headers->set('X-Eval-Run-Id', 'test-run-'.uniqid());
}

it('persists collected trace events to cache by conversation id (P0.1 fix)', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    tracePersistActingAs($user, ['bypass-preview-mode']);

    event(new GateChecked('kyc', 'global', true, [], microtime(true)));
    event(new EngineCalled('protection_analysis', [], ['result_path' => 'happy'], 100, microtime(true)));
    event(new AgentDecision('AdviceFyn', 'response_mode', 'recommendation', [], microtime(true)));

    $conversationId = 1234;
    Cache::forget("eval_trace:conversation:{$conversationId}");

    app(EvalTraceCollector::class)->persistForConversation($conversationId);

    $cached = Cache::get("eval_trace:conversation:{$conversationId}");

    expect($cached)->toBeArray()
        ->and($cached)->toHaveCount(3)
        ->and($cached[0]['event'])->toBe('GateChecked')
        ->and($cached[1]['event'])->toBe('EngineCalled')
        ->and($cached[2]['event'])->toBe('AgentDecision');
});

it('does not write to cache when collector is empty', function () {
    $conversationId = 9999;
    Cache::forget("eval_trace:conversation:{$conversationId}");

    app(EvalTraceCollector::class)->persistForConversation($conversationId);

    expect(Cache::has("eval_trace:conversation:{$conversationId}"))->toBeFalse();
});

it('cache entry expires after 10 minutes', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    tracePersistActingAs($user, ['bypass-preview-mode']);

    event(new GateChecked('kyc', 'global', true, [], microtime(true)));

    $conversationId = 7777;
    Cache::forget("eval_trace:conversation:{$conversationId}");

    app(EvalTraceCollector::class)->persistForConversation($conversationId);

    expect(Cache::has("eval_trace:conversation:{$conversationId}"))->toBeTrue();
});
