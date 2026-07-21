<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AuditChainService;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->conv = AiConversation::factory()->create(['user_id' => $this->user->id]);
});

it('passes when the v2 episode blob on disk matches its recorded sha', function (): void {
    Storage::disk('local')->put('episodic/2026/06/01/3/99.md', 'BLOB');
    app(AuditChainService::class)->appendEpisode([
        'user_id' => $this->user->id, 'conversation_id' => $this->conv->id, 'entity_id' => 99,
        'blob_md_sha256' => hash('sha256', 'BLOB'), 'blob_md_path' => 'episodic/2026/06/01/3/99.md',
        'semantic_snapshot_id' => null, 'fetch_provenance' => [],
    ]);

    $this->artisan('ai:audit:verify-chain')->assertExitCode(0);
});

it('fails when a v2 episode blob has been tampered on disk', function (): void {
    Storage::disk('local')->put('episodic/2026/06/01/3/99.md', 'BLOB');
    app(AuditChainService::class)->appendEpisode([
        'user_id' => $this->user->id, 'conversation_id' => $this->conv->id, 'entity_id' => 99,
        'blob_md_sha256' => hash('sha256', 'BLOB'), 'blob_md_path' => 'episodic/2026/06/01/3/99.md',
        'semantic_snapshot_id' => null, 'fetch_provenance' => [],
    ]);
    Storage::disk('local')->put('episodic/2026/06/01/3/99.md', 'TAMPERED');

    $this->artisan('ai:audit:verify-chain')->assertExitCode(1);
});

it('fails when a v2 episode blob is missing on disk', function (): void {
    app(AuditChainService::class)->appendEpisode([
        'user_id' => $this->user->id, 'conversation_id' => $this->conv->id, 'entity_id' => 99,
        'blob_md_sha256' => hash('sha256', 'BLOB'), 'blob_md_path' => 'episodic/2026/06/01/3/missing.md',
        'semantic_snapshot_id' => null, 'fetch_provenance' => [],
    ]);

    $this->artisan('ai:audit:verify-chain')->assertExitCode(1);
});
