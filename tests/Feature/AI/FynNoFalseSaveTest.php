<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\TaxConfiguration;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\AI\Fyn\CertaintyFilter;
use App\Services\AI\XaiClient;
use App\Services\TaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/*
 * TODO item 7a, walking release #1071: after the /m personal form, "Actually my
 * date of birth is 2 August 1960" reached Advice Fyn, which answered "Thank you
 * — I have updated your date of birth to 2 August 1960." with no write; the date
 * stayed 1 August. Advice saves nothing, so the reply every surface streams and
 * stores never says it saved something.
 */
final class NoFalseSaveXaiClient
{
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
            public function __construct(private readonly NoFalseSaveXaiClient $client) {}

            /** @param  array<string, mixed>  $params */
            public function createStreamed(array $params): Generator
            {
                yield from $this->client->nextTurn();
            }
        };
    }

    /** @return list<object> */
    public function nextTurn(): array
    {
        return array_shift($this->turns) ?? [];
    }
}

/** @return list<object> */
function noFalseSaveTextTurn(string $text): array
{
    return [(object) [
        'choices' => [(object) [
            'delta' => (object) ['content' => $text],
            'finishReason' => 'stop',
        ]],
    ]];
}

beforeEach(function (): void {
    TierConfiguration::updateOrCreate(['tier' => 'free'], tierConfigFixture('free'));
    TaxConfiguration::factory()->create(['is_active' => true]);
    app()->forgetInstance(TaxConfigService::class);
    Cache::put('ai_provider', 'xai');
});

it('never streams or stores an advice reply saying it saved something', function (): void {
    app()->instance(XaiClient::class, new NoFalseSaveXaiClient([
        noFalseSaveTextTurn('Thank you — I have updated your date of birth to 2 August 1960. Is there anything else you would like to change or add?'),
    ]));
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test', 'title' => 'Advice', 'message_count' => 0]);

    $events = iterator_to_array(app(CoordinatingAgent::class)->chatWithPromptOverride(
        user: $user,
        conversation: $conversation,
        message: 'Actually my date of birth is 2 August 1960',
        currentRoute: null,
        systemPromptOverride: null,
        allowedTools: null,
        personaOverride: 'advice',
    ), preserve_keys: false);

    $streamed = collect($events)->where('type', 'content')->pluck('text')->implode('');
    $stored = AiMessage::query()->where('conversation_id', $conversation->id)->where('role', 'assistant')->latest('id')->firstOrFail();

    expect($streamed)->not->toContain('I have updated')
        ->toContain(CertaintyFilter::NOT_SAVED)
        ->and($stored->content)->toBe(CertaintyFilter::NOT_SAVED.' Is there anything else you would like to change or add?');
});
