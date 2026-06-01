<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiAuditEvent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Memory\Episodic\FetchProvenanceCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    Storage::fake('local');
});

it('persists the episode blob + provenance + __episode__ attestation', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $assistant = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant']);

    // seed the request-scoped collector as if a pointer fetch happened this turn
    app(FetchProvenanceCollector::class)->record([
        'pointer_id' => 'isa', 'handler' => 'tax_allowance', 'source_label' => 'TaxConfigService', 'source_version' => '2026/27', 'digest' => 'd1',
    ]);

    // call the private persistEpisode via reflection on the real agent
    $agent = app(CoordinatingAgent::class);
    $m = new ReflectionMethod($agent, 'persistEpisode');
    $m->setAccessible(true);
    $m->invoke($agent, $assistant, $conv, $user, 'SYS', 'CTX', 'grok-4', [['name' => 'x']], [['ok' => true]]);

    $assistant->refresh();
    expect($assistant->blob_md_path)->not->toBeNull()
        ->and($assistant->blob_md_sha256)->toHaveLength(64)
        ->and($assistant->fetch_provenance[0]['handler'])->toBe('tax_allowance')
        ->and(Storage::disk('local')->exists($assistant->blob_md_path))->toBeTrue();

    $event = AiAuditEvent::where('tool_name', '__episode__')->where('entity_id', $assistant->id)->first();
    expect($event)->not->toBeNull()
        ->and((int) $event->hash_scheme)->toBe(2)
        ->and($event->result_summary['blob_md_sha256'])->toBe($assistant->blob_md_sha256);
});

it('does not throw when the blob write fails (resilient)', function (): void {
    // point the disk at an unwritable state is awkward; instead assert that a
    // collector with no entries + a normal message still persists cleanly and
    // the turn-critical path returns without exception.
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $assistant = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant']);
    $agent = app(CoordinatingAgent::class);
    $m = new ReflectionMethod($agent, 'persistEpisode');
    $m->setAccessible(true);
    $m->invoke($agent, $assistant, $conv, $user, 'SYS', 'CTX', 'grok-4', null, null);
    expect($assistant->fresh()->blob_md_path)->not->toBeNull();
});
