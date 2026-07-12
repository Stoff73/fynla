<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

$root = dirname(__DIR__, 2);

putenv('APP_ENV=e2e');
$_ENV['APP_ENV'] = 'e2e';
$_SERVER['APP_ENV'] = 'e2e';

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('e2e')) {
    fwrite(STDERR, "Application did not boot in the e2e environment.\n");
    exit(1);
}

foreach (['e2e.verification-code', 'e2e.user'] as $route) {
    if (! Route::has($route)) {
        fwrite(STDERR, "Missing route: {$route}\n");
        exit(1);
    }
}

fwrite(STDOUT, "E2E routes registered.\n");
