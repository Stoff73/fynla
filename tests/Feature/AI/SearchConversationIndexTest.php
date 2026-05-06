<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use App\Services\AI\AiToolDefinitions;
use App\Services\AI\XaiToolDefinitions;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * S1.5 — `search_conversation_index` tool returns prior summarised
 * conversations matching the supplied topic_keywords / entity_types,
 * ordered by `last_message_at` desc, scoped to the calling user, and
 * excluding the active conversation when the dispatcher passes its id.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function callSearchConversationIndex(array $input, User $user, ?int $conversationId = null): array
{
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionMethod($agent, 'handleSearchConversationIndex');
    $reflection->setAccessible(true);

    return $reflection->invoke($agent, $input, $user, $conversationId);
}

it('returns conversations matching topic_keywords ordered by last_message_at desc', function () {
    $user = User::factory()->create();

    $oldest = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Old retirement chat',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'summary' => 'User asked about retirement at 60.',
        'topics' => ['retirement'],
        'intents_stated' => ['I want to retire at 60'],
        'entities_mentioned' => [],
        'last_message_at' => now()->subDays(7),
        'summarised_at' => now()->subDays(7),
    ]);

    $middle = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'ISA discussion',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'summary' => 'User asked about Stocks & Shares ISA contributions.',
        'topics' => ['savings', 'investment'],
        'intents_stated' => ['ISA contribution timing'],
        'entities_mentioned' => [],
        'last_message_at' => now()->subDays(3),
        'summarised_at' => now()->subDays(3),
    ]);

    $newest = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Pension top-up',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'summary' => 'User asked about adding a SIPP top-up at year-end.',
        'topics' => ['retirement', 'tax_optimisation'],
        'intents_stated' => ['SIPP top-up before tax year ends'],
        'entities_mentioned' => [],
        'last_message_at' => now()->subDay(),
        'summarised_at' => now()->subDay(),
    ]);

    $result = callSearchConversationIndex(['topic_keywords' => ['retirement']], $user);

    expect($result['count'])->toBe(2);
    expect($result['conversations'][0]['id'])->toBe($newest->id);
    expect($result['conversations'][1]['id'])->toBe($oldest->id);
});

it('returns conversations matching entity_types', function () {
    $user = User::factory()->create();

    $withSipp = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'SIPP review',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'summary' => 'User has a Vanguard SIPP worth £80k.',
        'topics' => ['retirement'],
        'intents_stated' => [],
        'entities_mentioned' => [
            ['type' => 'dc_pension', 'label' => 'Vanguard SIPP'],
        ],
        'last_message_at' => now()->subHour(),
        'summarised_at' => now()->subHour(),
    ]);

    AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Random',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'summary' => 'User talked about something else.',
        'topics' => ['general'],
        'intents_stated' => [],
        'entities_mentioned' => [
            ['type' => 'savings_account', 'label' => 'Halifax cash'],
        ],
        'last_message_at' => now()->subHour(),
        'summarised_at' => now()->subHour(),
    ]);

    $result = callSearchConversationIndex(['entity_types' => ['dc_pension']], $user);

    expect($result['count'])->toBe(1);
    expect($result['conversations'][0]['id'])->toBe($withSipp->id);
});

it('per-user ACL — does not return another user\'s conversations even on matching keywords', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();

    AiConversation::create([
        'user_id' => $intruder->id,
        'title' => 'Intruder retirement chat',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'summary' => 'Their retirement context.',
        'topics' => ['retirement'],
        'intents_stated' => [],
        'entities_mentioned' => [],
        'last_message_at' => now(),
        'summarised_at' => now(),
    ]);

    $result = callSearchConversationIndex(['topic_keywords' => ['retirement']], $user);

    expect($result['count'])->toBe(0);
    expect($result['conversations'])->toBe([]);
});

it('skips conversations that have not been summarised yet', function () {
    $user = User::factory()->create();

    AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Not yet summarised',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'topics' => ['retirement'],
        'last_message_at' => now()->subHour(),
        'summarised_at' => null,
        'summary' => null,
    ]);

    $result = callSearchConversationIndex(['topic_keywords' => ['retirement']], $user);

    expect($result['count'])->toBe(0);
});

it('excludes the active conversation when its id is supplied', function () {
    $user = User::factory()->create();

    $active = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Active retirement chat',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'summary' => 'Just talked about retirement.',
        'topics' => ['retirement'],
        'last_message_at' => now()->subMinute(),
        'summarised_at' => now()->subMinute(),
    ]);

    $older = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Older retirement chat',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'summary' => 'Earlier retirement talk.',
        'topics' => ['retirement'],
        'last_message_at' => now()->subDay(),
        'summarised_at' => now()->subDay(),
    ]);

    $result = callSearchConversationIndex(['topic_keywords' => ['retirement']], $user, $active->id);

    expect($result['count'])->toBe(1);
    expect($result['conversations'][0]['id'])->toBe($older->id);
});

it('caps results at 10', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 15; $i++) {
        AiConversation::create([
            'user_id' => $user->id,
            'title' => "Convo {$i}",
            'status' => 'active',
            'model_used' => 'grok-4-1-fast-reasoning',
            'summary' => "Convo {$i} summary.",
            'topics' => ['retirement'],
            'last_message_at' => now()->subMinutes($i),
            'summarised_at' => now()->subMinutes($i),
        ]);
    }

    $result = callSearchConversationIndex(['topic_keywords' => ['retirement']], $user);

    expect($result['count'])->toBe(10);
});

it('with no filters returns all summarised conversations for the user (still capped + ordered)', function () {
    $user = User::factory()->create();

    $a = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'A',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'summary' => 'A',
        'topics' => ['retirement'],
        'last_message_at' => now()->subDay(),
        'summarised_at' => now()->subDay(),
    ]);

    $b = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'B',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'summary' => 'B',
        'topics' => ['estate_planning'],
        'last_message_at' => now()->subHour(),
        'summarised_at' => now()->subHour(),
    ]);

    $result = callSearchConversationIndex([], $user);

    expect($result['count'])->toBe(2);
    expect($result['conversations'][0]['id'])->toBe($b->id); // newer first
    expect($result['conversations'][1]['id'])->toBe($a->id);
});

it('search_conversation_index is NOT in AdviceFyn::WRITE_TOOLS — naturally permitted in advice mode', function () {
    $reflection = new ReflectionClass(AdviceFyn::class);
    $writeTools = $reflection->getReflectionConstant('WRITE_TOOLS')->getValue();

    expect($writeTools)->not->toContain('search_conversation_index');
});

it('AiToolDefinitions registers search_conversation_index with topic_keywords + entity_types arrays', function () {
    $defs = app(AiToolDefinitions::class);
    Cache::put('ai_provider', 'anthropic');
    $tools = $defs->getTools(false);

    $tool = collect($tools)->firstWhere('name', 'search_conversation_index');

    expect($tool)->not->toBeNull();
    expect($tool['input_schema']['properties'])->toHaveKey('topic_keywords');
    expect($tool['input_schema']['properties'])->toHaveKey('entity_types');
    expect($tool['input_schema']['additionalProperties'])->toBeFalse();
});

it('XaiToolDefinitions registers search_conversation_index with strict mode', function () {
    $defs = app(XaiToolDefinitions::class);
    Cache::put('ai_provider', 'xai');
    $tools = $defs->getTools(false);

    $tool = collect($tools)->first(fn ($t) => ($t['function']['name'] ?? null) === 'search_conversation_index');

    expect($tool)->not->toBeNull();
    expect($tool['function']['strict'])->toBeTrue();
    expect($tool['function']['parameters']['additionalProperties'])->toBeFalse();
});
