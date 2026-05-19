<?php

declare(strict_types=1);

use App\Services\Stores\TierGate;
use App\Services\Tiers\DbTierGate;

it('resolves the TierGate interface to DbTierGate (SP2 PR3 global binding)', function () {
    expect(app(TierGate::class))->toBeInstanceOf(DbTierGate::class);
});
