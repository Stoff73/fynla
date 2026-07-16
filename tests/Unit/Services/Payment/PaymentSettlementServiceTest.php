<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Services\Payment\DiscountCodeService;
use App\Services\Payment\PaymentSettlementService;
use App\Services\Stores\TierConfigurationStore;
use Illuminate\Support\Facades\DB;

it('gives the entitlement transaction three attempts for transient deadlocks', function () {
    $database = DB::getFacadeRoot();
    $payment = Payment::factory()->make();
    $fakeDatabase = Mockery::mock();
    $fakeDatabase->shouldReceive('transaction')
        ->once()
        ->with(Mockery::type(Closure::class), 3)
        ->andReturn($payment);

    DB::swap($fakeDatabase);

    try {
        $service = new PaymentSettlementService(
            Mockery::mock(TierConfigurationStore::class),
            Mockery::mock(DiscountCodeService::class),
        );

        expect($service->settle($payment, []))->toBe($payment);
    } finally {
        DB::swap($database);
    }
});
