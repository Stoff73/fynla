<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AiToolDefinitions;
use App\Services\AI\FynPersonaInvoker;
use App\Services\AI\FynPersonaRegistry;
use App\Services\AI\XaiToolDefinitions;
use App\ValueObjects\CaptureContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers FynPersonaInvoker's persona-turn responsibilities.
 *
 *   - Handoff SSE events are intercepted (never leak to frontend) and
 *     captured via lastHandoff().
 *   - Internal handoff tool_use events are stripped from the SSE stream.
 *   - B-9 guardrail: data_capture persona content is truncated to a
 *     single ≤25-word sentence. Advice persona content passes through
 *     unchanged.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('intercepts handoff SSE events and exposes them via lastHandoff', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Test',
    ]);

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () {
            yield [
                'type' => 'handoff',
                'handoff_type' => 'delegate_to_capture',
                'payload' => ['reason' => 'need SIPP data'],
            ];
            yield ['type' => 'done'];
        });

    $invoker = new FynPersonaInvoker(
        app(FynPersonaRegistry::class),
        app(AiToolDefinitions::class),
        app(XaiToolDefinitions::class),
        $mock,
        app(\App\Services\Onboarding\AssetCaptureEntityExtractor::class),
    );

    $received = [];
    foreach ($invoker->invoke(
        FynPersonaRegistry::PERSONA_ADVICE,
        $user,
        $conversation,
        'What should I do about my SIPP?'
    ) as $event) {
        $received[] = $event;
    }

    // Handoff event NEVER reaches the downstream consumer.
    expect(collect($received)->pluck('type')->toArray())->not->toContain('handoff');

    // …but lastHandoff() returns the captured payload.
    expect($invoker->lastHandoff())->toBe([
        'handoff_type' => 'delegate_to_capture',
        'payload' => ['reason' => 'need SIPP data'],
    ]);
});

it('truncates multi-paragraph data_capture acknowledgments to one sentence', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Test',
    ]);

    // Simulate a misbehaving LLM emitting advice-shaped prose on a capture
    // turn. Each delta mimics the streaming token granularity.
    $misbehavingStream = [
        ['type' => 'content', 'text' => 'Got it — recorded your SIPP.'],
        ['type' => 'content', 'text' => ' Based on your current pension'],
        ['type' => 'content', 'text' => ' contributions you should consider'],
        ['type' => 'content', 'text' => ' increasing them by 2% to maximise'],
        ['type' => 'content', 'text' => ' your annual allowance and employer match.'],
        ['type' => 'content', 'text' => "\n\n## Next steps\n- Review your allocation\n- Set up auto-escalation"],
        ['type' => 'done'],
    ];

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($misbehavingStream) {
            foreach ($misbehavingStream as $event) {
                yield $event;
            }
        });

    $invoker = new FynPersonaInvoker(
        app(FynPersonaRegistry::class),
        app(AiToolDefinitions::class),
        app(XaiToolDefinitions::class),
        $mock,
        app(\App\Services\Onboarding\AssetCaptureEntityExtractor::class),
    );

    $received = [];
    foreach ($invoker->invoke(
        FynPersonaRegistry::PERSONA_DATA_CAPTURE,
        $user,
        $conversation,
        'I have a Scottish Widows SIPP worth £50k',
        captureContext: new CaptureContext(
            reason: 'SIPP capture',
            entityTypes: ['pension'],
        ),
    ) as $event) {
        $received[] = $event;
    }

    $content = array_values(array_filter($received, fn (array $e): bool => ($e['type'] ?? null) === 'content'));

    // Exactly ONE content event emitted (post-truncation), not 6.
    expect($content)->toHaveCount(1);

    $text = (string) ($content[0]['text'] ?? '');

    // First sentence preserved.
    expect($text)->toBe('Got it — recorded your SIPP.');

    // Advice-shaped prose, markdown headers, bullet points — all stripped.
    expect($text)->not->toContain('contributions');
    expect($text)->not->toContain('Next steps');
    expect($text)->not->toContain('auto-escalation');
    expect($text)->not->toContain('##');
});

it('leaves advice persona content completely unchanged', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Test',
    ]);

    // Advice Fyn is supposed to produce rich, multi-paragraph prose. The
    // capture truncator MUST NOT touch these events.
    $adviceStream = [
        ['type' => 'content', 'text' => "Based on your pension contributions and the current "],
        ['type' => 'content', 'text' => "tax year allowance, you have headroom of £18,000 remaining. "],
        ['type' => 'content', 'text' => "\n\nIncreasing your monthly contribution by £200 would use "],
        ['type' => 'content', 'text' => "most of that allowance before 5 April."],
        ['type' => 'done'],
    ];

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($adviceStream) {
            foreach ($adviceStream as $event) {
                yield $event;
            }
        });

    $invoker = new FynPersonaInvoker(
        app(FynPersonaRegistry::class),
        app(AiToolDefinitions::class),
        app(XaiToolDefinitions::class),
        $mock,
        app(\App\Services\Onboarding\AssetCaptureEntityExtractor::class),
    );

    $received = [];
    foreach ($invoker->invoke(
        FynPersonaRegistry::PERSONA_ADVICE,
        $user,
        $conversation,
        'How much pension allowance do I have left?'
    ) as $event) {
        $received[] = $event;
    }

    $contentEvents = array_values(array_filter($received, fn (array $e): bool => ($e['type'] ?? null) === 'content'));

    // All four content deltas pass through intact — advice Fyn is not truncated.
    expect($contentEvents)->toHaveCount(4);

    $joined = implode('', array_column($contentEvents, 'text'));
    expect($joined)->toContain('pension contributions');
    expect($joined)->toContain('£18,000');
    expect($joined)->toContain('5 April');
});

it('caps a single-sentence capture ack at 25 words with an ellipsis', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Test',
    ]);

    // 40-word single sentence, no terminator — should be capped.
    $longAck = str_repeat('word ', 40);

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($longAck) {
            yield ['type' => 'content', 'text' => $longAck];
            yield ['type' => 'done'];
        });

    $invoker = new FynPersonaInvoker(
        app(FynPersonaRegistry::class),
        app(AiToolDefinitions::class),
        app(XaiToolDefinitions::class),
        $mock,
        app(\App\Services\Onboarding\AssetCaptureEntityExtractor::class),
    );

    $received = [];
    foreach ($invoker->invoke(
        FynPersonaRegistry::PERSONA_DATA_CAPTURE,
        $user,
        $conversation,
        'capture stuff',
        captureContext: new CaptureContext(reason: 'r', entityTypes: ['pension']),
    ) as $event) {
        $received[] = $event;
    }

    $content = collect($received)->firstWhere('type', 'content');
    $words = preg_split('/\s+/u', trim((string) ($content['text'] ?? '')));

    // 25 content words + trailing ellipsis (attached to the last word, so
    // explode-by-whitespace returns 25 tokens).
    expect(count($words))->toBeLessThanOrEqual(26);
    expect($content['text'])->toEndWith('…');
});
