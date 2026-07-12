<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

it('defines every blocking quality job', function (): void {
    $root = dirname(__DIR__, 2);
    $workflow = Yaml::parseFile($root.'/.github/workflows/quality.yml');

    expect($workflow['jobs'])->toHaveKeys([
        'lint', 'php-tests', 'frontend-tests', 'builds', 'browser-smoke',
    ])->and($workflow['on']['pull_request']['branches'])->toBe(['dev', 'main'])
        ->and($workflow['permissions']['contents'])->toBe('read')
        ->and($workflow['concurrency']['cancel-in-progress'])->toBeTrue()
        ->and($workflow['jobs']['php-tests']['strategy']['matrix']['suite'])
        ->toBe(['Architecture', 'Unit', 'Feature', 'Integration', 'Eval'])
        ->and($workflow['jobs']['php-tests']['services']['mysql']['image'])->toBe('mysql:8.0')
        ->and($workflow['jobs']['browser-smoke']['services']['mysql']['env']['MYSQL_DATABASE'])
        ->toBe('fynla_e2e_ci');

    $lint = collect($workflow['jobs']['lint']['steps'])
        ->firstWhere('run', 'bash scripts/quality/run.sh lint');
    $build = collect($workflow['jobs']['builds']['steps'])
        ->firstWhere('run', 'bash scripts/quality/run.sh build');
    $browserSteps = collect($workflow['jobs']['browser-smoke']['steps']);
    $prepare = $browserSteps->firstWhere('run', 'bash scripts/e2e/prepare.sh');
    $smoke = $browserSteps->firstWhere('run', 'npm run test:e2e:smoke');
    $artifact = collect($workflow['jobs']['browser-smoke']['steps'])
        ->firstWhere('uses', 'actions/upload-artifact@v4');
    $runs = fn (string $job): array => collect($workflow['jobs'][$job]['steps'])
        ->pluck('run')->filter()->values()->all();

    expect($lint)->not->toBeNull()
        ->and($lint['env'])->toHaveKeys(['QUALITY_BASE', 'QUALITY_HEAD', 'PR_BODY'])
        ->and($lint['env']['QUALITY_BASE'])->toBe('${{ github.event.pull_request.base.sha }}')
        ->and($lint['env']['QUALITY_HEAD'])->toBe('${{ github.event.pull_request.head.sha }}')
        ->and($build)->not->toBeNull()
        ->and($prepare['env']['E2E_DB_NAME'])->toBe('fynla_e2e_ci')
        ->and($smoke['env']['APP_ENV'])->toBe('e2e')
        ->and($smoke['env']['DB_DATABASE'])->toBe('fynla_e2e_ci')
        ->and($artifact)->not->toBeNull()
        ->and($artifact['if'])->toBe('always()')
        ->and($runs('lint'))->toContain('composer install --no-interaction --prefer-dist', 'npm ci')
        ->and($runs('php-tests'))->toContain('composer install --no-interaction --prefer-dist')
        ->and($runs('frontend-tests'))->toContain('npm ci')
        ->and($runs('builds'))->toContain('npm ci')
        ->and($runs('browser-smoke'))->toContain(
            'composer install --no-interaction --prefer-dist',
            'npm ci',
        );
});

it('runs full browser and dependency checks nightly', function (): void {
    $root = dirname(__DIR__, 2);
    $workflow = Yaml::parseFile($root.'/.github/workflows/nightly.yml');

    expect($workflow['jobs'])->toHaveKeys(['full-browser', 'dependency-audit'])
        ->and($workflow['on']['schedule'][0]['cron'])->toBe('30 2 * * *')
        ->and($workflow['on'])->toHaveKey('workflow_dispatch');

    $browserSteps = collect($workflow['jobs']['full-browser']['steps']);
    $auditSteps = collect($workflow['jobs']['dependency-audit']['steps']);
    $browserCheckout = $browserSteps->firstWhere('uses', 'actions/checkout@v4');
    $auditCheckout = $auditSteps->firstWhere('uses', 'actions/checkout@v4');
    $browserRuns = $browserSteps->pluck('run')->filter()->values();
    $auditRuns = $auditSteps->pluck('run')->filter()->values();

    expect($workflow['permissions']['contents'])->toBe('read')
        ->and($workflow['concurrency']['cancel-in-progress'])->toBeFalse()
        ->and($workflow['env']['DB_DATABASE'])->toBe('fynla_e2e_ci')
        ->and($browserCheckout['with']['ref'])->toBe('dev')
        ->and($auditCheckout['with']['ref'])->toBe('dev')
        ->and($browserSteps->firstWhere('run', 'npm run test:e2e:full'))->not->toBeNull()
        ->and($browserSteps->firstWhere('uses', 'actions/upload-artifact@v4')['if'])->toBe('always()')
        ->and($browserRuns)->toContain('composer install --no-interaction --prefer-dist', 'npm ci')
        ->and($auditRuns)->toContain('composer install --no-interaction --prefer-dist', 'npm ci')
        ->and($auditSteps->firstWhere('run', 'composer audit'))->not->toBeNull()
        ->and($auditSteps->firstWhere('run', 'npm audit --omit=dev'))->not->toBeNull();
});
