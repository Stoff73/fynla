<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\TaxConfiguration;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\AI\AiProvider;
use App\Services\AI\AiToolDefinitions;
use App\Services\AI\Loop\Planner;
use App\Services\AI\XaiClient;
use App\Services\TaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * TODO item 17a (CSJ 2026-10-08): OpenAI is a third choice in the admin AI
 * panel, so Fyn can run on GPT-6 Luna. It speaks the OpenAI Chat Completions
 * format, so it shares xAI's catalogue, client and request shape with its own
 * key, base URL and model. GPT-6 Luna calls functions on Chat Completions only
 * with reasoning_effort "none" (https://developers.openai.com/api/docs/models/gpt-6-luna).
 */
final class CapturingOpenAiFormatClient
{
    /** @var list<array<string, mixed>> */
    public array $requests = [];

    /** @var list<string|null> */
    public array $providers = [];

    public function forConversation(int|string $conversationId, ?string $provider = null): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    public function chat(?string $provider = null): object
    {
        if ($provider !== null) {
            $this->providers[] = $provider;
        }
        $client = $this;

        return new class($client)
        {
            public function __construct(private readonly CapturingOpenAiFormatClient $client) {}

            /** @param  array<string, mixed>  $params */
            public function createStreamed(array $params): Generator
            {
                $this->client->requests[] = $params;

                yield (object) ['choices' => [(object) [
                    'delta' => (object) ['content' => 'Here is your answer.'],
                    'finishReason' => 'stop',
                ]]];
            }
        };
    }
}

beforeEach(function (): void {
    Cache::forget('ai_provider_version');
    Cache::forever('ai_provider', 'openai');
    config()->set('services.openai.api_key', 'test-stub-openai');
    config()->set('services.openai.chat_model', 'gpt-6-luna');
    config()->set('services.openai.advanced_chat_model', 'gpt-6-luna');
});

it('lists OpenAI GPT-6 Luna in the admin AI choices and switches to it', function (): void {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    Cache::forever('ai_provider', 'xai');

    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/ai-provider')
        ->assertOk()
        ->assertJsonPath('data.available_providers.2', [
            'id' => 'openai',
            'name' => 'OpenAI GPT',
            'model' => 'gpt-6-luna',
            'configured' => true,
        ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/ai-provider', ['provider' => 'openai'])
        ->assertOk();

    expect(AiProvider::active())->toBe('openai');
});

it('refuses to switch to OpenAI without its API key', function (): void {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    config()->set('services.openai.api_key', '');
    Cache::forever('ai_provider', 'xai');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/ai-provider', ['provider' => 'openai'])
        ->assertStatus(422);

    expect(AiProvider::active())->toBe('xai');
});

it('serves the OpenAI function-calling catalogue on OpenAI', function (): void {
    $tools = app(AiToolDefinitions::class)->getTools(false);

    expect($tools)->not->toBeEmpty()
        ->and(collect($tools)->every(fn (array $tool): bool => ! isset($tool['input_schema'])))->toBeTrue()
        ->and(AiProvider::toolFormat('openai'))->toBe('xai');
});

it('runs a Fyn advice turn on GPT-6 Luna over the OpenAI connection', function (): void {
    TierConfiguration::updateOrCreate(['tier' => 'free'], tierConfigFixture('free'));
    TaxConfiguration::factory()->create(['is_active' => true]);
    app()->forgetInstance(TaxConfigService::class);

    $client = new CapturingOpenAiFormatClient;
    app()->instance(XaiClient::class, $client);
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test', 'title' => 'Advice', 'message_count' => 0]);

    $events = iterator_to_array(app(CoordinatingAgent::class)->chatWithPromptOverride(
        user: $user,
        conversation: $conversation,
        message: 'How much is in my ISA?',
        currentRoute: null,
        systemPromptOverride: null,
        allowedTools: null,
        personaOverride: 'advice',
    ), preserve_keys: false);

    $request = $client->requests[0];

    expect($client->providers)->toContain('openai')
        ->and($request['model'])->toBe('gpt-6-luna')
        ->and($request['reasoning_effort'])->toBe('none')
        ->and($request['store'])->toBeFalse()
        ->and($request['messages'][0]['role'])->toBe('system')
        ->and($request['tools'][0]['type'])->toBe('function')
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(''))->toContain('Here is your answer.');
});

it('plans on GPT-6 Luna with reasoning effort none, so the forced plan tool is allowed', function (): void {
    $client = new CapturingOpenAiFormatClient;
    app()->instance(XaiClient::class, $client);

    app(Planner::class)->plan('system', [['role' => 'user', 'content' => 'hello']]);

    expect($client->providers)->toBe(['openai'])
        ->and($client->requests[0]['model'])->toBe('gpt-6-luna')
        ->and($client->requests[0]['reasoning_effort'])->toBe('none')
        ->and($client->requests[0]['store'])->toBeFalse()
        ->and($client->requests[0]['tool_choice']['function']['name'])->toBe('plan');
});

it('connects the shared client to OpenAI with its own key and base URL', function (): void {
    config()->set('services.openai.base_url', 'https://api.openai.com/v1');
    config()->set('services.xai.api_key', '');

    $client = new XaiClient;
    $base = (new ReflectionProperty($client->client(), 'transporter'))->getValue($client->client());
    $config = (new ReflectionProperty($base, 'baseUri'))->getValue($base);

    expect($config->toString())->toBe('https://api.openai.com/v1/');
});

it('sends a strict tool with optional properties to OpenAI as non-strict, and leaves xAI and closed schemas strict', function (): void {
    $optional = ['type' => 'function', 'function' => ['name' => 'capture_state_pension', 'strict' => true, 'parameters' => [
        'type' => 'object',
        'properties' => ['forecast_annual' => ['type' => 'number'], 'state_pension_age' => ['type' => 'integer']],
        'required' => ['state_pension_age'],
        'additionalProperties' => false,
    ]]];
    $closed = ['type' => 'function', 'function' => ['name' => 'get_savings', 'strict' => true, 'parameters' => [
        'type' => 'object',
        'properties' => ['filter' => ['type' => 'object', 'properties' => ['kind' => ['type' => ['string', 'null']]], 'required' => ['kind'], 'additionalProperties' => false]],
        'required' => ['filter'],
        'additionalProperties' => false,
    ]]];
    $nestedOptional = $closed;
    $nestedOptional['function']['parameters']['properties']['filter']['required'] = [];

    $openai = AiProvider::toolsFor('openai', [$optional, $closed, $nestedOptional]);

    expect($openai[0]['function']['strict'])->toBeFalse()
        ->and($openai[0]['function']['parameters'])->toBe($optional['function']['parameters'])
        ->and($openai[1]['function']['strict'])->toBeTrue()
        ->and($openai[2]['function']['strict'])->toBeFalse()
        ->and(AiProvider::toolsFor('xai', [$optional]))->toBe([$optional]);
});

it('never lets OpenAI keep a one-shot helper call, and sends it to OpenAI while OpenAI is active (CSJ 2026-10-08)', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{}']]]])]);

    AiProvider::postChatCompletion(AiProvider::helperConnection(), ['messages' => [['role' => 'user', 'content' => 'hi']]], 30);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.com/v1/chat/completions'
        && $request['store'] === false
        && $request['model'] === 'gpt-6-luna'
        && $request->hasHeader('Authorization', 'Bearer test-stub-openai'));
});

it('keeps the one-shot helpers on xAI, without OpenAI options, while xAI or Anthropic is active', function (): void {
    config()->set('services.xai.api_key', 'test-stub-xai');
    Http::fake(['api.x.ai/*' => Http::response(['choices' => [['message' => ['content' => '{}']]]])]);

    foreach (['xai', 'anthropic'] as $provider) {
        Cache::forever('ai_provider', $provider);
        AiProvider::postChatCompletion(AiProvider::helperConnection(), ['messages' => [['role' => 'user', 'content' => 'hi']]], 30);
    }

    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.x.ai/v1/chat/completions')
        && ! isset($request['store']));
});
