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

it('rejects unknown E2E server targets', function (): void {
    $root = dirname(__DIR__, 3);
    $process = new Process(['bash', $root.'/scripts/e2e/serve.sh', 'unknown'], $root);
    $process->run();

    expect($process->getExitCode())->toBe(64)
        ->and($process->getErrorOutput())->toContain('Unknown E2E server target');
});
