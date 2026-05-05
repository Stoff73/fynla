<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Estate\Gift;
use App\Models\Estate\Trust;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers PRD FR-M15 (F5) — Trust CLT orphan prevention.
 *
 * Pre-S0.5.l: handleCreateTrust returned fill_form and the user-facing form
 * persisted the Trust. The CLT Gift was written by TrustObserver::created
 * when (and only when) the Trust row was actually saved.
 *
 * Post-S0.5.l (commit 17ea6df): handleCreateTrust direct-writes the Trust
 * inside DB::transaction. The created event still fires the same observer,
 * so the CLT Gift is written exactly once at persistence time. The
 * orphan-prevention contract is preserved by routing the write through the
 * model layer instead of writing the Gift speculatively from the agent.
 *
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md §FR-M15
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

describe('TrustObserver::created (FR-M15)', function () {
    it('auto-creates a CLT Gift when a Trust with initial_value > 0 is saved', function () {
        $user = User::factory()->create();

        $trust = Trust::create([
            'user_id' => $user->id,
            'trust_name' => 'Chen Family Trust',
            'trust_type' => 'discretionary',
            'trust_creation_date' => '2026-04-20',
            'initial_value' => 250000,
            'current_value' => 250000,
            'settlor' => $user->first_name.' '.$user->surname,
        ]);

        $gift = Gift::where('user_id', $user->id)
            ->where('recipient', 'Chen Family Trust')
            ->first();

        expect($gift)->not->toBeNull()
            ->and($gift->gift_type)->toBe('clt')
            ->and((float) $gift->gift_value)->toBe(250000.0)
            ->and($gift->gift_date->format('Y-m-d'))->toBe('2026-04-20');
    });

    it('does NOT create a CLT Gift when initial_value is zero', function () {
        $user = User::factory()->create();

        Trust::create([
            'user_id' => $user->id,
            'trust_name' => 'Empty Shell Trust',
            'trust_type' => 'bare',
            'trust_creation_date' => '2026-04-20',
            'initial_value' => 0,
            'current_value' => 0,
        ]);

        expect(Gift::where('user_id', $user->id)->exists())->toBeFalse();
    });

});

describe('CoordinatingAgent::handleCreateTrust (post-S0.5.l direct-write)', function () {
    it('persists the Trust and the matching CLT Gift in one transaction when initial_value > 0', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        $result = app(CoordinatingAgent::class)->executeTool('create_trust', [
            'trust_name' => 'Direct-Write Trust',
            'trust_type' => 'discretionary',
            'initial_value' => 500000,
            'trust_creation_date' => '2026-04-20',
        ], $user);

        expect($result['success'] ?? false)->toBeTrue()
            ->and($result['created'] ?? false)->toBeTrue()
            ->and($result['entity_type'] ?? null)->toBe('trust');

        $trust = Trust::where('user_id', $user->id)->first();
        expect($trust)->not->toBeNull()
            ->and($trust->trust_name)->toBe('Direct-Write Trust')
            ->and((float) $trust->initial_value)->toBe(500000.0);

        // Observer-driven CLT Gift — the agent does not write it directly.
        $gift = Gift::where('user_id', $user->id)
            ->where('recipient', 'Direct-Write Trust')
            ->first();
        expect($gift)->not->toBeNull()
            ->and($gift->gift_type)->toBe('clt')
            ->and((float) $gift->gift_value)->toBe(500000.0)
            ->and($gift->gift_date->format('Y-m-d'))->toBe('2026-04-20');

        // Success message surfaces the CLT to the user.
        expect($result['message'] ?? '')->toContain('Chargeable Lifetime Transfer');
    });

    it('does not write a CLT Gift when initial_value is zero', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        $result = app(CoordinatingAgent::class)->executeTool('create_trust', [
            'trust_name' => 'Empty Shell',
            'trust_type' => 'bare',
            'initial_value' => 0,
            'trust_creation_date' => '2026-04-20',
        ], $user);

        expect($result['success'] ?? false)->toBeTrue();
        expect(Trust::where('user_id', $user->id)->exists())->toBeTrue()
            ->and(Gift::where('user_id', $user->id)->exists())->toBeFalse();

        // CLT mention should be absent from the success message when no
        // chargeable transfer was recorded.
        expect($result['message'] ?? '')->not->toContain('Chargeable Lifetime Transfer');
    });

    it('writes nothing on validation failure (no Trust, no Gift)', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        $result = app(CoordinatingAgent::class)->executeTool('create_trust', [
            // trust_name omitted → validation fails.
            'trust_type' => 'discretionary',
            'initial_value' => 250000,
        ], $user);

        expect($result['error'] ?? null)->toBeTrue()
            ->and($result['error_type'] ?? null)->toBe('validation_failed');
        expect(Trust::where('user_id', $user->id)->exists())->toBeFalse()
            ->and(Gift::where('user_id', $user->id)->exists())->toBeFalse();
    });

    it('blocks preview users without persisting Trust or Gift', function () {
        $user = User::factory()->create(['is_preview_user' => true]);

        $result = app(CoordinatingAgent::class)->executeTool('create_trust', [
            'trust_name' => 'Preview Trust',
            'trust_type' => 'discretionary',
            'initial_value' => 250000,
            'trust_creation_date' => '2026-04-20',
        ], $user);

        expect($result['blocked'] ?? false)->toBeTrue();
        expect(Trust::where('user_id', $user->id)->exists())->toBeFalse()
            ->and(Gift::where('user_id', $user->id)->exists())->toBeFalse();
    });
});
