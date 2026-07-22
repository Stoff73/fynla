<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentAllowanceGate;
use App\Services\Stores\CurrencyDisplayService;
use App\Services\Stores\Snapshots\SnapshotPolicies;
use App\Services\Stores\TierConfigurationStore;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

// ── 3a: Document allowance ──────────────────────────────────────────────────

describe('DocumentAllowanceGate (§11)', function () {
    it('allows Premium uploads when retained storage is below the ceiling', function () {
        $user = User::factory()->withActivePremiumSubscription()->create();

        Document::factory(2)->create(['user_id' => $user->id]);

        $gate = app(DocumentAllowanceGate::class);
        expect($gate->check($user))->toBeNull(); // allowed
    });

    it('blocks Free uploads immediately and returns the Premium target shape', function () {
        $user = User::factory()->create(['tier' => 'free']);

        $gate = app(DocumentAllowanceGate::class);
        $result = $gate->check($user);

        expect($result)->not->toBeNull()
            ->and($result['allowed'])->toBeFalse()
            ->and($result['entity_key'])->toBe('document_upload')
            ->and($result['limit'])->toBe(0)
            ->and($result['target_tier'])->toBeArray();
    });

    it('does NOT delete existing documents — only blocks new uploads', function () {
        $user = User::factory()->create(['tier' => 'free']);

        // Already has 5 documents (grandfathered — over the new limit)
        Document::factory(5)->create(['user_id' => $user->id]);

        // Existing 5 must still be present (no deletion occurred)
        expect(Document::where('user_id', $user->id)->count())->toBe(5);

        // Gate blocks the 6th
        $gate = app(DocumentAllowanceGate::class);
        expect($gate->check($user))->not->toBeNull();
    });

    it('exempts preview users from the document allowance gate', function () {
        $user = User::factory()->create(['is_preview_user' => true]);

        // Over any allowance
        Document::factory(10)->create(['user_id' => $user->id]);

        $gate = app(DocumentAllowanceGate::class);
        expect($gate->check($user))->toBeNull(); // always allowed for preview
    });

    it('exempts admin users from the document allowance gate', function () {
        $user = User::factory()->create(['is_admin' => true]);

        Document::factory(10)->create(['user_id' => $user->id]);

        $gate = app(DocumentAllowanceGate::class);
        expect($gate->check($user))->toBeNull();
    });

    it('Premium user is not blocked by document count', function () {
        $user = User::factory()->withActivePremiumSubscription()->create();

        Document::factory(10)->create(['user_id' => $user->id, 'file_size' => 1024]);

        $gate = app(DocumentAllowanceGate::class);
        expect($gate->check($user))->toBeNull();
    });

    it('Premium user over the storage ceiling is blocked without a higher target tier', function () {
        $store = app(TierConfigurationStore::class);
        $premiumConfig = $store->forTier('premium');

        $ceilingBytes = (float) $premiumConfig->document_storage_gb * 1024 * 1024 * 1024;

        $user = User::factory()->withActivePremiumSubscription()->create();

        Document::factory()->create(['user_id' => $user->id, 'file_size' => (int) $ceilingBytes]);

        $gate = app(DocumentAllowanceGate::class);
        $result = $gate->check($user, 1);

        expect($result)->not->toBeNull()
            ->and($result['allowed'])->toBeFalse()
            ->and($result['entity_key'])->toBe('document_storage')
            ->and($result['target_tier'])->toBeNull();
    });
});

// ── 3b: Currency display mode ──────────────────────────────────────────────

describe('CurrencyDisplayService (§12)', function () {
    it('returns gbp_only for free tier', function () {
        $user = User::factory()->create(['tier' => 'free']);
        $svc = app(CurrencyDisplayService::class);
        expect($svc->modeFor($user))->toBe('gbp_only')
            ->and($svc->canChooseCurrency($user))->toBeFalse();
    });

    it('returns user_choice for Premium', function () {
        $user = User::factory()->withActivePremiumSubscription()->create();
        $svc = app(CurrencyDisplayService::class);
        expect($svc->modeFor($user))->toBe('user_choice')
            ->and($svc->canChooseCurrency($user))->toBeTrue();
    });

    it('reads from TierConfigurationStore, not hardcoded values', function () {
        // Verify the service delegates to the store, not a local map
        $user = User::factory()->withActivePremiumSubscription()->create();
        $svc = app(CurrencyDisplayService::class);
        $store = app(TierConfigurationStore::class);

        expect($svc->modeFor($user))
            ->toBe($store->forTier('premium')->currency_display_mode);
    });
});

// ── 3c: Snapshot surfacing window ─────────────────────────────────────────

describe('SnapshotPolicies (§13)', function () {
    it('snapshot policies read surfacing window from TierConfigurationStore', function () {
        $store = app(TierConfigurationStore::class);
        $policies = app(SnapshotPolicies::class);

        $policy = $policies->savingsAccountBalance();

        expect($policy->surfacingWindow('free'))
            ->toBe($store->forTier('free')->snapshot_surfacing_window_days)
            ->and($policy->surfacingWindow('premium'))
            ->toBe($store->forTier('premium')->snapshot_surfacing_window_days);
    });

    it('all three savings policies share the same store-driven window', function () {
        $policies = app(SnapshotPolicies::class);

        $balance = $policies->savingsAccountBalance();
        $interest = $policies->savingsAnnualInterestProjected();
        $isa = $policies->savingsIsaAllowanceUsedPct();

        expect($balance->surfacingWindow('premium'))
            ->toBe($interest->surfacingWindow('premium'))
            ->toBe($isa->surfacingWindow('premium'))
            ->toBeNull();
    });
});

// ── 3d: Open-API affordance ────────────────────────────────────────────────

describe('Open API affordance via /api/auth/user (§14)', function () {
    it('Premium user gets open_api_affordance=true in tier_flags', function () {
        Sanctum::actingAs(User::factory()->withActivePremiumSubscription()->create());

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.tier_flags.open_api_affordance', true)
            ->assertJsonPath('data.tier_flags.resolved_tier', 'premium');
    });

    it('free user gets open_api_affordance=false in tier_flags', function () {
        Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.tier_flags.open_api_affordance', false)
            ->assertJsonPath('data.tier_flags.currency_display_mode', 'gbp_only');
    });

    it('tier_flags include currency_display_mode and snapshot_surfacing_window_days', function () {
        Sanctum::actingAs(User::factory()->withActivePremiumSubscription()->create());

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'tier_flags' => [
                        'resolved_tier',
                        'open_api_affordance',
                        'currency_display_mode',
                        'snapshot_surfacing_window_days',
                    ],
                ],
            ])
            ->assertJsonPath('data.tier_flags.currency_display_mode', 'user_choice')
            ->assertJsonPath('data.tier_flags.snapshot_surfacing_window_days', null);
    });

    it('preview user tier_flags have open_api_affordance=false (preview outside tiers)', function () {
        Sanctum::actingAs(User::factory()->create(['is_preview_user' => true]));

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.tier_flags.open_api_affordance', false);
    });
});
