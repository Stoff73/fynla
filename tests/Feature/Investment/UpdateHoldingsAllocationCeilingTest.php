<?php

declare(strict_types=1);

use App\Http\Requests\StoreInvestmentAccountRequest;
use Illuminate\Support\Facades\Validator;

/**
 * W-0321 — the 100% ceiling applied on create and not on update.
 *
 * `StoreInvestmentAccountRequest` summed the allocations and refused a total
 * above 100. `UpdateInvestmentAccountRequest` carried the per-holding `max:100`
 * and nothing summing them — so an account created at 100% could be pushed past
 * it by an edit. Create refused what update accepted, for the same account and
 * the same numbers.
 *
 * The rule is now static and shared. These drive it directly, because the defect
 * was that one caller never invoked it at all.
 */
it('refuses a total above 100 per cent', function () {
    $validator = Validator::make([], []);

    StoreInvestmentAccountRequest::validateHoldingsAllocation($validator, [
        ['allocation_percent' => 70],
        ['allocation_percent' => 40],
    ]);

    expect($validator->errors()->has('holdings'))->toBeTrue();
});

it('allows exactly 100, which is the normal fully-allocated account', function () {
    $validator = Validator::make([], []);

    StoreInvestmentAccountRequest::validateHoldingsAllocation($validator, [
        ['allocation_percent' => 70],
        ['allocation_percent' => 30],
    ]);

    expect($validator->errors()->has('holdings'))->toBeFalse();
});

it('says nothing when the request carries no holdings at all', function () {
    // A partial update - a provider rename, say - must not be refused for
    // failing to re-state an allocation it never touched.
    $validator = Validator::make([], []);

    StoreInvestmentAccountRequest::validateHoldingsAllocation($validator, null);

    expect($validator->errors()->has('holdings'))->toBeFalse();
});
