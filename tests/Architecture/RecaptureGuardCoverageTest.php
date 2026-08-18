<?php

declare(strict_types=1);

use App\Services\AI\Fyn\RecaptureGuard;

/**
 * SPEC-crud-handler-contract §5, Rule 20.
 *
 * Every create handler asks RecaptureGuard whether the user already has this
 * record. Sixteen of nineteen had no existence check at all before 2026-08-17
 * and duplicated silently; the way that comes back is a new handler written
 * without the call, so this test is the thing that notices.
 *
 * It also pins the other half: a handler may only name an entity type the guard
 * actually knows, because an unknown type makes `inspect()` a silent no-op.
 */
function createHandlerBodies(): array
{
    // Not app_path() — the Architecture suite runs without the framework booted.
    $lines = file(__DIR__.'/../../app/Agents/CoordinatingAgent.php', FILE_IGNORE_NEW_LINES);
    $starts = [];
    foreach ($lines as $i => $line) {
        if (preg_match('/function (handleCreate\w+)\(/', $line, $m) === 1) {
            $starts[] = [$i, $m[1]];
        }
    }
    $starts[] = [count($lines), 'EOF'];

    $bodies = [];
    for ($n = 0; $n < count($starts) - 1; $n++) {
        [$from, $name] = $starts[$n];
        $bodies[$name] = implode("\n", array_slice($lines, $from, $starts[$n + 1][0] - $from));
    }

    return $bodies;
}

it('routes every create handler through the shared recapture guard', function (): void {
    $missing = array_keys(array_filter(
        createHandlerBodies(),
        fn (string $body): bool => ! str_contains($body, 'guardRecapture('),
    ));

    expect($missing)->toBe(
        [],
        'These create handlers can duplicate a record the user already has. '
        .'Add a $this->guardRecapture(...) call once the payload is built.',
    );
});

it('only guards entity types the registry knows', function (): void {
    $known = (new ReflectionClass(RecaptureGuard::class))->getConstant('ENTITIES');

    $used = [];
    foreach (createHandlerBodies() as $body) {
        preg_match_all("/guardRecapture\('([a-z_]+)'/", $body, $matches);
        $used = array_merge($used, $matches[1]);
    }

    expect(array_values(array_diff(array_unique($used), array_keys($known))))->toBe(
        [],
        'An entity type with no registry entry makes the guard a silent no-op.',
    );
    expect($used)->not->toBeEmpty();
});
