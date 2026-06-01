<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AuditChainService;

beforeEach(function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    // ai_audit_events.user_id / conversation_id are FK-constrained; the
    // scenarios below reference user_id = 1 and conversation ids 1 and 3.
    User::factory()->create(['id' => 1]);
    AiConversation::factory()->create(['id' => 1, 'user_id' => 1]);
    AiConversation::factory()->create(['id' => 3, 'user_id' => 1]);
});

it('appends a v2 __episode__ event binding the blob sha and verifies in-chain', function (): void {
    $svc = app(AuditChainService::class);

    $svc->append(['user_id' => 1, 'tool_name' => 'list_goals', 'operation' => 'read', 'status' => 'dispatched']);

    $ep = $svc->appendEpisode([
        'user_id' => 1, 'conversation_id' => 3, 'entity_id' => 99,
        'blob_md_sha256' => str_repeat('a', 64),
        'blob_md_path' => 'episodic/2026/06/01/3/99.md',
        'semantic_snapshot_id' => str_repeat('b', 64),
        'fetch_provenance' => [['digest' => 'd1'], ['digest' => 'd2']],
    ]);

    expect($ep->tool_name)->toBe('__episode__')
        ->and((int) $ep->hash_scheme)->toBe(2)
        ->and((int) $ep->entity_id)->toBe(99)
        ->and($ep->result_summary['blob_md_sha256'])->toBe(str_repeat('a', 64))
        ->and($ep->result_summary['blob_md_path'])->toBe('episodic/2026/06/01/3/99.md');

    expect($svc->verifyChain()['chain_valid'])->toBeTrue();
});

it('a v1 chain still verifies green after the v2 method exists (no reserialisation)', function (): void {
    $svc = app(AuditChainService::class);
    $svc->append(['user_id' => 1, 'tool_name' => 'a', 'operation' => 'read', 'status' => 'dispatched']);
    $svc->append(['user_id' => 1, 'tool_name' => 'b', 'operation' => 'read', 'status' => 'dispatched']);
    expect($svc->verifyChain()['chain_valid'])->toBeTrue();
});

it('a mixed v1 + v2 chain verifies green', function (): void {
    $svc = app(AuditChainService::class);
    $svc->append(['user_id' => 1, 'tool_name' => 'a', 'operation' => 'read', 'status' => 'dispatched']);
    $svc->appendEpisode(['user_id' => 1, 'conversation_id' => 1, 'entity_id' => 1, 'blob_md_sha256' => str_repeat('c', 64), 'blob_md_path' => 'episodic/x.md', 'semantic_snapshot_id' => null, 'fetch_provenance' => []]);
    $svc->append(['user_id' => 1, 'tool_name' => 'b', 'operation' => 'read', 'status' => 'dispatched']);
    $svc->appendEpisode(['user_id' => 1, 'conversation_id' => 1, 'entity_id' => 2, 'blob_md_sha256' => str_repeat('d', 64), 'blob_md_path' => 'episodic/y.md', 'semantic_snapshot_id' => null, 'fetch_provenance' => []]);
    expect($svc->verifyChain()['chain_valid'])->toBeTrue();
});

it('detects tampering with a v2 row hash', function (): void {
    $svc = app(AuditChainService::class);
    $ep = $svc->appendEpisode(['user_id' => 1, 'conversation_id' => 1, 'entity_id' => 1, 'blob_md_sha256' => str_repeat('a', 64), 'blob_md_path' => 'episodic/x.md', 'semantic_snapshot_id' => null, 'fetch_provenance' => []]);
    // tamper the stored sha in result_summary without recomputing the hash
    $rs = $ep->result_summary;
    $rs['blob_md_sha256'] = str_repeat('e', 64);
    $ep->result_summary = $rs;
    $ep->saveQuietly();
    expect($svc->verifyChain()['chain_valid'])->toBeFalse();
});
