<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\AdviceFyn;
use App\Services\AI\AiToolDefinitions;
use App\Services\AI\XaiToolDefinitions;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Auto-enumerate every persistent-write tool a provider exposes.
 *
 * Catches new write-shaped tools the moment they're defined — no hand-edit of
 * a fixture required. Anything matching the create_/update_/delete_/capture_/
 * set_ convention is treated as a write, except the two handoff signals
 * (delegate_to_capture is ALLOWED in advice mode and routes writes to
 * Onboarding Fyn; capture_complete is ALSO allowed because it's a meta-handoff
 * signal for closing onboarding turns, not a record write).
 *
 * @return list<string>
 */
function adviceFynWriteTools(string $provider): array
{
    $defs = $provider === 'xai'
        ? app(XaiToolDefinitions::class)
        : app(AiToolDefinitions::class);

    $tools = $defs->getTools(false);
    $names = array_filter(array_map(
        fn (array $t) => $t['name'] ?? ($t['function']['name'] ?? null),
        $tools,
    ));

    $handoffExceptions = ['delegate_to_capture', 'capture_complete'];

    return array_values(array_filter(
        $names,
        fn (string $name) => ! in_array($name, $handoffExceptions, true)
            && preg_match('/^(create_|update_|delete_|capture_|set_)/', $name) === 1,
    ));
}

it('AdviceFyn tool list excludes every DB-mutating tool on Anthropic', function (): void {
    cache()->forever('ai_provider', 'anthropic');
    $writeTools = adviceFynWriteTools('anthropic');
    expect($writeTools)->not->toBeEmpty(); // sanity — auto-enumeration is finding tools

    $user = User::factory()->create();
    $tools = app(AdviceFyn::class)->buildToolList($user);
    expect(array_intersect($tools, $writeTools))->toBeEmpty();
});

it('AdviceFyn tool list excludes every DB-mutating tool on xAI', function (): void {
    cache()->forever('ai_provider', 'xai');
    $writeTools = adviceFynWriteTools('xai');
    expect($writeTools)->not->toBeEmpty();

    $user = User::factory()->create();
    $tools = app(AdviceFyn::class)->buildToolList($user);
    expect(array_intersect($tools, $writeTools))->toBeEmpty();
});

it('AdviceFyn tool list exposes delegate_to_capture so the LLM can hand off writes', function (): void {
    cache()->forever('ai_provider', 'anthropic');
    $user = User::factory()->create();
    $tools = app(AdviceFyn::class)->buildToolList($user);
    expect($tools)->toContain('delegate_to_capture');
});

it('AdviceFyn tool list exposes delegate_to_capture on xAI as well', function (): void {
    cache()->forever('ai_provider', 'xai');
    $user = User::factory()->create();
    $tools = app(AdviceFyn::class)->buildToolList($user);
    expect($tools)->toContain('delegate_to_capture');
});
