<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Memory\Episodic\FetchProvenanceCollector;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('a recommendation turn persists surfaced strategy ids into fetch_provenance', function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    Storage::fake('local');

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $assistant = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant']);

    $agent = app(CoordinatingAgent::class);

    $h = new ReflectionMethod($agent, 'handleRecommendations');
    $h->setAccessible(true);
    $h->invoke($agent, $user);

    expect(app(FetchProvenanceCollector::class)->all())->not->toBeEmpty();

    $m = new ReflectionMethod($agent, 'persistEpisode');
    $m->setAccessible(true);
    $m->invoke($agent, $assistant, $conv, $user, 'SYS', 'CTX', 'grok-4', null, null);

    $assistant->refresh();
    $entry = collect($assistant->fetch_provenance)->firstWhere('pointer_id', 'recommendations');

    expect($entry)->not->toBeNull()
        ->and($entry)->toHaveKeys(['strategy_ids', 'locked_strategy_ids', 'digest']);
});
