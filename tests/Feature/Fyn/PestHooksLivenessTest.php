<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Models\TaxConfiguration;
use App\Services\AI\XaiClient;
use Tests\Support\Fyn\ScriptedAnthropicClient;
use Tests\Support\Fyn\ScriptedXaiClient;

/*
|--------------------------------------------------------------------------
| Pest.php global hook liveness pins
|--------------------------------------------------------------------------
|
| These tests pin the two Pest.php global `uses()->beforeEach(...)->in(...)`
| hooks — if either test fails, the corresponding hook has rotted (see PR #536
| for how that happened invisibly last time: the hooks were registered via the
| bare `beforeEach(closure)->in(...)` form, which silently never executes).
|
| This file deliberately lives in tests/Feature/Fyn/ because that directory is
| inside BOTH hooks' ->in() coverage ('Feature' for the TaxConfiguration
| safety net, 'Feature/Fyn' for the scripted AI-client binding), so one file
| pins both. Neither test seeds, binds, or arranges anything itself — passing
| is only possible if the hooks executed.
|
| Do not add a beforeEach to this file, and do not move it out of
| tests/Feature/Fyn/ without re-checking the ->in() lists in tests/Pest.php.
*/

// This test pins the Pest.php global beforeEach hook — if it fails, the hook
// has rotted (see PR #536 for how that happened invisibly last time). It
// asserts an active TaxConfiguration EXISTS without seeding anything itself:
// under RefreshDatabase the test database starts empty, so the row can only
// have been created by the hook.
it('pins the global TaxConfiguration safety-net hook as live', function () {
    expect(TaxConfiguration::where('is_active', true)->exists())->toBeTrue();

    // The hook pins the safety-net row to 2019/20 (outside the factory's
    // random year range and the seeder's years) — assert the shape so a
    // future edit that changes the hook's collision-avoidance contract
    // surfaces here rather than as random unique-index flakes.
    expect(TaxConfiguration::where('is_active', true)->value('tax_year'))->toBe('2019/20');
});

// This test pins the Pest.php global scripted-AI-client hook (the network
// guard) — if it fails, the hook has rotted (see PR #536). It asserts the
// container resolves the empty scripted clients the hook installs, without
// any per-test setup: nothing else binds these instances, so resolving them
// proves the hook executed.
it('pins the global scripted AI-client binding hook as live', function () {
    expect(app(Client::class))->toBeInstanceOf(ScriptedAnthropicClient::class);
    expect(app(XaiClient::class))->toBeInstanceOf(ScriptedXaiClient::class);
});
