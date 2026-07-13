<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\TaxConfiguration;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\AI\XaiClient;
use App\Services\TaxConfigService;
use Illuminate\Support\Facades\Cache;

final class RepetitionCountingAgent extends CoordinatingAgent
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $executions = [];

    public function executeTool(
        string $toolName,
        array $input,
        User $user,
        ?int $conversationId = null,
        ?array $classification = null,
        ?array $kycResult = null,
    ): array {
        $this->executions[$toolName][] = $input;

        return parent::executeTool($toolName, $input, $user, $conversationId, $classification, $kycResult);
    }

    /** @return list<array<string, mixed>> */
    public function callsFor(string $toolName): array
    {
        return $this->executions[$toolName] ?? [];
    }
}

final class RepetitionScriptedXaiClient
{
    /** @var list<list<object>> */
    private array $turns;

    /** @var list<array<string, mixed>> */
    private array $requests = [];

    /** @param list<list<object>> $turns */
    public function __construct(array $turns)
    {
        $this->turns = $turns;
    }

    public function forConversation(int|string $conversationId): self
    {
        return $this;
    }

    public function chat(): RepetitionScriptedXaiChat
    {
        return new RepetitionScriptedXaiChat($this);
    }

    /** @param array<string, mixed> $params
     * @return list<object>
     */
    public function nextTurn(array $params): array
    {
        $this->requests[] = $params;

        return array_shift($this->turns) ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function requests(): array
    {
        return $this->requests;
    }
}

final class RepetitionScriptedXaiChat
{
    public function __construct(private readonly RepetitionScriptedXaiClient $client) {}

    /** @param array<string, mixed> $params */
    public function createStreamed(array $params): Generator
    {
        yield from $this->client->nextTurn($params);
    }
}

/** @param array<string, mixed> $arguments
 * @return list<object>
 */
function repetitionToolTurn(string $text, string $id, array $arguments): array
{
    return [(object) [
        'choices' => [(object) [
            'delta' => (object) [
                'content' => $text,
                'toolCalls' => [(object) [
                    'index' => 0,
                    'id' => $id,
                    'function' => (object) [
                        'name' => 'get_tax_information',
                        'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
                    ],
                ]],
            ],
            'finishReason' => 'tool_calls',
        ]],
    ]];
}

/** @return list<object> */
function repetitionTextTurn(string $text): array
{
    return [(object) [
        'choices' => [(object) [
            'delta' => (object) ['content' => $text],
            'finishReason' => 'stop',
        ]],
    ]];
}

beforeEach(function (): void {
    TierConfiguration::create(tierConfigFixture('free'));
    TaxConfiguration::factory()->create(['is_active' => true]);
    app()->forgetInstance(TaxConfigService::class);
});

it('executes a normalised tool call once, uses current-iteration history, and persists one clean block', function (): void {
    $preCapParagraph = 'I need a little more information.';
    $preCapRepeated = implode("\n\n", array_fill(0, 4, $preCapParagraph));
    $finalParagraph = 'Here is your clean final answer.';
    $finalRepeated = implode("\n\n", array_fill(0, 4, $finalParagraph));
    $firstArgs = [
        'topic' => 'income_tax',
        'optional_value' => 'null',
        'label' => 'Tax &amp; allowances',
        'options' => ['b' => 2, 'a' => 1],
    ];
    $sameEffectiveArgsDifferentOrder = [
        'options' => ['a' => 1, 'b' => 2],
        'label' => 'Tax & allowances',
        'optional_value' => null,
        'topic' => 'income_tax',
    ];

    $client = new RepetitionScriptedXaiClient([
        repetitionToolTurn($preCapRepeated, 'call_tax_1', $firstArgs),
        repetitionToolTurn($preCapRepeated, 'call_tax_2', $sameEffectiveArgsDifferentOrder),
        repetitionToolTurn($preCapRepeated, 'call_tax_3', $firstArgs),
        repetitionTextTurn($finalRepeated),
    ]);
    Cache::put('ai_provider', 'xai');
    app()->instance(XaiClient::class, $client);

    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
        'annual_employment_income' => 75000,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Loop regression',
        'message_count' => 0,
    ]);
    $agent = app(RepetitionCountingAgent::class);

    iterator_to_array(
        $agent->chat($user, $conversation, 'What is my income breakdown?'),
        preserve_keys: false,
    );

    $persisted = AiMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', 'assistant')
        ->latest('id')
        ->firstOrFail();
    $requests = $client->requests();
    $thirdRequestAssistants = array_values(array_filter(
        $requests[2]['messages'] ?? [],
        fn (array $message): bool => ($message['role'] ?? null) === 'assistant',
    ));
    $lastAssistant = end($thirdRequestAssistants);
    $syntheticResult = collect($requests[2]['messages'] ?? [])
        ->first(fn (array $message): bool => ($message['role'] ?? null) === 'tool'
            && ($message['tool_call_id'] ?? null) === 'call_tax_2');
    $syntheticPayload = json_decode($syntheticResult['content'] ?? '{}', true, flags: JSON_THROW_ON_ERROR);
    $violationRules = array_column($persisted->metadata['validation_violations'] ?? [], 'rule');

    expect($agent->callsFor('get_tax_information'))->toHaveCount(1)
        ->and($requests)->toHaveCount(4)
        ->and($lastAssistant['content'] ?? null)->toBe($preCapRepeated)
        ->and($lastAssistant['content'] ?? null)->not->toBe($preCapRepeated.$preCapRepeated)
        ->and($syntheticPayload['already_supplied'] ?? false)->toBeTrue()
        ->and($requests[3])->not->toHaveKey('tools')
        ->and($persisted->content)->toBe($finalParagraph)
        ->and($persisted->content)->not->toContain($preCapParagraph)
        ->and(substr_count($persisted->content, $finalParagraph))->toBe(1)
        ->and($violationRules)->toContain('repetition_collapsed');
});
