<?php

declare(strict_types=1);

use App\Http\Controllers\TestSupport\E2EController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Process\Process;

it('does not register E2E support routes outside the e2e environment', function (): void {
    expect(app()->environment('e2e'))->toBeFalse()
        ->and(Route::has('e2e.verification-code'))->toBeFalse()
        ->and(Route::has('e2e.user'))->toBeFalse();
});

it('rejects direct controller access outside the e2e environment', function (string $action): void {
    $controller = app(E2EController::class);
    $request = Request::create('/__e2e/'.$action, 'GET', [
        'email' => 'person@example.com',
    ]);

    expect(fn () => $controller->{$action}($request))
        ->toThrow(NotFoundHttpException::class);
})->with(['verificationCode', 'user']);

it('registers E2E support routes only when the application boots in e2e', function (): void {
    $root = dirname(__DIR__, 3);
    $process = new Process(
        [PHP_BINARY, $root.'/tests/Helpers/assert-e2e-routes.php'],
        $root,
        ['APP_ENV' => 'e2e'],
    );
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});

it('refuses unsafe E2E database names before invoking MySQL', function (string $database): void {
    $root = dirname(__DIR__, 3);
    $process = new Process(
        ['bash', $root.'/scripts/e2e/prepare.sh'],
        $root,
        ['E2E_DB_NAME' => $database],
    );
    $process->run();

    expect($process->getExitCode())->toBe(64)
        ->and($process->getErrorOutput())->toContain('Refusing non-E2E database name');
})->with([
    'normal application database' => 'laravel',
    'shell and SQL metacharacters' => 'laravel`;DROP_DATABASE_e2e',
]);

it('bypasses cached configuration and verifies Laravel database resolution', function (string $script): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/scripts/e2e/'.$script);

    expect($contents)->toContain('APP_CONFIG_CACHE')
        ->and($contents)->toContain('resolved_database')
        ->and($contents)->toContain('Laravel resolved a non-E2E database');
})->with(['prepare.sh', 'serve.sh']);

it('serves the e2e application on the host trusted by Laravel', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/scripts/e2e/serve.sh');

    expect($contents)->toContain('APP_URL=http://127.0.0.1:8000');
});

it('isolates the route cache used by the e2e application', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/scripts/e2e/serve.sh');

    expect($contents)->toContain('APP_ROUTES_CACHE');
});

it('builds an isolated local-base mobile bundle before the e2e server starts', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/scripts/e2e/serve.sh');

    expect($contents)->toContain('VITE_ROUTER_BASE=/')
        ->and($contents)->toContain('VITE_MOBILE_BASE_PATH=/m-e2e-build/')
        ->and($contents)->toContain('VITE_MOBILE_OUT_DIR=public/m-e2e-build')
        ->and($contents)->toContain('npm run build:mobile');
});

it('probes a Vite URL that exists in Laravel middleware mode', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/playwright.config.js');

    expect($contents)->toContain("url: 'http://127.0.0.1:5173/@vite/client'");
});

it('rejects unknown E2E server targets', function (): void {
    $root = dirname(__DIR__, 3);
    $process = new Process(['bash', $root.'/scripts/e2e/serve.sh', 'unknown'], $root);
    $process->run();

    expect($process->getExitCode())->toBe(64)
        ->and($process->getErrorOutput())->toContain('Unknown E2E server target');
});
