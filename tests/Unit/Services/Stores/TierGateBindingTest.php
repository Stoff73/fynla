<?php

declare(strict_types=1);

use App\Services\Stores\PermissiveTierGate;
use App\Services\Stores\TierGate;

it('resolves TierGate interface to PermissiveTierGate by default', function () {
    expect(app(TierGate::class))->toBeInstanceOf(PermissiveTierGate::class);
});
