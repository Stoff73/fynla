<?php

declare(strict_types=1);

use App\Services\AI\XaiToolDefinitions;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 4b-xai hard gate. The fixtures in tests/Fixtures/XaiToolSchema are the
 * byte-for-byte assembled xAI tool catalogue BEFORE externalisation. After the
 * refactor the corpus-driven assembly must reproduce them exactly.
 *
 * The dynamic `fetch_*` pointer tools (live PointerRegistry) are out of scope
 * and filtered out of every captured/asserted catalogue so the golden master
 * is deterministic. A separate assertion confirms the pointer tool names/count
 * are unchanged across the refactor (produced by the untouched pointerTools()).
 */
$fixtureDir = __DIR__.'/../../fixtures/XaiToolSchema';

/** Deterministic, ordering-faithful encoding of an xAI tool list with fetch_* removed. */
$encode = function (array $tools): string {
    $static = array_values(array_filter(
        $tools,
        static fn (array $t): bool => ! str_starts_with(($t['function']['name'] ?? ''), 'fetch_'),
    ));

    return json_encode($static, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
};

/**
 * The 3 variants. xAI tools are pre-wrapped; getTools() reads no cache key for
 * shape (always OpenAI function objects), so no Cache::put is needed, but we set
 * the provider cache the way the live path does for parity with pointerTools().
 */
$variants = function () {
    return [
        'getTools_xai_live' => function (): array {
            Cache::put('ai_provider', 'xai');

            return app(XaiToolDefinitions::class)->getTools(false);
        },
        'getTools_xai_preview' => function (): array {
            Cache::put('ai_provider', 'xai');

            return app(XaiToolDefinitions::class)->getTools(true);
        },
        'handoffTools_xai' => fn (): array => app(XaiToolDefinitions::class)->handoffTools(),
    ];
};

it('captures the current xAI catalogue into fixtures', function () use ($fixtureDir, $encode, $variants): void {
    if (getenv('CAPTURE_XAI_TOOL_SCHEMA_GOLDEN') !== '1') {
        $this->markTestSkipped('Capture only runs with CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1.');
    }

    if (! is_dir($fixtureDir)) {
        mkdir($fixtureDir, 0777, true);
    }

    foreach ($variants() as $name => $build) {
        file_put_contents($fixtureDir.'/'.$name.'.json', $encode($build()));
    }

    expect(glob($fixtureDir.'/[!_]*.json'))->toHaveCount(3);
});

it('assembles each xAI variant byte-identical to the committed fixture', function (string $name, $build) use ($fixtureDir, $encode): void {
    $fixturePath = $fixtureDir.'/'.$name.'.json';
    expect(file_exists($fixturePath))->toBeTrue("Missing fixture {$name}.json — run the capture step first.");

    expect($encode($build()))->toBe(file_get_contents($fixturePath));
})->with(function () use ($variants) {
    $out = [];
    foreach ($variants() as $name => $build) {
        $out[$name] = [$name, $build];
    }

    return $out;
});

it('keeps the xAI pointer-tool names and count unchanged (out of scope)', function (): void {
    Cache::put('ai_provider', 'xai');
    $tools = app(XaiToolDefinitions::class)->getTools(false);
    $fetchNames = array_values(array_filter(
        array_map(static fn (array $t): string => $t['function']['name'] ?? '', $tools),
        static fn (string $n): bool => str_starts_with($n, 'fetch_'),
    ));

    $baselinePath = __DIR__.'/../../fixtures/XaiToolSchema/_pointer_baseline.json';
    if (getenv('CAPTURE_XAI_TOOL_SCHEMA_GOLDEN') === '1') {
        file_put_contents($baselinePath, json_encode($fetchNames, JSON_PRETTY_PRINT));
        $this->markTestSkipped('Captured xAI pointer baseline.');
    }

    expect(file_exists($baselinePath))->toBeTrue();
    expect($fetchNames)->toBe(json_decode(file_get_contents($baselinePath), true));
});
