<?php

declare(strict_types=1);

use App\Services\Account\RetentionPurgeService;
use Illuminate\Support\Facades\Schema;

it('every table in deletion order has a user_id column', function () {
    $svc = app(RetentionPurgeService::class);
    $reflection = new ReflectionClass($svc);
    $method = $reflection->getMethod('getDeletionOrder');
    $method->setAccessible(true);
    $order = $method->invoke($svc);

    foreach ($order as $table) {
        expect(Schema::hasTable($table))
            ->toBeTrue("table {$table} from getDeletionOrder must exist");
        expect(Schema::hasColumn($table, 'user_id'))
            ->toBeTrue("table {$table} from getDeletionOrder must have user_id column");
    }
});
