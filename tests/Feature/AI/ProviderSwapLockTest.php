<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * Sprint 0.11.4 — Provider-swap lock (INV-2.9.4).
 *
 * The active AI provider must be captured ONCE per chat() loop and the
 * snapshot reused for the rest of that call. If an admin toggles the
 * provider mid-stream, in-flight requests must finish on the provider
 * they started with — otherwise Anthropic prompt-cache markers (which
 * are baked into AiToolDefinitions) leak into xAI's endpoint and cause
 * a 400, or vice versa.
 *
 * Mechanism: AdminController::setAiProvider increments a version
 * counter (`ai_provider_version`) and writes the new value under the
 * versioned key (`ai_provider:v{N}`). HasAiGuardrails::getAiProviderForLoop
 * reads the version + key as a single snapshot. The chat() loop
 * captures the resolved provider once at the top and never re-reads it,
 * so a mid-stream `Cache::forever('ai_provider:v{N+1}', ...)` write by
 * the admin endpoint cannot affect the in-flight call.
 */
beforeEach(function () {
    Cache::forget('ai_provider');
    Cache::forget('ai_provider_version');
    for ($v = 1; $v <= 10; $v++) {
        Cache::forget("ai_provider:v{$v}");
    }

    // AdminController::setAiProvider 422s when the target provider's API key
    // is empty in config — that's correct production behaviour but couples
    // these tests to the local .env. Stub both keys so the test deterministically
    // exercises the version-bump lock, not the env wiring.
    config()->set('services.anthropic.api_key', 'test-stub-anthropic');
    config()->set('services.xai.api_key', 'test-stub-xai');
});

function callGetAiProviderForLoop(): string
{
    $reflection = new ReflectionMethod(CoordinatingAgent::class, 'getAiProviderForLoop');
    $reflection->setAccessible(true);

    return $reflection->invoke(null);
}

function asAdminUser(): User
{
    return User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);
}

describe('Provider-swap lock (S0.11.4 / INV-2.9.4)', function () {
    it('exposes a getAiProviderForLoop helper on HasAiGuardrails', function () {
        expect(method_exists(CoordinatingAgent::class, 'getAiProviderForLoop'))
            ->toBeTrue();
    });

    it('returns the config default when no override is set', function () {
        $expected = config('services.ai_provider', 'anthropic');
        expect(callGetAiProviderForLoop())->toBe($expected);
    });

    it('reads the versioned cache key when present', function () {
        Cache::forever('ai_provider_version', 3);
        Cache::forever('ai_provider:v3', 'xai');

        expect(callGetAiProviderForLoop())->toBe('xai');
    });

    it('falls back to the legacy unversioned key when no version is set', function () {
        // Pre-S0.11.4 callers (existing tests) write directly to
        // `ai_provider` without bumping the version. Those reads must
        // keep working so we do not break legacy fixtures or admin
        // tooling that hasn't been updated yet.
        Cache::forever('ai_provider', 'xai');

        expect(callGetAiProviderForLoop())->toBe('xai');
    });

    it('admin setAiProvider bumps the version and writes the new versioned key', function () {
        $admin = asAdminUser();

        $this->actingAs($admin)
            ->postJson('/api/admin/ai-provider', ['provider' => 'xai'])
            ->assertOk();

        $version = (int) Cache::get('ai_provider_version', 0);
        expect($version)->toBeGreaterThan(0)
            ->and(Cache::get("ai_provider:v{$version}"))->toBe('xai');
    });

    it('admin toggle from xai → anthropic increments the version a second time', function () {
        $admin = asAdminUser();

        $this->actingAs($admin)
            ->postJson('/api/admin/ai-provider', ['provider' => 'xai'])
            ->assertOk();
        $v1 = (int) Cache::get('ai_provider_version', 0);

        $this->actingAs($admin)
            ->postJson('/api/admin/ai-provider', ['provider' => 'anthropic'])
            ->assertOk();
        $v2 = (int) Cache::get('ai_provider_version', 0);

        expect($v2)->toBeGreaterThan($v1)
            ->and(Cache::get("ai_provider:v{$v2}"))->toBe('anthropic');
    });

    it('a chat-loop snapshot does not change after a mid-stream admin toggle', function () {
        $admin = asAdminUser();

        // Start with anthropic
        $this->actingAs($admin)
            ->postJson('/api/admin/ai-provider', ['provider' => 'anthropic'])
            ->assertOk();

        // Caller (e.g. HasAiChat::chat) snapshots once at the top of the loop
        $captured = callGetAiProviderForLoop();
        expect($captured)->toBe('anthropic');

        // Admin swaps mid-stream
        $this->actingAs($admin)
            ->postJson('/api/admin/ai-provider', ['provider' => 'xai'])
            ->assertOk();

        // The captured local must not change — PHP scalar copy semantics
        // is the floor; the test pins that the loop variable stays put.
        expect($captured)->toBe('anthropic');

        // A fresh resolution sees the new provider (correct for next request)
        expect(callGetAiProviderForLoop())->toBe('xai');
    });

    it('admin GET ai-provider also reads the versioned key', function () {
        $admin = asAdminUser();

        Cache::forever('ai_provider_version', 5);
        Cache::forever('ai_provider:v5', 'xai');

        $response = $this->actingAs($admin)->getJson('/api/admin/ai-provider');

        $response->assertOk()
            ->assertJsonPath('data.provider', 'xai');
    });
});
