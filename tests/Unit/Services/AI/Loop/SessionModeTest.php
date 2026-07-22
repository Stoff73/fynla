<?php

declare(strict_types=1);

use App\Services\AI\Actions\SurfaceAllowlist;
use App\Services\AI\Loop\SessionMode;

/*
 * CoALA Phase 5 item 4 — SessionMode.
 *
 * The first-class working-memory field the shared FynLoop is keyed on
 * (plan line 434/497: "session_mode (advice | onboarding)"). It bridges the
 * loop's mode to the two existing string vocabularies it must drive:
 *   - the persona tag on ai_messages / HasAiChat::personaOverride
 *     ('advice' | 'data_capture'), and
 *   - the SurfaceAllowlist gate key (the read-only mode is 'advice').
 */

it('exposes the two canonical session modes', function () {
    expect(SessionMode::Advice->value)->toBe('advice')
        ->and(SessionMode::Onboarding->value)->toBe('onboarding');
});

it('maps each mode to its ai_messages persona tag', function () {
    expect(SessionMode::Advice->persona())->toBe('advice')
        ->and(SessionMode::Onboarding->persona())->toBe('data_capture');
});

it('reports which mode is read-only', function () {
    expect(SessionMode::Advice->isReadOnly())->toBeTrue()
        ->and(SessionMode::Onboarding->isReadOnly())->toBeFalse();
});

it('drives the SurfaceAllowlist gate key — only advice denies write surfaces', function () {
    $allowlist = app(SurfaceAllowlist::class);

    expect($allowlist->allows(SessionMode::Advice->persona(), 'create_savings_account'))->toBeFalse()
        ->and($allowlist->allows(SessionMode::Onboarding->persona(), 'create_savings_account'))->toBeTrue();
});
