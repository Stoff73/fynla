<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

it('keeps and registers the complete July Updates corpus on the dev line', function (): void {
    $root = dirname(__DIR__, 2);
    $register = Yaml::parseFile($root.'/docs/online-readiness/july-plan-register.yaml');
    $registered = collect($register['artifacts'])->pluck('path')->sort()->values();

    $actual = collect([
        'July/July1Updates', 'July/July3Updates', 'July/July4Updates',
        'July/July5Updates', 'July/July6Updates', 'July/July7Updates',
    ])->flatMap(function (string $directory) use ($root) {
        if (! is_dir($root.'/'.$directory)) {
            return [];
        }

        return iterator_to_array(
            (new Finder)->files()->in($root.'/'.$directory)->getIterator(),
            false
        );
    })
        ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['md', 'patch', 'jpeg', 'png'], true))
        ->map(fn ($file) => str_replace($root.DIRECTORY_SEPARATOR, '', $file->getRealPath()))
        ->sort()
        ->values();

    expect($register['source']['ref'])->toBe('origin/main')
        ->and($register['source']['commit'])->toBe('2e8357bef1c453da40e2c1991a462d8914b262e5')
        ->and($registered)->toHaveCount(34)
        ->and($registered->all())->toBe($actual->all());

    foreach ($register['artifacts'] as $artifact) {
        expect(array_keys($artifact))->toContain(
            'path', 'kind', 'disposition', 'source_blob', 'programme_tasks', 'proof'
        );
        expect($artifact['source_blob'])->toMatch('/^[0-9a-f]{40}$/');
        expect($artifact['disposition'])->toBeIn([
            'delivered', 'launch_remediation', 'continuation', 'evidence_only', 'superseded',
        ]);

        if (in_array($artifact['disposition'], ['launch_remediation', 'continuation'], true)) {
            expect($artifact['programme_tasks'])->not->toBeEmpty($artifact['path']);
        }

        if ($artifact['disposition'] === 'delivered') {
            expect($artifact['proof'])->not->toBeEmpty($artifact['path']);
        }
    }
});

it('maps every executable July work package to programme tasks', function (): void {
    $root = dirname(__DIR__, 2);
    $register = Yaml::parseFile($root.'/docs/online-readiness/july-plan-register.yaml');
    $workPackages = collect($register['work_packages'] ?? []);
    $registeredPaths = collect($register['artifacts'])->pluck('path');

    expect($register['work_packages'] ?? null)->toBeArray()
        ->and($workPackages)->toHaveCount(123)
        ->and($workPackages->pluck('id')->unique())->toHaveCount(123);

    foreach ($workPackages as $workPackage) {
        expect(array_keys($workPackage))->toContain(
            'id', 'source_path', 'source_heading', 'disposition', 'programme_tasks', 'proof'
        );
        $source = file_get_contents($root.'/'.$workPackage['source_path']);

        expect($source)->not->toBeFalse()
            ->and($source)->toContain($workPackage['source_heading']);
        expect($workPackage['programme_tasks'])->not->toBeEmpty($workPackage['id'])
            ->and($registeredPaths)->toContain($workPackage['source_path']);
    }
});

it('requires actionable fields on every launch finding', function (): void {
    $root = dirname(__DIR__, 2);
    $ledger = Yaml::parseFile($root.'/docs/online-readiness/audit-ledger.yaml');
    $findings = collect($ledger['findings'] ?? []);

    expect($ledger['findings'])->toBeArray()
        ->and($findings)->toHaveCount(95)
        ->and($findings->pluck('id')->unique())->toHaveCount($findings->count());

    foreach (['FAA' => 8, 'BS' => 52, 'FYN' => 21, 'REG' => 14] as $family => $count) {
        expect($findings->filter(
            fn (array $finding): bool => str_starts_with($finding['id'], $family.'-')
        ))->toHaveCount($count);
    }

    expect($findings->pluck('id'))->toContain(
        'REG-SAVETAX',
        'REG-WP1', 'REG-WP2', 'REG-WP3', 'REG-WP4', 'REG-WP5', 'REG-WP6',
        'REG-WP5C-I', 'REG-WP5C-II', 'REG-WP5C-III',
        'REG-PENSIONCHECK', 'REG-PR612', 'REG-PR613', 'REG-PR614',
    );

    foreach ($findings as $finding) {
        expect(array_keys($finding))->toContain(
            'id', 'source', 'severity', 'title', 'workstream',
            'task', 'status', 'tests', 'evidence'
        );
        expect($finding['status'])->toBeIn(['open', 'in_progress', 'green', 'inapplicable', 'deferred']);

        if (in_array($finding['severity'], ['critical', 'high'], true)) {
            expect($finding['status'])->not->toBe('deferred');
        }
    }
});

it('captures the release anchors and deployment surfaces', function (): void {
    $root = dirname(__DIR__, 2);
    $manifest = file_get_contents($root.'/docs/online-readiness/release-manifest.md');

    expect($manifest)->not->toBeFalse();

    foreach ([
        '## Branch anchors',
        '## Release difference',
        '## Migrations',
        '## Runtime surfaces',
        '## Deployment state',
    ] as $heading) {
        expect($manifest)->toContain($heading);
    }
});
