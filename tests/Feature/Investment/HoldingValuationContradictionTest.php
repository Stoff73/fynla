<?php

declare(strict_types=1);

use App\Http\Requests\Investment\StoreHoldingRequest;
use Illuminate\Support\Facades\Validator;

/**
 * W-0127 — units, price and value may not contradict each other.
 *
 * `HoldingValuation::reconcile()` resolves the three by precedence: where units
 * are supplied they win and the value follows. That is right for a FORM, where a
 * user edits one field and expects the others to follow.
 *
 * It is wrong for an IMPORT, which supplies all three at once from a statement.
 * There a value disagreeing with units × price is not a stale derived figure —
 * it is a contradiction in the source data, and silently overwriting it discards
 * the only evidence the import was wrong. The user then reconciles against their
 * platform, sees a figure Fynla computed rather than the one their provider
 * sent, and has no way to tell.
 */
function holdingValuation(array $payload): \Illuminate\Contracts\Validation\Validator
{
    $request = StoreHoldingRequest::create('/', 'POST', $payload);
    $validator = Validator::make($payload, []);

    $request->setContainer(app())->validateResolved ?? null;
    (function () use ($validator) {
        $this->validateHoldingValuationAgrees($validator);
    })->call($request);

    return $validator;
}

it('refuses a value that contradicts units times price', function () {
    // 100 × £10 is £1,000, not £5,000. One of the three is wrong and the import
    // is the only thing that knows which.
    $validator = holdingValuation([
        'quantity' => 100,
        'current_price' => 10,
        'current_value' => 5000,
    ]);

    expect($validator->errors()->has('current_value'))->toBeTrue()
        ->and($validator->errors()->first('current_value'))->toContain('1,000.00');
});

it('accepts figures that agree', function () {
    expect(holdingValuation([
        'quantity' => 100,
        'current_price' => 10,
        'current_value' => 1000,
    ])->errors()->has('current_value'))->toBeFalse();
});

it('tolerates a provider rounding to the penny', function () {
    // Rounding in a provider's own export is ordinary. Failing on it would
    // refuse good data, which is a worse defect than the one this closes.
    expect(holdingValuation([
        'quantity' => 33.333333,
        'current_price' => 3,
        'current_value' => 100.01,
    ])->errors()->has('current_value'))->toBeFalse();
});

it('says nothing when only two of the three are supplied', function () {
    // The form case. `reconcile()` derives the third, and there is no
    // contradiction to find because nothing was asserted twice.
    expect(holdingValuation([
        'quantity' => 100,
        'current_price' => 10,
    ])->errors()->has('current_value'))->toBeFalse();

    expect(holdingValuation([
        'current_value' => 5000,
    ])->errors()->has('current_value'))->toBeFalse();
});
