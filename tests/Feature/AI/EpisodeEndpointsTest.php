<?php

declare(strict_types=1);

use App\Models\AdvisorClient;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\AuditChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    Storage::fake('local');
});

/**
 * Create an assistant episode (AiMessage) with a real blob on disk for $client.
 */
function makeEpisode(User $client, array $overrides = []): AiMessage
{
    $conv = AiConversation::factory()->create(['user_id' => $client->id]);
    $path = 'episodic/'.$client->id.'/'.uniqid('ep_', true).'.md';
    $body = "# Episode\n\nbody for {$client->id}";
    Storage::disk('local')->put($path, $body);

    return AiMessage::factory()->create(array_merge([
        'conversation_id' => $conv->id,
        'role' => 'assistant',
        'persona' => 'advice',
        'model_used' => 'grok-4',
        'metadata' => ['module' => 'retirement'],
        'blob_md_path' => $path,
        'blob_md_sha256' => hash('sha256', $body),
    ], $overrides));
}

describe('admin episode endpoints', function (): void {
    it('returns a paginated list of all users episodes for an admin', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);
        $a = User::factory()->create();
        $b = User::factory()->create();
        makeEpisode($a);
        makeEpisode($b);

        $response = $this->actingAs($admin)->getJson('/api/admin/ai-audit/episodes');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [['id', 'created_at', 'persona', 'module', 'has_blob']],
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);
        expect($response->json('data.total'))->toBe(2);
    });

    it('filters the admin episode list by user_id', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);
        $a = User::factory()->create();
        $b = User::factory()->create();
        makeEpisode($a);
        makeEpisode($b);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/ai-audit/episodes?user_id='.$a->id);

        $response->assertOk();
        expect($response->json('data.total'))->toBe(1);
    });

    it('returns episode detail with blob_body for an admin', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();
        $episode = makeEpisode($client);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/ai-audit/episodes/'.$episode->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $episode->id)
            ->assertJsonStructure(['success', 'data' => ['id', 'blob_md_sha256', 'blob_body']]);
        expect($response->json('data.blob_body'))->toContain('body for '.$client->id);
    });

    it('forbids a non-admin non-advisor user from the admin list', function (): void {
        $plain = User::factory()->create(['is_admin' => false, 'is_advisor' => false]);

        $this->actingAs($plain)
            ->getJson('/api/admin/ai-audit/episodes')
            ->assertForbidden();
    });
});

describe('advisor client episode endpoints', function (): void {
    it('returns episodes for an advisors own client', function (): void {
        $advisor = User::factory()->create(['is_advisor' => true]);
        $client = User::factory()->create();
        AdvisorClient::factory()->create([
            'advisor_id' => $advisor->id,
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        makeEpisode($client);

        $response = $this->actingAs($advisor)
            ->getJson('/api/advisor/clients/'.$client->id.'/episodes');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['data', 'total']]);
        expect($response->json('data.total'))->toBe(1);
    });

    it('forbids an advisor from viewing a non-client episode list', function (): void {
        $advisor = User::factory()->create(['is_advisor' => true]);
        $stranger = User::factory()->create();
        makeEpisode($stranger);

        $this->actingAs($advisor)
            ->getJson('/api/advisor/clients/'.$stranger->id.'/episodes')
            ->assertNotFound();
    });

    it('lets an advisor read detail for their own clients episode', function (): void {
        $advisor = User::factory()->create(['is_advisor' => true]);
        $client = User::factory()->create();
        AdvisorClient::factory()->create([
            'advisor_id' => $advisor->id,
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        $episode = makeEpisode($client);

        $this->actingAs($advisor)
            ->getJson('/api/advisor/clients/'.$client->id.'/episodes/'.$episode->id)
            ->assertOk()
            ->assertJsonPath('data.id', $episode->id);
    });

    it('forbids an advisor reading detail of a non-client episode', function (): void {
        $advisor = User::factory()->create(['is_advisor' => true]);
        $stranger = User::factory()->create();
        $episode = makeEpisode($stranger);

        $this->actingAs($advisor)
            ->getJson('/api/advisor/clients/'.$stranger->id.'/episodes/'.$episode->id)
            ->assertNotFound();
    });
});

describe('episode verification endpoint', function (): void {
    it('reports verified when a valid attestation exists', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();
        $episode = makeEpisode($client);

        app(AuditChainService::class)->appendEpisode([
            'user_id' => $client->id,
            'conversation_id' => $episode->conversation_id,
            'entity_id' => $episode->id,
            'blob_md_sha256' => $episode->blob_md_sha256,
            'blob_md_path' => $episode->blob_md_path,
            'semantic_snapshot_id' => null,
            'fetch_provenance' => [],
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/ai-audit/episodes/'.$episode->id.'/verify');

        $response->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.state', 'verified');
    });

    it('reports no_attestation when no episode audit row exists', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();
        $episode = makeEpisode($client);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/ai-audit/episodes/'.$episode->id.'/verify');

        $response->assertOk()
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.state', 'no_attestation');
    });

    it('reports modified when the blob no longer matches the attestation', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();
        $episode = makeEpisode($client);

        app(AuditChainService::class)->appendEpisode([
            'user_id' => $client->id,
            'conversation_id' => $episode->conversation_id,
            'entity_id' => $episode->id,
            'blob_md_sha256' => $episode->blob_md_sha256,
            'blob_md_path' => $episode->blob_md_path,
            'semantic_snapshot_id' => null,
            'fetch_provenance' => [],
        ]);

        // tamper the blob on disk
        Storage::disk('local')->put($episode->blob_md_path, 'TAMPERED');

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/ai-audit/episodes/'.$episode->id.'/verify');

        $response->assertOk()
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.state', 'modified');
    });
});
