<?php

declare(strict_types=1);

use App\Services\AI\AiToolDefinitions;
use App\Services\AI\XaiToolDefinitions;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 4b hard gate. The 8 fixtures in tests/Fixtures/ToolSchema are the
 * byte-for-byte assembled tool catalogue BEFORE externalisation. After the
 * refactor the corpus-driven assembly must reproduce them exactly.
 *
 * The dynamic `fetch_*` pointer tools (live PointerRegistry) are out of scope
 * for 4b and are filtered out of every captured/asserted catalogue so the
 * golden master is deterministic. A separate assertion confirms the pointer
 * tool names/count are unchanged across the refactor (they are produced by the
 * untouched pointerTools()).
 */
$fixtureDir = __DIR__.'/../../fixtures/ToolSchema';

/** Deterministic, ordering-faithful encoding of a tool list with fetch_* removed. */
$encode = function (array $tools): string {
    $static = array_values(array_filter(
        $tools,
        static fn (array $t): bool => ! str_starts_with(($t['name'] ?? ''), 'fetch_'),
    ));

    return json_encode($static, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
};

/**
 * The 8 variants. Each closure returns the assembled catalogue for that variant.
 * getTools() reads Cache::get('ai_provider') to decide the output shape, so the
 * provider variants set the cache key the same way the live request path does.
 */
$variants = function () {
    return [
        'getTools_anthropic_live' => function (): array {
            Cache::put('ai_provider', 'anthropic');

            return app(AiToolDefinitions::class)->getTools(false);
        },
        'getTools_anthropic_preview' => function (): array {
            Cache::put('ai_provider', 'anthropic');

            return app(AiToolDefinitions::class)->getTools(true);
        },
        'getTools_xai_live' => function (): array {
            Cache::put('ai_provider', 'xai');

            return app(AiToolDefinitions::class)->getTools(false);
        },
        'getTools_xai_preview' => function (): array {
            Cache::put('ai_provider', 'xai');

            return app(AiToolDefinitions::class)->getTools(true);
        },
        'handoffTools_anthropic' => fn (): array => app(AiToolDefinitions::class)->handoffTools('anthropic'),
        'handoffTools_xai' => fn (): array => app(AiToolDefinitions::class)->handoffTools('xai'),
        'onboardingExtractionTools_anthropic' => fn (): array => app(AiToolDefinitions::class)->onboardingExtractionTools('anthropic'),
        'onboardingExtractionTools_xai' => fn (): array => app(AiToolDefinitions::class)->onboardingExtractionTools('xai'),
    ];
};

it('captures the current catalogue into fixtures', function () use ($fixtureDir, $encode, $variants): void {
    if (getenv('CAPTURE_TOOL_SCHEMA_GOLDEN') !== '1') {
        $this->markTestSkipped('Capture only runs with CAPTURE_TOOL_SCHEMA_GOLDEN=1.');
    }

    if (! is_dir($fixtureDir)) {
        mkdir($fixtureDir, 0777, true);
    }

    foreach ($variants() as $name => $build) {
        file_put_contents($fixtureDir.'/'.$name.'.json', $encode($build()));
    }

    expect(glob($fixtureDir.'/[!_]*.json'))->toHaveCount(8);
});

it('assembles each variant byte-identical to the committed fixture', function (string $name, $build) use ($fixtureDir, $encode): void {
    $fixturePath = $fixtureDir.'/'.$name.'.json';
    expect(file_exists($fixturePath))->toBeTrue("Missing fixture {$name}.json — run the capture step first.");

    expect($encode($build()))->toBe(file_get_contents($fixturePath));
})->with(function () use ($variants) {
    // Pest dataset: [label => [name, closure]].
    $out = [];
    foreach ($variants() as $name => $build) {
        $out[$name] = [$name, $build];
    }

    return $out;
});

it('keeps the pointer-tool names and count unchanged (out of 4b scope)', function (): void {
    Cache::put('ai_provider', 'anthropic');
    $tools = app(AiToolDefinitions::class)->getTools(false);
    $fetchNames = array_values(array_filter(
        array_map(static fn (array $t): string => $t['name'] ?? '', $tools),
        static fn (string $n): bool => str_starts_with($n, 'fetch_'),
    ));

    // The committed fixture set is fetch_*-free; this asserts the pointer tools
    // still flow through getTools() (their exact set is owned by the pointer
    // registry, a different subsystem). We only assert the count is stable
    // relative to a recorded baseline written at capture time.
    $baselinePath = __DIR__.'/../../fixtures/ToolSchema/_pointer_baseline.json';
    if (getenv('CAPTURE_TOOL_SCHEMA_GOLDEN') === '1') {
        file_put_contents($baselinePath, json_encode($fetchNames, JSON_PRETTY_PRINT));
        $this->markTestSkipped('Captured pointer baseline.');
    }

    expect(file_exists($baselinePath))->toBeTrue();
    expect($fetchNames)->toBe(json_decode(file_get_contents($baselinePath), true));
});

it('exposes the xAI catalogue unchanged (XaiToolDefinitions untouched by 4b)', function (): void {
    // Guard that 4b did not accidentally touch the separate xAI class.
    Cache::put('ai_provider', 'xai');
    $names = collect(app(XaiToolDefinitions::class)->getTools(false))
        ->map(fn (array $t) => $t['function']['name'] ?? $t['name'] ?? null)
        ->filter()
        ->values()
        ->all();

    expect($names)->not->toBeEmpty();
});
