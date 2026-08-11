<?php

declare(strict_types=1);

it('tracks the complete iOS and mobile web parity closure contract', function (): void {
    $path = dirname(__DIR__, 2).'/docs/architecture/client-parity-ledger.md';

    expect(file_exists($path))->toBeTrue();

    $ledger = file_get_contents($path);

    expect($ledger)->not->toBeFalse()
        ->and($ledger)->toContain('<!-- PARITY-CLOSURE-START -->')
        ->and($ledger)->toContain('<!-- PARITY-CLOSURE-END -->');

    preg_match(
        '/<!-- PARITY-CLOSURE-START -->(.*)<!-- PARITY-CLOSURE-END -->/s',
        (string) $ledger,
        $matches,
    );
    $closure = $matches[1] ?? '';

    $rows = collect(preg_split('/\R/', $closure))
        ->filter(fn (string $line): bool => preg_match('/^\| M-\d{2} \|/', $line) === 1)
        ->map(function (string $line): array {
            $cells = explode('|', trim($line));
            array_shift($cells);
            array_pop($cells);

            return array_map('trim', $cells);
        })
        ->values();

    expect($rows)->toHaveCount(34);

    $expectedIds = collect(range(1, 34))
        ->map(fn (int $number): string => sprintf('M-%02d', $number))
        ->all();

    expect($rows->pluck(0)->all())->toBe($expectedIds);

    preg_match_all(
        '/^\| ([A-Z][A-Z0-9-]+) \| [^|]+ \| `([^`]+)` \|$/m',
        (string) $ledger,
        $registryMatches,
        PREG_SET_ORDER,
    );
    $evidenceRegistry = collect($registryMatches)
        ->mapWithKeys(fn (array $match): array => [$match[1] => $match[2]]);

    expect($evidenceRegistry)->toHaveCount(14);
    foreach ($evidenceRegistry as $key => $evidencePath) {
        expect(file_exists(dirname(__DIR__, 2).'/'.$evidencePath))
            ->toBeTrue("Evidence key {$key} points to missing path {$evidencePath}");
    }

    foreach ($rows as $row) {
        expect($row)->toHaveCount(8)
            ->and($row[1])->not->toBe('')
            ->and($row[2])->not->toBe('')
            ->and($row[3])->not->toBe('')
            ->and($row[4])->not->toBe('')
            ->and($row[5])->not->toBe('')
            ->and($row[6])->not->toBe('')
            ->and($row[7])->toBeIn(['pending-ci', 'green']);

        foreach (['L-', 'M-', 'I-', 'U-', 'E-'] as $surfacePrefix) {
            $surfaceKeys = collect(explode(' ', $row[6]))
                ->filter(fn (string $key): bool => str_starts_with($key, $surfacePrefix));

            expect($surfaceKeys)->not->toBeEmpty(
                "{$row[0]} has no {$surfacePrefix} evidence key"
            );
            foreach ($surfaceKeys as $key) {
                expect($evidenceRegistry->has($key))
                    ->toBeTrue("{$row[0]} references unknown evidence key {$key}");
            }
        }
    }

    $statuses = $rows->pluck(7)->unique()->values();
    expect($statuses)->toHaveCount(1);

    $evidence = file_get_contents(
        dirname(__DIR__, 2).'/docs/superpowers/evidence/2026-08-11-pr7-ios-m-parity-closure.md'
    );
    expect($evidence)->not->toBeFalse();

    if ($statuses->first() === 'pending-ci') {
        expect($evidence)->toContain('local CoreSimulator host block');
    } else {
        expect($evidence)->toContain('IOS-CI-GREEN:');
    }

    expect($ledger)
        ->toContain('Laravel rehydrates existing financial facts from canonical records')
        ->toContain('Clients send identifiers and proposed changes, never authoritative balances')
        ->toContain('One canonical portfolio exposure and drift method')
        ->toContain('Recorded history never contains projected values')
        ->toContain('Semantic destinations are allowlisted')
        ->toContain('Unknown or unauthorised resources')
        ->not->toContain('`required`')
        ->not->toContain('`not-landed`');
});
