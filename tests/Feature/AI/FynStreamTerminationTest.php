<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\TaxConfiguration;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AI\XaiClient;
use App\Services\GDPR\ConsentService;
use App\Services\TaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Fyn\ScriptedAnthropicClient;
use Tests\Support\Fyn\Sse;

uses(RefreshDatabase::class);

// L3-2 (fynla.org /m, 29 Sep 2026, conversation 940 — docs/testing/
// 2026-09-29-savetax-scenario-matrix.md): the first question in a new advice
// conversation streamed "I'll fetch the latest tax information... Let me pull
// the full details..." and then the stream ended with no answer and no error.
// Three holes let a tool-calling turn end that way; each is pinned here.

/** A tool that throws a PHP \Error, which `catch (\Exception)` never caught. */
final class ThrowingToolAgent extends CoordinatingAgent
{
    public function executeTool(
        string $toolName,
        array $input,
        User $user,
        ?int $conversationId = null,
        ?array $classification = null,
        ?array $kycResult = null,
        ?string $evidenceOverride = null,
        ?array $confirmedFacts = null,
    ): array {
        throw new TypeError('Unsupported operand types: string * float');
    }
}

/** Replays scripted xAI turns and records every request it was sent. */
final class TerminationScriptedXaiClient
{
    /** @var list<array<string, mixed>> */
    public array $requests = [];

    /** @param  list<list<object>>  $turns */
    public function __construct(private array $turns) {}

    public function forConversation(int|string $conversationId): self
    {
        return $this;
    }

    public function chat(): object
    {
        $client = $this;

        return new class($client)
        {
            public function __construct(private readonly TerminationScriptedXaiClient $client) {}

            /** @param  array<string, mixed>  $params */
            public function createStreamed(array $params): Generator
            {
                yield from $this->client->nextTurn($params);
            }
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<object>
     */
    public function nextTurn(array $params): array
    {
        $this->requests[] = $params;

        return array_shift($this->turns) ?? [];
    }
}

/** @return list<object> a preamble plus a get_tax_information call, closed with the given finish reason */
function terminationToolTurn(string $text, ?string $finishReason): array
{
    return [(object) [
        'choices' => [(object) [
            'delta' => (object) [
                'content' => $text,
                'toolCalls' => [(object) [
                    'index' => 0,
                    'id' => 'call_tax_1',
                    'function' => (object) [
                        'name' => 'get_tax_information',
                        'arguments' => json_encode(['topic' => 'income_tax'], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ],
            'finishReason' => $finishReason,
        ]],
    ]];
}

/** @return list<object> */
function terminationTextTurn(string $text): array
{
    return [(object) [
        'choices' => [(object) [
            'delta' => (object) ['content' => $text],
            'finishReason' => 'stop',
        ]],
    ]];
}

function terminationConversation(User $user): AiConversation
{
    return AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Stream termination',
        'message_count' => 0,
    ]);
}

describe('the tool loop', function (): void {
    beforeEach(function (): void {
        TierConfiguration::updateOrCreate(['tier' => 'free'], tierConfigFixture('free'));
        TaxConfiguration::factory()->create(['is_active' => true]);
        app()->forgetInstance(TaxConfigService::class);
        Cache::put('ai_provider', 'xai');
    });

    it('reads the tool results and answers when tool calls arrive with a non-tool finish reason', function (?string $finishReason): void {
        $client = new TerminationScriptedXaiClient([
            terminationToolTurn('Let me pull the full details.', $finishReason),
            terminationTextTurn('Your Personal Allowance is tapered above the income limit.'),
        ]);
        app()->instance(XaiClient::class, $client);
        $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
        $conversation = terminationConversation($user);

        $events = iterator_to_array(
            app(CoordinatingAgent::class)->chat($user, $conversation, 'How does the tax trap work?'),
            preserve_keys: false,
        );

        $toolMessage = collect($client->requests[1]['messages'] ?? [])
            ->first(fn (array $message): bool => ($message['role'] ?? null) === 'tool');
        $answer = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->latest('id')
            ->firstOrFail();

        expect($client->requests)->toHaveCount(2)
            ->and($toolMessage)->not->toBeNull()
            ->and($answer->content)->toContain('Your Personal Allowance is tapered')
            ->and(collect($events)->last()['type'])->toBe('done');
    })->with([
        'xAI reports stop' => ['stop'],
        'no finish reason' => [null],
    ]);

    it('turns a tool that throws a PHP Error into a failed result the model answers around', function (): void {
        $client = new TerminationScriptedXaiClient([
            terminationToolTurn("I'll fetch the latest tax information.", 'tool_calls'),
            terminationTextTurn('I could not fetch the tax figures just now.'),
        ]);
        app()->instance(XaiClient::class, $client);
        $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
        $conversation = terminationConversation($user);

        $events = iterator_to_array(
            app(ThrowingToolAgent::class)->chat($user, $conversation, 'What is my tax position?'),
            preserve_keys: false,
        );

        $toolMessage = collect($client->requests[1]['messages'] ?? [])
            ->first(fn (array $message): bool => ($message['role'] ?? null) === 'tool');
        $toolPayload = json_decode($toolMessage['content'] ?? '{}', true);

        expect($toolPayload['error'] ?? false)->toBeTrue()
            ->and($toolPayload['error_type'] ?? null)->toBe('execution_failed')
            ->and(collect($events)->pluck('type')->all())->not->toContain('error')
            ->and(collect($events)->last()['type'])->toBe('done');
    });
});

describe('the chat stream', function (): void {
    beforeEach(function (): void {
        Cache::put('ai_provider', 'anthropic');
        app()->instance(Client::class, new ScriptedAnthropicClient([]));
    });

    $advice = function (Closure $turn): AiConversation {
        $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
        app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
        test()->actingAs($user, 'sanctum');
        test()->mock(CoordinatingAgent::class, function ($mock) use ($turn): void {
            $mock->shouldReceive('chatWithPromptOverride')->andReturnUsing(fn () => $turn());
        });

        return AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);
    };

    it('ends with an error frame when the turn throws a PHP Error mid-stream', function () use ($advice): void {
        $conversation = $advice(function (): Generator {
            yield ['type' => 'content', 'text' => "I'll fetch the latest tax information."];

            throw new TypeError('Unsupported operand types: string * float');
        });

        $response = test()->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => 'What is my tax position?']);

        $response->assertOk();
        $frames = collect(Sse::frames($response->streamedContent()));

        expect($frames->pluck('type')->all())->toContain('content')
            ->and($frames->last()['type'])->toBe('error')
            ->and($frames->last()['message'])->toBe('An unexpected error occurred. Please try again.');
    });

    it('closes a turn that returns without a terminal frame with done', function () use ($advice): void {
        $conversation = $advice(function (): Generator {
            yield ['type' => 'content', 'text' => 'Here is the answer.'];
        });

        $response = test()->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => 'Hello']);

        $frames = collect(Sse::frames($response->streamedContent()));

        expect($frames->pluck('type')->all())->toBe(['thinking', 'content', 'done']);
    });

    it('never adds a second terminal frame to a turn that ended properly', function () use ($advice): void {
        $conversation = $advice(function (): Generator {
            yield ['type' => 'content', 'text' => 'Here is the answer.'];
            yield ['type' => 'done'];
        });

        $response = test()->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => 'Hello']);

        $types = collect(Sse::frames($response->streamedContent()))->pluck('type');

        expect($types->filter(fn (string $type): bool => in_array($type, ['done', 'error'], true))->values()->all())
            ->toBe(['done']);
    });
});
