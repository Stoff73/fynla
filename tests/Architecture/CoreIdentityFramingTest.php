<?php

declare(strict_types=1);

/**
 * S0.13 — INV-2.10.1.
 *
 * `CoreIdentity::get` must NOT frame Fyn as a regulated professional. The
 * Sprint 0 rewrite drops the "qualified financial planner" framing and
 * relabels Fyn as a guidance tool that signposts to a real adviser.
 */
$root = realpath(__DIR__.'/../../');
$coreIdentityPath = $root.'/app/Services/AI/Prompts/CoreIdentity.php';

it('CoreIdentity does not frame Fyn as a regulated professional', function () use ($coreIdentityPath): void {
    $src = (string) file_get_contents($coreIdentityPath);
    $lower = strtolower($src);

    expect($lower)->not->toContain('qualified financial planner')
        ->and($lower)->not->toContain('authorised adviser')
        ->and($lower)->not->toContain('regulated adviser')
        ->and($lower)->not->toContain('think like a qualified');
});

it('CoreIdentity frames Fyn as a guidance tool', function () use ($coreIdentityPath): void {
    $src = (string) file_get_contents($coreIdentityPath);
    $lower = strtolower($src);

    // Must explicitly carve out personalised regulated advice.
    expect($lower)->toContain('do not give personalised regulated financial advice');
});
