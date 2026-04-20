<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Estate\Gift;
use App\Models\Estate\Trust;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers PRD FR-M15 (F5) — Trust CLT orphan prevention.
 *
 * The pre-fix behaviour: handleCreateTrust wrote the CLT Gift directly
 * before returning fill_form, so if the user cancelled the trust form,
 * the Gift remained orphaned and IHT calculations double-counted the
 * unsaved settlement.
 *
 * Post-fix: handleCreateTrust returns fill_form only. The CLT Gift is
 * written by TrustObserver::created when (and only when) the Trust row
 * is actually saved.
 *
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md §FR-M15
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
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

describe('CoordinatingAgent::handleCreateTrust (FR-M15 shift)', function () {
    it('does not write a CLT Gift when returning fill_form (prevents orphan on form cancel)', function () {
        $user = User::factory()->create();
        $agent = app(CoordinatingAgent::class);
        $reflection = new ReflectionMethod($agent, 'handleCreateTrust');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($agent, [
            'trust_name' => 'Pre-Form Trust',
            'trust_type' => 'discretionary',
            'initial_value' => 500000,
            'trust_creation_date' => '2026-04-20',
        ], $user, false);

        expect($result['action'] ?? null)->toBe('fill_form')
            ->and($result['entity_type'] ?? null)->toBe('trust');

        // No Trust saved yet (form not submitted), and therefore no CLT Gift.
        expect(Trust::where('user_id', $user->id)->exists())->toBeFalse()
            ->and(Gift::where('user_id', $user->id)->exists())->toBeFalse();
    });
});
