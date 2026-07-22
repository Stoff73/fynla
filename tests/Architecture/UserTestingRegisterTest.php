<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

it('registers every reviewed user testing finding with an accountable disposition', function (): void {
    $root = dirname(__DIR__, 2);
    $register = Yaml::parseFile($root.'/docs/online-readiness/user-testing-register.yaml');
    $findings = collect($register['findings'] ?? []);

    expect($register['source']['revision'] ?? null)
        ->toBe('ALtnJHwFove9vFBwb0swbbomT3l2IUyrgLUqXvszSAgI9qHVaJJdph8g1KRHT650bTEim3GQn5FZgo1rSHjagf0G5cgTrS_zbZJZ7QOHhCk')
        ->and($findings)->toHaveCount(25)
        ->and($findings->pluck('id')->unique())->toHaveCount(25)
        ->and($findings->pluck('id')->sort()->values()->all())->toBe(
            collect(range(1, 25))->map(fn (int $id): string => sprintf('UT-%02d', $id))->all()
        );

    foreach ($findings as $finding) {
        expect(array_keys($finding))->toContain(
            'id', 'source_revision', 'disposition', 'programme_tasks', 'status', 'tests', 'evidence'
        );
        expect($finding['source_revision'])->toBe($register['source']['revision'])
            ->and($finding['disposition'])->toBeIn([
                'prelaunch_fix',
                'prelaunch_regression',
                'prelaunch_clarity',
                'continuation_decision',
            ])
            ->and($finding['programme_tasks'])->not->toBeEmpty($finding['id'])
            ->and($finding['status'])->toBeIn(['open', 'in_progress', 'green', 'deferred'])
            ->and($finding['tests'])->toBeArray()
            ->and($finding['evidence'])->toBeArray();

        if ($finding['status'] === 'green') {
            expect($finding['tests'])->not->toBeEmpty($finding['id'])
                ->and($finding['evidence'])->not->toBeEmpty($finding['id']);
        }

        if ($finding['status'] === 'deferred') {
            expect($finding['id'])->toBe('UT-03');
        }
    }
});
