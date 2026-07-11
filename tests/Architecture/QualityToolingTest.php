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
