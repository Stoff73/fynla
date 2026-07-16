<?php

declare(strict_types=1);

it('tracks every native migration capability with valid surface statuses', function (): void {
    $path = dirname(__DIR__, 2).'/docs/architecture/client-parity-ledger.md';

    expect(file_exists($path))->toBeTrue();

    $ledger = file_get_contents($path);

    expect($ledger)->not->toBeFalse();

    foreach ([
        'Register and verify',
        'Login, verification and multi-factor authentication',
        'Free/Premium entitlement',
        'Dashboard and gamification',
        'Fyn onboarding/advice/write handoff',
        'Income/expenditure/net worth',
        'Savings/investment',
        'Retirement/protection',
        'Estate/goals',
        'Tax Strategy/Holistic Plan',
        'Face ID',
        'StoreKit purchase',
        'Account deletion outcome',
    ] as $capability) {
        expect($ledger)->toContain("| {$capability} |");
    }

    foreach (['required', 'not-landed', 'not-applicable', 'green'] as $status) {
        expect($ledger)->toContain("`{$status}`");
    }

    $rows = collect(preg_split('/\R/', $ledger))
        ->filter(fn (string $line): bool => preg_match('/^\| (?!Capability |---)/', $line) === 1)
        ->map(function (string $line): array {
            $cells = explode('|', trim($line));
            array_shift($cells);
            array_pop($cells);

            return array_map('trim', $cells);
        });

    expect($rows)->toHaveCount(13);

    $allowedStatuses = ['required', 'not-landed', 'not-applicable', 'green'];

    foreach ($rows as $row) {
        expect($row)->toHaveCount(10)
            ->and($row[1])->toBeIn($allowedStatuses)
            ->and($row[2])->toBeIn($allowedStatuses)
            ->and($row[3])->toBeIn($allowedStatuses);

        if (in_array('green', array_slice($row, 1, 3), true)) {
            expect($row[6])->not->toBe('')
                ->and($row[7])->not->toBe('');
        }
    }
});
