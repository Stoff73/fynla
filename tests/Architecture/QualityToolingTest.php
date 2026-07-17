<?php

declare(strict_types=1);

it('exposes canonical quality scripts', function (): void {
    $root = dirname(__DIR__, 2);
    $package = json_decode(
        file_get_contents($root.'/package.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );
    $composer = json_decode(
        file_get_contents($root.'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );
    $lock = json_decode(
        file_get_contents($root.'/package-lock.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect($package['scripts'])->toHaveKeys([
        'lint', 'lint:policy', 'test:frontend', 'test:e2e:smoke', 'test:e2e:full',
    ]);
    expect($composer['scripts'])->toHaveKeys(['lint:php', 'quality']);

    foreach (['eslint', '@eslint/js', 'eslint-plugin-vue', 'globals'] as $dependency) {
        expect($lock['packages']['']['devDependencies'][$dependency] ?? null)
            ->toBe($package['devDependencies'][$dependency], $dependency);
    }

    foreach ([
        'eslint.config.js',
        'scripts/quality/eslint-changed.mjs',
        'scripts/quality/php-syntax.sh',
        'scripts/quality/pint-changed.sh',
        'scripts/quality/policy-lint.sh',
        'scripts/quality/check-mobile-impact.mjs',
    ] as $path) {
        expect(file_exists($root.'/'.$path))->toBeTrue($path);
    }
});

it('defines every quality lane and pull request evidence field', function (): void {
    $root = dirname(__DIR__, 2);
    $runner = file_get_contents($root.'/scripts/quality/run.sh');
    $template = file_get_contents($root.'/.github/pull_request_template.md');

    expect($runner)->not->toBeFalse()
        ->and($template)->not->toBeFalse();

    foreach (['lint', 'php', 'frontend', 'build', 'browser:smoke', 'browser:full', 'all'] as $lane) {
        expect($runner)->toContain($lane.')');
    }

    expect($template)->toContain(
        'Mobile impact:',
        'Desktop browser evidence:',
        '/m browser evidence:',
    );
});

it('runs every chromium-labelled Playwright project with installed Google Chrome', function (): void {
    $root = dirname(__DIR__, 2);
    $config = file_get_contents($root.'/playwright.config.js');

    expect($config)->not->toBeFalse()
        ->and($config)->toContain("process.env.PLAYWRIGHT_CHROME_CHANNEL || 'chrome'")
        ->and(substr_count($config, 'channel: chromeChannel'))->toBe(2);
});
