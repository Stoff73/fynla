<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Support\SharedOwnership;
use Illuminate\Contracts\Validation\Validator;

/**
 * Refuses a stated ownership share that is not a shared split (W-0040).
 *
 * Lives here rather than under App\Http\Requests because everything in that
 * namespace must extend FormRequest and be suffixed `Request` — an expectation
 * whose whole point is to stop non-request classes accumulating in a request
 * namespace. A shared trait is exactly what it excludes, and App\Http\Traits is
 * where this codebase already keeps HTTP-layer traits.
 *
 * The one place the refusal is written. Every asset form request that accepts
 * `ownership_percentage` calls this, so the rule cannot come to mean different
 * things on savings and on property — which is how the app arrived at refusing
 * a stated `0` while silently rewriting a stated `100` to `50`.
 *
 * Says nothing when the caller stated no share: that case is a default, not an
 * assertion, and `SharedOwnership` resolves it downstream. A form with no share
 * input therefore never sees this rule — which is every form today.
 */
trait ValidatesSharedOwnership
{
    protected function validateSharedOwnershipSplit(
        Validator $validator,
        ?string $ownershipType,
        mixed $statedShare
    ): void {
        if (! SharedOwnership::isShared($ownershipType)) {
            return;
        }

        if (SharedOwnership::statedShare($statedShare) === null) {
            return;
        }

        if (SharedOwnership::isValidSharedSplit($statedShare)) {
            return;
        }

        $validator->errors()->add(
            'ownership_percentage',
            'A shared asset is split between two owners, so your share must be above 0% and below 100%. If you own all of it, choose individual ownership.'
        );
    }
}
