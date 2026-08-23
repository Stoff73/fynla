<?php

declare(strict_types=1);

namespace App\Constants;

use App\Http\Controllers\Api\Retirement\DCPensionHoldingsController;
use App\Http\Requests\Investment\StoreHoldingRequest;
use App\Http\Requests\Investment\UpdateHoldingRequest;

/**
 * The one home for a holding's fund sub-type vocabulary.
 *
 * It existed as a private `getSubTypes()` method on BOTH
 * `Investment\StoreHoldingRequest` and `Investment\UpdateHoldingRequest`, with
 * identical bodies — and `DCPensionHoldingsController` had no rule for the
 * column at all, so a fund type chosen on a pension holding was validated by
 * the client, dropped by `validated()` and reported as saved.
 *
 * Adding a third copy to fix that is what Rule 20 names as the violation rather
 * than the fix, so the vocabulary moved here and all three read it. The select
 * in `HoldingForm.vue` is the fourth consumer and mirrors this list; it is a UI
 * mirror of a server-owned vocabulary, in the same way `ownership.js` mirrors
 * `CalculatesOwnershipShare`.
 *
 * @see StoreHoldingRequest
 * @see UpdateHoldingRequest
 * @see DCPensionHoldingsController
 */
final class HoldingSubTypes
{
    /**
     * Every value `holdings.sub_type` accepts.
     *
     * @var list<string>
     */
    public const ALL = [
        'equity_fund',
        'bond_fund',
        'mixed_fund',
        'income_fund',
        'index_fund',
        'money_market_fund',
        'property_fund',
    ];
}
