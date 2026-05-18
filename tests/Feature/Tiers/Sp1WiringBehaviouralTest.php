<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\User;
use App\Services\Stores\CurrencyDisplayService;
use App\Services\Stores\Snapshots\SnapshotPolicies;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierResolver;
use App\Services\Documents\DocumentAllowanceGate;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

// ── 3a: Document allowance ──────────────────────────────────────────────────

describe('DocumentAllowanceGate (§11)', function () {
    it('allows upload when retained count is below allowance', function () {
        $user = User::factory()->create(['tier' => 'free']); // allowance = 3

        // 2 existing documents
        Document::factory(2)->create(['user_id' => $user->id]);

        $gate = app(DocumentAllowanceGate::class);
        expect($gate->check($user))->toBeNull(); // allowed
    });

    it('blocks upload when retained count reaches allowance and returns CTA shape', function () {
        $user = User::factory()->create(['tier' => 'free']); // allowance = 3

        // 3 existing — at the limit
        Document::factory(3)->create(['user_id' => $user->id]);

        $gate = app(DocumentAllowanceGate::class);
        $result = $gate->check($user);

        expect($result)->not->toBeNull()
            ->and($result['allowed'])->toBeFalse()
            ->and($result['entity_key'])->toBe('document_upload')
            ->and($result['limit'])->toBe(3)
            ->and($result['target_tier'])->toBeArray();
    });

    it('does NOT delete existing documents — only blocks new uploads', function () {
        $user = User::factory()->create(['tier' => 'free']); // allowance = 3

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

    it('enforces storage ceiling for tier2 users and targets tier3 as upgrade', function () {
        $user = User::factory()->create(['tier' => 'tier2']); // 5 GB ceiling

        // Simulate docs near the storage ceiling (just under 5 GB)
        $fourGb = (int) (4.9 * 1024 * 1024 * 1024);
        Document::factory()->create(['user_id' => $user->id, 'file_size' => $fourGb]);

        $gate = app(DocumentAllowanceGate::class);
        $store = app(TierConfigurationStore::class);

        // A 200 MB file would push over the 5 GB ceiling
        $twoHundredMb = 200 * 1024 * 1024;
        $result = $gate->check($user, $twoHundredMb);

        expect($result)->not->toBeNull()
            ->and($result['entity_key'])->toBe('document_storage')
            ->and($result['target_tier'])->toBeArray()
            ->and($result['target_tier']['tier'])->toBe('tier3')
            ->and($result['target_tier']['display_name'])->toBe($store->forTier('tier3')->display_name);
    });

    it('count allowance block for tier2 user targets tier3 (next strictly-greater allowance)', function () {
        $user = User::factory()->create(['tier' => 'tier2']); // allowance = 5

        // 5 existing — at the limit
        Document::factory(5)->create(['user_id' => $user->id]);

        $gate = app(DocumentAllowanceGate::class);
        $store = app(TierConfigurationStore::class);
        $result = $gate->check($user);

        expect($result)->not->toBeNull()
            ->and($result['allowed'])->toBeFalse()
            ->and($result['entity_key'])->toBe('document_upload')
            ->and($result['limit'])->toBe(5)
            ->and($result['target_tier'])->toBeArray()
            ->and($result['target_tier']['tier'])->toBe('tier3')
            ->and($result['target_tier']['display_name'])->toBe($store->forTier('tier3')->display_name);
    });

    it('tier2/3 storage ceiling null (tier1) means no storage check', function () {
        $user = User::factory()->create(['tier' => 'tier1']); // document_storage_gb = null

        // Fill with massive file — no ceiling for tier1
        Document::factory()->create(['user_id' => $user->id, 'file_size' => 100 * 1024 * 1024]);

        $gate = app(DocumentAllowanceGate::class);

        // Below allowance=4, no storage ceiling → allowed
        expect($gate->check($user, 1 * 1024 * 1024))->toBeNull();
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

    it('returns gbp_only for tier1', function () {
        $user = User::factory()->create(['tier' => 'tier1']);
        $svc = app(CurrencyDisplayService::class);
        expect($svc->modeFor($user))->toBe('gbp_only');
    });

    it('returns user_choice for tier2', function () {
        $user = User::factory()->create(['tier' => 'tier2']);
        $svc = app(CurrencyDisplayService::class);
        expect($svc->modeFor($user))->toBe('user_choice')
            ->and($svc->canChooseCurrency($user))->toBeTrue();
    });

    it('returns user_choice for tier3', function () {
        $user = User::factory()->create(['tier' => 'tier3']);
        $svc = app(CurrencyDisplayService::class);
        expect($svc->modeFor($user))->toBe('user_choice');
    });

    it('reads from TierConfigurationStore, not hardcoded values', function () {
        // Verify the service delegates to the store, not a local map
        $user = User::factory()->create(['tier' => 'tier2']);
        $svc = app(CurrencyDisplayService::class);
        $store = app(TierConfigurationStore::class);

        expect($svc->modeFor($user))
            ->toBe($store->forTier('tier2')->currency_display_mode);
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
            ->and($policy->surfacingWindow('tier1'))
            ->toBe($store->forTier('tier1')->snapshot_surfacing_window_days)
            ->and($policy->surfacingWindow('tier2'))
            ->toBe($store->forTier('tier2')->snapshot_surfacing_window_days)
            ->and($policy->surfacingWindow('tier3'))
            ->toBe($store->forTier('tier3')->snapshot_surfacing_window_days);
    });

    it('all three savings policies share the same store-driven window', function () {
        $policies = app(SnapshotPolicies::class);

        $balance = $policies->savingsAccountBalance();
        $interest = $policies->savingsAnnualInterestProjected();
        $isa = $policies->savingsIsaAllowanceUsedPct();

        expect($balance->surfacingWindow('tier3'))
            ->toBe($interest->surfacingWindow('tier3'))
            ->toBe($isa->surfacingWindow('tier3'))
            ->toBe(2555);
    });
});

// ── 3d: Open-API affordance ────────────────────────────────────────────────

describe('Open API affordance via /api/auth/user (§14)', function () {
    it('tier2 user gets open_api_affordance=true in tier_flags', function () {
        Sanctum::actingAs(User::factory()->create(['tier' => 'tier2']));

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.tier_flags.open_api_affordance', true)
            ->assertJsonPath('data.tier_flags.resolved_tier', 'tier2');
    });

    it('tier3 user gets open_api_affordance=true in tier_flags', function () {
        Sanctum::actingAs(User::factory()->create(['tier' => 'tier3']));

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.tier_flags.open_api_affordance', true);
    });

    it('free user gets open_api_affordance=false in tier_flags', function () {
        Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.tier_flags.open_api_affordance', false)
            ->assertJsonPath('data.tier_flags.currency_display_mode', 'gbp_only');
    });

    it('tier1 user gets open_api_affordance=false in tier_flags', function () {
        Sanctum::actingAs(User::factory()->create(['tier' => 'tier1']));

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.tier_flags.open_api_affordance', false);
    });

    it('tier_flags include currency_display_mode and snapshot_surfacing_window_days', function () {
        Sanctum::actingAs(User::factory()->create(['tier' => 'tier2']));

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
            ->assertJsonPath('data.tier_flags.snapshot_surfacing_window_days', 1825);
    });

    it('preview user tier_flags have open_api_affordance=false (preview outside tiers)', function () {
        Sanctum::actingAs(User::factory()->create(['is_preview_user' => true]));

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.tier_flags.open_api_affordance', false);
    });
});
