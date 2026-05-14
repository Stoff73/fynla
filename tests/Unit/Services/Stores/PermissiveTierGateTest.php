<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\PermissiveTierGate;

it('permissive gate always allows create', function () {
    $user = User::factory()->make(['id' => 1]);
    $gate = new PermissiveTierGate;

    expect($gate->canCreate($user, 'savings_account', 999))->toBeTrue();
    expect($gate->softLimit($user, 'savings_account'))->toBeNull();
    expect($gate->hardLimit($user, 'savings_account'))->toBeNull();
});
